# Artiey Art Platform 2.30.4 — Profile Tabs and Chart Connections

## Elementor profile tabs
- Set profile layout to **Tabbed** to give each selected, data-bearing section its own clickable tab.
- Choose **Horizontal** tabs above content or **Vertical** tabs beside content.
- Choose a separate mobile orientation (horizontal, vertical, or same as desktop).
- Style navigation width, typography, alignment, padding, gap, active colour, border, radius and panel background/padding.
- Keyboard interaction is orientation-aware and retains ARIA tab/tabpanel relationships. Existing stacked layout remains the default.
- Direction controls are available in artist, gallery and artwork profile widgets.

## Activity and sales chart connection
Each chart has its own connection mode in the artist profile widget:

1. **Automatic COA connection** — resolves the artist's canonical WordPress post to its linked COA artist row and renders through the internal ChartKit.
2. **Manual shortcode** — executes one or more approved chart shortcodes. Use the matching field for activity or sales.

Examples:

```text
[art_coa_charts type="activity" artist="{artist_id}" months="{months}"]
[art_coa_charts type="sales_value" artist="{artist_id}" months="{months}"]
```

Placeholders:
- `{artist_id}` — linked numeric artist ID in COA; if no COA relationship exists, this placeholder fails closed.
- `{artist_post_id}` — canonical WordPress artist post ID.
- `{months}` — selected chart time window (3–36 months).

Only `[art_coa_charts]` and `[artiey_charts]` are allowed in these manual fields. Arbitrary shortcodes are rejected. The COA chart renderer continues to enforce access control and sales-data privacy. If the connection is not configured or does not return output, explanatory diagnostics are visible to editors only.

## Validation performed
- 375 automated tests passed, zero failures.
- All 128 PHP files passed `php -l`.
- All 12 JavaScript files passed `node --check`.
- CSS brace integrity checks passed.
- ZIP archive integrity checked before delivery.

Live WordPress/Elementor runtime verification has not been performed; test on a staging copy before replacing the active plugin.
