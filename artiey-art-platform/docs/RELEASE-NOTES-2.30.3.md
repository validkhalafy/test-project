# Artiey Art Platform 2.30.3 — Media Picker Reliability Audit

## Scope

This release follows the media-picker defect report and audits every bundled WordPress Media Library entry point found in the plugin: the shared gallery/artist/artwork meta-box picker, WP Art COA single/multi pickers, COA person profile images, COA institution logo, and certificate artwork image picker.

## Fixes

- **Shared WordPress media fields:** the existing MediaFrame `select` / `open` events remain bound using Backbone event names, not jQuery event namespaces. Selected images are filtered before previews/IDs update; single-image previews match the saved ID; current values are restored on opening the modal.
- **WP Art COA single-image picker:** caches the media frame, reports unavailable Media Library states, restores the current attachment and triggers input changes after select/remove.
- **WP Art COA multi-image picker:** makes the current visible thumbnail list the source of truth on each opening, preventing images removed via the delete button from returning from stale in-memory media selection. Duplicate IDs are prevented, the 24-image storage limit is reflected in the UI, and invalid selections produce feedback.
- **COA person profile image picker:** caches the frame, restores existing portrait/cover selection, reports missing media dependencies, validates selected mime type where present, and clears a stale hidden attachment ID when the URL field is manually edited.
- **COA settings logo picker:** checks whether `wp.media` exists, validates chosen media, previews manual URL edits, and avoids creating a new frame every click.
- **COA certificate artwork image picker:** reuses the frame and restores the currently selected image when reopened.
- **Gallery metadata save:** skips revision/autosave requests and ignores requests with no gallery meta-box payload instead of blanking metadata. Cover and gallery photos accept only image attachments; IDs are normalized, de-duplicated and limited.
- **Artwork metadata save:** skips revisions/autosaves and missing meta-box payloads; additional artwork images are normalized from arrays or legacy CSV metadata, validated as images, de-duplicated and capped at 30.
- **COA/WordPress bridge:** refuses non-image attachments. A malformed non-zero attachment ID no longer clears/desynchronizes an existing valid image. Partial URL-only writes no longer clear attachment IDs accidentally.
- **Asset cache invalidation:** updated cached media scripts and admin CSS use the platform release version. The embedded COA module compatibility versions remain unchanged.

## Verification

- Internal test suite: **354 passed, 0 failed**.
- PHP syntax lint: all PHP files passed.
- JavaScript syntax check (`node --check`): all JavaScript files passed.
- ZIP archive integrity: verified.

## Limitation

The plugin is not connected to the user's live WordPress database/browser in this environment. These tests verify syntax, source-level regressions, and the plugin's local test suite, but do not replace staging verification against the actual WordPress/Elementor installation.
