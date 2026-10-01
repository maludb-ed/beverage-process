"""Bearer-token auth for the client-facing MCP servers. Tokens are issued by the
app's AI access tokens screen and stored as sha256 hashes in app.mcp_access_tokens
with a scope ('records' or 'activity'). Revoked tokens fail immediately."""
from __future__ import annotations

import hashlib
import json
from typing import Any

from . import db
from .activity import current_server, current_token_name


async def check_token(token: str, scope: str) -> str | None:
    """The token's name when valid for the scope, else None."""
    if not token or len(token) < 32:
        return None
    digest = hashlib.sha256(token.encode()).hexdigest()
    row = await db.fetch_one(
        "app",
        "SELECT id, name FROM app.mcp_access_tokens WHERE token_hash = %s AND scope = %s AND revoked_at IS NULL",
        (digest, scope),
    )
    if row is None:
        return None
    try:
        await db.execute("app", "UPDATE app.mcp_access_tokens SET last_used_at = now() WHERE id = %s", (row["id"],))
    except Exception:
        pass
    return str(row["name"])


class BearerTokenMiddleware:
    """ASGI middleware: requires 'Authorization: Bearer <token>' valid for `scope`.
    Sets the token name and server name for activity logging of the tool calls."""

    def __init__(self, app: Any, scope: str, server_name: str) -> None:
        self.app = app
        self.scope = scope
        self.server_name = server_name

    async def __call__(self, scope: dict, receive: Any, send: Any) -> None:
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return
        if scope.get("path", "").rstrip("/") == "/health":
            await _respond(send, 200, {"status": "ok", "server": self.server_name})
            return
        headers = {k.decode().lower(): v.decode() for k, v in scope.get("headers", [])}
        auth = headers.get("authorization", "")
        token = auth[7:].strip() if auth.lower().startswith("bearer ") else ""
        name = await check_token(token, self.scope)
        if name is None:
            await _respond(send, 401, {"error": "invalid_token", "error_description": f"A valid {self.scope} access token is required. Create one under Setup > AI access tokens."},
                           extra=[(b"www-authenticate", b'Bearer realm="cidery"')])
            return
        token_var = current_token_name.set(name)
        server_var = current_server.set(self.server_name)
        try:
            await self.app(scope, receive, send)
        finally:
            current_token_name.reset(token_var)
            current_server.reset(server_var)


async def _respond(send: Any, status: int, body: dict, extra: list | None = None) -> None:
    payload = json.dumps(body).encode()
    headers = [(b"content-type", b"application/json"), (b"content-length", str(len(payload)).encode())] + (extra or [])
    await send({"type": "http.response.start", "status": status, "headers": headers})
    await send({"type": "http.response.body", "body": payload})
