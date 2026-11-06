<?php
declare(strict_types=1);

namespace WPArtCOA\Entities;

use WPArtCOA\Audit;
use WPArtCOA\Database;
use WPArtCOA\Media;
use WPArtCOA\Ulid;

defined('ABSPATH') || exit;

final class Collector
{
    public static function create(array $data): array
    {
        global $wpdb;

        $name = sanitize_text_field($data['display_name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'message' => 'نام مجموعه‌دار الزامی است.'];
        }

        $slug = \Artiey_AET_Slug::from(($data['slug'] ?? '') ?: $name, 'collector');
        $base = $slug;
        $i = 1;
        while ($wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Database::table('collectors') . ' WHERE slug = %s',
            $slug
        ))) {
            $slug = $base . '-' . $i++;
        }

        $result = $wpdb->insert(
            Database::table('collectors'),
            [
                'ulid'            => Ulid::generate(),
                'user_id'         => !empty($data['user_id']) ? absint($data['user_id']) : null,
                'display_name'    => $name,
                'slug'            => $slug,
                'collection_name' => sanitize_text_field($data['collection_name'] ?? ''),
                'bio'             => wp_kses_post($data['bio'] ?? ''),
                'is_public'       => !empty($data['is_public']) ? 1 : 0,
                'status'          => 'active',
                'created_at_utc'  => gmdate('Y-m-d H:i:s'),
            ] + Media::columns($data, 'avatar')
        );

        if (!$result) {
            return ['success' => false, 'message' => $wpdb->last_error ?: 'خطا.'];
        }

        $id = (int) $wpdb->insert_id;
        Audit::log('collector', $id, 'created');
        return ['success' => true, 'id' => $id, 'slug' => $slug];
    }

    public static function all(int $limit = 100): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Database::table('collectors') . ' ORDER BY id DESC LIMIT %d',
                $limit
            )
        ) ?: [];
    }

    public static function update(int $id, array $data): array
    {
        return Updater::apply('collector', 'collectors', $id, $data, [
            'display_name'    => 'text!',
            'collection_name' => 'text',
            'bio'             => 'html',
            'is_public'       => 'bool',
        ], ['avatar']);
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('collectors') . ' WHERE id = %d',
            $id
        )) ?: null;
    }
}
