"""Unit conversions mirroring app/units.php: the database stores base units (L, kg, ea);
forms take display units from app.client_settings (gal, lb by default)."""
from __future__ import annotations

import time
from datetime import datetime
from zoneinfo import ZoneInfo

from common import db

_cache: dict = {"at": 0.0}

ALIASES = {
    "gallon": "gal", "gallons": "gal", "gals": "gal", "liter": "L", "liters": "L", "litre": "L", "litres": "L", "l": "L",
    "milliliter": "mL", "milliliters": "mL", "ml": "mL", "hectoliter": "hL", "hl": "hL", "barrel": "bbl", "barrels": "bbl",
    "pound": "lb", "pounds": "lb", "lbs": "lb", "kilogram": "kg", "kilograms": "kg", "kilo": "kg", "kilos": "kg", "kgs": "kg",
    "gram": "g", "grams": "g", "ounce": "oz", "ounces": "oz", "tons": "ton", "bushels": "bushel", "each": "ea", "units": "ea",
    "unit": "ea", "pcs": "ea", "piece": "ea", "pieces": "ea", "bins": "bin", "cases": "case", "fl oz": "floz",
}


async def _load() -> dict:
    if time.time() - _cache["at"] > 60:
        units = await db.fetch_all("records", "SELECT code, dimension, to_base_factor FROM app.units")
        settings = await db.fetch_one("records", "SELECT volume_display_unit, mass_display_unit, fruit_display_unit, timezone FROM app.client_settings WHERE id = 1") or {}
        _cache.update(at=time.time(), units={u["code"]: (u["dimension"], float(u["to_base_factor"])) for u in units},
                      settings={"volume": settings.get("volume_display_unit") or "gal", "mass": settings.get("mass_display_unit") or "lb",
                                "fruit": settings.get("fruit_display_unit") or "lb", "timezone": settings.get("timezone") or "America/New_York"})
    return _cache


def normalize(unit: str | None) -> str | None:
    if unit is None:
        return None
    text = unit.strip()
    return ALIASES.get(text.lower(), text)


async def table() -> dict[str, tuple[str, float]]:
    return (await _load())["units"]


async def display_unit(base_unit: str, kind: str = "default") -> str:
    s = (await _load())["settings"]
    if base_unit == "L":
        return s["volume"]
    if base_unit == "kg":
        return s["fruit"] if kind == "fruit" else s["mass"]
    return base_unit


async def factor(unit: str) -> float | None:
    entry = (await table()).get(unit)
    return entry[1] if entry else None


async def to_base(qty: float, unit: str, base_unit: str, item_units: dict[str, float] | None = None) -> float | None:
    """qty in `unit` → base quantity, or None when the unit does not fit the base unit's dimension."""
    unit = normalize(unit) or base_unit
    if item_units and unit in item_units:
        return qty * item_units[unit]
    units = await table()
    if unit not in units or base_unit not in units or units[unit][0] != units[base_unit][0]:
        return None
    return qty * units[unit][1] / units[base_unit][1]


async def base_to_display(qty_base: float, base_unit: str, kind: str = "default") -> tuple[float, str]:
    unit = await display_unit(base_unit, kind)
    f = await factor(unit) or 1.0
    return qty_base / f, unit


async def to_display(qty: float, unit: str | None, base_unit: str, kind: str = "default", item_units: dict[str, float] | None = None) -> float | None:
    """A quantity said in any fitting unit → the display unit the PHP form expects."""
    base = await to_base(qty, unit or await display_unit(base_unit, kind), base_unit, item_units)
    if base is None:
        return None
    return (await base_to_display(base, base_unit, kind))[0]


async def timezone() -> ZoneInfo:
    return ZoneInfo((await _load())["settings"]["timezone"])


async def now_local() -> str:
    """'YYYY-MM-DDTHH:MM' in the client time zone (datetime-local form value)."""
    return datetime.now(await timezone()).strftime("%Y-%m-%dT%H:%M")


async def local_datetime(value: str | None) -> str:
    """Normalize a user-given time (ISO date/time) to the form's datetime-local value; default now."""
    if not value:
        return await now_local()
    text = value.strip().replace(" ", "T")
    try:
        parsed = datetime.fromisoformat(text)
    except ValueError as exc:
        raise ValueError(f"'{value}' is not a date and time; use YYYY-MM-DDTHH:MM.") from exc
    if parsed.tzinfo is not None:
        parsed = parsed.astimezone(await timezone())
    return parsed.strftime("%Y-%m-%dT%H:%M")


def fmt(qty: float, unit: str, decimals: int = 2) -> str:
    text = f"{qty:,.{decimals}f}".rstrip("0").rstrip(".") if decimals else f"{qty:,.0f}"
    return f"{text} {unit}"
