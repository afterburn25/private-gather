<?php

declare(strict_types=1);

/**
 * Create the Private Gather community and lifestyle membership tables during
 * browser-based fresh installs.
 *
 * Existing installations receive these same tables through the matching Laravel
 * migrations. Keeping this step separate from the mature base schema reduces the
 * risk of destabilizing retry-safe legacy installer SQL.
 */
function installer_import_private_community(PDO $pdo): void
{
    $statements = [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS community_posts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 body TEXT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'active',
 is_pinned TINYINT(1) NOT NULL DEFAULT 0,
 edited_at TIMESTAMP NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 KEY community_posts_status_index(status),
 KEY community_posts_pinned_index(is_pinned),
 KEY community_posts_tenant_pinned_created_idx(tenant_id,is_pinned,created_at),
 CONSTRAINT community_posts_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT community_posts_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS community_comments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 post_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 body TEXT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'active',
 edited_at TIMESTAMP NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 KEY community_comments_status_index(status),
 KEY community_comments_tenant_post_created_idx(tenant_id,post_id,created_at),
 CONSTRAINT community_comments_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT community_comments_post_fk FOREIGN KEY(post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
 CONSTRAINT community_comments_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS community_reactions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 post_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 reaction VARCHAR(24) NOT NULL DEFAULT 'like',
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 UNIQUE KEY community_reactions_post_user_unique(post_id,user_id),
 KEY community_reactions_tenant_reaction_index(tenant_id,reaction),
 CONSTRAINT community_reactions_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT community_reactions_post_fk FOREIGN KEY(post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
 CONSTRAINT community_reactions_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS community_chat_messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 body TEXT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 KEY community_chat_status_index(status),
 KEY community_chat_tenant_id_index(tenant_id,id),
 KEY community_chat_tenant_created_index(tenant_id,created_at),
 CONSTRAINT community_chat_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT community_chat_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS tenant_membership_applications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 profile_type VARCHAR(24) NOT NULL DEFAULT 'individual',
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 referred_by VARCHAR(255) NULL,
 introduction TEXT NULL,
 answers JSON NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at TIMESTAMP NULL,
 decision_note TEXT NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 KEY tenant_membership_applications_profile_type_index(profile_type),
 KEY tenant_membership_applications_status_index(status),
 KEY tenant_membership_applications_tenant_status_created_idx(tenant_id,status,created_at),
 KEY tenant_membership_applications_tenant_user_status_idx(tenant_id,user_id,status),
 KEY tenant_membership_applications_user_created_idx(user_id,created_at),
 CONSTRAINT tenant_membership_applications_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT tenant_membership_applications_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT tenant_membership_applications_reviewer_fk FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ];

    foreach ($statements as $number => $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Private community schema import failed at statement '.($number + 1).': '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    // ticket_types is created by install/schema.sql. Add the lifestyle-specific
    // admission fields only when absent so browser installs remain retry-safe.
    installer_add_column_if_missing($pdo, 'ticket_types', 'profile_eligibility', "VARCHAR(24) NOT NULL DEFAULT 'any'");
    installer_add_column_if_missing($pdo, 'ticket_types', 'membership_required', 'TINYINT(1) NOT NULL DEFAULT 0');
    installer_add_column_if_missing($pdo, 'ticket_types', 'approval_required', 'TINYINT(1) NOT NULL DEFAULT 0');

    $registeredMigrations = [
        '2026_08_22_230000_create_tenant_membership_applications',
        '2026_08_22_231000_add_lifestyle_ticket_eligibility',
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)'
    );
    foreach ($registeredMigrations as $migration) {
        $stmt->execute([$migration, $migration]);
    }
}

function installer_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (! preg_match('/^[A-Za-z0-9_]+$/', $table) || ! preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        throw new InvalidArgumentException('Unsafe installer schema identifier.');
    }

    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $check->execute([$table, $column]);

    if ((int) $check->fetchColumn() > 0) {
        return;
    }

    $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
}
