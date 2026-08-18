<?php

return [
    'product' => env('UPGRADE_PRODUCT', 'privategather/private-gather'),
    'legacy_products' => ['privora-labs/multi-venue-social-platform'],
    'max_upload_mb' => (int) env('UPGRADE_MAX_UPLOAD_MB', 256),
    'max_files' => (int) env('UPGRADE_MAX_FILES', 10000),
    'keep_backups' => (int) env('UPGRADE_KEEP_BACKUPS', 5),

    // Release signing is supported but left optional until a stable Private Gather
    // Ed25519 release keypair is provisioned. When enabled, unsigned packages fail closed.
    'require_signature' => (bool) env('UPGRADE_REQUIRE_SIGNATURE', false),
    'ed25519_public_key' => env('UPGRADE_ED25519_PUBLIC_KEY'),

    'storage_path' => storage_path('app/upgrades'),
];
