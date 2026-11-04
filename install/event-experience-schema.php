<?php

declare(strict_types=1);

require_once __DIR__.'/event-operations-schema.php';

function installer_import_event_experience(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS ticket_transfers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 ticket_id BIGINT UNSIGNED NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL,
 event_id BIGINT UNSIGNED NOT NULL,
 from_user_id BIGINT UNSIGNED NOT NULL,
 to_user_id BIGINT UNSIGNED NOT NULL,
 initiated_by BIGINT UNSIGNED NULL,
 token_digest CHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 reason VARCHAR(500) NULL,
 expires_at TIMESTAMP NOT NULL,
 accepted_at TIMESTAMP NULL,
 revoked_at TIMESTAMP NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 UNIQUE KEY ticket_transfers_token_digest_unique(token_digest),
 KEY ticket_transfers_status_idx(status),
 KEY ticket_transfers_expires_idx(expires_at),
 KEY ticket_transfers_ticket_status_idx(ticket_id,status),
 KEY ticket_transfers_recipient_status_idx(to_user_id,status,expires_at),
 CONSTRAINT ticket_transfers_ticket_fk FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
 CONSTRAINT ticket_transfers_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT ticket_transfers_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
 CONSTRAINT ticket_transfers_from_user_fk FOREIGN KEY(from_user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT ticket_transfers_to_user_fk FOREIGN KEY(to_user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT ticket_transfers_initiator_fk FOREIGN KEY(initiated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $migration = '2026_08_23_028000_add_ticket_transfer_audit';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$migration, $migration]);

    installer_import_event_operations($pdo);
}
