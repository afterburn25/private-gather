<?php

declare(strict_types=1);

function installer_import_chapter_operations(PDO $pdo): void
{
    installer_add_column_if_missing($pdo, 'community_groups', 'branding', 'JSON NULL');
    installer_add_column_if_missing($pdo, 'events', 'community_group_id', 'BIGINT UNSIGNED NULL');
    installer_add_index_if_missing($pdo, 'events', 'events_tenant_chapter_status_starts_idx', '`tenant_id`,`community_group_id`,`status`,`starts_at`');
    installer_add_foreign_key_if_missing($pdo, 'events', 'events_chapter_fk', 'community_group_id', 'community_groups', 'id', 'SET NULL');

    $migration = '2026_08_23_026000_expand_chapter_operations';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$migration, $migration]);
}
