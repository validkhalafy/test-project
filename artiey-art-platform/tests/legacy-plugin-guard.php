<?php
declare(strict_types=1);
/**
 * Isolated regression test for the legacy-plugin load-order guard.
 * Run: php tests/legacy-plugin-guard.php
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['guard_actions'] = [];
$GLOBALS['guard_deactivation_calls'] = [];
function get_option($key, $default = false) {
    if ($key === 'active_plugins') {
        return ['artiey-exhibition-timeline/artiey-exhibition-timeline.php'];
    }
    return $default;
}
function is_multisite() { return true; }
function get_site_option($key, $default = false) {
    return $key === 'active_sitewide_plugins'
        ? ['wp-coa-pro/wp-coa-pro.php' => 1700000000]
        : $default;
}
function add_action($hook, $callback, $priority = 10) {
    $GLOBALS['guard_actions'][$hook][] = $callback;
}
function register_activation_hook($file, $callback) {
    $GLOBALS['guard_activation_callback'] = $callback;
}
function deactivate_plugins($plugins, $silent = false, $network_wide = null) {
    $GLOBALS['guard_deactivation_calls'][] = [$plugins, $silent, $network_wide];
}
function set_transient($key, $value, $expiration = 0) {
    $GLOBALS['guard_transient'] = [$key, $value, $expiration];
}

include dirname(__DIR__) . '/artiey-art-platform.php';

if (defined('AET_VERSION')) {
    fwrite(STDERR, "FAIL: bundled modules were loaded while legacy plugins were active\n");
    exit(1);
}
if (empty($GLOBALS['guard_actions']['admin_init'])) {
    fwrite(STDERR, "FAIL: legacy deactivation was not scheduled\n");
    exit(1);
}
$callback = $GLOBALS['guard_actions']['admin_init'][0];
$callback();
if (count($GLOBALS['guard_deactivation_calls']) !== 2) {
    fwrite(STDERR, "FAIL: expected both site and network deactivation calls\n");
    exit(1);
}
$expected = [
    'artiey-exhibition-timeline/artiey-exhibition-timeline.php',
    'wp-coa-pro/wp-coa-pro.php',
    'wp-art-coa-pro/wp-art-coa-pro.php',
];
foreach ($GLOBALS['guard_deactivation_calls'] as $call) {
    if ($call[0] !== $expected || $call[1] !== true) {
        fwrite(STDERR, "FAIL: deactivation did not target all legacy plugin files silently\n");
        exit(1);
    }
}
if ($GLOBALS['guard_deactivation_calls'][0][2] !== false || $GLOBALS['guard_deactivation_calls'][1][2] !== true) {
    fwrite(STDERR, "FAIL: site/network deactivation modes were not both used\n");
    exit(1);
}
echo "PASS: legacy plugin load-order guard prevents bundled module loading and schedules site/network deactivation\n";
