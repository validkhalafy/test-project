<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Activator
{
    public static function activate(): void
    {
        require_once WP_ART_COA_DIR . 'includes/class-database.php';
        require_once WP_ART_COA_DIR . 'includes/class-roles.php';
        require_once WP_ART_COA_DIR . 'includes/class-profiles.php';
        require_once WP_ART_COA_DIR . 'includes/class-registration.php';

        Database::create_tables();
        Roles::register_roles();
        self::seed_options();
        Profiles::rewrites();
        if (class_exists(Registration::class)) {
            Registration::rewrites();
        }
        require_once WP_ART_COA_DIR . 'includes/class-portfolio.php';
        Portfolio::rewrites();
        require_once WP_ART_COA_DIR . 'includes/class-certificate-template.php';
        CertificateTemplate::maybe_migrate_gallery_columns();
        require_once WP_ART_COA_DIR . 'includes/class-sitemap.php';
        Sitemap::rewrites();
        require_once WP_ART_COA_DIR . 'includes/class-shop.php';
        Shop::maybe_migrate();
        Shop::rewrites();
        require_once WP_ART_COA_DIR . 'includes/class-secondary-market.php';
        SecondaryMarket::maybe_migrate();
        SecondaryMarket::rewrites();
        require_once WP_ART_COA_DIR . 'includes/class-auction.php';
        Auction::maybe_migrate();
        Auction::rewrites();
        require_once WP_ART_COA_DIR . 'includes/class-calls.php';
        Calls::maybe_migrate();
        Calls::rewrites();
        require_once WP_ART_COA_DIR . 'includes/class-artist-portfolio.php';
        ArtistPortfolio::maybe_migrate();
        require_once WP_ART_COA_DIR . 'includes/class-follows.php';
        Follows::maybe_migrate();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    private static function seed_options(): void
    {
        update_option('wp_art_coa_version', WP_ART_COA_VERSION);
        if (!get_option('wp_art_coa_settings')) {
            update_option('wp_art_coa_settings', [
                'language'     => 'fa',
                'jalali_dates' => 1,
                'enable_seo'   => 1,
                'cache_ttl'    => 180,
            ]);
        }

        $pages = [
            'verify' => [
                'title'   => 'تأیید گواهی اصالت',
                'content' => '[art_coa_verify]',
                'option'  => 'wp_art_coa_page_verify',
            ],
            'register' => [
                'title'   => 'ثبت‌نام اعضا',
                'content' => '[art_coa_register]',
                'option'  => 'wp_art_coa_page_register',
            ],
            'member' => [
                'title'   => 'داشبورد اعضا',
                'content' => '[art_coa_member_dashboard]',
                'option'  => 'wp_art_coa_page_member',
            ],
        ];

        foreach ($pages as $page) {
            // A stored ID that points to a deleted/trashed page must not block re-creation,
            // otherwise every generated link ends in a 404.
            $stored = absint(get_option($page['option']));
            if ($stored) {
                $existing = get_post($stored);
                if ($existing && 'page' === $existing->post_type && 'trash' !== $existing->post_status) {
                    continue;
                }
                delete_option($page['option']);
            }
            // Reuse a published page that already carries the shortcode (e.g. rebuilt in Elementor).
            $found = get_posts([
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                's'              => $page['content'],
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);
            if (!empty($found)) {
                update_option($page['option'], (int) $found[0]);
                continue;
            }
            if (!get_option($page['option'])) {
                $id = wp_insert_post([
                    'post_title'   => $page['title'],
                    'post_content' => $page['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                ]);
                if ($id && !is_wp_error($id)) {
                    update_option($page['option'], $id);
                }
            }
        }
    }
}
