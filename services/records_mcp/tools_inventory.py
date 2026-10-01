"""Inventory tools (R9 to R12, R14, R15, R17)."""
from __future__ import annotations

import datetime as dt
from typing import Literal

from pydantic import Field, model_validator

from common import db

from . import fmt, queries as Q
from .registry import Input, Paged, ToolFailure, page, records_tool
from .resolve import echo, resolve, resolve_opt, rid
from .tools_receiving import ITEM_CLASSES, check_range, is_fruit, std

QUALITY = Literal["quarantine", "hold", "released", "rejected"]


def _qty(r: dict, field: str) -> dict | None:
    return fmt.q(r[field], r["base_unit_code"], fruit=is_fruit(r), unit_volume_l=r.get("unit_volume_l"))


class OnHandInput(Paged):
    item: str | None = Field(None, max_length=120, description="Item code or name.")
    item_class: ITEM_CLASSES | None = Field(None, description="Restrict to one item class.")
    location: str | None = Field(None, max_length=120, description="Location name, e.g. 'Cold room'.")
    lot_number: str | None = Field(None, max_length=60, description="One lot.")
    quality_status: QUALITY | None = Field(None, description="Only lots in this quality status.")
    include_zero: bool = Field(False, description="Include balances that are now zero.")


@records_tool("inventory_on_hand", "Stock on hand by lot and location",
              """Call for how much of an item (or class, location or lot) is on hand, by lot and location (R9). Each row: lot,
              quality status, expiry, location and tax state, on hand, allocated and available (base units plus lb/gal; finished
              goods also in gallons), and value. 'totals' sums each item across all matching rows, released stock separately.""")
async def inventory_on_hand(p: OnHandInput) -> dict:
    item = await resolve_opt("item", p.item)
    location = await resolve_opt("location", p.location)
    lot = await resolve_opt("lot", p.lot_number)
    args = std(p, include_zero=p.include_zero, item_id=rid(item), item_class=p.item_class, location_id=rid(location), lot_id=rid(lot),
               quality_status=p.quality_status)
    rows = await db.fetch_all("records", Q.ON_HAND, args)
    totals = await db.fetch_all("records", Q.ON_HAND_TOTALS, args)
    out = [fmt.drop_none({
        "item_code": r["item_code"], "item_name": r["item_name"], "lot_number": r["lot_number"], "quality_status": r["quality_status"],
        "expires_on": r["expires_on"], "received_on": r["received_on"], "location": r["location_name"], "tax_state": r["tax_state"],
        "on_hand": _qty(r, "qty_on_hand"), "allocated": _qty(r, "qty_allocated") if r["qty_allocated"] else None,
        "available": _qty(r, "qty_available"), "value": fmt.money(r["value"])}) for r in rows]
    tot = [fmt.drop_none({
        "item_code": t["item_code"], "item_name": t["item_name"], "lots": t["lots"],
        "on_hand": fmt.q(t["qty_on_hand"], t["base_unit_code"], fruit=is_fruit(t)),
        "released": fmt.q(t["qty_released"] or 0, t["base_unit_code"], fruit=is_fruit(t)),
        "allocated": fmt.q(t["qty_allocated"], t["base_unit_code"]) if t["qty_allocated"] else None,
        "packaged_volume": fmt.liters(t["volume_l"]) if t["volume_l"] else None, "value": fmt.money(t["value"])}) for t in totals]
    result = page(out, p, resolved=echo(item=item, location=location, lot=lot), totals=tot)
    if not out:
        result["note"] = "No stock matches. Check the item with inventory_movements, or include_zero=true to see emptied balances."
    return result


class AvailableInput(Paged):
    item: str | None = Field(None, max_length=120, description="Item code or name; omit for every item with stock or demand.")
    item_class: ITEM_CLASSES | None = Field(None, description="Restrict to one item class.")


@records_tool("inventory_available_after_orders", "Available after production orders",
              """Call for how much is available after what the next batches need (R10). Per item: on hand, released and
              unallocated, allocated to open production orders (with the orders), recipe requirements of planned orders not yet
              allocated, net available (negative means short) and quantity on order from suppliers.""")
async def inventory_available_after_orders(p: AvailableInput) -> dict:
    item = await resolve_opt("item", p.item)
    rows = await db.fetch_all("records", Q.AVAILABLE_AFTER_ORDERS, std(p, item_id=rid(item), item_class=p.item_class))
    out = []
    for r in rows:
        u, fr = r["base_unit_code"], is_fruit(r)
        out.append(fmt.drop_none({
            "item_code": r["item_code"], "item_name": r["item_name"], "on_hand": fmt.q(r["on_hand"], u, fruit=fr),
            "released_available": fmt.q(r["released_available"], u, fruit=fr), "allocated_to_orders": fmt.q(r["allocated_to_orders"], u, fruit=fr),
            "planned_requirements": fmt.q(r["planned_requirements"], u, fruit=fr), "net_available": fmt.q(r["net_available"], u, fruit=fr),
            "short": float(r["net_available"]) < 0, "on_order": fmt.q(r["qty_on_order"], u, fruit=fr),
            "allocated_orders": r["allocated_orders"], "planned_orders": r["planned_orders"]}))
    return page(out, p, resolved=echo(item=item),
                note="Allocations count for planned, released and in-progress orders; planned orders without allocations count at recipe quantity.")


class PickOrderInput(Paged):
    item: str | None = Field(None, max_length=120, description="Item code or name. Required unless expiring_within_days is given.")
    qty: float | None = Field(None, gt=0, description="Quantity needed; the pick list stops when it is covered.")
    unit: str | None = Field(None, max_length=10, description="Unit of qty (e.g. lb, gal, kg, L); default the item's base unit.")
    expiring_within_days: int | None = Field(None, ge=0, le=3650, description="Only lots expiring within this many days (any item if item is omitted).")
    location: str | None = Field(None, max_length=120, description="Only stock at this location.")

    @model_validator(mode="after")
    def one_of(self):
        if not self.item and self.expiring_within_days is None:
            raise ValueError("Give item, or expiring_within_days to list expiring lots of every item.")
        return self


@records_tool("inventory_pick_order", "First-expiry-first-out pick list",
              """Call for which lots to use first and which are expiring (R11). Lists lots with stock in FEFO order (expiry, then
              received date), released lots first, with days to expiry and available quantity. With qty, marks how much to take
              from each lot until the need is covered; lots not released are listed but never picked.""")
async def inventory_pick_order(p: PickOrderInput) -> dict:
    item = await resolve_opt("item", p.item)
    location = await resolve_opt("location", p.location)
    rows = await db.fetch_all("records", Q.PICK_ORDER, std(p, item_id=rid(item), location_id=rid(location), within=p.expiring_within_days))
    need = None
    if p.qty is not None:
        if not rows:
            raise ToolFailure("No stock of that item to pick from.")
        base_unit = rows[0]["base_unit_code"]
        unit = p.unit or base_unit
        if fmt.factor(unit) is None or fmt.dimension(unit) != fmt.dimension(base_unit):
            raise ToolFailure(f"Unit '{unit}' cannot be converted to the item's base unit {base_unit}; use a {fmt.dimension(base_unit)} unit.")
        need = p.qty * fmt.factor(unit) / (fmt.factor(base_unit) or 1)
    remaining = need
    out = []
    for r in rows:
        row = fmt.drop_none({
            "item_code": r["item_code"], "lot_number": r["lot_number"], "quality_status": r["quality_status"], "expires_on": r["expires_on"],
            "days_to_expiry": r["days_to_expiry"], "received_on": r["received_on"], "location": r["location_name"],
            "available": _qty(r, "qty_available")})
        if r["quality_status"] != "released":
            row["usable"] = False
        elif remaining is not None and remaining > 0:
            take = min(remaining, float(r["qty_available"]))
            if take > 0:
                row["pick"] = fmt.q(take, r["base_unit_code"], fruit=is_fruit(r))
                remaining -= take
        out.append(row)
    extra = {"resolved": echo(item=item, location=location)}
    if need is not None:
        extra["needed"] = fmt.q(need, rows[0]["base_unit_code"], fruit=is_fruit(rows[0]))
        extra["covered"] = remaining is not None and remaining <= 1e-9
        if not extra["covered"]:
            extra["still_short"] = fmt.q(remaining, rows[0]["base_unit_code"], fruit=is_fruit(rows[0]))
    return page(out, p, **extra)


class BelowReorderInput(Paged):
    item_class: ITEM_CLASSES | None = Field(None, description="Restrict to one item class.")


@records_tool("inventory_below_reorder", "Items below reorder point",
              """Call for what is below its reorder point (R12). Each item: available, on order, reorder point, shortfall,
              shortfall after what is already on order, and a suggested order up to the maximum when one is set.""")
async def inventory_below_reorder(p: BelowReorderInput) -> dict:
    rows = await db.fetch_all("records", Q.BELOW_REORDER, std(p, item_class=p.item_class))
    conf = await db.fetch_one("records", Q.REORDER_CONFIGURED, {"item_class": p.item_class})
    out = []
    for r in rows:
        u, fr = r["base_unit_code"], is_fruit(r)
        out.append(fmt.drop_none({
            "item_code": r["item_code"], "item_name": r["item_name"], "item_class": r["item_class"], "available": fmt.q(r["qty_available"], u, fruit=fr),
            "on_order": fmt.q(r["qty_on_order"], u, fruit=fr), "reorder_point": fmt.q(r["reorder_point_base"], u, fruit=fr),
            "shortfall": fmt.q(r["shortfall"], u, fruit=fr), "shortfall_after_on_order": fmt.q(r["shortfall_after_on_order"], u, fruit=fr),
            "suggested_order_to_max": fmt.q(r["suggested_order_to_max"], u, fruit=fr)}))
    return page(out, p, items_with_reorder_point=conf["items_with_reorder_point"], active_items=conf["active_items"])


class AdjustmentsInput(Paged):
    date_from: dt.date | None = Field(None, description="Adjusted on or after (ISO date). For 'this month' pass the first of the month.")
    date_to: dt.date | None = Field(None, description="Adjusted on or before (ISO date).")
    location: str | None = Field(None, max_length=120, description="Location name.")
    reason: str | None = Field(None, max_length=60, description="Reason code or name, e.g. DAMAGE, 'expired'.")
    status: Literal["draft", "pending_approval", "posted", "cancelled"] | None = Field(None, description="Only this status (default all).")


@records_tool("inventory_adjustments", "Inventory adjustments and reasons",
              """Call for what adjustments were made in a period and why (R14). Each adjustment: number, status, when, location,
              reason code and name, notes, who created, approved and posted it, and its lines (item, lot, quantity change, value).
              Newest first.""")
async def inventory_adjustments(p: AdjustmentsInput) -> dict:
    check_range(p.date_from, p.date_to)
    location = await resolve_opt("location", p.location)
    reason = await resolve_opt("reason", p.reason)
    rows = await db.fetch_all("records", Q.ADJUSTMENTS, std(p, location_id=rid(location), reason_id=rid(reason), status=p.status,
                                                         date_from=p.date_from, date_to=p.date_to))
    out = []
    for r in rows:
        d = fmt.drop_none(dict(r))
        d["lines"] = [fmt.drop_none({**l, "qty_delta": fmt.q(l["qty_delta_base"], l["unit"], fruit=l["item_class"] == "fruit")}) for l in (r["lines"] or [])]
        for l in d["lines"]:
            l.pop("qty_delta_base", None)
        out.append(d)
    return page(out, p, resolved=echo(location=location, reason=reason))


class LastCountInput(Input):
    location: str = Field(..., min_length=2, max_length=120, description="Location name, e.g. 'Cold room'.")


@records_tool("inventory_last_count", "Last stock count at a location",
              """Call for when a location was last counted and what the variance was (R15). Returns the latest count (approved
              first), who started and approved it, every line's expected, counted and variance with value, the ledger corrections it
              posted, and up to five earlier counts.""")
async def inventory_last_count(p: LastCountInput) -> dict:
    location = await resolve("location", p.location)
    counts = await db.fetch_all("records", Q.COUNTS_AT_LOCATION, {"location_id": location["id"]})
    if not counts:
        raise ToolFailure(f"{location['label']} has never been counted.")
    last = counts[0]
    lines = await db.fetch_all("records", Q.COUNT_LINES, {"count_id": last["id"]})
    corrections = await db.fetch_all("records", Q.COUNT_CORRECTIONS, {"count_id": last["id"]})
    out_lines = []
    for r in lines:
        u, fr = r["base_unit_code"], is_fruit(r)
        out_lines.append(fmt.drop_none({
            "item_code": r["item_code"], "item_name": r["item_name"], "lot_number": r["lot_number"], "expected": fmt.q(r["qty_expected_base"], u, fruit=fr),
            "counted": fmt.q(r["qty_counted_base"], u, fruit=fr), "variance": fmt.q(r["variance_base"], u, fruit=fr), "variance_value": r["variance_value"],
            "counted_by": r["counted_by"], "counted_at": r["counted_at"], "note": r["note"]}))
    header = fmt.drop_none({k: v for k, v in last.items() if k != "id"})
    note = None
    if last["status"] != "approved":
        note = (f"No approved count exists for {location['label']}; this is the latest one ({last['number']}, {last['status']}), "
                "so its variances were never posted as corrections.")
    return {
        "note": note,
        "resolved": echo(location=location), "count": header, "lines": out_lines,
        "total_variance_value": fmt.money(sum(float(r["variance_value"] or 0) for r in lines)),
        "lines_with_variance": sum(1 for r in lines if r["variance_base"] not in (None, 0)),
        "corrections": [fmt.drop_none({**dict(c), "qty": fmt.q(c["qty_base"], c["base_unit_code"])}) for c in corrections],
        "earlier_counts": [fmt.drop_none({k: v for k, v in c.items() if k in ("number", "status", "started_at", "approved_at", "approved_by")}) for c in counts[1:]],
    }


class SlowMoversInput(Paged):
    months: int = Field(6, ge=1, le=60, description="Window in months with no outbound movement.")
    item_class: ITEM_CLASSES | None = Field(None, description="Restrict to one item class.")


@records_tool("inventory_slow_movers", "Items that have not moved",
              """Call for which items have not moved in N months (R17). Items in stock for the whole window with no issue, removal,
              packaging use, destruction or transfer out during it, with on hand, value, last outbound and last movement dates.""")
async def inventory_slow_movers(p: SlowMoversInput) -> dict:
    rows = await db.fetch_all("records", Q.SLOW_MOVERS, std(p, months=p.months, item_class=p.item_class))
    out = [fmt.drop_none({
        "item_code": r["item_code"], "item_name": r["item_name"], "item_class": r["item_class"], "on_hand": fmt.q(r["on_hand"], r["base_unit_code"], fruit=is_fruit(r)),
        "value": fmt.money(r["value"]), "first_received_at": r["first_inbound_at"], "last_outbound_at": r["last_outbound_at"],
        "last_movement_at": r["last_movement_at"], "days_without_outbound": r["days_without_outbound"]}) for r in rows]
    res = page(out, p, months=p.months)
    if not out:
        res["note"] = f"No item has been in stock for {p.months} months without an outbound movement."
    return res


class MovementsInput(Paged):
    item: str | None = Field(None, max_length=120, description="Item code or name.")
    item_class: ITEM_CLASSES | None = Field(None, description="Restrict to one item class.")
    lot_number: str | None = Field(None, max_length=60, description="One lot.")
    location: str | None = Field(None, max_length=120, description="Location name.")
    txn_type: Literal["receipt", "issue", "transfer_out", "transfer_in", "adjustment", "count_correction", "production_output",
                      "packaging_output", "removal", "return", "destruction", "reversal"] | None = Field(None, description="Ledger transaction type.")
    date_from: dt.date | None = Field(None, description="On or after (ISO date).")
    date_to: dt.date | None = Field(None, description="On or before (ISO date).")


@records_tool("inventory_movements", "Inventory ledger movements",
              """Call for the raw inventory ledger: every receipt, issue, transfer, adjustment, count correction, packaging output,
              removal or return matching item, lot, location, type and dates, newest first (long tail of R14 and R17). Each row has
              the signed quantity with display units, value, TTB category, the source document number, counterparty, reason and
              actor.""")
async def inventory_movements(p: MovementsInput) -> dict:
    check_range(p.date_from, p.date_to)
    item = await resolve_opt("item", p.item)
    lot = await resolve_opt("lot", p.lot_number)
    location = await resolve_opt("location", p.location)
    rows = await db.fetch_all("records", Q.MOVEMENTS, std(p, item_id=rid(item), lot_id=rid(lot), location_id=rid(location), txn_type=p.txn_type,
                                                       item_class=p.item_class, date_from=p.date_from, date_to=p.date_to))
    out = [fmt.drop_none({
        "occurred_at": r["occurred_at"], "txn_type": r["txn_type"], "item_code": r["item_code"], "lot_number": r["lot_number"],
        "location": r["location_name"], "tax_state": r["tax_state"], "qty": _qty(r, "qty_base"), "value": r["value"],
        "ttb_category": None if r["ttb_category"] == "none" else r["ttb_category"], "reference": r["reference_kind"],
        "reference_number": r["reference_number"], "counterparty": r["counterparty"], "reason_code": r["reason_code"], "actor": r["actor"],
        "note": r["note"]}) for r in rows]
    return page(out, p, resolved=echo(item=item, lot=lot, location=location))
