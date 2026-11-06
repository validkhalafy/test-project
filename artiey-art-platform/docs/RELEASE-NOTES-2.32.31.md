# Artiey Art Platform 2.32.31

## Hardening
- Added `WPArtCOA\Security::input_scalar()` to read scalar request values safely and unslash them exactly once. Arrays, objects, and missing values now return a safe default before reaching WordPress string sanitizers.
- Applied safe scalar reads to auction, calls, artwork moderation, settings, follow actions, shop inquiries, secondary-market submissions, and artist portfolio metadata.
- Hardened Elementor request detection against malformed array-valued `action` query parameters.

## Validation
- PHP syntax check: all bundled PHP files pass.
- JavaScript syntax check: all bundled JS files pass.
- Automated test suite: 723 passed, 0 failed.
- No database migration, content migration, CPT change, slug change, or destructive operation is introduced.

## Scope
This is an incremental hardening release based on 2.32.30. It does not claim live-host, Elementor editor, WooCommerce checkout, or end-to-end browser testing; those require a staging WordPress environment.
