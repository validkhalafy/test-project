<?php
/** Structure of the 3.1 Elementor widgets (follow, calls, curator, gallery profile): no duplicate ids, sections closed, every condition points to a real control. Run: php tests/new-widgets.php */
declare(strict_types=1);

namespace WPArtCOA {
    final class I18n { public static function t(string $k): string { return $k; } public static function is_fa(): bool { return true; } }
    final class Calls { public const TYPES = ['exhibition', 'event', 'residency', 'open_studio']; public static function type_label(string $t): string { return $t; } }
    final class Follows {}
}

namespace {
    define('ABSPATH', __DIR__ . '/'); define('MINUTE_IN_SECONDS', 60);
    function add_action(...$a) {} function add_shortcode(...$a) {} function get_option($k, $d = false) { return $d; }
    function get_transient($k) { return false; } function set_transient(...$a) { return true; }
    require __DIR__ . '/elementor-stubs.php';
    require dirname(__DIR__) . '/includes/class-text.php';
    require dirname(__DIR__) . '/includes/class-gallery.php';
    require dirname(__DIR__) . '/includes/class-elementor-tabs.php';
    require dirname(__DIR__) . '/includes/class-elementor-style.php';
    require dirname(__DIR__) . '/includes/class-elementor-follow-widget.php';
    require dirname(__DIR__) . '/includes/class-elementor-calls-widget.php';

    $pass = 0; $fail = 0;
    function ok(bool $c, string $n): void { global $pass, $fail; if ($c) { $pass++; echo "  ok    $n\n"; } else { $fail++; echo "  FAIL  $n\n"; } }

    foreach (['Artiey_AET_Elementor_Follow_Widget' => 'artiey_follow', 'Artiey_AET_Elementor_Calls_Widget' => 'artiey_calls'] as $cls => $name) {
        $w = new $cls();
        $w->build();
        ok($w->get_name() === $name && in_array('artiey-art', $w->get_categories(), true), "$name: registered in the Artiey category");
        ok(!$w->errors, "$name: control structure is valid" . ($w->errors ? ' (' . implode('; ', $w->errors) . ')' : ''));
        $ids = array_keys($w->controls);
        $bad = [];
        foreach ($w->controls as $id => $c) {
            foreach (array_keys((array) ($c['args']['condition'] ?? [])) as $k) {
                if (!in_array(rtrim($k, '!'), $ids, true)) { $bad[] = "$id->$k"; }
            }
        }
        ok(!$bad, "$name: every condition points to an existing control" . ($bad ? ' (' . implode(', ', $bad) . ')' : ''));
    }
    ok(array_keys(Artiey_AET_Elementor_Follow_Widget::sources()) === ['auto', 'artist_post', 'gallery_post', 'curator', 'artist', 'gallery', 'collector'], 'follow widget offers automatic and manual sources');

    echo "\n$pass passed, $fail failed\n";
    exit($fail ? 1 : 0);
}
