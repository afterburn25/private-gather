# Browser Installation

## Supported placement

The package can be extracted directly into the document root of a normal domain or subdomain. Examples:

- `example.com` document root
- `demo.privoralabs.com` document root

Do not append `/public` to the URL. The root `.htaccess` and `index.php` safely front the Laravel `public/` directory while blocking direct HTTP access to application internals.

## Before upload

The production/deployable package must contain Composer dependencies in `vendor/`. A source-only package can be prepared on a build machine with:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

## Browser flow

1. Extract the package into the selected domain/subdomain document root.
2. Browse to the domain, e.g. `https://demo.privoralabs.com`.
3. If not installed, the root front controller renders the installer inline at the URL you opened. It does not redirect the browser to `/install/`.
4. The installer verifies PHP/extensions/writable directories and the Laravel vendor runtime.
5. Enter the site/domain, database, and first administrator settings.
6. The installer can create the MySQL/MariaDB database when the supplied account has CREATE DATABASE privilege; otherwise point it at a database created in the hosting panel.
7. The installer injects the complete initial schema and administrator account.
8. It generates `.env`, `APP_KEY`, an install receipt, and `storage/app/installed.lock`.
9. It removes `/install` automatically. If filesystem policy blocks deletion, it attempts to rename the folder out of the `/install` path and the installation lock prevents reuse.
10. Subsequent requests boot the application directly while `APP_INSTALLED=true` or the lock exists.

## demo.privoralabs.com initial test

Recommended initial installer values:

- Application URL: `https://demo.privoralabs.com`
- Platform root domain: `demo.privoralabs.com`
- Domain target: generated as `domains.demo.privoralabs.com`

For platform subdomains such as `club.demo.privoralabs.com`, DNS will later need a wildcard/appropriate record pointing those hostnames to the application infrastructure.


## Private Gather production identity

The production brand domain is `privategather.com`. The initial certification environment may use `demo.privoralabs.com`; the browser installer auto-detects that hostname so tenant test subdomains can be created beneath the demo root without changing the production brand identity.

## Subdirectory installation

Private Gather 1.0.8 can run below the server document root. For example, when the project directory is `/private-gather`, opening `https://demo.privoralabs.com/private-gather/` keeps that URL in the browser and renders the installer inline. The installer writes `APP_URL=https://demo.privoralabs.com/private-gather` and all first-run assets/links retain that mount path.


## Browser-cache note for previous test builds

Older test builds used redirects during first-run installation. A browser that previously cached one of those redirects can continue jumping from `/private-gather/` to `/install/` even after the server has the corrected 1.0.8 files. If `/private-gather/index.php` and `/private-gather/private-gather.php` both stay inside the application but the bare `/private-gather/` path still jumps to `/install/`, test in a clean browser profile or clear site data before changing server routing.
