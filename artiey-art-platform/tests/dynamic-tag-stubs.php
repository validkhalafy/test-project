<?php
declare(strict_types=1);

namespace Elementor\Core\DynamicTags {
    abstract class Tag {
        protected array $test_settings = [];
        protected array $test_controls = [];
        public function __construct(array $data = []) { $this->test_settings = (array) ($data['settings'] ?? []); }
        public function get_settings($key = null) { return null === $key ? $this->test_settings : ($this->test_settings[$key] ?? ''); }
        public function set_test_settings(array $settings): void { $this->test_settings = $settings; }
        public function add_control($id, array $args): void { $this->test_controls[$id] = $args; }
        public function get_test_controls(): array { $this->register_controls(); return $this->test_controls; }
        abstract public function get_name(): string;
        abstract public function get_title(): string;
        abstract public function get_group(): array;
        abstract public function get_categories(): array;
        abstract public function render(): void;
        abstract protected function register_controls(): void;
    }
}

namespace Elementor\Modules\DynamicTags {
    class Module { public const TEXT_CATEGORY = 'text'; }
}

namespace {
    if (!function_exists('shortcode_exists')) {
        function shortcode_exists($tag): bool { return isset($GLOBALS['shortcode_tags'][(string) $tag]); }
    }
    if (!function_exists('shortcode_parse_atts')) {
        function shortcode_parse_atts($text): array {
            $out = [];
            preg_match_all('/([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s]+))/', (string) $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) { $out[$m[1]] = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : ($m[4] ?? '')); }
            return $out;
        }
    }
    if (!function_exists('do_shortcode')) {
        function do_shortcode($text): string { $GLOBALS['__last_dynamic_shortcode'] = (string) $text; return '<strong>dynamic output</strong>'; }
    }
    if (!function_exists('get_the_ID')) { function get_the_ID(): int { return 42; } }
    if (!function_exists('get_queried_object_id')) { function get_queried_object_id(): int { return (int) ($GLOBALS['__dynamic_queried_object_id'] ?? 0); } }
    if (!function_exists('get_post_type')) { function get_post_type($id = null): string { return 42 === (int) $id ? 'artiey_artist' : ''; } }
    if (!function_exists('get_bloginfo')) { function get_bloginfo($show = ''): string { return 'UTF-8'; } }

    final class Test_Dynamic_Tag_Manager {
        public array $groups = [];
        public array $tags = [];
        public function register_group($id, array $settings): void { $this->groups[$id] = $settings; }
        public function register($tag): void { $this->tags[$tag->get_name()] = $tag; }
    }
}
