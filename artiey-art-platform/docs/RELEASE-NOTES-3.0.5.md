# Artiey Art Platform 3.0.5

## Fixed
- Legacy replacement detection now covers all three standalone plugins in site and Multisite network activation lists.
- Avoided duplicate class loading when a legacy plugin appears later in WordPress' plugin load order by keeping bundled modules inert for that request.
- Deactivation now targets both site-level and network-level legacy activations while preserving their data and tables.

## Tests
- `php tests/legacy-plugin-guard.php`: passed.
- `php tests/run.php`: 728 passed, 0 failed.
- PHP and JavaScript syntax checks: passed (see QA report).
