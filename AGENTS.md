# Repository working agreement

- Keep source, comments, documentation, examples, and commits in English.
- Preserve HTTP, service, domain, repository, security, and cache boundaries.
- Use prepared SQL and explicit transactions for every mutation.
- Keep incident updates and audit records append-only.
- Invalidate public cache generation in the same transaction as status changes.
- Add a new numbered migration; never rewrite one already applied to a shared database.
- Use fictional service names and never commit subscriber data or credentials.
- Run `composer check` and PostgreSQL smoke checks before proposing a change.
- Use Conventional Commits without attribution trailers unless a contributor explicitly requests them.
