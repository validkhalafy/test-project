<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use WPArtCOA\ArtworkHistoryTimeline;

defined('ABSPATH') || exit;

final class Artwork_History_Widget extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string { return 'art_coa_artwork_history'; }
    public function get_title(): string { return 'تایم‌لاین حراج و تاریخچه اثر'; }
    public function get_icon(): string { return 'eicon-history'; }
    public function get_categories(): array { return ['art-coa']; }
    public function get_style_depends(): array { return ['wp-art-coa-front', 'wp-art-coa-dynamic', 'artiey-aet-profile']; }
    public function get_script_depends(): array { return ['artiey-aet-profile']; }
    public function get_keywords(): array { return ['auction history', 'artwork provenance', 'ownership', 'sales', 'حراج', 'تاریخچه اثر', 'خرید و فروش', 'مالکیت']; }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => 'داده‌های تایم‌لاین']);
        $this->add_control('presentation', ['label' => 'نحوه نمایش', 'type' => Controls_Manager::SELECT, 'options' => ['single' => 'تایم‌لاین یکپارچه', 'tabs' => 'تفکیک سابقه‌ها در تب'], 'default' => 'single']);
        $this->add_control('history_tabs', ['label' => 'نوع سابقه در تب‌ها', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => ['auction' => 'حراج‌ها', 'sales' => 'فروش‌های ثبت‌شده', 'ownership' => 'انتقال مالکیت', 'market' => 'بازار ثانویه'], 'default' => ['auction', 'sales', 'ownership', 'market'], 'condition' => ['presentation' => 'tabs']]);
        \Artiey_AET_Tabs::register_behavior_controls($this, 'presentation', 'tabs', ['auction' => 'حراج‌ها', 'sales' => 'فروش‌های ثبت‌شده', 'ownership' => 'انتقال مالکیت', 'market' => 'بازار ثانویه']);
        $this->add_control('title', ['label' => 'عنوان بخش', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'default' => 'تاریخچه بازار و مالکیت اثر']);
        $this->add_control('source', [
            'label' => 'نوع سابقه', 'type' => Controls_Manager::SELECT,
            'options' => [
                'all' => 'همه رویدادها',
                'auction' => 'فقط حراج‌ها',
                'sales' => 'فقط فروش‌های ثبت‌شده',
                'ownership' => 'تغییرات مالکیت',
                'market' => 'بازار ثانویه و آگهی‌ها',
            ],
            'default' => 'all',
            'condition' => ['presentation' => 'single'],
        ]);
        $this->entity_picker('artwork_id', 'artwork', 'انتخاب یک اثر (اختیاری)', 'استفاده از اثر صفحه جاری یا همه آثار');
        $this->end_controls_section();

        $this->start_controls_section('history_display', ['label' => 'نحوه نمایش رویدادها', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('current_artwork', ['label' => 'تشخیص خودکار اثر در صفحهٔ پروفایل اثر', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        $this->add_control('order', ['label' => 'ترتیب', 'type' => Controls_Manager::SELECT, 'options' => ['desc' => 'جدیدترین ابتدا', 'asc' => 'قدیمی‌ترین ابتدا'], 'default' => 'desc']);
        $this->add_control('limit', ['label' => 'تعداد رویدادها', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 50, 'step' => 1, 'default' => 12]);
        $this->add_control('layout', [
            'label' => 'چیدمان', 'type' => Controls_Manager::SELECT,
            'options' => ['line' => 'خط زمانی عمودی', 'cards' => 'کارت‌های جداگانه'], 'default' => 'line',
        ]);
        $this->add_control('show_image', ['label' => 'نمایش تصویر اثر', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        $this->add_control('show_price', ['label' => 'نمایش قیمت‌های ثبت‌شده', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        $this->add_control('show_owner', ['label' => 'نمایش نام مالک در سوابق انتقال', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '']);
        $this->end_controls_section();

        $this->start_controls_section('history_style', ['label' => 'شخصی‌سازی ظاهر تاریخچه', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('history_accent', ['label' => 'رنگ تأکیدی', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-artwork-history' => '--art-coa-ah-accent: {{VALUE}};']]);
        $this->add_control('history_card_background', ['label' => 'پس‌زمینه کارت‌ها', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-ah-event' => 'background-color: {{VALUE}};']]);
        $this->add_control('history_line_color', ['label' => 'رنگ خط زمانی', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-ah-list.is-line' => 'border-color: {{VALUE}};']]);
        $this->add_control('history_meta_color', ['label' => 'رنگ اطلاعات فرعی', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-ah-date, {{WRAPPER}} .art-coa-ah-artist, {{WRAPPER}} .art-coa-ah-detail' => 'color: {{VALUE}};']]);
        $this->end_controls_section();

        $T = \Elementor\Group_Control_Typography::get_type();
        $W = '{{WRAPPER}} .art-coa-artwork-history';
        $this->start_controls_section('history_text', ['label' => 'متن‌ها و برچسب‌ها', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('ah_heading_color', ['label' => 'رنگ عنوان بخش', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-elementor-title' => 'color: {{VALUE}};']]);
        $this->add_group_control($T, ['name' => 'ah_heading_typo', 'label' => 'تایپوگرافی عنوان بخش', 'selector' => '{{WRAPPER}} .art-coa-elementor-title']);
        $this->add_group_control($T, ['name' => 'ah_title_typo', 'label' => 'تایپوگرافی عنوان رویداد', 'selector' => $W . ' .art-coa-ah-title']);
        $this->add_control('ah_title_color', ['label' => 'رنگ عنوان رویداد', 'type' => Controls_Manager::COLOR, 'selectors' => [$W . ' .art-coa-ah-title, ' . $W . ' .art-coa-ah-title a' => 'color: {{VALUE}};']]);
        $this->add_control('ah_title_hover', ['label' => 'رنگ عنوان هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => [$W . ' .art-coa-ah-title a:hover' => 'color: {{VALUE}};']]);
        $this->add_group_control($T, ['name' => 'ah_detail_typo', 'label' => 'تایپوگرافی توضیحات و هنرمند', 'selector' => $W . ' .art-coa-ah-detail, ' . $W . ' .art-coa-ah-artist, ' . $W . ' .art-coa-ah-owner']);
        $this->add_group_control($T, ['name' => 'ah_date_typo', 'label' => 'تایپوگرافی تاریخ', 'selector' => $W . ' .art-coa-ah-date']);
        $this->add_group_control($T, ['name' => 'ah_type_typo', 'label' => 'تایپوگرافی برچسب نوع رویداد', 'selector' => $W . ' .art-coa-ah-type']);
        $this->add_control('ah_type_color', ['label' => 'رنگ متن برچسب نوع', 'type' => Controls_Manager::COLOR, 'selectors' => [$W . ' .art-coa-ah-type' => 'color: {{VALUE}};']]);
        $this->add_control('ah_type_bg', ['label' => 'پس‌زمینهٔ برچسب نوع', 'type' => Controls_Manager::COLOR, 'selectors' => [$W . ' .art-coa-ah-type' => 'background-color: {{VALUE}};']]);
        $this->add_group_control($T, ['name' => 'ah_price_typo', 'label' => 'تایپوگرافی مبلغ', 'selector' => $W . ' .art-coa-ah-price strong']);
        $this->add_control('ah_price_color', ['label' => 'رنگ مبلغ', 'type' => Controls_Manager::COLOR, 'selectors' => [$W . ' .art-coa-ah-price strong' => 'color: {{VALUE}};']]);
        $this->add_control('ah_link_color', ['label' => 'رنگ پیوند «مشاهده»', 'type' => Controls_Manager::COLOR, 'selectors' => [$W . ' .art-coa-ah-link' => 'color: {{VALUE}};']]);
        $this->add_control('ah_marker_color', ['label' => 'رنگ نقطه‌های خط زمانی', 'type' => Controls_Manager::COLOR, 'selectors' => [$W . ' .art-coa-ah-marker' => 'border-color: {{VALUE}};']]);
        $this->end_controls_section();

        $this->start_controls_section('history_card', ['label' => 'کارت رویداد', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'ah_card_border', 'selector' => $W . ' .art-coa-ah-event']);
        $this->add_responsive_control('ah_card_radius', ['label' => 'گردی گوشه', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => [$W . ' .art-coa-ah-event' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), ['name' => 'ah_card_shadow', 'selector' => $W . ' .art-coa-ah-event']);
        $this->add_responsive_control('ah_card_padding', ['label' => 'فاصلهٔ داخلی', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em'], 'selectors' => [$W . ' .art-coa-ah-event' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('ah_gap', ['label' => 'فاصلهٔ بین کارت‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => [$W . ' .art-coa-ah-list' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('ah_img_radius', ['label' => 'گردی گوشهٔ تصویر', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', '%'], 'range' => ['px' => ['min' => 0, 'max' => 40], '%' => ['min' => 0, 'max' => 50]], 'selectors' => [$W . ' .art-coa-ah-image' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('ah_img_w', ['label' => 'عرض تصویر', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 40, 'max' => 220]], 'selectors' => [$W . ' .art-coa-ah-image' => 'flex-basis: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        \Artiey_AET_Tabs::register_style_controls($this, 'historytabs');
        $this->register_design_controls('.art-coa-artwork-history', '', '', '');
        $this->register_arrow_controls('.art-coa-ah-link-arrow', 'history_arrow', '.art-coa-ah-link:hover', '.art-coa-ah-link');
    }

    private function render_history(string $source, array $s): string
    {
        $route = class_exists('\\Artiey_AET_Elementor_Layouts') ? \Artiey_AET_Elementor_Layouts::context() : [];
        $artist_id = 0;
        if (($route['entity_type'] ?? '') === 'artist' && !empty($route['slug']) && empty($s['artwork_id'])) {
            $artist = \WPArtCOA\Entities\Artist::get_by_slug(sanitize_title((string) $route['slug']));
            $artist_id = $artist ? (int) $artist->id : 0;
        }
        return ArtworkHistoryTimeline::render([
            'source' => $source,
            'artwork_id' => isset($s['artwork_id']) ? absint($s['artwork_id']) : 0,
            'artist_id' => $artist_id,
            'current_artwork' => !empty($s['current_artwork']),
            'limit' => isset($s['limit']) ? absint($s['limit']) : 12,
            'order' => (string) ($s['order'] ?? 'desc'),
            'layout' => $s['layout'] ?? 'line',
            'show_image' => !empty($s['show_image']),
            'show_price' => !empty($s['show_price']),
            'show_owner' => !empty($s['show_owner']),
        ]);
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        echo '<div class="art-coa-elementor-widget art-coa-artwork-history-widget">';
        if (!empty($s['title'])) {
            echo '<h2 class="art-coa-elementor-title">' . esc_html((string) $s['title']) . '</h2>';
        }
        if (($s['presentation'] ?? 'single') === 'tabs') {
            $allowed = ['auction', 'sales', 'ownership', 'market'];
            $selected = array_values(array_intersect($allowed, array_map('sanitize_key', (array) ($s['history_tabs'] ?? []))));
            $labels = ['auction' => 'حراج‌ها', 'sales' => 'فروش‌های ثبت‌شده', 'ownership' => 'انتقال مالکیت', 'market' => 'بازار ثانویه'];
            $panels = [];
            foreach ($selected as $source) {
                $html = $this->render_history($source, $s);
                if ($html !== '') { $panels[] = ['key' => $source, 'label' => $labels[$source], 'html' => $html]; }
            }
            $html = \Artiey_AET_Tabs::render($panels, $s, 'تب‌های تاریخچه اثر');
            echo $html !== '' ? $html : '<p class="art-coa-empty">برای این اثر سابقه‌ای در بخش‌های انتخاب‌شده ثبت نشده است.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo $this->render_history((string) ($s['source'] ?? 'all'), $s); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped renderer.
        }
        echo '</div>';
    }
}
