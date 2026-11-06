<?php
/** Media gallery + curator widgets: security of untrusted input, privacy, and wiring between PHP, CSS and JS. Run: php tests/media-curator-widgets.php */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/'); define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600);
define('AET_URL', 'https://example.test/wp-content/plugins/x/'); define('AET_VERSION', '9.9.9');
$GLOBALS['reg'] = ['style' => [], 'script' => []]; $GLOBALS['tr'] = []; $GLOBALS['sql'] = []; $GLOBALS['rows'] = [];

function add_action(...$a) {} function add_shortcode(...$a) {} function get_option($k, $d = false) { return $d; }
function get_transient($k) { return $GLOBALS['tr'][$k] ?? false; } function set_transient($k, $v, $e = 0) { $GLOBALS['tr'][$k] = $v; return true; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($u, $p = null) { return preg_match('#^https?://#i', (string) $u) ? (string) $u : ''; }
function sanitize_text_field($s) { return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $s))); }
function sanitize_title($s) { return strtolower(trim((string) preg_replace('/[^a-z0-9\-%]+/i', '-', (string) $s), '-')); }
function wp_strip_all_tags($s) { return strip_tags((string) $s); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function wp_parse_url($u) { return parse_url($u); }
function home_url($p = '') { return 'https://example.test' . $p; }
function shortcode_atts($d, $a, $t = '') { return array_merge($d, array_intersect_key((array) $a, $d)); }
function wp_register_style($h, ...$a) { $GLOBALS['reg']['style'][$h] = $a[0] ?? ''; } function wp_register_script($h, ...$a) { $GLOBALS['reg']['script'][$h] = $a[0] ?? ''; }
function wp_enqueue_style($h) {} function wp_enqueue_script($h) {}
function get_the_ID() { return 5; } function get_the_title($i = 0) { return 'عنوان نوشته'; } function get_permalink($i = 0) { return 'https://example.test/g/5/'; }
function get_post_modified_time($f, $g, $i) { return '2026-01-02T03:04:05+00:00'; }
final class FakeWpdb {
    public function esc_like($s) { return addcslashes((string) $s, '_%\\'); }
    public function prepare($q, ...$a) { $i = 0; return preg_replace_callback('/%[sd]/', function ($m) use (&$i, $a) { $v = $a[$i++] ?? ''; return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes((string) $v) . "'"; }, $q); }
    public function get_results($q) { $GLOBALS['sql'][] = $q; return $GLOBALS['rows']; }
}
$GLOBALS['wpdb'] = new FakeWpdb();

require __DIR__ . '/widget-stubs.php';
require dirname(__DIR__) . '/includes/class-media-gallery.php';
require dirname(__DIR__) . '/includes/class-curator-view.php';
require dirname(__DIR__) . '/includes/class-elementor-media-widget.php';
require dirname(__DIR__) . '/includes/class-elementor-curator-widget.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $n): void { global $pass, $fail; if ($c) { $pass++; echo "  ok    $n\n"; } else { $fail++; echo "  FAIL  $n\n"; } }
$G = 'Artiey_AET_Media_Gallery'; $C = 'Artiey_AET_Curator_View';

/* ---------------- video URL parsing ---------------- */
echo "# Video URLs\n";
foreach (['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ?t=42', 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://m.youtube.com/watch?v=dQw4w9WgXcQ&list=x'] as $u) {
    $v = $G::parse_video($u); ok($v && $v['provider'] === 'youtube' && $v['id'] === 'dQw4w9WgXcQ' && str_starts_with($v['embed'], 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'), "YouTube: $u");
}
ok($G::parse_video('https://youtu.be/dQw4w9WgXcQ?t=42')['embed'] === 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1&playsinline=1&start=42', 'YouTube start time is kept as an integer');
$v = $G::parse_video('https://vimeo.com/123456789/abcdef1234'); ok($v && $v['provider'] === 'vimeo' && str_contains($v['embed'], 'player.vimeo.com/video/123456789') && str_contains($v['embed'], 'dnt=1') && str_contains($v['embed'], 'h=abcdef1234'), 'Vimeo with privacy hash, do-not-track on');
$v = $G::parse_video('https://www.aparat.com/v/AbC123'); ok($v && $v['provider'] === 'aparat' && $v['embed'] === 'https://www.aparat.com/video/video/embed/videohash/AbC123/vt/frame', 'Aparat');
$v = $G::parse_video('https://example.test/wp-content/uploads/film.MP4'); ok($v && $v['provider'] === 'file' && $v['mime'] === 'video/mp4', 'direct mp4 file');
foreach (['javascript:alert(1)', 'data:text/html;base64,AAAA', 'https://evil.com/watch?v=dQw4w9WgXcQ', 'https://youtube.com.evil.com/watch?v=dQw4w9WgXcQ', 'https://notyoutube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=short', 'https://example.test/shell.php', 'https://example.test/film.mp4.php', 'ftp://example.test/a.mp4', '', str_repeat('a', 2500)] as $bad) {
    ok($G::parse_video($bad) === null, 'rejected: ' . substr($bad, 0, 60));
}

/* ---------------- items ---------------- */
echo "# Items\n";
$items = $G::normalize_items([
    ['type' => 'image', 'src' => 'https://e.test/a.jpg', 'title' => 'A', 'alt' => ''],
    ['type' => 'image', 'src' => 'javascript:alert(1)'],
    ['type' => 'image', 'src' => '/relative.jpg'],
    ['type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'فیلم'],
    ['type' => 'video', 'video_url' => 'https://evil.example/x'],
    ['type' => 'image', 'src' => 'https://e.test/b.jpg', 'srcset' => 'https://e.test/b-300.jpg 300w, javascript:alert(1) 600w, https://e.test/b-900.jpg 900w'],
    'not-an-array',
]);
ok(count($items) === 3, 'unsafe images and unsupported videos are dropped (' . count($items) . ' kept)');
ok($items[0]['alt'] === 'A', 'alt falls back to the title');
ok($items[1]['type'] === 'video' && $items[1]['poster'] === 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', 'YouTube video gets its own thumbnail as poster');
ok($items[2]['srcset'] === 'https://e.test/b-300.jpg 300w, https://e.test/b-900.jpg 900w', 'srcset keeps only absolute http(s) entries');
ok(count($G::normalize_items(array_fill(0, 100, ['type' => 'image', 'src' => 'https://e.test/x.jpg']), 10)) === 10, 'item count is capped');

/* ---------------- markup + lightbox payload ---------------- */
echo "# Markup\n";
$evil = $G::normalize_items([['type' => 'image', 'src' => 'https://e.test/a.jpg', 'title' => '"><script>alert(1)</script>', 'caption' => "</script><img src=x onerror=alert(1)>"]]);
$html = $G::render($evil, ['layout' => 'scroller', 'lightbox' => true, 'arrows' => true, 'dots' => true, 'label' => '"><b>x']);
ok(substr_count($html, '<script') === 1, 'only the JSON data block is a <script>; titles cannot add another');
ok(!preg_match('/<script>alert|onerror=alert\(1\)>/', $html) , 'script and event-handler payloads are escaped in markup');
preg_match('#<script type="application/json" class="aetmg-data">(.*?)</script>#s', $html, $m);
ok(isset($m[1]) && !str_contains($m[1], '<') && !str_contains($m[1], '>') && is_array(json_decode($m[1], true)), 'lightbox JSON contains no < or > and still decodes');
ok(!str_contains(json_decode($m[1], true)[0]['c'], '<'), 'titles and captions are stripped of tags before they reach the lightbox data');
$o = $G::options(['layout' => 'evil', 'ratio' => '4/3;background:url(//x)', 'caption' => 'x', 'hover' => 'x', 'interval' => 1]);
ok($o['layout'] === 'scroller' && $o['ratio'] === '4/3' && $o['caption'] === 'below' && $o['hover'] === 'zoom' && $o['interval'] === 2000, 'unknown layout/ratio/caption/hover fall back to safe defaults');
$v = $G::render($G::normalize_items([['type' => 'video', 'video_url' => 'https://example.test/f.mp4', 'poster' => 'https://e.test/p.jpg', 'title' => 'ویدیو']]), ['layout' => 'grid', 'lightbox' => false]);
ok(str_contains($v, 'class="aetmg-open"') && str_contains($v, 'aetmg-play') && str_contains($v, 'aria-label="پخش ویدیو'), 'a video is always a button that opens the player, even when the lightbox is off for photos');
ok(!str_contains($v, 'aetmg-nav'), 'arrows only exist in the scroller layout');

/* ---------------- schema ---------------- */
echo "# Schema\n";
$items = $G::normalize_items([
    ['type' => 'image', 'src' => 'https://e.test/a.jpg', 'alt' => 'اثر', 'width' => 800, 'height' => 600],
    ['type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'نمایش'],
    ['type' => 'video', 'video_url' => 'https://vimeo.com/123456789', 'title' => 'بدون پوستر'],   // no thumbnail => not eligible
    ['type' => 'video', 'video_url' => 'https://example.test/f.mp4', 'poster' => 'https://e.test/p.jpg'],  // no title => named after the gallery
]);
$sch = $G::schema($items, ['name' => 'گالری', 'url' => 'https://example.test/g/', 'date' => '2026-01-02T03:04:05+00:00']);
$types = array_column($sch['hasPart'], '@type');
ok($sch['@type'] === 'ImageGallery' && $types === ['ImageObject', 'VideoObject', 'VideoObject'], 'only eligible items reach the structured data (' . implode(',', $types) . ')');
ok(!str_contains(json_encode($sch), 'vimeo.com'), 'a Vimeo video without a poster is left out (Google requires a thumbnail)');
ok($sch['hasPart'][2]['name'] === 'گالری' && $sch['hasPart'][2]['contentUrl'] === 'https://example.test/f.mp4', 'an unnamed video falls back to the gallery name and exposes contentUrl for files');
$vo = $sch['hasPart'][1]; ok(!empty($vo['name']) && !empty($vo['thumbnailUrl']) && $vo['uploadDate'] === '2026-01-02T03:04:05+00:00' && str_contains($vo['embedUrl'], 'youtube-nocookie.com'), 'VideoObject has the fields Google requires');
ok(!str_contains($G::schema_script($G::schema([['type' => 'image', 'src' => 'https://e/a.jpg', 'srcset' => '', 'width' => 0, 'height' => 0, 'alt' => '</script><script>x', 'title' => '', 'caption' => '', 'video' => null, 'poster' => '']], [])), '</script><script>'), 'JSON-LD cannot be closed early by content');
ok($G::schema([], ['name' => 'x']) === [], 'no items, no structured data');

/* ---------------- curator view: privacy + safety ---------------- */
echo "# Curator\n";
ok(!preg_match('/\b(email|phone|user_id)\b/', $C::PUBLIC_COLUMNS), 'private columns (email, phone, user_id) are never selected');
$GLOBALS['rows'] = [(object) ['id' => 7, 'display_name' => 'نگین <b>رضایی</b>', 'slug' => 'negin', 'bio' => str_repeat('کلمه ', 80), 'specialties' => 'هنر معاصر، عکاسی; ویدئو آرت' . "\n" . 'هنر معاصر', 'curatorial_statement' => '', 'affiliation' => 'موزه "الف"', 'languages' => 'فارسی', 'years_experience' => 12, 'website' => 'https://neg.in', 'instagram_url' => 'javascript:alert(1)', 'linkedin_url' => '', 'avatar_url' => '', 'cover_url' => '']];
$rows = $C::fetch(['specialty' => "50%_'; DROP TABLE x;--", 'limit' => 9999, 'orderby' => 'evil; DROP']);
$last_sql = end($GLOBALS['sql']);
ok(str_contains($last_sql, "status = 'active'") && !str_contains($last_sql, 'email') && !str_contains($last_sql, 'phone'), 'only active curators and public columns are queried');
ok(str_contains($last_sql, 'LIMIT 48') && str_contains($last_sql, 'ORDER BY id DESC') && !str_contains($last_sql, 'evil'), 'limit is clamped and ORDER BY comes from a fixed list');
ok(str_contains($last_sql, "\\'") && !str_contains($last_sql, "' DROP"), 'the specialty filter is bound as an escaped value');
ok($C::chips('هنر معاصر، عکاسی; ویدئو آرت' . "\n" . 'هنر معاصر') === ['هنر معاصر', 'عکاسی', 'ویدئو آرت'], 'specialties split on Persian and Latin separators, de-duplicated');
ok(str_ends_with($C::excerpt(str_repeat('کلمه ', 80), 10), '…') && count(explode(' ', rtrim($C::excerpt(str_repeat('کلمه ', 80), 10), '…'))) === 10, 'bio excerpt is cut by words');
ok($C::initials('نگین رضایی') === 'نر' && $C::initials('') === '؟', 'initials work for Persian names and empty names');
$html = $C::render($rows, ['layout' => 'card', 'social' => true, 'meta' => true, 'name_tag' => 'script', 'avatar_shape' => '"><x']);
ok(!str_contains($html, '<b>') && str_contains($html, '&lt;b&gt;') || !str_contains($html, '<b>رضایی'), 'curator name is escaped');
ok(str_contains($html, '<h3 class="aetcu-name">') && str_contains($html, 'aetcu-shape-circle'), 'disallowed heading tag and avatar shape fall back to safe values');
ok(str_contains($html, 'https://neg.in') && !str_contains($html, 'javascript:'), 'only http(s) social links are rendered');
ok(str_contains($html, 'rel="me nofollow noopener noreferrer"'), 'external links carry rel="me nofollow noopener noreferrer"');
ok(!str_contains($html, '@') , 'no email address appears in the markup');
$p = $C::schema($rows); ok($p['@type'] === 'Person' && $p['jobTitle'] === 'کیوریتور' && $p['sameAs'] === ['https://neg.in'] && $p['worksFor']['name'] === 'موزه "الف"', 'a single curator becomes a Person with worksFor and sameAs');
ok(!isset($p['email']) && !isset($p['telephone']), 'structured data never includes contact details');
$two = $C::schema([$rows[0], $rows[0]]); ok($two['@type'] === 'ItemList' && count($two['itemListElement']) === 2 && $two['itemListElement'][1]['position'] === 2, 'several curators become an ItemList');

/* ---------------- Elementor widgets: editor panel integrity ---------------- */
echo "# Widget editor panels\n";
$css_all = file_get_contents(dirname(__DIR__) . '/assets/css/media-gallery.css') . file_get_contents(dirname(__DIR__) . '/assets/css/curator.css');
foreach ([['Artiey_AET_Elementor_Media_Gallery_Widget', 'artiey_media_gallery'], ['Artiey_AET_Elementor_Curator_Widget', 'artiey_curator']] as [$cls, $name]) {
    $w = new $cls();
    ok($w->get_name() === $name && $w->get_categories() === ['artiey-art'], "$name: name and category");
    try { $w->boot(); $booted = true; } catch (Throwable $e) { $booted = false; echo '        ' . $e->getMessage() . "\n"; }
    ok($booted && count($w->controls) > 40, "$name: editor panel builds with unique control and section ids (" . count($w->controls) . ' controls)');
    $ids = array_keys($w->controls);
    $bad_cond = [];
    foreach ($w->controls as $id => $a) { foreach (array_keys((array) ($a['condition'] ?? [])) as $k) { $k = rtrim($k, '!'); if (!in_array($k, $ids, true)) { $bad_cond[] = "$id->$k"; } } }
    foreach ($w->sections as $id => $a) { foreach (array_keys((array) ($a['condition'] ?? [])) as $k) { $k = rtrim($k, '!'); if (!in_array($k, $ids, true)) { $bad_cond[] = "section $id->$k"; } } }
    ok(!$bad_cond, "$name: every condition points to an existing control" . ($bad_cond ? ' (' . implode(', ', $bad_cond) . ')' : ''));
    $selectors = $w->group_selectors;
    foreach ($w->controls as $a) { foreach (array_keys((array) ($a['selectors'] ?? [])) as $sel) { $selectors[] = $sel; } }
    $missing = [];
    foreach ($selectors as $sel) { preg_match_all('/\.(aet(?:mg|cu)[a-z0-9_-]*)/', $sel, $mm); foreach ($mm[1] as $cl) { if (!str_contains($css_all, '.' . $cl)) { $missing[$cl] = true; } } }
    ok(!$missing, "$name: every style selector targets a class that exists in the stylesheet" . ($missing ? ' (' . implode(', ', array_keys($missing)) . ')' : ''));
    // 'artiey-follow' is registered by the follow module (art-coa/includes/class-follows.php, covered by tests/follows.php).
    $handles = array_diff(array_merge($w->get_style_depends(), method_exists($w, 'get_script_depends') ? $w->get_script_depends() : []), ['artiey-follow']);
    $registered = array_merge(array_keys($GLOBALS['reg']['style']), array_keys($GLOBALS['reg']['script']));
    if (!$registered) { $G::register_assets(); $C::register_assets(); $registered = array_merge(array_keys($GLOBALS['reg']['style']), array_keys($GLOBALS['reg']['script'])); }
    ok(!array_diff($handles, $registered), "$name: stylesheet/script handles it depends on are registered");
}

/* ---------------- widget render ---------------- */
echo "# Widget render\n";
$w = (new Artiey_AET_Elementor_Media_Gallery_Widget())->boot();
$w->settings = ['source' => 'manual', 'layout' => 'mosaic', 'lightbox' => 'yes', 'caption' => 'overlay', 'schema' => 'yes', 'heading' => 'آثار برگزیده', 'heading_tag' => 'script', 'max_items' => 10, 'aria_label' => 'گالری',
    'items' => [
        ['item_type' => 'image', 'image' => ['url' => 'https://e.test/a.jpg', 'id' => 0], 'item_title' => 'اثر اول'],
        ['item_type' => 'video', 'video_source' => 'link', 'video_url' => ['url' => 'https://youtu.be/dQw4w9WgXcQ'], 'item_title' => 'ویدیو'],
        ['item_type' => 'video', 'video_source' => 'link', 'video_url' => ['url' => 'https://evil.example/x'], 'item_title' => 'بد'],
    ]];
$out = $w->html();
ok(str_contains($out, 'aetmg--mosaic') && substr_count($out, 'class="aetmg-item"') === 2, 'widget renders the valid items only (unsafe video dropped)');
ok(str_contains($out, '<h2 class="aetmg-heading">آثار برگزیده</h2>'), 'heading tag outside the allow-list falls back to h2');
ok(str_contains($out, '"@type":"VideoObject"') && str_contains($out, 'application/ld+json'), 'structured data with VideoObject is printed on the front end');
\Elementor\Plugin::$instance->editor->edit = true; ok(!str_contains($w->html(), 'ld+json'), 'structured data is not printed inside the editor preview'); \Elementor\Plugin::$instance->editor->edit = false;
$w->settings['schema'] = ''; ok(!str_contains($w->html(), 'ld+json'), 'structured data can be switched off');
$w->settings['items'] = []; ok($w->html() === '', 'an empty gallery prints nothing on the front end');

$cw = (new Artiey_AET_Elementor_Curator_Widget())->boot();
$cw->settings = ['mode' => 'single', 'curator_id' => '7', 'layout' => 'featured', 'show_avatar' => 'yes', 'show_bio' => 'yes', 'show_button' => 'yes', 'schema' => 'yes', 'show_social' => 'yes'];
$out = $cw->html();
ok(str_contains($out, 'aetcu--featured') && str_contains($out, 'href="https://example.test/art-coa/curator/negin/"') && str_contains($out, '"@type":"Person"'), 'curator widget renders a featured profile with link and Person data');
ok(str_contains(end($GLOBALS['sql']), 'id = 7'), 'the picked curator is fetched by id');
$cw->settings = ['mode' => 'single']; $GLOBALS['sql'] = []; ok($cw->html() === '' && !$GLOBALS['sql'], 'single mode without a choice prints nothing and queries nothing');

/* ---------------- PHP <-> CSS <-> JS contract ---------------- */
echo "# PHP / CSS / JS contract\n";
$js = file_get_contents(dirname(__DIR__) . '/assets/js/media-gallery.js'); $mg_css = file_get_contents(dirname(__DIR__) . '/assets/css/media-gallery.css');
$all_html = '';
foreach (['scroller', 'grid', 'masonry', 'mosaic'] as $lay) { foreach (['none', 'below', 'overlay'] as $cap) {
    $all_html .= $G::render($G::normalize_items([['type' => 'image', 'src' => 'https://e/a.jpg', 'title' => 't'], ['type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'v'], ['type' => 'image', 'src' => 'https://e/b.jpg']]), ['layout' => $lay, 'caption' => $cap, 'lightbox' => true, 'arrows' => true, 'dots' => true]);
} }
preg_match_all('/class="([^"]+)"/', $all_html, $mm); $classes = array_unique(array_merge(...array_map(fn($c) => preg_split('/\s+/', $c), $mm[1])));
$hook_only = ['aetmg-cap-none', 'aetmg-fit-cover', 'aetmg-data']; // state hooks / JSON holder: intentionally have no rule
$no_css = array_filter($classes, fn($c) => str_starts_with($c, 'aetmg') && !in_array($c, $hook_only, true) && !str_contains($mg_css, '.' . $c));
ok(!$no_css, 'every aetmg-* class the markup uses is styled' . ($no_css ? ' (unstyled: ' . implode(', ', $no_css) . ')' : ''));
preg_match_all("/(?:querySelector(?:All)?|closest)\('([^']+)'\)/", $js, $mm); $js_classes = [];
foreach ($mm[1] as $sel) { preg_match_all('/\.(aetmg-[a-z-]+)/', $sel, $c2); foreach ($c2[1] as $c) { $js_classes[$c] = true; } }
$lightbox_only = ['aetmg-lb-stage', 'aetmg-lb-close', 'aetmg-lb-prev', 'aetmg-lb-next', 'aetmg-lb-cap']; // created by the script itself
$orphan = array_filter(array_keys($js_classes), fn($c) => !in_array($c, $classes, true) && !in_array($c, $lightbox_only, true));
ok(!$orphan, 'every class the script looks up exists in the rendered markup' . ($orphan ? ' (missing: ' . implode(', ', $orphan) . ')' : ''));
ok(str_contains($js, 'EMBED_OK') && str_contains($js, "'noopener noreferrer'"), 'the script re-validates embed hosts before creating an iframe');
$lb_css = array_filter($lightbox_only, fn($c) => !str_contains($mg_css, '.' . $c));
ok(!$lb_css, 'the lightbox elements built by the script are styled');

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
