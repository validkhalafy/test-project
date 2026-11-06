<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Admin\Assets;
use WPArtCOA\Admin\Menus;
use WPArtCOA\Elementor\Loader as ElementorLoader;
use WPArtCOA\Rest\Api as RestApi;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->load_dependencies();
        I18n::init();
        $this->init_hooks();
    }

    private static function is_elementor_admin_request(): bool
    {
        return function_exists('artiey_ap_is_elementor_request') && \artiey_ap_is_elementor_request();
    }

    private function load_dependencies(): void
    {
        require_once WP_ART_COA_DIR . 'includes/class-database.php';
        require_once WP_ART_COA_DIR . 'includes/class-media.php';
        require_once WP_ART_COA_DIR . 'includes/class-catalog.php';
        require_once WP_ART_COA_DIR . 'includes/class-woo-bridge.php';
        require_once WP_ART_COA_DIR . 'includes/class-ui.php';
        require_once WP_ART_COA_DIR . 'includes/class-certificate-mirror.php';
        require_once WP_ART_COA_DIR . 'includes/class-profile-fields.php';
        require_once WP_ART_COA_DIR . 'includes/class-content-sync.php';
        require_once WP_ART_COA_DIR . 'includes/class-member-tools.php';
        require_once WP_ART_COA_DIR . 'includes/entities/class-updater.php';
        require_once WP_ART_COA_DIR . 'includes/class-roles.php';
        require_once WP_ART_COA_DIR . 'includes/class-ulid.php';
        require_once WP_ART_COA_DIR . 'includes/class-audit.php';
        require_once WP_ART_COA_DIR . 'includes/class-activator.php';
        require_once WP_ART_COA_DIR . 'includes/class-shortcodes.php';
        require_once WP_ART_COA_DIR . 'includes/class-calendar.php';
        require_once WP_ART_COA_DIR . 'includes/class-timeline.php';
        require_once WP_ART_COA_DIR . 'includes/class-stats.php';
        require_once WP_ART_COA_DIR . 'includes/class-profiles.php';
        require_once WP_ART_COA_DIR . 'includes/class-registration.php';
        require_once WP_ART_COA_DIR . 'includes/class-member-forms.php';
        require_once WP_ART_COA_DIR . 'includes/class-member-forms-admin.php';
        require_once WP_ART_COA_DIR . 'includes/class-hierarchy.php';
        require_once WP_ART_COA_DIR . 'includes/class-portfolio.php';
        require_once WP_ART_COA_DIR . 'includes/class-security.php';
        require_once WP_ART_COA_DIR . 'includes/class-branding.php';
        require_once WP_ART_COA_DIR . 'includes/class-certificate-template.php';
        require_once WP_ART_COA_DIR . 'includes/class-sitemap.php';
        require_once WP_ART_COA_DIR . 'includes/class-shop.php';
        require_once WP_ART_COA_DIR . 'includes/class-secondary-market.php';
        require_once WP_ART_COA_DIR . 'includes/class-auction.php';
        require_once WP_ART_COA_DIR . 'includes/class-auction-stats.php';
        require_once WP_ART_COA_DIR . 'includes/class-calls.php';
        require_once WP_ART_COA_DIR . 'includes/class-artist-portfolio.php';
        require_once WP_ART_COA_DIR . 'includes/class-follows.php';

        require_once WP_ART_COA_DIR . 'includes/entities/class-artist.php';
        require_once WP_ART_COA_DIR . 'includes/entities/class-gallery.php';
        require_once WP_ART_COA_DIR . 'includes/entities/class-curator.php';
        require_once WP_ART_COA_DIR . 'includes/entities/class-collector.php';
        require_once WP_ART_COA_DIR . 'includes/entities/class-artwork.php';
        require_once WP_ART_COA_DIR . 'includes/entities/class-certificate.php';
        require_once WP_ART_COA_DIR . 'includes/entities/class-exhibition.php';
        require_once WP_ART_COA_DIR . 'includes/entities/class-ownership.php';
        require_once WP_ART_COA_DIR . 'includes/entities/class-buyer.php';
        require_once WP_ART_COA_DIR . 'includes/class-content-bridge.php';

        // The Elementor editor does not render the Art COA wp-admin CRUD screens.
        // Avoid parsing ten large admin page traits on editor/Elementor-AJAX requests.
        if (!self::is_elementor_admin_request()) {
            require_once WP_ART_COA_DIR . 'includes/admin/pages/trait-helpers.php';
            require_once WP_ART_COA_DIR . 'includes/admin/pages/trait-dashboard.php';
            require_once WP_ART_COA_DIR . 'includes/admin/pages/trait-artists-page.php';
            require_once WP_ART_COA_DIR . 'includes/admin/pages/trait-artworks-page.php';
            require_once WP_ART_COA_DIR . 'includes/admin/pages/trait-galleries-page.php';
            require_once WP_ART_COA_DIR . 'includes/admin/pages/trait-people-page.php';
            require_once WP_ART_COA_DIR . 'includes/admin/pages/trait-certificates-page.php';
            require_once WP_ART_COA_DIR . 'includes/admin/pages/trait-exhibitions-page.php';
            require_once WP_ART_COA_DIR . 'includes/admin/pages/trait-auctions-page.php';
            require_once WP_ART_COA_DIR . 'includes/admin/pages/trait-calls-page.php';
            require_once WP_ART_COA_DIR . 'includes/admin/class-settings.php';
            require_once WP_ART_COA_DIR . 'includes/admin/class-menus.php';
        }
        require_once WP_ART_COA_DIR . 'includes/admin/class-assets.php';
        require_once WP_ART_COA_DIR . 'includes/rest/class-api.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/class-loader.php';
    }

    private function init_hooks(): void
    {
        add_action('init', [I18n::class, 'init'], 0);
        add_action('init', [Shortcodes::class, 'register']);
        if (!self::is_elementor_admin_request()) {
            add_action('admin_menu', [Menus::class, 'register']);
            add_action('admin_init', [Menus::class, 'register_entity_handlers']);
        }
        add_action('admin_enqueue_scripts', [Assets::class, 'enqueue']);
        add_action('wp_enqueue_scripts', [Assets::class, 'frontend']);
        add_action('rest_api_init', [RestApi::class, 'register']);

        if (did_action('elementor/loaded')) {
            ElementorLoader::init();
        } else {
            add_action('elementor/loaded', [ElementorLoader::class, 'init']);
        }

        Media::init();
        Cache::init();
        Catalog::init();
        MemberTools::init();
        WooBridge::init();
        UI::init();
        CertificateMirror::init();
        ContentSync::init();
        ContentBridge::init();
        Profiles::init();
        Registration::init();
        MemberForms::init();
        Portfolio::init();
        Security::init();
        CertificateTemplate::init();
        Sitemap::init();
        Shop::init();
        SecondaryMarket::init();
        Auction::init();
        Calls::init();
        ArtistPortfolio::init();
        Follows::init();
        add_action('wp_head', [Branding::class, 'inject_front_css'], 5);
    }
}
