<?php

$fromName = trim((string) env('MAIL_FROM_NAME', ''));
if ($fromName === '' || in_array($fromName, ['Private Gather', 'Private Gather'], true)) {
    $fromName = 'Private Gather';
}

return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'smtp' => ['transport' => 'smtp', 'scheme' => env('MAIL_SCHEME'), 'url' => env('MAIL_URL'), 'host' => env('MAIL_HOST', '127.0.0.1'), 'port' => env('MAIL_PORT', 2525), 'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'), 'timeout' => null, 'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'https://privategather.com'), PHP_URL_HOST))],
        'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')],
        'array' => ['transport' => 'array'],
    ],
    'from' => ['address' => env('MAIL_FROM_ADDRESS', 'noreply@privategather.com'), 'name' => $fromName],
];
