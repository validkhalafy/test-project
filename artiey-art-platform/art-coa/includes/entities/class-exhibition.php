<?php
declare(strict_types=1);

namespace WPArtCOA\Entities;

use WPArtCOA\Audit;
use WPArtCOA\ContentSync;
use WPArtCOA\Database;
use WPArtCOA\Media;
use WPArtCOA\Ulid;

defined('ABSPATH') || exit;

final class Exhibition
{
    private static bool $content_link_schema_checked = false;

    /** event_type: exhibition | artfair | auction */
    public static function create(array $data): array
    {
        global $wpdb;

        $title = sanitize_text_field($data['title'] ?? '');
        if ($title === '') {
            return ['success' => false, 'message' => 'عنوان الزامی است.'];
        }

        $slug = \Artiey_AET_Slug::from(($data['slug'] ?? '') ?: $title, 'exhibition');
        $slug = self::unique_slug($slug);
        $now  = gmdate('Y-m-d H:i:s');

        $event_type = sanitize_key($data['event_type'] ?? 'exhibition');
        if (!in_array($event_type, ['exhibition', 'artfair', 'auction'], true)) {
            $event_type = 'exhibition';
        }

        // Ensure event_type column exists (soft migration)
        self::maybe_add_columns();

        $result = $wpdb->insert(
            Database::table('exhibitions'),
            [
                'ulid'           => Ulid::generate(),
                'title'          => $title,
                'slug'           => $slug,
                'gallery_id'     => !empty($data['gallery_id']) ? absint($data['gallery_id']) : null,
                'curator_id'     => !empty($data['curator_id']) ? absint($data['curator_id']) : null,
                'description'    => wp_kses_post($data['description'] ?? ''),
                'start_date'     => self::normalize_date($data['start_date'] ?? null),
                'end_date'       => self::normalize_date($data['end_date'] ?? null),
                'status'         => self::clean_status($data['status'] ?? 'published'),
                'gallery_ids'    => Media::sanitize_ids($data['gallery_ids'] ?? ''),
                'created_at_utc' => $now,
                'updated_at_utc' => $now,
            ] + Media::columns($data, 'poster')
        );

        if (!$result) {
            return ['success' => false, 'message' => $wpdb->last_error ?: 'خطا در ذخیره.'];
        }

        $id = (int) $wpdb->insert_id;

        // Store event_type & artist_label in description meta via options table helper
        // Prefer direct column if available
        if (self::has_column('event_type')) {
            $wpdb->update(
                Database::table('exhibitions'),
                [
                    'event_type'   => $event_type,
                    'artist_label' => sanitize_text_field($data['artist_label'] ?? ''),
                    'subtitle'     => sanitize_text_field($data['subtitle'] ?? ''),
                ],
                ['id' => $id]
            );
        } else {
            update_option('art_coa_ex_meta_' . $id, [
                'event_type'   => $event_type,
                'artist_label' => sanitize_text_field($data['artist_label'] ?? ''),
                'subtitle'     => sanitize_text_field($data['subtitle'] ?? ''),
            ], false);
        }

        Audit::log('exhibition', $id, 'created');
        return ['success' => true, 'id' => $id, 'slug' => $slug];
    }

    public static function all(int $limit = 200, bool $public_only = false): array
    {
        global $wpdb;
        self::maybe_add_columns();

        $where = $public_only ? "WHERE e.status IN ('published','active')" : '';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT e.*, g.name AS gallery_name, g.logo_url AS gallery_logo, g.logo_id AS gallery_logo_id, g.slug AS gallery_slug,
                        c.display_name AS curator_name
                 FROM ' . Database::table('exhibitions') . ' e
                 LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = e.gallery_id
                 LEFT JOIN ' . Database::table('curators') . ' c ON c.id = e.curator_id
                 ' . $where . '
                 ORDER BY e.start_date DESC, e.id DESC
                 LIMIT %d',
                $limit
            )
        ) ?: [];

        foreach ($rows as $row) {
            self::hydrate_meta($row);
        }
        return $rows;
    }

    /**
     * Events that overlap a given Gregorian date range.
     */
    public static function in_range(string $from, string $to, ?string $event_type = null, array $gallery_post_ids = []): array
    {
        global $wpdb;
        self::maybe_add_columns();
        if (!self::$content_link_schema_checked && class_exists(ContentSync::class)) {
            ContentSync::ensure_schema();
            self::$content_link_schema_checked = true;
        }

        $gallery_table = Database::table('galleries');
        $gallery_cols = $wpdb->get_col("DESCRIBE `{$gallery_table}`", 0) ?: [];
        $gallery_post_select = in_array('cpt_post_id', $gallery_cols, true) ? 'g.cpt_post_id AS gallery_post_id' : '0 AS gallery_post_id';
        $sql = 'SELECT e.*, g.name AS gallery_name, g.logo_url AS gallery_logo, g.slug AS gallery_slug, ' . $gallery_post_select . '
                FROM ' . Database::table('exhibitions') . ' e
                LEFT JOIN ' . $gallery_table . ' g ON g.id = e.gallery_id
                WHERE e.status IN ("published","active")
                  AND e.start_date IS NOT NULL
                  AND e.start_date <= %s
                  AND (e.end_date IS NULL OR e.end_date >= %s)';

        $params = [$to, $from];

        if ($event_type && self::has_column('event_type')) {
            $sql .= ' AND e.event_type = %s';
            $params[] = $event_type;
        }

        $gallery_post_ids = array_values(array_unique(array_filter(array_map('absint', $gallery_post_ids))));
        if ($gallery_post_ids) {
            // Filter the mirrored table row through its canonical WordPress gallery post ID.
            if (!in_array('cpt_post_id', $gallery_cols, true)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($gallery_post_ids), '%d'));
            $sql .= ' AND e.gallery_id IN (SELECT id FROM ' . $gallery_table . ' WHERE cpt_post_id IN (' . $placeholders . '))';
            $params = array_merge($params, $gallery_post_ids);
        }

        $sql .= ' ORDER BY e.start_date ASC, e.id ASC';

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params)) ?: [];
        foreach ($rows as $row) {
            self::hydrate_meta($row);
            if ($event_type && empty($row->event_type)) {
                $row->event_type = 'exhibition';
            }
            if ($event_type && ($row->event_type ?? '') !== $event_type) {
                continue;
            }
        }

        if ($event_type && !self::has_column('event_type')) {
            $rows = array_values(array_filter($rows, static fn($r) => ($r->event_type ?? 'exhibition') === $event_type));
        }

        return $rows;
    }

    public static function by_year(int $year_jalali_or_gregorian, ?string $event_type = null, array $gallery_post_ids = []): array
    {
        // Accept Jalali year ~1400+ or Gregorian
        if ($year_jalali_or_gregorian > 1300 && $year_jalali_or_gregorian < 1600) {
            // Rough Jalali → Gregorian range for Iranian calendar year
            $g_start = $year_jalali_or_gregorian + 621;
            $from = sprintf('%d-03-21', $g_start);
            $to   = sprintf('%d-03-20', $g_start + 1);
        } else {
            $from = sprintf('%d-01-01', $year_jalali_or_gregorian);
            $to   = sprintf('%d-12-31', $year_jalali_or_gregorian);
        }
        return self::in_range($from, $to, $event_type, $gallery_post_ids);
    }

    private static function hydrate_meta(object $row): void
    {
        $meta = get_option('art_coa_ex_meta_' . (int) ($row->id ?? 0), []);
        $meta = is_array($meta) ? $meta : [];
        if (empty($row->event_type)) {
            $row->event_type = $meta['event_type'] ?? 'exhibition';
        }
        if (empty($row->artist_label)) {
            $row->artist_label = $meta['artist_label'] ?? '';
        }
        if (empty($row->subtitle)) {
            $row->subtitle = $meta['subtitle'] ?? '';
        }
    }

    private static function normalize_date(?string $d): ?string
    {
        if (!$d) {
            return null;
        }
        $d = sanitize_text_field($d);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $d)) {
            return substr($d, 0, 10);
        }
        return null;
    }

    private static function unique_slug(string $slug): string
    {
        global $wpdb;
        $base = $slug;
        $i = 1;
        while ($wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Database::table('exhibitions') . ' WHERE slug = %s',
            $slug
        ))) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private static function has_column(string $col): bool
    {
        global $wpdb;
        static $cache = [];
        if (isset($cache[$col])) {
            return $cache[$col];
        }
        $table = Database::table('exhibitions');
        // phpcs:ignore
        $found = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
        $cache[$col] = !empty($found);
        return $cache[$col];
    }

    public static function maybe_add_columns(): void
    {
        global $wpdb;
        $table = Database::table('exhibitions');
        if (!self::has_column('event_type')) {
            // phpcs:ignore
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN event_type VARCHAR(30) NOT NULL DEFAULT 'exhibition' AFTER status");
        }
        if (!self::has_column('artist_label')) {
            // phpcs:ignore
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN artist_label VARCHAR(255) NULL AFTER event_type");
        }
        if (!self::has_column('subtitle')) {
            // phpcs:ignore
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN subtitle VARCHAR(500) NULL AFTER artist_label");
        }
    }

    /**
     * Split exhibitions into upcoming / ongoing / past.
     * @return array{upcoming: object[], ongoing: object[], past: object[]}
     */
    public static function categorized(int $limit = 200, bool $public_only = false): array
    {
        $all = self::all($limit, $public_only);
        $today = gmdate('Y-m-d');
        $out = ['upcoming' => [], 'ongoing' => [], 'past' => []];
        foreach ($all as $e) {
            $start = $e->start_date ?: null;
            $end = $e->end_date ?: $start;
            if (!$start) {
                $out['upcoming'][] = $e;
                continue;
            }
            if ($start > $today) {
                $out['upcoming'][] = $e;
            } elseif ($end && $end < $today) {
                $out['past'][] = $e;
            } else {
                $out['ongoing'][] = $e;
            }
        }
        // Sort
        usort($out['upcoming'], static fn($a, $b) => strcmp($a->start_date ?? '', $b->start_date ?? ''));
        usort($out['ongoing'], static fn($a, $b) => strcmp($a->start_date ?? '', $b->start_date ?? ''));
        usort($out['past'], static fn($a, $b) => -strcmp($a->start_date ?? '', $b->start_date ?? ''));
        return $out;
    }

    /** Related artist names via linked artworks */
    public static function related_artists(int $exhibition_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT ar.id, ar.display_name, ar.slug, ar.first_name, ar.last_name
             FROM ' . Database::table('exhibition_artworks') . ' ea
             INNER JOIN ' . Database::table('artworks') . ' a ON a.id = ea.artwork_id
             INNER JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
             WHERE ea.exhibition_id = %d
             ORDER BY ar.display_name ASC
             LIMIT 30',
            $exhibition_id
        )) ?: [];
    }


    public static function get(int $id): ?object
    {
        global $wpdb;
        self::maybe_add_columns();
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('exhibitions') . ' WHERE id = %d',
            $id
        ));
        if ($row) {
            self::hydrate_meta($row);
        }
        return $row ?: null;
    }

    public static function update(int $id, array $data): array
    {
        self::maybe_add_columns();
        if (isset($data['event_type']) && !in_array(sanitize_key((string) $data['event_type']), ['exhibition', 'artfair', 'auction'], true)) {
            unset($data['event_type']);
        }
        if (array_key_exists('status', $data)) {
            $data['status'] = self::clean_status($data['status']);
        }
        foreach (['start_date', 'end_date'] as $d) {
            if (array_key_exists($d, $data)) {
                $data[$d] = self::normalize_date((string) $data[$d]);
            }
        }
        // gallery_id / curator_id: empty string means "none"
        return Updater::apply('exhibition', 'exhibitions', $id, $data, [
            'title'        => 'text!',
            'subtitle'     => 'text',
            'artist_label' => 'text',
            'event_type'   => 'key',
            'gallery_id'   => 'int',
            'curator_id'   => 'int',
            'description'  => 'html',
            'start_date'   => 'raw',
            'end_date'     => 'raw',
            'status'       => 'key',
        ], ['poster'], ['gallery_ids']);
    }

    /** published | active | pending | draft | rejected */
    private static function clean_status($status): string
    {
        $status = sanitize_key((string) $status);
        return in_array($status, ['published', 'active', 'pending', 'draft', 'rejected'], true) ? $status : 'published';
    }

    public static function set_status(int $id, string $status): bool
    {
        $r = self::update($id, ['status' => $status]);
        return !empty($r['success']);
    }
}
