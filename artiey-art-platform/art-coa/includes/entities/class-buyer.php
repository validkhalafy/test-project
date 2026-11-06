<?php
declare(strict_types=1);

namespace WPArtCOA\Entities;

use WPArtCOA\Audit;
use WPArtCOA\Database;
use WPArtCOA\Ulid;

defined('ABSPATH') || exit;

final class Buyer
{
    public static function create(array $data): array
    {
        global $wpdb;

        $name = sanitize_text_field($data['display_name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'message' => 'Name is required'];
        }

        $result = $wpdb->insert(
            Database::table('buyers'),
            [
                'ulid'           => Ulid::generate(),
                'user_id'        => !empty($data['user_id']) ? absint($data['user_id']) : null,
                'display_name'   => $name,
                'email'          => sanitize_email($data['email'] ?? ''),
                'phone'          => sanitize_text_field($data['phone'] ?? ''),
                'status'         => 'active',
                'created_at_utc' => gmdate('Y-m-d H:i:s'),
            ]
        );

        if (!$result) {
            return ['success' => false, 'message' => $wpdb->last_error ?: 'Error'];
        }

        $id = (int) $wpdb->insert_id;
        Audit::log('buyer', $id, 'created');
        return ['success' => true, 'id' => $id, 'slug' => ''];
    }

    public static function get_by_user(int $user_id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('buyers') . ' WHERE user_id = %d LIMIT 1',
            $user_id
        )) ?: null;
    }
}
