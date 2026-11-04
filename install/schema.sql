SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  display_name VARCHAR(255) NULL,
  email VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMP NULL,
  date_of_birth DATE NULL,
  adult_confirmed_at TIMESTAMP NULL,
  terms_accepted_at TIMESTAMP NULL,
  last_login_at TIMESTAMP NULL,
  locale VARCHAR(10) NOT NULL DEFAULT 'en',
  status VARCHAR(255) NOT NULL DEFAULT 'active',
  is_platform_admin TINYINT(1) NOT NULL DEFAULT 0,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100) NULL,
  two_factor_secret TEXT NULL,
  two_factor_recovery_codes TEXT NULL,
  two_factor_confirmed_at TIMESTAMP NULL,
  privacy_accepted_at TIMESTAMP NULL,
  privacy_version VARCHAR(50) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (id), UNIQUE KEY users_email_unique (email), KEY users_status_index (status), KEY users_admin_index (is_platform_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (email VARCHAR(255) NOT NULL, token VARCHAR(255) NOT NULL, created_at TIMESTAMP NULL, PRIMARY KEY (email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sessions (id VARCHAR(255) NOT NULL, user_id BIGINT UNSIGNED NULL, ip_address VARCHAR(45) NULL, user_agent TEXT NULL, payload LONGTEXT NOT NULL, last_activity INT NOT NULL, PRIMARY KEY (id), KEY sessions_user_id_index (user_id), KEY sessions_last_activity_index (last_activity)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cache (`key` VARCHAR(255) NOT NULL, value MEDIUMTEXT NOT NULL, expiration INT NOT NULL, PRIMARY KEY (`key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cache_locks (`key` VARCHAR(255) NOT NULL, owner VARCHAR(255) NOT NULL, expiration INT NOT NULL, PRIMARY KEY (`key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS jobs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, queue VARCHAR(255) NOT NULL, payload LONGTEXT NOT NULL, attempts TINYINT UNSIGNED NOT NULL, reserved_at INT UNSIGNED NULL, available_at INT UNSIGNED NOT NULL, created_at INT UNSIGNED NOT NULL, PRIMARY KEY (id), KEY jobs_queue_index (queue)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS job_batches (id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, total_jobs INT NOT NULL, pending_jobs INT NOT NULL, failed_jobs INT NOT NULL, failed_job_ids LONGTEXT NOT NULL, options MEDIUMTEXT NULL, cancelled_at INT NULL, created_at INT NOT NULL, finished_at INT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS failed_jobs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, uuid VARCHAR(255) NOT NULL, connection TEXT NOT NULL, queue TEXT NOT NULL, payload LONGTEXT NOT NULL, exception LONGTEXT NOT NULL, failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY failed_jobs_uuid_unique (uuid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenants (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL DEFAULT 'active', plan VARCHAR(255) NOT NULL DEFAULT 'starter', settings JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY (id), UNIQUE KEY tenants_slug_unique (slug), KEY tenants_type_index (type), KEY tenants_status_index (status), KEY tenants_plan_index (plan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, role VARCHAR(255) NOT NULL DEFAULT 'member', status VARCHAR(255) NOT NULL DEFAULT 'active', created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY (id), UNIQUE KEY tenant_users_unique (tenant_id,user_id), KEY tenant_users_role_index (role), KEY tenant_users_status_index (status), CONSTRAINT tenant_users_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT tenant_users_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_domains (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, domain VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, is_primary TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(255) NOT NULL DEFAULT 'pending', verification_token VARCHAR(80) NULL, verified_at TIMESTAMP NULL, ssl_status VARCHAR(255) NOT NULL DEFAULT 'pending', ssl_last_checked_at TIMESTAMP NULL, redirect_to_primary TINYINT(1) NOT NULL DEFAULT 0, dns_status VARCHAR(255) NOT NULL DEFAULT 'pending', dns_last_checked_at TIMESTAMP NULL, last_error TEXT NULL, health JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY (id), UNIQUE KEY tenant_domains_domain_unique (domain), UNIQUE KEY tenant_domains_verification_unique (verification_token), KEY tenant_domains_type_index (type), KEY tenant_domains_primary_index (is_primary), KEY tenant_domains_status_index (status), KEY tenant_domains_ssl_index (ssl_status), KEY tenant_domains_dns_index(dns_status), KEY tenant_domains_tenant_status_index (tenant_id,status), CONSTRAINT tenant_domains_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, summary TEXT NULL, description LONGTEXT NULL, visibility VARCHAR(255) NOT NULL DEFAULT 'public', rsvp_mode VARCHAR(255) NOT NULL DEFAULT 'instant', status VARCHAR(255) NOT NULL DEFAULT 'draft', starts_at DATETIME NOT NULL, ends_at DATETIME NULL, timezone VARCHAR(255) NOT NULL DEFAULT 'America/Chicago', capacity INT UNSIGNED NULL, city VARCHAR(255) NULL, region VARCHAR(255) NULL, public_location_label VARCHAR(255) NULL, exact_address TEXT NULL, exact_address_visibility VARCHAR(255) NOT NULL DEFAULT 'approved_attendees', cover_image_path VARCHAR(255) NULL, category VARCHAR(255) NULL, dress_code VARCHAR(255) NULL, rules LONGTEXT NULL, schedule JSON NULL, waitlist_enabled TINYINT(1) NOT NULL DEFAULT 1, requires_verified_profile TINYINT(1) NOT NULL DEFAULT 0, registration_opens_at TIMESTAMP NULL, registration_closes_at TIMESTAMP NULL, recurrence_rule VARCHAR(20) NULL, recurrence_until TIMESTAMP NULL, parent_event_id BIGINT UNSIGNED NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY (id), UNIQUE KEY events_tenant_slug_unique (tenant_id,slug), KEY events_visibility_index (visibility), KEY events_rsvp_mode_index (rsvp_mode), KEY events_status_index (status), KEY events_starts_at_index (starts_at), KEY events_city_index (city), KEY events_region_index (region), KEY events_category_index(category), KEY events_tenant_status_start_index (tenant_id,status,starts_at), KEY events_tenant_status_starts_idx(tenant_id,status,starts_at), KEY events_marketplace_idx(visibility,status,starts_at), CONSTRAINT events_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT events_parent_fk FOREIGN KEY(parent_event_id) REFERENCES events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_rsvps (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, status VARCHAR(255) NOT NULL DEFAULT 'pending', guest_count SMALLINT UNSIGNED NOT NULL DEFAULT 1, answers JSON NULL, approved_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY (id), UNIQUE KEY event_rsvps_event_user_unique (event_id,user_id), KEY event_rsvps_status_index (status), KEY rsvps_event_status_created_idx(event_id,status,created_at), KEY rsvps_user_status_idx(user_id,status), CONSTRAINT event_rsvps_event_fk FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE, CONSTRAINT event_rsvps_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS event_invitations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NULL, email VARCHAR(255) NULL, token VARCHAR(96) NOT NULL, status VARCHAR(255) NOT NULL DEFAULT 'pending', max_guests SMALLINT UNSIGNED NOT NULL DEFAULT 1, expires_at TIMESTAMP NULL, accepted_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY event_invitations_token_unique(token), KEY event_invitations_email_index(email), KEY event_invitations_status_index(status), KEY event_invitations_expires_index(expires_at), KEY event_invitations_event_status_index(event_id,status),
 CONSTRAINT event_invitations_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE, CONSTRAINT event_invitations_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT event_invitations_creator_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cms_pages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, slug VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, seo_title VARCHAR(255) NULL, seo_description TEXT NULL, status VARCHAR(255) NOT NULL DEFAULT 'draft', is_homepage TINYINT(1) NOT NULL DEFAULT 0, published_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY (id), UNIQUE KEY cms_pages_tenant_slug_unique (tenant_id,slug), KEY cms_pages_status_index (status), KEY cms_pages_home_index (is_homepage), CONSTRAINT cms_pages_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cms_sections (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, cms_page_id BIGINT UNSIGNED NOT NULL, type VARCHAR(255) NOT NULL, name VARCHAR(255) NULL, content JSON NULL, settings JSON NULL, sort_order INT UNSIGNED NOT NULL DEFAULT 0, is_enabled TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY (id), KEY cms_sections_type_index (type), KEY cms_sections_sort_index (sort_order), KEY cms_sections_enabled_index (is_enabled), CONSTRAINT cms_sections_page_fk FOREIGN KEY (cms_page_id) REFERENCES cms_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, `group` VARCHAR(255) NOT NULL DEFAULT 'general', `key` VARCHAR(255) NOT NULL, value LONGTEXT NULL, type VARCHAR(255) NOT NULL DEFAULT 'string', is_public TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY (id), UNIQUE KEY site_settings_tenant_key_unique (tenant_id,`key`), KEY site_settings_group_index (`group`), CONSTRAINT site_settings_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NULL, action VARCHAR(255) NOT NULL, subject_type VARCHAR(255) NULL, subject_id BIGINT UNSIGNED NULL, `before` JSON NULL, `after` JSON NULL, ip_address VARCHAR(45) NULL, user_agent TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id), KEY audit_logs_action_index (action), KEY audit_logs_created_index (created_at), KEY audit_logs_subject_index (subject_type,subject_id), CONSTRAINT audit_logs_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL, CONSTRAINT audit_logs_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_upgrades (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, upgrade_id CHAR(36) NOT NULL, from_version VARCHAR(40) NOT NULL, to_version VARCHAR(40) NOT NULL, status VARCHAR(40) NOT NULL, package_name VARCHAR(255) NOT NULL, package_sha256 CHAR(64) NOT NULL, backup_path TEXT NULL, log_path TEXT NULL, initiated_by BIGINT UNSIGNED NULL, started_at TIMESTAMP NOT NULL, completed_at TIMESTAMP NULL, error_message TEXT NULL, manifest JSON NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY (id), UNIQUE KEY platform_upgrades_upgrade_id_unique (upgrade_id), KEY platform_upgrades_status_index (status), CONSTRAINT platform_upgrades_user_fk FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Fresh installs define the final 1.0.8 table shape directly so the schema can be retried safely.

CREATE TABLE IF NOT EXISTS profiles (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, partner_user_id BIGINT UNSIGNED NULL, profile_type VARCHAR(255) NOT NULL DEFAULT 'individual', headline VARCHAR(255) NULL, bio LONGTEXT NULL, city VARCHAR(255) NULL, region VARCHAR(255) NULL, avatar_path VARCHAR(255) NULL, cover_path VARCHAR(255) NULL, interests JSON NULL, visibility JSON NULL, discoverable TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY profiles_user_unique(user_id), KEY profiles_type_index(profile_type), KEY profiles_city_index(city), KEY profiles_region_index(region), KEY profiles_discoverable_index(discoverable),
 CONSTRAINT profiles_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT profiles_partner_fk FOREIGN KEY(partner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_photos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, profile_id BIGINT UNSIGNED NOT NULL, path VARCHAR(255) NOT NULL, visibility VARCHAR(255) NOT NULL DEFAULT 'members', sort_order INT UNSIGNED NOT NULL DEFAULT 0, is_primary TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY profile_photos_visibility_index(visibility), CONSTRAINT profile_photos_profile_fk FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_blocks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, blocked_user_id BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY user_blocks_unique(user_id,blocked_user_id), CONSTRAINT user_blocks_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT user_blocks_blocked_fk FOREIGN KEY(blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_invitations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, email VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL DEFAULT 'staff', token VARCHAR(80) NOT NULL, expires_at TIMESTAMP NOT NULL, accepted_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY tenant_invitations_token_unique(token), KEY tenant_invitations_tenant_email_index(tenant_id,email), CONSTRAINT tenant_invitations_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_assets (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, uploaded_by BIGINT UNSIGNED NULL, disk VARCHAR(255) NOT NULL DEFAULT 'public', path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NULL, size BIGINT UNSIGNED NOT NULL DEFAULT 0, alt_text VARCHAR(255) NULL, visibility VARCHAR(255) NOT NULL DEFAULT 'tenant', sha256 CHAR(64) NULL, metadata_stripped TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY media_visibility_index(visibility), KEY media_sha_index(sha256), CONSTRAINT media_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT media_user_fk FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cms_navigation_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, location VARCHAR(255) NOT NULL DEFAULT 'header', label VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, sort_order INT UNSIGNED NOT NULL DEFAULT 0, is_enabled TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY cms_nav_location_index(location), CONSTRAINT cms_nav_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cms_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, cms_page_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL, snapshot JSON NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), CONSTRAINT cms_revisions_page_fk FOREIGN KEY(cms_page_id) REFERENCES cms_pages(id) ON DELETE CASCADE, CONSTRAINT cms_revisions_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS event_questions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_id BIGINT UNSIGNED NOT NULL, label VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL DEFAULT 'text', options JSON NULL, required TINYINT(1) NOT NULL DEFAULT 0, sort_order INT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), CONSTRAINT event_questions_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_waitlist (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, guest_count SMALLINT UNSIGNED NOT NULL DEFAULT 1, position INT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY event_waitlist_unique(event_id,user_id), CONSTRAINT event_waitlist_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE, CONSTRAINT event_waitlist_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_favorites (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY event_favorites_unique(event_id,user_id), CONSTRAINT event_favorites_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE, CONSTRAINT event_favorites_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_follows (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY tenant_follows_unique(tenant_id,user_id), CONSTRAINT tenant_follows_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT tenant_follows_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS ticket_types (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, description TEXT NULL, price_cents INT UNSIGNED NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'USD', quantity INT UNSIGNED NULL, max_per_order INT UNSIGNED NOT NULL DEFAULT 10, sales_start_at TIMESTAMP NULL, sales_end_at TIMESTAMP NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), CONSTRAINT ticket_types_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promo_codes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, event_id BIGINT UNSIGNED NULL, code VARCHAR(255) NOT NULL, discount_type VARCHAR(255) NOT NULL DEFAULT 'percent', discount_value INT UNSIGNED NOT NULL, max_uses INT UNSIGNED NULL, uses INT UNSIGNED NOT NULL DEFAULT 0, expires_at TIMESTAMP NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY promo_codes_unique(tenant_id,code), CONSTRAINT promo_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT promo_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id CHAR(36) NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, event_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, status VARCHAR(255) NOT NULL DEFAULT 'pending', currency CHAR(3) NOT NULL DEFAULT 'USD', subtotal_cents INT UNSIGNED NOT NULL DEFAULT 0, discount_cents INT UNSIGNED NOT NULL DEFAULT 0, fee_cents INT UNSIGNED NOT NULL DEFAULT 0, total_cents INT UNSIGNED NOT NULL DEFAULT 0, promo_code VARCHAR(255) NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY orders_public_unique(public_id), KEY orders_status_index(status), KEY orders_tenant_status_created_idx(tenant_id,status,created_at), CONSTRAINT orders_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT orders_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE, CONSTRAINT orders_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, order_id BIGINT UNSIGNED NOT NULL, ticket_type_id BIGINT UNSIGNED NOT NULL, quantity INT UNSIGNED NOT NULL, unit_price_cents INT UNSIGNED NOT NULL, total_cents INT UNSIGNED NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), CONSTRAINT order_items_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE, CONSTRAINT order_items_type_fk FOREIGN KEY(ticket_type_id) REFERENCES ticket_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, order_id BIGINT UNSIGNED NOT NULL, provider VARCHAR(255) NOT NULL, provider_reference VARCHAR(255) NULL, status VARCHAR(255) NOT NULL DEFAULT 'pending', amount_cents INT UNSIGNED NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'USD', metadata JSON NULL, paid_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY payments_provider_ref_index(provider_reference), KEY payments_status_index(status), CONSTRAINT payments_order_fk FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id CHAR(36) NOT NULL, order_item_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, qr_token VARCHAR(80) NOT NULL, status VARCHAR(255) NOT NULL DEFAULT 'valid', checked_in_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY tickets_public_unique(public_id), UNIQUE KEY tickets_qr_unique(qr_token), KEY tickets_status_index(status), CONSTRAINT tickets_item_fk FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE, CONSTRAINT tickets_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NULL, event_id BIGINT UNSIGNED NULL, type VARCHAR(255) NOT NULL DEFAULT 'direct', subject VARCHAR(255) NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY conversations_type_index(type), CONSTRAINT conversations_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT conversations_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversation_participants (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, conversation_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, last_read_at TIMESTAMP NULL, muted TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY conversation_participants_unique(conversation_id,user_id), CONSTRAINT cp_conversation_fk FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE, CONSTRAINT cp_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, conversation_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL, body LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL DEFAULT 'sent', created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY messages_conversation_created_index(conversation_id,created_at), KEY messages_user_created_idx(user_id,created_at), CONSTRAINT messages_conversation_fk FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE, CONSTRAINT messages_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, email_events TINYINT(1) NOT NULL DEFAULT 1, email_messages TINYINT(1) NOT NULL DEFAULT 1, email_marketing TINYINT(1) NOT NULL DEFAULT 0, browser_notifications TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY notification_preferences_user_unique(user_id), CONSTRAINT notification_preferences_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_notifications (
 id CHAR(36) NOT NULL, user_id BIGINT UNSIGNED NOT NULL, type VARCHAR(255) NOT NULL, data JSON NOT NULL, read_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY platform_notifications_user_read_index(user_id,read_at), CONSTRAINT platform_notifications_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_checkins (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL, ticket_id BIGINT UNSIGNED NULL, checked_in_by BIGINT UNSIGNED NULL, method VARCHAR(255) NOT NULL DEFAULT 'manual', guest_count SMALLINT UNSIGNED NOT NULL DEFAULT 1, checked_in_at TIMESTAMP NOT NULL, metadata JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY event_checkins_event_time_index(event_id,checked_in_at), CONSTRAINT checkin_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE, CONSTRAINT checkin_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT checkin_ticket_fk FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE SET NULL, CONSTRAINT checkin_staff_fk FOREIGN KEY(checked_in_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, reporter_id BIGINT UNSIGNED NULL, tenant_id BIGINT UNSIGNED NULL, reportable_type VARCHAR(255) NOT NULL, reportable_id BIGINT UNSIGNED NOT NULL, category VARCHAR(255) NOT NULL, details TEXT NULL, status VARCHAR(255) NOT NULL DEFAULT 'open', assigned_to BIGINT UNSIGNED NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY reports_category_index(category), KEY reports_status_index(status), KEY reports_target_index(reportable_type,reportable_id), CONSTRAINT reports_reporter_fk FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT reports_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT reports_assignee_fk FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS moderation_actions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, report_id BIGINT UNSIGNED NULL, actor_id BIGINT UNSIGNED NULL, target_type VARCHAR(255) NOT NULL, target_id BIGINT UNSIGNED NOT NULL, action VARCHAR(255) NOT NULL, reason TEXT NULL, metadata JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY moderation_target_index(target_type,target_id), CONSTRAINT moderation_report_fk FOREIGN KEY(report_id) REFERENCES reports(id) ON DELETE SET NULL, CONSTRAINT moderation_actor_fk FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NULL, tenant_id BIGINT UNSIGNED NULL, type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL DEFAULT 'pending', provider VARCHAR(255) NULL, provider_reference VARCHAR(255) NULL, reviewed_by BIGINT UNSIGNED NULL, verified_at TIMESTAMP NULL, expires_at TIMESTAMP NULL, metadata JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY verifications_type_index(type), KEY verifications_status_index(status), CONSTRAINT verifications_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT verifications_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT verifications_reviewer_fk FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, price_monthly_cents INT UNSIGNED NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'USD', features JSON NULL, active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY plans_code_unique(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_subscriptions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, plan_id BIGINT UNSIGNED NULL, status VARCHAR(255) NOT NULL DEFAULT 'active', provider VARCHAR(255) NULL, provider_reference VARCHAR(255) NULL, trial_ends_at TIMESTAMP NULL, current_period_ends_at TIMESTAMP NULL, cancelled_at TIMESTAMP NULL, metadata JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY tenant_subscriptions_tenant_unique(tenant_id), KEY tenant_subscriptions_status_index(status), CONSTRAINT subscriptions_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT subscriptions_plan_fk FOREIGN KEY(plan_id) REFERENCES plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NULL, `key` VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, body_html LONGTEXT NOT NULL, body_text LONGTEXT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY email_templates_tenant_key_unique(tenant_id,`key`), CONSTRAINT email_templates_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NULL, event_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NULL, event_name VARCHAR(255) NOT NULL, session_key VARCHAR(80) NULL, path VARCHAR(500) NULL, referrer VARCHAR(1000) NULL, properties JSON NULL, occurred_at TIMESTAMP NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY analytics_name_index(event_name), KEY analytics_session_index(session_key), KEY analytics_occurred_index(occurred_at), KEY analytics_tenant_name_time_index(tenant_id,event_name,occurred_at), CONSTRAINT analytics_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT analytics_event_fk FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE, CONSTRAINT analytics_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consent_records (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, consent_type VARCHAR(255) NOT NULL, document_version VARCHAR(80) NOT NULL, granted TINYINT(1) NOT NULL, ip_hash CHAR(64) NULL, recorded_at TIMESTAMP NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY consent_type_index(consent_type), KEY consent_user_type_time_index(user_id,consent_type,recorded_at), CONSTRAINT consent_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL DEFAULT 'pending', requested_at TIMESTAMP NOT NULL, completed_at TIMESTAMP NULL, artifact_path VARCHAR(255) NULL, admin_notes TEXT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY data_requests_type_index(type), KEY data_requests_status_index(status), CONSTRAINT data_requests_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NULL, event VARCHAR(255) NOT NULL, ip_hash CHAR(64) NULL, user_agent_hash CHAR(64) NULL, metadata JSON NULL, occurred_at TIMESTAMP NOT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), KEY security_event_index(event), KEY security_occurred_index(occurred_at), CONSTRAINT security_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS platform_settings (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `group` VARCHAR(255) NOT NULL DEFAULT 'content', `key` VARCHAR(255) NOT NULL, value LONGTEXT NULL, type VARCHAR(255) NOT NULL DEFAULT 'string', updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY platform_settings_key_unique(`key`), KEY platform_settings_group_index(`group`), CONSTRAINT platform_settings_updated_by_fk FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_branding (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, logo_path VARCHAR(255) NULL, favicon_path VARCHAR(255) NULL, primary_color VARCHAR(20) NULL, accent_color VARCHAR(20) NULL, font_family VARCHAR(120) NULL, email_from_name VARCHAR(255) NULL, show_platform_branding TINYINT(1) NOT NULL DEFAULT 1, theme JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
 PRIMARY KEY(id), UNIQUE KEY tenant_branding_tenant_unique(tenant_id), CONSTRAINT tenant_branding_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO plans(code,name,price_monthly_cents,currency,features,active,sort_order,created_at,updated_at) VALUES
('starter','Starter',0,'USD','{"custom_domains":false,"white_label":false,"staff_limit":2,"events_limit":25,"analytics":false,"ticketing":true}',1,0,NOW(),NOW()),
('pro','Pro',0,'USD','{"custom_domains":true,"white_label":false,"staff_limit":10,"events_limit":"unlimited","analytics":true,"ticketing":true}',1,10,NOW(),NOW()),
('business','Business',0,'USD','{"custom_domains":true,"white_label":false,"staff_limit":30,"events_limit":"unlimited","analytics":true,"ticketing":true,"priority_support":true}',1,20,NOW(),NOW()),
('white-label','White Label',0,'USD','{"custom_domains":true,"white_label":true,"staff_limit":"unlimited","events_limit":"unlimited","analytics":true,"ticketing":true,"priority_support":true}',1,30,NOW(),NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name),price_monthly_cents=VALUES(price_monthly_cents),currency=VALUES(currency),features=VALUES(features),active=VALUES(active),sort_order=VALUES(sort_order),updated_at=VALUES(updated_at);

INSERT INTO migrations (migration,batch) SELECT '0001_01_01_000000_create_users_table',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='0001_01_01_000000_create_users_table');
INSERT INTO migrations (migration,batch) SELECT '0001_01_01_000001_create_cache_table',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='0001_01_01_000001_create_cache_table');
INSERT INTO migrations (migration,batch) SELECT '0001_01_01_000002_create_jobs_table',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='0001_01_01_000002_create_jobs_table');
INSERT INTO migrations (migration,batch) SELECT '2026_08_16_180000_create_tenants_table',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_16_180000_create_tenants_table');
INSERT INTO migrations (migration,batch) SELECT '2026_08_16_180100_create_tenant_domains_table',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_16_180100_create_tenant_domains_table');
INSERT INTO migrations (migration,batch) SELECT '2026_08_16_180200_create_events_table',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_16_180200_create_events_table');
INSERT INTO migrations (migration,batch) SELECT '2026_08_16_180300_create_cms_tables',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_16_180300_create_cms_tables');
INSERT INTO migrations (migration,batch) SELECT '2026_08_16_180400_create_audit_logs_table',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_16_180400_create_audit_logs_table');
INSERT INTO migrations (migration,batch) SELECT '2026_08_16_190000_create_platform_upgrades_table',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_16_190000_create_platform_upgrades_table');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_000100_create_member_profiles_and_security',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_000100_create_member_profiles_and_security');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_000200_create_tenant_invitations',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_000200_create_tenant_invitations');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_000300_create_cms_media_navigation',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_000300_create_cms_media_navigation');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_000400_expand_events_rsvp_discovery',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_000400_expand_events_rsvp_discovery');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_000500_create_ticketing_orders_payments',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_000500_create_ticketing_orders_payments');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_000600_create_messaging_notifications',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_000600_create_messaging_notifications');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_000700_create_checkin_moderation_trust',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_000700_create_checkin_moderation_trust');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_000800_create_saas_analytics_email',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_000800_create_saas_analytics_email');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_000900_create_security_privacy_controls',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_000900_create_security_privacy_controls');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_001000_create_tenant_branding',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_001000_create_tenant_branding');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_001100_expand_tenant_domain_health',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_001100_expand_tenant_domain_health');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_001200_harden_media_privacy',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_001200_harden_media_privacy');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_001300_add_scale_indexes',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_001300_add_scale_indexes');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_001400_seed_v1_defaults',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_001400_seed_v1_defaults');

INSERT INTO migrations (migration,batch) SELECT '2026_08_17_001500_create_event_invitations',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_001500_create_event_invitations');

INSERT INTO migrations (migration,batch) SELECT '2026_08_17_001600_create_platform_settings',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_001600_create_platform_settings');
INSERT INTO migrations (migration,batch) SELECT '2026_08_17_040000_private_gather_brand_identity',1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='2026_08_17_040000_private_gather_brand_identity');

SET FOREIGN_KEY_CHECKS=1;
