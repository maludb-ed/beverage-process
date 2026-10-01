"""Validation for the guarded long-tail SQL tools (records_search, activity_sql):
exactly one read-only SELECT (or WITH ... SELECT), no semicolons, no comments,
no writes, no session or admin functions, a hard row cap. The database role is
read-only and has a statement timeout as well; this is the first line of defense."""
from __future__ import annotations

import re

FORBIDDEN = re.compile(
    r"\b(insert|update|delete|merge|truncate|drop|alter|create|grant|revoke|comment|copy|call|do|execute|prepare|"
    r"vacuum|analyze|cluster|reindex|refresh|listen|notify|lock|set|reset|begin|commit|rollback|savepoint|"
    r"pg_sleep|pg_read_file|pg_read_binary_file|pg_ls_dir|pg_stat_file|lo_import|lo_export|dblink|pg_terminate_backend|"
    r"pg_cancel_backend|set_config|current_setting|into)\b",
    re.IGNORECASE,
)


def validate_select(sql: str, max_rows: int = 200) -> str:
    """Return a wrapped, row-capped query or raise ValueError with an actionable message."""
    text = sql.strip()
    if not text:
        raise ValueError("Provide one SELECT statement.")
    if ";" in text.rstrip(";") or "--" in text or "/*" in text:
        raise ValueError("Use a single statement with no semicolons or comments.")
    text = text.rstrip(";").strip()
    if not re.match(r"^(select|with)\b", text, re.IGNORECASE):
        raise ValueError("Only SELECT (or WITH ... SELECT) queries are allowed.")
    # Ignore string literals when checking for forbidden words.
    stripped = re.sub(r"'(?:[^']|'')*'", "''", text)
    bad = FORBIDDEN.search(stripped)
    if bad:
        raise ValueError(f"'{bad.group(0)}' is not allowed; this tool only reads.")
    return f"SELECT * FROM ({text}) AS q LIMIT {int(max_rows)}"
