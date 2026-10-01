"""Quality (R41 to R44) and costing (R16, R32, R34, R40, R45, R46) tools."""
from __future__ import annotations

import datetime as dt
from typing import Literal

from pydantic import Field, model_validator

from common import db

from . import fmt, queries as Q
from .registry import Input, Paged, ToolFailure, page, records_tool
from .resolve import echo, resolve, resolve_opt, rid
from .tools_receiving import std


def vol(v):
    return fmt.liters(v)


class OutOfSpecInput(Paged):
    batch_number: str | None = Field(None, max_length=40, description="One batch; default every batch.")
    days: int = Field(30, ge=1, le=3650, description="Look back this many days.")


@records_tool("quality_out_of_spec", "Out-of-spec readings",
              """Call for whether anything is out of spec (R41): batch readings that failed their product and stage spec in the last
              N days, with value, spec min, max and target, stage, analyst, and whether a later passing reading of the same
              measurement exists.""")
async def quality_out_of_spec(p: OutOfSpecInput) -> dict:
    batch = await resolve_opt("batch", p.batch_number)
    rows = await db.fetch_all("records", Q.OUT_OF_SPEC, std(p, batch_id=rid(batch), days=p.days))
    out = [fmt.drop_none(dict(r)) for r in rows]
    return page(out, p, resolved=echo(batch=batch), days=p.days, unresolved=sum(1 for r in rows if not r["later_pass"]))


class ReleaseQueueInput(Input):
    pass


@records_tool("quality_release_queue", "Batches and lots awaiting release",
              """Call for which batches and lots await quality release (R42). Batches: active batches at maturation, blend,
              back-sweeten or carbonate with no release since entering the stage, with latest ABV, CO2, free SO2 and pH, failing
              readings in the stage, latest sensory verdict and last decision. Lots: lots in quarantine or on hold that still have
              stock, with CoA flag and latest readings.""")
async def quality_release_queue(p: ReleaseQueueInput) -> dict:
    batches = await db.fetch_all("records", Q.RELEASE_QUEUE_BATCHES)
    lots = await db.fetch_all("records", Q.RELEASE_QUEUE_LOTS)
    return {
        "batches": [fmt.drop_none({**dict(b), "current_volume_l": None, "volume": vol(b["current_volume_l"])}) for b in batches],
        "lots": [fmt.drop_none({**{k: v for k, v in l.items() if k not in ("qty_on_hand", "base_unit_code")},
                                "on_hand": fmt.q(l["qty_on_hand"], l["base_unit_code"], fruit=l["item_class"] == "fruit")}) for l in lots],
        "count": len(batches) + len(lots),
    }


class TargetInput(Input):
    batch_number: str | None = Field(None, max_length=40, description="Batch number.")
    lot_number: str | None = Field(None, max_length=60, description="Lot number.")

    @model_validator(mode="after")
    def one_of(self):
        if bool(self.batch_number) == bool(self.lot_number):
            raise ValueError("Give exactly one of batch_number or lot_number.")
        return self


async def _target(p) -> tuple[str, dict]:
    if p.batch_number:
        return "batch", await resolve("batch", p.batch_number)
    return "lot", await resolve("lot", p.lot_number)


@records_tool("quality_release_history", "Release decisions for a batch or lot",
              """Call for who released a batch or lot, when and on what basis (R42, R4). Every release, hold and reject decision:
              from and to status, basis (CoA, inspection, readings, sensory, override), override flag and reason, note, decided by
              and when, newest first.""")
async def quality_release_history(p: TargetInput) -> dict:
    kind, target = await _target(p)
    rows = await db.fetch_all("records", Q.RELEASE_DECISIONS, {"kind": kind, "target_id": target["id"], "lim": 200})
    out = [fmt.drop_none(dict(r)) for r in rows]
    released = next((r for r in out if r["to_status"] == "released"), None)
    return {"resolved": echo(**{kind: target}), "decisions": out, "current_status": out[0]["to_status"] if out else None,
            "last_release": released, "note": None if out else f"No release decisions are recorded for this {kind}."}


class SensoryInput(TargetInput, Paged):
    date_from: dt.date | None = Field(None, description="Panels on or after (ISO date).")


@records_tool("quality_sensory", "Sensory panel results",
              """Call for what the sensory panel said about a batch or lot (R44). Each record: panel date, panelist, sample code,
              verdict (pass, fail, hold), attribute intensities, faults with intensity, comment; plus verdict counts.""")
async def quality_sensory(p: SensoryInput) -> dict:
    kind, target = await _target(p)
    rows = await db.fetch_all("records", Q.SENSORY, std(p, target_kind=kind, target_id=target["id"], date_from=p.date_from))
    verdicts: dict[str, int] = {}
    for r in rows:
        verdicts[r["verdict"]] = verdicts.get(r["verdict"], 0) + 1
    res = page([fmt.drop_none(dict(r)) for r in rows], p, resolved=echo(**{kind: target}), verdicts=verdicts)
    if not rows:
        res["note"] = f"No sensory records for this {kind}."
    return res


class CostBatchInput(Input):
    batch_number: str = Field(..., min_length=2, max_length=40, description="Batch number.")


@records_tool("cost_batch", "Cost of a batch, keg or case",
              """Call for what a batch cost against standard (R45) or the cost of a keg or case from a batch (R40). Returns material
              cost (by lot), packaging, overhead, total, liquid cost per liter and gallon, the recipe standard at the batch's actual
              volume and the variance, cost inherited from parent batches through splits and blends, and per package: cost per unit,
              per case and per keg.""")
async def cost_batch(p: CostBatchInput) -> dict:
    batch = await resolve("batch", p.batch_number)
    c = await db.fetch_one("records", Q.BATCH_COST, {"batch_id": batch["id"]})
    mats = await db.fetch_all("records", Q.BATCH_MATERIAL_COSTS, {"batch_id": batch["id"]})
    parents = await db.fetch_all("records", Q.PARENT_LIQUID_COSTS, {"batch_id": batch["id"]})
    packages = await db.fetch_all("records", Q.BATCH_PACKAGE_COSTS, {"batch_id": batch["id"]})
    gal = fmt.factor("gal") or 3.785411784
    start_vol = float(c["starting_volume_l"] or 0)
    inherited = sum(float(x["inherited_cost"] or 0) for x in parents)
    liquid_per_l = float(c["liquid_cost_per_l"]) if c["liquid_cost_per_l"] is not None else None
    if liquid_per_l is not None and inherited and start_vol:
        liquid_per_l_incl = liquid_per_l + inherited / start_vol
    else:
        liquid_per_l_incl = liquid_per_l
    std_at_actual = float(c["standard_cost_per_l"]) * start_vol if c["standard_cost_per_l"] is not None and start_vol else None
    total = float(c["total_cost"])
    pkg_out = []
    for k in packages:
        per_unit_liquid = liquid_per_l_incl * float(k["fill_volume_l"]) if liquid_per_l_incl is not None else None
        per_unit = None if per_unit_liquid is None else per_unit_liquid + float(k["packaging_cost_per_unit"] or 0)
        e = fmt.drop_none({"package": k["package_name"], "package_kind": k["package_kind"], "units_packaged": k["units_packaged"], "lots": k["lots"],
                           "fill_volume": vol(k["fill_volume_l"]), "recorded_unit_cost": fmt.money(k["recorded_unit_cost"]),
                           "estimated_unit_cost": fmt.money(per_unit), "liquid_per_unit": fmt.money(per_unit_liquid),
                           "packaging_materials_per_unit": fmt.money(k["packaging_cost_per_unit"])})
        basis = k["recorded_unit_cost"] if k["recorded_unit_cost"] is not None else per_unit
        if basis is not None and k["units_per_case"]:
            e["cost_per_case"] = fmt.money(float(basis) * k["units_per_case"])
        if k["package_kind"] == "keg" and basis is not None:
            e["cost_per_keg"] = fmt.money(basis)
        pkg_out.append(e)
    return fmt.drop_none({
        "resolved": echo(batch=batch), "batch": c["number"], "status": c["status"], "recipe_version": c["recipe_version"],
        "material_cost": fmt.money(c["material_cost"]), "packaging_cost": fmt.money(c["packaging_cost"]), "overhead_cost": fmt.money(c["overhead_cost"]),
        "total_cost": fmt.money(total), "starting_volume": vol(c["starting_volume_l"]), "packaged_volume": vol(c["packaged_volume_l"]), "units_out": c["units_out"],
        "liquid_cost_per_l": None if liquid_per_l is None else round(liquid_per_l, 4),
        "liquid_cost_per_gal": None if liquid_per_l is None else round(liquid_per_l * gal, 4),
        "inherited_from_parents": [fmt.drop_none(dict(x)) for x in parents] or None,
        "liquid_cost_per_l_including_parents": None if not inherited or liquid_per_l_incl is None else round(liquid_per_l_incl, 4),
        "standard": fmt.drop_none({"recipe_standard_total": fmt.money(c["standard_cost_total"]), "recipe_target_volume": vol(c["target_batch_volume_l"]),
                                   "standard_per_l": c["standard_cost_per_l"], "standard_at_actual_volume": fmt.money(std_at_actual),
                                   "variance_to_recipe_total": fmt.money(c["variance_to_standard"]),
                                   "variance_at_actual_volume": fmt.money(total - std_at_actual) if std_at_actual is not None else None}) or None,
        "materials": [fmt.drop_none(dict(m)) for m in mats], "packages": pkg_out,
        "note": "Totals come from app.v_batch_costs: materials consumed directly by this batch at lot cost, packaging materials at standard, overhead per starting liter. "
                "Split and blend children carry their parents' liquid cost only in 'inherited_from_parents'.",
    })


class ValuationInput(Input):
    as_of: dt.date | None = Field(None, description="Value at the end of this date (ISO); default today.")
    group_by: Literal["item_class", "tax_state", "location", "item", "class_and_tax_state"] = Field("item_class", description="Grouping.")
    premises: str | None = Field(None, max_length=120, description="One premises.")


@records_tool("cost_inventory_valuation", "Inventory valuation",
              """Call for what inventory is worth by category, tax state (bonded versus tax-paid), location or item, as of a date
              (R16, R46). Rebuilt from the ledger at the end of the date, valued at lot cost (standard cost for standard-costed
              items). Bulk cider in vessels is not a ledger item, so it is valued separately at each batch's liquid cost (bonded).""")
async def cost_inventory_valuation(p: ValuationInput) -> dict:
    premises = await resolve_opt("premises", p.premises)
    as_of = p.as_of or fmt.today()
    args = {"as_of": as_of, "group_by": p.group_by, "tz": fmt.tz(), "premises_id": rid(premises)}
    rows = await db.fetch_all("records", Q.VALUATION, args)
    bulk = await db.fetch_one("records", Q.BULK_VALUE, args)
    groups = []
    for r in rows:
        g = {"group": r["grp"], "value": fmt.money(r["value"]), "lots": r["lots"]}
        if r["unit"]:
            g["qty"] = fmt.q(r["qty"], r["unit"])
        if r["packaged_volume_l"]:
            g["packaged_volume"] = vol(r["packaged_volume_l"])
        groups.append(g)
    total = sum(float(r["value"] or 0) for r in rows)
    bulk_out = None
    if bulk and bulk["volume_l"]:
        bulk_out = {"volume": vol(bulk["volume_l"]), "value": fmt.money(bulk["value"]), "batches": bulk["batches"], "tax_state": "bonded"}
    return fmt.drop_none({"resolved": echo(premises=premises), "as_of": as_of, "group_by": p.group_by, "groups": groups, "ledger_total_value": fmt.money(total),
                          "bulk_in_vessels": bulk_out, "total_value_including_bulk": fmt.money(total + float((bulk or {}).get("value") or 0))})


class StageYieldsInput(Input):
    batch_number: str | None = Field(None, max_length=40, description="One batch.")
    product: str | None = Field(None, max_length=120, description="A product: its last_n batches and averages per stage.")
    last_n: int = Field(5, ge=1, le=50, description="With product: how many recent batches.")

    @model_validator(mode="after")
    def one_of(self):
        if bool(self.batch_number) == bool(self.product):
            raise ValueError("Give exactly one of batch_number or product.")
        return self


@records_tool("cost_stage_yields", "Stage yields against recipe",
              """Call for the yield at each stage against the recipe's expected loss (R32). Per batch and stage: volume in and out,
              actual loss percent, expected loss percent, variance and recorded loss events; for a product, its last N batches and
              the average actual loss per stage.""")
async def cost_stage_yields(p: StageYieldsInput) -> dict:
    if p.batch_number:
        batch = await resolve("batch", p.batch_number)
        ids, resolved = [batch["id"]], echo(batch=batch)
    else:
        product = await resolve("product", p.product)
        bs = await db.fetch_all("records", Q.LAST_BATCHES_FOR_PRODUCT, {"product_id": product["id"], "n": p.last_n})
        if not bs:
            raise ToolFailure(f"{product['label']} has no batches yet.")
        ids, resolved = [b["id"] for b in bs], echo(product=product)
    rows = await db.fetch_all("records", Q.STAGE_YIELDS, {"batch_ids": ids})
    avg: dict[str, list] = {}
    for r in rows:
        if r["actual_loss_pct"] is not None:
            avg.setdefault(r["stage_code"], []).append((float(r["actual_loss_pct"]), r["expected_loss_pct"]))
    averages = [{"stage": k, "batches": len(v), "avg_actual_loss_pct": round(sum(x for x, _ in v) / len(v), 2),
                 "expected_loss_pct": v[0][1]} for k, v in avg.items()]
    out = [fmt.drop_none({**{k: v for k, v in r.items() if k not in ("volume_in_l", "volume_out_l", "recorded_loss_l")},
                          "volume_in": vol(r["volume_in_l"]), "volume_out": vol(r["volume_out_l"]), "recorded_loss": vol(r["recorded_loss_l"]) if r["recorded_loss_l"] else None}) for r in rows]
    return {"resolved": resolved, "stages": out, "averages_by_stage": averages}


class JuiceYieldInput(Input):
    season_year: int | None = Field(None, ge=2000, le=2100, description="Harvest year; default every season.")
    variety: str | None = Field(None, max_length=80, description="Variety fragment.")


@records_tool("cost_juice_yield_by_variety", "Juice yield per ton by variety",
              """Call for how much juice per ton (and per bushel) each variety gave in a season (R34). Groups posted press runs by
              season and fruit-lot variety: press runs, fruit in kg, lb and tons, juice attributed in liters and gallons, gal/ton,
              gal/bushel (42 lb) and L/kg. Juice is attributed to varieties in proportion to fruit weight within each run.""")
async def cost_juice_yield_by_variety(p: JuiceYieldInput) -> dict:
    rows = await db.fetch_all("records", Q.JUICE_YIELD, {"season_year": p.season_year, "variety_pat": None if not p.variety else f"%{p.variety}%"})
    out = [fmt.drop_none({"season_year": r["season_year"], "variety": r["variety"], "press_runs": r["press_runs"], "press_run_numbers": r["press_run_numbers"],
                          "fruit": fmt.q(r["fruit_kg"], "kg", fruit=True), "juice": vol(r["juice_l"]),
                          "gal_per_ton": None if r["gal_per_ton"] is None else round(float(r["gal_per_ton"]), 1),
                          "gal_per_bushel": None if r["gal_per_bushel"] is None else round(float(r["gal_per_bushel"]), 2),
                          "l_per_kg": None if r["l_per_kg"] is None else round(float(r["l_per_kg"]), 4)}) for r in rows]
    return {"count": len(out), "rows": out}
