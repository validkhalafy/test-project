# Artiey Art Platform 3.0.5 — QA and bug-fix report

## Source examined
- Base archive: `artiey-art-platform-3.0.4-final.zip`
- Updated release: `3.0.5`
- Declared compatibility: WordPress 6.2+, PHP 8.1+

## Confirmed issue fixed

### Legacy plugin load-order collision

**File:** `artiey-art-platform.php` (legacy-plugin guard near the top of the bootstrap file)

**Issue:** The guard detected the standalone `WP Art COA Pro` plugin through the active plugin lists, but did not check the active-plugin lists for the other two replaced plugins (`Artiey Exhibition Timeline` and `WP COA Pro`). It relied on already-declared classes/constants for those two. If one of those legacy plugins appeared later in WordPress' plugin load order, the bundled platform could begin loading its replacement classes first, leaving a possible duplicate-class fatal error when the legacy plugin was loaded later.

**Fix:**
- Check all three legacy plugin basenames in the site's `active_plugins` option.
- Check all three in Multisite's `active_sitewide_plugins` option.
- If a legacy copy is active, return before loading bundled modules for the current request. This protects against plugin load order: even if WordPress still includes a later legacy entry from the plugin list already prepared for that request, the bundled replacement classes have not been loaded.
- Schedule deactivation of all three legacy copies on `admin_init`, for both site-level and network-level activation.
- Keep the existing activation-hook safety behavior.
- Preserve all plugin data and database tables; this guard only deactivates plugin copies.

## Tests
- New isolated regression test: `php tests/legacy-plugin-guard.php` — passed. It simulates a legacy plugin loaded later in the plugin order and a network-active legacy plugin, confirms bundled modules remain unloaded, and verifies both site and network deactivation calls.
- Existing regression suite: `php tests/run.php` — 728 passed, 0 failed.
- PHP syntax: all PHP files checked after the patch.
- JavaScript syntax: all JavaScript files checked after the patch.

## Broader audit observations
- No `wp_ajax_*` registrations were found in the scanned PHP source; therefore no speculative AJAX nonce patches were added.
- The only `eval()` occurrences found are in test scaffolding, not production PHP.
- One guarded `unserialize()` call exists in the directory module and limits allowed classes to `false`; it is used to read plugin-owned primitive metadata.
- Live WordPress/Elementor browser behavior, live database CRUD, and host/CDN cache behavior remain unverified without a staging site.
- DOM-based JavaScript tests remain unavailable in this environment because `jsdom` is not installed and package registry access was unavailable during the prior validation.

## Deployment notes
Install on a staging copy first. If an old standalone replacement is active, the updated plugin intentionally stays inert for that request and deactivates the old copy in the admin request; reload the admin once more so the bundled platform can initialize. No database tables or content are deleted.
