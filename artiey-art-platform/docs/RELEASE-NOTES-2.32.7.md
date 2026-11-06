# Artiey Art Platform 2.32.7 — Artist widget and timeline fixes

## Artist profile and relationship graph

- The artist profile now combines published catalogue artwork records connected through WordPress `_aw_artist` metadata with canonical COA artist→artwork relations.
- Gallery relationships are resolved through direct gallery artist metadata, published WordPress artwork→gallery metadata, and the COA artist→artwork→gallery bridge.
- Exhibition relationships are resolved through direct WordPress artist links and the COA artist→artwork→exhibition bridge (`exhibition_artworks`).
- COA mirror post IDs are accepted only when a non-empty canonical slug is available and the WordPress post has the expected post type and published status. Slug fallback supports repaired/missing mirrors without guessing from names.
- The Elementor artist widget retains a configurable catalogue works limit.

## Artistic timeline

- Education and awards entries are sorted chronologically; entries without a year remain last and stable.
- Exhibition groups are ordered by current/upcoming/past state: current events by soonest end, upcoming by nearest start, and past exhibitions newest-first.
- Artist exhibition cards show the curator and link to the public curator profile only when the COA curator record is active and has a valid slug. Curator data for visible cards is fetched in one bounded query.
- COA auction, market, sale, and ownership events remain scoped to the linked artist and are sorted by event date. Owner names are shown only for active public collector profiles; buyer and private owner data are not exposed.

## Performance

- Artist→COA ID resolution is cached for the current request so separate Elementor sections do not repeat the bridge/schema lookup.
- Related artwork/gallery/exhibition results use request-local caches.
- Curator profile lookups for exhibition cards are batched and cached, including misses.

## Validation

- `php tests/run.php`: 474 passed, 0 failed.
- `php tests/page-integration.php`: 4 passed, 0 failed.
- `php tests/gallery-directory.php`: 4 passed, 0 failed.
- All 131 PHP files passed `php -l`.
- All 12 JavaScript files passed `node --check`.
- The ZIP package passed `unzip -t`.

A live acceptance test inside a production WordPress/Elementor installation was not available during this code review. Please verify the widget against the site's own published artist, artwork, gallery, curator, and exhibition records after installation.
