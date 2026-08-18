# Initial Test Deployment — demo.privoralabs.com

This build is designed so the application files can live directly in the document root assigned to `demo.privoralabs.com`.

## Expected browser flow

Before install:

`https://demo.privoralabs.com/` → `https://demo.privoralabs.com/install/`

During install the detected defaults should be:

- Application URL: `https://demo.privoralabs.com`
- Platform root domain: `demo.privoralabs.com`

After install:

`https://demo.privoralabs.com/` → application homepage (no `/install` redirect)

`https://demo.privoralabs.com/install/` → unavailable because the installer directory is removed/disabled.

## Hosting prerequisites

- PHP 8.3+
- PDO MySQL
- Mbstring
- OpenSSL
- Fileinfo
- Apache with `.htaccess`/mod_rewrite support for the direct-document-root deployment mode
- MySQL/MariaDB database account
- Writable project root during install (for `.env`)
- Writable `storage/` and `bootstrap/cache/`
- Deployable package containing Composer `vendor/`

## Client platform subdomains later

If `PLATFORM_ROOT_DOMAIN=demo.privoralabs.com`, a client platform subdomain can be generated as:

`client.demo.privoralabs.com`

Those hostnames will require DNS coverage (typically a wildcard or equivalent routing record) before they can resolve publicly.


## Private Gather production identity

The production brand domain is `privategather.com`. The initial certification environment may use `demo.privoralabs.com`; the browser installer auto-detects that hostname so tenant test subdomains can be created beneath the demo root without changing the production brand identity.
