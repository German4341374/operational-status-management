# Contributing

Use a focused branch and Conventional Commits:

- `feat(incident): add monitoring transition`
- `fix(cache): prevent stale render repopulation`
- `test(security): cover CSRF intent isolation`
- `docs(runbook): clarify migration recovery`

Before opening a pull request:

```bash
composer check
php bin/migrate.php
php bin/migrate.php
php bin/database-smoke.php
docker compose config
```

Never commit `.env`, credentials, subscriber addresses, production component names, logs containing session IDs, or database dumps. Do not modify an applied migration; add a forward migration. Any status-affecting write must preserve the incident/component/audit/cache transaction boundary and include tests.
