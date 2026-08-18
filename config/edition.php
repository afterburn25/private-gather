<?php

$edition = strtolower(trim((string) env('PRIVATE_GATHER_EDITION', 'hosted')));
$edition = match ($edition) {
    'self-hosted', 'selfhosted', 'single_site', 'single-site' => 'self_hosted',
    'hosted', 'self_hosted' => $edition,
    default => 'hosted',
};

return [
    'name' => $edition,

    'self_hosted' => [
        // The installer writes the one organization created for a Self-Hosted
        // installation here. A null value falls back to the first active tenant
        // so existing installations can be converted deliberately if needed.
        'tenant_id' => env('SELF_HOSTED_TENANT_ID'),

        // private: anonymous visitors are sent to login before any site/event
        // content is rendered. public: normal tenant visibility rules apply.
        'visibility' => strtolower(trim((string) env('SELF_HOSTED_VISIBILITY', 'private'))),

        // open: account becomes active immediately.
        // approval: account is created pending administrator approval.
        // disabled: public member registration is unavailable.
        'registration' => strtolower(trim((string) env('SELF_HOSTED_REGISTRATION', 'approval'))),
    ],
];
