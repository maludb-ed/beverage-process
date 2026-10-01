"""Resolution of human labels to log keys: actors (name or id), entities (lot
number, batch number, PO number, recipe name...), action names and screen ids.
Ambiguity raises a ToolError that lists the candidates."""
from __future__ import annotations

import json
import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

from common import db

from .util import fail

ROLE = "activity"

ENTITY_ALIASES = {
    "po": "purchase_order", "purchase order": "purchase_order", "purchase_orders": "purchase_order",
    "receipt": "goods_receipt", "delivery": "goods_receipt", "goods receipt": "goods_receipt", "gr": "goods_receipt",
    "recipe": "recipe_version", "recipe version": "recipe_version",
    "work order": "production_order", "wo": "production_order", "production order": "production_order",
    "count": "count", "inventory count": "count", "cycle count": "count",
    "adjustment": "adjustment", "inventory adjustment": "adjustment",
    "packaging run": "packaging_run", "press run": "press_run", "finished lot": "finished_lot",
    "tank": "vessel", "vessel": "vessel", "report": "period_report", "ttb report": "period_report",
    "packaging configuration": "packaging_configuration", "packaging config": "packaging_configuration",
    "approval": "product_approval", "label approval": "product_approval", "reading": "reading", "lab reading": "reading",
}

MANIFEST = Path(__file__).resolve().parents[2] / "config" / "manifest.json"


def norm_entity_type(value: str | None) -> str | None:
    if not value:
        return None
    v = value.strip().lower()
    v2 = v.replace("-", " ").replace("_", " ")
    if v2 in ENTITY_ALIASES:
        return ENTITY_ALIASES[v2]
    if v2.endswith("s") and v2[:-1] in ENTITY_ALIASES:
        return ENTITY_ALIASES[v2[:-1]]
    v = v.replace(" ", "_").replace("-", "_")
    return v[:-1] if v.endswith("s") and not v.endswith("ss") else v


@dataclass
class Actor:
    actor_id: int | None
    label: str                      # display name, or the actor_label for non-user actors
    labels: list[str] = field(default_factory=list)   # actor_label values to match when actor_id is None

    def sql(self, alias: str = "a") -> tuple[str, dict[str, Any]]:
        if self.actor_id is not None:
            return f"{alias}.actor_id = %(actor_id)s", {"actor_id": self.actor_id}
        return f"{alias}.actor_label = ANY(%(actor_labels)s)", {"actor_labels": self.labels}

    def out(self) -> dict[str, Any]:
        return {"actor_id": self.actor_id, "name": self.label}


async def actor(value: str) -> Actor:
    """A user id, 'user/5', a display name (or fragment), or a non-user actor label
    such as 'system/ingest' or 'mcp/token:nightly'."""
    text = value.strip()
    m = re.fullmatch(r"(?:user/)?(\d+)(?:\s.*)?", text)
    if m:
        row = await db.fetch_one(ROLE, "SELECT id, display_name FROM app.users WHERE id = %s", (int(m.group(1)),))
        if row is None:
            fail(f"No user has id {m.group(1)}. Pass a person's name instead, e.g. actor='Ed'.")
        return Actor(row["id"], row["display_name"])
    users = await db.fetch_all(ROLE, """
        SELECT id, display_name, role, status, similarity(display_name, %(q)s) AS sim
        FROM app.users
        WHERE display_name ILIKE %(like)s OR similarity(display_name, %(q)s) > 0.3
        ORDER BY (lower(display_name) = lower(%(q)s)) DESC, (display_name ILIKE %(like)s) DESC, sim DESC LIMIT 10""",
        {"q": text, "like": f"%{text}%"})
    exact = [u for u in users if u["display_name"].lower() == text.lower()]
    if len(exact) == 1:
        return Actor(exact[0]["id"], exact[0]["display_name"])
    contains = [u for u in users if text.lower() in u["display_name"].lower()]
    if len(contains) == 1:
        return Actor(contains[0]["id"], contains[0]["display_name"])
    if len(contains) > 1 or (not contains and len(users) > 1):
        cands = ", ".join(f"{u['display_name']} (id {u['id']}, {u['role']})" for u in (contains or users))
        fail(f"'{value}' matches several people: {cands}. Call again with the exact name or the id.")
    if len(users) == 1:
        return Actor(users[0]["id"], users[0]["display_name"])
    # Non-user actors: system jobs and MCP tokens.
    labels = await db.fetch_all(ROLE, """
        SELECT DISTINCT actor_label FROM app.activity_log
        WHERE actor_id IS NULL AND actor_label ILIKE %s ORDER BY actor_label LIMIT 20""", (f"%{text}%",))
    if labels:
        names = [r["actor_label"] for r in labels]
        return Actor(None, names[0] if len(names) == 1 else text, names)
    known = await db.fetch_all(ROLE, "SELECT display_name FROM app.users ORDER BY display_name LIMIT 30")
    fail(f"No person or actor matches '{value}'. Known people: {', '.join(r['display_name'] for r in known)}. "
         "Non-user actors look like 'system/...' or 'mcp/token:<name>'.")
    raise AssertionError


@dataclass
class Entity:
    pairs: list[tuple[str, int | None]]      # (entity_type, entity_id); id None = label-only entity
    label: str | None

    def sql(self, alias: str = "a") -> tuple[str, dict[str, Any]]:
        clauses, params = [], {}
        for i, (etype, eid) in enumerate(self.pairs):
            if eid is None:
                clauses.append(f"({alias}.entity_type = %(et{i})s AND {alias}.entity_label = %(el{i})s)")
                params[f"el{i}"] = self.label
            else:
                clauses.append(f"({alias}.entity_type = %(et{i})s AND {alias}.entity_id = %(ei{i})s)")
                params[f"ei{i}"] = eid
            params[f"et{i}"] = etype
        return "(" + " OR ".join(clauses) + ")", params

    def out(self) -> dict[str, Any]:
        return {"label": self.label, "records": [{"entity_type": t, "entity_id": i} for t, i in self.pairs]}


async def entity(entity_type: str | None, entity_id: int | None, label: str | None) -> Entity:
    """Resolve a record from (type, id) or from its human label as recorded in the
    log (lot number, batch number, PO number, receipt number, recipe name, ...).
    An exact label shared by several record types (a lot and its finished lot) is
    kept as one entity; fuzzy matches with several labels return candidates."""
    etype = norm_entity_type(entity_type)
    if entity_id is not None:
        if not etype:
            fail("entity_id needs entity_type too (e.g. entity_type='batch', entity_id=6), or pass the label instead.")
        row = await db.fetch_one(ROLE, """
            SELECT entity_label FROM app.activity_log WHERE entity_type = %s AND entity_id = %s
            ORDER BY occurred_at DESC LIMIT 1""", (etype, entity_id))
        if row is None:
            await _check_type(etype)
            fail(f"No activity is recorded for {etype} {entity_id}. Check the id, or search by label.")
        return Entity([(etype, entity_id)], row["entity_label"])
    if not label:
        fail("Name the record: pass entity (its label, e.g. 'L-261001-001', 'B-26-004', 'PO-00001', 'Hill Dry Cider v2') "
             "or entity_type with entity_id.")
    text = label.strip()
    type_sql = " AND entity_type = %(et)s" if etype else ""
    params = {"q": text, "like": f"%{text}%", "et": etype}
    rows = await db.fetch_all(ROLE, f"""
        SELECT entity_type, entity_id, entity_label, count(*) AS events, max(occurred_at) AS last_at
        FROM app.activity_log
        WHERE entity_type IS NOT NULL AND lower(entity_label) = lower(%(q)s){type_sql}
        GROUP BY 1, 2, 3 ORDER BY events DESC""", params)
    if rows:
        return Entity([(r["entity_type"], r["entity_id"]) for r in rows], rows[0]["entity_label"])
    rows = await db.fetch_all(ROLE, f"""
        SELECT entity_type, entity_id, entity_label, count(*) AS events,
               max(similarity(entity_label, %(q)s)) AS sim
        FROM app.activity_log
        WHERE entity_type IS NOT NULL AND entity_label IS NOT NULL{type_sql}
          AND (entity_label ILIKE %(like)s OR similarity(entity_label, %(q)s) > 0.35)
        GROUP BY 1, 2, 3 ORDER BY (entity_label ILIKE %(like)s) DESC, sim DESC, events DESC LIMIT 12""", params)
    labels = {r["entity_label"] for r in rows}
    if len(labels) == 1:
        return Entity([(r["entity_type"], r["entity_id"]) for r in rows], rows[0]["entity_label"])
    if rows:
        cands = "; ".join(f"{r['entity_type']} '{r['entity_label']}' (id {r['entity_id']})" for r in rows)
        fail(f"'{label}' matches several records: {cands}. Call again with the exact label or entity_type and entity_id.")
    if etype:
        await _check_type(etype)
    fail(f"No activity mentions a record labelled '{label}'"
         + (f" of type {etype}" if etype else "")
         + ". Labels are document numbers and names as shown in the app (L-261001-001, B-26-004, PO-00001, GR-00001, WO-00001, CNT-00001).")
    raise AssertionError


async def _check_type(etype: str) -> None:
    rows = await db.fetch_all(ROLE, "SELECT DISTINCT entity_type FROM app.activity_log WHERE entity_type IS NOT NULL ORDER BY 1")
    types = [r["entity_type"] for r in rows]
    if etype not in types:
        fail(f"Unknown entity_type '{etype}'. Known types: {', '.join(types)}.")


async def actions(value: str) -> list[str]:
    """Exact action name, a '*' wildcard pattern (count_*), or a fragment that matches
    exactly one action ('approved' is ambiguous; 'po_approved' is not)."""
    text = value.strip().lower().replace(" ", "_").replace("-", "_")
    rows = await db.fetch_all(ROLE, "SELECT action, count(*) AS n FROM app.activity_log GROUP BY 1 ORDER BY 1")
    known = {r["action"]: r["n"] for r in rows}
    if text in known:
        return [text]
    if "*" in text:
        rx = re.compile("^" + re.escape(text).replace(r"\*", ".*") + "$")
        hits = [a for a in known if rx.match(a)]
        if hits:
            return hits
        fail(f"No action matches '{value}'.")
    hits = [a for a in known if text in a]
    if len(hits) == 1:
        return hits
    if hits:
        fail(f"'{value}' matches several actions: {', '.join(hits)}. Pick one, or use a wildcard such as '*{text}*' to include them all.")
    words = [w for w in text.split("_") if len(w) > 2]
    near = [a for a in known if any(w in a for w in words)][:25]
    fail(f"No action is named '{value}'." + (f" Close: {', '.join(near)}." if near else "")
         + " Call activity_search, or activity_sql with SELECT DISTINCT action FROM app.activity_log, to see every action name.")
    raise AssertionError


def _manifest_screens() -> dict[str, str]:
    try:
        data = json.loads(MANIFEST.read_text())
    except Exception:
        return {}
    screens = data.get("screens", data) if isinstance(data, dict) else data
    out: dict[str, str] = {}
    items = screens.values() if isinstance(screens, dict) else screens
    for s in items if isinstance(items, (list, type({}.values()))) else []:
        if isinstance(s, dict) and s.get("id"):
            out[str(s["id"])] = str(s.get("title") or s.get("label") or s.get("name") or s["id"])
    return out


async def screen(value: str) -> str:
    """A screen id (tank-board) or its words ('tank board', 'Tank board')."""
    text = value.strip().lower()
    slug = re.sub(r"[^a-z0-9]+", "-", text).strip("-")
    rows = await db.fetch_all(ROLE, "SELECT DISTINCT screen FROM app.activity_log WHERE screen IS NOT NULL")
    known = {r["screen"] for r in rows} | set(_manifest_screens())
    if slug in known:
        return slug
    labels = {sid: lab.lower() for sid, lab in _manifest_screens().items()}
    by_label = [sid for sid, lab in labels.items() if lab == text]
    if len(by_label) == 1:
        return by_label[0]
    hits = sorted(s for s in known if slug in s)
    if len(hits) == 1:
        return hits[0]
    if hits:
        fail(f"'{value}' matches several screens: {', '.join(hits[:30])}. Pass the exact screen id.")
    fail(f"No screen is called '{value}'. Screen ids look like tank-board, recipe-view, lots-list; "
         "call activity_screen_usage with group_by='screen' to see them all.")
    raise AssertionError
