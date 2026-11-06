<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Shop
{
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
        status_header(200);
        nocache_headers();
        $slug = sanitize_title((string) get_query_var('art_coa_slug'));
        $args = ['filter' => 'all'];
        if ($mode === 'artist') {
            $args = ['filter' => 'artist', 'slug' => $slug];
            $route_key = 'shop_artist';
        } else {
            $route_key = 'shop_archive';
        }
        $content = self::render($args);
        if (class_exists('\\Artiey_AET_Elementor_Layouts')) {
            $content = \Artiey_AET_Elementor_Layouts::render_route($route_key, [
                'module' => 'shop', 'entity_type' => 'shop', 'mode' => $mode === 'artist' ? 'single' : 'archive', 'slug' => $slug,
            ], $content);
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
        ], $atts, 'art_coa_shop');
        return self::render($atts);
    }

    public static function render(array $args): string
    {
        self::maybe_migrate();

        $filter   = sanitize_key($args['filter'] ?? 'all');
        $artist_id = absint($args['artist'] ?? 0);
        $slug     = sanitize_title($args['slug'] ?? '');
        $limit    = max(1, min(100, absint($args['limit'] ?? 24)));
        $columns  = max(1, min(4, absint($args['columns'] ?? 3)));
        $only_sale = !isset($args['only_sale']) || in_array($args['only_sale'], ['1', 'yes', 1, true], true);
        $show_title = !isset($args['show_title']) || in_array($args['show_title'], ['1', 'yes', 1, true], true);
        $show_intro = !isset($args['show_intro']) || in_array($args['show_intro'], ['1', 'yes', 1, true], true);
        $show_filter = !isset($args['show_filter']) || in_array($args['show_filter'], ['1', 'yes', 1, true], true);
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

        $q_artist = absint(wp_unslash($_GET['artist'] ?? 0));
        $q_gallery = absint(wp_unslash($_GET['gallery'] ?? 0)) ?: $fixed_gallery;
        $min_raw = wp_unslash($_GET['min_price'] ?? '');
        $max_raw = wp_unslash($_GET['max_price'] ?? '');
        $q_min = is_scalar($min_raw) && is_numeric($min_raw) ? max(0, (float) $min_raw) : 0;
        $q_max = is_scalar($max_raw) && is_numeric($max_raw) ? max(0, (float) $max_raw) : 0;

        if ($q_artist) {
            $artist_id = $q_artist;
            $filter = 'artist';
        }

        $items = self::query($artist_id, $q_gallery, $only_sale, $limit, $q_min, $q_max);
        $artists = self::artists_with_sale();
        $galleries = self::galleries_with_sale();
        $flash = get_transient('art_coa_shop_flash_' . self::guest_key());
        if ($flash) {
            delete_transient('art_coa_shop_flash_' . self::guest_key());
        }

        ob_start();
        ?>
        <div class="art-coa-shop" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <?php if ($show_title): ?><h2 class="art-coa-shop-title"><?php echo esc_html($title); ?></h2><?php endif; ?>
            <?php if ($show_intro): ?><p class="art-coa-shop-intro"><?php echo esc_html(I18n::t('shop_intro')); ?></p><?php endif; ?>

            <?php if ($flash): ?>
                <div class="art-coa-result valid"><p><?php echo esc_html((string) $flash); ?></p></div>
            <?php endif; ?>

            <?php if ($show_filter): ?><form method="get" class="art-coa-shop-filters">
                <select name="artist">
                    <option value=""><?php echo esc_html(I18n::t('all') . ' — ' . I18n::t('artists')); ?></option>
                    <?php foreach ($artists as $a):
                        $name = $a->display_name ?: trim($a->first_name . ' ' . $a->last_name);
                        ?>
                        <option value="<?php echo (int) $a->id; ?>" <?php selected($artist_id, (int) $a->id); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="gallery">
                    <option value=""><?php echo esc_html(I18n::t('all') . ' — ' . I18n::t('galleries')); ?></option>
                    <?php foreach ($galleries as $g): ?>
                        <option value="<?php echo (int) $g->id; ?>" <?php selected($q_gallery, (int) $g->id); ?>><?php echo esc_html($g->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="min_price" placeholder="<?php echo esc_attr(I18n::t('min_price')); ?>" value="<?php echo $q_min ? esc_attr((string) $q_min) : ''; ?>" min="0" step="any">
                <input type="number" name="max_price" placeholder="<?php echo esc_attr(I18n::t('max_price')); ?>" value="<?php echo $q_max ? esc_attr((string) $q_max) : ''; ?>" min="0" step="any">
                <button type="submit" class="button"><?php echo esc_html(I18n::t('filter')); ?></button>
            </form><?php endif; ?>

            <?php if (!$items): ?>
                <p class="art-coa-empty"><?php echo esc_html(I18n::t('shop_empty')); ?></p>
            <?php else: ?>
                <div class="art-coa-shop-grid cols-<?php echo (int) $columns; ?>">
                    <?php foreach ($items as $aw):
                        $an = $aw->artist_name ?: trim(($aw->first_name ?? '') . ' ' . ($aw->last_name ?? ''));
                        $price = self::format_price($aw->price ?? null, $aw->currency ?? 'IRR');
                        ?>
                        <article class="art-coa-shop-card">
                            <div class="art-coa-shop-img">
                                <?php if (\WPArtCOA\Media::url($aw, 'image', 'large')): ?>
                                    <img src="<?php echo esc_url(\WPArtCOA\Media::url($aw, 'image', 'large')); ?>" alt="<?php echo esc_attr($aw->title); ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="art-coa-portfolio-placeholder">🖼</div>
                                <?php endif; ?>
                                <?php if (!empty($aw->for_sale)): ?>
                                    <span class="art-coa-shop-badge"><?php echo esc_html(I18n::t('for_sale')); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="art-coa-shop-body">
                                <h3><?php echo esc_html($aw->title); ?></h3>
                                <?php if ($an && !empty($aw->artist_slug)): ?>
                                    <p class="meta">
                                        <a href="<?php echo esc_url(ContentSync::artist_url((string) $aw->artist_slug)); ?>"><?php echo esc_html($an); ?></a>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($aw->gallery_name) && !empty($aw->gallery_slug)): ?>
                                    <p class="meta">
                                        <a href="<?php echo esc_url(home_url('/art-coa/gallery/' . $aw->gallery_slug)); ?>"><?php echo esc_html($aw->gallery_name); ?></a>
                                    </p>
                                <?php endif; ?>
                                <?php if ($aw->year_created || $aw->medium): ?>
                                    <p class="meta"><?php
                                        echo esc_html(trim(($aw->year_created ? (string) $aw->year_created : '') . ($aw->medium ? ' · ' . $aw->medium : ''), ' ·'));
                                    ?></p>
                                <?php endif; ?>
                                <p class="art-coa-shop-price"><?php echo esc_html($price); ?></p>
                                <?php if (!empty($aw->sale_note)): ?>
                                    <p class="meta"><?php echo esc_html($aw->sale_note); ?></p>
                                <?php endif; ?>
                                <form method="post" class="art-coa-shop-inquiry">
                                    <?php wp_nonce_field('art_coa_shop_inquiry'); ?>
                                    <input type="hidden" name="art_coa_shop_inquiry" value="1">
                                    <input type="hidden" name="artwork_id" value="<?php echo (int) $aw->id; ?>">
                                    <input type="text" name="buyer_name" required placeholder="<?php echo esc_attr(I18n::t('name')); ?>" class="widefat">
                                    <input type="email" name="buyer_email" required placeholder="<?php echo esc_attr(I18n::t('email')); ?>" class="widefat">
                                    <input type="text" name="buyer_phone" placeholder="<?php echo esc_attr(I18n::t('phone')); ?>" class="widefat">
                                    <textarea name="message" rows="2" placeholder="<?php echo esc_attr(I18n::t('shop_message')); ?>" class="widefat"></textarea>
                                    <button type="submit" class="button button-primary"><?php echo esc_html(I18n::t('shop_inquire')); ?></button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function handle_inquiry(): void
    {
        if (empty($_POST['art_coa_shop_inquiry'])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field((string) ($_POST['_wpnonce'] ?? '')), 'art_coa_shop_inquiry')) {
            return;
        }

        $artwork_id = absint($_POST['artwork_id'] ?? 0);
        $name = sanitize_text_field(Security::input_scalar($_POST, 'buyer_name'));
        $email = sanitize_email(Security::input_scalar($_POST, 'buyer_email'));
        $phone = sanitize_text_field(Security::input_scalar($_POST, 'buyer_phone'));
        $message = sanitize_textarea_field(Security::input_scalar($_POST, 'message'));

        if (!$artwork_id || $name === '' || !is_email($email)) {
            return;
        }

        global $wpdb;
        $art = $wpdb->get_row($wpdb->prepare(
            'SELECT a.title, ar.display_name, ar.first_name, ar.last_name, ar.email AS artist_email, g.email AS gallery_email, g.name AS gallery_name
             FROM ' . Database::table('artworks') . ' a
             LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = a.gallery_id
             WHERE a.id = %d',
            $artwork_id
        ));

        $artist_name = $art ? ($art->display_name ?: trim(($art->first_name ?? '') . ' ' . ($art->last_name ?? ''))) : '';
        $subject = sprintf('[%s] %s: %s', I18n::t('shop'), I18n::t('shop_inquire'), $art->title ?? ('#' . $artwork_id));
        $body = "Artwork: " . ($art->title ?? $artwork_id) . "\n"
            . "Artist: {$artist_name}\n"
            . "Gallery: " . ($art->gallery_name ?? '') . "\n"
            . "Buyer: {$name}\nEmail: {$email}\nPhone: {$phone}\n\n{$message}\n";

        $to = get_option('admin_email');
        $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>'];
        wp_mail($to, $subject, $body, $headers);

        if (!empty($art->artist_email) && is_email($art->artist_email)) {
            wp_mail($art->artist_email, $subject, $body, $headers);
        }
        if (!empty($art->gallery_email) && is_email($art->gallery_email) && $art->gallery_email !== $art->artist_email) {
            wp_mail($art->gallery_email, $subject, $body, $headers);
        }

        Audit::log('artwork', $artwork_id, 'shop_inquiry', [
            'name'  => $name,
            'email' => $email,
        ]);

        set_transient('art_coa_shop_flash_' . self::guest_key(), I18n::t('shop_inquiry_sent'), 120);
        wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/shop/'));
        exit;
    }

    private static function query(int $artist_id, int $gallery_id, bool $only_sale, int $limit, float $min, float $max): array
    {
        global $wpdb;
        $sql = 'SELECT a.*, ar.display_name AS artist_name, ar.slug AS artist_slug, ar.first_name, ar.last_name,
                       g.name AS gallery_name, g.slug AS gallery_slug
                FROM ' . Database::table('artworks') . ' a
                LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = a.gallery_id
                WHERE a.status IN (\'published\',\'active\')';
        $params = [];
        if ($only_sale) {
            $sql .= ' AND a.for_sale = 1';
        }
        if ($artist_id) {
            $sql .= ' AND a.artist_id = %d';
            $params[] = $artist_id;
        }
        if ($gallery_id) {
            $sql .= ' AND a.gallery_id = %d';
            $params[] = $gallery_id;
        }
        if ($min > 0) {
            $sql .= ' AND a.price >= %f';
            $params[] = $min;
        }
        if ($max > 0) {
            $sql .= ' AND a.price <= %f';
            $params[] = $max;
        }
        $sql .= ' ORDER BY a.id DESC LIMIT %d';
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, ...$params)) ?: [];
    }

    private static function artists_with_sale(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT DISTINCT ar.id, ar.display_name, ar.first_name, ar.last_name
             FROM ' . Database::table('artists') . ' ar
             INNER JOIN ' . Database::table('artworks') . ' a ON a.artist_id = ar.id AND a.for_sale = 1
             ORDER BY ar.display_name ASC LIMIT 200'
        ) ?: [];
    }

    private static function galleries_with_sale(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT DISTINCT g.id, g.name FROM ' . Database::table('galleries') . ' g
             INNER JOIN ' . Database::table('artworks') . ' a ON a.gallery_id = g.id AND a.for_sale = 1
             ORDER BY g.name ASC LIMIT 200'
        ) ?: [];
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

    private static function guest_key(): string
    {
        return substr(md5($_SERVER['REMOTE_ADDR'] ?? 'x'), 0, 12);
    }
}
