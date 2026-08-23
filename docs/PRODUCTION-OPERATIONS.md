# Private Gather Production Operations

This runbook applies to Hosted and Self-Hosted deployments built from the synchronized Private Gather core. It does not replace provider-specific database, object-storage, mail, DNS, payment, or monitoring procedures.

## Release discipline

1. Deploy only an exact package produced from a green CI commit. Do not rebuild a different dependency tree on production.
2. Verify `VERSION`, the package SHA-256 file, PHP >= 8.3, application health, database connectivity, private storage, queue backend, and production debug state.
3. Keep live `.env`, `storage/`, installed locks, generated uploads, and database data outside browser-upgrade replacement semantics.
4. Before an upgrade, take a database backup and a protected application-data backup. Record their timestamps and the source release version.
5. Put the application into the platform's normal maintenance/deployment procedure only for the shortest necessary window. Run migrations with the release code that owns them.
6. Clear/rebuild framework caches after configuration or code deployment, restart long-running queue workers, then run `php tools/http-smoke.php https://your-host`.
7. Confirm tenant routing, login, one public event page, one tenant management page, queue processing, mail delivery, and payment/provider callbacks appropriate to the installation.

## Backup policy

Back up **database + private application data + configuration secrets** as separate protected assets. A source-code ZIP alone is not a recoverable Private Gather installation.

Recommended minimum retention: daily snapshots for 7 days, weekly for 4 weeks, monthly for 3 months, adjusted to the operator's legal and business requirements. Encrypt backups at rest and in transit. Do not put backups under a public web root.

Database backups should use the database vendor's consistent snapshot/dump tooling. For MySQL/MariaDB, use a transaction-consistent dump appropriate to the storage engine and verify the dump can be read. For managed databases, prefer provider snapshots plus periodic export testing.

Private files include at least storage-backed uploads/attachments, generated ticket or export artifacts that are not reproducible, and any provider-specific private media. Public immutable build assets can be restored from the certified release package.

Treat `.env` and external provider credentials as secrets. Store them in an approved secrets/password system rather than an unencrypted general-purpose backup archive.

## Restore drill

A backup is not certified until it has been restored outside production. On a disposable test host:

1. Install the same Private Gather release that created the backup.
2. Restore a copy of the database and private storage using non-production credentials and domains.
3. Set `APP_ENV` and all outbound mail/payment/provider integrations to safe test values before booting the restored site.
4. Run `php artisan about`, database migration status, the protected System Health page, and `php tools/http-smoke.php` against the test URL.
5. Verify one member account, one tenant, one event, one order/ticket, one private upload, and one CMS page. Never send real campaign mail/SMS from a restore drill.
6. Record the recovery time and any manual steps. Fix the runbook when a restore requires undocumented knowledge.

## Database and migration rollback

Database migrations are forward-owned by the release. Do not blindly run `migrate:rollback` on a live incident: destructive down migrations can lose data added after deployment. For a failed release, prefer restoring the pre-upgrade database snapshot and the matching pre-upgrade application version when schema/data compatibility is uncertain.

## Queue, scheduled work and mail

Production should use a durable queue backend rather than `sync`. Supervise queue workers and restart them after each application deployment so old code is not retained in memory. Failed-job growth, queue age and recurring scheduler execution should be externally monitored.

`MAIL_MAILER=log` is suitable for development, not customer notifications. Before launch, test delivery, bounce handling, SPF/DKIM/DMARC for the sending domain, and unsubscribe/consent behavior for marketing mail.

## Payments, refunds and payouts

Private Gather's ledger separates gross amount, platform fee and tenant net. External processor state remains authoritative for actual movement of funds. Never mark a provider-backed membership or ticket payment paid merely because a local request was created.

Reconcile processor transactions against Private Gather order/payment/provider references. Refunds and payouts should be checked for `requested`, `processing`, `completed/paid`, failure, and manual-required states. Restrict provider secrets to server configuration and least-privilege credentials.

## Domains and TLS

The domain marketplace fails closed when no registrar adapter is configured. Existing-domain verification remains a separate DNS workflow. Monitor custom-domain DNS and TLS health; do not infer registry ownership from a DNS lookup alone.

## Privacy and trust operations

Do not log raw identity documents, passwords, session cookies, push authentication keys, precise private locations, or payment credentials. Trust cases may reference a verification record/status but must not copy raw verification evidence into general case notes.

Member discovery, messaging and location visibility are privacy-controlled. Deployment smoke tests should use test accounts rather than loosening production privacy settings.

## Monitoring and alerting

Monitor from outside the application: HTTPS availability, `/health`, TLS expiration, database availability, queue lag/failed jobs, storage capacity, error rate, latency, backup freshness and restore-drill age. Optional observability can be connected through `OBSERVABILITY_DSN`; the in-app health page is not a replacement for external monitoring.

Suggested alerts include sustained 5xx responses, database failures, storage below 10% free, queue lag above the service target, failed payment/webhook processing, and expired/failed tenant TLS.

## Capacity and smoke testing

`php tools/http-smoke.php https://host` performs only a small, read-only deployment check. Run real load tests only against staging or an explicitly isolated environment with synthetic accounts/data. Capacity testing should cover anonymous discovery, authenticated member pages, tenant administration, event checkout contention, queue throughput, and database saturation without exposing real private content.

## Incident priorities

For suspected credential exposure or unauthorized access, preserve logs/evidence, rotate affected secrets/sessions, restrict access and follow the operator's incident/legal process. For data corruption, stop writes where practical and preserve a snapshot before repair. For performance incidents, do not delete queues, orders, audit records or user data as a shortcut.

## Final release checklist

- CI green on the exact commit for source integrity, Hosted, Self-Hosted, PHP 8.3 and packaging.
- SHA-256 package verification passed.
- `APP_DEBUG=false`, strong `APP_KEY`, encrypted durable sessions, durable queue.
- Database/private storage backup completed and restore procedure current.
- Upgrade signatures enabled for production browser upgrades with the correct public key.
- Mail/provider/domain/push/observability optional capabilities explicitly configured or intentionally disabled.
- External HTTPS health monitoring active.
- Post-deploy HTTP smoke and representative tenant/member checks passed.
