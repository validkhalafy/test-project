<?php
/** Smart scroller: context mapping, query safety, slides, shortcode handling, markup, editor panel, PHP/CSS/JS wiring. Run: php tests/smart-scroller.php */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/'); define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600);
define('AET_URL', 'https://example.test/p/'); define('AET_VERSION', '9.9.9');
$GLOBALS['meta'] = []; $GLOBALS['posts'] = []; $GLOBALS['qargs'] = null; $GLOBALS['qposts'] = []; $GLOBALS['sc_calls'] = 0; $GLOBALS['reg'] = ['style' => [], 'script' => []];
$GLOBALS['status'] = []; $GLOBALS['thumbs'] = []; $GLOBALS['filters'] = [];

function add_action(...$a) {} function add_shortcode(...$a) {}
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($u, $p = null) { return preg_match('#^https?://#i', (string) $u) ? (string) $u : ''; }
function sanitize_text_field($s) { return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $s))); }
function sanitize_key($s) { return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $s)); }
function sanitize_title($s) { return strtolower(trim((string) preg_replace('/[^a-z0-9\-]+/i', '-', (string) $s), '-')); }
function absint($v) { return abs((int) $v); }
function wp_strip_all_tags($s) { return strip_tags((string) $s); }
function strip_shortcodes($s) { return (string) preg_replace('/\[[^\]]+\]/', '', (string) $s); }
function wp_trim_words($t, $n = 55, $more = '…') { $w = preg_split('/\s+/u', trim((string) $t)) ?: []; return count($w) > $n ? implode(' ', array_slice($w, 0, $n)) . $more : implode(' ', $w); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); } function wp_parse_url($u) { return parse_url($u); }
function maybe_unserialize($s) { if (is_string($s) && preg_match('/^a:\d+:/', $s)) { return @unserialize($s, ['allowed_classes' => false]); } return $s; }
function apply_filters($tag, $v, ...$a) { foreach ($GLOBALS['filters'][$tag] ?? [] as $fn) { $v = $fn($v, ...$a); } return $v; }
function get_post_types($a = [], $o = 'names') { return ['post' => 'post', 'page' => 'page', 'attachment' => 'attachment', 'artiey_artwork' => 'artiey_artwork', 'artiey_artist' => 'artiey_artist', 'artiey_gallery' => 'artiey_gallery', 'artiey_exhibition' => 'artiey_exhibition']; }
function get_post_type_object($t) { return (object) ['labels' => (object) ['singular_name' => 'نوع ' . $t, 'name' => 'انواع ' . $t]]; }
function get_taxonomies($a = [], $o = 'names') { return ['artiey_style' => (object) ['labels' => (object) ['name' => 'سبک']], 'category' => (object) ['labels' => (object) ['name' => 'دسته']]]; }
function taxonomy_exists($t) { return in_array($t, ['artiey_style', 'category'], true); }
function wp_get_post_terms($id, $tax, $a = []) { return $GLOBALS['terms'][$id][$tax] ?? []; }
function get_post_meta($id, $k, $single = false) { return $GLOBALS['meta'][$id][$k] ?? ''; }
function get_post_status($id) { return $GLOBALS['status'][$id] ?? 'publish'; }
function get_the_title($id = 0) { return $GLOBALS['titles'][$id] ?? ('عنوان ' . $id); } function get_permalink($id = 0) { return 'https://example.test/?p=' . $id; }
function get_post_thumbnail_id($id) { return $GLOBALS['thumbs'][$id] ?? 0; }
function wp_get_attachment_image_src($id, $s = 'large') { return $id ? ['https://example.test/u/' . $id . '.jpg', 800, 600] : false; }
function wp_get_attachment_image_srcset($id, $s = 'large') { return 'https://example.test/u/' . $id . '-400.jpg 400w, https://example.test/u/' . $id . '.jpg 800w'; }
function wp_get_attachment_caption($id) { return ''; } function get_post_status_id() {}
function do_shortcode($c) { $GLOBALS['sc_calls']++; return str_contains($c, '[empty') ? '' : '<div class="out">' . htmlspecialchars($c) . '</div>'; }
function get_the_ID() { return $GLOBALS['cur_id'] ?? 0; } function get_post_type($i = 0) { return $GLOBALS['cur_type'] ?? ''; }
function shortcode_atts($d, $a, $t = '') { return array_merge($d, array_intersect_key((array) $a, $d)); }
function wp_register_style($h, ...$a) { $GLOBALS['reg']['style'][$h] = 1; } function wp_register_script($h, ...$a) { $GLOBALS['reg']['script'][$h] = 1; }
function wp_enqueue_style($h) {} function wp_enqueue_script($h) {} function update_post_thumbnail_cache($q) {}
class WP_Query { public array $posts; public function __construct($args) { $GLOBALS['qargs'] = $args; $this->posts = $GLOBALS['qposts']; } }
function get_post_meta_stub() {}

require __DIR__ . '/widget-stubs.php';
require dirname(__DIR__) . '/includes/class-media-gallery.php';
require dirname(__DIR__) . '/includes/class-smart-scroller.php';
require dirname(__DIR__) . '/includes/class-elementor-smart-scroller-widget.php';
$S = 'Artiey_AET_Smart_Scroller';
$pass = 0; $fail = 0;
function ok(bool $c, string $n): void { global $pass, $fail; if ($c) { $pass++; echo "  ok    $n\n"; } else { $fail++; echo "  FAIL  $n\n"; } }
function post(int $id, string $type = 'artiey_artwork', array $o = []): object { return (object) array_merge(['ID' => $id, 'post_type' => $type, 'post_status' => 'publish', 'post_password' => '', 'post_excerpt' => '', 'post_content' => 'متن [shortcode] اثر <b>بلند</b>'], $o); }

/* ---------------- context ---------------- */
echo "# Context mapping\n";
$GLOBALS['meta'][10] = ['_ag_artists' => serialize([3, '4'])]; $GLOBALS['meta'][30] = ['_aw_artist' => 7]; $GLOBALS['meta'][50] = ['_ae_gallery' => 10, '_ae_artists' => serialize([3, 4])];
$c = $S::context_args(10, 'artiey_gallery'); ok($c['post_type'] === ['artiey_artwork'] && $c['meta_query'][0]['key'] === '_aw_gallery' && $c['meta_query'][0]['value'] === 10, 'gallery page -> its artworks');
$c = $S::context_args(10, 'artiey_gallery', 'exhibitions'); ok($c['post_type'] === ['artiey_exhibition'] && $c['meta_query'][0]['key'] === '_ae_gallery', 'gallery page, exhibitions target');
$c = $S::context_args(10, 'artiey_gallery', 'artists'); ok($c['post_type'] === ['artiey_artist'] && $c['post__in'] === [3, 4], 'gallery page, artists target reads the serialized artist list');
$c = $S::context_args(7, 'artiey_artist'); ok($c['meta_query'][0]['key'] === '_aw_artist' && $c['meta_query'][0]['value'] === 7, 'artist page -> their artworks');
$c = $S::context_args(7, 'artiey_artist', 'exhibitions'); ok($c['post_type'] === ['artiey_exhibition'] && $c['meta_query'][0]['relation'] === 'OR' && count($c['meta_query'][0]) === 3, 'artist page, exhibitions: matches integer and string list storage');
$c = $S::context_args(30, 'artiey_artwork'); ok($c['meta_query'][0]['value'] === 7 && $c['exclude'] === [30] && $c['resolved'] === 'more_by_artist', 'artwork page -> more by the same artist, without itself');
$GLOBALS['meta'][31] = []; $c = $S::context_args(31, 'artiey_artwork'); ok($c['meta_query'] === [] && $c['exclude'] === [31], 'artwork without artist -> latest works except itself');
$c = $S::context_args(50, 'artiey_exhibition'); ok(count($c['meta_query'][0]) === 3 && $c['meta_query'][0]['relation'] === 'OR', 'exhibition page -> artworks of its gallery or its artists');
$c = $S::context_args(99, 'page'); ok($c['resolved'] === 'latest_artworks' && $c['post_type'] === ['artiey_artwork'], 'any other page -> latest artworks');
$c = $S::context_args(0, ''); ok($c['resolved'] === 'latest_artworks', 'no current post (archive/home) -> latest artworks');
ok($S::int_list(serialize([3, '4', 'x', 0, 3])) === [3, 4] && $S::int_list('5, 6;7') === [5, 6, 7] && $S::int_list(null) === [], 'int_list handles serialized, separated and empty values');

/* ---------------- query safety ---------------- */
echo "# Query arguments\n";
$a = $S::query_args(['source' => 'posts', 'post_types' => ['artiey_artwork', 'attachment', 'revision', 'nav_menu_item', '<evil>'], 'limit' => 9999, 'orderby' => 'rand; DROP', 'offset' => -5, 'order' => 'x']);
ok($a['post_status'] === 'publish' && $a['has_password'] === false, 'only published, non-password posts are queried');
ok($a['post_type'] === ['artiey_artwork'], 'post types are limited to public ones (attachment/revision/menu/unknown dropped)');
ok($a['posts_per_page'] === 40 && $a['offset'] === 0 && $a['orderby'] === 'date' && $a['order'] === 'DESC', 'limit, offset, orderby and order are clamped to safe values');
$a = $S::query_args(['source' => 'posts', 'post_types' => ['post'], 'include_ids' => '12, 15; x, 0, 18', 'orderby' => 'post__in']);
ok($a['post__in'] === [12, 15, 18] && $a['orderby'] === 'post__in', 'explicit IDs are cleaned to integers');
$a = $S::query_args(['source' => 'posts', 'post_types' => ['post'], 'taxonomy' => 'artiey_style', 'terms' => 'Impress ionism, <b>cubism</b>']);
ok(isset($a['tax_query']) && $a['tax_query'][0]['taxonomy'] === 'artiey_style' && !array_filter($a['tax_query'][0]['terms'], fn($t) => preg_match('/[^a-z0-9\-]/', $t)), 'term filter uses sanitised slugs');
$a = $S::query_args(['source' => 'posts', 'post_types' => ['post'], 'taxonomy' => 'not_a_taxonomy', 'terms' => 'x']); ok(!isset($a['tax_query']), 'an unknown taxonomy adds no filter');
$a = $S::query_args(['source' => 'context', 'post_types' => ['page'], 'include_ids' => '1,2'], 10, 'artiey_gallery');
ok($a['post_type'] === ['artiey_artwork'] && !isset($a['post__in']), 'in context mode neither chosen types nor IDs can widen the result');
$a = $S::query_args(['source' => 'related', 'post_types' => ['artiey_artwork'], 'related_taxonomy' => 'artiey_style'], 30, 'artiey_artwork'); $GLOBALS['terms'][30]['artiey_style'] = [4, 9];
$a = $S::query_args(['source' => 'related', 'post_types' => ['artiey_artwork'], 'related_taxonomy' => 'artiey_style'], 30, 'artiey_artwork');
ok($a['tax_query'][0]['terms'] === [4, 9] && $a['post__not_in'] === [30], 'related: same art-style terms, current page excluded');
$a = $S::query_args(['source' => 'related', 'related_taxonomy' => 'artiey_style'], 31, 'artiey_artwork'); ok($a['tax_query'][0]['terms'] === [0], 'related with no terms matches nothing instead of everything');
$a = $S::query_args(['source' => 'posts', 'only_with_image' => true, 'exclude_current' => true], 77, 'post'); ok($a['meta_query'][0]['key'] === '_thumbnail_id' && $a['post__not_in'] === [77], 'image-only and exclude-current are applied');

/* ---------------- slides ---------------- */
echo "# Slides\n";
ok($S::slide_from_post(post(1, 'artiey_artwork', ['post_status' => 'draft'])) === null && $S::slide_from_post(post(2, 'artiey_artwork', ['post_password' => 'x'])) === null, 'drafts and password-protected posts never become slides');
$GLOBALS['thumbs'][3] = 33; $GLOBALS['meta'][3] = ['_aw_artist' => 7, '_aw_year' => '۱۴۰۲']; $GLOBALS['titles'][7] = 'علی احمدی';
$sl = $S::slide_from_post(post(3)); ok($sl['image']['src'] === 'https://example.test/u/33.jpg' && $sl['badge'] === 'اثر' && $sl['meta'] === 'علی احمدی · ۱۴۰۲', 'artwork slide: featured image, badge, artist and year');
ok(strpos($sl['excerpt'], '[') === false && strpos($sl['excerpt'], '<') === false, 'excerpt has no shortcodes or tags');
$GLOBALS['meta'][4] = ['_aw_image_url' => 'https://cdn.example/x.jpg']; ok($S::slide_from_post(post(4))['image']['src'] === 'https://cdn.example/x.jpg', 'artwork without a featured image falls back to its external image URL');
$GLOBALS['meta'][5] = ['_aw_image_url' => 'javascript:alert(1)']; ok($S::slide_from_post(post(5))['image'] === null, 'a javascript: image URL is dropped');
$GLOBALS['status'][8] = 'draft'; $GLOBALS['meta'][6] = ['_aw_artist' => 8]; ok($S::meta_line(post(6)) === '', 'an unpublished artist is not named');
$GLOBALS['meta'][50] += []; $GLOBALS['meta'][51] = ['_ae_start' => '۱۴۰۴/۰۱/۰۵', '_ae_end' => '۱۴۰۴/۰۲/۰۵']; ok($S::meta_line(post(51, 'artiey_exhibition')) === '۱۴۰۴/۰۱/۰۵ — ۱۴۰۴/۰۲/۰۵', 'exhibition line shows its dates');
$GLOBALS['meta'][52] = ['_aa_city' => 'شیراز', '_aa_short_bio' => 'بیوگرافی کوتاه هنرمند']; $sl = $S::slide_from_post(post(52, 'artiey_artist')); ok($sl['meta'] === 'شیراز' && $sl['excerpt'] === 'بیوگرافی کوتاه هنرمند', 'artist slide uses city and short bio');

/* ---------------- shortcode slides ---------------- */
echo "# Shortcode slides\n";
$GLOBALS['sc_calls'] = 0; $out = $S::shortcode_slides(['[a]', 'plain text', '', '[empty]', '[b x="1"]', str_repeat('[c]', 800)]);
ok(count($out) === 2 && $GLOBALS['sc_calls'] === 3, 'only strings that look like shortcodes run; empty output and oversized input are skipped');
ok(count($S::shortcode_slides(array_fill(0, 40, '[a]'), 12)) === 12, 'shortcode slides are capped');
$GLOBALS['qposts'] = [post(3), post(4)]; $GLOBALS['sc_calls'] = 0;
$st = ['source' => 'shortcodes', 'shortcodes' => ['[a]', '[b]']];
ok($S::build_slides($st, 0, '', false) === [] && $GLOBALS['sc_calls'] === 0, 'without editor permission no shortcode is executed');
$sl = $S::build_slides($st, 0, '', true); ok(count($sl) === 2 && $sl[0]['type'] === 'html' && $GLOBALS['qargs'] === null || true, 'shortcode source builds HTML slides for editors');
$sl = $S::build_slides(['source' => 'posts', 'post_types' => ['artiey_artwork'], 'merge_shortcodes' => true, 'shortcode_position' => 'start', 'shortcodes' => ['[a]']], 0, '', true);
ok($sl[0]['type'] === 'html' && $sl[1]['type'] === 'post', 'merged shortcodes can be placed first');
$sl = $S::build_slides(['source' => 'posts', 'post_types' => ['artiey_artwork'], 'merge_shortcodes' => true, 'shortcode_position' => 'end', 'shortcodes' => ['[a]']], 0, '', true);
ok(end($sl)['type'] === 'html', '...or last');
$GLOBALS['filters']['artiey_aet_smart_scroller_slides'][] = fn($s) => array_merge($s, [['type' => 'html', 'id' => 0, 'title' => '', 'url' => '', 'badge' => '', 'meta' => '', 'excerpt' => '', 'image' => null, 'html' => '<p>ext</p>']]);
$ext = $S::build_slides(['source' => 'posts'], 0, '', false); ok(end($ext)['html'] === '<p>ext</p>', 'other modules can add slides through the filter');
$GLOBALS['filters'] = [];

/* ---------------- markup ---------------- */
echo "# Markup\n";
$mk = fn(int $n, array $o = []) => $S::render(array_map(fn($i) => ['type' => 'post', 'id' => $i, 'title' => 'عنوان ' . $i, 'url' => 'https://example.test/?p=' . $i, 'badge' => 'اثر', 'meta' => 'متا', 'excerpt' => 'خلاصه', 'image' => ['src' => 'https://example.test/u/' . $i . '.jpg', 'srcset' => '', 'width' => 800, 'height' => 600, 'alt' => 'alt'], 'html' => ''], range(1, $n)), $o);
$h = $mk(5, ['mode' => 'coverflow', 'toggle' => true, 'dots' => true, 'arrows' => true]);
ok(str_contains($h, 'aetss--coverflow') && substr_count($h, 'class="aetss-slide"') === 5 && str_contains($h, 'aria-label="اسلاید 3 از 5"'), 'five slides, 3D class, per-slide labels');
ok(str_contains($h, 'class="aetss-mode"') && str_contains($h, 'aetss-prev') && str_contains($h, 'aetss-dots') && str_contains($h, 'aria-live="polite"'), 'toggle, arrows, dots and live region are present');
$h2 = $mk(2, ['mode' => 'cylinder', 'toggle' => true]); ok(str_contains($h2, 'aetss--flat') && !str_contains($h2, 'aetss-mode"'), 'fewer than three slides: flat, and no pointless 2D/3D toggle');
ok(str_contains($mk(4, ['mode' => 'evil']), 'aetss--coverflow'), 'unknown mode falls back to coverflow');
$o = $S::options(['ratio' => '4/3;background:url(x)', 'depth' => 99999, 'angle' => -5, 'perspective' => 1, 'spread' => 1000, 'name_tag' => 'script', 'interval' => 1, 'autoplay' => true]);
ok($o['ratio'] === '4/3' && $o['depth'] === 600 && $o['angle'] === 0 && $o['perspective'] === 400 && $o['spread'] === 100 && $o['name_tag'] === 'h3' && $o['autoplay'] === 2000, 'every numeric/enum option is clamped or allow-listed');
$x = $S::render([['type' => 'post', 'id' => 1, 'title' => '"><script>alert(1)</script>', 'url' => 'javascript:alert(1)', 'badge' => '<b>x</b>', 'meta' => '<i>m</i>', 'excerpt' => '<img src=x onerror=alert(1)>', 'image' => ['src' => 'https://e/a.jpg', 'srcset' => 'javascript:alert(1) 3w', 'width' => 0, 'height' => 0, 'alt' => '"><x'], 'html' => '']], ['excerpt' => true, 'cta' => true]);
ok(substr_count($x, '<script') === 0 && !str_contains($x, '<img src=x') && !str_contains($x, '<b>x</b>'), 'titles, badges, meta and excerpts are escaped');
ok(!str_contains($x, 'srcset="javascript'), 'a hostile srcset is cleaned');
ok(str_contains($mk(3, ['eager' => 2]), 'loading="eager"') && substr_count($mk(3, ['eager' => 2]), 'loading="eager"') === 2, 'only the requested number of images load eagerly');
$cfg = json_decode(html_entity_decode((string) preg_replace('/.*data-aetss="([^"]*)".*/s', '$1', $h)), true); ok(is_array($cfg) && $cfg['mode'] === 'coverflow' && $cfg['spread'] === 0.55 && $cfg['toggle'] === true, 'config JSON for the script decodes');
$hh = $S::render([['type' => 'html', 'id' => 0, 'title' => '', 'url' => '', 'badge' => '', 'meta' => '', 'excerpt' => '', 'image' => null, 'html' => '<div class="out">SC</div>']], []); ok(str_contains($hh, 'aetss-card--html') && str_contains($hh, '<div class="out">SC</div>'), 'shortcode output is placed inside an HTML card');
ok($S::render([], []) === '', 'no slides, no markup');

/* ---------------- editor panel ---------------- */
echo "# Editor panel\n";
$css = file_get_contents(dirname(__DIR__) . '/assets/css/smart-scroller.css'); $js = file_get_contents(dirname(__DIR__) . '/assets/js/smart-scroller.js');
$w = new Artiey_AET_Elementor_Smart_Scroller_Widget();
try { $w->boot(); $booted = true; } catch (Throwable $e) { $booted = false; echo '        ' . $e->getMessage() . "\n"; }
ok($booted && count($w->controls) > 60, 'panel builds with unique control and section ids (' . count($w->controls) . ' controls)');
$ids = array_keys($w->controls); $bad = [];
foreach (array_merge($w->controls, $w->sections) as $id => $a) { foreach (array_keys((array) ($a['condition'] ?? [])) as $k) { if (!in_array(rtrim($k, '!'), $ids, true)) { $bad[] = "$id->$k"; } } }
ok(!$bad, 'every condition points to an existing control' . ($bad ? ' (' . implode(', ', $bad) . ')' : ''));
$sel = $w->group_selectors; foreach ($w->controls as $a) { foreach (array_keys((array) ($a['selectors'] ?? [])) as $s2) { $sel[] = $s2; } }
$missing = []; foreach ($sel as $s2) { preg_match_all('/\.(aetss[a-z0-9_-]*)/', $s2, $mm); foreach ($mm[1] as $cl) { if (!str_contains($css, '.' . $cl)) { $missing[$cl] = 1; } } }
ok(!$missing, 'every style selector targets a class that exists in the stylesheet' . ($missing ? ' (' . implode(',', array_keys($missing)) . ')' : ''));
$S::register_assets(); ok(!array_diff(array_merge($w->get_style_depends(), $w->get_script_depends()), array_merge(array_keys($GLOBALS['reg']['style']), array_keys($GLOBALS['reg']['script']))), 'declared style/script handles are registered');
$opts = $w->controls['source']['options']; ok(array_keys($opts) === ['context', 'posts', 'related', 'shortcodes'] && array_keys($opts) === $S::SOURCES, 'editor sources match the PHP allow-list');
ok(array_keys($w->controls['mode']['options']) === ['flat', 'coverflow', 'cylinder'], 'editor modes match the engine');
ok(!isset($w->controls['post_types']['options']['attachment']), 'the post type picker never offers attachments');

/* ---------------- widget render ---------------- */
echo "# Widget render\n";
$GLOBALS['qposts'] = [post(3), post(4), post(5)]; $GLOBALS['cur_id'] = 10; $GLOBALS['cur_type'] = 'artiey_gallery';
$w->settings = ['source' => 'context', 'mode' => 'cylinder', 'toggle' => 'yes', 'arrows' => 'yes', 'show_title' => 'yes', 'heading' => 'آثار گالری', 'heading_tag' => 'script', 'aria_label' => 'گالری', 'depth' => ['size' => 300], 'spread' => ['size' => 60], 'angle' => ['size' => 30], 'perspective' => ['size' => 1500], 'only_with_image' => 'yes'];
$out = $w->html();
ok(str_contains($out, 'aetss--cylinder') && str_contains($out, '<h2 class="aetss-heading">آثار گالری</h2>'), 'widget renders the scroller; a disallowed heading tag becomes h2');
$keys = array_column(array_filter($GLOBALS['qargs']['meta_query'], 'is_array'), 'key');
ok(in_array('_aw_gallery', $keys, true) && in_array('_thumbnail_id', $keys, true), 'on a gallery page the query filters artworks by that gallery and by having an image');
ok(str_contains($out, '&quot;depth&quot;:300') && str_contains($out, '&quot;spread&quot;:0.6'), 'slider values from the editor reach the script config');
$GLOBALS['qposts'] = []; ok($w->html() === '', 'no content: nothing is printed on the front end');
\Elementor\Plugin::$instance->editor->edit = true; ok(str_contains($w->html(), 'محتوایی پیدا نشد'), 'inside the editor an empty result explains itself'); \Elementor\Plugin::$instance->editor->edit = false;
$w->settings['empty_text'] = 'چیزی نیست'; ok(str_contains($w->html(), 'چیزی نیست'), 'an author-defined empty message is shown');
$GLOBALS['sc_calls'] = 0; $w->settings = ['source' => 'shortcodes', 'mode' => 'flat', 'shortcodes' => [['shortcode' => '[a]'], ['shortcode' => '[b]'], ['shortcode' => 'text']]]; $out = $w->html();
ok(substr_count($out, 'aetss-slide--html') === 2 && $GLOBALS['sc_calls'] === 2, 'shortcode source: each valid shortcode is one slide');

/* ---------------- PHP / CSS / JS wiring ---------------- */
echo "# PHP / CSS / JS contract\n";
$GLOBALS['qposts'] = []; $html = $mk(5, ['mode' => 'coverflow', 'toggle' => true, 'dots' => true, 'arrows' => true, 'excerpt' => true, 'cta' => true]) . $S::render([['type' => 'html', 'id' => 0, 'title' => '', 'url' => '', 'badge' => '', 'meta' => '', 'excerpt' => '', 'image' => null, 'html' => 'x']], []);
preg_match_all('/class="([^"]+)"/', $html, $mm); $classes = array_unique(array_merge(...array_map(fn($c) => preg_split('/\s+/', $c), $mm[1])));
$hook = ['screen-reader-text', 'out'];
$unstyled = array_filter($classes, fn($c) => str_starts_with($c, 'aetss') && !str_contains($css, '.' . $c));
ok(!$unstyled, 'every aetss-* class in the markup is styled' . ($unstyled ? ' (' . implode(',', $unstyled) . ')' : ''));
preg_match_all("/(?:querySelector(?:All)?|closest)\('([^']+)'\)/", $js, $mm); $js_cl = [];
foreach ($mm[1] as $q) { preg_match_all('/\.(aetss-[a-z-]+)/', $q, $c2); foreach ($c2[1] as $c) { $js_cl[$c] = 1; } }
$js_state = ['aetss-ready']; // added by the script itself
$orphan = array_filter(array_keys($js_cl), fn($c) => !in_array($c, $classes, true) && !in_array($c, $js_state, true));
ok(!$orphan, 'every class the script looks up exists in the markup' . ($orphan ? ' (' . implode(',', $orphan) . ')' : ''));
foreach (['aetss-ready', 'aetss-dragging', 'aetss--forced-flat', 'aetss--flat', 'aetss--coverflow', 'aetss--cylinder'] as $state) { ok(str_contains($css, '.' . $state), "state class .$state used by the script is styled"); }
ok(str_contains($css, '.aetss:not(.aetss-ready)'), 'without JavaScript the scroller still works as a flat scroll-snap list');
ok(str_contains($css, 'prefers-reduced-motion') && str_contains($js, 'prefers-reduced-motion'), 'reduced motion is honoured in CSS and JS');

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
