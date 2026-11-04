<?php

declare(strict_types=1);

function installer_import_commerce_accounting(PDO $pdo): void
{
    // This importer is reached through the supplemental 1.4 chain from the
    // community installer. Fresh browser installs can therefore reach it before
    // the older product-completion importer. Ensure the two accounting tables
    // owned by this module exist before ALTER operations are attempted. The
    // later CREATE TABLE IF NOT EXISTS statements remain safely idempotent.
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS payouts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 amount_cents INT UNSIGNED NOT NULL,
 currency VARCHAR(3) NOT NULL DEFAULT 'USD',
 status VARCHAR(24) NOT NULL DEFAULT 'requested',
 provider_reference VARCHAR(255) NULL,
 scheduled_at TIMESTAMP NULL,
 paid_at TIMESTAMP NULL,
 metadata JSON NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 KEY payouts_tenant_status_idx(tenant_id,status),
 CONSTRAINT payouts_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS refunds (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 payment_id BIGINT UNSIGNED NULL,
 amount_cents INT UNSIGNED NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'requested',
 provider_reference VARCHAR(255) NULL,
 reason TEXT NULL,
 metadata JSON NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 CONSTRAINT refunds_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT refunds_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT refunds_payment_fk FOREIGN KEY(payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    installer_add_column_if_missing($pdo, 'refunds', 'platform_fee_reversed_cents', 'INT UNSIGNED NOT NULL DEFAULT 0');
    installer_add_column_if_missing($pdo, 'refunds', 'tenant_net_reversed_cents', 'INT UNSIGNED NOT NULL DEFAULT 0');
    installer_add_column_if_missing($pdo, 'refunds', 'requested_by', 'BIGINT UNSIGNED NULL');
    installer_add_column_if_missing($pdo, 'refunds', 'processed_at', 'TIMESTAMP NULL');
    installer_add_index_if_missing($pdo, 'refunds', 'refunds_tenant_status_created_idx', '`tenant_id`,`status`,`created_at`');
    installer_add_foreign_key_if_missing($pdo, 'refunds', 'refunds_requested_by_fk', 'requested_by', 'users', 'id', 'SET NULL');

    installer_add_column_if_missing($pdo, 'payouts', 'requested_by', 'BIGINT UNSIGNED NULL');
    installer_add_column_if_missing($pdo, 'payouts', 'status_reason', 'TEXT NULL');
    installer_add_column_if_missing($pdo, 'payouts', 'requested_at', 'TIMESTAMP NULL');
    installer_add_index_if_missing($pdo, 'payouts', 'payouts_tenant_status_created_idx', '`tenant_id`,`status`,`created_at`');
    installer_add_foreign_key_if_missing($pdo, 'payouts', 'payouts_requested_by_fk', 'requested_by', 'users', 'id', 'SET NULL');

    $migration = '2026_08_23_033000_harden_commerce_accounting';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$migration, $migration]);
}
