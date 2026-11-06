<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('OBJECT', 'OBJECT');
define('WP_COA_PRO_URL', 'https://example.test/coa/');
define('WP_COA_PRO_VERSION', 'test');

$GLOBALS['__options'] = array();
$GLOBALS['__posts'] = array();
$GLOBALS['__post_meta'] = array();
$GLOBALS['__post_updates'] = array();
function get_option($key, $default = false) { return $GLOBALS['__options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['__options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['__options'][$key]); return true; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function __( $text, $domain = null ) { return $text; }
function get_post($id) { return $GLOBALS['__posts'][(int) $id] ?? null; }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['__post_meta'][(int) $id][$key] ?? ''; }
function get_posts($args = array()) {
    $result = array();
    foreach ($GLOBALS['__posts'] as $post) {
        if (($post->post_type ?? '') !== ($args['post_type'] ?? '')) { continue; }
        if (!in_array(($post->post_status ?? ''), (array) ($args['post_status'] ?? array()), true)) { continue; }
        if (($GLOBALS['__post_meta'][(int) $post->ID][$args['meta_key'] ?? ''] ?? null) !== ($args['meta_value'] ?? null)) { continue; }
        $result[] = $post;
    }
    usort($result, static function ($a, $b) { return (int) $a->ID <=> (int) $b->ID; });
    return array_slice($result, 0, (int) ($args['posts_per_page'] ?? 1));
}
function get_permalink($id) { return 'https://example.test/page-' . (int) $id . '/'; }
function get_page_by_path($path, $output = null, $post_type = 'page') {
    foreach ($GLOBALS['__posts'] as $post) {
        if (($post_type === 'any' || ($post->post_type ?? '') === $post_type) && ($post->post_name ?? '') === $path && (int) ($post->post_parent ?? 0) === 0) { return $post; }
    }
    return null;
}
function is_wp_error($value) { return false; }
function wp_insert_post($data, $wp_error = false) {
    $id = max(array_keys($GLOBALS['__posts'] ?: array(0 => 0))) + 1;
    $post = (object) array_merge(array('ID' => $id, 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => 0, 'post_content' => ''), $data);
    unset($post->meta_input);
    $GLOBALS['__posts'][$id] = $post;
    foreach (($data['meta_input'] ?? array()) as $key => $value) { $GLOBALS['__post_meta'][$id][$key] = $value; }
    return $id;
}
function update_post_meta($id, $key, $value) { $GLOBALS['__post_meta'][(int) $id][$key] = $value; return true; }
function delete_post_meta($id, $key) { unset($GLOBALS['__post_meta'][(int) $id][$key]); return true; }
function wp_update_post($data) {
    $id = (int) ($data['ID'] ?? 0);
    $GLOBALS['__post_updates'][] = $data;
    if (isset($GLOBALS['__posts'][$id])) { foreach ($data as $key => $value) { if ($key !== 'ID') { $GLOBALS['__posts'][$id]->{$key} = $value; } } }
    return $id;
}

require_once dirname(__DIR__) . '/coa/includes/class-wp-coa-pro-page-integration.php';

// The stored ID now belongs to an unrelated page; a valid managed page still exists.
$GLOBALS['__posts'][10] = (object) array('ID' => 10, 'post_type' => 'page', 'post_status' => 'publish');
$GLOBALS['__post_meta'][10]['_wp_coa_pro_managed_page'] = 'something-else';
$GLOBALS['__posts'][42] = (object) array('ID' => 42, 'post_type' => 'page', 'post_status' => 'publish');
$GLOBALS['__post_meta'][42]['_wp_coa_pro_managed_page'] = 'verification';
$GLOBALS['__options'][WP_COA_Pro_Page_Integration::PAGE_IDS_OPTION] = array('verification' => 10);

$url = WP_COA_Pro_Page_Integration::get_managed_page_url('verification');
if ($url !== 'https://example.test/page-42/') {
    fwrite(STDERR, "FAIL: stale page IDs must resolve to the actual managed page\n");
    exit(1);
}
$ids = get_option(WP_COA_Pro_Page_Integration::PAGE_IDS_OPTION, array());
if (($ids['verification'] ?? 0) !== 42) {
    fwrite(STDERR, "FAIL: repaired managed page ID must be persisted\n");
    exit(1);
}

// A draft is still managed/editable but must not be exposed as a public destination.
$GLOBALS['__posts'][42]->post_status = 'draft';
if (WP_COA_Pro_Page_Integration::get_managed_page_url('verification') !== '') {
    fwrite(STDERR, "FAIL: draft managed pages must not produce public URLs\n");
    exit(1);
}
if (WP_COA_Pro_Page_Integration::get_managed_page_url('not-a-managed-key') !== '') {
    fwrite(STDERR, "FAIL: unknown managed page key must fail closed\n");
    exit(1);
}

// An unexpected status (such as auto-draft) is not a valid managed page state.
$GLOBALS['__posts'][42]->post_status = 'auto-draft';
if (WP_COA_Pro_Page_Integration::get_managed_page_url('verification') !== '') {
    fwrite(STDERR, "FAIL: auto-draft managed pages must not produce public URLs\n");
    exit(1);
}

echo "Page integration regressions: 4 passed, 0 failed\n";


// The canonical archive remains /artists/ and uses one shared WordPress artist source.
$recommended = new ReflectionMethod('WP_COA_Pro_Page_Integration', 'recommended_pages');
$recommended->setAccessible(true);
$recommended_pages = $recommended->invoke(null);
if (($recommended_pages['artist_directory']['slug'] ?? '') !== 'artists'
    || strpos((string) ($recommended_pages['artist_directory']['shortcode'] ?? ''), '[artiey_artist_archive') !== 0) {
    fwrite(STDERR, "FAIL: artist archive must use /artists/ and the canonical Artiey archive shortcode\n");
    exit(1);
}

$GLOBALS['__posts'][43] = (object) array(
    'ID' => 43, 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'artists', 'post_parent' => 0,
    'post_title' => 'هنرمندان', 'post_content' => '<h1>هنرمندان ما</h1>[coa_artist_directory columns="4"] [coa_profile_directory type="collector"]',
);
$GLOBALS['__post_meta'][43]['_wp_coa_pro_managed_page'] = 'artist_directory';
$GLOBALS['__options'][WP_COA_Pro_Page_Integration::PAGE_IDS_OPTION] = array('artist_directory' => 43);
WP_COA_Pro_Page_Integration::maybe_unify_artist_archive_page();
$artist_content = (string) $GLOBALS['__posts'][43]->post_content;
if (strpos($artist_content, '[artiey_artist_archive columns="3" per_page="12" ajax="yes"]') === false
    || strpos($artist_content, '[coa_artist_directory') !== false
    || strpos($artist_content, '[coa_profile_directory type="collector"]') === false
    || strpos($artist_content, '<h1>هنرمندان ما</h1>') === false) {
    fwrite(STDERR, "FAIL: archive migration should replace only artist directory output and preserve page content/collector directory\n");
    exit(1);
}
if (get_option('wp_coa_pro_artist_archive_unified_23212') !== 1) {
    fwrite(STDERR, "FAIL: artist archive migration marker should be stored after success\n");
    exit(1);
}
$updates_after_first_run = count($GLOBALS['__post_updates']);
WP_COA_Pro_Page_Integration::maybe_unify_artist_archive_page();
if (count($GLOBALS['__post_updates']) !== $updates_after_first_run) {
    fwrite(STDERR, "FAIL: artist archive migration must be idempotent\n");
    exit(1);
}

// An existing Elementor archive at /artists/ is adopted only when its document contains the artist directory widget.
unset($GLOBALS['__options']['wp_coa_pro_artist_archive_unified_23212']);
delete_post_meta(43, '_wp_coa_pro_managed_page');
$GLOBALS['__posts'][43]->post_name = 'artists-old';
$GLOBALS['__options'][WP_COA_Pro_Page_Integration::PAGE_IDS_OPTION] = array();
$GLOBALS['__posts'][44] = (object) array(
    'ID' => 44, 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'artists', 'post_parent' => 0,
    'post_title' => 'آرشیو هنرمندان', 'post_content' => '<!-- existing Elementor design -->',
);
$GLOBALS['__post_meta'][44]['_elementor_data'] = json_encode(array(array(
    'widgetType' => 'wp-coa-person-directory', 'settings' => array('profile_type' => 'artist'),
)));
WP_COA_Pro_Page_Integration::maybe_unify_artist_archive_page();
if (($GLOBALS['__post_meta'][44]['_wp_coa_pro_managed_page'] ?? '') !== 'artist_directory'
    || (($GLOBALS['__options'][WP_COA_Pro_Page_Integration::PAGE_IDS_OPTION]['artist_directory'] ?? 0) !== 44)) {
    fwrite(STDERR, "FAIL: existing /artists/ Elementor artist archive should be adopted without replacing its design\n");
    exit(1);
}
if (($GLOBALS['__posts'][44]->post_content ?? '') !== '<!-- existing Elementor design -->') {
    fwrite(STDERR, "FAIL: adoption must preserve the existing Elementor page content\n");
    exit(1);
}

// A public /artists/ page owns the canonical route even when it was not originally created by COA.
// Keep its copy, append the archive shortcode, and retire the old managed archive as a redirect.
$GLOBALS['__posts'][44]->post_name = 'artists-elementor-old';
unset($GLOBALS['__options']['wp_coa_pro_artist_archive_unified_23212']);
$GLOBALS['__posts'][45] = (object) array(
    'ID' => 45, 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'artists', 'post_parent' => 0,
    'post_title' => 'هنرمندان آرتی', 'post_content' => '<h1>به هنرمندان ما خوش آمدید</h1>',
);
$GLOBALS['__posts'][46] = (object) array(
    'ID' => 46, 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'artists-old', 'post_parent' => 0,
    'post_title' => 'آرشیو قدیمی', 'post_content' => '[artiey_artist_archive]',
);
$GLOBALS['__post_meta'][46]['_wp_coa_pro_managed_page'] = 'artist_directory';
$GLOBALS['__options'][WP_COA_Pro_Page_Integration::PAGE_IDS_OPTION] = array('artist_directory' => 46);
WP_COA_Pro_Page_Integration::maybe_unify_artist_archive_page();
if (strpos($GLOBALS['__posts'][45]->post_content, '<h1>به هنرمندان ما خوش آمدید</h1>') === false
    || strpos($GLOBALS['__posts'][45]->post_content, '[artiey_artist_archive') === false) {
    fwrite(STDERR, "FAIL: canonical /artists/ page should keep its content and receive the artist archive output\n");
    exit(1);
}
if (($GLOBALS['__post_meta'][45]['_wp_coa_pro_managed_page'] ?? '') !== 'artist_directory'
    || ($GLOBALS['__post_meta'][46]['_wp_coa_pro_artist_archive_legacy_redirect'] ?? 0) != 1
    || isset($GLOBALS['__post_meta'][46]['_wp_coa_pro_managed_page'])
    || (($GLOBALS['__options'][WP_COA_Pro_Page_Integration::PAGE_IDS_OPTION]['artist_directory'] ?? 0) !== 45)) {
    fwrite(STDERR, "FAIL: archive ID must be moved to /artists/ and the prior managed page must become a 301 alias\n");
    exit(1);
}


// A deleted canonical archive must not remain stuck behind a completed one-time migration.
foreach ($GLOBALS['__posts'] as $existing_id => $existing_post) {
    if (($existing_post->post_type ?? '') === 'page' && ($existing_post->post_name ?? '') === 'artists' && (int) ($existing_post->post_parent ?? 0) === 0) {
        unset($GLOBALS['__posts'][$existing_id], $GLOBALS['__post_meta'][$existing_id]);
    }
}
$GLOBALS['__options'][WP_COA_Pro_Page_Integration::PAGE_IDS_OPTION] = array();
delete_option('wp_coa_pro_artist_archive_unified_23212');
WP_COA_Pro_Page_Integration::maybe_unify_artist_archive_page();
$new_archive_id = (int) ($GLOBALS['__options'][WP_COA_Pro_Page_Integration::PAGE_IDS_OPTION]['artist_directory'] ?? 0);
if (!$new_archive_id || !isset($GLOBALS['__posts'][$new_archive_id])
    || $GLOBALS['__posts'][$new_archive_id]->post_name !== 'artists'
    || $GLOBALS['__posts'][$new_archive_id]->post_status !== 'publish'
    || ($GLOBALS['__post_meta'][$new_archive_id]['_wp_coa_pro_managed_page'] ?? '') !== 'artist_directory') {
    fwrite(STDERR, "FAIL: deleted /artists/ archive should be recreated as a published managed page when the root slug is free\n");
    exit(1);
}
$recovered_content = (string) ($GLOBALS['__posts'][$new_archive_id]->post_content ?? '');
$recovered_elementor = (string) ($GLOBALS['__post_meta'][$new_archive_id]['_elementor_data'] ?? '');
if (strpos($recovered_content, '[artiey_artist_archive') === false
    && (strpos($recovered_elementor, 'wp-coa-person-directory') === false || strpos($recovered_elementor, '"profile_type":"artist"') === false)) {
    fwrite(STDERR, "FAIL: recreated /artists/ archive should contain the canonical artist directory output in WordPress or Elementor content\n");
    exit(1);
}
// A managed archive left in draft must be restored to public so anonymous visitors do not get 404.
$GLOBALS['__posts'][$new_archive_id]->post_status = 'draft';
delete_option('wp_coa_pro_artist_archive_unified_23212');
WP_COA_Pro_Page_Integration::maybe_unify_artist_archive_page();
if (($GLOBALS['__posts'][$new_archive_id]->post_status ?? '') !== 'publish') {
    fwrite(STDERR, "FAIL: managed /artists/ archive should be republished if left in draft\n");
    exit(1);
}
// An Elementor artist archive whose managed marker was lost is still safely identifiable.
$GLOBALS['__posts'][$new_archive_id]->post_status = 'draft';
delete_post_meta($new_archive_id, '_wp_coa_pro_managed_page');
delete_option('wp_coa_pro_artist_archive_unified_23212');
WP_COA_Pro_Page_Integration::maybe_unify_artist_archive_page();
if (($GLOBALS['__posts'][$new_archive_id]->post_status ?? '') !== 'publish'
    || ($GLOBALS['__post_meta'][$new_archive_id]['_wp_coa_pro_managed_page'] ?? '') !== 'artist_directory') {
    fwrite(STDERR, "FAIL: identifiable Elementor /artists/ archive should be adopted and restored to public status\n");
    exit(1);
}
echo "Artist archive unification regressions: 13 passed, 0 failed\n";
