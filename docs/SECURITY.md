# Security & Privacy Baseline

The platform is designed for adult users and privacy-sensitive events. 1.0 establishes these controls:

- 18+ registration enforcement and recorded terms/privacy consent.
- Password hashing through Laravel authentication facilities.
- Optional TOTP 2FA with recovery codes.
- Email verification/password-reset foundations.
- Account status/suspension/banning.
- Tenant role isolation for owners/admins/managers/staff/check-in staff.
- Host-based tenant resolution that fails closed for unknown tenant domains.
- Private exact event address separate from public location text.
- Member block/report and moderation records.
- Private media authorization and optional image re-encoding/metadata stripping when GD is available.
- CSRF/session protections supplied by Laravel web middleware.
- Rate limits on high-risk public authentication/report endpoints.
- Upgrade package path traversal/symlink/protected-path checks, per-file hashes, backups, and optional Ed25519 signing.

Before production launch, run a third-party security review/penetration test, configure HTTPS everywhere, use a non-development mail provider, choose an approved payment provider, establish encrypted off-site backups, and document incident/data-retention procedures.
