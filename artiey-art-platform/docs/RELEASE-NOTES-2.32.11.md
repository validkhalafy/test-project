# Artiey Art Platform 2.32.11

## Canonical artist archive and URLs
- Keeps the content archive at `/artists/` and artist singles at `/artists/{slug}/`; the artist base is locked and cannot be changed from settings, preventing future URL fragmentation.
- The `/artists/` managed page is adopted only when it is recognizable as an artist directory; its existing Elementor layout and unrelated content are preserved. The canonical archive shortcode is installed in place of legacy artist-directory shortcodes where safe.
- Migrates the managed artist directory page from the legacy COA-only directory shortcode to `[artiey_artist_archive]`, while preserving its title and page design.
- Routes legacy artist-directory shortcodes and the legacy Elementor artist-directory widget to the canonical WordPress artist archive. The legacy Elementor widget styles are mapped to the canonical archive cards. Collector profiles remain on their existing COA directory.
- Redirects old `/artist/` and remembered custom artist-base roots to `/artists/`; previous single-profile bases remain compatibility aliases. The old `art-person-profile` endpoint is not shadowed, and exact-match redirects avoid fuzzy or incorrect profile mapping.
- Routes a legacy COA artist profile to a WordPress artist post only when the slug matches exactly and the CPT post is published. Unmatched legacy profiles keep their existing URL.
- Adds a curator filter based on explicit artist → artwork → exhibition → curator IDs, with cached options and result sets.
- Keeps the existing gallery, city, nationality, style, medium, works, exhibition and sort filters.

## Template guidance
Create one Elementor Theme Builder Single template for `artiey_artist` and set its display condition to all artist posts. New artists then use the same template automatically; no per-artist template is required.
