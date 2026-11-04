<?php

declare(strict_types=1);

require_once __DIR__.'/chapter-operations-schema.php';
require_once __DIR__.'/communications-schema.php';
require_once __DIR__.'/event-experience-schema.php';

function installer_import_membership_lifecycle(PDO $pdo): void
{
    installer_add_column_if_missing($pdo, 'tenant_membership_levels', 'grace_days', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0');
    installer_add_column_if_missing($pdo, 'tenant_membership_terms', 'renewal_reminder_sent_at', 'TIMESTAMP NULL');
    installer_add_index_if_missing($pdo, 'tenant_membership_terms', 'membership_terms_lifecycle_idx', '`status`,`current_period_ends_at`');

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS tenant_membership_invitations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 membership_level_id BIGINT UNSIGNED NULL,
 invited_by BIGINT UNSIGNED NULL,
 email VARCHAR(255) NOT NULL,
 token_digest CHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 message TEXT NULL,
 expires_at TIMESTAMP NOT NULL,
 accepted_by BIGINT UNSIGNED NULL,
 accepted_at TIMESTAMP NULL,
 revoked_at TIMESTAMP NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 UNIQUE KEY membership_invitation_token_digest_unique(token_digest),
 KEY membership_invitation_status_idx(status),
 KEY membership_invitation_expires_idx(expires_at),
 KEY membership_invite_tenant_email_status_idx(tenant_id,email,status),
 CONSTRAINT membership_invitation_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT membership_invitation_level_fk FOREIGN KEY(membership_level_id) REFERENCES tenant_membership_levels(id) ON DELETE SET NULL,
 CONSTRAINT membership_invitation_inviter_fk FOREIGN KEY(invited_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT membership_invitation_acceptor_fk FOREIGN KEY(accepted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $migration = '2026_08_23_025000_add_membership_lifecycle_and_invitations';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$migration, $migration]);

    installer_import_chapter_operations($pdo);
    installer_import_communications_center($pdo);

    // Event Experience owns registration of 2026_08_23_028000_add_ticket_transfer_audit,
    // chains Event Operations for 2026_08_23_029000_add_event_guest_policy_and_scoped_staff,
    // and Event Operations chains Community Enhancements for
    // 2026_08_23_030000_add_community_mentions_and_event_threads.
    installer_import_event_experience($pdo);
}
