## Summary

Describe the operational behavior and affected security or consistency boundary.

## Verification

- [ ] `composer check`
- [ ] Migrations were run twice against PostgreSQL
- [ ] `docker compose config` succeeds
- [ ] No credentials, subscriber data, or production service names were committed
- [ ] Cache invalidation and rollback behavior were considered

## Recovery

Describe rollback steps, especially for schema or incident lifecycle changes.
