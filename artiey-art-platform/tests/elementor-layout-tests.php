<?php
require_once dirname(__DIR__) . '/includes/class-elementor-layouts.php';

$routes = Artiey_AET_Elementor_Layouts::routes();
$groups = Artiey_AET_Elementor_Layouts::route_groups();
$grouped_routes = [];
foreach ($groups as $group_keys) { $grouped_routes = array_merge($grouped_routes, $group_keys); }
sort($grouped_routes);
$registered_routes = array_keys($routes);
sort($registered_routes);
T::eq($grouped_routes, $registered_routes, 'Elementor route manager groups every custom single/archive route exactly once');
foreach ([
    'profile_artist', 'profile_curator', 'profile_gallery', 'profile_exhibition', 'profile_collector',
    'profile_artwork', 'profile_certificate', 'archive_curators', 'archive_galleries', 'archive_exhibitions',
    'archive_artworks', 'auction_archive', 'auction_single', 'calls_archive', 'calls_single',
    'portfolio_archive', 'portfolio_artist', 'portfolio_gallery', 'shop_archive', 'shop_artist', 'secondary_market',
] as $route_key) {
    T::ok(isset($routes[$route_key]), 'Elementor route template registry contains ' . $route_key);
}
T::eq(Artiey_AET_Elementor_Layouts::assigned_template_id('not-registered'), 0, 'unknown route cannot resolve an Elementor template');
T::eq(Artiey_AET_Elementor_Layouts::assigned_template_id('profile_artist'), 0, 'unassigned route safely falls back to native profile output');

$GLOBALS[Artiey_AET_Elementor_Layouts::CONTEXT_GLOBAL] = ['entity_type' => 'artist', 'slug' => 'sample-artist'];
T::eq(Artiey_AET_Elementor_Layouts::context('entity_type'), 'artist', 'current route entity type is readable inside Elementor templates');
T::eq(Artiey_AET_Elementor_Layouts::context('slug'), 'sample-artist', 'current route slug is readable inside Elementor templates');
$GLOBALS[Artiey_AET_Elementor_Layouts::CONTENT_GLOBAL] = '<section>safe rendered profile</section>';
T::ok(strpos(Artiey_AET_Elementor_Layouts::current_route_content(), '<!--artiey-route-content-->') === 0, 'current route content widget emits the marker used to avoid duplicate fallback output');
T::ok(strpos(Artiey_AET_Elementor_Layouts::current_route_content(), 'safe rendered profile') !== false, 'current route content widget preserves the native relation-aware profile renderer');
unset($GLOBALS[Artiey_AET_Elementor_Layouts::CONTEXT_GLOBAL], $GLOBALS[Artiey_AET_Elementor_Layouts::CONTENT_GLOBAL]);

$plugin_file = file_get_contents(dirname(__DIR__) . '/artiey-art-platform.php');
preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/m', $plugin_file, $hdr_m);
preg_match("/define\(\s*'AET_VERSION',\s*'([0-9.]+)'\s*\)/", $plugin_file, $const_m);
T::ok(!empty($hdr_m[1]) && ($const_m[1] ?? '') === $hdr_m[1], 'version constant and plugin header are in sync for layout release');
T::ok(strpos($plugin_file, "class-elementor-layouts.php") !== false && strpos($plugin_file, 'Artiey_AET_Elementor_Layouts::init()') !== false, 'layout manager loads and initializes in the main plugin');
T::ok(strpos($plugin_file, 'Artiey_AET_Elementor_Route_Content_Widget') !== false, 'route content Elementor widget is registered');

foreach ([
    'class-profiles.php' => ['profile_curator', 'archive_curators', 'render_route('],
    'class-auction.php' => ['auction_single', 'auction_archive', 'render_route('],
    'class-calls.php' => ['calls_single', 'calls_archive', 'render_route('],
    'class-portfolio.php' => ['portfolio_artist', 'portfolio_gallery', 'portfolio_archive', 'render_route('],
    'class-shop.php' => ['shop_artist', 'shop_archive', 'render_route('],
    'class-secondary-market.php' => ['secondary_market', 'render_route('],
] as $file => $needles) {
    $source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/' . $file);
    foreach ($needles as $needle) {
        T::ok(strpos($source, $needle) !== false, $file . ' integrates Elementor route layout ' . $needle);
    }
}

$widget_source = file_get_contents(dirname(__DIR__) . '/includes/class-elementor-route-content-widget.php');
T::ok(strpos($widget_source, "'artiey_route_content'") !== false && strpos($widget_source, 'current_route_content()') !== false, 'Elementor route-content widget renders safe native route content');
$shop_widget_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/elementor/widgets/class-widget-shop.php');
T::ok(strpos($shop_widget_source, 'Artiey_AET_Elementor_Layouts::context()') !== false && strpos($shop_widget_source, 'Entities\\Artist::get_by_slug') !== false && strpos($shop_widget_source, 'Entities\\Gallery::get_by_slug') !== false, 'shop widget automatically filters to the current artist or gallery profile');
$history_widget_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/elementor/widgets/class-widget-artwork-history.php');
$history_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-artwork-history-timeline.php');
T::ok(strpos($history_widget_source, '\'artist_id\' => $artist_id') !== false && strpos($history_source, "current_type === 'artwork'") !== false && strpos($history_source, "current_type === 'artist'") !== false, 'artwork history follows the current artwork or artist in single templates');
$layout_source = file_get_contents(dirname(__DIR__) . '/includes/class-elementor-layouts.php');
T::ok(strpos($layout_source, 'private static function detect_current_context()') !== false && strpos($layout_source, 'Artiey_AET_Elementor_Options::current_post_id( $post_type )') !== false, 'native Theme Builder preview context is resolved from the active Elementor document');

$post_types_source = file_get_contents(dirname(__DIR__) . '/includes/class-post-types.php');
T::ok(substr_count($post_types_source, "'publicly_queryable'  => true") === 4, 'all four Artiey CPTs explicitly support public single templates');
T::ok(substr_count($post_types_source, "'custom-fields'") >= 4, 'all four Artiey CPTs expose custom fields to Elementor dynamic tags');

$platform_menu_source = file_get_contents(dirname(__DIR__) . '/includes/class-platform-admin.php');
T::ok(strpos($platform_menu_source, 'page:artiey-elementor-layouts') !== false, 'Elementor route layout manager is included in Artiey admin navigation');
