<?php
declare(strict_types=1);

namespace WPArtCOA\Entities;

use WPArtCOA\Audit;
use WPArtCOA\Database;
use WPArtCOA\Media;
use WPArtCOA\Ulid;

defined('ABSPATH') || exit;

final class Gallery
{
    public static function create(array $data): array
    {
        global $wpdb;

        $name = sanitize_text_field($data['name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'message' => 'نام گالری الزامی است.'];
        }

        $slug = \Artiey_AET_Slug::from(($data['slug'] ?? '') ?: $name, 'gallery');
        $slug = self::unique_slug($slug);
        $now  = gmdate('Y-m-d H:i:s');

        $result = $wpdb->insert(
            Database::table('galleries'),
            [
                'ulid'           => Ulid::generate(),
                'user_id'        => !empty($data['user_id']) ? absint($data['user_id']) : null,
                'name'           => $name,
                'slug'           => $slug,
                'email'          => sanitize_email($data['email'] ?? ''),
                'phone'          => sanitize_text_field($data['phone'] ?? ''),
                'address'        => sanitize_textarea_field($data['address'] ?? ''),
                'city'           => sanitize_text_field($data['city'] ?? ''),
                'country'        => sanitize_text_field($data['country'] ?? ''),
                'about'          => wp_kses_post($data['about'] ?? ''),
                'website'        => esc_url_raw($data['website'] ?? ''),
                'brand_color'    => sanitize_hex_color($data['brand_color'] ?? '') ?: null,
                'brand_color_2'  => sanitize_hex_color($data['brand_color_2'] ?? '') ?: null,
                'signature_url'  => esc_url_raw($data['signature_url'] ?? ''),
                'signature_name' => sanitize_text_field($data['signature_name'] ?? ''),
                'status'         => 'active',
                'created_at_utc' => $now,
            ] + Media::columns($data, 'logo') + Media::columns($data, 'cover')
        );

        if (!$result) {
            return ['success' => false, 'message' => $wpdb->last_error ?: 'خطا در ذخیره.'];
        }

        $id = (int) $wpdb->insert_id;
        Audit::log('gallery', $id, 'created');

        return ['success' => true, 'id' => $id, 'slug' => $slug];
    }

    public static function all(int $limit = 100): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Database::table('galleries') . ' ORDER BY id DESC LIMIT %d',
                $limit
            )
        ) ?: [];
    }

    public static function get_by_slug(string $slug): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('galleries') . ' WHERE slug = %s',
            sanitize_title($slug)
        )) ?: null;
    }

    private static function unique_slug(string $slug): string
    {
        global $wpdb;
        $base = $slug;
        $i = 1;
        while ($wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Database::table('galleries') . ' WHERE slug = %s',
            $slug
        ))) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public static function update(int $id, array $data): array
    {
        return Updater::apply('gallery', 'galleries', $id, $data, [
            'name'           => 'text!',
            'email'          => 'email',
            'phone'          => 'text',
            'address'        => 'textarea',
            'city'           => 'text',
            'country'        => 'text',
            'about'          => 'html',
            'website'        => 'url',
            'brand_color'    => 'color',
            'brand_color_2'  => 'color',
            'signature_url'  => 'url',
            'signature_name' => 'text',
        ], ['logo', 'cover']);
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('galleries') . ' WHERE id = %d',
            $id
        )) ?: null;
    }
}
