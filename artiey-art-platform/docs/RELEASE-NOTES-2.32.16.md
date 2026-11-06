# Artiey Art Platform 2.32.16 — Complete Elementor tab editor

## What changed

- Added the shared editable tab repeater to every bundled tabbed Art COA Elementor widget: shop, auction list, exhibition timeline, auction timeline, artist hierarchy, portfolio, artist bio, artwork history, and charts.
- Every tab row can be reordered, renamed, assigned an Elementor icon, and enabled or disabled.
- The artist hierarchy tab editor is only visible when the selected relation type is Artist.
- Fixed the empty-repeater edge case: explicitly deleting all repeater rows now removes all tabs, while legacy widgets with no saved repeater setting retain their previous default tabs.
- Added regression tests for all tabbed COA widgets and tab manager conditions.

## Compatibility

- Preserves existing widget settings and the original section-selection controls.
- Legacy widgets without the new repeater setting continue rendering their original tabs.
- No database migration is required.

## Validation

- Complete PHP test suite and PHP/JavaScript syntax checks are run for this release.
- Live Elementor editor testing still requires the target WordPress installation.
