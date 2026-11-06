# Artiey Art Platform 2.32.22 — QA Report

## Slug reuse after permanent deletion
- The COA mirror row is retained for audit/history and relational integrity.
- Its public slug is replaced with a deterministic tombstone slug when the corresponding CPT is permanently deleted.
- This releases the original slug for a newly created artist, gallery, artwork, or exhibition without deleting related records.
- Public URL caches and catalogue/statistics caches are invalidated.
- WordPress trash is intentionally not treated as permanent deletion; restoring a trashed post keeps its original slug.

## Screenshot note
The supplied screenshot shows the active theme's WordPress 404 template at the site root. It does not identify a particular plugin route. If the root URL is intended to be the homepage, verify Settings → Reading → Homepage and ensure the selected page still exists and is Published. This release does not silently replace the site's chosen homepage.

## Validation
- PHP syntax validation and bundled regression tests: 720 passed, 0 failed; artist URL tests: 11 passed, 0 failed.
- PHP syntax validation: 139 files passed.
- A live WordPress/Elementor/host-cache smoke test is still required.
