<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Portfolio
{
    public static function init(): void
    {
        add_shortcode('art_coa_portfolio', [self::class, 'shortcode']);
        add_action('init', [self::class, 'rewrites']);
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'art_coa_portfolio';
            return $vars;
        });
        add_action('template_redirect', [self::class, 'maybe_render_page']);
    }

    public static function rewrites(): void
    {
        add_rewrite_rule('^art-coa/portfolio/?$', 'index.php?art_coa_portfolio=all', 'top');
        add_rewrite_rule('^art-coa/portfolio/artist/([^/]+)/?$', 'index.php?art_coa_portfolio=artist&art_coa_slug=$matches[1]', 'top');
        add_rewrite_rule('^art-coa/portfolio/gallery/([^/]+)/?$', 'index.php?art_coa_portfolio=gallery&art_coa_slug=$matches[1]', 'top');
    }

    public static function maybe_render_page(): void
    {
        $mode = get_query_var('art_coa_portfolio');
        if (!$mode) {
            return;
        }
        status_header(200);
        nocache_headers();
        $slug = sanitize_title((string) get_query_var('art_coa_slug'));
        if ($mode === 'artist') {
            $content = self::render(['filter' => 'artist', 'slug' => $slug]);
            $route_key = 'portfolio_artist';
        } elseif ($mode === 'gallery') {
            $content = self::render(['filter' => 'gallery', 'slug' => $slug]);
            $route_key = 'portfolio_gallery';
        } else {
            $content = self::render(['filter' => 'all']);
            $route_key = 'portfolio_archive';
        }
        if (class_exists('\\Artiey_AET_Elementor_Layouts')) {
            $content = \Artiey_AET_Elementor_Layouts::render_route($route_key, [
                'module' => 'portfolio', 'entity_type' => 'portfolio', 'mode' => $mode === 'all' ? 'archive' : 'single', 'slug' => $slug,
            ], $content);
        }
        get_header();
        echo '<div class="art-coa-profile-wrap" dir="' . (I18n::is_fa() ? 'rtl' : 'ltr') . '">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted portfolio renderer/template.
        get_footer();
        exit;
    }

    public static function shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'filter'     => 'all', // all | artist | gallery
            'id'         => 0,
            'slug'       => '',
            'limit'      => 24,
            'columns'    => 3,
            'show_filter'=> '1',
            'show_title' => '1',
            'title'      => '',
            'certified_only' => 0,
            'ignore_request_filters' => 0,
        ], $atts, 'art_coa_portfolio');

        return self::render($atts);
    }

    public static function render(array $args): string
    {
        $filter  = sanitize_key($args['filter'] ?? 'all');
        $id      = absint($args['id'] ?? 0);
        $slug    = sanitize_title($args['slug'] ?? '');
        $limit   = max(1, min(100, absint($args['limit'] ?? 24)));
        $columns = max(1, min(4, absint($args['columns'] ?? 3)));
        $show_filter = !empty($args['show_filter']);
        $show_title = !isset($args['show_title']) || in_array($args['show_title'], [true, '1', 'yes', 1], true);
        $certified_only = !empty($args['certified_only']);
        $ignore_request_filters = !empty($args['ignore_request_filters']);
        $title = sanitize_text_field((string) ($args['title'] ?? ''));
        $title = $title !== '' ? $title : I18n::t('portfolio');

        // Resolve slug to id
        if (!$id && $slug) {
            global $wpdb;
            if ($filter === 'artist') {
                $id = (int) $wpdb->get_var($wpdb->prepare(
                    'SELECT id FROM ' . Database::table('artists') . ' WHERE slug = %s',
                    $slug
                ));
            } elseif ($filter === 'gallery') {
                $id = (int) $wpdb->get_var($wpdb->prepare(
                    'SELECT id FROM ' . Database::table('galleries') . ' WHERE slug = %s',
                    $slug
                ));
            }
        }

        // Read public filters only from scalar request values; malformed array payloads must not leak into casts.
        $request_value = static function (string $key) use ($ignore_request_filters) {
            if ($ignore_request_filters || !isset($_GET[$key]) || !is_scalar($_GET[$key])) {
                return '';
            }
            return wp_unslash((string) $_GET[$key]);
        };
        $q_artist  = absint($request_value('artist'));
        $q_gallery = absint($request_value('gallery'));
        $q_year    = absint($request_value('year'));
        $q_medium  = sanitize_text_field((string) $request_value('medium'));

        if (!$ignore_request_filters && $q_artist) {
            $filter = 'artist';
            $id = $q_artist;
        } elseif (!$ignore_request_filters && $q_gallery) {
            $filter = 'gallery';
            $id = $q_gallery;
        }

        $items = self::query_artworks($filter, $id, $limit, $q_year, $q_medium, $certified_only);
        // Do not run four catalogue queries when filters are hidden (e.g. tabbed Elementor panels).
        $artists = $show_filter ? self::filter_artists() : [];
        $galleries = $show_filter ? self::filter_galleries() : [];
        $years = $show_filter ? self::filter_years() : [];
        $mediums = $show_filter ? self::filter_mediums() : [];

        ob_start();
        ?>
        <div class="art-coa-portfolio" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <?php if ($show_title): ?><h2 class="art-coa-portfolio-title"><?php echo esc_html($title); ?></h2><?php endif; ?>

            <?php if ($show_filter): ?>
            <form method="get" class="art-coa-portfolio-filters">
                <select name="artist">
                    <option value=""><?php echo esc_html(I18n::t('all') . ' — ' . I18n::t('artists')); ?></option>
                    <?php foreach ($artists as $a): ?>
                        <option value="<?php echo (int)$a->id; ?>" <?php selected($q_artist, (int)$a->id); ?>>
                            <?php echo esc_html($a->display_name ?: trim($a->first_name . ' ' . $a->last_name)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="gallery">
                    <option value=""><?php echo esc_html(I18n::t('all') . ' — ' . I18n::t('galleries')); ?></option>
                    <?php foreach ($galleries as $g): ?>
                        <option value="<?php echo (int)$g->id; ?>" <?php selected($q_gallery, (int)$g->id); ?>>
                            <?php echo esc_html($g->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="year">
                    <option value=""><?php echo esc_html(I18n::t('all') . ' — ' . I18n::t('year')); ?></option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo (int)$y; ?>" <?php selected($q_year, (int)$y); ?>><?php echo esc_html((string)$y); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="medium">
                    <option value=""><?php echo esc_html(I18n::t('all') . ' — ' . I18n::t('medium')); ?></option>
                    <?php foreach ($mediums as $m): ?>
                        <option value="<?php echo esc_attr($m); ?>" <?php selected($q_medium, $m); ?>><?php echo esc_html($m); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php echo esc_html(I18n::t('filter')); ?></button>
            </form>
            <?php endif; ?>

            <?php if (!$items): ?>
                <p class="art-coa-empty"><?php echo esc_html(I18n::t('no_items')); ?></p>
            <?php else: ?>
                <div class="art-coa-portfolio-grid cols-<?php echo (int)$columns; ?>">
                    <?php foreach ($items as $aw): ?>
                        <article class="art-coa-portfolio-card">
                            <?php if (\WPArtCOA\Media::url($aw, 'image', 'large')): ?>
                                <div class="art-coa-portfolio-img">
                                    <img src="<?php echo esc_url(\WPArtCOA\Media::url($aw, 'image', 'large')); ?>" alt="<?php echo esc_attr($aw->title); ?>" loading="lazy" decoding="async">
                                </div>
                            <?php else: ?>
                                <div class="art-coa-portfolio-img art-coa-portfolio-placeholder">🖼</div>
                            <?php endif; ?>
                            <div class="art-coa-portfolio-body">
                                <h3><?php echo esc_html($aw->title); ?></h3>
                                <p class="meta">
                                    <?php
                                    $an = $aw->artist_name ?: trim(($aw->first_name ?? '') . ' ' . ($aw->last_name ?? ''));
                                    if ($an && !empty($aw->artist_slug)):
                                    ?>
                                        <a href="<?php echo esc_url(ContentSync::artist_url((string) $aw->artist_slug)); ?>"><?php echo esc_html($an); ?></a>
                                    <?php endif; ?>
                                    <?php if ($aw->year_created): ?> · <?php echo esc_html((string)$aw->year_created); ?><?php endif; ?>
                                </p>
                                <?php if ($aw->medium): ?>
                                    <p class="meta"><?php echo esc_html($aw->medium); ?></p>
                                <?php endif; ?>
                                <?php if ($aw->gallery_name && $aw->gallery_slug): ?>
                                    <p class="meta">
                                        <a href="<?php echo esc_url(home_url('/art-coa/gallery/' . $aw->gallery_slug)); ?>"><?php echo esc_html($aw->gallery_name); ?></a>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($aw->cert_serial)): ?>
                                    <p class="meta">
                                        🎖️ <a href="<?php echo esc_url(home_url('/art-coa/certificate/' . rawurlencode($aw->cert_serial))); ?>"><code><?php echo esc_html($aw->cert_serial); ?></code></a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function query_artworks(string $filter, int $id, int $limit, int $year, string $medium, bool $certified_only = false): array
    {
        global $wpdb;
        $sql = 'SELECT a.*, ar.display_name AS artist_name, ar.slug AS artist_slug, ar.first_name, ar.last_name,
                       g.name AS gallery_name, g.slug AS gallery_slug,
                       (SELECT c.serial_number FROM ' . Database::table('certificates') . ' c
                        WHERE c.artwork_id = a.id AND c.status = \'active\' ORDER BY c.id DESC LIMIT 1) AS cert_serial
                FROM ' . Database::table('artworks') . ' a
                LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = a.gallery_id
                WHERE a.status IN (\'published\',\'active\')';
        $params = [];

        if ($filter === 'artist' && $id) {
            $sql .= ' AND a.artist_id = %d';
            $params[] = $id;
        } elseif ($filter === 'gallery' && $id) {
            $sql .= ' AND a.gallery_id = %d';
            $params[] = $id;
        }
        if ($certified_only) {
            $sql .= " AND EXISTS (SELECT 1 FROM " . Database::table('certificates') . " cert_filter WHERE cert_filter.artwork_id = a.id AND cert_filter.status = 'active')";
        }
        if ($year) {
            $sql .= ' AND a.year_created = %d';
            $params[] = $year;
        }
        if ($medium !== '') {
            $sql .= ' AND a.medium = %s';
            $params[] = $medium;
        }

        $sql .= ' ORDER BY a.id DESC LIMIT %d';
        $params[] = $limit;

        if ($params) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$params)) ?: [];
        }
        return $wpdb->get_results($sql) ?: [];
    }

    private static function filter_artists(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT id, display_name, first_name, last_name FROM ' . Database::table('artists') .
            " WHERE status='active' ORDER BY display_name ASC LIMIT 200"
        ) ?: [];
    }

    private static function filter_galleries(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT id, name FROM ' . Database::table('galleries') .
            " WHERE status='active' ORDER BY name ASC LIMIT 200"
        ) ?: [];
    }

    private static function filter_years(): array
    {
        global $wpdb;
        $rows = $wpdb->get_col(
            'SELECT DISTINCT year_created FROM ' . Database::table('artworks') .
            ' WHERE year_created IS NOT NULL AND year_created > 0 ORDER BY year_created DESC LIMIT 50'
        ) ?: [];
        return array_map('intval', $rows);
    }

    private static function filter_mediums(): array
    {
        global $wpdb;
        $rows = $wpdb->get_col(
            'SELECT DISTINCT medium FROM ' . Database::table('artworks') .
            " WHERE medium IS NOT NULL AND medium != '' ORDER BY medium ASC LIMIT 50"
        ) ?: [];
        return $rows;
    }
}
