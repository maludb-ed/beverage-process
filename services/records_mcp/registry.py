"""Tool registration for the records server.

Each tool is an async function taking one Pydantic input model and returning a dict.
The registry wraps it so that every call is validated (extra fields forbidden), timed,
logged to app.activity_log, encoded as compact JSON, and turned into an actionable
error message on failure. The input model is published as the tool's flat input schema.
"""
from __future__ import annotations

import datetime as dt
import json
import logging
import re
from zoneinfo import ZoneInfo
import typing
from dataclasses import dataclass
from typing import Any, Awaitable, Callable

import psycopg
from mcp.server.mcpserver.exceptions import ToolError
from mcp.server.mcpserver.utilities.func_metadata import ArgModelBase
from mcp_types import CallToolResult, TextContent, ToolAnnotations
from pydantic import ConfigDict, Field, ValidationError, create_model

from common import activity

from . import fmt

log = logging.getLogger("records_mcp")


class Input(ArgModelBase):
    """Base input model: strict (unknown fields rejected), whitespace stripped."""
    model_config = ConfigDict(extra="forbid", str_strip_whitespace=True, arbitrary_types_allowed=True)


class Paged(Input):
    limit: int = Field(50, ge=1, le=200, description="Maximum rows to return (1 to 200).")
    offset: int = Field(0, ge=0, le=1_000_000, description="Rows to skip, for the next page.")


class _RawArguments(ArgModelBase):
    """Accepts any arguments so that validation happens inside the logged wrapper (every call,
    including a rejected one, reaches app.activity_log). The strict model is what tools/list publishes."""
    model_config = ConfigDict(extra="allow")

    def model_dump_one_level(self) -> dict[str, Any]:
        return dict(self.model_extra or {})


def _validation_message(spec: "ToolSpec", exc: ValidationError) -> str:
    allowed = ", ".join(spec.model.model_fields)
    parts = []
    for err in exc.errors()[:6]:
        loc = ".".join(str(x) for x in err.get("loc", ())) or "arguments"
        if err.get("type") == "extra_forbidden":
            parts.append(f"'{loc}' is not an argument of {spec.name} (allowed: {allowed})")
        else:
            parts.append(f"{loc}: {err.get('msg', 'invalid value').removeprefix('Value error, ')}")
    return "Invalid arguments: " + "; ".join(parts) + "."


class ToolFailure(Exception):
    """An anticipated failure whose message is shown to the caller as is."""


class Ambiguous(Exception):
    """A label matched several records; the caller gets the candidates, not an error."""

    def __init__(self, field: str, query: str, candidates: list[dict], hint: str):
        super().__init__(f"{field} '{query}' is ambiguous")
        self.field, self.query, self.candidates, self.hint = field, query, candidates, hint


@dataclass
class ToolSpec:
    name: str
    title: str
    description: str
    model: type[Input]
    fn: Callable[[Any], Awaitable[dict]]


REGISTRY: list[ToolSpec] = []


def records_tool(name: str, title: str, description: str):
    """Decorator: register `async def fn(params: SomeInput) -> dict` as a read-only tool."""
    def deco(fn):
        hints = typing.get_type_hints(fn)
        model = next(v for k, v in hints.items() if k != "return")
        REGISTRY.append(ToolSpec(name, title, " ".join(description.split()), model, fn))
        return fn
    return deco


def page(rows: list[dict], params: Any, **extra: Any) -> dict:
    """Standard list envelope. Queries fetch limit+1 rows so has_more is exact."""
    limit, offset = params.limit, params.offset
    more = len(rows) > limit
    rows = rows[:limit]
    out: dict[str, Any] = dict(extra)
    out.update(count=len(rows), offset=offset, has_more=more)
    if more:
        out["next_offset"] = offset + limit
    out["rows"] = rows
    return out


def _row_count(result: Any) -> int:
    if isinstance(result, dict):
        for key in ("rows", "candidates"):
            if isinstance(result.get(key), list):
                return len(result[key])
    return 1


def _db_error_message(spec: ToolSpec, exc: psycopg.Error) -> str:
    if isinstance(exc, psycopg.errors.QueryCanceled):
        return "The query took longer than 15 seconds and was stopped. Narrow it with a shorter date range or more filters."
    if isinstance(exc, psycopg.errors.InsufficientPrivilege):
        return ("That data is not readable through the records server (sign-in, password and token tables are excluded; "
                "app.users exposes only id, display_name, role and status).")
    if isinstance(exc, psycopg.OperationalError):
        return "The records database is not reachable right now. Try again in a minute."
    if spec.name == "records_search" and exc.diag and exc.diag.message_primary:
        hint = f" Hint: {exc.diag.message_hint}" if exc.diag.message_hint else ""
        return f"The database rejected the query: {exc.diag.message_primary}.{hint} Check table and column names against the schema summary in this tool's description."
    return f"{spec.name} could not read the records ({type(exc).__name__}). Try narrower arguments, or records_search for an unusual question."


_UNIT_SUFFIX = {"L": "l", "mL": "ml", "hL": "hl", "ea": "units"}


def _is_qty(v: Any) -> bool:
    return isinstance(v, dict) and "base" in v and "unit" in v and all(isinstance(x, (int, float)) for k, x in v.items() if k != "unit")


def _is_price(v: Any) -> bool:
    return isinstance(v, dict) and bool(v) and all(k.startswith("per_") for k in v)


_ISO_TS = re.compile(r"^\d{4}-\d\d-\d\d[T ]\d\d:\d\d:\d\d(\.\d+)?([+-]\d\d(:?\d\d)?|Z)$")


def _local_ts(text: str) -> str:
    """Timestamps nested in jsonb arrive as UTC strings; restate them in the client time zone."""
    try:
        t = dt.datetime.fromisoformat(text.replace("Z", "+00:00"))
        return t.astimezone(ZoneInfo(fmt.tz())).isoformat(timespec="minutes")
    except ValueError:
        return text


def flatten(obj: Any) -> Any:
    """Shared convention with the activity server: a quantity {"base": 10, "unit": "kg", "lb": 22.05} under key
    'on_hand' becomes on_hand_kg=10, on_hand_lb=22.05 (liters as _l, counts as _units); a unit price
    {"per_kg": .., "per_lb": ..} under 'avg_unit_cost' becomes avg_unit_cost_per_kg, avg_unit_cost_per_lb."""
    if isinstance(obj, list):
        return [flatten(x) for x in obj]
    if isinstance(obj, str) and _ISO_TS.match(obj):
        return _local_ts(obj)
    if not isinstance(obj, dict):
        return obj
    out: dict[str, Any] = {}
    for k, v in obj.items():
        if _is_qty(v):
            unit = v["unit"]
            out[f"{k}_{_UNIT_SUFFIX.get(unit, unit.lower())}"] = v["base"]
            for u, x in v.items():
                if u not in ("base", "unit"):
                    out[f"{k}_{_UNIT_SUFFIX.get(u, u.lower())}"] = x
        elif _is_price(v) and k not in ("resolved",):
            for u, x in v.items():
                unit = u.removeprefix("per_")
                out[f"{k}_per_{_UNIT_SUFFIX.get(unit, unit.lower())}"] = x
        else:
            out[k] = flatten(v)
    return out


def _unwrap(arguments: dict[str, Any]) -> dict[str, Any]:
    """Arguments arrive as {"params": {...}} (the published shape, same as the activity server).
    A flat object is accepted too; a JSON string under params is parsed."""
    if "params" in arguments and len(arguments) == 1:
        inner = arguments["params"]
        if isinstance(inner, str):
            try:
                inner = json.loads(inner)
            except ValueError:
                raise ToolFailure("params must be a JSON object.")
        if inner is None:
            inner = {}
        if not isinstance(inner, dict):
            raise ToolFailure("params must be an object of named arguments.")
        return inner
    return arguments


async def _run(spec: ToolSpec, arguments: dict[str, Any]) -> CallToolResult:
    result: Any = None
    error: str | None = None
    try:
        logged_args: dict[str, Any] = json.loads(fmt.dumps(_unwrap(arguments)))
    except ToolFailure:
        logged_args = json.loads(fmt.dumps(arguments))
    with activity.Timer() as timer:
        try:
            await fmt.refresh_if_stale()
            params = spec.model.model_validate(_unwrap(arguments))
            logged_args = params.model_dump(mode="json", exclude_defaults=True)
            result = await spec.fn(params)
        except ValidationError as exc:
            error = _validation_message(spec, exc)
        except Ambiguous as amb:
            result = {"ambiguous": True, "field": amb.field, "query": amb.query, "candidates": amb.candidates, "hint": amb.hint}
        except ToolFailure as exc:
            error = str(exc)
        except psycopg.Error as exc:
            log.warning("tool %s database error: %s", spec.name, exc)
            error = _db_error_message(spec, exc)
        except Exception:  # never leak a stack trace to the caller
            log.exception("tool %s failed", spec.name)
            error = f"{spec.name} hit an internal error. Try again with simpler arguments; if it persists, use records_search."
    await activity.log_tool_call(spec.name, logged_args, timer.ms, rows=None if error else _row_count(result), error=error)
    if error is not None:
        raise ToolError(error)
    data = json.loads(fmt.dumps(flatten(result)))
    return CallToolResult(content=[TextContent(type="text", text=fmt.dumps(data))], structured_content=data)


def _params_schema(model: type[Input]) -> dict[str, Any]:
    required = any(f.is_required() for f in model.model_fields.values())
    field = (model, ...) if required else (model, Field(default_factory=model))
    wrapper = create_model(f"{model.__name__}Arguments", params=field)
    return wrapper.model_json_schema()


def _make_handler(spec: ToolSpec):
    async def handler(**kwargs: Any) -> CallToolResult:
        return await _run(spec, kwargs)
    handler.__name__ = spec.name
    return handler


def register_all(server) -> int:
    annotations_for = lambda title: ToolAnnotations(title=title, read_only_hint=True, destructive_hint=False,
                                                    idempotent_hint=True, open_world_hint=False)
    for spec in REGISTRY:
        handler = _make_handler(spec)
        server.add_tool(handler, name=spec.name, title=spec.title, description=spec.description,
                        annotations=annotations_for(spec.title), structured_output=False)
        tool = server._tool_manager.get_tool(spec.name)
        # Publish {"params": <strict model>} (the activity server's shape); validate inside the logged wrapper.
        tool.fn_metadata.arg_model = _RawArguments
        tool.parameters = _params_schema(spec.model)
    return len(REGISTRY)
