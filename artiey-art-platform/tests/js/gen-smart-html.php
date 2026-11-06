<?php
// usage: php gen-smart-html.php '{"n":6,"mode":"coverflow","toggle":true}'
define('ABSPATH', '/'); define('AET_URL', '/'); define('AET_VERSION', '1'); define('HOUR_IN_SECONDS', 3600);
function add_action(...$a) {} function add_shortcode(...$a) {}
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); } function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); } function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url_raw($u) { return preg_match('#^https?://#i', $u) ? $u : ''; } function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); } function wp_parse_url($u) { return parse_url($u); }
require dirname(__DIR__, 2) . '/includes/class-media-gallery.php';
require dirname(__DIR__, 2) . '/includes/class-smart-scroller.php';
$o = json_decode($argv[1] ?? '{}', true) ?: []; $n = (int) ($o['n'] ?? 6); unset($o['n']);
$slides = [];
for ($i = 1; $i <= $n; $i++) {
    $slides[] = ['type' => 'post', 'id' => $i, 'title' => "اثر $i", 'url' => "https://example.test/?p=$i", 'badge' => 'اثر', 'meta' => 'هنرمند', 'excerpt' => '',
        'image' => ['src' => "https://example.test/u/$i.jpg", 'srcset' => '', 'width' => 800, 'height' => 600, 'alt' => "اثر $i"], 'html' => ''];
}
echo Artiey_AET_Smart_Scroller::render($slides, $o);
