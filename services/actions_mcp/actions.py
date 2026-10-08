"""The action tools. Each one resolves names to ids (records role), validates what the
model can get wrong (units, plausibility, options), then POSTs to the app's own
endpoint as the user (core.perform). Descriptions are routing knowledge: when to call,
when not, terminal semantics. Placeholders <<...>> are filled from the catalog at startup.

No `from __future__ import annotations` here: MCPServer reads these annotations at runtime.
"""
from datetime import date
from typing import Annotated, Any, Literal

from pydantic import BaseModel, ConfigDict, Field

from common import db

from . import catalog, resolve, units
from .core import ACTIONS as ACTIONS_META
from .core import ActionMeta, Call, Invalid, action, needs_confirmation, perform

TERMINAL = "After success: read back what was recorded in one short sentence (the `done` text) and END THE TURN — no further tool calls. Failures return field messages or candidates: fix the one value and retry once, or ask the user one short question."
NOT_FOR = "Do NOT call this to answer questions (use the record/activity tools) or to move between screens (use navigate)."
CONFIRM = "If the result is needs_confirmation, ask the user the summary as one short yes/no question and end the turn; when the user confirms, call again with the same arguments plus confirmed=true (it executes only on the user's confirmed turn)."

Confirmed = Annotated[bool, Field(description="Set true when re-calling after the user confirmed this tool's needs_confirmation summary. The app's confirmation header, not this flag, is what lets it execute.")]
Note = Annotated[str | None, Field(default=None, max_length=2000, description="Optional note, the user's words")]
When = Annotated[str | None, Field(default=None, description="When it happened, ISO 'YYYY-MM-DDTHH:MM' in local time; omit for now")]


class Strict(BaseModel):
    model_config = ConfigDict(extra="forbid")


# Shared lookups ---------------------------------------------------------------------------

async def active_batch(name: str) -> dict[str, Any]:
    try:
        found = await resolve.resolve("batch", name, where="b.status = 'active'", what="active batch")
    except resolve.ResolveError:
        try:
            other = await resolve.resolve("batch", name)
        except resolve.ResolveError:
            raise
        raise Invalid(f"Batch {other['label']} is not active ({other['detail']}) and accepts no more events.", "batch")
    row = await db.fetch_one("records", """SELECT b.id, b.number, b.current_stage_code, b.current_volume_l::float AS volume_l, b.notes,
                                                  b.tax_class_override, b.tax_class_override_reason_code_id, p.name AS product
                                           FROM app.batches b JOIN app.products p ON p.id = b.product_id WHERE b.id = %s""", (found["id"],))
    return row


async def gal(liters: float) -> str:
    qty, unit = await units.base_to_display(liters, "L")
    return units.fmt(qty, unit, 1)


def reason_by_code(text: str, rows: list[dict[str, Any]], what: str) -> dict[str, Any]:
    key = text.strip().lower()
    for r in rows:
        if key in (r["code"].lower(), r["name"].lower()):
            return r
    matches = [r for r in rows if key in r["name"].lower() or key in r["code"].lower()]
    if len(matches) == 1:
        return matches[0]
    raise Invalid(f"No {what} '{text}'. Choose one of: " + ", ".join(f"{r['code']} ({r['name']})" for r in rows) + ".", "reason",
                  [{"code": r["code"], "name": r["name"]} for r in rows])


async def item_units(item_id: int) -> dict[str, float]:
    rows = await db.fetch_all("records", "SELECT unit_code, to_base_factor::float AS f FROM app.item_units WHERE item_id = %s", (item_id,))
    return {r["unit_code"]: r["f"] for r in rows}


async def item_row(item_id: int) -> dict[str, Any]:
    return await db.fetch_one("records", "SELECT id, code, name, item_class, base_unit_code, lot_controlled, catch_weight FROM app.items WHERE id = %s", (item_id,))


def unit_kind(item_class: str) -> str:
    return "fruit" if item_class == "fruit" else "default"


async def default_premises() -> int:
    rows = await db.fetch_all("records", "SELECT id FROM app.premises WHERE active ORDER BY id")
    if len(rows) != 1:
        raise Invalid("There is more than one premises; say which one.", "premises")
    return rows[0]["id"]


# Batch execution --------------------------------------------------------------------------

@action(ActionMeta(
    name="batch_reading_record", title="Record batch reading", doc_actions=["batch_reading_record"],
    endpoint="POST /batches/{id}/readings/save", role="production", confirm="never",
    undo_kind="delete_row", undo_how="POST /lab/{reading_id}/delete (reading id from the activity row's after.reading_id; only the person who took it, the same day)",
    events=["batch_reading_recorded"], refresh=["batchesChanged"]))
async def batch_reading_record(
    call: Call,
    batch: Annotated[str, Field(description="Batch number as said, e.g. 'B-26-004' (dictation like 'b 26 004' is fine). On a batch screen, use the screen's record. Pass the label; never invent an id.")],
    measurement: Annotated[str, Field(description="Measurement code or name: <<measurements>>")],
    value: Annotated[float, Field(description="The reading in the measurement's own unit (pH units, °Bx, SG like 1.012, mg/L, °C — convert °F to °C). Spelled-out numbers are fine: 'three point four' = 3.4.")],
    taken_at: When = None,
    stage: Annotated[str | None, Field(default=None, description="Stage code if not the batch's current stage: <<stages>>")] = None,
    note: Note = None,
) -> dict[str, Any]:
    """Record a reading on an active batch: pH, Brix, SG, SO2, temperature, ABV, CO2, TA and so on.

    Call when the user reports a measurement they took on a batch: "record a pH of 3.4 on B-26-004",
    "gravity is 1.012", "log brix twelve on that batch". Executes immediately (no confirmation) and
    returns what was recorded, whether it is in spec, and an undo_id. For a reading on a lot (not a
    batch) use lab_reading_record. <<not_for>>

    <<terminal>>"""
    b = await active_batch(batch)
    m = catalog.find_measurement(measurement)
    if m is None:
        raise Invalid(f"No measurement '{measurement}'. Use one of: " + ", ".join(catalog.data["measurements"]), "measurement",
                      list(catalog.data["measurements"]))
    if not (m["min_valid"] <= value <= m["max_valid"]):
        raise Invalid(f"{m['name']} {value:g} is outside the plausible range {m['min_valid']:g}–{m['max_valid']:g} {m['unit']}; check what was said.", "value")
    stage_code = catalog.find_stage(stage) if stage else None
    if stage and not stage_code:
        raise Invalid(f"No stage '{stage}'. Stages: {catalog.stage_lines()}", "stage")
    at = await units.local_datetime(taken_at)
    result = await perform(call, ACTIONS_META["batch_reading_record"], f"/batches/{b['id']}/readings/save",
                           [("measurement", m["code"]), ("value", f"{value:g}"), ("taken_at", at), ("stage", stage_code or ""), ("note", note or "")],
                           done=f"{m['name']} {value:g}{'' if m['unit'] == m['name'] else ' ' + m['unit']} recorded on {b['number']}",
                           field_map={"measurement": "measurement", "value": "value", "taken_at": "taken_at", "stage": "stage"})
    if result["status"] == "success":
        spec = await db.fetch_one("records", """SELECT r.spec_result, s.min_value::float AS lo, s.max_value::float AS hi FROM app.readings r
                                                LEFT JOIN app.specs s ON s.id = r.spec_id
                                                WHERE r.target_kind = 'batch' AND r.target_id = %s ORDER BY r.id DESC LIMIT 1""", (b["id"],))
        if spec and spec["spec_result"] == "fail":
            result["done"] += f" — OUT OF SPEC ({spec['lo']:g}–{spec['hi']:g} {m['unit']})"
        elif spec and spec["spec_result"] == "pass":
            result["done"] += " (in spec)"
        result["recorded"] = {"batch": b["number"], "measurement": m["code"], "value": value, "unit": m["unit"], "taken_at": at}
    return result


@action(ActionMeta(
    name="batch_addition_record", title="Record batch addition", doc_actions=["batch_addition_record"],
    endpoint="POST /batches/{id}/additions/save", role="production", confirm="always (no undo endpoint: an addition issues stock from a lot)",
    undo_kind="none", undo_how="The manifest's 'reverse' has no endpoint yet; correct with an inventory adjustment on the screen.",
    events=["batch_addition_recorded"], refresh=["batchesChanged", "lotsChanged", "inventoryChanged"]))
async def batch_addition_record(
    call: Call,
    batch: Annotated[str, Field(description="Batch number as said, e.g. 'B-26-004'; on a batch screen use the screen's record")],
    item: Annotated[str, Field(description="Additive, yeast or juice item as said: code or name, e.g. 'Fermaid K', 'KMS'")],
    qty: Annotated[float, Field(gt=0, le=100000, description="Quantity added, in `unit`")],
    unit: Annotated[str, Field(description="Unit of qty as said: g, kg, oz, lb, mL, L, gal, ea, or the item's own unit")],
    purpose: Annotated[Literal["nutrient", "sulfite", "enzyme", "sweetener", "acid", "fining", "base_juice", "other"], Field(description="Why it was added")],
    lot: Annotated[str | None, Field(default=None, description="Lot number to issue from; omit to use the only released lot with stock (FEFO for non-lot-controlled items)")] = None,
    stage: Annotated[str | None, Field(default=None, description="Stage code if not the batch's current stage: <<stages>>")] = None,
    added_at: When = None,
    note: Note = None,
    confirmed: Confirmed = False,
) -> dict[str, Any]:
    """Record an ingredient added to an active batch (nutrient, sulfite, enzyme, sweetener, acid, fining, juice).

    Call when the user says they ADDED something to a batch: "added 30 grams of Fermaid K to B-26-004",
    "sulfite the batch with 20 g KMS". It issues the quantity from a released lot, so it always asks
    for confirmation first (no undo by voice). <<confirm>> <<not_for>>

    <<terminal>>"""
    b = await active_batch(batch)
    it = await resolve.resolve("item", item, where="(item_class IN ('additive', 'yeast', 'juice', 'intermediate') OR item_class IN (SELECT code FROM app.item_classes WHERE recipe_ingredient AND active AND NOT is_builtin))", what="additive, yeast or juice item")
    row = await item_row(it["id"])
    own = await item_units(row["id"])
    unit_code = units.normalize(unit) or row["base_unit_code"]
    if unit_code in own or unit_code == row["base_unit_code"]:
        send_unit, send_qty = unit_code, qty
    else:
        base = await units.to_base(qty, unit_code, row["base_unit_code"], own)
        if base is None:
            raise Invalid(f"{row['name']} is measured in {row['base_unit_code']}; '{unit}' does not convert to it.", "unit")
        send_unit, send_qty = row["base_unit_code"], round(base, 4)
    lots = await db.fetch_all("records", """SELECT lot_id, lot_number, sum(qty_on_hand)::float AS on_hand FROM app.v_lot_balances
                                            WHERE item_id = %s AND quality_status = 'released' AND qty_on_hand > 0
                                            GROUP BY lot_id, lot_number, expires_on, received_on ORDER BY expires_on NULLS LAST, received_on NULLS LAST, lot_number""", (row["id"],))
    if not lots:
        raise Invalid(f"No released lot of {row['name']} has stock.", "lot")
    if lot:
        chosen = [l for l in lots if l["lot_number"].lower() == lot.strip().lower()]
        if not chosen:
            raise Invalid(f"{lot} is not a released lot of {row['name']} with stock. Lots: " + ", ".join(l["lot_number"] for l in lots), "lot",
                          [l["lot_number"] for l in lots])
        chosen_lot = chosen[0]
    elif len(lots) == 1 or not row["lot_controlled"]:
        chosen_lot = lots[0]
    else:
        raise Invalid(f"{row['name']} has several released lots: " + ", ".join(l["lot_number"] for l in lots) + ". Which lot?", "lot",
                      [l["lot_number"] for l in lots])
    stage_code = catalog.find_stage(stage) if stage else b["current_stage_code"]
    if not stage_code:
        raise Invalid(f"No stage '{stage}'. Stages: {catalog.stage_lines()}", "stage")
    summary = f"Add {units.fmt(qty, unit_code, 3)} of {row['name']} (lot {chosen_lot['lot_number']}, {purpose}) to {b['number']}?"
    if not confirmed:
        return needs_confirmation(summary + " This issues stock and cannot be undone by voice.")
    at = await units.local_datetime(added_at)
    return await perform(call, ACTIONS_META["batch_addition_record"], f"/batches/{b['id']}/additions/save",
                         [("item_id", row["id"]), ("lot_id", chosen_lot["lot_id"]), ("unit", send_unit), ("qty", f"{send_qty:g}"),
                          ("purpose", purpose), ("stage", stage_code), ("added_at", at), ("note", note or "")],
                         done=f"Added {units.fmt(qty, unit_code, 3)} of {row['name']} (lot {chosen_lot['lot_number']}) to {b['number']}",
                         undo_available=False, undo_note="An addition issues stock; correct it with an inventory adjustment if it was wrong.")


@action(ActionMeta(
    name="batch_stage_move", title="Move batch stage", doc_actions=["batch_stage_move"],
    endpoint="POST /batches/{id}/stage", role="production", confirm="always (stage moves only go forward; no undo endpoint)",
    undo_kind="none", undo_how="The manifest's restore_prior is not possible: the endpoint accepts only later stages.",
    events=["batch_stage_moved"], refresh=["batchesChanged"]))
async def batch_stage_move(
    call: Call,
    batch: Annotated[str, Field(description="Batch number as said; on a batch screen use the screen's record")],
    to_stage: Annotated[str, Field(description="The later stage to move to (code or name): <<stages>>")],
    volume_out_gal: Annotated[float | None, Field(default=None, gt=0, le=100000, description="Volume leaving the stage in US GALLONS (convert liters ÷ 3.785); omit when all of it moves. Less than the batch volume records the rest as an expected loss.")] = None,
    moved_at: When = None,
    note: Note = None,
    confirmed: Confirmed = False,
) -> dict[str, Any]:
    """Move an active batch to a later stage (primary → rack → maturation → blend → back-sweeten → carbonate).

    Call when the user says a batch moved on: "move B-26-001 to maturation", "racked the batch, 118 gallons
    came off". Stage moves cannot be reversed, so it always asks for confirmation first. Packaging is not a
    stage move (use the packaging screens). <<confirm>> <<not_for>>

    <<terminal>>"""
    b = await active_batch(batch)
    code = catalog.find_stage(to_stage)
    current = catalog.data["stages"].get(b["current_stage_code"], {"display_order": 0})
    later = {c: s for c, s in catalog.data["stages"].items() if s["display_order"] > current["display_order"] and not s["is_terminal"]}
    if code not in later:
        raise Invalid(f"{b['number']} is at {b['current_stage_code']}; it can move to: " + (", ".join(later) or "no later stage (package it instead)") + ".",
                      "to_stage", list(later))
    current_gal = await gal(b["volume_l"])
    vol_text = f"{volume_out_gal:g} gal" if volume_out_gal else f"all {current_gal}"
    if not confirmed:
        return needs_confirmation(f"Move {b['number']} from {b['current_stage_code']} to {later[code]['name']} with {vol_text} leaving the stage? Stage moves cannot be undone.")
    at = await units.local_datetime(moved_at)
    vol_display = ""
    if volume_out_gal is not None:
        vol_display = f"{await units.to_display(volume_out_gal, 'gal', 'L'):.3f}"
    return await perform(call, ACTIONS_META["batch_stage_move"], f"/batches/{b['id']}/stage",
                         [("to_stage", code), ("moved_at", at), ("volume_out_gal", vol_display), ("note", note or "")],
                         done=f"{b['number']} moved to {later[code]['name']} ({vol_text})", undo_available=False,
                         undo_note="Stage moves only go forward.")


@action(ActionMeta(
    name="batch_loss_record", title="Record batch loss", doc_actions=["batch_loss_record"],
    endpoint="POST /batches/{id}/losses/save", role="production",
    confirm="always (no reversal endpoint; the manifest's 'yes when exceptional above threshold' is a subset)",
    undo_kind="none", undo_how="The manifest's 'reverse' has no endpoint yet.",
    events=["batch_loss_recorded"], refresh=["batchesChanged"]))
async def batch_loss_record(
    call: Call,
    batch: Annotated[str, Field(description="Batch number as said; on a batch screen use the screen's record")],
    qty_gal: Annotated[float, Field(gt=0, le=100000, description="Volume lost in US GALLONS (convert liters ÷ 3.785)")],
    reason: Annotated[str, Field(description="Loss reason code or name: <<loss_reasons>>")],
    stage: Annotated[str | None, Field(default=None, description="Stage of the loss if not the current stage")] = None,
    occurred_at: When = None,
    note: Annotated[str | None, Field(default=None, max_length=2000, description="What happened; REQUIRED for exceptional reasons (spill, breakage)")] = None,
    confirmed: Confirmed = False,
) -> dict[str, Any]:
    """Record volume lost from an active batch with a reason (spill, lees, racking, sampling, evaporation...).

    Call when the user reports lost cider: "we spilled five gallons of B-26-004", "lost 2 gallons to lees".
    A loss reduces the batch and is reportable to TTB, so it always asks for confirmation first. <<confirm>> <<not_for>>

    <<terminal>>"""
    b = await active_batch(batch)
    r = reason_by_code(reason, catalog.data["loss_reasons"], "loss reason")
    if r["classification"] == "exceptional" and not note:
        raise Invalid(f"{r['name']} is an exceptional loss: say what happened (note).", "note")
    current_gal = await units.to_display(b["volume_l"], "L", "L")
    if qty_gal > (current_gal or 0) + 0.001:
        raise Invalid(f"{b['number']} holds only {await gal(b['volume_l'])}.", "qty_gal")
    stage_code = catalog.find_stage(stage) if stage else b["current_stage_code"]
    if not confirmed:
        approval = ""
        if r["threshold_l"] is not None and qty_gal * 3.78541178 > r["threshold_l"]:
            approval = " It is above the approval threshold and will wait for compliance approval."
        return needs_confirmation(f"Record a {qty_gal:g} gal {r['name'].lower()} loss on {b['number']} (stage {stage_code})?{approval} Losses cannot be undone by voice.")
    at = await units.local_datetime(occurred_at)
    qty_display = await units.to_display(qty_gal, "gal", "L")
    return await perform(call, ACTIONS_META["batch_loss_record"], f"/batches/{b['id']}/losses/save",
                         [("qty_gal", f"{qty_display:.3f}"), ("reason", r["id"]), ("stage", stage_code), ("occurred_at", at), ("note", note or "")],
                         done=f"{qty_gal:g} gal {r['name'].lower()} loss recorded on {b['number']}", undo_available=False,
                         undo_note="Losses cannot be reversed by voice.")


@action(ActionMeta(
    name="batch_update", title="Update batch notes", doc_actions=["batch_update"],
    endpoint="POST /batches/{id}/save (save.php → edit-save.php)", role="production", confirm="never",
    undo_kind="restore_prior", undo_how="Re-POST the before-image notes to /batches/{id}/save",
    events=["batch_updated"], refresh=["batchesChanged"]))
async def batch_update(
    call: Call,
    batch: Annotated[str, Field(description="Batch number as said; on a batch screen use the screen's record")],
    notes: Annotated[str, Field(max_length=2000, description="The complete new notes text (replaces the old notes)")],
    append: Annotated[bool, Field(description="True to add the text to the end of the existing notes instead of replacing them")] = True,
) -> dict[str, Any]:
    """Change an active batch's notes ("note on B-26-004 that the airlock was replaced").

    Executes immediately and can be undone. It never changes the tax class override. <<not_for>>

    <<terminal>>"""
    b = await active_batch(batch)
    text = ((b["notes"] + "\n") if append and b["notes"] else "") + notes
    return await perform(call, ACTIONS_META["batch_update"], f"/batches/{b['id']}/save", [
        ("notes", text), ("tax_class_override", b["tax_class_override"] or "none"),
        ("tax_class_override_reason", b["tax_class_override_reason_code_id"] or "")],
        done=f"Notes on {b['number']} {'updated' if not append else 'added'}")


# Receiving and lots -----------------------------------------------------------------------

@action(ActionMeta(
    name="lot_attribute_set", title="Set lot attribute", doc_actions=["lot_attribute_set"],
    endpoint="POST /lots/{id}/attributes/save", role="quality", confirm="never",
    undo_kind="restore_prior", undo_how="Re-POST the before-image value to /lots/{id}/attributes/save; a new attribute (no prior value) cannot be removed (no endpoint)",
    events=["lot_attribute_set"], refresh=["lotsChanged"]))
async def lot_attribute_set(
    call: Call,
    lot: Annotated[str, Field(description="Lot number as said, e.g. 'L-261001-001'; on a lot screen use the screen's record")],
    attribute: Annotated[str, Field(description="Attribute key: <<attributes>>; or a new key in snake_case")],
    value_num: Annotated[float | None, Field(default=None, ge=-100000, le=10000000, description="Numeric value (Brix, pH, TA g/L, SO2 mg/L, ABV %, ...)")] = None,
    value_text: Annotated[str | None, Field(default=None, max_length=200, description="Text value (variety, orchard, block, strain)")] = None,
    unit: Annotated[str | None, Field(default=None, max_length=20, description="Unit code for value_num when it has one, e.g. 'g/L'")] = None,
) -> dict[str, Any]:
    """Set an attribute on a lot: Brix, pH, TA, SO2, ABV, variety, orchard, block, yeast strain, bins...

    Call when the user states a property of a lot: "set the brix of L-261001-001 to 12.5", "that lot is
    Golden Russet from Hill Orchard block 4" (one call per attribute). Executes immediately; replacing an
    existing value can be undone. <<not_for>>

    <<terminal>>"""
    found = await resolve.resolve("lot", lot)
    key = attribute.strip().lower().replace(" ", "_").replace("-", "_")
    synonyms = {"so2": "free_so2", "titratable_acidity": "ta", "alcohol": "abv", "bins": "bin_count", "yeast_strain": "strain", "net_weight": "net_kg"}
    key = synonyms.get(key, key)
    if value_num is None and not value_text:
        raise Invalid("Give a number (value_num) or a text value (value_text).", "value_num")
    if key in catalog.LOT_ATTRIBUTE_KEYS:
        fields = [("key", key), ("other_key", "")]
    else:
        fields = [("key", "other"), ("other_key", key)]
    prior = await db.fetch_one("records", "SELECT value_num::float AS value_num, value_text FROM app.lot_attributes WHERE lot_id = %s AND key = %s", (found["id"], key))
    shown = f"{value_num:g}{(' ' + unit) if unit else ''}" if value_num is not None else value_text
    result = await perform(call, ACTIONS_META["lot_attribute_set"], f"/lots/{found['id']}/attributes/save",
                           fields + [("value_num", "" if value_num is None else f"{value_num:g}"), ("value_text", value_text or ""), ("unit_code", unit or "")],
                           done=f"{catalog.LOT_ATTRIBUTE_KEYS.get(key, key)} of {found['label']} set to {shown}" + (
                               f" (was {prior['value_num']:g})" if prior and prior["value_num"] is not None else (f" (was {prior['value_text']})" if prior and prior["value_text"] else "")),
                           undo_available=prior is not None,
                           undo_note="This attribute is new on the lot; there is no endpoint to remove an attribute, so set it to the right value instead.")
    return result


@action(ActionMeta(
    name="lot_release", title="Lot release decision", doc_actions=["lot_release"],
    endpoint="POST /lots/{id}/release (release-save.php)", role="quality",
    confirm="always (the release screen confirms every decision; the manifest's 'yes when override' is a subset)",
    undo_kind="reverse", undo_how="POST /lots/{id}/release with to_status = the before-image status, basis 'other', note 'Undo of decision …'",
    events=["lot_released"], refresh=["lotsChanged"]))
async def lot_release(
    call: Call,
    lot: Annotated[str, Field(description="Lot number as said; on a lot screen use the screen's record")],
    to_status: Annotated[Literal["released", "hold", "rejected", "quarantine"], Field(description="The decision: release it, put it on hold, reject it (or back to quarantine)")],
    basis: Annotated[Literal["coa", "inspection", "readings", "sensory", "override", "other"], Field(description="What the decision is based on: coa (certificate of analysis), inspection, readings, sensory, override, other")],
    note: Note = None,
    override_reason: Annotated[str | None, Field(default=None, description="Required when basis is override: <<override_reasons>>")] = None,
    confirmed: Confirmed = False,
) -> dict[str, Any]:
    """Release, hold or reject a lot (a quality decision with its basis).

    Call when the user decides a lot's quality status: "release L-261001-001 based on the CoA", "put that lot
    on hold, it smells off", "reject lot L-261001-016". A decision changes what production may use, so it
    always asks for confirmation first; it can be undone (a new decision back to the prior status).
    <<confirm>> <<not_for>>

    <<terminal>>"""
    found = await resolve.resolve("lot", lot)
    current = await db.fetch_one("records", "SELECT quality_status FROM app.lots WHERE id = %s", (found["id"],))
    if current["quality_status"] == to_status:
        raise Invalid(f"Lot {found['label']} is already {to_status}.", "to_status")
    fields = [("to_status", to_status), ("basis", basis), ("note", note or "")]
    if basis == "override":
        if not override_reason:
            raise Invalid("An override needs a reason: " + ", ".join(r["code"] for r in catalog.data["override_reasons"]), "override_reason")
        r = reason_by_code(override_reason, catalog.data["override_reasons"], "override reason")
        fields += [("is_override", "1"), ("reason_code_id", r["id"])]
    if not confirmed:
        return needs_confirmation(f"Change lot {found['label']} from {current['quality_status']} to {to_status} (basis: {basis})?")
    return await perform(call, ACTIONS_META["lot_release"], f"/lots/{found['id']}/release", fields,
                         done=f"Lot {found['label']} is now {to_status} (was {current['quality_status']}, basis {basis})")


@action(ActionMeta(
    name="lab_reading_record", title="Record lot lab reading", doc_actions=["lab_reading_record"],
    endpoint="POST /lab/save (target_kind=lot)", role="quality", confirm="never",
    undo_kind="delete_row", undo_how="POST /lab/{reading_id}/delete (the activity row's entity_id; own readings, same day)",
    events=["lab_reading_recorded"], refresh=["readingsChanged"]))
async def lab_reading_record(
    call: Call,
    lot: Annotated[str, Field(description="Lot number as said (a juice, fruit or finished lot)")],
    measurement: Annotated[str, Field(description="Measurement code or name: <<measurements>>")],
    value: Annotated[float, Field(description="The reading in the measurement's own unit")],
    taken_at: When = None,
    method: Annotated[str | None, Field(default=None, max_length=200, description="Lab method, e.g. 'pH meter', 'Ripper titration'")] = None,
    note: Note = None,
) -> dict[str, Any]:
    """Record a lab test on a LOT (pH, Brix, SO2, TA, ABV...). For a reading on a batch use batch_reading_record.

    Call for "the juice lot L-261001-003 tested at pH 3.5". Executes immediately; can be undone the same day.
    <<not_for>>

    <<terminal>>"""
    found = await resolve.resolve("lot", lot)
    m = catalog.find_measurement(measurement)
    if m is None:
        raise Invalid(f"No measurement '{measurement}'. Use one of: " + ", ".join(catalog.data["measurements"]), "measurement")
    if not (m["min_valid"] <= value <= m["max_valid"]):
        raise Invalid(f"{m['name']} {value:g} is outside {m['min_valid']:g}–{m['max_valid']:g} {m['unit']}; check what was said.", "value")
    at = await units.local_datetime(taken_at)
    return await perform(call, ACTIONS_META["lab_reading_record"], "/lab/save", [
        ("target_kind", "lot"), ("target_id", found["id"]), ("measurement_type_code", m["code"]), ("value", f"{value:g}"),
        ("taken_at", at), ("stage_code", ""), ("method", method or ""), ("note", note or "")],
        done=f"{m['name']} {value:g} {m['unit']} recorded on lot {found['label']}")


class PoLine(Strict):
    item: str = Field(..., description="Item code or name as said, e.g. 'cans', 'CAN-16', 'Fermaid K'")
    qty: float = Field(..., gt=0, le=10000000, description="Quantity ordered, in `unit`")
    unit: str | None = Field(default=None, description="Purchase unit as said (ea, case, kg, lb, gal, L, bin, bushel...); omit for the supplier's usual unit or the item's base unit")
    unit_price_usd: float | None = Field(default=None, ge=0, le=1000000, description="Price per unit in US DOLLARS; omit if not said")


async def _line_unit(item: dict[str, Any], unit: str | None, supplier_id: int | None) -> tuple[str, float | None]:
    """(unit code to send, conversion note) — the PO/receipt forms accept same-dimension units and the item's own units."""
    own = await item_units(item["id"])
    if unit is None and supplier_id is not None:
        terms = await db.fetch_one("records", "SELECT purchase_unit_code FROM app.supplier_items WHERE supplier_id = %s AND item_id = %s AND active", (supplier_id, item["id"]))
        if terms:
            return terms["purchase_unit_code"], None
    code = units.normalize(unit) or item["base_unit_code"]
    table = await units.table()
    dimension = table.get(item["base_unit_code"], ("count", 1))[0]
    if code in own or (code in table and table[code][0] == dimension and code != "case"):
        return code, None
    raise Invalid(f"{item['name']} cannot be ordered in '{unit}'. Units: " + ", ".join(sorted(set([c for c, (d, _) in table.items() if d == dimension and c != "case"] + list(own)))), "unit")


@action(ActionMeta(
    name="po_create", title="Create draft purchase order", doc_actions=["po_create"],
    endpoint="POST /purchase-orders/save", role="receiving", confirm="never",
    undo_kind="cancel_draft", undo_how="POST /purchase-orders/{id}/cancel (no delete endpoint exists; a cancelled draft stays as history)",
    events=["po_created"], refresh=["purchaseOrdersChanged"]))
async def po_create(
    call: Call,
    supplier: Annotated[str, Field(description="Supplier name as said, e.g. 'Can Supply', 'Lallemand'")],
    lines: Annotated[list[PoLine], Field(min_length=1, max_length=30, description="What to order, one entry per item")],
    expected_on: Annotated[date | None, Field(default=None, description="Expected delivery date (YYYY-MM-DD); resolve 'next Friday' to a date")] = None,
    notes: Note = None,
) -> dict[str, Any]:
    """Create a DRAFT purchase order with its lines ("order 2000 cans from Can Supply for next Friday").

    Executes immediately as a draft (nothing is approved or sent) and can be undone. Approving it is
    a separate step (po_approve). <<not_for>>

    <<terminal>>"""
    sup = await resolve.resolve("supplier", supplier)
    premises = await default_premises()
    fields: list[tuple[str, Any]] = [("supplier_id", sup["id"]), ("premises_id", premises), ("ordered_on", date.today().isoformat()),
                                     ("expected_on", expected_on.isoformat() if expected_on else ""), ("notes", notes or "")]
    described = []
    for n, line in enumerate(lines, start=1):
        it = await resolve.resolve("item", line.item, where="item_class IN (SELECT code FROM app.item_classes WHERE purchasable AND active)", what="purchasable item")
        row = await item_row(it["id"])
        unit, _ = await _line_unit(row, line.unit, sup["id"])
        fields += [(f"lines[n{n}][item_id]", row["id"]), (f"lines[n{n}][qty_ordered]", f"{line.qty:g}"), (f"lines[n{n}][purchase_unit_code]", unit),
                   (f"lines[n{n}][unit_price]", "" if line.unit_price_usd is None else f"{line.unit_price_usd:g}"), (f"lines[n{n}][expected_on]", "")]
        described.append(f"{units.fmt(line.qty, unit, 3)} {row['name']}" + (f" at ${line.unit_price_usd:g}/{unit}" if line.unit_price_usd is not None else ""))
    result = await perform(call, ACTIONS_META["po_create"], "/purchase-orders/save", fields,
                           done=f"Draft purchase order for {sup['label']}: " + "; ".join(described) + (f", expected {expected_on.isoformat()}" if expected_on else ""))
    return result


class WeighTag(Strict):
    gross_lb: float = Field(..., gt=0, le=200000, description="Gross weight on the tag in POUNDS (convert kg × 2.2046)")
    tare_lb: float = Field(default=0, ge=0, le=200000, description="Tare in POUNDS; 0 if not said")
    bin_count: int | None = Field(default=None, ge=0, le=1000, description="Number of bins")
    variety: str | None = Field(default=None, max_length=80)
    orchard: str | None = Field(default=None, max_length=80)
    block: str | None = Field(default=None, max_length=80)
    brix: float | None = Field(default=None, ge=0, le=40, description="Brix at receipt, °Bx")
    tag_number: str | None = Field(default=None, max_length=40, description="The weigh tag's number")


class ReceiptLine(Strict):
    item: str = Field(..., description="Item code or name as said, e.g. 'McIntosh apples', 'APL-MAC'")
    qty: float | None = Field(default=None, gt=0, le=10000000, description="Count received in `unit`; optional for fruit received by weigh tag (net weight is used)")
    unit: str | None = Field(default=None, description="Unit as said (bin, bushel, lb, kg, ea, gal...); omit for the supplier's usual unit or base unit")
    unit_price_usd: float | None = Field(default=None, ge=0, le=1000000, description="Price per unit in US DOLLARS")
    supplier_lot: str | None = Field(default=None, max_length=80, description="The supplier's lot number")
    expires_on: date | None = Field(default=None, description="Expiry date")
    weigh_tag: WeighTag | None = Field(default=None, description="REQUIRED for fruit: the weigh tag figures")


@action(ActionMeta(
    name="receipt_create", title="Create draft receipt", doc_actions=["receipt_create", "weigh_tag_record"],
    endpoint="POST /receipts/save", role="receiving", confirm="always (no delete or cancel endpoint for draft receipts)",
    undo_kind="none", undo_how="The manifest's delete_row has no endpoint for receipts; edit the draft on its screen.",
    events=["receipt_created"], refresh=["receiptsChanged"]))
async def receipt_create(
    call: Call,
    supplier: Annotated[str, Field(description="Supplier or orchard as said, e.g. 'Hill Orchard'")],
    lines: Annotated[list[ReceiptLine], Field(min_length=1, max_length=30, description="One entry per item delivered (fruit lines carry a weigh_tag)")],
    po_number: Annotated[str | None, Field(default=None, description="Purchase order number it is received against, if said")] = None,
    received_at: When = None,
    delivery_note_ref: Annotated[str | None, Field(default=None, max_length=80, description="Delivery note or bill of lading number")] = None,
    receiving_location: Annotated[str | None, Field(default=None, description="Where it was received; omit for the receiving dock")] = None,
    notes: Note = None,
    confirmed: Confirmed = False,
) -> dict[str, Any]:
    """Record a delivery as a DRAFT receipt, including fruit by weigh tag ("Hill Orchard delivered 4 bins of
    McIntosh, gross 3,600 pounds, tare 400, brix 12").

    Posting (which creates the lots) is a separate step: receipt_post. Draft receipts cannot be deleted by
    voice, so it asks for confirmation first. <<confirm>> <<not_for>>

    <<terminal>>"""
    sup = await resolve.resolve("supplier", supplier)
    premises = await default_premises()
    order = None
    if po_number:
        order = await resolve.resolve("purchase_order", po_number, where="o.status IN ('open', 'partial')", what="open purchase order")
    locations = await db.fetch_all("records", "SELECT id, name, kind FROM app.locations WHERE active AND premises_id = %s ORDER BY name", (premises,))
    if receiving_location:
        loc = await resolve.resolve("location", receiving_location, where="premises_id = %(p)s", params={"p": premises})
    else:
        dock = [l for l in locations if l["kind"] == "receiving"]
        if len(dock) != 1:
            raise Invalid("Which location was it received at? " + ", ".join(l["name"] for l in locations), "receiving_location")
        loc = {"id": dock[0]["id"], "label": dock[0]["name"]}
    po_lines = []
    if order:
        po_lines = await db.fetch_all("records", "SELECT id, item_id FROM app.purchase_order_lines WHERE purchase_order_id = %s", (order["id"],))
    fruit_unit = await units.display_unit("kg", "fruit")
    lb_factor = (await units.factor("lb") or 0.45359237) / (await units.factor(fruit_unit) or 1)
    fields: list[tuple[str, Any]] = [("purchase_order_id", order["id"] if order else ""), ("supplier_id", sup["id"]), ("premises_id", premises),
                                     ("receiving_location_id", loc["id"]), ("received_at", await units.local_datetime(received_at)),
                                     ("delivery_note_ref", delivery_note_ref or ""), ("notes", notes or "")]
    described = []
    for n, line in enumerate(lines, start=1):
        it = await resolve.resolve("item", line.item, where="item_class IN (SELECT code FROM app.item_classes WHERE purchasable AND active)", what="purchasable item")
        row = await item_row(it["id"])
        unit, _ = await _line_unit(row, line.unit, sup["id"])
        p = f"lines[n{n}]"
        matching = [pl["id"] for pl in po_lines if pl["item_id"] == row["id"]]
        fields += [(f"{p}[purchase_order_line_id]", matching[0] if len(matching) == 1 else ""), (f"{p}[item_id]", row["id"]),
                   (f"{p}[purchase_unit_code]", unit), (f"{p}[qty_received]", "" if line.qty is None else f"{line.qty:g}"),
                   (f"{p}[unit_price]", "" if line.unit_price_usd is None else f"{line.unit_price_usd:g}"),
                   (f"{p}[supplier_lot_number]", line.supplier_lot or ""), (f"{p}[expires_on]", line.expires_on.isoformat() if line.expires_on else ""),
                   (f"{p}[discrepancy_kind]", "none")]
        needs_tag = row["item_class"] == "fruit" or row["catch_weight"]
        if needs_tag and line.weigh_tag is None:
            raise Invalid(f"{row['name']} is received by weigh tag: give the gross (and tare) weight in pounds.", "weigh_tag")
        if needs_tag:
            t = line.weigh_tag
            if t.tare_lb >= t.gross_lb:
                raise Invalid("Tare must be below the gross weight.", "weigh_tag.tare_lb")
            fields += [(f"{p}[weigh_tag][gross]", f"{t.gross_lb * lb_factor:.3f}"), (f"{p}[weigh_tag][tare]", f"{t.tare_lb * lb_factor:.3f}"),
                       (f"{p}[weigh_tag][bin_count]", "" if t.bin_count is None else t.bin_count), (f"{p}[weigh_tag][variety]", t.variety or ""),
                       (f"{p}[weigh_tag][orchard]", t.orchard or ""), (f"{p}[weigh_tag][block]", t.block or ""),
                       (f"{p}[weigh_tag][brix]", "" if t.brix is None else f"{t.brix:g}"), (f"{p}[weigh_tag][tag_number]", t.tag_number or "")]
            described.append(f"{row['name']} {t.gross_lb - t.tare_lb:,.0f} lb net" + (f" ({t.bin_count} bins)" if t.bin_count else "") + (f", {t.brix:g} °Bx" if t.brix is not None else ""))
        else:
            if line.qty is None:
                raise Invalid(f"How many {unit} of {row['name']} arrived?", "qty")
            described.append(f"{units.fmt(line.qty, unit, 3)} {row['name']}")
    summary = f"Draft receipt from {sup['label']}" + (f" against {order['label']}" if order else "") + ": " + "; ".join(described)
    if not confirmed:
        return needs_confirmation(summary + "? Draft receipts cannot be deleted by voice.")
    return await perform(call, ACTIONS_META["receipt_create"], "/receipts/save", fields, done=summary, undo_available=False,
                         undo_note="Draft receipts have no delete endpoint; edit the draft on its screen.")


# Inventory --------------------------------------------------------------------------------

class TransferLine(Strict):
    lot: str = Field(..., description="Lot number to move, e.g. 'L-261001-004'")
    qty: float = Field(..., gt=0, le=10000000, description="Quantity to move, in `unit`")
    unit: str | None = Field(default=None, description="Unit as said (gal, L, lb, kg, ea...); omit for the display unit (gallons for liquids, pounds for weights, each for counted goods)")


@action(ActionMeta(
    name="transfer_create", title="Create draft transfer", doc_actions=["transfer_create"],
    endpoint="POST /transfers/save", role="receiving", confirm="never",
    undo_kind="cancel_draft", undo_how="POST /transfers/{id}/cancel (drafts have no delete endpoint)",
    events=["transfer_created"], refresh=["transfersChanged"]))
async def transfer_create(
    call: Call,
    from_location: Annotated[str, Field(description="Location the stock leaves, e.g. 'Receiving dock'")],
    to_location: Annotated[str, Field(description="Destination location (same tax state), e.g. 'Cold room'")],
    lines: Annotated[list[TransferLine], Field(min_length=1, max_length=30, description="One entry per lot moved")],
    transferred_at: When = None,
    notes: Note = None,
) -> dict[str, Any]:
    """Create a DRAFT stock transfer between locations ("move 50 pounds of lot L-261001-004 from the dock to the cold room").

    Executes immediately as a draft and can be undone; posting it moves the stock (transfer_post). <<not_for>>

    <<terminal>>"""
    src = await resolve.resolve("location", from_location)
    dst = await resolve.resolve("location", to_location)
    fields: list[tuple[str, Any]] = [("from_location_id", src["id"]), ("to_location_id", dst["id"]),
                                     ("transferred_at", await units.local_datetime(transferred_at)), ("notes", notes or "")]
    described = []
    for n, line in enumerate(lines, start=1):
        lt = await resolve.resolve("lot", line.lot)
        bal = await db.fetch_one("records", """SELECT item_id, item_name, item_class, base_unit_code, qty_available::float AS available
                                               FROM app.v_lot_balances WHERE lot_id = %s AND location_id = %s""", (lt["id"], src["id"]))
        if not bal:
            where = await db.fetch_all("records", "SELECT location_name FROM app.v_lot_balances WHERE lot_id = %s AND qty_on_hand > 0", (lt["id"],))
            raise Invalid(f"Lot {lt['label']} has no stock at {src['label']}" + (f"; it is at {', '.join(w['location_name'] for w in where)}." if where else "."), "lines.lot")
        kind = unit_kind(bal["item_class"])
        qty_display = await units.to_display(line.qty, line.unit, bal["base_unit_code"], kind, await item_units(bal["item_id"]))
        if qty_display is None:
            raise Invalid(f"{bal['item_name']} is stocked in {bal['base_unit_code']}; '{line.unit}' does not convert.", "lines.unit")
        disp_unit = await units.display_unit(bal["base_unit_code"], kind)
        fields += [(f"lines[n{n}][item_id]", bal["item_id"]), (f"lines[n{n}][lot_id]", lt["id"]), (f"lines[n{n}][qty]", f"{qty_display:.6f}")]
        described.append(f"{units.fmt(qty_display, disp_unit, 2)} of {lt['label']} ({bal['item_name']})")
    return await perform(call, ACTIONS_META["transfer_create"], "/transfers/save", fields,
                         done=f"Draft transfer {src['label']} → {dst['label']}: " + "; ".join(described))


@action(ActionMeta(
    name="count_start", title="Start a count", doc_actions=["count_start"],
    endpoint="POST /counts/save", role="receiving", confirm="never",
    undo_kind="cancel_draft", undo_how="POST /counts/{id}/cancel (no delete endpoint)",
    events=["count_started"], refresh=["countsChanged"]))
async def count_start(
    call: Call,
    location: Annotated[str, Field(description="Location to count, e.g. 'Cold room'")],
    kind: Annotated[Literal["cycle", "physical"], Field(description="cycle (a routine partial count) or physical (the full stocktake)")] = "cycle",
    notes: Note = None,
) -> dict[str, Any]:
    """Start a cycle or physical count of a location ("start a cycle count of the cold room").

    Executes immediately and can be undone (cancels the count). Record counted quantities with count_line_record.
    <<not_for>>

    <<terminal>>"""
    loc = await resolve.resolve("location", location)
    return await perform(call, ACTIONS_META["count_start"], "/counts/save", [("location_id", loc["id"]), ("kind", kind), ("notes", notes or "")],
                         done=f"Started a {kind} count of {loc['label']}")


@action(ActionMeta(
    name="count_line_record", title="Record a counted quantity", doc_actions=["count_line_record"],
    endpoint="POST /counts/{id}/lines/save (line_id)", role="receiving", confirm="never",
    undo_kind="restore_prior", undo_how="Re-POST the before-image counted quantity; a first count of a line (no prior quantity) cannot be cleared (no endpoint)",
    events=["count_line_recorded"], refresh=[]))
async def count_line_record(
    call: Call,
    qty_counted: Annotated[float, Field(ge=0, le=10000000, description="Quantity counted, in `unit`")],
    count: Annotated[str | None, Field(default=None, description="Count number; omit to use the only open count (or the one on screen)")] = None,
    lot: Annotated[str | None, Field(default=None, description="Lot number on the count sheet")] = None,
    item: Annotated[str | None, Field(default=None, description="Item code or name, for lines without a lot or to narrow")] = None,
    unit: Annotated[str | None, Field(default=None, description="Unit as said; omit for the display unit (gal, lb, ea)")] = None,
    note: Annotated[str | None, Field(default=None, max_length=200)] = None,
) -> dict[str, Any]:
    """Record a counted quantity on an open count ("counted 48 cases of L-261001-019", "12 kilos of Fermaid on the cold room count").

    Executes immediately; re-counting a line can be undone. <<not_for>>

    <<terminal>>"""
    if count:
        c = await resolve.resolve("count", count, where="c.status IN ('open', 'counting')", what="open count")
    else:
        open_counts = await db.fetch_all("records", "SELECT id, number FROM app.inventory_counts WHERE status IN ('open', 'counting') ORDER BY id")
        if len(open_counts) != 1:
            raise Invalid("Which count? Open counts: " + (", ".join(o["number"] for o in open_counts) or "none"), "count")
        c = {"id": open_counts[0]["id"], "label": open_counts[0]["number"]}
    if not lot and not item:
        raise Invalid("Say which lot (or item) was counted.", "lot")
    lines = await db.fetch_all("records", """SELECT cl.id, cl.item_id, i.code, i.name, i.item_class, i.base_unit_code, l.lot_number, cl.qty_counted_base::float AS counted
                                             FROM app.inventory_count_lines cl JOIN app.items i ON i.id = cl.item_id LEFT JOIN app.lots l ON l.id = cl.lot_id
                                             WHERE cl.count_id = %s ORDER BY cl.id""", (c["id"],))
    def norm(s: str | None) -> str:
        return "".join(ch for ch in (s or "").lower() if ch.isalnum())
    picked = lines
    if lot:
        picked = [l for l in picked if norm(l["lot_number"]) == norm(lot)]
    if item:
        picked = [l for l in picked if norm(item) in (norm(l["code"]), norm(l["name"])) or norm(item) in norm(l["name"])]
    if len(picked) != 1:
        options = [f"{l['lot_number'] or '(no lot)'} {l['code']}" for l in (picked or lines)][:12]
        raise Invalid(("Several lines match: " if picked else "No line on the count matches. Lines: ") + ", ".join(options), "lot", options)
    line = picked[0]
    kind = unit_kind(line["item_class"])
    qty_display = await units.to_display(qty_counted, unit, line["base_unit_code"], kind, await item_units(line["item_id"]))
    if qty_display is None:
        raise Invalid(f"{line['name']} is counted in {line['base_unit_code']}; '{unit}' does not convert.", "unit")
    disp_unit = await units.display_unit(line["base_unit_code"], kind)
    return await perform(call, ACTIONS_META["count_line_record"], f"/counts/{c['id']}/lines/save",
                         [("line_id", line["id"]), ("qty_counted", f"{qty_display:.6f}"), ("note", note or "")],
                         done=f"Counted {units.fmt(qty_counted, units.normalize(unit), 3) if unit else units.fmt(qty_display, disp_unit, 3)} of {line['lot_number'] or line['code']} on {c['label']}",
                         undo_available=line["counted"] is not None,
                         undo_note="This was the line's first count; there is no endpoint to clear it, so record the right quantity instead.",
                         field_map={"qty_counted": "qty_counted"})


# Packaging and kegs -----------------------------------------------------------------------

@action(ActionMeta(
    name="keg_event", title="Keg state event", doc_actions=["keg_fill", "keg_clean", "keg_mark_lost", "keg_found", "keg_retire"],
    endpoint="POST /kegs/{id}/state (event=fill|clean|mark_lost|found|retire)", role="production",
    confirm="mark_lost and retire (manifest); fill and clean too (no undo endpoint)",
    undo_kind="reverse", undo_how="mark_lost ↔ found through the same endpoint (found returns the keg as returned_dirty); fill, clean and retire have no inverse endpoint",
    events=["keg_filled", "keg_cleaned", "keg_marked_lost", "keg_found", "keg_retired"], refresh=["kegsChanged"]))
async def keg_event(
    call: Call,
    keg: Annotated[str, Field(description="Keg serial as said, e.g. 'KEG-0003' or '3'... pass what the user said")],
    event: Annotated[Literal["fill", "clean", "mark_lost", "found", "retire"], Field(description="fill (from a finished keg lot), clean (a returned dirty keg), mark_lost, found (a lost keg turned up), retire")],
    finished_lot: Annotated[str | None, Field(default=None, description="For fill: the finished keg lot number it is filled from")] = None,
    confirmed: Confirmed = False,
) -> dict[str, Any]:
    """Change a keg's state: fill it from a finished lot, clean it, mark it lost, mark it found, or retire it.

    Call for "KEG-0001 is clean", "fill keg 2 from L-261001-020", "keg 3 is lost". Marking lost or found can be
    undone; fill, clean and retire cannot, so those (and mark_lost) ask for confirmation first. Returns from
    customers use keg_return. <<confirm>> <<not_for>>

    <<terminal>>"""
    text = keg.strip()
    if text.isdigit():
        text = f"KEG-{int(text):04d}"
    k = await resolve.resolve("keg", text)
    fields = [("event", event)]
    lot_label = None
    if event == "fill":
        if not finished_lot:
            raise Invalid("Which finished keg lot is it filled from?", "finished_lot")
        fl = await resolve.resolve("finished_lot", finished_lot)
        fields.append(("finished_lot", fl["id"]))
        lot_label = fl["label"]
    verb = {"fill": f"filled from {lot_label}", "clean": "cleaned", "mark_lost": "marked lost", "found": "marked found", "retire": "retired"}[event]
    if event != "found" and not confirmed:
        tail = " This can be undone (marked found)." if event == "mark_lost" else " This cannot be undone by voice."
        return needs_confirmation(f"Keg {k['label']} ({k['detail']}): {verb}?{tail}")
    events = {"fill": "keg_filled", "clean": "keg_cleaned", "mark_lost": "keg_marked_lost", "found": "keg_found", "retire": "keg_retired"}
    return await perform(call, ACTIONS_META["keg_event"], f"/kegs/{k['id']}/state", fields, events=[events[event]],
                         done=f"Keg {k['label']} {verb}", undo_available=event in ("mark_lost", "found"),
                         undo_note="Only marking lost or found has an inverse; fix other keg states on the keg screen.",
                         field_map={"finished_lot": "finished_lot"})


@action(ActionMeta(
    name="keg_register", title="Register a keg", doc_actions=["keg_register"],
    endpoint="POST /kegs/save", role="production", confirm="never",
    undo_kind="delete_row", undo_how="POST /kegs/{id}/delete (only while never filled and without movements)",
    events=["keg_registered"], refresh=["kegsChanged"]))
async def keg_register(
    call: Call,
    serial: Annotated[str, Field(min_length=1, max_length=60, description="The keg's serial, e.g. 'KEG-0004'")],
    size_gal: Annotated[float, Field(gt=0, le=60, description="Keg size in US GALLONS: half barrel = 15.5, quarter = 7.75, sixtel = 5.16; 50 L = 13.2")],
    ownership: Annotated[Literal["owned", "rented", "customer_owned"], Field(description="Who owns the keg")] = "owned",
    deposit_usd: Annotated[float, Field(ge=0, le=1000, description="Deposit in US DOLLARS; 0 if none")] = 0,
    notes: Note = None,
) -> dict[str, Any]:
    """Register a new keg in the fleet ("add keg KEG-0004, a half barrel, $30 deposit").

    Executes immediately and can be undone while the keg is unused. <<not_for>>

    <<terminal>>"""
    size_display = await units.to_display(size_gal, "gal", "L")
    return await perform(call, ACTIONS_META["keg_register"], "/kegs/save", [
        ("serial", serial.strip()), ("size_gal", f"{size_display:.3f}"), ("ownership", ownership), ("deposit_amount", f"{deposit_usd:.2f}"), ("notes", notes or "")],
        done=f"Keg {serial.strip()} registered ({size_gal:g} gal, {ownership}, ${deposit_usd:.2f} deposit)")


@action(ActionMeta(
    name="keg_return", title="Return kegs", doc_actions=["keg_return"],
    endpoint="POST /kegs/return", role="production", confirm="always (no inverse endpoint)",
    undo_kind="none", undo_how="The manifest's 'reverse' has no endpoint.", events=["keg_returned"], refresh=["kegsChanged"]))
async def keg_return(
    call: Call,
    serials: Annotated[list[str], Field(min_length=1, max_length=200, description="Returned keg serials as said")],
    customer: Annotated[str | None, Field(default=None, description="Customer returning them, if said")] = None,
    confirmed: Confirmed = False,
) -> dict[str, Any]:
    """Record kegs coming back from customers ("kegs 3 and 7 came back from the Taproom Bar").

    Asks for confirmation first (no undo). <<confirm>> <<not_for>>

    <<terminal>>"""
    fixed = [f"KEG-{int(s):04d}" if s.strip().isdigit() else s.strip() for s in serials]
    fields = [("serials", "\n".join(fixed))]
    cust = None
    if customer:
        cust = await resolve.resolve("customer", customer)
        fields.append(("customer_id", cust["id"]))
    if not confirmed:
        return needs_confirmation(f"Return {', '.join(fixed)}" + (f" from {cust['label']}" if cust else "") + "? This cannot be undone by voice.")
    return await perform(call, ACTIONS_META["keg_return"], "/kegs/return", fields, done=f"Returned {', '.join(fixed)}",
                         undo_available=False, undo_note="Keg returns have no inverse endpoint.")


# Removals and customers -------------------------------------------------------------------

class RemovalLine(Strict):
    finished_lot: str = Field(..., description="Finished lot number, e.g. 'L-261001-019'")
    units: int = Field(..., ge=1, le=100000, description="Whole units (cases, kegs) of that lot")
    kegs: list[str] | None = Field(default=None, description="For keg lots: the keg serials shipped (one per unit); omit to take the lot's filled kegs in serial order")


@action(ActionMeta(
    name="removal_create", title="Create draft removal", doc_actions=["removal_create", "return_create"],
    endpoint="POST /removals/save", role="compliance", confirm="never",
    undo_kind="delete_row", undo_how="POST /removals/{id}/delete (drafts only)", events=["removal_created"], refresh=["removalsChanged"]))
async def removal_create(
    call: Call,
    destination_kind: Annotated[Literal["tax_paid_sale", "taproom_transfer", "in_bond_transfer", "export", "sample_testing", "destroyed", "breakage", "family_use", "return_from_customer"],
                                Field(description="Where the goods go: tax_paid_sale, taproom_transfer, in_bond_transfer, export, sample_testing, destroyed, breakage, family_use; return_from_customer for goods coming back")],
    lines: Annotated[list[RemovalLine], Field(min_length=1, max_length=30)],
    customer: Annotated[str | None, Field(default=None, description="Customer name; required for sales, in-bond, export and returns")] = None,
    from_location: Annotated[str | None, Field(default=None, description="Bonded location the goods leave; omit when the lots' stock is in one place")] = None,
    to_location: Annotated[str | None, Field(default=None, description="Taproom (taproom_transfer) or packaged-goods location (returns); omit when there is only one")] = None,
    removed_at: When = None,
    reference: Annotated[str | None, Field(default=None, max_length=80, description="Invoice or order reference")] = None,
    notes: Note = None,
) -> dict[str, Any]:
    """Create a DRAFT removal of finished goods (sale, taproom transfer, in-bond, export, samples, destroyed...) or a
    draft return from a customer ("10 cases of L-261001-019 to Main Street Market, tax paid").

    Executes immediately as a draft and can be undone (deleted). Posting it (removal_post) moves stock and
    determines tax. <<not_for>>

    <<terminal>>"""
    direction = "in" if destination_kind == "return_from_customer" else "out"
    cust = None
    if destination_kind in catalog.REMOVAL_CUSTOMER_REQUIRED:
        if not customer:
            raise Invalid(f"Which customer? A {destination_kind.replace('_', ' ')} needs one.", "customer")
        cust = await resolve.resolve("customer", customer)
    lots = []
    for line in lines:
        lt = await resolve.resolve("finished_lot", line.finished_lot)
        lots.append((line, lt))
    fields: list[tuple[str, Any]] = [("direction", direction), ("destination_kind", destination_kind), ("customer_id", cust["id"] if cust else "")]
    src_id = dst_id = None
    if direction == "out":
        if from_location:
            src_id = (await resolve.resolve("location", from_location, where="kind IN ('packaged_goods', 'taproom') AND tax_state = 'bonded'", what="bonded packaged-goods location"))["id"]
        else:
            places = await db.fetch_all("records", "SELECT DISTINCT location_id, location_name FROM app.v_finished_stock WHERE lot_id = ANY(%s) AND units_available > 0 AND tax_state = 'bonded'",
                                        ([lt["id"] for _, lt in lots],))
            if len(places) != 1:
                raise Invalid("Which location do they leave from? " + (", ".join(p["location_name"] for p in places) or "no bonded stock of those lots"), "from_location")
            src_id = places[0]["location_id"]
    if direction == "in" or destination_kind == "taproom_transfer":
        where = "kind = 'taproom' AND tax_state = 'tax_paid'" if direction == "out" else "kind = 'packaged_goods' AND tax_state = 'bonded'"
        if to_location:
            dst_id = (await resolve.resolve("location", to_location, where=where))["id"]
        else:
            options = await db.fetch_all("records", f"SELECT id, name FROM app.locations WHERE active AND {where}")
            if len(options) != 1:
                raise Invalid("Which location do they go to? " + ", ".join(o["name"] for o in options), "to_location")
            dst_id = options[0]["id"]
    fields += [("from_location_id", src_id or ""), ("to_location_id", dst_id or ""), ("removed_at", await units.local_datetime(removed_at)),
               ("reference", reference or ""), ("notes", notes or "")]
    described = []
    for n, (line, lt) in enumerate(lots, start=1):
        fields += [(f"lines[n{n}][lot_id]", lt["id"]), (f"lines[n{n}][units]", line.units)]
        kind = await db.fetch_one("records", "SELECT pc.package_kind FROM app.finished_lots f JOIN app.packaging_configurations pc ON pc.id = f.packaging_configuration_id WHERE f.lot_id = %s", (lt["id"],))
        if kind and kind["package_kind"] == "keg":
            if direction == "in":
                pool = await db.fetch_all("records", "SELECT id, serial FROM app.kegs WHERE state = 'at_customer' AND current_holder_kind = 'customer' AND current_holder_id = %s AND current_lot_id = %s ORDER BY serial", (cust["id"], lt["id"]))
            else:
                pool = await db.fetch_all("records", "SELECT id, serial FROM app.kegs WHERE state = 'filled' AND current_lot_id = %s ORDER BY serial", (lt["id"],))
            by_serial = {p["serial"].lower(): p["id"] for p in pool}
            if line.kegs:
                wanted = [f"KEG-{int(s):04d}" if s.strip().isdigit() else s.strip() for s in line.kegs]
                missing = [w for w in wanted if w.lower() not in by_serial]
                if missing:
                    raise Invalid(f"Kegs {', '.join(missing)} are not available for {lt['label']}. Available: " + (", ".join(p["serial"] for p in pool) or "none"), "lines.kegs")
                ids = [by_serial[w.lower()] for w in wanted]
            else:
                if len(pool) < line.units:
                    raise Invalid(f"Only {len(pool)} keg(s) of {lt['label']} are available: " + (", ".join(p["serial"] for p in pool) or "none"), "lines.units")
                ids = [p["id"] for p in pool[: line.units]]
            fields += [(f"lines[n{n}][keg_ids][]", i) for i in ids]
        described.append(f"{line.units} × {lt['label']}")
    label = "return" if direction == "in" else "removal"
    return await perform(call, ACTIONS_META["removal_create"], "/removals/save", fields,
                         done=f"Draft {label} ({destination_kind.replace('_', ' ')}{', ' + cust['label'] if cust else ''}): " + ", ".join(described))


@action(ActionMeta(
    name="customer_create", title="Add a customer", doc_actions=["customer_create"],
    endpoint="POST /customers/save", role="compliance", confirm="never",
    undo_kind="delete_row", undo_how="POST /customers/{id}/delete (deletes, or deactivates once used)",
    events=["customer_created"], refresh=["customersChanged"]))
async def customer_create(
    call: Call,
    name: Annotated[str, Field(min_length=1, max_length=120)],
    kind: Annotated[Literal["distributor", "retailer", "taproom", "consumer", "bonded_premises", "other"], Field(description="What kind of customer")],
    default_destination: Annotated[Literal["tax_paid_sale", "taproom_transfer", "in_bond_transfer", "export"], Field(description="Usual removal type")] = "tax_paid_sale",
    permit_number: Annotated[str | None, Field(default=None, max_length=40, description="TTB permit number (required for in-bond consignees)")] = None,
    contact_name: Annotated[str | None, Field(default=None, max_length=120)] = None,
    email: Annotated[str | None, Field(default=None, max_length=200)] = None,
    phone: Annotated[str | None, Field(default=None, max_length=40)] = None,
) -> dict[str, Any]:
    """Add a customer (removal destination): "add Main Street Market as a retailer".

    Executes immediately and can be undone. <<not_for>>

    <<terminal>>"""
    return await perform(call, ACTIONS_META["customer_create"], "/customers/save", [
        ("name", name), ("kind", kind), ("default_destination", default_destination), ("permit_number", permit_number or ""),
        ("contact_name", contact_name or ""), ("email", email or ""), ("phone", phone or ""), ("address", ""), ("notes", ""), ("active", "1")],
        done=f"Customer {name} added ({kind})")


# Foundation -------------------------------------------------------------------------------

@action(ActionMeta(
    name="vessel_set_status", title="Set vessel status", doc_actions=["vessel_set_status"],
    endpoint="POST /vessels/{id}/status", role="production", confirm="never",
    undo_kind="restore_prior", undo_how="Re-POST the before-image status (when it was empty, cleaning or out of service)",
    events=["vessel_status_set"], refresh=["vesselChanged"]))
async def vessel_set_status(
    call: Call,
    vessel: Annotated[str, Field(description="Vessel name as said, e.g. 'FV-2', 'tank 3'")],
    status: Annotated[Literal["empty", "cleaning", "out_of_service"], Field(description="empty (clean and ready), cleaning, out_of_service")],
) -> dict[str, Any]:
    """Set an idle vessel's status ("FV-2 is being cleaned", "tank 3 is out of service", "FV-1 is ready").

    Executes immediately and can be undone. A vessel in use cannot be changed. <<not_for>>

    <<terminal>>"""
    v = await resolve.resolve("vessel", vessel)
    return await perform(call, ACTIONS_META["vessel_set_status"], f"/vessels/{v['id']}/status", [("status", status)],
                         done=f"Vessel {v['label']} is now {status.replace('_', ' ')}")


# Document steps (one id, no fields) ---------------------------------------------------------

DOCUMENT_STEPS = [
    # name, kind, verb path, event, doc action, role, confirm rule, undo kind/how, refresh, title, trigger phrase
    ("receipt_post", "receipt", "post", "receipt_posted", "receiving", "always", "none", "Posting creates lots and ledger receipts; receipts have no reversal endpoint.", ["receiptsChanged", "lotsChanged", "inventoryChanged"], "Post a receipt", "post receipt R-…, the delivery is checked"),
    ("transfer_post", "transfer", "post", "transfer_posted", "receiving", "never", "reverse", "POST /transfers/{id}/reverse", ["transfersChanged", "inventoryChanged"], "Post a transfer", "post the transfer, move the stock now"),
    ("adjustment_post", "adjustment", "post", "adjustment_posted", "receiving", "when any line is a write-down", "reverse", "POST /adjustments/{id}/reverse", ["adjustmentsChanged", "inventoryChanged"], "Post an adjustment", "post adjustment A-…"),
    ("count_submit", "count", "submit", "count_submitted", "receiving", "always", "none", "The manifest's restore_prior has no endpoint.", ["countsChanged", "inventoryChanged"], "Submit a count for review", "the count is done, submit it"),
    ("po_approve", "purchase_order", "approve", "po_approved", "owner", "always", "none", "The manifest's restore_prior (back to draft) has no endpoint.", ["purchaseOrdersChanged"], "Approve a purchase order", "approve PO-…"),
    ("removal_post", "removal", "post", "removal_posted", "compliance", "always", "reverse", "POST /removals/{id}/reverse with a reason (posts a reversing removal)", ["removalsChanged", "inventoryChanged", "kegsChanged"], "Post a removal", "post the removal, ship it"),
    ("packaging_run_post", "packaging_run", "post", "packaging_run_posted", "production", "never", "reverse", "POST /packaging-runs/{id}/reverse (while every unit is on hand)", ["packagingRunsChanged", "finishedLotsChanged", "lotsChanged", "inventoryChanged", "batchesChanged"], "Post a packaging run", "post packaging run P-…"),
    ("press_run_post", "press_run", "post", "press_run_posted", "production", "always", "none", "The manifest's reverse has no endpoint for press runs.", ["pressRunsChanged", "lotsChanged", "inventoryChanged", "batchesChanged"], "Post a press run", "post the press run"),
    ("po_cancel", "purchase_order", "cancel", "po_cancelled", "receiving", "always", "none", "The manifest's restore_prior has no endpoint.", ["purchaseOrdersChanged"], "Cancel a purchase order", "cancel PO-…"),
    ("count_cancel", "count", "cancel", "count_cancelled", "receiving", "always", "none", "The manifest's restore_prior has no endpoint.", ["countsChanged"], "Cancel a count", "cancel the count"),
]

STATUS_RULES = {
    "receipt_post": ("goods_receipts", "draft"), "transfer_post": ("inventory_transfers", "draft"), "adjustment_post": ("inventory_adjustments", "draft"),
    "count_submit": ("inventory_counts", None), "po_approve": ("purchase_orders", "draft"), "removal_post": ("removals", "draft"),
    "packaging_run_post": ("packaging_runs", "draft"), "press_run_post": ("press_runs", "draft"), "po_cancel": ("purchase_orders", None),
    "count_cancel": ("inventory_counts", None),
}


def _make_step(spec: tuple) -> None:
    name, kind, verb, event, role, confirm, undo_kind, undo_how, refresh, title, phrase = spec
    noun = resolve.KINDS[kind].noun
    url = {"receipt": "receipts", "transfer": "transfers", "adjustment": "adjustments", "count": "counts", "purchase_order": "purchase-orders",
           "removal": "removals", "packaging_run": "packaging-runs", "press_run": "press-runs"}[kind]
    meta = ActionMeta(name=name, title=title, doc_actions=[name], endpoint=f"POST /{url}/{{id}}/{verb}", role=role,
                      confirm=confirm, undo_kind=undo_kind, undo_how=undo_how, events=[event], refresh=refresh,
                      destructive=verb == "cancel")

    async def step(
        call: Call,
        record: Annotated[str, Field(description=f"The {noun} number as said; on its screen use the screen's record")],
        confirmed: Confirmed = False,
    ) -> dict[str, Any]:
        found = await resolve.resolve(kind, record)
        table, wanted = STATUS_RULES[name]
        row = await db.fetch_one("records", f"SELECT status FROM app.{table} WHERE id = %s", (found["id"],))
        if wanted and row and row["status"] != wanted:
            raise Invalid(f"{found['label']} is {row['status']}; only a {wanted} {noun} can be {verb}ed.", "record")
        must_confirm = confirm == "always"
        if name == "adjustment_post":
            neg = await db.fetch_one("records", "SELECT count(*) AS n FROM app.inventory_adjustment_lines WHERE adjustment_id = %s AND qty_delta_base < 0", (found["id"],))
            must_confirm = bool(neg and neg["n"])
        past = {"post": "posted", "submit": "submitted", "approve": "approved", "cancel": "cancelled"}[verb]
        if must_confirm and not confirmed:
            tail = "" if undo_kind != "none" else " This cannot be undone by voice."
            return needs_confirmation(f"{verb.capitalize()} {noun} {found['label']} ({found['detail']})?{tail}")
        return await perform(call, meta, f"/{url}/{found['id']}/{verb}", [], done=f"{noun.capitalize()} {found['label']} {past}",
                             undo_available=undo_kind != "none", undo_note=undo_how)

    step.__doc__ = (f"{title} ({phrase}). " + ("Asks for confirmation first. <<confirm>> " if confirm != "never" else "Executes immediately and can be undone. ")
                    + "<<not_for>>\n\n    <<terminal>>")
    step.__name__ = name
    action(meta)(step)


for _spec in DOCUMENT_STEPS:
    _make_step(_spec)


# Equipment scheduling (db/023, docs/16) ------------------------------------------------------------------------

@action(ActionMeta(
    name="equipment_create", title="Add equipment", doc_actions=["equipment_create"],
    endpoint="POST /equipment/save", role="production", confirm="never",
    undo_kind="restore_prior", undo_how="Not undone by voice: deactivate it on its page (equipment is never deleted)",
    events=["equipment_created"], refresh=["equipmentChanged"]))
async def equipment_create(
    call: Call,
    name: Annotated[str, Field(min_length=1, max_length=120, description="The equipment's name as said, e.g. 'canning line', 'pump 2'")],
    kind: Annotated[Literal["mill", "pump", "filter", "chiller", "carbonator", "canning_line", "bottling_line", "keg_line", "keg_washer", "labeler", "other"], Field(description="What it is")],
    rating: Annotated[str | None, Field(default=None, max_length=120, description="In words: '120 cans/min', '4,000 L/h'")] = None,
    location: Annotated[str | None, Field(default=None, max_length=120, description="The area it stands in, when known")] = None,
    premises: Annotated[str | None, Field(default=None, max_length=120, description="Premises name; omit when there is one")] = None,
    note: Note = None,
) -> dict[str, Any]:
    """Add a piece of equipment that holds no liquid — a mill, pump, filter, chiller, carbonator, canning or bottling line,
    keg line, keg washer or labeler ("add the new canning line, 120 cans a minute"). A tank or a press is a VESSEL: use
    the Vessels screen (vessel_create). Executes immediately; equipment is deactivated, never deleted. <<not_for>>

    <<terminal>>"""
    prem_id = (await resolve.resolve("premises", premises))["id"] if premises else await default_premises()
    loc = await resolve.resolve("location", location) if location else None
    return await perform(call, ACTIONS_META["equipment_create"], "/equipment/save", [
        ("premises_id", prem_id), ("location_id", loc["id"] if loc else ""), ("name", name), ("kind", kind), ("status", "available"),
        ("rating", rating or ""), ("notes", note or "")], done=f"Equipment {name} added ({kind.replace('_', ' ')})", undo_available=False)


@action(ActionMeta(
    name="equipment_set_status", title="Set equipment status", doc_actions=["equipment_set_status"],
    endpoint="POST /equipment/{id}/status", role="production", confirm="never",
    undo_kind="restore_prior", undo_how="Re-POST the before-image status",
    events=["equipment_status_set"], refresh=["equipmentChanged"]))
async def equipment_set_status(
    call: Call,
    equipment: Annotated[str, Field(description="Equipment name as said, e.g. 'canning line', 'pump 1'")],
    status: Annotated[Literal["available", "cleaning", "out_of_service"], Field(description="available, cleaning, out_of_service")],
) -> dict[str, Any]:
    """Set a piece of equipment's status ("the canning line is out of service", "the filter is clean"). Bookings ahead stay
    and are shaded on the schedule. Executes immediately and can be undone. For a tank use vessel_set_status. <<not_for>>

    <<terminal>>"""
    e = await resolve.resolve("equipment", equipment)
    return await perform(call, ACTIONS_META["equipment_set_status"], f"/equipment/{e['id']}/status", [("status", status)],
                         done=f"Equipment {e['label']} is now {status.replace('_', ' ')}")


@action(ActionMeta(
    name="equipment_reserve", title="Reserve equipment", doc_actions=["equipment_reserve"],
    endpoint="POST /reservations/save", role="production", confirm="never",
    undo_kind="delete_row", undo_how="POST /reservations/{id}/cancel",
    events=["equipment_reserved"], refresh=["reservationsChanged"]))
async def equipment_reserve(
    call: Call,
    from_date: Annotated[date, Field(description="First day, ISO YYYY-MM-DD")],
    to_date: Annotated[date | None, Field(default=None, description="Last day, inclusive; omit for one day")] = None,
    vessel: Annotated[str | None, Field(default=None, description="The tank or press to book, e.g. 'FV-2' (one of vessel / equipment)")] = None,
    equipment: Annotated[str | None, Field(default=None, description="The equipment to book, e.g. 'canning line' (one of vessel / equipment)")] = None,
    order: Annotated[str | None, Field(default=None, description="The production order it is for (WO-00012) — one of order / batch / press_run / packaging_run, or a block")] = None,
    batch: Annotated[str | None, Field(default=None, description="The batch it is for (B-26-004)")] = None,
    press_run: Annotated[str | None, Field(default=None, description="The press run it is for (PR-00003)")] = None,
    packaging_run: Annotated[str | None, Field(default=None, description="The packaging run it is for (PK-00005)")] = None,
    block: Annotated[Literal["cleaning", "maintenance", "hold"] | None, Field(default=None, description="Block the resource instead of booking a run")] = None,
    role: Annotated[Literal["primary", "maturation", "brite", "blend", "press", "mill", "transfer", "filter", "carbonate", "package", "other"] | None,
                    Field(default=None, description="What the resource does in the run; default primary for a vessel, other for equipment")] = None,
    start_time: Annotated[str | None, Field(default=None, pattern=r"^([01]\d|2[0-3]):[0-5]\d$", description="HH:MM when it is not all day")] = None,
    end_time: Annotated[str | None, Field(default=None, pattern=r"^([01]\d|2[0-3]):[0-5]\d$", description="HH:MM when it is not all day")] = None,
    share: Annotated[bool, Field(description="Book over a clash as shared use — only when the organization allows double booking and the person said so")] = False,
    note: Note = None,
) -> dict[str, Any]:
    """Book a tank, press, line or piece of equipment for a run, or block it ("book FV-2 for WO-00012 from Nov 2 to Nov 16",
    "put the canning line on B-26-004 Nov 25 from 8 to 12", "block the filter for cleaning tomorrow"). Give the days (and
    times when two runs share a day); say which run it is for, or a block. A window already booked is refused with the
    clashes named; when the organization allows double booking and the person wants it anyway, call again with
    share=true. Executes immediately and can be undone (cancelled). <<not_for>>

    <<terminal>>"""
    if bool(vessel) == bool(equipment):
        raise Invalid("Name exactly one of vessel or equipment.", "vessel")
    runs = [k for k, v in (("order", order), ("batch", batch), ("press_run", press_run), ("packaging_run", packaging_run)) if v]
    if block is None and len(runs) != 1:
        raise Invalid("Say which run the booking is for (one of order, batch, press_run, packaging_run), or a block (cleaning, maintenance, hold).", "order")
    if block is not None and runs:
        raise Invalid("A block has no run; give a run or a block, not both.", "block")
    fields: list[tuple[str, Any]] = [("planned_from", from_date.isoformat()), ("planned_to", (to_date or from_date).isoformat()), ("notes", note or "")]
    if vessel:
        v = await resolve.resolve("vessel", vessel)
        fields.append(("resource", f"vessel:{v['id']}"))
        what = v["label"]
    else:
        e = await resolve.resolve("equipment", equipment)
        fields.append(("resource", f"equipment:{e['id']}"))
        what = e["label"]
    if block is not None:
        fields.append(("kind", block))
        for_text = block
    else:
        kind_map = {"order": ("production_order", "production_order"), "batch": ("batch", "batch"), "press_run": ("press_run", "press_run"), "packaging_run": ("packaging_run", "packaging_run")}
        resolve_kind, subject_kind = kind_map[runs[0]]
        found = await resolve.resolve(resolve_kind, {"order": order, "batch": batch, "press_run": press_run, "packaging_run": packaging_run}[runs[0]])
        fields += [("kind", "run"), ("subject_kind", subject_kind), ("subject_id", found["id"])]
        for_text = found["label"]
    fields.append(("role", role or ("primary" if vessel else "other")))
    if start_time or end_time:
        if not (start_time and end_time):
            raise Invalid("Give both a start and an end time, or neither.", "start_time")
        fields += [("all_day", "0"), ("start_time", start_time), ("end_time", end_time)]
    else:
        fields.append(("all_day", "1"))
    if share:
        fields.append(("share", "1"))
    when = from_date.isoformat() + ("" if not to_date or to_date == from_date else f" to {to_date.isoformat()}") + (f", {start_time}–{end_time}" if start_time else "")
    return await perform(call, ACTIONS_META["equipment_reserve"], "/reservations/save", fields,
                         done=f"{what} booked for {for_text}, {when}" + (" (shared)" if share else ""),
                         field_map={"clashes": "share", "resource": "vessel", "subject_id": "order", "planned_to": "to_date", "planned_from": "from_date"})


@action(ActionMeta(
    name="equipment_reservation_cancel", title="Cancel a reservation", doc_actions=["equipment_reservation_cancel"],
    endpoint="POST /reservations/{id}/cancel", role="production", confirm="always",
    undo_kind="none", undo_how="Book it again",
    events=["equipment_reservation_cancelled"], refresh=["reservationsChanged"]))
async def equipment_reservation_cancel(
    call: Call,
    reservation: Annotated[str, Field(description="The booking: the resource and the run as said ('FV-2 for WO-00012'), or its id from equipment_schedule (id:123)")],
    confirmed: Confirmed = False,
) -> dict[str, Any]:
    """Cancel an equipment booking ("cancel the canning line booking for B-26-004"). Asks for confirmation first; cannot be
    undone by voice (book it again). Cancelling a production order, press run or packaging run cancels its bookings by
    itself. <<confirm>> <<not_for>>

    <<terminal>>"""
    r = await resolve.resolve("reservation", reservation)
    if not confirmed:
        return needs_confirmation(f"Cancel the booking {r['label']} ({r['detail']})? It cannot be undone by voice.")
    return await perform(call, ACTIONS_META["equipment_reservation_cancel"], f"/reservations/{r['id']}/cancel", [],
                         done=f"Booking {r['label']} cancelled", undo_available=False)
