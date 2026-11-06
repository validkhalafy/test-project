<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Open calls for exhibitions and events (فراخوان).
 */
final class Calls
{
    private const DB_VERSION = '2';
    private const TYPES = ['exhibition', 'event', 'residency', 'open_studio'];
    private static ?string $page_title = null;
    private static ?object $current_call = null;

    public static function init(): void
    {
        add_action('admin_init', [self::class, 'maybe_migrate']);
        add_shortcode('art_coa_calls', [self::class, 'shortcode']);
        add_action('init', [self::class, 'rewrites']);
        add_action('init', [self::class, 'schedule_expiry'], 20);
        add_action('art_coa_close_expired_calls', [self::class, 'auto_close_expired']);
        add_filter('pre_get_document_title', [self::class, 'document_title']);
        add_action('wp_head', [self::class, 'print_event_schema'], 99);
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
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY call_type (call_type),
            KEY deadline_utc (deadline_utc)
        ) {$charset};");

        dbDelta('CREATE TABLE ' . self::applications_table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            call_id BIGINT UNSIGNED NOT NULL,
            artist_id BIGINT UNSIGNED NULL,
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
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY call_id (call_id),
            KEY status (status)
        ) {$charset};");

        // Upgrade existing installs
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
        ];
        foreach ($extra as $col => $def) {
            if (!in_array($col, $cols, true)) {
                $wpdb->query("ALTER TABLE {$app_table} ADD COLUMN {$col} {$def}");
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

    public static function maybe_render(): void
    {
        $mode = get_query_var('art_coa_calls');
        if (!$mode) {
            return;
        }

        $not_found = false;
        $call_id = 0;
        $call_slug = '';
        if ($mode === 'single') {
            $call_id = absint(get_query_var('art_coa_call_id'));
            $content = $call_id ? self::render_single($call_id) : '';
            $not_found = $content === '';
        } elseif ($mode === 'slug') {
            $call_slug = sanitize_title(rawurldecode((string) get_query_var('art_coa_slug')));
            global $wpdb;
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
        echo $content !== '' ? $content : '<p class="art-coa-empty">' . esc_html(I18n::t('no_items')) . '</p>';
        echo '</div>';
        get_footer();
        exit;
    }

    public static function shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'type'   => '',
            'status' => 'open',
            'limit'  => 20,
        ], $atts, 'art_coa_calls');
        return self::render_list($atts);
    }

    public static function render_list(array $args): string
    {
        self::maybe_migrate();

        $raw_type = $args['type'] ?? '';
        $type = is_scalar($raw_type) ? sanitize_key((string) $raw_type) : '';
        if ($type === '' && isset($_GET['type']) && is_scalar($_GET['type'])) {
            $type = sanitize_key(wp_unslash((string) $_GET['type']));
        }
        if (!in_array($type, self::TYPES, true)) {
            $type = '';
        }
        $raw_status = $args['status'] ?? 'open';
        $status = is_scalar($raw_status) ? sanitize_key((string) $raw_status) : 'open';
        if (!in_array($status, ['open', 'closed', 'all'], true)) {
            $status = 'open';
        }
        $limit = max(1, min(50, absint($args['limit'] ?? 20)));

        global $wpdb;
        $sql = 'SELECT c.*, g.name AS gallery_name, g.slug AS gallery_slug
                FROM ' . self::table() . ' c
                LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = c.gallery_id
                WHERE 1=1';
        $params = [];
        if ($status && $status !== 'all') {
            $sql .= ' AND c.status = %s';
            $params[] = $status;
            if ($status === 'open') {
                $sql .= ' AND (c.deadline_utc IS NULL OR c.deadline_utc >= %s)';
                $params[] = gmdate('Y-m-d H:i:s');
            }
        }
        if ($type) {
            $sql .= ' AND c.call_type = %s';
            $params[] = $type;
        }
        $sql .= ' ORDER BY c.is_featured DESC, (c.deadline_utc IS NULL) ASC, c.deadline_utc ASC, c.id DESC LIMIT %d';
        $params[] = $limit;
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$params)) ?: [];
        $heading_tag = get_query_var('art_coa_calls') === 'list' ? 'h1' : 'h2';

        $flash = self::get_flash();
        ob_start();
        ?>
        <div class="art-coa-calls" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <<?php echo esc_html($heading_tag); ?>><?php echo esc_html(I18n::t('calls_title')); ?></<?php echo esc_html($heading_tag); ?>>
            <p class="art-coa-shop-intro"><?php echo esc_html(I18n::t('calls_intro')); ?></p>
            <?php if ($flash): ?>
                <div class="art-coa-result <?php echo $flash['type'] === 'ok' ? 'valid' : 'invalid'; ?>">
                    <p><?php echo esc_html($flash['msg']); ?></p>
                </div>
            <?php endif; ?>

            <div class="art-coa-calls-filters">
                <a class="button <?php echo $type === '' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(home_url('/art-coa/calls/')); ?>"><?php echo esc_html(I18n::t('all')); ?></a>
                <a class="button <?php echo $type === 'exhibition' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('type', 'exhibition', home_url('/art-coa/calls/'))); ?>"><?php echo esc_html(I18n::t('call_type_exhibition')); ?></a>
                <a class="button <?php echo $type === 'event' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('type', 'event', home_url('/art-coa/calls/'))); ?>"><?php echo esc_html(I18n::t('call_type_event')); ?></a>
                <a class="button <?php echo $type === 'residency' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('type', 'residency', home_url('/art-coa/calls/'))); ?>"><?php echo esc_html(I18n::t('call_type_residency')); ?></a>
                <a class="button <?php echo $type === 'open_studio' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('type', 'open_studio', home_url('/art-coa/calls/'))); ?>"><?php echo esc_html(I18n::t('call_type_open_studio')); ?></a>
            </div>

            <?php if (!$items): ?>
                <p class="art-coa-empty"><?php echo esc_html(I18n::t('calls_empty')); ?></p>
            <?php else: ?>
                <div class="art-coa-calls-grid">
                    <?php foreach ($items as $row):
                        echo self::card_html($row);
                    endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_single(int $id): string
    {
        self::maybe_migrate();
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT c.*, g.name AS gallery_name, g.slug AS gallery_slug, e.title AS exhibition_title, e.slug AS exhibition_slug
             FROM ' . self::table() . ' c
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = c.gallery_id
             LEFT JOIN ' . Database::table('exhibitions') . ' e ON e.id = c.exhibition_id
             WHERE c.id = %d',
            $id
        ));
        if (!$row) {
            return '';
        }

        self::$page_title = sanitize_text_field((string) $row->title);
        self::$current_call = $row;
        $open = self::is_open($row);
        $flash = self::get_flash();

        ob_start();
        ?>
        <div class="art-coa-call-single" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <?php if ($flash): ?>
                <div class="art-coa-result <?php echo $flash['type'] === 'ok' ? 'valid' : 'invalid'; ?>">
                    <p><?php echo esc_html($flash['msg']); ?></p>
                </div>
            <?php endif; ?>

            <div class="art-coa-call-header">
                <span class="art-coa-shop-badge"><?php echo esc_html(self::type_label($row->call_type)); ?></span>
                <?php if ($row->is_featured): ?>
                    <span class="art-coa-shop-badge aap-s-1fb46f"><?php echo esc_html(I18n::t('featured')); ?></span>
                <?php endif; ?>
                <h1><?php echo esc_html($row->title); ?></h1>
                <p class="meta">
                    <?php if ($row->gallery_name && $row->gallery_slug): ?>
                        <a href="<?php echo esc_url(home_url('/art-coa/gallery/' . $row->gallery_slug)); ?>"><?php echo esc_html($row->gallery_name); ?></a>
                        ·
                    <?php endif; ?>
                    <span class="art-badge"><?php echo esc_html($open ? I18n::t('call_open') : I18n::t('call_closed')); ?></span>
                </p>
            </div>

            <?php if ($row->cover_url): ?>
                <div class="art-coa-call-cover">
                    <img src="<?php echo esc_url($row->cover_url); ?>" alt="">
                </div>
            <?php endif; ?>

            <div class="art-coa-call-meta-grid">
                <?php if ($row->deadline_utc): ?>
                    <div><span class="lbl"><?php echo esc_html(I18n::t('deadline')); ?></span>
                        <span class="val"><?php echo esc_html(Dates::format($row->deadline_utc, true)); ?></span></div>
                <?php endif; ?>
                <?php if ($row->event_start_utc): ?>
                    <div><span class="lbl"><?php echo esc_html(I18n::t('event_dates')); ?></span>
                        <span class="val"><?php echo esc_html(Dates::format($row->event_start_utc)); ?>
                            <?php if ($row->event_end_utc): ?> — <?php echo esc_html(Dates::format($row->event_end_utc)); ?><?php endif; ?>
                        </span></div>
                <?php endif; ?>
                <?php if ($row->location || $row->city): ?>
                    <div><span class="lbl"><?php echo esc_html(I18n::t('location')); ?></span>
                        <span class="val"><?php echo esc_html(trim(($row->location ?: '') . ' ' . ($row->city ?: '') . ' ' . ($row->country ?: ''))); ?></span></div>
                <?php endif; ?>
                <?php if ($row->fee !== null && (float) $row->fee > 0): ?>
                    <div><span class="lbl"><?php echo esc_html(I18n::t('entry_fee')); ?></span>
                        <span class="val"><?php echo esc_html(Shop::format_price($row->fee, $row->currency)); ?></span></div>
                <?php endif; ?>
                <?php if ($row->exhibition_title && $row->exhibition_slug): ?>
                    <div><span class="lbl"><?php echo esc_html(I18n::t('exhibition')); ?></span>
                        <span class="val"><a href="<?php echo esc_url(home_url('/art-coa/exhibition/' . $row->exhibition_slug)); ?>"><?php echo esc_html($row->exhibition_title); ?></a></span></div>
                <?php endif; ?>
            </div>

            <?php if ($row->description): ?>
                <section class="art-coa-section">
                    <h2><?php echo esc_html(I18n::t('description')); ?></h2>
                    <div class="art-coa-prose"><?php echo wp_kses_post(wpautop($row->description)); ?></div>
                </section>
            <?php endif; ?>

            <?php if ($row->requirements): ?>
                <section class="art-coa-section">
                    <h2><?php echo esc_html(I18n::t('requirements')); ?></h2>
                    <div class="art-coa-prose"><?php echo wp_kses_post(wpautop($row->requirements)); ?></div>
                </section>
            <?php endif; ?>

            <?php if ($row->deadline_utc): ?>
                <div class="art-coa-deadline-box <?php echo $open ? 'open' : 'closed'; ?>" data-deadline="<?php echo esc_attr($row->deadline_utc); ?>">
                    <strong><?php echo esc_html(I18n::t('deadline')); ?> (Dead time):</strong>
                    <span><?php echo esc_html(Dates::format($row->deadline_utc, true)); ?></span>
                    <?php if ($open): ?>
                        <span class="art-coa-countdown" id="art-coa-call-countdown"></span>
                    <?php else: ?>
                        <span class="art-coa-deadline-ended"><?php echo esc_html(I18n::t('deadline_passed')); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($open): ?>
                <section class="art-coa-section art-coa-call-apply" id="call-artist-register">
                    <h2><?php echo esc_html(I18n::t('artist_register_for_call')); ?></h2>
                    <p class="description"><?php echo esc_html(I18n::t('artist_register_help')); ?></p>
                    <form method="post" class="art-coa-form art-coa-artist-call-form">
                        <?php wp_nonce_field('art_coa_call_apply'); ?>
                        <input type="hidden" name="art_coa_call_apply" value="1">
                        <input type="hidden" name="call_id" value="<?php echo (int) $row->id; ?>">

                        <div class="art-coa-form-grid">
                            <p><label><?php echo esc_html(I18n::t('first_name')); ?> *<br>
                                <input type="text" name="first_name" required class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('last_name')); ?> *<br>
                                <input type="text" name="last_name" required class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('email')); ?> *<br>
                                <input type="email" name="applicant_email" required class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('phone')); ?> *<br>
                                <input type="text" name="applicant_phone" required class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('city')); ?><br>
                                <input type="text" name="city" class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('country')); ?><br>
                                <input type="text" name="country" class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('medium')); ?><br>
                                <input type="text" name="medium" class="widefat" placeholder="<?php echo esc_attr(I18n::is_fa() ? 'نقاشی، مجسمه، عکس…' : 'Painting, sculpture…'); ?>"></label></p>
                            <p><label><?php echo esc_html(I18n::t('birth_year')); ?><br>
                                <input type="number" name="birth_year" min="1900" max="2100" class="widefat"></label></p>
                            <p><label><?php echo esc_html(I18n::t('website')); ?><br>
                                <input type="url" name="website" class="widefat" placeholder="https://"></label></p>
                            <p><label><?php echo esc_html(I18n::t('portfolio_url')); ?><br>
                                <input type="url" name="portfolio_url" class="widefat" placeholder="https://"></label></p>
                            <p><label>Instagram<br>
                                <input type="text" name="instagram" class="widefat" placeholder="@username"></label></p>
                        </div>
                        <p><label><?php echo esc_html(I18n::t('bio')); ?><br>
                            <textarea name="bio" rows="3" class="widefat" placeholder="<?php echo esc_attr(I18n::t('bio_placeholder')); ?>"></textarea></label></p>
                        <p><label><?php echo esc_html(I18n::t('works_info')); ?> *<br>
                            <textarea name="works_info" rows="4" required class="widefat" placeholder="<?php echo esc_attr(I18n::t('works_info_placeholder')); ?>"></textarea></label></p>
                        <p><label><?php echo esc_html(I18n::t('message')); ?><br>
                            <textarea name="message" rows="3" class="widefat"></textarea></label></p>
                        <p class="art-coa-deadline-note">
                            <?php echo esc_html(I18n::t('deadline_form_note')); ?>
                            <?php if ($row->deadline_utc): ?>
                                <strong><?php echo esc_html(Dates::format($row->deadline_utc, true)); ?></strong>
                            <?php endif; ?>
                        </p>
                        <p><button type="submit" class="button button-primary"><?php echo esc_html(I18n::t('submit_artist_registration')); ?></button></p>
                    </form>
                </section>
                <script>
                (function(){
                  var box = document.querySelector('.art-coa-deadline-box[data-deadline]');
                  var el = document.getElementById('art-coa-call-countdown');
                  if (!box || !el) return;
                  var end = Date.parse(box.getAttribute('data-deadline').replace(' ', 'T') + 'Z');
                  function tick(){
                    var diff = end - Date.now();
                    if (diff <= 0) { el.textContent = '—'; return; }
                    var d = Math.floor(diff/86400000), h = Math.floor((diff%86400000)/3600000),
                        m = Math.floor((diff%3600000)/60000), s = Math.floor((diff%60000)/1000);
                    el.textContent = (d?d+'d ':'') + h + 'h ' + m + 'm ' + s + 's';
                    setTimeout(tick, 1000);
                  }
                  tick();
                })();
                </script>
            <?php else: ?>
                <p class="art-coa-auction-closed"><strong><?php echo esc_html(I18n::t('call_closed')); ?></strong>
                    <?php if ($row->deadline_utc): ?>
                        — <?php echo esc_html(I18n::t('deadline')); ?>: <?php echo esc_html(Dates::format($row->deadline_utc, true)); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <p><a href="<?php echo esc_url(home_url('/art-coa/calls/')); ?>">← <?php echo esc_html(I18n::t('calls_title')); ?></a></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function document_title(string $title): string
    {
        if (self::$page_title === null || self::$page_title === '') {
            return $title;
        }
        return self::$page_title . ' | ' . get_bloginfo('name');
    }

    public static function print_event_schema(): void
    {
        $call = self::$current_call;
        if (!$call || ($call->call_type ?? '') !== 'event' || empty($call->event_start_utc)) {
            return;
        }

        $start_date = self::schema_datetime((string) $call->event_start_utc);
        if ($start_date === null) {
            return;
        }
        $schema = [
            '@context'  => 'https://schema.org',
            '@type'     => 'Event',
            'name'      => (string) $call->title,
            'startDate' => $start_date,
            'url'       => home_url('/art-coa/call/' . (string) $call->slug . '/'),
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
        if (!empty($call->cover_url)) {
            $schema['image'] = esc_url_raw((string) $call->cover_url);
        }
        if (!empty($call->location) || !empty($call->city) || !empty($call->country)) {
            $place = ['@type' => 'Place'];
            $place_name = trim(implode(', ', array_filter([
                (string) ($call->location ?? ''),
                (string) ($call->city ?? ''),
                (string) ($call->country ?? ''),
            ])));
            if ($place_name !== '') {
                $place['name'] = $place_name;
            }
            $schema['location'] = $place;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
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

    private static function card_html(object $row): string
    {
        $slug = !empty($row->slug) ? (string) $row->slug : (string) (int) $row->id;
        $url = home_url('/art-coa/call/' . $slug . '/');
        $open = self::is_open($row);
        ob_start();
        ?>
        <article class="art-coa-call-card <?php echo $row->is_featured ? 'featured' : ''; ?>">
            <?php if ($row->cover_url): ?>
                <a href="<?php echo esc_url($url); ?>" class="art-coa-call-card-img">
                    <img src="<?php echo esc_url($row->cover_url); ?>" alt="" loading="lazy">
                </a>
            <?php endif; ?>
            <div class="art-coa-call-card-body">
                <span class="art-coa-shop-badge"><?php echo esc_html(self::type_label($row->call_type)); ?></span>
                <h3><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($row->title); ?></a></h3>
                <?php if ($row->gallery_name): ?>
                    <p class="meta"><?php echo esc_html($row->gallery_name); ?></p>
                <?php endif; ?>
                <?php if ($row->deadline_utc): ?>
                    <p class="meta"><?php echo esc_html(I18n::t('deadline')); ?>: <?php echo esc_html(Dates::format($row->deadline_utc, true)); ?></p>
                <?php endif; ?>
                <?php if ($row->city || $row->location): ?>
                    <p class="meta"><?php echo esc_html($row->city ?: $row->location); ?></p>
                <?php endif; ?>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html($open ? I18n::t('view_and_apply') : I18n::t('view_page')); ?>
                    </a>
                </p>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    public static function handle_apply(): void
    {
        if (empty($_POST['art_coa_call_apply'])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(self::input_string($_POST, '_wpnonce')), 'art_coa_call_apply')) {
            return;
        }

        $call_id = absint($_POST['call_id'] ?? 0);
        $first = sanitize_text_field(self::input_string($_POST, 'first_name'));
        $last = sanitize_text_field(self::input_string($_POST, 'last_name'));
        $name = trim($first . ' ' . $last);
        if ($name === '') {
            $name = sanitize_text_field(self::input_string($_POST, 'applicant_name'));
        }
        $email = sanitize_email(self::input_string($_POST, 'applicant_email'));
        $phone = sanitize_text_field(self::input_string($_POST, 'applicant_phone'));
        $city = sanitize_text_field(self::input_string($_POST, 'city'));
        $country = sanitize_text_field(self::input_string($_POST, 'country'));
        $website = esc_url_raw(self::input_string($_POST, 'website'));
        $portfolio = esc_url_raw(self::input_string($_POST, 'portfolio_url'));
        $instagram = sanitize_text_field(self::input_string($_POST, 'instagram'));
        $medium = sanitize_text_field(self::input_string($_POST, 'medium'));
        $birth_year = absint($_POST['birth_year'] ?? 0) ?: null;
        $current_year = (int) gmdate('Y');
        if ($birth_year !== null && ($birth_year < 1900 || $birth_year > $current_year)) {
            $birth_year = null;
        }
        $bio = sanitize_textarea_field(self::input_string($_POST, 'bio'));
        $works_info = sanitize_textarea_field(self::input_string($_POST, 'works_info'));
        $message = sanitize_textarea_field(self::input_string($_POST, 'message'));

        if (!$call_id || $name === '' || !is_email($email) || $phone === '' || $works_info === '') {
            self::set_flash('err', I18n::t('reg_required'));
            wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/calls/'));
            exit;
        }

        $ip = Security::client_ip();
        $rk = 'art_coa_call_app_' . md5($ip);
        $hits = (int) get_transient($rk);
        if ($hits >= 5) {
            self::set_flash('err', I18n::t('sec_rate_limit'));
            wp_safe_redirect(home_url('/art-coa/call/' . $call_id));
            exit;
        }
        set_transient($rk, $hits + 1, HOUR_IN_SECONDS);

        global $wpdb;
        $call = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $call_id));
        if (!$call || !self::is_open($call)) {
            self::set_flash('err', I18n::t('call_closed'));
            wp_safe_redirect(home_url('/art-coa/call/' . $call_id));
            exit;
        }

        $inserted = $wpdb->insert(self::applications_table(), [
            'call_id'          => $call_id,
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
            wp_safe_redirect(home_url('/art-coa/call/' . $call_id));
            exit;
        }

        $to = $call->contact_email ?: get_option('admin_email');
        $subject = '[' . I18n::t('calls_title') . '] ' . $call->title . ' — ' . $name;
        $body = "Artist registration for call: {$call->title}\n"
            . "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\n"
            . "City: {$city} / {$country}\nMedium: {$medium}\nBirth year: {$birth_year}\n"
            . "Website: {$website}\nPortfolio: {$portfolio}\nInstagram: {$instagram}\n\n"
            . "Bio:\n{$bio}\n\nWorks:\n{$works_info}\n\nMessage:\n{$message}\n";
        wp_mail($to, $subject, $body, [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $name . ' <' . $email . '>',
        ]);

        Audit::log('call', $call_id, 'application', ['email' => $email]);
        self::set_flash('ok', I18n::t('application_sent'));
        wp_safe_redirect(home_url('/art-coa/call/' . $call_id));
        exit;
    }

    public static function auto_close_expired(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table() . " SET status = 'closed'
             WHERE status = 'open' AND deadline_utc IS NOT NULL AND deadline_utc < %s",
            gmdate('Y-m-d H:i:s')
        ));
    }

    private static function is_open(object $row): bool
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

    public static function create(array $data): int
    {
        global $wpdb;
        $title = sanitize_text_field(self::input_string($data, 'title'));
        if ($title === '') {
            return 0;
        }
        $type = sanitize_key(self::input_string($data, 'call_type', 'exhibition'));
        if (!in_array($type, self::TYPES, true)) {
            return 0;
        }
        $deadline_input = self::input_string($data, 'deadline');
        $deadline = self::parse_dt($deadline_input);
        $event_start_input = self::input_string($data, 'event_start');
        $event_end_input = self::input_string($data, 'event_end');
        $event_start = self::parse_dt($event_start_input);
        $event_end = self::parse_dt($event_end_input);
        if (!$deadline || ($event_start_input !== '' && !$event_start) || ($event_end_input !== '' && !$event_end)) {
            return 0;
        }
        if ($event_start && $event_end && $event_end < $event_start) {
            return 0;
        }

        $contact_email = sanitize_email(self::input_string($data, 'contact_email'));
        if (self::input_string($data, 'contact_email') !== '' && !is_email($contact_email)) {
            return 0;
        }
        $fee_input = self::input_string($data, 'fee');
        if ($fee_input !== '' && (!is_numeric($fee_input) || (float) $fee_input < 0)) {
            return 0;
        }

        $slug = \Artiey_AET_Slug::from(self::input_string($data, 'slug', $title) ?: $title, 'call');
        if ($slug === '') {
            $slug = 'call-' . time();
        }
        $base = $slug;
        $n = 1;
        while ($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE slug = %s', $slug))) {
            $slug = $base . '-' . $n++;
        }

        $now = gmdate('Y-m-d H:i:s');
        $status = sanitize_key(self::input_string($data, 'status', 'open'));
        if (!in_array($status, ['open', 'closed'], true)) {
            return 0;
        }
        if ($status === 'open' && $deadline <= $now) {
            $status = 'closed';
        }
        $wpdb->insert(self::table(), [
            'title'            => $title,
            'slug'             => $slug,
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
            'cover_url'        => esc_url_raw(self::input_string($data, 'cover_url')),
            'is_featured'      => !empty($data['is_featured']) ? 1 : 0,
            'created_at_utc'   => $now,
            'updated_at_utc'   => $now,
        ]);
        return $wpdb->insert_id && $wpdb->last_error === '' ? (int) $wpdb->insert_id : 0;
    }

    private static function parse_dt(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $timezone = wp_timezone();
        foreach (['!Y-m-d\\TH:i', '!Y-m-d\\TH:i:s', '!Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $v, $timezone);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || (!$errors['warning_count'] && !$errors['error_count']))) {
                return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    private static function input_string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;
        return is_scalar($value) ? (string) wp_unslash((string) $value) : $default;
    }

    private static function set_flash(string $type, string $msg): void
    {
        set_transient('art_coa_call_flash_' . self::guest_key(), ['type' => $type, 'msg' => $msg], 120);
    }

    private static function get_flash(): ?array
    {
        $k = 'art_coa_call_flash_' . self::guest_key();
        $d = get_transient($k);
        if ($d) {
            delete_transient($k);
            return is_array($d) ? $d : null;
        }
        return null;
    }

    private static function guest_key(): string
    {
        return substr(md5($_SERVER['REMOTE_ADDR'] ?? 'x'), 0, 12);
    }
}
