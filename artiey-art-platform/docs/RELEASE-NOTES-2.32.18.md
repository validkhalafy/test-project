# Artiey Art Platform 2.32.18 — Route, privacy and Elementor context fixes

## Fixes
- Added collector records to slug-history visibility validation; public collector routes now require both `status=active` and `is_public=1`.
- Old-slug redirects are performed only when the target profile is public; private/inactive/missing records return HTTP 404 and `noindex`.
- Unknown Art COA profile types now fail closed with a proper 404 response instead of a soft 200.
- Call routes resolve slug-based records to their numeric ID and pass both ID and slug into the Elementor route context. Invalid call routes return 404 and `noindex`.
- Public auction and secondary-market listings now exclude unpublished/draft artworks. Auction status and artwork publication status are kept separate so scheduled and completed auction states are not mistaken for artwork moderation states.
- Public auction and secondary-market output hides inactive artist identities and private/inactive collector identities.

## Compatibility
- Existing public profile URLs and existing Elementor template assignments remain unchanged.
- Administrators retain access to inactive/private collector profiles for authorized review.
- Elementor route layouts retain their native-content fallback.

## Validation
- Added regression checks for route visibility, collector privacy, artwork publication state, invalid routes, and call ID/slug context.
- Full test suite: 709 passed, 0 failed.
- PHP and JavaScript syntax checks are run during packaging; see `QA-REPORT-2.32.18.md`.
