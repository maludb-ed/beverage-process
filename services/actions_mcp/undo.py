"""The one implementation of the undo rules (docs/05 "Undo definitions"), applied to an
activity_log row through the app's own endpoints. Used by the undo_last MCP tool and by
POST /undo (html/assistant/undo.php, the command bar's Undo button)."""
from __future__ import annotations

from typing import Any, Awaitable, Callable

from common import db

from . import catalog, units
from .core import ACTIONS, ActionMeta, Call, Invalid, activity_row, log_action_undone, needs_confirmation, perform

Plan = dict[str, Any]   # {path, fields, event, summary} or {status, message}
Handler = Callable[[dict[str, Any], Call], Awaitable[Plan]]
UNDO: dict[str, Handler] = {}


def handles(*events: str):
    def deco(fn: Handler) -> Handler:
        for e in events:
            UNDO[e] = fn
        return fn
    return deco


def _unavailable(message: str) -> Plan:
    return {"status": "not_undoable", "message": message}


def meta_for_event(event: str) -> ActionMeta | None:
    for meta in ACTIONS.values():
        if event in meta.events:
            return meta
    return None


@handles("batch_reading_recorded", "lab_reading_recorded")
async def _reading(row, call):
    rid = (row["after"] or {}).get("reading_id") if row["action"] == "batch_reading_recorded" else row["entity_id"]
    if not rid:
        return _unavailable("The activity row does not name the reading.")
    reading = await db.fetch_one("records", "SELECT id, measurement_type_code, value::float AS value FROM app.readings WHERE id = %s", (rid,))
    if reading is None:
        return {"status": "already_undone", "message": "That reading no longer exists."}
    return {"path": f"/lab/{rid}/delete", "fields": [], "event": "lab_reading_deleted",
            "summary": f"Removed the {reading['measurement_type_code']} {reading['value']:g} reading from {row['entity_label']}"}


@handles("batch_updated")
async def _batch_notes(row, call):
    b = await db.fetch_one("records", "SELECT status, tax_class_override, tax_class_override_reason_code_id FROM app.batches WHERE id = %s", (row["entity_id"],))
    if b is None or b["status"] != "active":
        return _unavailable(f"{row['entity_label']} is no longer active.")
    return {"path": f"/batches/{row['entity_id']}/save", "event": "batch_updated",
            "fields": [("notes", (row["before"] or {}).get("notes") or ""), ("tax_class_override", b["tax_class_override"] or "none"),
                       ("tax_class_override_reason", b["tax_class_override_reason_code_id"] or "")],
            "summary": f"Restored the previous notes on {row['entity_label']}"}


@handles("lot_attribute_set")
async def _lot_attribute(row, call):
    before = row["before"]
    after = row["after"] or {}
    if not before:
        return _unavailable(f"The {after.get('key', 'attribute')} attribute was new on lot {row['entity_label']}; there is no endpoint to remove an attribute. Set it to the right value instead.")
    key = before["key"]
    current = await db.fetch_one("records", "SELECT unit_code FROM app.lot_attributes WHERE lot_id = %s AND key = %s", (row["entity_id"], key))
    keyfields = [("key", key), ("other_key", "")] if key in catalog.LOT_ATTRIBUTE_KEYS else [("key", "other"), ("other_key", key)]
    num = before.get("value_num")
    shown = f"{float(num):g}" if num is not None else before.get("value_text")
    return {"path": f"/lots/{row['entity_id']}/attributes/save", "event": "lot_attribute_set",
            "fields": keyfields + [("value_num", "" if num is None else f"{float(num):g}"), ("value_text", before.get("value_text") or ""),
                                   ("unit_code", (current or {}).get("unit_code") or "")],
            "summary": f"{catalog.LOT_ATTRIBUTE_KEYS.get(key, key)} of {row['entity_label']} back to {shown}"}


@handles("lot_released")
async def _lot_release(row, call):
    prior = (row["before"] or {}).get("quality_status")
    after = (row["after"] or {}).get("quality_status")
    lot = await db.fetch_one("records", "SELECT quality_status FROM app.lots WHERE id = %s", (row["entity_id"],))
    if lot is None or not prior:
        return _unavailable("The lot or its prior status is unknown.")
    if lot["quality_status"] != after:
        return _unavailable(f"Lot {row['entity_label']} has changed since (now {lot['quality_status']}); make a new release decision instead.")
    return {"path": f"/lots/{row['entity_id']}/release", "event": "lot_released",
            "fields": [("to_status", prior), ("basis", "other"), ("note", f"Undo of the {after} decision (activity #{row['id']})")],
            "summary": f"Lot {row['entity_label']} back to {prior}"}


async def _status(table: str, rid: int) -> str | None:
    r = await db.fetch_one("records", f"SELECT status FROM app.{table} WHERE id = %s", (rid,))
    return r["status"] if r else None


def _simple(path_fmt: str, event: str, table: str, allowed: tuple[str, ...], verb: str):
    async def handler(row, call):
        status = await _status(table, row["entity_id"])
        if status is None:
            return {"status": "already_undone", "message": f"{row['entity_label']} no longer exists."}
        if status not in allowed:
            return _unavailable(f"{row['entity_label']} is {status} now, so it cannot be {verb}.")
        return {"path": path_fmt.format(id=row["entity_id"]), "fields": [], "event": event, "summary": f"{row['entity_label']} {verb}"}
    return handler


UNDO["po_created"] = _simple("/purchase-orders/{id}/cancel", "po_cancelled", "purchase_orders", ("draft",), "cancelled")
UNDO["transfer_created"] = _simple("/transfers/{id}/cancel", "transfer_cancelled", "inventory_transfers", ("draft",), "cancelled")
UNDO["count_started"] = _simple("/counts/{id}/cancel", "count_cancelled", "inventory_counts", ("open", "counting"), "cancelled")
UNDO["removal_created"] = _simple("/removals/{id}/delete", "removal_deleted", "removals", ("draft",), "deleted")
UNDO["transfer_posted"] = _simple("/transfers/{id}/reverse", "transfer_reversed", "inventory_transfers", ("posted",), "reversed")
UNDO["adjustment_posted"] = _simple("/adjustments/{id}/reverse", "adjustment_reversed", "inventory_adjustments", ("posted",), "reversed")
UNDO["packaging_run_posted"] = _simple("/packaging-runs/{id}/reverse", "packaging_run_reversed", "packaging_runs", ("posted",), "reversed")


@handles("removal_posted")
async def _removal_posted(row, call):
    status = await _status("removals", row["entity_id"])
    if status != "posted":
        return _unavailable(f"{row['entity_label']} is {status}, so it cannot be reversed.")
    return {"path": f"/removals/{row['entity_id']}/reverse", "event": "removal_reversed", "confirm": True,
            "fields": [("reason", f"Undo by the assistant of activity #{row['id']}")],
            "summary": f"{row['entity_label']} reversed (a reversing document was posted)"}


@handles("count_line_recorded")
async def _count_line(row, call):
    before = row["before"] or {}
    if before.get("qty_counted_base") is None:
        return _unavailable("That was the line's first count; there is no endpoint to clear it. Record the right quantity instead.")
    line = await db.fetch_one("records", """SELECT i.base_unit_code, i.item_class, cl.item_id FROM app.inventory_count_lines cl JOIN app.items i ON i.id = cl.item_id
                                            WHERE cl.id = %s""", (before["line_id"],))
    qty, unit = await units.base_to_display(float(before["qty_counted_base"]), line["base_unit_code"], "fruit" if line["item_class"] == "fruit" else "default")
    return {"path": f"/counts/{row['entity_id']}/lines/save", "event": "count_line_recorded",
            "fields": [("line_id", before["line_id"]), ("qty_counted", f"{qty:.6f}")],
            "summary": f"Count of {before.get('lot') or 'the line'} on {row['entity_label']} back to {units.fmt(qty, unit, 3)}"}


@handles("keg_marked_lost", "keg_found")
async def _keg(row, call):
    keg = await db.fetch_one("records", "SELECT state FROM app.kegs WHERE id = %s", (row["entity_id"],))
    lost = row["action"] == "keg_marked_lost"
    if keg is None or (lost and keg["state"] != "lost") or (not lost and keg["state"] != "returned_dirty"):
        return _unavailable(f"Keg {row['entity_label']} has moved on since (now {keg['state'] if keg else 'gone'}).")
    return {"path": f"/kegs/{row['entity_id']}/state", "fields": [("event", "found" if lost else "mark_lost")],
            "event": "keg_found" if lost else "keg_marked_lost",
            "summary": f"Keg {row['entity_label']} " + ("marked found again (it is back as returned dirty)" if lost else "marked lost again")}


@handles("keg_registered")
async def _keg_registered(row, call):
    return {"path": f"/kegs/{row['entity_id']}/delete", "fields": [], "event": "keg_deleted", "summary": f"Keg {row['entity_label']} removed from the fleet"}


@handles("customer_created")
async def _customer(row, call):
    return {"path": f"/customers/{row['entity_id']}/delete", "fields": [], "event": ["customer_deleted", "customer_updated"],
            "summary": f"Customer {row['entity_label']} removed"}


@handles("vessel_status_set")
async def _vessel(row, call):
    prior = (row["before"] or {}).get("status")
    if prior not in catalog.VESSEL_SETTABLE_STATUSES:
        return _unavailable(f"The vessel was {prior} before; that status cannot be set by hand.")
    return {"path": f"/vessels/{row['entity_id']}/status", "fields": [("status", prior)], "event": "vessel_status_set",
            "summary": f"Vessel {row['entity_label']} back to {prior.replace('_', ' ')}"}


@handles("equipment_status_set")
async def _equipment_status(row, call):
    prior = (row["before"] or {}).get("status")
    if prior not in catalog.EQUIPMENT_STATUSES:
        return _unavailable("The equipment's earlier status is not known.")
    return {"path": f"/equipment/{row['entity_id']}/status", "fields": [("status", prior)], "event": "equipment_status_set",
            "summary": f"Equipment {row['entity_label']} back to {prior.replace('_', ' ')}"}


@handles("equipment_reserved")
async def _equipment_reserved(row, call):
    return {"path": f"/reservations/{row['entity_id']}/cancel", "fields": [], "event": "equipment_reservation_cancelled",
            "summary": f"Booking {row['entity_label']} cancelled"}


@handles("order_created", "order_created_from_standing")
async def _order_created(row, call):
    o = await db.fetch_one("records", """SELECT so.status, EXISTS (SELECT 1 FROM app.v_sales_order_lines l WHERE l.sales_order_id = so.id
                                                                  AND (l.units_shipped > 0 OR l.units_in_packaging_runs > 0)) AS busy
                                         FROM app.sales_orders so WHERE so.id = %s""", (row["entity_id"],))
    if o is None or o["status"] == "cancelled":
        return {"status": "already_undone", "message": f"Order {row['entity_label']} is already cancelled."}
    if o["status"] not in ("draft", "confirmed") or o["busy"]:
        return _unavailable(f"Order {row['entity_label']} is {o['status'].replace('_', ' ')} with packaging or shipments; cancel those first.")
    return {"path": f"/orders/{row['entity_id']}/cancel", "fields": [("cancel_reason", f"Undone by the assistant (activity #{row['id']})")],
            "event": "order_cancelled", "summary": f"Order {row['entity_label']} cancelled"}


@handles("order_packaging_runs_created")
async def _order_runs(row, call):
    runs = (row["after"] or {}).get("packaging_runs") or []
    if not runs:
        return _unavailable("The activity row does not name the packaging runs.")
    return {"path": "/orders/package-undo", "fields": [("runs[]", n) for n in runs], "event": "packaging_run_deleted",
            "summary": f"Draft packaging {'run' if len(runs) == 1 else 'runs'} {', '.join(runs)} deleted"}


@handles("order_shipment_created")
async def _order_shipment(row, call):
    number = (row["after"] or {}).get("removal")
    r = await db.fetch_one("records", "SELECT id, status FROM app.removals WHERE number = %s", (number,)) if number else None
    if r is None:
        return {"status": "already_undone", "message": f"Shipment {number or ''} no longer exists."}
    if r["status"] != "draft":
        return _unavailable(f"Shipment {number} is {r['status']}; a posted shipment is reversed by compliance on its screen.")
    return {"path": f"/removals/{r['id']}/delete", "fields": [], "event": "removal_deleted", "summary": f"Draft shipment {number} deleted"}


@handles("standing_order_deactivated")
async def _standing_paused(row, call):
    return {"path": f"/orders/standing/{row['entity_id']}/active", "fields": [("active", "1")], "event": "standing_order_updated",
            "summary": f"Standing order {row['entity_label']} resumed"}


UNDOABLE_EVENTS = set(UNDO)
NEVER_UNDO = {"screen_entered", "action_undone", "mcp_tool_called", "assistant_message", "ama_question", "login", "logout"}


async def latest_undoable(user_id: int) -> dict[str, Any] | None:
    rows = await db.fetch_all("activity", """
        SELECT a.id FROM app.activity_log a
        WHERE a.actor_id = %s AND a.source = 'command_bar' AND a.action = ANY(%s) AND a.occurred_at > now() - interval '1 day'
          AND NOT EXISTS (SELECT 1 FROM app.activity_log u WHERE u.action = 'action_undone' AND u.details->>'undone_activity_id' = a.id::text)
          AND NOT EXISTS (SELECT 1 FROM app.activity_log u WHERE u.action = 'action_undone' AND u.details->>'inverse_activity_id' = a.id::text)
        ORDER BY a.id DESC LIMIT 1""", (user_id, sorted(UNDOABLE_EVENTS)))
    return rows[0] if rows else None


async def undo(call: Call, undo_id: int | None, confirmed: bool = False) -> dict[str, Any]:
    if undo_id is None:
        # The latest undoable action; one whose effect is already gone (a draft another undo deleted, such as the
        # removal_created row beside order_shipment_created) is marked undone and skipped, so "undo" keeps going back.
        for _ in range(5):
            latest = await latest_undoable(call.user_id)
            if latest is None:
                return {"status": "not_found", "message": "There is nothing of yours from the command bar in the last day that can be undone."}
            row = await activity_row(latest["id"])
            handler = UNDO.get(row["action"]) if row else None
            plan = await handler(row, call) if handler else {}
            if plan.get("status") != "already_undone":
                break
            await log_action_undone(row, None, plan.get("message", "already undone"))
        undo_id = latest["id"]
    row = await activity_row(undo_id)
    if row is None:
        return {"status": "not_found", "message": f"No activity #{undo_id}."}
    if row["actor_id"] != call.user_id:
        return {"status": "refused", "message": "You can undo only your own actions."}
    if row["action"] in NEVER_UNDO:
        return {"status": "not_undoable", "message": f"Activity #{undo_id} ({row['action']}) is not an action that can be undone."}
    done = await db.fetch_one("activity", "SELECT id FROM app.activity_log WHERE action = 'action_undone' AND details->>'undone_activity_id' = %s LIMIT 1", (str(undo_id),))
    if done:
        return {"status": "already_undone", "message": f"That was already undone (activity #{done['id']})."}
    handler = UNDO.get(row["action"])
    meta = meta_for_event(row["action"])
    if handler is None:
        how = meta.undo_how if meta else "The manifest gives it no undo through an endpoint."
        return {"status": "not_undoable", "message": f"{row['action'].replace('_', ' ').capitalize()} on {row['entity_label']} cannot be undone by voice: {how}"}
    plan = await handler(row, call)
    if "status" in plan:
        return plan
    if plan.get("confirm") and not confirmed:
        return needs_confirmation(f"Undo: {plan['summary']}? This changes tax state.")
    events = plan["event"] if isinstance(plan["event"], list) else [plan["event"]]
    result = await perform(call, meta or ActionMeta(row["action"], "", [], "", "owner", "never", "", "", events, []),
                           plan["path"], plan["fields"], done=plan["summary"], events=events)
    if result["status"] != "success":
        result["message"] = "Could not undo: " + result.get("message", "")
        return result
    undone_id = await log_action_undone(row, result["activity_id"], plan["summary"])
    return {"status": "success", "done": f"Undone: {plan['summary']}", "undone": {"undo_id": undo_id, "action": row["action"], "entity": row["entity_label"]},
            "undo_id": None, "refresh": result["refresh"], "navigate": result["navigate"], "activity_id": result["activity_id"],
            "logged_as": undone_id}
