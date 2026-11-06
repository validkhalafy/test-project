<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * COA Pro is the single certificate engine (signature, ledger, revocation, transfer, print).
 * `art_certificates` is only a derived index of it, so the Art COA pages (artist/gallery/artwork profiles, stats, sitemap,
 * ownership chain) can keep querying by artwork. Nothing is ever signed or verified from this table.
 */
final class CertificateMirror
{
    public static function init(): void
    {
        add_action('wp_coa_pro_certificate_issued', [self::class, 'on_issued'], 20, 2);
        add_action('wp_coa_pro_certificate_revoked', [self::class, 'on_revoked'], 10, 2);
        add_action('wp_coa_pro_certificate_transferred', [self::class, 'on_transferred'], 10, 2);
    }

    private static function table(): string
    {
        return Database::table('certificates');
    }

    /** One-time schema step (called from Media::migrate): the index no longer needs an artwork or an HMAC. */
    public static function migrate(): void
    {
        global $wpdb;
        $t = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t)))) {
            return;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $cols = $wpdb->get_results("SHOW COLUMNS FROM `{$t}`", OBJECT_K) ?: [];
        if (!isset($cols['coa_id'])) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("ALTER TABLE `{$t}` ADD COLUMN `coa_id` BIGINT UNSIGNED NULL, ADD UNIQUE KEY `coa_id` (`coa_id`)");
        }
        if (isset($cols['artwork_id']) && strtoupper((string) $cols['artwork_id']->Null) === 'NO') {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("ALTER TABLE `{$t}` MODIFY `artwork_id` BIGINT UNSIGNED NULL");
        }
        if (isset($cols['hmac_signature']) && strtoupper((string) $cols['hmac_signature']->Null) === 'NO') {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("ALTER TABLE `{$t}` MODIFY `hmac_signature` CHAR(64) NULL");
        }
        self::sync_all();
    }

    /* ------------------------------------------------------------------ events */

    public static function on_issued($record, $data = []): void
    {
        if (!is_object($record) || empty($record->id)) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- COA verified the nonce for this request.
        $post_ref = isset($_POST['aet_artwork_id']) ? absint(wp_unslash($_POST['aet_artwork_id'])) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $art_ref  = isset($_POST['art_artwork_id']) ? absint(wp_unslash($_POST['art_artwork_id'])) : 0;
        $artwork_id = 0;
        if ($post_ref && class_exists(ContentSync::class) && get_post_type($post_ref) === 'artiey_artwork') {
            $artwork_id = ContentSync::sync_post($post_ref);
        }
        if (!$artwork_id && $art_ref) {
            $artwork_id = self::artwork_exists($art_ref) ? $art_ref : 0;
        }
        self::upsert($record, $artwork_id ?: null);
    }

    public static function on_revoked($coa_id, $reason = ''): void
    {
        global $wpdb;
        $wpdb->update(self::table(), [
            'status'         => 'revoked',
            'revoked_at_utc' => gmdate('Y-m-d H:i:s'),
            'revoked_reason' => sanitize_textarea_field((string) $reason),
        ], ['coa_id' => (int) $coa_id]);
        Cache::flush_stats();
    }

    public static function on_transferred($coa_id, $record = null): void
    {
        if (!is_object($record)) {
            return;
        }
        global $wpdb;
        $wpdb->update(self::table(), ['issued_to_name' => self::owner($record)], ['coa_id' => (int) $coa_id]);
        Cache::flush_stats();
    }

    /* ------------------------------------------------------------------ writes */

    /** @param object $r a COA Pro certificate record */
    public static function upsert(object $r, ?int $artwork_id = null): int
    {
        global $wpdb;
        $t   = self::table();
        $row = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$t}` WHERE coa_id = %d", (int) $r->id)); // phpcs:ignore
        $issued = !empty($r->issue_date) ? (string) $r->issue_date : gmdate('Y-m-d H:i:s');
        $data = [
            'serial_number'  => (string) $r->certificate_code,
            'issued_to_name' => self::owner($r),
            'issued_at_utc'  => $issued,
            'status'         => (string) ($r->status ?? 'active'),
        ];
        if ($artwork_id) {
            $data['artwork_id'] = $artwork_id;
        }
        if ($row) {
            $wpdb->update($t, $data, ['id' => $row]);
        } else {
            $data += [
                'ulid'           => Ulid::generate(),
                'coa_id'         => (int) $r->id,
                'issued_to_type' => 'person',
                'created_at_utc' => gmdate('Y-m-d H:i:s'),
            ];
            if (false === $wpdb->insert($t, $data)) {
                return 0;
            }
            $row = (int) $wpdb->insert_id;
        }
        Cache::flush_stats();
        do_action('wp_art_coa_certificate_issued', $row, (string) $r->certificate_code, (int) $artwork_id);
        return $row;
    }

    /** Attach a COA certificate to an Art COA artwork (admin action). */
    public static function link(int $coa_id, int $artwork_id): bool
    {
        if ($coa_id <= 0 || !self::artwork_exists($artwork_id) || !class_exists('WP_COA_Pro_Repository')) {
            return false;
        }
        $repo = new \WP_COA_Pro_Repository(new \WP_COA_Pro_Signature(), new \WP_COA_Pro_Ledger());
        $r = $repo->get_by_id($coa_id);
        if (!$r) {
            return false;
        }
        return self::upsert($r, $artwork_id) > 0;
    }

    /** Backfill / repair: index every COA certificate that is not mirrored yet. */
    public static function sync_all(): int
    {
        if (!class_exists('WP_COA_Pro_Repository')) {
            return 0;
        }
        global $wpdb;
        $coa_table = $wpdb->prefix . 'coa_certificates';
        $t = self::table();
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($coa_table)))) {
            return 0;
        }
        $rows = $wpdb->get_results("SELECT c.* FROM `{$coa_table}` c LEFT JOIN `{$t}` m ON m.coa_id = c.id WHERE m.id IS NULL ORDER BY c.id ASC LIMIT 500") ?: [];
        // phpcs:enable
        $n = 0;
        foreach ($rows as $r) {
            $n += self::upsert($r, null) ? 1 : 0;
        }
        return $n;
    }

    /* ------------------------------------------------------------------ helpers */

    private static function owner(object $r): string
    {
        foreach (['current_owner_name', 'collector_name', 'artist_name'] as $k) {
            if (!empty($r->{$k})) {
                return (string) $r->{$k};
            }
        }
        return '';
    }

    private static function artwork_exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Database::table('artworks') . ' WHERE id = %d', $id));
    }
}
