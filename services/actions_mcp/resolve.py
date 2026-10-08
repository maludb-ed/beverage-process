"""Human labels → record ids, through the read-only records role.

Matching order: numeric id; exact label (case and punctuation insensitive, so a dictated
"b 26 004" finds B-26-004); substring; trigram similarity. One clear match resolves; several
or none fail usefully with the closest candidates.
"""
from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from common import db


@dataclass(frozen=True)
class Kind:
    noun: str
    sql: str              # SELECT id, label, detail FROM ... (no WHERE; aliases: id, label, alt, detail)
    where: str = ""        # extra filter always applied
    plural: str = ""


def _k(noun: str, select: str, where: str = "", plural: str = "") -> Kind:
    return Kind(noun, select, where, plural or noun + "s")


KINDS: dict[str, Kind] = {
    "batch": _k("batch", "SELECT b.id, b.number AS label, NULL::text AS alt, p.name || ' · ' || b.status || ' · ' || b.current_stage_code AS detail FROM app.batches b JOIN app.products p ON p.id = b.product_id", plural="batches"),
    "lot": _k("lot", "SELECT l.id, l.lot_number AS label, l.supplier_lot_number AS alt, i.code || ' ' || i.name || ' · ' || l.quality_status AS detail FROM app.lots l JOIN app.items i ON i.id = l.item_id"),
    "finished_lot": _k("finished lot", "SELECT l.id, l.lot_number AS label, NULL::text AS alt, i.name || ' · ' || l.quality_status AS detail FROM app.finished_lots f JOIN app.lots l ON l.id = f.lot_id JOIN app.items i ON i.id = l.item_id"),
    "item": _k("item", "SELECT id, code AS label, name AS alt, name || ' · ' || item_class || ' · ' || base_unit_code AS detail FROM app.items", "active"),
    "supplier": _k("supplier", "SELECT id, name AS label, NULL::text AS alt, kind AS detail FROM app.suppliers", "active"),
    "customer": _k("customer", "SELECT id, name AS label, NULL::text AS alt, kind AS detail FROM app.customers", "active"),
    "purchase_order": _k("purchase order", "SELECT o.id, o.number AS label, NULL::text AS alt, s.name || ' · ' || o.status AS detail FROM app.purchase_orders o JOIN app.suppliers s ON s.id = o.supplier_id"),
    "receipt": _k("receipt", "SELECT r.id, r.number AS label, r.delivery_note_ref AS alt, s.name || ' · ' || r.status AS detail FROM app.goods_receipts r JOIN app.suppliers s ON s.id = r.supplier_id"),
    "vessel": _k("vessel", "SELECT id, name AS label, NULL::text AS alt, kind || ' · ' || status AS detail FROM app.vessels", "active"),
    "location": _k("location", "SELECT id, name AS label, kind AS alt, kind || ' · ' || tax_state AS detail FROM app.locations", "active"),
    "premises": _k("premises", "SELECT id, name AS label, registry_number AS alt, kind AS detail FROM app.premises", "active", plural="premises"),
    "product": _k("product", "SELECT id, name AS label, code AS alt, style || ' · ' || status AS detail FROM app.products"),
    "keg": _k("keg", "SELECT id, serial AS label, NULL::text AS alt, state AS detail FROM app.kegs"),
    "transfer": _k("transfer", "SELECT id, number AS label, NULL::text AS alt, status AS detail FROM app.inventory_transfers"),
    "adjustment": _k("adjustment", "SELECT id, number AS label, NULL::text AS alt, status AS detail FROM app.inventory_adjustments"),
    "count": _k("count", "SELECT c.id, c.number AS label, NULL::text AS alt, c.kind || ' · ' || c.status || ' · ' || l.name AS detail FROM app.inventory_counts c JOIN app.locations l ON l.id = c.location_id"),
    "removal": _k("removal", "SELECT r.id, r.number AS label, r.reference AS alt, r.destination_kind || ' · ' || r.status AS detail FROM app.removals r"),
    "press_run": _k("press run", "SELECT id, number AS label, NULL::text AS alt, status AS detail FROM app.press_runs"),
    "sales_order": _k("customer order", "SELECT o.id, o.number AS label, o.customer_reference AS alt, c.name || ' · ' || o.status || ' · due ' || o.requested_on AS detail FROM app.sales_orders o JOIN app.customers c ON c.id = o.customer_id"),
    "standing_order": _k("standing order", "SELECT s.id, s.number AS label, NULL::text AS alt, c.name || ' · ' || s.frequency || CASE WHEN s.active THEN '' ELSE ' · paused' END AS detail FROM app.standing_orders s JOIN app.customers c ON c.id = s.customer_id"),
    "order_import": _k("order import", "SELECT i.id, i.number AS label, NULL::text AS alt, a.file_name || ' · ' || i.status AS detail FROM app.order_imports i JOIN app.attachments a ON a.id = i.attachment_id"),
    "packaging_run": _k("packaging run", "SELECT id, number AS label, NULL::text AS alt, status AS detail FROM app.packaging_runs"),
    "production_order": _k("production order", "SELECT o.id, o.number AS label, NULL::text AS alt, p.name || ' · ' || o.status AS detail FROM app.production_orders o JOIN app.products p ON p.id = o.product_id"),
    "recipe": _k("recipe version", "SELECT r.id, p.name || ' v' || r.version_no AS label, p.code || ' v' || r.version_no AS alt, r.status AS detail FROM app.recipe_versions r JOIN app.products p ON p.id = r.product_id"),
    "packaging_config": _k("packaging configuration", "SELECT id, name AS label, package_kind AS alt, package_kind AS detail FROM app.packaging_configurations"),
    "spec": _k("spec", "SELECT s.id, p.name || ' ' || s.stage_code || ' ' || s.measurement_type_code AS label, NULL::text AS alt, coalesce(s.min_value::text, '') || '–' || coalesce(s.max_value::text, '') AS detail FROM app.specs s JOIN app.products p ON p.id = s.product_id"),
    "approval": _k("approval", "SELECT a.id, p.name || ' ' || a.kind AS label, a.reference_no AS alt, a.status AS detail FROM app.product_approvals a JOIN app.products p ON p.id = a.product_id"),
    "reason_code": _k("reason code", "SELECT id, code AS label, name AS alt, applies_to || ' · ' || classification AS detail FROM app.reason_codes", "active"),
    "ttb_report": _k("TTB report", "SELECT r.id, r.number AS label, r.period_start::text || ' to ' || r.period_end::text AS alt, r.status AS detail FROM app.period_reports r"),
    "equipment": _k("equipment", "SELECT id, name AS label, NULL::text AS alt, kind || ' · ' || status AS detail FROM app.equipment", "active", plural="equipment"),
    "reservation": _k("equipment reservation", "SELECT t.id, t.resource_name || ' · ' || COALESCE(t.subject_number, t.kind) || ' · ' || t.local_from::text AS label, t.subject_number AS alt, COALESCE(t.subject_label, t.notes, '') || ' · ' || t.role || ' · ' || t.local_from::text || ' to ' || t.local_to::text AS detail FROM app.v_equipment_schedule t"),
}

KIND_ALIASES = {
    "batches": "batch", "lots": "lot", "items": "item", "suppliers": "supplier", "customers": "customer", "po": "purchase_order",
    "purchase order": "purchase_order", "purchase_orders": "purchase_order", "receipts": "receipt", "goods_receipt": "receipt",
    "vessels": "vessel", "tank": "vessel", "tanks": "vessel", "locations": "location", "products": "product", "kegs": "keg",
    "transfers": "transfer", "adjustments": "adjustment", "counts": "count", "removals": "removal", "press run": "press_run",
    "packaging run": "packaging_run", "production order": "production_order", "finished lot": "finished_lot",
    "finished_lots": "finished_lot", "recipes": "recipe", "recipe_version": "recipe", "reason": "reason_code",
    "ttb": "ttb_report", "report": "ttb_report", "packaging configuration": "packaging_config", "packaging_configuration": "packaging_config",
    "reservations": "reservation", "booking": "reservation", "bookings": "reservation", "equipment reservation": "reservation",
}


def kind_of(name: str) -> str | None:
    key = name.strip().lower()
    key = KIND_ALIASES.get(key, key).replace(" ", "_")
    return key if key in KINDS else None


class ResolveError(Exception):
    def __init__(self, message: str, candidates: list[dict[str, Any]] | None = None):
        super().__init__(message)
        self.message = message
        self.candidates = candidates or []

    def result(self, status: str | None = None) -> dict[str, Any]:
        out: dict[str, Any] = {"status": status or ("ambiguous" if len(self.candidates) > 1 else "not_found"), "message": self.message}
        if self.candidates:
            out["candidates"] = self.candidates
        return out


def _inner(kind: str, where: str = "") -> str:
    """The kind's SELECT with its filters (they reference the underlying table aliases)."""
    k = KINDS[kind]
    filters = [f for f in (k.where, where) if f]
    return k.sql + (" WHERE " + " AND ".join(f"({f})" for f in filters) if filters else "")


def _norm_sql(expr: str) -> str:
    return f"regexp_replace(lower(coalesce({expr}, '')), '[^a-z0-9]', '', 'g')"


async def search(kind: str, query: str, *, where: str = "", params: dict[str, Any] | None = None, limit: int = 8) -> list[dict[str, Any]]:
    """Ranked candidates: rank 0 exact, 1 normalized exact, 2 substring, 3 similarity."""
    inner = _inner(kind, where)
    p = {"q": query.strip(), "n": "".join(ch for ch in query.lower() if ch.isalnum()), "lim": limit, **(params or {})}
    sql = f"""
        SELECT id, label, alt, detail, rank, sim FROM (
            SELECT rec.*,
                   CASE WHEN lower(rec.label) = lower(%(q)s) OR lower(coalesce(rec.alt, '')) = lower(%(q)s) THEN 0
                        WHEN {_norm_sql('rec.label')} = %(n)s OR ({_norm_sql('rec.alt')} = %(n)s AND %(n)s <> '') THEN 1
                        WHEN rec.label ILIKE '%%' || %(q)s || '%%' OR rec.alt ILIKE '%%' || %(q)s || '%%'
                             OR ({_norm_sql('rec.label')} LIKE '%%' || %(n)s || '%%' AND length(%(n)s) >= 3) THEN 2
                        ELSE 3 END AS rank,
                   greatest(similarity(lower(rec.label), lower(%(q)s)), similarity(lower(coalesce(rec.alt, '')), lower(%(q)s))) AS sim
            FROM ({inner}) AS rec
        ) ranked
        WHERE rank < 3 OR sim >= 0.25
        ORDER BY rank, sim DESC, label
        LIMIT %(lim)s"""
    return await db.fetch_all("records", sql, p)


def _candidate(row: dict[str, Any]) -> dict[str, Any]:
    return {"id": row["id"], "label": row["label"], "detail": row.get("detail")}


async def resolve(kind: str, query: str | int | None, *, where: str = "", params: dict[str, Any] | None = None, what: str | None = None) -> dict[str, Any]:
    """One record {id, label, detail} or ResolveError with candidates."""
    k = KINDS[kind]
    noun = what or k.noun
    if query is None or str(query).strip() == "":
        raise ResolveError(f"Which {noun}? Name it (number, code or name).")
    text = str(query).strip()
    if text.isdigit() and kind not in ("keg",):
        rows = await db.fetch_all("records", f"SELECT * FROM ({_inner(kind, where)}) rec WHERE rec.id = %(id)s", {"id": int(text), **(params or {})})
        if rows:
            return _candidate(rows[0])
    rows = await search(kind, text, where=where, params=params)
    if not rows:
        raise ResolveError(f"No {noun} matches '{text}'.")
    best = rows[0]["rank"]
    top = [r for r in rows if r["rank"] == best]
    if best <= 1 and len(top) == 1:
        return _candidate(top[0])
    if best == 2 and len(top) == 1:
        return _candidate(top[0])
    if len(top) > 1 and best <= 2:
        raise ResolveError(f"'{text}' matches several {k.plural}: " + ", ".join(r["label"] for r in top[:5]) + ". Which one?", [_candidate(r) for r in top[:8]])
    raise ResolveError(f"No {noun} is called '{text}'. Closest: " + ", ".join(r["label"] for r in rows[:5]) + ".", [_candidate(r) for r in rows[:8]])
