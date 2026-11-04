<?php

declare(strict_types=1);

require_once __DIR__.'/community-enhancements-schema.php';

function installer_import_event_operations(PDO $pdo): void
{
    installer_add_column_if_missing($pdo, 'events', 'guest_policy', "VARCHAR(32) NOT NULL DEFAULT 'allowed'");
    installer_add_column_if_missing($pdo, 'events', 'max_party_size', 'TINYINT UNSIGNED NOT NULL DEFAULT 10');

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS event_staff_assignments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 event_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 assigned_by BIGINT UNSIGNED NULL,
 capabilities JSON NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 UNIQUE KEY event_staff_assignments_event_user_unique(event_id,user_id),
 KEY event_staff_assignments_tenant_status_idx(tenant_id,status),
 KEY event_staff_assignments_user_status_idx(user_id,status),
 CONSTRAINT event_staff_assignments_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT event_staff_assignments_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
 CONSTRAINT event_staff_assignments_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT event_staff_assignments_assigner_fk FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $migration = '2026_08_23_029000_add_event_guest_policy_and_scoped_staff';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$migration, $migration]);

    installer_import_community_enhancements($pdo);
}
