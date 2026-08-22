<?php
$versionFile = base_path('VERSION');
return [
    'product' => 'privategather/private-gather',
    'version' => is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '1.2.0',
    'name' => 'Private Gather',
    'release_name' => 'Verified Identity & Global Membership Safety',
    'website' => 'https://privategather.com',
    'logo' => '/assets/branding/private-gather-crest-gold.webp',
];
