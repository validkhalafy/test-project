# Artiey Art Platform 2.32.21

- Added artist profile sections for active certificate statistics and curated interviews / related editorial links. Video URLs supported by WordPress oEmbed render in a responsive player; non-embeddable links remain ordinary safe links.
- Added a native scroll-snap portfolio carousel for selected artist works, with touch-friendly mobile behavior.
- Separated the awards list from the career timeline. Existing education and award records are preserved; new independent professional timeline entries can be maintained without duplicating the awards list.
- Added profile-cache invalidation when artist metadata is saved, preventing stale short biography and media fields in same-request previews.
- Added a translation-provider-aware language switcher shortcode `[artiey_language_switcher]` for WPML and Polylang. Actual translated content still requires translated pages / posts and a configured translation provider.
- Bumped route schema so rewrite rules and plugin-owned URL caches refresh once after upgrade.
- Added a shared arbitrary-shortcode field to platform and COA Elementor widget controls; registered WordPress, theme and third-party shortcodes can be appended to the widget output.
