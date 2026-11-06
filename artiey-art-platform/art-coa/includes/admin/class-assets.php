<?php
declare(strict_types=1);

namespace WPArtCOA\Admin;

defined('ABSPATH') || exit;

final class Assets
{
    public static function enqueue(string $hook): void
    {
        if (strpos($hook, 'wp-art-coa') === false) {
            return;
        }

        wp_enqueue_style(
            'wp-art-coa-admin',
            WP_ART_COA_URL . 'assets/css/admin.css',
            [],
            defined('AET_VERSION') ? (string) AET_VERSION : WP_ART_COA_VERSION
        );
        wp_add_inline_style('wp-art-coa-admin', self::css());

        // Load the media modal only on screens that render image pickers.
        if (preg_match('/wp-art-coa-(artworks|artists|galleries|curators|collectors|certificates|exhibitions|auctions|calls)(-|$)/', $hook)) {
            wp_enqueue_media();
            wp_enqueue_script(
                'wp-art-coa-media',
                WP_ART_COA_URL . 'assets/js/admin-media.js',
                ['jquery', 'jquery-ui-sortable'],
                defined('AET_VERSION') ? (string) AET_VERSION : WP_ART_COA_VERSION,
                true
            );
            wp_localize_script('wp-art-coa-media', 'ArtCoaMedia', [
                'pick'   => \WPArtCOA\I18n::t('pick_image'),
                'use'    => \WPArtCOA\I18n::t('use_image'),
                'remove' => \WPArtCOA\I18n::t('remove_image'),
            ]);
        }

        // Chart.js only on main dashboard
        if ($hook === 'artiey-platform_page_wp-art-coa') {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                [],
                '4.4.1',
                true
            );
            wp_enqueue_script(
                'wp-art-coa-charts',
                WP_ART_COA_URL . 'assets/js/dashboard-charts.js',
                ['chart-js'],
                defined('AET_VERSION') ? (string) AET_VERSION : WP_ART_COA_VERSION,
                true
            );
        }
    }

    /** Shortcodes whose output needs the larger interactive front-end bundle. */
    private const DYNAMIC_SHORTCODES = [
        'art_coa_calendar', 'art_coa_timeline', 'art_coa_artwork_history',
        'art_coa_auction_timeline', 'art_coa_artist_bio', 'art_coa_directory',
        'art_coa_register', 'art_coa_member_dashboard', 'art_coa_member_profile', 'art_coa_portfolio',
        'art_coa_shop', 'art_coa_secondary', 'art_coa_catalog', 'art_coa_search',
        'art_coa_auction', 'art_coa_artist_portfolio_upload',
        'art_coa_calls', 'art_coa_follow_button', 'art_coa_my_follows', 'art_coa_sitemap',
    ];

    /** Shortcodes styled entirely by the small verification/certificate stylesheet. */
    private const LIGHT_SHORTCODES = ['art_coa_verify', 'art_coa_certificate', 'art_coa_certificate_full'];

    /** Match a group of registered shortcodes in one pass instead of rescanning page content for every tag. */
    private static function content_has_any_shortcode(string $content, array $tags): bool
    {
        if ($content === '' || strpos($content, '[') === false || !function_exists('get_shortcode_regex')) {
            return false;
        }
        $tags = array_values(array_filter($tags, 'shortcode_exists'));
        if (!$tags) {
            return false;
        }
        return (bool) preg_match('/' . get_shortcode_regex($tags) . '/s', $content);
    }

    public static function frontend(): void
    {
        $asset_version = defined('AET_VERSION') ? (string) AET_VERSION : WP_ART_COA_VERSION;
        wp_register_style('wp-art-coa-front', WP_ART_COA_URL . 'assets/css/frontend.css', [], $asset_version);
        wp_register_style('wp-art-coa-dynamic', WP_ART_COA_URL . 'assets/css/frontend-dynamic.css', ['wp-art-coa-front'], $asset_version);
        wp_register_style('wp-art-coa-member-forms', WP_ART_COA_URL . 'assets/css/member-forms.css', [], $asset_version);
        wp_register_script('wp-art-coa-charts', WP_ART_COA_URL . 'assets/js/charts.js', [], $asset_version, true);

        // Query-driven public routes always need the interactive bundle.
        $route_load = (bool) (
            get_query_var('art_coa_type') || get_query_var('art_coa_member') ||
            get_query_var('art_coa_portfolio') || get_query_var('art_coa_shop') ||
            get_query_var('art_coa_secondary') || get_query_var('art_coa_auction') ||
            get_query_var('art_coa_calls')
        );
        $load_dynamic = $route_load;
        $load_light   = $route_load;

        if (!$route_load && is_singular()) {
            $post = get_post();
            $content = ($post && isset($post->post_content)) ? (string) $post->post_content : '';
            $load_dynamic = self::content_has_any_shortcode($content, self::DYNAMIC_SHORTCODES);
            $load_light = $load_dynamic || self::content_has_any_shortcode($content, self::LIGHT_SHORTCODES);
        }

        // A serial supplied in the query string needs only the lightweight verifier CSS.
        if (!$load_light && !empty($_GET['art_coa_verify'])) {
            $load_light = true;
        }
        if (!$load_light) {
            return;
        }

        wp_add_inline_style(
            'wp-art-coa-front',
            \WPArtCOA\Branding::css_variables() . ( $load_dynamic ? self::front_css() : self::light_front_css() )
        );
        wp_enqueue_style('wp-art-coa-front');
        if (!$load_dynamic) {
            return;
        }

        // The 70 KB component stylesheet and interactive JS are not sent to a page
        // containing only a static certificate verifier/card.
        wp_enqueue_style('wp-art-coa-dynamic');
        $defer = ['in_footer' => true, 'strategy' => 'defer'];
        wp_enqueue_script('wp-art-coa-dynamic', WP_ART_COA_URL . 'assets/js/frontend-dynamic.js', [], $asset_version, $defer);
        wp_localize_script('wp-art-coa-dynamic', 'ArtCoaFront', [
            'rest'   => esc_url_raw(rest_url('art-coa/v1/')),
            'nonce'  => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'maxMb'  => \WPArtCOA\Media::max_mb(),
            'i18n'   => [
                'auSending' => \WPArtCOA\I18n::t('au_sending'),
                'auNetErr'  => \WPArtCOA\I18n::t('au_net_error'),
                'auOutbid'  => \WPArtCOA\I18n::t('au_outbid_now'),
                'loadMore'  => \WPArtCOA\I18n::t('cat_load_more'),
                'loading'   => \WPArtCOA\I18n::t('cat_loading'),
                'error'     => \WPArtCOA\I18n::t('cat_error'),
                'copied'    => \WPArtCOA\I18n::t('cat_copied'),
                'close'     => \WPArtCOA\I18n::t('close'),
                'gallery'   => \WPArtCOA\I18n::t('a11y_viewer'),
                'prev'      => \WPArtCOA\I18n::t('cat_prev'),
                'next'      => \WPArtCOA\I18n::t('cat_next'),
                'resultsCount' => \WPArtCOA\I18n::t('a11y_results'),
                'tooBig'    => \WPArtCOA\I18n::t('file_too_large', ['mb' => \WPArtCOA\Media::max_mb()]),
                'badType'   => \WPArtCOA\I18n::t('invalid_image'),
                'uploading' => \WPArtCOA\I18n::t('dz_uploading'),
                'verifying' => \WPArtCOA\I18n::t('cat_verifying'),
                'noResults' => \WPArtCOA\I18n::t('cat_empty'),
                'valid'     => \WPArtCOA\I18n::t('cert_valid'),
                'invalid'   => \WPArtCOA\I18n::t('cert_invalid'),
                'serial'    => \WPArtCOA\I18n::t('serial'),
                'artwork'   => \WPArtCOA\I18n::t('artwork'),
                'artist'    => \WPArtCOA\I18n::t('artist'),
                'owner'     => \WPArtCOA\I18n::t('owner'),
                'date'      => \WPArtCOA\I18n::t('date'),
            ],
        ]);
    }

    private static function css(): string
    {
        return '
        .art-coa-wrap h1{display:flex;align-items:center;gap:12px}
        .art-coa-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin:20px 0}
        .art-coa-stat{background:#fff;padding:22px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);text-align:center}
        .art-coa-stat .val{display:block;font-size:28px;font-weight:700;color:#2271b1}
        .art-coa-stat .lbl{display:block;margin-top:4px;color:#646970;font-size:13px}
        .art-coa-stat.success .val{color:#00a32a}
        .art-coa-stat-primary .val{color:#135e96}
        .art-coa-stat-sales .val{color:#b32d2e}
        .art-coa-dash-heading{font-size:15px;margin:22px 0 10px;color:#1d2327;font-weight:600}
        .art-coa-trend-box{margin:12px 0 20px;padding:14px 18px;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);font-size:14px}
        .art-coa-trend-box.trend-up{border-right:4px solid #00a32a}
        .art-coa-trend-box.trend-down{border-right:4px solid #d63638}
        .art-coa-trend-box.trend-flat{border-right:4px solid #646970}
        .art-coa-trend-arrow{font-weight:700;margin:0 6px}
        .trend-up .art-coa-trend-arrow{color:#00a32a}
        .trend-down .art-coa-trend-arrow{color:#d63638}
        .art-coa-charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:16px 0}
        .art-coa-chart-card{background:#fff;padding:16px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
        .art-coa-chart-card h3{margin:0 0 12px;font-size:14px}
        @media(max-width:960px){.art-coa-charts-grid{grid-template-columns:1fr}}

        .art-coa-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:8px 0 16px}
        .art-coa-box{background:#f0f6fc;border-right:4px solid #2271b1;padding:18px 20px;border-radius:6px;margin-top:16px}
        .art-coa-form{background:#fff;padding:22px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin:16px 0;max-width:640px}
        .art-coa-form .widefat{width:100%}
        .art-badge{display:inline-block;padding:2px 10px;border-radius:4px;font-size:12px;font-weight:600}
        .art-badge-active{background:#d1f2d9;color:#00a32a}
        .art-badge-draft{background:#f0f0f1;color:#646970}
        .art-badge-revoked{background:#fcf0f1;color:#d63638}
        @media(max-width:900px){.art-coa-stats{grid-template-columns:1fr 1fr}}
        ';
    }

    /** Critical CSS for the static certificate verifier/card; excludes calendars, catalogs and profile UI. */
    private static function light_front_css(): string
    {
        return '
        .art-coa-verify{max-width:720px;margin:30px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
        .art-coa-verify h2{margin-top:0}
        .art-coa-verify input[type=text]{width:100%;max-width:420px;padding:12px;font-family:monospace;direction:ltr;border:1px solid #c3c4c7;border-radius:6px}
        .art-coa-result{padding:20px;border-radius:8px;margin-top:18px}
        .art-coa-result.valid{background:#edfaef;border:2px solid #00a32a}
        .art-coa-result.invalid{background:#fcf0f1;border:2px solid #d63638}
        .art-coa-result table{width:100%;border-collapse:collapse;margin-top:12px}
        .art-coa-result td{padding:8px 10px;border-bottom:1px solid #e2e4e7}
        .art-coa-result td:first-child{width:140px;font-weight:600}
        .art-coa-cert-card{border:1px solid #e2e4e7;border-radius:10px;padding:20px;background:#fafafa}
        .art-coa-cert-card .serial{font-family:monospace;font-size:1.1em;letter-spacing:1px}
        ';
    }

    private static function front_css(): string
    {
        return '
        .art-coa-verify{max-width:720px;margin:30px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
        .art-coa-verify h2{margin-top:0}
        .art-coa-verify input[type=text]{width:100%;max-width:420px;padding:12px;font-family:monospace;direction:ltr;border:1px solid #c3c4c7;border-radius:6px}
        .art-coa-result{padding:20px;border-radius:8px;margin-top:18px}
        .art-coa-result.valid{background:#edfaef;border:2px solid #00a32a}
        .art-coa-result.invalid{background:#fcf0f1;border:2px solid #d63638}
        .art-coa-result table{width:100%;border-collapse:collapse;margin-top:12px}
        .art-coa-result td{padding:8px 10px;border-bottom:1px solid #e2e4e7}
        .art-coa-result td:first-child{width:140px;font-weight:600}
        .art-coa-cert-card{border:1px solid #e2e4e7;border-radius:10px;padding:20px;background:#fafafa}
        .art-coa-cert-card .serial{font-family:monospace;font-size:1.1em;letter-spacing:1px}

        .art-coa-calendar{max-width:1100px;margin:24px auto;font-family:Tahoma,Arial,sans-serif;background:#f7f7f8;border-radius:4px;overflow:hidden}
        .art-coa-cal-header{display:grid;grid-template-columns:repeat(7,1fr) 90px;background:#f0f0f1;border-bottom:1px solid #e0e0e0;text-align:center}
        .art-coa-cal-day{padding:12px 4px;border-left:1px solid #e8e8e8;position:relative}
        .art-coa-cal-day .wd{display:block;font-size:12px;color:#666}
        .art-coa-cal-day .num{display:block;font-size:22px;font-weight:700;color:#222;margin:4px 0}
        .art-coa-cal-day .mo{display:block;font-size:11px;color:#888}
        .art-coa-cal-day.is-today{background:#fff}
        .art-coa-cal-day.is-today::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;background:#e67e22}
        .art-coa-cal-label{display:flex;align-items:center;justify-content:center;font-size:13px;color:#555;background:#f0f0f1}
        .art-coa-cal-body{background:#fafafa;min-height:120px}
        .art-coa-cal-row{display:grid;grid-template-columns:repeat(7,1fr) 90px;min-height:56px;border-bottom:1px solid #eee;align-items:stretch}
        .art-coa-cal-bar{margin:6px 4px;background:#5a5a5a;color:#fff;border-radius:2px;padding:8px 12px;display:flex;align-items:center}
        .art-coa-cal-bar .bar-title{display:block;font-size:14px;font-weight:600}
        .art-coa-cal-bar .bar-sub{display:block;font-size:11px;opacity:.85;margin-top:2px}
        .art-coa-cal-gallery{display:flex;align-items:center;justify-content:center;padding:4px;border-right:1px solid #eee}
        .art-coa-cal-gallery .gal-logo{max-width:70px;max-height:40px;object-fit:contain}
        .art-coa-cal-gallery .gal-name{font-size:10px;color:#666;text-align:center;line-height:1.2}
        .art-coa-cal-footer{padding:16px;text-align:center;background:#fff}
        .art-coa-more-galleries{display:inline-block;padding:10px 28px;border:1px solid #ccc;border-radius:2px;color:#333;text-decoration:none;font-size:14px}
        .art-coa-empty{padding:24px;text-align:center;color:#888}

        .art-coa-timeline{max-width:720px;margin:40px auto;padding:0 16px;font-family:Tahoma,Arial,sans-serif;text-align:right}
        .art-coa-tl-title{font-size:28px;font-weight:700;margin:0 0 8px;display:inline-block}
        .art-coa-tl-title::after{content:"";display:block;width:48px;height:2px;background:#222;margin:8px 0 0 auto}
        .art-coa-tl-filters{display:flex;flex-wrap:wrap;gap:8px 0;justify-content:flex-end;margin:28px 0 24px;font-size:14px}
        .art-coa-tl-filter{color:#aaa;text-decoration:none;padding:0 10px;border-left:1px solid #ddd}
        .art-coa-tl-filter:last-child{border-left:none}
        .art-coa-tl-filter.is-active{color:#222;font-weight:600}
        .art-coa-tl-year{margin:20px 0 28px}
        .art-coa-tl-year-badge{display:inline-block;background:#3a3a3a;color:#fff;padding:10px 18px;font-size:18px;font-weight:700;border-radius:2px}
        .art-coa-tl-item{margin:0 0 22px}
        .art-coa-tl-item-title{font-size:16px;font-weight:600;margin:0;color:#333;line-height:1.6}
        .art-coa-tl-date{font-weight:400;color:#666}
        .art-coa-tl-sub{margin:4px 0 0;font-size:13px;color:#888}

        .art-coa-curators-directory{max-width:1100px;margin:0 auto}
        .art-coa-curators-filters{display:flex;align-items:flex-end;gap:12px;margin:18px 0 22px;padding:16px;border:1px solid #e2e4e7;border-radius:12px;background:#fff}
        .art-coa-curators-filters label{display:flex;flex:1;flex-direction:column;gap:6px;font-size:13px}
        .art-coa-curators-filters input{width:100%;min-height:42px;padding:8px 12px;border:1px solid #c3c4c7;border-radius:6px}
        .art-coa-curator-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px;margin:20px 0}
        .art-coa-curator-card{display:flex;align-items:flex-start;gap:16px;padding:20px;border:1px solid #e2e4e7;border-radius:12px;background:#fff;color:inherit!important;text-decoration:none!important;transition:transform .18s ease,box-shadow .18s ease}
        .art-coa-curator-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.08)}
        .art-coa-curator-card-avatar{display:flex;flex:0 0 76px;width:76px;height:76px;align-items:center;justify-content:center;overflow:hidden;border-radius:50%;background:#f0f2f5;color:#526174;font-size:26px;font-weight:700}
        .art-coa-curator-card-avatar img{width:100%;height:100%;object-fit:cover}
        .art-coa-curator-card-content{display:flex;min-width:0;flex-direction:column;gap:6px}
        .art-coa-curator-card-content strong{font-size:17px;color:var(--art-coa-primary,#1a365d)}
        .art-coa-curator-card-content small,.art-coa-curator-card-bio{color:#666;font-size:13px;line-height:1.7}
        .art-coa-curator-specialties{color:#526174}
        .art-coa-curator-socials{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
        .art-coa-facts{display:grid;grid-template-columns:minmax(130px,220px) 1fr;gap:10px 18px;margin:0}
        .art-coa-facts dt{font-weight:700;color:#526174}
        .art-coa-facts dd{margin:0;line-height:1.8;white-space:pre-line}
        .art-coa-curator-pager{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin:24px 0}
        .art-coa-curator-pager a{min-width:38px;padding:8px 12px;border:1px solid #e2e4e7;border-radius:7px;text-align:center;text-decoration:none}
        .art-coa-curator-pager a.is-current{background:var(--art-coa-primary,#1a365d);color:#fff}
        @media(max-width:600px){.art-coa-curators-filters{align-items:stretch;flex-direction:column}.art-coa-facts{grid-template-columns:1fr;gap:4px}.art-coa-facts dd{margin-bottom:12px}}        .art-coa-profile-wrap{max-width:1100px;margin:0 auto;padding:24px 16px;font-family:Tahoma,Arial,sans-serif}
        .art-coa-profile-header{display:flex;gap:20px;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap}
        .art-coa-avatar{width:96px;height:96px;object-fit:cover;border-radius:50%;background:#eee}
        .art-coa-logo{border-radius:8px;object-fit:contain;background:#fff;border:1px solid #eee}
        .art-coa-eyebrow{font-size:12px;color:#888;margin:0 0 4px;text-transform:uppercase;letter-spacing:1px}
        .art-coa-profile h1{margin:0 0 8px;font-size:28px}
        .art-coa-profile .meta{color:#666;font-size:14px;margin:4px 0}
        .art-coa-stats-bar{display:flex;flex-wrap:wrap;gap:12px;margin:20px 0;padding:16px;background:#f6f7f7;border-radius:10px}
        .art-coa-stat-pill{background:#fff;padding:12px 18px;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,.06);min-width:100px;text-align:center}
        .art-coa-stat-pill .val{display:block;font-size:22px;font-weight:700;color:#1d2327}
        .art-coa-stat-pill .lbl{display:block;font-size:12px;color:#646970;margin-top:2px}
        .art-coa-section{margin:28px 0}
        .art-coa-section h2{font-size:18px;margin:0 0 12px;border-bottom:1px solid #e2e4e7;padding-bottom:8px}
        .art-coa-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}
        .art-coa-card{background:#fff;border:1px solid #e2e4e7;border-radius:10px;padding:12px;overflow:hidden}
        .art-coa-card img{width:100%;height:160px;object-fit:cover;border-radius:6px;margin-bottom:8px}
        .art-coa-card h3{font-size:15px;margin:0 0 4px}
        .art-coa-card-link{text-decoration:none;color:inherit;display:block;transition:box-shadow .15s}
        .art-coa-card-link:hover{box-shadow:0 4px 14px rgba(0,0,0,.08)}
        .art-coa-link-list{list-style:none;margin:0;padding:0}
        .art-coa-link-list li{padding:10px 0;border-bottom:1px solid #eee}
        .art-coa-chip-list{display:flex;flex-wrap:wrap;gap:8px}
        .art-coa-chip{display:inline-block;padding:6px 12px;background:#f0f0f1;border-radius:20px;text-decoration:none;color:#1d2327;font-size:13px}
        .art-coa-chip:hover{background:#dcdcde}
        .art-coa-dir-links{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0}
        .art-coa-dir-links a{padding:8px 14px;background:#2271b1;color:#fff;border-radius:6px;text-decoration:none;font-size:13px}
        .art-coa-register,.art-coa-member-dash{max-width:640px;margin:30px auto;padding:24px;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
        .art-coa-reg-role{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;border:none;margin:0 0 20px;padding:0}
        .art-coa-role-card{display:flex;align-items:center;gap:8px;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;cursor:pointer;background:#fafafa}
        .art-coa-role-card:has(input:checked){border-color:#2271b1;background:#f0f6fc}
        .art-coa-reg-form .widefat,.art-coa-reg-form input[type=text],.art-coa-reg-form input[type=email],.art-coa-reg-form input[type=password],.art-coa-reg-form input[type=url],.art-coa-reg-form textarea{width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px}
        .art-coa-reg-form h3{margin:20px 0 10px;font-size:16px}
        .art-coa-reg-notice{text-align:center;padding:16px}
        .art-coa-hierarchy{margin:16px 0;padding:16px;background:#fafafa;border-radius:8px;border:1px solid #e2e4e7}
        .art-coa-h-root{margin:0 0 12px;font-size:18px}
        .art-coa-tree{list-style:none;margin:0;padding:0 0 0 12px;border-right:2px solid #dcdcde}
        .art-coa-tree ul{list-style:none;margin:6px 0;padding:0 0 0 16px;border-right:1px solid #e8e8e8}
        .art-coa-tree li{margin:6px 0;line-height:1.5}
        .art-coa-tree a{text-decoration:none}
        .art-coa-tree a:hover{text-decoration:underline}
        .art-coa-history{font-size:12px;color:#666}
        .art-coa-follow-wrap{display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap;margin:8px 0}
        .art-coa-follow-form{display:inline}
        .art-coa-follow-btn.is-following{background:#f0f0f1;border-color:#c3c4c7;color:#1d2327}
        .art-coa-follow-count{font-size:13px;color:#646970}
        .art-coa-my-follows{margin-top:28px;padding:16px;background:#f6f7f7;border-radius:10px}
        .art-coa-my-follows h3{margin-top:0}
        .art-coa-my-follows li{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:10px}

        .art-coa-deadline-box{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 16px;border-radius:8px;margin:14px 0;font-size:14px}
        .art-coa-deadline-box.open{background:#edfaef;border:1px solid #00a32a;color:#1e4620}
        .art-coa-deadline-box.closed{background:#fcf0f1;border:1px solid #d63638}
        .art-coa-countdown{font-weight:700;font-variant-numeric:tabular-nums;direction:ltr}
        .art-coa-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px}
        .art-coa-artist-call-form{max-width:720px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:10px}
        .art-coa-deadline-note{font-size:13px;color:#646970}
        @media(max-width:600px){.art-coa-form-grid{grid-template-columns:1fr}}

        .art-coa-calls-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;margin-top:16px}
        .art-coa-call-card{background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.05);display:flex;flex-direction:column}
        .art-coa-call-card.featured{border-color:var(--art-coa-accent,#c9a227)}
        .art-coa-call-card-img img{width:100%;height:160px;object-fit:cover;display:block}
        .art-coa-call-card-body{padding:14px;display:flex;flex-direction:column;gap:6px;flex:1}
        .art-coa-call-card-body h3{margin:4px 0;font-size:16px}
        .art-coa-calls-filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
        .art-coa-call-cover img{width:100%;max-height:320px;object-fit:cover;border-radius:10px;margin:12px 0}
        .art-coa-call-meta-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin:16px 0;padding:14px;background:#f6f7f7;border-radius:8px}
        .art-coa-call-meta-grid .lbl{display:block;font-size:11px;color:#666}
        .art-coa-call-meta-grid .val{font-weight:600}
        .art-coa-prose{line-height:1.7}

        .art-coa-auction-layout{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:16px 0}
        .art-coa-auction-media img{width:100%;border-radius:10px;object-fit:contain;max-height:420px;background:#f6f7f7}
        .art-coa-auction-media .art-coa-aw-thumbs{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
        .art-coa-auction-media .art-coa-aw-thumbs img{width:76px;height:76px;object-fit:cover;border-radius:7px}
        .art-coa-auction-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:16px 0;padding:12px;background:#f6f7f7;border-radius:8px}
        .art-coa-auction-stats .lbl{display:block;font-size:11px;color:#666}
        .art-coa-auction-stats .val{font-weight:700;font-size:15px;color:var(--art-coa-primary,#1a365d)}
        .art-coa-bid-form{margin-top:16px;padding:14px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
        .art-coa-auction-closed{padding:12px;background:#fcf0f1;border-radius:6px}
        .art-coa-auction-filters{display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin:18px 0 24px;padding:16px;border:1px solid #e2e4e7;border-radius:12px;background:#fff}
        .art-coa-auction-filters label{display:flex;flex:1 1 220px;flex-direction:column;gap:6px;font-size:13px;font-weight:600}
        .art-coa-auction-filters input,.art-coa-auction-filters select{min-height:40px;padding:7px 10px;border:1px solid #c3c4c7;border-radius:6px;background:#fff}
        .art-coa-auction-artwork-facts,.art-coa-auction-detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:14px 0}
        .art-coa-auction-artwork-facts div,.art-coa-auction-detail-grid div{padding:12px;border:1px solid #e6e8eb;border-radius:8px;background:#fff}
        .art-coa-auction-artwork-facts dt,.art-coa-auction-detail-grid dt{font-size:12px;color:#646970;margin-bottom:5px}
        .art-coa-auction-artwork-facts dd,.art-coa-auction-detail-grid dd{margin:0;line-height:1.7;white-space:normal}
        .art-coa-auction-details{margin:22px 0;padding:18px;border:1px solid #e6e8eb;border-radius:12px;background:#fbfbfc}
        .art-coa-auction-details h2{margin-top:0}
        .art-coa-auction-estimate{padding:12px 14px;border-radius:8px;background:#f0f6fc;color:var(--art-coa-primary,#1a365d)}
        .art-coa-auction-extra{margin:14px 0;padding:12px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
        .art-coa-auction-extra summary{cursor:pointer;font-weight:700}
        .art-coa-auction-extra[open] summary{margin-bottom:12px}
        .art-coa-auction-table-lot{font-size:11px;color:#646970}
        .art-coa-auction-result-count{font-size:13px;color:#646970;margin:0 0 10px}
        .art-coa-auction-pager{display:flex;align-items:center;justify-content:center;gap:14px;margin:24px 0}
        .art-coa-auction-pager span{font-size:13px;color:#646970}
        @media(max-width:768px){.art-coa-auction-layout,.art-coa-auction-stats{grid-template-columns:1fr}}

        .art-coa-sec-badge{background:#646970!important}
        .art-coa-price-change{font-weight:700;margin-inline-start:6px}
        .art-coa-price-change.up{color:#00a32a}
        .art-coa-price-change.down{color:#d63638}
        .art-coa-sec-panel{margin-top:24px;padding:16px;background:#f6f7f7;border-radius:8px}
        .art-coa-sec-list-form .widefat{max-width:100%}

        .art-coa-shop{max-width:1100px;margin:0 auto}
        .art-coa-shop-title{margin:0 0 8px;color:var(--art-coa-primary,#1a365d)}
        .art-coa-shop-intro{color:#555;margin:0 0 16px}
        .art-coa-shop-filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}
        .art-coa-shop-filters select,.art-coa-shop-filters input{padding:6px 10px;border:1px solid #c3c4c7;border-radius:4px}
        .art-coa-shop-grid{display:grid;gap:18px}
        .art-coa-shop-grid.cols-2{grid-template-columns:repeat(2,1fr)}
        .art-coa-shop-grid.cols-3{grid-template-columns:repeat(3,1fr)}
        .art-coa-shop-grid.cols-4{grid-template-columns:repeat(4,1fr)}
        .art-coa-shop-card{background:#fff;border-radius:12px;overflow:hidden;border:1px solid #eee;box-shadow:0 2px 12px rgba(0,0,0,.06);display:flex;flex-direction:column}
        .art-coa-shop-img{position:relative;aspect-ratio:1;background:#f0f0f1;overflow:hidden}
        .art-coa-shop-img img{width:100%;height:100%;object-fit:cover}
        .art-coa-shop-badge{position:absolute;top:10px;inset-inline-start:10px;background:var(--art-coa-secondary,#b32d2e);color:#fff;font-size:11px;padding:4px 8px;border-radius:4px}
        .art-coa-shop-body{padding:14px;display:flex;flex-direction:column;gap:6px;flex:1}
        .art-coa-shop-body h3{margin:0;font-size:16px;color:var(--art-coa-primary,#1a365d)}
        .art-coa-shop-price{font-size:18px;font-weight:700;color:var(--art-coa-secondary,#b32d2e);margin:4px 0}
        .art-coa-shop-inquiry{margin-top:auto;display:flex;flex-direction:column;gap:6px}
        .art-coa-shop-inquiry input,.art-coa-shop-inquiry textarea{width:100%;padding:6px 8px;border:1px solid #c3c4c7;border-radius:4px}
        @media(max-width:768px){.art-coa-shop-grid.cols-2,.art-coa-shop-grid.cols-3,.art-coa-shop-grid.cols-4{grid-template-columns:1fr 1fr}}
        @media(max-width:480px){.art-coa-shop-grid.cols-2,.art-coa-shop-grid.cols-3,.art-coa-shop-grid.cols-4{grid-template-columns:1fr}}

        .art-coa-profile-wrap a{color:var(--art-coa-primary,#1a365d)}
        .art-coa-profile-wrap .art-coa-stat-pill .val,
        .art-coa-profile-wrap h1,.art-coa-profile-wrap h2{color:var(--art-coa-primary,#1a365d)}
        .art-coa-dir-links a,.art-coa-portfolio-card{border-top:3px solid var(--art-coa-primary,#1a365d)}
        .art-coa-cert-doc{max-width:860px;margin:24px auto;font-family:Tahoma,Arial,sans-serif}
        .art-coa-cert-frame{border:3px solid var(--cert-primary,#1a365d);padding:28px 32px;background:#fff;box-shadow:0 8px 30px rgba(0,0,0,.08);position:relative}
        .art-coa-cert-frame:before{content:"";position:absolute;inset:8px;border:1px solid var(--cert-secondary,#b32d2e);pointer-events:none}
        .art-coa-cert-header{display:grid;grid-template-columns:1fr 2fr 1fr;gap:12px;align-items:center;margin-bottom:20px}
        .art-coa-cert-logo{max-height:72px;max-width:120px;object-fit:contain}
        .art-coa-cert-logo-slot{text-align:center}
        .art-coa-cert-logo-text{font-weight:700;font-size:14px;color:var(--cert-primary,#1a365d)}
        .art-coa-cert-logo-label{display:block;font-size:11px;color:#666;margin-top:4px}
        .art-coa-cert-title-block{text-align:center}
        .art-coa-cert-title-block h1{margin:0;font-size:22px;color:var(--cert-primary,#1a365d)}
        .art-coa-cert-serial code{font-size:15px;background:#f6f7f7;padding:4px 10px;border-radius:4px}
        .art-coa-cert-badge{text-align:center;margin:12px 0;padding:8px;border-radius:6px;font-weight:700}
        .art-coa-cert-badge.valid{background:#edfaef;color:#00a32a}
        .art-coa-cert-artwork-img{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;margin:20px auto;width:100%;max-width:100%}
        .art-coa-cert-artwork-img img{display:block;margin:0 auto;max-width:min(420px,90%);max-height:300px;width:auto;height:auto;object-fit:contain;border:1px solid #e2e4e7;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.08);background:#fafafa}
        .art-coa-cert-artwork-caption{display:block;margin:10px auto 0;text-align:center;font-size:13px;color:#555;font-style:italic;max-width:90%}
        

        .art-coa-cert-qr-hash{display:grid;grid-template-columns:160px 1fr;gap:20px;align-items:center;margin:16px 0;padding:14px;background:#f8f9fa;border-radius:8px;border:1px solid #e2e4e7}
        .art-coa-cert-qr{text-align:center}
        .art-coa-cert-qr img{display:block;margin:0 auto 6px;border:4px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.1)}
        .art-coa-cert-qr span{font-size:11px;color:#666}
        .art-coa-hash-code{display:block;word-break:break-all;font-size:12px;background:#fff;padding:8px 10px;border-radius:4px;border:1px dashed #c3c4c7;margin:8px 0;direction:ltr;text-align:left}
        @media(max-width:600px){.art-coa-cert-qr-hash{grid-template-columns:1fr}}

        .art-coa-cert-table{width:100%;border-collapse:collapse;margin:16px 0}
        .art-coa-cert-table th,.art-coa-cert-table td{padding:10px 12px;border-bottom:1px solid #e2e4e7;text-align:start}
        .art-coa-cert-table th{width:32%;color:#555;font-weight:600}
        .art-coa-cert-signatures{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin:28px 0 12px}
        .art-coa-cert-sign-box{text-align:center}
        .art-coa-cert-sign-img{max-height:64px;max-width:160px;object-fit:contain;margin-bottom:6px}
        .art-coa-cert-sign-placeholder{height:48px;margin-bottom:6px}
        .art-coa-cert-sign-line{border-top:1px solid #1d2327;margin:0 auto 8px;width:80%}
        .art-coa-cert-sign-box strong{display:block;font-size:13px}
        .art-coa-cert-sign-box span{font-size:11px;color:#666}
        .art-coa-cert-footer{margin-top:20px;padding-top:12px;border-top:1px dashed #ccc;font-size:12px;color:#555;text-align:center}
        .art-coa-cert-actions{text-align:center;margin-top:16px}
        .art-coa-sitemap .art-coa-section{margin-bottom:20px}
        @media print{
            .no-print,header,footer,.site-header,.site-footer,.admin-bar,#wpadminbar,nav,.menu,.elementor-location-header,.elementor-location-footer{display:none!important}
            body{background:#fff!important;margin:0!important;padding:0!important}
            .art-coa-profile-wrap,.art-coa-cert-doc{max-width:100%!important;margin:0 auto!important;padding:0!important}
            .art-coa-cert-frame{box-shadow:none!important;border-width:2px;margin:0 auto!important;page-break-inside:avoid}
            .art-coa-cert-artwork-img{
                display:block!important;
                text-align:center!important;
                margin:16px auto!important;
                width:100%!important;
            }
            .art-coa-cert-artwork-img img{
                display:block!important;
                margin-left:auto!important;
                margin-right:auto!important;
                max-width:70%!important;
                max-height:240px!important;
                width:auto!important;
                height:auto!important;
            }
            .art-coa-cert-artwork-caption{text-align:center!important;margin:8px auto!important}
            .art-coa-cert-header,.art-coa-cert-qr-hash,.art-coa-cert-signatures{page-break-inside:avoid}
        }
        @media(max-width:700px){
            .art-coa-cert-header{grid-template-columns:1fr;text-align:center}
            .art-coa-cert-signatures{grid-template-columns:1fr}
        }

        .art-coa-portfolio{max-width:1100px;margin:0 auto}
        .art-coa-portfolio-title{margin:0 0 16px}
        .art-coa-portfolio-filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}
        .art-coa-portfolio-filters select{padding:6px 10px;border:1px solid #c3c4c7;border-radius:4px}
        .art-coa-portfolio-grid{display:grid;gap:16px}
        .art-coa-portfolio-grid.cols-2{grid-template-columns:repeat(2,1fr)}
        .art-coa-portfolio-grid.cols-3{grid-template-columns:repeat(3,1fr)}
        .art-coa-portfolio-grid.cols-4{grid-template-columns:repeat(4,1fr)}
        .art-coa-portfolio-card{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.06);border:1px solid #eee}
        .art-coa-portfolio-img{aspect-ratio:1;background:#f0f0f1;overflow:hidden}
        .art-coa-portfolio-img img{width:100%;height:100%;object-fit:cover;display:block}
        .art-coa-portfolio-placeholder{display:flex;align-items:center;justify-content:center;font-size:48px;height:100%}
        .art-coa-portfolio-body{padding:12px 14px}
        .art-coa-portfolio-body h3{margin:0 0 6px;font-size:15px}
        @media(max-width:768px){
            .art-coa-portfolio-grid.cols-2,.art-coa-portfolio-grid.cols-3,.art-coa-portfolio-grid.cols-4{grid-template-columns:1fr 1fr}
        }
        @media(max-width:480px){
            .art-coa-portfolio-grid.cols-2,.art-coa-portfolio-grid.cols-3,.art-coa-portfolio-grid.cols-4{grid-template-columns:1fr}
        }




        @media(max-width:768px){
            .art-coa-cal-header,.art-coa-cal-row{grid-template-columns:repeat(7,minmax(36px,1fr)) 60px}
            .art-coa-cal-day .num{font-size:16px}
        }
        ';
    }
}
