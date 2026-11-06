<?php
declare(strict_types=1);

namespace WPArtCOA\Entities;

use WPArtCOA\Audit;
use WPArtCOA\Database;
use WPArtCOA\I18n;
use WPArtCOA\Media;
use WPArtCOA\Ulid;

defined('ABSPATH') || exit;

final class Artwork
{
    private const STATUSES = ['pending', 'approved', 'published', 'rejected', 'draft', 'sold'];

    public static function create(array $data): array
    {
        global $wpdb;

        $title     = sanitize_text_field($data['title'] ?? '');
        $artist_id = absint($data['artist_id'] ?? 0);

        if ($title === '' || !$artist_id) {
            return ['success' => false, 'message' => 'عنوان و هنرمند الزامی است.'];
        }

        $slug = \Artiey_AET_Slug::from(($data['slug'] ?? '') ?: $title, 'artwork');
        $slug = self::unique_slug($slug !== '' ? $slug : 'artwork');
        $now  = gmdate('Y-m-d H:i:s');

        $status = sanitize_key($data['status'] ?? 'pending');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }

        $result = $wpdb->insert(
            Database::table('artworks'),
            [
                'ulid'           => Ulid::generate(),
                'title'          => $title,
                'slug'           => $slug,
                'artist_id'      => $artist_id,
                'gallery_id'     => !empty($data['gallery_id']) ? absint($data['gallery_id']) : null,
                'medium'         => sanitize_text_field($data['medium'] ?? ''),
                'year_created'   => !empty($data['year_created']) ? absint($data['year_created']) : null,
                'dimensions'     => sanitize_text_field($data['dimensions'] ?? ''),
                'description'    => wp_kses_post($data['description'] ?? ''),
                'price'          => isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
                'currency'       => sanitize_text_field($data['currency'] ?? 'IRR') ?: 'IRR',
                'for_sale'       => !empty($data['for_sale']) ? 1 : 0,
                'sale_note'      => sanitize_textarea_field($data['sale_note'] ?? ''),
                'status'         => $status,
                'gallery_ids'    => Media::sanitize_ids($data['gallery_ids'] ?? ''),
                'created_at_utc' => $now,
            ] + Media::columns($data, 'image')
        );

        if (!$result) {
            return ['success' => false, 'message' => $wpdb->last_error ?: 'خطا در ذخیره.'];
        }

        $id = (int) $wpdb->insert_id;
        Audit::log('artwork', $id, 'created');
        self::flush($id);

        return ['success' => true, 'id' => $id, 'slug' => $slug];
    }

    /** Partial update: only keys present in $data are touched. */
    public static function update(int $id, array $data): array
    {
        global $wpdb;
        if (!self::get($id)) {
            return ['success' => false, 'message' => I18n::t('not_found')];
        }

        $f = [];
        if (array_key_exists('title', $data)) {
            $title = sanitize_text_field((string) $data['title']);
            if ($title === '') {
                return ['success' => false, 'message' => 'عنوان الزامی است.'];
            }
            $f['title'] = $title;
        }
        if (!empty($data['artist_id'])) {
            $f['artist_id'] = absint($data['artist_id']);
        }
        if (array_key_exists('gallery_id', $data)) {
            $f['gallery_id'] = absint($data['gallery_id']) ?: null;
        }
        foreach (['medium', 'dimensions'] as $k) {
            if (array_key_exists($k, $data)) {
                $f[$k] = sanitize_text_field((string) $data[$k]);
            }
        }
        if (array_key_exists('year_created', $data)) {
            $f['year_created'] = absint($data['year_created']) ?: null;
        }
        if (array_key_exists('description', $data)) {
            $f['description'] = wp_kses_post((string) $data['description']);
        }
        if (array_key_exists('price', $data)) {
            $f['price'] = $data['price'] !== '' ? (float) $data['price'] : null;
        }
        if (array_key_exists('currency', $data)) {
            $f['currency'] = sanitize_text_field((string) $data['currency']) ?: 'IRR';
        }
        if (array_key_exists('for_sale', $data)) {
            $f['for_sale'] = !empty($data['for_sale']) ? 1 : 0;
        }
        if (array_key_exists('sale_note', $data)) {
            $f['sale_note'] = sanitize_textarea_field((string) $data['sale_note']);
        }
        if (array_key_exists('status', $data)) {
            $st = sanitize_key((string) $data['status']);
            if (in_array($st, self::STATUSES, true)) {
                $f['status'] = $st;
            }
        }
        if (array_key_exists('gallery_ids', $data)) {
            $f['gallery_ids'] = Media::sanitize_ids($data['gallery_ids']);
        }
        if (Media::has($data, 'image')) {
            $f += Media::columns($data, 'image');
        }
        if (!$f) {
            return ['success' => true, 'id' => $id];
        }

        $f['updated_at_utc'] = gmdate('Y-m-d H:i:s');
        $ok = $wpdb->update(Database::table('artworks'), $f, ['id' => $id]);
        if ($ok === false) {
            return ['success' => false, 'message' => $wpdb->last_error ?: 'خطا در ذخیره.'];
        }
        Audit::log('artwork', $id, 'updated');
        self::flush($id);
        return ['success' => true, 'id' => $id];
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT a.*, ar.display_name AS artist_name, ar.first_name, ar.last_name, ar.slug AS artist_slug
             FROM ' . Database::table('artworks') . ' a
             LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
             WHERE a.id = %d',
            $id
        )) ?: null;
    }

    public static function get_by_slug(string $slug): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT a.*, ar.display_name AS artist_name, ar.first_name, ar.last_name, ar.slug AS artist_slug,
                    g.name AS gallery_name, g.slug AS gallery_slug
             FROM ' . Database::table('artworks') . ' a
             LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = a.gallery_id
             WHERE a.slug = %s',
            sanitize_title($slug)
        )) ?: null;
    }

    public static function all(int $limit = 100): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT a.*, ar.display_name AS artist_name, ar.first_name, ar.last_name
                 FROM ' . Database::table('artworks') . ' a
                 LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                 ORDER BY a.id DESC LIMIT %d',
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
            'SELECT id FROM ' . Database::table('artworks') . ' WHERE slug = %s',
            $slug
        ))) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public static function set_status(int $id, string $status): bool
    {
        $status = sanitize_key($status);
        if ($id <= 0 || !in_array($status, self::STATUSES, true)) {
            return false;
        }
        global $wpdb;
        $ok = false !== $wpdb->update(
            Database::table('artworks'),
            ['status' => $status, 'updated_at_utc' => gmdate('Y-m-d H:i:s')],
            ['id' => $id]
        );
        if ($ok) {
            Audit::log('artwork', $id, 'status_' . $status);
            self::flush($id);
        }
        return (bool) $ok;
    }

    /** Statuses visible in public listings (sold works stay visible, flagged as sold). */
    public static function public_statuses(): array
    {
        return ['approved', 'published', 'active', 'sold'];
    }

    private static function flush(int $id = 0): void
    {
        if ($id > 0) {
            do_action('art_coa_artwork_changed', $id);
        }
        if (class_exists('\WPArtCOA\Cache')) {
            \WPArtCOA\Cache::flush_stats();
        }
    }
}
