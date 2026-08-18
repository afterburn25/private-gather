<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TenantDomain;

final class TenantUrl
{
    public static function to(TenantDomain|string|null $domain, string $path = '/'): string
    {
        $host = $domain instanceof TenantDomain ? $domain->domain : trim((string) $domain);
        if ($host === '') {
            return '';
        }

        $host = DomainName::normalize($host);
        $scheme = strtolower((string) config('platform.tenant_scheme', 'https'));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'https';
        }

        $mount = trim((string) config('platform.tenant_mount_path', ''), '/');
        $relative = '/'.ltrim($path, '/');
        if ($path === '' || $path === '/') {
            $relative = '/';
        }

        $base = $scheme.'://'.$host;
        if ($mount !== '') {
            $base .= '/'.$mount;
        }

        return rtrim($base, '/').$relative;
    }

    public static function wildcardPattern(): string
    {
        return '*. '.config('platform.root_domain');
    }

    public static function wildcardTarget(): string
    {
        return (string) config('platform.wildcard_target', config('platform.root_domain'));
    }
}
