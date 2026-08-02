# Security policy

Use GitHub private vulnerability reporting for suspected security issues. Include the affected revision, impact, and a sanitized reproduction. Do not create a public issue containing credentials, subscriber identifiers, session cookies, or production service data.

The latest `main` revision is supported until tagged releases are introduced.

Before production use:

- place Nginx behind TLS and configure trusted proxy addresses;
- replace the demonstration password hash and store all secrets in a managed secret system;
- integrate centralized identity, MFA, and authorization;
- use a least-privilege PostgreSQL role rather than a schema owner at runtime;
- export audit events to tamper-evident external storage;
- configure database backups, restore tests, monitoring, and retention;
- pin deployment images by digest and scan them in the delivery platform.
