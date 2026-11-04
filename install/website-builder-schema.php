<?php

declare(strict_types=1);

require_once __DIR__.'/domain-operations-schema.php';

function installer_import_website_builder(PDO $pdo): void
{
    installer_add_column_if_missing($pdo, 'cms_pages', 'social_title', 'VARCHAR(190) NULL');
    installer_add_column_if_missing($pdo, 'cms_pages', 'social_description', 'TEXT NULL');
    installer_add_column_if_missing($pdo, 'cms_pages', 'social_image_url', 'VARCHAR(1000) NULL');

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cms_reusable_blocks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 name VARCHAR(120) NOT NULL,
 type VARCHAR(48) NOT NULL,
 content JSON NULL,
 settings JSON NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 KEY cms_reusable_blocks_type_idx(type),
 KEY cms_reusable_blocks_status_idx(status),
 KEY cms_reusable_blocks_tenant_status_name_idx(tenant_id,status,name),
 CONSTRAINT cms_reusable_blocks_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT cms_reusable_blocks_creator_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cms_form_submissions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 cms_page_id BIGINT UNSIGNED NULL,
 cms_section_id BIGINT UNSIGNED NULL,
 user_id BIGINT UNSIGNED NULL,
 name VARCHAR(190) NOT NULL,
 email VARCHAR(255) NOT NULL,
 message TEXT NOT NULL,
 privacy_ack TINYINT(1) NOT NULL DEFAULT 0,
 status VARCHAR(24) NOT NULL DEFAULT 'new',
 submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 PRIMARY KEY(id),
 KEY cms_form_submissions_status_idx(status),
 KEY cms_form_submissions_submitted_at_idx(submitted_at),
 KEY cms_form_submissions_tenant_status_idx(tenant_id,status,submitted_at),
 CONSTRAINT cms_form_submissions_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT cms_form_submissions_page_fk FOREIGN KEY(cms_page_id) REFERENCES cms_pages(id) ON DELETE SET NULL,
 CONSTRAINT cms_form_submissions_section_fk FOREIGN KEY(cms_section_id) REFERENCES cms_sections(id) ON DELETE SET NULL,
 CONSTRAINT cms_form_submissions_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $migration = '2026_08_23_031000_expand_website_builder';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$migration, $migration]);

    // Chained migration registry: 2026_08_23_032000_expand_domain_operations
    // Chained migration registry: 2026_08_23_033000_harden_commerce_accounting
    installer_import_domain_operations($pdo);
}
