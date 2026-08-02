# Security boundaries

## Public boundary

Nginx exposes public HTML, RSS, read-only JSON, health, subscription recording, and the admin entry point. Repository queries filter `is_internal` unless the authenticated admin view explicitly requests internal components. Subscriptions store `HMAC-SHA-256(normalized email, APP_SECRET)` and a non-sensitive scope; no plaintext email or delivery queue exists.

## Administrative boundary

One administrator identity is configured through `ADMIN_USERNAME` and `ADMIN_PASSWORD_HASH`. Symfony PasswordHasher verifies the adaptive hash. A successful login rotates the session ID; logout invalidates it. Login, logout, and writes have intent-specific Symfony CSRF validation. A fixed-window limiter reduces online guessing.

The username stored in audit rows is an application identity label. Production use needs an identity provider and authorization policy so the actor is externally verifiable.

## Session boundary

Session files are writable only by PHP-FPM. Cookie options use HttpOnly, SameSite Strict, cookie-only storage, strict IDs, and 288 bits of identifier material. `SESSION_SECURE` must be true behind HTTPS. Admin responses are `private, no-store`.

## Network and process boundary

Only non-root Nginx publishes a port. Non-root PHP-FPM is reachable only from the edge network. PostgreSQL is isolated on an internal network with no host port. Containers drop capabilities, disallow privilege escalation, and use read-only roots with narrowly scoped tmpfs storage.

## Known boundary limits

- Client-address limiting is safe only after trusted proxy handling is configured.
- Application-level audit immutability does not protect against a database owner or filesystem administrator.
- There is no RBAC, MFA, account recovery, or automatic secret rotation.
- TLS is expected at an external reverse proxy for shared deployments.
