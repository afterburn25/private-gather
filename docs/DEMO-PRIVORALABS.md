# Initial Test Deployment — demo.privoralabs.com/private-gather

The Private Gather certification deployment lives in the `/private-gather` subdirectory beneath the `demo.privoralabs.com` document root.

## Expected browser flow

Before installation:

`https://demo.privoralabs.com/private-gather/` → the Private Gather installer is rendered inline while the browser remains under `/private-gather/`.

The application must not redirect the bare application root to the domain-root path `/install/`.

During installation the detected defaults should be:

- Application URL: `https://demo.privoralabs.com/private-gather`
- Platform host/domain: detected from the current deployment configuration
- Application filesystem root: the directory containing `composer.json`, `artisan`, `app/`, `bootstrap/`, `install/`, and `public/`

After installation:

`https://demo.privoralabs.com/private-gather/` → application homepage.

The installer becomes unavailable after successful installation according to the installer lock/disable procedure.

## Hosting prerequisites

- PHP 8.3+
- PDO MySQL
- Mbstring
- OpenSSL
- Fileinfo
- Apache with `.htaccess`/mod_rewrite support
- MySQL/MariaDB database account
- Writable project root during install (for `.env`)
- Writable `storage/` and `bootstrap/cache/`
- Composer dependencies installed in the Private Gather application root

For this certification host the Composer dependency directory belongs at:

`/home/velvetvixftp/demo.privoralabs.com/private-gather/vendor`

and the required autoloader is:

`/home/velvetvixftp/demo.privoralabs.com/private-gather/vendor/autoload.php`

Composer must be run from:

`/home/velvetvixftp/demo.privoralabs.com/private-gather`

## Hosted tenant subdomains

Production hosted tenant URLs use the Private Gather platform domain model, such as:

`client.privategather.com`

Test tenant hostnames beneath `demo.privoralabs.com` require matching DNS and web-server routing before they can resolve publicly.

## Private Gather production identity

The production brand domain is `privategather.com`. The certification environment uses `demo.privoralabs.com/private-gather`; that deployment path does not change the production brand identity.
