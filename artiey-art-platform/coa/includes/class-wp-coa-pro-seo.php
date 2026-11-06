<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Technical SEO and performance helpers for public artist and collector profiles.
 * Does not replace Yoast, Rank Math, SEOPress or AIOSEO; it integrates with them.
 */
final class WP_COA_Pro_SEO {
    const REWRITE_VERSION = '2';
    const SITEMAP_PAGE_SIZE = 1000;
    const CACHE_TTL = 300;

    private static $requested_profile_loaded = false;
    private static $requested_profile = null;
    private static $canonical_artist_url_cache = array();

    public static function init() {
        add_action('init', array(__CLASS__, 'register_rewrite_rules'), 8);
        add_action('init', array(__CLASS__, 'maybe_flush_rewrite_rules'), 99);
        add_filter('query_vars', array(__CLASS__, 'register_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'maybe_serve_sitemap'), 0);
        add_action('template_redirect', array(__CLASS__, 'redirect_legacy_profile_url_or_404'), 0);
        add_filter('redirect_canonical', array(__CLASS__, 'prevent_profile_canonical_redirect'), 10, 2);
        add_filter('pre_get_document_title', array(__CLASS__, 'filter_document_title'), 99);
        add_action('wp_head', array(__CLASS__, 'output_fallback_metadata'), 3);
        add_action('wp_head', array(__CLASS__, 'output_profile_schema'), 25);
        add_filter('wp_robots', array(__CLASS__, 'filter_robots'));
        add_filter('robots_txt', array(__CLASS__, 'append_sitemap_to_robots'), 20, 2);

        // Yoast SEO.
        add_filter('wpseo_title', array(__CLASS__, 'filter_document_title'), 20);
        add_filter('wpseo_metadesc', array(__CLASS__, 'filter_description'), 20);
        add_filter('wpseo_canonical', array(__CLASS__, 'filter_canonical'), 20);
        add_filter('wpseo_opengraph_title', array(__CLASS__, 'filter_document_title'), 20);
        add_filter('wpseo_opengraph_desc', array(__CLASS__, 'filter_description'), 20);
        add_filter('wpseo_opengraph_image', array(__CLASS__, 'filter_image'), 20);
        add_filter('wpseo_twitter_title', array(__CLASS__, 'filter_document_title'), 20);
        add_filter('wpseo_twitter_description', array(__CLASS__, 'filter_description'), 20);

        // Rank Math.
        add_filter('rank_math/frontend/title', array(__CLASS__, 'filter_document_title'), 20);
        add_filter('rank_math/frontend/description', array(__CLASS__, 'filter_description'), 20);
        add_filter('rank_math/frontend/canonical', array(__CLASS__, 'filter_canonical'), 20);
        add_filter('rank_math/opengraph/facebook/title', array(__CLASS__, 'filter_document_title'), 20);
        add_filter('rank_math/opengraph/facebook/description', array(__CLASS__, 'filter_description'), 20);
        add_filter('rank_math/opengraph/facebook/image', array(__CLASS__, 'filter_image'), 20);
        add_filter('rank_math/opengraph/twitter/title', array(__CLASS__, 'filter_document_title'), 20);
        add_filter('rank_math/opengraph/twitter/description', array(__CLASS__, 'filter_description'), 20);

        // SEOPress.
        add_filter('seopress_titles_title', array(__CLASS__, 'filter_document_title'), 20);
        add_filter('seopress_titles_desc', array(__CLASS__, 'filter_description'), 20);
        add_filter('seopress_titles_canonical', array(__CLASS__, 'filter_canonical'), 20);
        add_filter('seopress_social_og_title', array(__CLASS__, 'filter_document_title'), 20);
        add_filter('seopress_social_og_desc', array(__CLASS__, 'filter_description'), 20);
        add_filter('seopress_social_og_img', array(__CLASS__, 'filter_image'), 20);

        // AIOSEO.
        add_filter('aioseo_title', array(__CLASS__, 'filter_document_title'), 20);
        add_filter('aioseo_description', array(__CLASS__, 'filter_description'), 20);
        add_filter('aioseo_canonical_url', array(__CLASS__, 'filter_canonical'), 20);
        add_filter('aioseo_og_title', array(__CLASS__, 'filter_document_title'), 20);
        add_filter('aioseo_og_description', array(__CLASS__, 'filter_description'), 20);
        add_filter('aioseo_og_image', array(__CLASS__, 'filter_image'), 20);
    }

    public static function activate() {
        self::register_rewrite_rules();
        flush_rewrite_rules(false);
        update_option('wp_coa_pro_rewrite_version', self::REWRITE_VERSION, false);
    }

    public static function register_query_vars($vars) {
        foreach (array('coa_profile_slug', 'wp_coa_pro_sitemap', 'wp_coa_pro_sitemap_page', 'wp_coa_verify_code') as $var) {
            if (!in_array($var, $vars, true)) {
                $vars[] = $var;
            }
        }
        return $vars;
    }

    public static function register_rewrite_rules() {
        add_rewrite_rule('^coa-verify/([A-Za-z0-9-]+)/?$', 'index.php?wp_coa_verify_code=$matches[1]', 'top');
        add_rewrite_rule('^coa-pro-sitemap\.xml/?$', 'index.php?wp_coa_pro_sitemap=index', 'top');
        add_rewrite_rule('^coa-pro-sitemap-profiles-([0-9]+)\.xml/?$', 'index.php?wp_coa_pro_sitemap=profiles&wp_coa_pro_sitemap_page=$matches[1]', 'top');

        $page_path = self::profile_page_path();
        if ($page_path !== '') {
            // Keep the old default route as an alias if the managed page slug is later customized.
            $paths = array_unique(array_filter(array(trim($page_path, '/'), 'art-person-profile')));
            foreach ($paths as $route_path) {
                $regex = '^' . preg_quote($route_path, '#') . '/([^/]+)/?$';
                $target = 'index.php?pagename=' . trim($page_path, '/') . '&coa_profile_slug=$matches[1]';
                add_rewrite_rule($regex, $target, 'top');
            }
        }
    }

    public static function maybe_flush_rewrite_rules() {
        if ((string) get_option('wp_coa_pro_rewrite_version', '') === self::REWRITE_VERSION) {
            return;
        }
        // The bundled platform performs one combined flush after every route provider
        // has registered. Record this module's schema without issuing a second flush.
        if (class_exists('Artiey_AET_Slug') && Artiey_AET_Slug::routes_refreshed_this_request()) {
            update_option('wp_coa_pro_rewrite_version', self::REWRITE_VERSION, false);
            return;
        }
        // Standalone COA Pro installations still refresh their own rules.
        flush_rewrite_rules(false);
        update_option('wp_coa_pro_rewrite_version', self::REWRITE_VERSION, false);
    }

    private static function profile_page_path() {
        $ids = get_option('wp_coa_pro_managed_page_ids', array());
        $page_id = is_array($ids) && !empty($ids['profile_view']) ? absint($ids['profile_view']) : 0;
        if ($page_id && get_post($page_id) && get_post_type($page_id) === 'page') {
            $path = get_page_uri($page_id);
            if (is_string($path) && $path !== '') {
                return trim($path, '/');
            }
        }
        return 'art-person-profile';
    }

    public static function profile_url($profile) {
        if (!$profile || empty($profile->slug)) {
            return '';
        }

        // Every published artist profile now shares the canonical /artists/{slug}/ namespace.
        // The URL dispatcher serves the WordPress post first and the published COA profile
        // as a safe compatibility fallback until older records are fully adopted.
        if (isset($profile->profile_type) && $profile->profile_type === 'artist' && (string) get_option('permalink_structure', '') !== '') {
            $slug = sanitize_title((string) $profile->slug);
            if ($slug !== '') {
                if (!array_key_exists($slug, self::$canonical_artist_url_cache)) {
                    $url = class_exists('\\WPArtCOA\\ContentSync')
                        ? \WPArtCOA\ContentSync::artist_url($slug)
                        : user_trailingslashit(home_url('/artists/' . rawurlencode($slug)), 'single');
                    self::$canonical_artist_url_cache[$slug] = is_string($url) ? $url : '';
                }
                if (self::$canonical_artist_url_cache[$slug] !== '') {
                    return self::$canonical_artist_url_cache[$slug];
                }
            }
        }

        $base = class_exists('WP_COA_Pro_Page_Integration') ? WP_COA_Pro_Page_Integration::get_managed_page_url('profile_view') : '';
        if ($base && (string) get_option('permalink_structure', '') !== '') {
            return user_trailingslashit(untrailingslashit($base) . '/' . ltrim((string) $profile->slug, '/'), 'single');
        }
        // Plain-permalink sites cannot reliably route a nested path; preserve the working query URL.
        return add_query_arg(array('coa_profile_type' => isset($profile->profile_type) ? $profile->profile_type : '', 'coa_profile_slug' => $profile->slug), $base ? $base : home_url('/'));
    }

    private static function requested_slug() {
        $slug = get_query_var('coa_profile_slug');
        if (!$slug && isset($_GET['coa_profile_slug']) && is_scalar($_GET['coa_profile_slug'])) {
            $slug = wp_unslash($_GET['coa_profile_slug']);
        }
        return is_scalar($slug) ? sanitize_title((string) $slug) : '';
    }

    private static function is_profile_request() {
        return self::requested_slug() !== '';
    }

    private static function requested_profile() {
        if (self::$requested_profile_loaded) {
            return self::$requested_profile;
        }
        self::$requested_profile_loaded = true;
        $slug = self::requested_slug();
        if ($slug !== '' && class_exists('WP_COA_Pro_Person_Profiles')) {
            self::$requested_profile = WP_COA_Pro_Person_Profiles::get_by_slug($slug);
        }
        return self::$requested_profile;
    }

    public static function prevent_profile_canonical_redirect($redirect_url, $requested_url) {
        return self::is_profile_request() ? false : $redirect_url;
    }

    public static function redirect_legacy_profile_url_or_404() {
        if (!self::is_profile_request()) {
            return;
        }
        // WordPress core canonicalizes the request to the base page URL; profile pages have their own canonical.
        remove_action('wp_head', 'rel_canonical');
        $profile = self::requested_profile();
        if (!$profile) {
            // Some installations used the old COA profile path for WordPress artist
            // posts before the unified archive existed. Redirect only an exact published
            // CPT slug match; otherwise retain the normal 404 and never guess by title.
            $legacy_slug = self::requested_slug();
            if ($legacy_slug !== '' && (string) get_option('permalink_structure', '') !== '' && function_exists('get_page_by_path')) {
                $candidate = get_page_by_path($legacy_slug, OBJECT, 'artiey_artist');
                if ($candidate && isset($candidate->ID, $candidate->post_type, $candidate->post_status, $candidate->post_name)
                    && $candidate->post_type === 'artiey_artist'
                    && $candidate->post_status === 'publish'
                    && $candidate->post_name === $legacy_slug) {
                    $target = get_permalink($candidate);
                    if ($target) { wp_safe_redirect($target, 301, 'WP COA Pro'); exit; }
                }
            }
            global $wp_query;
            if (is_object($wp_query) && method_exists($wp_query, 'set_404')) {
                $wp_query->set_404();
            }
            status_header(404);
            nocache_headers();
            return;
        }

        $canonical = self::profile_url($profile);
        if (!$canonical || strpos($canonical, 'coa_profile_slug=') !== false) {
            return;
        }
        $should_redirect = isset($_GET['coa_profile_slug']);
        if (!$should_redirect && (string) get_option('permalink_structure', '') !== '' && isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
            $request_path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
            $canonical_path = wp_parse_url($canonical, PHP_URL_PATH);
            if (is_string($request_path) && is_string($canonical_path)) {
                $should_redirect = untrailingslashit($request_path) !== untrailingslashit($canonical_path);
            }
        }
        if ($should_redirect) {
            wp_safe_redirect($canonical, 301, 'WP COA Pro');
            exit;
        }
    }

    public static function filter_document_title($original = '') {
        $profile = self::requested_profile();
        if (!$profile) {
            return $original;
        }
        $custom = isset($profile->seo_title) ? trim((string) $profile->seo_title) : '';
        if ($custom !== '') {
            $title = self::unicode_limit(wp_strip_all_tags($custom), 70);
            return apply_filters('wp_coa_pro_profile_seo_title', $title, $profile);
        }
        $locale = strtolower((string) (function_exists('determine_locale') ? determine_locale() : get_locale()));
        $english = strpos($locale, 'en') === 0;
        $name = $english && !empty($profile->display_name_en) ? $profile->display_name_en : $profile->display_name;
        if ($name === '' && !empty($profile->display_name_en)) {
            $name = $profile->display_name_en;
        }
        $kind = $profile->profile_type === 'artist'
            ? ($english ? 'Artist Profile' : 'شناسنامه هنرمند')
            : ($english ? 'Collector & Art Collection' : 'شناسنامه مجموعه‌دار و مجموعه هنری');
        $site_name = trim((string) get_bloginfo('name'));
        $title = trim($name . ' | ' . $kind . ($site_name !== '' ? ' | ' . $site_name : ''));
        return apply_filters('wp_coa_pro_profile_seo_title', self::unicode_limit($title, 70), $profile);
    }

    public static function filter_description($original = '') {
        $profile = self::requested_profile();
        if (!$profile) {
            return $original;
        }
        $custom = isset($profile->seo_description) ? trim((string) $profile->seo_description) : '';
        if ($custom !== '') {
            $description = self::truncate_description($custom, 160);
        } else {
            $locale = strtolower((string) (function_exists('determine_locale') ? determine_locale() : get_locale()));
            $english = strpos($locale, 'en') === 0;
            $tagline = $english && !empty($profile->tagline_en) ? $profile->tagline_en : $profile->tagline;
            $biography = $english && !empty($profile->biography_en) ? $profile->biography_en : $profile->biography;
            $source = trim((string) $tagline);
            if ($source === '') {
                $source = (string) $biography;
            } elseif (trim((string) $biography) !== '') {
                $source .= ' — ' . wp_strip_all_tags((string) $biography);
            }
            if (trim($source) === '') {
                $name = !empty($profile->display_name) ? $profile->display_name : $profile->display_name_en;
                $source = $name . ($profile->profile_type === 'artist' ? '؛ شناسنامه و سوابق هنری.' : '؛ شناسنامه مجموعه هنری و سوابق مرتبط.');
            }
            $description = self::truncate_description($source, 160);
        }
        return apply_filters('wp_coa_pro_profile_seo_description', $description, $profile);
    }

    public static function filter_canonical($original = '') {
        $profile = self::requested_profile();
        if (!$profile) {
            return $original;
        }
        $canonical = self::profile_url($profile);
        return $canonical ? apply_filters('wp_coa_pro_profile_canonical', $canonical, $profile) : $original;
    }

    public static function filter_image($original = '') {
        $profile = self::requested_profile();
        if (!$profile) {
            return $original;
        }
        $image = !empty($profile->profile_image_url) ? $profile->profile_image_url : (!empty($profile->cover_image_url) ? $profile->cover_image_url : '');
        $image = $image ? esc_url_raw($image, array('http', 'https')) : '';
        return $image !== '' ? apply_filters('wp_coa_pro_profile_social_image', $image, $profile) : $original;
    }

    public static function filter_robots($robots) {
        if (self::is_profile_request() && !self::requested_profile()) {
            $robots['noindex'] = true;
            $robots['follow'] = true;
        }
        return $robots;
    }

    public static function output_fallback_metadata() {
        $profile = self::requested_profile();
        if (!$profile || self::has_seo_plugin()) {
            return;
        }
        $canonical = self::profile_url($profile);
        $description = self::filter_description('');
        $title = self::filter_document_title('');
        $image = self::filter_image('');
        if ($description !== '') {
            echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        }
        if ($canonical !== '') {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
        }
        echo '<meta property="og:type" content="profile" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        if ($canonical !== '') {
            echo '<meta property="og:url" content="' . esc_url($canonical) . '" />' . "\n";
        }
        if ($image !== '') {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        }
    }

    public static function output_profile_schema() {
        $profile = self::requested_profile();
        if (!$profile) {
            return;
        }
        $url = self::profile_url($profile);
        $description = self::filter_description('');
        $locale = strtolower((string) (function_exists('determine_locale') ? determine_locale() : get_locale()));
        $english = strpos($locale, 'en') === 0;
        $name = $english && !empty($profile->display_name_en) ? $profile->display_name_en : $profile->display_name;
        if ($name === '') {
            $name = $profile->display_name_en;
        }
        $image = !empty($profile->profile_image_url) ? esc_url_raw($profile->profile_image_url, array('http', 'https')) : (!empty($profile->cover_image_url) ? esc_url_raw($profile->cover_image_url, array('http', 'https')) : '');
        $same_as = array();
        foreach (array('website_url', 'portfolio_url', 'instagram_url', 'linkedin_url', 'facebook_url', 'other_social_url') as $field) {
            if (!empty($profile->{$field})) {
                $valid = esc_url_raw((string) $profile->{$field}, array('http', 'https'));
                if ($valid !== '') {
                    $same_as[] = $valid;
                }
            }
        }
        $is_person = $profile->profile_type === 'artist' || (isset($profile->entity_kind) && $profile->entity_kind === 'person');
        $entity = array(
            '@type' => $is_person ? 'Person' : 'Organization',
            '@id' => $url . '#entity',
            'name' => $name,
            'url' => $url,
            'description' => $description,
        );
        if (!empty($profile->display_name_en) && $profile->display_name_en !== $name) {
            $entity['alternateName'] = $profile->display_name_en;
        }
        if ($image !== '') {
            $entity['image'] = $image;
        }
        if ($same_as) {
            $entity['sameAs'] = array_values(array_unique($same_as));
        }
        if ($is_person && !empty($profile->birth_year) && preg_match('/^[0-9]{4}$/', (string) $profile->birth_year)) {
            $entity['birthDate'] = (string) $profile->birth_year;
        }
        if (!$is_person && !empty($profile->founded_year) && preg_match('/^[0-9]{4}$/', (string) $profile->founded_year)) {
            $entity['foundingDate'] = (string) $profile->founded_year;
        }
        if (!empty($profile->show_location_public) && (!empty($profile->city) || !empty($profile->country))) {
            $address = array('@type' => 'PostalAddress');
            if (!empty($profile->city)) { $address['addressLocality'] = (string) $profile->city; }
            if (!empty($profile->country)) { $address['addressCountry'] = (string) $profile->country; }
            $entity['address'] = $address;
        }
        $profile_page = array(
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            '@id' => $url . '#profilepage',
            'url' => $url,
            'name' => self::filter_document_title(''),
            'description' => $description,
            'inLanguage' => $english ? 'en' : 'fa',
            'mainEntity' => $entity,
        );
        if (!empty($profile->updated_at)) {
            $modified = strtotime((string) $profile->updated_at . ' UTC');
            if ($modified) {
                $profile_page['dateModified'] = gmdate('c', $modified);
            }
        }
        $directory_key = $profile->profile_type === 'artist' ? 'artist_directory' : 'collector_directory';
        $directory_url = class_exists('WP_COA_Pro_Page_Integration') ? WP_COA_Pro_Page_Integration::get_managed_page_url($directory_key) : '';
        $breadcrumb_items = array(
            array('@type' => 'ListItem', 'position' => 1, 'name' => (string) get_bloginfo('name'), 'item' => home_url('/')),
        );
        if ($directory_url !== '') {
            $breadcrumb_items[] = array('@type' => 'ListItem', 'position' => 2, 'name' => $profile->profile_type === 'artist' ? ($english ? 'Artists' : 'هنرمندان') : ($english ? 'Collectors and Collections' : 'کلکتورها و مجموعه‌های هنری'), 'item' => $directory_url);
            $breadcrumb_items[] = array('@type' => 'ListItem', 'position' => 3, 'name' => $name, 'item' => $url);
        } else {
            $breadcrumb_items[] = array('@type' => 'ListItem', 'position' => 2, 'name' => $name, 'item' => $url);
        }
        $profile_page['breadcrumb'] = array('@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumb_items);
        $json = wp_json_encode($profile_page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (is_string($json) && $json !== '') {
            echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
        }
    }

    public static function append_sitemap_to_robots($output, $public) {
        if (!$public || !is_string($output)) {
            return $output;
        }
        $sitemap_url = self::sitemap_index_url();
        if (stripos($output, $sitemap_url) === false) {
            $output = rtrim($output) . "\nSitemap: " . esc_url_raw($sitemap_url) . "\n";
        }
        return $output;
    }

    public static function maybe_serve_sitemap() {
        $kind = sanitize_key((string) get_query_var('wp_coa_pro_sitemap'));
        if ($kind === '') {
            return;
        }
        if ($kind === 'index') {
            $xml = self::build_sitemap_index();
        } elseif ($kind === 'profiles') {
            $page = max(1, absint(get_query_var('wp_coa_pro_sitemap_page')));
            $xml = self::build_profiles_sitemap($page);
        } else {
            status_header(404);
            exit;
        }
        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');
        header('Cache-Control: public, max-age=900');
        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is built with escaped values.
        exit;
    }

    private static function sitemap_index_url() {
        if ((string) get_option('permalink_structure', '') === '') {
            return add_query_arg('wp_coa_pro_sitemap', 'index', home_url('/'));
        }
        return home_url('/coa-pro-sitemap.xml');
    }

    private static function sitemap_page_url($page) {
        $page = max(1, absint($page));
        if ((string) get_option('permalink_structure', '') === '') {
            return add_query_arg(array('wp_coa_pro_sitemap' => 'profiles', 'wp_coa_pro_sitemap_page' => $page), home_url('/'));
        }
        return home_url('/coa-pro-sitemap-profiles-' . $page . '.xml');
    }

    private static function build_sitemap_index() {
        $generation = self::cache_generation();
        $cache_key = 'wp_coa_smi_' . $generation;
        $cached = get_transient($cache_key);
        if (is_string($cached)) {
            return $cached;
        }
        $count = class_exists('WP_COA_Pro_Person_Profiles') ? WP_COA_Pro_Person_Profiles::count_profiles('', 'published') : 0;
        $pages = max(0, (int) ceil($count / self::SITEMAP_PAGE_SIZE));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        for ($page = 1; $page <= $pages; $page++) {
            $url = self::sitemap_page_url($page);
            $xml .= '<sitemap><loc>' . esc_xml($url) . '</loc></sitemap>' . "\n";
        }
        $xml .= '</sitemapindex>';
        set_transient($cache_key, $xml, HOUR_IN_SECONDS);
        return $xml;
    }

    private static function build_profiles_sitemap($page) {
        $generation = self::cache_generation();
        $cache_key = 'wp_coa_smp_' . $generation . '_' . absint($page);
        $cached = get_transient($cache_key);
        if (is_string($cached)) {
            return $cached;
        }
        global $wpdb;
        $table = WP_COA_Pro_Person_Profiles::table_name();
        $offset = ($page - 1) * self::SITEMAP_PAGE_SIZE;
        $records = $wpdb->get_results($wpdb->prepare("SELECT slug, profile_type, updated_at FROM {$table} WHERE status = 'published' ORDER BY id ASC LIMIT %d OFFSET %d", self::SITEMAP_PAGE_SIZE, $offset));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ((array) $records as $record) {
            $url = self::profile_url($record);
            if (!$url) { continue; }
            $xml .= '<url><loc>' . esc_xml($url) . '</loc>';
            if (!empty($record->updated_at)) {
                $timestamp = strtotime((string) $record->updated_at . ' UTC');
                if ($timestamp) {
                    $xml .= '<lastmod>' . esc_xml(gmdate('c', $timestamp)) . '</lastmod>';
                }
            }
            $xml .= '</url>' . "\n";
        }
        $xml .= '</urlset>';
        set_transient($cache_key, $xml, HOUR_IN_SECONDS);
        return $xml;
    }

    private static function has_seo_plugin() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Options') || defined('RANK_MATH_VERSION') || class_exists('RankMath\\Helper') || defined('SEOPRESS_VERSION') || function_exists('aioseo') || defined('AIOSEO_VERSION');
    }

    private static function truncate_description($text, $limit) {
        $text = html_entity_decode(wp_strip_all_tags((string) $text), ENT_QUOTES, get_bloginfo('charset') ? get_bloginfo('charset') : 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text));
        return self::unicode_limit((string) $text, max(1, absint($limit)));
    }

    private static function unicode_limit($text, $limit) {
        $text = (string) $text;
        $limit = max(1, absint($limit));
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $limit, 'UTF-8');
        }
        if (function_exists('iconv_substr')) {
            $result = iconv_substr($text, 0, $limit, 'UTF-8');
            if ($result !== false) { return $result; }
        }
        return substr($text, 0, $limit);
    }

    private static function cache_generation() {
        return max(1, absint(get_option('wp_coa_pro_profile_cache_generation', 1)));
    }
}
