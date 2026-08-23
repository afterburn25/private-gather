<?php

declare(strict_types=1);

/** Supplemental fresh-install schema for community, lifestyle membership and trust modules. */
function installer_import_private_community(PDO $pdo): void
{
    $statements = [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS community_groups (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, created_by BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, description TEXT NULL, visibility VARCHAR(24) NOT NULL DEFAULT 'tenant', join_policy VARCHAR(24) NOT NULL DEFAULT 'approval', status VARCHAR(24) NOT NULL DEFAULT 'active', allow_member_posts TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY community_groups_tenant_slug_unique(tenant_id,slug), KEY community_groups_tenant_status_index(tenant_id,status),
 CONSTRAINT community_groups_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT community_groups_creator_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS community_group_members (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, community_group_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, role VARCHAR(24) NOT NULL DEFAULT 'member', status VARCHAR(24) NOT NULL DEFAULT 'active', created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY community_group_members_group_user_unique(community_group_id,user_id), KEY community_group_members_user_status_index(user_id,status),
 CONSTRAINT community_group_members_group_fk FOREIGN KEY(community_group_id) REFERENCES community_groups(id) ON DELETE CASCADE, CONSTRAINT community_group_members_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS community_posts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, community_group_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NULL, body TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'active', is_pinned TINYINT(1) NOT NULL DEFAULT 0, edited_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY community_posts_status_index(status), KEY community_posts_pinned_index(is_pinned), KEY community_posts_tenant_pinned_created_idx(tenant_id,is_pinned,created_at), KEY community_posts_tenant_group_status_idx(tenant_id,community_group_id,status),
 CONSTRAINT community_posts_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT community_posts_group_fk FOREIGN KEY(community_group_id) REFERENCES community_groups(id) ON DELETE SET NULL, CONSTRAINT community_posts_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS community_comments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, post_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL, body TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'active', edited_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY community_comments_status_index(status), KEY community_comments_tenant_post_created_idx(tenant_id,post_id,created_at),
 CONSTRAINT community_comments_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT community_comments_post_fk FOREIGN KEY(post_id) REFERENCES community_posts(id) ON DELETE CASCADE, CONSTRAINT community_comments_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS community_reactions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, post_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, reaction VARCHAR(24) NOT NULL DEFAULT 'like', created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY community_reactions_post_user_unique(post_id,user_id), KEY community_reactions_tenant_reaction_index(tenant_id,reaction),
 CONSTRAINT community_reactions_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT community_reactions_post_fk FOREIGN KEY(post_id) REFERENCES community_posts(id) ON DELETE CASCADE, CONSTRAINT community_reactions_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS community_chat_messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL, body TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'active', created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY community_chat_status_index(status), KEY community_chat_tenant_id_index(tenant_id,id), KEY community_chat_tenant_created_index(tenant_id,created_at),
 CONSTRAINT community_chat_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT community_chat_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS tenant_membership_applications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, profile_type VARCHAR(24) NOT NULL DEFAULT 'individual', status VARCHAR(24) NOT NULL DEFAULT 'pending', referred_by VARCHAR(255) NULL, introduction TEXT NULL, answers JSON NULL, reviewed_by BIGINT UNSIGNED NULL, reviewed_at TIMESTAMP NULL, decision_note TEXT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY tenant_membership_applications_profile_type_index(profile_type), KEY tenant_membership_applications_status_index(status), KEY tenant_membership_applications_tenant_status_created_idx(tenant_id,status,created_at), KEY tenant_membership_applications_tenant_user_status_idx(tenant_id,user_id,status), KEY tenant_membership_applications_user_created_idx(user_id,created_at),
 CONSTRAINT tenant_membership_applications_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT tenant_membership_applications_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT tenant_membership_applications_reviewer_fk FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS badges (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NULL, scope VARCHAR(24) NOT NULL DEFAULT 'tenant', name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, description TEXT NULL, icon VARCHAR(64) NULL, badge_color VARCHAR(16) NOT NULL DEFAULT '#7d3b69', text_color VARCHAR(16) NOT NULL DEFAULT '#ffffff', category VARCHAR(32) NOT NULL DEFAULT 'custom', visibility VARCHAR(32) NOT NULL DEFAULT 'members', issuance_type VARCHAR(32) NOT NULL DEFAULT 'manual', criteria JSON NULL, expires_after_days INT UNSIGNED NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, is_system_reserved TINYINT(1) NOT NULL DEFAULT 0, created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY badges_scope_index(scope), KEY badges_category_index(category), KEY badges_issuance_type_index(issuance_type), KEY badges_is_active_index(is_active), KEY badges_tenant_scope_active_idx(tenant_id,scope,is_active), KEY badges_tenant_slug_idx(tenant_id,slug),
 CONSTRAINT badges_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT badges_creator_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS user_badges (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, badge_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NULL, issued_by BIGINT UNSIGNED NULL, issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at TIMESTAMP NULL, revoked_at TIMESTAMP NULL, revoked_by BIGINT UNSIGNED NULL, revocation_reason TEXT NULL, metadata JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY user_badges_user_revoked_expiry_idx(user_id,revoked_at,expires_at), KEY user_badges_tenant_user_revoked_idx(tenant_id,user_id,revoked_at), KEY user_badges_badge_user_revoked_idx(badge_id,user_id,revoked_at),
 CONSTRAINT user_badges_badge_fk FOREIGN KEY(badge_id) REFERENCES badges(id) ON DELETE CASCADE, CONSTRAINT user_badges_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT user_badges_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT user_badges_issuer_fk FOREIGN KEY(issued_by) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT user_badges_revoker_fk FOREIGN KEY(revoked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS tenant_membership_levels (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, description TEXT NULL, price_cents INT UNSIGNED NOT NULL DEFAULT 0, currency VARCHAR(3) NOT NULL DEFAULT 'USD', billing_interval VARCHAR(24) NOT NULL DEFAULT 'none', duration_days INT UNSIGNED NULL, profile_eligibility VARCHAR(24) NOT NULL DEFAULT 'any', guest_limit INT UNSIGNED NOT NULL DEFAULT 0, event_discount_percent TINYINT UNSIGNED NOT NULL DEFAULT 0, requires_approval TINYINT(1) NOT NULL DEFAULT 1, is_default TINYINT(1) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT UNSIGNED NOT NULL DEFAULT 0, benefits JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY tenant_membership_levels_tenant_slug_unique(tenant_id,slug), KEY tenant_membership_levels_billing_interval_idx(billing_interval), KEY tenant_membership_levels_profile_eligibility_idx(profile_eligibility), KEY tenant_membership_levels_default_idx(is_default), KEY tenant_membership_levels_active_idx(is_active), KEY tenant_membership_levels_tenant_active_sort_idx(tenant_id,is_active,sort_order),
 CONSTRAINT tenant_membership_levels_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS tenant_membership_terms (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, membership_level_id BIGINT UNSIGNED NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'active', starts_at TIMESTAMP NOT NULL, current_period_starts_at TIMESTAMP NOT NULL, current_period_ends_at TIMESTAMP NULL, renews_at TIMESTAMP NULL, expires_at TIMESTAMP NULL, auto_renew TINYINT(1) NOT NULL DEFAULT 0, cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0, source VARCHAR(32) NOT NULL DEFAULT 'staff', provider VARCHAR(255) NULL, provider_reference VARCHAR(255) NULL, notes TEXT NULL, created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY tenant_membership_terms_tenant_user_unique(tenant_id,user_id), KEY tenant_membership_terms_status_idx(status), KEY tenant_membership_terms_provider_reference_idx(provider_reference), KEY tenant_membership_terms_tenant_status_expiry_idx(tenant_id,status,expires_at), KEY tenant_membership_terms_level_status_idx(membership_level_id,status),
 CONSTRAINT tenant_membership_terms_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT tenant_membership_terms_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT tenant_membership_terms_level_fk FOREIGN KEY(membership_level_id) REFERENCES tenant_membership_levels(id) ON DELETE RESTRICT, CONSTRAINT tenant_membership_terms_creator_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ];

    foreach ($statements as $number => $statement) {
        try { $pdo->exec($statement); }
        catch (Throwable $e) { throw new RuntimeException('Private community schema import failed at statement '.($number + 1).': '.$e->getMessage(),0,$e); }
    }

    installer_add_column_if_missing($pdo,'community_posts','community_group_id','BIGINT UNSIGNED NULL');
    installer_add_index_if_missing($pdo,'community_posts','community_posts_tenant_group_status_idx','`tenant_id`,`community_group_id`,`status`');
    installer_add_foreign_key_if_missing($pdo,'community_posts','community_posts_group_fk','community_group_id','community_groups','id','SET NULL');

    installer_add_column_if_missing($pdo,'ticket_types','profile_eligibility',"VARCHAR(24) NOT NULL DEFAULT 'any'");
    installer_add_column_if_missing($pdo,'ticket_types','membership_required','TINYINT(1) NOT NULL DEFAULT 0');
    installer_add_column_if_missing($pdo,'ticket_types','approval_required','TINYINT(1) NOT NULL DEFAULT 0');

    $registeredMigrations=['2026_08_22_230000_create_tenant_membership_applications','2026_08_22_231000_add_lifestyle_ticket_eligibility','2026_08_22_232000_create_badge_system','2026_08_22_233000_create_tenant_membership_levels','2026_08_22_234000_create_redesign_community_groups'];
    $stmt=$pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    foreach($registeredMigrations as $migration)$stmt->execute([$migration,$migration]);
}

function installer_add_column_if_missing(PDO $pdo,string $table,string $column,string $definition):void
{
    if(!preg_match('/^[A-Za-z0-9_]+$/',$table)||!preg_match('/^[A-Za-z0-9_]+$/',$column))throw new InvalidArgumentException('Unsafe installer schema identifier.');
    $check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');$check->execute([$table,$column]);if((int)$check->fetchColumn()>0)return;
    $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s',$table,$column,$definition));
}

function installer_add_index_if_missing(PDO $pdo,string $table,string $index,string $columns):void
{
    foreach([$table,$index] as $identifier)if(!preg_match('/^[A-Za-z0-9_]+$/',$identifier))throw new InvalidArgumentException('Unsafe installer schema identifier.');
    if(!preg_match('/^[A-Za-z0-9_`,]+$/',$columns))throw new InvalidArgumentException('Unsafe installer index columns.');
    $check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');$check->execute([$table,$index]);if((int)$check->fetchColumn()>0)return;
    $pdo->exec(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (%s)',$table,$index,$columns));
}

function installer_add_foreign_key_if_missing(PDO $pdo,string $table,string $constraint,string $column,string $referencedTable,string $referencedColumn,string $onDelete):void
{
    foreach([$table,$constraint,$column,$referencedTable,$referencedColumn] as $identifier)if(!preg_match('/^[A-Za-z0-9_]+$/',$identifier))throw new InvalidArgumentException('Unsafe installer schema identifier.');
    if(!in_array($onDelete,['CASCADE','SET NULL','RESTRICT'],true))throw new InvalidArgumentException('Unsafe installer foreign-key action.');
    $check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?');$check->execute([$table,$constraint,'FOREIGN KEY']);if((int)$check->fetchColumn()>0)return;
    $pdo->exec(sprintf('ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s',$table,$constraint,$column,$referencedTable,$referencedColumn,$onDelete));
}
