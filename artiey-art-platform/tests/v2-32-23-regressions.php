<?php
/**
 * 2.32.23 regressions: appearance layer, page builder, cache control, hierarchy privacy, Cache::get falsy values.
 * Run:  php tests/v2-32-23-regressions.php
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_ART_COA_DIR', dirname(__DIR__) . '/art-coa/');
define('MINUTE_IN_SECONDS', 60);

/* ---------------- tiny WordPress stand-in ---------------- */
$GLOBALS['o'] = []; $GLOBALS['t'] = []; $GLOBALS['c'] = [];
$GLOBALS['posts'] = []; $GLOBALS['meta'] = []; $GLOBALS['next_id'] = 100; $GLOBALS['var_id'] = null;
function get_option($k, $d = false) { return $GLOBALS['o'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['o'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['o'][$k]); return true; }
function get_transient($k) { return $GLOBALS['t'][$k] ?? false; }
function set_transient($k, $v, $e = 0) { $GLOBALS['t'][$k] = $v; return true; }
function delete_transient($k) { unset($GLOBALS['t'][$k]); return true; }
function wp_cache_get($k, $g = '', $f = false, &$found = null) { if (array_key_exists($g . $k, $GLOBALS['c'])) { $found = true; return $GLOBALS['c'][$g . $k]; } $found = false; return false; }
function wp_cache_set($k, $v, $g = '', $e = 0) { $GLOBALS['c'][$g . $k] = $v; return true; }
function wp_cache_delete($k, $g = '') { unset($GLOBALS['c'][$g . $k]); return true; }
function apply_filters($tag, $value) { return $value; }
function do_action() {}
function add_action() {}
function add_filter() {}
function absint($v) { return abs((int) $v); }
function sanitize_text_field($s) { return trim(preg_replace('/\s+/', ' ', strip_tags((string) $s))); }
function sanitize_title($s) { return trim((string) preg_replace('/[^a-z0-9\-]+/', '-', strtolower((string) $s)), '-'); }
function wp_strip_all_tags($s) { return strip_tags((string) $s); }
function wp_json_encode($v) { return json_encode($v); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function admin_url($p = '') { return 'https://example.test/wp-admin/' . $p; }
function current_user_can($c) { return true; }
function get_post($id) { return $GLOBALS['posts'][(int) $id] ?? null; }
function get_post_meta($id, $k, $single = false) { return $GLOBALS['meta'][(int) $id][$k] ?? ''; }
function update_post_meta($id, $k, $v) { $GLOBALS['meta'][(int) $id][$k] = $v; return true; }
function get_permalink($id) { return 'https://example.test/?p=' . (int) $id; }
function is_wp_error($v) { return false; }
function wp_insert_post($data, $err = false) {
    $id = $GLOBALS['next_id']++;
    $GLOBALS['posts'][$id] = (object) ['ID' => $id, 'post_type' => $data['post_type'], 'post_status' => $data['post_status'],
        'post_title' => $data['post_title'], 'post_name' => $data['post_name'], 'post_content' => $data['post_content']];
    foreach ((array) ($data['meta_input'] ?? []) as $k => $v) { $GLOBALS['meta'][$id][$k] = $v; }
    return $id;
}
function wp_update_post($data) { foreach ($data as $k => $v) { if ($k !== 'ID') { $GLOBALS['posts'][(int) $data['ID']]->$k = $v; } } return (int) $data['ID']; }
function wp_untrash_post($id) { $GLOBALS['posts'][(int) $id]->post_status = 'draft'; return true; }
function get_posts($args = []) {
    $out = [];
    foreach ($GLOBALS['posts'] as $p) {
        if ($p->post_type !== ($args['post_type'] ?? '')) { continue; }
        if (!in_array($p->post_status, (array) ($args['post_status'] ?? []), true)) { continue; }
        if (isset($args['meta_key']) && ($GLOBALS['meta'][$p->ID][$args['meta_key']] ?? null) !== $args['meta_value']) { continue; }
        $out[] = $p;
    }
    return array_slice($out, 0, (int) ($args['posts_per_page'] ?? 1));
}
final class FakeDb {
    public string $posts = 'wp_posts'; public string $postmeta = 'wp_postmeta'; public string $options = 'wp_options';
    public function esc_like($s) { return addcslashes((string) $s, '_%\\'); }
    public function prepare($q, ...$a) { return $q; }
    /** Emulates the shortcode lookup: first page whose content contains the "[tag" needle passed to prepare(). */
    public function get_var($q) { return $GLOBALS['var_id']; }
}
$GLOBALS['wpdb'] = new FakeDb();

final class Artiey_AET_Slug { public static function base($k) { return ['gallery' => 'galleries', 'exhibition' => 'exhibitions', 'artist' => 'artists', 'artwork' => 'artworks', 'style' => 'styles'][$k] ?? $k; } }

require dirname(__DIR__) . '/includes/class-appearance.php';
require dirname(__DIR__) . '/includes/class-page-builder.php';
require dirname(__DIR__) . '/includes/class-cache-control.php';
require dirname(__DIR__) . '/includes/class-image-sitemap.php';
require WP_ART_COA_DIR . 'includes/class-cache.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $n): void { global $pass, $fail; if ($c) { $pass++; echo "  ok    $n\n"; } else { $fail++; echo "  FAIL  $n\n"; } }

/* ---------------- Appearance ---------------- */
echo "# Appearance\n";
$s = Artiey_AET_Appearance::sanitize(['primary' => '#ABC', 'accent' => 'red', 'radius' => '99', 'font_size' => '5', 'max_width' => '5000', 'shadow' => 'evil', 'font' => 'nope']);
ok($s['primary'] === '#abc', 'valid short hex accepted and lowercased');
ok($s['accent'] === '', 'named colour rejected');
ok($s['radius'] === '32', 'radius clamped to 32');
ok($s['font_size'] === '13', 'font size clamped to 13');
ok($s['max_width'] === '1600', 'max width clamped to 1600');
ok($s['shadow'] === '' && $s['font'] === '', 'unknown shadow/font collapse to default');
ok(Artiey_AET_Appearance::build_css([]) === '', 'no settings -> no CSS emitted');
$css = Artiey_AET_Appearance::build_css(['primary' => '#112233', 'accent' => '#c9a227', 'radius' => '12', 'shadow' => 'none']);
ok(strpos($css, '--art-coa-primary:#112233') !== false, 'primary overrides COA token');
ok(strpos($css, '--art-coa-primary-soft:#11223322') !== false, 'soft colour is 8-digit hex');
ok(strpos($css, '.aetp{--aetp-accent:#c9a227}') !== false, 'platform accent set on its own scope');
ok(strpos($css, '--aap-r:12px') !== false && strpos($css, '--aap-r-lg:19px') !== false, 'radius scale derived');
ok(strpos($css, '--aap-shadow:none') !== false, 'shadow preset applied');
ok(Artiey_AET_Appearance::soft('#abc') === '#aabbcc22', '3-digit hex expanded before alpha');
$bad = Artiey_AET_Appearance::clean_css("a{color:red}</style><script>alert(1)</script>@import url(http://x/y.css);b{background:url(javascript:alert(1))}");
ok(stripos($bad, '<') === false && stripos($bad, '@import') === false && stripos($bad, 'javascript') === false, 'custom CSS cannot break out or import');
ok(strpos($bad, 'a{color:red}') !== false, 'legitimate CSS survives cleaning');
$f = Artiey_AET_Appearance::sanitize(['font' => 'custom', 'custom_font' => 'Vazirmatn, "My Font"; } body{display:none']);
ok(strpos($f['custom_font'], ';') === false && strpos($f['custom_font'], '{') === false, 'custom font list cannot inject declarations');

/* ---------------- Page builder ---------------- */
echo "# Page builder\n";
$defs = Artiey_AET_Page_Builder::definitions();
ok(count($defs) >= 15, 'definitions cover the platform pages');
$tags = array_column($defs, 'tag');
ok(count($tags) === count(array_unique($tags)), 'no two definitions share a shortcode tag');
$slugs = array_column($defs, 'slug');
ok(count($slugs) === count(array_unique($slugs)), 'default slugs are unique');
ok(!array_intersect($slugs, Artiey_AET_Page_Builder::reserved_slugs()), 'no default slug collides with a CPT base or route');
ok(Artiey_AET_Page_Builder::clean_slug('Galleries') === 'galleries-page', 'slug equal to a CPT archive base is made safe');
ok(Artiey_AET_Page_Builder::clean_slug('', 'fallback') === 'fallback', 'empty slug uses fallback');

$r = Artiey_AET_Page_Builder::ensure('shop');
ok($r['result'] === 'created' && $r['id'] > 0, 'missing page is created');
$id = $r['id'];
ok($GLOBALS['posts'][$id]->post_content === '<!-- wp:shortcode -->[art_coa_shop limit="24" columns="3"]<!-- /wp:shortcode -->', 'created page carries only its shortcode');
ok(Artiey_AET_Page_Builder::ensure('shop')['result'] === 'existing', 'second run does not duplicate');
ok(count($GLOBALS['posts']) === 1, 'exactly one page exists after two runs');

// A page already showing the shortcode (found through the DB lookup) is adopted, not duplicated.
$GLOBALS['posts'][500] = (object) ['ID' => 500, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Mine', 'post_name' => 'my-auctions', 'post_content' => '[art_coa_auction]'];
$GLOBALS['var_id'] = 500;
$a = Artiey_AET_Page_Builder::ensure('auctions');
ok($a['result'] === 'existing' && $a['id'] === 500, 'existing page with the shortcode is adopted');
$GLOBALS['var_id'] = null;

// An unrelated page with the same slug is not taken over.
$GLOBALS['posts'][600] = (object) ['ID' => 600, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Calls', 'post_name' => 'art-calls', 'post_content' => 'unrelated text'];
$c = Artiey_AET_Page_Builder::ensure('calls');
ok($c['result'] === 'created' && $c['id'] !== 600, 'unrelated page with the same slug is left alone');

// Trashed by the admin: automatic run respects it, explicit repair restores it.
$GLOBALS['posts'][$id]->post_status = 'trash';
ok(Artiey_AET_Page_Builder::ensure('shop', false)['result'] === 'skipped', 'automatic run does not re-create a trashed page');
ok(Artiey_AET_Page_Builder::status('shop') === 'trash', 'status reports trash');
ok(Artiey_AET_Page_Builder::ensure('shop', true)['result'] === 'restored' && $GLOBALS['posts'][$id]->post_status === 'publish', 'repair restores and publishes');
ok(Artiey_AET_Page_Builder::url('shop') !== '', 'url() resolves for a published page');

// Overrides are validated.
update_option(Artiey_AET_Page_Builder::OVERRIDES, ['stats' => ['title' => '<b>آمار ما</b>', 'slug' => 'artists']]);
$st = Artiey_AET_Page_Builder::resolved('stats');
ok($st['title'] === 'آمار ما', 'override title is sanitised');
ok($st['slug'] === 'artists-page', 'override slug colliding with /artists/ is neutralised');

/* ---------------- Cache control ---------------- */
echo "# Cache control\n";
ok(Artiey_AET_Cache_Control::content_has_dynamic_shortcode('x [art_coa_register] y'), 'register form is dynamic');
ok(Artiey_AET_Cache_Control::content_has_dynamic_shortcode('[art_coa_auction limit="3"]'), 'auction shortcode with attributes is dynamic');
ok(!Artiey_AET_Cache_Control::content_has_dynamic_shortcode('[art_coa_auction_timeline]'), 'similarly-named static shortcode is not matched');
ok(!Artiey_AET_Cache_Control::content_has_dynamic_shortcode('plain text'), 'plain content is cacheable');
ok(Artiey_AET_Cache_Control::is_dynamic_tag('art_coa_shop') && !Artiey_AET_Cache_Control::is_dynamic_tag('artiey_stats'), 'tag classifier');

/* ---------------- Art COA Cache::get ---------------- */
echo "# Cache::get\n";
$calls = 0; $cb = function () use (&$calls) { $calls++; return false; };
\WPArtCOA\Cache::get('probe', $cb); \WPArtCOA\Cache::get('probe', $cb);
ok($calls === 1, 'a false result is cached instead of recomputed every time');
$GLOBALS['c'] = [];
\WPArtCOA\Cache::get('probe', $cb);
ok($calls === 1, 'transient fallback serves it after an object-cache wipe');
$GLOBALS['t']['art_coa_old'] = ['n' => 5];
ok(\WPArtCOA\Cache::get('old', fn() => 'x') === ['n' => 5], 'entries written by older versions are still readable');

/* ---------------- Hierarchy privacy ---------------- */
echo "# Hierarchy::public_tree\n";
require WP_ART_COA_DIR . 'includes/class-hierarchy.php';
$ex = ['id' => 1, 'title' => 'E', 'slug' => 'e', 'start' => '', 'end' => '', 'artworks' => [
    (object) ['id' => 1, 'title' => 'Public', 'status' => 'published'],
    (object) ['id' => 2, 'title' => 'Draft', 'status' => 'draft'],
]];
$tree = \WPArtCOA\Hierarchy::public_tree(['gallery' => (object) [], 'artists' => [], 'exhibitions' => [$ex]], 'gallery');
ok(count($tree['exhibitions'][0]['artworks']) === 1 && $tree['exhibitions'][0]['artworks'][0]->title === 'Public', 'draft artworks linked from an exhibition are hidden');
$col = ['collector' => (object) ['display_name' => 'C'], 'artworks' => [
    ['artwork' => (object) ['status' => 'approved'], 'cert' => (object) ['status' => 'revoked', 'serial_number' => 'X']],
    ['artwork' => (object) ['status' => 'pending'], 'cert' => null],
], 'history' => [(object) ['artwork_id' => 1]]];
$ct = \WPArtCOA\Hierarchy::public_tree($col, 'collector');
ok(count($ct['artworks']) === 1, 'collector page drops non-public artworks');
ok($ct['artworks'][0]['cert'] === null, 'collector page drops non-active certificates');
ok($ct['history'] === [], 'collector page hides transfer history');


/* ---------------- Image sitemap ---------------- */
echo "# Image sitemap\n";
$xml = Artiey_AET_Image_Sitemap::build_xml([
    ['loc' => 'https://e.test/artworks/a/?x=1&y=2', 'lastmod' => '2026-01-02T03:04:05+00:00', 'images' => [['loc' => 'https://e.test/u/a.jpg', 'title' => 'اثر <b> & "x"', 'caption' => "کپشن\x01"], ['loc' => 'https://e.test/u/b.jpg']]],
    ['loc' => 'https://e.test/no-images/', 'images' => []],
]);
$dom = new DOMDocument(); $loaded = $dom->loadXML($xml);
ok($loaded === true, 'sitemap is well-formed XML even with <, &, quotes and control characters in titles');
ok(substr_count($xml, '<url>') === 1 && substr_count($xml, '<image:image>') === 2, 'URLs without images are skipped');
ok(strpos($xml, 'x=1&amp;y=2') !== false, 'ampersands in URLs are escaped');
ok(strpos($xml, 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"') !== false, 'image namespace declared');
$many = ['loc' => 'https://e.test/x/', 'images' => array_map(fn($i) => ['loc' => "https://e.test/$i.jpg"], range(1, 20))];
ok(substr_count(Artiey_AET_Image_Sitemap::build_xml([$many]), '<image:image>') === Artiey_AET_Image_Sitemap::MAX_IMAGES_PER_URL, 'image count per URL is capped');
ok(Artiey_AET_Image_Sitemap::usable_image('/relative.jpg') === '' && Artiey_AET_Image_Sitemap::usable_image('javascript:alert(1)') === '', 'only absolute http(s) image URLs are listed');

/* ---------------- Hierarchy batching ---------------- */
echo "# Hierarchy query batching\n";
require __DIR__ . '/hierarchy-stubs.php';
final class HierDb {
    public string $prefix = 'wp_'; public array $log = [];
    public function prepare($q, ...$a) { return vsprintf(str_replace('%d', '%s', $q), array_map('strval', $a)); }
    public function get_row($q) { $this->log[] = $q; return (object) ['id' => 1, 'name' => 'G', 'display_name' => 'A', 'first_name' => '', 'last_name' => '', 'slug' => 'a', 'email' => 'x@y', 'cpt_post_id' => 0]; }
    public function get_col($q, $x = 0) { return []; }
    public function get_var($q) { $this->log[] = $q; return null; }
    public function get_results($q) {
        $this->log[] = $q;
        if (strpos($q, 'FROM wp_certificates') !== false) { return [(object) ['artwork_id' => 11, 'serial_number' => 'S1', 'status' => 'active'], (object) ['artwork_id' => 12, 'serial_number' => 'S2', 'status' => 'revoked']]; }
        if (strpos($q, 'FROM wp_ownership_history') !== false) { $o = []; for ($i = 0; $i < 14; $i++) { $o[] = (object) ['artwork_id' => 11, 'transferred_at_utc' => '2024-01-' . sprintf('%02d', $i + 1)]; } return $o; }
        if (strpos($q, 'FROM wp_collectors') !== false) { return [(object) ['id' => 7, 'label' => 'Collector Seven']]; }
        if (strpos($q, 'FROM wp_artworks a') !== false && strpos($q, 'artist_id = 1') !== false) {
            return [(object) ['id' => 11, 'title' => 'W1', 'artist_id' => 1, 'gallery_id' => 1, 'current_owner_type' => 'collector', 'current_owner_id' => 7, 'cpt_post_id' => 0, 'slug' => 'w1'],
                    (object) ['id' => 12, 'title' => 'W2', 'artist_id' => 1, 'gallery_id' => 1, 'current_owner_type' => 'collector', 'current_owner_id' => 7, 'cpt_post_id' => 0, 'slug' => 'w2']];
        }
        return [];
    }
}
$GLOBALS['wpdb'] = $db = new HierDb();
$t = \WPArtCOA\Hierarchy::artist_tree(1);
$cert_q = count(array_filter($db->log, fn($q) => strpos($q, 'FROM wp_certificates') !== false));
$hist_q = count(array_filter($db->log, fn($q) => strpos($q, 'FROM wp_ownership_history') !== false));
$owner_q = count(array_filter($db->log, fn($q) => strpos($q, 'FROM wp_collectors') !== false));
ok($cert_q === 1 && $hist_q === 1 && $owner_q === 1, "artist tree: one query each for certificates, history, owners (got $cert_q/$hist_q/$owner_q)");
ok(count($t['artworks']) === 2 && $t['artworks'][0]['certs'][0]->serial_number === 'S1' && $t['artworks'][1]['certs'][0]->serial_number === 'S2', 'certificates attached to the right artworks');
ok(count($t['artworks'][0]['history']) === 10 && $t['artworks'][1]['history'] === [], 'history capped at 10 per artwork');
ok($t['artworks'][0]['owner'] === 'Collector Seven (collector)', 'owner label resolved from the prefetched name');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
