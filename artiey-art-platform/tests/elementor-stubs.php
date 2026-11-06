<?php
declare(strict_types=1);

namespace Elementor;

class Controls_Manager
{
    public const TAB_CONTENT = 'content', TAB_STYLE = 'style', TEXT = 'text', NUMBER = 'number', SELECT = 'select',
        SELECT2 = 'select2', SWITCHER = 'switcher', COLOR = 'color', SLIDER = 'slider', DIMENSIONS = 'dimensions',
        URL = 'url', RAW_HTML = 'raw_html', TEXTAREA = 'textarea', REPEATER = 'repeater', ICONS = 'icons';
}

class Repeater
{
    private array $controls = [];
    public function add_control($id, $args = []): void { $this->controls[$id] = $args; }
    public function get_controls(): array { return $this->controls; }
}
class Icons_Manager { public static function render_icon($icon, $attributes = []): string { $value = is_array($icon) ? (string) ($icon['value'] ?? '') : ''; return $value !== '' ? '<i class="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>' : ''; } }
class Group_Control_Typography { public static function get_type(): string { return 'typography'; } }
class Group_Control_Border { public static function get_type(): string { return 'border'; } }
class Group_Control_Box_Shadow { public static function get_type(): string { return 'box-shadow'; } }

/** Records what a widget registers so the structure can be checked without Elementor. */
abstract class Widget_Base
{
    public array $controls = [];
    public array $errors = [];
    public array $settings = [];
    private int $open = 0;
    private string $section = '';

    public function start_controls_section($id, $args = []): void
    {
        if ($this->open) { $this->errors[] = "section $id opened inside {$this->section}"; }
        $this->open++; $this->section = (string) $id;
        $this->controls[$id] = ['type' => 'section', 'args' => $args];
    }
    public function end_controls_section(): void
    {
        if (!$this->open) { $this->errors[] = 'end_controls_section without start'; }
        $this->open--;
    }
    private function record($id, $args, string $kind): void
    {
        if (!$this->open) { $this->errors[] = "control $id outside a section"; }
        if (isset($this->controls[$id])) { $this->errors[] = "duplicate control id $id"; }
        $this->controls[$id] = ['type' => $args['type'] ?? $kind, 'args' => $args, 'section' => $this->section];
    }
    public function add_control($id, $args = []): void { $this->record($id, $args, 'control'); }
    public function add_responsive_control($id, $args = []): void { $this->record($id, $args, 'responsive'); }
    public function add_group_control($type, $args = []): void { $this->record($args['name'] ?? $type, $args, 'group'); }

    public function build(): void
    {
        $this->register_controls();
        if ($this->open) { $this->errors[] = 'unclosed section'; }
    }
    abstract protected function register_controls();
}
