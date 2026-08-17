# Private Gather 1.0.7
Private Gather is a multi-tenant event, membership, ticketing, website and operations platform for adult social clubs, independent organizers, venues and private hosts.

This repository is source-available for development and debugging, but the application remains proprietary; public repository visibility does not grant an open-source license.

## 1.0 capabilities

- Multi-tenant organizations: clubs/venues, organizers, and private hosts.
- Included Private Gather subdomains plus customer-owned custom domains.
- Tenant-specific branding, navigation, CMS pages, media, optional marketplace visibility, and a separate platform-admin CMS for the central marketplace text/images.
- Member registration/login, 18+ confirmation, email verification, password recovery, privacy controls, optional TOTP 2FA, and individual/couple profile foundations.
- Event publishing, private/unlisted/member-oriented visibility, recurring-event generation, RSVP approval, questions, waitlists, secure invite tokens for private/invite-only events, favorites/follows data, and private exact-location handling.
- Ticket types, promo/order/payment records, ticket issuance, member tickets, and organizer order management.
- Provider-neutral payment gateway contract. The bundled offline/manual gateway is safe for development and processor-independent deployments; a live payment provider must be integrated before accepting online card payments.
- Direct messaging and platform notifications.
- Mobile-friendly staff/door check-in by ticket token or manual attendee lookup.
- Reports, moderation cases/actions, verification records, platform user/tenant administration, plans, feature entitlements, and analytics/CSV exports.
- Browser installer with automatic environment generation, database schema installation, first-admin creation, installation lock, and removal/disablement of `/install`.
- Administrator ZIP Upgrade Center with version validation, hashes, backups, maintenance mode, migrations, recovery logs, and optional Ed25519 package signing.

## Recommended deployment

For the current Privora Labs test deployment, Private Gather is mounted below the demo host at:

`https://demo.privoralabs.com/private-gather/`

The application also supports installation at a domain or subdomain document root.

The distributed source ZIP does not bundle Composer's `vendor/` directory. Before the browser installer can run, prepare dependencies with `composer install --no-dev --optimize-autoloader` either on the server or on a compatible build machine and include the resulting `vendor/` directory in the uploaded tree. The installer deliberately fails closed when `vendor/autoload.php` is absent rather than creating a half-installed site.

Upload the prepared release contents to the intended application directory. The application entry point protects Laravel internals while transparently serving files from `public/`, so `/public` is not part of the URL.

Open the application URL in a browser. Before installation, the front controller renders the installer inline at the application URL; it does not redirect the browser to a domain-root `/install/` path. After a successful installation, the installer writes a permanent lock, attempts to remove or disable the install directory, and the same application URL loads the installed site.

See `docs/1.0-DEPLOYMENT.md` for server requirements and DNS/domain guidance.

## Server requirements

- PHP 8.3+
- PDO and PDO MySQL
- mbstring
- OpenSSL
- fileinfo
- JSON
- ZipArchive / PHP zip extension
- MySQL 8+ or a compatible recent MariaDB release
- Writable `storage/` and `bootstrap/cache/`
- Composer dependencies installed in `vendor/`

The browser installer verifies critical requirements before modifying the site.

## Existing 0.1.2 installations

Do not overwrite an installed site manually. Use the administrator Upgrade Center. Existing installations should use versioned backend upgrade packages. Private Gather 1.0.7 hardens deployment support for mounting the complete application below a web root, including `/private-gather`, while retaining root and subdomain-root deployment support. Each package declares its source version and is rejected when installed from an unsupported version.

## Domain model

Every tenant may have an included Private Gather hostname such as:

`clubname.demo.privoralabs.com`

A tenant may also attach a customer-controlled hostname such as:

`clubname.com`

or:

`events.clubname.com`

The application resolves the tenant from the HTTP Host header; it does not iframe or redirect custom-domain visitors to the central marketplace. DNS and TLS termination must route the hostname to this application. The bundled provider-neutral/manual domain provisioner performs application-side ownership/health handling but does not itself purchase or automatically provision an external TLS certificate.

## Security notes

This application handles privacy-sensitive account/event information. Run production deployments over HTTPS only, keep `.env` private, use database backups, configure outbound email, choose a production-grade payment provider deliberately, and review local legal/compliance obligations before accepting real users or payments.

## Verification

Run static/project checks:

```bash
php tools/verify-v1-structure.php
php tools/verify-installer.php
php tools/verify-upgrader.php
php tools/test-domain-name.php
```

With Composer dependencies installed:

```bash
php artisan test
php artisan platform:health
```
