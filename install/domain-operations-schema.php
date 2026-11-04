<?php

declare(strict_types=1);

require_once __DIR__.'/commerce-accounting-schema.php';

function installer_import_domain_operations(PDO $pdo): void
{
    installer_add_column_if_missing($pdo, 'tenant_domains', 'ssl_expires_at', 'TIMESTAMP NULL');
    installer_add_column_if_missing($pdo, 'tenant_domains', 'provision_last_attempt_at', 'TIMESTAMP NULL');
    installer_add_column_if_missing($pdo, 'tenant_domains', 'provision_attempts', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0');
    installer_add_index_if_missing($pdo, 'tenant_domains', 'tenant_domains_tenant_ssl_expiry_idx', '`tenant_id`,`ssl_status`,`ssl_expires_at`');

    $migration = '2026_08_23_032000_expand_domain_operations';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$migration, $migration]);

    // Chained migration registry: 2026_08_23_033000_harden_commerce_accounting
    installer_import_commerce_accounting($pdo);
}
