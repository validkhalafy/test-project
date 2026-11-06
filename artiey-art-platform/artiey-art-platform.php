<?php
/**
 * Plugin Name:       Artiey Art Platform (پلتفرم جامع آرتی)
 * Description:       پلتفرم یکپارچه هنرهای تجسمی با گالری‌ها، هنرمندان، آثار، نمایشگاه‌ها، دو ماژول گواهی اصالت، بازار هنری، حراج، ابزارک‌های المنتور و سئوی فنی.
 * Version:           3.1.0
 * Requires PHP:      8.1
 * Requires at least: 6.2
 * Author:            Artiey
 * License:           GPL-2.0-or-later
 * Text Domain:       artiey-aet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* 1. Legacy-plugin guard                                              */
/* ------------------------------------------------------------------ */
/*
 * This plugin REPLACES the standalone legacy plugins listed below.
 * Detect them from the active-plugin options before loading bundled classes.
 * If one is active, keep this plugin inert for the current request and
 * deactivate the legacy copy on admin_init. This is important when a legacy
 * plugin appears later in WordPress' already-built plugin load list: the
 * legacy plugin may still load this request, but our bundled classes will not,
 * preventing duplicate class declarations. The next request loads Artiey alone.
 * Existing data and database tables are never deleted by this guard.
 */
$artiey_ap_legacy_plugin_files = array(
    'artiey-exhibition-timeline/artiey-exhibition-timeline.php',
    'wp-coa-pro/wp-coa-pro.php',
    'wp-art-coa-pro/wp-art-coa-pro.php',
);
$artiey_ap_active_plugins = (array) get_option( 'active_plugins', array() );
$artiey_ap_network_plugins = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
$artiey_ap_active_legacy_plugins = array_values( array_intersect( $artiey_ap_legacy_plugin_files, $artiey_ap_active_plugins ) );
$artiey_ap_network_legacy_plugins = array_values( array_intersect( $artiey_ap_legacy_plugin_files, array_keys( $artiey_ap_network_plugins ) ) );
$artiey_ap_legacy_loaded = ! empty( $artiey_ap_active_legacy_plugins )
    || ! empty( $artiey_ap_network_legacy_plugins )
    || class_exists( 'Artiey_AET_Settings', false )
    || class_exists( 'WP_COA_Pro_Installer', false )
    || defined( 'WP_COA_PRO_VERSION' )
    || defined( 'AET_VERSION' );

if ( $artiey_ap_legacy_loaded ) {
    if ( ! function_exists( 'artiey_ap_deactivate_legacy' ) ) {
        function artiey_ap_deactivate_legacy() {
            if ( ! function_exists( 'deactivate_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $legacy = array(
                'artiey-exhibition-timeline/artiey-exhibition-timeline.php',
                'wp-coa-pro/wp-coa-pro.php',
                'wp-art-coa-pro/wp-art-coa-pro.php',
            );
            // Deactivate site-level copies and, on Multisite, network-wide copies.
            deactivate_plugins( $legacy, true, false );
            if ( is_multisite() ) {
                deactivate_plugins( $legacy, true, true );
            }
            set_transient( 'artiey_ap_legacy_notice', 1, 120 );
        }
    }
    if ( ! empty( $artiey_ap_active_legacy_plugins ) || ! empty( $artiey_ap_network_legacy_plugins ) ) {
        add_action( 'admin_init', 'artiey_ap_deactivate_legacy', 1 );
    }
    register_activation_hook( __FILE__, 'artiey_ap_deactivate_legacy' );
    add_action(
        'admin_notices',
        function () {
            echo '<div class="notice notice-warning"><p><strong>پلتفرم جامع آرتی:</strong> نسخه مستقل قدیمی «Artiey Exhibition Timeline»، «WP COA Pro» یا «WP Art COA Pro» شناسایی شد. برای جلوگیری از تداخل، ماژول‌های پلتفرم در این درخواست بارگذاری نشده‌اند و نسخه‌های مستقل در پنل مدیریت غیرفعال می‌شوند. اطلاعات و جداول آن‌ها حذف نمی‌شود. پس از بارگذاری مجدد، پلتفرم آرتی فعال خواهد شد.</p></div>';
        }
    );
    return;
}

/* ------------------------------------------------------------------ */
/* 2. Constants                                                        */
/* ------------------------------------------------------------------ */

define( 'AET_VERSION', '3.1.0' );
define( 'AET_FILE', __FILE__ );
define( 'AET_DIR', plugin_dir_path( __FILE__ ) );
define( 'AET_URL', plugin_dir_url( __FILE__ ) );

// Shared accessible Elementor tabs are loaded before both platform and COA widgets register.
require_once AET_DIR . 'includes/class-elementor-tabs.php';

// WP COA Pro module (kept byte-compatible so every issued certificate still verifies).
define( 'WP_COA_PRO_VERSION', '1.11.2' );
define( 'WP_COA_PRO_DB_VERSION', '8' );
define( 'WP_COA_PRO_FILE', __FILE__ );
define( 'WP_COA_PRO_DIR', AET_DIR . 'coa/' );
define( 'WP_COA_PRO_URL', AET_URL . 'coa/' );

// Additional Art COA module from WP Art COA Pro, bundled under this plugin.
define( 'WP_ART_COA_VERSION', '1.7.1' );
define( 'WP_ART_COA_FILE', AET_FILE );
define( 'WP_ART_COA_DIR', AET_DIR . 'art-coa/' );
define( 'WP_ART_COA_URL', AET_URL . 'art-coa/' );
define( 'WP_ART_COA_BASENAME', dirname( plugin_basename( AET_FILE ) ) . '/art-coa/module.php' );


/**
 * Detect Elementor editor shell and its AJAX requests.
 * These requests do not need unrelated Art COA admin menus/CRUD screens.
 */
function artiey_ap_is_elementor_request() {
	if ( ! is_admin() ) {
		return false;
	}
	$action = '';
	if ( isset( $_REQUEST['action'] ) && is_scalar( $_REQUEST['action'] ) ) {
		$action = sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) );
	}
	if ( in_array( $action, array( 'elementor', 'elementor_ajax', 'elementor_ajax_save' ), true ) ) {
		return true;
	}
	return isset( $_GET['action'] ) && is_scalar( $_GET['action'] )
		&& 'elementor' === sanitize_key( wp_unslash( (string) $_GET['action'] ) );
}

/* ------------------------------------------------------------------ */
/* 3. Load modules                                                     */
/* ------------------------------------------------------------------ */

// COA module (order matters: same as the original plugin).
foreach ( array( 'installer', 'galleries', 'person-profiles', 'seo', 'signature', 'ledger', 'repository' ) as $artiey_ap_file ) {
	require_once WP_COA_PRO_DIR . 'includes/class-wp-coa-pro-' . $artiey_ap_file . '.php';
}
require_once WP_COA_PRO_DIR . 'includes/class-wp-coa-pro.php';
require_once WP_COA_PRO_DIR . 'includes/class-wp-coa-pro-admin.php';
require_once WP_COA_PRO_DIR . 'includes/class-wp-coa-pro-elementor-widget.php';
require_once WP_COA_PRO_DIR . 'includes/class-wp-coa-pro-page-integration.php';

// The namespaced Art COA module is loaded as part of this plugin, not as a second plugin.
require_once WP_ART_COA_DIR . 'includes/class-autoloader.php';
require_once WP_ART_COA_DIR . 'includes/class-plugin.php';

// Shared platform helpers.
require_once AET_DIR . 'includes/class-text.php';
require_once AET_DIR . 'includes/class-language-switcher.php';
Artiey_AET_Language_Switcher::init();

// Exhibitions / galleries / artists / artworks module.
require_once AET_DIR . 'includes/class-jalali.php';
require_once AET_DIR . 'includes/class-slug.php';
require_once AET_DIR . 'includes/class-settings.php';
require_once AET_DIR . 'includes/class-statistics.php';
require_once AET_DIR . 'includes/class-post-types.php';
require_once AET_DIR . 'includes/class-renderer.php';
require_once AET_DIR . 'includes/class-profile-guard.php';
require_once AET_DIR . 'includes/class-exhibition.php';
require_once AET_DIR . 'includes/class-gallery.php';
require_once AET_DIR . 'includes/class-artist.php';
require_once AET_DIR . 'includes/class-coa-bridge.php';
require_once AET_DIR . 'includes/class-artwork.php';
require_once AET_DIR . 'includes/class-directory.php';
require_once AET_DIR . 'includes/class-schema.php';
require_once AET_DIR . 'includes/class-seo-meta.php';
require_once AET_DIR . 'includes/class-demo.php';
require_once AET_DIR . 'includes/class-gallery-admin.php';
require_once AET_DIR . 'includes/class-artist-admin.php';
require_once AET_DIR . 'includes/class-admin.php';
require_once AET_DIR . 'includes/class-platform-admin.php';
require_once AET_DIR . 'includes/class-elementor-layouts.php';
require_once AET_DIR . 'includes/class-cache-control.php';
require_once AET_DIR . 'includes/class-page-builder.php';
require_once AET_DIR . 'includes/class-appearance.php';
require_once AET_DIR . 'includes/class-image-sitemap.php';
require_once AET_DIR . 'includes/class-media-gallery.php';
require_once AET_DIR . 'includes/class-curator-view.php';
require_once AET_DIR . 'includes/class-smart-scroller.php';

Artiey_AET_Settings::init();
Artiey_AET_Slug::init();
Artiey_AET_Profile_Guard::init();
add_filter( 'wp_insert_post_data', array( 'Artiey_AET_Profile_Guard', 'filter_save' ), 20 );
Artiey_AET_Statistics::init();
Artiey_AET_Post_Types::init();
Artiey_AET_Renderer::init();
Artiey_AET_Exhibition::init();
Artiey_AET_Gallery::init();
Artiey_AET_Artist::init();
Artiey_AET_Artwork::init();
Artiey_AET_COA_Bridge::init();
Artiey_AET_Directory::init();
Artiey_AET_Schema::init();
Artiey_AET_SEO_Meta::init();
Artiey_AET_Elementor_Layouts::init();
Artiey_AET_Cache_Control::init();
Artiey_AET_Page_Builder::init();
Artiey_AET_Appearance::init();
Artiey_AET_Image_Sitemap::init();
Artiey_AET_Media_Gallery::init();
Artiey_AET_Curator_View::init();
Artiey_AET_Smart_Scroller::init();

// Admin-only screens, meta boxes and dashboard hooks are not needed on the public
// site, and the Elementor editor uses its own editing interface. Avoid registering
// these callbacks there so the editor bootstrap has less work to do.
if ( is_admin() && ! artiey_ap_is_elementor_request() ) {
    Artiey_AET_Demo::init();
    Artiey_AET_Gallery_Admin::init();
    Artiey_AET_Artist_Admin::init();
    Artiey_AET_Admin::init();
    Artiey_AET_Platform_Admin::init();
}

/* COA bootstrapping (identical behaviour to the standalone plugin). */
add_action( 'init', 'artiey_ap_load_textdomains', 0 );
add_action( 'plugins_loaded', array( 'WP_COA_Pro_SEO', 'init' ), 8 );
add_action( 'plugins_loaded', array( 'WP_COA_Pro', 'init' ) );
add_action( 'plugins_loaded', array( '\\WPArtCOA\\Plugin', 'instance' ), 11 );

// WooCommerce HPOS + checkout blocks compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

function artiey_ap_load_textdomains() {
	load_plugin_textdomain( 'artiey-aet', false, dirname( plugin_basename( AET_FILE ) ) . '/languages' );
	// COA ships its own Persian translation under coa/languages.
	load_plugin_textdomain( 'wp-coa-pro', false, dirname( plugin_basename( AET_FILE ) ) . '/coa/languages' );
}

/* ------------------------------------------------------------------ */
/* 4. Elementor                                                        */
/* ------------------------------------------------------------------ */

add_action(
	'elementor/elements/categories_registered',
	function ( $elements_manager ) {
		$elements_manager->add_category(
			'artiey-art',
			array(
				'title' => 'پلتفرم آرتی',
				'icon'  => 'fa fa-palette',
			)
		);
	}
);

/**
 * Register Artiey and COA shortcodes as Elementor Dynamic Tags.
 * Dynamic Tags UI is provided by Elementor Pro; the callback is guarded so the
 * platform remains fully functional when only Elementor Core is installed.
 */
add_action(
    'elementor/dynamic_tags/register',
    function ( $dynamic_tags_manager ) {
        if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' )
            || ! is_object( $dynamic_tags_manager )
            || ! method_exists( $dynamic_tags_manager, 'register' )
            || ! method_exists( $dynamic_tags_manager, 'register_group' ) ) {
            return;
        }
        require_once AET_DIR . 'includes/class-elementor-shortcode-dynamic-tags.php';
        Artiey_AET_Elementor_Shortcode_Dynamic_Tags::register( $dynamic_tags_manager );
    },
    20
);

add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		require_once AET_DIR . 'includes/class-elementor-options.php';
		require_once AET_DIR . 'includes/class-elementor-style.php';
		require_once AET_DIR . 'includes/class-elementor-widget.php';
		$widgets_manager->register( new Artiey_AET_Elementor_Widget() );
		require_once AET_DIR . 'includes/class-elementor-profile-widget.php';
		$widgets_manager->register( new Artiey_AET_Elementor_Profile_Widget() );
		require_once AET_DIR . 'includes/class-elementor-more-widgets.php';
		$widgets_manager->register( new Artiey_AET_Elementor_Artist_Widget() );
		$widgets_manager->register( new Artiey_AET_Elementor_Directory_Widget() );
		$widgets_manager->register( new Artiey_AET_Elementor_Artwork_Widget() );
		$widgets_manager->register( new Artiey_AET_Elementor_Stats_Widget() );
		$widgets_manager->register( new Artiey_AET_Elementor_Exhibition_Widget() );
		require_once AET_DIR . 'includes/class-elementor-route-content-widget.php';
		$widgets_manager->register( new Artiey_AET_Elementor_Route_Content_Widget() );
		require_once AET_DIR . 'includes/class-elementor-media-widget.php';
		$widgets_manager->register( new Artiey_AET_Elementor_Media_Gallery_Widget() );
		require_once AET_DIR . 'includes/class-elementor-curator-widget.php';
		$widgets_manager->register( new Artiey_AET_Elementor_Curator_Widget() );
		require_once AET_DIR . 'includes/class-elementor-smart-scroller-widget.php';
		$widgets_manager->register( new Artiey_AET_Elementor_Smart_Scroller_Widget() );
		if ( class_exists( '\\WPArtCOA\\Follows' ) ) {
			require_once AET_DIR . 'includes/class-elementor-follow-widget.php';
			$widgets_manager->register( new Artiey_AET_Elementor_Follow_Widget() );
		}
		if ( class_exists( '\\WPArtCOA\\Calls' ) ) {
			require_once AET_DIR . 'includes/class-elementor-calls-widget.php';
			$widgets_manager->register( new Artiey_AET_Elementor_Calls_Widget() );
		}
	}
);

/** Append the configured external shortcode to any Artiey / COA Elementor widget. */
add_filter( 'elementor/widget/render_content', 'artiey_ap_append_widget_shortcode', 20, 2 );
function artiey_ap_append_widget_shortcode( $content, $widget ) {
    if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_settings_for_display' ) ) { return $content; }
    $raw = $widget->get_settings_for_display( 'aet_extra_shortcode' );
    if ( ! is_string( $raw ) || '' === trim( $raw ) || false === strpos( $raw, '[' ) ) { return $content; }
    // Widget settings can only be changed by users permitted to edit the Elementor document.
    $shortcode = trim( $raw );
    if ( strlen( $shortcode ) > 10000 ) { return $content; }
    return $content . '<div class="aetp-widget-extra-shortcode">' . do_shortcode( $shortcode ) . '</div>';
}

/* Cache invalidation for the widget pickers must run on every request that saves content, not only when Elementor loads. */
add_action(
	'init',
	function () {
		require_once AET_DIR . 'includes/class-elementor-options.php';
		Artiey_AET_Elementor_Options::init();
	},
	1
);

/* ------------------------------------------------------------------ */
/* 5. Lifecycle                                                        */
/* ------------------------------------------------------------------ */

/** Post types that Elementor should be able to edit. */
function artiey_ap_enable_elementor_cpts() {
	$support = get_option( 'elementor_cpt_support', array( 'page', 'post' ) );
	if ( ! is_array( $support ) ) {
		$support = array( 'page', 'post' );
	}
	foreach ( array( 'artiey_exhibition', 'artiey_gallery', 'artiey_artist', 'artiey_artwork' ) as $type ) {
		if ( ! in_array( $type, $support, true ) ) {
			$support[] = $type;
		}
	}
	$current = get_option( 'elementor_cpt_support', array( 'page', 'post' ) );
	if ( $current !== $support ) {
		update_option( 'elementor_cpt_support', $support );
	}
}

register_activation_hook(
	__FILE__,
	function () {
		// 1) Migrate canonical public URL bases before registering rewrite rules.
		Artiey_AET_Slug::migrate_bases();
		// 2) COA database tables, signing key, capability.
		WP_COA_Pro_Installer::install();
		// 3) Our post types, then 4) COA rewrite rules + a single flush for everything.
		Artiey_AET_Post_Types::register();
		artiey_ap_enable_elementor_cpts();
		WP_COA_Pro_SEO::activate();
		// Create the additional Art COA tables, roles, public pages, and rewrite rules.
		\WPArtCOA\Activator::activate();
		update_option( 'artiey_aet_ver', AET_VERSION, false );
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'art_coa_close_expired_auctions' );
		wp_clear_scheduled_hook( 'art_coa_purge_unverified' );
		flush_rewrite_rules( false );
	}
);

/**
 * After a plugin update (files replaced without re-activation): refresh permalinks and
 * make sure Elementor can edit the post types.
 */
add_action(
	'init',
	function () {
		$previous_version = (string) get_option( 'artiey_aet_ver', '0' );
		if ( $previous_version === AET_VERSION ) {
			return;
		}

		// Route schema flushing is handled once by Artiey_AET_Slug::maybe_refresh_routes()
		// after all rewrite providers have registered their rules on this request.

		// The profile-content repair used to scan and parse up to 300 posts in this request.
		// Older sites still receive the repair, but in small WP-Cron batches, never during editor boot.
		if ( version_compare( $previous_version, '2.32.7', '<' ) && method_exists( 'Artiey_AET_Profile_Guard', 'queue_repair' ) ) {
			Artiey_AET_Profile_Guard::queue_repair();
		}

		artiey_ap_enable_elementor_cpts();
		Artiey_AET_Settings::bump();

		// The bundled Art COA module has its own migration version. A change to the parent
		// plugin's CSS/widgets must not run dbDelta() and every module migrator again.
		$module_version = (string) get_option( 'wp_art_coa_version', '0' );
		if ( class_exists( '\\WPArtCOA\\Activator' ) && version_compare( $module_version, WP_ART_COA_VERSION, '<' ) ) {
			\WPArtCOA\Activator::activate();
		}

		update_option( 'artiey_aet_ver', AET_VERSION, false );
	},
	99
);

/** Shown once after the legacy plugins were switched off by activation. */
add_action(
	'admin_notices',
	function () {
		if ( ! get_transient( 'artiey_ap_legacy_notice' ) ) {
			return;
		}
		delete_transient( 'artiey_ap_legacy_notice' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>پلتفرم جامع آرتی فعال شد.</strong> افزونه‌های قدیمی غیرفعال شدند؛ همه گواهی‌ها، هنرمندان و نمایشگاه‌ها دست‌نخورده‌اند. اگر لینک‌ها ۴۰۴ دادند، یک بار «تنظیمات ← پیوندهای یکتا» را ذخیره کنید.</p></div>';
	}
);
