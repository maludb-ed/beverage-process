"""Reference vocabularies read at startup (records role) for tool descriptions and validation,
plus the fixed option lists the PHP controllers accept (copied from their constants)."""
from __future__ import annotations

from typing import Any

from common import db

# app/features/lots/queries.php LOT_ATTRIBUTE_KEYS
LOT_ATTRIBUTE_KEYS = {
    "variety": "Variety", "orchard": "Orchard", "block": "Block", "brix": "Brix (°Bx)", "ph": "pH", "ta": "TA (g/L)",
    "free_so2": "Free SO2 (mg/L)", "total_so2": "Total SO2 (mg/L)", "abv": "ABV (%)", "co2_g_100ml": "CO2 (g/100 mL)",
    "fruit_share_pct": "Fruit share (%)", "strain": "Yeast strain", "generation": "Yeast generation", "viability_pct": "Viability (%)",
    "alpha_acid_pct": "Alpha acid (%)", "moisture_pct": "Moisture (%)", "bin_count": "Bins", "net_kg": "Net weight (kg)",
}
TEXT_ATTRIBUTES = {"variety", "orchard", "block", "strain"}
# app/features/batches/queries.php BATCH_ADDITION_PURPOSES
ADDITION_PURPOSES = ["nutrient", "sulfite", "enzyme", "sweetener", "acid", "fining", "base_juice", "other"]
LOT_STATUSES = ["quarantine", "hold", "released", "rejected"]
RELEASE_BASES = ["coa", "inspection", "readings", "sensory", "override", "other"]
VESSEL_SETTABLE_STATUSES = ["empty", "cleaning", "out_of_service"]
COUNT_KINDS = ["cycle", "physical"]
KEG_EVENTS = ["fill", "clean", "mark_lost", "found", "retire"]
KEG_OWNERSHIPS = ["owned", "rented", "customer_owned"]
REMOVAL_DESTINATIONS = ["tax_paid_sale", "taproom_transfer", "in_bond_transfer", "export", "sample_testing", "destroyed",
                        "breakage", "family_use", "return_from_customer"]
REMOVAL_CUSTOMER_REQUIRED = ["tax_paid_sale", "in_bond_transfer", "export", "return_from_customer"]

data: dict[str, Any] = {}


async def load() -> dict[str, Any]:
    data["measurements"] = {r["code"]: r for r in await db.fetch_all(
        "records", "SELECT code, name, unit, decimals, min_valid::float AS min_valid, max_valid::float AS max_valid FROM app.measurement_types ORDER BY name")}
    data["stages"] = {r["code"]: r for r in await db.fetch_all(
        "records", "SELECT code, name, display_order, is_terminal FROM app.stages WHERE 'cider' = ANY(beverage_types) ORDER BY display_order")}
    data["loss_reasons"] = await db.fetch_all(
        "records", "SELECT id, code, name, classification, requires_approval_above::float AS threshold_l FROM app.reason_codes WHERE active AND applies_to IN ('loss', 'dump') ORDER BY name")
    data["override_reasons"] = await db.fetch_all(
        "records", "SELECT id, code, name FROM app.reason_codes WHERE active AND applies_to = 'override' ORDER BY name")
    for key, sql in {
        "customer_kinds": "SELECT DISTINCT kind FROM app.customers",
        "supplier_kinds": "SELECT DISTINCT kind FROM app.suppliers",
    }.items():
        data[key] = [r["kind"] for r in await db.fetch_all("records", sql)]
    return data


def measurement_lines() -> str:
    return "; ".join(f"{c} = {m['name']} ({m['unit']}, {m['min_valid']:g}–{m['max_valid']:g})" for c, m in data["measurements"].items())


def stage_lines() -> str:
    return ", ".join(f"{c} ({s['name']})" for c, s in data["stages"].items())


def loss_reason_lines() -> str:
    return ", ".join(f"{r['code']} ({r['name']}, {r['classification']})" for r in data["loss_reasons"])


def find_measurement(text: str) -> dict[str, Any] | None:
    key = text.strip().lower().replace(" ", "_")
    synonyms = {"gravity": "sg", "specific_gravity": "sg", "so2": "free_so2", "free_so₂": "free_so2", "temperature": "temp_c",
                "temp": "temp_c", "alcohol": "abv", "acidity": "ta", "titratable_acidity": "ta", "sugar": "brix",
                "residual_sugar": "rs", "dissolved_oxygen": "do", "carbonation": "co2_vol", "co2_volumes": "co2_vol"}
    key = synonyms.get(key, key)
    if key in data["measurements"]:
        return data["measurements"][key]
    for code, m in data["measurements"].items():
        if m["name"].lower() == text.strip().lower():
            return m
    return None


def find_stage(text: str | None) -> str | None:
    if not text:
        return None
    key = text.strip().lower().replace(" ", "_")
    if key in data["stages"]:
        return key
    for code, s in data["stages"].items():
        if s["name"].lower() == text.strip().lower() or s["name"].lower().startswith(text.strip().lower()):
            return code
    return None
