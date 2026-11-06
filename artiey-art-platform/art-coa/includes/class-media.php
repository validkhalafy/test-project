<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Exhibition;

defined('ABSPATH') || exit;

/**
 * Image layer: schema upgrade, URL resolution, admin media pickers,
 * secure front-end uploads (REST) that create real Media Library attachments.
 *
 * Convention: an image "field" `foo` is stored as `foo_id` (attachment ID, preferred)
 * plus `foo_url` (legacy / external URL fallback).
 */
final class Media
{
    public const DB_VERSION = '1.8.0';

    /** @var string[] */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** Roles (art_coa_role user meta) allowed to upload from the front end */
    private const UPLOAD_ROLES = ['artist', 'gallery', 'curator', 'collector'];

    public static function init(): void
    {
        add_action('init', [self::class, 'maybe_upgrade'], 5);
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /* ------------------------------------------------------------------
     * Schema
     * ---------------------------------------------------------------- */

    public static function maybe_upgrade(): void
    {
        if (get_option('wp_art_coa_db_version') === self::DB_VERSION) {
            return;
        }
        self::migrate();
        update_option('wp_art_coa_db_version', self::DB_VERSION, false);
        // New rewrite rule (/artwork/{slug}) — flush once, after all rules are registered.
        add_action('init', static function (): void {
            flush_rewrite_rules(false);
        }, 99);
    }

    public static function migrate(): void
    {
        // Make sure every soft-migrated column exists first.
        if (class_exists(Shop::class)) {
            Shop::maybe_migrate();
        }
        if (class_exists(CertificateTemplate::class)) {
            CertificateTemplate::maybe_migrate_gallery_columns();
        }
        Exhibition::maybe_add_columns();
        if (class_exists(Calls::class)) {
            Calls::maybe_migrate();
        }
        if (class_exists(ArtistPortfolio::class)) {
            ArtistPortfolio::maybe_migrate();
        }
        if (class_exists(Auction::class)) {
            Auction::maybe_migrate();
        }
        if (class_exists(Follows::class)) {
            Follows::maybe_migrate();
        }

        if (class_exists(ContentSync::class)) {
            ContentSync::ensure_schema();
        }
        if (class_exists(CertificateMirror::class)) {
            CertificateMirror::migrate();
        }
        self::add_columns(Database::table('artworks'), [
            'image_id'    => 'BIGINT UNSIGNED NULL',
            'gallery_ids' => 'TEXT NULL',
            'wc_product_id' => 'BIGINT UNSIGNED NULL',
        ]);
        self::add_columns(Database::table('exhibitions'), [
            'poster_id'   => 'BIGINT UNSIGNED NULL',
            'poster_url'  => 'VARCHAR(500) NULL',
            'gallery_ids' => 'TEXT NULL',
        ]);
        self::add_columns(Database::table('galleries'), [
            'logo_id'   => 'BIGINT UNSIGNED NULL',
            'cover_id'  => 'BIGINT UNSIGNED NULL',
            'cover_url' => 'VARCHAR(500) NULL',
        ]);
        self::add_columns(Database::table('artists'), [
            'avatar_id' => 'BIGINT UNSIGNED NULL',
            'cover_id'  => 'BIGINT UNSIGNED NULL',
            'cover_url' => 'VARCHAR(500) NULL',
        ]);
        foreach (['artists', 'galleries', 'artworks', 'exhibitions'] as $pf_table) {
            self::add_columns(Database::table($pf_table), ProfileFields::columns($pf_table));
        }
        self::add_columns(Database::table('curators'), [
            'avatar_id' => 'BIGINT UNSIGNED NULL',
            'cover_id' => 'BIGINT UNSIGNED NULL',
            'cover_url' => 'VARCHAR(500) NULL',
            'curatorial_statement' => 'LONGTEXT NULL',
            'affiliation' => 'VARCHAR(190) NULL',
            'education' => 'TEXT NULL',
            'credentials' => 'TEXT NULL',
            'languages' => 'VARCHAR(255) NULL',
            'years_experience' => 'SMALLINT UNSIGNED NULL',
            'phone' => 'VARCHAR(30) NULL',
            'website' => 'VARCHAR(300) NULL',
            'instagram_url' => 'VARCHAR(300) NULL',
            'linkedin_url' => 'VARCHAR(300) NULL',
        ]);
        self::add_columns(Database::table('collectors'), ['avatar_id' => 'BIGINT UNSIGNED NULL']);

        self::add_indexes(Database::table('curators'), [
            'status_display' => ['status', 'display_name'],
        ]);
        self::add_indexes(Database::table('artworks'), [
            'artist_status' => ['artist_id', 'status'],
            'gallery_status' => ['gallery_id', 'status'],
            'status_id' => ['status', 'id'],
        ]);
        self::add_indexes(Database::table('certificates'), [
            'artwork_status' => ['artwork_id', 'status'],
        ]);
        self::add_indexes(Database::table('exhibitions'), [
            'curator_status_date' => ['curator_id', 'status', 'start_date'],
        ]);

        if (class_exists(Calls::class)) {
            self::add_columns(Calls::table(), ['cover_id' => 'BIGINT UNSIGNED NULL']);
        }
        if (class_exists(ArtistPortfolio::class)) {
            self::add_columns(ArtistPortfolio::table(), ['image_id' => 'BIGINT UNSIGNED NULL']);
        }
    }

    /** @param array<string,string> $cols */
    private static function add_columns(string $table, array $cols): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if (!$exists) {
            return;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $have = $wpdb->get_col("DESCRIBE `{$table}`", 0) ?: [];
        foreach ($cols as $col => $def) {
            if (!in_array($col, $have, true)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    /** @param array<string,string[]> $indexes */
    private static function add_indexes(string $table, array $indexes): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if (!$table_exists) {
            return;
        }
        foreach ($indexes as $name => $columns) {
            // Index names and columns are internal constants, never request data.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $exists = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $name));
            if ($exists) {
                continue;
            }
            $quoted_columns = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("ALTER TABLE `{$table}` ADD KEY `{$name}` ({$quoted_columns})");
        }
    }

    /* ------------------------------------------------------------------
     * Resolving images from rows
     * ---------------------------------------------------------------- */

    public static function url(?object $row, string $field, string $size = 'large'): string
    {
        if (!$row) {
            return '';
        }
        $id = (int) ($row->{$field . '_id'} ?? 0);
        if ($id > 0) {
            $u = wp_get_attachment_image_url($id, $size);
            if ($u) {
                return (string) $u;
            }
        }
        return (string) ($row->{$field . '_url'} ?? '');
    }

    /** <img> markup (with srcset when it is a Media Library image). */
    public static function img(?object $row, string $field, string $size = 'large', array $attr = []): string
    {
        if (!$row) {
            return '';
        }
        $attr += ['loading' => 'lazy', 'decoding' => 'async', 'alt' => ''];
        $id = (int) ($row->{$field . '_id'} ?? 0);
        if ($id > 0) {
            $html = wp_get_attachment_image($id, $size, false, $attr);
            if ($html) {
                return $html;
            }
        }
        $url = (string) ($row->{$field . '_url'} ?? '');
        if ($url === '') {
            return '';
        }
        $out = '<img src="' . esc_url($url) . '"';
        foreach ($attr as $k => $v) {
            $out .= ' ' . esc_attr((string) $k) . '="' . esc_attr((string) $v) . '"';
        }
        return $out . '>';
    }

    /** @return int[] */
    public static function ids($csv): array
    {
        if (is_array($csv)) {
            $parts = $csv;
        } else {
            $parts = preg_split('/[,\s]+/', (string) $csv) ?: [];
        }
        $out = [];
        foreach ($parts as $p) {
            $i = absint($p);
            if ($i > 0 && !in_array($i, $out, true)) {
                $out[] = $i;
            }
        }
        return $out;
    }

    /**
     * Extra images (gallery_ids) as [['id','thumb','full','alt'], ...].
     * @return array<int,array{id:int,thumb:string,full:string,alt:string}>
     */
    public static function gallery_items(?string $csv, string $thumb_size = 'medium'): array
    {
        $items = [];
        foreach (self::ids($csv) as $id) {
            $full = wp_get_attachment_image_url($id, 'full');
            if (!$full) {
                continue;
            }
            $items[] = [
                'id'    => $id,
                'thumb' => (string) (wp_get_attachment_image_url($id, $thumb_size) ?: $full),
                'full'  => (string) (wp_get_attachment_image_url($id, 'large') ?: $full),
                'alt'   => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
            ];
        }
        return $items;
    }

    /* ------------------------------------------------------------------
     * Reading submitted values (create / update)
     * ---------------------------------------------------------------- */

    /** Whether the current user may attach this Media Library item to a record. */
    private static function can_use(int $attachment_id): bool
    {
        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            return false;
        }
        if (current_user_can('manage_options')) {
            return true;
        }
        return (int) get_post_field('post_author', $attachment_id) === get_current_user_id();
    }

    /**
     * Resolve `{field}_id` / `{field}_url` from submitted data.
     * @return array{0:?int,1:string} [attachment id or null, url]
     */
    public static function resolve(array $data, string $field): array
    {
        $id = absint($data[$field . '_id'] ?? 0);
        if ($id && self::can_use($id)) {
            $url = wp_get_attachment_image_url($id, 'full');
            return [$id, $url ? (string) $url : ''];
        }
        return [null, esc_url_raw((string) ($data[$field . '_url'] ?? ''))];
    }

    /** CSV of valid attachment IDs the user may use (max 24). */
    public static function sanitize_ids($raw): string
    {
        $ok = [];
        foreach (self::ids($raw) as $id) {
            if (self::can_use($id)) {
                $ok[] = $id;
            }
            if (count($ok) >= 24) {
                break;
            }
        }
        return implode(',', $ok);
    }

    /** Whether the submitted data carries any value for this image field (used by partial updates). */
    public static function has(array $data, string $field): bool
    {
        return array_key_exists($field . '_id', $data) || array_key_exists($field . '_url', $data);
    }

    /** Columns for $wpdb->insert/update for one image field. */
    public static function columns(array $data, string $field): array
    {
        [$id, $url] = self::resolve($data, $field);
        return [$field . '_id' => $id, $field . '_url' => $url];
    }

    /* ------------------------------------------------------------------
     * Admin pickers (WordPress media modal — see assets/js/admin-media.js)
     * ---------------------------------------------------------------- */

    /**
     * Single-image picker. Posts `{field}_id` and `{field}_url`.
     * @param array{label?:string,help?:string,shape?:string} $o shape: portrait|landscape|square|wide
     */
    public static function picker(string $field, ?object $row, array $o = []): string
    {
        $label = $o['label'] ?? I18n::t('image');
        $help  = $o['help'] ?? '';
        $shape = $o['shape'] ?? 'landscape';
        $id    = (int) ($row->{$field . '_id'} ?? 0);
        $full  = self::url($row, $field, 'full');
        $prev  = self::url($row, $field, 'medium');

        ob_start();
        ?>
        <div class="art-coa-picker art-coa-picker--<?php echo esc_attr($shape); ?>" data-field="<?php echo esc_attr($field); ?>"
             data-title="<?php echo esc_attr($label); ?>">
            <strong class="art-coa-picker-label"><?php echo esc_html($label); ?></strong>
            <div class="art-coa-picker-box">
                <div class="art-coa-picker-preview<?php echo $prev ? ' has-image' : ''; ?>">
                    <img src="<?php echo esc_url($prev); ?>" alt="" <?php echo $prev ? '' : 'hidden'; ?>>
                    <span class="art-coa-picker-ph" <?php echo $prev ? 'hidden' : ''; ?>>🖼</span>
                </div>
                <div class="art-coa-picker-actions">
                    <input type="hidden" name="<?php echo esc_attr($field); ?>_id" value="<?php echo $id ? (int) $id : ''; ?>" class="js-id">
                    <input type="hidden" name="<?php echo esc_attr($field); ?>_url" value="<?php echo esc_attr($full); ?>" class="js-url">
                    <button type="button" class="button js-pick"><?php echo esc_html(I18n::t('pick_image')); ?></button>
                    <button type="button" class="button-link button-link-delete js-remove" <?php echo $prev ? '' : 'hidden'; ?>><?php echo esc_html(I18n::t('remove_image')); ?></button>
                    <?php if ($help): ?><p class="description"><?php echo esc_html($help); ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** Multi-image picker (sortable). Posts `{field}` as CSV of attachment IDs. */
    public static function multi_picker(string $field, ?string $csv, array $o = []): string
    {
        $label = $o['label'] ?? I18n::t('gallery_images');
        $help  = $o['help'] ?? '';
        $items = self::gallery_items($csv, 'thumbnail');

        ob_start();
        ?>
        <div class="art-coa-multi" data-title="<?php echo esc_attr($label); ?>">
            <strong class="art-coa-picker-label"><?php echo esc_html($label); ?></strong>
            <input type="hidden" name="<?php echo esc_attr($field); ?>" class="js-ids" value="<?php echo esc_attr(implode(',', array_column($items, 'id'))); ?>">
            <ul class="art-coa-multi-list">
                <?php foreach ($items as $it): ?>
                    <li data-id="<?php echo (int) $it['id']; ?>">
                        <img src="<?php echo esc_url($it['thumb']); ?>" alt="">
                        <button type="button" class="js-del" aria-label="<?php echo esc_attr(I18n::t('remove_image')); ?>">×</button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="button js-add"><?php echo esc_html(I18n::t('add_images')); ?></button>
            <?php if ($help): ?><p class="description"><?php echo esc_html($help); ?></p><?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /* ------------------------------------------------------------------
     * Front-end dropzone (see assets/js/frontend-upload.js)
     * ---------------------------------------------------------------- */

    /**
     * Drag & drop uploader. Uploads through REST immediately and stores the
     * resulting attachment ID(s) in hidden inputs of the surrounding form.
     */
    public static function dropzone(string $field, array $o = []): string
    {
        $multiple = !empty($o['multiple']);
        $label    = $o['label'] ?? I18n::t('image');
        $required = !empty($o['required']);
        $current  = $o['current'] ?? [];   // [['id'=>..,'url'=>..], ...]

        ob_start();
        ?>
        <div class="art-coa-dz" data-multiple="<?php echo $multiple ? '1' : '0'; ?>" data-max="<?php echo (int) ($o['max'] ?? ($multiple ? 10 : 1)); ?>"
             data-required="<?php echo $required ? '1' : '0'; ?>">
            <span class="art-coa-dz-label"><?php echo esc_html($label); ?><?php echo $required ? ' *' : ''; ?></span>
            <input type="hidden" name="<?php echo esc_attr($field . ($multiple ? '' : '_id')); ?>" class="js-value"
                   value="<?php echo esc_attr(implode(',', array_column($current, 'id'))); ?>">
            <div class="art-coa-dz-area" tabindex="0" role="button" aria-label="<?php echo esc_attr($label); ?>">
                <span class="art-coa-dz-icon" aria-hidden="true">⬆</span>
                <span class="art-coa-dz-text"><?php echo esc_html(I18n::t('dz_hint')); ?></span>
                <small class="art-coa-dz-sub"><?php echo esc_html(I18n::t('dz_sub', ['mb' => self::max_mb()])); ?></small>
                <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" <?php echo $multiple ? 'multiple' : ''; ?> hidden>
            </div>
            <ul class="art-coa-dz-previews">
                <?php foreach ($current as $c): ?>
                    <li class="is-done" data-id="<?php echo (int) $c['id']; ?>">
                        <img src="<?php echo esc_url($c['url']); ?>" alt="">
                        <button type="button" class="js-del" aria-label="<?php echo esc_attr(I18n::t('remove_image')); ?>">×</button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="art-coa-dz-error" role="alert" hidden></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /* ------------------------------------------------------------------
     * Upload (REST: POST /art-coa/v1/upload, multipart field "file")
     * ---------------------------------------------------------------- */

    public static function max_mb(): int
    {
        $s = get_option('wp_art_coa_settings', []);
        return max(1, min(64, (int) ($s['max_upload_mb'] ?? 8)));
    }

    public static function register_routes(): void
    {
        register_rest_route('art-coa/v1', '/upload', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'rest_upload'],
            'permission_callback' => [self::class, 'can_upload'],
        ]);
    }

    public static function can_upload(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }
        if (current_user_can('manage_options')) {
            return true;
        }
        $role = (string) get_user_meta(get_current_user_id(), 'art_coa_role', true);
        return in_array($role, self::UPLOAD_ROLES, true);
    }

    public static function rest_upload(\WP_REST_Request $request)
    {
        // Count attempts before file processing so concurrent or invalid uploads are also limited.
        if (!Security::throttle('upload:' . get_current_user_id(), 40, HOUR_IN_SECONDS)) {
            return new \WP_Error('art_coa_rate_limited', I18n::t('sec_rate_limit'), ['status' => 429]);
        }

        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new \WP_Error('art_coa_no_file', I18n::t('invalid_image'), ['status' => 400]);
        }

        $res = self::handle_file($files['file']);
        if (is_wp_error($res)) {
            return $res;
        }
        return rest_ensure_response($res);
    }

    /**
     * Validate and store one uploaded image as a Media Library attachment.
     * @param array{name?:string,tmp_name?:string,size?:int,error?:int} $file
     * @return array{id:int,url:string,thumb:string}|\WP_Error
     */
    public static function handle_file(array $file)
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return new \WP_Error('art_coa_upload_failed', I18n::t('invalid_image'), ['status' => 400]);
        }
        if ((int) ($file['size'] ?? 0) > self::max_mb() * MB_IN_BYTES) {
            return new \WP_Error('art_coa_too_large', I18n::t('file_too_large', ['mb' => self::max_mb()]), ['status' => 413]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $name  = sanitize_file_name((string) ($file['name'] ?? 'image'));
        $check = wp_check_filetype_and_ext((string) $file['tmp_name'], $name);
        if (empty($check['type']) || !in_array($check['type'], self::ALLOWED_MIMES, true)) {
            return new \WP_Error('art_coa_bad_type', I18n::t('invalid_image'), ['status' => 415]);
        }
        // Must really decode as an image (blocks polyglot / renamed files).
        if (@getimagesize((string) $file['tmp_name']) === false) {
            return new \WP_Error('art_coa_bad_image', I18n::t('invalid_image'), ['status' => 415]);
        }
        if (!empty($check['proper_filename'])) {
            $name = (string) $check['proper_filename'];
        }

        $id = media_handle_sideload(['name' => $name, 'tmp_name' => (string) $file['tmp_name']], 0);
        if (is_wp_error($id)) {
            return new \WP_Error('art_coa_sideload', $id->get_error_message(), ['status' => 500]);
        }
        update_post_meta((int) $id, '_art_coa_upload', 1);

        $full = (string) wp_get_attachment_image_url((int) $id, 'large');
        return [
            'id'    => (int) $id,
            'url'   => $full,
            'thumb' => (string) (wp_get_attachment_image_url((int) $id, 'medium') ?: $full),
        ];
    }
}
