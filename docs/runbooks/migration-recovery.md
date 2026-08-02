# Runbook: recover from a failed migration

## Detection

The application container exits before PHP-FPM starts. Logs contain `Migration failed and was rolled back` and the migration filename. `/health` is unavailable because Nginx cannot reach the application.

## Immediate actions

1. Stop retries if the container is rapidly restarting: `docker compose stop application nginx`.
2. Preserve the application and database logs with timestamps. Remove credentials and subscriber data before sharing them.
3. Confirm database availability and free disk space.
4. Inspect `schema_migrations`; the failed version must be absent. Do not insert it manually.
5. Verify the last applied migration checksum by running `php bin/migrate.php` from the same revision against a restored disposable copy.

## Recovery paths

### Failure before shared application

If the migration has never been applied to a shared environment, correct the SQL, rerun the full test and migration suite against a fresh database, then redeploy the corrected revision.

### Failure after a version was applied elsewhere

Never edit the applied file. Create the next numbered migration with a forward fix. Test upgrading from both the last good backup and a database already containing the earlier version.

### Database may be damaged

The runner wraps each SQL file in a PostgreSQL transaction, so ordinary DDL and seed failure roll back. If a migration invoked non-transactional behavior or an external operation, stop writes, take a forensic snapshot, and restore the most recent verified backup. Apply migrations to the restored copy before reopening traffic.

## Validate service recovery

```bash
php bin/migrate.php
php bin/migrate.php
php bin/database-smoke.php
docker compose up --detach --wait
curl --fail http://localhost:8080/health
```

Confirm component counts, active incidents, immutable update triggers, audit rows, and cache generation. Compare status-page output with the incident timeline before declaring recovery.

## Prevention

- Keep applied migrations immutable and checksum-verified.
- Exercise both clean installation and upgrade paths in CI.
- Back up before schema changes and regularly prove restore procedures.
- Prefer forward-compatible, additive schema changes before destructive cleanup.
