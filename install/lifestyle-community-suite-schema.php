<?php

declare(strict_types=1);

/**
 * Retry-safe fresh-install schema parity for the August 22 lifestyle/community
 * suite and Private Gather 1.2.3 identity-verification release.
 *
 * The base/community/hosted installer schemas are imported before this file is
 * executed. We therefore add only missing columns, indexes and constraints, and
 * create new tables with IF NOT EXISTS. Migration rows are registered by
 * install/index.php only after this function completes successfully.
 */
function installer_schema_has_index(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->execute([$table, $index]);

    return (int) $stmt->fetchColumn() > 0;
}

function installer_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (! installer_schema_has_column($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
    }
}

function installer_add_index_if_missing(PDO $pdo, string $table, string $index, string $columns, bool $unique = false): void
{
    if (! installer_schema_has_index($pdo, $table, $index)) {
        $pdo->exec('ALTER TABLE `'.$table.'` ADD '.($unique ? 'UNIQUE ' : '').'INDEX `'.$index.'` ('.$columns.')');
    }
}

function installer_add_constraint_if_missing(PDO $pdo, string $table, string $constraint, string $definition): void
{
    if (! installer_schema_has_constraint($pdo, $table, $constraint)) {
        $pdo->exec('ALTER TABLE `'.$table.'` ADD CONSTRAINT `'.$constraint.'` '.$definition);
    }
}

function installer_import_lifestyle_community_suite(PDO $pdo): void
{
    // 2026_08_22_030000_expand_lifestyle_community_suite
    $profileColumns = [
        'lifestyle_identity' => 'VARCHAR(40) NULL',
        'relationship_status' => 'VARCHAR(40) NULL',
        'experience_level' => 'VARCHAR(40) NULL',
        'pronouns' => 'VARCHAR(80) NULL',
        'looking_for' => 'JSON NULL',
        'lifestyle_interests' => 'JSON NULL',
        'boundaries' => 'TEXT NULL',
        'message_permissions' => "VARCHAR(30) NOT NULL DEFAULT 'members'",
        'show_age' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'show_last_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
    ];
    foreach ($profileColumns as $column => $definition) {
        installer_add_column_if_missing($pdo, 'profiles', $column, $definition);
    }
    installer_add_index_if_missing($pdo, 'profiles', 'profiles_lifestyle_identity_index', '`lifestyle_identity`');
    installer_add_index_if_missing($pdo, 'profiles', 'profiles_relationship_status_index', '`relationship_status`');

    $postColumns = [
        'wall_user_id' => 'BIGINT UNSIGNED NULL',
        'group_id' => 'BIGINT UNSIGNED NULL',
        'event_id' => 'BIGINT UNSIGNED NULL',
        'post_type' => "VARCHAR(30) NOT NULL DEFAULT 'status'",
        'visibility' => "VARCHAR(30) NOT NULL DEFAULT 'members'",
        'share_to_club_wall' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'media_path' => 'VARCHAR(255) NULL',
        'media_type' => 'VARCHAR(20) NULL',
        'media_mime' => 'VARCHAR(100) NULL',
        'media_size' => 'BIGINT UNSIGNED NULL',
        'poll_options' => 'JSON NULL',
        'expires_at' => 'TIMESTAMP NULL',
    ];
    foreach ($postColumns as $column => $definition) {
        installer_add_column_if_missing($pdo, 'community_posts', $column, $definition);
    }
    installer_add_constraint_if_missing($pdo, 'community_posts', 'community_posts_wall_user_id_foreign', 'FOREIGN KEY (`wall_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
    installer_add_constraint_if_missing($pdo, 'community_posts', 'community_posts_group_id_foreign', 'FOREIGN KEY (`group_id`) REFERENCES `community_groups`(`id`) ON DELETE SET NULL');
    installer_add_constraint_if_missing($pdo, 'community_posts', 'community_posts_event_id_foreign', 'FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL');
    installer_add_index_if_missing($pdo, 'community_posts', 'community_posts_post_type_index', '`post_type`');
    installer_add_index_if_missing($pdo, 'community_posts', 'community_posts_visibility_index', '`visibility`');
    installer_add_index_if_missing($pdo, 'community_posts', 'community_posts_share_to_club_wall_index', '`share_to_club_wall`');
    installer_add_index_if_missing($pdo, 'community_posts', 'community_posts_expires_at_index', '`expires_at`');
    installer_add_index_if_missing($pdo, 'community_posts', 'community_posts_wall_idx', '`tenant_id`,`wall_user_id`,`status`,`created_at`');
    installer_add_index_if_missing($pdo, 'community_posts', 'community_posts_group_idx', '`tenant_id`,`group_id`,`status`,`created_at`');

    installer_add_column_if_missing($pdo, 'community_comments', 'parent_id', 'BIGINT UNSIGNED NULL');
    installer_add_constraint_if_missing($pdo, 'community_comments', 'community_comments_parent_id_foreign', 'FOREIGN KEY (`parent_id`) REFERENCES `community_comments`(`id`) ON DELETE CASCADE');

    $albumPhotoColumns = [
        'media_type' => "VARCHAR(20) NOT NULL DEFAULT 'image'",
        'mime_type' => 'VARCHAR(100) NULL',
        'size_bytes' => 'BIGINT UNSIGNED NULL',
        'duration_seconds' => 'INT UNSIGNED NULL',
        'thumbnail_path' => 'VARCHAR(255) NULL',
    ];
    foreach ($albumPhotoColumns as $column => $definition) {
        installer_add_column_if_missing($pdo, 'private_album_photos', $column, $definition);
    }
    installer_add_index_if_missing($pdo, 'private_album_photos', 'private_album_photos_media_type_index', '`media_type`');

    $groupColumns = [
        'cover_path' => 'VARCHAR(255) NULL',
        'group_type' => "VARCHAR(40) NOT NULL DEFAULT 'interest'",
        'allow_member_posts' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'is_featured' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ];
    foreach ($groupColumns as $column => $definition) {
        installer_add_column_if_missing($pdo, 'community_groups', $column, $definition);
    }
    installer_add_index_if_missing($pdo, 'community_groups', 'community_groups_group_type_index', '`group_type`');
    installer_add_index_if_missing($pdo, 'community_groups', 'community_groups_is_featured_index', '`is_featured`');

    installer_add_column_if_missing($pdo, 'conversation_participants', 'typing_at', 'TIMESTAMP NULL');
    installer_add_index_if_missing($pdo, 'conversation_participants', 'conversation_participants_typing_at_index', '`typing_at`');

    $messageColumns = [
        'attachment_path' => 'VARCHAR(255) NULL',
        'attachment_type' => 'VARCHAR(20) NULL',
        'attachment_mime' => 'VARCHAR(100) NULL',
        'attachment_size' => 'BIGINT UNSIGNED NULL',
        'edited_at' => 'TIMESTAMP NULL',
        'deleted_at' => 'TIMESTAMP NULL',
    ];
    foreach ($messageColumns as $column => $definition) {
        installer_add_column_if_missing($pdo, 'messages', $column, $definition);
    }
    installer_add_index_if_missing($pdo, 'messages', 'messages_deleted_at_index', '`deleted_at`');

    $notificationColumns = [
        'email_reactions' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'email_connections' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'email_group_activity' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'in_app_messages' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'in_app_reactions' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'in_app_connections' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'in_app_events' => 'TINYINT(1) NOT NULL DEFAULT 1',
    ];
    foreach ($notificationColumns as $column => $definition) {
        installer_add_column_if_missing($pdo, 'notification_preferences', $column, $definition);
    }

    installer_add_column_if_missing($pdo, 'platform_notifications', 'tenant_id', 'BIGINT UNSIGNED NULL');
    installer_add_constraint_if_missing($pdo, 'platform_notifications', 'platform_notifications_tenant_id_foreign', 'FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE');
    installer_add_index_if_missing($pdo, 'platform_notifications', 'platform_notifications_tenant_user_idx', '`tenant_id`,`user_id`,`read_at`');

    $statements = [
        "CREATE TABLE IF NOT EXISTS message_reads (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            read_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY message_reads_message_user_unique(message_id,user_id),
            CONSTRAINT message_reads_message_fk FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE,
            CONSTRAINT message_reads_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS community_group_invites (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            invited_by_user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            expires_at TIMESTAMP NULL,
            responded_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY community_group_invites_group_user_unique(group_id,user_id),
            KEY community_group_invites_status_index(status),
            CONSTRAINT community_group_invites_group_fk FOREIGN KEY(group_id) REFERENCES community_groups(id) ON DELETE CASCADE,
            CONSTRAINT community_group_invites_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT community_group_invites_inviter_fk FOREIGN KEY(invited_by_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS membership_applications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            answers JSON NULL,
            member_note TEXT NULL,
            review_note TEXT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY membership_applications_tenant_user_unique(tenant_id,user_id),
            KEY membership_applications_status_index(status),
            KEY membership_applications_tenant_status_created_index(tenant_id,status,created_at),
            CONSTRAINT membership_applications_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT membership_applications_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT membership_applications_reviewer_fk FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS member_badges (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(80) NOT NULL,
            label VARCHAR(120) NOT NULL,
            icon VARCHAR(40) NULL,
            visibility VARCHAR(24) NOT NULL DEFAULT 'members',
            awarded_by BIGINT UNSIGNED NULL,
            awarded_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY member_badges_tenant_user_code_unique(tenant_id,user_id,code),
            CONSTRAINT member_badges_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT member_badges_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT member_badges_awarder_fk FOREIGN KEY(awarded_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS club_news_posts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            slug VARCHAR(200) NOT NULL,
            excerpt TEXT NULL,
            body LONGTEXT NOT NULL,
            cover_path VARCHAR(255) NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            visibility VARCHAR(24) NOT NULL DEFAULT 'public',
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            published_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY club_news_posts_tenant_slug_unique(tenant_id,slug),
            KEY club_news_posts_status_index(status),
            KEY club_news_posts_visibility_index(visibility),
            KEY club_news_posts_is_pinned_index(is_pinned),
            KEY club_news_posts_published_at_index(published_at),
            CONSTRAINT club_news_posts_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT club_news_posts_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS community_post_views (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            viewed_at TIMESTAMP NOT NULL,
            PRIMARY KEY(id),
            UNIQUE KEY community_post_views_post_user_unique(post_id,user_id),
            CONSTRAINT community_post_views_post_fk FOREIGN KEY(post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
            CONSTRAINT community_post_views_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 2026_08_22_031000_create_community_poll_votes
        "CREATE TABLE IF NOT EXISTS community_poll_votes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            option_index SMALLINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY community_poll_votes_post_user_unique(post_id,user_id),
            KEY community_poll_votes_post_option_index(post_id,option_index),
            CONSTRAINT community_poll_votes_post_fk FOREIGN KEY(post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
            CONSTRAINT community_poll_votes_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 2026_08_22_032000_create_profile_partner_invites
        "CREATE TABLE IF NOT EXISTS profile_partner_invites (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            sender_user_id BIGINT UNSIGNED NOT NULL,
            recipient_user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            expires_at TIMESTAMP NULL,
            responded_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY profile_partner_invites_tenant_sender_recipient_unique(tenant_id,sender_user_id,recipient_user_id),
            CONSTRAINT profile_partner_invites_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT profile_partner_invites_sender_fk FOREIGN KEY(sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT profile_partner_invites_recipient_fk FOREIGN KEY(recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 2026_08_22_033000_create_club_rewards
        "CREATE TABLE IF NOT EXISTS club_rewards (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            description TEXT NULL,
            points_cost INT UNSIGNED NOT NULL,
            inventory INT UNSIGNED NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            KEY club_rewards_active_index(active),
            KEY club_rewards_tenant_active_points_index(tenant_id,active,points_cost),
            CONSTRAINT club_rewards_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS member_point_ledger (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            points INT NOT NULL,
            reason VARCHAR(190) NOT NULL,
            source_type VARCHAR(60) NULL,
            source_id BIGINT UNSIGNED NULL,
            issued_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            KEY member_point_ledger_tenant_user_created_index(tenant_id,user_id,created_at),
            CONSTRAINT member_point_ledger_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT member_point_ledger_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT member_point_ledger_issuer_fk FOREIGN KEY(issued_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS reward_redemptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            reward_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            points_spent INT UNSIGNED NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            fulfilled_by BIGINT UNSIGNED NULL,
            fulfilled_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            KEY reward_redemptions_status_index(status),
            KEY reward_redemptions_tenant_status_created_index(tenant_id,status,created_at),
            CONSTRAINT reward_redemptions_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT reward_redemptions_reward_fk FOREIGN KEY(reward_id) REFERENCES club_rewards(id) ON DELETE CASCADE,
            CONSTRAINT reward_redemptions_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT reward_redemptions_fulfiller_fk FOREIGN KEY(fulfilled_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    // 2026_08_22_200000_global_username_and_age_verification_v123
    $userColumns = [
        'username' => 'VARCHAR(32) NULL',
        'verification_status' => "VARCHAR(32) NOT NULL DEFAULT 'unverified'",
        'verification_level' => 'VARCHAR(32) NULL',
        'identity_verified_at' => 'TIMESTAMP NULL',
        'identity_verification_expires_at' => 'TIMESTAMP NULL',
    ];
    foreach ($userColumns as $column => $definition) {
        installer_add_column_if_missing($pdo, 'users', $column, $definition);
    }
    installer_add_index_if_missing($pdo, 'users', 'users_verification_status_index', '`verification_status`');
    installer_add_index_if_missing($pdo, 'users', 'users_username_unique', '`username`', true);

    installer_add_column_if_missing($pdo, 'verifications', 'provider_reference_hash', 'CHAR(64) NULL');
    installer_add_index_if_missing($pdo, 'verifications', 'verifications_provider_reference_hash_index', '`provider_reference_hash`');

    $pdo->exec("CREATE TABLE IF NOT EXISTS verification_webhook_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        provider VARCHAR(32) NOT NULL,
        event_id VARCHAR(120) NOT NULL,
        event_type VARCHAR(120) NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        received_at TIMESTAMP NOT NULL,
        processed_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        PRIMARY KEY(id),
        UNIQUE KEY verification_webhook_provider_event_unique(provider,event_id),
        KEY verification_webhook_event_received_index(event_type,received_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
