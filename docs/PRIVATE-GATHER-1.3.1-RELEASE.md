# Private Gather 1.3.1 — Dedicated Installers & Automatic Runtime Repair

Private Gather 1.3.1 is a fresh-install stabilization release for the certified 1.3 product-completion line.

## Automatic runtime permissions

The browser installer now repairs Laravel runtime directories before requirements are evaluated. It creates missing paths, applies mode `0775`, and verifies real write access with a temporary write probe.

Covered paths include:

- `storage/`
- `storage/app/`
- `storage/app/private/`
- `storage/app/public/`
- `storage/framework/cache/data/`
- `storage/framework/sessions/`
- `storage/framework/views/`
- `storage/logs/`
- `bootstrap/cache/`

The installer does not use an unsafe `0777` fallback. If a host has uploaded the application under ownership that PHP cannot modify, installation fails closed and identifies the unresolved requirements.

## Dedicated Hosted base

The Hosted package is now explicitly a Hosted-platform base.

- No edition selector is displayed.
- No Self-Hosted organization setup is displayed.
- Installation is locked to `PRIVATE_GATHER_EDITION=hosted`.
- The packaged Hosted `.env.example` omits `SELF_HOSTED_*` configuration options.
- The installer provisions the multi-organization platform administrator and platform/domain configuration only.

## Dedicated Self-Hosted base

The Self-Hosted package now receives its own installer as `install/index.php` during packaging.

- No Hosted-platform option or edition selector is displayed.
- Installation is locked to `PRIVATE_GATHER_EDITION=self_hosted`.
- Organization name/type, site visibility, and member-registration policy are first-class installation fields.
- The initial organization and owner membership are provisioned automatically.
- Hosted wildcard-domain targets are removed from the packaged Self-Hosted environment preset and wildcard mode is disabled.

The maintained application Core remains shared so security fixes and compatible product improvements can be developed once, while the deployed installation bases and installation experience remain clearly separated.

## Installer self-removal

After a successful install, Private Gather writes the permanent installation lock first and then automatically removes installer code.

The cleanup sequence:

1. writes `.env` with `APP_INSTALLED=true`;
2. writes `storage/app/installed.lock`;
3. attempts immediate recursive deletion of `/install`;
4. if hosting restrictions force the existing safe rename fallback, a shutdown cleanup pass makes the tree removable and deletes the fallback directory too;
5. confirms that neither `/install` nor a disabled installer fallback remains.

This behavior is covered by regression tests that exercise restrictive file/directory modes.

## Package identity

Edition package metadata schema 2 records:

- edition;
- installation base (`hosted-platform` or `self-hosted-organization`);
- dedicated-installer status;
- exact source commit;
- shared-Core identity.

Package verification rejects cross-edition installer fields, cross-edition environment options, protected deployment state, unexpected runtime logs, missing installer/runtime files, and shared-Core drift.

## Compatibility

- PHP 8.3 minimum remains supported.
- Existing 1.3 application behavior and database schema are preserved.
- Historical Private Gather 1.2.x source remains a separate lineage.
