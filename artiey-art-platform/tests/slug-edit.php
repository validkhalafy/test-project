<?php
/** Slug editing: classic, Block editor/REST, cleared slug, legacy re-save, art-style terms. Run: php tests/slug-edit.php */
define('ABSPATH', '/');
$GLOBALS['stored'] = [5 => 'my-old-slug']; $GLOBALS['terms'] = [];
function add_filter(...$a) {} function add_action(...$a) {} function get_option($k, $d = false) { return $d; }
function get_post_field($f, $id) { return $GLOBALS['stored'][$id] ?? ''; }
function is_wp_error($v) { return false; }
function get_term($id, $tax) { return $GLOBALS['terms'][$id] ?? null; }
function wp_unique_term_slug($slug, $term) { return $slug; }
function sanitize_title($s) { $s = strtolower(trim(strip_tags((string) $s))); $s = preg_replace('/[\s_]+/u', '-', $s); $s = preg_replace('/[^\p{L}\p{N}\-%]/u', '', $s); $o = ''; foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $c) { $o .= (strlen($c) > 1) ? strtolower(rawurlencode($c)) : $c; } return trim(preg_replace('/-+/', '-', $o), '-'); }
function wp_unique_post_slug($s, ...$a) { return $s; }
require dirname(__DIR__) . '/includes/class-slug.php';
$pass = 0; $fail = 0;
function ok(bool $c, string $n): void { global $pass, $fail; if ($c) { $pass++; echo "  ok    $n\n"; } else { $fail++; echo "  FAIL  $n\n"; } }
function post(string $raw, string $data_name, string $title, bool $update, int $id = 5): string {
    $data = ['post_type' => 'artiey_gallery', 'post_status' => 'publish', 'post_parent' => 0, 'post_name' => $data_name, 'post_title' => $title];
    return Artiey_AET_Slug::filter_post_data($data, ['ID' => $id, 'post_title' => $title], ['post_name' => $raw, 'post_title' => $title], $update)['post_name'];
}
$fa = sanitize_title('گالری جدید');
ok(preg_match('/^[a-z0-9-]+$/', post('گالری جدید', $fa, 'x', true)) === 1, 'classic editor: typed Persian slug becomes a Latin slug');
ok(preg_match('/^[a-z0-9-]+$/', post($fa, $fa, 'x', true)) === 1, 'Block editor / REST: percent-encoded Persian slug becomes a Latin slug (was left encoded)');
ok(preg_match('/^[a-z0-9-]+$/', post('', sanitize_title('گالری مدرن'), 'گالری مدرن', true)) === 1, 'cleared slug on update is rebuilt from the title in Latin (was left encoded)');
ok(post('my-gallery', 'my-gallery', 'x', true) === 'my-gallery', 'Latin slugs are never touched');
ok(preg_match('/^[a-z0-9-]+$/', post('', sanitize_title('گالری نو'), 'گالری نو', false, 0)) === 1, 'new post without slug uses the Latin title slug');
$GLOBALS['stored'][6] = sanitize_title('گالری قدیمی');
ok(post($GLOBALS['stored'][6], $GLOBALS['stored'][6], 'گالری قدیمی', true, 6) === $GLOBALS['stored'][6], 're-saving a legacy post does not silently move its URL');
ok(post('گالری قدیمی', $GLOBALS['stored'][6], 'گالری قدیمی', true, 6) === $GLOBALS['stored'][6], '...also when the classic form posts the decoded slug back');
// terms (art styles)
$style = ['name' => 'امپرسیونیسم', 'slug' => sanitize_title('امپرسیونیسم'), 'parent' => 0];
ok(preg_match('/^[a-z0-9-]+$/', Artiey_AET_Slug::filter_term_data($style, 'artiey_style', [])['slug']) === 1, 'new art style gets a Latin slug');
$GLOBALS['terms'][9] = (object) ['slug' => 'impressionism'];
ok(preg_match('/^[a-z0-9-]+$/', Artiey_AET_Slug::filter_term_data(['name' => 'x', 'slug' => sanitize_title('کلاسیک')] , 9, 'artiey_style', [])['slug']) === 1, 'editing an art style slug to Persian gives a Latin slug');
$GLOBALS['terms'][10] = (object) ['slug' => sanitize_title('کلاسیک')];
ok(Artiey_AET_Slug::filter_term_data(['name' => 'کلاسیک', 'slug' => sanitize_title('کلاسیک')], 10, 'artiey_style', [])['slug'] === sanitize_title('کلاسیک'), 'unchanged legacy term slug is left alone');
ok(Artiey_AET_Slug::filter_term_data(['name' => 'کلاسیک', 'slug' => sanitize_title('کلاسیک')], 'category', [])['slug'] === sanitize_title('کلاسیک'), 'other taxonomies are not touched');
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
