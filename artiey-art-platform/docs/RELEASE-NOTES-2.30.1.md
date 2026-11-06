# Artiey Art Platform 2.30.1 — Elementor Widget Upgrade

## Overview

This release improves Elementor presentation and customization while keeping the canonical WordPress artist post type and existing COA relational records intact. It does not migrate, duplicate, or delete artist records.

## Shared Elementor design controls

- Ready-made visual presets: Classic, Editorial/Curatorial, Luxury/Gallery, Minimal, and Framed.
- Responsive maximum-width control, container border/shadow, inner spacing, corner radius, heading alignment/decorations, and section spacing.
- Expanded card controls: title/subtitle typography and colors, card padding/alignment, border/shadow/radius, and hover effects.
- Shared button/pill typography, colors, hover colors, border radius, and responsive padding.
- Tab appearance choices: pill, square, and underline.
- CSS additions include focus-visible outlines and reduced-motion handling.

## Artist profile widget

- Header layout choices: Classic, Editorial, Centered, and Compact.
- Selectable HTML heading level for the artist name.
- Independent visibility toggles for cover, avatar, Latin name, city, age/birth information, and art-style chips.
- Separate activity and sales chart headings, title/legend/table visibility controls, configurable month range and chart height.
- Configurable artwork-history heading and visibility.
- Optional portfolio captions and target behaviour for artwork image links.
- Added styling controls for career timeline, chart titles, history cards, statement panel, and header style chips.
- Chart sales remain subject to the existing COA privacy policy.

## Other profile and COA widgets

- Gallery profile: Classic, Editorial, Centered, and Compact header layouts.
- Artwork profile: Catalog, Showcase, and Minimal presentation presets.
- Exhibition profile: Editorial, Poster-led, and Compact layouts.
- COA widget design controls now include shared presets, responsive width, title alignment where a title selector exists, card background/padding, typography, button styles, and border/shadow controls.

## Compatibility and data safety

- Existing widget identifiers and saved settings are retained.
- New controls use defaults intended to preserve prior presentation where possible.
- Existing WordPress posts, artist IDs, COA IDs, certificates, and ownership history are not changed by these front-end/style changes.

## Verification

- PHP syntax lint: all 128 PHP files pass in the current build environment.
- JavaScript syntax check: all 12 JavaScript files pass `node --check`.
- Internal test suite: 329 passed, 0 failed.
- CSS brace-balance check: `assets/css/profile.css` and `art-coa/assets/css/frontend.css` balanced.

The release has been tested with the bundled test harness and CLI syntax checks. It has not been run against the user's live WordPress database or an actual browser-based Elementor editor in this environment.
