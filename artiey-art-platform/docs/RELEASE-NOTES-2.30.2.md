# Artiey Art Platform 2.30.2 — Media Picker and Admin Reliability Fixes

## Main defects fixed

- **Root cause of image selection failure:** the MediaFrame is a Backbone object; `select.aetg` and `open.aetg` were treated as literal Backbone event names and therefore did not receive WordPress's `select` and `open` events. The picker now binds to the correct events once when the frame is created.
- **Optional CDN dependency:** the shared admin script depended on Leaflet from a third-party CDN even on artist pages, and on gallery pages a blocked CDN could prevent the whole media-picker JS file from running. Leaflet is now loaded only on gallery screens and is not a dependency of the media UI. The map still degrades gracefully if Leaflet is unavailable.
- **Selection restoration:** current image IDs are loaded into the media modal when it opens, and the in-memory selection is reset first so stale images do not reappear.
- **Clear/status feedback:** media fields now provide an accessible live status message when selecting fails or media is cleared.
- **Media validation:** cover and portfolio fields now accept only image attachments, normalize legacy CSV portfolio metadata in both the admin editor and public profile, remove duplicate IDs and limit the portfolio to 60 images.
- **Save safety:** the artist meta box save handler now checks post type, revision/autosave, capability and nonce and does not clear existing profile fields when the form payload is missing.

## Tests

- PHP syntax lint: all PHP files pass.
- JavaScript syntax check: all JavaScript files pass.
- Internal suite: 339 passed, 0 failed.
- Static regression checks ensure the invalid namespaced MediaFrame event names do not return and Leaflet is not a media-script dependency.

The package has not been run against the user's live WordPress database/browser. Install on a staging copy first.
