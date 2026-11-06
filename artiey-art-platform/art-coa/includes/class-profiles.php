<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Artist;
use WPArtCOA\Entities\Certificate;
use WPArtCOA\Entities\Collector;
use WPArtCOA\Entities\Curator;
use WPArtCOA\Entities\Exhibition;
use WPArtCOA\Entities\Gallery;
use WPArtCOA\Entities\Artwork;

defined('ABSPATH') || exit;

final class Profiles
{
    public static function init(): void
    {
        add_action('init', [self::class, 'rewrites']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'template_redirect']);
    }

    public static function rewrites(): void
    {
        add_rewrite_rule('^art-coa/artist/([^/]+)/?$', 'index.php?art_coa_type=artist&art_coa_slug=$matches[1]', 'top');
        add_rewrite_rule('^art-coa/curator/([^/]+)/?$', 'index.php?art_coa_type=curator&art_coa_slug=$matches[1]', 'top');
        add_rewrite_rule('^art-coa/curators/?$', 'index.php?art_coa_type=curators_list', 'top');
        add_rewrite_rule('^art-coa/gallery/([^/]+)/?$', 'index.php?art_coa_type=gallery&art_coa_slug=$matches[1]', 'top');
        add_rewrite_rule('^art-coa/exhibition/([^/]+)/?$', 'index.php?art_coa_type=exhibition&art_coa_slug=$matches[1]', 'top');
        add_rewrite_rule('^art-coa/collector/([^/]+)/?$', 'index.php?art_coa_type=collector&art_coa_slug=$matches[1]', 'top');
        add_rewrite_rule('^art-coa/certificate/([^/]+)/?$', 'index.php?art_coa_type=certificate&art_coa_slug=$matches[1]', 'top');
        add_rewrite_rule('^art-coa/artwork/([^/]+)/?$', 'index.php?art_coa_type=artwork&art_coa_slug=$matches[1]', 'top');
        add_rewrite_rule('^art-coa/artworks/?$', 'index.php?art_coa_type=artworks_list', 'top');
        add_rewrite_rule('^art-coa/artists/?$', 'index.php?art_coa_type=artists_list', 'top');
        add_rewrite_rule('^art-coa/galleries/?$', 'index.php?art_coa_type=galleries_list', 'top');
        add_rewrite_rule('^art-coa/exhibitions/?$', 'index.php?art_coa_type=exhibitions_list', 'top');
    }

    /** Stored biographies may contain <style> blocks or copies of the profile saved by older versions. */
    private static function clean_text($html): string
    {
        return class_exists('Artiey_AET_Profile_Guard') ? \Artiey_AET_Profile_Guard::clean_markup((string) $html) : (string) $html;
    }

    public static function query_vars(array $vars): array
    {
        $vars[] = 'art_coa_type';
        $vars[] = 'art_coa_slug';
        return $vars;
    }

    public static function template_redirect(): void
    {
        $type = get_query_var('art_coa_type');
        if (!$type) {
            return;
        }
        $known_types = [
            'artist', 'curator', 'curators_list', 'gallery', 'exhibition', 'collector',
            'certificate', 'artwork', 'artists_list', 'galleries_list', 'exhibitions_list', 'artworks_list',
        ];
        if (!in_array((string) $type, $known_types, true)) {
            global $wp_query;
            if ($wp_query instanceof \WP_Query) {
                $wp_query->set_404();
            }
            status_header(404);
            add_filter('wp_robots', 'wp_robots_no_robots');
            nocache_headers();
            get_header();
            echo '<div class="art-coa-profile-wrap"><p class="art-coa-empty">' . esc_html(I18n::t('not_found')) . '</p></div>';
            get_footer();
            exit;
        }

        // The old COA artist list is a permanent compatibility alias only.
        if ((string) $type === 'artists_list') {
            $canonical_archive = ContentSync::artist_archive_url();
            if ($canonical_archive !== '') {
                wp_safe_redirect($canonical_archive, 301, 'Artiey Art Platform');
                exit;
            }
        }

        $raw  = (string) get_query_var('art_coa_slug');
        $slug = sanitize_title($raw);
        $table_key = SlugHistory::TABLES[(string) $type] ?? '';
        if ($table_key !== '' && in_array((string) $type, ['artist', 'gallery', 'artwork', 'exhibition'], true)) {
            $legacy_row = SlugHistory::find($table_key, $raw);
            if (!$legacy_row) {
                $redirect_slug = SlugHistory::resolve($table_key, $slug);
                if ($redirect_slug !== '') {
                    $legacy_row = SlugHistory::find($table_key, $redirect_slug);
                }
            }
            if ((string) $type === 'artist') {
                $canonical_slug = $legacy_row ? sanitize_title((string) $legacy_row->slug) : $slug;
                $candidate = function_exists('get_page_by_path') ? get_page_by_path($canonical_slug, OBJECT, 'artiey_artist') : null;
                $has_canonical_artist = $candidate && isset($candidate->ID, $candidate->post_type, $candidate->post_status, $candidate->post_name)
                    && $candidate->post_type === 'artiey_artist' && $candidate->post_status === 'publish' && $candidate->post_name === $canonical_slug;
                // Only permanently redirect a public profile. A stale/inactive relational
                // row must not turn an old URL into a 301 pointing at a canonical 404.
                $artist_entity = Artist::get_by_slug($canonical_slug);
                $has_public_coa_artist = $artist_entity && (string) ($artist_entity->status ?? '') === 'active';
                if ($has_canonical_artist || $has_public_coa_artist) {
                    $canonical = Seo::canonical_url('artist', $canonical_slug);
                    $request_path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) : '';
                    $canonical_path = wp_parse_url($canonical, PHP_URL_PATH);
                    if ($canonical !== '' && is_string($request_path) && is_string($canonical_path)
                        && untrailingslashit($request_path) !== untrailingslashit($canonical_path)) {
                        wp_safe_redirect($canonical, 301, 'Artiey Art Platform');
                        exit;
                    }
                }
            } elseif ($legacy_row && SlugHistory::is_public_row((string) $type, $legacy_row, current_user_can('manage_options'))) {
                $canonical = ContentSync::permalink($table_key, (string) $legacy_row->slug);
                if ($canonical !== '' && $canonical !== home_url('/art-coa/' . $type . '/' . $raw)) {
                    wp_safe_redirect($canonical, 301, 'Artiey Art Platform');
                    exit;
                }
            }
        }
        $found = SlugHistory::guard((string) $type, $raw);
        status_header($found ? 200 : 404);
        nocache_headers();

        ob_start();
        echo '<div class="art-coa-profile-wrap" dir="' . (I18n::is_fa() ? 'rtl' : 'ltr') . '">';
        match ($type) {
            'artist'            => self::render_artist($slug),
            'curator'           => self::render_curator($slug),
            'curators_list'     => self::render_curators_list(),
            'gallery'           => self::render_gallery($slug),
            'exhibition'        => self::render_exhibition($slug),
            'collector'         => self::render_collector($slug),
            'certificate'       => self::render_certificate($slug),
            'artwork'           => self::render_artwork($slug),
            'artists_list'      => self::render_artists_list(),
            'galleries_list'    => self::render_galleries_list(),
            'exhibitions_list'  => self::render_exhibitions_list(),
            'artworks_list'     => self::render_artworks_list(),
            default             => print '<p>' . esc_html(I18n::t('not_found')) . '</p>',
        };
        echo '</div>';
        $default_content = (string) ob_get_clean();
        $route_map = array(
            'artist' => 'profile_artist', 'curator' => 'profile_curator', 'gallery' => 'profile_gallery',
            'exhibition' => 'profile_exhibition', 'collector' => 'profile_collector',
            'certificate' => 'profile_certificate', 'artwork' => 'profile_artwork',
            'curators_list' => 'archive_curators', 'galleries_list' => 'archive_galleries',
            'exhibitions_list' => 'archive_exhibitions', 'artworks_list' => 'archive_artworks',
        );
        $route_key = $route_map[(string) $type] ?? '';
        if ( $route_key && class_exists( '\\Artiey_AET_Elementor_Layouts' ) && $found ) {
            $default_content = \Artiey_AET_Elementor_Layouts::render_route( $route_key, array(
                'module' => 'profiles', 'entity_type' => sanitize_key( (string) $type ), 'slug' => $slug,
                'mode' => isset( $route_map[(string) $type] ) && 0 === strpos( $route_key, 'archive_' ) ? 'archive' : 'single',
            ), $default_content );
        }
        get_header();
        echo $default_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted profile renderers and Elementor template output.
        get_footer();
        exit;
    }

    /* ---------- Single profiles ---------- */

    private static function render_artist(string $slug): void
    {
        $artist = Artist::get_by_slug($slug); // Entity lookup overlays canonical WordPress profile fields.
        $artist_post_id = (int) ($artist->cpt_post_id ?? 0);
        $can_preview = $artist_post_id > 0 && current_user_can('edit_post', $artist_post_id);
        if (!$artist || ($artist->status !== 'active' && !$can_preview && !current_user_can('manage_options'))) {
            echo '<p class="art-coa-empty">هنرمند یافت نشد.</p>';
            return;
        }
        $is_admin = current_user_can('manage_options');
        $stats = Stats::artist((int) $artist->id, !$is_admin);
        global $wpdb;
        $artworks = $wpdb->get_results($wpdb->prepare(
            'SELECT a.*, g.name AS gallery_name, g.slug AS gallery_slug
             FROM ' . Database::table('artworks') . ' a
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = a.gallery_id
             WHERE a.artist_id = %d' . self::public_clause('a') . ' ORDER BY a.id DESC LIMIT 60',
            ...array_merge([(int) $artist->id], self::public_params())
        )) ?: [];
        $certs = $wpdb->get_results($wpdb->prepare(
            'SELECT c.serial_number, c.status FROM ' . Database::table('certificates') . ' c
             INNER JOIN ' . Database::table('artworks') . ' a ON a.id = c.artwork_id
             WHERE a.artist_id = %d AND c.status = \'active\'' . self::public_clause('a') . ' ORDER BY c.id DESC LIMIT 20',
            ...array_merge([(int) $artist->id], self::public_params())
        )) ?: [];
        ?>
        <div class="art-coa-profile art-coa-artist-profile">
            <?php echo self::cover($artist); ?>
            <header class="art-coa-profile-header art-coa-artist-hero">
                <?php echo Media::img($artist, 'avatar', 'medium', ['class' => 'art-coa-avatar', 'alt' => (string) ($artist->display_name ?: $artist->first_name), 'loading' => 'eager']); ?>
                <div>
                    <p class="art-coa-eyebrow">هنرمند</p>
                    <h1><?php echo esc_html($artist->display_name ?: ($artist->first_name . ' ' . $artist->last_name)); ?></h1>
                    <?php if ($artist->email && is_email($artist->email)): ?><p class="meta">✉ <a href="<?php echo esc_url('mailto:' . antispambot((string) $artist->email)); ?>"><?php echo esc_html(antispambot((string) $artist->email)); ?></a></p><?php endif; ?>
                    <?php if ($artist->phone): ?><p class="meta">☎ <?php echo esc_html($artist->phone); ?></p><?php endif; ?>
                    <?php if ($artist->website): ?>
                        <p><a href="<?php echo esc_url($artist->website); ?>" target="_blank" rel="me noopener noreferrer"><?php echo esc_html(\WPArtCOA\I18n::t('website')); ?></a></p>
                    <?php endif; ?>
                    <div class="art-coa-artist-actions">
                        <a class="button" href="<?php echo esc_url(home_url('/art-coa/shop/artist/' . $artist->slug)); ?>"><?php echo esc_html(I18n::t('shop')); ?></a>
                        <?php echo \WPArtCOA\Follows::button_html('artist', (int) $artist->id); ?>
                    </div>
                    <?php \WPArtCOA\ProfileFields::render_social('artists', $artist); ?>
                </div>
            </header>
            <?php $artist_stats = [
                \WPArtCOA\I18n::t('artworks') => $stats['artworks'],
                \WPArtCOA\I18n::t('certificates') => $stats['certs'],
                \WPArtCOA\I18n::t('exhibitions') => $stats['exhibitions'],
            ]; if ($is_admin) { $artist_stats[\WPArtCOA\I18n::t('sales_total')] = $stats['sales']; } self::stats_bar($artist_stats); ?>
            <?php echo \WPArtCOA\Follows::followers_html('artist', (int) $artist->id, ['avatars' => 10, 'show_button' => false, 'layout' => 'inline']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Follows ?>
            <?php if (apply_filters('art_coa_profile_charts', true, 'artist', $artist)) { echo \WPArtCOA\ChartKit::artist_panel($artist); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ChartKit ?>
            <?php } ?>
            <?php echo apply_filters('art_coa_artist_bio_html', \WPArtCOA\ArtistBio::render($artist), $artist); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ArtistBio ?>
            <section class="art-coa-section">
                <h2>آثار</h2>
                <?php if ($artworks): ?>
                    <div class="art-coa-cards art-coa-cards--artworks">
                        <?php echo Catalog::cards_html('artworks', $artworks); // phpcs:ignore ?>
                    </div>
                <?php else: ?>
                    <p class="art-coa-empty">هنوز اثری برای نمایش ثبت نشده است.</p>
                <?php endif; ?>
            </section>
            <?php
            $artist_galleries = [];
            foreach ($artworks as $artwork) {
                if (!empty($artwork->gallery_id) && !empty($artwork->gallery_slug) && !empty($artwork->gallery_name)) {
                    $artist_galleries[(int) $artwork->gallery_id] = $artwork;
                }
            }
            if ($artist_galleries): ?>
            <section class="art-coa-section art-coa-artist-galleries">
                <h2>گالری‌های مرتبط</h2>
                <div class="art-coa-chip-list">
                    <?php foreach ($artist_galleries as $gallery): ?>
                        <a class="art-coa-chip" href="<?php echo esc_url(home_url('/art-coa/gallery/' . rawurlencode($gallery->gallery_slug))); ?>"><?php echo esc_html($gallery->gallery_name); ?></a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <?php $pf_items = \WPArtCOA\ArtistPortfolio::for_artist((int) $artist->id, true); if ($pf_items): ?>
            <section class="art-coa-section art-coa-artist-portfolio">
                <h2><?php echo esc_html(I18n::t('portfolio')); ?></h2>
                <div class="art-coa-shop-grid cols-3">
                    <?php foreach ($pf_items as $pfi): ?>
                        <article class="art-coa-shop-card"><div class="art-coa-shop-img"><a href="<?php echo esc_url(Media::url($pfi, 'image', 'full')); ?>" data-art-lightbox="portfolio"><?php echo Media::img($pfi, 'image', 'medium_large') ?: ''; // phpcs:ignore ?></a></div><div class="art-coa-shop-body"><h3><?php echo esc_html($pfi->title ?: ''); ?></h3></div></article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <?php if ($certs): ?>
            <section class="art-coa-section">
                <h2>گواهی‌های اصالت</h2>
                <ul class="art-coa-link-list">
                    <?php foreach ($certs as $c): ?>
                        <li>
                            <a href="<?php echo esc_url(home_url('/art-coa/certificate/' . rawurlencode($c->serial_number))); ?>">
                                <code><?php echo esc_html($c->serial_number); ?></code>
                            </a>
                            <span class="art-badge art-badge-<?php echo esc_attr($c->status); ?>"><?php echo esc_html($c->status); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_curator(string $slug): void
    {
        $curator = Curator::get_by_slug($slug);
        if (!$curator || ($curator->status !== 'active' && !current_user_can('manage_options'))) {
            echo '<p class="art-coa-empty">' . esc_html(I18n::is_fa() ? 'کیوریتور پیدا نشد.' : 'Curator not found.') . '</p>';
            return;
        }
        global $wpdb;
        $exhibitions = $wpdb->get_results($wpdb->prepare(
            'SELECT e.*, g.name AS gallery_name, g.slug AS gallery_slug
             FROM ' . Database::table('exhibitions') . ' e
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = e.gallery_id
             WHERE e.curator_id = %d AND e.status IN (\'active\', \'published\')
             ORDER BY e.start_date DESC, e.id DESC LIMIT 60',
            (int) $curator->id
        )) ?: [];
        $counts = $wpdb->get_row($wpdb->prepare(
            'SELECT COUNT(*) AS exhibitions, COUNT(DISTINCT gallery_id) AS venues
             FROM ' . Database::table('exhibitions') . '
             WHERE curator_id = %d AND status IN (\'active\', \'published\')',
            (int) $curator->id
        ));
        $display_name = (string) $curator->display_name;
        $links = [
            'website' => $curator->website ?? '',
            'instagram_url' => $curator->instagram_url ?? '',
            'linkedin_url' => $curator->linkedin_url ?? '',
        ];
        ?>
        <div class="art-coa-profile art-coa-curator-profile">
            <nav class="art-coa-crumbs" aria-label="breadcrumb"><a href="<?php echo esc_url(home_url('/art-coa/curators')); ?>"><?php echo esc_html(I18n::t('curators')); ?></a><span aria-hidden="true">›</span><span><?php echo esc_html($display_name); ?></span></nav>
            <?php echo self::cover($curator); ?>
            <header class="art-coa-profile-header">
                <?php echo Media::img($curator, 'avatar', 'medium', ['class' => 'art-coa-avatar', 'alt' => $display_name, 'loading' => 'eager']); ?>
                <div>
                    <p class="art-coa-eyebrow"><?php echo esc_html(I18n::t('curator')); ?></p>
                    <h1><?php echo esc_html($display_name); ?></h1>
                    <?php if (!empty($curator->specialties)): ?><p class="art-coa-curator-specialties"><?php echo esc_html($curator->specialties); ?></p><?php endif; ?>
                    <?php if (!empty($curator->affiliation)): ?><p class="meta"><?php echo esc_html(I18n::is_fa() ? 'وابستگی سازمانی' : 'Affiliation'); ?>: <?php echo esc_html($curator->affiliation); ?></p><?php endif; ?>
                    <?php if (!empty($curator->email) && is_email($curator->email) && apply_filters('art_coa_curator_public_contact', true, $curator)): ?><p class="meta">✉ <a href="<?php echo esc_url('mailto:' . antispambot((string) $curator->email)); ?>"><?php echo esc_html(antispambot((string) $curator->email)); ?></a></p><?php endif; ?>
                    <?php if (!empty($curator->phone) && apply_filters('art_coa_curator_public_contact', true, $curator)): ?><p class="meta">☎ <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $curator->phone)); ?>"><?php echo esc_html($curator->phone); ?></a></p><?php endif; ?>
                    <div class="art-coa-curator-socials">
                        <?php foreach ($links as $service => $url): if ($url): ?>
                            <a class="art-coa-chip" href="<?php echo esc_url($url); ?>" target="_blank" rel="me noopener noreferrer"><?php echo esc_html(['website' => I18n::t('website'), 'instagram_url' => 'Instagram', 'linkedin_url' => 'LinkedIn'][$service]); ?></a>
                        <?php endif; endforeach; ?>
                    </div>
                    <div class="art-coa-artist-actions"><?php echo \WPArtCOA\Follows::button_html('curator', (int) $curator->id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </div>
            </header>
            <?php self::stats_bar([
                I18n::t('exhibitions') => (int) ($counts->exhibitions ?? 0),
                (I18n::is_fa() ? 'فضاهای همکاری' : 'Partner venues') => (int) ($counts->venues ?? 0),
                (I18n::is_fa() ? 'سابقهٔ حرفه‌ای (سال)' : 'Years of experience') => (int) ($curator->years_experience ?? 0),
            ]); ?>
            <?php echo \WPArtCOA\Follows::followers_html('curator', (int) $curator->id, ['avatars' => 10, 'show_button' => false, 'layout' => 'inline']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php if (!empty($curator->curatorial_statement)): ?><section class="art-coa-section"><h2><?php echo esc_html(I18n::is_fa() ? 'رویکرد کیوریتوری' : 'Curatorial statement'); ?></h2><div class="art-coa-bio"><?php echo wpautop(esc_html($curator->curatorial_statement)); ?></div></section><?php endif; ?>
            <?php if (!empty($curator->bio)): ?><section class="art-coa-section"><h2><?php echo esc_html(I18n::is_fa() ? 'دربارهٔ کیوریتور' : 'Biography'); ?></h2><div class="art-coa-bio"><?php echo wp_kses_post(wpautop(self::clean_text($curator->bio))); ?></div></section><?php endif; ?>
            <?php if (!empty($curator->education) || !empty($curator->credentials) || !empty($curator->languages)): ?>
                <section class="art-coa-section"><h2><?php echo esc_html(I18n::is_fa() ? 'سوابق حرفه‌ای' : 'Professional background'); ?></h2><dl class="art-coa-facts">
                    <?php if (!empty($curator->education)): ?><dt><?php echo esc_html(I18n::is_fa() ? 'تحصیلات و آموزش' : 'Education'); ?></dt><dd><?php echo nl2br(esc_html($curator->education)); ?></dd><?php endif; ?>
                    <?php if (!empty($curator->credentials)): ?><dt><?php echo esc_html(I18n::is_fa() ? 'پروژه‌ها و دستاوردها' : 'Selected projects and credentials'); ?></dt><dd><?php echo nl2br(esc_html($curator->credentials)); ?></dd><?php endif; ?>
                    <?php if (!empty($curator->languages)): ?><dt><?php echo esc_html(I18n::is_fa() ? 'زبان‌ها' : 'Languages'); ?></dt><dd><?php echo esc_html($curator->languages); ?></dd><?php endif; ?>
                </dl></section>
            <?php endif; ?>
            <section class="art-coa-section"><h2><?php echo esc_html(I18n::is_fa() ? 'نمایشگاه‌های کیوریت‌شده' : 'Curated exhibitions'); ?></h2><div class="art-coa-cards art-coa-cards--exhibitions"><?php echo Catalog::cards_html('exhibitions', $exhibitions); // phpcs:ignore ?></div></section>
        </div>
        <?php
    }

    private static function render_curators_list(): void
    {
        global $wpdb;
        $limit = 12;
        $page = max(1, absint($_GET['ac_page'] ?? 1));
        $search_raw = isset($_GET['ac_q']) && is_scalar($_GET['ac_q']) ? wp_unslash($_GET['ac_q']) : '';
        $search = sanitize_text_field((string) $search_raw);
        $search = \Artiey_AET_Text::substr($search, 0, 100);
        $table = Database::table('curators');
        $where = "WHERE status = 'active'";
        $params = [];
        if ($search !== '') {
            $where .= ' AND (display_name LIKE %s OR specialties LIKE %s OR affiliation LIKE %s OR bio LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params = [$like, $like, $like, $like];
        }
        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
        $count_query = $params ? $wpdb->prepare($count_sql, $params) : $count_sql;
        $total = (int) $wpdb->get_var($count_query);
        $pages = (int) ceil($total / $limit);
        $page = min($page, max(1, $pages));
        $sql = "SELECT * FROM {$table} {$where} ORDER BY display_name ASC LIMIT %d OFFSET %d";
        $items = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [$limit, ($page - 1) * $limit]))) ?: [];
        echo '<div class="art-coa-curators-directory">';
        echo '<h1>' . esc_html(I18n::t('curators')) . '</h1>';
        self::stats_bar([I18n::t('curators') => $total]);
        echo '<form class="art-coa-curators-filters" method="get" action="' . esc_url(home_url('/art-coa/curators/')) . '"><label><span>' . esc_html(I18n::is_fa() ? 'جستجو در نام، تخصص یا وابستگی' : 'Search name, specialty or affiliation') . '</span><input type="search" name="ac_q" value="' . esc_attr($search) . '" maxlength="100"></label><button class="art-coa-btn" type="submit">' . esc_html(I18n::is_fa() ? 'جستجو' : 'Search') . '</button></form>';
        if (!$items) {
            echo '<p class="art-coa-empty">' . esc_html(I18n::is_fa() ? 'کیوریتوری با این مشخصات پیدا نشد.' : 'No curators matched your search.') . '</p>';
        } else {
            echo '<div class="art-coa-curator-grid">';
            foreach ($items as $item) {
                $name = (string) $item->display_name;
                echo '<a class="art-coa-curator-card" href="' . esc_url(home_url('/art-coa/curator/' . $item->slug)) . '">';
                echo '<span class="art-coa-curator-card-avatar">' . (Media::img($item, 'avatar', 'medium', ['alt' => $name, 'loading' => 'lazy']) ?: '<span aria-hidden="true">' . esc_html(\Artiey_AET_Text::substr($name, 0, 1)) . '</span>') . '</span>';
                echo '<span class="art-coa-curator-card-content"><strong>' . esc_html($name) . '</strong>';
                if ($item->specialties): echo '<span>' . esc_html($item->specialties) . '</span>'; endif;
                if ($item->affiliation): echo '<small>' . esc_html($item->affiliation) . '</small>'; endif;
                if ($item->bio): echo '<span class="art-coa-curator-card-bio">' . esc_html(wp_trim_words(wp_strip_all_tags($item->bio), 20)) . '</span>'; endif;
                echo '</span></a>';
            }
            echo '</div>';
        }
        if ($pages > 1) {
            echo '<nav class="art-coa-curator-pager" aria-label="' . esc_attr(I18n::is_fa() ? 'صفحه‌بندی کیوریتورها' : 'Curator pages') . '">';
            for ($p = 1; $p <= $pages; $p++) {
                echo '<a' . ($p === $page ? ' class="is-current" aria-current="page"' : '') . ' href="' . esc_url(add_query_arg(['ac_q' => $search, 'ac_page' => $p], home_url('/art-coa/curators/'))) . '">' . esc_html(Calendar::to_persian_digits((string) $p)) . '</a>';
            }
            echo '</nav>';
        }
        echo '</div>';
    }

    private static function render_gallery(string $slug): void
    {
        $gallery = Gallery::get_by_slug($slug);
        if (!$gallery || ($gallery->status !== 'active' && !current_user_can('manage_options'))) {
            echo '<p class="art-coa-empty">گالری یافت نشد.</p>';
            return;
        }
        $stats = Stats::gallery((int) $gallery->id, !current_user_can('manage_options'));
        global $wpdb;
        \WPArtCOA\Entities\Exhibition::maybe_add_columns();
        $exhibitions = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Database::table('exhibitions') . " WHERE gallery_id = %d AND status IN ('published','active') ORDER BY start_date DESC LIMIT 30",
            $gallery->id
        )) ?: [];
        foreach ($exhibitions as $exr) {
            $exr->gallery_name = $gallery->name;
            $exr->gallery_slug = $gallery->slug;
        }
        $artworks = $wpdb->get_results($wpdb->prepare(
            'SELECT a.*, ar.display_name AS artist_name, ar.slug AS artist_slug, ar.first_name, ar.last_name
             FROM ' . Database::table('artworks') . ' a
             LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
             WHERE a.gallery_id = %d' . self::public_clause('a') . ' ORDER BY a.id DESC LIMIT 40',
            ...array_merge([(int) $gallery->id], self::public_params())
        )) ?: [];
        $artists = $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT ar.* FROM ' . Database::table('artists') . ' ar
             INNER JOIN ' . Database::table('artworks') . ' a ON a.artist_id = ar.id
             WHERE a.gallery_id = %d AND ar.status = \'active\'' . self::public_clause('a') . ' ORDER BY ar.display_name ASC LIMIT 40',
            ...array_merge([(int) $gallery->id], self::public_params())
        )) ?: [];
        ?>
        <div class="art-coa-profile art-coa-gallery-profile">
            <?php echo self::cover($gallery); ?>
            <header class="art-coa-profile-header">
                <?php echo Media::img($gallery, 'logo', 'medium', ['class' => 'art-coa-avatar art-coa-logo', 'alt' => (string) $gallery->name, 'loading' => 'eager']); ?>
                <div>
                    <p class="art-coa-eyebrow">گالری</p>
                    <h1><?php echo esc_html($gallery->name); ?></h1>
                    <div class="aap-s-1cb660"><?php echo \WPArtCOA\Follows::button_html('gallery', (int) $gallery->id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <p class="meta"><?php echo esc_html(trim(($gallery->city ?: '') . ' ' . ($gallery->country ?: ''))); ?></p>
                    <?php if ($gallery->email): ?><p class="meta">✉ <?php echo esc_html($gallery->email); ?></p><?php endif; ?>
                    <?php if ($gallery->phone): ?><p class="meta">☎ <?php echo esc_html($gallery->phone); ?></p><?php endif; ?>
                    <?php if ($gallery->address): ?><p class="meta">📍 <?php echo esc_html($gallery->address); ?></p><?php endif; ?>
                    <?php if ($gallery->website): ?>
                        <p><a href="<?php echo esc_url($gallery->website); ?>" target="_blank" rel="noopener"><?php echo esc_html(\WPArtCOA\I18n::t('website')); ?></a></p>
                    <?php endif; ?>
                </div>
            </header>
            <?php self::stats_bar([
                \WPArtCOA\I18n::t('artworks') => $stats['artworks'],
                \WPArtCOA\I18n::t('exhibitions') => $stats['exhibitions'],
                \WPArtCOA\I18n::t('artists') => $stats['artists'],
                \WPArtCOA\I18n::t('certificates') => $stats['certs'],
            ]); ?>
            <?php echo \WPArtCOA\Follows::followers_html('gallery', (int) $gallery->id, ['avatars' => 10, 'show_button' => false, 'layout' => 'inline']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php if (apply_filters('art_coa_profile_charts', true, 'gallery', $gallery)) { echo \WPArtCOA\ChartKit::gallery_panel($gallery); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ChartKit ?>
            <?php } ?>
            <?php \WPArtCOA\ProfileFields::render('galleries', $gallery); ?>
            <?php if (!empty($gallery->short_bio) && !$gallery->about): ?><p class="art-coa-lead"><?php echo esc_html($gallery->short_bio); ?></p><?php endif; ?>
            <?php if ($gallery->about): ?>
                <section class="art-coa-section"><h2>درباره گالری</h2><div class="art-coa-bio"><?php echo wp_kses_post(wpautop(self::clean_text($gallery->about))); ?></div></section>
            <?php endif; ?>
            <?php if ($gallery->address): ?>
                <section class="art-coa-section"><h2>آدرس</h2><p><?php echo esc_html($gallery->address); ?></p></section>
            <?php endif; ?>
            <section class="art-coa-section">
                <h2><?php echo esc_html(\WPArtCOA\I18n::t('stats_ecosystem')); ?></h2>
                <?php
                $gallery_tree = \WPArtCOA\Hierarchy::gallery_tree((int) $gallery->id);
                if (!current_user_can('manage_options')) { $gallery_tree = \WPArtCOA\Hierarchy::public_tree($gallery_tree, 'gallery'); }
                echo \WPArtCOA\Hierarchy::render_html($gallery_tree, 'gallery');
                ?>
            </section>
            <section class="art-coa-section">
                <h2>نمایشگاه‌ها</h2>
                <div class="art-coa-cards art-coa-cards--exhibitions">
                    <?php echo Catalog::cards_html('exhibitions', $exhibitions); // phpcs:ignore ?>
                </div>
            </section>
            <section class="art-coa-section">
                <h2>هنرمندان نمایندگی‌شده</h2>
                <div class="art-coa-chip-list">
                    <?php if (!$artists): ?>
                        <span class="art-coa-empty">—</span>
                    <?php else: foreach ($artists as $ar): ?>
                        <a class="art-coa-chip" href="<?php echo esc_url(ContentSync::artist_url((string) $ar->slug)); ?>">
                            <?php echo esc_html($ar->display_name ?: ($ar->first_name . ' ' . $ar->last_name)); ?>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </section>
            <section class="art-coa-section">
                <h2>آثار</h2>
                <div class="art-coa-cards art-coa-cards--artworks">
                    <?php echo Catalog::cards_html('artworks', $artworks); // phpcs:ignore ?>
                </div>
            </section>
        </div>
        <?php
    }

    private static function render_exhibition(string $slug): void
    {
        global $wpdb;
        \WPArtCOA\Entities\Exhibition::maybe_add_columns();
        $ex = $wpdb->get_row($wpdb->prepare(
            'SELECT e.*, g.name AS gallery_name, g.slug AS gallery_slug, g.logo_url, g.logo_id,
                    c.display_name AS curator_name, c.slug AS curator_slug
             FROM ' . Database::table('exhibitions') . ' e
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = e.gallery_id
             LEFT JOIN ' . Database::table('curators') . ' c ON c.id = e.curator_id
             WHERE e.slug = %s',
            $slug
        ));
        $is_admin = current_user_can('manage_options');
        if (!$ex || (!in_array($ex->status, ['published', 'active'], true) && !$is_admin)) {
            echo '<p class="art-coa-empty">' . esc_html(I18n::t('not_found')) . '</p>';
            return;
        }
        if (empty($ex->event_type)) {
            $meta = get_option('art_coa_ex_meta_' . $ex->id, []);
            $ex->event_type = $meta['event_type'] ?? 'exhibition';
            $ex->artist_label = $meta['artist_label'] ?? '';
            $ex->subtitle = $meta['subtitle'] ?? ($ex->subtitle ?? '');
        }
        $artworks = $wpdb->get_results($wpdb->prepare(
            'SELECT a.*, ar.display_name AS artist_name, ar.slug AS artist_slug, ar.first_name, ar.last_name
             FROM ' . Database::table('exhibition_artworks') . ' ea
             INNER JOIN ' . Database::table('artworks') . ' a ON a.id = ea.artwork_id
             LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
             WHERE ea.exhibition_id = %d' . self::public_clause('a') . '
             ORDER BY ea.sort_order ASC, a.id ASC',
            ...array_merge([(int) $ex->id], self::public_params())
        )) ?: [];
        $artists = \WPArtCOA\Entities\Exhibition::related_artists((int) $ex->id);
        $photos  = Media::gallery_items($ex->gallery_ids ?? '');
        $poster_full = Media::url($ex, 'poster', 'full');
        $phase  = Catalog::phase($ex);
        $phase_labels = [0 => I18n::t('exhibitions_ongoing'), 1 => I18n::t('exhibitions_upcoming'), 2 => I18n::t('exhibitions_past')];
        $type_labels = ['exhibition' => I18n::t('exhibition'), 'artfair' => I18n::t('artfair'), 'auction' => I18n::t('auction')];
        ?>
        <div class="art-coa-profile art-coa-exhibition-profile">
            <?php if (!in_array($ex->status, ['published', 'active'], true)): ?>
                <div class="art-coa-result invalid"><p><?php echo esc_html(I18n::t('cat_not_public')); ?> (<?php echo esc_html($ex->status); ?>)</p></div>
            <?php endif; ?>
            <div class="art-coa-ex-hero<?php echo $poster_full ? ' has-poster' : ''; ?>">
                <?php if ($poster_full): ?>
                    <figure class="art-coa-ex-poster">
                        <a href="<?php echo esc_url(Media::url($ex, 'poster', 'large')); ?>" data-art-lightbox="ex" aria-label="<?php echo esc_attr(I18n::t('cat_view_poster')); ?>">
                            <?php echo Media::img($ex, 'poster', 'large', ['alt' => (string) $ex->title, 'loading' => 'eager']); ?>
                        </a>
                    </figure>
                <?php endif; ?>
                <div class="art-coa-ex-info">
                    <p class="art-coa-eyebrow">
                        <?php echo esc_html($type_labels[$ex->event_type] ?? I18n::t('event')); ?>
                        <span class="art-coa-c-badge phase-<?php echo (int) $phase; ?> is-inline"><?php echo esc_html($phase_labels[$phase]); ?></span>
                    </p>
                    <h1><?php echo esc_html($ex->title); ?></h1>
                    <?php if (!empty($ex->subtitle)): ?><p class="art-coa-ex-sub"><?php echo esc_html($ex->subtitle); ?></p><?php endif; ?>
                    <dl class="art-coa-facts">
                        <?php if (!empty($ex->artist_label)): ?>
                            <dt><?php echo esc_html(I18n::t('artist')); ?></dt><dd><?php echo esc_html($ex->artist_label); ?></dd>
                        <?php endif; ?>
                        <?php if ($ex->start_date): ?>
                            <dt><?php echo esc_html(I18n::t('date')); ?></dt>
                            <dd><?php echo esc_html(\WPArtCOA\Dates::format($ex->start_date)); ?><?php echo $ex->end_date ? ' — ' . esc_html(\WPArtCOA\Dates::format($ex->end_date)) : ''; ?></dd>
                        <?php endif; ?>
                        <?php if ($ex->gallery_name): ?>
                            <dt><?php echo esc_html(I18n::t('gallery')); ?></dt>
                            <dd><a href="<?php echo esc_url(home_url('/art-coa/gallery/' . $ex->gallery_slug)); ?>"><?php echo esc_html($ex->gallery_name); ?></a></dd>
                        <?php endif; ?>
                        <?php if ($ex->curator_name): ?>
                            <dt><?php echo esc_html(I18n::t('curator')); ?></dt><dd><?php if (!empty($ex->curator_slug)): ?><a href="<?php echo esc_url(home_url('/art-coa/curator/' . $ex->curator_slug)); ?>"><?php endif; ?><?php echo esc_html($ex->curator_name); ?><?php if (!empty($ex->curator_slug)): ?></a><?php endif; ?></dd>
                        <?php endif; ?>
                    </dl>
                    <?php if ($artists): ?>
                        <div class="art-coa-chip-list">
                            <?php foreach ($artists as $ar):
                                $nm = $ar->display_name ?: trim(($ar->first_name ?? '') . ' ' . ($ar->last_name ?? '')); ?>
                                <a class="art-coa-chip" href="<?php echo esc_url(ContentSync::artist_url((string) $ar->slug)); ?>"><?php echo esc_html($nm); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <p class="art-coa-actions"><button type="button" class="art-coa-btn art-coa-btn--ghost" data-art-copy="<?php echo esc_attr(home_url('/art-coa/exhibition/' . $ex->slug)); ?>"><?php echo esc_html(I18n::t('cat_copy_link')); ?></button></p>
                </div>
            </div>
            <?php \WPArtCOA\ProfileFields::render('exhibitions', $ex); ?>
            <?php if ($ex->description): ?>
                <section class="art-coa-section"><h2><?php echo esc_html(I18n::t('about')); ?></h2><div class="art-coa-bio"><?php echo wp_kses_post(wpautop(self::clean_text($ex->description))); ?></div></section>
            <?php endif; ?>
            <?php if ($photos): ?>
                <section class="art-coa-section">
                    <h2><?php echo esc_html(I18n::t('cat_photos')); ?></h2>
                    <div class="art-coa-photos">
                        <?php foreach ($photos as $ph): ?>
                            <a href="<?php echo esc_url($ph['full']); ?>" data-art-lightbox="ex"><img src="<?php echo esc_url($ph['thumb']); ?>" alt="<?php echo esc_attr($ph['alt']); ?>" loading="lazy"></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <section class="art-coa-section">
                <h2><?php echo esc_html(I18n::t('cat_exhibition_works')); ?> (<?php echo esc_html(Catalog::n(count($artworks))); ?>)</h2>
                <div class="art-coa-cards art-coa-cards--artworks">
                    <?php if (!$artworks): ?>
                        <p class="art-coa-empty"><?php echo esc_html(I18n::t('cat_no_linked_works')); ?></p>
                    <?php else: echo Catalog::cards_html('artworks', $artworks); // phpcs:ignore
                    endif; ?>
                </div>
            </section>
        </div>
        <?php
    }

    private static function render_collector(string $slug): void
    {
        global $wpdb;
        $col = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('collectors') . ' WHERE slug = %s',
            $slug
        ));
        if (!$col) {
            echo '<p class="art-coa-empty">مجموعه‌دار یافت نشد.</p>';
            return;
        }
        if ((! (int) $col->is_public || ($col->status ?? 'active') !== 'active') && !current_user_can('manage_options')) {
            echo '<p class="art-coa-empty">مجموعه‌دار یافت نشد.</p>';
            return;
        }
        $stats = Stats::collector((int) $col->id, !current_user_can('manage_options'));
        $artworks = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, ar.display_name AS artist_name, ar.slug AS artist_slug, ar.first_name, ar.last_name
             FROM " . Database::table('artworks') . " a
             LEFT JOIN " . Database::table('artists') . " ar ON ar.id = a.artist_id
             WHERE a.current_owner_type = 'collector' AND a.current_owner_id = %d" . self::public_clause('a') . "
             ORDER BY a.id DESC LIMIT 50",
            ...array_merge([(int) $col->id], self::public_params())
        )) ?: [];
        ?>
        <div class="art-coa-profile">
            <header class="art-coa-profile-header">
                <?php echo Media::img($col, 'avatar', 'medium', ['class' => 'art-coa-avatar', 'alt' => (string) $col->display_name, 'loading' => 'eager']); ?>
                <div>
                    <p class="art-coa-eyebrow">مجموعه‌دار</p>
                    <h1><?php echo esc_html($col->display_name); ?></h1>
                    <?php if ($col->collection_name): ?><p class="meta"><?php echo esc_html($col->collection_name); ?></p><?php endif; ?>
                    <div class="art-coa-artist-actions"><?php echo \WPArtCOA\Follows::button_html('collector', (int) $col->id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </div>
            </header>
            <?php $collector_stats = ['آثار مجموعه' => $stats['artworks'], 'گواهی‌های فعال' => $stats['certificates']]; if (current_user_can('manage_options')) { $collector_stats['خرید / انتقال'] = $stats['acquisitions']; } self::stats_bar($collector_stats); ?>
            <?php if ($col->bio): ?>
                <section class="art-coa-section"><h2>درباره</h2><div class="art-coa-bio"><?php echo wp_kses_post(wpautop(self::clean_text($col->bio))); ?></div></section>
            <?php endif; ?>
            <section class="art-coa-section">
                <h2><?php echo esc_html(\WPArtCOA\I18n::t('stats_ecosystem')); ?></h2>
                <?php
                $collector_tree = \WPArtCOA\Hierarchy::collector_tree((int) $col->id);
                if (!current_user_can('manage_options')) { $collector_tree = \WPArtCOA\Hierarchy::public_tree($collector_tree, 'collector'); }
                echo \WPArtCOA\Hierarchy::render_html($collector_tree, 'collector');
                ?>
            </section>
            <section class="art-coa-section">
                <h2>آثار مجموعه</h2>
                <div class="art-coa-cards art-coa-cards--artworks">
                    <?php echo Catalog::cards_html('artworks', $artworks); // phpcs:ignore ?>
                </div>
            </section>
        </div>
        <?php
    }

    private static function render_certificate(string $serial): void
    {
        \WPArtCOA\CertificateTemplate::maybe_migrate_gallery_columns();
        echo \WPArtCOA\CertificateTemplate::render($serial);
    }


    private static function render_artists_list(): void
    {
        $g = Stats::global();
        echo '<h1>' . esc_html(I18n::t('artists')) . '</h1>';
        self::stats_bar([I18n::t('artists') => $g['artists'], I18n::t('artworks') => $g['artworks']]);
        echo Catalog::render_widget('artists', ['per_page' => 18]); // phpcs:ignore
    }

    private static function render_galleries_list(): void
    {
        $g = Stats::global();
        echo '<h1>' . esc_html(I18n::t('galleries')) . '</h1>';
        self::stats_bar([I18n::t('galleries') => $g['galleries'], I18n::t('exhibitions') => $g['exhibitions']]);
        echo Catalog::render_widget('galleries', ['per_page' => 12]); // phpcs:ignore
    }

    private static function render_exhibitions_list(): void
    {
        echo '<h1>' . esc_html(I18n::t('exhibitions')) . '</h1>';
        echo Catalog::render_widget('exhibitions', ['per_page' => 12]); // phpcs:ignore
    }

    private static function render_artworks_list(): void
    {
        $g = Stats::global();
        echo '<h1>' . esc_html(I18n::t('artworks')) . '</h1>';
        self::stats_bar([I18n::t('artworks') => $g['artworks'], I18n::t('ecosystem_active') => $g['active_certs'], I18n::t('cat_transfers') => $g['transfers']]);
        echo Catalog::render_widget('artworks', ['per_page' => 12]); // phpcs:ignore
    }

    /* ---------- Artwork detail ---------- */

    private static function render_artwork(string $slug): void
    {
        global $wpdb;
        $art = Artwork::get_by_slug($slug);
        $is_admin = current_user_can('manage_options');
        if (!$art || (!in_array($art->status, Artwork::public_statuses(), true) && !$is_admin)) {
            echo '<p class="art-coa-empty">' . esc_html(I18n::t('not_found')) . '</p>';
            return;
        }

        $name   = Catalog::artist_name($art);
        $extra  = Media::gallery_items($art->gallery_ids ?? '');
        $main_f = Media::url($art, 'image', 'large');
        $sold   = $art->status === 'sold';
        $cert   = $wpdb->get_row($wpdb->prepare(
            'SELECT serial_number, issued_at_utc FROM ' . Database::table('certificates') . " WHERE artwork_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
            $art->id
        ));
        $shows = $wpdb->get_results($wpdb->prepare(
            'SELECT e.slug, e.title FROM ' . Database::table('exhibition_artworks') . ' ea
             INNER JOIN ' . Database::table('exhibitions') . " e ON e.id = ea.exhibition_id AND e.status IN ('published','active')
             WHERE ea.artwork_id = %d ORDER BY e.start_date DESC LIMIT 10",
            $art->id
        )) ?: [];
        $related = $wpdb->get_results($wpdb->prepare(
            'SELECT a.*, ar.display_name AS artist_name, ar.first_name, ar.last_name, ar.slug AS artist_slug
             FROM ' . Database::table('artworks') . ' a LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
             WHERE a.artist_id = %d AND a.id <> %d' . self::public_clause('a') . ' ORDER BY a.id DESC LIMIT 4',
            ...array_merge([(int) $art->artist_id, (int) $art->id], self::public_params())
        )) ?: [];
        ?>
        <div class="art-coa-profile art-coa-artwork-page">
            <nav class="art-coa-crumbs" aria-label="breadcrumb">
                <a href="<?php echo esc_url(home_url('/art-coa/artworks')); ?>"><?php echo esc_html(I18n::t('artworks')); ?></a>
                <span aria-hidden="true">›</span>
                <span><?php echo esc_html($art->title); ?></span>
            </nav>
            <?php if (!in_array($art->status, Artwork::public_statuses(), true)): ?>
                <div class="art-coa-result invalid"><p><?php echo esc_html(I18n::t('cat_not_public')); ?> (<?php echo esc_html($art->status); ?>)</p></div>
            <?php endif; ?>
            <div class="art-coa-aw">
                <div class="art-coa-aw-media">
                    <?php if ($main_f): ?>
                        <a class="art-coa-aw-main" href="<?php echo esc_url($main_f); ?>" data-art-lightbox="aw" aria-label="<?php echo esc_attr(I18n::t('cat_zoom')); ?>">
                            <?php echo Media::img($art, 'image', 'large', ['alt' => (string) $art->title, 'loading' => 'eager']); ?>
                        </a>
                        <?php if ($extra): ?>
                            <div class="art-coa-aw-thumbs">
                                <?php foreach ($extra as $ph): ?>
                                    <a href="<?php echo esc_url($ph['full']); ?>" data-art-lightbox="aw"><img src="<?php echo esc_url($ph['thumb']); ?>" alt="<?php echo esc_attr($ph['alt']); ?>" loading="lazy"></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="art-coa-aw-main is-empty"><span class="art-coa-c-ph" aria-hidden="true">🖼</span></div>
                    <?php endif; ?>
                </div>
                <div class="art-coa-aw-info">
                    <h1><?php echo esc_html($art->title); ?></h1>
                    <?php if ($name): ?>
                        <p class="art-coa-aw-artist"><a href="<?php echo esc_url(ContentSync::artist_url((string) $art->artist_slug)); ?>"><?php echo esc_html($name); ?></a></p>
                    <?php endif; ?>
                    <?php if ($sold): ?>
                        <p><span class="art-coa-c-badge is-sold is-inline"><?php echo esc_html(I18n::t('cat_sold')); ?></span></p>
                    <?php elseif (!empty($art->for_sale)): ?>
                        <p class="art-coa-aw-price">
                            <span class="art-coa-c-badge is-sale is-inline"><?php echo esc_html(I18n::t('for_sale')); ?></span>
                            <strong><?php echo esc_html(Shop::format_price($art->price ?? null, (string) ($art->currency ?? 'IRR'))); ?></strong>
                        </p>
                        <?php if (!empty($art->sale_note)): ?><p class="meta"><?php echo esc_html($art->sale_note); ?></p><?php endif; ?>
                        <?php $buy = WooBridge::buy_url($art); if ($buy): ?>
                            <p><a class="art-coa-btn" href="<?php echo esc_url($buy); ?>"><?php echo esc_html(I18n::t('wc_buy')); ?></a></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <dl class="art-coa-facts">
                        <?php if ($art->year_created): ?><dt><?php echo esc_html(I18n::t('year_created')); ?></dt><dd><?php echo esc_html(Catalog::n($art->year_created)); ?></dd><?php endif; ?>
                        <?php if ($art->medium): ?><dt><?php echo esc_html(I18n::t('medium')); ?></dt><dd><?php echo esc_html($art->medium); ?></dd><?php endif; ?>
                        <?php if ($art->dimensions): ?><dt><?php echo esc_html(I18n::t('dimensions')); ?></dt><dd><?php echo esc_html($art->dimensions); ?></dd><?php endif; ?>
                        <?php if (!empty($art->gallery_name)): ?>
                            <dt><?php echo esc_html(I18n::t('gallery')); ?></dt>
                            <dd><a href="<?php echo esc_url(home_url('/art-coa/gallery/' . $art->gallery_slug)); ?>"><?php echo esc_html($art->gallery_name); ?></a></dd>
                        <?php endif; ?>
                    </dl>
                    <?php if ($cert): ?>
                        <div class="art-coa-aw-cert">
                            <span aria-hidden="true">🎖️</span>
                            <div>
                                <strong><?php echo esc_html(I18n::t('cat_has_cert')); ?></strong>
                                <a href="<?php echo esc_url(home_url('/art-coa/certificate/' . rawurlencode($cert->serial_number))); ?>"><code><?php echo esc_html($cert->serial_number); ?></code></a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php \WPArtCOA\ProfileFields::render('artworks', $art); ?>
                    <?php if ($art->description): ?>
                        <div class="art-coa-bio"><?php echo wp_kses_post(wpautop(self::clean_text($art->description))); ?></div>
                    <?php endif; ?>
                    <?php \WPArtCOA\ProfileFields::render_sections('artworks', $art); ?>
                    <?php if ($shows): ?>
                        <h3><?php echo esc_html(I18n::t('exhibitions')); ?></h3>
                        <ul class="art-coa-link-list">
                            <?php foreach ($shows as $sh): ?>
                                <li><a href="<?php echo esc_url(home_url('/art-coa/exhibition/' . $sh->slug)); ?>"><?php echo esc_html($sh->title); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p class="art-coa-actions"><button type="button" class="art-coa-btn art-coa-btn--ghost" data-art-copy="<?php echo esc_attr(home_url('/art-coa/artwork/' . $art->slug)); ?>"><?php echo esc_html(I18n::t('cat_copy_link')); ?></button></p>
                </div>
            </div>
            <?php if ($related): ?>
                <section class="art-coa-section">
                    <h2><?php echo esc_html(I18n::t('cat_more_by', ['name' => $name])); ?></h2>
                    <div class="art-coa-cards art-coa-cards--artworks"><?php echo Catalog::cards_html('artworks', $related); // phpcs:ignore ?></div>
                </section>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ---------- helpers ---------- */

    /** Cover banner for artists / galleries (empty string if none). */
    private static function cover(object $row): string
    {
        $img = Media::img($row, 'cover', 'full', ['alt' => '', 'loading' => 'eager']);
        return $img ? '<div class="art-coa-cover">' . $img . '</div>' : '';
    }

    /** " AND alias.status IN (%s,..)" — public statuses only, unless an admin is viewing. */
    private static function public_clause(string $alias): string
    {
        if (current_user_can('manage_options')) {
            return '';
        }
        $n = count(Artwork::public_statuses());
        return ' AND ' . $alias . '.status IN (' . implode(',', array_fill(0, $n, '%s')) . ')';
    }

    /** @return string[] */
    private static function public_params(): array
    {
        return current_user_can('manage_options') ? [] : Artwork::public_statuses();
    }

    private static function stats_bar(array $items): void
    {
        echo '<div class="art-coa-stats-bar">';
        foreach ($items as $label => $val) {
            echo '<div class="art-coa-stat-pill"><span class="val">' . esc_html(Calendar::to_persian_digits((string) $val)) . '</span><span class="lbl">' . esc_html($label) . '</span></div>';
        }
        echo '</div>';
    }

    public static function url(string $type, string $slug): string
    {
        return home_url('/' . $type . '/' . $slug);
    }
}
