<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use WPArtCOA\I18n;
use WPArtCOA\Shop;

defined('ABSPATH') || exit;

final class Widget_Shop extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string
    {
        return 'art_coa_shop';
    }

    public function get_title(): string
    {
        return 'Art COA Shop';
    }

    public function get_icon(): string
    {
        return 'eicon-products';
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
        $this->start_controls_section('content', ['label' => I18n::t('shop')]);
        $this->add_control('show_title', ['label' => 'نمایش عنوان', 'type' => Controls_Manager::SWITCHER, 'return_value' => '1', 'default' => '1']);
        $this->add_control('title', ['label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'default' => 'بازار آثار', 'condition' => ['show_title' => '1']]);
        $this->add_control('show_intro', ['label' => 'نمایش توضیح', 'type' => Controls_Manager::SWITCHER, 'return_value' => '1', 'default' => '1']);
        $this->add_control('presentation', ['label' => 'نحوه ارائه', 'type' => Controls_Manager::SELECT, 'options' => ['single' => 'فروشگاه یکپارچه', 'tabs' => 'تب‌بندی آثار'], 'default' => 'single']);
        $this->add_control('shop_tabs', [
            'label' => 'تب‌های فروشگاه', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true,
            'options' => ['sale' => 'آثار قابل فروش', 'all' => 'همه آثار'], 'default' => ['sale', 'all'],
            'condition' => ['presentation' => 'tabs'],
            'description' => 'هر تب یک مجموعه مستقل از داده‌ها را نمایش می‌دهد. فیلتر هنرمند/گالری انتخاب‌شده در هر دو تب حفظ می‌شود.',
        ]);
        \Artiey_AET_Tabs::register_behavior_controls($this, 'presentation', 'tabs', ['sale' => 'آثار قابل فروش', 'all' => 'همه آثار']);
        $this->end_controls_section();

        $this->start_controls_section('shop_filters_sources', ['label' => 'فیلترها و منبع محصولات', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('show_filter', ['label' => 'نمایش فیلترها', 'type' => Controls_Manager::SWITCHER, 'return_value' => '1', 'default' => '1']);
        $this->entity_picker('pick_artist', 'artist', 'هنرمند (انتخاب از فهرست)', 'همه / از شناسه یا نامک');
        $this->entity_picker('pick_gallery', 'gallery', 'گالری (انتخاب از فهرست)', 'همه');
        $this->add_control('artist', [
            'label' => I18n::t('id') . ' (' . I18n::t('artist') . ')',
            'type'  => Controls_Manager::NUMBER,
            'default' => 0,
        ]);
        $this->add_control('gallery', ['label' => 'شناسه گالری (اختیاری)', 'type' => Controls_Manager::NUMBER, 'default' => 0, 'min' => 0]);
        $this->add_control('only_sale', ['label' => 'فقط آثار قابل فروش', 'type' => Controls_Manager::SWITCHER, 'return_value' => '1', 'default' => '1']);
        $this->add_control('certified', ['label' => 'فقط آثار دارای گواهی اصالت', 'type' => Controls_Manager::SWITCHER, 'return_value' => '1', 'default' => '']);
        $this->add_control('sort', ['label' => 'مرتب‌سازی پیش‌فرض', 'type' => Controls_Manager::SELECT, 'default' => 'newest', 'options' => ['newest' => 'جدیدترین', 'price_low' => 'ارزان‌ترین', 'price_high' => 'گران‌ترین', 'title' => 'عنوان']]);
        $this->add_control('follow_page', ['label' => 'آثار هنرمند/گالری همین صفحه', 'type' => Controls_Manager::SWITCHER, 'return_value' => '1', 'default' => '1', 'description' => 'در قالب تک‌هنرمند یا تک‌گالری، اگر هنرمند/گالری انتخاب نشده باشد، آثار همان صفحه نمایش داده می‌شود.']);
        $this->end_controls_section();

        $this->start_controls_section('shop_layout_controls', ['label' => 'تعداد و چیدمان محصولات', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('slug', [
            'label' => I18n::t('slug'),
            'type'  => Controls_Manager::TEXT,
        ]);
        $this->add_control('limit', [
            'label' => 'حداکثر تعداد آثار',
            'type'  => Controls_Manager::NUMBER,
            'default' => 12,
        ]);
        $this->add_control('columns', [
            'label' => 'تعداد ستون‌ها',
            'type'  => Controls_Manager::SELECT,
            'default' => '3',
            'options' => ['2' => '2', '3' => '3', '4' => '4'],
        ]);
        $this->end_controls_section();

        \Artiey_AET_Tabs::register_style_controls($this, 'shoptabs');
        $this->register_design_controls('.art-coa-shop', '.art-coa-shop-title', '.art-coa-shop-card', '.art-coa-shop-grid');
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $artist = absint(!empty($s['pick_artist']) ? $s['pick_artist'] : ($s['artist'] ?? 0));
        $gallery = absint(!empty($s['pick_gallery']) ? $s['pick_gallery'] : ($s['gallery'] ?? 0));
        $slug = sanitize_title($s['slug'] ?? '');
        $route = class_exists('\\Artiey_AET_Elementor_Layouts') ? \Artiey_AET_Elementor_Layouts::context() : [];
        $route_type = sanitize_key((string) ($route['entity_type'] ?? ''));
        $route_slug = sanitize_title((string) ($route['slug'] ?? ''));
        // Explicit widget selections always win; otherwise inherit the artist/gallery single currently previewed in Elementor.
        if (!$artist && !$gallery && $slug === '' && $route_slug !== '' && $route_type === 'artist') {
            $row = \WPArtCOA\Entities\Artist::get_by_slug($route_slug);
            if ($row) { $artist = (int) $row->id; $slug = $route_slug; }
        } elseif (!$artist && !$gallery && $slug === '' && $route_slug !== '' && $route_type === 'gallery') {
            $row = \WPArtCOA\Entities\Gallery::get_by_slug($route_slug);
            if ($row) { $gallery = (int) $row->id; }
        }
        // WordPress artist / gallery single pages (Theme Builder): map the post to its catalogue row (read-only).
        if (!$artist && !$gallery && $slug === '' && !empty($s['follow_page']) && class_exists('\\Artiey_AET_Elementor_Options') && get_option('artiey_aet_content_bridge_schema_version')) {
            global $wpdb;
            foreach (['artiey_artist' => 'artists', 'artiey_gallery' => 'galleries'] as $post_type => $table) {
                $pid = (int) \Artiey_AET_Elementor_Options::current_post_id($post_type);
                if (!$pid) { continue; }
                $ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . \WPArtCOA\Database::table($table) . ' WHERE cpt_post_id = %d LIMIT 2', $pid)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                if (is_array($ids) && count($ids) === 1) {
                    if ($table === 'artists') { $artist = (int) $ids[0]; } else { $gallery = (int) $ids[0]; }
                }
                break;
            }
        }
        $filter = $artist || $slug !== '' ? 'artist' : 'all';
        $base = [
            'filter' => $filter, 'artist' => $artist, 'slug' => $slug,
            'limit' => max(1, min(100, absint($s['limit'] ?? 12))), 'columns' => max(1, min(4, absint($s['columns'] ?? 3))),
            'gallery' => $gallery, 'show_title' => false, 'title' => '', 'show_intro' => false, 'show_filter' => false,
            'sort' => sanitize_key((string) ($s['sort'] ?? 'newest')), 'certified' => !empty($s['certified']) ? '1' : '0',
        ];
        if (($s['presentation'] ?? 'single') !== 'tabs') {
            echo Shop::render(array_merge($base, [
                'only_sale' => !empty($s['only_sale']), 'show_title' => !empty($s['show_title']),
                'title' => sanitize_text_field((string) ($s['title'] ?? '')), 'show_intro' => !empty($s['show_intro']), 'show_filter' => !empty($s['show_filter']),
            ]));
            return;
        }

        echo '<div class="art-coa-elementor-widget art-coa-shop-widget art-coa-shop-widget--tabs">';
        if (!empty($s['show_title'])) {
            $title = sanitize_text_field((string) ($s['title'] ?? ''));
            echo '<h2 class="art-coa-shop-title">' . esc_html($title !== '' ? $title : I18n::t('shop')) . '</h2>';
        }
        if (!empty($s['show_intro'])) {
            echo '<p class="art-coa-shop-intro">' . esc_html(I18n::t('shop_intro')) . '</p>';
        }
        $allowed = ['sale', 'all'];
        $selected = array_values(array_intersect($allowed, array_map('sanitize_key', (array) ($s['shop_tabs'] ?? ['sale', 'all']))));
        $labels = ['sale' => 'آثار قابل فروش', 'all' => 'همه آثار'];
        $panels = [];
        foreach ($selected as $mode) {
            $panel = Shop::render(array_merge($base, ['only_sale' => $mode === 'sale']));
            if (is_string($panel) && trim($panel) !== '') { $panels[] = ['key' => $mode, 'label' => $labels[$mode], 'html' => $panel]; }
        }
        $tab_settings = $s;
        $tab_settings['force_tabs'] = 'yes';
        $tabs = \Artiey_AET_Tabs::render($panels, $tab_settings, 'نمایش آثار در فروشگاه');
        echo $tabs !== '' ? $tabs : '<p class="art-coa-empty">اثری برای نمایش پیدا نشد.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shop::render escapes values.
        echo '</div>';
    }
}
