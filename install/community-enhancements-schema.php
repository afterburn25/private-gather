<?php

declare(strict_types=1);

require_once __DIR__.'/website-builder-schema.php';

function installer_import_community_enhancements(PDO $pdo): void
{
    installer_add_column_if_missing($pdo, 'community_posts', 'event_id', 'BIGINT UNSIGNED NULL');
    installer_add_index_if_missing($pdo, 'community_posts', 'community_posts_tenant_event_status_idx', '`tenant_id`,`event_id`,`status`');
    installer_add_foreign_key_if_missing($pdo, 'community_posts', 'community_posts_event_fk', 'event_id', 'events', 'id', 'SET NULL');

    installer_add_column_if_missing($pdo, 'network_community_posts', 'event_id', 'BIGINT UNSIGNED NULL');
    installer_add_index_if_missing($pdo, 'network_community_posts', 'network_community_posts_event_status_idx', '`event_id`,`status`');
    installer_add_foreign_key_if_missing($pdo, 'network_community_posts', 'network_community_posts_event_fk', 'event_id', 'events', 'id', 'SET NULL');

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS community_mentions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 scope VARCHAR(16) NOT NULL,
 tenant_id BIGINT UNSIGNED NULL,
 source_type VARCHAR(32) NOT NULL,
 source_id BIGINT UNSIGNED NOT NULL,
 author_id BIGINT UNSIGNED NULL,
 mentioned_user_id BIGINT UNSIGNED NOT NULL,
 notified_at TIMESTAMP NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 UNIQUE KEY community_mentions_source_user_unique(scope,source_type,source_id,mentioned_user_id),
 KEY community_mentions_user_created_idx(mentioned_user_id,created_at),
 KEY community_mentions_tenant_created_idx(tenant_id,created_at),
 CONSTRAINT community_mentions_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT community_mentions_author_fk FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT community_mentions_user_fk FOREIGN KEY(mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $migration = '2026_08_23_030000_add_community_mentions_and_event_threads';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$migration, $migration]);

    installer_import_website_builder($pdo);
}
