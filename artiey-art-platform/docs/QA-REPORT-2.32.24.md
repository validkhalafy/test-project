# Artiey Art Platform 2.32.24 — QA Report

Built on 2.32.22 (language switcher, slug tombstones kept unchanged).

## Validation
- PHP lint on every file, JS syntax check: clean.
- tests/run.php: 720 passed; artist-url-unification 11; page-integration 4+13; gallery-directory 4.
- tests/v2-32-23-regressions.php (includes hierarchy query-count checks): 50 passed (appearance sanitising/CSS, page builder adopt/skip/restore, cache control, Cache::get, hierarchy privacy).

## Not verified
Run only against stubs, not a live WordPress. Needs a smoke test on staging: the three new admin screens (کش و مسیرها، ساخت صفحات، ظاهر و شخصی‌سازی), the hierarchy queries against real MySQL, and the effect on host/CDN page caches.
