<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Secondary market: collectors resell owned artworks at a new price.
 */
final class SecondaryMarket
{
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'maybe_migrate']);
        add_shortcode('art_coa_secondary', [self::class, 'shortcode']);
        add_action('init', [self::class, 'rewrites']);
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'art_coa_secondary';
            return $vars;
        });
        add_action('template_redirect', [self::class, 'maybe_render']);
        add_action('init', [self::class, 'handle_list']);
        add_action('init', [self::class, 'handle_inquiry']);
        add_action('init', [self::class, 'handle_unlist']);
    }

    public static function table(): string
    {
        return Database::table('secondary_listings');
    }

    public static function maybe_migrate(): void
    {
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            artwork_id BIGINT UNSIGNED NOT NULL,
            collector_id BIGINT UNSIGNED NOT NULL,
            seller_user_id BIGINT UNSIGNED NULL,
            list_price DECIMAL(12,2) NOT NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'IRR',
            original_price DECIMAL(12,2) NULL,
            note TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            listed_at_utc DATETIME NOT NULL,
            sold_at_utc DATETIME NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY artwork_id (artwork_id),
            KEY collector_id (collector_id),
            KEY status (status)
        ) {$charset};";
        dbDelta($sql);
    }

    public static function rewrites(): void
    {
        add_rewrite_rule('^art-coa/secondary-market/?$', 'index.php?art_coa_secondary=all', 'top');
    }

    public static function maybe_render(): void
    {
        if (get_query_var('art_coa_secondary') !== 'all') {
            return;
        }
        status_header(200);
        nocache_headers();
        $content = self::render([]);
        if (class_exists('\\Artiey_AET_Elementor_Layouts')) {
            $content = \Artiey_AET_Elementor_Layouts::render_route('secondary_market', [
                'module' => 'secondary-market', 'entity_type' => 'secondary_market', 'mode' => 'archive',
            ], $content);
        }
        get_header();
        echo '<div class="art-coa-profile-wrap" dir="' . (I18n::is_fa() ? 'rtl' : 'ltr') . '">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted renderer/template.
        get_footer();
        exit;
    }

    public static function shortcode($atts = []): string
    {
        return self::render(shortcode_atts(['limit' => 24, 'columns' => 3], $atts));
    }

    public static function render(array $args): string
    {
        self::maybe_migrate();
        $limit = max(1, min(100, absint($args['limit'] ?? 24)));
        $columns = max(1, min(4, absint($args['columns'] ?? 3)));
        $items = self::active_listings($limit);
        $flash = get_transient('art_coa_sec_flash_' . self::guest_key());
        if ($flash) {
            delete_transient('art_coa_sec_flash_' . self::guest_key());
        }

        ob_start();
        ?>
        <div class="art-coa-shop art-coa-secondary" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <h2><?php echo esc_html(I18n::t('secondary_market')); ?></h2>
            <p class="art-coa-shop-intro"><?php echo esc_html(I18n::t('secondary_intro')); ?></p>
            <?php if ($flash): ?>
                <div class="art-coa-result valid"><p><?php echo esc_html((string) $flash); ?></p></div>
            <?php endif; ?>

            <?php if (!$items): ?>
                <p class="art-coa-empty"><?php echo esc_html(I18n::t('secondary_empty')); ?></p>
            <?php else: ?>
                <div class="art-coa-shop-grid cols-<?php echo (int) $columns; ?>">
                    <?php foreach ($items as $row):
                        $price = Shop::format_price($row->list_price, $row->currency);
                        $orig = $row->original_price !== null ? Shop::format_price($row->original_price, $row->currency) : null;
                        $change = self::price_change($row->original_price, $row->list_price);
                        $artist_is_public = ($row->artist_status ?? '') === 'active';
                        $an = $artist_is_public ? ($row->artist_name ?: trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''))) : '';
                        ?>
                        <article class="art-coa-shop-card">
                            <div class="art-coa-shop-img">
                                <?php if (\WPArtCOA\Media::url($row, 'image', 'large')): ?>
                                    <img src="<?php echo esc_url(\WPArtCOA\Media::url($row, 'image', 'large')); ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="art-coa-portfolio-placeholder">🖼</div>
                                <?php endif; ?>
                                <span class="art-coa-shop-badge art-coa-sec-badge"><?php echo esc_html(I18n::t('secondary_badge')); ?></span>
                            </div>
                            <div class="art-coa-shop-body">
                                <h3><?php echo esc_html($row->title); ?></h3>
                                <?php if ($an && $row->artist_slug): ?>
                                    <p class="meta"><a href="<?php echo esc_url(ContentSync::artist_url((string) $row->artist_slug)); ?>"><?php echo esc_html($an); ?></a></p>
                                <?php endif; ?>
                                <p class="meta"><?php echo esc_html(I18n::t('seller')); ?>:
                                    <?php if ((int) ($row->collector_is_public ?? 0) === 1 && ($row->collector_status ?? '') === 'active' && !empty($row->collector_slug)): ?>
                                        <a href="<?php echo esc_url(home_url('/art-coa/collector/' . rawurlencode((string) $row->collector_slug))); ?>"><?php echo esc_html((string) $row->collector_name); ?></a>
                                    <?php else: ?>
                                        <?php echo esc_html(I18n::is_fa() ? 'مجموعه‌دار خصوصی' : 'Private collector'); ?>
                                    <?php endif; ?>
                                </p>
                                <p class="art-coa-shop-price"><?php echo esc_html($price); ?></p>
                                <?php if ($orig): ?>
                                    <p class="meta art-coa-price-hist">
                                        <?php echo esc_html(I18n::t('original_price')); ?>: <?php echo esc_html($orig); ?>
                                        <?php if ($change !== null): ?>
                                            <span class="art-coa-price-change <?php echo $change >= 0 ? 'up' : 'down'; ?>">
                                                <?php echo $change >= 0 ? '▲' : '▼'; ?>
                                                <?php echo esc_html((string) abs($change)); ?>%
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($row->note): ?>
                                    <p class="meta"><?php echo esc_html($row->note); ?></p>
                                <?php endif; ?>
                                <form method="post" class="art-coa-shop-inquiry">
                                    <?php wp_nonce_field('art_coa_sec_inquiry'); ?>
                                    <input type="hidden" name="art_coa_sec_inquiry" value="1">
                                    <input type="hidden" name="listing_id" value="<?php echo (int) $row->id; ?>">
                                    <input type="text" name="buyer_name" required placeholder="<?php echo esc_attr(I18n::t('name')); ?>">
                                    <input type="email" name="buyer_email" required placeholder="<?php echo esc_attr(I18n::t('email')); ?>">
                                    <input type="text" name="buyer_phone" placeholder="<?php echo esc_attr(I18n::t('phone')); ?>">
                                    <textarea name="message" rows="2" placeholder="<?php echo esc_attr(I18n::t('shop_message')); ?>"></textarea>
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

    /**
     * Form HTML for collector dashboard: list owned artworks for resale.
     */
    public static function collector_panel(int $collector_id, int $user_id = 0): string
    {
        self::maybe_migrate();
        $owned = self::owned_artworks($collector_id);
        $my_listings = self::listings_by_collector($collector_id);

        ob_start();
        echo '<div class="art-coa-sec-panel">';
        echo '<h3>' . esc_html(I18n::t('secondary_list_title')) . '</h3>';
        echo '<p class="description">' . esc_html(I18n::t('secondary_list_help')) . '</p>';

        if ($my_listings) {
            echo '<h4>' . esc_html(I18n::t('secondary_my_listings')) . '</h4><ul class="art-coa-link-list">';
            foreach ($my_listings as $L) {
                $change = self::price_change($L->original_price, $L->list_price);
                echo '<li><strong>' . esc_html($L->title) . '</strong> — '
                    . esc_html(Shop::format_price($L->list_price, $L->currency));
                if ($change !== null) {
                    echo ' <span class="art-coa-price-change ' . ($change >= 0 ? 'up' : 'down') . '">'
                        . ($change >= 0 ? '▲' : '▼') . esc_html((string) abs($change)) . '%</span>';
                }
                if ($L->status === 'active') {
                    echo ' <form method="post" class="aap-inline">'
                        . wp_nonce_field('art_coa_sec_unlist', '_wpnonce', true, false)
                        . '<input type="hidden" name="art_coa_sec_unlist" value="1">'
                        . '<input type="hidden" name="listing_id" value="' . (int) $L->id . '">'
                        . '<button class="button button-small">' . esc_html(I18n::t('unlist')) . '</button></form>';
                } else {
                    echo ' (' . esc_html($L->status) . ')';
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        if (!$owned) {
            echo '<p class="art-coa-empty">' . esc_html(I18n::t('secondary_no_owned')) . '</p></div>';
            return (string) ob_get_clean();
        }

        echo '<form method="post" class="art-coa-form art-coa-sec-list-form">';
        wp_nonce_field('art_coa_sec_list');
        echo '<input type="hidden" name="art_coa_sec_list" value="1">';
        echo '<input type="hidden" name="collector_id" value="' . (int) $collector_id . '">';
        echo '<p><label>' . esc_html(I18n::t('artwork')) . '<br><select name="artwork_id" required class="widefat">';
        echo '<option value="">' . esc_html(I18n::t('select')) . '</option>';
        foreach ($owned as $aw) {
            // skip if already actively listed
            $listed = false;
            foreach ($my_listings as $L) {
                if ((int) $L->artwork_id === (int) $aw->id && $L->status === 'active') {
                    $listed = true;
                    break;
                }
            }
            if ($listed) {
                continue;
            }
            echo '<option value="' . (int) $aw->id . '" data-price="' . esc_attr((string) ($aw->price ?? '')) . '">'
                . esc_html($aw->title)
                . ($aw->price ? ' (' . esc_html(Shop::format_price($aw->price, $aw->currency ?? 'IRR')) . ')' : '')
                . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>' . esc_html(I18n::t('resale_price')) . ' *<br>'
            . '<input type="number" name="list_price" step="any" min="0" required class="widefat"></label></p>';
        echo '<p><label>' . esc_html(I18n::t('currency')) . '<br><select name="currency" class="widefat">'
            . '<option value="IRR">IRR</option><option value="IRT">IRT</option>'
            . '<option value="USD">USD</option><option value="EUR">EUR</option></select></label></p>';
        echo '<p><label>' . esc_html(I18n::t('sale_note')) . '<br><textarea name="note" rows="2" class="widefat"></textarea></label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html(I18n::t('list_for_sale')) . '</button></p>';
        echo '</form></div>';
        return (string) ob_get_clean();
    }

    public static function handle_list(): void
    {
        if (empty($_POST['art_coa_sec_list'])) {
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field((string) ($_POST['_wpnonce'] ?? '')), 'art_coa_sec_list')) {
            return;
        }

        $user_id = get_current_user_id();
        $collector_id = absint($_POST['collector_id'] ?? 0);
        $artwork_id = absint($_POST['artwork_id'] ?? 0);
        $price = (float) ($_POST['list_price'] ?? 0);
        $currency = sanitize_text_field(Security::input_scalar($_POST, 'currency', 'IRR')) ?: 'IRR';
        $note = sanitize_textarea_field(Security::input_scalar($_POST, 'note'));

        // Verify collector belongs to user
        $meta_id = (int) get_user_meta($user_id, 'art_coa_entity_id', true);
        $role = get_user_meta($user_id, 'art_coa_role', true);
        if ($role !== 'collector' || $meta_id !== $collector_id) {
            // allow manage_options
            if (!current_user_can('manage_options')) {
                return;
            }
        }

        if (!$artwork_id || $price <= 0 || !self::collector_owns($collector_id, $artwork_id)) {
            set_transient('art_coa_sec_flash_' . self::guest_key(), I18n::t('error'), 60);
            wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/member-dashboard/'));
            exit;
        }

        global $wpdb;
        $aw = $wpdb->get_row($wpdb->prepare(
            'SELECT price, currency FROM ' . Database::table('artworks') . ' WHERE id = %d',
            $artwork_id
        ));

        // Close previous active listing for same artwork
        $wpdb->update(self::table(), ['status' => 'cancelled'], [
            'artwork_id' => $artwork_id,
            'status'     => 'active',
        ]);

        $wpdb->insert(self::table(), [
            'artwork_id'      => $artwork_id,
            'collector_id'    => $collector_id,
            'seller_user_id'  => $user_id,
            'list_price'      => $price,
            'currency'        => $currency,
            'original_price'  => $aw->price !== null ? (float) $aw->price : null,
            'note'            => $note,
            'status'          => 'active',
            'listed_at_utc'   => gmdate('Y-m-d H:i:s'),
            'created_at_utc'  => gmdate('Y-m-d H:i:s'),
        ]);

        Audit::log('artwork', $artwork_id, 'secondary_listed', [
            'collector_id' => $collector_id,
            'price'        => $price,
            'original'     => $aw->price ?? null,
        ]);

        set_transient('art_coa_sec_flash_' . self::guest_key(), I18n::t('secondary_listed'), 120);
        wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/member-dashboard/'));
        exit;
    }

    public static function handle_unlist(): void
    {
        if (empty($_POST['art_coa_sec_unlist'])) {
            return;
        }
        if (!is_user_logged_in() || !wp_verify_nonce(sanitize_text_field((string) ($_POST['_wpnonce'] ?? '')), 'art_coa_sec_unlist')) {
            return;
        }
        $id = absint($_POST['listing_id'] ?? 0);
        $user_id = get_current_user_id();
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
        if (!$row) {
            return;
        }
        $meta_id = (int) get_user_meta($user_id, 'art_coa_entity_id', true);
        if ((int) $row->collector_id !== $meta_id && !current_user_can('manage_options')) {
            return;
        }
        $wpdb->update(self::table(), ['status' => 'cancelled'], ['id' => $id]);
        set_transient('art_coa_sec_flash_' . self::guest_key(), I18n::t('secondary_unlisted'), 60);
        wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/member-dashboard/'));
        exit;
    }

    public static function handle_inquiry(): void
    {
        if (empty($_POST['art_coa_sec_inquiry'])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field((string) ($_POST['_wpnonce'] ?? '')), 'art_coa_sec_inquiry')) {
            return;
        }
        $listing_id = absint($_POST['listing_id'] ?? 0);
        $name = sanitize_text_field(Security::input_scalar($_POST, 'buyer_name'));
        $email = sanitize_email(Security::input_scalar($_POST, 'buyer_email'));
        $phone = sanitize_text_field(Security::input_scalar($_POST, 'buyer_phone'));
        $message = sanitize_textarea_field(Security::input_scalar($_POST, 'message'));
        if (!$listing_id || $name === '' || !is_email($email)) {
            return;
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT l.*, a.title, c.display_name AS collector_name, c.user_id AS collector_user
             FROM ' . self::table() . ' l
             LEFT JOIN ' . Database::table('artworks') . ' a ON a.id = l.artwork_id
             LEFT JOIN ' . Database::table('collectors') . ' c ON c.id = l.collector_id
             WHERE l.id = %d AND l.status = %s',
            $listing_id,
            'active'
        ));
        if (!$row) {
            return;
        }

        $subject = '[' . I18n::t('secondary_market') . '] ' . ($row->title ?? '');
        $body = "Secondary market inquiry\nArtwork: {$row->title}\nList price: {$row->list_price} {$row->currency}\n"
            . "Seller: {$row->collector_name}\nBuyer: {$name}\nEmail: {$email}\nPhone: {$phone}\n\n{$message}\n";
        $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>'];
        wp_mail(get_option('admin_email'), $subject, $body, $headers);
        if ($row->collector_user) {
            $u = get_userdata((int) $row->collector_user);
            if ($u && $u->user_email) {
                wp_mail($u->user_email, $subject, $body, $headers);
            }
        }

        Audit::log('artwork', (int) $row->artwork_id, 'secondary_inquiry', ['listing_id' => $listing_id, 'email' => $email]);
        set_transient('art_coa_sec_flash_' . self::guest_key(), I18n::t('shop_inquiry_sent'), 120);
        wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/secondary-market/'));
        exit;
    }

    private static function active_listings(int $limit): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT l.*, a.title, a.image_url, a.image_id, a.year_created, a.medium,
                    ar.display_name AS artist_name, ar.slug AS artist_slug, ar.first_name, ar.last_name, ar.status AS artist_status,
                    c.display_name AS collector_name, c.slug AS collector_slug, c.is_public AS collector_is_public, c.status AS collector_status
             FROM ' . self::table() . ' l
             INNER JOIN ' . Database::table('artworks') . ' a ON a.id = l.artwork_id
             LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
             LEFT JOIN ' . Database::table('collectors') . ' c ON c.id = l.collector_id
             WHERE l.status = %s
             AND a.status IN (\'approved\',\'published\',\'active\',\'sold\')
             ORDER BY l.listed_at_utc DESC
             LIMIT %d',
            'active',
            $limit
        )) ?: [];
    }

    private static function owned_artworks(int $collector_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.* FROM " . Database::table('artworks') . " a
             WHERE a.current_owner_type = 'collector' AND a.current_owner_id = %d
             ORDER BY a.title ASC",
            $collector_id
        )) ?: [];
    }

    private static function listings_by_collector(int $collector_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT l.*, a.title FROM ' . self::table() . ' l
             LEFT JOIN ' . Database::table('artworks') . ' a ON a.id = l.artwork_id
             WHERE l.collector_id = %d
             ORDER BY l.id DESC LIMIT 50',
            $collector_id
        )) ?: [];
    }

    private static function collector_owns(int $collector_id, int $artwork_id): bool
    {
        global $wpdb;
        $n = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . Database::table('artworks') . "
             WHERE id = %d AND current_owner_type = 'collector' AND current_owner_id = %d",
            $artwork_id,
            $collector_id
        ));
        return $n > 0;
    }

    /** Percent change original → list. Null if no original. */
    public static function price_change($original, $list): ?float
    {
        $o = (float) $original;
        $l = (float) $list;
        if ($o <= 0) {
            return null;
        }
        return round((($l - $o) / $o) * 100, 1);
    }

    private static function guest_key(): string
    {
        return substr(md5($_SERVER['REMOTE_ADDR'] ?? 'x'), 0, 12);
    }
}
