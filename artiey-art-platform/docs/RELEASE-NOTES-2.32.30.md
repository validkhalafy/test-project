# Artiey Art Platform 2.32.30

## Fixes
- Fixed global Elementor route-layout class resolution in all namespaced Art COA services and Elementor widgets. This prevents the fatal `Class WPArtCOA\Elementor\Widgets\Artiey_AET_Elementor_Layouts not found` error when rendering the portfolio widget and related route-aware components.
- Deferred the bundled COA QR script registration to `wp_enqueue_scripts` and `admin_enqueue_scripts`.
- Deferred the bundled COA public component stylesheet registration to supported asset enqueue hooks.
- Bumped the plugin version to 2.32.30 for reliable update/cache invalidation.

## Compatibility notes
- No database schema, content, custom post type, slug, Elementor document, or certificate data migrations are introduced by this patch.
- Third-party notices from `bdthemes-element-pack` and `epic-news-element` are outside this plugin and are not changed by this release.
