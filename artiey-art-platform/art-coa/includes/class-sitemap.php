<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Sitemap
{
    public static function init(): void
    {
        add_action('init', [self::class, 'rewrites']);
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'art_coa_sitemap';
            return $vars;
        });
        add_action('template_redirect', [self::class, 'render']);
        add_shortcode('art_coa_sitemap', [self::class, 'html_shortcode']);
    }

    public static function rewrites(): void
    {
        add_rewrite_rule('^art-coa-sitemap\.xml$', 'index.php?art_coa_sitemap=xml', 'top');
        add_rewrite_rule('^art-coa/site-map/?$', 'index.php?art_coa_sitemap=html', 'top');
    }

    public static function render(): void
    {
        $mode = get_query_var('art_coa_sitemap');
        if (!$mode) {
            return;
        }
        if ($mode === 'xml') {
            self::output_xml();
            exit;
        }
        if ($mode === 'html') {
            status_header(200);
            get_header();
            echo '<div class="art-coa-profile-wrap" dir="' . (I18n::is_fa() ? 'rtl' : 'ltr') . '">';
            echo self::html_shortcode([]);
            echo '</div>';
            get_footer();
            exit;
        }
    }

    public static function urls(): array
    {
        global $wpdb;
        $urls = [];

        $static = [

            '/art-coa/galleries' => I18n::t('galleries'),
            '/art-coa/exhibitions' => I18n::t('exhibitions'),
            '/art-coa/artworks' => I18n::t('artworks'),
            '/art-coa/portfolio' => I18n::t('portfolio'),
            '/art-coa/shop' => I18n::t('shop'),
            '/art-coa/secondary-market' => I18n::t('secondary_market'),
            '/art-coa/auctions' => I18n::t('auction_title'),
            '/art-coa/calls' => I18n::t('calls_title'),
            '/art-coa/member-dashboard' => I18n::t('member_dashboard'),
        ];
        foreach ($static as $path => $label) {
            $urls[] = ['loc' => home_url($path), 'label' => $label, 'group' => 'main'];
        }
        // The sole artist archive is the content-managed WordPress page at /artists/.
        $urls[] = ['loc' => ContentSync::artist_archive_url(), 'label' => I18n::t('artists'), 'group' => 'main'];

        $reg = get_option('wp_art_coa_page_register');
        if ($reg) {
            $urls[] = ['loc' => get_permalink($reg), 'label' => I18n::t('reg_page'), 'group' => 'main'];
        }
        $verify = get_option('wp_art_coa_page_verify');
        if ($verify) {
            $urls[] = ['loc' => get_permalink($verify), 'label' => I18n::t('verify_title'), 'group' => 'main'];
        }

        foreach ($wpdb->get_results('SELECT slug, display_name, first_name, last_name FROM ' . Database::table('artists') . " WHERE status='active' AND cpt_post_id IS NULL LIMIT 500") ?: [] as $r) {
            $name = $r->display_name ?: trim($r->first_name . ' ' . $r->last_name);
            $urls[] = ['loc' => ContentSync::artist_url((string) $r->slug), 'label' => $name, 'group' => 'artists'];
        }
        foreach ($wpdb->get_results('SELECT slug, name FROM ' . Database::table('galleries') . " WHERE status='active' AND cpt_post_id IS NULL LIMIT 500") ?: [] as $r) {
            $urls[] = ['loc' => home_url('/art-coa/gallery/' . $r->slug), 'label' => $r->name, 'group' => 'galleries'];
        }
        foreach ($wpdb->get_results('SELECT slug, title FROM ' . Database::table('exhibitions') . ' WHERE status IN (\'published\',\'active\') AND cpt_post_id IS NULL LIMIT 500') ?: [] as $r) {
            $urls[] = ['loc' => home_url('/art-coa/exhibition/' . $r->slug), 'label' => $r->title, 'group' => 'exhibitions'];
        }
        foreach ($wpdb->get_results('SELECT slug, title FROM ' . Database::table('artworks') . " WHERE status IN ('approved','published','active','sold') AND cpt_post_id IS NULL LIMIT 1000") ?: [] as $r) {
            $urls[] = ['loc' => home_url('/art-coa/artwork/' . $r->slug), 'label' => $r->title, 'group' => 'artworks'];
        }
        foreach ($wpdb->get_results('SELECT slug, display_name FROM ' . Database::table('collectors') . ' WHERE is_public = 1 AND status=\'active\' LIMIT 200') ?: [] as $r) {
            $urls[] = ['loc' => home_url('/art-coa/collector/' . $r->slug), 'label' => $r->display_name, 'group' => 'collectors'];
        }
        foreach ($wpdb->get_results("SELECT serial_number FROM " . Database::table('certificates') . " WHERE status='active' LIMIT 500") ?: [] as $r) {
            $urls[] = ['loc' => home_url('/art-coa/certificate/' . rawurlencode($r->serial_number)), 'label' => $r->serial_number, 'group' => 'certificates'];
        }

        return $urls;
    }

    private static function output_xml(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (self::urls() as $u) {
            echo '  <url><loc>' . esc_url($u['loc']) . '</loc><changefreq>weekly</changefreq></url>' . "\n";
        }
        echo '</urlset>';
    }

    public static function html_shortcode($atts = []): string
    {
        $urls = self::urls();
        $groups = [
            'main' => I18n::is_fa() ? 'صفحات اصلی' : 'Main pages',
            'artists' => I18n::t('artists'),
            'galleries' => I18n::t('galleries'),
            'exhibitions' => I18n::t('exhibitions'),
            'artworks' => I18n::t('artworks'),
            'collectors' => I18n::t('collectors'),
            'certificates' => I18n::t('certificates'),
        ];
        ob_start();
        echo '<div class="art-coa-sitemap"><h1>' . esc_html(I18n::is_fa() ? 'نقشه سایت' : 'Sitemap') . '</h1>';
        foreach ($groups as $key => $title) {
            $items = array_filter($urls, static fn($u) => ($u['group'] ?? '') === $key);
            if (!$items) {
                continue;
            }
            echo '<section class="art-coa-section"><h2>' . esc_html($title) . '</h2><ul class="art-coa-link-list">';
            foreach ($items as $u) {
                echo '<li><a href="' . esc_url($u['loc']) . '">' . esc_html($u['label']) . '</a></li>';
            }
            echo '</ul></section>';
        }
        echo '<p class="meta"><a href="' . esc_url(home_url('/art-coa-sitemap.xml')) . '">XML Sitemap</a></p></div>';
        return (string) ob_get_clean();
    }
}
