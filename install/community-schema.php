<?php

declare(strict_types=1);

/**
 * Create the Private Gather community tables during browser-based fresh installs.
 *
 * Existing installations receive these same tables through the matching Laravel
 * migration. Keeping this step separate from the mature base schema reduces the
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
}
