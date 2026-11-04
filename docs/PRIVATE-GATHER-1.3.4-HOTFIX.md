# Private Gather 1.3.4 — Database Transport Autodetection Hotfix

This hotfix removes the ambiguity between MySQL socket and TCP connections during browser installation.

## Behavior

- Database port remains blank by default. Blank means Auto.
- `localhost` + Auto first tests native localhost transport without an explicit port.
- If that attempt is rejected or unreachable, the installer then tests forced TCP at `127.0.0.1:3306`.
- Entering an explicit port while the host is `localhost` forces TCP through `127.0.0.1:<port>` because PDO/MySQL may otherwise keep using a Unix socket for `localhost`.
- The transport that actually succeeds is persisted into `.env` so Laravel does not revert to a failed connection method after installation.
- If both socket and forced TCP return MySQL 1045, the installer reports that both routes were tested and that the remaining issue is the database account/password/grant rather than port selection alone.
- Existing database-first behavior remains: Private Gather only requests CREATE DATABASE privileges when the selected database is missing and automatic creation is enabled.

No database schema changes are introduced by this hotfix.
