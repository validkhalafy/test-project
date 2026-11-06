# Artiey Art Platform 2.32.14 — Elementor Dynamic Shortcode Tags

## What changed

- 45 dedicated Dynamic Tags are available for all shortcodes bundled with Artiey Art Platform, Art COA, and the COA compatibility module.
- A universal Dynamic Tag lists every shortcode registered by WordPress, including shortcodes from third-party plugins and the active theme.
- Each tag accepts shortcode attributes and can return either complete HTML or plain text.
- Artist, gallery, and artwork profile/field tags can automatically use the current WordPress post ID when editing a matching single template. Explicit IDs supplied in the attributes always take precedence. Related-exhibitions tags automatically use the current artist or gallery relationship when applicable.
- Tags are grouped into Artiey, Art COA, COA compatibility, and other active shortcodes.

## Usage

1. Edit a widget/control in Elementor and click its Dynamic Tags icon.
2. Find the Artiey, Art COA, or COA shortcode group and select the needed tag.
3. Set optional attributes such as `field="name"`, `sections="header,bio"`, or `limit="12"`.
4. Choose full HTML or plain-text output.
5. For artist/gallery/artwork single templates, leave current-post context enabled to resolve the current record automatically.

The universal shortcode tag can select any currently registered WordPress shortcode. Dynamic Tags UI is an Elementor Pro feature; Elementor Core without Pro remains supported and the plugin continues to run normally, but does not expose the Dynamic Tags interface. Tags are registered for text-compatible controls only; arbitrary shortcode HTML is not exposed as a URL or image value.

## QA

- 561 automated tests passed; 0 failed.
- PHP syntax checks and JavaScript syntax checks are run before packaging.
- Live rendering on the production site's Elementor editor has not been tested in this environment.
