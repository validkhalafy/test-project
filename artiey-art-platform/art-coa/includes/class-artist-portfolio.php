<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Artist portfolio items with upload + moderation.
 */
final class ArtistPortfolio
{
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'maybe_migrate']);
        add_action('init', [self::class, 'handle_upload']);
        add_shortcode('art_coa_artist_portfolio_upload', [self::class, 'upload_shortcode']);
    }

    public static function table(): string
    {
        return Database::table('artist_portfolio_items');
    }

    public static function maybe_migrate(): void
    {
        if (get_option('art_coa_dbv_artist_portfolio') === '2.3.0') {
            return;
        }
        self::run_migration();
        update_option('art_coa_dbv_artist_portfolio', '2.3.0', false);
    }

    private static function run_migration(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta('CREATE TABLE ' . self::table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            artist_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NULL,
            image_url TEXT NOT NULL,
            medium VARCHAR(190) NULL,
            year_created SMALLINT NULL,
            description TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY artist_id (artist_id),
            KEY status (status)
        ) {$charset};");
    }

    public static function for_artist(int $artist_id, bool $public_only = true): array
    {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::table() . ' WHERE artist_id = %d';
        if ($public_only) {
            $sql .= " AND status IN ('approved','published')";
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        return $wpdb->get_results($wpdb->prepare($sql, $artist_id)) ?: [];
    }

    public static function upload_shortcode($atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p class="art-coa-empty">' . esc_html(I18n::t('login_required')) . '</p>';
        }
        $user_id = get_current_user_id();
        $role = get_user_meta($user_id, 'art_coa_role', true);
        $artist_id = (int) get_user_meta($user_id, 'art_coa_entity_id', true);
        if ($role !== 'artist' || $artist_id <= 0) {
            if (!current_user_can('manage_options')) {
                return '<p class="art-coa-empty">' . esc_html(I18n::t('artist_only_upload')) . '</p>';
            }
            $artist_id = absint($atts['artist_id'] ?? 0);
        }

        $flash = get_transient('art_coa_pf_flash_' . $user_id);
        if ($flash) {
            delete_transient('art_coa_pf_flash_' . $user_id);
        }

        $items = $artist_id ? self::for_artist($artist_id, false) : [];

        ob_start();
        echo '<div class="art-coa-portfolio-upload" dir="' . (I18n::is_fa() ? 'rtl' : 'ltr') . '">';
        if ($flash) {
            echo '<div class="art-coa-result valid"><p>' . esc_html((string) $flash) . '</p></div>';
        }
        echo '<h3>' . esc_html(I18n::t('upload_portfolio')) . '</h3>';
        echo '<p class="description">' . esc_html(I18n::t('portfolio_upload_help')) . '</p>';
        echo '<form method="post" enctype="multipart/form-data" class="art-coa-form">';
        wp_nonce_field('art_coa_portfolio_upload');
        echo '<input type="hidden" name="art_coa_portfolio_upload" value="1">';
        echo '<input type="hidden" name="artist_id" value="' . (int) $artist_id . '">';
        echo '<p><label>' . esc_html(I18n::t('title')) . '<br><input type="text" name="title" class="widefat"></label></p>';
        echo '<p><label>' . esc_html(I18n::t('medium')) . '<br><input type="text" name="medium" class="widefat"></label></p>';
        echo '<p><label>' . esc_html(I18n::t('year')) . '<br><input type="number" name="year_created" min="1900" max="2100" class="widefat"></label></p>';
        echo '<p><label>' . esc_html(I18n::t('description')) . '<br><textarea name="description" rows="2" class="widefat"></textarea></label></p>';
        echo '<p><label>' . esc_html(I18n::t('portfolio_image')) . ' *<br>';
        echo '<input type="file" name="portfolio_image" accept="image/jpeg,image/png,image/webp,image/gif" required></label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html(I18n::t('upload_pending')) . '</button></p>';
        echo '</form>';

        if ($items) {
            echo '<h4>' . esc_html(I18n::t('my_portfolio_items')) . '</h4>';
            echo '<div class="art-coa-shop-grid cols-3">';
            foreach ($items as $it) {
                echo '<article class="art-coa-shop-card"><div class="art-coa-shop-img">';
                echo '<img src="' . esc_url($it->image_url) . '" alt="">';
                echo '<span class="art-coa-shop-badge">' . esc_html($it->status) . '</span></div>';
                echo '<div class="art-coa-shop-body"><h3>' . esc_html($it->title ?: '—') . '</h3></div></article>';
            }
            echo '</div>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    public static function handle_upload(): void
    {
        if (empty($_POST['art_coa_portfolio_upload']) || !is_user_logged_in()) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field((string) ($_POST['_wpnonce'] ?? '')), 'art_coa_portfolio_upload')) {
            return;
        }

        $user_id = get_current_user_id();
        $artist_id = absint($_POST['artist_id'] ?? 0);
        $role = get_user_meta($user_id, 'art_coa_role', true);
        $meta_id = (int) get_user_meta($user_id, 'art_coa_entity_id', true);

        if (!current_user_can('manage_options')) {
            if ($role !== 'artist' || $meta_id !== $artist_id) {
                return;
            }
        }
        if ($artist_id <= 0 || empty($_FILES['portfolio_image']['name'])) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file = $_FILES['portfolio_image'];
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (empty($check['type']) || !in_array($check['type'], $allowed, true)) {
            set_transient('art_coa_pf_flash_' . $user_id, I18n::t('invalid_image'), 60);
            wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/member-dashboard/'));
            exit;
        }

        $upload = wp_handle_upload($file, ['test_form' => false]);
        if (isset($upload['error'])) {
            set_transient('art_coa_pf_flash_' . $user_id, $upload['error'], 60);
            wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/member-dashboard/'));
            exit;
        }

        global $wpdb;
        self::maybe_migrate();
        $wpdb->insert(self::table(), [
            'artist_id'      => $artist_id,
            'user_id'        => $user_id,
            'title'          => sanitize_text_field(Security::input_scalar($_POST, 'title')),
            'image_url'      => esc_url_raw($upload['url']),
            'medium'         => sanitize_text_field(Security::input_scalar($_POST, 'medium')),
            'year_created'   => absint($_POST['year_created'] ?? 0) ?: null,
            'description'    => sanitize_textarea_field(Security::input_scalar($_POST, 'description')),
            'status'         => 'pending',
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);

        Audit::log('artist', $artist_id, 'portfolio_upload', ['item_id' => (int) $wpdb->insert_id]);
        set_transient('art_coa_pf_flash_' . $user_id, I18n::t('portfolio_pending_msg'), 120);
        wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/member-dashboard/'));
        exit;
    }

    public static function set_status(int $id, string $status): bool
    {
        $allowed = ['pending', 'approved', 'published', 'rejected'];
        $status = sanitize_key($status);
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        global $wpdb;
        return false !== $wpdb->update(self::table(), ['status' => $status], ['id' => $id]);
    }
}
