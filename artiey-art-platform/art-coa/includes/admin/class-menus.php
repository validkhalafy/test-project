<?php
declare(strict_types=1);

namespace WPArtCOA\Admin;

use WPArtCOA\Database;
use WPArtCOA\Entities\Artist;
use WPArtCOA\Entities\Artwork;
use WPArtCOA\Entities\Certificate;
use WPArtCOA\Entities\Collector;
use WPArtCOA\Entities\Curator;
use WPArtCOA\Entities\Gallery;
use WPArtCOA\Entities\Exhibition;
use WPArtCOA\I18n;
use WPArtCOA\Dates;

defined('ABSPATH') || exit;

final class Menus
{
    use Pages\Dashboard;
    use Pages\Helpers;
    use Pages\ArtistsPage;
    use Pages\ArtworksPage;
    use Pages\GalleriesPage;
    use Pages\PeoplePage;
    use Pages\CertificatesPage;
    use Pages\ExhibitionsPage;
    use Pages\AuctionsPage;
    use Pages\CallsPage;

    public static function register_entity_handlers(): void
    {
        add_action('admin_post_art_coa_entity_form', [self::class, 'handle_entity_submission']);
        add_action('admin_notices', [self::class, 'render_entity_notice']);
    }

    public static function register(): void
    {
        add_submenu_page(
            'artiey-platform',
            I18n::t('dashboard') . ' — ' . I18n::t('plugin_name'),
            '🎖️ گواهی اصالت',
            'manage_options',
            'wp-art-coa',
            [self::class, 'page_dashboard']
        );

        // Core content (artists, galleries, artworks and exhibitions) is managed by
        // WordPress CPT screens. The Art COA tables are mirrors for certificates,
        // sales and statistics, so duplicate CRUD screens are intentionally hidden.
        foreach ([
            'wp-art-coa-artists' => 'artiey_artist',
            'wp-art-coa-galleries' => 'artiey_gallery',
            'wp-art-coa-artworks' => 'artiey_artwork',
            'wp-art-coa-exhibitions' => 'artiey_exhibition',
        ] as $legacy_page => $post_type) {
            add_submenu_page(null, '', '', 'edit_posts', $legacy_page, static function () use ($post_type): void {
                if (!current_user_can('edit_posts')) {
                    wp_die(esc_html__('You are not allowed to perform this action.', 'artiey-aet'), '', ['response' => 403]);
                }
                wp_safe_redirect(admin_url('edit.php?post_type=' . rawurlencode($post_type)));
                exit;
            });
        }
        add_submenu_page('artiey-platform', I18n::t('curators'), I18n::t('curators'), 'manage_options', 'wp-art-coa-curators', [self::class, 'page_curators']);
        add_submenu_page('artiey-platform', I18n::t('collectors'), I18n::t('collectors'), 'manage_options', 'wp-art-coa-collectors', [self::class, 'page_collectors']);
        add_submenu_page('artiey-platform', I18n::t('certificates'), I18n::t('certificates'), 'manage_options', 'wp-art-coa-certificates', [self::class, 'page_certificates']);
        add_submenu_page('artiey-platform', I18n::t('auction_title'), I18n::t('auction_title'), 'manage_options', 'wp-art-coa-auctions', [self::class, 'page_auctions']);
        add_submenu_page('artiey-platform', I18n::t('calls_title'), I18n::t('calls_title'), 'manage_options', 'wp-art-coa-calls', [self::class, 'page_calls']);
        // Single navigation: the COA Pro engine (issue / revoke / transfer / print) is reachable from the platform menu.
        add_submenu_page('artiey-platform', 'COA Pro', '🏛 COA Pro', 'manage_coa_certificates', 'admin.php?page=wp-coa-pro');
        \WPArtCOA\Admin\Settings::register_menu();
    }
}
