<?php
/**
 * Minimal WordPress stubs so the pure-logic parts of the plugin can be tested with plain PHP
 * (no WordPress install, no composer needed):  php tests/run.php
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('WP_ART_COA_DIR', dirname(__DIR__) . '/art-coa/');
require_once dirname(__DIR__) . '/includes/class-text.php';
if (!defined('WP_ART_COA_URL')) { define('WP_ART_COA_URL', 'https://example.test/art-coa/'); }
if (!defined('WP_ART_COA_VERSION')) { define('WP_ART_COA_VERSION', 'test'); }

$GLOBALS['__opts'] = [];
$GLOBALS['__trans'] = [];

function get_option($k, $d = false) { return $GLOBALS['__opts'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['__opts'][$k] = $v; return true; }
function get_transient($k) { return $GLOBALS['__trans'][$k] ?? false; }
function set_transient($k, $v, $t = 0) { $GLOBALS['__trans'][$k] = $v; return true; }
function absint($v) { return abs((int) $v); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
if (!function_exists('wp_slash')) { function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; } }
function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
function sanitize_title($s) { return trim(preg_replace('/[^a-z0-9\-]+/', '-', strtolower((string) $s)), '-'); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function wp_strip_all_tags($s) { return strip_tags((string) $s); }
function wp_kses_post($s) { return (string) $s; }
function wp_kses($s, $allowed = []) { return (string) $s; }
function wp_get_attachment_image_url($id, $size = '') { return false; }
function wp_cache_delete() {}
function esc_url_raw($u) { return preg_match('#^https?://#i', (string) $u) ? (string) $u : ''; }
function wp_using_ext_object_cache() { return false; }
function delete_transient($k) { unset($GLOBALS['__trans'][$k]); return true; }
function get_woocommerce_currency() { return $GLOBALS['__wc_currency'] ?? 'IRT'; }
function apply_filters($tag, $value) { return $value; }
function do_action() {}
function add_action() {}
function add_filter() {}

/** Fake $wpdb: returns a canned row for get_row(), records nothing else. */
final class FakeWpdb
{
    public string $prefix = 'wp_';
    public ?object $row = null;
    public int $insert_id = 0;
    /** @var array<int,array{0:string,1:array,2:array|null}> */
    public array $log = [];
    /** value returned by get_var() (null = no row) */
    public $var = null;
    public function prepare(string $q, ...$a): string { return $q; }
    public function get_row($q) { return $this->row; }
    public function get_var($q) { return $this->var; }
    public array $results = [];
    /** Optional per-call rows for identity-conflict tests. */
    public array $result_queue = [];
    public function get_results($q) { return $this->result_queue ? array_shift($this->result_queue) : $this->results; }
    public function query($q) { return 0; }
    public function get_col($q) { return []; }
    public function insert($t, $d) { $this->log[] = ['insert', $d, null]; $this->insert_id++; return 1; }
    public function update($t, $d, $w) { $this->log[] = ['update', $d, $w]; return 1; }
}
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function home_url($p = '') { return 'https://example.test' . $p; }
if (!function_exists('get_post_status')) { function get_post_status($id = null) { return $id ? 'publish' : false; } }
if (!function_exists('get_permalink')) { function get_permalink($id = 0) { return 'https://example.test/post/' . (int) $id . '/'; } }
function gmdate_stub() {}
class Cache_Stub {}

foreach (['wp_enqueue_style', 'wp_enqueue_script', 'wp_register_script', 'wp_register_style'] as $__f) {
    if (!function_exists($__f)) { eval("function $__f() { return true; }"); }
}
if (!function_exists('current_user_can')) { function current_user_can($c) { return false; } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return false; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 0; } }
if (!function_exists('get_query_var')) { function get_query_var($k) { return ''; } }
if (!function_exists('is_singular')) { function is_singular($t = '') { return false; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v) { return json_encode($v, JSON_UNESCAPED_UNICODE); } }
if (!function_exists('absint')) { function absint($v) { return abs((int) $v); } }
if (!function_exists('wp_cache_get')) { function wp_cache_get($k, $g = '', $f = false, &$found = null) { $found = false; return false; } }
if (!function_exists('wp_cache_set')) { function wp_cache_set($k, $v, $g = '', $t = 0) { return true; } }
if (!function_exists('get_transient')) { function get_transient($k) { return false; } }
if (!function_exists('set_transient')) { function set_transient($k, $v, $t = 0) { return true; } }
if (!function_exists('sanitize_html_class')) { function sanitize_html_class($c) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $c); } }
if (!function_exists('remove_query_arg')) { function remove_query_arg($k, $u = false) { return 'https://example.test/page/'; } }
if (!function_exists('add_query_arg')) { function add_query_arg($a, $u = '') { return rtrim((string) $u, '?') . '?' . http_build_query((array) $a); } }
if (!function_exists('current_time')) { function current_time($t) { return gmdate($t === 'mysql' ? 'Y-m-d H:i:s' : $t); } }
foreach (['esc_attr', 'esc_html', 'esc_url', 'esc_textarea'] as $__e) {
    if (!function_exists($__e)) { eval("function $__e(\$s) { return htmlspecialchars((string) \$s, ENT_QUOTES, 'UTF-8'); }"); }
}
$GLOBALS['wpdb'] = new FakeWpdb();

foreach (['class-database', 'class-ulid', 'class-security', 'class-cache', 'class-media', 'class-catalog', 'class-auction', 'class-woo-bridge', 'class-certificate-mirror', 'class-i18n', 'class-profile-fields'] as $f) {
    if (is_file(WP_ART_COA_DIR . "includes/$f.php")) {
        // Some classes declare helpers that need WP at load time only inside methods; loading is safe.
        require_once WP_ART_COA_DIR . "includes/$f.php";
    }
}
require_once WP_ART_COA_DIR . 'includes/entities/class-certificate.php';

/* ---- stubs of the standalone COA Pro module (unified verification fallback) ---- */
$GLOBALS['__coa_record'] = null; $GLOBALS['__coa_sig_ok'] = true; $GLOBALS['__coa_ledger_ok'] = true; $GLOBALS['__coa_flags'] = [];
class WP_COA_Pro_Signature { public function verify($r) { return $GLOBALS['__coa_sig_ok']; } }
class WP_COA_Pro_Ledger { public function verify_certificate_state($r) { return $GLOBALS['__coa_ledger_ok']; } }
class WP_COA_Pro_Repository {
    public function __construct($a = null, $b = null) {}
    public function get_by_code($c) { $r = $GLOBALS['__coa_record']; return $r && $r->certificate_code === $c ? $r : null; }
}
class WP_COA_Pro_Admin { public static function get_settings() { return []; } }
class WP_COA_Pro_Galleries { public static function settings_for_record($r, $s) { return $GLOBALS['__coa_flags']; } }

/* ---- tiny assertion helpers ---- */
final class T
{
    public static int $pass = 0;
    public static int $fail = 0;
    public static function ok(bool $cond, string $name): void
    {
        if ($cond) { self::$pass++; echo "  ok    $name\n"; }
        else { self::$fail++; echo "  FAIL  $name\n"; }
    }
    public static function eq($a, $b, string $name): void
    {
        self::ok($a === $b, $name . ($a === $b ? '' : ' (got ' . var_export($a, true) . ', want ' . var_export($b, true) . ')'));
    }
}
