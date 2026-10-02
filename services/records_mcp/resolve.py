"""Server-side label resolution: lot numbers, batch numbers, item codes or names,
suppliers, products, vessels, customers, locations, documents.

Order of preference: exact case-insensitive match, then substring (ILIKE), then
trigram similarity. One best match resolves; several at the same level raise
Ambiguous with up to ten candidates. "id:123" resolves by primary key.
"""
from __future__ import annotations

import re
from dataclasses import dataclass

from common import db

from .registry import Ambiguous, ToolFailure


@dataclass(frozen=True)
class Kind:
    noun: str
    source: str            # FROM ... (aliased t)
    keys: tuple[str, ...]  # columns matched against the label
    label: str             # SQL for the display label
    detail: str            # SQL for a short description of each candidate
    hint: str              # how to find valid values
    where: str = "true"
    id_col: str = "t.id"


KINDS: dict[str, Kind] = {
    "lot": Kind("lot", "app.lots t JOIN app.items i ON i.id = t.item_id", ("t.lot_number", "t.supplier_lot_number"),
                "t.lot_number", "i.name || ' · ' || t.quality_status || COALESCE(' · supplier lot ' || t.supplier_lot_number, '')",
                "Lot numbers look like L-261001-004; a fragment such as '261001-00' or a supplier lot number also works."),
    "batch": Kind("batch", "app.batches t JOIN app.products p ON p.id = t.product_id", ("t.number",),
                  "t.number", "p.name || ' · ' || t.status || ' · ' || t.current_stage_code",
                  "Batch numbers look like B-26-001; call batch_find to search by product or status."),
    "item": Kind("item", "app.items t", ("t.code", "t.name"), "t.code", "t.name || ' (' || t.item_class || ', ' || t.base_unit_code || ')'",
                 "Use an item code such as APL-GOLD or part of the item name."),
    "supplier": Kind("supplier", "app.suppliers t", ("t.name",), "t.name", "t.kind", "Use part of the supplier's name."),
    "product": Kind("product", "app.products t", ("t.code", "t.name"), "t.name", "t.code || ' · ' || t.status",
                    "Use a product code or part of its name; product_find lists products."),
    "vessel": Kind("vessel", "app.vessels t", ("t.name",), "t.name", "t.kind || ' · ' || t.status", "Use a vessel name such as FV-1 or Tank-3; production_tank_board lists vessels."),
    "customer": Kind("customer", "app.customers t", ("t.name",), "t.name", "t.kind", "Use part of the customer's name."),
    "location": Kind("location", "app.locations t", ("t.name",), "t.name", "t.kind || ' · ' || t.tax_state", "Use a location name such as Cold room or Cellar."),
    "premises": Kind("premises", "app.premises t", ("t.name", "t.registry_number"), "t.name", "t.kind || COALESCE(' · ' || t.registry_number, '')",
                     "Use the premises name or registry number."),
    "receipt": Kind("receipt", "app.goods_receipts t JOIN app.suppliers s ON s.id = t.supplier_id", ("t.number", "t.delivery_note_ref"), "t.number",
                    "s.name || ' · ' || t.status || ' · ' || to_char(t.received_at, 'YYYY-MM-DD')", "Receipt numbers look like GR-00001; a delivery note reference also works."),
    "po": Kind("purchase order", "app.purchase_orders t JOIN app.suppliers s ON s.id = t.supplier_id", ("t.number",), "t.number",
               "s.name || ' · ' || t.status", "Purchase order numbers look like PO-00001."),
    "order": Kind("production order", "app.production_orders t JOIN app.products p ON p.id = t.product_id", ("t.number",), "t.number",
                  "p.name || ' · ' || t.status", "Production order numbers look like WO-00001; production_orders_by_status lists them."),
    "keg": Kind("keg", "app.kegs t", ("t.serial",), "t.serial", "t.state || ' · ' || t.size_l || ' L'", "Use the keg serial; keg_fleet lists kegs."),
    "sales_order": Kind("customer order", "app.sales_orders t JOIN app.customers c ON c.id = t.customer_id", ("t.number", "t.customer_reference"), "t.number",
                        "c.name || ' · ' || t.status || ' · due ' || t.requested_on || COALESCE(' · ref ' || t.customer_reference, '')",
                        "Customer order numbers look like SO-00001; the customer's PO reference also works; orders_find lists them."),
    "standing_order": Kind("standing order", "app.standing_orders t JOIN app.customers c ON c.id = t.customer_id", ("t.number",), "t.number",
                           "c.name || ' · ' || t.frequency || CASE WHEN t.active THEN '' ELSE ' · paused' END",
                           "Standing order numbers look like STO-0001; standing_orders_find lists them."),
    "packaging_run": Kind("packaging run", "app.packaging_runs t JOIN app.batches b ON b.id = t.batch_id", ("t.number",), "t.number",
                          "b.number || ' · ' || t.status || ' · ' || t.run_on", "Packaging run numbers look like PK-00001."),
    "report": Kind("period report", "app.period_reports t", ("t.number",), "t.number", "t.form_code || ' · ' || t.period_start || ' to ' || t.period_end || ' · ' || t.status",
                   "Report numbers look like RPT-00001; call compliance_report without arguments to list them."),
    "reason": Kind("reason code", "app.reason_codes t", ("t.code", "t.name"), "t.code", "t.name || ' (' || t.applies_to || ')'",
                   "Use a reason code such as DAMAGE or COUNT, or part of its name."),
    "measurement": Kind("measurement", "app.measurement_types t", ("t.code", "t.name"), "t.code", "t.name || ' (' || t.unit || ')'",
                        "Use a measurement code such as sg, brix, ph, free_so2, total_so2, abv, co2.", id_col="t.code"),
    "stage": Kind("stage", "app.stages t", ("t.code", "t.name"), "t.code", "t.name", "Stages: pitch, primary, rack, maturation, blend, back_sweeten, carbonate, package.", id_col="t.code"),
}

_ID = re.compile(r"^id:(\d+)$", re.IGNORECASE)


def _escape_like(text: str) -> str:
    return text.replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_")


async def candidates(kind: str, label: str, limit: int = 11) -> list[dict]:
    k = KINDS[kind]
    m = _ID.match(label)
    if m:
        rows = await db.fetch_all("records", f"SELECT {k.id_col} AS id, {k.label} AS label, {k.detail} AS detail, 3 AS rank, 1.0 AS sim FROM {k.source} WHERE t.id = %(id)s",
                                  {"id": int(m.group(1))})
        return rows
    exact = " OR ".join(f"lower({c}) = lower(%(q)s)" for c in k.keys)
    like = " OR ".join(f"{c} ILIKE %(pat)s" for c in k.keys)
    sim = "greatest(" + ", ".join(f"similarity(COALESCE({c}, ''), %(q)s)" for c in k.keys) + ")" if len(k.keys) > 1 else f"similarity(COALESCE({k.keys[0]}, ''), %(q)s)"
    sql = f"""
        SELECT * FROM (
          SELECT {k.id_col} AS id, {k.label} AS label, {k.detail} AS detail,
                 CASE WHEN {exact} THEN 3 WHEN {like} THEN 2 ELSE 1 END AS rank, {sim} AS sim
            FROM {k.source}
           WHERE ({k.where}) AND (({exact}) OR ({like}) OR {sim} > 0.3)
        ) c ORDER BY rank DESC, sim DESC, label LIMIT %(limit)s"""
    return await db.fetch_all("records", sql, {"q": label, "pat": f"%{_escape_like(label)}%", "limit": limit})


async def resolve(kind: str, label: str) -> dict:
    """Return {'id', 'label', 'detail'} for one record, or raise Ambiguous / ToolFailure."""
    k = KINDS[kind]
    rows = await candidates(kind, label)
    if not rows:
        raise ToolFailure(f"No {k.noun} matched '{label}'. {k.hint}")
    top = rows[0]["rank"]
    best = [r for r in rows if r["rank"] == top]
    if len(best) == 1:
        r = best[0]
        return {"id": r["id"], "label": r["label"], "detail": r["detail"], "match": {3: "exact", 2: "partial", 1: "similar"}[top]}
    raise Ambiguous(kind, label, [{"label": r["label"], "detail": r["detail"]} for r in best[:10]],
                    f"Several {k.noun}s match '{label}'. Call again with one of these labels exactly.")


async def resolve_opt(kind: str, label: str | None) -> dict | None:
    return None if label is None or label == "" else await resolve(kind, label)


async def resolve_many(kind: str, label: str, limit: int = 50) -> list[dict]:
    """All records matching at the best level (for filters such as measurement 'so2' covering free and total)."""
    rows = await candidates(kind, label, limit)
    if not rows:
        raise ToolFailure(f"No {KINDS[kind].noun} matched '{label}'. {KINDS[kind].hint}")
    top = rows[0]["rank"]
    return [r for r in rows if r["rank"] == top]


def rid(resolved: dict | None) -> int | None:
    return None if resolved is None else resolved["id"]


def echo(**resolved: dict | None) -> dict:
    """What each label resolved to, for the caller to confirm."""
    return {k: (v["label"] + (f" ({v['detail']})" if v.get("detail") else "")) for k, v in resolved.items() if v}
