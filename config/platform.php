<?php

$rootDomain = strtolower(trim((string) env('PLATFORM_ROOT_DOMAIN', 'privategather.com')));
$appUrl = (string) env('APP_URL', 'https://'.$rootDomain);
$appScheme = strtolower((string) (parse_url($appUrl, PHP_URL_SCHEME) ?: 'https'));
if (! in_array($appScheme, ['http', 'https'], true)) $appScheme = 'https';
$appMount = trim((string) (parse_url($appUrl, PHP_URL_PATH) ?: ''), '/');
$tenantScheme = strtolower(trim((string) env('PLATFORM_TENANT_SCHEME', $appScheme)));
if (! in_array($tenantScheme, ['http', 'https'], true)) $tenantScheme = 'https';
$tenantMount = trim((string) env('PLATFORM_TENANT_MOUNT_PATH', $appMount), '/');
$wildcardEnabled = filter_var(env('PLATFORM_WILDCARD_ENABLED', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
if ($wildcardEnabled === null) $wildcardEnabled = true;
$showcaseContent = filter_var(env('PRIVATE_GATHER_SHOWCASE_CONTENT', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
if ($showcaseContent === null) $showcaseContent = false;
$marketplaceFeeBps = max(0, min(5000, (int) env('PLATFORM_MARKETPLACE_FEE_BPS', 1000)));

return [
    'terms_version' => env('PLATFORM_TERMS_VERSION', '1.0'),
    'privacy_version' => env('PLATFORM_PRIVACY_VERSION', '1.0'),
    'root_domain' => $rootDomain,
    'showcase_content' => $showcaseContent,
    'marketplace_fee_bps' => $marketplaceFeeBps,
    'tenant_scheme' => $tenantScheme,
    'tenant_mount_path' => $tenantMount === '' ? '' : '/'.$tenantMount,
    'wildcard_enabled' => $wildcardEnabled,
    'wildcard_domain' => '*.'.$rootDomain,
    'wildcard_target' => strtolower(trim((string) env('PLATFORM_WILDCARD_TARGET', $rootDomain))),
    'central_domains' => array_values(array_unique(array_filter([$rootDomain, 'www.'.$rootDomain, parse_url($appUrl, PHP_URL_HOST), 'localhost', '127.0.0.1']))),
    'domain_target' => strtolower(trim((string) env('PLATFORM_DOMAIN_TARGET', 'domains.'.$rootDomain))),
    'reserved_subdomains' => array_values(array_unique(array_filter(array_map(static fn (string $value): string => strtolower(trim($value)), explode(',', (string) env('PLATFORM_RESERVED_SUBDOMAINS', 'www,admin,api,mail,support,help,billing,account,login,signup,status,cdn,assets,static,domains,install')))))),
    'domain_verification' => ['txt_prefix' => '_privategather-verification'],
];
