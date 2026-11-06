# Artiey Art Platform 2.32.5 — Performance release

## Performance improvements

- Restrict platform admin navigation CSS/JS to platform, Artiey post-type, and Artiey taxonomy screens.
- Replace repeated per-shortcode page-content scans with grouped shortcode detection.
- Serve only the lightweight verifier/certificate stylesheet on pages containing only `[art_coa_verify]` or `[art_coa_certificate]`; defer the 70 KB dynamic component CSS and dynamic JS to interactive routes/widgets.
- Bulk-prime attachment post and metadata caches before rendering gallery photo grids and artist/curator/collector/auction cards to avoid cold-cache N+1 attachment reads.
- Combine global COA dashboard metrics into one SQL round-trip while preserving public/admin visibility rules.
- Use the platform release version for Art COA dynamic frontend and dashboard asset cache-busting.

## Validation

Run:

```sh
php tests/run.php
php tests/page-integration.php
php tests/gallery-directory.php
```

Static PHP syntax validation and ZIP integrity should also be checked after packaging.
