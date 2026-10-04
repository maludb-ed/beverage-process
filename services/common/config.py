"""Configuration for the Python services.

Standalone: config/services.env (CIDERY_* keys). Beside the Business OS kernel (os-adoption, 2026-10-04): config/.env,
the file the kernel's installer writes, whose keys follow the integration contract (DB_NAME, MCP_RECORDS_PORT, …).
Both files are read; a CIDERY_* key falls back to its contract alias; the real environment overrides everything.
"""
from __future__ import annotations

import os
from functools import lru_cache
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]          # the repository root (/srv/apps/cidery under the OS)
ENV_FILES = (ROOT / "config" / "services.env", ROOT / "config" / ".env")

# CIDERY_* key -> the contract's key in config/.env (maludb-os-integration, registration.md)
ALIASES = {
    "CIDERY_DB_HOST": "DB_HOST", "CIDERY_DB_PORT": "DB_PORT", "CIDERY_DB_NAME": "DB_NAME",
    "CIDERY_APP_DB_USER": "DB_USER", "CIDERY_APP_DB_PASSWORD": "DB_PASSWORD",
    "CIDERY_RECORDS_DB_USER": "MCP_RECORDS_DB_USER", "CIDERY_RECORDS_DB_PASSWORD": "MCP_RECORDS_DB_PASSWORD",
    "CIDERY_ACTIVITY_DB_USER": "MCP_ACTIVITY_DB_USER", "CIDERY_ACTIVITY_DB_PASSWORD": "MCP_ACTIVITY_DB_PASSWORD",
    "CIDERY_ACTION_TOKEN_KEY": "ACTION_TOKEN_KEY", "CIDERY_APP_BASE_URL": "APP_URL",
    "CIDERY_RECORDS_MCP_PORT": "MCP_RECORDS_PORT", "CIDERY_ACTIVITY_MCP_PORT": "MCP_ACTIVITY_PORT",
    "CIDERY_ACTIONS_MCP_PORT": "ACTIONS_MCP_PORT", "CIDERY_ASSISTANT_PORT": "ASSISTANT_PORT",
}


@lru_cache(maxsize=1)
def _file_values() -> dict[str, str]:
    values: dict[str, str] = {}
    for path in ENV_FILES:                            # config/.env (the kernel's) read last: it wins over services.env
        if not path.is_file():
            continue
        for line in path.read_text().splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            value = value.strip()
            if len(value) >= 2 and value[0] in "\"'" and value[-1] == value[0]:
                value = value[1:-1]
            if value != "":
                values[key.strip()] = value
    return values


def get(key: str, default: str | None = None) -> str | None:
    """Environment first, then the files, then the contract alias of a CIDERY_* key, then the default."""
    for k in (key, ALIASES.get(key)):
        if k is None:
            continue
        value = os.environ.get(k) or _file_values().get(k)
        if value:
            return value
    return default


def require(key: str) -> str:
    value = get(key)
    if not value:
        raise RuntimeError(f"{key} is not configured (config/services.env or config/.env)")
    return value


def os_enabled() -> bool:
    return (get("OS_ENABLED", "") or "").strip().lower() in ("1", "true", "on", "yes")


def dsn(role: str) -> str:
    """Connection string for role 'app', 'records' or 'activity'."""
    prefix = {"app": "CIDERY_APP_DB", "records": "CIDERY_RECORDS_DB", "activity": "CIDERY_ACTIVITY_DB"}[role]
    return (
        f"host={require('CIDERY_DB_HOST')} port={get('CIDERY_DB_PORT', '5432')} dbname={require('CIDERY_DB_NAME')} "
        f"user={require(prefix + '_USER')} password={require(prefix + '_PASSWORD')} application_name=cidery_{role}"
    )
