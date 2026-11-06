# Artiey Art Platform 2.32.29

## Form builder improvements
- Added native HTML5 drag-and-drop reordering for custom registration fields.
- Added a visible drag handle and drop-target feedback.
- Kept the up/down controls as a fallback for touch devices and keyboard users.
- Added Alt+ArrowUp / Alt+ArrowDown keyboard reordering.
- Kept the existing JSON storage format and server-side allow-list sanitization.
- Preserved the 20-field per-role limit and the existing role/field schema.

## QA scope
- PHP syntax checks and JavaScript syntax checks were run on the modified files.
- This archive has not been tested against a live WordPress installation, database, Elementor, or third-party plugins.
- Before production deployment, test registration, saving/reloading field order, select options, permissions, and mobile behavior on a staging copy.
