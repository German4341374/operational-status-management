# Five-minute demonstration

## Prepare

```bash
cp .env.example .env
# Replace APP_SECRET and POSTGRES_PASSWORD. The demo admin is admin / ChangeMe-Local-Only-2026.
docker compose up --build --detach --wait
curl --fail http://localhost:8080/health
```

## Minute 0–1: public communication

Open <http://localhost:8080>. Point out the automatically calculated aggregate state, grouped public components, 90-day uptime, active incident updates, and scheduled maintenance. Mention that internal components are filtered at the repository boundary.

## Minute 1–2: operations interface

Sign in at `/admin`. Show the internal Support Console, component/group forms, incident severity, maintenance scheduler, and audit evidence. Explain the environment-based password hash, CSRF token, strict session, and login limiter.

## Minute 2–3: consistency workflow

Create a Critical incident affecting Public API. Refresh the public page: the component and overall state become Major Outage. Explain that incident, immutable initial update, component history, audit event, and cache generation committed together.

## Minute 3–4: immutable timeline and recovery

Publish an Identified update, then Monitoring, then Resolved. Point out optimistic `lock_version` and the fact that corrections are new updates rather than edits. The component returns to its status derived from any remaining incidents.

## Minute 4–5: engineering evidence

```bash
composer check
php bin/migrate.php
php bin/database-smoke.php
docker compose ps
```

Show the GitHub Actions run, PostgreSQL immutability smoke test, non-root images, internal database network, and migration recovery runbook. Close with the honest limits: one admin, no outbound notifications, manual status data, and no trusted proxy configuration.
