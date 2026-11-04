<?php

return [
    'domain_registrar' => [
        'name' => env('DOMAIN_REGISTRAR_NAME', 'manual'),
        'endpoint' => env('DOMAIN_REGISTRAR_ENDPOINT'),
        'token' => env('DOMAIN_REGISTRAR_TOKEN'),
    ],
    'webpush' => [
        'public_key' => env('WEBPUSH_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_PRIVATE_KEY'),
        'subject' => env('WEBPUSH_SUBJECT', env('MAIL_FROM_ADDRESS')),
    ],
    'observability' => [
        'dsn' => env('OBSERVABILITY_DSN'),
        'environment' => env('OBSERVABILITY_ENVIRONMENT', env('APP_ENV', 'production')),
    ],
    'cdn' => [
        'url' => env('CDN_URL'),
    ],
];
