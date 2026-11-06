<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Stats
{
    public static function global(): array
    {
        $public_only = !current_user_can('manage_options');
        $key = 'global_stats_' . Cache::stats_version() . '_' . ($public_only ? 'public' : 'admin');
        return Cache::get($key, static function () use ($public_only): array {
            return self::global_uncached($public_only);
        }, 180);
    }

    public static function global_uncached(bool $public_only = false): array
    {
        global $wpdb;

        $artists      = Database::table('artists');
        $galleries    = Database::table('galleries');
        $artworks     = Database::table('artworks');
        $exhibitions  = Database::table('exhibitions');
        $certificates = Database::table('certificates');
        $collectors   = Database::table('collectors');
        $buyers       = Database::table('buyers');
        $history      = Database::table('ownership_history');
        $curators     = Database::table('curators');

        $artwork_public_where    = $public_only ? " WHERE status IN ('approved','published','active','sold')" : '';
        $exhibition_public_where = $public_only ? " WHERE status IN ('published','active')" : '';
        $owned_where = $public_only
            ? " WHERE status IN ('approved','published','active','sold') AND current_owner_id IS NOT NULL AND current_owner_id > 0"
            : ' WHERE current_owner_id IS NOT NULL AND current_owner_id > 0';
        $certificates_count = $public_only
            ? "(SELECT COUNT(*) FROM `{$certificates}` c INNER JOIN `{$artworks}` a ON a.id = c.artwork_id WHERE c.status = 'active' AND a.status IN ('approved','published','active','sold'))"
            : "(SELECT COUNT(*) FROM `{$certificates}`)";
        $active_certificates_count = $public_only
            ? $certificates_count
            : "(SELECT COUNT(*) FROM `{$certificates}` WHERE status = 'active')";

        // The admin dashboard previously issued a separate COUNT(*) request for each
        // metric. Scalar subqueries preserve the exact visibility rules while reducing
        // the request to a single database round-trip on a cold stats cache.
        $sql = "SELECT
            (SELECT COUNT(*) FROM `{$artists}` WHERE status = 'active') AS artists,
            (SELECT COUNT(*) FROM `{$galleries}` WHERE status = 'active') AS galleries,
            (SELECT COUNT(*) FROM `{$artworks}`{$artwork_public_where}) AS artworks,
            (SELECT COUNT(*) FROM `{$artworks}` WHERE status IN ('published','active')) AS artworks_published,
            " . ( $public_only ? '0' : "(SELECT COUNT(*) FROM `{$artworks}` WHERE status = 'draft')" ) . " AS artworks_draft,
            (SELECT COUNT(*) FROM `{$artworks}`{$owned_where}) AS artworks_owned,
            (SELECT COUNT(*) FROM `{$exhibitions}`{$exhibition_public_where}) AS exhibitions,
            {$certificates_count} AS certificates,
            {$active_certificates_count} AS active_certs,
            " . ( $public_only ? '0' : "(SELECT COUNT(*) FROM `{$collectors}`)" ) . " AS collectors,
            " . ( $public_only ? '0' : "(SELECT COUNT(*) FROM `{$buyers}`)" ) . " AS buyers,
            " . ( $public_only ? '0' : "(SELECT COUNT(*) FROM `{$history}`)" ) . " AS transfers,
            " . ( $public_only ? '0' : "(SELECT COUNT(*) FROM `{$history}`)" ) . " AS sales_total,
            " . ( $public_only ? '0' : "(SELECT COUNT(*) FROM `{$history}` WHERE transferred_at_utc >= %s)" ) . " AS sales_month,
            " . ( $public_only ? '0' : "(SELECT COUNT(*) FROM `{$history}` WHERE to_type = 'collector')" ) . " AS sales_to_collector,
            " . ( $public_only ? '0' : "(SELECT COUNT(*) FROM `{$history}` WHERE to_type = 'buyer')" ) . " AS sales_to_buyer,
            (SELECT COUNT(*) FROM `{$curators}`" . ( $public_only ? " WHERE status = 'active'" : '' ) . ') AS curators';

        $month_start = gmdate('Y-m-01 00:00:00');
        $row = $public_only ? $wpdb->get_row($sql) : $wpdb->get_row($wpdb->prepare($sql, $month_start));
        $keys = [
            'artists', 'galleries', 'artworks', 'artworks_published', 'artworks_draft',
            'artworks_owned', 'exhibitions', 'certificates', 'active_certs', 'collectors',
            'buyers', 'transfers', 'sales_total', 'sales_month', 'sales_to_collector',
            'sales_to_buyer', 'curators',
        ];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = isset($row->{$key}) ? (int) $row->{$key} : 0;
        }
        return $out;
    }

    public static function artist(int $id, bool $public_only = false): array
    {
        return Cache::get('artist_stats_' . Cache::stats_version() . '_' . ($public_only ? 'public_' : 'all_') . $id, static function () use ($id, $public_only): array {
            return self::artist_uncached($id, $public_only);
        }, 180);
    }

    private static function artist_uncached(int $id, bool $public_only): array
    {
        global $wpdb;
        $visible = $public_only ? " AND status IN ('approved','published','active','sold')" : '';
        $visible_a = $public_only ? " AND a.status IN ('approved','published','active','sold')" : '';
        $artworks = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('artworks') . ' WHERE artist_id = %d' . $visible,
            $id
        ));
        $certs = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('certificates') . ' c
             INNER JOIN ' . Database::table('artworks') . ' a ON a.id = c.artwork_id
             WHERE a.artist_id = %d' . ($public_only ? " AND c.status = 'active' AND a.status IN ('approved','published','active','sold')" : ''),
            $id
        ));
        $exhibitions = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(DISTINCT ea.exhibition_id) FROM ' . Database::table('exhibition_artworks') . ' ea
             INNER JOIN ' . Database::table('artworks') . ' a ON a.id = ea.artwork_id
             INNER JOIN ' . Database::table('exhibitions') . " e ON e.id = ea.exhibition_id " . ($public_only ? "AND e.status IN ('published','active')" : '') . '
             WHERE a.artist_id = %d' . $visible_a,
            $id
        ));
        // Fallback: exhibitions linked via subtitle/artist_label is weak; also count gallery links later
        $sales = $public_only ? 0 : (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('ownership_history') . ' oh
             INNER JOIN ' . Database::table('artworks') . ' a ON a.id = oh.artwork_id
             WHERE a.artist_id = %d' . $visible_a,
            $id
        ));
        return compact('artworks', 'certs', 'exhibitions', 'sales');
    }

    public static function gallery(int $id, bool $public_only = false): array
    {
        return Cache::get('gallery_stats_' . Cache::stats_version() . '_' . ($public_only ? 'public_' : 'all_') . $id, static function () use ($id, $public_only): array {
            return self::gallery_uncached($id, $public_only);
        }, 180);
    }

    private static function gallery_uncached(int $id, bool $public_only): array
    {
        global $wpdb;
        $visible = $public_only ? " AND status IN ('approved','published','active','sold')" : '';
        $artworks = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('artworks') . ' WHERE gallery_id = %d' . $visible,
            $id
        ));
        $exhibitions = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('exhibitions') . " WHERE gallery_id = %d" . ($public_only ? " AND status IN ('published','active')" : ''),
            $id
        ));
        $artists = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(DISTINCT artist_id) FROM ' . Database::table('artworks') . ' WHERE gallery_id = %d AND artist_id > 0' . $visible,
            $id
        ));
        $certs = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('certificates') . ' c
             INNER JOIN ' . Database::table('artworks') . ' a ON a.id = c.artwork_id
             WHERE a.gallery_id = %d' . ($public_only ? " AND c.status = 'active' AND a.status IN ('approved','published','active','sold')" : ''),
            $id
        ));
        return compact('artworks', 'exhibitions', 'artists', 'certs');
    }

    public static function collector(int $id, bool $public_only = false): array
    {
        return Cache::get('collector_stats_' . Cache::stats_version() . '_' . ($public_only ? 'public_' : 'all_') . $id, static function () use ($id, $public_only): array {
            return self::collector_uncached($id, $public_only);
        }, 180);
    }

    private static function collector_uncached(int $id, bool $public_only): array
    {
        global $wpdb;
        $visible = $public_only ? " AND status IN ('approved','published','active','sold')" : '';
        $owned = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . Database::table('artworks') . "
             WHERE current_owner_type = 'collector' AND current_owner_id = %d" . $visible,
            $id
        ));
        $transfers_in = $public_only ? 0 : (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('ownership_history') . ' oh' . ($public_only ? ' INNER JOIN ' . Database::table('artworks') . ' a ON a.id = oh.artwork_id' : '') . "
             WHERE oh.to_type = 'collector' AND oh.to_id = %d" . ($public_only ? " AND a.status IN ('approved','published','active','sold')" : ''),
            $id
        ));
        $certs = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('certificates') . ' c' . ($public_only ? ' INNER JOIN ' . Database::table('artworks') . ' a ON a.id = c.artwork_id' : '') . "
             WHERE c.issued_to_type = 'collector' AND c.issued_to_id = %d AND c.status = 'active'" . ($public_only ? " AND a.status IN ('approved','published','active','sold')" : ''),
            $id
        ));
        return [
            'artworks'    => $owned,
            'acquisitions'=> $transfers_in,
            'certificates'=> $certs,
        ];
    }

    public static function exhibition(int $id): array
    {
        global $wpdb;
        $artworks = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('exhibition_artworks') . ' WHERE exhibition_id = %d',
            $id
        ));
        return compact('artworks');
    }
}
