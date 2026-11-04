<?php

declare(strict_types=1);

function installer_import_communications_center(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS marketing_contacts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 name VARCHAR(255) NULL,
 email VARCHAR(255) NULL,
 phone VARCHAR(40) NULL,
 email_opt_in_at TIMESTAMP NULL,
 email_opt_out_at TIMESTAMP NULL,
 sms_opt_in_at TIMESTAMP NULL,
 sms_opt_out_at TIMESTAMP NULL,
 source VARCHAR(48) NOT NULL DEFAULT 'member',
 status VARCHAR(24) NOT NULL DEFAULT 'active',
 last_contacted_at TIMESTAMP NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 KEY marketing_contacts_tenant_status_idx(tenant_id,status),
 KEY marketing_contacts_email_opt_out_idx(email_opt_out_at),
 KEY marketing_contacts_sms_opt_out_idx(sms_opt_out_at),
 CONSTRAINT marketing_contacts_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT marketing_contacts_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS marketing_segments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 name VARCHAR(120) NOT NULL,
 description TEXT NULL,
 definition JSON NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 UNIQUE KEY marketing_segments_tenant_name_unique(tenant_id,name),
 KEY marketing_segments_active_idx(is_active),
 CONSTRAINT marketing_segments_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT marketing_segments_creator_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS marketing_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 name VARCHAR(120) NOT NULL,
 channel VARCHAR(24) NOT NULL DEFAULT 'email',
 message_type VARCHAR(24) NOT NULL DEFAULT 'marketing',
 subject VARCHAR(255) NULL,
 body LONGTEXT NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 KEY marketing_templates_active_idx(is_active),
 KEY marketing_templates_tenant_channel_type_idx(tenant_id,channel,message_type),
 CONSTRAINT marketing_templates_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT marketing_templates_creator_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS marketing_campaigns (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 segment_id BIGINT UNSIGNED NULL,
 template_id BIGINT UNSIGNED NULL,
 name VARCHAR(160) NOT NULL,
 channel VARCHAR(24) NOT NULL DEFAULT 'email',
 message_type VARCHAR(24) NOT NULL DEFAULT 'marketing',
 status VARCHAR(24) NOT NULL DEFAULT 'draft',
 audience JSON NULL,
 subject VARCHAR(255) NULL,
 body LONGTEXT NULL,
 scheduled_at TIMESTAMP NULL,
 processing_started_at TIMESTAMP NULL,
 sent_at TIMESTAMP NULL,
 stats JSON NULL,
 recipient_total INT UNSIGNED NOT NULL DEFAULT 0,
 sent_total INT UNSIGNED NOT NULL DEFAULT 0,
 failed_total INT UNSIGNED NOT NULL DEFAULT 0,
 skipped_total INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 KEY marketing_campaigns_message_type_idx(message_type),
 KEY marketing_campaigns_due_idx(tenant_id,status,scheduled_at),
 CONSTRAINT marketing_campaigns_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT marketing_campaigns_creator_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT marketing_campaigns_segment_fk FOREIGN KEY(segment_id) REFERENCES marketing_segments(id) ON DELETE SET NULL,
 CONSTRAINT marketing_campaigns_template_fk FOREIGN KEY(template_id) REFERENCES marketing_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS marketing_campaign_recipients (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 marketing_contact_id BIGINT UNSIGNED NULL,
 user_id BIGINT UNSIGNED NULL,
 channel VARCHAR(24) NOT NULL DEFAULT 'email',
 address_hash CHAR(64) NOT NULL,
 address VARCHAR(255) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'queued',
 failure_reason VARCHAR(500) NULL,
 sent_at TIMESTAMP NULL,
 delivered_at TIMESTAMP NULL,
 opened_at TIMESTAMP NULL,
 clicked_at TIMESTAMP NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 UNIQUE KEY marketing_campaign_recipient_unique(campaign_id,channel,address_hash),
 KEY marketing_campaign_recipients_tenant_status_idx(tenant_id,status,id),
 CONSTRAINT marketing_campaign_recipients_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT marketing_campaign_recipients_campaign_fk FOREIGN KEY(campaign_id) REFERENCES marketing_campaigns(id) ON DELETE CASCADE,
 CONSTRAINT marketing_campaign_recipients_contact_fk FOREIGN KEY(marketing_contact_id) REFERENCES marketing_contacts(id) ON DELETE SET NULL,
 CONSTRAINT marketing_campaign_recipients_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS marketing_unsubscribe_tokens (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 marketing_contact_id BIGINT UNSIGNED NOT NULL,
 channel VARCHAR(24) NOT NULL DEFAULT 'email',
 token_digest CHAR(64) NOT NULL,
 expires_at TIMESTAMP NOT NULL,
 used_at TIMESTAMP NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 UNIQUE KEY marketing_unsubscribe_token_digest_unique(token_digest),
 KEY marketing_unsubscribe_expires_idx(expires_at),
 KEY marketing_unsubscribe_contact_channel_idx(marketing_contact_id,channel,used_at),
 CONSTRAINT marketing_unsubscribe_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT marketing_unsubscribe_contact_fk FOREIGN KEY(marketing_contact_id) REFERENCES marketing_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    // If a partially newer product-completion schema already created the base
    // tables, reconcile it to the complete Communications Center shape.
    installer_add_column_if_missing($pdo, 'marketing_contacts', 'email_opt_out_at', 'TIMESTAMP NULL');
    installer_add_column_if_missing($pdo, 'marketing_contacts', 'sms_opt_out_at', 'TIMESTAMP NULL');
    installer_add_column_if_missing($pdo, 'marketing_contacts', 'last_contacted_at', 'TIMESTAMP NULL');
    installer_add_index_if_missing($pdo, 'marketing_contacts', 'marketing_contacts_email_opt_out_idx', '`email_opt_out_at`');
    installer_add_index_if_missing($pdo, 'marketing_contacts', 'marketing_contacts_sms_opt_out_idx', '`sms_opt_out_at`');

    installer_add_column_if_missing($pdo, 'marketing_campaigns', 'segment_id', 'BIGINT UNSIGNED NULL');
    installer_add_column_if_missing($pdo, 'marketing_campaigns', 'template_id', 'BIGINT UNSIGNED NULL');
    installer_add_column_if_missing($pdo, 'marketing_campaigns', 'message_type', "VARCHAR(24) NOT NULL DEFAULT 'marketing'");
    installer_add_column_if_missing($pdo, 'marketing_campaigns', 'recipient_total', 'INT UNSIGNED NOT NULL DEFAULT 0');
    installer_add_column_if_missing($pdo, 'marketing_campaigns', 'sent_total', 'INT UNSIGNED NOT NULL DEFAULT 0');
    installer_add_column_if_missing($pdo, 'marketing_campaigns', 'failed_total', 'INT UNSIGNED NOT NULL DEFAULT 0');
    installer_add_column_if_missing($pdo, 'marketing_campaigns', 'skipped_total', 'INT UNSIGNED NOT NULL DEFAULT 0');
    installer_add_column_if_missing($pdo, 'marketing_campaigns', 'processing_started_at', 'TIMESTAMP NULL');
    installer_add_index_if_missing($pdo, 'marketing_campaigns', 'marketing_campaigns_message_type_idx', '`message_type`');
    installer_add_index_if_missing($pdo, 'marketing_campaigns', 'marketing_campaigns_due_idx', '`tenant_id`,`status`,`scheduled_at`');
    installer_add_foreign_key_if_missing($pdo, 'marketing_campaigns', 'marketing_campaigns_segment_fk', 'segment_id', 'marketing_segments', 'id', 'SET NULL');
    installer_add_foreign_key_if_missing($pdo, 'marketing_campaigns', 'marketing_campaigns_template_fk', 'template_id', 'marketing_templates', 'id', 'SET NULL');

    $migration = '2026_08_23_027000_create_communications_center';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$migration, $migration]);
}
