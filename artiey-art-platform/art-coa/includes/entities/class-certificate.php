<?php
declare(strict_types=1);

namespace WPArtCOA\Entities;

use WPArtCOA\Database;
use WPArtCOA\Security;

defined('ABSPATH') || exit;

/**
 * Public certificate lookups. COA Pro is the only certificate engine: it issues, signs (integrity hash + event ledger),
 * revokes and transfers. This class never signs anything; it asks COA Pro to verify and returns a privacy-filtered view
 * (each gallery's show_* switches decide which fields are public).
 */
final class Certificate
{
    /**
     * @return array<string,mixed> ['valid'=>bool, 'message'?, 'data'?]
     */
    public static function verify(string $serial): array
    {
        $code = strtoupper(Security::sanitize_serial($serial));
        $not_found = ['valid' => false, 'message' => 'گواهی با این سریال یافت نشد.'];
        // Honour the COA Pro "Enable public lookup" switch: this endpoint must not expose what the COA page hides.
        if (class_exists('WP_COA_Pro_Admin')) {
            $coa_settings = (array) \WP_COA_Pro_Admin::get_settings();
            if (array_key_exists('enable_public_lookup', $coa_settings) && empty($coa_settings['enable_public_lookup'])) {
                return ['valid' => false, 'message' => 'استعلام عمومی گواهی غیرفعال است.'];
            }
        }
        if ($code === '' || !class_exists('WP_COA_Pro_Repository') || !class_exists('WP_COA_Pro_Signature') || !class_exists('WP_COA_Pro_Ledger')) {
            return $not_found;
        }
        try {
            $sig    = new \WP_COA_Pro_Signature();
            $ledger = new \WP_COA_Pro_Ledger();
            $repo   = new \WP_COA_Pro_Repository($sig, $ledger);
            $r      = $repo->get_by_code($code);
        } catch (\Throwable $e) {
            return $not_found;
        }
        if (!$r) {
            return $not_found;
        }
        if ($r->status !== 'active') {
            return ['valid' => false, 'message' => 'این گواهی باطل شده است.', 'status' => (string) $r->status];
        }
        if (!$sig->verify($r) || $ledger->verify_certificate_state($r) === false) {
            return ['valid' => false, 'message' => 'امضای دیجیتال معتبر نیست (احتمال دستکاری).'];
        }

        $settings = [];
        if (class_exists('WP_COA_Pro_Galleries') && class_exists('WP_COA_Pro_Admin')) {
            $settings = (array) \WP_COA_Pro_Galleries::settings_for_record($r, \WP_COA_Pro_Admin::get_settings());
        }
        $show = static fn(string $flag): bool => !empty($settings[$flag]);

        $image = '';
        if ($show('show_artwork_image_public') && !empty($r->artwork_image_id)) {
            $image = (string) wp_get_attachment_image_url((int) $r->artwork_image_id, 'large');
        }
        $owner = '';
        if ($show('show_current_owner_public') && !empty($r->current_owner_name)) {
            $owner = (string) $r->current_owner_name;
        } elseif ($show('show_collector_public') && !empty($r->collector_name)) {
            $owner = (string) $r->collector_name;
        }
        $hash = (string) ($r->integrity_hash ?? '');

        return [
            'valid' => true,
            'data'  => [
                'serial_number'    => (string) $r->certificate_code,
                'artwork_id'       => 0,
                'artwork_title'    => $show('show_artwork_title') ? (string) $r->artwork_title : '',
                'year_created'     => $show('show_year_created') ? $r->year_created : '',
                'medium'           => $show('show_medium') ? (string) $r->medium : '',
                'dimensions'       => $show('show_dimensions') ? (string) $r->dimensions : '',
                'image_url'        => $image,
                'artist_name'      => $show('show_artist_name') ? (string) $r->artist_name : '',
                'issued_to'        => $owner,
                'issued_to_name'   => $owner,
                'issued_at_utc'    => $show('show_issue_date') ? (string) $r->issue_date : '',
                'expires_at_utc'   => null,
                'status'           => (string) $r->status,
                'qr_code_data'     => home_url('/art-coa/certificate/' . rawurlencode((string) $r->certificate_code)),
                'hmac_fingerprint' => $hash !== '' ? substr($hash, 0, 16) . '…' . substr($hash, -8) : '',
                'source'           => 'coa-pro',
            ],
        ];
    }

    /** Mirrored (derived) row by serial; used only for linking / listings, never for trust decisions. */
    public static function get_by_serial(string $serial): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('certificates') . ' WHERE serial_number = %s',
            sanitize_text_field($serial)
        )) ?: null;
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('certificates') . ' WHERE id = %d',
            $id
        )) ?: null;
    }
}
