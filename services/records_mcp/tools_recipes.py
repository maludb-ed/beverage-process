"""Recipe and product tools (R18 to R22, R50, product_find)."""
from __future__ import annotations

from typing import Literal

from pydantic import Field, model_validator

from common import db

from . import fmt, queries as Q
from .registry import Input, Paged, ToolFailure, page, records_tool
from .resolve import echo, resolve, resolve_opt, rid
from .tools_receiving import is_fruit, std


class VolumeMixin(Input):
    batch_volume_l: float | None = Field(None, gt=0, le=1_000_000, description="Batch volume in liters.")
    batch_volume_gal: float | None = Field(None, gt=0, le=300_000, description="Batch volume in US gallons (instead of liters).")

    def volume_l(self, default: float) -> float:
        if self.batch_volume_l and self.batch_volume_gal:
            raise ToolFailure("Give batch_volume_l or batch_volume_gal, not both.")
        if self.batch_volume_gal:
            return self.batch_volume_gal * (fmt.factor("gal") or 3.785411784)
        return self.batch_volume_l or default


async def _version(product: dict, version_no: int | None) -> dict:
    rv = await db.fetch_one("records", Q.RECIPE_VERSION, {"product_id": product["id"], "version_no": version_no})
    if rv is None:
        versions = await db.fetch_all("records", Q.RECIPE_VERSIONS_ALL, {"product_id": product["id"]})
        listed = ", ".join(f"v{v['version_no']} ({v['status']})" for v in versions) or "none"
        which = f"version {version_no}" if version_no else "an active recipe version"
        raise ToolFailure(f"{product['label']} has no {which}. Versions: {listed}.")
    return rv


def _version_header(rv: dict) -> dict:
    return fmt.drop_none({
        "product": rv["product_name"], "product_code": rv["product_code"], "version_no": rv["version_no"], "status": rv["status"],
        "target_batch_volume": fmt.liters(rv["target_batch_volume_l"]), "expected_total_loss_pct": rv["expected_total_loss_pct"],
        "standard_cost_total_at_activation": fmt.money(rv["standard_cost_total"]), "standard_cost_per_l": rv["standard_cost_per_l"],
        "change_note": rv["change_note"], "activated_at": rv["activated_at"], "activated_by": rv["activated_by"]})


class RecipeCurrentInput(VolumeMixin):
    product: str = Field(..., min_length=1, max_length=120, description="Product code or name.")
    version: int | None = Field(None, ge=1, le=10_000, description="A specific version number; default the active version.")


@records_tool("recipe_current", "Current recipe scaled to a batch size",
              """Call for the current recipe for a product at a given batch size (R18). Returns the active (or requested) version
              with its stages (expected loss, duration, instructions) and lines (item, stage, purpose, per-liter or fixed quantity)
              scaled to the batch volume (liters or gallons; default the recipe's target volume).""")
async def recipe_current(p: RecipeCurrentInput) -> dict:
    product = await resolve("product", p.product)
    rv = await _version(product, p.version)
    vol = p.volume_l(float(rv["target_batch_volume_l"]))
    stages = await db.fetch_all("records", Q.RECIPE_STAGES, {"rv_id": rv["id"]})
    lines = await db.fetch_all("records", Q.RECIPE_LINES, {"rv_id": rv["id"], "volume_l": vol, "tz": fmt.tz()})
    return {
        "resolved": echo(product=product), "recipe": _version_header(rv), "batch_volume": fmt.liters(vol),
        "stages": [fmt.drop_none(dict(s)) for s in stages],
        "lines": [fmt.drop_none({
            "seq": l["seq"], "item_code": l["item_code"], "item_name": l["item_name"], "stage": l["stage_code"], "purpose": l["purpose"],
            "basis": "fixed per batch" if l["qty_per_batch_base"] is not None else f"{l['qty_per_l']} {l['base_unit_code']} per L",
            "qty": fmt.q(l["qty_required"], l["base_unit_code"], fruit=is_fruit(l)), "consumption_mode": l["consumption_mode"], "notes": l["notes"]})
            for l in lines],
    }


class RecipeDiffInput(Input):
    product: str = Field(..., min_length=1, max_length=120, description="Product code or name.")
    from_version: int | None = Field(None, ge=1, le=10_000, description="Older version; default the version before to_version.")
    to_version: int | None = Field(None, ge=1, le=10_000, description="Newer version; default the active version.")


def _line_key(l: dict) -> tuple:
    return (l["item_code"], l["stage_code"], l["purpose"])


@records_tool("recipe_diff", "Differences between recipe versions",
              """Call for what changed between two recipe versions and why (R19). Returns lines added, removed and changed
              (quantity, stage, mode), stage changes (expected loss, duration, instructions), both versions' change notes, who
              activated each and when. Defaults compare the active version with the one before it.""")
async def recipe_diff(p: RecipeDiffInput) -> dict:
    product = await resolve("product", p.product)
    to_rv = await _version(product, p.to_version)
    from_no = p.from_version
    if from_no is None:
        versions = [v["version_no"] for v in await db.fetch_all("records", Q.RECIPE_VERSIONS_ALL, {"product_id": product["id"]})]
        older = [v for v in versions if v < to_rv["version_no"]]
        if not older:
            raise ToolFailure(f"{product['label']} v{to_rv['version_no']} has no earlier version to compare with.")
        from_no = max(older)
    from_rv = await _version(product, from_no)
    args = {"volume_l": 1, "tz": fmt.tz()}
    a_lines = {_line_key(l): l for l in await db.fetch_all("records", Q.RECIPE_LINES, {**args, "rv_id": from_rv["id"]})}
    b_lines = {_line_key(l): l for l in await db.fetch_all("records", Q.RECIPE_LINES, {**args, "rv_id": to_rv["id"]})}
    fields = ("qty_per_batch_base", "qty_per_l", "consumption_mode", "notes")
    simple = lambda l: fmt.drop_none({"item_code": l["item_code"], "item_name": l["item_name"], "stage": l["stage_code"], "purpose": l["purpose"],
                                      "qty_per_batch_base": l["qty_per_batch_base"], "qty_per_l": l["qty_per_l"], "unit": l["base_unit_code"]})
    changed = []
    for k in a_lines.keys() & b_lines.keys():
        a, b = a_lines[k], b_lines[k]
        diffs = {f: {"from": a[f], "to": b[f]} for f in fields if a[f] != b[f]}
        if diffs:
            entry = {"item_code": k[0], "stage": k[1], "purpose": k[2], "unit": b["base_unit_code"], "changes": diffs}
            for f in ("qty_per_batch_base", "qty_per_l"):
                if f in diffs and a[f] and b[f]:
                    entry["change_pct"] = round(100 * (float(b[f]) - float(a[f])) / float(a[f]), 1)
            changed.append(entry)
    a_st = {s["stage_code"]: s for s in await db.fetch_all("records", Q.RECIPE_STAGES, {"rv_id": from_rv["id"]})}
    b_st = {s["stage_code"]: s for s in await db.fetch_all("records", Q.RECIPE_STAGES, {"rv_id": to_rv["id"]})}
    st_fields = ("seq", "expected_loss_pct", "expected_duration_days", "instructions")
    stage_changes = [{"stage": k, "changes": {f: {"from": a_st[k][f], "to": b_st[k][f]} for f in st_fields if a_st[k][f] != b_st[k][f]}}
                     for k in a_st.keys() & b_st.keys() if any(a_st[k][f] != b_st[k][f] for f in st_fields)]
    return {
        "resolved": echo(product=product), "from": _version_header(from_rv), "to": _version_header(to_rv),
        "lines_added": [simple(b_lines[k]) for k in b_lines.keys() - a_lines.keys()],
        "lines_removed": [simple(a_lines[k]) for k in a_lines.keys() - b_lines.keys()],
        "lines_changed": changed, "stages_added": sorted(b_st.keys() - a_st.keys()), "stages_removed": sorted(a_st.keys() - b_st.keys()),
        "stages_changed": stage_changes,
        "why": to_rv["change_note"] or "No change note was recorded on the newer version.",
    }


class RecipeBatchesInput(Paged):
    product: str = Field(..., min_length=1, max_length=120, description="Product code or name.")
    version: int | None = Field(None, ge=1, le=10_000, description="Only batches made with this version number.")


@records_tool("recipe_batches_made", "Batches made with a recipe version",
              """Call for which batches were made with recipe version N (R20). Lists batches per version with status, stage,
              origin (pitch, split, blend), start and close dates, current volume and production order. Also counts batches of the
              product with no recipe version (blends and dumps).""")
async def recipe_batches_made(p: RecipeBatchesInput) -> dict:
    product = await resolve("product", p.product)
    rows = await db.fetch_all("records", Q.RECIPE_BATCHES, std(p, product_id=product["id"], version_no=p.version))
    out = [fmt.drop_none({**dict(r), "current_volume_l": None, "current_volume": fmt.liters(r["current_volume_l"])}) for r in rows]
    nov = await db.fetch_one("records", Q.RECIPE_BATCHES_NO_VERSION, {"product_id": product["id"]})
    return page(out, p, resolved=echo(product=product), batches_without_recipe_version=fmt.drop_none(dict(nov)) if nov["batches"] else None)


class CanMakeInput(VolumeMixin):
    product: str = Field(..., min_length=1, max_length=120, description="Product code or name.")
    version: int | None = Field(None, ge=1, le=10_000, description="Recipe version; default active.")


@records_tool("recipe_can_make", "Can we make a batch with stock on hand",
              """Call for whether a product can be made at a volume with what is on hand (R21). For each recipe line: required at
              that volume, released and unallocated stock, stock already allocated to open orders, stock awaiting release, on order,
              and the shortfall. can_make is true when every line is covered by released stock.""")
async def recipe_can_make(p: CanMakeInput) -> dict:
    product = await resolve("product", p.product)
    rv = await _version(product, p.version)
    vol = p.volume_l(float(rv["target_batch_volume_l"]))
    lines = await db.fetch_all("records", Q.RECIPE_LINES, {"rv_id": rv["id"], "volume_l": vol, "tz": fmt.tz()})
    out, ok, ok_after = [], True, True
    for l in lines:
        u, fr = l["base_unit_code"], is_fruit(l)
        req, avail, alloc = float(l["qty_required"]), float(l["released_available"]), float(l["allocated_to_orders"])
        short = max(req - avail, 0.0)
        short_after = max(req - (avail - alloc), 0.0)
        ok &= short <= 1e-9
        ok_after &= short_after <= 1e-9
        out.append(fmt.drop_none({
            "item_code": l["item_code"], "item_name": l["item_name"], "stage": l["stage_code"], "purpose": l["purpose"],
            "required": fmt.q(req, u, fruit=fr), "released_available": fmt.q(avail, u, fruit=fr),
            "allocated_to_other_orders": fmt.q(alloc, u, fruit=fr) if alloc else None,
            "awaiting_release_or_rejected": fmt.q(float(l["on_hand_all_statuses"]) - avail, u, fruit=fr) if float(l["on_hand_all_statuses"]) > avail + 1e-9 else None,
            "on_order": fmt.q(l["qty_on_order"], u, fruit=fr) if l["qty_on_order"] else None,
            "shortfall": fmt.q(short, u, fruit=fr), "shortfall_after_allocations": fmt.q(short_after, u, fruit=fr) if alloc else None}))
    return {"resolved": echo(product=product), "recipe_version": rv["version_no"], "batch_volume": fmt.liters(vol), "can_make": ok,
            "can_make_after_other_allocations": ok_after, "lines": out, "short_items": [l["item_code"] for l in out if l["shortfall"]["base"] > 0]}


class StandardCostInput(VolumeMixin):
    product: str = Field(..., min_length=1, max_length=120, description="Product code or name.")
    version: int | None = Field(None, ge=1, le=10_000, description="Recipe version; default active.")
    premises: str | None = Field(None, max_length=120, description="Premises whose overhead rate applies; default the latest rate.")


@records_tool("recipe_standard_cost", "Standard cost of a batch",
              """Call for the standard cost of a batch of a product (R22). Each recipe line is costed at the item's standard cost,
              or the last lot cost when no standard exists, plus overhead per liter at the premises rate. Returns line costs, overhead,
              total, cost per liter and per gallon, and the snapshot taken when the version was activated.""")
async def recipe_standard_cost(p: StandardCostInput) -> dict:
    product = await resolve("product", p.product)
    premises = await resolve_opt("premises", p.premises)
    rv = await _version(product, p.version)
    vol = p.volume_l(float(rv["target_batch_volume_l"]))
    lines = await db.fetch_all("records", Q.RECIPE_LINES, {"rv_id": rv["id"], "volume_l": vol, "tz": fmt.tz()})
    oh = await db.fetch_one("records", Q.OVERHEAD_RATE, {"premises_id": rid(premises), "tz": fmt.tz()})
    out, material = [], 0.0
    for l in lines:
        cost_basis, unit_cost = ("standard", l["standard_cost_per_base"]) if l["standard_cost_per_base"] is not None else ("last lot cost", l["last_lot_cost_per_base"])
        cost = None if unit_cost is None else float(l["qty_required"]) * float(unit_cost)
        material += cost or 0.0
        out.append(fmt.drop_none({
            "item_code": l["item_code"], "item_name": l["item_name"], "qty": fmt.q(l["qty_required"], l["base_unit_code"], fruit=is_fruit(l)),
            "unit_cost": fmt.unit_price(unit_cost, l["base_unit_code"], fruit=is_fruit(l)), "cost_basis": cost_basis if unit_cost is not None else "no cost on file",
            "cost": fmt.money(cost)}))
    overhead = float(oh["rate_per_l"]) * vol if oh else 0.0
    total = material + overhead
    return {
        "resolved": echo(product=product, premises=premises), "recipe": _version_header(rv), "batch_volume": fmt.liters(vol), "lines": out,
        "material_cost": fmt.money(material),
        "overhead": fmt.drop_none({"rate_per_l": oh and oh["rate_per_l"], "effective_from": oh and oh["effective_from"], "premises": oh and oh["premises_name"], "cost": fmt.money(overhead)}),
        "total_cost": fmt.money(total), "cost_per_l": round(total / vol, 4), "cost_per_gal": round(total / vol * (fmt.factor("gal") or 3.785411784), 4),
        "missing_costs": [l["item_code"] for l in out if l["cost_basis"] == "no cost on file"],
    }


class ApprovalsInput(Paged):
    status: Literal["not_required", "required", "submitted", "approved", "expired", "rejected"] | None = Field(
        None, description="Only this status. Default: required, submitted, expired, rejected, and approved ones expiring soon.")
    product: str | None = Field(None, max_length=120, description="One product.")
    expiring_within_days: int = Field(60, ge=0, le=3650, description="With the default filter, approved items expiring within this many days are included.")


@records_tool("product_approvals_needed", "Formula and label approvals needed",
              """Call for which products need a TTB formula or label (COLA) approval, have one submitted, rejected or expired, or
              will expire soon (R50). Each row: product, kind, package, reference, status, approved and expiry dates, days to expiry.
              Also lists active products with no formula record at all.""")
async def product_approvals_needed(p: ApprovalsInput) -> dict:
    product = await resolve_opt("product", p.product)
    rows = await db.fetch_all("records", Q.PRODUCT_APPROVALS, std(p, product_id=rid(product), status=p.status, expiring_within_days=p.expiring_within_days))
    missing = await db.fetch_all("records", Q.PRODUCTS_WITHOUT_FORMULA)
    return page([fmt.drop_none(dict(r)) for r in rows], p, resolved=echo(product=product),
                products_without_formula_record=[dict(m) for m in missing] or None)


class ProductFindInput(Paged):
    query: str | None = Field(None, max_length=120, description="Code, name or style fragment; omit to list all products.")
    status: Literal["draft", "active", "retired"] | None = Field(None, description="Only this status.")


@records_tool("product_find", "Find products",
              """Call to find a product by code, name or style before another tool needs its name, or to list products. Returns
              code, name, status, intended tax class, target ABV, fruit share, active recipe version, batch counts and package
              configurations.""")
async def product_find(p: ProductFindInput) -> dict:
    pat = None if not p.query else f"%{p.query}%"
    rows = await db.fetch_all("records", Q.PRODUCT_FIND, std(p, pat=pat, q=p.query or "", status=p.status))
    return page([fmt.drop_none(dict(r)) for r in rows], p)
