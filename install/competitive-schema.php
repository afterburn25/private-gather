<?php

declare(strict_types=1);

require_once __DIR__.'/hosted-member-network-schema.php';
require_once __DIR__.'/hosted-discovery-revenue-schema.php';

function installer_schema_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function installer_schema_has_constraint(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
    $stmt->execute([$table, $constraint]);

    return (int) $stmt->fetchColumn() > 0;
}

function installer_import_competitive_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS membership_levels (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        code VARCHAR(80) NOT NULL,
        description TEXT NULL,
        price_cents INT UNSIGNED NOT NULL DEFAULT 0,
        currency VARCHAR(3) NOT NULL DEFAULT 'USD',
        billing_interval VARCHAR(20) NOT NULL DEFAULT 'annual',
        application_required TINYINT(1) NOT NULL DEFAULT 1,
        ticket_discount_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT UNSIGNED NOT NULL DEFAULT 10,
        benefits JSON NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        PRIMARY KEY(id),
        UNIQUE KEY membership_levels_tenant_code_unique(tenant_id,code),
        KEY membership_levels_tenant_active_sort_index(tenant_id,active,sort_order),
        CONSTRAINT membership_levels_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $tenantUserColumns = [
        'membership_level_id' => 'BIGINT UNSIGNED NULL',
        'membership_started_at' => 'TIMESTAMP NULL',
        'membership_expires_at' => 'TIMESTAMP NULL',
        'membership_billing_status' => 'VARCHAR(30) NULL',
    ];
    foreach ($tenantUserColumns as $column => $definition) {
        if (! installer_schema_has_column($pdo, 'tenant_users', $column)) {
            $pdo->exec('ALTER TABLE tenant_users ADD COLUMN `'.$column.'` '.$definition);
        }
    }
    if (! installer_schema_has_constraint($pdo, 'tenant_users', 'tenant_users_membership_level_fk')) {
        $pdo->exec('ALTER TABLE tenant_users ADD CONSTRAINT tenant_users_membership_level_fk FOREIGN KEY(membership_level_id) REFERENCES membership_levels(id) ON DELETE SET NULL');
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS event_addons (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            description TEXT NULL,
            type VARCHAR(30) NOT NULL DEFAULT 'other',
            price_cents INT UNSIGNED NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            quantity INT UNSIGNED NULL,
            max_per_order INT UNSIGNED NOT NULL DEFAULT 10,
            member_only TINYINT(1) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 10,
            metadata JSON NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id), KEY event_addons_event_active_sort_index(event_id,active,sort_order),
            CONSTRAINT event_addons_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS order_addon_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            event_addon_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            unit_price_cents INT UNSIGNED NOT NULL,
            total_cents INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id), UNIQUE KEY order_addon_items_order_addon_unique(order_id,event_addon_id),
            CONSTRAINT order_addon_items_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT order_addon_items_addon_fk FOREIGN KEY(event_addon_id) REFERENCES event_addons(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS promoters (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(255) NULL,
            code VARCHAR(80) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            commission_type VARCHAR(20) NOT NULL DEFAULT 'percent',
            commission_value INT UNSIGNED NOT NULL DEFAULT 0,
            metadata JSON NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id), UNIQUE KEY promoters_tenant_code_unique(tenant_id,code), KEY promoters_tenant_status_index(tenant_id,status),
            CONSTRAINT promoters_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT promoters_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS promoter_attributions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            promoter_id BIGINT UNSIGNED NOT NULL,
            event_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            commission_cents INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            paid_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id), UNIQUE KEY promoter_attributions_order_unique(order_id), KEY promoter_attributions_tenant_promoter_status_index(tenant_id,promoter_id,status),
            CONSTRAINT promoter_attributions_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT promoter_attributions_promoter_fk FOREIGN KEY(promoter_id) REFERENCES promoters(id) ON DELETE RESTRICT,
            CONSTRAINT promoter_attributions_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            CONSTRAINT promoter_attributions_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS marketing_contacts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(40) NULL,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            source VARCHAR(40) NOT NULL DEFAULT 'manual',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            email_opt_in TINYINT(1) NOT NULL DEFAULT 0,
            sms_opt_in TINYINT(1) NOT NULL DEFAULT 0,
            email_consented_at TIMESTAMP NULL,
            sms_consented_at TIMESTAMP NULL,
            unsubscribed_at TIMESTAMP NULL,
            metadata JSON NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id), KEY marketing_contacts_tenant_email_index(tenant_id,email), KEY marketing_contacts_tenant_phone_index(tenant_id,phone), KEY marketing_contacts_status_index(status),
            CONSTRAINT marketing_contacts_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT marketing_contacts_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS marketing_lists (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            description TEXT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id), UNIQUE KEY marketing_lists_tenant_name_unique(tenant_id,name),
            CONSTRAINT marketing_lists_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS marketing_list_contacts (
            marketing_list_id BIGINT UNSIGNED NOT NULL,
            marketing_contact_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL,
            PRIMARY KEY(marketing_list_id,marketing_contact_id),
            CONSTRAINT marketing_list_contacts_list_fk FOREIGN KEY(marketing_list_id) REFERENCES marketing_lists(id) ON DELETE CASCADE,
            CONSTRAINT marketing_list_contacts_contact_fk FOREIGN KEY(marketing_contact_id) REFERENCES marketing_contacts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS marketing_campaigns (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            marketing_list_id BIGINT UNSIGNED NULL,
            name VARCHAR(160) NOT NULL,
            channel VARCHAR(20) NOT NULL DEFAULT 'email',
            subject VARCHAR(255) NULL,
            body TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            scheduled_at TIMESTAMP NULL,
            sent_at TIMESTAMP NULL,
            recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
            delivered_count INT UNSIGNED NOT NULL DEFAULT 0,
            open_count INT UNSIGNED NOT NULL DEFAULT 0,
            click_count INT UNSIGNED NOT NULL DEFAULT 0,
            metadata JSON NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id), KEY marketing_campaigns_tenant_channel_status_index(tenant_id,channel,status), KEY marketing_campaigns_status_index(status),
            CONSTRAINT marketing_campaigns_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT marketing_campaigns_list_fk FOREIGN KEY(marketing_list_id) REFERENCES marketing_lists(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $selectedEdition = strtolower(trim((string) ($_POST['edition'] ?? 'hosted')));
    if ($selectedEdition === 'hosted') {
        installer_import_hosted_member_network($pdo);
        installer_import_hosted_discovery_revenue($pdo);

        $migration = '2026_08_19_220000_create_hosted_member_network';
        $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
        $stmt->execute([$migration, $migration]);
    }
}
