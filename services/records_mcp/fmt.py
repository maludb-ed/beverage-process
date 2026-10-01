"""Display units, client settings, JSON encoding and date helpers for the records server.

Quantities are stored in base units (L, kg, ea). Every tool returns a quantity as
{"base": 123.4, "unit": "L", "gal": 32.6} using the factors in app.units and the
display units in app.client_settings (volume, mass, fruit; fruit also in tons and bushels).
"""
from __future__ import annotations

import datetime as dt
import json
import time
import uuid
from decimal import Decimal
from typing import Any
from zoneinfo import ZoneInfo

import psycopg
from psycopg.rows import dict_row

from common import config, db

# Defaults match db/004_foundation.sql; replaced from the database at startup and every 5 minutes.
_state: dict[str, Any] = {
    "units": {"L": ("volume", 1.0), "gal": ("volume", 3.785411784), "kg": ("mass", 1.0), "lb": ("mass", 0.45359237),
              "ton": ("mass", 907.18474), "bushel": ("mass", 19.05087954), "ea": ("count", 1.0)},
    "volume": "gal", "mass": "lb", "fruit": "lb", "tz": "America/New_York", "client": "", "loaded_at": 0.0,
}
_TTL = 300.0
_SETTINGS_SQL = "SELECT client_name, timezone, volume_display_unit, mass_display_unit, fruit_display_unit FROM app.client_settings WHERE id = 1"
_UNITS_SQL = "SELECT code, dimension, to_base_factor FROM app.units"


def _apply(settings: dict | None, units: list[dict]) -> None:
    if units:
        _state["units"] = {u["code"]: (u["dimension"], float(u["to_base_factor"])) for u in units}
    if settings:
        _state.update(client=settings["client_name"], tz=settings["timezone"], volume=settings["volume_display_unit"],
                      mass=settings["mass_display_unit"], fruit=settings["fruit_display_unit"])
    _state["loaded_at"] = time.monotonic()


def load_sync() -> None:
    """Startup load on a short-lived synchronous connection (the async pools belong to the server loop)."""
    with psycopg.connect(config.dsn("records"), row_factory=dict_row) as conn:
        settings = conn.execute(_SETTINGS_SQL).fetchone()
        units = conn.execute(_UNITS_SQL).fetchall()
    _apply(settings, units)


async def refresh_if_stale() -> None:
    if time.monotonic() - _state["loaded_at"] < _TTL:
        return
    try:
        settings = await db.fetch_one("records", _SETTINGS_SQL)
        units = await db.fetch_all("records", _UNITS_SQL)
        _apply(settings, units)
    except Exception:
        _state["loaded_at"] = time.monotonic()  # keep the previous values; retry after the TTL


def tz() -> str:
    return _state["tz"]


def today() -> dt.date:
    return dt.datetime.now(ZoneInfo(_state["tz"])).date()


def factor(unit: str) -> float | None:
    u = _state["units"].get(unit)
    return u[1] if u else None


def dimension(unit: str) -> str | None:
    u = _state["units"].get(unit)
    return u[0] if u else None


def display_units() -> dict[str, str]:
    return {"volume": _state["volume"], "mass": _state["mass"], "fruit": _state["fruit"]}


def _num(value: Any) -> float | None:
    if value is None:
        return None
    return float(value)


def _round(value: float, unit: str) -> float:
    if abs(value) < 1:
        return round(value, 4)
    return round(value, 3 if unit in ("ton", "hL", "bbl") else 2)


def q(value: Any, unit: str | None, *, fruit: bool = False, unit_volume_l: Any = None) -> dict | None:
    """A quantity in its unit plus display values. `fruit` adds pounds, tons and bushels for fruit;
    `unit_volume_l` converts a count of packages (ea) into liters and gallons."""
    v = _num(value)
    if v is None:
        return None
    unit = unit or "ea"
    out: dict[str, Any] = {"base": round(v, 4), "unit": unit}
    dim = dimension(unit)
    f = factor(unit) or 1.0
    base_value = v * f
    if dim == "volume":
        targets = ["L", _state["volume"]] if unit != "L" else [_state["volume"]]
    elif dim == "mass":
        targets = [_state["fruit"], "ton", "bushel"] if fruit else [_state["mass"]]
        if unit != "kg":
            targets = ["kg"] + targets
    else:
        targets = []
        uv = _num(unit_volume_l)
        if uv:
            out["L"] = round(v * uv, 2)
            out[_state["volume"]] = _round(v * uv / (factor(_state["volume"]) or 1.0), _state["volume"])
    for t in dict.fromkeys(targets):
        if t != unit and factor(t):
            out[t] = _round(base_value / factor(t), t)
    return out


def liters(value: Any) -> dict | None:
    return q(value, "L")


def gallons(liters_value: Any) -> float | None:
    v = _num(liters_value)
    return None if v is None else round(v / (factor("gal") or 3.785411784), 3)


def unit_price(cost_per_base: Any, base_unit: str, *, fruit: bool = False) -> dict | None:
    """Cost per base unit restated per display unit (per lb, per ton, per gal)."""
    c = _num(cost_per_base)
    if c is None:
        return None
    out = {f"per_{base_unit}": round(c, 4)}
    dim = dimension(base_unit)
    if dim == "volume":
        targets = [_state["volume"]]
    elif dim == "mass":
        targets = [_state["fruit"], "ton", "bushel"] if fruit else [_state["mass"]]
    else:
        targets = []
    for t in dict.fromkeys(targets):
        if t != base_unit and factor(t):
            out[f"per_{t}"] = round(c * factor(t), 4)
    return out


def money(value: Any) -> float | None:
    v = _num(value)
    return None if v is None else round(v, 2)


def _default(obj: Any) -> Any:
    if isinstance(obj, Decimal):
        f = float(obj)
        return int(f) if f.is_integer() and abs(f) < 1e15 else round(f, 6)
    if isinstance(obj, dt.datetime):
        if obj.tzinfo is not None:
            obj = obj.astimezone(ZoneInfo(_state["tz"]))
        return obj.isoformat(timespec="minutes")
    if isinstance(obj, (dt.date, dt.time)):
        return obj.isoformat()
    if isinstance(obj, dt.timedelta):
        return round(obj.total_seconds() / 86400, 2)
    if isinstance(obj, uuid.UUID):
        return str(obj)
    if isinstance(obj, (set, tuple)):
        return list(obj)
    if isinstance(obj, (bytes, memoryview)):
        return "<binary>"
    return str(obj)


def dumps(payload: Any) -> str:
    return json.dumps(payload, default=_default, separators=(",", ":"), ensure_ascii=False)


def drop_none(row: dict) -> dict:
    """Compact output: leave out null fields."""
    return {k: v for k, v in row.items() if v is not None}
