<?php
$versionFile = base_path('VERSION');
return [
    'product' => 'privategather/private-gather',
    'version' => is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '1.0.8',
    'name' => 'Private Gather',
    'release_name' => 'Security, Tenant Isolation & Transactional Workflow Hardening',
    'website' => 'https://privategather.com',
    'logo' => '/assets/branding/private-gather-logo.png',
];
