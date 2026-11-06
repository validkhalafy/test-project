<?php
// Minimal Elementor + COA stand-ins so the widgets can be instantiated and rendered without WordPress.
namespace WPArtCOA { final class Database { public static function table(string $n): string { return 'wp_art_' . $n; } } }

namespace Elementor {
    class Controls_Manager {
        const TAB_CONTENT = 'content'; const TAB_STYLE = 'style'; const SELECT = 'select'; const TEXT = 'text'; const TEXTAREA = 'textarea';
        const NUMBER = 'number'; const SWITCHER = 'switcher'; const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater';
        const SELECT2 = 'select2'; const SLIDER = 'slider'; const COLOR = 'color'; const DIMENSIONS = 'dimensions';
    }
    class Group_Control_Typography { public static function get_type() { return 'typography'; } }
    class Group_Control_Border { public static function get_type() { return 'border'; } }
    class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
    class Repeater { public array $c = []; public function add_control($id, $a) { $this->c[$id] = $a; } public function get_controls() { return $this->c; } }
    class Editor { public bool $edit = false; public function is_edit_mode() { return $this->edit; } }
    class Plugin { public static $instance; public $editor; }
    Plugin::$instance = new Plugin(); Plugin::$instance->editor = new Editor();
    abstract class Widget_Base {
        public array $controls = []; public array $group_selectors = []; public array $settings = []; public array $sections = [];
        public function start_controls_section($id, $a = []) { if (isset($this->sections[$id])) { throw new \Exception("duplicate section $id"); } $this->sections[$id] = $a; }
        public function end_controls_section() {}
        public function add_control($id, $a = []) { if (isset($this->controls[$id])) { throw new \Exception("duplicate control $id"); } $this->controls[$id] = $a; }
        public function add_responsive_control($id, $a = []) { $this->add_control($id, $a); }
        public function add_group_control($type, $a = []) { $this->group_selectors[] = $a['selector'] ?? ''; $this->add_control(($a['name'] ?? 'x') . '__group', $a); }
        public function get_settings_for_display($k = null) { return $k === null ? $this->settings : ($this->settings[$k] ?? null); }
        public function get_id() { return 'abc123'; }
        public function boot() { $m = new \ReflectionMethod($this, 'register_controls'); $m->setAccessible(true); $m->invoke($this); return $this; }
        public function html() { $m = new \ReflectionMethod($this, 'render'); $m->setAccessible(true); ob_start(); $m->invoke($this); return (string) ob_get_clean(); }
    }
}
