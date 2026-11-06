# QA report — Artiey Art Platform 2.32.7

## Scope

Artist Elementor widget, artist profile renderer, WordPress↔COA relationship resolution, career timeline, exhibition timeline, curator profile references, and public artwork history privacy.

## Results

| Check | Result |
|---|---:|
| Core regression suite | 474 passed / 0 failed |
| Page integration regressions | 4 passed / 0 failed |
| Gallery directory regressions | 4 passed / 0 failed |
| PHP syntax | 131 files passed |
| JavaScript syntax | 12 files passed |
| ZIP integrity | Passed |

## Reviewed relationship paths

- Artist → artwork: WordPress `_aw_artist` metadata and canonical COA `artworks.artist_id`.
- Artwork → gallery: WordPress `_aw_gallery` metadata and COA `artworks.gallery_id`.
- Artist → gallery: direct `_ag_artists` relationship plus the artwork bridge.
- Artist → exhibition: direct `_ae_artists` relationship plus COA `exhibition_artworks` joined to artworks by `artist_id`.
- Exhibition → curator: `_ae_curator_id` resolved to an active COA curator profile; inactive/missing records are not linked.
- Artist → sales/auction/ownership timeline: COA rows filtered by artist ID, ordered by event date.

## Safety and integrity rules

- Only published WordPress mirror posts are rendered in public artist related sections.
- COA mirror IDs are checked against canonical slugs before use; no name-based relationship guessing is used.
- Collector names in ownership history are available only for active public collector records.
- The WooCommerce event timeline omits buyer identity and suppresses sales later released/cancelled in the audit log.

## Limitation

The code and regression suite were tested in the supplied development harness. The live WordPress database, active plugin configuration, theme, and Elementor editor on the user's site were not available. Consequently, real-site visual acceptance and validation of that site's specific record links remain outstanding.
