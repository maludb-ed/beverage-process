"""Production and batch tools (R13, R23 to R33, R52, batch_find)."""
from __future__ import annotations

import datetime as dt
from typing import Literal
from zoneinfo import ZoneInfo

from pydantic import Field, model_validator

from common import db

from . import fmt, queries as Q
from .registry import Input, Paged, ToolFailure, page, records_tool
from .resolve import echo, resolve, resolve_many, resolve_opt, rid
from .tools_receiving import check_range, is_fruit, std

PURPOSES = Literal["base_juice", "yeast", "nutrient", "sulfite", "enzyme", "sweetener", "acid", "fining", "fruit", "other"]


def vol(v) -> dict | None:
    return fmt.liters(v)


class TankBoardInput(Input):
    premises: str | None = Field(None, max_length=120, description="Premises name; default all.")
    vessel: str | None = Field(None, max_length=60, description="One vessel, e.g. FV-1.")
    include_empty: bool = Field(True, description="Include empty vessels.")


@records_tool("production_tank_board", "What is in every vessel",
              """Call for what is in every vessel right now, since when and at what stage (R23), or what is in one tank. Each
              vessel: kind, capacity, status, occupant (batch number or juice lot), product, volume in liters and gallons, fill
              percent, occupied since, current stage and since when, and where it stands on the Tank view (floor_position, grid units).""")
async def production_tank_board(p: TankBoardInput) -> dict:
    premises = await resolve_opt("premises", p.premises)
    vessel = await resolve_opt("vessel", p.vessel)
    rows = await db.fetch_all("records", Q.TANK_BOARD, {"premises_id": rid(premises), "vessel_id": rid(vessel), "include_empty": p.include_empty})
    out = [fmt.drop_none({
        "vessel": r["vessel_name"], "kind": r["vessel_kind"], "capacity": vol(r["capacity_l"]), "vessel_status": r["vessel_status"],
        "occupant_kind": r["occupant_kind"], "occupant": r["occupant_label"], "product": r["product_name"] or r["lot_item"],
        "volume": vol(r["volume_l"]), "fill_pct": r["fill_pct"], "occupied_since": r["occupied_since"], "stage": r["current_stage_code"],
        "stage_name": r["stage_name"], "stage_since": r["stage_since"], "batch_status": r["batch_status"],
        "floor_position": None if r["board_x"] is None else {"x": r["board_x"], "y": r["board_y"]}}) for r in rows]
    return {"resolved": echo(premises=premises, vessel=vessel), "count": len(out), "vessels": out,
            "occupied": sum(1 for o in out if o.get("occupant")), "total_volume": vol(sum(float(r["volume_l"] or 0) for r in rows))}


class InProgressInput(Paged):
    product: str | None = Field(None, max_length=120, description="Product code or name.")
    stage: str | None = Field(None, max_length=40, description="Stage code or name, e.g. primary, maturation.")
    include_finished: bool = Field(False, description="Also include packaged, dumped and closed batches.")


@records_tool("production_batches_in_progress", "Batches in progress and readiness",
              """Call for which batches are in progress and when each will be ready (R24). Each active batch: product, stage and
              days in it, volume, vessels, recipe version, production order and planned package date, an estimated ready date from
              the recipe's remaining stage durations, and the latest reading.""")
async def production_batches_in_progress(p: InProgressInput) -> dict:
    product = await resolve_opt("product", p.product)
    stage = await resolve_opt("stage", p.stage)
    rows = await db.fetch_all("records", Q.BATCHES_IN_PROGRESS, std(p, product_id=rid(product), stage=stage and stage["label"], include_finished=p.include_finished))
    out = []
    for r in rows:
        d = fmt.drop_none(dict(r))
        d.pop("current_volume_l", None)
        d["volume"] = vol(r["current_volume_l"])
        since = r["stage_since"] or r["started_at"]
        if r["planned_package_on"]:
            d["ready_estimate"] = {"on": r["planned_package_on"], "basis": "production order planned package date"}
        elif r["remaining_stage_days"] is not None and since is not None:
            d["ready_estimate"] = {"on": (since.date() + dt.timedelta(days=int(r["remaining_stage_days"]))), "basis": "recipe stage durations from current stage entry"}
        d.pop("remaining_stage_days", None)
        out.append(d)
    return page(out, p, resolved=echo(product=product, stage=stage))


class PressRunsInput(Paged):
    date_from: dt.date | None = Field(None, description="Run on or after (ISO date); omit for the most recent runs.")
    date_to: dt.date | None = Field(None, description="Run on or before (ISO date).")
    status: Literal["draft", "posted", "cancelled"] | None = Field("posted", description="Run status; default posted.")


@records_tool("production_press_runs", "Press runs and yields",
              """Call for what was pressed in a period and what it yielded per ton and per bushel (R25). Each run: date, fruit in
              (kg, lb, tons, bushels), juice out (L, gal), pomace, yield in L/kg, gal/ton and gal/bushel (42 lb), input fruit lots
              with variety and the juice and pomace lots produced. Newest first.""")
async def production_press_runs(p: PressRunsInput) -> dict:
    check_range(p.date_from, p.date_to)
    rows = await db.fetch_all("records", Q.PRESS_RUNS, std(p, status=p.status, date_from=p.date_from, date_to=p.date_to))
    out = []
    for r in rows:
        out.append(fmt.drop_none({
            "number": r["number"], "run_on": r["run_on"], "status": r["status"], "press": r["press"],
            "fruit_in": fmt.q(r["fruit_kg_total"], "kg", fruit=True), "juice_out": vol(r["juice_l_total"]), "pomace": fmt.q(r["pomace_kg_total"], "kg", fruit=True),
            "yield_l_per_kg": r["yield_l_per_kg"], "gal_per_ton": None if r["gal_per_ton"] is None else round(float(r["gal_per_ton"]), 1),
            "gal_per_bushel": None if r["gal_per_bushel"] is None else round(float(r["gal_per_bushel"]), 2),
            "inputs": [{**{k: v for k, v in i.items() if k != "kg"}, "weight": fmt.q(i["kg"], "kg", fruit=True)} for i in (r["inputs"] or [])],
            "outputs": r["outputs"], "posted_by": r["posted_by"], "notes": r["notes"]}))
    return page(out, p)


class OrdersInput(Paged):
    status: Literal["planned", "released", "in_progress", "complete", "closed", "cancelled", "open"] | None = Field(
        None, description="Order status; 'open' means planned, released or in progress. Default all.")
    product: str | None = Field(None, max_length=120, description="Product code or name.")
    not_started_only: bool = Field(False, description="Only orders with no batch yet (e.g. released but not started).")


@records_tool("production_orders_by_status", "Production orders by status",
              """Call for production orders by status, e.g. which are released but not started (R26: status='released',
              not_started_only=true). Each order: product, recipe version, planned volume, planned pitch and package dates, who
              released it, planned vessels, allocations and the batches started from it.""")
async def production_orders_by_status(p: OrdersInput) -> dict:
    product = await resolve_opt("product", p.product)
    statuses = None if p.status is None else (["planned", "released", "in_progress"] if p.status == "open" else [p.status])
    rows = await db.fetch_all("records", Q.PRODUCTION_ORDERS, std(p, statuses=statuses, product_id=rid(product), not_started_only=p.not_started_only))
    out = []
    for r in rows:
        d = fmt.drop_none({k: v for k, v in r.items() if k not in ("id", "planned_volume_l")})
        d["planned_volume"] = vol(r["planned_volume_l"])
        d["started"] = bool(r["batches"])
        out.append(d)
    return page(out, p, resolved=echo(product=product))


class ShortagesInput(Input):
    order_number: str | None = Field(None, max_length=40, description="One production order (WO-00004); default every open order.")


@records_tool("production_order_shortages", "Shortages for scheduled production",
              """Call for what the scheduled production orders need that is not in stock (R13). Orders are taken in planned pitch
              order; each recipe line's remaining requirement (planned volume, less what its batches already consumed) is met from
              released stock in that order. Shows required, covered, short, on order and the next expected delivery.""")
async def production_order_shortages(p: ShortagesInput) -> dict:
    order = await resolve_opt("order", p.order_number)
    reqs = await db.fetch_all("records", Q.ORDER_REQUIREMENTS, {"order_id": rid(order)})
    if not reqs:
        raise ToolFailure("No open production orders with recipe lines." if order is None else f"{order['label']} has no recipe lines.")
    supply = {r["item_id"]: r for r in await db.fetch_all("records", Q.ITEM_SUPPLY, {"item_ids": list({r["item_id"] for r in reqs})})}
    remaining = {k: float(v["released_available"]) for k, v in supply.items()}
    orders: dict[str, dict] = {}
    shortages: dict[str, dict] = {}
    for r in reqs:
        o = orders.setdefault(r["number"], {"order": r["number"], "status": r["status"], "product": r["product_name"], "planned_pitch_on": r["planned_pitch_on"],
                                            "planned_volume": vol(r["planned_volume_l"]), "lines": []})
        u, fr = r["base_unit_code"], is_fruit(r)
        need = max(float(r["required"]) - float(r["consumed"]), 0.0)
        have = remaining.get(r["item_id"], 0.0)
        covered = min(need, max(have, 0.0))
        remaining[r["item_id"]] = have - need
        short = need - covered
        s = supply.get(r["item_id"], {})
        line = fmt.drop_none({"item_code": r["item_code"], "item_name": r["item_name"], "stage": r["stage_code"], "purpose": r["purpose"],
                              "required": fmt.q(r["required"], u, fruit=fr), "already_consumed": fmt.q(r["consumed"], u, fruit=fr) if float(r["consumed"]) else None,
                              "still_needed": fmt.q(need, u, fruit=fr), "covered_by_stock": fmt.q(covered, u, fruit=fr), "short": fmt.q(short, u, fruit=fr)})
        if short > 1e-9:
            line.update(fmt.drop_none({"on_order": fmt.q(s.get("on_order"), u, fruit=fr), "next_expected_on": s.get("next_expected_on"),
                                       "awaiting_release": fmt.q(s.get("awaiting_release"), u, fruit=fr) if s.get("awaiting_release") else None}))
            agg = shortages.setdefault(r["item_code"], {"item_code": r["item_code"], "item_name": r["item_name"], "unit": u, "short_base": 0.0, "orders": [], "fruit": fr,
                                                        "on_order": s.get("on_order"), "next_expected_on": s.get("next_expected_on")})
            agg["short_base"] += short
            agg["orders"].append(r["number"])
        o["lines"].append(line)
    summary = [fmt.drop_none({"item_code": a["item_code"], "item_name": a["item_name"], "short": fmt.q(a["short_base"], a["unit"], fruit=a["fruit"]),
                              "on_order": fmt.q(a["on_order"], a["unit"], fruit=a["fruit"]), "next_expected_on": a["next_expected_on"], "orders": a["orders"]})
               for a in shortages.values()]
    return {"resolved": echo(order=order), "orders": list(orders.values()), "shortages": summary, "all_covered": not summary}


class BatchFindInput(Paged):
    query: str | None = Field(None, max_length=120, description="Batch number, product code or name fragment; omit to list recent batches.")
    product: str | None = Field(None, max_length=120, description="Product code or name.")
    status: Literal["active", "packaged", "dumped", "closed"] | None = Field(None, description="Batch status.")
    date_from: dt.date | None = Field(None, description="Started on or after (ISO date).")
    date_to: dt.date | None = Field(None, description="Started on or before (ISO date).")


@records_tool("batch_find", "Find batches",
              """Call to find batches by number fragment, product, status or start date when you do not have an exact batch
              number. Returns batch number, product, status, stage, origin, volume, start and close dates, recipe version,
              production order and current vessels, newest first.""")
async def batch_find(p: BatchFindInput) -> dict:
    product = await resolve_opt("product", p.product)
    pat = None if not p.query else f"%{p.query}%"
    rows = await db.fetch_all("records", Q.BATCH_FIND, std(p, pat=pat, product_id=rid(product), status=p.status, date_from=p.date_from, date_to=p.date_to))
    out = []
    for r in rows:
        d = fmt.drop_none(dict(r))
        d.pop("current_volume_l", None)
        d["volume"] = vol(r["current_volume_l"])
        out.append(d)
    return page(out, p, resolved=echo(product=product))


class BatchInput(Input):
    batch_number: str = Field(..., min_length=2, max_length=40, description="Batch number such as B-26-001 (or a fragment).")


class ConsumptionsInput(BatchInput):
    purpose: PURPOSES | None = Field(None, description="Only this purpose, e.g. sulfite, nutrient, sweetener.")
    stage: str | None = Field(None, max_length=40, description="Only additions at this stage.")
    after_stage: str | None = Field(None, max_length=40, description="Only additions at stages after this one, e.g. 'primary' for post-fermentation additions.")


@records_tool("batch_consumptions", "What a batch consumed, by lot",
              """Call for what was consumed in a batch, by lot (R27), or what was added after fermentation such as sulfite,
              nutrient or sweetener (R28: after_stage='primary'). Each addition: item, lot, supplier lot, quantity, planned
              quantity, purpose, stage, when, by whom, cost; plus totals per item.""")
async def batch_consumptions(p: ConsumptionsInput) -> dict:
    batch = await resolve("batch", p.batch_number)
    stage = await resolve_opt("stage", p.stage)
    after = await resolve_opt("stage", p.after_stage)
    rows = await db.fetch_all("records", Q.BATCH_CONSUMPTIONS, {"batch_id": batch["id"], "purpose": p.purpose, "stage": stage and stage["label"],
                                                                "after_stage": after and after["label"]})
    totals: dict[str, dict] = {}
    out = []
    for r in rows:
        u, fr = r["base_unit_code"], is_fruit(r)
        out.append(fmt.drop_none({
            "consumed_at": r["consumed_at"], "item_code": r["item_code"], "item_name": r["item_name"], "lot_number": r["lot_number"],
            "supplier_lot_number": r["supplier_lot_number"], "supplier": r["supplier_name"], "qty": fmt.q(r["qty_base"], u, fruit=fr),
            "planned_qty": fmt.q(r["planned_qty_base"], u, fruit=fr), "purpose": r["purpose"], "stage": r["stage_code"], "actor": r["actor"],
            "cost": r["cost"], "note": r["note"]}))
        t = totals.setdefault(r["item_code"], {"item_code": r["item_code"], "item_name": r["item_name"], "unit": u, "fruit": fr, "qty": 0.0, "lots": set()})
        t["qty"] += float(r["qty_base"])
        t["lots"].add(r["lot_number"])
    tot = [{"item_code": t["item_code"], "item_name": t["item_name"], "qty": fmt.q(t["qty"], t["unit"], fruit=t["fruit"]), "lots": sorted(t["lots"])} for t in totals.values()]
    res = {"resolved": echo(batch=batch, stage=stage, after_stage=after), "count": len(out), "consumptions": out, "totals_by_item": tot}
    if not out:
        res["note"] = "No consumptions match. Split and blend children inherit their parents' additions; see batch_genealogy."
    return res


class BlendsInput(Input):
    batch_number: str | None = Field(None, max_length=40, description="A batch; omit to list recent blends.")
    date_from: dt.date | None = Field(None, description="When listing: blended on or after.")
    date_to: dt.date | None = Field(None, description="When listing: blended on or before.")
    limit: int = Field(50, ge=1, le=200, description="Maximum blends when listing.")
    offset: int = Field(0, ge=0, description="Rows to skip when listing.")


@records_tool("batch_blends", "Blends and proportions",
              """Call for which batches were blended and in what proportion (R29). For a batch: blends that produced it (each
              source batch with volume and percent share) and blends it went into. Without a batch: recent blend events with their
              inputs.""")
async def batch_blends(p: BlendsInput) -> dict:
    if not p.batch_number:
        rows = await db.fetch_all("records", Q.RECENT_BLENDS, std(p, date_from=p.date_from, date_to=p.date_to))
        return page([fmt.drop_none({**dict(r), "volume_out_l": None, "volume_out": vol(r["volume_out_l"])}) for r in rows], p)
    batch = await resolve("batch", p.batch_number)
    made = await db.fetch_all("records", Q.BATCH_BLENDS_AS_RESULT, {"batch_id": batch["id"]})
    into = await db.fetch_all("records", Q.BATCH_BLENDS_AS_SOURCE, {"batch_id": batch["id"]})
    return {"resolved": echo(batch=batch),
            "made_by_blend": [fmt.drop_none({k: v for k, v in r.items() if k != "id"} | {"volume_out": vol(r["volume_out_l"])}) for r in made],
            "blended_into": [fmt.drop_none(dict(r)) for r in into],
            "note": None if made or into else "This batch was not part of any blend."}


class GenealogyInput(Input):
    batch_number: str | None = Field(None, max_length=40, description="Batch number.")
    vessel: str | None = Field(None, max_length=60, description="Vessel name, e.g. 'tank 7' or FV-1: uses what is in it now.")

    @model_validator(mode="after")
    def one_of(self):
        if not self.batch_number and not self.vessel:
            raise ValueError("Give batch_number or vessel.")
        return self


@records_tool("batch_genealogy", "Full genealogy of a batch or tank",
              """Call for the full genealogy of what is in a vessel or of a batch (R30). Returns ancestors through splits and
              blends recursively (volumes and fractions), descendants, and the root lots: juice, yeast and additive lots consumed and
              the fruit lots and suppliers behind pressed juice. A vessel holding a juice lot returns that lot's press run and fruit.""")
async def batch_genealogy(p: GenealogyInput) -> dict:
    vessel = await resolve_opt("vessel", p.vessel)
    if vessel and not p.batch_number:
        occ = await db.fetch_one("records", Q.VESSEL_OCCUPANT, {"vessel_id": vessel["id"]})
        if occ is None:
            raise ToolFailure(f"{vessel['label']} is empty. Give a batch_number, or check production_tank_board.")
        if occ["occupant_kind"] == "lot":
            origin = await db.fetch_one("records", Q.LOT_ORIGIN, {"lot_id": occ["occupant_id"]})
            return {"resolved": echo(vessel=vessel), "occupant": "lot", "volume": vol(occ["volume_l"]), "since": occ["from_at"], "lot": fmt.drop_none(dict(origin))}
        batch = {"id": occ["occupant_id"]}
        header = await db.fetch_one("records", Q.BATCH_HEADER, {"batch_id": batch["id"]})
        batch.update(label=header["number"], detail=f"in {vessel['label']} since {occ['from_at']:%Y-%m-%d}")
    else:
        batch = await resolve("batch", p.batch_number)
        header = await db.fetch_one("records", Q.BATCH_HEADER, {"batch_id": batch["id"]})
    up = await db.fetch_all("records", Q.LINEAGE_UP, {"batch_id": batch["id"]})
    down = await db.fetch_all("records", Q.LINEAGE_DOWN, {"batch_id": batch["id"]})
    trace = await db.fetch_all("records", Q.TRACE_BACKWARD, {"batch_id": batch["id"]})
    lots = [fmt.drop_none({"level": t["level"], "kind": t["kind"], "lot_number": t["label"], "supplier": t["supplier_name"],
                           "supplier_lot_number": t["supplier_lot_number"], **(t["detail"] or {})}) for t in trace if t["kind"] != "batch"]
    return {
        "resolved": echo(batch=batch, vessel=vessel),
        "batch": fmt.drop_none({"number": header["number"], "product": header["product_name"], "status": header["status"], "stage": header["current_stage_code"],
                                "origin": header["origin_kind"], "volume": vol(header["current_volume_l"]), "started_at": header["started_at"]}),
        "ancestors": [fmt.drop_none(dict(r)) for r in up], "descendants": [fmt.drop_none(dict(r)) for r in down],
        "ingredient_lots": [l for l in lots if l["kind"] == "lot"], "fruit_lots": [l for l in lots if l["kind"] == "fruit_lot"],
        "batches_in_tree": sorted({t["label"] for t in trace if t["kind"] == "batch"}),
    }


class ReadingsInput(Paged):
    batch_number: str | None = Field(None, max_length=40, description="Batch number.")
    lot_number: str | None = Field(None, max_length=60, description="Lot number (juice, fruit or finished lot).")
    vessel: str | None = Field(None, max_length=60, description="Readings taken on a vessel.")
    measurement: str | None = Field(None, max_length=40, description="Measurement code or name: sg or brix (fermentation curve), ph, ta, free_so2, total_so2 ('so2' gives both), abv, co2, temp_c ...")
    date_from: dt.date | None = Field(None, description="Taken on or after (ISO date).")
    date_to: dt.date | None = Field(None, description="Taken on or before (ISO date).")

    @model_validator(mode="after")
    def one_of(self):
        if sum(1 for x in (self.batch_number, self.lot_number, self.vessel) if x) != 1:
            raise ValueError("Give exactly one of batch_number, lot_number or vessel.")
        return self


@records_tool("batch_readings", "Readings and fermentation curve",
              """Call for readings on a batch, lot or vessel in time order: the fermentation curve (R31, measurement='sg' or
              'brix'), the last reading and whether it is in spec (R41), or the SO2 history of a lot or batch (R43,
              measurement='so2'). Each reading: time, measurement, value and unit, stage, lab or cellar, spec range and result,
              analyst. 'latest' gives the newest value per measurement.""")
async def batch_readings(p: ReadingsInput) -> dict:
    measurements = None
    if p.measurement:
        ms = await resolve_many("measurement", p.measurement)
        measurements = [m["label"] for m in ms]
    note = None
    if p.batch_number:
        target = await resolve("batch", p.batch_number); kind = "batch"
    elif p.lot_number:
        target = await resolve("lot", p.lot_number); kind = "lot"
        d = await db.fetch_one("records", Q.LOT_DETAIL, {"lot_id": target["id"]})
        if d["finished_from_batch"]:
            note = f"{target['label']} is a finished lot from batch {d['finished_from_batch']}; batch readings are under batch_number={d['finished_from_batch']}."
    else:
        target = await resolve("vessel", p.vessel); kind = "vessel"
    args = std(p, target_kind=kind, target_ids=[target["id"]], measurements=measurements, date_from=p.date_from, date_to=p.date_to)
    rows = await db.fetch_all("records", Q.READINGS, args)
    latest = await db.fetch_all("records", Q.LATEST_READINGS, args)
    res = page([fmt.drop_none(dict(r)) for r in rows], p, resolved=echo(**{kind: target}), measurements=measurements,
               latest=[fmt.drop_none(dict(r)) for r in latest], out_of_spec=sum(1 for r in rows if r["spec_result"] == "fail"))
    if note:
        res["note"] = note
    elif not rows:
        res["note"] = "No readings match. Readings taken before a split or blend are recorded on the parent batch (see batch_genealogy)."
    return res


class LossesInput(BatchInput):
    stage: str | None = Field(None, max_length=40, description="Only losses at this stage.")


@records_tool("batch_losses", "Where a batch lost volume and why",
              """Call for where a batch lost volume and why (R33). Returns loss events (stage, liters and gallons, reason code,
              TTB category, expected or exceptional, approval), transfer losses, packaging run losses and stage-to-stage volume
              drops, with totals by reason.""")
async def batch_losses(p: LossesInput) -> dict:
    batch = await resolve("batch", p.batch_number)
    stage = await resolve_opt("stage", p.stage)
    st = stage and stage["label"]
    events = await db.fetch_all("records", Q.BATCH_LOSS_EVENTS, {"batch_id": batch["id"], "stage": st})
    transfers = [] if st else await db.fetch_all("records", Q.BATCH_TRANSFERS, {"batch_id": batch["id"]})
    pkg = [] if st not in (None, "package") else await db.fetch_all("records", Q.BATCH_PACKAGING_LOSSES, {"batch_id": batch["id"]})
    stages = await db.fetch_all("records", Q.STAGE_HISTORY, {"batch_id": batch["id"]})
    by_reason: dict[str, float] = {}
    for e in events:
        if e["unit_code"] == "L":
            by_reason[f"{e['reason_code']} {e['reason_name']}"] = by_reason.get(f"{e['reason_code']} {e['reason_name']}", 0.0) + float(e["qty_base"])
    stage_drops = [fmt.drop_none({"stage": s["stage_code"], "volume_in": vol(s["volume_in_l"]), "volume_out": vol(s["volume_out_l"]),
                                  "drop": vol(float(s["volume_in_l"]) - float(s["volume_out_l"])), "actual_loss_pct": s["actual_loss_pct"],
                                  "expected_loss_pct": s["expected_loss_pct"]})
                   for s in stages if s["volume_in_l"] is not None and s["volume_out_l"] is not None and (st is None or s["stage_code"] == st)]
    return {
        "resolved": echo(batch=batch, stage=stage),
        "loss_events": [fmt.drop_none({**{k: v for k, v in e.items() if k not in ("qty_base", "unit_code")}, "qty": fmt.q(e["qty_base"], e["unit_code"])}) for e in events],
        "totals_by_reason": [{"reason": k, "volume": vol(v)} for k, v in sorted(by_reason.items(), key=lambda kv: -kv[1])],
        "total_loss_events": vol(sum(by_reason.values())),
        "transfer_losses": [fmt.drop_none({**{k: v for k, v in t.items() if k not in ("volume_l", "loss_l")}, "volume": vol(t["volume_l"]), "loss": vol(t["loss_l"])})
                            for t in transfers if float(t["loss_l"] or 0) > 0],
        "packaging_losses": [fmt.drop_none({"run": k["run_number"], "run_on": k["run_on"], "package": k["package_name"], "loss": vol(k["loss_l"]),
                                            "loss_pct": round(100 * float(k["loss_l"]) / float(k["volume_in_l"]), 2) if k["volume_in_l"] else None,
                                            "expected_loss_pct": k["expected_loss_pct"]}) for k in pkg if float(k["loss_l"] or 0) > 0],
        "stage_volume_drops": stage_drops,
        "note": "Transfer and packaging losses may also be posted as loss events (RACK, PKG); do not add them twice.",
    }


@records_tool("batch_stage_history", "Stage history of a batch",
              """Call for a batch's stage history: when it entered and left each stage, days in each, volumes in and out, actual
              loss percent against the recipe's expected loss and duration, who recorded it (R23 for one batch, R32 per batch).""")
async def batch_stage_history(p: BatchInput) -> dict:
    batch = await resolve("batch", p.batch_number)
    header = await db.fetch_one("records", Q.BATCH_HEADER, {"batch_id": batch["id"]})
    rows = await db.fetch_all("records", Q.STAGE_HISTORY, {"batch_id": batch["id"]})
    out = [fmt.drop_none({**{k: v for k, v in r.items() if k not in ("volume_in_l", "volume_out_l")},
                          "volume_in": vol(r["volume_in_l"]), "volume_out": vol(r["volume_out_l"])}) for r in rows]
    return {"resolved": echo(batch=batch), "batch": fmt.drop_none({"number": header["number"], "product": header["product_name"], "status": header["status"],
                                                                   "current_stage": header["current_stage_code"], "recipe_version": header["recipe_version"],
                                                                   "volume": vol(header["current_volume_l"])}), "stages": out}


class CoProductInput(Paged):
    date_from: dt.date | None = Field(None, description="Disposed on or after (ISO date).")
    date_to: dt.date | None = Field(None, description="Disposed on or before (ISO date).")
    destination: Literal["compost", "farm", "sale", "waste", "other"] | None = Field(None, description="Only this destination.")
    lot_number: str | None = Field(None, max_length=60, description="One pomace (co-product) lot.")


@records_tool("co_product_dispositions", "Where the pomace went",
              """Call for where the pomace (or another co-product) went (R52). Each disposition: date, lot, press run, quantity in
              kg, lb and tons, destination (compost, farm, sale, waste), recipient and who recorded it; totals by destination and the
              co-product still on hand.""")
async def co_product_dispositions(p: CoProductInput) -> dict:
    check_range(p.date_from, p.date_to)
    lot = await resolve_opt("lot", p.lot_number)
    args = std(p, destination=p.destination, lot_id=rid(lot), date_from=p.date_from, date_to=p.date_to)
    rows = await db.fetch_all("records", Q.CO_PRODUCT, args)
    totals = await db.fetch_all("records", Q.CO_PRODUCT_TOTALS, args)
    on_hand = await db.fetch_all("records", Q.CO_PRODUCT_ON_HAND)
    out = [fmt.drop_none({**{k: v for k, v in r.items() if k not in ("qty_base", "base_unit_code")}, "qty": fmt.q(r["qty_base"], r["base_unit_code"], fruit=True)}) for r in rows]
    return page(out, p, resolved=echo(lot=lot),
                totals_by_destination=[{"destination": t["destination"], "dispositions": t["dispositions"], "qty": fmt.q(t["qty_base"], t["base_unit_code"], fruit=True)} for t in totals],
                still_on_hand=[{"lot_number": o["lot_number"], "item": o["item_name"], "location": o["location_name"], "qty": fmt.q(o["qty_on_hand"], o["base_unit_code"], fruit=True)} for o in on_hand])


# Equipment scheduling (db/023, docs/16) ------------------------------------------------------------------------

RESOURCE_KINDS = ("vessel", "equipment")
VESSEL_TYPES = ("tank", "fermenter", "brite", "tote", "ibc", "barrel", "press")
EQUIPMENT_TYPES = ("mill", "pump", "filter", "chiller", "carbonator", "canning_line", "bottling_line", "keg_line", "keg_washer", "labeler", "other")


def _kind_filter(kind: str | None) -> tuple[str | None, str | None]:
    """'vessel' / 'equipment' → the resource kind; a type such as 'fermenter' or 'canning_line' → the kind and the type."""
    if kind is None:
        return None, None
    k = kind.strip().lower().replace(" ", "_")
    if k in RESOURCE_KINDS:
        return k, None
    if k in VESSEL_TYPES:
        return "vessel", k
    if k in EQUIPMENT_TYPES:
        return "equipment", k
    raise ToolFailure(f"Unknown kind '{kind}'. Use vessel, equipment, or one of: " + ", ".join(VESSEL_TYPES + EQUIPMENT_TYPES) + ".")


def _window(date_from: dt.date | None, date_to: dt.date | None, default_weeks: int) -> tuple[dt.datetime, dt.datetime, dt.date, dt.date]:
    tz = ZoneInfo(fmt.tz())
    start = date_from or fmt.today()
    end = date_to or (start + dt.timedelta(weeks=default_weeks))
    if end < start:
        raise ToolFailure("date_to is before date_from.")
    return (dt.datetime.combine(start, dt.time(), tz), dt.datetime.combine(end + dt.timedelta(days=1), dt.time(), tz), start, end)


def _booking(r: dict) -> dict:
    tz = ZoneInfo(fmt.tz())
    out = {"reservation_id": r["id"], "resource": r["resource_name"], "resource_kind": r["resource_kind"], "resource_type": r["resource_type"],
           "resource_status": r["resource_status"], "booking": r["kind"], "role": r["role"], "from": r["local_from"], "to": r["local_to"],
           "all_day": r["all_day"], "shared": r["shared"], "overlaps": r["clash_count"], "notes": r["notes"]}
    if r["subject_kind"]:
        out["for"] = {"kind": r["subject_kind"], "number": r["subject_number"], "label": r["subject_label"], "status": r["subject_status"]}
    if not r["all_day"]:
        out["starts_at"] = r["starts_at"].astimezone(tz).strftime("%Y-%m-%d %H:%M")
        out["ends_at"] = r["ends_at"].astimezone(tz).strftime("%Y-%m-%d %H:%M")
    return fmt.drop_none(out)


class ScheduleInput(Paged):
    vessel: str | None = Field(None, max_length=60, description="One vessel, e.g. FV-1 (tanks and presses are vessels).")
    equipment: str | None = Field(None, max_length=120, description="One piece of equipment, e.g. 'canning line', 'pump 1'.")
    kind: str | None = Field(None, max_length=30, description="'vessel' or 'equipment', or a type: fermenter, brite, tank, press, canning_line, pump, filter, …")
    premises: str | None = Field(None, max_length=120, description="Premises name; default all.")
    date_from: dt.date | None = Field(None, description="Window start (ISO date); default today.")
    date_to: dt.date | None = Field(None, description="Window end, inclusive; default 4 weeks after date_from.")
    order: str | None = Field(None, max_length=40, description="Only the bookings of one production order (WO-00012).")
    batch: str | None = Field(None, max_length=40, description="Only the bookings of one batch (B-26-004).")


@records_tool("equipment_schedule", "What is booked on which equipment, when",
              """Call for the equipment schedule: what is booked on which tank, press, line or piece of equipment, when and for which
              run (E1) — "what is on the canning line next week", "when is FV-2 booked", "what equipment does WO-00012 hold". Each
              booking: the resource, the run (production order, batch, press run or packaging run) or the block (cleaning,
              maintenance, hold), its role, its days (and times when not all day), whether it is shared and how many other
              bookings it overlaps. Bookings of cancelled runs are gone; what a tank holds right now is production_tank_board.""")
async def equipment_schedule(p: ScheduleInput) -> dict:
    vessel = await resolve_opt("vessel", p.vessel)
    equipment = await resolve_opt("equipment", p.equipment)
    premises = await resolve_opt("premises", p.premises)
    order = await resolve_opt("order", p.order)
    batch = await resolve_opt("batch", p.batch)
    if vessel and equipment:
        raise ToolFailure("Name a vessel or a piece of equipment, not both.")
    resource_kind, resource_type = _kind_filter(p.kind)
    resource_id = None
    if vessel:
        resource_kind, resource_id = "vessel", rid(vessel)
    elif equipment:
        resource_kind, resource_id = "equipment", rid(equipment)
    subject_kind = subject_id = None
    if order:
        subject_kind, subject_id = "production_order", rid(order)
    elif batch:
        subject_kind, subject_id = "batch", rid(batch)
    starts, ends, d0, d1 = _window(p.date_from, p.date_to, 4)
    rows = await db.fetch_all("records", Q.EQUIPMENT_SCHEDULE, std(p, starts=starts, ends=ends, resource_kind=resource_kind, resource_id=resource_id,
                                                                     resource_type=resource_type, premises_id=rid(premises), subject_kind=subject_kind, subject_id=subject_id))
    out = [_booking(r) for r in rows]
    return page(out, p, resolved=echo(vessel=vessel, equipment=equipment, premises=premises, order=order, batch=batch),
                window={"from": d0, "to": d1}, shared=sum(1 for o in out if o.get("shared")), overlapping=sum(1 for o in out if o.get("overlaps")))


class FreeInput(Input):
    kind: str = Field(..., max_length=30, description="What is needed: 'vessel', 'equipment', or a type such as fermenter, brite, tank, press, canning_line, pump, filter.")
    days: int = Field(1, ge=1, le=365, description="How many whole days in a row are needed.")
    min_capacity_l: float | None = Field(None, ge=0, description="For vessels: at least this capacity in liters.")
    min_capacity_gal: float | None = Field(None, ge=0, description="For vessels: at least this capacity in US gallons (converted).")
    premises: str | None = Field(None, max_length=120, description="Premises name; default all.")
    date_from: dt.date | None = Field(None, description="Look from this date (ISO); default today.")
    date_to: dt.date | None = Field(None, description="Look up to this date, inclusive; default 12 weeks after date_from.")
    limit: int = Field(20, ge=1, le=100, description="At most this many resources.")


@records_tool("equipment_free", "When is equipment free",
              """Call to find when a resource is free for a stretch of days (E2) — "where can I ferment 500 gallons for two weeks
              from Monday", "when is a brite free for 3 days", "which fermenters are open in December". For every active vessel or
              piece of equipment of the kind (with at least the capacity, when given) that is not out of service: its free
              windows of at least `days` whole days inside the window, the first one first; resources with the earliest free
              window come first. A free window means no booking at all; it does not look at what a tank holds today
              (production_tank_board does).""")
async def equipment_free(p: FreeInput) -> dict:
    premises = await resolve_opt("premises", p.premises)
    resource_kind, resource_type = _kind_filter(p.kind)
    min_l = p.min_capacity_l
    if p.min_capacity_gal is not None:
        min_l = max(min_l or 0.0, p.min_capacity_gal * 3.785411784)
    starts, ends, d0, d1 = _window(p.date_from, p.date_to, 12)
    resources = await db.fetch_all("records", Q.EQUIPMENT_RESOURCES, {"resource_kind": resource_kind, "resource_type": resource_type, "premises_id": rid(premises), "min_capacity_l": min_l})
    booked = await db.fetch_all("records", Q.EQUIPMENT_BOOKED_WINDOWS, {"starts": starts, "ends": ends})
    by_key: dict[tuple[str, int], list[tuple[dt.datetime, dt.datetime]]] = {}
    for b in booked:
        by_key.setdefault((b["resource_kind"], b["resource_id"]), []).append((b["starts_at"], b["ends_at"]))
    need = dt.timedelta(days=p.days)
    tz = ZoneInfo(fmt.tz())
    out = []
    for r in resources:
        cursor = starts
        windows = []
        for s, e in sorted(by_key.get((r["resource_kind"], r["resource_id"]), [])):
            if s > cursor and s - cursor >= need:
                windows.append((cursor, s))
            cursor = max(cursor, e)
        if ends > cursor and ends - cursor >= need:
            windows.append((cursor, ends))
        if not windows:
            continue
        out.append({"resource": r["name"], "resource_kind": r["resource_kind"], "type": r["kind"], "status": r["status"], "capacity": vol(r["capacity_l"]),
                    "free": [{"from": s.astimezone(tz).date(), "to": (e.astimezone(tz) - dt.timedelta(days=1)).date(), "days": (e - s).days} for s, e in windows],
                    "first_free": windows[0][0].astimezone(tz).date()})
    out.sort(key=lambda o: (o["first_free"], o["resource"]))
    return {"resolved": echo(premises=premises), "window": {"from": d0, "to": d1}, "days_needed": p.days, "count": len(out), "resources": out[: p.limit],
            "none_free": [r["name"] for r in resources if r["name"] not in {o["resource"] for o in out}]}
