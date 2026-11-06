# QA Report — Artiey Art Platform 3.0.4

## Scope
Reviewed the supplied 3.0.3 source archive, focusing on page-cache behavior, directory/statistics cache invalidation, and the existing entity relationship/hierarchy implementation.

## Changes applied
1. `includes/class-statistics.php`: added invalidation for added/updated/deleted metadata on Artiey entity post types; added invalidation on entity deletion; coalesced repeated invalidation hooks to one generation bump per request.
2. `includes/class-cache-control.php`: added targeted cache purges for saved/deleted/status-changed WordPress pages and Artiey entity posts; added post-level adapters for WP Rocket, W3 Total Cache, WP Super Cache, and LiteSpeed where their APIs exist; added `artiey_aet_purge_post_cache` for custom host/CDN integrations. No full-domain purge is triggered on every save.
3. `artiey-art-platform.php`, `readme.txt`: bumped runtime/header/stable version to 3.0.4.
4. `tests/run.php`: added regression assertions for metadata/deletion invalidation and targeted page-cache purges.
5. Added release notes and this QA report.

## Verification
- `php tests/run.php`: **728 passed, 0 failed**.
- `php -l` over all PHP files: **162 files, no syntax errors**.
- Existing tests cover hierarchy relations and related-entity display for artist/gallery/curator/collector/auction/exhibition/artwork flows, Elementor route/widget structure, and canonical artist archive routing.

## Remaining live-environment checks
- Test with the actual WordPress/PHP version, active theme, Elementor version, and installed cache plugin.
- Confirm host-level FastCGI/Varnish cache and CDN purge behavior. These layers may bypass WordPress; configure the host/CDN integration using `artiey_aet_purge_post_cache` if needed.
- Verify cache exclusions for dynamic member forms, live auction/bid pages, and nonce-bearing shortcodes on anonymous and logged-in sessions.
- Verify actual relationship records and metadata keys in the production database; standalone tests validate code paths and expected structures, not live database contents.
- Run end-to-end create/edit/delete tests for linked entities and confirm relationship cards, counts, timelines, and archives refresh.

## Deployment note
Back up the site and database before replacing the production plugin. Deploy during a low-traffic window, clear the active page cache/CDN once after deployment, and test representative public and authenticated routes. No production site or database was modified by this work.
