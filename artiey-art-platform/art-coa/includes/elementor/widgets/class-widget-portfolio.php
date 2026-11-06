<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use WPArtCOA\I18n;
use WPArtCOA\Portfolio;

defined('ABSPATH') || exit;

final class Widget_Portfolio extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string
    {
        return 'art_coa_portfolio';
    }

    public function get_title(): string
    {
        return 'Art COA Portfolio';
    }

    public function get_icon(): string
    {
        return 'eicon-gallery-grid';
    }

    public function get_categories(): array
    {
        return ['art-coa', 'artiey-art'];
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
            'label' => I18n::t('portfolio'),
        ]);

        $this->add_control('show_title', ['label' => 'نمایش عنوان', 'type' => Controls_Manager::SWITCHER, 'return_value' => '1', 'default' => '1']);
        $this->add_control('presentation', ['label' => 'نحوه نمایش پورتفولیو', 'type' => Controls_Manager::SELECT, 'options' => ['grid' => 'شبکه معمولی', 'tabs' => 'تب‌بندی مجموعه آثار'], 'default' => 'grid']);
        $this->add_control('portfolio_tabs', ['label' => 'تب‌های مجموعه آثار', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => ['all' => 'همه آثار', 'certified' => 'دارای گواهی اصالت فعال'], 'default' => ['all', 'certified'], 'condition' => ['presentation' => 'tabs'], 'description' => 'هر نوع اثر در تب مستقل نمایش داده می‌شود؛ تب گواهی فقط آثاری را می‌آورد که گواهی فعال دارند.']);
        \Artiey_AET_Tabs::register_behavior_controls($this, 'presentation', 'tabs', ['all' => 'همه آثار', 'certified' => 'دارای گواهی اصالت فعال']);
        $this->add_control('title', ['label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'default' => 'نمونه آثار', 'condition' => ['show_title' => '1']]);
        $this->end_controls_section();

        $this->start_controls_section('portfolio_sources', ['label' => 'منبع و فیلتر آثار', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->entity_picker('pick_artist', 'artist', 'هنرمند (انتخاب از فهرست)', 'همه / از شناسه یا نامک');
        $this->entity_picker('pick_gallery', 'gallery', 'گالری (انتخاب از فهرست)', 'همه / از شناسه یا نامک');

        $this->add_control('filter', [
            'label'   => I18n::t('type') . ' (پیشرفته)',
            'type'    => Controls_Manager::SELECT,
            'default' => 'all',
            'options' => [
                'all'     => I18n::t('all'),
                'artist'  => I18n::t('artist'),
                'gallery' => I18n::t('gallery'),
            ],
        ]);

        $this->add_control('id', [
            'label'   => I18n::t('id'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 0,
        ]);

        $this->add_control('slug', [
            'label'   => I18n::t('slug'),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('portfolio_layout_controls', ['label' => 'تعداد و چیدمان کارت‌ها', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('limit', [
            'label'   => 'حداکثر تعداد آثار',
            'type'    => Controls_Manager::NUMBER,
            'default' => 12,
            'min'     => 1,
            'max'     => 48,
        ]);

        $this->add_control('columns', [
            'label'   => 'تعداد ستون‌ها',
            'type'    => Controls_Manager::SELECT,
            'default' => '3',
            'options' => ['2' => '2', '3' => '3', '4' => '4'],
        ]);

        $this->add_control('show_filter', [
            'label'        => I18n::t('filter'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => '1',
            'default'      => '1',
        ]);

        $this->end_controls_section();

        \Artiey_AET_Tabs::register_style_controls($this, 'portfoliotabs');
        $this->register_design_controls('.art-coa-portfolio', '.art-coa-portfolio-title', '.art-coa-portfolio-card', '.art-coa-portfolio-grid');
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $filter = $s['filter'] ?? 'all';
        $id     = absint($s['id'] ?? 0);
        $route = class_exists('\\Artiey_AET_Elementor_Layouts') ? \Artiey_AET_Elementor_Layouts::context() : [];
        $route_type = (string) ($route['entity_type'] ?? '');
        $route_slug = sanitize_title((string) ($route['slug'] ?? ''));
        if (!empty($s['pick_artist'])) {
            $filter = 'artist'; $id = absint($s['pick_artist']);
        } elseif (!empty($s['pick_gallery'])) {
            $filter = 'gallery'; $id = absint($s['pick_gallery']);
        } elseif ($route_slug !== '' && in_array($route_type, ['artist', 'gallery'], true) && empty($s['slug']) && !$id) {
            $filter = $route_type;
            $s['slug'] = $route_slug;
        }
        $base = [
            'filter'      => $filter,
            'id'          => $id,
            'slug'        => sanitize_title($s['slug'] ?? ''),
            'limit'       => max(1, min(48, absint($s['limit'] ?? 12))),
            'columns'     => max(2, min(4, absint($s['columns'] ?? 3))),
            'show_filter' => !empty($s['show_filter']),
            'show_title'  => !empty($s['show_title']),
            'title'       => sanitize_text_field((string) ($s['title'] ?? '')),
        ];
        if (($s['presentation'] ?? 'grid') !== 'tabs') {
            echo Portfolio::render($base); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes data.
            return;
        }

        $allowed = ['all', 'certified'];
        $selected = array_values(array_intersect($allowed, array_map('sanitize_key', (array) ($s['portfolio_tabs'] ?? $allowed))));
        if (!$selected) { $selected = ['all']; }
        $labels = ['all' => 'همه آثار', 'certified' => 'دارای گواهی اصالت فعال'];
        $panels = [];
        foreach ($selected as $view) {
            $panel_args = array_merge($base, [
                'show_filter' => false,
                'show_title' => false,
                'ignore_request_filters' => true,
                'certified_only' => $view === 'certified',
            ]);
            $html = Portfolio::render($panel_args);
            if ($html !== '') { $panels[] = ['key' => $view, 'label' => $labels[$view], 'html' => $html]; }
        }
        echo '<div class="art-coa-elementor-widget art-coa-portfolio-widget">';
        if (!empty($s['show_title'])) {
            $title = sanitize_text_field((string) ($s['title'] ?? ''));
            echo '<h2 class="art-coa-elementor-title">' . esc_html($title !== '' ? $title : I18n::t('portfolio')) . '</h2>';
        }
        $tab_settings = $s; $tab_settings['force_tabs'] = 'yes';
        $tabs_html = \Artiey_AET_Tabs::render($panels, $tab_settings, 'تب‌های نمونه آثار');
        echo $tabs_html !== '' ? $tabs_html : '<p class="art-coa-empty">اثری برای نمایش در این مجموعه ثبت نشده است.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes portfolio data.
        echo '</div>';
    }
}
