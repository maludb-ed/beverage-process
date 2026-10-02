"""Customer order action tools (docs/05 "Customer orders and planning"). Each resolves names to ids through the
records role, then POSTs to the app's own endpoint as the user (core.perform), like the tools in actions.py.

No `from __future__ import annotations` here: MCPServer reads these annotations at runtime.
"""
from datetime import date
from typing import Annotated, Any

from pydantic import Field

from common import db

from . import resolve
from .actions import CONFIRM, Confirmed, Note, Strict, default_premises
from .core import ACTIONS as ACTIONS_META
from .core import ActionMeta, Call, Invalid, action, needs_confirmation, perform

ORDER_REFRESH = ["ordersChanged"]


class OrderLine(Strict):
    format: Annotated[str, Field(min_length=1, max_length=120, description="Package format as said: 'half barrel', 'kegs', '16 oz can case', 'cans'")]
    units: Annotated[int, Field(ge=1, le=1_000_000, description="Units: kegs, or cans (not cases: 2 cases of 24 = 48 units)")]
    product: Annotated[str | None, Field(default=None, max_length=120, description="Product name when there is more than one product, e.g. 'Hill Dry Cider'")] = None
    unit_price: Annotated[float | None, Field(default=None, ge=0, le=100_000, description="Price per unit in dollars when the user says one; omit for the list price")] = None


async def find_format(text: str, product: str | None) -> dict[str, Any]:
    """A packaging configuration from spoken words: exact name or item code, then every word contained ('half barrel'), narrowed by product."""
    rows = await db.fetch_all("records", """
        SELECT pc.id, pc.name, pc.package_kind, pc.units_per_case, pc.default_unit_price::float AS price, p.name AS product, i.code AS item_code
        FROM app.packaging_configurations pc JOIN app.products p ON p.id = pc.product_id JOIN app.items i ON i.id = pc.finished_item_id
        WHERE pc.active AND p.status = 'active' ORDER BY p.name, pc.name""")
    if product:
        p = product.strip().lower()
        rows = [r for r in rows if p in r["product"].lower()] or rows
    key = text.strip().lower()
    exact = [r for r in rows if key in (r["name"].lower(), r["item_code"].lower())]
    if len(exact) == 1:
        return exact[0]
    words = [w for w in key.replace("-", " ").split() if w not in ("of", "the", "a", "an")]
    synonyms = {"keg": "keg", "kegs": "keg", "barrel": "keg", "barrels": "keg", "can": "can", "cans": "can", "case": "can", "cases": "can", "bottle": "bottle", "bottles": "bottle"}
    kinds = {synonyms[w] for w in words if w in synonyms}
    matches = [r for r in rows if all(w in r["name"].lower() or synonyms.get(w) == r["package_kind"] for w in words)]
    if not matches and kinds:
        matches = [r for r in rows if r["package_kind"] in kinds]
    if len(matches) == 1:
        return matches[0]
    candidates = matches or rows
    raise Invalid(f"Which format: " + "; ".join(f"{r['product']} {r['name']}" for r in candidates[:8]) + "?", "format",
                  [{"product": r["product"], "format": r["name"]} for r in candidates[:8]])


async def order_row(label: str) -> dict[str, Any]:
    found = await resolve.resolve("sales_order", label)
    return await db.fetch_one("records", """SELECT so.id, so.number, so.status, so.customer_id, so.premises_id, so.destination_kind, so.ordered_on, so.requested_on,
                                                   so.customer_reference, so.notes, c.name AS customer
                                            FROM app.sales_orders so JOIN app.customers c ON c.id = so.customer_id WHERE so.id = %s""", (found["id"],))


async def activity_after(activity_id: int) -> dict[str, Any]:
    row = await db.fetch_one("activity", "SELECT entity_id, after FROM app.activity_log WHERE id = %s", (activity_id,))
    return row or {}


@action(ActionMeta(
    name="order_create", title="Enter a customer order", doc_actions=["order_create"],
    endpoint="POST /orders/save (then POST /orders/{id}/confirm)", role="sales", confirm="never",
    undo_kind="delete_row", undo_how="POST /orders/{id}/cancel with a reason (an order is cancelled, never deleted)",
    events=["order_created"], refresh=ORDER_REFRESH))
async def order_create(
    call: Call,
    customer: Annotated[str, Field(description="Customer name as said, e.g. \"Joe's Taproom\"")],
    due_on: Annotated[date, Field(description="The date the customer wants it, ISO YYYY-MM-DD (resolve 'the 15th', 'next Friday' from today)")],
    lines: Annotated[list[OrderLine], Field(min_length=1, max_length=20, description="One entry per product format ordered")],
    reference: Annotated[str | None, Field(default=None, max_length=60, description="The customer's PO number, if said")] = None,
    ordered_on: Annotated[date | None, Field(default=None, description="When the order was placed; omit for today")] = None,
    confirm: Annotated[bool, Field(description="Confirm the order at once so it counts as demand (default). False keeps it a draft.")] = True,
    note: Note = None,
) -> dict[str, Any]:
    """Enter a customer order: "Joe's Taproom wants 4 half barrels of Hill Dry on the 15th", "Green Mountain ordered 20 cases
    of cans for next Friday, PO 7781".

    Executes immediately: the order is saved and confirmed (it then counts as firm demand in packaging and planning) and can
    be undone (the order is cancelled). Units are kegs or cans: convert cases with the format's units per case. A blank
    price uses the format's list price. To add to an existing order use order_add_line. <<not_for>>

    <<terminal>>"""
    cust = await resolve.resolve("customer", customer)
    crow = await db.fetch_one("records", "SELECT default_destination FROM app.customers WHERE id = %s", (cust["id"],))
    destination = crow["default_destination"] if crow and crow["default_destination"] in ("tax_paid_sale", "taproom_transfer", "in_bond_transfer", "export") else "tax_paid_sale"
    placed = ordered_on or date.today()
    if due_on < placed:
        raise Invalid(f"The due date {due_on} is before the order date {placed}.", "due_on")
    fields: list[tuple[str, Any]] = [("customer_id", cust["id"]), ("destination_kind", destination), ("premises_id", await default_premises()),
                                     ("ordered_on", placed.isoformat()), ("requested_on", due_on.isoformat()), ("customer_reference", reference or ""), ("notes", note or "")]
    described = []
    for n, line in enumerate(lines, start=1):
        fmt_row = await find_format(line.format, line.product)
        price = line.unit_price if line.unit_price is not None else fmt_row["price"]
        fields += [(f"lines[n{n}][packaging_configuration_id]", fmt_row["id"]), (f"lines[n{n}][units_ordered]", line.units),
                   (f"lines[n{n}][unit_price]", "" if price is None else f"{price:.2f}")]
        described.append(f"{line.units} × {fmt_row['name']}")
    result = await perform(call, ACTIONS_META["order_create"], "/orders/save", fields,
                           done=f"Order for {cust['label']} due {due_on:%b %-d}: " + ", ".join(described),
                           field_map={"customer_id": "customer", "requested_on": "due_on", "customer_reference": "reference"})
    if result["status"] != "success":
        return result
    created = await activity_after(result["activity_id"])
    order_id = created.get("entity_id")
    number = (created.get("after") or {}).get("number", "")
    result["done"] = result["done"].replace("Order for", f"Order {number} for", 1)
    if confirm and order_id:
        confirmed = await perform(call, ActionMeta("order_confirm", "", [], "", "sales", "never", "none", "", ["order_confirmed"], ORDER_REFRESH),
                                  f"/orders/{order_id}/confirm", [], done="confirmed", undo_available=False)
        result["done"] += " — confirmed" if confirmed["status"] == "success" else " — saved as a draft (" + confirmed.get("message", "not confirmed") + ")"
        result["navigate"] = confirmed.get("navigate") or result["navigate"]
    else:
        result["done"] += " — saved as a draft"
    return result


@action(ActionMeta(
    name="order_add_line", title="Add a line to a customer order", doc_actions=["order_add_line"],
    endpoint="POST /orders/save (every line)", role="sales", confirm="always (no undo endpoint for a changed order)",
    undo_kind="none", undo_how="Edit the order on its screen to remove the line.", events=["order_updated"], refresh=ORDER_REFRESH))
async def order_add_line(
    call: Call,
    order: Annotated[str, Field(description="Order number (SO-00042) or the customer's PO reference; on an order screen use its record")],
    format: Annotated[str, Field(min_length=1, max_length=120, description="Package format as said")],
    units: Annotated[int, Field(ge=1, le=1_000_000, description="Units: kegs or cans")],
    product: Annotated[str | None, Field(default=None, max_length=120)] = None,
    unit_price: Annotated[float | None, Field(default=None, ge=0, le=100_000)] = None,
    confirmed: Confirmed = False,
) -> dict[str, Any]:
    """Add a product line to a draft or confirmed customer order: "add 2 more half barrels to SO-00042".

    Asks for confirmation first. <<confirm>> <<not_for>>

    <<terminal>>"""
    o = await order_row(order)
    if o["status"] not in ("draft", "confirmed"):
        raise Invalid(f"{o['number']} is {o['status'].replace('_', ' ')}; only draft or confirmed orders can change.", "order")
    fmt_row = await find_format(format, product)
    if not confirmed:
        return needs_confirmation(f"Add {units} × {fmt_row['name']} to {o['number']} ({o['customer']})?")
    lines = await db.fetch_all("records", "SELECT id, packaging_configuration_id, units_ordered, unit_price::float AS unit_price, notes FROM app.sales_order_lines WHERE sales_order_id = %s ORDER BY line_no", (o["id"],))
    fields: list[tuple[str, Any]] = [("id", o["id"]), ("customer_id", o["customer_id"]), ("destination_kind", o["destination_kind"]), ("premises_id", o["premises_id"]),
                                     ("ordered_on", o["ordered_on"].isoformat()), ("requested_on", o["requested_on"].isoformat()),
                                     ("customer_reference", o["customer_reference"] or ""), ("notes", o["notes"] or "")]
    for n, line in enumerate(lines, start=1):
        fields += [(f"lines[n{n}][id]", line["id"]), (f"lines[n{n}][packaging_configuration_id]", line["packaging_configuration_id"]),
                   (f"lines[n{n}][units_ordered]", line["units_ordered"]), (f"lines[n{n}][unit_price]", "" if line["unit_price"] is None else f"{line['unit_price']:.2f}"),
                   (f"lines[n{n}][notes]", line["notes"] or "")]
    n = len(lines) + 1
    price = unit_price if unit_price is not None else fmt_row["price"]
    fields += [(f"lines[n{n}][packaging_configuration_id]", fmt_row["id"]), (f"lines[n{n}][units_ordered]", units), (f"lines[n{n}][unit_price]", "" if price is None else f"{price:.2f}")]
    return await perform(call, ACTIONS_META["order_add_line"], "/orders/save", fields, done=f"Added {units} × {fmt_row['name']} to {o['number']}",
                         undo_available=False, undo_note="Edit the order on its screen to remove the line.")


async def _order_step(call: Call, name: str, label: str, verb: str, allowed: tuple[str, ...], fields: list[tuple[str, Any]], done: str,
                      undo_available: bool = False, undo_note: str | None = None) -> dict[str, Any]:
    o = await order_row(label)
    if o["status"] not in allowed:
        raise Invalid(f"{o['number']} is {o['status'].replace('_', ' ')}; it cannot be {done}.", "order")
    return await perform(call, ACTIONS_META[name], f"/orders/{o['id']}/{verb}", fields, done=f"Order {o['number']} ({o['customer']}) {done}",
                         undo_available=undo_available, undo_note=undo_note)


OrderLabel = Annotated[str, Field(description="Order number (SO-00042) or the customer's PO reference; on an order screen use its record")]


@action(ActionMeta(
    name="order_confirm", title="Confirm a customer order", doc_actions=["order_confirm"], endpoint="POST /orders/{id}/confirm", role="sales",
    confirm="never", undo_kind="none", undo_how="No endpoint returns an order to draft; cancel it instead.", events=["order_confirmed"], refresh=ORDER_REFRESH))
async def order_confirm(call: Call, order: OrderLabel) -> dict[str, Any]:
    """Confirm a draft customer order so it counts as firm demand ("confirm SO-00042", "that order is firm").

    Executes immediately. <<not_for>>

    <<terminal>>"""
    return await _order_step(call, "order_confirm", order, "confirm", ("draft",), [], "confirmed", undo_note="Cancel the order instead.")


@action(ActionMeta(
    name="order_cancel", title="Cancel a customer order", doc_actions=["order_cancel"], endpoint="POST /orders/{id}/cancel", role="sales",
    confirm="always", undo_kind="none", undo_how="No endpoint reopens a cancelled order; enter it again.", events=["order_cancelled"], refresh=ORDER_REFRESH,
    destructive=True))
async def order_cancel(call: Call, order: OrderLabel, reason: Annotated[str, Field(min_length=1, max_length=500, description="Why, in the user's words")],
                       confirmed: Confirmed = False) -> dict[str, Any]:
    """Cancel a draft or confirmed customer order with nothing shipped or packaged for it ("cancel Joe's order, they called it off").

    Asks for confirmation first. <<confirm>> <<not_for>>

    <<terminal>>"""
    o = await order_row(order)
    if not confirmed:
        return needs_confirmation(f"Cancel order {o['number']} for {o['customer']} ({reason})? This cannot be undone.")
    return await _order_step(call, "order_cancel", order, "cancel", ("draft", "confirmed"), [("cancel_reason", reason)], "cancelled")


@action(ActionMeta(
    name="order_close", title="Close a customer order", doc_actions=["order_close"], endpoint="POST /orders/{id}/close", role="sales",
    confirm="always", undo_kind="none", undo_how="No endpoint reopens a closed order.", events=["order_closed"], refresh=ORDER_REFRESH))
async def order_close(call: Call, order: OrderLabel, confirmed: Confirmed = False) -> dict[str, Any]:
    """Close a customer order; whatever has not shipped is closed short ("close SO-00042, they don't want the rest").

    Asks for confirmation first. <<confirm>> <<not_for>>

    <<terminal>>"""
    o = await order_row(order)
    if not confirmed:
        return needs_confirmation(f"Close order {o['number']} for {o['customer']}? Units not shipped are closed short.")
    return await _order_step(call, "order_close", order, "close", ("confirmed", "in_fulfillment", "shipped"), [], "closed")


@action(ActionMeta(
    name="order_package", title="Create packaging runs for an order", doc_actions=["order_package"], endpoint="POST /orders/package-save (use_defaults)",
    role="sales", confirm="never", undo_kind="delete_row", undo_how="POST /orders/package-undo with the draft runs",
    events=["order_packaging_runs_created"], refresh=["packagingRunsChanged", "ordersChanged"]))
async def order_package(call: Call, order: OrderLabel) -> dict[str, Any]:
    """Create the draft packaging runs that fill a customer order ("package Joe's order", "set up the canning run for SO-00042").

    Uses the Package screen's suggestions: units still to package after stock on hand and earlier orders, the oldest released
    batch whose tank holds enough, today. Executes immediately and can be undone (the drafts are deleted). Formats with nothing
    to package or no batch ready are skipped and named. Production still enters ABV and CO2 and posts each run. To choose a
    different batch or units, navigate to the order-package screen instead. <<not_for>>

    <<terminal>>"""
    o = await order_row(order)
    if o["status"] not in ("confirmed", "in_fulfillment"):
        raise Invalid(f"{o['number']} is {o['status'].replace('_', ' ')}; only confirmed orders are packaged.", "order")
    lines = await db.fetch_all("records", "SELECT id FROM app.sales_order_lines WHERE sales_order_id = %s", (o["id"],))
    result = await perform(call, ACTIONS_META["order_package"], "/orders/package-save",
                           [("order_id", o["id"]), ("use_defaults", "1")] + [("line_ids[]", line["id"]) for line in lines],
                           done=f"Draft packaging runs for {o['number']}")
    if result["status"] == "success":
        after = (await activity_after(result["activity_id"])).get("after") or {}
        runs = after.get("packaging_runs", [])
        result["done"] = f"Draft packaging {'run' if len(runs) == 1 else 'runs'} {', '.join(runs)} for {o['number']}; production enters ABV and CO2 and posts"
        if after.get("skipped"):
            result["done"] += ". Not packaged: " + "; ".join(after["skipped"])
    return result


@action(ActionMeta(
    name="order_ship", title="Ship a customer order", doc_actions=["order_ship"], endpoint="POST /orders/{id}/ship", role="sales",
    confirm="never", undo_kind="delete_row", undo_how="POST /removals/{id}/delete (the draft shipment)",
    events=["order_shipment_created"], refresh=["removalsChanged", "ordersChanged"]))
async def order_ship(call: Call, order: OrderLabel) -> dict[str, Any]:
    """Draft the shipment (a removal) for what is on hand for a customer order ("ship SO-00042", "send Joe's order").

    Takes released stock first-packaged first from the location holding the most, kegs filled with the lot; units not on hand
    stay open. Executes immediately and can be undone (the draft is deleted). Compliance reviews and posts the removal; posting
    is removal_post. <<not_for>>

    <<terminal>>"""
    o = await order_row(order)
    result = await _order_step(call, "order_ship", order, "ship", ("confirmed", "in_fulfillment"), [], "drafted for shipping", undo_available=True)
    if result["status"] == "success":
        after = (await activity_after(result["activity_id"])).get("after") or {}
        units = sum((after.get("units") or {}).values()) if isinstance(after.get("units"), dict) else None
        result["done"] = f"Draft shipment {after.get('removal', '')} for {o['number']}" + (f": {units} units" if units else "") + "; post it to ship"
    return result


@action(ActionMeta(
    name="order_from_standing", title="Order a standing order's date", doc_actions=["order_from_standing"], endpoint="POST /orders/standing/{id}/occurrence",
    role="sales", confirm="never", undo_kind="delete_row", undo_how="POST /orders/{id}/cancel with a reason", events=["order_created_from_standing"],
    refresh=ORDER_REFRESH))
async def order_from_standing(
    call: Call,
    standing_order: Annotated[str, Field(description="Standing order number (STO-0001) or the customer's name")],
    on: Annotated[date, Field(description="The delivery date of the standing order to turn into an order, ISO YYYY-MM-DD")],
) -> dict[str, Any]:
    """Turn one date of a standing order into a confirmed customer order ("make the taproom's Friday order", "order STO-0001 for
    the 16th"). The date must be one of its scheduled dates. Executes immediately and can be undone (the order is cancelled). <<not_for>>

    <<terminal>>"""
    try:
        s = await resolve.resolve("standing_order", standing_order)
    except resolve.ResolveError:
        cust = await resolve.resolve("customer", standing_order)
        rows = await db.fetch_all("records", "SELECT id, number FROM app.standing_orders WHERE customer_id = %s AND active", (cust["id"],))
        if len(rows) != 1:
            raise Invalid(f"{cust['label']} has {len(rows)} running standing orders; say which (" + ", ".join(r["number"] for r in rows) + ").", "standing_order")
        s = {"id": rows[0]["id"], "label": rows[0]["number"]}
    return await perform(call, ACTIONS_META["order_from_standing"], f"/orders/standing/{s['id']}/occurrence", [("occurs_on", on.isoformat())],
                         done=f"Order from {s['label']} for {on:%b %-d}")


@action(ActionMeta(
    name="standing_order_deactivate", title="Pause or resume a standing order", doc_actions=["standing_order_deactivate"],
    endpoint="POST /orders/standing/{id}/active", role="sales", confirm="never", undo_kind="restore_prior", undo_how="POST /orders/standing/{id}/active with active=1",
    events=["standing_order_deactivated", "standing_order_updated"], refresh=ORDER_REFRESH))
async def standing_order_deactivate(
    call: Call,
    standing_order: Annotated[str, Field(description="Standing order number (STO-0001)")],
    resume: Annotated[bool, Field(description="True to resume a paused standing order")] = False,
) -> dict[str, Any]:
    """Pause a standing order (its dates stop counting as demand) or resume it ("pause the taproom's weekly order").

    Executes immediately and can be undone. <<not_for>>

    <<terminal>>"""
    s = await resolve.resolve("standing_order", standing_order)
    return await perform(call, ACTIONS_META["standing_order_deactivate"], f"/orders/standing/{s['id']}/active", [("active", "1" if resume else "0")],
                         done=f"Standing order {s['label']} {'resumed' if resume else 'paused'}", events=["standing_order_updated" if resume else "standing_order_deactivated"])
