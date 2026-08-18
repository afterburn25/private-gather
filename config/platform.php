<?php

$rootDomain = strtolower(trim((string) env('PLATFORM_ROOT_DOMAIN', 'privategather.com')));

return [
    'terms_version' => env('PLATFORM_TERMS_VERSION', '1.0'),
    'privacy_version' => env('PLATFORM_PRIVACY_VERSION', '1.0'),
    'root_domain' => $rootDomain,

    // Incoming hosts that belong to the central Private Gather marketplace rather than a tenant.
    'central_domains' => array_values(array_unique(array_filter([
        $rootDomain,
        'www.'.$rootDomain,
        parse_url((string) env('APP_URL', ''), PHP_URL_HOST),
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
