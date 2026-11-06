# QA Report — Artiey Art Platform 2.32.18

- Automated test suite: 709 passed, 0 failed.
- PHP source files: 138 syntax-checked with `php -l`, all successful.
- JavaScript source files: 12 syntax-checked with `node --check`, all successful.
- ZIP archive: checked with `unzip -t`.

## Scope
Regression checks cover public/private collector profile visibility, valid versus inactive entity status, sold versus draft artwork status, unpublished artwork exclusion from auction/secondary market, inactive artist identity suppression, invalid COA route handling, call slug-to-ID resolution, and Elementor route context.

## Environment limitation
These checks run against the plugin source and local WordPress stubs. They do not replace a live integration test against the site's own WordPress database, theme, Elementor version, server PHP configuration, cache layer, or third-party plugins. The release must still be smoke-tested on a staging copy of the live site before production replacement.
