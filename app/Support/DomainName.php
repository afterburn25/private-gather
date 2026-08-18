<?php

namespace App\Support;

use InvalidArgumentException;

final class DomainName
{
    public static function normalize(string $value): string
    {
        $value = trim(strtolower($value));

        if ($value === '') {
            throw new InvalidArgumentException('Domain name cannot be empty.');
        }

        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);
            $value = is_string($host) ? $host : '';
        } else {
            $value = preg_replace('#/.*$#', '', $value) ?? '';
            $value = preg_replace('/:\\d+$/', '', $value) ?? '';
        }

        $value = rtrim($value, '.');

        if ($value === '' || strlen($value) > 253) {
            throw new InvalidArgumentException('Invalid domain name.');
        }

        if (! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException('Invalid domain name.');
        }

        return $value;
    }

    public static function platformSubdomain(string $subdomain, string $rootDomain): string
    {
        $subdomain = strtolower(trim($subdomain));

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain)) {
            throw new InvalidArgumentException('Subdomain may contain only letters, numbers, and interior hyphens.');
        }

        return $subdomain.'.'.self::normalize($rootDomain);
    }
}
