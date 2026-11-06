<?php
declare(strict_types=1);

namespace WPArtCOA\Entities;

use WPArtCOA\Audit;
use WPArtCOA\Database;
use WPArtCOA\Media;
use WPArtCOA\Ulid;

defined('ABSPATH') || exit;

final class Artist
{
    public static function create(array $data): array
    {
        $first = sanitize_text_field((string) ($data['first_name'] ?? ''));
        $last  = sanitize_text_field((string) ($data['last_name'] ?? ''));
        if ($first === '' || $last === '') {
            return ['success' => false, 'message' => 'نام و نام خانوادگی الزامی است.'];
        }
        if (!class_exists('\\WPArtCOA\\ContentBridge')) {
            return ['success' => false, 'message' => 'لایه یکپارچه‌سازی محتوا آماده نیست؛ هیچ رکورد جداگانه‌ای ساخته نشد.'];
        }
        $data['first_name'] = $first;
        $data['last_name'] = $last;
        $data['display_name'] = sanitize_text_field((string) ($data['display_name'] ?? '')) ?: trim($first . ' ' . $last);
        $result = \WPArtCOA\ContentBridge::save_from_form('artist', $data, 0);
        if (empty($result['success'])) {
            return $result;
        }
        return [
            'success' => true,
            'id' => absint($result['id'] ?? 0),
            'post_id' => absint($result['post_id'] ?? 0),
            'slug' => sanitize_title((string) ($result['slug'] ?? '')),
        ];
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('artists') . ' WHERE id = %d',
            $id
        )) ?: null;
        if ($row && class_exists('\WPArtCOA\ContentBridge')) {
            return \WPArtCOA\ContentBridge::canonical_artist_row($row);
        }
        return $row;
    }

    public static function get_by_slug(string $slug): ?object
    {
        global $wpdb;
        $slug = sanitize_title($slug);
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('artists') . ' WHERE slug = %s',
            $slug
        )) ?: null;
        if (!$row && $slug !== '' && function_exists('get_page_by_path') && class_exists('\WPArtCOA\ContentBridge')) {
            // If the mirror is temporarily stale, resolve the canonical post and rebuild/adopt its row.
            $post = get_page_by_path($slug, OBJECT, 'artiey_artist');
            if ($post instanceof \WP_Post) {
                $row_id = \WPArtCOA\ContentBridge::row_id_for_post('artists', (int) $post->ID);
                if ($row_id > 0) {
                    $row = $wpdb->get_row($wpdb->prepare(
                        'SELECT * FROM ' . Database::table('artists') . ' WHERE id = %d',
                        $row_id
                    )) ?: null;
                }
            }
        }
        if ($row && class_exists('\WPArtCOA\ContentBridge')) {
            return \WPArtCOA\ContentBridge::canonical_artist_row($row);
        }
        return $row;
    }

    public static function all(int $limit = 100): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Database::table('artists') . ' ORDER BY id DESC LIMIT %d',
                $limit
            )
        ) ?: [];
    }

    private static function unique_slug(string $slug): string
    {
        global $wpdb;
        $base = $slug;
        $i = 1;
        while ($wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Database::table('artists') . ' WHERE slug = %s',
            $slug
        ))) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public static function update(int $id, array $data): array
    {
        if (class_exists('\\WPArtCOA\\ContentBridge')) {
            return \WPArtCOA\ContentBridge::update_from_entity_row('artist', $id, $data);
        }
        return ['success' => false, 'message' => 'لایه یکپارچه‌سازی محتوا آماده نیست؛ به‌روزرسانی انجام نشد.'];
    }
}
