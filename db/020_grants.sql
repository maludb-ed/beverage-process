-- 020_grants.sql — read-only roles for the MCP servers. Run as superuser in
-- the client database after every schema file. Idempotent.

-- Records MCP server: read every app table and view, nothing else.
GRANT USAGE ON SCHEMA app TO cidery_records_ro;
GRANT SELECT ON ALL TABLES IN SCHEMA app TO cidery_records_ro;
ALTER DEFAULT PRIVILEGES FOR ROLE cidery_app IN SCHEMA app GRANT SELECT ON TABLES TO cidery_records_ro;
GRANT EXECUTE ON FUNCTION app.trace_forward(bigint), app.trace_backward(bigint),
                          app.derive_tax_class(text, numeric, numeric, numeric, boolean, boolean, boolean, date)
    TO cidery_records_ro;
-- The records server must never see auth material.
REVOKE SELECT ON app.users, app.auth_identities, app.totp_recovery_codes, app.login_attempts,
                 app.one_time_tokens, app.mcp_access_tokens FROM cidery_records_ro;
GRANT SELECT (id, display_name, role, status) ON app.users TO cidery_records_ro;

-- Activity MCP server: the memory schema plus the raw log and user names.
GRANT USAGE ON SCHEMA memory, app TO cidery_activity_ro;
GRANT SELECT ON ALL TABLES IN SCHEMA memory TO cidery_activity_ro;
ALTER DEFAULT PRIVILEGES FOR ROLE cidery_app IN SCHEMA memory GRANT SELECT ON TABLES TO cidery_activity_ro;
GRANT SELECT ON app.activity_log TO cidery_activity_ro;
GRANT SELECT (id, display_name, role, status) ON app.users TO cidery_activity_ro;
GRANT maludb_read TO cidery_activity_ro;

-- Both read roles: statement timeout and no writes, belt and braces.
ALTER ROLE cidery_records_ro  SET statement_timeout = '15s';
ALTER ROLE cidery_activity_ro SET statement_timeout = '15s';
ALTER ROLE cidery_records_ro  SET default_transaction_read_only = on;
ALTER ROLE cidery_activity_ro SET default_transaction_read_only = on;
