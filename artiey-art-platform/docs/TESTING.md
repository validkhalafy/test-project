# Tests

Dependency-free: no Composer or WordPress installation is required for the local regression suite.

Run the core suite and the focused integration regressions:

```sh
php tests/run.php
php tests/page-integration.php
php tests/gallery-directory.php
```

`tests/bootstrap.php` stubs the few WordPress functions the pure-logic code needs (options, transients, sanitizers) and a fake `$wpdb`.

The core suite covers ULID, HMAC sign/verify (tamper, key order), serial sanitising, client-IP trust (no X-Forwarded-For), rate-limit window, image-ID parsing, catalog query normalising (caps, whitelists), auction minimum bid, certificate verification (valid / tampered / revoked / expired / unknown, and that the public response hides signature, ULID and QR data), and WooCommerce price conversion.

The focused regressions cover:

- Managed WordPress page IDs: recovery from a stale/recycled ID, persistence of the repaired ID, no public URL for draft pages, and rejection of unknown page keys.
- COA gallery directory: SQL-side active/activity/search filtering, SQL result limit, prepared search values, transient caching only for low-cardinality searches, and indexed active-profile lookup by slug.

Add a test: append assertions to `tests/run.php` using `T::ok()` / `T::eq()`. Each script exits non-zero on failure and can run in CI.
