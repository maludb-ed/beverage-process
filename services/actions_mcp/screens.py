"""The screen and action registries of docs/05-action-manifest.md, parsed into data,
and the URL rules navigate() applies (config/manifest.json is generated from this)."""
from __future__ import annotations

import re
from pathlib import Path
from typing import Any
from urllib.parse import urlencode

from common.config import ROOT

DOC = ROOT / "docs" / "05-action-manifest.md"
MANIFEST = ROOT / "config" / "manifest.json"

# Which record a "{id}" in a URL names, by the URL's first segment (nested resources use their parent).
RECORD_KINDS = {
    "premises": "premises", "locations": "location", "vessels": "vessel", "items": "item", "suppliers": "supplier",
    "users": None, "reason-codes": "reason_code", "purchase-orders": "purchase_order", "receipts": "receipt", "lots": "lot",
    "transfers": "transfer", "adjustments": "adjustment", "counts": "count", "products": "product", "recipes": "recipe",
    "packaging-configs": "packaging_config", "specs": "spec", "approvals": "approval", "production-orders": "production_order",
    "press-runs": "press_run", "batches": "batch", "packaging-runs": "packaging_run", "finished-lots": "finished_lot",
    "kegs": "keg", "customers": "customer", "removals": "removal", "ttb-reports": "ttb_report",
}

# Screens a signed-in user is never sent to by the assistant (full-page auth flows).
NOT_NAVIGABLE = {"login": "the sign-in page is for signed-out users", "login-2fa": "part of signing in",
                 "password-reset": "for signed-out users"}


def _cells(line: str) -> list[str]:
    return [c.strip() for c in line.strip().strip("|").split("|")]


def _codes(cell: str) -> list[str]:
    return re.findall(r"`([^`]+)`", cell)


def parse_doc(path: Path = DOC) -> dict[str, Any]:
    """{'screens': [...], 'actions': [...]} from the two registries' markdown tables."""
    text = path.read_text()
    screens: list[dict[str, Any]] = []
    actions: list[dict[str, Any]] = []
    section = None
    part = None
    for line in text.splitlines():
        if line.startswith("## Screen registry"):
            part = "screens"
        elif line.startswith("## Action registry"):
            part = "actions"
        elif line.startswith("## ") and part:
            part = None
        if line.startswith("### "):
            section = line[4:].strip()
        if not line.startswith("|") or part is None or set(line.replace("|", "").strip()) <= set("-: "):
            continue
        cells = _cells(line)
        if part == "screens":
            if cells[0] in ("Screen id",) or not cells[0].startswith("`"):
                continue
            ids, urls = _codes(cells[0]), _codes(cells[1])
            title, when = cells[2], cells[3]
            prefill = _codes(cells[4]) if len(cells) > 4 else []
            if len(ids) == 2 and len(urls) == 1:
                urls = urls * 2
            for n, sid in enumerate(ids):
                url = urls[min(n, len(urls) - 1)]
                kind = "edit" if sid.endswith("-edit") else None
                # A pair "x-add / x-edit" shares its description; the edit form takes a record, not prefill.
                screen_when = when
                if len(ids) == 2:
                    screen_when = when + (" — the form for an existing record" if n == 1 else " — the empty form for a new one")
                screens.append({
                    "id": sid, "url": url, "title": title, "when": screen_when, "section": section,
                    "prefill": [] if kind == "edit" else prefill,
                })
        else:
            if section == "Assistant-internal" or cells[0] in ("Action",) or not cells[0].startswith("`"):
                continue
            names = _codes(cells[0])
            endpoint = cells[1]
            undo, confirm, role = (cells[3], cells[4], cells[5]) if len(cells) >= 6 else ("", "", "")
            for n, name in enumerate(names):
                if name.startswith("_"):
                    name = names[0].rsplit("_", 1)[0] + name
                undo_parts = [u.strip() for u in undo.split("/")] if "/" in undo and len(names) > 1 else [undo]
                actions.append({
                    "name": name, "section": section, "endpoint": endpoint.replace("`", ""), "params": cells[2],
                    "undo": undo_parts[min(n, len(undo_parts) - 1)], "confirm": confirm, "role": role,
                })
    return {"screens": screens, "actions": actions}


def record_kind(url: str) -> str | None:
    if "{id}" not in url:
        return None
    first = url.strip("/").split("/")[0]
    return RECORD_KINDS.get(first)


def build_path(url: str, record_id: int | None, params: dict[str, str] | None) -> str:
    path = url.replace("{id}", str(record_id)) if "{id}" in url else url
    if params:
        joiner = "&" if "?" in path else "?"
        path += joiner + urlencode(params)
    return path
