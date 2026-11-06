<?php
/** Static wiring checks between modules (shortcodes, loaders, stylesheets, admin handlers, routes). Run: php tests/connections.php */
$root = dirname(__DIR__);
$src = []; $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $f) { if ($f->getExtension() === 'php' && !str_contains($f->getPathname(), '/tests/')) { $src[$f->getPathname()] = file_get_contents($f->getPathname()); } }
$all = implode("\n", $src);
$pass = 0; $fail = 0;
function ok(bool $c, string $n): void { global $pass, $fail; if ($c) { $pass++; echo "  ok    $n\n"; } else { $fail++; echo "  FAIL  $n\n"; } }

preg_match_all("/add_shortcode\(\s*'([a-z0-9_]+)'/", $all, $m); $registered = array_unique($m[1]);
preg_match_all("/'tag' => '([a-z0-9_]+)'/", $src["$root/includes/class-page-builder.php"], $m); $page_tags = $m[1];
ok(!array_diff($page_tags, $registered), 'every shortcode a generated page uses is registered');

$assets = file_get_contents("$root/art-coa/includes/admin/class-assets.php");
preg_match("/DYNAMIC_SHORTCODES = \[(.*?)\];/s", $assets, $d); preg_match("/LIGHT_SHORTCODES = \[(.*?)\];/s", $assets, $l);
preg_match_all("/'([a-z0-9_]+)'/", ($d[1] ?? '') . ($l[1] ?? ''), $m); $loaded = $m[1];
$self_styled = ['art_coa_charts', 'artiey_stats', 'artiey_timeline']; // platform shortcodes enqueue their own CSS
$missing = array_filter($page_tags, fn($t) => str_starts_with($t, 'art_coa_') && !in_array($t, $loaded, true) && !in_array($t, $self_styled, true));
ok(!$missing, 'every art_coa_* shortcode on a generated page gets its stylesheet loaded' . ($missing ? ' (missing: ' . implode(',', $missing) . ')' : ''));

$css = file_get_contents("$root/art-coa/assets/css/frontend-dynamic.css");
ok(str_contains($css, '.art-coa-call-card{'), 'call cards have styles');

preg_match("/DYNAMIC_SHORTCODES = array\((.*?)\);/s", $src["$root/includes/class-cache-control.php"], $c); preg_match_all("/'([a-z0-9_]+)'/", $c[1], $m);
ok(!array_diff($m[1], $registered), 'every page-cache-excluded shortcode is a registered shortcode');

// admin_post handlers: every hooked action has a form that posts it
preg_match_all("/add_action\(\s*'admin_post_([a-z0-9_]+)'/", $all, $m); $hooks = array_unique($m[1]);
$unposted = [];
foreach ($hooks as $h) {
    $posted = preg_match('/name="action"\s+value="' . preg_quote($h, '/') . '"/', $all) || preg_match("/'action'\s*=>\s*'" . preg_quote($h, '/') . "'/", $all) || str_contains($all, "self::ACTION") && in_array($h, ['artiey_aet_pages', 'artiey_aet_clear_cache'], true)
        || str_contains($all, "esc_attr( self::SAVE )") && $h === 'artiey_aet_appearance_save' || str_contains($all, "esc_attr( self::RESET )") && $h === 'artiey_aet_appearance_reset';
    if (!$posted) { $unposted[] = $h; }
}
ok(count($hooks) > 5, 'admin_post handlers were found (' . count($hooks) . '), so the form check below is not vacuous');
echo '        (handlers without an obvious form: ' . ($unposted ? implode(', ', $unposted) : 'none') . ")\n";
ok(!array_intersect(['art_coa_member_form_save', 'art_coa_set_person_status', 'artiey_aet_clear_cache', 'artiey_aet_pages', 'artiey_aet_appearance_save', 'artiey_aet_appearance_reset'], $unposted), 'every new admin handler is reachable from a form');

// the stylesheet handle used by the registration form is registered
ok(str_contains($assets, "wp_register_style('wp-art-coa-member-forms'"), 'member form stylesheet handle is registered');
ok(str_contains(file_get_contents("$root/art-coa/includes/class-ui.php"), "'wp-art-coa-member-forms'"), 'member form stylesheet pulls in the shared design tokens');

$member_admin = file_get_contents("$root/art-coa/includes/class-member-forms-admin.php");
$registration = file_get_contents("$root/art-coa/includes/class-registration.php");
$member_css = file_get_contents("$root/art-coa/assets/css/member-forms.css");
ok(str_contains($member_admin, 'data-acmf-builder') && str_contains($member_admin, 'acmf-add') && str_contains($member_admin, 'acmf-remove'), 'registration custom fields have a visual add/remove builder');
ok(str_contains($member_admin, 'acmf-up') && str_contains($member_admin, 'acmf-down') && str_contains($member_admin, 'acmf-json'), 'visual field builder supports ordering and retains the JSON compatibility path');
ok(str_contains($registration, 'acmd-overview') && str_contains($registration, 'acmd-workspace') && str_contains($registration, 'acmd-profile') && str_contains($registration, 'acmd-following'), 'member dashboard exposes role workspace, profile and follows navigation');
ok(str_contains($member_css, '.acmd-nav') && str_contains($member_css, '@media(max-width:600px)'), 'member dashboard navigation has responsive front-end styles');

// every /art-coa/<segment>/ link has a rewrite rule
preg_match_all("#home_url\(\s*'/art-coa/([a-z0-9_-]+)#", $all, $m); $used = array_unique($m[1]);
preg_match_all('#\^art-coa/\(?(?:\?:)?([a-z0-9_|-]+)#', $all, $m); $ruled = [];
foreach ($m[1] as $x) { foreach (explode('|', $x) as $p) { $ruled[$p] = true; } }
$orphans = array_filter($used, fn($u) => !isset($ruled[$u]));
ok(!$orphans, 'every /art-coa/ link has a rewrite rule' . ($orphans ? ' (missing: ' . implode(',', $orphans) . ')' : ''));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
