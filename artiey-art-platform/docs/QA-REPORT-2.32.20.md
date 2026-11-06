# Artiey Art Platform 2.32.20 — URL / Rewrite / Cache Repair QA

## Scope
- Rebuild WordPress rewrite rules after all bundled CPT and COA rewrite providers have registered.
- Invalidate URL-related plugin transients and generation-based caches without flushing the entire persistent object cache.
- Avoid a second redundant rewrite flush from the bundled COA SEO module.
- Recover the managed `/artists/` archive if it was deleted or accidentally left unpublished, while avoiding takeover of unrelated pages.
- Preserve old URL aliases as 301 compatibility routes.

## Changes
- Bumped the plugin release and route schema marker to `2.32.20`.
- Added one-time rewrite refresh and internal URL-cache purge on the next WordPress `init` after update.
- Added invalidation for directory, profile, gallery, statistics, Elementor options and COA catalogue/facet caches; removed known stale plugin transient rows from the options table.
- Cleared cached post objects for managed COA pages and the canonical artist archive.
- Added a one-time admin notice explaining that host/CDN full-page caches are outside the plugin's direct control.
- Prevented the COA SEO module from flushing rewrite rules twice after the shared platform flush.
- Promoted a managed or explicitly identifiable Elementor artist directory page back to `publish` if it was left as `draft`, `private` or `pending`; unrelated pages are not promoted or taken over.

## Validation
- `tests/run.php`: 715 passed, 0 failed.
- `tests/artist-url-unification.php`: 11 passed, 0 failed.
- `tests/page-integration.php`: 4 passed, 0 failed.
- Artist archive unification regressions (same page integration suite): 13 passed, 0 failed.
- `tests/elementor-layout-tests.php`: passed.
- PHP syntax: 138 files, no syntax errors.
- JavaScript syntax: 12 files, no syntax errors.
- ZIP integrity: verified after packaging.

## Limitations
This QA was run in the local test environment, not on the live WordPress host. The plugin can rebuild its rewrite table and purge its own transients/object-cache generations. It cannot remotely purge a host-level full-page cache, CDN such as Cloudflare, or browser cache; those remain outside the plugin runtime.
