<?php
/**
 * 3.2 regressions: certificate-of-authenticity link between artworks, shop and auctions; artwork page sections;
 * shop and auction fixes. Dependency-free. Run: php tests/artwork-shop-auction.php
 */
declare(strict_types=1);

namespace WPArtCOA {
    final class Database { public static function table(string $t): string { return 'wp_art_' . $t; } }
    final class CertificateMirror { public static array $links = []; public static function link(int $coa, int $art): bool { self::$links[] = [$coa, $art]; return true; } }
}

namespace {
    define('ABSPATH', __DIR__ . '/'); define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600);
    define('AET_URL', 'https://example.test/p/'); define('AET_VERSION', '9');
    $GLOBALS['meta'] = []; $GLOBALS['tr'] = []; $GLOBALS['caps'] = []; $GLOBALS['types'] = []; $GLOBALS['status'] = []; $GLOBALS['opts'] = ['artiey_aet_content_bridge_schema_version' => '1'];

    function add_action(...$a) {} function add_filter(...$a) {} function add_shortcode(...$a) {} function do_action(...$a) {}
    function get_option($k, $d = false) { return $GLOBALS['opts'][$k] ?? $d; } function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
    function get_transient($k) { return $GLOBALS['tr'][$k] ?? false; } function set_transient($k, $v, $e = 0) { $GLOBALS['tr'][$k] = $v; return true; } function delete_transient($k) { unset($GLOBALS['tr'][$k]); return true; }
    function get_post_meta($id, $k, $s = false) { return $GLOBALS['meta'][(int) $id][$k] ?? ''; }
    function update_post_meta($id, $k, $v) { $GLOBALS['meta'][(int) $id][$k] = $v; return true; }
    function get_post_type($id = 0) { return $GLOBALS['types'][(int) $id] ?? false; }
    function get_post_status($id = 0) { return $GLOBALS['status'][(int) $id] ?? 'publish'; }
    function post_password_required($id = 0) { return false; }
    function get_the_title($id = 0) { return 'اثر ' . (int) $id; }
    function current_user_can($cap, ...$a) { return !empty($GLOBALS['caps'][$cap]); }
    function get_current_user_id() { return 1; }
    function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } function esc_attr($s) { return esc_html($s); }
    function esc_url($s) { return preg_match('#^https?://#', (string) $s) ? esc_html($s) : ''; }
    function wp_style_is($h, $l = '') { return true; } function wp_register_style(...$a) {} function wp_enqueue_style(...$a) {}
    function absint($v) { return abs((int) $v); }

    final class Artiey_AET_Settings { public static function version() { return $GLOBALS['opts']['ver'] ?? '1'; } public static function bump() { $GLOBALS['opts']['ver'] = (string) ((int) ($GLOBALS['opts']['ver'] ?? 1) + 1); } public static function get($k) { return $k === 'show_certificates' ? true : ''; } }
    final class WP_COA_Pro_Signature { public function verify($r) { return empty($r->tampered); } }
    final class WP_COA_Pro_Ledger {}
    final class WP_COA_Pro_Repository {
        public static array $rows = [];
        public function __construct($a = null, $b = null) {}
        public function get_by_id($id) { return self::$rows[(int) $id] ?? null; }
        public function get_by_code($c) { foreach (self::$rows as $r) { if ($r->certificate_code === $c) { return $r; } } return null; }
    }
    final class WP_COA_Pro { public static function get_verification_url($c) { return 'https://example.test/verify/' . rawurlencode((string) $c); } }
    final class WP_COA_Pro_Admin { public static function get_settings() { return ['enable_public_lookup' => 1]; } }

    final class CertDb {
        public $postmeta = 'wp_postmeta';
        public array $index = [];      // art row id => [coa ids]
        public array $cpt = [];        // post id => art row id
        public array $updates = [];
        public function prepare($q, ...$a) { $i = 0; return preg_replace_callback('/%[sd]/', function ($m) use (&$i, $a) { $v = $a[$i++] ?? ''; return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes((string) $v) . "'"; }, $q); }
        public function esc_like($s) { return addcslashes((string) $s, '_%\\'); }
        public function get_col($q) {
            if (preg_match('/SELECT id FROM wp_art_artworks WHERE cpt_post_id = (\d+)/', $q, $m)) { return isset($this->cpt[(int) $m[1]]) ? [$this->cpt[(int) $m[1]]] : []; }
            if (preg_match('/SELECT coa_id FROM wp_art_certificates WHERE artwork_id = (\d+)/', $q, $m)) { return $this->index[(int) $m[1]] ?? []; }
            return [];
        }
        public function get_var($q) {
            if (preg_match('/SELECT cpt_post_id FROM wp_art_artworks WHERE id = (\d+)/', $q, $m)) { $f = array_search((int) $m[1], $this->cpt, true); return $f === false ? null : $f; }
            if (str_contains($q, "meta_key = '_aw_coa_certs'")) {
                foreach ($GLOBALS['meta'] as $pid => $meta) {
                    if (preg_match('/post_id <> (\d+)/', $q, $m) && (int) $m[1] === $pid) { continue; }
                    foreach ((array) ($meta['_aw_coa_certs'] ?? []) as $l) { if (str_contains($q, (string) $l['code'])) { return $pid; } }
                }
                return null;
            }
            return null;
        }
        public function update($t, $d, $w) { $this->updates[] = [$t, $d, $w]; return 1; }
    }
    $GLOBALS['wpdb'] = $db = new CertDb();

    require dirname(__DIR__) . '/includes/class-text.php';
    require dirname(__DIR__) . '/includes/class-jalali.php';
    require dirname(__DIR__) . '/includes/class-coa-bridge.php';

    $pass = 0; $fail = 0;
    function ok(bool $c, string $n): void { global $pass, $fail; if ($c) { $pass++; echo "  ok    $n\n"; } else { $fail++; echo "  FAIL  $n\n"; } }
    $B = 'Artiey_AET_COA_Bridge';
    $cert = static fn(int $id, string $code, string $status = 'active', bool $tampered = false) => (object) ['id' => $id, 'certificate_code' => $code, 'status' => $status, 'issue_date' => '2026-03-21 10:00:00', 'tampered' => $tampered];
    WP_COA_Pro_Repository::$rows = [11 => $cert(11, 'COA-AAA'), 12 => $cert(12, 'COA-BBB', 'revoked'), 13 => $cert(13, 'COA-CCC', 'active', true), 14 => $cert(14, 'COA-DDD'), 15 => $cert(15, 'COA-<b>X</b>')];

    echo "# Certificate links (both models)\n";
    $GLOBALS['types'] = [100 => 'artiey_artwork', 101 => 'artiey_artwork', 102 => 'artiey_artwork', 103 => 'post'];
    $GLOBALS['meta'][100]['_aw_coa_certs'] = [['id' => 11, 'code' => 'COA-AAA', 'at' => 1]];
    $db->cpt = [100 => 7];
    $db->index = [7 => [14, 11]];
    ok($B::certificate_ids(100) === [11, 14], 'post links and the Art COA index are merged without duplicates');
    ok($B::certificate_ids(0, 7) === [11, 14], 'a catalogue row resolves back to its WordPress artwork post');
    $sum = $B::certificate_summaries(100);
    ok(count($sum) === 2 && $sum[0]['state'] === 'valid' && $sum[0]['url'] === 'https://example.test/verify/COA-AAA', 'summaries carry state and the public verification URL');
    ok(!array_diff(array_keys($sum[0]), ['id', 'code', 'state', 'label', 'url', 'issued']), 'summaries expose public fields only (no owner, notes or signature)');
    $GLOBALS['meta'][101]['_aw_coa_certs'] = [['id' => 12, 'code' => 'COA-BBB'], ['id' => 13, 'code' => 'COA-CCC']];
    $sum = $B::certificate_summaries(101);
    ok(array_column($sum, 'state') === ['check', 'revoked'], 'tampered certificates need review, revoked ones are listed last');
    ok(!$B::has_valid_certificate(101) && $B::has_valid_certificate(100), 'the ✓ badge only appears with a valid, verified certificate');
    ok($B::badge_html($B::certificate_summaries(101), 'badge') === '', 'no badge for revoked / unverifiable certificates');
    $GLOBALS['meta'][102]['_aw_coa_certs'] = [['id' => 15, 'code' => 'x']];
    $panel = $B::badge_html($B::certificate_summaries(102), 'panel');
    ok(str_contains($panel, 'COA-&lt;b&gt;X&lt;/b&gt;') && !str_contains($panel, '<b>X'), 'certificate codes are escaped in the panel');
    ok(str_contains($panel, 'استعلام آنلاین گواهی') && str_contains($panel, 'aet-coa-valid'), 'panel links to online verification');

    echo "# Admin linking\n";
    ok($B::link_certificate_code(102, 'COA-DDD') !== '', 'linking requires the certificate-management capability');
    $GLOBALS['caps'] = ['manage_coa_certificates' => true, 'edit_post' => true];
    ok($B::link_certificate_code(102, 'NOPE') === 'گواهی با این کد پیدا نشد.', 'unknown codes are rejected');
    ok(str_contains($B::link_certificate_code(102, 'COA-AAA'), 'به اثر دیگری'), 'a certificate already linked to another artwork is refused');
    ok($B::link_certificate_code(103, 'COA-DDD') !== '', 'only artwork posts can receive certificates');
    $db->cpt[102] = 9;
    $ver = Artiey_AET_Settings::version();
    ok($B::link_certificate_code(102, ' coa-ddd ') === '' && in_array(14, array_column($GLOBALS['meta'][102]['_aw_coa_certs'], 'id'), true), 'a valid code (case/space-insensitive) is linked');
    ok(\WPArtCOA\CertificateMirror::$links === [[14, 9]], 'the Art COA index is pointed at the mirrored catalogue row');
    ok(Artiey_AET_Settings::version() !== $ver, 'linking invalidates cached summaries');
    ok($B::unlink_certificate(102, 14) && !in_array(14, array_column($GLOBALS['meta'][102]['_aw_coa_certs'], 'id'), true) && end($db->updates)[2] === ['coa_id' => 14], 'unlinking removes both links');

    echo "# Artwork page\n";
    $src = file_get_contents(dirname(__DIR__) . '/includes/class-artwork.php');
    preg_match("/const SECTIONS = array\\(([^)]*)\\)/", $src, $m);
    ok(str_contains($m[1], "'certificate'") && str_contains($m[1], "'market'"), 'artwork page has certificate and market sections');
    ok(str_contains($src, "! self::can_view( \$id )") && substr_count($src, 'self::can_view( $id )') >= 2, 'artwork renderer and field shortcode refuse drafts for visitors');
    ok(str_contains($src, 'has_valid_certificate( $p->ID )'), 'artwork cards show ✓ only for a valid certificate');
    ok(str_contains($src, "WHERE cpt_post_id = %d LIMIT 2") && !str_contains($src, 'row_id_for_post'), 'artwork page looks up the catalogue row read-only (no sync on view)');
    $artist = file_get_contents(dirname(__DIR__) . '/includes/class-artist.php');
    ok(str_contains($artist, "function_exists( 'is_admin' ) && is_admin() && class_exists( '\\\\WPArtCOA\\\\ContentBridge' )"), 'artist page no longer syncs the relational row on public views');
    ok(str_contains($artist, "antispambot( \$a['email'] )"), 'artist email is obfuscated');
    $style = file_get_contents(dirname(__DIR__) . '/includes/class-elementor-style.php');
    ok(str_contains($style, "'interviews' => 'مصاحبه‌ها', 'certificate_stats'"), 'artist tab layout no longer drops interviews and certificate sections');
    $js = file_get_contents(dirname(__DIR__) . '/assets/js/profile.js');
    ok(str_contains($js, 'a[data-aetp-lightbox]') && str_contains($js, "e.key === 'Escape'") && str_contains($js, 'opener.focus()'), 'profile lightbox: keyboard support and focus restore');

    echo "# Shop\n";
    $shop = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-shop.php');
    ok(str_contains($shop, "'art_coa_company'") && str_contains($shop, 'time() - $started < 3') && str_contains($shop, "art_coa_shop_inq_"), 'inquiries: honeypot, minimum fill time and per-IP rate limit');
    ok(str_contains($shop, "WHERE a.id = %d AND a.status IN ("), 'inquiries are accepted only for public artworks');
    ok(str_contains($shop, "self::set_flash('err', I18n::t('reg_required'))"), 'invalid inquiries show an error instead of silently failing');
    ok(str_contains($shop, 'function artwork_url') && str_contains($shop, "<h3><?php if (\$url)"), 'shop cards link to the artwork page');
    ok(str_contains($shop, "WHERE ar.status = 'active'") && str_contains($shop, "WHERE g.status = 'active'"), 'filter lists only contain public artists and galleries');
    ok(str_contains($shop, "badge_html(\$certs, 'badge')") && str_contains($shop, 'AS auction_id'), 'shop cards show certificate and auction badges');
    ok(str_contains($shop, "'Reply-To: \"' . \$reply_name"), 'Reply-To header is quoted and stripped of control characters');

    echo "# Auction\n";
    $au = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-auction.php');
    ok(str_contains($au, '$params = array_merge($params, [$like, $like, $like, $like]);'), 'visitor search keeps the public-status parameters (search was broken for non-admins)');
    ok(str_contains($au, "badge_html(\$lot_certs, 'panel')"), 'lot page shows the verified certificate panel');
    ok(str_contains($au, "'scheduled' => (I18n::is_fa() ? 'هنوز آغاز نشده'") && str_contains($au, "is-scheduled"), 'scheduled auctions are labelled "upcoming", not live');
    ok(str_contains($au, "'@type' => ['Product', 'VisualArtwork']") && str_contains($au, 'priceValidUntil'), 'lot pages publish Product/Offer structured data');

    echo "\n$pass passed, $fail failed\n";
    exit($fail ? 1 : 0);
}
