<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

defined('ABSPATH') || exit;

final class Auction_List_Widget extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string { return 'art_coa_auction_list'; }
    public function get_title(): string { return 'فهرست حراج‌های فعال'; }
    public function get_icon(): string { return 'eicon-price-list'; }
    public function get_categories(): array { return ['art-coa', 'artiey-art']; }
    public function get_style_depends(): array { return ['wp-art-coa-front', 'wp-art-coa-dynamic', 'artiey-aet-profile']; }
    public function get_script_depends(): array { return ['artiey-aet-profile']; }
    public function get_keywords(): array { return ['auction', 'bid', 'حراج', 'مزایده', 'پیشنهاد']; }

    private function yes(string $id, string $label, bool $default = true, array $condition = []): void
    {
        $this->add_control($id, ['label' => $label, 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => $default ? 'yes' : ''] + ($condition ? ['condition' => $condition] : []));
    }

    private function color(string $id, string $label, string $selector, string $prop = 'color'): void
    {
        $this->add_control($id, ['label' => $label, 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} ' . $selector => $prop . ': {{VALUE}};']]);
    }

    private function typo(string $id, string $label, string $selector): void
    {
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => $id, 'label' => $label, 'selector' => '{{WRAPPER}} ' . $selector]);
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => 'محتوا']);
        $this->yes('show_title', 'نمایش عنوان');
        $this->add_control('title', ['label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'placeholder' => 'حراج‌های هنری', 'condition' => ['show_title' => 'yes']]);
        $this->add_control('presentation', ['label' => 'نحوه ارائه', 'type' => Controls_Manager::SELECT, 'options' => ['list' => 'فهرست یکپارچه', 'tabs' => 'تب‌بندی مرتب‌سازی‌ها'], 'default' => 'list']);
        $this->add_control('auction_views', [
            'label' => 'تب‌های فهرست حراج', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true,
            'options' => ['ending' => 'نزدیک‌ترین پایان', 'newest' => 'جدیدترین', 'bids' => 'بیشترین پیشنهاد', 'price_high' => 'بالاترین قیمت', 'price_low' => 'پایین‌ترین قیمت'],
            'default' => ['ending', 'newest', 'bids'], 'condition' => ['presentation' => 'tabs'],
            'description' => 'برای هر روش مرتب‌سازی انتخاب‌شده یک تب جدا ساخته می‌شود.',
        ]);
        \Artiey_AET_Tabs::register_behavior_controls($this, 'presentation', 'tabs', ['ending' => 'نزدیک‌ترین پایان', 'newest' => 'جدیدترین', 'bids' => 'بیشترین پیشنهاد', 'price_high' => 'بالاترین قیمت', 'price_low' => 'پایین‌ترین قیمت']);
        $this->yes('show_intro', 'نمایش توضیح زیر عنوان');
        $this->yes('show_filters', 'نوار جستجو و مرتب‌سازی');
        $this->end_controls_section();

        $this->start_controls_section('auction_query', ['label' => 'فیلتر و ترتیب حراج‌ها', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('sort', ['label' => 'مرتب‌سازی پیش‌فرض', 'type' => Controls_Manager::SELECT, 'default' => 'ending', 'options' => ['ending' => 'نزدیک‌ترین پایان', 'newest' => 'جدیدترین', 'bids' => 'بیشترین پیشنهاد', 'price_high' => 'بالاترین قیمت', 'price_low' => 'پایین‌ترین قیمت']]);
        $this->add_control('limit', ['label' => 'تعداد در هر صفحه', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 50, 'default' => 12]);
        $this->end_controls_section();

        $this->start_controls_section('layout_section', ['label' => 'چیدمان و کارت']);
        $this->add_responsive_control('columns', [
            'label' => 'تعداد ستون‌ها', 'type' => Controls_Manager::SELECT, 'options' => ['1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵'], 'default' => '3', 'tablet_default' => '2', 'mobile_default' => '1',
            'selectors' => ['{{WRAPPER}} .art-coa-auction-list' => '--art-coa-au-cols: {{VALUE}};'],
        ]);
        $this->yes('show_badge', 'برچسب «در حال برگزاری»');
        $this->yes('show_artist', 'نام هنرمند');
        $this->yes('show_lot', 'شمارهٔ لات');
        $this->yes('show_estimate', 'برآورد قیمت');
        $this->yes('show_house', 'خانهٔ حراج');
        $this->yes('show_bids', 'تعداد پیشنهادها');
        $this->yes('show_ends', 'زمان پایان');
        $this->yes('show_countdown', 'زمان باقی‌مانده', false);
        $this->add_control('button_text', ['label' => 'متن دکمه', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'placeholder' => 'ثبت پیشنهاد']);
        $this->end_controls_section();

        $this->start_controls_section('au_style_head', ['label' => 'عنوان و فیلتر', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->typo('au_title_typo', 'تایپوگرافی عنوان', '.art-coa-auction-list .art-coa-auction-title');
        $this->color('au_title_color', 'رنگ عنوان', '.art-coa-auction-list .art-coa-auction-title');
        $this->typo('au_intro_typo', 'تایپوگرافی توضیح', '.art-coa-auction-list .art-coa-auction-intro');
        $this->color('au_intro_color', 'رنگ توضیح', '.art-coa-auction-list .art-coa-auction-intro');
        $this->color('au_filter_bg', 'پس‌زمینهٔ نوار فیلتر', '.art-coa-auction-list .art-coa-auction-filters', 'background-color');
        $this->end_controls_section();

        $this->start_controls_section('au_style_card', ['label' => 'کارت حراج', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->color('au_card_bg', 'پس‌زمینهٔ کارت', '.art-coa-auction-list .art-coa-au-card', 'background-color');
        $this->add_group_control(Group_Control_Border::get_type(), ['name' => 'au_card_border', 'selector' => '{{WRAPPER}} .art-coa-auction-list .art-coa-au-card']);
        $this->add_responsive_control('au_card_radius', ['label' => 'گردی گوشه', 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => ['{{WRAPPER}} .art-coa-auction-list .art-coa-au-card' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name' => 'au_card_shadow', 'selector' => '{{WRAPPER}} .art-coa-auction-list .art-coa-au-card']);
        $this->add_responsive_control('au_gap', ['label' => 'فاصلهٔ کارت‌ها', 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => ['{{WRAPPER}} .art-coa-auction-list .art-coa-auction-grid' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('au_body_pad', ['label' => 'فاصلهٔ درونی متن', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px'], 'selectors' => ['{{WRAPPER}} .art-coa-auction-list .art-coa-au-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('au_ratio', ['label' => 'نسبت تصویر', 'type' => Controls_Manager::SELECT, 'options' => ['' => 'پیش‌فرض (۴:۵)', '1/1' => '۱:۱', '3/4' => '۳:۴', '4/3' => '۴:۳', '16/9' => '۱۶:۹'], 'selectors' => ['{{WRAPPER}} .art-coa-auction-list .art-coa-au-media' => 'aspect-ratio: {{VALUE}};']]);
        $this->typo('au_item_title_typo', 'تایپوگرافی عنوان اثر', '.art-coa-auction-list .art-coa-au-title');
        $this->color('au_item_title_color', 'رنگ عنوان اثر', '.art-coa-auction-list .art-coa-au-title a');
        $this->color('au_artist_color', 'رنگ نام هنرمند', '.art-coa-auction-list .art-coa-au-artist a');
        $this->typo('au_price_typo', 'تایپوگرافی قیمت', '.art-coa-auction-list .art-coa-au-price strong');
        $this->color('au_price_color', 'رنگ قیمت', '.art-coa-auction-list .art-coa-au-price strong');
        $this->color('au_meta_color', 'رنگ اطلاعات جانبی', '.art-coa-auction-list .art-coa-au-meta');
        $this->color('au_badge_bg', 'پس‌زمینهٔ برچسب', '.art-coa-auction-list .art-coa-au-badge', 'background-color');
        $this->color('au_badge_color', 'رنگ متن برچسب', '.art-coa-auction-list .art-coa-au-badge');
        $this->end_controls_section();

        $this->start_controls_section('au_style_btn', ['label' => 'دکمه', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->typo('au_btn_typo', 'تایپوگرافی دکمه', '.art-coa-auction-list .art-coa-au-btn');
        $this->color('au_btn_color', 'رنگ متن', '.art-coa-auction-list .art-coa-au-btn');
        $this->color('au_btn_bg', 'پس‌زمینه', '.art-coa-auction-list .art-coa-au-btn', 'background-color');
        $this->color('au_btn_hover_bg', 'پس‌زمینه هنگام اشاره', '.art-coa-auction-list .art-coa-au-btn:hover', 'background-color');
        $this->add_responsive_control('au_btn_radius', ['label' => 'گردی گوشه', 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => ['{{WRAPPER}} .art-coa-auction-list .art-coa-au-btn' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        \Artiey_AET_Tabs::register_style_controls($this, 'auctionlisttabs');
        $this->register_design_controls('.art-coa-auction-list', '', '', '');
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        \WPArtCOA\Auction::close_expired();
        $show = static fn(string $key): bool => !empty($s[$key]);
        $render_list = static function (string $sort, array $overrides = []) use ($s, $show): string {
            return \WPArtCOA\Auction::render_list(array_merge([
                'limit' => max(1, min(50, (int) ($s['limit'] ?? 12))), 'sort' => $sort,
                'ignore_request_sort' => true, 'columns' => (int) ($s['columns'] ?? 3),
                'title' => '', 'show_title' => false, 'show_intro' => false, 'show_filters' => false,
                'show_badge' => $show('show_badge'), 'show_artist' => $show('show_artist'), 'show_lot' => $show('show_lot'),
                'show_estimate' => $show('show_estimate'), 'show_house' => $show('show_house'), 'show_bids' => $show('show_bids'),
                'show_ends' => $show('show_ends'), 'show_countdown' => $show('show_countdown'), 'button_text' => sanitize_text_field((string) ($s['button_text'] ?? '')),
            ], $overrides));
        };

        echo '<div class="art-coa-elementor-widget art-coa-auction-list-widget">';
        if (($s['presentation'] ?? 'list') === 'tabs') {
            if ($show('show_title')) {
                $title = sanitize_text_field((string) ($s['title'] ?? ''));
                echo '<h2 class="art-coa-auction-title">' . esc_html($title !== '' ? $title : 'حراج‌های هنری') . '</h2>';
            }
            if ($show('show_intro')) {
                echo '<p class="art-coa-shop-intro art-coa-auction-intro">' . esc_html(\WPArtCOA\I18n::t('auction_intro')) . '</p>';
            }
            // Keep search capability but hide the redundant sort dropdown because tabs are the sort selector.
            if ($show('show_filters')) {
                $query_raw = isset($_GET['art_coa_auction_q']) && is_scalar($_GET['art_coa_auction_q']) ? wp_unslash($_GET['art_coa_auction_q']) : '';
                echo '<form class="art-coa-auction-filters art-coa-auction-filters--tabs" method="get" action="' . esc_url(is_singular() ? get_permalink() : home_url('/art-coa/auctions/')) . '">';
                echo '<label><span>جستجوی اثر، هنرمند یا حراجی</span><input type="search" name="art_coa_auction_q" value="' . esc_attr(sanitize_text_field((string) $query_raw)) . '" maxlength="100"></label>';
                echo '<input type="hidden" name="art_coa_auction_page" value="1"><button class="button button-primary" type="submit">جستجو</button></form>';
            }
            $allowed = ['ending', 'newest', 'bids', 'price_high', 'price_low'];
            $selected = array_values(array_intersect($allowed, array_map('sanitize_key', (array) ($s['auction_views'] ?? ['ending', 'newest', 'bids']))));
            $labels = ['ending' => 'نزدیک‌ترین پایان', 'newest' => 'جدیدترین', 'bids' => 'بیشترین پیشنهاد', 'price_high' => 'بالاترین قیمت', 'price_low' => 'پایین‌ترین قیمت'];
            $panels = [];
            foreach ($selected as $sort) {
                $html = $render_list($sort);
                if (trim($html) !== '') { $panels[] = ['key' => $sort, 'label' => $labels[$sort], 'html' => $html]; }
            }
            $tab_settings = $s;
            $tab_settings['force_tabs'] = 'yes';
            $tabs = \Artiey_AET_Tabs::render($panels, $tab_settings, 'مرتب‌سازی فهرست حراج‌ها');
            echo $tabs !== '' ? $tabs : '<p class="art-coa-empty">حراجی برای نمایش وجود ندارد.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted escaped Auction renderer.
        } else {
            echo \WPArtCOA\Auction::render_list([
                'limit' => (int) ($s['limit'] ?? 12), 'sort' => (string) ($s['sort'] ?? 'ending'), 'columns' => (int) ($s['columns'] ?? 3),
                'title' => sanitize_text_field((string) ($s['title'] ?? '')), 'show_title' => $show('show_title'), 'show_intro' => $show('show_intro'),
                'show_filters' => $show('show_filters'), 'show_badge' => $show('show_badge'), 'show_artist' => $show('show_artist'), 'show_lot' => $show('show_lot'),
                'show_estimate' => $show('show_estimate'), 'show_house' => $show('show_house'), 'show_bids' => $show('show_bids'), 'show_ends' => $show('show_ends'),
                'show_countdown' => $show('show_countdown'), 'button_text' => sanitize_text_field((string) ($s['button_text'] ?? '')),
            ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Auction::render_list.
        }
        echo '</div>';
    }
}
