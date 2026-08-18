<?php

$rootDomain = strtolower(trim((string) env('PLATFORM_ROOT_DOMAIN', 'privategather.com')));
$appUrl = (string) env('APP_URL', 'https://'.$rootDomain);
$appScheme = strtolower((string) (parse_url($appUrl, PHP_URL_SCHEME) ?: 'https'));
if (! in_array($appScheme, ['http', 'https'], true)) {
    $appScheme = 'https';
}
$appMount = trim((string) (parse_url($appUrl, PHP_URL_PATH) ?: ''), '/');
$tenantScheme = strtolower(trim((string) env('PLATFORM_TENANT_SCHEME', $appScheme)));
if (! in_array($tenantScheme, ['http', 'https'], true)) {
    $tenantScheme = 'https';
}
$tenantMount = trim((string) env('PLATFORM_TENANT_MOUNT_PATH', $appMount), '/');
$wildcardEnabled = filter_var(env('PLATFORM_WILDCARD_ENABLED', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
if ($wildcardEnabled === null) {
    $wildcardEnabled = true;
}

return [
    'terms_version' => env('PLATFORM_TERMS_VERSION', '1.0'),
    'privacy_version' => env('PLATFORM_PRIVACY_VERSION', '1.0'),
    'root_domain' => $rootDomain,

    // Public hosted tenant addresses. PLATFORM_TENANT_MOUNT_PATH defaults to the
    // APP_URL path, so a demo installed at /private-gather automatically creates
    // links such as https://club.example.test/private-gather/ while a root-domain
    // production install creates https://club.privategather.com/.
    'tenant_scheme' => $tenantScheme,
    'tenant_mount_path' => $tenantMount === '' ? '' : '/'.$tenantMount,
    'wildcard_enabled' => $wildcardEnabled,
    'wildcard_domain' => '*.'.$rootDomain,
    'wildcard_target' => strtolower(trim((string) env('PLATFORM_WILDCARD_TARGET', $rootDomain))),

    // Incoming hosts that belong to the central Private Gather marketplace rather than a tenant.
    'central_domains' => array_values(array_unique(array_filter([
        $rootDomain,
        'www.'.$rootDomain,
        parse_url($appUrl, PHP_URL_HOST),
        'localhost',
        '127.0.0.1',
    ]))),

    // Custom-domain DNS records can point here. Infrastructure can later map this
    // to Caddy, Nginx, Cloudflare for SaaS, or another edge provider.
    'domain_target' => strtolower(trim((string) env('PLATFORM_DOMAIN_TARGET', 'domains.'.$rootDomain))),

    'reserved_subdomains' => array_values(array_unique(array_filter(array_map(
        static fn (string $value): string => strtolower(trim($value)),
        explode(',', (string) env('PLATFORM_RESERVED_SUBDOMAINS', 'www,admin,api,mail,support,help,billing,account,login,signup,status,cdn,assets,static,domains,install'))
    )))),

    'domain_verification' => [
        'txt_prefix' => '_privategather-verification',
    ],
];
