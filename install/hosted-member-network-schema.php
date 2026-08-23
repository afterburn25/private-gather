<?php

declare(strict_types=1);

/**
 * Fresh-install schema for Hosted Edition social-network features.
 *
 * This is intentionally separate from the legacy/community installer schema so
 * Hosted development can advance without coupling new work to Self-Hosted.
 */
function installer_import_hosted_member_network(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS private_albums (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(160) NOT NULL,
            description TEXT NULL,
            visibility VARCHAR(30) NOT NULL DEFAULT 'granted',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            KEY private_albums_status_index(status),
            KEY private_albums_tenant_owner_status_index(tenant_id,owner_user_id,status),
            CONSTRAINT private_albums_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT private_albums_owner_fk FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS private_album_photos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            album_id BIGINT UNSIGNED NOT NULL,
            path VARCHAR(255) NOT NULL,
            caption VARCHAR(255) NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            KEY private_album_photos_album_sort_index(album_id,sort_order),
            CONSTRAINT private_album_photos_album_fk FOREIGN KEY(album_id) REFERENCES private_albums(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS private_album_access_grants (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            album_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            granted_by_user_id BIGINT UNSIGNED NOT NULL,
            expires_at TIMESTAMP NULL,
            revoked_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY private_album_access_unique(album_id,user_id),
            KEY private_album_access_user_state_index(user_id,revoked_at,expires_at),
            CONSTRAINT private_album_access_album_fk FOREIGN KEY(album_id) REFERENCES private_albums(id) ON DELETE CASCADE,
            CONSTRAINT private_album_access_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT private_album_access_granter_fk FOREIGN KEY(granted_by_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS member_connections (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_one_id BIGINT UNSIGNED NOT NULL,
            user_two_id BIGINT UNSIGNED NOT NULL,
            requested_by_user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            accepted_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY member_connections_pair_unique(tenant_id,user_one_id,user_two_id),
            KEY member_connections_tenant_status_index(tenant_id,status),
            KEY member_connections_status_index(status),
            CONSTRAINT member_connections_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT member_connections_user_one_fk FOREIGN KEY(user_one_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT member_connections_user_two_fk FOREIGN KEY(user_two_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT member_connections_requester_fk FOREIGN KEY(requested_by_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS community_groups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL,
            description TEXT NULL,
            visibility VARCHAR(30) NOT NULL DEFAULT 'members',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY community_groups_tenant_slug_unique(tenant_id,slug),
            KEY community_groups_tenant_visibility_status_index(tenant_id,visibility,status),
            KEY community_groups_status_index(status),
            CONSTRAINT community_groups_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT community_groups_owner_fk FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS community_group_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(30) NOT NULL DEFAULT 'member',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY community_group_members_unique(group_id,user_id),
            KEY community_group_members_status_index(status),
            CONSTRAINT community_group_members_group_fk FOREIGN KEY(group_id) REFERENCES community_groups(id) ON DELETE CASCADE,
            CONSTRAINT community_group_members_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS travel_plans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            city VARCHAR(120) NOT NULL,
            region VARCHAR(120) NULL,
            country VARCHAR(120) NULL,
            starts_on DATE NOT NULL,
            ends_on DATE NOT NULL,
            visibility VARCHAR(30) NOT NULL DEFAULT 'connections',
            notes TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            KEY travel_plans_tenant_dates_status_index(tenant_id,starts_on,ends_on,status),
            KEY travel_plans_tenant_city_status_index(tenant_id,city,status),
            KEY travel_plans_status_index(status),
            CONSTRAINT travel_plans_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT travel_plans_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS member_likes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            liker_user_id BIGINT UNSIGNED NOT NULL,
            liked_user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            source VARCHAR(30) NOT NULL DEFAULT 'network',
            matched_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY member_likes_direction_unique(tenant_id,liker_user_id,liked_user_id),
            KEY member_likes_liker_status_index(tenant_id,liker_user_id,status),
            KEY member_likes_liked_status_index(tenant_id,liked_user_id,status),
            KEY member_likes_status_index(status),
            KEY member_likes_matched_at_index(matched_at),
            CONSTRAINT member_likes_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT member_likes_liker_fk FOREIGN KEY(liker_user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT member_likes_liked_fk FOREIGN KEY(liked_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $matchingMigration = '2026_08_19_230000_create_hosted_member_matching';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$matchingMigration, $matchingMigration]);
}
