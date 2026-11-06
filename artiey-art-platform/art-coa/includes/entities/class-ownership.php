<?php
declare(strict_types=1);

namespace WPArtCOA\Entities;

use WPArtCOA\Audit;
use WPArtCOA\Database;

defined('ABSPATH') || exit;

final class Ownership
{
    public static function transfer(array $args): array
    {
        global $wpdb;

        $artwork_id = absint($args['artwork_id'] ?? 0);
        $to_type    = sanitize_key($args['to_type'] ?? 'person');
        $to_id      = !empty($args['to_id']) ? absint($args['to_id']) : null;
        $to_name    = sanitize_text_field($args['to_name'] ?? '');
        $cert_id    = !empty($args['certificate_id']) ? absint($args['certificate_id']) : null;
        $notes      = sanitize_textarea_field($args['notes'] ?? '');

        if (!$artwork_id || $to_name === '') {
            return ['success' => false, 'message' => 'اثر و نام مالک جدید الزامی است.'];
        }

        $art = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('artworks') . ' WHERE id = %d',
            $artwork_id
        ));
        if (!$art) {
            return ['success' => false, 'message' => 'اثر یافت نشد.'];
        }

        $now = gmdate('Y-m-d H:i:s');

        $wpdb->insert(Database::table('ownership_history'), [
            'artwork_id'         => $artwork_id,
            'from_type'          => $art->current_owner_type,
            'from_id'            => $art->current_owner_id,
            'to_type'            => $to_type,
            'to_id'              => $to_id,
            'to_name'            => $to_name,
            'certificate_id'     => $cert_id,
            'transferred_at_utc' => $now,
            'notes'              => $notes,
        ]);

        $wpdb->update(
            Database::table('artworks'),
            [
                'current_owner_type' => $to_type,
                'current_owner_id'   => $to_id,
                'updated_at_utc'     => $now,
            ],
            ['id' => $artwork_id]
        );

        Audit::log('artwork', $artwork_id, 'ownership_transfer', [
            'to_type' => $to_type,
            'to_name' => $to_name,
        ]);

        return ['success' => true, 'message' => 'مالکیت منتقل شد.'];
    }

    public static function link_exhibition_artwork(int $exhibition_id, int $artwork_id, int $sort = 0): array
    {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Database::table('exhibition_artworks') . ' WHERE exhibition_id = %d AND artwork_id = %d',
            $exhibition_id,
            $artwork_id
        ));
        if ($exists) {
            return ['success' => true, 'message' => 'قبلاً لینک شده.'];
        }
        $wpdb->insert(Database::table('exhibition_artworks'), [
            'exhibition_id' => $exhibition_id,
            'artwork_id'    => $artwork_id,
            'sort_order'    => $sort,
        ]);
        return ['success' => true];
    }
}
