<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use WPArtCOA\Hierarchy;
use WPArtCOA\I18n;

defined('ABSPATH') || exit;

final class Widget_Hierarchy extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string
    {
        return 'art_coa_hierarchy';
    }

    public function get_title(): string
    {
        return 'Art COA Hierarchy';
    }

    public function get_icon(): string
    {
        return 'eicon-sitemap';
    }

    public function get_categories(): array
    {
        return ['art-coa'];
    }

    public function get_style_depends(): array
    {
        return ['wp-art-coa-front', 'wp-art-coa-dynamic', 'artiey-aet-profile'];
    }

    public function get_script_depends(): array
    {
        return ['artiey-aet-profile'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', [
            'label' => I18n::t('stats_ecosystem'),
        ]);

        $this->add_control('show_title', ['label' => 'نمایش عنوان', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        $this->add_control('title', ['label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'default' => 'ارتباطات و سوابق', 'condition' => ['show_title' => 'yes']]);

        $this->end_controls_section();

        $this->start_controls_section('hierarchy_relation_type', ['label' => 'نوع ارتباط و نمایش', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('entity_type', [
            'label'   => I18n::t('type'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'gallery',
            'options' => [
                'gallery'   => I18n::t('gallery'),
                'artist'    => I18n::t('artist'),
                'collector' => I18n::t('collector'),
                'buyer'     => I18n::t('buyer'),
            ],
        ]);

        $this->add_control('display_mode', ['label' => 'نمایش سلسله‌مراتب هنرمند', 'type' => Controls_Manager::SELECT, 'options' => ['tree' => 'درختی (سلسله‌مراتب)', 'tabs' => 'تب‌دار (گالری‌ها، آثار، نمایشگاه‌ها و مالکیت)'], 'default' => 'tree', 'condition' => ['entity_type' => 'artist']]);
        \Artiey_AET_Tabs::register_behavior_controls($this, 'display_mode', 'tabs', ['galleries' => 'گالری‌های مرتبط', 'artworks' => 'نمونه آثار', 'exhibitions' => 'نمایشگاه‌ها', 'provenance' => 'گواهی و مالکیت'], ['entity_type' => 'artist']);

        $this->entity_picker('pick_artist', 'artist', 'هنرمند (انتخاب از فهرست)', 'از شناسه یا نامک', ['entity_type' => 'artist']);
        $this->entity_picker('pick_gallery', 'gallery', 'گالری (انتخاب از فهرست)', 'از شناسه یا نامک', ['entity_type' => 'gallery']);
        $this->end_controls_section();

        $this->start_controls_section('hierarchy_source', ['label' => 'انتخاب رکورد', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('entity_id', [
            'label'       => I18n::t('id'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 0,
            'description' => 'Entity ID (gallery / artist / collector / buyer)',
        ]);

        $this->add_control('entity_slug', [
            'label'       => I18n::t('slug'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'description' => 'برای گالری، هنرمند یا مجموعه‌دار؛ خریدار فقط با شناسه انتخاب می‌شود.',
        ]);

        $this->end_controls_section();

        \Artiey_AET_Tabs::register_style_controls($this, 'hierarchytabs');
        $this->register_design_controls('.art-coa-hierarchy', '.art-coa-elementor-title', '.art-coa-hierarchy li', '');
    }

    protected function render(): void
    {
        $is_admin = current_user_can('manage_options');
        $s    = $this->get_settings_for_display();
        $type = sanitize_key((string) ($s['entity_type'] ?? 'gallery'));
        $route = class_exists('\\Artiey_AET_Elementor_Layouts') ? \Artiey_AET_Elementor_Layouts::context() : [];
        $route_type = sanitize_key((string) ($route['entity_type'] ?? ''));
        if ($route_type !== '' && empty($s['entity_id']) && empty($s['entity_slug']) && empty($s['pick_artist']) && empty($s['pick_gallery'])
            && in_array($route_type, ['artist', 'gallery', 'collector'], true)) {
            $type = $route_type;
            $s['entity_slug'] = sanitize_title((string) ($route['slug'] ?? ''));
        }
        if (!in_array($type, ['gallery', 'artist', 'collector', 'buyer'], true)) { $type = 'gallery'; }
        // Visitors get a privacy-safe gallery/artist hierarchy; collector and buyer trees are ownership data (admins only).
        if (!$is_admin && !in_array($type, ['gallery', 'artist'], true)) {
            if (class_exists('\\Elementor\\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p class="art-coa-empty">برای محافظت از اطلاعات مالکیت، درخت مجموعه‌دار و خریدار فقط برای مدیران سایت نمایش داده می‌شود.</p>';
            }
            return;
        }
        $id   = absint($s['entity_id'] ?? 0);
        if ($type === 'artist' && !empty($s['pick_artist'])) {
            $id = absint($s['pick_artist']);
        } elseif ($type === 'gallery' && !empty($s['pick_gallery'])) {
            $id = absint($s['pick_gallery']);
        }
        $slug = sanitize_title($s['entity_slug'] ?? '');

        if (!$id && $slug) {
            $id = self::resolve_id($type, $slug);
        }

        if (!$id) {
            echo '<p class="art-coa-empty">' . esc_html(I18n::t('no_items')) . '</p>';
            return;
        }

        $tree = match ($type) {
            'artist'    => Hierarchy::artist_tree($id),
            'collector' => Hierarchy::collector_tree($id),
            'buyer'     => Hierarchy::buyer_tree($id),
            default     => Hierarchy::gallery_tree($id),
        };

        if (!$is_admin) { $tree = Hierarchy::public_tree((array) $tree, $type); }
        $tabbed_artist = ($type === 'artist' && ($s['display_mode'] ?? 'tree') === 'tabs');
        $content_html = $tabbed_artist ? Hierarchy::render_artist_tabs($tree, $s) : Hierarchy::render_html($tree, $type);
        if (!empty($s['show_title'])) {
            echo '<div class="art-coa-elementor-widget"><h2 class="art-coa-elementor-title">' . esc_html((string) ($s['title'] ?? 'ارتباطات و سوابق')) . '</h2>' . $content_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hierarchy renderer escapes dynamic values.
        } else {
            echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hierarchy renderer escapes dynamic values.
        }
    }

    private static function resolve_id(string $type, string $slug): int
    {
        global $wpdb;
        $table = match ($type) {
            'artist'    => 'artists',
            'collector' => 'collectors',
            'gallery'   => 'galleries',
            default     => '',
        };
        if ($table === '') {
            return 0;
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . \WPArtCOA\Database::table($table) . ' WHERE slug = %s LIMIT 1',
            $slug
        ));
    }
}
