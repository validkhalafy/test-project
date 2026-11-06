<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
$GLOBALS['__url_options'] = array(
    'artiey_aet_options' => array('base_artist' => 'artist-profiles'),
);
$GLOBALS['__rewrite_rules'] = array();
$GLOBALS['__flush_calls'] = 0;
$GLOBALS['__transients'] = array();
function get_option($key, $default = false) { return $GLOBALS['__url_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['__url_options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['__url_options'][$key]); return true; }
function flush_rewrite_rules($hard = false) { $GLOBALS['__flush_calls']++; return true; }
function wp_cache_delete($key, $group = '') { $GLOBALS['__cache_deletes'][] = array($key, $group); return true; }
function set_transient($key, $value, $expiration = 0) { $GLOBALS['__transients'][$key] = $value; return true; }
function get_transient($key) { return $GLOBALS['__transients'][$key] ?? false; }
function delete_transient($key) { unset($GLOBALS['__transients'][$key]); return true; }
function sanitize_title($value) { return trim(preg_replace('/[^a-z0-9\-]+/', '-', strtolower((string) $value)), '-'); }
function absint($value) { return abs((int) $value); }
function add_rewrite_rule($regex, $query, $after = 'bottom') { $GLOBALS['__rewrite_rules'][] = array($regex, $query, $after); }

final class Artiey_AET_Settings {
    const OPTION = 'artiey_aet_options';
    public static function get($key) {
        $settings = get_option(self::OPTION, array());
        return is_array($settings) ? ($settings[$key] ?? '') : '';
    }
    public static function clear_cache() {}
}

require_once dirname(__DIR__) . '/includes/class-slug.php';

$changed = Artiey_AET_Slug::normalize_artist_base();
if (!$changed || Artiey_AET_Slug::base('artist') !== 'artists') {
    fwrite(STDERR, "FAIL: artist profile base must normalize to /artists/{slug}/\n");
    exit(1);
}
$aliases = get_option('artiey_aet_legacy_bases', array());
if (!in_array('artist-profiles', $aliases['artist'] ?? array(), true)) {
    fwrite(STDERR, "FAIL: custom artist base must be persisted before being replaced\n");
    exit(1);
}

// The old COA profile endpoint has a separate exact-slug migration and must not be shadowed.
$aliases['artist'][] = 'art-person-profile';
update_option('artiey_aet_legacy_bases', $aliases, false);
Artiey_AET_Slug::register_legacy_rules();
$rules = $GLOBALS['__rewrite_rules'];
$has_single_alias = false;
$has_archive_alias = false;
$has_canonical_dispatcher = false;
$has_default_singular = false;
$shadowed_coa_route = false;
foreach ($rules as $rule) {
    if (strpos($rule[0], '^artist') === 0 && strpos($rule[0], 'profiles') !== false && strpos($rule[0], '([^/]+)') !== false && strpos($rule[1], 'post_type=artiey_artist') !== false) { $has_single_alias = true; }
    if (strpos($rule[0], '^artist') === 0 && strpos($rule[0], 'profiles') !== false && strpos($rule[0], '([^/]+)') === false && strpos($rule[1], 'pagename=artists') !== false) { $has_archive_alias = true; }
    if ($rule[0] === '^artist/([^/]+)/?$' && strpos($rule[1], 'post_type=artiey_artist') !== false) { $has_default_singular = true; }
    if ($rule[0] === '^artists/([^/]+)/?$' && strpos($rule[1], 'artiey_artist_route=') !== false && $rule[2] === 'top') { $has_canonical_dispatcher = true; }
    if (strpos($rule[0], '^art-person-profile') === 0) { $shadowed_coa_route = true; }
}
if (!$has_single_alias || !$has_archive_alias || !$has_default_singular || !$has_canonical_dispatcher || $shadowed_coa_route) {
    fwrite(STDERR, "FAIL: legacy artist routes must redirect safely while preserving the legacy COA profile endpoint\n");
    exit(1);
}
if (Artiey_AET_Slug::normalize_artist_base() !== false) {
    fwrite(STDERR, "FAIL: artist URL normalization must be idempotent\n");
    exit(1);
}

// The artist namespace must stay canonical even if an old or manually edited option changes.
update_option('artiey_aet_options', array('base_artist' => 'custom-artists'));
if (Artiey_AET_Slug::base('artist') !== 'artists') {
    fwrite(STDERR, "FAIL: artist base must remain locked to /artists/\n");
    exit(1);
}

// Settings sanitization must also reject attempts to move the artist namespace.
if (strpos(file_get_contents(dirname(__DIR__) . '/includes/class-settings.php'), "( 'artist' === \$k ) ? \$def") === false) {
    fwrite(STDERR, "FAIL: settings sanitization must enforce the canonical artist base\n");
    exit(1);
}

$query_vars = Artiey_AET_Slug::query_vars(array('existing'));
if (!in_array('artiey_artist_route', $query_vars, true)) {
    fwrite(STDERR, "FAIL: canonical artist route must be a registered WordPress query var\n");
    exit(1);
}

// A stale stored rewrite schema must trigger exactly one flush, even when the plugin
// release is newer than the old 2.32.12 marker; subsequent requests must be no-ops.
$GLOBALS['__url_options']['artiey_aet_rewrite_version'] = '2.32.12';
$GLOBALS['__url_options']['artiey_aet_flush'] = 1;
if (!Artiey_AET_Slug::maybe_refresh_routes() || $GLOBALS['__flush_calls'] !== 1) {
    fwrite(STDERR, "FAIL: stale route schema must trigger one rewrite flush\n");
    exit(1);
}
if (get_option('artiey_aet_rewrite_version') !== '2.32.32' || get_option('artiey_aet_flush', false)) {
    fwrite(STDERR, "FAIL: route schema marker and pending-flush flag must be repaired\n");
    exit(1);
}
if (Artiey_AET_Slug::maybe_refresh_routes() !== false || $GLOBALS['__flush_calls'] !== 1) {
    fwrite(STDERR, "FAIL: route refresh must be idempotent and not flush on every request\n");
    exit(1);
}
if (get_option('artiey_aet_url_cache_generation', 0) < 2 || get_transient('artiey_aet_routes_cache_purged_notice') !== 1) {
    fwrite(STDERR, "FAIL: route repair must invalidate URL caches and schedule an admin notice\n");
    exit(1);
}
echo "Artist URL unification regressions: 11 passed, 0 failed\n";
