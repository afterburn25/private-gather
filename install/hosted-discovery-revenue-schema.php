<?php

declare(strict_types=1);

/** Fresh-install schema for Hosted club discovery and platform affiliate revenue. */
function installer_import_hosted_discovery_revenue(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS club_directory_profiles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            is_listed TINYINT(1) NOT NULL DEFAULT 0,
            listing_name VARCHAR(160) NULL,
            club_type VARCHAR(40) NOT NULL DEFAULT 'club',
            short_description TEXT NULL,
            city VARCHAR(120) NULL,
            region VARCHAR(120) NULL,
            country_code VARCHAR(2) NOT NULL DEFAULT 'US',
            postal_code VARCHAR(24) NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            amenities JSON NULL,
            contact_url VARCHAR(500) NULL,
            verified_at TIMESTAMP NULL,
            featured_until TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY club_directory_profiles_tenant_unique(tenant_id),
            KEY club_directory_is_listed_index(is_listed),
            KEY club_directory_location_index(is_listed,country_code,region,city),
            KEY club_directory_type_index(club_type),
            KEY club_directory_featured_index(featured_until),
            CONSTRAINT club_directory_profiles_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS affiliate_offers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(120) NOT NULL,
            title VARCHAR(180) NOT NULL,
            advertiser_name VARCHAR(180) NOT NULL,
            relationship_type VARCHAR(30) NOT NULL DEFAULT 'affiliate',
            category VARCHAR(60) NOT NULL DEFAULT 'other',
            description TEXT NULL,
            affiliate_url TEXT NOT NULL,
            image_url VARCHAR(1000) NULL,
            cta_label VARCHAR(80) NOT NULL DEFAULT 'Learn More',
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            placements JSON NULL,
            country_code VARCHAR(2) NULL,
            region VARCHAR(120) NULL,
            city VARCHAR(120) NULL,
            priority INT UNSIGNED NOT NULL DEFAULT 100,
            starts_at TIMESTAMP NULL,
            ends_at TIMESTAMP NULL,
            disclosure VARCHAR(255) NOT NULL DEFAULT 'Sponsored affiliate offer — Private Gather may earn a commission if you purchase through this link.',
            metadata JSON NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            UNIQUE KEY affiliate_offers_slug_unique(slug),
            KEY affiliate_offer_delivery_index(status,priority,starts_at,ends_at),
            KEY affiliate_offers_category_index(category),
            KEY affiliate_offers_country_index(country_code),
            KEY affiliate_offers_region_index(region),
            KEY affiliate_offers_city_index(city)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS affiliate_clicks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_offer_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            tenant_id BIGINT UNSIGNED NULL,
            placement VARCHAR(80) NULL,
            referrer_host VARCHAR(255) NULL,
            ip_hash CHAR(64) NULL,
            user_agent_hash CHAR(64) NULL,
            clicked_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            KEY affiliate_click_offer_time_index(affiliate_offer_id,clicked_at),
            KEY affiliate_clicks_placement_index(placement),
            KEY affiliate_clicks_clicked_at_index(clicked_at),
            CONSTRAINT affiliate_clicks_offer_fk FOREIGN KEY(affiliate_offer_id) REFERENCES affiliate_offers(id) ON DELETE CASCADE,
            CONSTRAINT affiliate_clicks_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT affiliate_clicks_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS affiliate_conversions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_offer_id BIGINT UNSIGNED NOT NULL,
            external_reference VARCHAR(180) NULL,
            sale_amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
            commission_cents INT UNSIGNED NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            status VARCHAR(30) NOT NULL DEFAULT 'reported',
            occurred_at TIMESTAMP NOT NULL,
            metadata JSON NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            PRIMARY KEY(id),
            KEY affiliate_conversion_offer_status_index(affiliate_offer_id,status,occurred_at),
            KEY affiliate_conversions_status_index(status),
            KEY affiliate_conversions_occurred_at_index(occurred_at),
            CONSTRAINT affiliate_conversions_offer_fk FOREIGN KEY(affiliate_offer_id) REFERENCES affiliate_offers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $migration = '2026_08_19_230000_create_hosted_club_directory_and_affiliate_revenue';
    $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
    $stmt->execute([$migration, $migration]);
}
