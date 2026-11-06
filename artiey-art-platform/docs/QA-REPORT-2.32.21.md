# Artiey Art Platform 2.32.21 — QA Report

## Changes checked
- Artist short-bio/profile cache invalidation after metadata save.
- Independent artist career timeline metadata; awards remain in their own list.
- Artist interview and related-media editor panels and safe oEmbed rendering.
- Artist active certificate count section.
- Touch-friendly scroll-snap portfolio carousel.
- Shared shortcode control in platform and bundled COA Elementor widget families.
- WPML / Polylang language-switcher bridge.
- Route schema version bump for one-time rewrite/cache refresh.

## Automated validation
- `php tests/run.php`: 719 passed, 0 failed.
- `php tests/artist-url-unification.php`: 11 passed, 0 failed.
- `php tests/elementor-layout-tests.php`: passed.
- PHP syntax checks: passed.
- JavaScript syntax checks: passed.

## Important integration notes
- The language switcher is provider-aware. A real bilingual site still requires WPML or Polylang, translated page/post content, and translated strings. This release adds the switcher bridge and text domain but does not automatically translate every hard-coded Persian admin label.
- Video playback depends on WordPress oEmbed support and the source provider's embed permissions. Unsupported URLs remain safe external links.
- The new career timeline field is independent. Existing education and award values are preserved; awards no longer duplicate into the career timeline automatically.
- Host/CDN full-page cache is outside plugin control. If an old page remains cached after upgrade, purge the host/CDN cache as well.
- QA ran in the included local test harness, not on the user's live WordPress host or Elementor editor. A live staging smoke test is still required before production deployment.
