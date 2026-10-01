"""Run an MCPServer as a stateless JSON streamable-HTTP app behind Apache."""
from __future__ import annotations

import contextlib

import uvicorn
from mcp.server.transport_security import TransportSecuritySettings

from . import db
from .auth import BearerTokenMiddleware


def build_app(server, *, scope: str | None, server_name: str, allowed_hosts: list[str] | None = None):
    """Starlette app at /mcp. `scope` None means no bearer check (the localhost-only actions server)."""
    security = TransportSecuritySettings(
        enable_dns_rebinding_protection=allowed_hosts is not None,
        allowed_hosts=allowed_hosts or [],
        allowed_origins=[],
    )
    app = server.streamable_http_app(streamable_http_path="/mcp", json_response=True, stateless_http=True,
                                     transport_security=security, host="0.0.0.0")
    return BearerTokenMiddleware(app, scope, server_name) if scope else app


def run(app, port: int) -> None:
    uvicorn.run(app, host="127.0.0.1", port=port, log_level="info", proxy_headers=True, forwarded_allow_ips="127.0.0.1")
