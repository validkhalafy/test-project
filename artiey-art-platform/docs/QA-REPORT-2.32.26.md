# Artiey Art Platform 2.32.26 — QA Report

Built on 2.32.22 (language switcher, slug tombstones kept unchanged).

## Validation
- PHP lint on every file, JS syntax check: clean.
- tests/run.php: 720 passed; artist-url-unification 11; page-integration 4+13; gallery-directory 4.
- tests/v2-32-23-regressions.php (includes hierarchy query-count checks): 50 passed (+69 in tests/member-forms.php) (appearance sanitising/CSS, page builder adopt/skip/restore, cache control, Cache::get, hierarchy privacy).

## Not verified
Run only against stubs, not a live WordPress. Needs a smoke test on staging: the three new admin screens (کش و مسیرها، ساخت صفحات، ظاهر و شخصی‌سازی), the hierarchy queries against real MySQL, and the effect on host/CDN page caches.

## Registration engine
Validated against stubs only: nothing was run on a live WordPress, no real email was sent, and the Persian/English strings and CSS were not reviewed in a browser. Smoke-test register -> confirm email -> dashboard -> edit profile for each role on staging.

Suites: run.php 720, member-forms 78, v2-32-23-regressions 56, slug-edit 11, artist-url 11, page-integration 4+13, gallery-directory 4. All against stubs; none on a live WordPress.
