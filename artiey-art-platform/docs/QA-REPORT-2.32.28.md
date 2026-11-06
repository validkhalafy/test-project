# Artiey Art Platform 2.32.28 — QA Report

## Scope of this patch
- Added a visual custom-field builder to the existing role-aware registration settings screen. It supports adding/removing fields, ordering, label, technical key, type, required flag, max length, and select options.
- Kept the existing JSON storage and sanitisation contract, including the 20-field cap and supported field-type whitelist. The advanced JSON editor remains available for backward compatibility.
- Improved the member dashboard shell with responsive navigation anchors for overview, role workspace, profile/account, and followed items. Existing role-specific panels and data sources remain in place.
- Updated plugin version to 2.32.28. No database schema migration or CPT/slug change was introduced.

## Automated checks
- PHP lint: all plugin PHP files clean.
- JavaScript syntax: all existing `.js` files pass `node --check`.
- `tests/run.php`: 720 passed, 0 failed.
- `tests/member-forms.php`: 85 passed, 0 failed.
- `tests/connections.php`: 13 passed, 0 failed (includes new form-builder and dashboard wiring checks).
- `tests/slug-edit.php`: 11 passed, 0 failed.
- `tests/artist-url-unification.php`: 11 passed, 0 failed.
- `tests/page-integration.php`: 4 + 13 passed, 0 failed.
- `tests/gallery-directory.php`: 4 passed, 0 failed.

## Environment limitations / required staging QA
- `tests/v2-32-23-regressions.php` could not complete in this CLI environment because the PHP DOM extension (`DOMDocument`) is not installed. This is an environment limitation, not a test pass.
- All automated tests use WordPress stubs; no live WordPress, MySQL, SMTP, Elementor editor, browser rendering, cache plugin, CDN, or real media upload was exercised.
- Before production, test on a staging clone: visual builder in RTL/LTR, adding/reordering/removing all supported field types, select option validation, old JSON settings compatibility, all five roles, email verification, moderation, profile ownership, dashboard mobile navigation, and page-cache exclusion.
