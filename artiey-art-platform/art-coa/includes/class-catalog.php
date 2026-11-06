<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Artwork;

defined('ABSPATH') || exit;

/**
 * Dynamic catalogs (artworks / artists / galleries / exhibitions):
 *  - one SQL layer + one PHP card renderer, shared by the server-rendered first page
 *    (SEO, no-JS) and the REST endpoint used by assets/js/frontend-dynamic.js
 *  - filters, live search, sorting, "load more", URL state
 *  - global live-search endpoint for the directory search box
 */
final class Catalog
{
    public const TYPES = ['artworks', 'artists', 'galleries', 'exhibitions'];

    /** GET parameter prefix (avoids clashing with WordPress query vars such as "year" or "s") */
    public const PREFIX = 'ac_';

    public static function init(): void
    {
        add_shortcode('art_coa_catalog', [self::class, 'shortcode']);
        add_shortcode('art_coa_search', [self::class, 'search_shortcode']);
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /* ==================================================================
     * REST
     * ================================================================ */

    public static function register_routes(): void
    {
        register_rest_route('art-coa/v1', '/catalog/(?P<type>artworks|artists|galleries|exhibitions)', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'rest_list'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('art-coa/v1', '/search', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'rest_search'],
            'permission_callback' => '__return_true',
            'args'                => ['q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field']],
        ]);
    }

    public static function rest_list(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!\WPArtCOA\Security::throttle('cat', 120)) {
            return \WPArtCOA\Security::throttled_response();
        }
        $type = (string) $request->get_param('type');
        $args = self::normalize($type, $request->get_params());
        $res  = self::query($type, $args);

        $resp = rest_ensure_response([
            'html'     => self::cards_html($type, $res['items']),
            'total'    => $res['total'],
            'page'     => $res['page'],
            'pages'    => $res['pages'],
            'per_page' => $res['per_page'],
            'label'    => self::count_label($res['total']),
        ]);
        $resp->header('Cache-Control', 'no-cache');
        return $resp;
    }

    public static function rest_search(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!\WPArtCOA\Security::throttle('srch', 60)) {
            return \WPArtCOA\Security::throttled_response();
        }
        $q = trim((string) $request->get_param('q'));
        $q = \Artiey_AET_Text::substr($q, 0, 60);
        $length = \Artiey_AET_Text::length($q);
        if ($length < 2) {
            return rest_ensure_response(['results' => []]);
        }
        $out = [];
        foreach (self::TYPES as $type) {
            $res = self::query($type, self::normalize($type, ['q' => $q, 'per_page' => 4]));
            foreach ($res['items'] as $row) {
                $out[] = self::search_row($type, $row);
            }
        }
        return rest_ensure_response(['results' => $out]);
    }

    /** @return array{type:string,label:string,title:string,sub:string,url:string,thumb:string} */
    private static function search_row(string $type, object $row): array
    {
        switch ($type) {
            case 'artworks':
                return [
                    'type'  => $type, 'label' => I18n::t('artwork'),
                    'title' => (string) $row->title, 'sub' => self::artist_name($row),
                    'url'   => home_url('/art-coa/artwork/' . $row->slug), 'thumb' => Media::url($row, 'image', 'thumbnail'),
                ];
            case 'artists':
                return [
                    'type'  => $type, 'label' => I18n::t('artist'),
                    'title' => self::artist_name($row), 'sub' => '',
                    'url'   => self::artist_url((string) $row->slug), 'thumb' => Media::url($row, 'avatar', 'thumbnail'),
                ];
            case 'galleries':
                return [
                    'type'  => $type, 'label' => I18n::t('gallery'),
                    'title' => (string) $row->name, 'sub' => (string) ($row->city ?? ''),
                    'url'   => home_url('/art-coa/gallery/' . $row->slug), 'thumb' => Media::url($row, 'logo', 'thumbnail'),
                ];
            default:
                return [
                    'type'  => $type, 'label' => I18n::t('exhibition'),
                    'title' => (string) $row->title, 'sub' => (string) ($row->gallery_name ?? ''),
                    'url'   => home_url('/art-coa/exhibition/' . $row->slug), 'thumb' => Media::url($row, 'poster', 'thumbnail'),
                ];
        }
    }

    /* ==================================================================
     * Arguments
     * ================================================================ */

    /** @return array<string,mixed> */
    public static function normalize(string $type, array $raw): array
    {
        $query = trim(sanitize_text_field((string) ($raw['q'] ?? '')));
        $query = \Artiey_AET_Text::substr($query, 0, 80);
        $a = [
            'q'        => $query,
            'page'     => max(1, absint($raw['page'] ?? 1)),
            'per_page' => min(48, max(4, absint($raw['per_page'] ?? 12) ?: 12)),
            'sort'     => sanitize_key((string) ($raw['sort'] ?? '')),
        ];
        switch ($type) {
            case 'artworks':
                $a['artist']    = sanitize_title((string) ($raw['artist'] ?? ''));
                $a['gallery']   = sanitize_title((string) ($raw['gallery'] ?? ''));
                $a['medium']    = sanitize_text_field((string) ($raw['medium'] ?? ''));
                $a['year_from'] = absint($raw['year_from'] ?? 0);
                $a['year_to']   = absint($raw['year_to'] ?? 0);
                $a['for_sale']  = !empty($raw['for_sale']) ? 1 : 0;
                break;
            case 'galleries':
                $a['city'] = sanitize_text_field((string) ($raw['city'] ?? ''));
                break;
            case 'exhibitions':
                $t = sanitize_key((string) ($raw['event_type'] ?? ''));
                $a['event_type'] = in_array($t, ['exhibition', 'artfair', 'auction'], true) ? $t : '';
                $w = sanitize_key((string) ($raw['when'] ?? ''));
                $a['when']    = in_array($w, ['ongoing', 'upcoming', 'past'], true) ? $w : '';
                $a['gallery'] = sanitize_title((string) ($raw['gallery'] ?? ''));
                break;
        }
        return $a;
    }

    /** Read ac_* GET parameters. */
    private static function from_request(string $type): array
    {
        $raw = [];
        foreach ($_GET as $k => $v) { // phpcs:ignore WordPress.Security.NonceVerification
            if (is_string($k) && strncmp($k, self::PREFIX, strlen(self::PREFIX)) === 0 && is_scalar($v)) {
                $raw[substr($k, strlen(self::PREFIX))] = wp_unslash((string) $v);
            }
        }
        return $raw;
    }

    /* ==================================================================
     * Queries
     * ================================================================ */

    /**
     * @return array{items:object[],total:int,pages:int,page:int,per_page:int}
     */
    public static function query(string $type, array $a): array
    {
        if (!current_user_can('manage_options')) {
            $version = (int) get_option('wp_art_coa_catalog_cache_version', 1);
            $key = 'catalog_' . $version . '_' . md5((string) wp_json_encode([$type, $a]));
            return Cache::get($key, static function () use ($type, $a): array {
                return self::query_uncached($type, $a);
            }, 60);
        }
        return self::query_uncached($type, $a);
    }

    /** @return array{items:object[],total:int,pages:int,page:int,per_page:int} */
    private static function query_uncached(string $type, array $a): array
    {
        global $wpdb;

        $like = static fn(string $s): string => '%' . $wpdb->esc_like($s) . '%';
        $per  = (int) $a['per_page'];
        $page = (int) $a['page'];

        $select_params = [];
        $where = [];
        $params = [];

        switch ($type) {
            case 'artworks':
                $st = Artwork::public_statuses();
                $from = ' FROM ' . Database::table('artworks') . ' a
                          LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                          LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = a.gallery_id';
                $select = 'a.*, ar.display_name AS artist_name, ar.first_name, ar.last_name, ar.slug AS artist_slug,
                           g.name AS gallery_name, g.slug AS gallery_slug';
                $where[] = 'a.status IN (' . implode(',', array_fill(0, count($st), '%s')) . ')';
                array_push($params, ...$st);
                if ($a['q'] !== '') {
                    $where[] = '(a.title LIKE %s OR a.medium LIKE %s OR ar.display_name LIKE %s OR ar.first_name LIKE %s OR ar.last_name LIKE %s)';
                    array_push($params, ...array_fill(0, 5, $like($a['q'])));
                }
                if (!empty($a['artist']))  { $where[] = 'ar.slug = %s'; $params[] = $a['artist']; }
                if (!empty($a['gallery'])) { $where[] = 'g.slug = %s';  $params[] = $a['gallery']; }
                if (!empty($a['medium']))  { $where[] = 'a.medium = %s'; $params[] = $a['medium']; }
                if (!empty($a['year_from'])) { $where[] = 'a.year_created >= %d'; $params[] = (int) $a['year_from']; }
                if (!empty($a['year_to']))   { $where[] = 'a.year_created <= %d'; $params[] = (int) $a['year_to']; }
                if (!empty($a['for_sale']))  { $where[] = "a.for_sale = 1 AND a.status <> 'sold'"; }
                $order = [
                    'oldest'     => 'a.id ASC',
                    'title'      => 'a.title ASC',
                    'price_asc'  => 'a.price IS NULL, a.price ASC, a.id DESC',
                    'price_desc' => 'a.price IS NULL, a.price DESC, a.id DESC',
                    'year_desc'  => 'a.year_created IS NULL, a.year_created DESC, a.id DESC',
                ][$a['sort']] ?? 'a.id DESC';
                break;

            case 'artists':
                $st = Artwork::public_statuses();
                $from = ' FROM ' . Database::table('artists') . ' ar';
                $select = 'ar.*, (SELECT COUNT(*) FROM ' . Database::table('artworks') . ' a
                                  WHERE a.artist_id = ar.id AND a.status IN (' . implode(',', array_fill(0, count($st), '%s')) . ')) AS works_count';
                array_push($select_params, ...$st);
                $where[] = "ar.status = 'active'";
                if ($a['q'] !== '') {
                    $where[] = '(ar.display_name LIKE %s OR ar.first_name LIKE %s OR ar.last_name LIKE %s)';
                    array_push($params, ...array_fill(0, 3, $like($a['q'])));
                }
                $order = [
                    'newest' => 'ar.id DESC',
                    'works'  => 'works_count DESC, ar.display_name ASC',
                ][$a['sort']] ?? 'ar.display_name ASC, ar.id DESC';
                break;

            case 'galleries':
                $st = Artwork::public_statuses();
                $from = ' FROM ' . Database::table('galleries') . ' g';
                $select = 'g.*,
                    (SELECT COUNT(*) FROM ' . Database::table('exhibitions') . " e WHERE e.gallery_id = g.id AND e.status IN ('published','active')) AS exhibitions_count,
                    (SELECT COUNT(*) FROM " . Database::table('artworks') . ' a WHERE a.gallery_id = g.id AND a.status IN (' . implode(',', array_fill(0, count($st), '%s')) . ')) AS works_count';
                array_push($select_params, ...$st);
                $where[] = "g.status = 'active'";
                if ($a['q'] !== '') {
                    $where[] = '(g.name LIKE %s OR g.city LIKE %s)';
                    array_push($params, ...array_fill(0, 2, $like($a['q'])));
                }
                if (!empty($a['city'])) { $where[] = 'g.city = %s'; $params[] = $a['city']; }
                $order = [
                    'newest'      => 'g.id DESC',
                    'exhibitions' => 'exhibitions_count DESC, g.name ASC',
                ][$a['sort']] ?? 'g.name ASC, g.id DESC';
                break;

            case 'exhibitions':
                $t = gmdate('Y-m-d'); // fixed-format literal, safe to inline
                $phase = "(CASE WHEN e.start_date IS NULL OR e.start_date > '{$t}' THEN 1
                                WHEN COALESCE(e.end_date, e.start_date) < '{$t}' THEN 2 ELSE 0 END)";
                $from = ' FROM ' . Database::table('exhibitions') . ' e
                          LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = e.gallery_id';
                $select = "e.*, g.name AS gallery_name, g.slug AS gallery_slug, g.logo_url AS gallery_logo, g.logo_id AS gallery_logo_id, {$phase} AS phase";
                $where[] = "e.status IN ('published','active')";
                if ($a['q'] !== '') {
                    $where[] = '(e.title LIKE %s OR e.subtitle LIKE %s OR e.artist_label LIKE %s OR g.name LIKE %s)';
                    array_push($params, ...array_fill(0, 4, $like($a['q'])));
                }
                if (!empty($a['event_type'])) { $where[] = 'e.event_type = %s'; $params[] = $a['event_type']; }
                if (!empty($a['gallery']))    { $where[] = 'g.slug = %s'; $params[] = $a['gallery']; }
                if (!empty($a['when'])) {
                    $where[] = $phase . ' = ' . ['ongoing' => 0, 'upcoming' => 1, 'past' => 2][$a['when']];
                }
                $order = [
                    'start_desc' => 'e.start_date DESC, e.id DESC',
                    'start_asc'  => 'e.start_date ASC, e.id ASC',
                    'title'      => 'e.title ASC',
                ][$a['sort']] ?? "phase ASC, (CASE WHEN {$phase} < 2 THEN e.start_date END) ASC, (CASE WHEN {$phase} = 2 THEN e.start_date END) DESC, e.id DESC";
                break;

            default:
                return ['items' => [], 'total' => 0, 'pages' => 0, 'page' => 1, 'per_page' => $per];
        }

        $where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $count_sql = 'SELECT COUNT(*)' . $from . $where_sql;
        $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, ...$params)) : $wpdb->get_var($count_sql));

        $pages = (int) max(1, ceil($total / $per));
        $page  = min($page, $pages);
        $offset = ($page - 1) * $per;

        $sql = 'SELECT ' . $select . $from . $where_sql . ' ORDER BY ' . $order . ' LIMIT %d OFFSET %d';
        $all_params = array_merge($select_params, $params, [$per, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$all_params)) ?: [];
        // phpcs:enable

        self::prime_media($items);
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $per];
    }

    /* ==================================================================
     * Facets for filter controls
     * ================================================================ */

    /** Load all attachments of a result page in one go (avoids one query per card image). */
    private static function prime_media(array $items): void
    {
        $ids = [];
        foreach ($items as $row) {
            foreach (['image_id', 'poster_id', 'avatar_id', 'logo_id', 'cover_id'] as $f) {
                if (!empty($row->{$f})) {
                    $ids[] = (int) $row->{$f};
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids && function_exists('_prime_post_caches')) {
            _prime_post_caches($ids, false, true);
        }
    }

    private static function facets(string $type): array
    {
        return Cache::get('facets_' . $type, fn() => self::build_facets($type), 600);
    }

    private static function build_facets(string $type): array
    {
        global $wpdb;
        $f = [];
        if ($type === 'artworks') {
            $st = Artwork::public_statuses();
            $ph = implode(',', array_fill(0, count($st), '%s'));
            $f['artists'] = $wpdb->get_results($wpdb->prepare(
                'SELECT DISTINCT ar.slug, COALESCE(NULLIF(ar.display_name, ""), CONCAT(ar.first_name, " ", ar.last_name)) AS name
                 FROM ' . Database::table('artists') . ' ar
                 INNER JOIN ' . Database::table('artworks') . " a ON a.artist_id = ar.id AND a.status IN ({$ph})
                 WHERE ar.status = 'active' ORDER BY name ASC LIMIT 300",
                ...$st
            )) ?: [];
            $f['galleries'] = $wpdb->get_results($wpdb->prepare(
                'SELECT DISTINCT g.slug, g.name FROM ' . Database::table('galleries') . ' g
                 INNER JOIN ' . Database::table('artworks') . " a ON a.gallery_id = g.id AND a.status IN ({$ph})
                 WHERE g.status = 'active' ORDER BY g.name ASC LIMIT 300",
                ...$st
            )) ?: [];
            $f['mediums'] = $wpdb->get_col($wpdb->prepare(
                'SELECT DISTINCT medium FROM ' . Database::table('artworks') . " WHERE status IN ({$ph}) AND medium IS NOT NULL AND medium <> '' ORDER BY medium ASC LIMIT 200",
                ...$st
            )) ?: [];
        } elseif ($type === 'galleries') {
            $f['cities'] = $wpdb->get_col(
                'SELECT DISTINCT city FROM ' . Database::table('galleries') . " WHERE status = 'active' AND city IS NOT NULL AND city <> '' ORDER BY city ASC LIMIT 200"
            ) ?: [];
        } elseif ($type === 'exhibitions') {
            $f['galleries'] = $wpdb->get_results(
                'SELECT DISTINCT g.slug, g.name FROM ' . Database::table('galleries') . ' g
                 INNER JOIN ' . Database::table('exhibitions') . " e ON e.gallery_id = g.id AND e.status IN ('published','active')
                 WHERE g.status = 'active' ORDER BY g.name ASC LIMIT 300"
            ) ?: [];
        }
        return $f;
    }

    /* ==================================================================
     * Widget (toolbar + results)
     * ================================================================ */

    public static function shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'type'     => 'artworks',
            'per_page' => 12,
            'filters'  => '1',
            'artist'   => '',
            'gallery'  => '',
            'when'     => '',
            'event_type' => '',
            'for_sale' => '',
        ], (array) $atts, 'art_coa_catalog');

        $type = in_array($atts['type'], self::TYPES, true) ? (string) $atts['type'] : 'artworks';
        $preset = array_filter([
            'artist'     => $atts['artist'],
            'gallery'    => $atts['gallery'],
            'when'       => $atts['when'],
            'event_type' => $atts['event_type'],
            'for_sale'   => $atts['for_sale'],
        ], static fn($v) => $v !== '' && $v !== null);

        return self::render_widget($type, [
            'per_page' => (int) $atts['per_page'],
            'filters'  => $atts['filters'] !== '0',
            'preset'   => $preset,
        ]);
    }

    /**
     * @param array{per_page?:int,filters?:bool,preset?:array<string,mixed>} $o
     */
    public static function render_widget(string $type, array $o = []): string
    {
        $o += ['per_page' => 12, 'filters' => true, 'preset' => []];

        $req  = self::from_request($type);
        // The "page" parameter is handled by the no-JS pager only.
        $args = self::normalize($type, array_merge($o['preset'], $req, ['per_page' => $o['per_page']]));
        $res  = self::query($type, $args);
        $f    = $o['filters'] ? self::facets($type) : [];

        $uid = 'ac' . wp_rand(1000, 9999);
        $cfg = [
            'type'     => $type,
            'endpoint' => rest_url('art-coa/v1/catalog/' . $type),
            'per_page' => (int) $o['per_page'],
            'preset'   => (object) $o['preset'],
            'pages'    => $res['pages'],
            'page'     => $res['page'],
            'prefix'   => self::PREFIX,
        ];

        ob_start();
        ?>
        <div class="art-coa-catalog art-coa-catalog--<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($uid); ?>"
             data-config="<?php echo esc_attr((string) wp_json_encode($cfg)); ?>" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <?php if ($o['filters']): ?>
            <form class="art-coa-toolbar" method="get" role="search" aria-label="<?php echo esc_attr(I18n::t('filter')); ?>">
                <?php
                // keep unrelated query args (e.g. page_id on plain permalinks)
                foreach ($_GET as $k => $v) { // phpcs:ignore WordPress.Security.NonceVerification
                    if (is_string($k) && is_scalar($v) && strncmp($k, self::PREFIX, strlen(self::PREFIX)) !== 0) {
                        echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) $v) . '">';
                    }
                }
                self::toolbar_fields($type, $args, $f);
                ?>
                <button type="submit" class="art-coa-btn"><?php echo esc_html(I18n::t('filter')); ?></button>
                <a class="art-coa-btn art-coa-btn--ghost js-reset" href="<?php echo esc_url(remove_query_arg(self::param_names($type))); ?>"><?php echo esc_html(I18n::t('cat_reset')); ?></a>
            </form>
            <?php endif; ?>

            <p class="art-coa-result-meta" role="status" aria-live="polite"><?php echo esc_html(self::count_label($res['total'])); ?></p>

            <div class="art-coa-cards art-coa-cards--<?php echo esc_attr($type); ?>" data-cards>
                <?php echo self::cards_html($type, $res['items']); // phpcs:ignore ?>
            </div>

            <nav class="art-coa-pager" aria-label="pagination" <?php echo $res['pages'] > 1 ? '' : 'hidden'; ?>>
                <?php if ($res['page'] > 1): ?>
                    <a rel="prev" href="<?php echo esc_url(add_query_arg(self::PREFIX . 'page', $res['page'] - 1)); ?>">‹ <?php echo esc_html(I18n::t('cat_prev')); ?></a>
                <?php endif; ?>
                <span><?php echo esc_html(self::n($res['page']) . ' / ' . self::n($res['pages'])); ?></span>
                <?php if ($res['page'] < $res['pages']): ?>
                    <a rel="next" href="<?php echo esc_url(add_query_arg(self::PREFIX . 'page', $res['page'] + 1)); ?>"><?php echo esc_html(I18n::t('cat_next')); ?> ›</a>
                <?php endif; ?>
            </nav>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @return string[] */
    private static function param_names(string $type): array
    {
        $keys = ['q', 'page', 'sort'];
        $keys = array_merge($keys, [
            'artworks'    => ['artist', 'gallery', 'medium', 'year_from', 'year_to', 'for_sale'],
            'artists'     => [],
            'galleries'   => ['city'],
            'exhibitions' => ['event_type', 'when', 'gallery'],
        ][$type] ?? []);
        return array_map(static fn($k) => self::PREFIX . $k, $keys);
    }

    private static function select(string $name, string $placeholder, array $options, $current): void
    {
        echo '<select name="' . esc_attr(self::PREFIX . $name) . '" aria-label="' . esc_attr($placeholder) . '">';
        echo '<option value="">' . esc_html($placeholder) . '</option>';
        foreach ($options as $val => $label) {
            echo '<option value="' . esc_attr((string) $val) . '"' . selected((string) $current, (string) $val, false) . '>' . esc_html((string) $label) . '</option>';
        }
        echo '</select>';
    }

    private static function toolbar_fields(string $type, array $a, array $f): void
    {
        echo '<input type="search" name="' . esc_attr(self::PREFIX . 'q') . '" value="' . esc_attr((string) $a['q']) . '" placeholder="' . esc_attr(I18n::t('cat_search')) . '" aria-label="' . esc_attr(I18n::t('cat_search')) . '" autocomplete="off">';

        switch ($type) {
            case 'artworks':
                self::select('artist', I18n::t('cat_all_artists'), array_column($f['artists'] ?? [], 'name', 'slug'), $a['artist'] ?? '');
                self::select('gallery', I18n::t('cat_all_galleries'), array_column($f['galleries'] ?? [], 'name', 'slug'), $a['gallery'] ?? '');
                self::select('medium', I18n::t('cat_all_mediums'), array_combine($f['mediums'] ?? [], $f['mediums'] ?? []) ?: [], $a['medium'] ?? '');
                echo '<input type="number" name="' . esc_attr(self::PREFIX . 'year_from') . '" value="' . esc_attr((string) ($a['year_from'] ?: '')) . '" placeholder="' . esc_attr(I18n::t('cat_year_from')) . '" aria-label="' . esc_attr(I18n::t('cat_year_from')) . '" min="1000" max="2100" class="is-narrow">';
                echo '<input type="number" name="' . esc_attr(self::PREFIX . 'year_to') . '" value="' . esc_attr((string) ($a['year_to'] ?: '')) . '" placeholder="' . esc_attr(I18n::t('cat_year_to')) . '" aria-label="' . esc_attr(I18n::t('cat_year_to')) . '" min="1000" max="2100" class="is-narrow">';
                echo '<label class="art-coa-check"><input type="checkbox" name="' . esc_attr(self::PREFIX . 'for_sale') . '" value="1"' . checked(!empty($a['for_sale']), true, false) . '> ' . esc_html(I18n::t('for_sale')) . '</label>';
                self::select('sort', I18n::t('cat_sort'), [
                    'newest' => I18n::t('cat_sort_newest'), 'oldest' => I18n::t('cat_sort_oldest'), 'title' => I18n::t('cat_sort_title'),
                    'year_desc' => I18n::t('cat_sort_year'), 'price_asc' => I18n::t('cat_sort_price_asc'), 'price_desc' => I18n::t('cat_sort_price_desc'),
                ], $a['sort']);
                break;
            case 'artists':
                self::select('sort', I18n::t('cat_sort'), [
                    'name' => I18n::t('cat_sort_name'), 'works' => I18n::t('cat_sort_works'), 'newest' => I18n::t('cat_sort_newest'),
                ], $a['sort']);
                break;
            case 'galleries':
                self::select('city', I18n::t('cat_all_cities'), array_combine($f['cities'] ?? [], $f['cities'] ?? []) ?: [], $a['city'] ?? '');
                self::select('sort', I18n::t('cat_sort'), [
                    'name' => I18n::t('cat_sort_name'), 'exhibitions' => I18n::t('cat_sort_exhibitions'), 'newest' => I18n::t('cat_sort_newest'),
                ], $a['sort']);
                break;
            case 'exhibitions':
                self::select('when', I18n::t('cat_when_all'), [
                    'ongoing' => I18n::t('exhibitions_ongoing'), 'upcoming' => I18n::t('exhibitions_upcoming'), 'past' => I18n::t('exhibitions_past'),
                ], $a['when'] ?? '');
                self::select('event_type', I18n::t('cat_all_types'), [
                    'exhibition' => I18n::t('exhibition'), 'artfair' => I18n::t('artfair'), 'auction' => I18n::t('auction'),
                ], $a['event_type'] ?? '');
                self::select('gallery', I18n::t('cat_all_galleries'), array_column($f['galleries'] ?? [], 'name', 'slug'), $a['gallery'] ?? '');
                self::select('sort', I18n::t('cat_sort'), [
                    'start_desc' => I18n::t('cat_sort_start_desc'), 'start_asc' => I18n::t('cat_sort_start_asc'), 'title' => I18n::t('cat_sort_title'),
                ], $a['sort']);
                break;
        }
    }

    /* ==================================================================
     * Cards
     * ================================================================ */

    public static function cards_html(string $type, array $items): string
    {
        if (!$items) {
            return '<p class="art-coa-empty art-coa-empty--block">' . esc_html(I18n::t('cat_empty')) . '</p>';
        }
        $out = '';
        foreach ($items as $row) {
            $out .= match ($type) {
                'artworks'    => self::card_artwork($row),
                'artists'     => self::card_artist($row),
                'galleries'   => self::card_gallery($row),
                'exhibitions' => self::card_exhibition($row),
                default       => '',
            };
        }
        return $out;
    }

    /** Canonical artist destination, with a safe fallback for isolated rendering contexts. */
    private static function artist_url(string $slug): string
    {
        if (class_exists(ContentSync::class)) {
            return ContentSync::artist_url($slug);
        }
        $slug = sanitize_title($slug);
        return $slug !== '' ? home_url('/artists/' . rawurlencode($slug) . '/') : home_url('/artists/');
    }

    public static function artist_name(object $row): string
    {
        $n = (string) ($row->artist_name ?? $row->display_name ?? '');
        if ($n === '') {
            $n = trim(((string) ($row->first_name ?? '')) . ' ' . ((string) ($row->last_name ?? '')));
        }
        return $n;
    }

    public static function n($v): string
    {
        $s = (string) $v;
        return I18n::is_fa() ? Calendar::to_persian_digits($s) : $s;
    }

    public static function count_label(int $n): string
    {
        return I18n::t('cat_results', ['n' => self::n($n)]);
    }

    private static function placeholder(string $glyph = '🖼'): string
    {
        return '<span class="art-coa-c-ph" aria-hidden="true">' . esc_html($glyph) . '</span>';
    }

    public static function card_artwork(object $a): string
    {
        $img  = Media::img($a, 'image', 'medium_large', ['alt' => (string) $a->title]);
        $sold = ($a->status ?? '') === 'sold';
        $sale = !$sold && !empty($a->for_sale);
        $meta = trim(implode(' · ', array_filter([
            !empty($a->year_created) ? self::n($a->year_created) : '',
            (string) ($a->medium ?? ''),
        ])));

        $h  = '<a class="art-coa-c art-coa-c--artwork" href="' . esc_url(home_url('/art-coa/artwork/' . $a->slug)) . '">';
        $h .= '<span class="art-coa-c-media">' . ($img ?: self::placeholder());
        if ($sold) {
            $h .= '<span class="art-coa-c-badge is-sold">' . esc_html(I18n::t('cat_sold')) . '</span>';
        } elseif ($sale) {
            $h .= '<span class="art-coa-c-badge is-sale">' . esc_html(I18n::t('for_sale')) . '</span>';
        }
        $h .= '</span><span class="art-coa-c-body">';
        $h .= '<span class="art-coa-c-title">' . esc_html((string) $a->title) . '</span>';
        if (($n = self::artist_name($a)) !== '') {
            $h .= '<span class="art-coa-c-sub">' . esc_html($n) . '</span>';
        }
        if ($meta !== '') {
            $h .= '<span class="art-coa-c-meta">' . esc_html($meta) . '</span>';
        }
        if ($sale && isset($a->price) && (float) $a->price > 0) {
            $h .= '<span class="art-coa-c-price">' . esc_html(Shop::format_price($a->price, (string) ($a->currency ?? 'IRR'))) . '</span>';
        }
        return $h . '</span></a>';
    }

    public static function card_artist(object $a): string
    {
        $name = self::artist_name($a);
        $img  = Media::img($a, 'avatar', 'medium', ['alt' => $name]);
        $h  = '<a class="art-coa-c art-coa-c--artist" href="' . esc_url(self::artist_url((string) $a->slug)) . '">';
        $h .= '<span class="art-coa-c-media is-round">' . ($img ?: self::placeholder(\Artiey_AET_Text::substr($name, 0, 1) ?: '•')) . '</span>';
        $h .= '<span class="art-coa-c-body"><span class="art-coa-c-title">' . esc_html($name) . '</span>';
        $h .= '<span class="art-coa-c-meta">' . esc_html(I18n::t('cat_n_works', ['n' => self::n((int) ($a->works_count ?? 0))])) . '</span></span></a>';
        return $h;
    }

    public static function card_gallery(object $g): string
    {
        $cover = Media::img($g, 'cover', 'medium_large', ['alt' => (string) $g->name]);
        $logo  = Media::img($g, 'logo', 'thumbnail', ['alt' => '']);
        $h  = '<a class="art-coa-c art-coa-c--gallery" href="' . esc_url(home_url('/art-coa/gallery/' . $g->slug)) . '">';
        $h .= '<span class="art-coa-c-media is-wide">' . ($cover ?: ($logo ?: self::placeholder('🏛')));
        if ($cover && $logo) {
            $h .= '<span class="art-coa-c-logo">' . $logo . '</span>';
        }
        $h .= '</span><span class="art-coa-c-body"><span class="art-coa-c-title">' . esc_html((string) $g->name) . '</span>';
        if (!empty($g->city)) {
            $h .= '<span class="art-coa-c-sub">' . esc_html((string) $g->city) . '</span>';
        }
        $h .= '<span class="art-coa-c-meta">' . esc_html(I18n::t('cat_n_exhibitions', ['n' => self::n((int) ($g->exhibitions_count ?? 0))]))
            . ' · ' . esc_html(I18n::t('cat_n_works', ['n' => self::n((int) ($g->works_count ?? 0))])) . '</span></span></a>';
        return $h;
    }

    /** 0 ongoing · 1 upcoming · 2 past */
    public static function phase(object $e): int
    {
        if (isset($e->phase)) {
            return (int) $e->phase;
        }
        $today = gmdate('Y-m-d');
        $start = $e->start_date ?? null;
        $end   = $e->end_date ?: $start;
        if (!$start || $start > $today) {
            return 1;
        }
        return ($end && $end < $today) ? 2 : 0;
    }

    public static function card_exhibition(object $e): string
    {
        $phase = self::phase($e);
        $labels = [0 => I18n::t('exhibitions_ongoing'), 1 => I18n::t('exhibitions_upcoming'), 2 => I18n::t('exhibitions_past')];
        $types  = ['exhibition' => I18n::t('exhibition'), 'artfair' => I18n::t('artfair'), 'auction' => I18n::t('auction')];
        $img = Media::img($e, 'poster', 'large', ['alt' => (string) $e->title]);

        $dates = '';
        if (!empty($e->start_date)) {
            $dates = Dates::format($e->start_date) . (!empty($e->end_date) ? ' — ' . Dates::format($e->end_date) : '');
        }

        $h  = '<a class="art-coa-c art-coa-c--exhibition phase-' . $phase . '" href="' . esc_url(home_url('/art-coa/exhibition/' . $e->slug)) . '">';
        $h .= '<span class="art-coa-c-media is-poster">' . ($img ?: self::placeholder('🖼'));
        $h .= '<span class="art-coa-c-badge phase-' . $phase . '">' . esc_html($labels[$phase]) . '</span></span>';
        $h .= '<span class="art-coa-c-body">';
        $h .= '<span class="art-coa-c-kicker">' . esc_html($types[$e->event_type ?? 'exhibition'] ?? '') . '</span>';
        $h .= '<span class="art-coa-c-title">' . esc_html((string) $e->title) . '</span>';
        $sub = !empty($e->subtitle) ? (string) $e->subtitle : (string) ($e->artist_label ?? '');
        if ($sub !== '') {
            $h .= '<span class="art-coa-c-sub">' . esc_html($sub) . '</span>';
        }
        if (!empty($e->gallery_name)) {
            $h .= '<span class="art-coa-c-meta">' . esc_html((string) $e->gallery_name) . '</span>';
        }
        if ($dates !== '') {
            $h .= '<span class="art-coa-c-meta">' . esc_html($dates) . '</span>';
        }
        return $h . '</span></a>';
    }

    /* ==================================================================
     * Live global search box
     * ================================================================ */

    public static function search_shortcode($atts = []): string
    {
        $cfg = ['endpoint' => rest_url('art-coa/v1/search')];
        ob_start();
        ?>
        <div class="art-coa-livesearch" data-config="<?php echo esc_attr((string) wp_json_encode($cfg)); ?>" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <input type="search" placeholder="<?php echo esc_attr(I18n::t('cat_live_search')); ?>" autocomplete="off" aria-label="<?php echo esc_attr(I18n::t('cat_live_search')); ?>">
            <div class="art-coa-livesearch-panel" role="listbox" hidden></div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
