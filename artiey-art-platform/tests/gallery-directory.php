<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
$GLOBALS['__options'] = array();
$GLOBALS['__transients'] = array();
function get_option($key, $default = false) { return $GLOBALS['__options'][$key] ?? $default; }
function get_transient($key) { return $GLOBALS['__transients'][$key] ?? false; }
function set_transient($key, $value, $expiration = 0) { $GLOBALS['__transients'][$key] = $value; return true; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_title($value) { return trim(preg_replace('/[^a-z0-9\-]+/i', '-', strtolower((string) $value)), '-'); }

final class DirectoryWpdb {
    public string $prefix = 'wp_';
    public int $result_calls = 0;
    public string $last_query = '';
    public array $last_args = array();
    public object $row;
    public function esc_like($text) { return addcslashes((string) $text, '_%\\'); }
    public function prepare($query, $args = array()) {
        $this->last_query = (string) $query;
        $this->last_args = is_array($args) ? $args : array_slice(func_get_args(), 1);
        return (string) $query;
    }
    public function get_results($query) {
        $this->result_calls++;
        $this->last_query = (string) $query;
        return array((object) array('id' => 1, 'name' => 'Test Gallery'));
    }
    public function get_row($query) {
        $this->last_query = (string) $query;
        return (object) array('id' => 7, 'slug' => 'test-gallery', 'status' => 'active');
    }
}
$GLOBALS['wpdb'] = new DirectoryWpdb();

require_once dirname(__DIR__) . '/coa/includes/class-wp-coa-pro-galleries.php';

$rows = WP_COA_Pro_Galleries::list_public_directory(12, 'museum', 'Test');
$sql = $GLOBALS['wpdb']->last_query;
$args = $GLOBALS['wpdb']->last_args;
if (count($rows) !== 1 || strpos($sql, "status = 'active'") === false || strpos($sql, 'activity_type = %s') === false || strpos($sql, 'LIMIT 12') === false) {
    fwrite(STDERR, "FAIL: directory must filter and limit rows in SQL\n");
    exit(1);
}
if (($args[0] ?? null) !== 'museum' || ($args[1] ?? null) !== '%Test%' || ($args[2] ?? null) !== '%Test%' || ($args[3] ?? null) !== '%Test%') {
    fwrite(STDERR, "FAIL: directory filters must use prepared parameters\n");
    exit(1);
}
$before = $GLOBALS['wpdb']->result_calls;
WP_COA_Pro_Galleries::list_public_directory(12, 'museum', 'Test');
if ($GLOBALS['wpdb']->result_calls !== $before + 1) {
    fwrite(STDERR, "FAIL: arbitrary search requests should not create persistent transient cache entries\n");
    exit(1);
}

// Repeated unfiltered directory requests use the low-cardinality transient cache.
WP_COA_Pro_Galleries::list_public_directory(12, 'museum', '');
$before = $GLOBALS['wpdb']->result_calls;
WP_COA_Pro_Galleries::list_public_directory(12, 'museum', '');
if ($GLOBALS['wpdb']->result_calls !== $before) {
    fwrite(STDERR, "FAIL: unfiltered directory requests should use transient cache\n");
    exit(1);
}

$profile = WP_COA_Pro_Galleries::get_active_profile_by_slug('Test Gallery');
if (!$profile || strpos($GLOBALS['wpdb']->last_query, "slug = %s AND status = 'active'") === false) {
    fwrite(STDERR, "FAIL: gallery profile lookup must use indexed slug query and active status\n");
    exit(1);
}

echo "Gallery directory regressions: 4 passed, 0 failed\n";
