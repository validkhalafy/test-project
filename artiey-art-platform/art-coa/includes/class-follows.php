<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * User follows for artists and galleries (favorites).
 */
final class Follows
{
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'maybe_migrate']);
        add_action('init', [self::class, 'handle']);
        add_shortcode('art_coa_follow_button', [self::class, 'button_shortcode']);
        add_shortcode('art_coa_my_follows', [self::class, 'dashboard_shortcode']);
    }

    public static function table(): string
    {
        return Database::table('follows');
    }

    public static function maybe_migrate(): void
    {
        if (get_option('art_coa_dbv_follows') === '2.3.0') {
            return;
        }
        self::run_migration();
        update_option('art_coa_dbv_follows', '2.3.0', false);
    }

    private static function run_migration(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta('CREATE TABLE ' . self::table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_entity (user_id, entity_type, entity_id),
            KEY entity (entity_type, entity_id)
        ) {$charset};");
    }

    public static function is_following(int $user_id, string $type, int $entity_id): bool
    {
        if ($user_id <= 0 || $entity_id <= 0) {
            return false;
        }
        $type = self::normalize_type($type);
        if (!$type) {
            return false;
        }
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::table() . ' WHERE user_id = %d AND entity_type = %s AND entity_id = %d',
            $user_id,
            $type,
            $entity_id
        )) > 0;
    }

    public static function count(string $type, int $entity_id): int
    {
        $type = self::normalize_type($type);
        if (!$type || $entity_id <= 0) {
            return 0;
        }
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE entity_type = %s AND entity_id = %d',
            $type,
            $entity_id
        ));
    }

    public static function follow(int $user_id, string $type, int $entity_id): bool
    {
        $type = self::normalize_type($type);
        if ($user_id <= 0 || !$type || $entity_id <= 0) {
            return false;
        }
        if (self::is_following($user_id, $type, $entity_id)) {
            return true;
        }
        global $wpdb;
        self::maybe_migrate();
        $ok = false !== $wpdb->insert(self::table(), [
            'user_id'        => $user_id,
            'entity_type'    => $type,
            'entity_id'      => $entity_id,
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($ok) {
            Audit::log($type, $entity_id, 'followed', ['user_id' => $user_id]);
        }
        return (bool) $ok;
    }

    public static function unfollow(int $user_id, string $type, int $entity_id): bool
    {
        $type = self::normalize_type($type);
        if ($user_id <= 0 || !$type || $entity_id <= 0) {
            return false;
        }
        global $wpdb;
        $ok = false !== $wpdb->delete(self::table(), [
            'user_id'     => $user_id,
            'entity_type' => $type,
            'entity_id'   => $entity_id,
        ]);
        if ($ok) {
            Audit::log($type, $entity_id, 'unfollowed', ['user_id' => $user_id]);
        }
        return (bool) $ok;
    }

    public static function for_user(int $user_id): array
    {
        if ($user_id <= 0) {
            return ['artists' => [], 'galleries' => []];
        }
        global $wpdb;
        self::maybe_migrate();
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY created_at_utc DESC LIMIT 200',
            $user_id
        )) ?: [];

        $artist_ids = [];
        $gallery_ids = [];
        foreach ($rows as $r) {
            if ($r->entity_type === 'artist') {
                $artist_ids[] = (int) $r->entity_id;
            } elseif ($r->entity_type === 'gallery') {
                $gallery_ids[] = (int) $r->entity_id;
            }
        }

        $artists = [];
        $galleries = [];
        if ($artist_ids) {
            $in = implode(',', array_map('intval', $artist_ids));
            $artists = $wpdb->get_results(
                'SELECT id, slug, display_name, first_name, last_name, avatar_url FROM '
                . Database::table('artists') . " WHERE id IN ({$in})"
            ) ?: [];
        }
        if ($gallery_ids) {
            $in = implode(',', array_map('intval', $gallery_ids));
            $galleries = $wpdb->get_results(
                'SELECT id, slug, name, logo_url, city FROM '
                . Database::table('galleries') . " WHERE id IN ({$in})"
            ) ?: [];
        }

        return ['artists' => $artists, 'galleries' => $galleries];
    }

    public static function button_html(string $type, int $entity_id): string
    {
        $type = self::normalize_type($type);
        if (!$type || $entity_id <= 0) {
            return '';
        }

        $count = self::count($type, $entity_id);
        $user_id = get_current_user_id();
        $following = $user_id ? self::is_following($user_id, $type, $entity_id) : false;

        ob_start();
        echo '<div class="art-coa-follow-wrap">';
        if (!$user_id) {
            $login = wp_login_url(get_permalink() ?: home_url($_SERVER['REQUEST_URI'] ?? '/'));
            echo '<a class="button art-coa-follow-btn" href="' . esc_url($login) . '">'
                . esc_html(I18n::t('follow')) . '</a>';
        } else {
            echo '<form method="post" class="art-coa-follow-form">';
            wp_nonce_field('art_coa_follow');
            echo '<input type="hidden" name="art_coa_follow" value="1">';
            echo '<input type="hidden" name="entity_type" value="' . esc_attr($type) . '">';
            echo '<input type="hidden" name="entity_id" value="' . (int) $entity_id . '">';
            echo '<input type="hidden" name="follow_action" value="' . ($following ? 'unfollow' : 'follow') . '">';
            echo '<button type="submit" class="button ' . ($following ? '' : 'button-primary') . ' art-coa-follow-btn'
                . ($following ? ' is-following' : '') . '">';
            echo esc_html($following ? I18n::t('following') : I18n::t('follow'));
            echo '</button></form>';
        }
        echo ' <span class="art-coa-follow-count">' . esc_html((string) $count) . ' '
            . esc_html(I18n::t('followers')) . '</span>';
        echo '</div>';
        return (string) ob_get_clean();
    }

    public static function button_shortcode($atts = []): string
    {
        $atts = shortcode_atts(['type' => 'artist', 'id' => 0], $atts, 'art_coa_follow_button');
        return self::button_html(sanitize_key($atts['type']), absint($atts['id']));
    }

    public static function dashboard_html(int $user_id = 0): string
    {
        $user_id = $user_id ?: get_current_user_id();
        if (!$user_id) {
            return '';
        }
        $data = self::for_user($user_id);
        ob_start();
        echo '<div class="art-coa-my-follows">';
        echo '<h3>' . esc_html(I18n::t('my_favorites')) . '</h3>';
        if (!$data['artists'] && !$data['galleries']) {
            echo '<p class="art-coa-empty">' . esc_html(I18n::t('no_follows')) . '</p>';
            echo '</div>';
            return (string) ob_get_clean();
        }
        if ($data['artists']) {
            echo '<h4>' . esc_html(I18n::t('artists')) . '</h4><ul class="art-coa-link-list">';
            foreach ($data['artists'] as $a) {
                $name = $a->display_name ?: trim($a->first_name . ' ' . $a->last_name);
                echo '<li><a href="' . esc_url(ContentSync::artist_url((string) $a->slug)) . '">' . esc_html($name) . '</a> ';
                echo self::button_html('artist', (int) $a->id);
                echo '</li>';
            }
            echo '</ul>';
        }
        if ($data['galleries']) {
            echo '<h4>' . esc_html(I18n::t('galleries')) . '</h4><ul class="art-coa-link-list">';
            foreach ($data['galleries'] as $g) {
                echo '<li><a href="' . esc_url(home_url('/art-coa/gallery/' . $g->slug)) . '">' . esc_html($g->name) . '</a> ';
                echo self::button_html('gallery', (int) $g->id);
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    public static function dashboard_shortcode($atts = []): string
    {
        return self::dashboard_html();
    }

    public static function handle(): void
    {
        if (empty($_POST['art_coa_follow']) || !is_user_logged_in()) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field((string) ($_POST['_wpnonce'] ?? '')), 'art_coa_follow')) {
            return;
        }
        $type = self::normalize_type(sanitize_key(Security::input_scalar($_POST, 'entity_type')));
        $id = absint($_POST['entity_id'] ?? 0);
        $action = sanitize_key(Security::input_scalar($_POST, 'follow_action', 'follow'));
        $user_id = get_current_user_id();
        if (!$type || !$id) {
            return;
        }
        if ($action === 'unfollow') {
            self::unfollow($user_id, $type, $id);
        } else {
            self::follow($user_id, $type, $id);
        }
        wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/member-dashboard/'));
        exit;
    }

    private static function normalize_type(string $type): ?string
    {
        $type = sanitize_key($type);
        return in_array($type, ['artist', 'gallery'], true) ? $type : null;
    }
}
