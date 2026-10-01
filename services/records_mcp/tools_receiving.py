"""Receiving and purchasing tools (R1 to R8)."""
from __future__ import annotations

import datetime as dt
from typing import Literal

from pydantic import Field, model_validator

from common import db

from . import fmt, queries as Q
from .registry import Input, Paged, ToolFailure, page, records_tool
from .resolve import echo, resolve, resolve_opt, rid

ITEM_CLASSES = Literal["fruit", "juice", "yeast", "additive", "packaging", "consumable", "intermediate", "finished_good", "co_product", "returnable_asset"]


def std(p, **extra) -> dict:
    out = {"tz": fmt.tz()}
    if hasattr(p, "limit"):
        out.update(lim=p.limit + 1, off=p.offset)
    out.update(extra)
    return out


def check_range(date_from: dt.date | None, date_to: dt.date | None) -> None:
    if date_from and date_to and date_from > date_to:
        raise ToolFailure(f"date_from {date_from} is after date_to {date_to}; swap them.")


def is_fruit(row: dict) -> bool:
    return row.get("item_class") == "fruit"


class OpenOrdersInput(Paged):
    date_from: dt.date | None = Field(None, description="Expected on or after this date (ISO). For 'this week' pass Monday.")
    date_to: dt.date | None = Field(None, description="Expected on or before this date (ISO).")
    supplier: str | None = Field(None, max_length=120, description="Supplier name or fragment.")
    item: str | None = Field(None, max_length=120, description="Item code or name.")
    overdue_only: bool = Field(False, description="Only lines whose expected date has passed.")


@records_tool("receiving_open_orders", "Open purchase order lines",
              """Call for what is arriving and against which purchase order (R1) or which open orders are overdue (R5).
              Returns open and partial PO lines with supplier, item, ordered, received and outstanding quantities (base units
              plus pounds or gallons), expected date and days overdue. Filter by expected-date range, supplier or item.""")
async def receiving_open_orders(p: OpenOrdersInput) -> dict:
    check_range(p.date_from, p.date_to)
    supplier = await resolve_opt("supplier", p.supplier)
    item = await resolve_opt("item", p.item)
    rows = await db.fetch_all("records", Q.OPEN_ORDERS, std(p, supplier_id=rid(supplier), item_id=rid(item), date_from=p.date_from,
                                                          date_to=p.date_to, overdue_only=p.overdue_only))
    out = []
    for r in rows:
        u, fr = r["base_unit_code"], is_fruit(r)
        out.append(fmt.drop_none({
            "po_number": r["po_number"], "po_status": r["po_status"], "supplier": r["supplier_name"], "line_no": r["line_no"],
            "item_code": r["item_code"], "item_name": r["item_name"], "ordered": f"{r['qty_ordered']} {r['purchase_unit_code']}",
            "ordered_qty": fmt.q(r["qty_ordered_base"], u, fruit=fr), "received_qty": fmt.q(r["qty_received_base"], u, fruit=fr),
            "outstanding_qty": fmt.q(r["qty_outstanding_base"], u, fruit=fr), "ordered_on": r["ordered_on"], "expected_on": r["expected_on"],
            "overdue": r["overdue"], "days_overdue": r["days_overdue"]}))
    return page(out, p, today=fmt.today(), resolved=echo(supplier=supplier, item=item))


class ReceiptVsOrderInput(Input):
    receipt_number: str | None = Field(None, max_length=60, description="Goods receipt number such as GR-00001 (or delivery note reference).")
    po_number: str | None = Field(None, max_length=60, description="Purchase order number such as PO-00001: compares every receipt against it.")

    @model_validator(mode="after")
    def one_of(self):
        if not self.receipt_number and not self.po_number:
            raise ValueError("Give receipt_number or po_number.")
        return self


@records_tool("receiving_receipt_vs_order", "Receipt against purchase order",
              """Call when asked whether a delivery matched the order, or what was short, over, damaged or substituted (R2).
              Give a receipt number (one delivery) or a PO number (every delivery against it plus the PO lines still outstanding).
              Each line shows ordered, received, the difference, the discrepancy kind and note.""")
async def receiving_receipt_vs_order(p: ReceiptVsOrderInput) -> dict:
    receipt = await resolve_opt("receipt", p.receipt_number)
    po = await resolve_opt("po", p.po_number)
    rows = await db.fetch_all("records", Q.RECEIPT_LINES, {"receipt_id": rid(receipt), "po_id": rid(po)})
    lines = []
    for r in rows:
        u, fr = r["base_unit_code"], is_fruit(r)
        diff = None if r["qty_ordered_base"] is None else float(r["qty_base"]) - float(r["qty_ordered_base"])
        lines.append(fmt.drop_none({
            "receipt_number": r["receipt_number"], "receipt_status": r["receipt_status"], "received_at": r["received_at"],
            "supplier": r["supplier_name"], "po_number": r["po_number"], "line_no": r["line_no"], "po_line_no": r["po_line_no"],
            "item_code": r["item_code"], "item_name": r["item_name"],
            "ordered": None if r["qty_ordered"] is None else f"{r['qty_ordered']} {r['po_unit']}",
            "ordered_qty": fmt.q(r["qty_ordered_base"], u, fruit=fr),
            "received": f"{r['qty_received']} {r['purchase_unit_code']}", "received_qty": fmt.q(r["qty_base"], u, fruit=fr),
            "difference_vs_ordered": fmt.q(diff, u, fruit=fr) if r["receipt_number"] and po is None else None,
            "po_line_received_total": fmt.q(r["po_line_received_base"], u, fruit=fr), "po_line_status": r["po_line_status"],
            "discrepancy": r["discrepancy_kind"], "discrepancy_note": r["discrepancy_note"], "lot_number": r["lot_number"],
            "received_by": r["received_by"]}))
    out: dict = {"resolved": echo(receipt=receipt, purchase_order=po), "receipt_lines": lines,
                 "discrepancies": [l for l in lines if l.get("discrepancy") not in (None, "none")]}
    if po:
        po_lines = await db.fetch_all("records", Q.PO_LINES, {"po_id": po["id"]})
        out["po_lines"] = [fmt.drop_none({
            "line_no": r["line_no"], "item_code": r["item_code"], "item_name": r["item_name"], "ordered": f"{r['qty_ordered']} {r['purchase_unit_code']}",
            "ordered_qty": fmt.q(r["qty_ordered_base"], r["base_unit_code"], fruit=is_fruit(r)),
            "received_qty": fmt.q(r["qty_received_base"], r["base_unit_code"], fruit=is_fruit(r)),
            "outstanding_qty": fmt.q(r["qty_outstanding_base"], r["base_unit_code"], fruit=is_fruit(r)),
            "status": r["line_status"], "close_reason": r["close_reason"]}) for r in po_lines]
    if not lines and not out.get("po_lines"):
        raise ToolFailure("That receipt has no lines yet (it may still be a draft with nothing entered).")
    return out


class ReceiptLotsInput(Input):
    receipt_number: str = Field(..., min_length=2, max_length=60, description="Goods receipt number such as GR-00001.")


@records_tool("receiving_receipt_lots", "Lots created by a receipt",
              """Call for which lots came in on a delivery and whether the certificate of analysis is on file (R3).
              Returns each receipt line's lot number, supplier lot, expiry, quality status, CoA records and files, captured lot
              attributes (variety, Brix, pH ...) and the weigh tag for fruit.""")
async def receiving_receipt_lots(p: ReceiptLotsInput) -> dict:
    receipt = await resolve("receipt", p.receipt_number)
    header = await db.fetch_one("records", Q.RECEIPT_HEADER, {"receipt_id": receipt["id"]})
    rows = await db.fetch_all("records", Q.RECEIPT_LOTS, {"receipt_id": receipt["id"]})
    lots = []
    for r in rows:
        lots.append(fmt.drop_none({
            "line_no": r["line_no"], "item_code": r["item_code"], "item_name": r["item_name"], "lot_number": r["lot_number"],
            "supplier_lot_number": r["supplier_lot_number"], "expires_on": r["expires_on"], "quality_status": r["quality_status"],
            "qty": fmt.q(r["qty_base"], r["base_unit_code"], fruit=is_fruit(r)),
            "has_coa": bool(r["certificates"] or r["coa_files"]), "certificates": r["certificates"], "coa_files": r["coa_files"],
            "attributes": r["attributes"], "weigh_tag": r["weigh_tag"]}))
    return {"receipt": fmt.drop_none(dict(header)), "lots": lots,
            "missing_coa": [l["lot_number"] for l in lots if l.get("lot_number") and not l["has_coa"]],
            "note": None if header["status"] == "posted" else "Lots are created when the receipt is posted; this receipt is " + header["status"] + "."}


class LotInput(Input):
    lot_number: str = Field(..., min_length=2, max_length=60, description="Lot number (L-261001-004), a fragment, or a supplier lot number.")


@records_tool("receiving_lot_status", "Lot quality status",
              """Call to answer whether a lot is released, in quarantine, on hold or rejected (R4). Returns the lot's item,
              supplier, dates, on-hand quantity, current quality status and the release decisions behind it (who, when, basis,
              override and reason), newest first.""")
async def receiving_lot_status(p: LotInput) -> dict:
    lot = await resolve("lot", p.lot_number)
    d = await db.fetch_one("records", Q.LOT_DETAIL, {"lot_id": lot["id"]})
    decisions = await db.fetch_all("records", Q.RELEASE_DECISIONS, {"kind": "lot", "target_id": lot["id"], "lim": 20})
    fr = d["item_class"] == "fruit"
    return {
        "lot": fmt.drop_none({"lot_number": d["lot_number"], "item_code": d["item_code"], "item_name": d["item_name"], "item_class": d["item_class"],
                              "quality_status": d["quality_status"], "usable": d["quality_status"] == "released",
                              "supplier": d["supplier_name"], "supplier_lot_number": d["supplier_lot_number"], "received_on": d["received_on"],
                              "produced_on": d["produced_on"], "expires_on": d["expires_on"], "source": d["source_kind"],
                              "on_hand": fmt.q(d["qty_on_hand"], d["base_unit_code"], fruit=fr, unit_volume_l=d["unit_volume_l"]),
                              "finished_from_batch": d["finished_from_batch"]}),
        "last_decision": fmt.drop_none(dict(decisions[0])) if decisions else None,
        "decisions": [fmt.drop_none(dict(r)) for r in decisions],
        "note": None if decisions else "No release decision is recorded; the status was set at creation (item default receipt status).",
    }


class PriceHistoryInput(Paged):
    item: str | None = Field(None, max_length=120, description="Item code or name, e.g. APL-GOLD or 'McIntosh'.")
    item_class: ITEM_CLASSES | None = Field(None, description="Instead of one item, every item of a class, e.g. 'fruit' for all apples.")
    supplier: str | None = Field(None, max_length=120, description="Supplier name or fragment.")
    date_from: dt.date | None = Field(None, description="Received on or after (ISO date).")
    date_to: dt.date | None = Field(None, description="Received on or before (ISO date).")
    group_by: Literal["month", "year", "receipt"] = Field("month", description="'year' compares seasons; 'receipt' lists each delivery.")

    @model_validator(mode="after")
    def one_of(self):
        if not self.item and not self.item_class:
            raise ValueError("Give item or item_class (e.g. item_class='fruit').")
        return self


@records_tool("receiving_price_history", "Purchase price history",
              """Call for what was paid for an item or class over time, e.g. per pound for apples this season versus last (R6).
              Groups posted receipt lines by month, year (season) or receipt, by supplier, with quantity, total cost and average
              unit cost per base unit and per display unit (per lb and per ton for fruit, per gal for liquids).""")
async def receiving_price_history(p: PriceHistoryInput) -> dict:
    check_range(p.date_from, p.date_to)
    item = await resolve_opt("item", p.item)
    supplier = await resolve_opt("supplier", p.supplier)
    rows = await db.fetch_all("records", Q.PRICE_HISTORY, std(p, item_id=rid(item), item_class=p.item_class, supplier_id=rid(supplier),
                                                            date_from=p.date_from, date_to=p.date_to, group_by=p.group_by))
    out = []
    for r in rows:
        fr, u = is_fruit(r), r["base_unit_code"]
        out.append(fmt.drop_none({
            "period": r["period"], "first_received_on": r["first_received_on"], "last_received_on": r["last_received_on"],
            "supplier": r["supplier_name"], "item_code": r["item_code"], "item_name": r["item_name"], "receipt_lines": r["receipt_lines"],
            "qty": fmt.q(r["qty_base"], u, fruit=fr), "total_cost": fmt.money(r["total_cost"]),
            "avg_unit_cost": fmt.unit_price(r["avg_cost_per_base"], u, fruit=fr),
            "min_unit_cost": fmt.unit_price(r["min_cost_per_base"], u, fruit=fr), "max_unit_cost": fmt.unit_price(r["max_cost_per_base"], u, fruit=fr)}))
    return page(out, p, resolved=echo(item=item, supplier=supplier), group_by=p.group_by)


class SupplierPerformanceInput(Paged):
    supplier: str | None = Field(None, max_length=120, description="One supplier; omit to rank all suppliers.")
    date_from: dt.date | None = Field(None, description="Receipts on or after (ISO date).")
    date_to: dt.date | None = Field(None, description="Receipts on or before (ISO date).")


@records_tool("receiving_supplier_performance", "Supplier delivery performance",
              """Call for which supplier delivers late or short most often (R7). Ranks suppliers by late receipts plus short and
              damaged lines in the period, with receipts, lines, over and substituted lines, worst days late, and open PO lines
              already overdue.""")
async def receiving_supplier_performance(p: SupplierPerformanceInput) -> dict:
    check_range(p.date_from, p.date_to)
    supplier = await resolve_opt("supplier", p.supplier)
    rows = await db.fetch_all("records", Q.SUPPLIER_PERFORMANCE, std(p, supplier_id=rid(supplier), date_from=p.date_from, date_to=p.date_to))
    out = []
    for r in rows:
        d = dict(r)
        d["late_rate_pct"] = round(100 * r["late_receipts"] / r["receipts"], 1) if r["receipts"] else None
        d["problem_line_rate_pct"] = round(100 * (r["short_lines"] + r["damaged_lines"]) / r["lines"], 1) if r["lines"] else None
        out.append(fmt.drop_none(d))
    return page(out, p, resolved=echo(supplier=supplier),
                note="Late means received after the PO line's (or PO's) expected date. Ranked by late receipts plus short and damaged lines.")


class FruitIntakeInput(Paged):
    season_year: int | None = Field(None, ge=2000, le=2100, description="Harvest year; omit for every season (grouped by year).")
    variety: str | None = Field(None, max_length=80, description="Variety fragment, e.g. 'Russet'.")
    orchard: str | None = Field(None, max_length=120, description="Orchard (or supplier) fragment.")


@records_tool("receiving_fruit_intake", "Fruit intake by variety and orchard",
              """Call for how many pounds (and tons) of each apple variety came in, from which orchards, at what Brix (R8).
              Groups posted weigh tags by season, variety and orchard with net weight, bins, weighted average Brix and the lot
              numbers created.""")
async def receiving_fruit_intake(p: FruitIntakeInput) -> dict:
    pat = lambda s: None if not s else f"%{s}%"
    rows = await db.fetch_all("records", Q.FRUIT_INTAKE, std(p, season_year=p.season_year, variety_pat=pat(p.variety), orchard_pat=pat(p.orchard)))
    out = []
    total_kg = 0.0
    for r in rows:
        total_kg += float(r["net_kg"] or 0)
        out.append(fmt.drop_none({
            "season_year": r["season_year"], "variety": r["variety"], "orchard": r["orchard"], "suppliers": r["suppliers"],
            "weigh_tags": r["weigh_tags"], "bins": r["bins"], "net_weight": fmt.q(r["net_kg"], "kg", fruit=True),
            "brix_weighted_avg": None if r["brix_weighted"] is None else round(float(r["brix_weighted"]), 2),
            "brix_range": None if r["brix_min"] is None else [r["brix_min"], r["brix_max"]], "lot_numbers": r["lot_numbers"],
            "first_received_on": r["first_received_on"], "last_received_on": r["last_received_on"]}))
    return page(out, p, total_net_weight=fmt.q(total_kg, "kg", fruit=True))
