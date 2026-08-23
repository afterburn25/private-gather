# Browser Installation

Private Gather is distributed as two separate deployable bases:

- **Private Gather Hosted** — multi-organization platform/marketplace with tenant sites and platform administration.
- **Private Gather Self-Hosted** — single-organization installation for one club, organizer, or private host.

There is no edition selector in either installer. Download and deploy the base you intend to run.

## Supported placement

Either package can be extracted directly into the document root of a normal domain or subdomain, or into a supported subdirectory mount. Do not append `/public` to the URL. The root `.htaccess` and `index.php` safely front the Laravel `public/` directory while blocking direct HTTP access to application internals.

## Before upload

Certified deployment packages include Composer dependencies in `vendor/`. A source-only checkout can be prepared on a build machine with:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

## Automatic runtime directory repair

Before the requirements screen is evaluated, the browser installer automatically creates and repairs Laravel runtime directories to mode `0775`, including:

- `storage/`
- `storage/app/`
- `storage/app/private/`
- `storage/app/public/`
- `storage/framework/cache/data/`
- `storage/framework/sessions/`
- `storage/framework/views/`
- `storage/logs/`
- `bootstrap/cache/`

The installer then performs a real write probe. A normal upload with conservative directory permissions therefore does not require the user to manually chmod these folders first.

If a directory still reports **FAIL** after automatic repair, the PHP/web-server account does not own the uploaded path and is not permitted by the operating system/hosting account to change it. The installer fails closed rather than using unsafe `0777` permissions.

## Hosted browser flow

1. Extract **Private-Gather-Hosted** into the selected document root or supported subdirectory.
2. Browse to the application URL. The installer renders inline at that URL and does not redirect to `/install/`.
3. Runtime directories are automatically provisioned/repaired.
4. Enter the Hosted platform name, application URL, platform root domain, database, timezone, and first platform administrator.
5. The installer creates/imports the complete schema, writes `.env` with `PRIVATE_GATHER_EDITION=hosted`, creates the administrator, writes the install receipt and lock, clears bootstrap cache artifacts, and disables/removes the installer.

The Hosted installer contains no Self-Hosted option or Self-Hosted organization fields.

## Self-Hosted browser flow

1. Extract **Private-Gather-Self-Hosted** into the customer-owned site root or supported subdirectory.
2. Browse to the application URL. Its dedicated Self-Hosted installer renders inline.
3. Runtime directories are automatically provisioned/repaired.
4. Enter the site/domain, organization name/type, visibility policy, registration policy, database, timezone, and first organization owner.
5. The installer creates/imports the complete schema, creates the single Self-Hosted organization and owner membership, writes `.env` with `PRIVATE_GATHER_EDITION=self_hosted`, writes the install receipt and lock, clears bootstrap cache artifacts, and disables/removes the installer.

The Self-Hosted installer contains no Hosted-platform option or edition selector.

## Database creation

Both installers can create the MySQL/MariaDB database when the supplied account has `CREATE DATABASE` privilege. Otherwise, create the database in the hosting control panel and provide its existing name and credentials.

## Installation lock

After successful installation, `.env` contains `APP_INSTALLED=true` and `storage/app/installed.lock` is written. The installer removes `/install` automatically when filesystem policy permits. If deletion is blocked, it attempts to rename the directory out of the `/install` path; the permanent installation lock prevents reuse either way.

## Production identity

The production brand domain is `privategather.com`. Test/staging environments may use another hostname. Hosted tenant DNS/wildcard configuration is an infrastructure step after installation and is not required by the Self-Hosted base.

## Subdirectory installation

Private Gather 1.3.x supports running below the server document root. For example, when the project directory is `/private-gather`, opening `https://demo.example.com/private-gather/` keeps that URL in the browser and renders the installer inline. The installer writes the full mounted `APP_URL`, and first-run assets/links retain the same mount path.

## Browser-cache note for previous builds

Very old test builds used redirects during first-run installation. A browser that cached one of those redirects can continue jumping to `/install/` even after the server has corrected files. If the application routing is correct but the browser still redirects, test in a clean browser profile or clear site data before changing server routing.
