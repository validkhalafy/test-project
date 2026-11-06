<?php
/**
 * Registration / profile form engine: validation, settings, anti-bot, ownership, full flows.
 * Run: php tests/member-forms.php
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600);

$GLOBALS['o'] = []; $GLOBALS['um'] = []; $GLOBALS['users'] = []; $GLOBALS['mail'] = []; $GLOBALS['audit'] = [];
$GLOBALS['entity_calls'] = []; $GLOBALS['update_calls'] = []; $GLOBALS['entity_rows'] = []; $GLOBALS['uid'] = 0; $GLOBALS['logged'] = 0;
$GLOBALS['owner'] = null; $GLOBALS['deleted'] = []; $GLOBALS['next_uid'] = 10; $GLOBALS['t'] = [];

final class Redirect extends Exception { public function __construct(public string $url) { parent::__construct($url); } }
function get_option($k, $d = false) { return $GLOBALS['o'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['o'][$k] = $v; return true; }
function get_transient($k) { return $GLOBALS['t'][$k] ?? false; }
function set_transient($k, $v, $e = 0) { $GLOBALS['t'][$k] = $v; return true; }
function apply_filters($t, $v) { return $v; }
function absint($v) { return abs((int) $v); }
function sanitize_text_field($s) { return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $s))); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_key($s) { return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $s)); }
function sanitize_email($s) { return filter_var(trim((string) $s), FILTER_VALIDATE_EMAIL) ?: ''; }
function sanitize_user($s, $strict = false) { return (string) preg_replace('/[^a-zA-Z0-9 _.\-@]/', '', (string) $s); }
function is_email($s) { return (bool) filter_var($s, FILTER_VALIDATE_EMAIL); }
function esc_url_raw($u, $p = null) { return preg_match('#^https?://#i', (string) $u) ? (string) $u : ''; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function checked($a, $b = true, $e = true) { $r = (string) $a === (string) $b ? ' checked' : ''; if ($e) { echo $r; } return $r; }
function selected($a, $b = true, $e = true) { $r = (string) $a === (string) $b ? ' selected' : ''; if ($e) { echo $r; } return $r; }
function wp_kses_post($s) { return (string) $s; }
function current_user_can($c) { return true; }
function esc_html__($t, $d = '') { return $t; }
function check_admin_referer($a) { $GLOBALS['referer_checked'][] = $a; return 1; }
function wp_die($m = '') { throw new RuntimeException('wp_die'); }
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : (is_string($v) ? stripslashes($v) : $v); }
function wp_salt($s = '') { return 'test-salt'; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function wp_verify_nonce($n, $a) { return $n === 'nonce:' . $a ? 1 : false; }
function wp_nonce_field($a, $n = '_wpnonce', $r = true, $e = true) { $h = '<input type="hidden" name="' . $n . '" value="nonce:' . $a . '">'; if ($e) { echo $h; } return $h; }
function wp_generate_password($l = 12, $s = true, $e = false) { return str_repeat('k', $l); }
function add_query_arg($a, $b = null, $c = null) { if (is_array($a)) { $url = (string) $b; $q = $a; } else { $url = (string) $c; $q = [$a => $b]; } return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($q); }
function remove_query_arg($k, $u = false) { return (string) $u; }
function home_url($p = '') { return 'https://example.test' . $p; }
function admin_url($p = '') { return 'https://example.test/wp-admin/' . $p; }
function wp_login_url($r = '') { return 'https://example.test/login'; }
function get_post_status($id) { return $id === 5 ? 'publish' : false; }
function get_permalink($id = 0) { return 'https://example.test/register/'; }
function wp_get_referer() { return 'https://example.test/dash/'; }
function wp_safe_redirect($u, $s = 302) { throw new Redirect($u); }
function wp_mail($to, $s, $b) { if (!empty($GLOBALS['mail_fail'])) { return false; } $GLOBALS['mail'][] = [$to, $s, $b]; return true; }
function is_ssl() { return true; }
function wp_set_password($p, $id) { $GLOBALS['pw_reset'][] = $id; }
function get_password_reset_key($u) { return 'resetkey'; }
function network_site_url($p = '', $s = null) { return 'https://example.test/' . $p; }
function wp_specialchars_decode($s, $q = 0) { return (string) $s; }
function get_bloginfo($k) { return 'Site'; }
function get_user_meta($id, $k, $single = false) { return $GLOBALS['um'][$id][$k] ?? ''; }
function update_user_meta($id, $k, $v) { $GLOBALS['um'][$id][$k] = $v; return true; }
function delete_user_meta($id, $k) { unset($GLOBALS['um'][$id][$k]); return true; }
function username_exists($u) { foreach ($GLOBALS['users'] as $x) { if ($x['user_login'] === $u) { return $x['ID']; } } return false; }
function email_exists($e) { foreach ($GLOBALS['users'] as $x) { if ($x['user_email'] === $e) { return $x['ID']; } } return false; }
function get_role($r) { return (object) ['name' => $r]; }
function wp_insert_user($d) { $id = $GLOBALS['next_uid']++; $GLOBALS['users'][$id] = $d + ['ID' => $id]; return $id; }
function wp_delete_user($id) { $GLOBALS['deleted'][] = $id; unset($GLOBALS['users'][$id]); return true; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function is_user_logged_in() { return $GLOBALS['logged'] > 0; }
function get_current_user_id() { return $GLOBALS['logged']; }
function wp_set_current_user($id) { $GLOBALS['logged'] = $id; }
function wp_set_auth_cookie($id) { $GLOBALS['cookie'] = $id; }
function shortcode_atts($d, $a, $t = '') { return array_merge($d, array_intersect_key((array) $a, $d)); }
function wp_enqueue_style($h) {}
function wp_get_current_user() { $o = new WP_User(); $o->ID = $GLOBALS['logged']; $o->user_email = 'me@example.com'; $o->roles = $GLOBALS['cur_roles'] ?? []; return $o; }
function get_user_by($f, $v) { foreach ($GLOBALS['users'] as $x) { if ($x['user_email'] === $v) { return get_userdata($x['ID']); } } return false; }
function get_userdata($id) { return isset($GLOBALS['users'][$id]) ? (object) ['ID' => $id, 'user_login' => $GLOBALS['users'][$id]['user_login'], 'user_email' => $GLOBALS['users'][$id]['user_email'], 'roles' => [$GLOBALS['users'][$id]['role']]] : false; }
class WP_Error { public function __construct(public string $code = '', public string $msg = '') {} }
class WP_User { public int $ID = 0; public array $roles = []; public string $user_email = ''; public function exists(): bool { return $this->ID > 0; } }
final class FakeDb {
    public string $postmeta = 'wp_postmeta';
    public function prepare($q, ...$a) { return $q; }
    public function get_var($q) { return str_contains($q, 'user_id FROM') ? $GLOBALS['owner'] : null; }
    public array $updates = [];
    public function update(...$a) { $this->updates[] = $a; return 1; }
}
$GLOBALS['wpdb'] = new FakeDb();
$GLOBALS['o']['wp_art_coa_page_register'] = 5;

require __DIR__ . '/member-forms-stubs.php';
require dirname(__DIR__) . '/art-coa/includes/class-member-forms.php';
require dirname(__DIR__) . '/art-coa/includes/class-member-forms-admin.php';
use WPArtCOA\MemberForms as M;

$pass = 0; $fail = 0;
function ok(bool $c, string $n): void { global $pass, $fail; if ($c) { $pass++; echo "  ok    $n\n"; } else { $fail++; echo "  FAIL  $n\n"; } }
function role(string $k, ?array $settings = null): array { return M::active_roles('', $settings)[$k]; }
function post(array $p): void { $_POST = $p; }
function redirect_of(callable $fn): ?string { try { $fn(); } catch (Redirect $r) { return $r->url; } return null; }
function reset_world(): void { M::flush_cache(); $_COOKIE = []; $GLOBALS['mail_fail'] = false; $GLOBALS['pw_reset'] = []; $GLOBALS['t'] = []; $GLOBALS['users'] = []; $GLOBALS['mail'] = []; $GLOBALS['um'] = []; $GLOBALS['entity_calls'] = []; $GLOBALS['update_calls'] = []; $GLOBALS['entity_fail'] = false; $GLOBALS['logged'] = 0; $GLOBALS['deleted'] = []; $GLOBALS['o'][M::OPTION] = []; $_POST = []; $_GET = []; }

/* ---------- validation ---------- */
echo "# Validation\n";
[$c, $e] = M::validate_role(role('artist'), ['first_name' => 'علی', 'last_name' => '', 'phone' => '۰۹۱۲۳۴۵۶۷۸۹', 'website' => 'javascript:alert(1)', 'birth_year' => '۱۳۷۰', 'mediums' => 'رنگ روغن, عکاسی ,رنگ روغن', 'instagram' => 'https://instagram.com/ali.art/']);
ok(isset($e['last_name']), 'artist last name is required (the old form let it through and then failed)');
ok($c['phone'] === '09123456789', 'Persian digits in phone are normalised');
ok(isset($e['website']), 'javascript: URL is rejected');
ok($c['birth_year'] === '1370', 'Jalali birth year accepted');
ok($c['mediums'] === 'رنگ روغن, عکاسی', 'tags trimmed and de-duplicated');
ok($c['instagram'] === 'ali.art', 'instagram URL reduced to the username');
[, $e] = M::validate_role(role('artist'), ['first_name' => 'a', 'last_name' => 'b', 'birth_year' => '1700', 'phone' => '12']);
ok(isset($e['birth_year']) && isset($e['phone']), 'impossible year and short phone rejected');
[, $e] = M::validate_role(role('artist'), ['first_name' => str_repeat('x', 200), 'last_name' => 'b']);
ok(isset($e['first_name']), 'over-long values are rejected');
[$c] = M::validate_role(role('collector'), ['display_name' => 'x', 'is_public' => '1']);
ok($c['is_public'] === 1, 'checkbox on');
[$c] = M::validate_role(role('collector'), ['display_name' => 'x']);
ok($c['is_public'] === 0, 'checkbox off when absent');
[$c, $e] = M::validate_role(role('curator'), ['display_name' => 'c', 'years_experience' => '۱۲']);
ok($c['years_experience'] === '12' && !$e, 'number field normalised');
[, $e] = M::validate_role(role('curator'), ['display_name' => 'c', 'years_experience' => '500']);
ok(isset($e['years_experience']), 'number out of range rejected');
[$c] = M::validate_role(role('gallery'), ['gallery_name' => 'گ', 'city' => 'تهران', 'about' => "<b>x</b>\nخط دوم"]);
ok(strpos($c['about'], '<b>') === false && strpos($c['about'], "\n") !== false, 'tags stripped, line breaks kept in textareas');
[$c] = M::validate_role(role('buyer'), ['display_name' => 'a\\b']);
ok($c['display_name'] === 'a\\b', 'backslashes are not unslashed a second time');

/* ---------- settings ---------- */
echo "# Settings\n";
$s = M::sanitize_settings(['fields' => ['artist' => ['first_name' => ['enabled' => 0, 'required' => 0], 'city' => ['enabled' => 0, 'required' => 1]], 'ghost' => ['x' => ['enabled' => 1]]], 'roles' => ['ghost' => ['enabled' => 1]], 'min_password' => 3]);
ok($s['fields']['artist']['first_name']['enabled'] === 1 && $s['fields']['artist']['first_name']['required'] === 1, 'locked field cannot be disabled or made optional');
ok($s['fields']['artist']['city']['enabled'] === 0 && $s['fields']['artist']['city']['required'] === 0, 'a hidden field cannot stay required');
ok(!isset($s['roles']['ghost']) && !isset($s['fields']['ghost']), 'unknown roles and fields are dropped');
ok($s['min_password'] === 8, 'minimum password length is clamped');
$custom = M::sanitize_settings(['roles' => ['gallery' => ['enabled' => 0], 'artist' => ['label' => '<b>نقاش</b>']], 'fields' => ['artist' => ['website' => ['required' => 1]]]]);
$roles = M::active_roles('', $custom);
ok(!isset($roles['gallery']) && $roles['artist']['title'] === 'نقاش', 'role can be disabled and renamed');
$web = array_values(array_filter($roles['artist']['fields'], fn($f) => $f['name'] === 'website'))[0];
ok($web['required'] === true, 'optional field can be made required');
ok(array_keys(M::active_roles('curator,buyer')) === ['curator', 'buyer'], 'shortcode roles= whitelist');

/* ---------- passwords / tokens / usernames ---------- */
echo "# Password, token, username\n";
ok(M::password_problems('abc', 'a@b.co', 10) !== [], 'short password rejected');
ok(M::password_problems('abcdefghijkl', 'a@b.co', 10) !== [], 'password without a digit rejected');
ok(M::password_problems('Sunny-day-2026', 'a@b.co', 10) === [], 'good password accepted');
ok(M::password_problems('ali12345ali', 'ali12345ali', 10) !== [], 'password equal to email rejected');
$tok = M::issue_time_token(1000000000);
ok(M::time_token_ok($tok, 3, 1000000010), 'genuine token after 10 s is accepted');
ok(!M::time_token_ok($tok, 3, 1000000001), 'instant submit is treated as a bot');
ok(!M::time_token_ok($tok, 3, 1000000000 + 8000), 'stale token rejected');
ok(!M::time_token_ok(str_replace('.', '.0', $tok), 3, 1000000010) && !M::time_token_ok('1000000000.' . str_repeat('a', 64), 3, 1000000010), 'forged token rejected');
$GLOBALS['users'][1] = ['ID' => 1, 'user_login' => 'sara', 'user_email' => 'x@y.z', 'role' => 'x'];
$login = M::username_from_email('sara.private@example.com');
ok(stripos($login, 'sara') === false && stripos($login, 'private') === false, 'login never contains part of the email (author archives would leak it)');
ok($login !== M::username_from_email('sara.private@example.com'), 'logins are random, not deterministic');
$GLOBALS['users'] = [];

/* ---------- entity mapping ---------- */
echo "# Entity mapping\n";
$d = M::entity_data('gallery', ['gallery_name' => 'گالری الف', 'city' => 'شیراز'], 9, 'g@x.io');
ok($d['name'] === 'گالری الف' && $d['user_id'] === 9 && $d['email'] === 'g@x.io', 'gallery_name maps to the entity name');
ok(M::entity_data('collector', ['display_name' => 'n', 'is_public' => 1], 9, 'e@x.io')['is_public'] === true, 'collector privacy flag maps to bool');
ok(M::completeness(role('buyer'), ['display_name' => 'n']) === 50, 'completeness counts filled fields (1 of 2)');

/* ---------- ownership ---------- */
echo "# Ownership\n";
reset_world(); $GLOBALS['logged'] = 5;
ok(M::current_member() === null, 'a user without an entity has no member page');
$GLOBALS['um'][5] = ['art_coa_role' => 'artist', 'art_coa_entity_id' => 40];
$GLOBALS['owner'] = '5'; ok(M::current_member() !== null, 'entity bound to this user is accessible');
$GLOBALS['owner'] = '99'; ok(M::current_member() === null, 'entity bound to another user is refused');
$GLOBALS['um'][5] = ['art_coa_role' => 'administrator', 'art_coa_entity_id' => 40]; $GLOBALS['owner'] = '5';
ok(M::current_member() === null, 'unknown role in user meta is refused');

/* ---------- rendering ---------- */
echo "# Rendering\n";
reset_world();
$html = M::render_register(['default' => 'gallery']);
preg_match_all('/<fieldset class="acm-panel"[^>]*>/', $html, $m);
$disabled = count(array_filter($m[0], fn($t) => strpos($t, 'disabled') !== false));
ok(count($m[0]) === 5 && $disabled === 4, 'five role panels, only the selected one is enabled');
ok(preg_match('/id="acm-panel-gallery"[^>]*>/', $html, $g) && strpos($g[0], 'disabled') === false, 'default role panel is the active one');
ok(strpos($html, 'name="f[artist][website]"') !== false && strpos($html, 'name="f[gallery][website]"') !== false, 'same field name is namespaced per role');
ok(!preg_match('/name="(bio|website|city|phone)"/', $html), 'no un-namespaced role fields can collide');
ok(strpos($html, 'art_coa_hp') !== false && strpos($html, 'art_coa_ts') !== false && strpos($html, 'art_coa_register_nonce') !== false, 'honeypot, time token and nonce are present');
ok(substr_count($html, 'type="radio" name="member_role"') === 5, 'curator is offered alongside the other roles');

/* ---------- registration flow: email verification on ---------- */
echo "# Registration flow (verification on)\n";
$valid = fn(array $over = []) => array_replace_recursive([
    'art_coa_register_nonce' => 'nonce:' . M::NONCE_REG, 'art_coa_ts' => M::issue_time_token(time() - 30),
    'member_role' => 'artist', 'email' => 'new@example.com', 'password' => 'Sunny-day-2026', 'password_confirm' => 'Sunny-day-2026',
    'f' => ['artist' => ['first_name' => 'علی', 'last_name' => 'رضایی', 'city' => 'تهران']],
], $over);
reset_world();
post($valid());
$url = redirect_of([M::class, 'handle_register']);
ok($url !== null && str_contains($url, 'art_coa_registered=pending'), 'success redirects to the "check your email" screen');
$u = array_values($GLOBALS['users'])[0] ?? [];
ok(($u['role'] ?? '') === 'art_artist', 'role comes from the server schema');
ok(count($GLOBALS['entity_calls']) === 0 && empty($GLOBALS['um'][$u['ID'] ?? 0]['art_coa_entity_id']), 'no profile is created before the email is confirmed');
ok(count($GLOBALS['mail']) === 1 && str_contains($GLOBALS['mail'][0][2], 'art_coa_confirm_email=' . ($u['ID'] ?? 0)), 'confirmation mail carries a link for this user');
ok($GLOBALS['logged'] === 0, 'user is not logged in before confirming');

reset_world(); post($valid(['member_role' => 'administrator']));
ok(redirect_of([M::class, 'handle_register']) === null && !$GLOBALS['users'], 'administrator cannot be chosen as a role');

reset_world(); post($valid(['art_coa_ts' => M::issue_time_token(time())]));
ok(redirect_of([M::class, 'handle_register']) === null && !$GLOBALS['users'], 'instant submit is rejected');

reset_world(); post($valid(['art_coa_register_nonce' => 'bad']));
ok(redirect_of([M::class, 'handle_register']) === null && !$GLOBALS['users'], 'bad nonce is rejected');

reset_world(); post($valid(['password_confirm' => 'different-1']));
ok(redirect_of([M::class, 'handle_register']) === null && !$GLOBALS['users'], 'password mismatch creates nothing');

reset_world(); $GLOBALS['users'][3] = ['ID' => 3, 'user_login' => 'old', 'user_email' => 'new@example.com', 'role' => 'subscriber'];
post($valid());
$url = redirect_of([M::class, 'handle_register']);
ok($url !== null && str_contains($url, 'art_coa_registered=pending') && count($GLOBALS['users']) === 1 && !$GLOBALS['mail'], 'existing email gets the same screen, no new user, no leak');

/* ---------- confirmation ---------- */
echo "# Confirmation\n";
reset_world(); post($valid()); redirect_of([M::class, 'handle_register']);
$uid = (int) array_key_first($GLOBALS['users']);
$_POST = []; $_GET = ['art_coa_confirm_email' => (string) $uid, 'key' => str_repeat('k', 40)];
$url = redirect_of([M::class, 'handle_confirm']);
ok(str_contains((string) $url, 'verified'), 'valid link confirms the account');
ok(($GLOBALS['um'][$uid]['art_coa_entity_id'] ?? 0) === 77 && ($GLOBALS['um'][$uid]['art_coa_role'] ?? '') === 'artist', 'profile is created and linked after confirmation');
ok(($GLOBALS['entity_calls'][0][1]['status'] ?? '') === 'pending' && ($GLOBALS['entity_calls'][0][1]['user_id'] ?? 0) === $uid, 'moderation: artist profile starts as pending and is bound to the new user');
ok(empty($GLOBALS['um'][$uid]['art_coa_email_pending']) && empty($GLOBALS['um'][$uid]['art_coa_pending_profile']), 'pending markers are cleared');
reset_world(); post($valid()); redirect_of([M::class, 'handle_register']); $uid = (int) array_key_first($GLOBALS['users']);
$_POST = []; $_GET = ['art_coa_confirm_email' => (string) $uid, 'key' => str_repeat('z', 40)];
ok(str_contains((string) redirect_of([M::class, 'handle_confirm']), 'invalid') && !$GLOBALS['entity_calls'], 'wrong key is refused and nothing is created');
$GLOBALS['um'][$uid]['art_coa_email_token_exp'] = time() - 5; $_GET['key'] = str_repeat('k', 40);
ok(str_contains((string) redirect_of([M::class, 'handle_confirm']), 'invalid'), 'expired link is refused');
$wp_user = new WP_User(); $wp_user->ID = $uid;
ok(M::block_unverified_login($wp_user) instanceof WP_Error, 'unconfirmed account cannot log in');

/* ---------- registration flow: verification off ---------- */
echo "# Registration flow (verification off)\n";
reset_world(); $GLOBALS['o'][M::OPTION] = ['verify_email' => 0, 'moderation' => 1];
post($valid(['member_role' => 'gallery', 'f' => ['gallery' => ['gallery_name' => 'گالری نو', 'city' => 'اصفهان']]]));
$url = redirect_of([M::class, 'handle_register']);
ok(str_contains((string) $url, 'art_coa_welcome=pending') && $GLOBALS['logged'] > 0, 'logged in and sent to the dashboard with a "pending review" notice');
ok(($GLOBALS['entity_calls'][0][1]['name'] ?? '') === 'گالری نو' && ($GLOBALS['entity_calls'][0][1]['status'] ?? '') === 'pending', 'gallery data mapped and held for moderation');

reset_world(); $GLOBALS['o'][M::OPTION] = ['verify_email' => 0]; $GLOBALS['entity_fail'] = true;
post($valid());
ok(redirect_of([M::class, 'handle_register']) === null && count($GLOBALS['deleted']) === 1 && !$GLOBALS['users'], 'profile failure removes the half-created user (no stuck account)');

reset_world(); $GLOBALS['o'][M::OPTION] = ['verify_email' => 0, 'moderation' => 0];
post($valid(['member_role' => 'buyer', 'f' => ['buyer' => ['display_name' => 'خریدار']]]));
$url = redirect_of([M::class, 'handle_register']);
ok(str_contains((string) $url, 'art_coa_welcome=ok'), 'buyers are never held for moderation');

/* ---------- profile save ---------- */
echo "# Profile save\n";
reset_world(); $GLOBALS['logged'] = 5; $GLOBALS['um'][5] = ['art_coa_role' => 'collector', 'art_coa_entity_id' => 40]; $GLOBALS['owner'] = '5';
post(['art_coa_profile_save' => '1', 'art_coa_profile_nonce' => 'nonce:' . M::NONCE_PROFILE, 'f' => ['collector' => ['display_name' => 'نام جدید'], 'artist' => ['first_name' => 'hack']], 'entity_id' => '999', 'user_id' => '1', 'role' => 'administrator']);
$url = redirect_of([M::class, 'handle_profile_save']);
$call = $GLOBALS['update_calls'][0] ?? null;
ok($call && $call[1] === 40 && str_contains((string) $call[0], 'Collector'), 'the update targets the member\'s own entity, not the posted id');
ok($call && !isset($call[2]['user_id']) && !isset($call[2]['email']), 'identity fields are never written from the profile form');
ok(str_contains((string) $url, 'art_coa_profile=saved'), 'redirects with a saved notice');
reset_world(); $GLOBALS['logged'] = 5; $GLOBALS['um'][5] = ['art_coa_role' => 'collector', 'art_coa_entity_id' => 40]; $GLOBALS['owner'] = '99';
post(['art_coa_profile_save' => '1', 'art_coa_profile_nonce' => 'nonce:' . M::NONCE_PROFILE, 'f' => ['collector' => ['display_name' => 'x']]]);
redirect_of([M::class, 'handle_profile_save']);
ok(!$GLOBALS['update_calls'], 'an entity owned by someone else is never updated');

/* ---------- hardening ---------- */
echo "# Hardening\n";
reset_world(); post($valid());
redirect_of([M::class, 'handle_register']);
$u = array_values($GLOBALS['users'])[0];
ok(stripos($u['user_login'], 'new') === false && ($u['nickname'] ?? '') === 'علی رضایی', 'public nickname is the person\'s name, not the login');

// pre-hijack: link opened in a different browser than the one that signed up
reset_world(); post($valid()); redirect_of([M::class, 'handle_register']); $uid = (int) array_key_first($GLOBALS['users']);
$_POST = []; $_GET = ['art_coa_confirm_email' => (string) $uid, 'key' => str_repeat('k', 40)]; $_COOKIE = [];
$url = (string) redirect_of([M::class, 'handle_confirm']);
ok(str_contains($url, 'action=rp') && $GLOBALS['pw_reset'] === [$uid], 'link opened elsewhere: password is reset, owner must choose a new one');
ok(($GLOBALS['um'][$uid]['art_coa_entity_id'] ?? 0) === 77, '...while the email still counts as confirmed and the profile is created');

reset_world(); post($valid()); redirect_of([M::class, 'handle_register']); $uid = (int) array_key_first($GLOBALS['users']);
$_POST = []; $_GET = ['art_coa_confirm_email' => (string) $uid, 'key' => str_repeat('k', 40)];
$url = (string) redirect_of([M::class, 'handle_confirm']);
ok(str_contains($url, 'verified') && !$GLOBALS['pw_reset'], 'same browser: normal confirmation, password kept');

// mail failure must not strand an account
reset_world(); $GLOBALS['mail_fail'] = true; post($valid());
ok(redirect_of([M::class, 'handle_register']) === null && count($GLOBALS['deleted']) === 1 && !$GLOBALS['users'], 'confirmation mail failure removes the account so the person can retry');

// resend limits
reset_world(); $GLOBALS['users'][4] = ['ID' => 4, 'user_login' => 'p', 'user_email' => 'p@example.com', 'role' => 'art_artist'];
$GLOBALS['um'][4] = ['art_coa_email_pending' => time(), 'art_coa_pending_profile' => json_encode(['role' => 'artist', 'data' => ['first_name' => 'a']])];
for ($i = 0; $i < 8; $i++) { $GLOBALS['t'] = array_filter($GLOBALS['t'], fn($k) => str_contains((string) $k, '_ip_'), ARRAY_FILTER_USE_KEY); post(['art_coa_resend_email' => "p$i@example.com", 'art_coa_resend_nonce' => 'nonce:' . M::NONCE_RESEND]); redirect_of([M::class, 'handle_resend']); }
ok((int) ($GLOBALS['t']['art_coa_resend_ip_' . md5('203.0.113.9')] ?? 0) === 5, 'resend is capped per IP even when the email changes');

// wp-admin access
$GLOBALS['logged'] = 8; $GLOBALS['cur_roles'] = ['art_artist'];
$_SERVER['SCRIPT_NAME'] = '/wp-admin/index.php';
ok(str_contains((string) redirect_of([M::class, 'keep_members_out_of_admin']), 'member-dashboard') || redirect_of([M::class, 'keep_members_out_of_admin']) !== null, 'a member opening wp-admin is sent to the dashboard');
$_SERVER['SCRIPT_NAME'] = '/wp-admin/profile.php';
ok(redirect_of([M::class, 'keep_members_out_of_admin']) === null, 'profile.php stays open so members can change their password');
$GLOBALS['cur_roles'] = ['art_artist', 'administrator'];
$_SERVER['SCRIPT_NAME'] = '/wp-admin/index.php';
ok(redirect_of([M::class, 'keep_members_out_of_admin']) === null, 'accounts that are also administrators are never locked out');

/* ---------- admin approval of curators/collectors ---------- */
echo "# Approval\n";
$cell = \WPArtCOA\MemberFormsAdmin::status_cell('collector', (object) ['id' => 5, 'status' => 'pending']);
ok(str_contains($cell, 'name="status" value="active"') && str_contains($cell, 'name="kind" value="collector"') && str_contains($cell, 'art_coa_person_status_collector_5'), 'a pending row offers "approve" with a nonce bound to the row');
$cell = \WPArtCOA\MemberFormsAdmin::status_cell('curator', (object) ['id' => 6, 'status' => 'active']);
ok(str_contains($cell, 'name="status" value="inactive"'), 'an active row offers "suspend"');
reset_world(); $GLOBALS['stats_flushed'] = 0; $GLOBALS['wpdb']->updates = [];
post(['kind' => 'collector', 'id' => '5', 'status' => 'active']);
$url = redirect_of([\WPArtCOA\MemberFormsAdmin::class, 'set_status']);
ok(($GLOBALS['wpdb']->updates[0][0] ?? '') === 'wp_collectors' && ($GLOBALS['wpdb']->updates[0][1] ?? []) === ['status' => 'active'] && ($GLOBALS['wpdb']->updates[0][2] ?? []) === ['id' => 5], 'approving a collector updates only its status');
ok(in_array('art_coa_person_status_collector_5', $GLOBALS['referer_checked'], true) && str_contains((string) $url, 'wp-art-coa-collectors') && $GLOBALS['stats_flushed'] === 1, 'nonce is checked, public caches are flushed, admin returns to the list');
foreach ([['artist', 'active'], ['collector', 'banned'], ['collector\' OR 1=1 --', 'active']] as [$k, $st]) {
    reset_world(); $GLOBALS['wpdb']->updates = []; post(['kind' => $k, 'id' => '5', 'status' => $st]);
    try { \WPArtCOA\MemberFormsAdmin::set_status(); $threw = false; } catch (RuntimeException $e) { $threw = true; } catch (Redirect $e) { $threw = false; }
    ok($threw && !$GLOBALS['wpdb']->updates, "invalid kind/status is rejected without touching the database ($k / $st)");
}

/* ---------- custom fields from the form builder (2.32.28/29) ---------- */
echo "# Custom fields\n";
reset_world();
$GLOBALS['o'][M::OPTION] = ['custom_fields' => ['buyer' => [
    ['name' => 'budget', 'label' => 'بودجه', 'type' => 'number', 'max' => 12],
    ['name' => 'city_pick', 'label' => 'شهر', 'type' => 'select', 'options' => ['تهران', 'شیراز']],
    ['name' => 'visit_day', 'label' => 'روز', 'type' => 'date'],
    ['name' => 'display_name', 'label' => 'تکراری', 'type' => 'text'],           // collides with a built-in field
    ['name' => 'Bad Name!', 'label' => 'x', 'type' => 'text'],                    // invalid technical name
    ['name' => 'evil', 'label' => 'x', 'type' => '<script>'],                     // unknown type
]]];
M::flush_cache();
$buyer = M::active_roles()['buyer'];
$names = array_column($buyer['fields'], 'name');
ok(in_array('budget', $names, true) && in_array('city_pick', $names, true) && in_array('visit_day', $names, true), 'valid custom fields are added to the role');
ok(count(array_keys($names, 'display_name', true)) === 1 && !in_array('evil', $names, true) && !in_array('bad_name', $names, true), 'colliding names, bad names and unknown types are dropped');
[$c, $e] = M::validate_role($buyer, ['display_name' => 'x', 'budget' => '250000000', 'city_pick' => '1', 'visit_day' => '2026-03-01']);
ok(!$e && $c['budget'] === '250000000', 'a custom number is not capped at the 0-80 limit of "years of experience"');
[, $e] = M::validate_role($buyer, ['display_name' => 'x', 'budget' => '12abc']);
ok(isset($e['budget']), 'a custom number still rejects non-digits');
[, $e] = M::validate_role($buyer, ['display_name' => 'x', 'city_pick' => '99']);
ok(isset($e['city_pick']), 'a select only accepts one of its own option keys');
[, $e] = M::validate_role($buyer, ['display_name' => 'x', 'visit_day' => '2026-02-31']);
ok(isset($e['visit_day']), 'an impossible date is rejected');
[, $e] = M::validate_role(role('curator'), ['display_name' => 'c', 'years_experience' => '81']);
ok(isset($e['years_experience']), 'the built-in years-of-experience field keeps its 0-80 limit');

// form builder JS: option keys must exist for Persian labels, stay stable, and the drag handle must not break text selection
$admin = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-member-forms-admin.php');
ok(strpos($admin, 'row.draggable=true') === false && strpos($admin, "addEventListener('pointerdown'") !== false, 'rows become draggable only while the handle is held (Firefox text selection)');
if (trim((string) shell_exec('command -v node 2>/dev/null')) !== '' && preg_match('/function optKey\(label\)\{.*?\}return[^}]*\}/s', $admin, $m)) {
    $js = "const known={'کهنه':'legacy_key'};" . $m[0] . "const pick=x=>Object.prototype.hasOwnProperty.call(known,x)?known[x]:optKey(x);"
        . "console.log(JSON.stringify(['تهران','شیراز','Tehran','کهنه'].map(pick)), pick('تهران')===optKey('تهران'));";
    $out = trim((string) shell_exec('node -e ' . escapeshellarg($js) . ' 2>&1'));
    [$json, $same] = array_pad(explode(' ', $out, 2), 2, '');
    $keys = json_decode($json, true) ?: [];
    ok(count($keys) === 4 && count(array_unique($keys)) === 4 && !in_array('', $keys, true), 'Persian option labels get distinct non-empty keys');
    ok(($keys[3] ?? '') === 'legacy_key' && trim($same) === 'true', 'existing option keys are kept and new ones are stable');
} else {
    echo "  skip  node not available or optKey not found\n";
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
