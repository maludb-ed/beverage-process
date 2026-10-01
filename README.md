# Cidery

A memory-first, ask-me-anything inventory and production application for small cideries (beer and wine later), built on the htmx-php-builder plugin stack: PostgreSQL 17 + MaluDB, Apache, vanilla PHP 8.3, Bootstrap 5.3 (nxl theme), HTMX.

## Layout

```
/var/www/
  app/            PHP application (outside the web root): bootstrap, helpers, features/*/queries.php, views/
  config/         application.php (defaults) + local.php (secrets, gitignored)
  db/             schema files, one per concern; db/host/ is the operator registry
  deploy/         provisioning script, systemd units for MaluDB ingestion
  docs/           research, decisions, Phase 0 plan, MCP tool surface, action manifest, build specs
  html/           Apache DocumentRoot: page controllers per feature, assets/, .htaccess + _router.php
  scripts/        operator CLI scripts (create-owner.php)
  storage/        uploads (per client), writable by www-data
```

## Running a development instance

1. Provision a client database: `deploy/provision-client.sh dev "Dev Cidery" owner@example.com` (creates `cidery_dev`, the `cidery_host` registry, and the MaluDB memory schema).
2. Set the `cidery_app` role password and write it to `config/local.php` (see `config/application.php` for every key; generate `security.totp_key`, `security.action_token_key` with `openssl rand -hex 32` and `security.dummy_password_hash` with `password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])`).
3. `composer install` (Google sign-in, TOTP, QR libraries).
4. Apache: `a2enmod rewrite headers`, `AllowOverride All` on `/var/www/html`, restart.
5. Create the first owner and open the printed invite link: `php scripts/create-owner.php owner@example.com "Owner Name"`.
6. Optional: enable the activity ingestion timer: copy `deploy/cidery-activity-ingest.*` to `/etc/systemd/system/`, create `/etc/cidery/cidery.env` with `CIDERY_DB_PASSWORD`, `systemctl enable --now cidery-activity-ingest.timer`.

Google sign-in activates when `google.client_id` and `google.client_secret` are set (redirect URI `{base_url}/auth/google/callback`). Email goes through MaluMail when `malumail.api_key` is set; without it, messages are written to the Apache error log and the activity log records them as `mode: dev_log`.

## Build order

Phase 0 plan: `docs/03-phase0-plan.md`. Phase 1 design: `db/`, `docs/04-mcp-tool-surface.md`, `docs/05-action-manifest.md`, `docs/build-specs/`. Phase 2 (auth + shell): `docs/06-phase2-shell.md`. Phases 3 and 4 follow the build specs and the manifest.
