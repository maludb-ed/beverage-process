"""Configuration from config/services.env (KEY=VALUE lines), overridable by the environment."""
from __future__ import annotations

import os
from functools import lru_cache
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]          # /var/www
ENV_FILE = ROOT / "config" / "services.env"


@lru_cache(maxsize=1)
def _file_values() -> dict[str, str]:
    values: dict[str, str] = {}
    if ENV_FILE.is_file():
        for line in ENV_FILE.read_text().splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            values[key.strip()] = value.strip()
    return values


def get(key: str, default: str | None = None) -> str | None:
    """Environment first, then config/services.env, then the default."""
    return os.environ.get(key, _file_values().get(key, default))


def require(key: str) -> str:
    value = get(key)
    if not value:
        raise RuntimeError(f"{key} is not configured (config/services.env)")
    return value


def dsn(role: str) -> str:
    """Connection string for role 'app', 'records' or 'activity'."""
    prefix = {"app": "CIDERY_APP_DB", "records": "CIDERY_RECORDS_DB", "activity": "CIDERY_ACTIVITY_DB"}[role]
    return (
        f"host={require('CIDERY_DB_HOST')} port={get('CIDERY_DB_PORT', '5432')} dbname={require('CIDERY_DB_NAME')} "
        f"user={require(prefix + '_USER')} password={require(prefix + '_PASSWORD')} application_name=cidery_{role}"
    )
