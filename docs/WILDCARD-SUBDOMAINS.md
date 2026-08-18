# Private Gather wildcard subdomains

Private Gather creates hosted tenant records such as `club.privategather.com` inside the application. A single wildcard DNS and web-server/TLS mapping makes every one of those records publicly reachable without creating a separate cPanel account or DNS record for each club.

## Required one-time DNS mapping

Create one wildcard record for the configured `PLATFORM_ROOT_DOMAIN`:

```text
*.privategather.com  CNAME  privategather.com
```

An A/AAAA record to the same web-server address can be used instead when the DNS provider does not permit the desired CNAME arrangement.

For the current demo style installation, if `PLATFORM_ROOT_DOMAIN=demo.privoralabs.com`, the equivalent mapping is:

```text
*.demo.privoralabs.com  CNAME  demo.privoralabs.com
```

The wildcard host must resolve to the same server that runs Private Gather.

## Web server

The virtual host serving Private Gather must accept the wildcard hostname in addition to the central hostname.

Apache/LiteSpeed concept:

```apache
ServerName privategather.com
ServerAlias *.privategather.com
```

For the demo hostname:

```apache
ServerName demo.privoralabs.com
ServerAlias *.demo.privoralabs.com
```

The wildcard host must reach the same document root/application directory as the central host. Private Gather's existing `.htaccess` and `private-gather.php` front controller then resolve the incoming Host to the correct tenant.

## HTTPS

Public tenant subdomains require TLS coverage. Use either:

- a wildcard certificate for `*.privategather.com`, or
- an edge/DNS provider that terminates HTTPS for wildcard tenant hosts.

If the root domain is nested, such as `demo.privoralabs.com`, TLS must specifically cover `*.demo.privoralabs.com`; a certificate for only `*.privoralabs.com` does not cover that extra label depth.

## Private Gather environment

Root production install:

```dotenv
APP_URL=https://privategather.com
PLATFORM_ROOT_DOMAIN=privategather.com
PLATFORM_WILDCARD_ENABLED=true
PLATFORM_WILDCARD_TARGET=privategather.com
PLATFORM_TENANT_SCHEME=https
PLATFORM_TENANT_MOUNT_PATH=
```

Subdirectory demo install:

```dotenv
APP_URL=https://demo.privoralabs.com/private-gather
PLATFORM_ROOT_DOMAIN=demo.privoralabs.com
PLATFORM_WILDCARD_ENABLED=true
PLATFORM_WILDCARD_TARGET=demo.privoralabs.com
PLATFORM_TENANT_SCHEME=https
PLATFORM_TENANT_MOUNT_PATH=/private-gather
```

`PLATFORM_TENANT_SCHEME` and `PLATFORM_TENANT_MOUNT_PATH` are optional. If omitted, Private Gather derives them from `APP_URL`, so existing installations remain compatible.

## Result

After the wildcard is live, creating a site named `Example Club` with address `example-club` creates the application tenant and exposes its public address immediately:

```text
https://example-club.privategather.com/
```

On a subdirectory demo configured as above:

```text
https://example-club.demo.privoralabs.com/private-gather/
```

No per-site DNS or hosting-account creation is required after the wildcard mapping is installed.
