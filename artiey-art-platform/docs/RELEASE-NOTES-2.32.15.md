# Artiey Art Platform 2.32.15 — Elementor tabs editor and preview fixes

## What changed

- Added a tab editor repeater to artist, gallery, artwork, exhibition, directory, and statistics widgets.
- Reorder tabs with drag-and-drop, customize labels, add Elementor icons, and enable/disable each tab.
- The existing content keys and renderers remain the source of truth; tab customization only affects ordering and presentation.
- Fixed Elementor live preview when it replaces tab content inside a container that was already initialized. The observer now reconciles existing tab containers and preserves the active tab when its key still exists.
- Disabled all tabs means no tab content is rendered; malformed stale repeater data safely falls back to the original tab order.
- Improved icon alignment and removed a contradictory hidden-panel CSS declaration.

## Where to edit

Open an Artiey widget in Elementor, choose a tab layout (for example, "تب‌دار"), then use the "ویرایش و ترتیب تب‌ها" repeater. Drag rows to reorder, select the connected section, provide an optional label and icon, and toggle visibility.

## QA

Automated tests cover widget control registration, available repeater fields, order changes, label sanitization, hidden tabs, empty tab state, and the Elementor re-render observer. Live behavior on the production Elementor editor still requires confirmation on the website.
