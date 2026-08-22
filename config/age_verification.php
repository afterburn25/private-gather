<?php

return [
    'enabled' => (bool) env('AGE_VERIFICATION_ENABLED', true),
    'driver' => env('AGE_VERIFICATION_DRIVER', 'persona'),
    'minimum_age' => (int) env('AGE_VERIFICATION_MINIMUM_AGE', 21),
    'valid_months' => (int) env('AGE_VERIFICATION_VALID_MONTHS', 12),
    'webhook_tolerance_seconds' => (int) env('AGE_VERIFICATION_WEBHOOK_TOLERANCE', 300),

    'persona' => [
        'api_key' => env('PERSONA_API_KEY'),
        'inquiry_template_id' => env('PERSONA_INQUIRY_TEMPLATE_ID'),
        'environment_id' => env('PERSONA_ENVIRONMENT_ID'),
        'webhook_secret' => env('PERSONA_WEBHOOK_SECRET'),
        'webhook_previous_secret' => env('PERSONA_WEBHOOK_PREVIOUS_SECRET'),
        'api_version' => env('PERSONA_API_VERSION', '2025-10-27'),
        'api_base' => env('PERSONA_API_BASE', 'https://api.withpersona.com/api/v1'),
        'hosted_base' => env('PERSONA_HOSTED_BASE', 'https://inquiry.withpersona.com/verify'),
    ],
];
