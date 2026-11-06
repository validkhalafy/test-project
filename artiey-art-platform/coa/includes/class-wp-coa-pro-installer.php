<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WP_COA_Pro_Installer {
    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $certificates = $wpdb->prefix . 'coa_certificates';
        $events = $wpdb->prefix . 'coa_events';
        $galleries = $wpdb->prefix . 'coa_galleries';
        $profiles = $wpdb->prefix . 'coa_person_profiles';

        $certificate_sql = "CREATE TABLE {$certificates} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            gallery_id bigint(20) unsigned NOT NULL DEFAULT 0,
            activity_type varchar(50) NOT NULL DEFAULT 'gallery',
            certificate_template varchar(30) NOT NULL DEFAULT 'classic',
            gallery_profile_snapshot longtext NULL,
            artist_profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
            collector_profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
            certificate_code varchar(64) NOT NULL,
            artwork_title varchar(255) NOT NULL,
            artwork_title_en varchar(255) NOT NULL DEFAULT '',
            artist_name varchar(255) NOT NULL,
            artist_name_en varchar(255) NOT NULL DEFAULT '',
            medium varchar(255) NOT NULL DEFAULT '',
            medium_en varchar(255) NOT NULL DEFAULT '',
            year_created varchar(20) NOT NULL DEFAULT '',
            dimensions varchar(255) NOT NULL DEFAULT '',
            accession_number varchar(100) NOT NULL DEFAULT '',
            artwork_image_id bigint(20) unsigned NOT NULL DEFAULT 0,
            artwork_image_sha256 char(64) NOT NULL DEFAULT '',
            artwork_description text NULL,
            artwork_description_en text NULL,
            collector_name varchar(255) NOT NULL DEFAULT '',
            collector_name_en varchar(255) NOT NULL DEFAULT '',
            current_owner_name varchar(255) NOT NULL DEFAULT '',
            current_owner_name_en varchar(255) NOT NULL DEFAULT '',
            previous_owner_name varchar(255) NOT NULL DEFAULT '',
            previous_owner_name_en varchar(255) NOT NULL DEFAULT '',
            issuer_name varchar(255) NOT NULL DEFAULT '',
            issuer_name_en varchar(255) NOT NULL DEFAULT '',
            issue_date datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            integrity_hash char(64) NOT NULL DEFAULT '',
            integrity_payload longtext NULL,
            signature_version tinyint(3) unsigned NOT NULL DEFAULT 1,
            integrity_key_source varchar(20) NOT NULL DEFAULT 'legacy',
            notes text NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            revoked_at datetime NULL,
            revoked_by bigint(20) unsigned NOT NULL DEFAULT 0,
            revocation_reason text NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY certificate_code (certificate_code),
            KEY status (status),
            KEY gallery_id (gallery_id),
            KEY artist_profile_id (artist_profile_id),
            KEY collector_profile_id (collector_profile_id),
            KEY activity_type (activity_type),
            KEY artist_name (artist_name(100)),
            KEY created_at (created_at),
            KEY issue_date (issue_date),
            KEY artist_active_works (artist_profile_id, status, issue_date),
            KEY collector_active_works (collector_profile_id, status, issue_date)
        ) ENGINE=InnoDB {$charset_collate};";

        $event_sql = "CREATE TABLE {$events} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            certificate_id bigint(20) unsigned NOT NULL,
            event_type varchar(40) NOT NULL,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_payload longtext NULL,
            previous_hash char(64) NOT NULL DEFAULT '',
            event_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY certificate_id (certificate_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) ENGINE=InnoDB {$charset_collate};";

        $gallery_sql = "CREATE TABLE {$galleries} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            name_en varchar(255) NOT NULL DEFAULT '',
            slug varchar(100) NOT NULL,
            activity_type varchar(50) NOT NULL DEFAULT 'gallery',
            logo_url varchar(500) NOT NULL DEFAULT '',
            primary_color char(7) NOT NULL DEFAULT '#65513b',
            accent_color char(7) NOT NULL DEFAULT '#b29a68',
            certificate_title varchar(255) NOT NULL DEFAULT 'گواهی اصالت اثر هنری',
            certificate_title_en varchar(255) NOT NULL DEFAULT 'Certificate of Authenticity',
            footer text NULL,
            footer_en text NULL,
            template_style varchar(30) NOT NULL DEFAULT 'classic',
            show_logo_public tinyint(1) NOT NULL DEFAULT 1,
            show_artwork_title tinyint(1) NOT NULL DEFAULT 1,
            show_artwork_image_public tinyint(1) NOT NULL DEFAULT 1,
            show_artist_name tinyint(1) NOT NULL DEFAULT 1,
            show_medium tinyint(1) NOT NULL DEFAULT 1,
            show_year_created tinyint(1) NOT NULL DEFAULT 1,
            show_dimensions tinyint(1) NOT NULL DEFAULT 1,
            show_accession_number tinyint(1) NOT NULL DEFAULT 1,
            show_issuer tinyint(1) NOT NULL DEFAULT 1,
            show_issue_date tinyint(1) NOT NULL DEFAULT 1,
            show_description tinyint(1) NOT NULL DEFAULT 1,
            show_collector_public tinyint(1) NOT NULL DEFAULT 0,
            show_current_owner_public tinyint(1) NOT NULL DEFAULT 0,
            show_previous_owner_public tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            is_default tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY activity_type (activity_type),
            KEY status (status),
            KEY is_default (is_default)
        ) ENGINE=InnoDB {$charset_collate};";

        $profiles_sql = "CREATE TABLE {$profiles} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            profile_type varchar(20) NOT NULL DEFAULT 'artist',
            entity_kind varchar(20) NOT NULL DEFAULT 'person',
            display_name varchar(255) NOT NULL,
            display_name_en varchar(255) NOT NULL DEFAULT '',
            slug varchar(180) NOT NULL,
            tagline varchar(500) NOT NULL DEFAULT '',
            tagline_en varchar(500) NOT NULL DEFAULT '',
            biography longtext NULL,
            biography_en longtext NULL,
            profile_image_url varchar(500) NOT NULL DEFAULT '',
            profile_image_id bigint(20) unsigned NOT NULL DEFAULT 0,
            cover_image_url varchar(500) NOT NULL DEFAULT '',
            cover_image_id bigint(20) unsigned NOT NULL DEFAULT 0,
            seo_title varchar(255) NOT NULL DEFAULT '',
            seo_description varchar(500) NOT NULL DEFAULT '',
            birth_year varchar(20) NOT NULL DEFAULT '',
            death_year varchar(20) NOT NULL DEFAULT '',
            founded_year varchar(20) NOT NULL DEFAULT '',
            nationality varchar(255) NOT NULL DEFAULT '',
            nationality_en varchar(255) NOT NULL DEFAULT '',
            country varchar(255) NOT NULL DEFAULT '',
            country_en varchar(255) NOT NULL DEFAULT '',
            city varchar(255) NOT NULL DEFAULT '',
            city_en varchar(255) NOT NULL DEFAULT '',
            disciplines text NULL,
            disciplines_en text NULL,
            collection_focus text NULL,
            collection_focus_en text NULL,
            education text NULL,
            education_en text NULL,
            awards text NULL,
            awards_en text NULL,
            exhibitions text NULL,
            exhibitions_en text NULL,
            website_url varchar(500) NOT NULL DEFAULT '',
            portfolio_url varchar(500) NOT NULL DEFAULT '',
            contact_email varchar(190) NOT NULL DEFAULT '',
            contact_phone varchar(80) NOT NULL DEFAULT '',
            instagram_url varchar(500) NOT NULL DEFAULT '',
            linkedin_url varchar(500) NOT NULL DEFAULT '',
            facebook_url varchar(500) NOT NULL DEFAULT '',
            other_social_url varchar(500) NOT NULL DEFAULT '',
            show_email_public tinyint(1) NOT NULL DEFAULT 0,
            show_phone_public tinyint(1) NOT NULL DEFAULT 0,
            show_location_public tinyint(1) NOT NULL DEFAULT 1,
            show_works_public tinyint(1) NOT NULL DEFAULT 1,
            is_verified tinyint(1) NOT NULL DEFAULT 0,
            is_featured tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY profile_type (profile_type),
            KEY entity_kind (entity_kind),
            KEY status (status),
            KEY is_verified (is_verified),
            KEY is_featured (is_featured),
            KEY public_listing (status, profile_type, is_featured, is_verified, display_name(80))
        ) ENGINE=InnoDB {$charset_collate};";

        dbDelta($certificate_sql);
        dbDelta($event_sql);
        dbDelta($gallery_sql);
        dbDelta($profiles_sql);

        $cert_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($certificates)));
        $events_exist = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($events)));
        $galleries_exist = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($galleries)));
        $profiles_exist = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($profiles)));
        if ($cert_exists !== $certificates || $events_exist !== $events || $galleries_exist !== $galleries || $profiles_exist !== $profiles) {
            return false;
        }

        self::ensure_signing_key();
        if (class_exists('WP_COA_Pro_Galleries')) {
            WP_COA_Pro_Galleries::ensure_default_profile();
        }
        $administrator = get_role('administrator');
        if ($administrator) {
            $administrator->add_cap('manage_coa_certificates');
        }
        update_option('wp_coa_pro_db_version', WP_COA_PRO_DB_VERSION, false);
        update_option('wp_coa_pro_version', WP_COA_PRO_VERSION, false);
        return true;
    }

    public static function maybe_upgrade() {
        $db_version = (string) get_option('wp_coa_pro_db_version', '0');
        $plugin_version = (string) get_option('wp_coa_pro_version', '0');
        if (version_compare($db_version, WP_COA_PRO_DB_VERSION, '<') || version_compare($plugin_version, WP_COA_PRO_VERSION, '<')) {
            self::install();
        }
    }

    public static function ensure_signing_key() {
        if (defined('WP_COA_PRO_SIGNING_KEY') && is_string(WP_COA_PRO_SIGNING_KEY) && strlen(WP_COA_PRO_SIGNING_KEY) >= 32) {
            return;
        }
        if (get_option('wp_coa_pro_signing_key', '') !== '') {
            return;
        }
        try {
            $key = bin2hex(random_bytes(32));
        } catch (Exception $exception) {
            $key = wp_generate_password(64, true, true);
        }
        add_option('wp_coa_pro_signing_key', $key, '', false);
    }
}
