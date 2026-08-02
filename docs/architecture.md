# Architecture and consistency decisions

## Modular monolith

The application is one PHP deployment split into HTTP, service, domain, repository, security, and cache modules. This keeps local operation small while preventing controllers from owning business transactions. Symfony components provide focused infrastructure without requiring the full Symfony framework.

## Incident and component consistency

`IncidentService` owns the consistency boundary. It locks the incident row for an update, checks `lock_version`, validates the state-machine transition, appends the update, changes the incident, recomputes each affected component, writes audit evidence, and increments cache generation in one transaction.

Component status is derived, not chosen independently during incident operations. The repository queries every unresolved incident affecting the component and chooses the most severe mapping:

- Minor → Degraded
- Major → Partial outage
- Critical → Major outage

If no incident is active, a currently active maintenance window yields Maintenance; otherwise the component becomes Operational. `status_history` receives a row only when the effective value changes. The public aggregate is a pure worst-state calculation across visible components.

The database uses foreign keys, enum-like checks, and `ON DELETE RESTRICT` for evidence records. Incident updates and audit events have triggers that reject update and delete. Immutability prevents rewriting history; correction is a new update.

## Cache consistency

`cache_generations` is the shared source of cache freshness. Public HTML uses `public_status_<generation>` as its key. A domain mutation increments the generation in its transaction. Rollback therefore restores both domain data and cache identity.

A request checks the generation, renders, and checks it again. A concurrent mutation between those reads prevents the rendered result from entering the cache. Old entries may remain on disk until TTL expiry, but no request using the new generation can select them. This is safer than deleting one shared key, which can allow a slow renderer to repopulate stale HTML after deletion.

The filesystem cache is deliberately local. Multiple instances still agree on freshness through PostgreSQL, but each stores its own rendered generation. Redis would improve hit sharing at the cost of another runtime dependency.

## Transaction boundaries

| Use case | Atomic work |
|---|---|
| Create incident | Incident, affected links, initial immutable update, component histories, audit, cache generation |
| Update incident | Row lock/version check, immutable update, lifecycle state, resolution time, component histories, audit, cache generation |
| Create component/group | Catalog row, initial history where applicable, audit, cache generation |
| Schedule maintenance | Window, affected links, audit, cache generation |
| Migration | One SQL file and its checksum record |

External email or webhook delivery is absent, so no distributed transaction or outbox is needed.

## Uptime model

The displayed 90-day uptime integrates intervals from `status_history`. Operational and Maintenance count as available; degraded and outage states count as unavailable. This is an internal communication metric, not an SLA proof. Missing history before the measurement window is assumed Operational, which is documented as a limitation.
