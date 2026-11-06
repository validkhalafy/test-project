<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Open calls for exhibitions and events (فراخوان).
 *
 * Public routes: /art-coa/calls/ (filterable, searchable, paginated) and /art-coa/call/{slug}/.
 * Numeric /art-coa/call/{id}/ URLs permanently redirect to the slug URL (one canonical URL per call).
 * Applications are protected by a nonce, a honeypot, a minimum fill time, a per-IP rate limit and a
 * one-application-per-email-per-call rule.
 */
final class Calls
{
    private const DB_VERSION = '3';
    public const TYPES = ['exhibition', 'event', 'residency', 'open_studio'];
    public const APPLICATION_STATUSES = ['pending', 'shortlisted', 'accepted', 'rejected'];
    private const PER_PAGE = 12;
    private static ?string $page_title = null;
    private static ?object $current_call = null;

    public static function init(): void
    {
        add_action('admin_init', [self::class, 'maybe_migrate']);
        add_action('admin_init', [self::class, 'maybe_export']);
        add_shortcode('art_coa_calls', [self::class, 'shortcode']);
        add_action('init', [self::class, 'rewrites']);
        add_action('init', [self::class, 'schedule_expiry'], 20);
        add_action('art_coa_close_expired_calls', [self::class, 'auto_close_expired']);
        add_filter('pre_get_document_title', [self::class, 'document_title']);
        add_action('wp_head', [self::class, 'print_meta'], 2);
        add_action('wp_head', [self::class, 'print_event_schema'], 99);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets'], 6);
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'art_coa_calls';
            $vars[] = 'art_coa_call_id';
            $vars[] = 'art_coa_slug';
            return $vars;
        });
        add_action('template_redirect', [self::class, 'maybe_render']);
        add_action('init', [self::class, 'handle_apply']);
    }

    public static function table(): string
    {
        return Database::table('open_calls');
    }

    public static function applications_table(): string
    {
        return Database::table('call_applications');
    }

    private static function tr(string $fa, string $en): string
    {
        return I18n::is_fa() ? $fa : $en;
    }

    /* ------------------------------------------------------------------ schema */

    public static function maybe_migrate(): void
    {
        $stored_version = get_option('wp_art_coa_calls_db_version', false);
        if ($stored_version === self::DB_VERSION) {
            return;
        }

        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta('CREATE TABLE ' . self::table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            call_type VARCHAR(40) NOT NULL DEFAULT 'exhibition',
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            gallery_id BIGINT UNSIGNED NULL,
            exhibition_id BIGINT UNSIGNED NULL,
            description LONGTEXT NULL,
            requirements TEXT NULL,
            deadline_utc DATETIME NULL,
            event_start_utc DATETIME NULL,
            event_end_utc DATETIME NULL,
            location VARCHAR(255) NULL,
            city VARCHAR(120) NULL,
            country VARCHAR(120) NULL,
            contact_email VARCHAR(190) NULL,
            max_works INT UNSIGNED NULL,
            fee DECIMAL(12,2) NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'IRR',
            cover_url VARCHAR(500) NULL,
            cover_id BIGINT UNSIGNED NULL,
            apply_url VARCHAR(500) NULL,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY call_type (call_type),
            KEY deadline_utc (deadline_utc),
            KEY gallery_status (gallery_id,status)
        ) {$charset};");

        dbDelta('CREATE TABLE ' . self::applications_table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            call_id BIGINT UNSIGNED NOT NULL,
            artist_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            applicant_name VARCHAR(190) NOT NULL,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            applicant_email VARCHAR(190) NOT NULL,
            applicant_phone VARCHAR(50) NULL,
            city VARCHAR(120) NULL,
            country VARCHAR(120) NULL,
            website VARCHAR(500) NULL,
            portfolio_url VARCHAR(500) NULL,
            instagram VARCHAR(190) NULL,
            medium VARCHAR(255) NULL,
            birth_year SMALLINT NULL,
            bio TEXT NULL,
            works_info TEXT NULL,
            message TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            admin_note TEXT NULL,
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NULL,
            PRIMARY KEY  (id),
            KEY call_id (call_id),
            KEY status (status),
            KEY call_email (call_id,applicant_email)
        ) {$charset};");

        // Older installs: columns dbDelta may not add on every MySQL flavour.
        $app_table = self::applications_table();
        $cols = $wpdb->get_col("DESCRIBE {$app_table}", 0) ?: [];
        $extra = [
            'first_name' => 'VARCHAR(100) NULL',
            'last_name'  => 'VARCHAR(100) NULL',
            'city'       => 'VARCHAR(120) NULL',
            'country'    => 'VARCHAR(120) NULL',
            'website'    => 'VARCHAR(500) NULL',
            'instagram'  => 'VARCHAR(190) NULL',
            'medium'     => 'VARCHAR(255) NULL',
            'birth_year' => 'SMALLINT NULL',
            'bio'        => 'TEXT NULL',
            'works_info' => 'TEXT NULL',
            'user_id'    => 'BIGINT UNSIGNED NULL',
            'admin_note' => 'TEXT NULL',
            'updated_at_utc' => 'DATETIME NULL',
        ];
        foreach ($extra as $col => $def) {
            if ($cols && !in_array($col, $cols, true)) {
                $wpdb->query("ALTER TABLE {$app_table} ADD COLUMN {$col} {$def}");
            }
        }
        $call_cols = $wpdb->get_col('DESCRIBE ' . self::table(), 0) ?: [];
        foreach (['cover_id' => 'BIGINT UNSIGNED NULL', 'apply_url' => 'VARCHAR(500) NULL'] as $col => $def) {
            if ($call_cols && !in_array($col, $call_cols, true)) {
                $wpdb->query('ALTER TABLE ' . self::table() . " ADD COLUMN {$col} {$def}");
            }
        }

        if ($stored_version === false) {
            self::normalize_legacy_dates();
        }

        $calls_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(self::table())));
        $apps_exist = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(self::applications_table())));
        if ($calls_exists === self::table() && $apps_exist === self::applications_table()) {
            update_option('wp_art_coa_calls_db_version', self::DB_VERSION, false);
        }
    }

    /** Convert pre-upgrade datetime-local values from site wall time into UTC once. */
    private static function normalize_legacy_dates(): void
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT id, deadline_utc, event_start_utc, event_end_utc FROM ' . self::table()
        ) ?: [];
        foreach ($rows as $row) {
            $updates = [];
            foreach (['deadline_utc', 'event_start_utc', 'event_end_utc'] as $column) {
                if (empty($row->{$column})) {
                    continue;
                }
                $local = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $row->{$column}, wp_timezone());
                $errors = \DateTimeImmutable::getLastErrors();
                if ($local && ($errors === false || (!$errors['warning_count'] && !$errors['error_count']))) {
                    $updates[$column] = $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                }
            }
            if ($updates) {
                $wpdb->update(self::table(), $updates, ['id' => (int) $row->id]);
            }
        }
    }

    public static function schedule_expiry(): void
    {
        if (!wp_next_scheduled('art_coa_close_expired_calls')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'art_coa_close_expired_calls');
        }
    }

    public static function rewrites(): void
    {
        add_rewrite_rule('^art-coa/calls/?$', 'index.php?art_coa_calls=list', 'top');
        add_rewrite_rule('^art-coa/call/([0-9]+)/?$', 'index.php?art_coa_calls=single&art_coa_call_id=$matches[1]', 'top');
        add_rewrite_rule('^art-coa/call/([^/]+)/?$', 'index.php?art_coa_calls=slug&art_coa_slug=$matches[1]', 'top');
    }

    /* ------------------------------------------------------------------ assets */

    public static function register_assets(): void
    {
        $version = defined('AET_VERSION') ? (string) AET_VERSION : WP_ART_COA_VERSION;
        $base = defined('AET_URL') ? AET_URL : plugins_url('/', WP_ART_COA_FILE);
        wp_register_style('artiey-calls', $base . 'assets/css/calls.css', [], $version);
        wp_register_script('artiey-calls', $base . 'assets/js/calls.js', [], $version, ['in_footer' => true, 'strategy' => 'defer']);
        wp_localize_script('artiey-calls', 'ArtieyCalls', [
            'fa'   => I18n::is_fa(),
            'i18n' => [
                'day' => self::tr('روز', 'd'), 'hour' => self::tr('ساعت', 'h'), 'minute' => self::tr('دقیقه', 'm'), 'second' => self::tr('ثانیه', 's'),
                'ended' => I18n::t('deadline_passed'), 'left' => self::tr('مانده', 'left'), 'copied' => self::tr('پیوند کپی شد', 'Link copied'),
            ],
        ]);
    }

    public static function enqueue(): void
    {
        if (!wp_style_is('artiey-calls', 'registered')) {
            self::register_assets();
        }
        wp_enqueue_style('artiey-calls');
        wp_enqueue_script('artiey-calls');
    }

    /* ------------------------------------------------------------------ routing */

    public static function maybe_render(): void
    {
        $mode = get_query_var('art_coa_calls');
        if (!$mode) {
            return;
        }

        $not_found = false;
        $call_id = 0;
        $call_slug = '';
        global $wpdb;
        if ($mode === 'single') {
            $call_id = absint(get_query_var('art_coa_call_id'));
            $slug = $call_id ? (string) $wpdb->get_var($wpdb->prepare('SELECT slug FROM ' . self::table() . ' WHERE id = %d', $call_id)) : '';
            // A call whose slug is numeric is served here; every other id URL moves to its slug URL.
            if ($slug !== '' && $slug !== (string) $call_id) {
                wp_safe_redirect(self::url((object) ['id' => $call_id, 'slug' => $slug]), 301, 'Artiey Art Platform');
                exit;
            }
            $content = $call_id ? self::render_single($call_id) : '';
            $not_found = $content === '';
        } elseif ($mode === 'slug') {
            $call_slug = sanitize_title(rawurldecode((string) get_query_var('art_coa_slug')));
            $call_id = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . self::table() . ' WHERE slug = %s',
                $call_slug
            ));
            $content = $call_id ? self::render_single($call_id) : '';
            $not_found = $content === '';
        } elseif ($mode === 'list') {
            self::$page_title = I18n::t('calls_title');
            $content = self::render_list([]);
        } else {
            $content = '';
            $not_found = true;
        }

        if ($not_found) {
            global $wp_query;
            if ($wp_query instanceof \WP_Query) {
                $wp_query->set_404();
            }
            status_header(404);
            add_filter('wp_robots', 'wp_robots_no_robots');
        } else {
            status_header(200);
        }
        nocache_headers();
        $route_key = $not_found ? '' : ($mode === 'single' || $mode === 'slug' ? 'calls_single' : 'calls_archive');
        if ($route_key !== '' && class_exists('\\Artiey_AET_Elementor_Layouts')) {
            $content = \Artiey_AET_Elementor_Layouts::render_route($route_key, [
                'module' => 'calls', 'entity_type' => 'call', 'mode' => $route_key === 'calls_single' ? 'single' : 'archive',
                'id' => $call_id, 'slug' => $call_slug,
            ], $content);
        }
        get_header();
        echo '<div class="art-coa-profile-wrap" dir="' . (I18n::is_fa() ? 'rtl' : 'ltr') . '">';
        echo $content !== '' ? $content : '<p class="art-coa-empty">' . esc_html(I18n::t('no_items')) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered with escaping above
        echo '</div>';
        get_footer();
        exit;
    }

    /* ------------------------------------------------------------------ helpers */

    public static function url(object $row): string
    {
        $slug = !empty($row->slug) ? (string) $row->slug : (string) (int) ($row->id ?? 0);
        return home_url('/art-coa/call/' . rawurlencode($slug) . '/');
    }

    /** Whole days left until a UTC deadline (0 = last day), null without deadline, -1 when passed. */
    public static function days_left(string $deadline_utc, ?int $now = null): ?int
    {
        if ($deadline_utc === '') {
            return null;
        }
        $deadline = strtotime($deadline_utc . ' UTC');
        if ($deadline === false) {
            return null;
        }
        $diff = $deadline - ($now ?? time());
        return $diff < 0 ? -1 : (int) floor($diff / DAY_IN_SECONDS);
    }

    public static function cover_html(object $row, string $size = 'medium_large', array $attr = []): string
    {
        $attr += ['alt' => (string) ($row->title ?? ''), 'loading' => 'lazy', 'decoding' => 'async'];
        $id = (int) ($row->cover_id ?? 0);
        if ($id > 0) {
            $img = wp_get_attachment_image($id, $size, false, $attr);
            if ($img) {
                return $img;
            }
        }
        $url = (string) ($row->cover_url ?? '');
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }
        $out = '<img src="' . esc_url($url) . '"';
        foreach ($attr as $k => $v) {
            $out .= ' ' . esc_attr((string) $k) . '="' . esc_attr((string) $v) . '"';
        }
        return $out . '>';
    }

    public static function cover_url(object $row, string $size = 'large'): string
    {
        $id = (int) ($row->cover_id ?? 0);
        if ($id > 0) {
            $u = wp_get_attachment_image_url($id, $size);
            if ($u) {
                return (string) $u;
            }
        }
        return (string) ($row->cover_url ?? '');
    }

    private static function fa_digits(string $s): string
    {
        return I18n::is_fa() ? Calendar::to_persian_digits($s) : $s;
    }

    public static function days_badge(object $row): string
    {
        if (!self::is_open($row)) {
            return '<span class="aet-call-badge is-closed">' . esc_html(I18n::t('call_closed')) . '</span>';
        }
        $days = self::days_left((string) ($row->deadline_utc ?? ''));
        if ($days === null) {
            return '<span class="aet-call-badge is-open">' . esc_html(I18n::t('call_open')) . '</span>';
        }
        if ($days === 0) {
            return '<span class="aet-call-badge is-soon">' . esc_html(self::tr('آخرین روز', 'Last day')) . '</span>';
        }
        $class = $days <= 3 ? 'is-soon' : 'is-open';
        return '<span class="aet-call-badge ' . $class . '">' . esc_html(self::fa_digits((string) $days) . ' ' . self::tr('روز مانده', 'days left')) . '</span>';
    }

    /* ------------------------------------------------------------------ queries */

    /**
     * @param array{type?:string,status?:string,limit?:int,page?:int,q?:string,gallery?:int,featured?:bool,exclude?:int} $args
     * @return array{items:object[],total:int,pages:int,page:int}
     */
    public static function query(array $args): array
    {
        self::maybe_migrate();
        $type = sanitize_key((string) ($args['type'] ?? ''));
        $type = in_array($type, self::TYPES, true) ? $type : '';
        $status = sanitize_key((string) ($args['status'] ?? 'open'));
        $status = in_array($status, ['open', 'closed', 'all'], true) ? $status : 'open';
        $limit = max(1, min(50, (int) ($args['limit'] ?? self::PER_PAGE)));
        $page = max(1, (int) ($args['page'] ?? 1));
        $q = \Artiey_AET_Text::substr(sanitize_text_field((string) ($args['q'] ?? '')), 0, 80);
        $now = gmdate('Y-m-d H:i:s');

        global $wpdb;
        $where = ' WHERE 1=1';
        $params = [];
        if ($status === 'open') {
            $where .= " AND c.status = 'open' AND (c.deadline_utc IS NULL OR c.deadline_utc >= %s)";
            $params[] = $now;
        } elseif ($status === 'closed') {
            $where .= " AND (c.status = 'closed' OR (c.deadline_utc IS NOT NULL AND c.deadline_utc < %s))";
            $params[] = $now;
        }
        if ($type !== '') {
            $where .= ' AND c.call_type = %s';
            $params[] = $type;
        }
        if (!empty($args['gallery'])) {
            $where .= ' AND c.gallery_id = %d';
            $params[] = (int) $args['gallery'];
        }
        if (!empty($args['featured'])) {
            $where .= ' AND c.is_featured = 1';
        }
        if (!empty($args['exclude'])) {
            $where .= ' AND c.id <> %d';
            $params[] = (int) $args['exclude'];
        }
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where .= ' AND (c.title LIKE %s OR c.city LIKE %s OR c.location LIKE %s OR g.name LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }
        $from = ' FROM ' . self::table() . ' c LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = c.gallery_id';
        $count_sql = 'SELECT COUNT(*)' . $from . $where;
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, ...$params) : $count_sql);
        $pages = max(1, (int) ceil($total / $limit));
        $page = min($page, $pages);
        $order = $status === 'closed'
            ? ' ORDER BY c.deadline_utc DESC, c.id DESC'
            : ' ORDER BY c.is_featured DESC, (c.deadline_utc IS NULL) ASC, c.deadline_utc ASC, c.id DESC';
        $sql = 'SELECT c.*, g.name AS gallery_name, g.slug AS gallery_slug' . $from . $where . $order . ' LIMIT %d OFFSET %d';
        $items = $wpdb->get_results($wpdb->prepare($sql, ...array_merge($params, [$limit, ($page - 1) * $limit]))) ?: [];
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /* ------------------------------------------------------------------ list */

    public static function shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'type'    => '',
            'status'  => 'open',
            'limit'   => 20,
            'gallery' => 0,
            'filters' => '1',
            'search'  => '1',
            'heading' => '1',
        ], $atts, 'art_coa_calls');
        return self::render_list($atts);
    }

    public static function render_list(array $args): string
    {
        self::enqueue();
        $on = static fn($v): bool => !in_array(strtolower((string) $v), ['0', 'no', 'false', 'off'], true);
        $type = is_scalar($args['type'] ?? '') ? sanitize_key((string) ($args['type'] ?? '')) : '';
        if ($type === '' && isset($_GET['type']) && is_scalar($_GET['type'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public filter
            $type = sanitize_key(wp_unslash((string) $_GET['type'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        $type = in_array($type, self::TYPES, true) ? $type : '';
        $status = is_scalar($args['status'] ?? 'open') ? sanitize_key((string) ($args['status'] ?? 'open')) : 'open';
        if (isset($_GET['call_status']) && is_scalar($_GET['call_status'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $status = sanitize_key(wp_unslash((string) $_GET['call_status'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        $status = in_array($status, ['open', 'closed', 'all'], true) ? $status : 'open';
        $q = isset($_GET['call_q']) && is_scalar($_GET['call_q']) ? sanitize_text_field(wp_unslash((string) $_GET['call_q'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['cp']) ? max(1, absint($_GET['cp'])) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $result = self::query([
            'type' => $type, 'status' => $status, 'limit' => absint($args['limit'] ?? self::PER_PAGE) ?: self::PER_PAGE,
            'page' => $page, 'q' => $q, 'gallery' => absint($args['gallery'] ?? 0),
        ]);
        $is_route = get_query_var('art_coa_calls') === 'list';
        $heading_tag = $is_route ? 'h1' : 'h2';
        $base = $is_route ? home_url('/art-coa/calls/') : (string) (get_permalink() ?: home_url('/art-coa/calls/'));
        $flash = self::get_flash();
        $link = static function (array $over) use ($base, $type, $status, $q): string {
            $params = array_filter(array_merge(['type' => $type, 'call_status' => $status === 'open' ? '' : $status, 'call_q' => $q], $over), static fn($v) => $v !== '' && $v !== null);
            return add_query_arg(array_map('rawurlencode', $params), $base);
        };

        ob_start();
        ?>
        <div class="art-coa-calls-page" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <?php if ($on($args['heading'] ?? '1')): ?>
                <<?php echo esc_html($heading_tag); ?> class="art-coa-calls-title"><?php echo esc_html(I18n::t('calls_title')); ?></<?php echo esc_html($heading_tag); ?>>
                <p class="art-coa-shop-intro"><?php echo esc_html(I18n::t('calls_intro')); ?></p>
            <?php endif; ?>
            <?php if ($flash): ?>
                <div class="art-coa-result <?php echo $flash['type'] === 'ok' ? 'valid' : 'invalid'; ?>" role="status"><p><?php echo esc_html($flash['msg']); ?></p></div>
            <?php endif; ?>

            <?php if ($on($args['filters'] ?? '1')): ?>
            <div class="aet-calls-toolbar">
                <nav class="aet-calls-tabs" aria-label="<?php echo esc_attr(self::tr('وضعیت فراخوان', 'Call status')); ?>">
                    <?php foreach (['open' => I18n::t('call_open'), 'closed' => self::tr('بایگانی (بسته)', 'Archive'), 'all' => I18n::t('all')] as $key => $label): ?>
                        <a class="<?php echo $status === $key ? 'is-active' : ''; ?>"<?php echo $status === $key ? ' aria-current="page"' : ''; ?> href="<?php echo esc_url($link(['call_status' => $key === 'open' ? '' : $key, 'cp' => ''])); ?>"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </nav>
                <div class="aet-calls-types">
                    <a class="aet-chip<?php echo $type === '' ? ' is-active' : ''; ?>" href="<?php echo esc_url($link(['type' => '', 'cp' => ''])); ?>"><?php echo esc_html(I18n::t('all')); ?></a>
                    <?php foreach (self::TYPES as $t): ?>
                        <a class="aet-chip<?php echo $type === $t ? ' is-active' : ''; ?>" href="<?php echo esc_url($link(['type' => $t, 'cp' => ''])); ?>"><?php echo esc_html(self::type_label($t)); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php if ($on($args['search'] ?? '1')): ?>
                <form class="aet-calls-search" method="get" action="<?php echo esc_url($base); ?>" role="search">
                    <?php if ($type !== ''): ?><input type="hidden" name="type" value="<?php echo esc_attr($type); ?>"><?php endif; ?>
                    <?php if ($status !== 'open'): ?><input type="hidden" name="call_status" value="<?php echo esc_attr($status); ?>"><?php endif; ?>
                    <label class="screen-reader-text" for="aet-call-q"><?php echo esc_html(self::tr('جستجو در فراخوان‌ها', 'Search calls')); ?></label>
                    <input id="aet-call-q" type="search" name="call_q" value="<?php echo esc_attr($q); ?>" maxlength="80" placeholder="<?php echo esc_attr(self::tr('عنوان، شهر یا گالری…', 'Title, city or gallery…')); ?>">
                    <button type="submit" class="button"><?php echo esc_html(self::tr('جستجو', 'Search')); ?></button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <p class="aet-calls-count"><?php echo esc_html(self::fa_digits((string) $result['total']) . ' ' . self::tr('فراخوان', 'calls')); ?></p>
            <?php if (!$result['items']): ?>
                <p class="art-coa-empty"><?php echo esc_html($q !== '' ? self::tr('فراخوانی با این جستجو پیدا نشد.', 'No calls matched your search.') : I18n::t('calls_empty')); ?></p>
            <?php else: ?>
                <div class="art-coa-call-grid">
                    <?php echo self::cards_html($result['items'], ['countdown' => true, 'excerpt' => 18]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
            <?php if ($result['pages'] > 1): ?>
                <nav class="aet-calls-pager" aria-label="<?php echo esc_attr(self::tr('صفحه‌بندی فراخوان‌ها', 'Calls pages')); ?>">
                    <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
                        <a<?php echo $p === $result['page'] ? ' class="is-current" aria-current="page"' : ''; ?> href="<?php echo esc_url($link(['cp' => $p > 1 ? (string) $p : ''])); ?>"><?php echo esc_html(self::fa_digits((string) $p)); ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param object[] $rows
     * @param array{countdown?:bool,excerpt?:int,button?:bool,gallery?:bool,image?:bool,heading_tag?:string} $o
     */
    public static function cards_html(array $rows, array $o = []): string
    {
        $html = '';
        foreach ($rows as $row) {
            $html .= self::card_html($row, $o);
        }
        return $html;
    }

    private static function card_html(object $row, array $o = []): string
    {
        $url = self::url($row);
        $open = self::is_open($row);
        $tag = in_array($o['heading_tag'] ?? 'h3', ['h2', 'h3', 'h4', 'div'], true) ? (string) ($o['heading_tag'] ?? 'h3') : 'h3';
        $excerpt_words = max(0, min(80, (int) ($o['excerpt'] ?? 0)));
        $cover = !array_key_exists('image', $o) || !empty($o['image']) ? self::cover_html($row, 'medium_large', ['alt' => '']) : '';
        ob_start();
        ?>
        <article class="art-coa-call-card<?php echo !empty($row->is_featured) ? ' featured' : ''; ?><?php echo $open ? '' : ' is-closed'; ?>">
            <?php if ($cover): ?>
                <a href="<?php echo esc_url($url); ?>" class="art-coa-call-card-img" tabindex="-1" aria-hidden="true"><?php echo $cover; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
            <?php endif; ?>
            <div class="art-coa-call-card-body">
                <div class="aet-call-card-badges">
                    <span class="art-coa-shop-badge"><?php echo esc_html(self::type_label((string) $row->call_type)); ?></span>
                    <?php echo self::days_badge($row); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <<?php echo esc_html($tag); ?> class="art-coa-call-card-title"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html((string) $row->title); ?></a></<?php echo esc_html($tag); ?>>
                <?php if (!empty($row->gallery_name) && (!array_key_exists('gallery', $o) || !empty($o['gallery']))): ?>
                    <p class="meta"><?php echo esc_html((string) $row->gallery_name); ?></p>
                <?php endif; ?>
                <?php if ($excerpt_words > 0 && !empty($row->description)): ?>
                    <p class="aet-call-excerpt"><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $row->description), $excerpt_words)); ?></p>
                <?php endif; ?>
                <?php if (!empty($row->deadline_utc)): ?>
                    <p class="meta"><?php echo esc_html(I18n::t('deadline')); ?>: <time datetime="<?php echo esc_attr(gmdate('c', (int) strtotime($row->deadline_utc . ' UTC'))); ?>"><?php echo esc_html(Dates::format($row->deadline_utc, true)); ?></time></p>
                    <?php if ($open && !empty($o['countdown'])): ?>
                        <p class="aet-call-countdown" data-aet-deadline="<?php echo esc_attr(gmdate('c', (int) strtotime($row->deadline_utc . ' UTC'))); ?>" aria-live="off"></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($row->city) || !empty($row->location)): ?>
                    <p class="meta"><?php echo esc_html((string) ($row->city ?: $row->location)); ?></p>
                <?php endif; ?>
                <?php if (!array_key_exists('button', $o) || !empty($o['button'])): ?>
                    <p class="aet-call-card-cta"><a class="button button-primary" href="<?php echo esc_url($url); ?>"><?php echo esc_html($open ? I18n::t('view_and_apply') : I18n::t('view_page')); ?><span class="screen-reader-text"> — <?php echo esc_html((string) $row->title); ?></span></a></p>
                <?php endif; ?>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /* ------------------------------------------------------------------ single */

    public static function get(int $id): ?object
    {
        self::maybe_migrate();
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT c.*, g.name AS gallery_name, g.slug AS gallery_slug, g.id AS gallery_row_id, e.title AS exhibition_title, e.slug AS exhibition_slug
             FROM ' . self::table() . ' c
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = c.gallery_id
             LEFT JOIN ' . Database::table('exhibitions') . ' e ON e.id = c.exhibition_id
             WHERE c.id = %d',
            $id
        ));
        return $row ?: null;
    }

    public static function render_single(int $id): string
    {
        $row = self::get($id);
        if (!$row) {
            return '';
        }
        self::enqueue();
        self::$page_title = sanitize_text_field((string) $row->title);
        self::$current_call = $row;
        $open = self::is_open($row);
        $flash = self::get_flash();
        $url = self::url($row);
        $deadline_iso = !empty($row->deadline_utc) ? gmdate('c', (int) strtotime($row->deadline_utc . ' UTC')) : '';
        $gallery_url = '';
        if (!empty($row->gallery_slug)) {
            $gallery_url = ContentSync::permalink('galleries', (string) $row->gallery_slug) ?: home_url('/art-coa/gallery/' . rawurlencode((string) $row->gallery_slug) . '/');
        }
        $user = wp_get_current_user();
        $prefill = [
            'first_name' => $user && $user->exists() ? (string) $user->first_name : '',
            'last_name'  => $user && $user->exists() ? (string) $user->last_name : '',
            'email'      => $user && $user->exists() ? (string) $user->user_email : '',
        ];

        ob_start();
        ?>
        <article class="art-coa-call-single aet-call-single" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <nav class="art-coa-crumbs" aria-label="breadcrumb"><a href="<?php echo esc_url(home_url('/art-coa/calls/')); ?>"><?php echo esc_html(I18n::t('calls_title')); ?></a><span aria-hidden="true">›</span><span><?php echo esc_html((string) $row->title); ?></span></nav>
            <?php if ($flash): ?>
                <div class="art-coa-result <?php echo $flash['type'] === 'ok' ? 'valid' : 'invalid'; ?>" role="status"><p><?php echo esc_html($flash['msg']); ?></p></div>
            <?php endif; ?>

            <header class="art-coa-call-header">
                <div class="aet-call-card-badges">
                    <span class="art-coa-shop-badge"><?php echo esc_html(self::type_label((string) $row->call_type)); ?></span>
                    <?php if ($row->is_featured): ?><span class="art-coa-shop-badge aap-s-1fb46f"><?php echo esc_html(I18n::t('featured')); ?></span><?php endif; ?>
                    <?php echo self::days_badge($row); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <h1><?php echo esc_html((string) $row->title); ?></h1>
                <?php if ($row->gallery_name): ?>
                    <div class="aet-call-organizer">
                        <span><?php echo esc_html(self::tr('برگزارکننده:', 'Organizer:')); ?></span>
                        <?php if ($gallery_url): ?><a href="<?php echo esc_url($gallery_url); ?>"><?php echo esc_html((string) $row->gallery_name); ?></a><?php else: ?><strong><?php echo esc_html((string) $row->gallery_name); ?></strong><?php endif; ?>
                        <?php if (!empty($row->gallery_row_id)) { echo Follows::button_html('gallery', (int) $row->gallery_row_id, ['size' => 'sm', 'style' => 'outline']); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
            </header>

            <?php $cover = self::cover_html($row, 'large', ['loading' => 'eager', 'fetchpriority' => 'high']); if ($cover): ?>
                <figure class="art-coa-call-cover"><?php echo $cover; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></figure>
            <?php endif; ?>

            <dl class="art-coa-call-meta-grid">
                <?php if ($row->deadline_utc): ?>
                    <div><dt class="lbl"><?php echo esc_html(I18n::t('deadline')); ?></dt><dd class="val"><time datetime="<?php echo esc_attr($deadline_iso); ?>"><?php echo esc_html(Dates::format($row->deadline_utc, true)); ?></time></dd></div>
                <?php endif; ?>
                <?php if ($row->event_start_utc): ?>
                    <div><dt class="lbl"><?php echo esc_html(I18n::t('event_dates')); ?></dt><dd class="val"><?php echo esc_html(Dates::format($row->event_start_utc)); ?><?php if ($row->event_end_utc): ?> — <?php echo esc_html(Dates::format($row->event_end_utc)); ?><?php endif; ?></dd></div>
                <?php endif; ?>
                <?php if ($row->location || $row->city): ?>
                    <div><dt class="lbl"><?php echo esc_html(I18n::t('location')); ?></dt><dd class="val"><?php echo esc_html(trim(implode('، ', array_filter([(string) $row->location, (string) $row->city, (string) $row->country])))); ?></dd></div>
                <?php endif; ?>
                <div><dt class="lbl"><?php echo esc_html(I18n::t('entry_fee')); ?></dt><dd class="val"><?php echo esc_html($row->fee !== null && (float) $row->fee > 0 ? Shop::format_price($row->fee, (string) $row->currency) : self::tr('رایگان', 'Free')); ?></dd></div>
                <?php if (!empty($row->max_works)): ?>
                    <div><dt class="lbl"><?php echo esc_html(self::tr('حداکثر تعداد اثر', 'Max. works')); ?></dt><dd class="val"><?php echo esc_html(self::fa_digits((string) (int) $row->max_works)); ?></dd></div>
                <?php endif; ?>
                <?php if ($row->exhibition_title && $row->exhibition_slug): ?>
                    <div><dt class="lbl"><?php echo esc_html(I18n::t('exhibition')); ?></dt><dd class="val"><a href="<?php echo esc_url(home_url('/art-coa/exhibition/' . rawurlencode((string) $row->exhibition_slug))); ?>"><?php echo esc_html((string) $row->exhibition_title); ?></a></dd></div>
                <?php endif; ?>
            </dl>

            <?php if ($row->deadline_utc): ?>
                <div class="art-coa-deadline-box <?php echo $open ? 'open' : 'closed'; ?>">
                    <strong><?php echo esc_html(I18n::t('deadline')); ?>:</strong>
                    <span><?php echo esc_html(Dates::format($row->deadline_utc, true)); ?></span>
                    <?php if ($open): ?>
                        <span class="aet-call-countdown art-coa-countdown" data-aet-deadline="<?php echo esc_attr($deadline_iso); ?>" aria-live="off"></span>
                    <?php else: ?>
                        <span class="art-coa-deadline-ended"><?php echo esc_html(I18n::t('deadline_passed')); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($row->description): ?>
                <section class="art-coa-section">
                    <h2><?php echo esc_html(I18n::t('description')); ?></h2>
                    <div class="art-coa-prose"><?php echo wp_kses_post(wpautop((string) $row->description)); ?></div>
                </section>
            <?php endif; ?>

            <?php if ($row->requirements): ?>
                <section class="art-coa-section">
                    <h2><?php echo esc_html(I18n::t('requirements')); ?></h2>
                    <div class="art-coa-prose"><?php echo wp_kses_post(wpautop((string) $row->requirements)); ?></div>
                </section>
            <?php endif; ?>

            <div class="aet-call-share" data-aet-share="<?php echo esc_url($url); ?>">
                <span><?php echo esc_html(self::tr('اشتراک‌گذاری:', 'Share:')); ?></span>
                <a href="<?php echo esc_url('https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode((string) $row->title)); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(self::tr('تلگرام', 'Telegram')); ?></a>
                <a href="<?php echo esc_url('https://wa.me/?text=' . rawurlencode((string) $row->title . ' ' . $url)); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(self::tr('واتس‌اپ', 'WhatsApp')); ?></a>
                <a href="<?php echo esc_url('https://twitter.com/intent/tweet?url=' . rawurlencode($url) . '&text=' . rawurlencode((string) $row->title)); ?>" target="_blank" rel="noopener noreferrer">X</a>
                <button type="button" class="aet-call-copy" data-aet-copy="<?php echo esc_url($url); ?>"><?php echo esc_html(self::tr('کپی پیوند', 'Copy link')); ?></button>
            </div>

            <?php if ($open && !empty($row->apply_url) && preg_match('#^https?://#i', (string) $row->apply_url)): ?>
                <p class="aet-call-external"><a class="button button-primary" href="<?php echo esc_url((string) $row->apply_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(self::tr('ثبت درخواست در سایت برگزارکننده', 'Apply on the organizer website')); ?></a></p>
            <?php elseif ($open): ?>
                <section class="art-coa-section art-coa-call-apply" id="call-artist-register">
                    <h2><?php echo esc_html(I18n::t('artist_register_for_call')); ?></h2>
                    <p class="description"><?php echo esc_html(I18n::t('artist_register_help')); ?></p>
                    <form method="post" class="art-coa-form art-coa-artist-call-form" action="<?php echo esc_url($url . '#call-artist-register'); ?>">
                        <?php wp_nonce_field('art_coa_call_apply'); ?>
                        <input type="hidden" name="art_coa_call_apply" value="1">
                        <input type="hidden" name="call_id" value="<?php echo (int) $row->id; ?>">
                        <input type="hidden" name="art_coa_ts" value="<?php echo esc_attr((string) time()); ?>">
                        <div class="aet-hp" aria-hidden="true"><label>Website URL <input type="text" name="art_coa_website_url" value="" tabindex="-1" autocomplete="off"></label></div>

                        <div class="art-coa-form-grid">
                            <p><label><?php echo esc_html(I18n::t('first_name')); ?> *<br>
                                <input type="text" name="first_name" required maxlength="100" autocomplete="given-name" class="widefat" value="<?php echo esc_attr($prefill['first_name']); ?>"></label></p>
                            <p><label><?php echo esc_html(I18n::t('last_name')); ?> *<br>
                                <input type="text" name="last_name" required maxlength="100" autocomplete="family-name" class="widefat" value="<?php echo esc_attr($prefill['last_name']); ?>"></label></p>
                            <p><label><?php echo esc_html(I18n::t('email')); ?> *<br>
                                <input type="email" name="applicant_email" required maxlength="190" autocomplete="email" dir="ltr" class="widefat" value="<?php echo esc_attr($prefill['email']); ?>"></label></p>
                            <p><label><?php echo esc_html(I18n::t('phone')); ?> *<br>
                                <input type="tel" name="applicant_phone" required maxlength="50" autocomplete="tel" dir="ltr" class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('city')); ?><br>
                                <input type="text" name="city" maxlength="120" class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('country')); ?><br>
                                <input type="text" name="country" maxlength="120" class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('medium')); ?><br>
                                <input type="text" name="medium" maxlength="255" class="widefat" placeholder="<?php echo esc_attr(self::tr('نقاشی، مجسمه، عکس…', 'Painting, sculpture…')); ?>"></label></p>
                            <p><label><?php echo esc_html(I18n::t('birth_year')); ?><br>
                                <input type="number" name="birth_year" min="1900" max="<?php echo (int) gmdate('Y'); ?>" class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('website')); ?><br>
                                <input type="url" name="website" maxlength="500" dir="ltr" class="widefat" placeholder="https://"></label></p>
                            <p><label><?php echo esc_html(I18n::t('portfolio_url')); ?><br>
                                <input type="url" name="portfolio_url" maxlength="500" dir="ltr" class="widefat" placeholder="https://"></label></p>
                            <p><label>Instagram<br>
                                <input type="text" name="instagram" maxlength="190" dir="ltr" class="widefat" placeholder="@username"></label></p>
                        </div>
                        <p><label><?php echo esc_html(I18n::t('bio')); ?><br>
                            <textarea name="bio" rows="3" maxlength="3000" class="widefat" placeholder="<?php echo esc_attr(I18n::t('bio_placeholder')); ?>"></textarea></label></p>
                        <p><label><?php echo esc_html(I18n::t('works_info')); ?> *<?php if (!empty($row->max_works)): ?> <small>(<?php echo esc_html(self::tr('حداکثر', 'max') . ' ' . self::fa_digits((string) (int) $row->max_works) . ' ' . self::tr('اثر', 'works')); ?>)</small><?php endif; ?><br>
                            <textarea name="works_info" rows="4" required maxlength="5000" class="widefat" placeholder="<?php echo esc_attr(I18n::t('works_info_placeholder')); ?>"></textarea></label></p>
                        <p><label><?php echo esc_html(I18n::t('message')); ?><br>
                            <textarea name="message" rows="3" maxlength="3000" class="widefat"></textarea></label></p>
                        <p class="art-coa-deadline-note">
                            <?php echo esc_html(I18n::t('deadline_form_note')); ?>
                            <?php if ($row->deadline_utc): ?><strong><?php echo esc_html(Dates::format($row->deadline_utc, true)); ?></strong><?php endif; ?>
                        </p>
                        <p><button type="submit" class="button button-primary"><?php echo esc_html(I18n::t('submit_artist_registration')); ?></button></p>
                    </form>
                </section>
            <?php else: ?>
                <p class="art-coa-auction-closed"><strong><?php echo esc_html(I18n::t('call_closed')); ?></strong>
                    <?php if ($row->deadline_utc): ?> — <?php echo esc_html(I18n::t('deadline')); ?>: <?php echo esc_html(Dates::format($row->deadline_utc, true)); ?><?php endif; ?>
                </p>
            <?php endif; ?>

            <?php $related = self::query(['status' => 'open', 'limit' => 3, 'exclude' => (int) $row->id, 'type' => (string) $row->call_type]); if ($related['items']): ?>
                <section class="art-coa-section aet-call-related">
                    <h2><?php echo esc_html(self::tr('فراخوان‌های باز دیگر', 'More open calls')); ?></h2>
                    <div class="art-coa-call-grid"><?php echo self::cards_html($related['items'], ['heading_tag' => 'h3']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </section>
            <?php endif; ?>

            <p><a href="<?php echo esc_url(home_url('/art-coa/calls/')); ?>">← <?php echo esc_html(I18n::t('calls_title')); ?></a></p>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /* ------------------------------------------------------------------ SEO */

    public static function document_title(string $title): string
    {
        if (self::$page_title === null || self::$page_title === '') {
            return $title;
        }
        return self::$page_title . ' | ' . get_bloginfo('name');
    }

    private static function seo_enabled(): bool
    {
        $settings = get_option('wp_art_coa_settings', []);
        return !(is_array($settings) && isset($settings['enable_seo']) && empty($settings['enable_seo']));
    }

    /** Canonical, description, robots and Open Graph tags for the calls routes. */
    public static function print_meta(): void
    {
        $mode = (string) get_query_var('art_coa_calls');
        if ($mode === '' || !self::seo_enabled() || is_404()) {
            return;
        }
        $call = self::$current_call;
        $filtered = isset($_GET['call_q']) && $_GET['call_q'] !== ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($call) {
            $url = self::url($call);
            $desc = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) ($call->description ?? ''))));
            if ($desc === '') {
                $desc = self::type_label((string) $call->call_type) . ' — ' . (string) $call->title;
            }
            $desc = \Artiey_AET_Text::substr($desc, 0, 160);
            $image = self::cover_url($call);
            $title = (string) $call->title;
            $type = 'article';
        } else {
            $url = home_url('/art-coa/calls/');
            $page = isset($_GET['cp']) ? absint($_GET['cp']) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $ctype = isset($_GET['type']) && is_scalar($_GET['type']) ? sanitize_key(wp_unslash((string) $_GET['type'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (in_array($ctype, self::TYPES, true)) {
                $url = add_query_arg('type', $ctype, $url);
            }
            if ($page > 1) {
                $url = add_query_arg('cp', $page, $url);
            }
            $desc = I18n::t('calls_intro');
            $image = '';
            $title = I18n::t('calls_title');
            $type = 'website';
        }
        echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
        echo '<meta name="description" content="' . esc_attr($desc) . '" />' . "\n";
        echo '<meta name="robots" content="' . ($filtered ? 'noindex, follow' : 'index, follow, max-image-preview:large') . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($type) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
        echo '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '" />' . "\n";
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
        }
    }

    /** schema.org Event for any call that has event dates (exhibition, event, residency, open studio). */
    public static function event_schema(object $call): ?array
    {
        if (empty($call->event_start_utc)) {
            return null;
        }
        $start_date = self::schema_datetime((string) $call->event_start_utc);
        if ($start_date === null) {
            return null;
        }
        $types = ['exhibition' => 'ExhibitionEvent', 'event' => 'Event', 'residency' => 'EducationEvent', 'open_studio' => 'VisualArtsEvent'];
        $schema = [
            '@context'  => 'https://schema.org',
            '@type'     => $types[(string) ($call->call_type ?? '')] ?? 'Event',
            'name'      => (string) $call->title,
            'startDate' => $start_date,
            'url'       => self::url($call),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        ];
        if (!empty($call->event_end_utc)) {
            $end_date = self::schema_datetime((string) $call->event_end_utc);
            if ($end_date !== null) {
                $schema['endDate'] = $end_date;
            }
        }
        $description = trim(wp_strip_all_tags((string) ($call->description ?? '')));
        if ($description !== '') {
            $schema['description'] = \Artiey_AET_Text::substr($description, 0, 500);
        }
        $image = self::cover_url($call);
        if ($image !== '') {
            $schema['image'] = esc_url_raw($image);
        }
        $place_name = trim(implode(', ', array_filter([
            (string) ($call->location ?? ''),
            (string) ($call->city ?? ''),
            (string) ($call->country ?? ''),
        ])));
        $place = ['@type' => 'Place', 'name' => $place_name !== '' ? $place_name : (string) ($call->gallery_name ?? $call->title)];
        $address = array_filter(['@type' => 'PostalAddress', 'streetAddress' => (string) ($call->location ?? ''), 'addressLocality' => (string) ($call->city ?? ''), 'addressCountry' => (string) ($call->country ?? '')]);
        if (count($address) > 1) {
            $place['address'] = $address;
        }
        $schema['location'] = $place;
        if (!empty($call->gallery_name)) {
            $organizer = ['@type' => 'ArtGallery', 'name' => (string) $call->gallery_name];
            if (!empty($call->gallery_slug)) {
                $organizer['url'] = home_url('/art-coa/gallery/' . rawurlencode((string) $call->gallery_slug) . '/');
            }
            $schema['organizer'] = $organizer;
        }
        if ($call->fee !== null && $call->fee !== '') {
            $offer = [
                '@type' => 'Offer',
                'price' => (string) (float) $call->fee,
                'priceCurrency' => (string) ($call->currency ?: 'IRR'),
                'url' => self::url($call),
                'availability' => self::is_open($call) ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            ];
            $valid = !empty($call->deadline_utc) ? self::schema_datetime((string) $call->deadline_utc) : null;
            if ($valid) {
                $offer['validThrough'] = $valid;
            }
            $schema['offers'] = $offer;
        }
        return $schema;
    }

    public static function print_event_schema(): void
    {
        $call = self::$current_call;
        if (!$call || !self::seo_enabled()) {
            return;
        }
        $schema = self::event_schema($call);
        if (!$schema) {
            return;
        }
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
    }

    private static function schema_datetime(string $datetime): ?string
    {
        try {
            return (new \DateTimeImmutable($datetime, new \DateTimeZone('UTC')))
                ->setTimezone(wp_timezone())
                ->format(DATE_ATOM);
        } catch (\Exception $exception) {
            return null;
        }
    }

    /* ------------------------------------------------------------------ applications */

    public static function handle_apply(): void
    {
        if (empty($_POST['art_coa_call_apply'])) {
            return;
        }
        $post = wp_unslash($_POST); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below
        $post = is_array($post) ? $post : [];
        if (!wp_verify_nonce(sanitize_text_field(self::input_string($post, '_wpnonce')), 'art_coa_call_apply')) {
            return;
        }

        $call_id = absint($post['call_id'] ?? 0);
        $back = $call_id ? self::url((object) ['id' => $call_id, 'slug' => self::slug_for($call_id)]) . '#call-artist-register' : home_url('/art-coa/calls/');

        // Bots: honeypot filled or the form was submitted faster than a person can fill it.
        $started = absint($post['art_coa_ts'] ?? 0);
        if (self::input_string($post, 'art_coa_website_url') !== '' || ($started > 0 && time() - $started < 3)) {
            self::set_flash('ok', I18n::t('application_sent'));
            wp_safe_redirect($back);
            exit;
        }

        $limit = static fn(string $v, int $n): string => \Artiey_AET_Text::substr($v, 0, $n);
        $first = $limit(sanitize_text_field(self::input_string($post, 'first_name')), 100);
        $last = $limit(sanitize_text_field(self::input_string($post, 'last_name')), 100);
        $name = trim($first . ' ' . $last);
        if ($name === '') {
            $name = $limit(sanitize_text_field(self::input_string($post, 'applicant_name')), 190);
        }
        $email = sanitize_email(self::input_string($post, 'applicant_email'));
        $phone = $limit(sanitize_text_field(self::input_string($post, 'applicant_phone')), 50);
        $city = $limit(sanitize_text_field(self::input_string($post, 'city')), 120);
        $country = $limit(sanitize_text_field(self::input_string($post, 'country')), 120);
        $website = esc_url_raw(self::input_string($post, 'website'), ['http', 'https']);
        $portfolio = esc_url_raw(self::input_string($post, 'portfolio_url'), ['http', 'https']);
        $instagram = $limit(sanitize_text_field(self::input_string($post, 'instagram')), 190);
        $medium = $limit(sanitize_text_field(self::input_string($post, 'medium')), 255);
        $birth_year = absint($post['birth_year'] ?? 0) ?: null;
        if ($birth_year !== null && ($birth_year < 1900 || $birth_year > (int) gmdate('Y'))) {
            $birth_year = null;
        }
        $bio = $limit(sanitize_textarea_field(self::input_string($post, 'bio')), 3000);
        $works_info = $limit(sanitize_textarea_field(self::input_string($post, 'works_info')), 5000);
        $message = $limit(sanitize_textarea_field(self::input_string($post, 'message')), 3000);

        if (!$call_id || $name === '' || !is_email($email) || $phone === '' || $works_info === '') {
            self::set_flash('err', I18n::t('reg_required'));
            wp_safe_redirect($back);
            exit;
        }

        $rk = 'art_coa_call_app_' . md5(Security::client_ip());
        $hits = (int) get_transient($rk);
        if ($hits >= 5) {
            self::set_flash('err', I18n::t('sec_rate_limit'));
            wp_safe_redirect($back);
            exit;
        }
        set_transient($rk, $hits + 1, HOUR_IN_SECONDS);

        global $wpdb;
        $call = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $call_id));
        if (!$call || !self::is_open($call)) {
            self::set_flash('err', I18n::t('call_closed'));
            wp_safe_redirect($back);
            exit;
        }
        $duplicate = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::applications_table() . ' WHERE call_id = %d AND applicant_email = %s LIMIT 1',
            $call_id,
            $email
        ));
        if ($duplicate) {
            self::set_flash('err', self::tr('برای این فراخوان قبلاً با همین ایمیل درخواست ثبت شده است.', 'An application with this email already exists for this call.'));
            wp_safe_redirect($back);
            exit;
        }

        $user_id = get_current_user_id();
        $artist_id = $user_id ? (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Database::table('artists') . ' WHERE user_id = %d LIMIT 1',
            $user_id
        )) : 0;

        $inserted = $wpdb->insert(self::applications_table(), [
            'call_id'          => $call_id,
            'artist_id'        => $artist_id ?: null,
            'user_id'          => $user_id ?: null,
            'applicant_name'   => $name,
            'first_name'       => $first,
            'last_name'        => $last,
            'applicant_email'  => $email,
            'applicant_phone'  => $phone,
            'city'             => $city,
            'country'          => $country,
            'website'          => $website,
            'portfolio_url'    => $portfolio,
            'instagram'        => $instagram,
            'medium'           => $medium,
            'birth_year'       => $birth_year,
            'bio'              => $bio,
            'works_info'       => $works_info,
            'message'          => $message,
            'status'           => 'pending',
            'created_at_utc'   => gmdate('Y-m-d H:i:s'),
        ]);
        if ($inserted === false) {
            self::set_flash('err', I18n::t('error'));
            wp_safe_redirect($back);
            exit;
        }

        $to = is_email((string) $call->contact_email) ? (string) $call->contact_email : (string) get_option('admin_email');
        $subject = '[' . I18n::t('calls_title') . '] ' . wp_strip_all_tags((string) $call->title) . ' — ' . $name;
        $body = "Artist registration for call: {$call->title}\n"
            . "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\n"
            . "City: {$city} / {$country}\nMedium: {$medium}\nBirth year: {$birth_year}\n"
            . "Website: {$website}\nPortfolio: {$portfolio}\nInstagram: {$instagram}\n\n"
            . "Bio:\n{$bio}\n\nWorks:\n{$works_info}\n\nMessage:\n{$message}\n\n"
            . 'Manage: ' . admin_url('admin.php?page=wp-art-coa-calls') . "\n";
        $reply_name = str_replace(['"', "\r", "\n", '<', '>'], '', $name);
        wp_mail($to, $subject, $body, [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: "' . $reply_name . '" <' . $email . '>',
        ]);
        do_action('art_coa_call_application', (int) $wpdb->insert_id, $call);

        Audit::log('call', $call_id, 'application', ['email' => $email]);
        self::set_flash('ok', I18n::t('application_sent'));
        wp_safe_redirect($back);
        exit;
    }

    private static function slug_for(int $id): string
    {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare('SELECT slug FROM ' . self::table() . ' WHERE id = %d', $id));
    }

    public static function auto_close_expired(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table() . " SET status = 'closed', updated_at_utc = %s
             WHERE status = 'open' AND deadline_utc IS NOT NULL AND deadline_utc < %s",
            gmdate('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s')
        ));
    }

    public static function is_open(object $row): bool
    {
        if (($row->status ?? '') !== 'open') {
            return false;
        }
        if (empty($row->deadline_utc)) {
            return true;
        }
        $deadline = strtotime((string) $row->deadline_utc . ' UTC');
        return $deadline !== false && $deadline > time();
    }

    public static function type_label(string $type): string
    {
        $map = [
            'exhibition' => I18n::t('call_type_exhibition'),
            'event'      => I18n::t('call_type_event'),
            'residency'  => I18n::t('call_type_residency'),
            'open_studio'=> I18n::t('call_type_open_studio'),
        ];
        return $map[$type] ?? $type;
    }

    public static function status_label(string $status): string
    {
        $map = [
            'pending'     => self::tr('در انتظار بررسی', 'Pending'),
            'shortlisted' => self::tr('فهرست کوتاه', 'Shortlisted'),
            'accepted'    => self::tr('پذیرفته‌شده', 'Accepted'),
            'rejected'    => self::tr('پذیرفته‌نشده', 'Not selected'),
        ];
        return $map[$status] ?? $status;
    }

    /* ------------------------------------------------------------------ admin data */

    /**
     * Validate admin form data (already unslashed).
     * @return array<string,mixed>|null column => value, or null when invalid
     */
    private static function prepare_row(array $data, bool $is_update): ?array
    {
        $title = sanitize_text_field(self::input_string($data, 'title'));
        if ($title === '') {
            return null;
        }
        $type = sanitize_key(self::input_string($data, 'call_type', 'exhibition'));
        if (!in_array($type, self::TYPES, true)) {
            return null;
        }
        $deadline = self::parse_dt(self::input_string($data, 'deadline'));
        $event_start_input = self::input_string($data, 'event_start');
        $event_end_input = self::input_string($data, 'event_end');
        $event_start = self::parse_dt($event_start_input);
        $event_end = self::parse_dt($event_end_input);
        if (!$deadline || ($event_start_input !== '' && !$event_start) || ($event_end_input !== '' && !$event_end)) {
            return null;
        }
        if ($event_start && $event_end && $event_end < $event_start) {
            return null;
        }
        $contact_email = sanitize_email(self::input_string($data, 'contact_email'));
        if (self::input_string($data, 'contact_email') !== '' && !is_email($contact_email)) {
            return null;
        }
        $fee_input = trim(self::input_string($data, 'fee'));
        if ($fee_input !== '' && (!is_numeric($fee_input) || (float) $fee_input < 0)) {
            return null;
        }
        $status = sanitize_key(self::input_string($data, 'status', 'open'));
        if (!in_array($status, ['open', 'closed'], true)) {
            return null;
        }
        if ($status === 'open' && $deadline <= gmdate('Y-m-d H:i:s')) {
            $status = 'closed';
        }
        $row = [
            'title'            => $title,
            'call_type'        => $type,
            'status'           => $status,
            'gallery_id'       => absint($data['gallery_id'] ?? 0) ?: null,
            'exhibition_id'    => absint($data['exhibition_id'] ?? 0) ?: null,
            'description'      => wp_kses_post(self::input_string($data, 'description')),
            'requirements'     => sanitize_textarea_field(self::input_string($data, 'requirements')),
            'deadline_utc'     => $deadline,
            'event_start_utc'  => $event_start,
            'event_end_utc'    => $event_end,
            'location'         => sanitize_text_field(self::input_string($data, 'location')),
            'city'             => sanitize_text_field(self::input_string($data, 'city')),
            'country'          => sanitize_text_field(self::input_string($data, 'country')),
            'contact_email'    => $contact_email,
            'max_works'        => absint($data['max_works'] ?? 0) ?: null,
            'fee'              => $fee_input !== '' ? (float) $fee_input : null,
            'currency'         => sanitize_text_field(self::input_string($data, 'currency', 'IRR')) ?: 'IRR',
            'apply_url'        => esc_url_raw(self::input_string($data, 'apply_url'), ['http', 'https']),
            'is_featured'      => !empty($data['is_featured']) ? 1 : 0,
            'updated_at_utc'   => gmdate('Y-m-d H:i:s'),
        ];
        if (!$is_update || Media::has($data, 'cover')) {
            $row += Media::columns($data, 'cover');
        }
        return $row;
    }

    private static function unique_slug(string $wanted, string $title, int $except = 0): string
    {
        global $wpdb;
        $slug = \Artiey_AET_Slug::from($wanted !== '' ? $wanted : $title, 'call');
        if ($slug === '') {
            $slug = 'call-' . time();
        }
        $base = $slug;
        $n = 1;
        while ((int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE slug = %s AND id <> %d', $slug, $except))) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    /** @param array<string,mixed> $data unslashed form data */
    public static function create(array $data): int
    {
        global $wpdb;
        $row = self::prepare_row($data, false);
        if (!$row) {
            return 0;
        }
        $row['slug'] = self::unique_slug(self::input_string($data, 'slug'), $row['title']);
        $row['created_at_utc'] = $row['updated_at_utc'];
        $wpdb->insert(self::table(), $row);
        return $wpdb->insert_id && $wpdb->last_error === '' ? (int) $wpdb->insert_id : 0;
    }

    /** @param array<string,mixed> $data unslashed form data */
    public static function update(int $id, array $data): bool
    {
        global $wpdb;
        if ($id <= 0) {
            return false;
        }
        $row = self::prepare_row($data, true);
        if (!$row) {
            return false;
        }
        $slug_input = self::input_string($data, 'slug');
        if ($slug_input !== '') {
            $row['slug'] = self::unique_slug($slug_input, $row['title'], $id);
        }
        return false !== $wpdb->update(self::table(), $row, ['id' => $id]);
    }

    public static function delete(int $id): bool
    {
        global $wpdb;
        if ($id <= 0) {
            return false;
        }
        $wpdb->delete(self::applications_table(), ['call_id' => $id], ['%d']);
        return (bool) $wpdb->delete(self::table(), ['id' => $id], ['%d']);
    }

    public static function set_application_status(int $id, string $status, string $note = '', bool $notify = false): bool
    {
        if ($id <= 0 || !in_array($status, self::APPLICATION_STATUSES, true)) {
            return false;
        }
        global $wpdb;
        $ok = false !== $wpdb->update(self::applications_table(), [
            'status' => $status,
            'admin_note' => \Artiey_AET_Text::substr(sanitize_textarea_field($note), 0, 2000),
            'updated_at_utc' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
        if ($ok && $notify && in_array($status, ['shortlisted', 'accepted', 'rejected'], true)) {
            $app = $wpdb->get_row($wpdb->prepare(
                'SELECT a.applicant_email, a.applicant_name, c.title FROM ' . self::applications_table() . ' a LEFT JOIN ' . self::table() . ' c ON c.id = a.call_id WHERE a.id = %d',
                $id
            ));
            if ($app && is_email((string) $app->applicant_email)) {
                $subject = sprintf(self::tr('نتیجهٔ درخواست شما برای «%s»', 'Your application for “%s”'), wp_strip_all_tags((string) $app->title));
                $body = sprintf(self::tr("%1\$s عزیز،\nوضعیت درخواست شما: %2\$s\n", "Dear %1\$s,\nYour application status: %2\$s\n"), (string) $app->applicant_name, self::status_label($status));
                wp_mail((string) $app->applicant_email, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
            }
        }
        return $ok;
    }

    /** CSV export of applications (Excel-friendly UTF-8 with BOM; formula injection neutralised). */
    public static function maybe_export(): void
    {
        if (empty($_GET['art_coa_export_apps']) || !current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('art_coa_export_apps');
        global $wpdb;
        $where = ' WHERE 1=1';
        $params = [];
        $call = absint($_GET['call'] ?? 0);
        $status = sanitize_key(wp_unslash((string) ($_GET['app_status'] ?? '')));
        if ($call) {
            $where .= ' AND a.call_id = %d';
            $params[] = $call;
        }
        if (in_array($status, self::APPLICATION_STATUSES, true)) {
            $where .= ' AND a.status = %s';
            $params[] = $status;
        }
        $sql = 'SELECT a.*, c.title AS call_title FROM ' . self::applications_table() . ' a LEFT JOIN ' . self::table() . ' c ON c.id = a.call_id' . $where . ' ORDER BY a.id DESC LIMIT 5000';
        $rows = $wpdb->get_results($params ? $wpdb->prepare($sql, ...$params) : $sql, ARRAY_A) ?: [];
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="call-applications-' . gmdate('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $cols = ['id', 'call_title', 'applicant_name', 'applicant_email', 'applicant_phone', 'city', 'country', 'medium', 'birth_year', 'website', 'portfolio_url', 'instagram', 'works_info', 'bio', 'message', 'status', 'admin_note', 'created_at_utc'];
        fputcsv($out, $cols);
        foreach ($rows as $r) {
            $line = [];
            foreach ($cols as $c) {
                $value = (string) ($r[$c] ?? '');
                $line[] = preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    public static function parse_dt(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $timezone = wp_timezone();
        foreach (['!Y-m-d\\TH:i', '!Y-m-d\\TH:i:s', '!Y-m-d H:i:s', '!Y-m-d H:i'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $v, $timezone);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || (!$errors['warning_count'] && !$errors['error_count']))) {
                return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    /** UTC → value for a datetime-local input in site time. */
    public static function to_local_input(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        try {
            return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(wp_timezone())->format('Y-m-d\\TH:i');
        } catch (\Exception $e) {
            return '';
        }
    }

    /** Reads a scalar from already-unslashed data. */
    private static function input_string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    /* ------------------------------------------------------------------ flash messages */

    private static function set_flash(string $type, string $msg): void
    {
        set_transient('art_coa_call_flash_' . self::guest_key(true), ['type' => $type, 'msg' => $msg], 120);
    }

    private static function get_flash(): ?array
    {
        $key = self::guest_key(false);
        if ($key === '') {
            return null;
        }
        $k = 'art_coa_call_flash_' . $key;
        $d = get_transient($k);
        if ($d) {
            delete_transient($k);
            return is_array($d) ? $d : null;
        }
        return null;
    }

    /** Per-browser key (cookie), so visitors behind one NAT never see each other's messages. */
    private static function guest_key(bool $create): string
    {
        $cookie = isset($_COOKIE['art_coa_flash']) && is_string($_COOKIE['art_coa_flash']) ? preg_replace('/[^a-f0-9]/', '', $_COOKIE['art_coa_flash']) : '';
        if (is_string($cookie) && strlen($cookie) === 32) {
            return $cookie;
        }
        if (!$create || headers_sent()) {
            return $create ? substr(md5(Security::client_ip()), 0, 12) : '';
        }
        $token = bin2hex(random_bytes(16));
        setcookie('art_coa_flash', $token, [
            'expires' => time() + HOUR_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
        $_COOKIE['art_coa_flash'] = $token;
        return $token;
    }
}
