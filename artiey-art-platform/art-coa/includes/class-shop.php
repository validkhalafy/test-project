<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Artwork shop (/art-coa/shop/ and [art_coa_shop]): filterable, sortable and paginated cards linked to the artwork
 * page, with certificate-of-authenticity and auction badges, an online "buy" button (WooCommerce) and a protected
 * purchase-inquiry form (nonce, honeypot, minimum fill time, per-IP rate limit, public works only).
 */
final class Shop
{
    private const SALE_STATUSES = ['approved', 'published', 'active'];
    private const SORTS = [
        'newest'     => 'a.id DESC',
        'price_low'  => '(a.price IS NULL OR a.price <= 0) ASC, a.price ASC, a.id DESC',
        'price_high' => 'a.price DESC, a.id DESC',
        'title'      => 'a.title ASC',
    ];

    public static function init(): void
    {
        add_shortcode('art_coa_shop', [self::class, 'shortcode']);
        add_action('init', [self::class, 'rewrites']);
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'art_coa_shop';
            return $vars;
        });
        add_action('template_redirect', [self::class, 'maybe_render']);
        add_action('init', [self::class, 'handle_inquiry']);
        add_action('admin_init', [self::class, 'maybe_migrate']);
    }

    private static function tr(string $fa, string $en): string
    {
        return I18n::is_fa() ? $fa : $en;
    }

    public static function maybe_migrate(): void
    {
        if (get_option('art_coa_dbv_shop') === '2.3.0') {
            return;
        }
        self::run_migration();
        update_option('art_coa_dbv_shop', '2.3.0', false);
    }

    private static function run_migration(): void
    {
        global $wpdb;
        $table = Database::table('artworks');
        $cols = $wpdb->get_col("DESCRIBE {$table}", 0) ?: [];
        $add = [
            'price'       => 'DECIMAL(12,2) NULL',
            'currency'    => "VARCHAR(10) NOT NULL DEFAULT 'IRR'",
            'for_sale'    => 'TINYINT(1) NOT NULL DEFAULT 0',
            'sale_note'   => 'TEXT NULL',
        ];
        foreach ($add as $col => $def) {
            if (!in_array($col, $cols, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$col} {$def}");
            }
        }
    }

    public static function rewrites(): void
    {
        add_rewrite_rule('^art-coa/shop/?$', 'index.php?art_coa_shop=all', 'top');
        add_rewrite_rule('^art-coa/shop/artist/([^/]+)/?$', 'index.php?art_coa_shop=artist&art_coa_slug=$matches[1]', 'top');
    }

    public static function maybe_render(): void
    {
        $mode = get_query_var('art_coa_shop');
        if (!$mode) {
            return;
        }
        nocache_headers();
        $slug = sanitize_title((string) get_query_var('art_coa_slug'));
        $args = ['filter' => 'all'];
        $found = true;
        if ($mode === 'artist') {
            $args = ['filter' => 'artist', 'slug' => $slug];
            $route_key = 'shop_artist';
            global $wpdb;
            $found = $slug !== '' && (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . Database::table('artists') . " WHERE slug = %s AND status = 'active'",
                $slug
            )) > 0;
        } else {
            $route_key = 'shop_archive';
        }
        if (!$found) {
            global $wp_query;
            if ($wp_query instanceof \WP_Query) {
                $wp_query->set_404();
            }
            status_header(404);
            add_filter('wp_robots', 'wp_robots_no_robots');
            $content = '<p class="art-coa-empty">' . esc_html(I18n::t('not_found')) . '</p>';
        } else {
            status_header(200);
            $content = self::render($args);
            if (class_exists('\\Artiey_AET_Elementor_Layouts')) {
                $content = \Artiey_AET_Elementor_Layouts::render_route($route_key, [
                    'module' => 'shop', 'entity_type' => 'shop', 'mode' => $mode === 'artist' ? 'single' : 'archive', 'slug' => $slug,
                ], $content);
            }
        }
        get_header();
        echo '<div class="art-coa-profile-wrap" dir="' . (I18n::is_fa() ? 'rtl' : 'ltr') . '">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted shop renderer/template.
        get_footer();
        exit;
    }

    public static function shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'filter'  => 'all',
            'artist'  => 0,
            'slug'    => '',
            'limit'   => 24,
            'columns' => 3,
            'only_sale' => '1',
            'show_title' => '1',
            'title' => '',
            'show_intro' => '1',
            'show_filter' => '1',
            'gallery' => 0,
            'sort' => 'newest',
            'certified' => '0',
        ], $atts, 'art_coa_shop');
        return self::render($atts);
    }

    private static function flag(array $args, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $args)) {
            return $default;
        }
        return in_array($args[$key], ['1', 'yes', 1, true, 'true', 'on'], true);
    }

    /** Public URL of a catalogue artwork: the canonical WordPress post when published, else the module page. */
    public static function artwork_url(object $aw): string
    {
        $pid = (int) ($aw->cpt_post_id ?? 0);
        if ($pid && get_post_status($pid) === 'publish') {
            $url = get_permalink($pid);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }
        return !empty($aw->slug) ? home_url('/art-coa/artwork/' . rawurlencode((string) $aw->slug) . '/') : '';
    }

    public static function render(array $args): string
    {
        self::maybe_migrate();

        $filter   = sanitize_key($args['filter'] ?? 'all');
        $artist_id = absint($args['artist'] ?? 0);
        $slug     = sanitize_title($args['slug'] ?? '');
        $limit    = max(1, min(100, absint($args['limit'] ?? 24)));
        $columns  = max(1, min(4, absint($args['columns'] ?? 3)));
        $only_sale = self::flag($args, 'only_sale', true);
        $show_title = self::flag($args, 'show_title', true);
        $show_intro = self::flag($args, 'show_intro', true);
        $show_filter = self::flag($args, 'show_filter', true);
        $certified_only = self::flag($args, 'certified', false);
        $title = sanitize_text_field((string) ($args['title'] ?? ''));
        $title = $title !== '' ? $title : I18n::t('shop');
        $fixed_gallery = absint($args['gallery'] ?? 0);

        if (!$artist_id && $slug && $filter === 'artist') {
            global $wpdb;
            $artist_id = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . Database::table('artists') . ' WHERE slug = %s',
                $slug
            ));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only filters
        $q_artist = isset($_GET['artist']) && is_scalar($_GET['artist']) ? absint($_GET['artist']) : 0;
        $q_gallery = (isset($_GET['gallery']) && is_scalar($_GET['gallery']) ? absint($_GET['gallery']) : 0) ?: $fixed_gallery;
        $min_raw = isset($_GET['min_price']) && is_scalar($_GET['min_price']) ? wp_unslash((string) $_GET['min_price']) : '';
        $max_raw = isset($_GET['max_price']) && is_scalar($_GET['max_price']) ? wp_unslash((string) $_GET['max_price']) : '';
        $sort = isset($_GET['shop_sort']) && is_scalar($_GET['shop_sort']) ? sanitize_key(wp_unslash((string) $_GET['shop_sort'])) : sanitize_key((string) ($args['sort'] ?? 'newest'));
        $page = isset($_GET['shop_page']) ? max(1, absint($_GET['shop_page'])) : 1;
        if (!empty($_GET['certified'])) {
            $certified_only = true;
        }
        // phpcs:enable
        $q_min = is_numeric($min_raw) ? max(0, (float) $min_raw) : 0;
        $q_max = is_numeric($max_raw) ? max(0, (float) $max_raw) : 0;
        if ($q_min && $q_max && $q_min > $q_max) {
            [$q_min, $q_max] = [$q_max, $q_min];
        }
        $sort = isset(self::SORTS[$sort]) ? $sort : 'newest';

        if ($q_artist) {
            $artist_id = $q_artist;
        }

        $result = self::query($artist_id, $q_gallery, $only_sale, $limit, $q_min, $q_max, $sort, $page, $certified_only);
        $items = $result['items'];
        $artists = self::artists_with_sale();
        $galleries = self::galleries_with_sale();
        $flash = self::get_flash();
        $base = is_singular() ? (string) get_permalink() : home_url('/art-coa/shop/');
        $page_link = static function (int $p) use ($base): string {
            $params = [];
            foreach (['artist', 'gallery', 'min_price', 'max_price', 'shop_sort', 'certified'] as $k) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if (isset($_GET[$k]) && is_scalar($_GET[$k]) && $_GET[$k] !== '') {
                    $params[$k] = rawurlencode(sanitize_text_field(wp_unslash((string) $_GET[$k]))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                }
            }
            if ($p > 1) {
                $params['shop_page'] = $p;
            }
            return add_query_arg($params, $base);
        };

        ob_start();
        ?>
        <div class="art-coa-shop" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <?php if ($show_title): ?><h2 class="art-coa-shop-title"><?php echo esc_html($title); ?></h2><?php endif; ?>
            <?php if ($show_intro): ?><p class="art-coa-shop-intro"><?php echo esc_html(I18n::t('shop_intro')); ?></p><?php endif; ?>

            <?php if ($flash): ?>
                <div class="art-coa-result <?php echo $flash['type'] === 'ok' ? 'valid' : 'invalid'; ?>" role="status"><p><?php echo esc_html($flash['msg']); ?></p></div>
            <?php endif; ?>

            <?php if ($show_filter): ?><form method="get" class="art-coa-shop-filters" action="<?php echo esc_url($base); ?>">
                <label class="screen-reader-text" for="aet-shop-artist"><?php echo esc_html(I18n::t('artists')); ?></label>
                <select name="artist" id="aet-shop-artist">
                    <option value=""><?php echo esc_html(I18n::t('all') . ' — ' . I18n::t('artists')); ?></option>
                    <?php foreach ($artists as $a):
                        $name = $a->display_name ?: trim($a->first_name . ' ' . $a->last_name);
                        ?>
                        <option value="<?php echo (int) $a->id; ?>" <?php selected($artist_id, (int) $a->id); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$fixed_gallery): ?>
                <label class="screen-reader-text" for="aet-shop-gallery"><?php echo esc_html(I18n::t('galleries')); ?></label>
                <select name="gallery" id="aet-shop-gallery">
                    <option value=""><?php echo esc_html(I18n::t('all') . ' — ' . I18n::t('galleries')); ?></option>
                    <?php foreach ($galleries as $g): ?>
                        <option value="<?php echo (int) $g->id; ?>" <?php selected($q_gallery, (int) $g->id); ?>><?php echo esc_html($g->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <input type="number" name="min_price" aria-label="<?php echo esc_attr(I18n::t('min_price')); ?>" placeholder="<?php echo esc_attr(I18n::t('min_price')); ?>" value="<?php echo $q_min ? esc_attr((string) $q_min) : ''; ?>" min="0" step="any">
                <input type="number" name="max_price" aria-label="<?php echo esc_attr(I18n::t('max_price')); ?>" placeholder="<?php echo esc_attr(I18n::t('max_price')); ?>" value="<?php echo $q_max ? esc_attr((string) $q_max) : ''; ?>" min="0" step="any">
                <label class="screen-reader-text" for="aet-shop-sort"><?php echo esc_html(self::tr('مرتب‌سازی', 'Sort')); ?></label>
                <select name="shop_sort" id="aet-shop-sort">
                    <?php foreach (['newest' => self::tr('جدیدترین', 'Newest'), 'price_low' => self::tr('ارزان‌ترین', 'Price: low to high'), 'price_high' => self::tr('گران‌ترین', 'Price: high to low'), 'title' => self::tr('عنوان (الفبایی)', 'Title')] as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($sort, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="aet-shop-check"><input type="checkbox" name="certified" value="1" <?php checked($certified_only); ?>> <?php echo esc_html(self::tr('فقط آثار دارای گواهی اصالت', 'Certified works only')); ?></label>
                <button type="submit" class="button"><?php echo esc_html(I18n::t('filter')); ?></button>
            </form><?php endif; ?>

            <p class="aet-shop-count"><?php echo esc_html(Calendar::to_persian_digits((string) $result['total']) . ' ' . self::tr('اثر', 'works')); ?></p>
            <?php if (!$items): ?>
                <p class="art-coa-empty"><?php echo esc_html(I18n::t('shop_empty')); ?></p>
            <?php else: ?>
                <div class="art-coa-shop-grid cols-<?php echo (int) $columns; ?>">
                    <?php foreach ($items as $aw) { echo self::card_html($aw); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <?php if ($result['pages'] > 1): ?>
                    <nav class="aet-calls-pager aet-shop-pager" aria-label="<?php echo esc_attr(self::tr('صفحه‌بندی فروشگاه', 'Shop pages')); ?>">
                        <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
                            <a<?php echo $p === $result['page'] ? ' class="is-current" aria-current="page"' : ''; ?> href="<?php echo esc_url($page_link($p)); ?>"><?php echo esc_html(Calendar::to_persian_digits((string) $p)); ?></a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function card_html(object $aw): string
    {
        $an = $aw->artist_name ?: trim(($aw->first_name ?? '') . ' ' . ($aw->last_name ?? ''));
        $price = self::format_price($aw->price ?? null, (string) ($aw->currency ?? 'IRR'));
        $url = self::artwork_url($aw);
        $certs = class_exists('\\Artiey_AET_COA_Bridge') ? \Artiey_AET_COA_Bridge::certificate_summaries((int) ($aw->cpt_post_id ?? 0), (int) $aw->id) : [];
        $buy = class_exists(WooBridge::class) ? WooBridge::buy_url($aw) : '';
        $sold = ($aw->status ?? '') === 'sold';
        $img = Media::img($aw, 'image', 'medium_large', ['alt' => (string) $aw->title . ($an ? ' — ' . $an : '')]);
        ob_start();
        ?>
        <article class="art-coa-shop-card<?php echo $sold ? ' is-sold' : ''; ?>">
            <div class="art-coa-shop-img">
                <?php if ($url): ?><a href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true"><?php endif; ?>
                <?php echo $img ?: '<div class="art-coa-portfolio-placeholder">🖼</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media::img escapes ?>
                <?php if ($url): ?></a><?php endif; ?>
                <span class="aet-shop-badges">
                    <?php if ($sold): ?><span class="art-coa-shop-badge is-sold"><?php echo esc_html(self::tr('فروخته شد', 'Sold')); ?></span>
                    <?php elseif (!empty($aw->for_sale)): ?><span class="art-coa-shop-badge"><?php echo esc_html(I18n::t('for_sale')); ?></span><?php endif; ?>
                    <?php if (!empty($aw->auction_id)): ?><span class="art-coa-shop-badge is-auction"><?php echo esc_html(self::tr('در حراج', 'In auction')); ?></span><?php endif; ?>
                </span>
            </div>
            <div class="art-coa-shop-body">
                <h3><?php if ($url): ?><a href="<?php echo esc_url($url); ?>"><?php endif; ?><?php echo esc_html((string) $aw->title); ?><?php if ($url): ?></a><?php endif; ?></h3>
                <?php if (class_exists('\\Artiey_AET_COA_Bridge')) { echo \Artiey_AET_COA_Bridge::badge_html($certs, 'badge'); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php if ($an && !empty($aw->artist_slug)): ?>
                    <p class="meta"><a href="<?php echo esc_url(ContentSync::artist_url((string) $aw->artist_slug)); ?>"><?php echo esc_html($an); ?></a></p>
                <?php endif; ?>
                <?php if (!empty($aw->gallery_name) && !empty($aw->gallery_slug)): ?>
                    <p class="meta"><a href="<?php echo esc_url(ContentSync::permalink('galleries', (string) $aw->gallery_slug) ?: home_url('/art-coa/gallery/' . rawurlencode((string) $aw->gallery_slug) . '/')); ?>"><?php echo esc_html((string) $aw->gallery_name); ?></a></p>
                <?php endif; ?>
                <?php if (!empty($aw->year_created) || !empty($aw->medium)): ?>
                    <p class="meta"><?php echo esc_html(trim((!empty($aw->year_created) ? (string) $aw->year_created : '') . (!empty($aw->medium) ? ' · ' . $aw->medium : ''), ' ·')); ?></p>
                <?php endif; ?>
                <p class="art-coa-shop-price"><?php echo esc_html($sold ? self::tr('فروخته شد', 'Sold') : $price); ?></p>
                <?php if (!empty($aw->sale_note)): ?><p class="meta"><?php echo esc_html((string) $aw->sale_note); ?></p><?php endif; ?>
                <div class="aet-shop-actions">
                    <?php if (!empty($aw->auction_id)): ?>
                        <a class="button button-primary" href="<?php echo esc_url(home_url('/art-coa/auction/' . (int) $aw->auction_id . '/')); ?>"><?php echo esc_html(self::tr('ثبت پیشنهاد در حراج', 'Bid in auction')); ?></a>
                    <?php elseif ($buy): ?>
                        <a class="button button-primary" href="<?php echo esc_url($buy); ?>"><?php echo esc_html(self::tr('خرید آنلاین', 'Buy online')); ?></a>
                    <?php endif; ?>
                    <?php if ($url): ?><a class="button" href="<?php echo esc_url($url); ?>"><?php echo esc_html(self::tr('مشاهده اثر', 'View artwork')); ?></a><?php endif; ?>
                </div>
                <?php if (!$sold && empty($aw->auction_id) && !empty($aw->for_sale)): ?>
                    <details class="aet-shop-inquiry"><summary><?php echo esc_html(I18n::t('shop_inquire')); ?></summary>
                        <?php echo self::inquiry_form((int) $aw->id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </details>
                <?php endif; ?>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /** Purchase inquiry form for one catalogue artwork (also used on the artwork page). */
    public static function inquiry_form(int $artwork_id): string
    {
        $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $name = $user && $user->exists() ? (string) $user->display_name : '';
        $email = $user && $user->exists() ? (string) $user->user_email : '';
        $uid = 'aet-inq-' . $artwork_id;
        ob_start();
        ?>
        <form method="post" class="art-coa-shop-inquiry" id="<?php echo esc_attr($uid); ?>">
            <?php wp_nonce_field('art_coa_shop_inquiry'); ?>
            <input type="hidden" name="art_coa_shop_inquiry" value="1">
            <input type="hidden" name="artwork_id" value="<?php echo (int) $artwork_id; ?>">
            <input type="hidden" name="art_coa_ts" value="<?php echo esc_attr((string) time()); ?>">
            <div class="aet-hp" aria-hidden="true"><label>Company <input type="text" name="art_coa_company" value="" tabindex="-1" autocomplete="off"></label></div>
            <label><span class="screen-reader-text"><?php echo esc_html(I18n::t('name')); ?></span><input type="text" name="buyer_name" required maxlength="120" autocomplete="name" placeholder="<?php echo esc_attr(I18n::t('name')); ?> *" class="widefat" value="<?php echo esc_attr($name); ?>"></label>
            <label><span class="screen-reader-text"><?php echo esc_html(I18n::t('email')); ?></span><input type="email" name="buyer_email" required maxlength="190" autocomplete="email" dir="ltr" placeholder="<?php echo esc_attr(I18n::t('email')); ?> *" class="widefat" value="<?php echo esc_attr($email); ?>"></label>
            <label><span class="screen-reader-text"><?php echo esc_html(I18n::t('phone')); ?></span><input type="tel" name="buyer_phone" maxlength="40" autocomplete="tel" dir="ltr" placeholder="<?php echo esc_attr(I18n::t('phone')); ?>" class="widefat"></label>
            <label><span class="screen-reader-text"><?php echo esc_html(I18n::t('shop_message')); ?></span><textarea name="message" rows="2" maxlength="2000" placeholder="<?php echo esc_attr(I18n::t('shop_message')); ?>" class="widefat"></textarea></label>
            <button type="submit" class="button button-primary"><?php echo esc_html(I18n::t('shop_inquire')); ?></button>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    public static function handle_inquiry(): void
    {
        if (empty($_POST['art_coa_shop_inquiry'])) {
            return;
        }
        $post = wp_unslash($_POST); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field
        $post = is_array($post) ? $post : [];
        if (!wp_verify_nonce(sanitize_text_field((string) ($post['_wpnonce'] ?? '')), 'art_coa_shop_inquiry')) {
            return;
        }
        $back = wp_get_referer() ?: home_url('/art-coa/shop/');

        // Bots: honeypot or a form posted faster than a person can type.
        $started = absint($post['art_coa_ts'] ?? 0);
        if (Security::input_scalar($post, 'art_coa_company') !== '' || ($started > 0 && time() - $started < 3)) {
            self::set_flash('ok', I18n::t('shop_inquiry_sent'));
            wp_safe_redirect($back);
            exit;
        }

        $artwork_id = absint($post['artwork_id'] ?? 0);
        $name = \Artiey_AET_Text::substr(sanitize_text_field(Security::input_scalar($post, 'buyer_name')), 0, 120);
        $email = sanitize_email(Security::input_scalar($post, 'buyer_email'));
        $phone = \Artiey_AET_Text::substr(sanitize_text_field(Security::input_scalar($post, 'buyer_phone')), 0, 40);
        $message = \Artiey_AET_Text::substr(sanitize_textarea_field(Security::input_scalar($post, 'message')), 0, 2000);

        if (!$artwork_id || $name === '' || !is_email($email)) {
            self::set_flash('err', I18n::t('reg_required'));
            wp_safe_redirect($back);
            exit;
        }

        $rk = 'art_coa_shop_inq_' . md5(Security::client_ip());
        $hits = (int) get_transient($rk);
        if ($hits >= 6) {
            self::set_flash('err', I18n::t('sec_rate_limit'));
            wp_safe_redirect($back);
            exit;
        }
        set_transient($rk, $hits + 1, HOUR_IN_SECONDS);

        global $wpdb;
        $statuses = self::SALE_STATUSES;
        $art = $wpdb->get_row($wpdb->prepare(
            'SELECT a.*, ar.display_name, ar.first_name, ar.last_name, ar.email AS artist_email, g.email AS gallery_email, g.name AS gallery_name
             FROM ' . Database::table('artworks') . ' a
             LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = a.gallery_id
             WHERE a.id = %d AND a.status IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')',
            $artwork_id,
            ...$statuses
        ));
        if (!$art) {
            self::set_flash('err', I18n::t('not_found'));
            wp_safe_redirect($back);
            exit;
        }

        $artist_name = $art->display_name ?: trim(($art->first_name ?? '') . ' ' . ($art->last_name ?? ''));
        $subject = sprintf('[%s] %s: %s', I18n::t('shop'), I18n::t('shop_inquire'), wp_strip_all_tags((string) $art->title));
        $body = 'Artwork: ' . $art->title . "\n"
            . 'Page: ' . self::artwork_url($art) . "\n"
            . "Artist: {$artist_name}\n"
            . 'Gallery: ' . ($art->gallery_name ?? '') . "\n"
            . "Buyer: {$name}\nEmail: {$email}\nPhone: {$phone}\n\n{$message}\n";

        $reply_name = str_replace(['"', "\r", "\n", '<', '>'], '', $name);
        $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: "' . $reply_name . '" <' . $email . '>'];
        $sent = [strtolower((string) get_option('admin_email')) => true];
        wp_mail((string) get_option('admin_email'), $subject, $body, $headers);
        foreach ([(string) ($art->artist_email ?? ''), (string) ($art->gallery_email ?? '')] as $to) {
            if ($to !== '' && is_email($to) && !isset($sent[strtolower($to)])) {
                $sent[strtolower($to)] = true;
                wp_mail($to, $subject, $body, $headers);
            }
        }

        Audit::log('artwork', $artwork_id, 'shop_inquiry', [
            'name'  => $name,
            'email' => $email,
        ]);
        do_action('art_coa_shop_inquiry', $artwork_id, ['name' => $name, 'email' => $email, 'phone' => $phone]);

        self::set_flash('ok', I18n::t('shop_inquiry_sent'));
        wp_safe_redirect($back);
        exit;
    }

    /** @return array{items:object[],total:int,pages:int,page:int} */
    private static function query(int $artist_id, int $gallery_id, bool $only_sale, int $limit, float $min, float $max, string $sort = 'newest', int $page = 1, bool $certified = false): array
    {
        global $wpdb;
        $statuses = $only_sale ? self::SALE_STATUSES : Entities\Artwork::public_statuses();
        $auction_table = class_exists(Auction::class) ? Auction::listings_table() : '';
        $from = ' FROM ' . Database::table('artworks') . ' a
                LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = a.gallery_id
                WHERE a.status IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')
                  AND (ar.id IS NULL OR ar.status = \'active\')';
        $params = $statuses;
        if ($only_sale) {
            $from .= ' AND a.for_sale = 1';
        }
        if ($artist_id) {
            $from .= ' AND a.artist_id = %d';
            $params[] = $artist_id;
        }
        if ($gallery_id) {
            $from .= ' AND a.gallery_id = %d';
            $params[] = $gallery_id;
        }
        if ($min > 0) {
            $from .= ' AND a.price >= %f';
            $params[] = $min;
        }
        if ($max > 0) {
            $from .= ' AND a.price <= %f';
            $params[] = $max;
        }
        if ($certified) {
            $from .= " AND EXISTS (SELECT 1 FROM " . Database::table('certificates') . " c WHERE c.artwork_id = a.id AND c.status = 'active')";
        }
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*)' . $from, ...$params));
        $pages = max(1, (int) ceil($total / $limit));
        $page = min(max(1, $page), $pages);
        $auction_col = $auction_table !== ''
            ? ", (SELECT au.id FROM {$auction_table} au WHERE au.artwork_id = a.id AND au.status = 'active' AND au.ends_at_utc > UTC_TIMESTAMP() ORDER BY au.ends_at_utc ASC LIMIT 1) AS auction_id"
            : ', NULL AS auction_id';
        $sql = 'SELECT a.*, ar.display_name AS artist_name, ar.slug AS artist_slug, ar.first_name, ar.last_name,
                       g.name AS gallery_name, g.slug AS gallery_slug' . $auction_col . $from
            . ' ORDER BY ' . self::SORTS[$sort] . ' LIMIT %d OFFSET %d';
        $items = $wpdb->get_results($wpdb->prepare($sql, ...array_merge($params, [$limit, ($page - 1) * $limit]))) ?: [];
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    private static function artists_with_sale(): array
    {
        global $wpdb;
        $st = self::SALE_STATUSES;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT ar.id, ar.display_name, ar.first_name, ar.last_name
             FROM ' . Database::table('artists') . ' ar
             INNER JOIN ' . Database::table('artworks') . ' a ON a.artist_id = ar.id AND a.for_sale = 1 AND a.status IN (' . implode(',', array_fill(0, count($st), '%s')) . ")
             WHERE ar.status = 'active'
             ORDER BY ar.display_name ASC LIMIT 200",
            ...$st
        )) ?: [];
    }

    private static function galleries_with_sale(): array
    {
        global $wpdb;
        $st = self::SALE_STATUSES;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT g.id, g.name FROM ' . Database::table('galleries') . ' g
             INNER JOIN ' . Database::table('artworks') . ' a ON a.gallery_id = g.id AND a.for_sale = 1 AND a.status IN (' . implode(',', array_fill(0, count($st), '%s')) . ")
             WHERE g.status = 'active'
             ORDER BY g.name ASC LIMIT 200",
            ...$st
        )) ?: [];
    }

    public static function format_price($price, string $currency = 'IRR'): string
    {
        if ($price === null || $price === '' || (float) $price <= 0) {
            return I18n::t('price_on_request');
        }
        $n = number_format((float) $price, 0, '.', ',');
        if (I18n::is_fa()) {
            $n = \WPArtCOA\Calendar::to_persian_digits($n);
            $map = ['IRR' => 'ریال', 'IRT' => 'تومان', 'USD' => 'دلار', 'EUR' => 'یورو'];
            return $n . ' ' . ($map[$currency] ?? $currency);
        }
        return $n . ' ' . $currency;
    }

    /* ------------------------------------------------------------------ flash */

    private static function set_flash(string $type, string $msg): void
    {
        set_transient('art_coa_shop_flash_' . self::guest_key(true), ['type' => $type, 'msg' => $msg], 120);
    }

    private static function get_flash(): ?array
    {
        $key = self::guest_key(false);
        if ($key === '') {
            return null;
        }
        $d = get_transient('art_coa_shop_flash_' . $key);
        if (!$d) {
            return null;
        }
        delete_transient('art_coa_shop_flash_' . $key);
        return is_array($d) ? $d : ['type' => 'ok', 'msg' => (string) $d];
    }

    /** Per-browser key (cookie) so visitors behind one NAT never see each other's messages. */
    private static function guest_key(bool $create): string
    {
        $cookie = isset($_COOKIE['art_coa_flash']) && is_string($_COOKIE['art_coa_flash']) ? (string) preg_replace('/[^a-f0-9]/', '', $_COOKIE['art_coa_flash']) : '';
        if (strlen($cookie) === 32) {
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
