<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Database
{
    public static function create_tables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = [];

        // Artists
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_artists (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ulid CHAR(26) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            first_name VARCHAR(100) NOT NULL DEFAULT '',
            last_name VARCHAR(100) NOT NULL DEFAULT '',
            display_name VARCHAR(200) NOT NULL DEFAULT '',
            nationality VARCHAR(120) NULL,
            artistic_statement LONGTEXT NULL,
            slug VARCHAR(200) NOT NULL DEFAULT '',
            national_id VARCHAR(20) NULL,
            email VARCHAR(150) NULL,
            phone VARCHAR(30) NULL,
            bio LONGTEXT NULL,
            avatar_url VARCHAR(500) NULL,
            website VARCHAR(300) NULL,
            signature_hash CHAR(64) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ulid (ulid),
            UNIQUE KEY slug (slug),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset;";

        // Galleries
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_galleries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ulid CHAR(26) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL DEFAULT '',
            slug VARCHAR(200) NOT NULL DEFAULT '',
            email VARCHAR(150) NULL,
            phone VARCHAR(30) NULL,
            address TEXT NULL,
            city VARCHAR(100) NULL,
            country VARCHAR(100) NULL,
            about LONGTEXT NULL,
            visitor_info TEXT NULL,
            logo_url VARCHAR(500) NULL,
            website VARCHAR(300) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ulid (ulid),
            UNIQUE KEY slug (slug),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset;";

        // Curators
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_curators (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ulid CHAR(26) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            display_name VARCHAR(200) NOT NULL DEFAULT '',
            slug VARCHAR(200) NOT NULL DEFAULT '',
            bio LONGTEXT NULL,
            specialties TEXT NULL,
            curatorial_statement LONGTEXT NULL,
            affiliation VARCHAR(190) NULL,
            education TEXT NULL,
            credentials TEXT NULL,
            languages VARCHAR(255) NULL,
            years_experience SMALLINT UNSIGNED NULL,
            email VARCHAR(150) NULL,
            phone VARCHAR(30) NULL,
            website VARCHAR(300) NULL,
            instagram_url VARCHAR(300) NULL,
            linkedin_url VARCHAR(300) NULL,
            avatar_url VARCHAR(500) NULL,
            cover_url VARCHAR(500) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ulid (ulid),
            UNIQUE KEY slug (slug),
            KEY user_id (user_id),
            KEY status_display (status, display_name)
        ) $charset;";

        // Collectors
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_collectors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ulid CHAR(26) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            display_name VARCHAR(200) NOT NULL DEFAULT '',
            slug VARCHAR(200) NOT NULL DEFAULT '',
            collection_name VARCHAR(255) NULL,
            bio LONGTEXT NULL,
            is_public TINYINT(1) NOT NULL DEFAULT 0,
            avatar_url VARCHAR(500) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ulid (ulid),
            UNIQUE KEY slug (slug),
            KEY user_id (user_id)
        ) $charset;";

        // Buyers (lightweight)
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_buyers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ulid CHAR(26) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            display_name VARCHAR(200) NOT NULL DEFAULT '',
            email VARCHAR(150) NULL,
            phone VARCHAR(30) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ulid (ulid),
            KEY user_id (user_id)
        ) $charset;";

        // Artworks
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_artworks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ulid CHAR(26) NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL DEFAULT '',
            artist_id BIGINT UNSIGNED NOT NULL,
            gallery_id BIGINT UNSIGNED NULL,
            medium VARCHAR(150) NULL,
            year_created INT NULL,
            dimensions VARCHAR(120) NULL,
            material VARCHAR(255) NULL,
            condition_note TEXT NULL,
            provenance LONGTEXT NULL,
            description LONGTEXT NULL,
            image_url VARCHAR(500) NULL,
            image_hash CHAR(64) NULL,
            current_owner_type VARCHAR(30) NULL,
            current_owner_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ulid (ulid),
            KEY artist_id (artist_id),
            KEY gallery_id (gallery_id),
            KEY status (status),
            KEY artist_status (artist_id, status),
            KEY gallery_status (gallery_id, status),
            KEY status_id (status, id),
            KEY owner (current_owner_type, current_owner_id)
        ) $charset;";

        // Certificates
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_certificates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ulid CHAR(26) NOT NULL,
            serial_number VARCHAR(50) NOT NULL,
            artwork_id BIGINT UNSIGNED NULL,
            issued_to_type VARCHAR(30) NOT NULL DEFAULT 'person',
            issued_to_id BIGINT UNSIGNED NULL,
            issued_to_name VARCHAR(255) NOT NULL DEFAULT '',
            issued_by BIGINT UNSIGNED NULL,
            issued_at_utc DATETIME NOT NULL,
            expires_at_utc DATETIME NULL,
            hmac_signature CHAR(64) NULL,
            coa_id BIGINT UNSIGNED NULL,
            qr_code_data TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            revoked_at_utc DATETIME NULL,
            revoked_reason TEXT NULL,
            notes TEXT NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ulid (ulid),
            UNIQUE KEY serial_number (serial_number),
            UNIQUE KEY coa_id (coa_id),
            KEY artwork_id (artwork_id),
            KEY status (status),
            KEY artwork_status (artwork_id, status)
        ) $charset;";

        // Exhibitions
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_exhibitions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ulid CHAR(26) NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL DEFAULT '',
            gallery_id BIGINT UNSIGNED NULL,
            curator_id BIGINT UNSIGNED NULL,
            curator_name VARCHAR(200) NULL,
            admission_info VARCHAR(255) NULL,
            description LONGTEXT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ulid (ulid),
            KEY gallery_id (gallery_id),
            KEY curator_id (curator_id),
            KEY curator_status_date (curator_id, status, start_date)
        ) $charset;";

        // Exhibition ↔ Artwork
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_exhibition_artworks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exhibition_id BIGINT UNSIGNED NOT NULL,
            artwork_id BIGINT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY exhibition_artwork (exhibition_id, artwork_id)
        ) $charset;";

        // Ownership history
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_ownership_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            artwork_id BIGINT UNSIGNED NOT NULL,
            from_type VARCHAR(30) NULL,
            from_id BIGINT UNSIGNED NULL,
            to_type VARCHAR(30) NOT NULL,
            to_id BIGINT UNSIGNED NULL,
            to_name VARCHAR(255) NULL,
            certificate_id BIGINT UNSIGNED NULL,
            transferred_at_utc DATETIME NOT NULL,
            notes TEXT NULL,
            PRIMARY KEY (id),
            KEY artwork_id (artwork_id)
        ) $charset;";

        // Audit trail
        $tables[] = "CREATE TABLE {$wpdb->prefix}art_audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(50) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            meta LONGTEXT NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY entity (entity_type, entity_id),
            KEY created (created_at_utc)
        ) $charset;";

        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'art_' . $name;
    }
}
