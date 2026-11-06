<?php
declare(strict_types=1);

namespace WPArtCOA\Entities;

use WPArtCOA\Audit;
use WPArtCOA\Database;
use WPArtCOA\Media;
use WPArtCOA\Ulid;

defined('ABSPATH') || exit;

final class Curator
{
    public static function create(array $data): array
    {
        global $wpdb;

        $name = sanitize_text_field($data['display_name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'message' => 'نام کیوریتور الزامی است.'];
        }

        $slug = \Artiey_AET_Slug::from(($data['slug'] ?? '') ?: $name, 'curator');
        $base = $slug;
        $i = 1;
        while ($wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Database::table('curators') . ' WHERE slug = %s',
            $slug
        ))) {
            $slug = $base . '-' . $i++;
        }

        $result = $wpdb->insert(
            Database::table('curators'),
            [
                'ulid'           => Ulid::generate(),
                'user_id'        => !empty($data['user_id']) ? absint($data['user_id']) : null,
                'display_name'   => $name,
                'slug'           => $slug,
                'bio'            => wp_kses_post($data['bio'] ?? ''),
                'specialties'    => sanitize_textarea_field($data['specialties'] ?? ''),
                'curatorial_statement' => sanitize_textarea_field($data['curatorial_statement'] ?? ''),
                'affiliation'    => sanitize_text_field($data['affiliation'] ?? ''),
                'education'      => sanitize_textarea_field($data['education'] ?? ''),
                'credentials'    => sanitize_textarea_field($data['credentials'] ?? ''),
                'languages'      => sanitize_text_field($data['languages'] ?? ''),
                'years_experience' => !empty($data['years_experience']) ? min(80, absint($data['years_experience'])) : null,
                'email'          => sanitize_email($data['email'] ?? ''),
                'phone'          => sanitize_text_field($data['phone'] ?? ''),
                'website'        => esc_url_raw($data['website'] ?? ''),
                'instagram_url'  => esc_url_raw($data['instagram_url'] ?? ''),
                'linkedin_url'   => esc_url_raw($data['linkedin_url'] ?? ''),
                'status'         => 'active',
                'created_at_utc' => gmdate('Y-m-d H:i:s'),
            ] + Media::columns($data, 'avatar') + Media::columns($data, 'cover')
        );

        if (!$result) {
            return ['success' => false, 'message' => $wpdb->last_error ?: 'خطا.'];
        }

        $id = (int) $wpdb->insert_id;
        Audit::log('curator', $id, 'created');
        return ['success' => true, 'id' => $id, 'slug' => $slug];
    }

    public static function all(int $limit = 100): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Database::table('curators') . ' ORDER BY id DESC LIMIT %d',
                $limit
            )
        ) ?: [];
    }

    public static function update(int $id, array $data): array
    {
        return Updater::apply('curator', 'curators', $id, $data, [
            'display_name' => 'text!',
            'specialties'  => 'textarea',
            'curatorial_statement' => 'textarea',
            'affiliation' => 'text',
            'education' => 'textarea',
            'credentials' => 'textarea',
            'languages' => 'text',
            'years_experience' => 'int',
            'email'        => 'email',
            'phone' => 'text',
            'website' => 'url',
            'instagram_url' => 'url',
            'linkedin_url' => 'url',
            'bio'          => 'html',
        ], ['avatar', 'cover']);
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('curators') . ' WHERE id = %d',
            $id
        )) ?: null;
    }

    public static function get_by_slug(string $slug): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('curators') . ' WHERE slug = %s LIMIT 1',
            sanitize_title($slug)
        )) ?: null;
    }
}
