<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * One-way mirror: post-type content (artiey_artist / gallery / artwork / exhibition, edited with Elementor or the
 * classic editor) -> art_* tables. The catalog, live search, sitemap and member tools then see ALL content in one
 * place. The post stays the source of truth; each mirrored row carries `cpt_post_id`. Nothing is ever deleted
 * from the tables by this class (a trashed/unpublished post only hides its row).
 */
final class ContentSync
{
    public const MAP = [
        'artiey_artist'     => 'artists',
        'artiey_gallery'    => 'galleries',
        'artiey_artwork'    => 'artworks',
        'artiey_exhibition' => 'exhibitions',
    ];

    public const BATCH_HOOK   = 'artiey_aet_sync_batch';
    public const LEGACY_BATCH_HOOK = 'artiey_aet_legacy_cpt_migration_batch';
    public const LEGACY_CURSOR_OPT = 'artiey_aet_legacy_cpt_migration_cursor';
    public const CURSOR_OPT   = 'artiey_aet_sync_cursor';
    public const FAILED_POSTS_OPT = 'artiey_aet_sync_failed_posts';
    public const FINAL_FAILED_POSTS_OPT = 'artiey_aet_sync_final_failed_posts';
    public const RETRY_FAILED_HOOK = 'artiey_aet_sync_retry_failed_posts';
    public const BATCH_SIZE   = 40;
    private const BRIDGE_SCHEMA_VERSION = '1';
    private static bool $bridge_schema_ready = false;
    private const MAX_SYNC_RETRIES = 2;
    /** Text columns that an empty post never blanks (a bio typed elsewhere is kept). */
    private const KEEP_IF_EMPTY = [
        'bio', 'about', 'description', 'first_name', 'last_name', 'national_id', 'email', 'phone', 'website', 'user_id',
        'nationality', 'artistic_statement', 'name_en', 'city', 'short_bio', 'mediums', 'education', 'awards',
        'instagram', 'telegram', 'name', 'country', 'address', 'visitor_info', 'hours', 'whatsapp',
        'medium', 'dimensions', 'material', 'condition_note', 'provenance', 'title_en', 'edition',
        'accession_number', 'subtitle', 'artist_label', 'curator_name', 'admission_info', 'opening_note',
        'website', 'brand_color', 'brand_color_2', 'signature_name', 'signature_url', 'sale_note', 'currency', 'price',
    ];

    /** @var array<int,bool> */
    private static array $busy = [];
    /** @var array<string,string[]> Table columns cached for the lifetime of this request. */
    private static array $column_cache = [];

    public static function init(): void
    {
        foreach (array_keys(self::MAP) as $pt) {
            add_action('save_post_' . $pt, [self::class, 'on_save'], 30, 1);
        }
        add_action('trashed_post', [self::class, 'on_trash'], 10, 1);
        add_action('untrashed_post', [self::class, 'on_untrash'], 10, 1);
        add_action('before_delete_post', [self::class, 'on_delete'], 10, 1);
        add_action(self::BATCH_HOOK, [self::class, 'run_batch']);
        add_action(self::RETRY_FAILED_HOOK, [self::class, 'run_failed_sync_batch']);
        add_action(self::LEGACY_BATCH_HOOK, [\WPArtCOA\ContentBridge::class, 'run_migration_batch']);
    }

    /* ------------------------------------------------------------------ background re-sync (large sites) */

    /** Starts a full re-sync that runs in small batches through WP-Cron instead of one long request. */
    public static function queue_full_sync(): void
    {
        // A new full pass supersedes the previous run's terminal-error report.
        delete_option(self::FINAL_FAILED_POSTS_OPT);
        update_option(self::CURSOR_OPT, ['type' => 0, 'after' => 0], false);
        if (!wp_next_scheduled(self::BATCH_HOOK)) {
            wp_schedule_single_event(time() + 5, self::BATCH_HOOK);
        }
    }

    /** Processes one batch; failed posts are queued for bounded retries instead of being skipped forever. */
    public static function run_batch(): void
    {
        $cur = get_option(self::CURSOR_OPT);
        if (!is_array($cur)) {
            return;
        }
        $types = array_keys(self::MAP); // artists + galleries first, then what references them.
        $i = max(0, (int) ($cur['type'] ?? 0));
        if ($i >= count($types)) {
            delete_option(self::CURSOR_OPT);
            $failed = get_option(self::FAILED_POSTS_OPT, []);
            if (is_array($failed) && $failed && !wp_next_scheduled(self::RETRY_FAILED_HOOK)) {
                wp_schedule_single_event(time() + 60, self::RETRY_FAILED_HOOK);
            }
            return;
        }
        global $wpdb;
        $after = max(0, (int) ($cur['after'] ?? 0));
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft','pending','private') AND ID > %d ORDER BY ID ASC LIMIT %d",
            $types[$i], $after, self::BATCH_SIZE
        ));
        // A transient query error must not be mistaken for an empty table, or the cursor would
        // permanently skip all records after the failed SELECT.
        if (isset($wpdb->last_error) && (string) $wpdb->last_error !== '') {
            if (!wp_next_scheduled(self::BATCH_HOOK)) {
                wp_schedule_single_event(time() + 60, self::BATCH_HOOK);
            }
            return;
        }
        $ids = is_array($ids) ? $ids : [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id <= 0) {
                continue;
            }
            $post = get_post($id);
            if (!$post || !isset(self::MAP[$post->post_type])) {
                // The post may have been deleted or changed between the ID query and this read.
                continue;
            }
            if (!self::sync_post($id)) {
                self::queue_failed_post($id, (string) $post->post_type);
            } else {
                self::clear_final_failed_post($id);
            }
        }
        if (count($ids) < self::BATCH_SIZE) {
            $cur = ['type' => $i + 1, 'after' => 0];
        } else {
            $cur['type'] = $i;
            $cur['after'] = (int) end($ids);
        }
        update_option(self::CURSOR_OPT, $cur, false);
        wp_schedule_single_event(time() + 10, self::BATCH_HOOK);
    }

    /** Public entry point for legacy forms that need a bounded retry after a failed mirror write. */
    public static function queue_sync_retry(int $post_id): void
    {
        $post_id = absint($post_id);
        $post = $post_id > 0 ? get_post($post_id) : null;
        if (!$post || !isset(self::MAP[$post->post_type])) {
            return;
        }
        self::queue_failed_post($post_id, (string) $post->post_type);
    }

    /** Record one failed mirror write without creating duplicate queue entries. */
    private static function queue_failed_post(int $post_id, string $post_type): void
    {
        if ($post_id <= 0 || !isset(self::MAP[$post_type])) {
            return;
        }
        $failed = get_option(self::FAILED_POSTS_OPT, []);
        $failed = is_array($failed) ? $failed : [];
        foreach ($failed as $item) {
            if (is_array($item) && absint($item['post_id'] ?? 0) === $post_id) {
                return;
            }
        }
        self::clear_final_failed_post($post_id);
        $failed[] = ['post_id' => $post_id, 'post_type' => $post_type, 'attempts' => 0];
        update_option(self::FAILED_POSTS_OPT, array_values($failed), false);
        if (!wp_next_scheduled(self::RETRY_FAILED_HOOK)) {
            wp_schedule_single_event(time() + 60, self::RETRY_FAILED_HOOK);
        }
    }

    /** Remove a resolved post from the terminal-failure report. */
    private static function clear_final_failed_post(int $post_id): void
    {
        $final = get_option(self::FINAL_FAILED_POSTS_OPT, []);
        if (!is_array($final) || !$final) {
            return;
        }
        $filtered = array_values(array_filter($final, static function ($item) use ($post_id): bool {
            return !is_array($item) || absint($item['post_id'] ?? 0) !== $post_id;
        }));
        if (count($filtered) === count($final)) {
            return;
        }
        if ($filtered) {
            update_option(self::FINAL_FAILED_POSTS_OPT, $filtered, false);
        } else {
            delete_option(self::FINAL_FAILED_POSTS_OPT);
        }
    }

    /** Retry failed mirrors twice; exhausted failures remain available for diagnostics. */
    public static function run_failed_sync_batch(): void
    {
        $failed = get_option(self::FAILED_POSTS_OPT, []);
        if (!is_array($failed) || !$failed) {
            delete_option(self::FAILED_POSTS_OPT);
            return;
        }

        $batch = array_splice($failed, 0, self::BATCH_SIZE);
        $retry = [];
        $final = get_option(self::FINAL_FAILED_POSTS_OPT, []);
        $final = is_array($final) ? $final : [];
        global $wpdb;

        foreach ($batch as $failure) {
            if (!is_array($failure)) {
                continue;
            }
            $post_id = absint($failure['post_id'] ?? 0);
            $post_type = sanitize_key((string) ($failure['post_type'] ?? ''));
            if ($post_id <= 0 || !isset(self::MAP[$post_type])) {
                continue;
            }
            $post = get_post($post_id);
            if (!$post || $post->post_type !== $post_type) {
                // Deleted or changed posts are no longer eligible for this mirror retry.
                continue;
            }
            if (self::sync_post($post_id)) {
                self::clear_final_failed_post($post_id);
                continue;
            }

            $attempts = absint($failure['attempts'] ?? 0) + 1;
            if ($attempts < self::MAX_SYNC_RETRIES) {
                $failure['attempts'] = $attempts;
                $retry[] = $failure;
                continue;
            }
            $final[] = [
                'post_id' => $post_id,
                'post_type' => $post_type,
                'attempts' => $attempts,
                'error' => isset($wpdb->last_error) ? sanitize_text_field((string) $wpdb->last_error) : '',
            ];
        }

        if ($final) {
            update_option(self::FINAL_FAILED_POSTS_OPT, array_values($final), false);
        }
        $pending = array_merge($failed, $retry);
        if ($pending) {
            update_option(self::FAILED_POSTS_OPT, array_values($pending), false);
            if (!wp_next_scheduled(self::RETRY_FAILED_HOOK)) {
                wp_schedule_single_event(time() + 60, self::RETRY_FAILED_HOOK);
            }
        } else {
            delete_option(self::FAILED_POSTS_OPT);
        }
    }

    /* ------------------------------------------------------------------ schema */

    public static function ensure_schema(): void
    {
        if (self::$bridge_schema_ready || get_option('artiey_aet_content_bridge_schema_version') === self::BRIDGE_SCHEMA_VERSION) {
            self::$bridge_schema_ready = true;
            return;
        }
        global $wpdb;
        $schema_ok = true;
        foreach (self::MAP as $table) {
            $t = Database::table($table);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t)));
            if ($exists !== $t) {
                $schema_ok = false;
                continue;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $cols = $wpdb->get_col("DESCRIBE `{$t}`", 0);
            if (!is_array($cols) || empty($cols)) {
                $schema_ok = false;
                continue;
            }
            if (!in_array('cpt_post_id', $cols, true)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                if (false === $wpdb->query("ALTER TABLE `{$t}` ADD COLUMN `cpt_post_id` BIGINT UNSIGNED NULL")) {
                    $schema_ok = false;
                    continue;
                }
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $idx = $wpdb->get_results("SHOW INDEX FROM `{$t}` WHERE Key_name = 'cpt_post_id'");
            if (!$idx) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                if (false === $wpdb->query("ALTER TABLE `{$t}` ADD KEY `cpt_post_id` (`cpt_post_id`)")) {
                    $schema_ok = false;
                }
            }
        }
        if ($schema_ok) {
            self::$bridge_schema_ready = true;
            update_option('artiey_aet_content_bridge_schema_version', self::BRIDGE_SCHEMA_VERSION, false);
        }
    }

    /* ------------------------------------------------------------------ hooks */

    public static function on_save($post_id): void
    {
        $post_id = (int) $post_id;
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!apply_filters('art_coa_content_sync', true, $post_id)) {
            return;
        }
        $synced = self::sync_post($post_id);
        if (!$synced) {
            $type = get_post_type($post_id);
            if ($type && isset(self::MAP[$type])) {
                self::queue_failed_post($post_id, (string) $type);
            }
        } else {
            self::clear_final_failed_post($post_id);
        }
        Cache::flush_stats();
    }

    /** Move a mirrored record out of the public slug namespace without deleting its audit/history row. */
    private static function release_public_slug(int $post_id): void
    {
        $type = get_post_type($post_id);
        if (!$type || !isset(self::MAP[$type])) {
            return;
        }
        global $wpdb;
        $table  = Database::table(self::MAP[$type]);
        $hidden = ($type === 'artiey_artist' || $type === 'artiey_gallery') ? 'inactive' : 'draft';
        $old_slug = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT slug FROM {$table} WHERE cpt_post_id = %d LIMIT 1",
            $post_id
        ));
        $changes = ['status' => $hidden];
        // Deterministic tombstone releases the unique slug while retaining the audit row.
        if ($old_slug !== '' && strpos($old_slug, 'deleted-' . $post_id . '-') !== 0) {
            $changes['slug'] = sanitize_title('deleted-' . $post_id . '-' . substr(md5($old_slug), 0, 8));
        }
        $wpdb->update($table, $changes, ['cpt_post_id' => $post_id]);
        Cache::flush_stats();
        if (class_exists('\\Artiey_AET_Slug') && method_exists('\\Artiey_AET_Slug', 'purge_public_url_caches')) {
            \Artiey_AET_Slug::purge_public_url_caches();
        }
    }

    /** A trashed post must not reserve its slug in the relational mirror. */
    public static function on_trash($post_id): void
    {
        self::release_public_slug(absint($post_id));
    }

    /** Rebuild the mirror after restoration; WordPress resolves any live post-slug collision. */
    public static function on_untrash($post_id): void
    {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return;
        }
        self::sync_post($post_id);
        Cache::flush_stats();
        if (class_exists('\\Artiey_AET_Slug') && method_exists('\\Artiey_AET_Slug', 'purge_public_url_caches')) {
            \Artiey_AET_Slug::purge_public_url_caches();
        }
    }

    /** Keep relational history, but free the public slug on permanent deletion too. */
    public static function on_delete($post_id): void
    {
        self::release_public_slug(absint($post_id));
    }

    /* ------------------------------------------------------------------ sync */

    /** @return array{artists:int,galleries:int,artworks:int,exhibitions:int} */
    public static function sync_all(): array
    {
        self::ensure_schema();
        $n = ['artists' => 0, 'galleries' => 0, 'artworks' => 0, 'exhibitions' => 0];
        // Order matters: artists + galleries first, then what references them.
        foreach (['artiey_artist', 'artiey_gallery', 'artiey_artwork', 'artiey_exhibition'] as $pt) {
            $ids = get_posts([
                'post_type' => $pt, 'post_status' => ['publish', 'draft', 'pending', 'private'],
                'numberposts' => 2000, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
                'no_found_rows' => true,
            ]);
            foreach ($ids as $id) {
                $post_id = (int) $id;
                if (self::sync_post($post_id)) {
                    $n[self::MAP[$pt]]++;
                    self::clear_final_failed_post($post_id);
                } else {
                    self::queue_failed_post($post_id, $pt);
                }
            }
        }
        return $n;
    }

    /** Mirrors one post; returns the table row id (0 when skipped). */
    public static function sync_post(int $post_id): int
    {
        $post = get_post($post_id);
        if (!$post || !isset(self::MAP[$post->post_type]) || isset(self::$busy[$post_id])) {
            return 0;
        }
        self::$busy[$post_id] = true;
        try {
            $fields = self::fields($post);
            if ($fields === null) {
                return 0;
            }
            $table  = self::MAP[$post->post_type];
            $fields = array_merge($fields, ProfileFields::from_meta($table, $post_id));
            return self::upsert($table, $post, $fields);
        } finally {
            unset(self::$busy[$post_id]);
        }
    }

    /** Row id of the mirrored post-type record (syncing it first when needed). */
    private static function row_for(int $post_id): int
    {
        if ($post_id <= 0) {
            return 0;
        }
        $type = get_post_type($post_id);
        if (!$type || !isset(self::MAP[$type])) {
            return 0;
        }
        global $wpdb;
        $id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Database::table(self::MAP[$type]) . ' WHERE cpt_post_id = %d',
            $post_id
        ));
        return $id ?: self::sync_post($post_id);
    }

    private static function live(\WP_Post $p): bool
    {
        return $p->post_status === 'publish';
    }

    private static function meta(int $id, string $key): string
    {
        $v = get_post_meta($id, $key, true);
        return is_scalar($v) ? trim((string) $v) : '';
    }

    /** Prefer real editorial content; use and then retire an import fallback only while content is empty. */
    private static function editorial_body(int $post_id, string $body, string $legacy_meta): string
    {
        if (trim(wp_strip_all_tags($body)) !== '') {
            if (get_post_meta($post_id, $legacy_meta, true) !== '') {
                delete_post_meta($post_id, $legacy_meta);
            }
            return $body;
        }
        return self::meta($post_id, $legacy_meta);
    }

    /** @return array<string,mixed>|null */
    private static function fields(\WP_Post $p): ?array
    {
        $id    = (int) $p->ID;
        $thumb = (int) get_post_thumbnail_id($id);
        // Cleaned editorial text only: never the HTML an Elementor page stored in post_content (it holds copies of the
        // profile and <style> blocks that would be printed as the biography).
        $body  = wp_kses_post(class_exists('Artiey_AET_Profile_Guard') ? \Artiey_AET_Profile_Guard::bio_source($p) : (string) $p->post_content);

        switch ($p->post_type) {
            case 'artiey_artist':
                return [
                    'display_name' => (string) $p->post_title,
                    'nationality' => sanitize_text_field(self::meta($id, '_aa_nationality')) ?: null,
                    'artistic_statement' => sanitize_textarea_field(self::meta($id, '_aa_statement')) ?: null,
                    'first_name'   => sanitize_text_field(self::meta($id, '_aa_first_name')) ?: null,
                    'last_name'    => sanitize_text_field(self::meta($id, '_aa_last_name')) ?: null,
                    'national_id'  => sanitize_text_field(self::meta($id, '_aa_national_id')) ?: null,
                    'email'        => sanitize_email(self::meta($id, '_aa_email')) ?: null,
                    'phone'        => sanitize_text_field(self::meta($id, '_aa_phone')) ?: null,
                    'website'      => esc_url_raw(self::meta($id, '_aa_website')) ?: null,
                    // WordPress is the canonical owner relation; the table value is only a relational mirror.
                    'user_id'      => absint(self::meta($id, '_aa_user_id')) ?: null,
                    'bio'          => self::editorial_body($id, $body, '_aa_legacy_bio') ?: sanitize_textarea_field(self::meta($id, '_aa_short_bio')),
                    'avatar_id'    => $thumb ?: null,
                    'cover_id'     => (int) self::meta($id, '_aa_cover') ?: null,
                    'status'       => self::live($p) ? 'active' : 'inactive',
                ];
            case 'artiey_gallery':
                return [
                    'name'     => (string) $p->post_title,
                    'email'    => sanitize_email(self::meta($id, '_ag_email')) ?: null,
                    'phone'    => sanitize_text_field(self::meta($id, '_ag_phone') ?: self::meta($id, '_ag_mobile')) ?: null,
                    'address'  => sanitize_textarea_field(self::meta($id, '_ag_address')) ?: null,
                    'city'     => sanitize_text_field(self::meta($id, '_ag_city')) ?: null,
                    'country'  => sanitize_text_field(self::meta($id, '_ag_country')) ?: null,
                    'brand_color' => sanitize_hex_color(self::meta($id, '_ag_brand_color')) ?: null,
                    'brand_color_2' => sanitize_hex_color(self::meta($id, '_ag_brand_color_2')) ?: null,
                    'signature_id' => (int) self::meta($id, '_ag_signature_id') ?: null,
                    'signature_url' => esc_url_raw(self::meta($id, '_ag_signature_url')) ?: null,
                    'signature_name' => sanitize_text_field(self::meta($id, '_ag_signature_name')) ?: null,
                    'website'  => esc_url_raw(self::meta($id, '_ag_website')) ?: null,
                    'about'    => self::editorial_body($id, $body, '_ag_legacy_about') ?: sanitize_textarea_field(self::meta($id, '_ag_short_bio')),
                    'visitor_info' => sanitize_textarea_field(self::meta($id, '_ag_visitor_info')) ?: null,
                    'logo_id'  => $thumb ?: null,
                    'cover_id' => (int) self::meta($id, '_ag_cover') ?: null,
                    'status'   => self::live($p) ? 'active' : 'inactive',
                ];
            case 'artiey_artwork':
                $artist_row = self::row_for((int) self::meta($id, '_aw_artist'));
                if (!$artist_row) {
                    return null; // art_artworks.artist_id is mandatory
                }
                $sale  = self::meta($id, '_aw_sale');
                $mode  = self::meta($id, '_aw_price_mode');
                $price = (float) self::meta($id, '_aw_price');
                $photos = get_post_meta($id, '_aw_photos', true);
                $photos = is_array($photos) ? array_values(array_filter(array_map('intval', $photos))) : [];
                $moderation = self::meta($id, '_aw_moderation_status');
                $status = in_array($moderation, ['pending', 'approved', 'published', 'draft', 'rejected', 'sold'], true)
                    ? $moderation
                    : (!self::live($p) ? 'draft' : ($sale === 'sold' ? 'sold' : 'published'));
                return [
                    'title'        => (string) $p->post_title,
                    'artist_id'    => $artist_row,
                    'gallery_id'   => self::row_for((int) self::meta($id, '_aw_gallery')) ?: null,
                    'medium'       => sanitize_text_field(self::meta($id, '_aw_medium')) ?: null,
                    'year_created' => (int) self::meta($id, '_aw_year') ?: null,
                    'dimensions'   => sanitize_text_field(self::meta($id, '_aw_dimensions')) ?: null,
                    'material'     => sanitize_text_field(self::meta($id, '_aw_material')) ?: null,
                    'condition_note' => sanitize_textarea_field(self::meta($id, '_aw_condition')) ?: null,
                    'provenance'   => sanitize_textarea_field(self::meta($id, '_aw_provenance')) ?: null,
                    'description'  => self::editorial_body($id, $body, '_aw_legacy_description'),
                    'price'        => ($mode === 'show' || $mode === '') && $price > 0 ? $price : null,
                    'currency'     => sanitize_text_field(self::meta($id, '_aw_currency')) ?: (string) apply_filters('art_coa_sync_currency', 'IRT'),
                    'sale_note'    => sanitize_textarea_field(self::meta($id, '_aw_sale_note')) ?: null,
                    'for_sale'     => ($sale === 'available' || $sale === '') && $price > 0 && $mode !== 'hide' ? 1 : 0,
                    'image_id'     => $thumb ?: ($photos[0] ?? null),
                    'gallery_ids'  => $photos ? implode(',', array_slice($photos, 0, 30)) : '',
                    'status'       => $status,
                ];
            case 'artiey_exhibition':
                $start = self::meta($id, '_ae_start');
                $end   = self::meta($id, '_ae_end');
                $type  = sanitize_key(self::meta($id, '_ae_type'));
                $moderation = sanitize_key(self::meta($id, '_ae_moderation_status'));
                $status = in_array($moderation, ['pending', 'published', 'draft', 'rejected', 'active'], true) ? $moderation : (self::live($p) ? 'published' : 'draft');
                return [
                    'title'        => (string) $p->post_title,
                    'gallery_id'   => self::row_for((int) self::meta($id, '_ae_gallery')) ?: null,
                    'curator_id'   => (int) self::meta($id, '_ae_curator_id') ?: null,
                    'description'  => self::editorial_body($id, $body, '_ae_legacy_description'),
                    'start_date'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? $start : null,
                    'end_date'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ? $end : null,
                    'event_type'   => in_array($type, ['exhibition', 'artfair', 'auction'], true) ? $type : 'exhibition',
                    'artist_label' => sanitize_text_field(self::meta($id, '_ae_artist')) ?: null,
                    'curator_name' => sanitize_text_field(self::meta($id, '_ae_curator')) ?: null,
                    'admission_info' => sanitize_text_field(self::meta($id, '_ae_admission')) ?: null,
                    'poster_id'    => $thumb ?: null,
                    'gallery_ids'  => Media::ids(get_post_meta($id, '_ae_photos', true)) ? implode(',', array_slice(Media::ids(get_post_meta($id, '_ae_photos', true)), 0, 30)) : '',
                    'status'       => $status,
                ];
        }
        return null;
    }

    /**
     * Find exactly one relational artist row by stable identity, even if its slug differs.
     * Returns ['id'=>0,'ambiguous'=>false] when no candidate exists. A candidate linked to another
     * WordPress post is treated as ambiguous so the sync fails safely rather than duplicating identity.
     * @param array<string,mixed> $fields
     * @param string[] $cols
     * @return array{id:int,ambiguous:bool}
     */
    private static function unlinked_artist_identity_row(string $table, array $fields, array $cols, int $post_id): array
    {
        global $wpdb;
        $keys = [
            'user_id' => ['user_id', 'int'],
            'national_id' => ['national_id', 'text'],
            'email' => ['email', 'text'],
        ];
        $candidate_rows = [];
        foreach ($keys as $field => [$column, $kind]) {
            if (!in_array($column, $cols, true) || !array_key_exists($field, $fields)) {
                continue;
            }
            $value = $fields[$field];
            if ($kind === 'int') {
                $value = absint($value);
                if ($value <= 0) continue;
            } else {
                $value = strtolower(trim((string) $value));
                if ($value === '') continue;
            }
            $sql = "SELECT id, cpt_post_id FROM `{$table}` WHERE `{$column}` = %s ORDER BY id ASC LIMIT 2";
            $rows = $wpdb->get_results($wpdb->prepare($sql, (string) $value)) ?: [];
            if (count($rows) > 1) {
                return ['id' => 0, 'ambiguous' => true];
            }
            if (count($rows) === 1) {
                $rid = absint($rows[0]->id ?? 0);
                $linked = absint($rows[0]->cpt_post_id ?? 0);
                if (!$rid || ($linked && $linked !== $post_id)) {
                    return ['id' => 0, 'ambiguous' => true];
                }
                $candidate_rows[$rid] = true;
            }
        }
        // Different stable fields pointing at different old rows is an identity conflict.
        if (count($candidate_rows) > 1) {
            return ['id' => 0, 'ambiguous' => true];
        }
        return ['id' => (int) (array_key_first($candidate_rows) ?: 0), 'ambiguous' => false];
    }

    /** @param array<string,mixed> $fields */
    private static function upsert(string $table, \WP_Post $post, array $fields): int
    {
        global $wpdb;
        $t   = Database::table($table);
        $now = gmdate('Y-m-d H:i:s');

        // Only touch columns that exist (older installs may lack a soft-migrated column).
        // DESCRIBE was previously executed on every save; cache the schema per request.
        if (!array_key_exists($t, self::$column_cache)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            self::$column_cache[$t] = $wpdb->get_col("DESCRIBE `{$t}`", 0) ?: [];
        }
        $cols = self::$column_cache[$t];
        if (!in_array('cpt_post_id', $cols, true)) {
            self::ensure_schema();
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            self::$column_cache[$t] = $wpdb->get_col("DESCRIBE `{$t}`", 0) ?: [];
            $cols = self::$column_cache[$t];
        }
        // Do not run queries against a partially migrated table. Returning 0 leaves the caller's
        // queue/link retryable instead of reporting a false success after a schema error.
        if (!in_array('id', $cols, true) || !in_array('slug', $cols, true) || !in_array('cpt_post_id', $cols, true)) {
            return 0;
        }
        $fields = array_intersect_key($fields, array_flip($cols));

        $linked_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM `{$t}` WHERE cpt_post_id = %d ORDER BY id ASC LIMIT 2", $post->ID)); // phpcs:ignore
        $linked_ids = is_array($linked_ids) ? array_values(array_filter(array_map('absint', $linked_ids))) : [];
        // Duplicate links are data-integrity errors. Do not silently update the lowest ID and leave
        // another row linked to the same canonical post; report the failure to the retry queue.
        if (count($linked_ids) > 1) {
            return 0;
        }
        $row = (int) ($linked_ids[0] ?? 0);
        $preserve_empty = false;
        $slug_base = $post->post_name !== '' ? sanitize_title((string) $post->post_name) : sanitize_title((string) $post->post_title);
        if ($slug_base === '') {
            $slug_base = sanitize_title($table . '-' . $post->ID);
        }
        // Search for the full legacy slug before shortening it for the table column; otherwise a
        // long slug could fail to adopt its old row and create a second relational record.
        $legacy_lookup_slug = $slug_base;
        if ($table === 'artists') {
            // Validate identity against every row, even when a linked row was found. This detects an
            // unlinked duplicate or a conflicting row before the mirror reports a successful sync.
            $identity = self::unlinked_artist_identity_row($t, $fields, $cols, (int) $post->ID);
            if (!empty($identity['ambiguous'])) {
                return 0;
            }
            $identity_id = absint($identity['id'] ?? 0);
            if ($row && $identity_id && $identity_id !== $row) {
                return 0;
            }
            if (!$row && $identity_id) {
                $row = $identity_id;
                $preserve_empty = true;
            }
        }
        if (!$row) {
            $row = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `{$t}` WHERE slug = %s AND (cpt_post_id IS NULL OR cpt_post_id = 0) ORDER BY id ASC LIMIT 1",
                $legacy_lookup_slug
            )); // phpcs:ignore
            $preserve_empty = $row > 0;
        }
        // Legacy tables generally allow 200 characters for slug. Keep room for a deterministic
        // uniqueness suffix so extremely long custom post slugs cannot overflow that column.
        $slug_base = class_exists('Artiey_AET_Slug')
            ? \Artiey_AET_Slug::shorten($slug_base, 160)
            : substr($slug_base, 0, 160);
        $slug_base = trim((string) $slug_base, '-');
        if ($slug_base === '') {
            $slug_base = $table . '-' . $post->ID;
        }
        // Do not assume one suffix is free: imported/manual records may already own it.
        // Iterate a bounded number of candidates and fail safely rather than overwrite a row.
        $candidate = $slug_base;
        $attempt = 0;
        do {
            $clash = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `{$t}` WHERE slug = %s AND id <> %d LIMIT 1",
                $candidate,
                $row
            )); // phpcs:ignore
            if (!$clash) {
                break;
            }
            ++$attempt;
            if ($attempt > 100) {
                return 0;
            }
            $suffix = '-p' . $post->ID . ($attempt > 1 ? '-' . $attempt : '');
            $candidate = rtrim(substr($slug_base, 0, max(1, 190 - strlen($suffix))), '-') . $suffix;
        } while (true);
        $fields['slug'] = $candidate;
        if ($row) {
            $old_slug = (string) $wpdb->get_var($wpdb->prepare("SELECT slug FROM `{$t}` WHERE id = %d", $row)); // phpcs:ignore
            SlugHistory::record($table, $old_slug, (string) $fields['slug']);
        }

        if ($row) {
            if ($preserve_empty) {
                foreach (self::KEEP_IF_EMPTY as $k) {
                    if (array_key_exists($k, $fields) && trim(wp_strip_all_tags((string) $fields[$k])) === '') {
                        unset($fields[$k]);
                    }
                }
            }
            if (in_array('updated_at_utc', $cols, true)) {
                $fields['updated_at_utc'] = $now;
            }
            $fields['cpt_post_id'] = (int) $post->ID;
            $updated = $wpdb->update($t, $fields, ['id' => $row]);
            if ($updated === false) {
                return 0;
            }
        } else {
            $fields['cpt_post_id'] = (int) $post->ID;
            if (in_array('ulid', $cols, true)) {
                $fields['ulid'] = Ulid::generate();
            }
            if (in_array('created_at_utc', $cols, true)) {
                $fields['created_at_utc'] = $now;
            }
            if ($wpdb->insert($t, $fields) === false) {
                return 0;
            }
            $row = (int) $wpdb->insert_id;
            if ($row <= 0) {
                return 0;
            }
        }
        if (class_exists('\WPArtCOA\Cache')) {
            Cache::flush_stats();
            Cache::forget_facets();
        }
        return $row;
    }

    /**
     * One public URL for every artist: /artists/{slug}/.
     * Published WordPress artist posts are authoritative; COA-only records use the
     * same route and are dispatched to their public COA profile until the bridge
     * creates/adopts the canonical WordPress post.
     */
    public static function artist_url(string $slug): string
    {
        $slug = sanitize_title(rawurldecode((string) $slug));
        if ($slug === '') {
            return '';
        }

        if (function_exists('get_page_by_path')) {
            $post = get_page_by_path($slug, OBJECT, 'artiey_artist');
            if ($post && isset($post->ID, $post->post_type, $post->post_status, $post->post_name)
                && $post->post_type === 'artiey_artist'
                && $post->post_status === 'publish'
                && $post->post_name === $slug) {
                $url = get_permalink((int) $post->ID);
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }

        if ((string) get_option('permalink_structure', '') === '') {
            return add_query_arg('artiey_artist_route', $slug, home_url('/'));
        }

        $path = '/artists/' . rawurlencode($slug);
        return user_trailingslashit(home_url($path), 'single');
    }

    /** Canonical content archive is always the top-level /artists/ page. */
    public static function artist_archive_url(): string
    {
        if (function_exists('get_page_by_path')) {
            $page = get_page_by_path('artists', OBJECT, 'page');
            if ($page && isset($page->ID, $page->post_type, $page->post_status, $page->post_name, $page->post_parent)
                && $page->post_type === 'page'
                && $page->post_status === 'publish'
                && $page->post_name === 'artists'
                && (int) $page->post_parent === 0) {
                $url = get_permalink((int) $page->ID);
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }
        // Never emit an accidental /artists-2/ URL from a stale managed-page ID.
        return home_url('/artists/');
    }

    /** Permalink of the post behind a mirrored row ('' when the row was created in art-coa itself). */
    public static function permalink(string $table, string $slug): string
    {
        global $wpdb;
        $pid = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT cpt_post_id FROM ' . Database::table($table) . ' WHERE slug = %s',
            $slug
        ));
        return $pid && get_post_status($pid) === 'publish' ? (string) get_permalink($pid) : '';
    }
}
