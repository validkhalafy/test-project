<?php
/**
 * 3.1 regressions: follow / followers, the professional gallery sections (statement, critics, press,
 * awards, team) and the open-calls module. Dependency-free. Run: php tests/follows-gallery-calls.php
 */
declare(strict_types=1);

namespace WPArtCOA {
    final class Database { public static function table(string $t): string { return 'wp_art_' . $t; } }
    final class I18n {
        public static function is_fa(): bool { return true; }
        public static function t(string $k, array $r = []): string { return ['follow' => 'دنبال کردن', 'following' => 'دنبال می‌کنید', 'followers' => 'دنبال‌کننده', 'call_open' => 'باز', 'call_closed' => 'بسته', 'call_type_exhibition' => 'نمایشگاه', 'call_type_event' => 'رویداد', 'call_type_residency' => 'رزیدنسی', 'call_type_open_studio' => 'اوپن‌استودیو'][$k] ?? $k; }
    }
    final class Media {
        public static function url($row, string $f, string $s = 'large'): string { return (string) ($row->{$f . '_url'} ?? ''); }
        public static function has(array $d, string $f): bool { return array_key_exists($f . '_id', $d) || array_key_exists($f . '_url', $d); }
        public static function columns(array $d, string $f): array { return [$f . '_id' => null, $f . '_url' => (string) ($d[$f . '_url'] ?? '')]; }
    }
    final class ContentSync {
        public static function artist_url(string $s): string { return 'https://example.test/artists/' . $s . '/'; }
        public static function permalink(string $t, string $s): string { return ''; }
    }
    final class Audit { public static array $log = []; public static function log(...$a): void { self::$log[] = $a; } }
    final class Security {
        public static function client_ip(): string { return '1.2.3.4'; }
        public static function input_scalar(array $s, string $k, string $d = ''): string { return isset($s[$k]) && is_scalar($s[$k]) ? (string) $s[$k] : $d; }
    }
    final class Calendar { public static function to_persian_digits(string $s): string { return strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']); } }
    final class Dates { public static function format(?string $d, bool $t = false): string { return (string) $d; } }
    final class Shop { public static function format_price($p, string $c): string { return $p . ' ' . $c; } }
}

namespace {
    define('ABSPATH', __DIR__ . '/');
    define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600); define('DAY_IN_SECONDS', 86400);
    define('AET_URL', 'https://example.test/wp-content/plugins/artiey/'); define('AET_VERSION', '9.9.9');
    define('WP_ART_COA_VERSION', '1'); define('WP_ART_COA_FILE', __FILE__);
    $GLOBALS['user'] = 0; $GLOBALS['tr'] = []; $GLOBALS['opts'] = ['artiey_aet_content_bridge_schema_version' => '1', 'art_coa_dbv_follows' => '3.1.0']; $GLOBALS['caps'] = false;

    function add_action(...$a) {} function add_filter(...$a) {} function add_shortcode(...$a) {} function do_action(...$a) {}
    function apply_filters($t, $v) { return $v; }
    function get_option($k, $d = false) { return $GLOBALS['opts'][$k] ?? $d; } function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
    function get_transient($k) { return $GLOBALS['tr'][$k] ?? false; } function set_transient($k, $v, $e = 0) { $GLOBALS['tr'][$k] = $v; return true; } function delete_transient($k) { unset($GLOBALS['tr'][$k]); return true; }
    function wp_cache_get($k, $g = '') { return false; } function wp_cache_set(...$a) { return true; } function wp_cache_delete(...$a) { return true; }
    function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
    function esc_url($s) { $s = (string) $s; return preg_match('#^(https?://|mailto:|tel:|/)#i', $s) ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : ''; }
    function esc_url_raw($u, $p = null) { return preg_match('#^https?://#i', trim((string) $u)) ? trim((string) $u) : ''; }
    function esc_textarea($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } function esc_js($s) { return addslashes((string) $s); }
    function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
    function sanitize_text_field($s) { return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $s))); }
    function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
    function sanitize_title($s) { return strtolower(trim((string) preg_replace('/[^a-z0-9\-]+/i', '-', (string) $s), '-')); }
    function sanitize_email($s) { return filter_var(trim((string) $s), FILTER_VALIDATE_EMAIL) ? trim((string) $s) : ''; }
    function is_email($s) { return (bool) filter_var((string) $s, FILTER_VALIDATE_EMAIL); }
    function absint($v) { return abs((int) $v); } function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
    function wp_strip_all_tags($s) { return strip_tags((string) $s); } function wp_kses_post($s) { return (string) preg_replace('#<script.*?</script>#is', '', (string) $s); }
    function wpautop($s) { return '<p>' . str_replace("\n\n", '</p><p>', (string) $s) . '</p>'; }
    function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
    function home_url($p = '') { return 'https://example.test' . $p; } function admin_url($p = '') { return 'https://example.test/wp-admin/' . $p; }
    function wp_login_url($r = '') { return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode((string) $r); }
    function wp_parse_url($u, $c = -1) { return parse_url((string) $u, $c); }
    function get_current_user_id() { return (int) $GLOBALS['user']; } function is_user_logged_in() { return $GLOBALS['user'] > 0; }
    function current_user_can($c, ...$a) { return (bool) $GLOBALS['caps']; }
    function wp_nonce_field($a, $n = '_wpnonce', $r = true, $e = true) { $h = '<input type="hidden" name="' . $n . '" value="nonce-' . $a . '">'; if ($e) { echo $h; } return $h; }
    function wp_create_nonce($a) { return 'nonce-' . $a; }
    function shortcode_atts($d, $a, $t = '') { return array_merge($d, array_intersect_key((array) $a, $d)); }
    function wp_register_style(...$a) {} function wp_register_script(...$a) {} function wp_localize_script(...$a) {} function wp_enqueue_style(...$a) {} function wp_enqueue_script(...$a) {}
    function wp_style_is($h, $l = '') { return true; }
    function get_query_var($k) { return $GLOBALS['qv'][$k] ?? ''; } function is_singular($t = '') { return false; } function get_permalink($p = 0) { return ''; }
    function get_post_type($id = 0) { return $GLOBALS['post_types'][(int) $id] ?? false; }
    function get_post($id) { return null; }
    function wp_attachment_is_image($id) { return in_array((int) $id, [55, 56], true); }
    function wp_get_attachment_image($id, $s = '', $i = false, $a = []) { return ''; } function wp_get_attachment_image_url($id, $s = '') { return false; }
    function wp_timezone() { return new DateTimeZone('Asia/Tehran'); }
    function wp_trim_words($t, $n = 55) { $w = preg_split('/\s+/u', trim((string) $t)); return count($w) > $n ? implode(' ', array_slice($w, 0, $n)) . '…' : (string) $t; }


    // ---- extra stubs for rendering a gallery profile ----
    function get_post_meta($id, $k, $single = false) { return $GLOBALS['meta'][(int) $id][$k] ?? ''; }
    function get_post_status($id) { return $GLOBALS['post_status'][(int) $id] ?? 'publish'; }
    function post_password_required($id = null) { return false; }
    function has_post_thumbnail($id = 0) { return false; }
    function get_the_title($id = 0) { return 'گالری <i>نمونه</i>'; }
    function get_the_terms($id, $tax) { return false; }
    function wp_date($f, $t = null) { return gmdate($f); }
    function current_time($f) { return gmdate($f === 'mysql' ? 'Y-m-d H:i:s' : $f); }
    function wp_get_attachment_url($id) { return 'https://example.test/u/' . (int) $id . '.jpg'; }
    function number_format_i18n($n) { return (string) $n; }
    function is_wp_error($x) { return false; }
    final class Artiey_AET_Statistics { public static function profile_strip(...$a) { return ''; } }
    final class Artiey_AET_Profile_Guard { public static function bio_html($p) { return ''; } public static function clean_markup($h) { return (string) $h; } public static function mark($id) {} }
    final class Artiey_AET_Settings { public static function get($k) { return $k === 'show_certificates' ? false : ''; } }
    final class Artiey_AET_Exhibition { public static function for_gallery($id) { return []; } public static function render_groups(...$a) { return ''; } public static function counts($x) { return ['now' => 0, 'upcoming' => 0]; } }
    final class Artiey_AET_COA_Bridge { public static function active_count_for_gallery_post($id) { return 0; } }

    final class FollowDb {
        public array $rows = [];      // follows table rows
        public array $profiles = [];  // type => [rows]
        public array $queries = [];
        public $last_error = '';
        public int $insert_id = 0;
        public function prepare($q, ...$a) { if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; } $i = 0; return preg_replace_callback('/%[sd]/', function ($m) use (&$i, $a) { $v = $a[$i++] ?? ''; return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes((string) $v) . "'"; }, $q); }
        public function esc_like($s) { return addcslashes((string) $s, '_%\\'); }
        private function table_rows(string $q): array { foreach ($this->profiles as $t => $rows) { if (str_contains($q, 'wp_art_' . $t . ' ')) { return $rows; } } return []; }
        public function get_var($q) {
            $this->queries[] = $q;
            if (preg_match("/FROM wp_art_follows WHERE user_id = (\d+) AND entity_type = '(\w+)' AND entity_id = (\d+)/", $q, $m)) {
                foreach ($this->rows as $r) { if ($r['user_id'] == $m[1] && $r['entity_type'] === $m[2] && $r['entity_id'] == $m[3]) { return 1; } } return null;
            }
            return null;
        }
        public function get_col($q) {
            $this->queries[] = $q;
            if (preg_match("/SELECT user_id FROM wp_art_follows WHERE entity_type = '(\w+)' AND entity_id = (\d+)/", $q, $m)) {
                return array_values(array_map(fn($r) => $r['user_id'], array_filter($this->rows, fn($r) => $r['entity_type'] === $m[1] && $r['entity_id'] == $m[2])));
            }
            return [];
        }
        public function get_results($q, $o = null) {
            $this->queries[] = $q;
            if (str_contains($q, 'COUNT(*) AS c FROM wp_art_follows')) {
                $c = [];
                foreach ($this->rows as $r) { $k = $r['entity_type'] . ':' . $r['entity_id']; $c[$k] = ($c[$k] ?? 0) + 1; }
                $out = [];
                foreach ($c as $k => $n) { [$t, $id] = explode(':', $k); if (preg_match("/entity_type = '$t' AND entity_id IN \(([0-9,]+)\)/", $q, $m) && in_array($id, explode(',', $m[1]), true)) { $out[] = (object) ['entity_type' => $t, 'entity_id' => $id, 'c' => $n]; } }
                return $out;
            }
            if (preg_match('/WHERE id IN \(([0-9,]+)\)/', $q, $m)) {
                $ids = explode(',', $m[1]);
                return array_values(array_filter($this->table_rows($q), fn($r) => in_array((string) $r->id, $ids, true)));
            }
            if (preg_match('/WHERE user_id IN \(([0-9,]+)\)/', $q, $m)) {
                $ids = explode(',', $m[1]);
                return array_values(array_filter($this->table_rows($q), fn($r) => in_array((string) ($r->user_id ?? ''), $ids, true)));
            }
            if (preg_match('/SELECT entity_type, entity_id FROM wp_art_follows WHERE user_id = (\d+)/', $q, $m)) {
                return array_map(fn($r) => (object) $r, array_values(array_filter($this->rows, fn($r) => $r['user_id'] == $m[1])));
            }
            return [];
        }
        public function insert($t, $d, $f = null) { $this->rows[] = $d; $this->insert_id++; return 1; }
        public function delete($t, $w, $f = null) { $before = count($this->rows); $this->rows = array_values(array_filter($this->rows, fn($r) => !(($r['user_id'] ?? null) == ($w['user_id'] ?? null) && ($r['entity_type'] ?? null) === ($w['entity_type'] ?? null) && ($r['entity_id'] ?? null) == ($w['entity_id'] ?? null)))); return $before - count($this->rows); }
        public function get_row($q) { return null; }
        public function update(...$a) { return 1; }
        public function query($q) { return 0; }
    }
    $GLOBALS['wpdb'] = $db = new FollowDb();
    $db->profiles['artists'] = [
        (object) ['id' => 7, 'slug' => 'sara', 'display_name' => 'سارا <b>رضایی</b>', 'status' => 'active', 'user_id' => 70, 'avatar_url' => ''],
        (object) ['id' => 8, 'slug' => 'hidden', 'display_name' => 'پنهان', 'status' => 'inactive', 'user_id' => 80, 'avatar_url' => ''],
    ];
    $db->profiles['curators'] = [(object) ['id' => 3, 'slug' => 'neda', 'display_name' => 'ندا', 'status' => 'active', 'user_id' => 30, 'avatar_url' => 'https://e.test/n.jpg']];
    $db->profiles['galleries'] = [(object) ['id' => 4, 'slug' => 'aria', 'name' => 'گالری آریا', 'status' => 'active', 'user_id' => 0, 'logo_url' => '']];
    $db->profiles['collectors'] = [(object) ['id' => 9, 'slug' => 'private', 'display_name' => 'مجموعه‌دار خصوصی', 'status' => 'active', 'is_public' => 0, 'user_id' => 90]];

    require dirname(__DIR__) . '/art-coa/includes/class-follows.php';
    require dirname(__DIR__) . '/art-coa/includes/class-calls.php';
    require dirname(__DIR__) . '/includes/class-text.php';
    require dirname(__DIR__) . '/includes/class-jalali.php';
    require dirname(__DIR__) . '/includes/class-gallery.php';
    require dirname(__DIR__) . '/includes/class-gallery-admin.php';

    $pass = 0; $fail = 0;
    function ok(bool $c, string $n): void { global $pass, $fail; if ($c) { $pass++; echo "  ok    $n\n"; } else { $fail++; echo "  FAIL  $n\n"; } }
    $F = 'WPArtCOA\\Follows';

    echo "# Follow subjects\n";
    ok($F::parse_key('artist:7') === ['artist', 7], 'valid subject key parses');
    foreach (['artist:0', 'admin:1', 'artist:7;drop', 'artist:-1', "artist:7\n", '', 'post:abc', 'gallery:99999999999999999999'] as $bad) {
        ok($F::parse_key($bad) === null, 'rejected subject key: ' . json_encode($bad));
    }
    ok($F::normalize_type('artiey_gallery') === 'post' && $F::normalize_type('curator') === 'curator' && $F::normalize_type('user') === null, 'types are allow-listed; post types map to "post"');
    ok($F::compact_number(950) === '۹۵۰' && $F::compact_number(1250) === '۱٫۳ هزار' && $F::compact_number(2000000) === '۲ میلیون', 'counts are compact and in Persian digits');

    echo "# Follow rules\n";
    ok($F::apply(0, 'artist', 7, 'follow')['code'] === 'login', 'guests must log in');
    $GLOBALS['user'] = 5;
    ok($F::apply(5, 'artist', 8, 'follow')['code'] === 'invalid', 'an inactive (non-public) profile cannot be followed');
    ok($F::apply(5, 'collector', 9, 'follow')['code'] === 'invalid', 'a private collector cannot be followed');
    ok($F::apply(5, 'artist', 404, 'follow')['code'] === 'invalid', 'an unknown profile cannot be followed');
    ok($F::apply(70, 'artist', 7, 'follow')['code'] === 'self', 'the owner cannot follow their own profile');
    $r = $F::apply(5, 'artist', 7, 'toggle');
    ok($r['ok'] && $r['following'] && $r['count'] === 1, 'toggle follows and returns the fresh count');
    $r = $F::apply(5, 'artist', 7, 'follow');
    ok($r['following'] && count($db->rows) === 1, 'following twice never creates a duplicate row');
    $F::apply(30, 'artist', 7, 'follow');
    ok($F::count('artist', 7) === 2, 'two followers are counted');
    $r = $F::apply(5, 'artist', 7, 'toggle');
    ok($r['ok'] && !$r['following'] && $r['count'] === 1, 'second toggle unfollows');
    $GLOBALS['tr']['aet_follow_rl_5'] = 40;
    ok($F::apply(5, 'artist', 7, 'follow')['code'] === 'rate', 'per-user rate limit stops toggle floods');
    unset($GLOBALS['tr']['aet_follow_rl_5']);

    echo "# Followers privacy\n";
    $F::apply(5, 'artist', 7, 'follow');      // user 5 has no public profile
    $pub = $F::public_followers('artist', 7, 10);
    ok($pub['total'] === 2 && count($pub['items']) === 1 && $pub['items'][0]['type'] === 'curator' && $pub['items'][0]['name'] === 'ندا', 'only followers with a public profile are listed, the rest are only counted');
    $F::apply(70, 'gallery', 4, 'follow');
    $following = $F::for_user(70, true);
    ok(count($following['galleries']) === 1 && $following['galleries'][0]['name'] === 'گالری آریا', 'an artist profile shows what its owner follows');

    echo "# Button markup\n";
    $GLOBALS['user'] = 0;
    $html = $F::button_html('artist', 7);
    ok(str_contains($html, 'data-aet-follow="artist:7"') && str_contains($html, 'wp-login.php') && str_contains($html, 'rel="nofollow"'), 'guests get a login link that JS can upgrade');
    ok(!str_contains($html, '<b>') && str_contains($html, '&lt;b&gt;'), 'profile names are escaped in labels');
    ok(str_contains($html, 'data-aet-follow-count="2"') && str_contains($html, '۲'), 'server-rendered count (works without JS)');
    $GLOBALS['user'] = 5;
    $html = $F::button_html('artist', 7, ['label_follow' => 'فالو', 'style' => '"><script>', 'size' => 'xl']);
    ok(str_contains($html, '<form method="post"') && str_contains($html, 'name="art_coa_follow"') && str_contains($html, 'nonce-art_coa_follow'), 'logged-in users get a no-JS form fallback with a nonce');
    ok(str_contains($html, 'aria-pressed="true"') && str_contains($html, 'is-following'), 'current state is rendered for screen readers');
    ok(str_contains($html, 'aet-follow--solid') && str_contains($html, 'aet-follow--md') && !str_contains($html, '<script>'), 'style and size options are allow-listed');
    $GLOBALS['user'] = 70;
    ok(str_contains($F::button_html('artist', 7), 'پروفایل شما'), 'the owner sees "your profile" instead of a follow button');
    ok($F::button_html('artist', 8) === '', 'no button for non-public profiles');
    $panel = $F::followers_html('artist', 7, ['avatars' => 5]);
    ok(str_contains($panel, 'data-aet-followers="artist:7"') && str_contains($panel, 'دنبال‌شونده') && str_contains($panel, 'https://e.test/n.jpg'), 'followers panel shows followers, following and public avatars');

    echo "# Front-end wiring\n";
    $js = file_get_contents(dirname(__DIR__) . '/assets/js/follow.js');
    $css = file_get_contents(dirname(__DIR__) . '/assets/css/follow.css');
    ok(str_contains($js, "'artiey_follow_toggle'") && str_contains($js, "'artiey_follow_state'") && str_contains($js, "credentials: 'same-origin'"), 'JS uses the two AJAX actions with cookies');
    ok(str_contains($js, 'data.code === \'nonce\'') && str_contains($js, 'retry'), 'JS retries once with a fresh nonce (page caches)');
    foreach (['aet-follow', 'aet-follow-btn', 'is-following', 'aet-follow-count', 'aet-followers-list', 'aet-follow-cards', 'is-busy'] as $cls) {
        ok(str_contains($css, '.' . $cls), "stylesheet styles .$cls");
    }

    echo "# Gallery: repeatable lists\n";
    $rows = Artiey_AET_Gallery::rows([['name' => 'الف', 'text' => 'متن', 'evil' => 'x'], 'junk', ['name' => '', 'text' => ''], ['name' => ['arr'], 'text' => 'ب']], ['name', 'text'], 10);
    ok(count($rows) === 2 && !isset($rows[0]['evil']) && $rows[1]['name'] === '', 'stored rows keep known keys, drop junk/empty rows and non-scalars');
    ok(count(Artiey_AET_Gallery::rows(array_fill(0, 50, ['name' => 'x']), ['name'], 24)) === 24, 'stored lists are capped');
    $aw = Artiey_AET_Gallery::award_lines("۱۴۰۱ | جایزه بهترین گالری\n1398 - عضویت انجمن\nبدون سال\n\n<b>تگ</b>");
    ok($aw[0] === ['year' => '۱۴۰۱', 'title' => 'جایزه بهترین گالری'] && $aw[1]['year'] === '1398' && $aw[2]['year'] === '' && $aw[3]['title'] === 'تگ', 'award lines split year and title; tags stripped');
    $GLOBALS['post_types'] = [55 => 'attachment', 99 => 'post'];
    $clean = Artiey_AET_Gallery_Admin::sanitize_rows([
        '__i__' => ['name' => 'template', 'text' => 'x'],
        'a' => ['name' => ' نقد <script>x</script>', 'text' => "خط اول\nخط دوم", 'url' => 'javascript:alert(1)', 'photo' => '55'],
        'b' => ['name' => 'بدون متن', 'text' => ''],
        'c' => ['name' => 'عکس نامعتبر', 'text' => 'ok', 'url' => 'https://ok.test/a', 'photo' => '99'],
    ], ['name' => 'text', 'text' => 'textarea', 'url' => 'url', 'photo' => 'image'], 24, ['name', 'text']);
    ok(count($clean) === 2, 'template row and rows missing required fields are dropped');
    ok($clean[0]['url'] === '' && $clean[0]['photo'] === 55 && !str_contains($clean[0]['name'], '<'), 'javascript: links are removed, valid image ids kept, tags stripped');
    ok($clean[1]['photo'] === '' && $clean[1]['url'] === 'https://ok.test/a', 'non-image attachments are rejected');

    echo "# Gallery: sections\n";
    ok(!array_diff(Artiey_AET_Gallery::ALL_SECTIONS, array_keys(Artiey_AET_Gallery::SECTION_LABELS)) && !array_diff(array_keys(Artiey_AET_Gallery::SECTION_LABELS), Artiey_AET_Gallery::ALL_SECTIONS), 'every section has an editor label and vice versa');
    foreach (['statement', 'critics', 'press', 'awards', 'team', 'follow', 'calls'] as $sec) {
        ok(in_array($sec, Artiey_AET_Gallery::ALL_SECTIONS, true), "gallery section available: $sec");
    }
    $prev14 = ['header', 'short_bio', 'bio', 'details', 'contact', 'hours', 'map', 'photos', 'artists', 'curators', 'collectors', 'auctions', 'exhibitions', 'past_exhibitions'];
    ok(Artiey_AET_Gallery::upgrade_default_sections($prev14) === Artiey_AET_Gallery::ALL_SECTIONS, 'widgets saved with the 3.0 default selection gain the new sections');
    $src = file_get_contents(dirname(__DIR__) . '/includes/class-gallery.php');
    ok(substr_count($src, 'referrerpolicy=') === 1, 'map iframe no longer has a duplicated referrerpolicy attribute');
    ok(str_contains($src, "! self::can_view( \$id )"), 'gallery renderer refuses drafts/private galleries for visitors');
    $pcss = file_get_contents(dirname(__DIR__) . '/assets/css/profile.css');
    foreach (['aetp-statement', 'aetp-critic', 'aetp-critics--slider', 'aetp-award-list', 'aetp-team-grid', 'aetp-press-item', 'aetp-hero-follow'] as $cls) {
        ok(str_contains($pcss, '.' . $cls), "profile stylesheet styles .$cls");
    }


    echo "# Gallery: rendering\n";
    $GLOBALS['post_types'][12] = 'artiey_gallery';
    $GLOBALS['meta'][12] = [
        '_ag_statement' => "هنر <strong>معاصر</strong> را <script>alert(1)</script> باور داریم.",
        '_ag_statement_author' => 'مریم <b>الف</b>', '_ag_statement_role' => 'مدیر هنری',
        '_ag_critics' => [
            ['name' => 'منتقد یک', 'role' => 'منتقد هنری', 'text' => str_repeat('واژه ', 120), 'source' => 'مجله تندیس', 'url' => 'https://tandis.test/a', 'date' => '1402', 'photo' => ''],
            ['name' => 'منتقد دو', 'role' => '', 'text' => 'نقد کوتاه <img src=x onerror=alert(1)>', 'source' => '', 'url' => 'javascript:alert(1)', 'date' => '', 'photo' => ''],
            ['name' => '', 'role' => '', 'text' => 'بی‌نام', 'source' => '', 'url' => '', 'date' => '', 'photo' => ''],
        ],
        '_ag_press' => [['title' => 'گزارش افتتاحیه', 'outlet' => 'ایسنا', 'url' => 'https://isna.test/x', 'date' => '1403', 'excerpt' => 'خلاصه']],
        '_ag_team' => [['name' => 'علی', 'role' => 'کیوریتور', 'photo' => '', 'url' => '']],
        '_ag_awards' => "1401 | جایزه سال",
    ];
    $html = Artiey_AET_Gallery::render_profile(12, ['statement', 'critics', 'press', 'team', 'awards'], true, ['critics_layout' => 'slider', 'critics_limit' => 5, 'critics_words' => 20, 'statement_style' => 'nope']);
    ok(str_contains($html, 'aetp-statement--quote') && str_contains($html, '<blockquote class="aetp-statement-text">') && !str_contains($html, '<script>'), 'statement renders as a quote; unknown style falls back; scripts removed');
    ok(str_contains($html, 'مریم &lt;b&gt;الف&lt;/b&gt;، مدیر هنری'), 'statement signature is escaped');
    ok(substr_count($html, 'class="aetp-critic"') === 2 && str_contains($html, 'aetp-critics--slider') && str_contains($html, 'tabindex="0" role="region"'), 'critics without a name are skipped; slider is keyboard scrollable');
    ok(str_contains($html, 'متن کامل نقد') && str_contains($html, 'cite="https://tandis.test/a"'), 'long critiques are shortened with a "full text" disclosure and cite the source');
    ok(!str_contains($html, 'javascript:') && !str_contains($html, '<img src=x'), 'critic links/markup cannot inject script');
    ok(str_contains($html, 'aetp-press-outlet">ایسنا') && str_contains($html, 'href="https://isna.test/x"') && str_contains($html, 'rel="noopener noreferrer"'), 'press items link out safely');
    ok(str_contains($html, 'aetp-award-year">۱۴۰۱') && str_contains($html, 'aetp-team-name">علی'), 'awards timeline and team cards render');
    $GLOBALS['post_status'][12] = 'draft';
    ok(Artiey_AET_Gallery::render_profile(12, ['statement']) === '', 'a draft gallery renders nothing for visitors');
    $GLOBALS['caps'] = true;
    ok(Artiey_AET_Gallery::render_profile(12, ['statement']) !== '', 'editors can preview a draft gallery');
    $GLOBALS['caps'] = false; $GLOBALS['post_status'][12] = 'publish';
    ok(Artiey_AET_Gallery::render_profile(12, ['critics', 'press', 'team', 'awards', 'statement'], true, []) !== '' && Artiey_AET_Gallery::render_profile(13, ['statement']) === '', 'non-gallery ids render nothing');

    echo "# Calls\n";
    $C = 'WPArtCOA\\Calls';
    $now = time();
    ok($C::days_left('') === null && $C::days_left(gmdate('Y-m-d H:i:s', $now + 3 * 86400 + 60), $now) === 3 && $C::days_left(gmdate('Y-m-d H:i:s', $now + 600), $now) === 0 && $C::days_left(gmdate('Y-m-d H:i:s', $now - 60), $now) === -1, 'days left: none / 3 / last day / passed');
    ok($C::url((object) ['id' => 5, 'slug' => 'open-call']) === 'https://example.test/art-coa/call/open-call/' && $C::url((object) ['id' => 5, 'slug' => '']) === 'https://example.test/art-coa/call/5/', 'call URL uses the slug, id only as fallback');
    ok($C::is_open((object) ['status' => 'open', 'deadline_utc' => gmdate('Y-m-d H:i:s', $now + 60)]) && !$C::is_open((object) ['status' => 'open', 'deadline_utc' => gmdate('Y-m-d H:i:s', $now - 60)]) && !$C::is_open((object) ['status' => 'closed', 'deadline_utc' => null]), 'open = status open and deadline not passed');
    ok($C::parse_dt('2026-12-01T10:30') === '2026-12-01 07:00:00' && $C::parse_dt('2026-13-01T10:30') === null && $C::parse_dt('') === null, 'admin datetime-local is site time and stored as UTC; invalid dates rejected');
    ok($C::to_local_input('2026-12-01 07:00:00') === '2026-12-01T10:30', 'stored UTC is shown back in site time in the edit form');
    $call = (object) ['id' => 1, 'slug' => 'x', 'title' => 'نمایشگاه گروهی', 'call_type' => 'exhibition', 'status' => 'open', 'event_start_utc' => '2026-12-01 07:00:00', 'event_end_utc' => '2026-12-10 07:00:00', 'deadline_utc' => gmdate('Y-m-d H:i:s', $now + 86400), 'description' => '<b>توضیح</b>', 'location' => 'خیابان ولیعصر', 'city' => 'تهران', 'country' => 'ایران', 'gallery_name' => 'گالری آریا', 'gallery_slug' => 'aria', 'fee' => '0', 'currency' => 'IRR', 'cover_url' => '', 'cover_id' => null];
    $sch = $C::event_schema($call);
    ok($sch['@type'] === 'ExhibitionEvent' && $sch['organizer']['name'] === 'گالری آریا' && $sch['location']['address']['addressLocality'] === 'تهران' && $sch['description'] === 'توضیح', 'exhibition calls get ExhibitionEvent schema with organizer and address');
    ok($sch['offers']['price'] === '0' && $sch['offers']['availability'] === 'https://schema.org/InStock', 'free entry is published as a zero-price offer');
    ok($sch['startDate'] === '2026-12-01T10:30:00+03:30', 'event dates are in the site time zone');
    ok($C::event_schema((object) ['event_start_utc' => null]) === null, 'no Event schema without event dates');
    $calls_src = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-calls.php');
    ok(str_contains($calls_src, "'art_coa_website_url'") && str_contains($calls_src, 'time() - $started < 3'), 'applications have a honeypot and a minimum fill time');
    ok(str_contains($calls_src, 'WHERE call_id = %d AND applicant_email = %s'), 'one application per email per call');
    ok(str_contains($calls_src, "wp_safe_redirect(self::url((object) ['id' => \$call_id, 'slug' => \$slug]), 301"), 'numeric call URLs 301 to the slug URL');
    ok(str_contains($calls_src, 'preg_match(\'/^[=+\\-@\\t\\r]/\', $value)'), 'CSV export neutralises spreadsheet formulas');
    ok(!str_contains($calls_src, 'class="art-coa-calls"'), 'list wrapper no longer reuses the grid class (layout bug)');
    $ccss = file_get_contents(dirname(__DIR__) . '/assets/css/calls.css');
    foreach (['aet-calls-toolbar', 'aet-calls-tabs', 'aet-call-badge', 'aet-calls-pager', 'aet-call-countdown', 'aet-hp', 'aet-call-share'] as $cls) {
        ok(str_contains($ccss, '.' . $cls), "calls stylesheet styles .$cls");
    }

    echo "\n$pass passed, $fail failed\n";
    exit($fail ? 1 : 0);
}
