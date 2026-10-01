"""Packaging, finished goods and keg tools (R35 to R39, R49)."""
from __future__ import annotations

import datetime as dt
from typing import Literal

from pydantic import Field, model_validator

from common import db

from . import fmt, queries as Q
from .registry import Input, Paged, ToolFailure, page, records_tool
from .resolve import echo, resolve, resolve_opt, rid
from .tools_receiving import std

PACKAGE_KINDS = Literal["keg", "can", "bottle"]


def vol(v):
    return fmt.liters(v)


class RunLossesInput(Input):
    run_number: str | None = Field(None, max_length=40, description="One packaging run (PK-00007); default the last N runs.")
    last_n: int = Field(5, ge=1, le=100, description="How many recent runs.")
    package_kind: PACKAGE_KINDS | None = Field(None, description="'can' for canning runs, 'keg' for kegging, 'bottle'.")
    batch_number: str | None = Field(None, max_length=40, description="Only runs from this batch.")
    status: Literal["draft", "posted", "cancelled"] | None = Field("posted", description="Run status; default posted.")


@records_tool("packaging_run_losses", "Packaging run losses",
              """Call for the packaging loss on the last canning (or kegging) run or any run (R35). Each run: batch, package,
              volume in, units out, volume out, loss in liters and gallons and percent against the configuration's expected loss,
              ABV and CO2 at packaging, materials used.""")
async def packaging_run_losses(p: RunLossesInput) -> dict:
    run = await resolve_opt("packaging_run", p.run_number)
    batch = await resolve_opt("batch", p.batch_number)
    rows = await db.fetch_all("records", Q.PACKAGING_RUNS, {"run_id": rid(run), "status": p.status, "package_kind": p.package_kind,
                                                            "batch_id": rid(batch), "lim": p.last_n})
    out = []
    for r in rows:
        d = fmt.drop_none({k: v for k, v in r.items() if k not in ("volume_in_l", "volume_out_l", "loss_l", "fill_volume_l")})
        d.update(volume_in=vol(r["volume_in_l"]), volume_out=vol(r["volume_out_l"]), loss=vol(r["loss_l"]), fill_volume=vol(r["fill_volume_l"]))
        if r["loss_pct"] is not None and r["expected_loss_pct"] is not None:
            d["loss_vs_expected_pct"] = round(float(r["loss_pct"]) - float(r["expected_loss_pct"]), 2)
        out.append(fmt.drop_none(d))
    if not out:
        raise ToolFailure("No packaging runs match. Try status=None for drafts and cancelled runs, or another package_kind.")
    return {"resolved": echo(run=run, batch=batch), "count": len(out), "runs": out}


class FinishedStockInput(Paged):
    product: str | None = Field(None, max_length=120, description="Product code or name.")
    package_kind: PACKAGE_KINDS | None = Field(None, description="keg, can or bottle.")
    location: str | None = Field(None, max_length=120, description="Location name.")
    include_unreleased: bool = Field(False, description="Also list lots on hold, in quarantine or rejected (never counted as ready).")


@records_tool("packaging_finished_stock", "Finished goods ready to sell",
              """Call for how many cases and kegs of a product are ready to sell (R36). Lists finished lots with stock by product
              and package: units on hand and available, cases (units / units per case), kegs, gallons, tax class, ABV, best before,
              location and tax state. 'summary' totals released stock per product and package.""")
async def packaging_finished_stock(p: FinishedStockInput) -> dict:
    product = await resolve_opt("product", p.product)
    location = await resolve_opt("location", p.location)
    rows = await db.fetch_all("records", Q.FINISHED_STOCK, std(p, product_id=rid(product), package_kind=p.package_kind, location_id=rid(location),
                                                             include_unreleased=p.include_unreleased))
    out, summary = [], {}
    for r in rows:
        upc = r["units_per_case"]
        units = float(r["units_available"])
        d = fmt.drop_none({
            "product": r["product_name"], "package": r["package_name"], "package_kind": r["package_kind"], "lot_number": r["lot_number"],
            "batch_number": r["batch_number"], "quality_status": r["quality_status"], "tax_class": r["tax_class"], "abv": r["abv"],
            "packaged_on": r["packaged_on"], "best_before_on": r["best_before_on"], "location": r["location_name"], "tax_state": r["tax_state"],
            "units_on_hand": r["units_on_hand"], "units_available": r["units_available"],
            "cases_available": round(units / upc, 2) if upc else None, "volume_on_hand": vol(r["volume_on_hand_l"]), "unit_cost": r["unit_cost"]})
        out.append(d)
        if r["quality_status"] == "released":
            key = (r["product_name"], r["package_name"])
            s = summary.setdefault(key, {"product": r["product_name"], "package": r["package_name"], "package_kind": r["package_kind"],
                                         "units_available": 0.0, "volume_l": 0.0, "units_per_case": upc, "tax_paid_units": 0.0})
            s["units_available"] += units
            s["volume_l"] += units * float(r["fill_volume_l"])
            if r["tax_state"] == "tax_paid":
                s["tax_paid_units"] += units
    summ = []
    for s in summary.values():
        e = {"product": s["product"], "package": s["package"], "package_kind": s["package_kind"], "units_available": s["units_available"],
             "volume": vol(s["volume_l"]), "in_tax_paid_locations": s["tax_paid_units"]}
        if s["units_per_case"]:
            e["cases_available"] = round(s["units_available"] / s["units_per_case"], 2)
        if s["package_kind"] == "keg":
            e["kegs_available"] = s["units_available"]
        summ.append(e)
    return page(out, p, resolved=echo(product=product, location=location), summary=summ)


class MaterialNeedsInput(Input):
    date_from: dt.date | None = Field(None, description="Packaging planned on or after (default today).")
    date_to: dt.date | None = Field(None, description="Packaging planned on or before (default 7 days from today).")
    package: str | None = Field(None, max_length=120, description="Packaging configuration name to assume (e.g. 'Dry 16 oz can case'); default each active configuration of the product.")


@records_tool("packaging_material_needs", "Packaging materials needed",
              """Call for what packaging materials next week's runs need and whether we have them (R37). Demand is active batches
              at carbonate or package stage plus production orders with a planned package date in the window. Production orders do
              not name a package, so each active configuration of the product is shown as a scenario (or the one named in
              'package'): units, then each BOM item's need against released stock and on order.""")
async def packaging_material_needs(p: MaterialNeedsInput) -> dict:
    today = fmt.today()
    d_from = p.date_from or today
    d_to = p.date_to or today + dt.timedelta(days=7)
    demand = await db.fetch_all("records", Q.PACKAGING_DEMAND, {"tz": fmt.tz(), "date_from": d_from, "date_to": d_to})
    if not demand:
        return {"window": [d_from, d_to], "demand": [], "note": "Nothing is scheduled for packaging in the window and no batch is at carbonate or package stage."}
    configs = await db.fetch_all("records", Q.PACKAGE_CONFIGS, {"product_ids": list({d["product_id"] for d in demand})})
    if p.package:
        configs = [c for c in configs if p.package.lower() in c["name"].lower()]
        if not configs:
            raise ToolFailure(f"No active packaging configuration matches '{p.package}'. Call product_find to see each product's packages.")
    item_ids = list({b["item_id"] for c in configs for b in (c["bom"] or [])})
    supply = {s["item_id"]: s for s in await db.fetch_all("records", Q.ITEM_SUPPLY, {"item_ids": item_ids})} if item_ids else {}
    out, totals = [], {}
    for d in demand:
        scen = []
        for c in [c for c in configs if c["product_id"] == d["product_id"]]:
            units = int(float(d["volume_l"]) * (1 - float(c["expected_loss_pct"]) / 100) // float(c["fill_volume_l"]))
            mats = []
            for b in c["bom"] or []:
                need = units * float(b["qty_per_unit_base"])
                mats.append({"item_code": b["item_code"], "item_name": b["item_name"], "need": fmt.q(need, b["unit"])})
                t = totals.setdefault((c["name"], b["item_id"]), {"package": c["name"], "item_id": b["item_id"], "item_code": b["item_code"],
                                                                  "item_name": b["item_name"], "unit": b["unit"], "need": 0.0})
                t["need"] += need
            scen.append(fmt.drop_none({"package": c["name"], "package_kind": c["package_kind"], "units": units,
                                       "cases": round(units / c["units_per_case"], 2) if c["units_per_case"] else None, "materials": mats}))
        out.append(fmt.drop_none({"source": d["source"], "ref": d["ref"], "product": d["product_name"], "stage_or_status": d["stage"],
                                  "package_on": d["package_on"], "volume": vol(d["volume_l"]), "scenarios": scen}))
    check = []
    for t in totals.values():
        s = supply.get(t["item_id"], {})
        have = float(s.get("released_available", 0))
        check.append(fmt.drop_none({"if_all_packaged_as": t["package"], "item_code": t["item_code"], "item_name": t["item_name"],
                                    "need": fmt.q(t["need"], t["unit"]), "released_available": fmt.q(have, t["unit"]),
                                    "short": fmt.q(max(t["need"] - have, 0), t["unit"]), "on_order": fmt.q(s.get("on_order"), t["unit"]),
                                    "next_expected_on": s.get("next_expected_on"), "enough": have >= t["need"]}))
    return {"window": [d_from, d_to], "demand": out, "materials_check": check}


class LotsForBatchInput(Input):
    batch_number: str | None = Field(None, max_length=40, description="Batch: list the finished lots packaged from it.")
    lot_number: str | None = Field(None, max_length=60, description="Finished lot (the code on a can or keg): find its batch.")

    @model_validator(mode="after")
    def one_of(self):
        if bool(self.batch_number) == bool(self.lot_number):
            raise ValueError("Give exactly one of batch_number or lot_number.")
        return self


@records_tool("packaging_lots_for_batch", "Finished lots of a batch, or the batch in a can",
              """Call for which finished lots came from a batch, or which batch is in a given can, case or keg (R38). For a batch:
              each finished lot with package, date, units packaged and on hand, tax class, quality status and packaging run. For a
              finished lot: its batch, product, recipe version and the sibling lots from the same batch.""")
async def packaging_lots_for_batch(p: LotsForBatchInput) -> dict:
    fix = lambda r: fmt.drop_none({**{k: v for k, v in r.items() if k != "unit_volume_l"}, "unit_volume": vol(r["unit_volume_l"])})
    if p.batch_number:
        batch = await resolve("batch", p.batch_number)
        rows = await db.fetch_all("records", Q.FINISHED_LOTS_FOR_BATCH, {"batch_id": batch["id"]})
        return {"resolved": echo(batch=batch), "count": len(rows), "finished_lots": [fix(r) for r in rows],
                "note": None if rows else "Nothing has been packaged from this batch (children of a split or blend carry their own packaging)."}
    lot = await resolve("lot", p.lot_number)
    fl = await db.fetch_one("records", Q.FINISHED_LOT, {"lot_id": lot["id"]})
    if fl is None:
        raise ToolFailure(f"{lot['label']} ({lot['detail']}) is not a finished lot. Use trace_forward to see where an ingredient lot went.")
    siblings = await db.fetch_all("records", Q.FINISHED_LOTS_FOR_BATCH, {"batch_id": fl["batch_id"]})
    return {"resolved": echo(lot=lot),
            "batch": {"batch_number": fl["batch_number"], "product": fl["product_name"], "recipe_version": fl["recipe_version"], "batch_status": fl["batch_status"]},
            "lot": fmt.drop_none({"lot_number": fl["lot_number"], "package": fl["package_name"], "packaged_on": fl["packaged_on"], "units_packaged": fl["units_packaged"],
                                  "tax_class": fl["tax_class"], "quality_status": fl["quality_status"], "packaging_run": fl["packaging_run"]}),
            "sibling_lots": [fix(r) for r in siblings if r["lot_number"] != fl["lot_number"]]}


class TaxClassInput(LotsForBatchInput):
    pass


def _margins(rule: dict, abv, co2, fruit) -> dict:
    hc = rule.get("hard_cider", {})
    m = {}
    if abv is not None:
        m["abv_below_max"] = round(float(hc["abv_max_exclusive"]) - float(abv), 2)
        m["abv_above_min"] = round(float(abv) - float(hc["abv_min"]), 2)
    if co2 is not None:
        m["co2_below_max_g_100ml"] = round(float(hc["co2_max_g_100ml"]) - float(co2), 3)
    if fruit is not None:
        m["fruit_share_above_min_pct"] = round(float(fruit) - float(hc["fruit_share_min_pct"]), 2)
    return m


@records_tool("packaging_tax_class_check", "Hard cider tax class check",
              """Call for whether a cider is still inside the hard cider tax class (R49), for a finished lot or a batch. Returns
              ABV, CO2 (g/100 mL), fruit share, other-fruit and flavoring flags, the stored class and its source (derived or
              override), the class derived now by the current rule, and the margin to each hard cider limit (ABV 0.5 to under 8.5,
              CO2 at most 0.64 g/100 mL, fruit share over 50 percent).""")
async def packaging_tax_class_check(p: TaxClassInput) -> dict:
    if p.lot_number:
        lot = await resolve("lot", p.lot_number)
        fl = await db.fetch_one("records", Q.FINISHED_LOT, {"lot_id": lot["id"]})
        if fl is None:
            raise ToolFailure(f"{lot['label']} is not a finished lot; give its batch_number instead.")
        subject = {"lot_number": fl["lot_number"], "batch_number": fl["batch_number"], "product": fl["product_name"]}
        abv, co2, fruit = fl["abv"], fl["co2_g_100ml"], fl["fruit_share_pct"]
        stored, source = fl["tax_class"], fl["tax_class_source"]
        beverage, other, flav = fl["beverage_type"], fl["contains_other_fruit"], fl["contains_flavoring"]
        override = fmt.drop_none({"reason": fl["tax_override_reason"], "by": fl["tax_override_by"]}) or None
        resolved = echo(lot=lot)
    else:
        batch = await resolve("batch", p.batch_number)
        b = await db.fetch_one("records", Q.BATCH_HEADER, {"batch_id": batch["id"]})
        latest = {r["measurement"]: r for r in await db.fetch_all("records", Q.LATEST_READINGS, {"target_kind": "batch", "target_ids": [batch["id"]], "measurements": ["abv", "co2"]})}
        subject = {"batch_number": b["number"], "product": b["product_name"], "status": b["status"], "stage": b["current_stage_code"]}
        abv = latest.get("abv", {}).get("value")
        co2 = latest.get("co2", {}).get("value")
        fruit = b["fruit_share_pct"]
        stored = b["tax_class_override"] or b["tax_class_derived"]
        source = "override" if b["tax_class_override"] else ("derived" if b["tax_class_derived"] else "none (intended " + b["intended_tax_class"] + ")")
        beverage, other, flav = b["beverage_type"], b["contains_other_fruit"], b["contains_flavoring"]
        override = None
        resolved = echo(batch=batch)
        subject["readings_used"] = {k: {"value": v["value"], "taken_at": v["taken_at"]} for k, v in latest.items()}
    rule = await db.fetch_one("records", Q.TAX_RULE, {"beverage_type": beverage, "tz": fmt.tz()})
    derived = await db.fetch_one("records", Q.DERIVE_TAX_CLASS, {"beverage_type": beverage, "abv": abv, "co2": co2, "fruit": fruit,
                                                                 "other_fruit": other, "flavoring": flav, "tz": fmt.tz()})
    missing = [n for n, v in (("abv", abv), ("co2", co2), ("fruit_share_pct", fruit)) if v is None]
    margins = _margins(rule["params"], abv, co2, fruit) if rule else {}
    return fmt.drop_none({
        "resolved": resolved, "subject": subject, "abv": abv, "co2_g_100ml": co2, "fruit_share_pct": fruit, "contains_other_fruit": other,
        "contains_flavoring": flav, "stored_tax_class": stored, "stored_source": source, "override": override,
        "derived_now": derived["tax_class"] if derived else None,
        "inside_hard_cider": (derived or {}).get("tax_class") == "hard_cider" and not missing,
        "margins": margins, "rule_effective_from": rule and rule["effective_from"],
        "missing_values": missing or None,
        "note": ("Missing " + ", ".join(missing) + ": the hard cider test needs ABV, CO2 and fruit share, so the derivation falls back to a wine class.") if missing else None,
    })


class KegFleetInput(Paged):
    state: Literal["empty", "filled", "at_customer", "returned_dirty", "cleaning", "lost", "out_of_service"] | None = Field(None, description="Only kegs in this state.")
    customer: str | None = Field(None, max_length=120, description="Only kegs held by this customer.")
    older_than_days: int | None = Field(None, ge=0, le=3650, description="Only kegs not moved for more than this many days.")


@records_tool("keg_fleet", "Where the kegs are",
              """Call for where the kegs are, how many are out and for how long (R39). Lists kegs with state, holder (customer or
              location), filled lot, fill count, deposit and days since last moved, longest first; plus a summary by state and by
              customer.""")
async def keg_fleet(p: KegFleetInput) -> dict:
    customer = await resolve_opt("customer", p.customer)
    rows = await db.fetch_all("records", Q.KEG_FLEET, std(p, state=p.state, customer_id=rid(customer), older_than_days=p.older_than_days))
    summary = await db.fetch_all("records", Q.KEG_SUMMARY)
    by_customer = await db.fetch_all("records", Q.KEGS_BY_CUSTOMER)
    return page([fmt.drop_none({**dict(r), "size_l": None, "size": vol(r["size_l"])}) for r in rows], p, resolved=echo(customer=customer),
                summary_by_state=[fmt.drop_none(dict(s)) for s in summary], at_customers=[dict(c) for c in by_customer],
                kegs_out=sum(c["kegs"] for c in by_customer))


class KegHistoryInput(Paged):
    serial: str = Field(..., min_length=1, max_length=60, description="Keg serial number.")


@records_tool("keg_history", "Movement history of one keg",
              """Call for one keg's history (R39): current state and holder, then every fill, ship, return, clean, loss and deposit
              event with lot, customer, location, removal document and who recorded it, newest first.""")
async def keg_history(p: KegHistoryInput) -> dict:
    keg = await resolve("keg", p.serial)
    k = await db.fetch_one("records", Q.KEG, {"keg_id": keg["id"]})
    rows = await db.fetch_all("records", Q.KEG_MOVEMENTS, std(p, keg_id=keg["id"]))
    return page([fmt.drop_none(dict(r)) for r in rows], p, keg=fmt.drop_none({**{x: y for x, y in k.items() if x != "id"}, "size_l": None, "size": vol(k["size_l"])}))
