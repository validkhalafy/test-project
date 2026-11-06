<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Curator;

defined('ABSPATH') || exit;

/**
 * Canonical content bridge.
 *
 * WordPress posts are the authoritative editorial records for artists, galleries, artworks and exhibitions.
 * The art_* tables remain as a compatibility/relational layer for certificates, ownership, auction and other
 * records that reference numeric table IDs. Existing table IDs are preserved during migration.
 */
final class ContentBridge
{
    private const TYPES = [
        'artists' => ['post_type' => 'artiey_artist', 'title' => 'display_name'],
        'galleries' => ['post_type' => 'artiey_gallery', 'title' => 'name'],
        'artworks' => ['post_type' => 'artiey_artwork', 'title' => 'title'],
        'exhibitions' => ['post_type' => 'artiey_exhibition', 'title' => 'title'],
    ];
    private const BATCH_SIZE = 15;
    // Stable bridge milestone. Bump only when legacy-row conversion logic materially changes.
    private const MIGRATION_VERSION = '2.32.7';
    private const FAILED_ROWS_OPT = 'artiey_aet_bridge_failed_rows';
    private const FINAL_FAILED_ROWS_OPT = 'artiey_aet_bridge_final_failed_rows';
    private const RETRY_HOOK = 'artiey_aet_bridge_retry_failed_rows';
    private const MAX_RETRIES = 2;
    private static bool $suspend_sync = false;
    private static bool $filter_added = false;

    public static function init(): void
    {
        if (!self::$filter_added) {
            add_filter('art_coa_content_sync', [self::class, 'allow_sync'], 999, 2);
            self::$filter_added = true;
        }
        add_action('init', [self::class, 'maybe_queue_migration'], 35);
        add_action(self::RETRY_HOOK, [self::class, 'run_failed_retry_batch']);
    }

    public static function allow_sync(bool $allowed, int $post_id): bool
    {
        return self::$suspend_sync ? false : $allowed;
    }

    /** Queue the safe, resumable legacy-table-to-CPT migration once for this plugin version. */
    public static function maybe_queue_migration(): void
    {
        $stored = (string) get_option('artiey_aet_bridge_migration_version', '');
        if ($stored === self::MIGRATION_VERSION) {
            return;
        }
        // Older parent releases recorded their own version here and queued this migration
        // on every patch. Treat 2.32.7+ as already converted; do not rescan the full legacy tables.
        if ($stored !== '' && version_compare($stored, self::MIGRATION_VERSION, '>=')) {
            update_option('artiey_aet_bridge_migration_version', self::MIGRATION_VERSION, false);
            return;
        }
        self::queue_legacy_migration();
        update_option('artiey_aet_bridge_migration_version', self::MIGRATION_VERSION, false);
    }

    public static function queue_legacy_migration(): void
    {
        // Scheduling must stay cheap. Schema checks/ALTER TABLE are performed by the worker,
        // not while Elementor or the public site is being bootstrapped.
        if (false === get_option(ContentSync::LEGACY_CURSOR_OPT, false)) {
            update_option(ContentSync::LEGACY_CURSOR_OPT, ['type' => 0, 'after' => 0], false);
        }
        if (!wp_next_scheduled(ContentSync::LEGACY_BATCH_HOOK)) {
            wp_schedule_single_event(time() + 30, ContentSync::LEGACY_BATCH_HOOK);
        }
    }

    /** Process a small chunk of legacy records; the original art_* row IDs are never changed. */
    public static function run_migration_batch(): void
    {
        $cursor = get_option(ContentSync::LEGACY_CURSOR_OPT);
        if (!is_array($cursor)) {
            return;
        }
        ContentSync::ensure_schema();
        $tables = array_keys(self::TYPES);
        $index = max(0, (int) ($cursor['type'] ?? 0));
        if ($index >= count($tables)) {
            delete_option(ContentSync::LEGACY_CURSOR_OPT);
            $failed = get_option(self::FAILED_ROWS_OPT, []);
            if (is_array($failed) && $failed) {
                if (!wp_next_scheduled(self::RETRY_HOOK)) {
                    wp_schedule_single_event(time() + 60, self::RETRY_HOOK);
                }
            } else {
                ContentSync::queue_full_sync();
            }
            return;
        }

        global $wpdb;
        $table_key = $tables[$index];
        $table = Database::table($table_key);
        $last = max(0, (int) ($cursor['after'] ?? 0));
        // Scan all rows: unlinked rows are migrated; linked rows are backfilled where CPT metadata is absent.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE id > %d ORDER BY id ASC LIMIT %d",
            $last,
            self::BATCH_SIZE
        ));
        // A temporary database error is not an empty page; retain the cursor and retry it.
        if (isset($wpdb->last_error) && (string) $wpdb->last_error !== '') {
            if (!wp_next_scheduled(ContentSync::LEGACY_BATCH_HOOK)) {
                wp_schedule_single_event(time() + 60, ContentSync::LEGACY_BATCH_HOOK);
            }
            return;
        }
        $rows = is_array($rows) ? $rows : [];

        $failed_rows = get_option(self::FAILED_ROWS_OPT, []);
        $failed_rows = is_array($failed_rows) ? $failed_rows : [];
        $known_failures = [];
        foreach ($failed_rows as $failure) {
            if (is_array($failure) && isset($failure['table'], $failure['id'])) {
                $known_failures[(string) $failure['table'] . ':' . (int) $failure['id']] = true;
            }
        }
        foreach ($rows as $row) {
            if (!self::migrate_row($table_key, $row)) {
                $failure_key = $table_key . ':' . (int) $row->id;
                if (!isset($known_failures[$failure_key])) {
                    $failed_rows[] = ['table' => $table_key, 'id' => (int) $row->id, 'attempts' => 0];
                    $known_failures[$failure_key] = true;
                }
            }
        }
        if ($failed_rows) {
            update_option(self::FAILED_ROWS_OPT, array_values($failed_rows), false);
        }

        if (count($rows) < self::BATCH_SIZE) {
            $cursor = ['type' => $index + 1, 'after' => 0];
        } else {
            $cursor['after'] = (int) end($rows)->id;
        }
        update_option(ContentSync::LEGACY_CURSOR_OPT, $cursor, false);
        wp_schedule_single_event(time() + 8, ContentSync::LEGACY_BATCH_HOOK);
    }

    /** Retry failed rows a bounded number of times, then retain an error list for diagnostics. */
    public static function run_failed_retry_batch(): void
    {
        $failed = get_option(self::FAILED_ROWS_OPT, []);
        if (!is_array($failed) || !$failed) {
            delete_option(self::FAILED_ROWS_OPT);
            ContentSync::queue_full_sync();
            return;
        }

        $batch = array_splice($failed, 0, self::BATCH_SIZE);
        $retry = [];
        $final = get_option(self::FINAL_FAILED_ROWS_OPT, []);
        $final = is_array($final) ? $final : [];
        global $wpdb;

        foreach ($batch as $failure) {
            $table_key = is_array($failure) ? (string) ($failure['table'] ?? '') : '';
            $row_id = is_array($failure) ? absint($failure['id'] ?? 0) : 0;
            $attempts = is_array($failure) ? absint($failure['attempts'] ?? 0) : 0;
            if (!isset(self::TYPES[$table_key]) || $row_id <= 0) {
                continue;
            }

            $table = Database::table($table_key);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $row_id));
            if ($row && self::migrate_row($table_key, $row)) {
                continue;
            }

            $failure['attempts'] = $attempts + 1;
            if ($failure['attempts'] < self::MAX_RETRIES) {
                $retry[] = $failure;
            } else {
                $final[] = [
                    'table' => $table_key,
                    'id' => $row_id,
                    'attempts' => $failure['attempts'],
                    'error' => isset($wpdb->last_error) ? sanitize_text_field((string) $wpdb->last_error) : '',
                ];
            }
        }

        if ($retry) {
            $failed = array_merge($failed, $retry);
        }
        if ($final) {
            update_option(self::FINAL_FAILED_ROWS_OPT, array_values($final), false);
        }
        if ($failed) {
            update_option(self::FAILED_ROWS_OPT, array_values($failed), false);
            if (!wp_next_scheduled(self::RETRY_HOOK)) {
                wp_schedule_single_event(time() + 60, self::RETRY_HOOK);
            }
            return;
        }

        delete_option(self::FAILED_ROWS_OPT);
        ContentSync::queue_full_sync();
    }

    /**
     * Return the stable COA relational row ID for a canonical WordPress post.
     * When the mirror is missing, try to create/adopt it synchronously before returning 0.
     */
    public static function row_id_for_post(string $table_key, int $post_id): int
    {
        if (!isset(self::TYPES[$table_key]) || $post_id <= 0 || get_post_type($post_id) !== self::TYPES[$table_key]['post_type']) {
            return 0;
        }
        ContentSync::ensure_schema();
        global $wpdb;
        $table = Database::table($table_key);
        $linked_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE cpt_post_id = %d ORDER BY id ASC LIMIT 2",
            $post_id
        ));
        $linked_ids = is_array($linked_ids) ? array_values(array_filter(array_map('absint', $linked_ids))) : [];
        if (count($linked_ids) > 1) {
            // Never guess which relational ID certificates/history should use; surface it through
            // the existing bounded sync retry and final-failure report.
            ContentSync::queue_sync_retry($post_id);
            return 0;
        }
        if (count($linked_ids) === 1) {
            return (int) $linked_ids[0];
        }
        $row_id = ContentSync::sync_post($post_id);
        if ($row_id > 0) {
            $linked_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE cpt_post_id = %d ORDER BY id ASC LIMIT 2",
                $post_id
            ));
            $linked_ids = is_array($linked_ids) ? array_values(array_filter(array_map('absint', $linked_ids))) : [];
            if (count($linked_ids) > 1) {
                ContentSync::queue_sync_retry($post_id);
                return 0;
            }
            return count($linked_ids) === 1 ? (int) $linked_ids[0] : 0;
        }
        return 0;
    }

    /**
     * Return an artist object whose editorial fields come from the canonical WordPress post,
     * while preserving the COA numeric row ID needed by artworks/certificates/ownership joins.
     */
    public static function canonical_artist_row(object $row): object
    {
        $row_id = absint($row->id ?? 0);
        if ($row_id <= 0) {
            return $row;
        }
        $post_id = absint($row->cpt_post_id ?? 0);
        if (!$post_id || get_post_type($post_id) !== self::TYPES['artists']['post_type']) {
            $post_id = self::ensure_post_for_row('artists', $row_id);
            if (!$post_id) {
                return $row;
            }
            global $wpdb;
            $fresh = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . Database::table('artists') . ' WHERE id = %d',
                $row_id
            ));
            if ($fresh) {
                $row = $fresh;
            }
        }
        if (get_post_type($post_id) !== self::TYPES['artists']['post_type']) {
            return $row;
        }
        self::write_legacy_meta('artists', $row, $post_id, false);
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return $row;
        }
        $out = clone $row;
        $meta_map = [
            'first_name' => '_aa_first_name', 'last_name' => '_aa_last_name', 'national_id' => '_aa_national_id',
            'email' => '_aa_email', 'phone' => '_aa_phone', 'website' => '_aa_website', 'nationality' => '_aa_nationality',
            'name_en' => '_aa_name_en', 'short_bio' => '_aa_short_bio', 'artistic_statement' => '_aa_statement',
            'city' => '_aa_city', 'birth_year' => '_aa_birth', 'mediums' => '_aa_mediums', 'education' => '_aa_education',
            'awards' => '_aa_awards', 'instagram' => '_aa_instagram', 'telegram' => '_aa_telegram', 'cover_id' => '_aa_cover',
            'cover_url' => '_aa_cover_url', 'avatar_url' => '_aa_avatar_url', 'user_id' => '_aa_user_id',
        ];
        $out->display_name = (string) $post->post_title;
        $out->slug = (string) $post->post_name;
        $out->cpt_post_id = $post_id;
        $body = class_exists('Artiey_AET_Profile_Guard')
            ? (string) \Artiey_AET_Profile_Guard::bio_source($post)
            : (string) $post->post_content;
        if (trim(wp_strip_all_tags($body)) === '') {
            $body = (string) get_post_meta($post_id, '_aa_legacy_bio', true);
        }
        $out->bio = wp_kses_post($body);
        $out->avatar_id = absint(get_post_thumbnail_id($post_id));
        foreach ($meta_map as $property => $meta_key) {
            $value = get_post_meta($post_id, $meta_key, true);
            if (is_scalar($value) && (string) $value !== '') {
                $out->{$property} = $value;
            } elseif (in_array($property, ['first_name', 'last_name', 'national_id', 'email', 'phone', 'website', 'nationality', 'name_en', 'short_bio', 'artistic_statement', 'city', 'birth_year', 'mediums', 'education', 'awards', 'instagram', 'telegram', 'cover_id', 'cover_url', 'avatar_url', 'user_id'], true)) {
                $out->{$property} = '';
            }
        }
        $out->status = $post->post_status === 'publish' ? 'active' : 'inactive';
        return $out;
    }

    /** Return canonical post ID for a legacy table row, migrating it synchronously if required. */
    public static function ensure_post_for_row(string $table_key, int $row_id): int
    {
        if (!isset(self::TYPES[$table_key]) || $row_id <= 0) {
            return 0;
        }
        global $wpdb;
        $table = Database::table($table_key);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $row_id));
        if (!$row) {
            return 0;
        }
        $post_id = (int) ($row->cpt_post_id ?? 0);
        if ($post_id && get_post_type($post_id) === self::TYPES[$table_key]['post_type']) {
            self::write_legacy_meta($table_key, $row, $post_id, false);
            return $post_id;
        }
        return self::migrate_row($table_key, $row);
    }

    /** Convert a legacy record to its WordPress post, preserving its database ID and relationships. */
    private static function migrate_row(string $table_key, object $row): int
    {
        global $wpdb;
        if (!isset(self::TYPES[$table_key])) {
            return 0;
        }
        $spec = self::TYPES[$table_key];
        $post_type = $spec['post_type'];
        $linked = (int) ($row->cpt_post_id ?? 0);
        if ($linked && get_post_type($linked) === $post_type) {
            // Backfill fields from legacy rows only where the canonical post has no value.
            self::write_legacy_meta($table_key, $row, $linked, false);
            return $linked;
        }
        if ($linked) {
            // A stale/deleted linkage must not prevent this legacy row from being adopted again.
            $detached = $wpdb->update(Database::table($table_key), ['cpt_post_id' => null], ['id' => (int) $row->id]);
            if ($detached === false) {
                return 0;
            }
        }

        $title = self::row_title($table_key, $row);
        if ($title === '') {
            $title = ucfirst(substr($table_key, 0, -1)) . ' ' . (int) $row->id;
        }
        $stored_slug = trim((string) ($row->slug ?? ''));
        $slug_source = $stored_slug !== '' ? rawurldecode($stored_slug) : $title;
        $slug = class_exists('\\Artiey_AET_Slug') ? \Artiey_AET_Slug::from($slug_source, $table_key) : sanitize_title($slug_source);
        $slug = sanitize_title($slug);
        if ($slug === '') {
            $slug = sanitize_title($title) ?: ($table_key . '-' . (int) $row->id);
        }

        // Before normalizing a table slug, look for a WordPress post that may already represent
        // the same legacy row. Adopt it by its old path rather than creating a duplicate post.
        // When such a post exists, preserve its post_name during this migration so old URLs keep
        // working; new/table-only records use the normalized slug below.
        $existing_legacy = null;
        if ($stored_slug !== '') {
            $legacy_candidates = array_unique([
                $stored_slug,
                rawurldecode($stored_slug),
                sanitize_title(rawurldecode($stored_slug)),
            ]);
            foreach ($legacy_candidates as $candidate) {
                if ($candidate === '') continue;
                $candidate_post = get_page_by_path($candidate, OBJECT, $post_type);
                if ($candidate_post instanceof \WP_Post) {
                    $existing_legacy = $candidate_post;
                    break;
                }
            }
        }
        if (!$existing_legacy && $table_key === 'artists') {
            $identity_match = self::find_existing_artist_post([
                'user_id' => absint($row->user_id ?? 0),
                'national_id' => (string) ($row->national_id ?? ''),
                'email' => (string) ($row->email ?? ''),
            ]);
            if (!empty($identity_match['ambiguous'])) {
                // Never clone an identity when the old row could match multiple canonical profiles.
                return 0;
            }
            $identity_post_id = absint($identity_match['post_id'] ?? 0);
            $identity_post = $identity_post_id ? get_post($identity_post_id) : null;
            if ($identity_post instanceof \WP_Post && $identity_post->post_type === $post_type) {
                $existing_legacy = $identity_post;
                $slug = (string) $identity_post->post_name;
            }
        }
        if ($existing_legacy instanceof \WP_Post) {
            if ($table_key === 'artists' && self::artist_identity_conflicts($row, $existing_legacy)) {
                // The slug is not enough to prove identity if account/ID/email data conflicts.
                return 0;
            }
            $slug = (string) $existing_legacy->post_name;
        }

        // Normalize legacy non-Latin/encoded slugs only after checking the unique table constraint.
        // Never assume a single fallback suffix is free: a hand-created/imported record may already
        // own it. Keep candidates within the legacy table's VARCHAR(200) slug limit.
        if ($stored_slug !== $slug) {
            $slug_base = $slug;
            $attempt = 0;
            do {
                $collision = (int) $wpdb->get_var($wpdb->prepare(
                    'SELECT id FROM ' . Database::table($table_key) . ' WHERE slug = %s AND id <> %d LIMIT 1',
                    $slug,
                    (int) $row->id
                ));
                if (!$collision) {
                    break;
                }
                ++$attempt;
                if ($attempt > 100) {
                    return 0;
                }
                $suffix = '-legacy-' . (int) $row->id . ($attempt > 1 ? '-' . $attempt : '');
                $slug = rtrim(substr($slug_base, 0, max(1, 200 - strlen($suffix))), '-') . $suffix;
            } while (true);

            if ($stored_slug !== '') {
                SlugHistory::record($table_key, $stored_slug, $slug);
            }
            $slug_updated = $wpdb->update(Database::table($table_key), ['slug' => $slug], ['id' => (int) $row->id]);
            if ($slug_updated === false) {
                return 0;
            }
            $row->slug = $slug;
        }

        $existing = $existing_legacy instanceof \WP_Post ? $existing_legacy : get_page_by_path($slug, OBJECT, $post_type);
        if ($existing instanceof \WP_Post) {
            $owner_id = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . Database::table($table_key) . ' WHERE cpt_post_id = %d AND id <> %d LIMIT 1',
                (int) $existing->ID,
                (int) $row->id
            ));
            // Never attach two legacy relational records to the same canonical post.
            if ($owner_id) {
                $existing = null;
                $existing_legacy = null;
            }
        }
        $is_new = !$existing;
        $post_id = $existing ? (int) $existing->ID : 0;
        if ($is_new) {
            $post_status = self::wp_status((string) ($row->status ?? ''), $table_key);
            $post_data = [
                'post_type' => $post_type,
                'post_title' => $title,
                'post_name' => $slug,
                'post_content' => self::row_content($table_key, $row),
                'post_status' => $post_status,
                'post_author' => absint($row->user_id ?? 0) ?: get_current_user_id() ?: 1,
            ];
            try {
                self::$suspend_sync = true;
                $created = wp_insert_post(wp_slash($post_data), true);
            } finally {
                // Always release the guard, including when another hook throws.
                self::$suspend_sync = false;
            }
            if (is_wp_error($created) || !$created) {
                return 0;
            }
            $post_id = (int) $created;
        }

        self::write_legacy_meta($table_key, $row, $post_id, $is_new);
        // Link the exact legacy row first. This preserves its numeric ID and prevents a duplicate
        // row if the normalized slug differs or a historical slug collision exists.
        $linked = $wpdb->update(Database::table($table_key), ['cpt_post_id' => $post_id], ['id' => (int) $row->id]);
        if ($linked === false) {
            return 0;
        }
        $row->cpt_post_id = $post_id;
        $mirror_id = ContentSync::sync_post($post_id);
        if (!$mirror_id) {
            // Keep the content available in WordPress but leave the relational row retryable.
            $wpdb->update(Database::table($table_key), ['cpt_post_id' => null], ['id' => (int) $row->id]);
            return 0;
        }
        return $post_id;
    }

    /** Save the old COA admin form through WordPress; the mirror gets the matching table row afterwards. */
    public static function save_from_form(string $entity, array $data, int $row_id = 0): array
    {
        $table_key = match ($entity) {
            'artist' => 'artists', 'gallery' => 'galleries', 'artwork' => 'artworks', 'exhibition' => 'exhibitions',
            default => '',
        };
        if ($table_key === '') {
            return ['success' => false, 'message' => 'نوع محتوا پشتیبانی نمی‌شود.'];
        }
        ContentSync::ensure_schema();

        $title = match ($entity) {
            'artist' => (sanitize_text_field((string) ($data['display_name'] ?? '')) ?: sanitize_text_field(trim((string) ($data['first_name'] ?? '') . ' ' . (string) ($data['last_name'] ?? '')))),
            'gallery' => sanitize_text_field((string) ($data['name'] ?? '')),
            default => sanitize_text_field((string) ($data['title'] ?? '')),
        };
        if ($title === '') {
            return ['success' => false, 'message' => $entity === 'artist' ? 'نام هنرمند الزامی است.' : 'عنوان یا نام الزامی است.'];
        }
        if ($entity === 'artist' && (trim((string) ($data['first_name'] ?? '')) === '' || trim((string) ($data['last_name'] ?? '')) === '')) {
            return ['success' => false, 'message' => 'نام و نام خانوادگی الزامی است.'];
        }
        if ($entity === 'artwork') {
            $artist_row_id = absint($data['artist_id'] ?? 0);
            if (!$artist_row_id || !self::ensure_post_for_row('artists', $artist_row_id)) {
                return ['success' => false, 'message' => 'برای ثبت اثر، هنرمند معتبر انتخاب کنید.'];
            }
        }

        $post_type = self::TYPES[$table_key]['post_type'];
        $row_id = absint($row_id);
        $post_id = 0;
        if ($row_id === 0 && $entity === 'artist') {
            $identity_match = self::find_existing_artist_identity($data);
            if (!empty($identity_match['ambiguous'])) {
                return ['success' => false, 'message' => 'چند پروفایل با شناسه، کد ملی یا ایمیل یکسان پیدا شد. برای جلوگیری از ایجاد هنرمند تکراری، ابتدا موارد تکراری را بررسی و یکی را انتخاب کنید.'];
            }
            $row_id = absint($identity_match['row_id'] ?? 0);
            $post_id = absint($identity_match['post_id'] ?? 0);
        }
        if ($row_id > 0) {
            // An edit/identity-adoption request must never silently fall back to creating a new post.
            $post_id = self::ensure_post_for_row($table_key, $row_id);
            if (!$post_id || get_post_type($post_id) !== $post_type) {
                return ['success' => false, 'message' => 'رکورد موردنظر پیدا نشد یا قابل بازیابی نیست؛ مورد جدیدی ایجاد نشد.'];
            }
        } elseif ($post_id > 0 && get_post_type($post_id) !== $post_type) {
            return ['success' => false, 'message' => 'پروفایل متناظر با شناسه موجود معتبر نیست؛ رکورد تازه‌ای ایجاد نشد.'];
        }
        $slug = sanitize_title((string) ($data['slug'] ?? ''));
        if ($slug === '' && $post_id) {
            $slug = sanitize_title((string) get_post_field('post_name', $post_id));
        }
        if ($slug === '') {
            $slug = class_exists('\\Artiey_AET_Slug') ? \Artiey_AET_Slug::from($title, $table_key) : sanitize_title($title);
        }
        $status_value = (string) ($data['status'] ?? '');
        if ($status_value === '' && $post_id > 0) {
            $existing_status = (string) get_post_status($post_id);
            $status_value = match ($existing_status) {
                'publish' => 'active', 'pending' => 'pending', 'private' => 'private', default => 'draft',
            };
        }
        if ($status_value === '') {
            $status_value = ($entity === 'artist' || $entity === 'gallery') ? 'active' : 'draft';
        }
        $status = self::wp_status($status_value, $table_key);
        $content = match ($entity) {
            'artist' => wp_kses_post((string) ($data['bio'] ?? '')),
            'gallery' => wp_kses_post((string) ($data['about'] ?? '')),
            default => wp_kses_post((string) ($data['description'] ?? '')),
        };
        // Existing Elementor page data is layout, not biography text. Never replace that layout
        // from this legacy form; store its editorial field in a dedicated fallback meta instead.
        $preserve_elementor_layout = $post_id > 0 && self::has_elementor_layout($post_id);
        $postarr = [
            'post_type' => $post_type,
            'post_title' => $title,
            'post_name' => $slug,
            'post_status' => $status,
        ];
        if (!$preserve_elementor_layout) {
            $postarr['post_content'] = $content;
        }
        if ($post_id) {
            $postarr['ID'] = $post_id;
        } else {
            $postarr['post_author'] = absint($data['user_id'] ?? 0) ?: get_current_user_id() ?: 1;
        }
        $was_existing = $post_id > 0;

        try {
            self::$suspend_sync = true;
            $saved = $post_id ? wp_update_post(wp_slash($postarr), true) : wp_insert_post(wp_slash($postarr), true);
        } finally {
            self::$suspend_sync = false;
        }
        if (is_wp_error($saved) || !$saved) {
            return ['success' => false, 'message' => is_wp_error($saved) ? $saved->get_error_message() : 'ذخیره پست وردپرس انجام نشد.'];
        }
        $post_id = (int) $saved;
        if ($preserve_elementor_layout) {
            update_post_meta($post_id, self::fallback_content_meta($entity), $content);
        }
        self::write_form_meta($entity, $data, $post_id);
        $mirror_id = ContentSync::sync_post($post_id);
        if (!$mirror_id) {
            // Keep the post as the canonical record, but do not leave a failed mirror write stranded.
            ContentSync::queue_sync_retry($post_id);
            return ['success' => false, 'message' => 'محتوا ذخیره شد، اما آینه دیتابیس به‌روزرسانی نشد؛ تلاش مجدد زمان‌بندی شد. شناسه پست: ' . $post_id, 'post_id' => $post_id];
        }
        Audit::log($entity, $mirror_id, $was_existing ? 'updated_via_cpt' : 'created_via_cpt');
        return ['success' => true, 'id' => $mirror_id, 'post_id' => $post_id, 'slug' => get_post_field('post_name', $post_id)];
    }

    /** Return true when a legacy artist row and an existing WordPress post have conflicting stable identities. */
    private static function artist_identity_conflicts(object $row, \WP_Post $post): bool
    {
        $row_user = absint($row->user_id ?? 0);
        $post_user = absint(get_post_meta((int) $post->ID, '_aa_user_id', true));
        if ($row_user > 0 && $post_user > 0) {
            // A shared account ID is the strongest identifier; if both exist they must agree.
            return $row_user !== $post_user;
        }
        $pairs = [
            [(string) ($row->national_id ?? ''), (string) get_post_meta((int) $post->ID, '_aa_national_id', true)],
            [strtolower(trim((string) ($row->email ?? ''))), strtolower(trim((string) get_post_meta((int) $post->ID, '_aa_email', true)))],
        ];
        foreach ($pairs as [$legacy, $canonical]) {
            $legacy = trim($legacy);
            $canonical = trim($canonical);
            if ($legacy !== '' && $canonical !== '' && $legacy !== $canonical) {
                return true;
            }
        }
        return false;
    }

    /**
     * Match an artist create request to an existing relational record or canonical post using stable identity.
     * Name-only matching is deliberately forbidden because two artists can share a name.
     * @return array{row_id:int,post_id:int,ambiguous:bool}
     */
    private static function find_existing_artist_identity(array $data): array
    {
        global $wpdb;
        $table = Database::table('artists');
        $identities = [
            'user_id' => ['column' => 'user_id', 'meta' => '_aa_user_id', 'value' => absint($data['user_id'] ?? 0), 'kind' => 'int'],
            'national_id' => ['column' => 'national_id', 'meta' => '_aa_national_id', 'value' => trim((string) ($data['national_id'] ?? '')), 'kind' => 'text'],
            'email' => ['column' => 'email', 'meta' => '_aa_email', 'value' => strtolower(trim((string) ($data['email'] ?? ''))), 'kind' => 'text'],
        ];
        $row_ids = [];
        $post_ids = [];
        $row_post_ids = [];
        $row_match_keys = [];
        $post_match_keys = [];
        foreach ($identities as $key => $identity) {
            $value = $identity['value'];
            if ($value === '' || $value === 0) {
                continue;
            }
            $operator = $identity['kind'] === 'int' ? '%d' : '%s';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, cpt_post_id FROM `{$table}` WHERE `{$identity['column']}` = {$operator} ORDER BY id ASC LIMIT 2",
                $value
            )) ?: [];
            if (count($rows) > 1) {
                return ['row_id' => 0, 'post_id' => 0, 'ambiguous' => true];
            }
            if (count($rows) === 1) {
                $rid = absint($rows[0]->id ?? 0);
                $pid = absint($rows[0]->cpt_post_id ?? 0);
                if ($rid) {
                    $row_ids[$rid] = true;
                    $row_match_keys[$key] = true;
                    if ($pid) {
                        $row_post_ids[$pid] = true;
                    }
                }
            }
            $ids = get_posts([
                'post_type' => 'artiey_artist',
                'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
                'numberposts' => 2,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_query' => [[
                    'key' => $identity['meta'],
                    'value' => (string) $value,
                    'compare' => '=',
                ]],
                'suppress_filters' => true,
            ]);
            $ids = is_array($ids) ? array_values(array_filter(array_map('absint', $ids))) : [];
            if (count($ids) > 1) {
                return ['row_id' => 0, 'post_id' => 0, 'ambiguous' => true];
            }
            if (count($ids) === 1) {
                $post_ids[$ids[0]] = true;
                $post_match_keys[$key] = true;
            }
        }
        // Different stable fields pointing at different table rows or posts is an identity conflict.
        if (count($row_ids) > 1 || count($post_ids) > 1 || count($row_post_ids) > 1) {
            return ['row_id' => 0, 'post_id' => 0, 'ambiguous' => true];
        }
        $row_id = (int) (array_key_first($row_ids) ?: 0);
        $post_id = (int) (array_key_first($post_ids) ?: 0);
        $linked_post_id = (int) (array_key_first($row_post_ids) ?: 0);
        if ($row_id && $linked_post_id && $post_id && $linked_post_id !== $post_id) {
            return ['row_id' => 0, 'post_id' => 0, 'ambiguous' => true];
        }
        if ($row_id && $post_id && !$linked_post_id && !array_intersect_key($row_match_keys, $post_match_keys)) {
            // A table row matched one identity field while a different post matched another. Neither is safe to adopt.
            return ['row_id' => 0, 'post_id' => 0, 'ambiguous' => true];
        }
        if ($row_id && $linked_post_id) {
            $post_id = $linked_post_id;
        }
        return ['row_id' => $row_id, 'post_id' => $post_id, 'ambiguous' => false];
    }

    /** Find one canonical artist post by an old relational row's stable identity, using post meta only. */
    private static function find_existing_artist_post(array $data): array
    {
        $identities = [
            ['meta' => '_aa_user_id', 'value' => absint($data['user_id'] ?? 0)],
            ['meta' => '_aa_national_id', 'value' => trim((string) ($data['national_id'] ?? ''))],
            ['meta' => '_aa_email', 'value' => strtolower(trim((string) ($data['email'] ?? '')))],
        ];
        $matches = [];
        foreach ($identities as $identity) {
            $value = $identity['value'];
            if ($value === '' || $value === 0) {
                continue;
            }
            $ids = get_posts([
                'post_type' => 'artiey_artist',
                'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
                'numberposts' => 2,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_query' => [['key' => $identity['meta'], 'value' => (string) $value, 'compare' => '=']],
                'suppress_filters' => true,
            ]);
            $ids = is_array($ids) ? array_values(array_filter(array_map('absint', $ids))) : [];
            if (count($ids) > 1) {
                return ['post_id' => 0, 'ambiguous' => true];
            }
            if (count($ids) === 1) {
                $matches[$ids[0]] = true;
            }
        }
        if (count($matches) > 1) {
            return ['post_id' => 0, 'ambiguous' => true];
        }
        return ['post_id' => (int) (array_key_first($matches) ?: 0), 'ambiguous' => false];
    }

    /**
     * Update a legacy/member-dashboard artist row by editing its canonical WordPress post first.
     * The numeric art_artists.id is retained so existing artworks and certificates remain linked.
     * @return array<string,mixed>
     */
    public static function update_from_entity_row(string $entity, int $row_id, array $data): array
    {
        if ($entity !== 'artist' || $row_id <= 0) {
            return ['success' => false, 'message' => 'درخواست ویرایش هنرمند معتبر نیست.'];
        }
        ContentSync::ensure_schema();
        $post_id = self::ensure_post_for_row('artists', $row_id);
        if (!$post_id || get_post_type($post_id) !== 'artiey_artist') {
            return ['success' => false, 'message' => 'پروفایل اصلی هنرمند پیدا نشد؛ اطلاعات تغییر نکرد.'];
        }
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return ['success' => false, 'message' => 'پست اصلی هنرمند در دسترس نیست.'];
        }
        $meta_map = [
            'first_name' => '_aa_first_name', 'last_name' => '_aa_last_name', 'national_id' => '_aa_national_id',
            'email' => '_aa_email', 'phone' => '_aa_phone', 'website' => '_aa_website', 'nationality' => '_aa_nationality',
            'name_en' => '_aa_name_en', 'short_bio' => '_aa_short_bio', 'statement' => '_aa_statement',
            'city' => '_aa_city', 'birth_year' => '_aa_birth', 'mediums' => '_aa_mediums', 'education' => '_aa_education',
            'awards' => '_aa_awards', 'instagram' => '_aa_instagram', 'telegram' => '_aa_telegram', 'user_id' => '_aa_user_id',
        ];
        foreach ($meta_map as $key => $meta_key) {
            if (!array_key_exists($key, $data)) continue;
            $raw = is_scalar($data[$key]) ? (string) $data[$key] : '';
            if ($key === 'user_id') {
                $value = absint($raw);
            } elseif ($key === 'email') {
                $value = sanitize_email($raw);
            } elseif ($key === 'website') {
                $value = esc_url_raw($raw);
            } elseif ($key === 'birth_year' || in_array($key, ['instagram', 'telegram', 'mediums'], true)) {
                $clean = ProfileFields::clean('artists', [$key => $raw]);
                $value = $clean[$key] ?? '';
            } elseif (in_array($key, ['short_bio', 'statement', 'education', 'awards'], true)) {
                $value = sanitize_textarea_field($raw);
            } else {
                $value = sanitize_text_field($raw);
            }
            update_post_meta($post_id, $meta_key, $value);
        }

        $postarr = ['ID' => $post_id];
        if (array_key_exists('display_name', $data)) {
            $new_title = sanitize_text_field((string) $data['display_name']);
            if ($new_title !== '') $postarr['post_title'] = $new_title;
        } elseif (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
            $first = array_key_exists('first_name', $data) ? sanitize_text_field((string) $data['first_name']) : (string) get_post_meta($post_id, '_aa_first_name', true);
            $last = array_key_exists('last_name', $data) ? sanitize_text_field((string) $data['last_name']) : (string) get_post_meta($post_id, '_aa_last_name', true);
            $new_title = trim($first . ' ' . $last);
            if ($new_title !== '') $postarr['post_title'] = $new_title;
        }
        if (array_key_exists('status', $data)) {
            $postarr['post_status'] = self::wp_status((string) $data['status'], 'artists');
        }
        if (array_key_exists('slug', $data) && trim((string) $data['slug']) !== '') {
            $postarr['post_name'] = sanitize_title((string) $data['slug']);
        }
        if (array_key_exists('bio', $data)) {
            $bio = wp_kses_post((string) $data['bio']);
            if (self::has_elementor_layout($post_id)) {
                update_post_meta($post_id, '_aa_legacy_bio', $bio);
            } else {
                $postarr['post_content'] = $bio;
                delete_post_meta($post_id, '_aa_legacy_bio');
            }
        }
        if (array_key_exists('avatar_id', $data) || array_key_exists('avatar_url', $data)) {
            self::image_meta($post_id, 'avatar', '_thumbnail_id', '_aa_avatar_url', $data);
        }
        if (array_key_exists('cover_id', $data) || array_key_exists('cover_url', $data)) {
            self::image_meta($post_id, 'cover', '_aa_cover', '_aa_cover_url', $data);
        }
        if (count($postarr) > 1) {
            try {
                self::$suspend_sync = true;
                $saved = wp_update_post(wp_slash($postarr), true);
            } finally {
                self::$suspend_sync = false;
            }
            if (is_wp_error($saved) || !$saved) {
                return ['success' => false, 'message' => is_wp_error($saved) ? $saved->get_error_message() : 'ذخیره پروفایل هنرمند ناموفق بود.'];
            }
        }
        $mirror_id = ContentSync::sync_post($post_id);
        if (!$mirror_id) {
            ContentSync::queue_sync_retry($post_id);
            return ['success' => false, 'message' => 'پروفایل وردپرس ذخیره شد؛ همگام‌سازی COA برای تلاش مجدد صف‌بندی شد.', 'id' => $row_id, 'post_id' => $post_id];
        }
        Audit::log('artist', $mirror_id, 'updated_via_cpt');
        return ['success' => true, 'id' => $mirror_id, 'post_id' => $post_id, 'slug' => (string) get_post_field('post_name', $post_id)];
    }

    private static function has_elementor_layout(int $post_id): bool
    {
        return get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder'
            || trim((string) get_post_meta($post_id, '_elementor_data', true)) !== '';
    }

    private static function fallback_content_meta(string $entity): string
    {
        return match ($entity) {
            'artist' => '_aa_legacy_bio',
            'gallery' => '_ag_legacy_about',
            'artwork' => '_aw_legacy_description',
            'exhibition' => '_ae_legacy_description',
            default => '',
        };
    }

    private static function write_form_meta(string $entity, array $data, int $post_id): void
    {
        $map = match ($entity) {
            'artist' => [
                'first_name' => '_aa_first_name', 'last_name' => '_aa_last_name', 'national_id' => '_aa_national_id',
                'email' => '_aa_email', 'phone' => '_aa_phone', 'website' => '_aa_website', 'nationality' => '_aa_nationality',
                'name_en' => '_aa_name_en', 'short_bio' => '_aa_short_bio', 'statement' => '_aa_statement', 'user_id' => '_aa_user_id',
                'city' => '_aa_city', 'birth_year' => '_aa_birth', 'mediums' => '_aa_mediums', 'education' => '_aa_education',
                'awards' => '_aa_awards', 'instagram' => '_aa_instagram', 'telegram' => '_aa_telegram',
            ],
            'gallery' => [
                'email' => '_ag_email', 'phone' => '_ag_phone', 'city' => '_ag_city', 'country' => '_ag_country',
                'address' => '_ag_address', 'website' => '_ag_website', 'brand_color' => '_ag_brand_color',
                'brand_color_2' => '_ag_brand_color_2', 'signature_name' => '_ag_signature_name', 'about' => '_ag_short_bio',
            ],
            'artwork' => [
                'medium' => '_aw_medium', 'year_created' => '_aw_year', 'dimensions' => '_aw_dimensions',
                'description' => '_aw_short_description', 'price' => '_aw_price', 'currency' => '_aw_currency',
                'sale_note' => '_aw_sale_note', 'title_en' => '_aw_title_en', 'edition' => '_aw_edition',
            ],
            'exhibition' => [
                'start_date' => '_ae_start', 'end_date' => '_ae_end', 'event_type' => '_ae_type',
                'artist_label' => '_ae_artist', 'subtitle' => '_ae_subtitle', 'admission_info' => '_ae_admission',
                'opening_note' => '_ae_opening', 'website' => '_ae_link',
            ],
            default => [],
        };
        foreach ($map as $key => $meta_key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = is_scalar($data[$key]) ? (string) $data[$key] : '';
            if ($key === 'user_id') {
                $value = (string) absint($data[$key]);
            } elseif ($key === 'email') {
                $value = sanitize_email($value);
            } elseif (in_array($key, ['birth_year', 'mediums', 'instagram', 'telegram'], true)) {
                $clean = ProfileFields::clean('artists', [$key => $value]);
                $value = (string) ($clean[$key] ?? '');
            } elseif (in_array($key, ['website'], true)) {
                $value = esc_url_raw($value);
            } elseif (in_array($key, ['about', 'bio', 'description'], true)) {
                $value = wp_kses_post($value);
            } elseif (in_array($key, ['address', 'sale_note', 'short_bio', 'statement', 'education', 'awards'], true)) {
                $value = sanitize_textarea_field($value);
            } else {
                $value = sanitize_text_field($value);
            }
            update_post_meta($post_id, $meta_key, $value);
        }

        if ($entity === 'artist') {
            update_post_meta($post_id, '_aa_nationality', sanitize_text_field((string) ($data['nationality'] ?? get_post_meta($post_id, '_aa_nationality', true))));
            self::image_meta($post_id, 'avatar', '_thumbnail_id', '_aa_avatar_url', $data);
            self::image_meta($post_id, 'cover', '_aa_cover', '_aa_cover_url', $data);
        } elseif ($entity === 'gallery') {
            self::image_meta($post_id, 'logo', '_thumbnail_id', '_ag_logo_url', $data);
            self::image_meta($post_id, 'cover', '_ag_cover', '_ag_cover_url', $data);
            self::image_meta($post_id, 'signature', '_ag_signature_id', '_ag_signature_url', $data);
            update_post_meta($post_id, '_ag_visitor_info', sanitize_textarea_field((string) ($data['visitor_info'] ?? '')));
            update_post_meta($post_id, '_ag_instagram', sanitize_text_field((string) ($data['instagram'] ?? '')));
            update_post_meta($post_id, '_ag_telegram', sanitize_text_field((string) ($data['telegram'] ?? '')));
            update_post_meta($post_id, '_ag_whatsapp', sanitize_text_field((string) ($data['whatsapp'] ?? '')));
            update_post_meta($post_id, '_ag_hours_note', sanitize_textarea_field((string) ($data['hours_note'] ?? '')));
        } elseif ($entity === 'artwork') {
            $artist_post = self::ensure_post_for_row('artists', absint($data['artist_id'] ?? 0));
            if ($artist_post) update_post_meta($post_id, '_aw_artist', $artist_post);
            $gallery_post = self::ensure_post_for_row('galleries', absint($data['gallery_id'] ?? 0));
            update_post_meta($post_id, '_aw_gallery', $gallery_post);
            $status = sanitize_key((string) ($data['status'] ?? 'pending'));
            update_post_meta($post_id, '_aw_moderation_status', $status);
            update_post_meta($post_id, '_aw_sale', $status === 'sold' ? 'sold' : (!empty($data['for_sale']) ? 'available' : 'unavailable'));
            update_post_meta($post_id, '_aw_price_mode', isset($data['price']) && (float) $data['price'] > 0 ? 'show' : 'hide');
            self::image_meta($post_id, 'image', '_thumbnail_id', '_aw_image_url', $data);
            $photos = Media::ids($data['gallery_ids'] ?? '');
            update_post_meta($post_id, '_aw_photos', $photos);
        } elseif ($entity === 'exhibition') {
            $gallery_post = self::ensure_post_for_row('galleries', absint($data['gallery_id'] ?? 0));
            update_post_meta($post_id, '_ae_gallery', $gallery_post);
            update_post_meta($post_id, '_ae_curator_id', absint($data['curator_id'] ?? 0));
            $curator = absint($data['curator_id'] ?? 0) ? Curator::get(absint($data['curator_id'])) : null;
            update_post_meta($post_id, '_ae_curator', is_object($curator) ? (string) ($curator->display_name ?? '') : '');
            self::image_meta($post_id, 'poster', '_thumbnail_id', '_ae_poster_url', $data);
            update_post_meta($post_id, '_ae_photos', Media::ids($data['gallery_ids'] ?? ''));
            update_post_meta($post_id, '_ae_moderation_status', sanitize_key((string) ($data['status'] ?? 'published')));
        }
    }

    private static function image_meta(int $post_id, string $field, string $id_meta, string $url_meta, array $data): void
    {
        $id_key = $field . '_id';
        $url_key = $field . '_url';
        if (!array_key_exists($id_key, $data) && !array_key_exists($url_key, $data)) {
            return;
        }
        $id = absint($data[$id_key] ?? 0);
        $url = esc_url_raw((string) ($data[$url_key] ?? ''));
        $is_valid_image = $id > 0 && get_post_type($id) === 'attachment' && wp_attachment_is_image($id);
        if ($id > 0 && !$is_valid_image) {
            // Do not let a malformed/non-image ID clear or desynchronize a good existing image.
            return;
        }
        if ($id_meta === '_thumbnail_id') {
            if ($is_valid_image) {
                set_post_thumbnail($post_id, $id);
            } elseif (array_key_exists($id_key, $data) && $id === 0) {
                // An explicit empty ID means remove the thumbnail; a URL-only partial update does not.
                delete_post_thumbnail($post_id);
            }
        } elseif (array_key_exists($id_key, $data)) {
            update_post_meta($post_id, $id_meta, $id);
        }
        if (array_key_exists($url_key, $data)) {
            update_post_meta($post_id, $url_meta, $url);
        }
    }

    private static function row_title(string $table_key, object $row): string
    {
        return match ($table_key) {
            'artists' => trim((string) ($row->display_name ?? '') ?: trim((string) ($row->first_name ?? '') . ' ' . (string) ($row->last_name ?? ''))),
            'galleries' => trim((string) ($row->name ?? '')),
            default => trim((string) ($row->title ?? '')),
        };
    }

    private static function row_content(string $table_key, object $row): string
    {
        $field = match ($table_key) {
            'artists' => 'bio', 'galleries' => 'about', default => 'description',
        };
        return wp_kses_post((string) ($row->{$field} ?? ''));
    }

    private static function wp_status(string $status, string $table_key): string
    {
        $status = sanitize_key($status);
        if (in_array($status, ['active', 'approved', 'published', 'sold', 'publish'], true)) return 'publish';
        if ($status === 'pending') return 'pending';
        if ($status === 'private') return 'private';
        return 'draft';
    }

    /** Write missing legacy metadata only. Existing editorial fields on a matching CPT are never overwritten. */
    private static function write_legacy_meta(string $table_key, object $row, int $post_id, bool $is_new): void
    {
        // Older releases sometimes kept the only biography/description in the relational table.
        // Backfill it only when the canonical WordPress post has no content at all; never overwrite
        // Elementor layouts or editorial text already stored in the post.
        $legacy_content = self::row_content($table_key, $row);
        $current_content = (string) get_post_field('post_content', $post_id);
        if (trim(wp_strip_all_tags($current_content)) === '' && trim(wp_strip_all_tags($legacy_content)) !== '') {
            self::$suspend_sync = true;
            try {
                wp_update_post(wp_slash([
                    'ID' => $post_id,
                    'post_content' => $legacy_content,
                ]));
            } finally {
                self::$suspend_sync = false;
            }
        }
        // If an existing Elementor-rendered post contains no extractable editorial text, retain
        // the old description in a fallback meta field rather than letting the mirror blank it.
        $post_object = get_post($post_id);
        $editorial_source = class_exists('Artiey_AET_Profile_Guard') && $post_object instanceof \WP_Post
            ? \Artiey_AET_Profile_Guard::bio_source($post_object)
            : (string) get_post_field('post_content', $post_id);
        if (trim(wp_strip_all_tags((string) $editorial_source)) === '' && trim(wp_strip_all_tags($legacy_content)) !== '') {
            $fallback_key = match ($table_key) {
                'artists' => '_aa_legacy_bio',
                'galleries' => '_ag_legacy_about',
                'artworks' => '_aw_legacy_description',
                'exhibitions' => '_ae_legacy_description',
                default => '',
            };
            if ($fallback_key !== '' && get_post_meta($post_id, $fallback_key, true) === '') {
                update_post_meta($post_id, $fallback_key, $legacy_content);
            }
        }

        $maps = match ($table_key) {
            'artists' => [
                'first_name' => '_aa_first_name', 'last_name' => '_aa_last_name', 'national_id' => '_aa_national_id',
                'email' => '_aa_email', 'phone' => '_aa_phone', 'website' => '_aa_website', 'nationality' => '_aa_nationality',
                'artistic_statement' => '_aa_statement', 'name_en' => '_aa_name_en', 'city' => '_aa_city', 'short_bio' => '_aa_short_bio',
                'mediums' => '_aa_mediums', 'education' => '_aa_education', 'awards' => '_aa_awards', 'instagram' => '_aa_instagram', 'telegram' => '_aa_telegram',
                'cover_id' => '_aa_cover', 'birth_year' => '_aa_birth', 'user_id' => '_aa_user_id',
            ],
            'galleries' => [
                'email' => '_ag_email', 'phone' => '_ag_phone', 'city' => '_ag_city', 'country' => '_ag_country', 'address' => '_ag_address',
                'website' => '_ag_website', 'visitor_info' => '_ag_visitor_info', 'cover_id' => '_ag_cover', 'name_en' => '_ag_name_en',
                'short_bio' => '_ag_short_bio', 'founded_year' => '_ag_founded', 'lat' => '_ag_lat', 'lng' => '_ag_lng',
                'instagram' => '_ag_instagram', 'telegram' => '_ag_telegram', 'whatsapp' => '_ag_whatsapp',
                'hours' => '_ag_hours_note', 'brand_color' => '_ag_brand_color', 'brand_color_2' => '_ag_brand_color_2',
                'signature_name' => '_ag_signature_name', 'signature_id' => '_ag_signature_id', 'signature_url' => '_ag_signature_url',
            ],
            'artworks' => [
                'medium' => '_aw_medium', 'year_created' => '_aw_year', 'dimensions' => '_aw_dimensions', 'material' => '_aw_material',
                'condition_note' => '_aw_condition', 'provenance' => '_aw_provenance', 'price' => '_aw_price', 'title_en' => '_aw_title_en',
                'edition' => '_aw_edition', 'accession_number' => '_aw_accession', 'status' => '_aw_moderation_status',
                'sale_note' => '_aw_sale_note', 'currency' => '_aw_currency',
            ],
            'exhibitions' => [
                'start_date' => '_ae_start', 'end_date' => '_ae_end', 'event_type' => '_ae_type', 'artist_label' => '_ae_artist',
                'curator_name' => '_ae_curator', 'admission_info' => '_ae_admission', 'opening_note' => '_ae_opening', 'website' => '_ae_link',
                'status' => '_ae_moderation_status', 'subtitle' => '_ae_subtitle', 'curator_id' => '_ae_curator_id',
            ],
            default => [],
        };
        foreach ($maps as $column => $meta_key) {
            $value = $row->{$column} ?? null;
            if ($value === null || $value === '') continue;
            if (!$is_new && get_post_meta($post_id, $meta_key, true) !== '') continue;
            update_post_meta($post_id, $meta_key, is_scalar($value) ? sanitize_text_field((string) $value) : '');
        }

        $image_map = match ($table_key) {
            'artists' => [['avatar_id', '_thumbnail_id', 'avatar_url', '_aa_avatar_url'], ['cover_id', '_aa_cover', 'cover_url', '_aa_cover_url']],
            'galleries' => [['logo_id', '_thumbnail_id', 'logo_url', '_ag_logo_url'], ['cover_id', '_ag_cover', 'cover_url', '_ag_cover_url']],
            'artworks' => [['image_id', '_thumbnail_id', 'image_url', '_aw_image_url']],
            'exhibitions' => [['poster_id', '_thumbnail_id', 'poster_url', '_ae_poster_url']],
            default => [],
        };
        foreach ($image_map as [$id_col, $id_meta, $url_col, $url_meta]) {
            $id = absint($row->{$id_col} ?? 0);
            $url = esc_url_raw((string) ($row->{$url_col} ?? ''));
            $meta_is_empty = $id_meta === '_thumbnail_id' ? !get_post_thumbnail_id($post_id) : !get_post_meta($post_id, $id_meta, true);
            if ($id > 0 && get_post_type($id) === 'attachment' && wp_attachment_is_image($id) && ($is_new || $meta_is_empty)) {
                if ($id_meta === '_thumbnail_id') set_post_thumbnail($post_id, $id);
                else update_post_meta($post_id, $id_meta, $id);
            }
            if ($url !== '' && ($is_new || get_post_meta($post_id, $url_meta, true) === '')) update_post_meta($post_id, $url_meta, $url);
        }

        if ($table_key === 'artworks') {
            $artist = self::ensure_post_for_row('artists', absint($row->artist_id ?? 0));
            if ($artist && ($is_new || !get_post_meta($post_id, '_aw_artist', true))) update_post_meta($post_id, '_aw_artist', $artist);
            $gallery = self::ensure_post_for_row('galleries', absint($row->gallery_id ?? 0));
            if ($gallery && ($is_new || !get_post_meta($post_id, '_aw_gallery', true))) update_post_meta($post_id, '_aw_gallery', $gallery);
            if ($is_new || get_post_meta($post_id, '_aw_sale', true) === '') update_post_meta($post_id, '_aw_sale', (string) (($row->status ?? '') === 'sold' ? 'sold' : (!empty($row->for_sale) ? 'available' : 'unavailable')));
            if ($is_new || get_post_meta($post_id, '_aw_price_mode', true) === '') update_post_meta($post_id, '_aw_price_mode', (float) ($row->price ?? 0) > 0 ? 'show' : 'hide');
            if ($is_new || get_post_meta($post_id, '_aw_photos', true) === '') update_post_meta($post_id, '_aw_photos', Media::ids($row->gallery_ids ?? ''));
            if ($is_new || get_post_meta($post_id, '_aw_sale_note', true) === '') update_post_meta($post_id, '_aw_sale_note', sanitize_textarea_field((string) ($row->sale_note ?? '')));
            if ($is_new || get_post_meta($post_id, '_aw_currency', true) === '') update_post_meta($post_id, '_aw_currency', sanitize_text_field((string) ($row->currency ?? 'IRT')));
        } elseif ($table_key === 'galleries') {
            // Preserve the brand/seal values used by COA certificate templates.
            if (isset($row->signature_id) && (int) $row->signature_id > 0 && ($is_new || !get_post_meta($post_id, '_ag_signature_id', true))) update_post_meta($post_id, '_ag_signature_id', (int) $row->signature_id);
            if (!empty($row->signature_url) && ($is_new || !get_post_meta($post_id, '_ag_signature_url', true))) update_post_meta($post_id, '_ag_signature_url', esc_url_raw((string) $row->signature_url));
        } elseif ($table_key === 'exhibitions') {
            $gallery = self::ensure_post_for_row('galleries', absint($row->gallery_id ?? 0));
            if ($gallery && ($is_new || !get_post_meta($post_id, '_ae_gallery', true))) update_post_meta($post_id, '_ae_gallery', $gallery);
            if ($is_new || get_post_meta($post_id, '_ae_photos', true) === '') update_post_meta($post_id, '_ae_photos', Media::ids($row->gallery_ids ?? ''));
        }
    }
}
