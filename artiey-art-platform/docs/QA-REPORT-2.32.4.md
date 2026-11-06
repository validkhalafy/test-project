# QA report — Artiey Art Platform 2.32.4

## Scope

- Gallery Elementor profile widget and its stacked/tabbed render paths.
- Gallery-to-artist relationships via gallery artwork records and the existing `_ag_artists` relation.
- Gallery-to-curator relationships via exhibitions.
- Gallery-to-collector relationships via public artwork ownership and active/scheduled auction listings.
- Gallery-to-auction relationships via artwork auction listings, including live, scheduled and completed lots.
- Elementor controls for limits, layout, metadata visibility, responsive columns, card chrome, images and typography. Existing widgets using the full legacy default selection are upgraded automatically; custom section selections are preserved.

## Privacy and compatibility

- Collector cards require `is_public = 1` and `status = 'active'`.
- No bidder emails, phone numbers, account identifiers or private collector fields are selected for public cards.
- Optional attachment/link columns are checked before being referenced, preserving compatibility with installations where the media migration has not yet added them.
- All database-sourced card labels, URLs, titles and images are escaped before output.
- Relation query results are transient-cached briefly; repeated schema checks are cached for the current request.

## Automated checks

- `php tests/run.php` — 448 passed, 0 failed.
- `php tests/page-integration.php` — 4 passed, 0 failed.
- `php tests/gallery-directory.php` — 4 passed, 0 failed.
- PHP syntax checks for all 131 plugin PHP files.
- JavaScript syntax checks for all 12 bundled JavaScript files.
- Structural CSS brace check for stylesheet files.
- ZIP integrity check.

A live editor/frontend acceptance test still requires a WordPress site with this plugin and Elementor active.
