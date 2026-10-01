"""Queries behind each activity tool. Every function takes the validated input
model and returns a JSON-safe dict with a 'returned' row count."""
from __future__ import annotations

import json
import re
from datetime import datetime, timedelta
from typing import Any

import psycopg

from common import db, sql_guard

from . import models as m
from . import resolve
from .util import clean, fail, human_duration, iso, now, parse_when, tz, window, window_out, with_display

ROLE = "activity"

EVENT_COLS = """a.id, a.occurred_at, a.actor_id, a.actor_label, u.display_name, a.source, a.session_hash, a.action,
                a.screen, a.entity_type, a.entity_id, a.entity_label, a.before, a.after, a.details, a.memory_episode_id"""
FROM = "FROM app.activity_log a LEFT JOIN app.users u ON u.id = a.actor_id"


def event(r: dict[str, Any], payloads: bool = True) -> dict[str, Any]:
    out: dict[str, Any] = {
        "id": r["id"],
        "at": iso(r["occurred_at"]),
        "actor": r.get("display_name") or r["actor_label"],
        "actor_id": r["actor_id"],
        "source": r["source"],
        "action": r["action"],
        "screen": r["screen"],
    }
    if r.get("entity_type") or r.get("entity_label"):
        out["entity"] = {"type": r["entity_type"], "id": r["entity_id"], "label": r["entity_label"]}
    if r.get("session_hash"):
        out["session"] = r["session_hash"][:12]
    if payloads:
        for key in ("before", "after"):
            if r.get(key) is not None:
                out[key] = with_display(r[key])
        if r.get("details"):
            out["details"] = with_display(r["details"])
    if r.get("relation"):
        out["relation"] = r["relation"]
    if r.get("memory_episode_id"):
        out["episode_id"] = r["memory_episode_id"]
    return clean(out)


def _window_sql(start: datetime | None, end: datetime | None, params: dict[str, Any], col: str = "a.occurred_at") -> list[str]:
    where = []
    if start:
        where.append(f"{col} >= %(w_from)s")
        params["w_from"] = start
    if end:
        where.append(f"{col} < %(w_to)s")
        params["w_to"] = end
    return where


def _page(total: int, offset: int, returned: int) -> dict[str, Any]:
    nxt = offset + returned
    return {"total": total, "offset": offset, "returned": returned, "next_offset": nxt if nxt < total else None}


def _mentions_sql(alias: str = "a") -> str:
    # MCP tool-call rows quote labels in their arguments; they are usage, not history.
    return (f"{alias}.action <> 'mcp_tool_called' AND position(lower(%(lab)s) in lower(coalesce({alias}.after::text, '') || ' ' || coalesce({alias}.before::text, '') || ' ' "
            f"|| {alias}.details::text || ' ' || coalesce({alias}.entity_label, ''))) > 0")


# ---------------------------------------------------------------- A1 A5 A10
async def record_history(p: m.RecordHistoryInput) -> dict[str, Any]:
    ent = await resolve.entity(p.entity_type, p.entity_id, p.entity)
    start, end = window(p.date_from, p.date_to, p.period)
    direct, params = ent.sql()
    related = bool(p.include_related and ent.label and len(ent.label) >= 5)
    if related:
        params["lab"] = ent.label
    match = f"({direct} OR ({_mentions_sql()}))" if related else direct
    where = [match] + _window_sql(start, end, params)
    if not p.include_screen_entries:
        where.append("a.action <> 'screen_entered'")
    params.update(limit=p.limit, offset=p.offset)
    rows = await db.fetch_all(ROLE, f"""
        SELECT {EVENT_COLS}, CASE WHEN {direct} THEN NULL ELSE 'mentions' END AS relation, count(*) OVER () AS total
        {FROM} WHERE {' AND '.join(where)}
        ORDER BY a.occurred_at, a.id LIMIT %(limit)s OFFSET %(offset)s""", params)
    total = rows[0]["total"] if rows else 0
    return {"record": ent.out(), "window": window_out(start, end), **_page(total, p.offset, len(rows)),
            "events": [event(r) for r in rows],
            "note": None if rows else "No events in this window for the record."}


# ---------------------------------------------------------------- A2 A4 A7
async def actor_timeline(p: m.ActorTimelineInput) -> dict[str, Any]:
    act = await resolve.actor(p.actor)
    start, end = window(p.date_from, p.date_to, p.period)
    clause, params = act.sql()
    where = [clause] + _window_sql(start, end, params)
    scr = None
    if p.screen:
        scr = await resolve.screen(p.screen)
        where.append("a.screen = %(screen)s")
        params["screen"] = scr
    if p.action:
        params["actions"] = await resolve.actions(p.action)
        where.append("a.action = ANY(%(actions)s)")
    if not p.include_screen_entries:
        where.append("a.action <> 'screen_entered'")
    params.update(limit=p.limit, offset=p.offset)
    order = "ASC" if p.order == "asc" else "DESC"
    rows = await db.fetch_all(ROLE, f"""
        SELECT {EVENT_COLS}, count(*) OVER () AS total,
               min(a.occurred_at) OVER () AS first_at, max(a.occurred_at) OVER () AS last_at,
               count(*) FILTER (WHERE a.action = 'screen_entered') OVER () AS screen_entries
        {FROM} WHERE {' AND '.join(where)}
        ORDER BY a.occurred_at {order}, a.id {order} LIMIT %(limit)s OFFSET %(offset)s""", params)
    total = rows[0]["total"] if rows else 0
    summary = {"first_at": iso(rows[0]["first_at"]), "last_at": iso(rows[0]["last_at"]), "screen_entries": rows[0]["screen_entries"]} if rows else {}
    return {"actor": act.out(), "screen": scr, "window": window_out(start, end), **_page(total, p.offset, len(rows)), "summary": summary,
            "events": [event(r, p.include_payloads) for r in rows],
            "note": None if rows else f"No events by {act.label} match these filters."}


# ---------------------------------------------------------------- A1 A3 A5 A6 A8
def _trail_lateral(n: int, minutes: int) -> str:
    return f"""
        LEFT JOIN LATERAL (
            SELECT jsonb_agg(jsonb_build_object('id', t.id, 'at', t.occurred_at, 'screen', t.screen, 'record', t.entity_label) ORDER BY t.occurred_at) AS trail
            FROM (SELECT t.id, t.occurred_at, t.screen, t.entity_label FROM app.activity_log t
                  WHERE t.action = 'screen_entered' AND t.id < a.id
                    AND ((a.session_hash IS NOT NULL AND t.session_hash = a.session_hash)
                         OR (a.session_hash IS NULL AND t.actor_label = a.actor_label))
                    AND t.occurred_at <= a.occurred_at AND t.occurred_at >= a.occurred_at - interval '{int(minutes)} minutes'
                  ORDER BY t.occurred_at DESC, t.id DESC LIMIT {int(n)}) t
        ) tr ON true"""


def _fix_trail(trail: list[dict[str, Any]] | None) -> list[dict[str, Any]]:
    out = []
    for t in trail or []:
        at = t.get("at")
        if isinstance(at, str):
            try:
                at = iso(datetime.fromisoformat(at))
            except ValueError:
                pass
        out.append({**t, "at": at})
    return out


async def who_did(p: m.WhoDidInput) -> dict[str, Any]:
    actions = await resolve.actions(p.action)
    start, end = window(p.date_from, p.date_to, p.period)
    params: dict[str, Any] = {"actions": actions, "limit": p.limit, "offset": p.offset}
    where = ["a.action = ANY(%(actions)s)"] + _window_sql(start, end, params)
    ent = None
    if p.entity:
        ent = await resolve.entity(p.entity_type, None, p.entity)
        clause, ep = ent.sql()
        where.append(clause)
        params.update(ep)
    elif p.entity_type:
        params["etype"] = resolve.norm_entity_type(p.entity_type)
        where.append("a.entity_type = %(etype)s")
    act = None
    if p.actor:
        act = await resolve.actor(p.actor)
        clause, ap = act.sql()
        where.append(clause)
        params.update(ap)
    trail = _trail_lateral(p.trail_length, p.trail_minutes) if p.trail_length else ""
    rows = await db.fetch_all(ROLE, f"""
        SELECT {EVENT_COLS}, count(*) OVER () AS total{', tr.trail' if trail else ''}
        {FROM} {trail}
        WHERE {' AND '.join(where)}
        ORDER BY a.occurred_at DESC, a.id DESC LIMIT %(limit)s OFFSET %(offset)s""", params)
    total = rows[0]["total"] if rows else 0
    events = []
    for r in rows:
        e = event(r)
        if trail:
            e["screen_trail_before"] = _fix_trail(r.get("trail"))
        events.append(e)
    return {"actions": actions, "record": ent.out() if ent else None, "actor": act.out() if act else None,
            "window": window_out(start, end), **_page(total, p.offset, len(rows)), "events": events,
            "note": None if rows else "Nobody performed that action with these filters."}


# ---------------------------------------------------------------- A4 A2
async def before_and_after(p: m.BeforeAfterInput) -> dict[str, Any]:
    if p.activity_id is not None:
        anchor = await db.fetch_one(ROLE, f"SELECT {EVENT_COLS} {FROM} WHERE a.id = %s", (p.activity_id,))
        if anchor is None:
            fail(f"No activity row has id {p.activity_id}.")
    else:
        act = await resolve.actor(p.actor or "")
        at = parse_when(p.at or "")
        clause, params = act.sql()
        params["at"] = at
        extra = ""
        if p.action:
            params["actions"] = await resolve.actions(p.action)
            extra = " AND a.action = ANY(%(actions)s)"
        anchor = await db.fetch_one(ROLE, f"""
            SELECT {EVENT_COLS} {FROM} WHERE {clause}{extra}
            ORDER BY abs(extract(epoch FROM a.occurred_at - %(at)s)) LIMIT 1""", params)
        if anchor is None:
            fail(f"No event by {act.label}" + (f" with action {p.action}" if p.action else "") + " was found.")
    assert anchor is not None
    params = {"id": anchor["id"], "at": anchor["occurred_at"], "w": timedelta(minutes=p.window_minutes),
              "sess": anchor["session_hash"], "lbl": anchor["actor_label"], "limit": p.limit}
    if p.same_session_only and anchor["session_hash"]:
        scope = "a.session_hash = %(sess)s"
        basis = "same session"
    else:
        scope = "a.actor_label = %(lbl)s"
        basis = "same actor"
    before = await db.fetch_all(ROLE, f"""
        SELECT * FROM (SELECT {EVENT_COLS} {FROM} WHERE {scope} AND a.id <> %(id)s
                         AND a.occurred_at <= %(at)s AND a.occurred_at >= %(at)s - %(w)s
                       ORDER BY a.occurred_at DESC, a.id DESC LIMIT %(limit)s) x ORDER BY occurred_at, id""", params)
    before = [r for r in before if not (r["occurred_at"] == anchor["occurred_at"] and r["id"] > anchor["id"])]
    after = await db.fetch_all(ROLE, f"""
        SELECT {EVENT_COLS} {FROM} WHERE {scope} AND a.id <> %(id)s
          AND a.occurred_at >= %(at)s AND a.occurred_at <= %(at)s + %(w)s
          AND NOT (a.occurred_at = %(at)s AND a.id < %(id)s)
        ORDER BY a.occurred_at, a.id LIMIT %(limit)s""", params)
    return {"anchor": event(anchor), "basis": basis, "window_minutes": p.window_minutes,
            "before": [event(r, False) for r in before], "after": [event(r, False) for r in after],
            "returned": len(before) + len(after) + 1}


# ---------------------------------------------------------------- A6 A8
async def elapsed(p: m.ElapsedInput) -> dict[str, Any]:
    starts = await resolve.actions(p.start_action)
    ends = await resolve.actions(p.end_action)
    start, end = window(p.date_from, p.date_to, p.period)
    params: dict[str, Any] = {"starts": starts, "ends": ends, "limit": p.limit, "offset": p.offset}
    where = ["a.action = ANY(%(starts)s)", "a.entity_type IS NOT NULL"] + _window_sql(start, end, params)
    ent = None
    if p.entity or p.entity_id:
        ent = await resolve.entity(p.entity_type, p.entity_id, p.entity)
        clause, ep = ent.sql()
        where.append(clause)
        params.update(ep)
    elif p.entity_type:
        params["etype"] = resolve.norm_entity_type(p.entity_type)
        where.append("a.entity_type = %(etype)s")
    rows = await db.fetch_all(ROLE, f"""
        WITH s AS (
            SELECT DISTINCT ON (a.entity_type, a.entity_id, a.entity_label)
                   a.id, a.entity_type, a.entity_id, a.entity_label, a.occurred_at, COALESCE(u.display_name, a.actor_label) AS actor
            {FROM} WHERE {' AND '.join(where)}
            ORDER BY a.entity_type, a.entity_id, a.entity_label, a.occurred_at, a.id)
        SELECT s.*, e.id AS end_id, e.occurred_at AS ended_at, e.actor AS end_actor, e.action AS end_action,
               steps.n AS steps_between, count(*) OVER () AS total,
               extract(epoch FROM e.occurred_at - s.occurred_at) AS seconds
        FROM s
        LEFT JOIN LATERAL (
            SELECT b.id, b.occurred_at, b.action, COALESCE(ub.display_name, b.actor_label) AS actor
            FROM app.activity_log b LEFT JOIN app.users ub ON ub.id = b.actor_id
            WHERE b.action = ANY(%(ends)s) AND b.entity_type = s.entity_type
              AND b.entity_id IS NOT DISTINCT FROM s.entity_id AND b.occurred_at >= s.occurred_at AND b.id <> s.id
            ORDER BY b.occurred_at, b.id LIMIT 1) e ON true
        LEFT JOIN LATERAL (
            SELECT count(*) AS n FROM app.activity_log c
            WHERE c.entity_type = s.entity_type AND c.entity_id IS NOT DISTINCT FROM s.entity_id
              AND c.action <> 'screen_entered' AND c.occurred_at > s.occurred_at
              AND c.occurred_at < COALESCE(e.occurred_at, now())) steps ON true
        ORDER BY s.occurred_at DESC LIMIT %(limit)s OFFSET %(offset)s""", params)
    total = rows[0]["total"] if rows else 0
    items, done = [], []
    for r in rows:
        secs = float(r["seconds"]) if r["seconds"] is not None else None
        if secs is not None:
            done.append(secs)
        open_for = (now() - r["occurred_at"]).total_seconds() if secs is None else None
        items.append(clean({
            "record": {"type": r["entity_type"], "id": r["entity_id"], "label": r["entity_label"]},
            "started": {"id": r["id"], "at": r["occurred_at"], "by": r["actor"]},
            "ended": {"id": r["end_id"], "at": r["ended_at"], "by": r["end_actor"], "action": r["end_action"]} if r["end_id"] else None,
            "elapsed_seconds": round(secs, 1) if secs is not None else None,
            "elapsed": human_duration(secs),
            "still_open_for": human_duration(open_for),
            "other_events_between": r["steps_between"],
        }))
    summary = {"completed": len(done), "open": len(items) - len(done),
               "average": human_duration(sum(done) / len(done)) if done else None,
               "longest": human_duration(max(done)) if done else None}
    return {"start_actions": starts, "end_actions": ends, "record": ent.out() if ent else None, "window": window_out(start, end),
            **_page(total, p.offset, len(rows)), "summary": summary, "items": items,
            "note": None if rows else f"No {', '.join(starts)} events match these filters."}


# ---------------------------------------------------------------- A7
async def screen_usage(p: m.ScreenUsageInput) -> dict[str, Any]:
    start, end = window(p.date_from, p.date_to, p.period)
    params: dict[str, Any] = {"tz": str(tz()), "limit": p.limit, "offset": p.offset}
    where = ["a.action = 'screen_entered'"] + _window_sql(start, end, params)
    scr = act = None
    if p.screen:
        scr = await resolve.screen(p.screen)
        where.append("a.screen = %(screen)s")
        params["screen"] = scr
    if p.actor:
        act = await resolve.actor(p.actor)
        clause, ap = act.sql()
        where.append(clause)
        params.update(ap)
    if p.role:
        where.append("u.role = %(role)s")
        params["role"] = p.role.lower()
    hour = "extract(hour FROM a.occurred_at AT TIME ZONE %(tz)s)::int"
    if p.hour_from is not None or p.hour_to is not None:
        hf = p.hour_from if p.hour_from is not None else 0
        ht = p.hour_to if p.hour_to is not None else 24
        params.update(hf=hf, ht=ht)
        where.append(f"({hour} >= %(hf)s AND {hour} < %(ht)s)" if hf < ht else f"({hour} >= %(hf)s OR {hour} < %(ht)s)")
    keys = {
        "actor": ("COALESCE(u.display_name, a.actor_label)", "entries DESC"),
        "screen": ("a.screen", "entries DESC"),
        "hour": (hour, "k1"),
        "role": ("COALESCE(u.role, 'non-user')", "entries DESC"),
        "day": ("(a.occurred_at AT TIME ZONE %(tz)s)::date", "k1 DESC"),
    }
    if p.group_by == "actor_screen":
        k1, k2, order = "COALESCE(u.display_name, a.actor_label)", "a.screen", "entries DESC"
    else:
        k1, order = keys[p.group_by]
        k2 = "NULL::text"
    rows = await db.fetch_all(ROLE, f"""
        SELECT {k1} AS k1, {k2} AS k2, count(*) AS entries, count(DISTINCT a.actor_label) AS people,
               count(DISTINCT a.screen) AS screens, min(a.occurred_at) AS first_at, max(a.occurred_at) AS last_at,
               count(*) OVER () AS total
        {FROM} WHERE {' AND '.join(where)}
        GROUP BY 1, 2 ORDER BY {order}, k1 LIMIT %(limit)s OFFSET %(offset)s""", params)
    total = rows[0]["total"] if rows else 0
    groups = []
    for r in rows:
        g: dict[str, Any] = {"actor" if p.group_by == "actor_screen" else p.group_by: r["k1"]}
        if p.group_by == "actor_screen":
            g["screen"] = r["k2"]
        g.update(entries=r["entries"], people=r["people"], screens=r["screens"], first_at=r["first_at"], last_at=r["last_at"])
        groups.append(clean(g))
    return {"group_by": p.group_by, "screen": scr, "actor": act.out() if act else None, "role": p.role,
            "hours": [p.hour_from, p.hour_to] if (p.hour_from is not None or p.hour_to is not None) else None,
            "window": window_out(start, end), **_page(total, p.offset, len(rows)), "groups": groups,
            "note": None if rows else "No screen entries match these filters."}


# ---------------------------------------------------------------- A9
ENTITY_TABLES = {
    "recipe_version": "SELECT rv.id, p.name || ' v' || rv.version_no AS label FROM app.recipe_versions rv JOIN app.products p ON p.id = rv.product_id",
    "lot": "SELECT id, lot_number AS label FROM app.lots",
    "batch": "SELECT id, number AS label FROM app.batches",
    "item": "SELECT id, code AS label FROM app.items",
    "supplier": "SELECT id, name AS label FROM app.suppliers",
    "customer": "SELECT id, name AS label FROM app.customers",
    "vessel": "SELECT id, name AS label FROM app.vessels",
    "product": "SELECT id, name AS label FROM app.products",
    "purchase_order": "SELECT id, number AS label FROM app.purchase_orders",
    "production_order": "SELECT id, number AS label FROM app.production_orders",
    "location": "SELECT id, name AS label FROM app.locations",
    "keg": "SELECT id, serial AS label FROM app.kegs",
}


async def untouched(p: m.UntouchedInput) -> dict[str, Any]:
    etype = resolve.norm_entity_type(p.entity_type) or ""
    scr = await resolve.screen(p.screen) if p.screen else None
    universe_sql = ENTITY_TABLES.get(etype)
    basis = "entity table"
    universe: list[dict[str, Any]] | None = None
    if universe_sql:
        try:
            universe = await db.fetch_all(ROLE, universe_sql)
        except psycopg.errors.InsufficientPrivilege:
            universe = None
    if universe is None:
        basis = "records known from the activity log (the read role cannot list the entity table)"
        universe = await db.fetch_all(ROLE, """
            SELECT DISTINCT ON (entity_id) entity_id AS id, entity_label AS label FROM app.activity_log
            WHERE entity_type = %s AND entity_id IS NOT NULL ORDER BY entity_id, occurred_at DESC""", (etype,))
        if not universe:
            await resolve._check_type(etype)
    cutoff = now() - timedelta(days=p.since_days)
    params: dict[str, Any] = {"etype": etype, "ids": [u["id"] for u in universe]}
    scr_sql = ""
    if scr:
        scr_sql = " AND screen = %(screen)s"
        params["screen"] = scr
    opened = await db.fetch_all(ROLE, f"""
        SELECT entity_id, max(occurred_at) AS last_opened, count(*) AS opens,
               (array_agg(actor_label ORDER BY occurred_at DESC))[1] AS last_by
        FROM app.activity_log WHERE action = 'screen_entered' AND entity_type = %(etype)s
          AND entity_id = ANY(%(ids)s){scr_sql} GROUP BY 1""", params)
    seen = {r["entity_id"]: r for r in opened}
    items = []
    for u in universe:
        o = seen.get(u["id"])
        if o is None or o["last_opened"] < cutoff:
            items.append(clean({"entity_type": etype, "entity_id": u["id"], "label": u["label"],
                                "last_opened": o["last_opened"] if o else None, "last_opened_by": o["last_by"] if o else None,
                                "opens_ever": o["opens"] if o else 0}))
    items.sort(key=lambda x: (x["last_opened"] is not None, x["last_opened"] or ""))
    page = items[p.offset:p.offset + p.limit]
    return {"entity_type": etype, "screen": scr, "since_days": p.since_days, "cutoff": iso(cutoff), "basis": basis,
            "records_considered": len(universe), **_page(len(items), p.offset, len(page)), "untouched": page,
            "note": None if items else f"Every {etype} was opened{' on ' + scr if scr else ''} within the last {p.since_days} days."}


# ---------------------------------------------------------------- A10
async def day_replay(p: m.DayReplayInput) -> dict[str, Any]:
    ent = None
    if p.entity or p.entity_id:
        ent = await resolve.entity(p.entity_type, p.entity_id, p.entity)
    found_by = None
    if p.date:
        day_start = parse_when(p.date)
        day_start = day_start.astimezone(tz()).replace(hour=0, minute=0, second=0, microsecond=0)
    else:
        assert ent is not None and p.day_of_action
        acts = await resolve.actions(p.day_of_action)
        clause, params = ent.sql()
        params["acts"] = acts
        hit = await db.fetch_one(ROLE, f"""SELECT {EVENT_COLS} {FROM} WHERE {clause} AND a.action = ANY(%(acts)s)
                                           ORDER BY a.occurred_at DESC LIMIT 1""", params)
        if hit is None:
            fail(f"No {', '.join(acts)} event is recorded on {ent.label}. Pass date instead, or check activity_record_history.")
        assert hit is not None
        found_by = event(hit)
        day_start = hit["occurred_at"].astimezone(tz()).replace(hour=0, minute=0, second=0, microsecond=0)
    day_end = day_start + timedelta(days=1)
    params: dict[str, Any] = {"d0": day_start, "d1": day_end, "limit": p.limit, "offset": p.offset}
    where = ["a.occurred_at >= %(d0)s", "a.occurred_at < %(d1)s"]
    rel = "NULL"
    if ent:
        direct, ep = ent.sql()
        params.update(ep)
        if ent.label and len(ent.label) >= 5:
            params["lab"] = ent.label
            where.append(f"({direct} OR ({_mentions_sql()}))")
            rel = f"CASE WHEN {direct} THEN NULL ELSE 'mentions' END"
        else:
            where.append(direct)
    if not p.include_screen_entries:
        where.append("a.action <> 'screen_entered'")
    ep_join = ep_cols = ""
    if p.include_episodes:
        ep_join = "LEFT JOIN memory.maludb_episode me ON me.episode_id = a.memory_episode_id"
        ep_cols = ", me.episode_kind, me.title AS episode_title"
    rows = await db.fetch_all(ROLE, f"""
        SELECT {EVENT_COLS}, {rel} AS relation, count(*) OVER () AS total{ep_cols}
        {FROM} {ep_join} WHERE {' AND '.join(where)}
        ORDER BY a.occurred_at, a.id LIMIT %(limit)s OFFSET %(offset)s""", params)
    total = rows[0]["total"] if rows else 0
    events = []
    for r in rows:
        e = event(r)
        if p.include_episodes and r.get("episode_title"):
            e["episode"] = {"id": r["memory_episode_id"], "kind": r["episode_kind"], "title": r["episode_title"]}
        events.append(e)
    counts: dict[str, int] = {}
    for e in events:
        counts[e["action"]] = counts.get(e["action"], 0) + 1
    return {"date": day_start.date().isoformat(), "time_zone": str(tz()), "record": ent.out() if ent else None,
            "found_by": found_by, **_page(total, p.offset, len(rows)), "actions_on_page": counts, "events": events,
            "note": None if rows else "Nothing happened that day for this record."}


# ---------------------------------------------------------------- A11
UNDO_MATCH = """(u.details->>'undo_id' = a.id::text OR u.details->>'activity_id' = a.id::text
                 OR u.details->>'undone_activity_id' = a.id::text OR u.details->>'target_activity_id' = a.id::text
                 OR u.before->>'activity_id' = a.id::text
                 OR (u.details->>'undone_action' = a.action AND u.entity_type IS NOT DISTINCT FROM a.entity_type
                     AND u.entity_id IS NOT DISTINCT FROM a.entity_id))"""


async def assistant_actions(p: m.AssistantActionsInput) -> dict[str, Any]:
    start, end = window(p.date_from, p.date_to, p.period)
    params: dict[str, Any] = {"limit": p.limit, "offset": p.offset}
    where = ["a.source IN ('command_bar', 'ama')"] + _window_sql(start, end, params)
    hidden = ["screen_entered", "action_undone"] + ([] if p.include_messages else ["assistant_message", "ama_question"])
    params["hidden"] = hidden
    where.append("a.action <> ALL(%(hidden)s)")
    act = None
    if p.actor:
        act = await resolve.actor(p.actor)
        clause, ap = act.sql()
        where.append(clause)
        params.update(ap)
    rows = await db.fetch_all(ROLE, f"""
        SELECT {EVENT_COLS}, count(*) OVER () AS total,
               count(*) FILTER (WHERE und.id IS NOT NULL) OVER () AS undone_total,
               und.id AS undo_id, und.occurred_at AS undone_at, und.actor_label AS undone_by, und.source AS undone_via
        {FROM}
        LEFT JOIN LATERAL (
            SELECT u.id, u.occurred_at, u.actor_label, u.source FROM app.activity_log u
            WHERE u.action = 'action_undone' AND u.occurred_at >= a.occurred_at AND u.id <> a.id AND {UNDO_MATCH}
            ORDER BY u.occurred_at LIMIT 1) und ON true
        WHERE {' AND '.join(where)}
        ORDER BY a.occurred_at DESC, a.id DESC LIMIT %(limit)s OFFSET %(offset)s""", params)
    total = rows[0]["total"] if rows else 0
    events = []
    for r in rows:
        e = event(r)
        if r["action"] not in ("assistant_message", "ama_question"):
            e["undone"] = clean({"at": r["undone_at"], "by": r["undone_by"], "via": r["undone_via"], "undo_event_id": r["undo_id"]}) if r["undo_id"] else False
        events.append(e)
    return {"actor": act.out() if act else None, "window": window_out(start, end), **_page(total, p.offset, len(rows)),
            "summary": {"actions": sum(1 for e in events if "undone" in e), "undone": rows[0]["undone_total"] if rows else 0},
            "events": events, "note": None if rows else "The assistant took no actions in this window."}


# ---------------------------------------------------------------- A12
async def mcp_usage(p: m.McpUsageInput) -> dict[str, Any]:
    start, end = window(p.date_from, p.date_to, p.period)
    params: dict[str, Any] = {"limit": p.limit, "offset": p.offset}
    where = ["a.source = 'mcp'", "a.action = 'mcp_tool_called'"] + _window_sql(start, end, params)
    if p.token:
        where.append("(a.actor_label = %(tok)s OR a.actor_label ILIKE %(toklike)s)")
        params.update(tok=f"mcp/token:{p.token}", toklike=f"mcp/token:%{p.token}%")
    if p.tool:
        where.append("(a.entity_label = %(tool)s OR a.details->>'tool' = %(tool)s)")
        params["tool"] = p.tool
    if p.server:
        where.append("a.details->>'server' = %(server)s")
        params["server"] = p.server
    w = " AND ".join(where)
    per_tool = await db.fetch_all(ROLE, f"""
        SELECT replace(a.actor_label, 'mcp/token:', '') AS token, COALESCE(a.details->>'tool', a.entity_label) AS tool,
               a.details->>'server' AS server, count(*) AS calls, count(*) FILTER (WHERE a.details ? 'error') AS errors,
               min(a.occurred_at) AS first_at, max(a.occurred_at) AS last_at
        FROM app.activity_log a WHERE {w} GROUP BY 1, 2, 3 ORDER BY 1, calls DESC""", params)
    tokens: dict[str, dict[str, Any]] = {}
    for r in per_tool:
        t = tokens.setdefault(r["token"], {"token": r["token"], "calls": 0, "errors": 0, "first_at": r["first_at"],
                                           "last_at": r["last_at"], "servers": [], "tools": {}})
        t["calls"] += r["calls"]
        t["errors"] += r["errors"]
        t["first_at"] = min(t["first_at"], r["first_at"])
        t["last_at"] = max(t["last_at"], r["last_at"])
        if r["server"] and r["server"] not in t["servers"]:
            t["servers"].append(r["server"])
        t["tools"][r["tool"]] = t["tools"].get(r["tool"], 0) + r["calls"]
    summary = sorted(tokens.values(), key=lambda t: -t["calls"])
    calls: list[dict[str, Any]] = []
    total = sum(s["calls"] for s in summary)
    if p.include_calls:
        rows = await db.fetch_all(ROLE, f"""
            SELECT a.id, a.occurred_at, a.actor_label, a.entity_label, a.details FROM app.activity_log a WHERE {w}
            ORDER BY a.occurred_at DESC, a.id DESC LIMIT %(limit)s OFFSET %(offset)s""", params)
        for r in rows:
            d = r["details"] or {}
            calls.append(clean({"id": r["id"], "at": r["occurred_at"], "token": r["actor_label"].replace("mcp/token:", ""),
                                "server": d.get("server"), "tool": d.get("tool") or r["entity_label"],
                                "arguments": d.get("arguments"), "rows": d.get("rows"), "duration_ms": d.get("duration_ms"),
                                "error": d.get("error")}))
    return {"window": window_out(start, end), "tokens": [clean(s) for s in summary], "total": total, "offset": p.offset,
            "returned": len(calls) if p.include_calls else len(summary), "calls": calls,
            "next_offset": (p.offset + len(calls)) if p.include_calls and p.offset + len(calls) < total else None,
            "note": None if summary else "No MCP tool calls in this window."}


# ---------------------------------------------------------------- long tail: episodes
async def activity_search(p: m.SearchInput) -> dict[str, Any]:
    start, end = window(p.date_from, p.date_to, p.period)
    params: dict[str, Any] = {"limit": p.limit, "offset": p.offset}
    where = _window_sql(start, end, params, col="e.occurred_at")
    joins = []
    mode = []
    if p.query:
        params["q"] = p.query
        hits = await db.fetch_one(ROLE, "SELECT count(*) AS n FROM maludb_core.text_search(%(q)s, ARRAY['episode_object'], 1)", params)
        if hits and hits["n"]:
            joins.append("JOIN maludb_core.text_search(%(q)s, ARRAY['episode_object'], 5000) ts ON ts.object_id = e.episode_id")
            mode.append("full_text")
        else:
            words = [w for w in re.split(r"\s+", p.query) if w]
            for i, wd in enumerate(words[:8]):
                params[f"w{i}"] = f"%{wd}%"
                where.append(f"(e.title ILIKE %(w{i})s OR e.payload_jsonb::text ILIKE %(w{i})s)")
            mode.append("substring (no full-text hits)")
    if p.subject or p.verb:
        cond = ["st.subject_kind = 'episode_object'", "st.subject_id = e.episode_id"]
        if p.verb:
            params["verb"] = p.verb.strip().lower().replace(" ", "_")
            params["verblike"] = f"%{params['verb']}%"
            cond.append("(v.canonical_name = %(verb)s OR v.canonical_name ILIKE %(verblike)s)")
        if p.subject:
            params["subj"] = f"%{p.subject}%"
            cond.append("(s.canonical_name ILIKE %(subj)s OR e.canonical_name ILIKE %(subj)s)")
        where.append(f"""EXISTS (SELECT 1 FROM memory.maludb_svpor_statement st
                         JOIN memory.maludb_verb v ON v.verb_id = st.verb_id
                         LEFT JOIN memory.maludb_subject s ON st.object_kind = 'subject' AND s.subject_id = st.object_id
                         WHERE {' AND '.join(cond)})""")
        mode.append("subject_verb")
    if p.kind:
        params["kind"] = p.kind.strip().lower()
        where.append("e.episode_kind = %(kind)s")
    sql = f"""SELECT e.episode_id, e.episode_kind, e.title, e.occurred_at, e.payload_jsonb, count(*) OVER () AS total
              FROM memory.maludb_episode e {' '.join(joins)}
              {('WHERE ' + ' AND '.join(where)) if where else ''}
              ORDER BY e.occurred_at DESC, e.episode_id DESC LIMIT %(limit)s OFFSET %(offset)s"""
    rows = await db.fetch_all(ROLE, sql, params)
    total = rows[0]["total"] if rows else 0
    out = []
    for r in rows:
        pl = r["payload_jsonb"] or {}
        item = {"episode_id": r["episode_id"], "kind": r["episode_kind"], "title": r["title"], "at": r["occurred_at"],
                "activity_id": pl.get("activity_id"), "actor": pl.get("actor"), "source": pl.get("source"), "screen": pl.get("screen")}
        if pl.get("entity_type") or pl.get("entity_label"):
            item["entity"] = {"type": pl.get("entity_type"), "id": pl.get("entity_id"), "label": pl.get("entity_label")}
        for key in ("before", "after", "details"):
            if pl.get(key):
                item[key] = with_display(pl[key])
        out.append(clean(item))
    return {"mode": mode or ["kind"], "window": window_out(start, end), **_page(total, p.offset, len(rows)), "episodes": out,
            "note": None if rows else "No episodes match. Try fewer words, a record label, or subject/verb."}


# ---------------------------------------------------------------- long tail: SQL
ALLOWED_APP = {"activity_log", "users"}
SYSTEM_SCHEMAS = {"pg_catalog", "information_schema", "pg_toast", "maludb_core", "public"}


async def activity_sql(p: m.SqlInput, schemas: set[str]) -> dict[str, Any]:
    try:
        wrapped = sql_guard.validate_select(p.sql, max_rows=200)
    except ValueError as exc:
        fail(str(exc))
        raise
    text = re.sub(r"'(?:[^']|'')*'", "''", p.sql)
    if "malu$" in text.lower():
        fail("Query the memory schema's maludb_* views, not the maludb_core base tables.")
    for sch, rel in re.findall(r'"?\b([a-z_][a-z0-9_$]*)"?\s*\.\s*"?([a-z_][a-z0-9_$]*)"?', text, re.IGNORECASE):
        s = sch.lower()
        if s in schemas or s in SYSTEM_SCHEMAS:
            if s == "app" and rel.lower() not in ALLOWED_APP:
                fail(f"app.{rel} is not available here; this tool reads app.activity_log, app.users and memory views only. "
                     "Record questions go to the records server.")
            if s not in ("app", "memory"):
                fail(f"Schema '{sch}' is not available; use app.activity_log, app.users and memory.maludb_* views.")
    try:
        plan = await db.fetch_all(ROLE, f"EXPLAIN (FORMAT JSON, VERBOSE) {wrapped}")
    except psycopg.errors.InsufficientPrivilege as exc:
        fail(_privilege_message(str(exc)))
        raise
    except psycopg.Error as exc:
        fail(f"The query is not valid: {_pg_message(exc)}")
        raise
    for schema, rel in _plan_relations(plan[0]["QUERY PLAN"] if plan else []):
        if schema == "app" and rel not in ALLOWED_APP:
            fail(f"The query reads app.{rel}, which is not available here; use app.activity_log, app.users and memory views only.")
        if schema in ("pg_catalog", "information_schema", "public") or (schema not in ("app", "memory", "maludb_core")):
            fail(f"The query reads {schema}.{rel}; only app.activity_log, app.users and memory.maludb_* views are allowed.")
    try:
        rows = await db.fetch_all(ROLE, wrapped)
    except psycopg.errors.InsufficientPrivilege as exc:
        fail(_privilege_message(str(exc)))
        raise
    return {"returned": len(rows), "row_cap": 200, "rows": [clean(dict(r)) for r in rows]}


def _plan_relations(node: Any) -> list[tuple[str, str]]:
    out: list[tuple[str, str]] = []
    if isinstance(node, dict):
        if "Relation Name" in node:
            out.append((str(node.get("Schema", "")), str(node["Relation Name"])))
        for v in node.values():
            out.extend(_plan_relations(v))
    elif isinstance(node, list):
        for v in node:
            out.extend(_plan_relations(v))
    return out


def _privilege_message(text: str) -> str:
    if "users" in text:
        return "Only id, display_name, role and status of app.users are readable; name those columns instead of *."
    return "That object is not readable by the activity reader; use app.activity_log, app.users (id, display_name, role, status) and memory views."


def _pg_message(exc: psycopg.Error) -> str:
    diag = getattr(exc, "diag", None)
    msg = (diag.message_primary if diag and diag.message_primary else str(exc)).split("\n")[0]
    return msg[:300]


async def schema_summary() -> tuple[str, set[str]]:
    """Columns of the readable tables and the key memory views, for activity_sql's description."""
    cols = await db.fetch_all(ROLE, """
        SELECT c.table_schema, c.table_name, string_agg(c.column_name || ' ' || c.data_type, ', ' ORDER BY c.ordinal_position) AS cols
        FROM information_schema.columns c
        WHERE (c.table_schema = 'app' AND c.table_name = 'activity_log')
           OR (c.table_schema = 'memory' AND c.table_name IN ('maludb_episode', 'maludb_subject', 'maludb_svpor_statement', 'maludb_verb', 'maludb_edge'))
        GROUP BY 1, 2 ORDER BY 1, 2""")
    users = await db.fetch_all(ROLE, """SELECT string_agg(column_name, ', ' ORDER BY column_name) AS cols FROM information_schema.column_privileges
                                        WHERE table_schema = 'app' AND table_name = 'users' AND privilege_type = 'SELECT'
                                          AND grantee IN (current_user, 'PUBLIC')""")
    views = await db.fetch_all(ROLE, "SELECT table_name FROM information_schema.views WHERE table_schema = 'memory' ORDER BY 1")
    schemas = await db.fetch_all(ROLE, "SELECT nspname FROM pg_namespace")
    lines = [f"- {r['table_schema']}.{r['table_name']}({r['cols']})" for r in cols]
    lines.insert(1, f"- app.users({(users[0]['cols'] if users and users[0]['cols'] else 'id, display_name, role, status')}) -- only these columns")
    other = [v["table_name"] for v in views if v["table_name"] not in ("maludb_episode", "maludb_subject", "maludb_svpor_statement", "maludb_verb", "maludb_edge")]
    lines.append("- other memory views: " + ", ".join(other))
    return "\n".join(lines), {r["nspname"].lower() for r in schemas}


async def client_timezone() -> str | None:
    try:
        row = await db.fetch_one(ROLE, "SELECT timezone FROM app.client_settings LIMIT 1")
        return row["timezone"] if row else None
    except psycopg.Error:
        return None


def to_json(obj: Any) -> str:
    return json.dumps(obj, default=str, separators=(",", ":"))
