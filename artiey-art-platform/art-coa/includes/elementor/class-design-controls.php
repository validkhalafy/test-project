<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Controls_Manager;

trait Design_Controls
{
    protected function register_design_controls(string $root, string $title, string $card = '', string $grid = ''): void
    {
        // Shared external shortcode slot for all bundled COA Elementor widgets.
        $this->start_controls_section('aet_extra_shortcode_section', ['label' => 'شورت‌کد و محتوای افزونه‌های دیگر', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('aet_extra_shortcode', [
            'label' => 'شورت‌کد دلخواه', 'type' => Controls_Manager::TEXTAREA, 'rows' => 4,
            'dynamic' => ['active' => true], 'placeholder' => '[your_shortcode attribute="value"]',
            'description' => 'خروجی هر شورت‌کد ثبت‌شده در وردپرس، افزونه یا قالب را پس از محتوای این ویجت نمایش می‌دهد.',
        ]);
        $this->end_controls_section();

        // Global visual direction and widget frame.
        $this->start_controls_section('design', ['label' => 'طرح کلی و هویت بصری', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('design_scheme', [
            'label' => 'حالت رنگی', 'type' => Controls_Manager::SELECT,
            'options' => ['' => 'روشن', 'dark' => 'تیره', 'auto' => 'خودکار (مطابق دستگاه بازدیدکننده)'],
            'default' => '', 'prefix_class' => 'art-coa-scheme-',
        ]);
        $this->add_control('design_preset', [
            'label' => 'قالب طراحی آماده', 'type' => Controls_Manager::SELECT,
            'options' => [
                'classic' => 'کلاسیک',
                'editorial' => 'مجله‌ای / کیوریتوری',
                'luxury' => 'لوکس و گالری‌محور',
                'minimal' => 'مینیمال',
                'framed' => 'قاب‌دار',
            ],
            'default' => 'classic', 'prefix_class' => 'art-coa-preset-',
            'description' => 'قالب آماده، ظاهر ویجت را تغییر می‌دهد و محتوای هنرمند، آثار، حراج یا گواهی را دست‌کاری نمی‌کند.',
        ]);
        $this->add_control('design_hover', [
            'label' => 'جلوهٔ اشاره روی کارت‌ها', 'type' => Controls_Manager::SELECT,
            'options' => ['' => 'پیش‌فرض', 'lift' => 'بالا آمدن کارت', 'scale' => 'بزرگ‌نمایی ملایم', 'glow' => 'درخشش سایه', 'none' => 'بدون جلوه'],
            'default' => '', 'prefix_class' => 'art-coa-hover-',
        ]);
        $this->add_control('design_text_color', [
            'label' => 'رنگ متن اصلی', 'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} ' . $root => 'color: {{VALUE}};'],
        ]);
        $this->add_control('design_background', [
            'label' => 'پس‌زمینهٔ کلی ویجت', 'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} ' . $root => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('design_accent', [
            'label' => 'رنگ تأکیدی / هویت برند', 'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} ' . $root => '--art-coa-primary: {{VALUE}}; --art-coa-secondary: {{VALUE}};'],
        ]);
        $this->add_responsive_control('design_max_width', [
            'label' => 'حداکثر عرض ویجت', 'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => ['px' => ['min' => 320, 'max' => 1600, 'step' => 20], '%' => ['min' => 50, 'max' => 100]],
            'selectors' => ['{{WRAPPER}} ' . $root => 'max-width: {{SIZE}}{{UNIT}}; width: 100%; margin-inline: auto;'],
        ]);
        if ($title !== '') {
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'design_title_typography', 'label' => 'تایپوگرافی عنوان اصلی',
                'selector' => '{{WRAPPER}} ' . $title,
            ]);
            $this->add_control('design_title_color', [
                'label' => 'رنگ عنوان اصلی', 'type' => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} ' . $title => 'color: {{VALUE}};'],
            ]);
            $this->add_responsive_control('design_title_alignment', [
                'label' => 'تراز عنوان اصلی', 'type' => Controls_Manager::SELECT,
                'options' => ['' => 'پیش‌فرض', 'right' => 'راست', 'center' => 'وسط', 'left' => 'چپ'],
                'selectors' => ['{{WRAPPER}} ' . $title => 'text-align: {{VALUE}};'],
            ]);
        }
        $this->end_controls_section();

        // Card customization appears only on widgets which actually render cards.
        if ($card !== '') {
            $this->start_controls_section('design_cards', ['label' => 'کارت‌ها و محتوای داخلی', 'tab' => Controls_Manager::TAB_STYLE]);
            $this->add_responsive_control('design_card_radius', [
                'label' => 'گردی گوشهٔ کارت‌ها', 'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 40]],
                'selectors' => ['{{WRAPPER}} ' . $card => 'border-radius: {{SIZE}}{{UNIT}};'],
            ]);
            $this->add_control('design_card_background', [
                'label' => 'پس‌زمینهٔ کارت', 'type' => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} ' . $card => 'background-color: {{VALUE}};'],
            ]);
            $this->add_responsive_control('design_card_padding', [
                'label' => 'فاصلهٔ داخلی کارت', 'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => ['{{WRAPPER}} ' . $card => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]);
            $this->add_group_control(\Elementor\Group_Control_Border::get_type(), [
                'name' => 'design_card_border', 'label' => 'حاشیهٔ کارت', 'selector' => '{{WRAPPER}} ' . $card,
            ]);
            $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), [
                'name' => 'design_card_shadow', 'label' => 'سایهٔ کارت', 'selector' => '{{WRAPPER}} ' . $card,
            ]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'design_card_title_typography', 'label' => 'تایپوگرافی عنوان کارت',
                'selector' => '{{WRAPPER}} .art-coa-tl-item-title, {{WRAPPER}} .art-coa-au-title, {{WRAPPER}} .art-coa-portfolio-title, {{WRAPPER}} .art-coa-shop-title, {{WRAPPER}} .art-coa-cert-title, {{WRAPPER}} .art-coa-shop-card h3',
            ]);
            $this->add_control('design_card_title_color', [
                'label' => 'رنگ عنوان کارت', 'type' => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .art-coa-tl-item-title, {{WRAPPER}} .art-coa-au-title, {{WRAPPER}} .art-coa-portfolio-title, {{WRAPPER}} .art-coa-shop-title, {{WRAPPER}} .art-coa-cert-title, {{WRAPPER}} .art-coa-shop-card h3' => 'color: {{VALUE}};'],
            ]);
            $this->add_control('design_card_meta_color', [
                'label' => 'رنگ جزئیات کارت', 'type' => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} ' . $card . ' .art-coa-meta, {{WRAPPER}} ' . $card . ' .art-coa-sub, {{WRAPPER}} ' . $card . ' small' => 'color: {{VALUE}};'],
            ]);
            $this->end_controls_section();
        }

        if ($grid !== '') {
            $this->start_controls_section('design_grid', ['label' => 'شبکه و فاصله‌گذاری', 'tab' => Controls_Manager::TAB_STYLE]);
            $this->add_responsive_control('design_columns', [
                'label' => 'تعداد ستون‌ها (واکنش‌گرا)', 'type' => Controls_Manager::SELECT,
                'options' => ['' => 'پیش‌فرض', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶'],
                'selectors' => ['{{WRAPPER}} ' . $grid => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));'],
            ]);
            $this->add_responsive_control('design_grid_gap', [
                'label' => 'فاصلهٔ افقی و عمودی کارت‌ها', 'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 48]],
                'selectors' => ['{{WRAPPER}} ' . $grid => 'gap: {{SIZE}}{{UNIT}};'],
            ]);
            $this->end_controls_section();
        }

        $this->start_controls_section('design_typography_actions', ['label' => 'تایپوگرافی و دکمه‌ها', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name' => 'design_body_typography', 'label' => 'تایپوگرافی متن اصلی',
            'selector' => '{{WRAPPER}} ' . $root,
        ]);
        $this->add_control('design_button_bg', [
            'label' => 'پس‌زمینهٔ دکمه‌ها', 'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .art-coa-btn:not(.art-coa-btn--ghost)' => 'background-color: {{VALUE}}; border-color: {{VALUE}};'],
        ]);
        $this->add_control('design_button_color', [
            'label' => 'رنگ نوشتهٔ دکمه‌ها', 'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .art-coa-btn:not(.art-coa-btn--ghost)' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('design_button_radius', [
            'label' => 'گردی گوشهٔ دکمه‌ها', 'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 32]],
            'selectors' => ['{{WRAPPER}} .art-coa-btn' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('design_surface_spacing', ['label' => 'کادر، سایه و فاصلهٔ بیرونی', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), [
            'name' => 'design_border', 'label' => 'حاشیهٔ کلی ویجت', 'selector' => '{{WRAPPER}} ' . $root,
        ]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), [
            'name' => 'design_shadow', 'label' => 'سایهٔ کلی ویجت', 'selector' => '{{WRAPPER}} ' . $root,
        ]);
        $this->add_responsive_control('design_radius', [
            'label' => 'گردی گوشه‌های ویجت', 'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 40]],
            'selectors' => ['{{WRAPPER}} ' . $root => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('design_padding', [
            'label' => 'فاصلهٔ داخلی ویجت', 'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => ['{{WRAPPER}} ' . $root => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('design_image_radius', [
            'label' => 'گردی گوشهٔ تصاویر', 'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 36]],
            'selectors' => ['{{WRAPPER}} ' . $root . ' img' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('design_image_height', [
            'label' => 'ارتفاع تصاویر کارت‌ها', 'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'], 'range' => ['px' => ['min' => 80, 'max' => 600]],
            'selectors' => ['{{WRAPPER}} ' . $root . ' .art-coa-portfolio-image img, {{WRAPPER}} ' . $root . ' .art-coa-au-image img, {{WRAPPER}} ' . $root . ' .art-coa-shop-image img, {{WRAPPER}} ' . $root . ' .art-coa-cert-image img' => 'height: {{SIZE}}{{UNIT}}; object-fit: cover;'],
        ]);
        $this->end_controls_section();
    }

    /**
     * Style panels shared by every timeline widget (exhibitions, auctions). All selectors are scoped with
     * `.art-coa-timeline`, which beats the stylesheet (no !important there), so every control takes effect.
     *
     * @param array<string,string> $statuses status class suffix => label (colour of that status)
     */
    protected function timeline_style(array $statuses = []): void
    {
        $T   = \Elementor\Group_Control_Typography::get_type();
        $W   = '{{WRAPPER}} .art-coa-timeline';
        $tab = Controls_Manager::TAB_STYLE;

        $color = function (string $id, string $label, string $selector, string $prop = 'color') {
            $this->add_control($id, ['label' => $label, 'type' => Controls_Manager::COLOR, 'selectors' => [$selector => $prop . ': {{VALUE}};']]);
        };

        /* heading, filters, year */
        $this->start_controls_section('tl_s_head', ['label' => 'عنوان اصلی، فیلترها و سال', 'tab' => $tab]);
        $this->add_group_control($T, ['name' => 'tl_heading_typo', 'label' => 'تایپوگرافی عنوان اصلی', 'selector' => '{{WRAPPER}} .art-coa-elementor-title, ' . $W . ' .art-coa-tl-title']);
        $color('tl_heading_color', 'رنگ عنوان اصلی', '{{WRAPPER}} .art-coa-elementor-title, ' . $W . ' .art-coa-tl-title');
        $this->add_responsive_control('tl_heading_align', ['label' => 'چینش عنوان اصلی', 'type' => Controls_Manager::SELECT, 'options' => ['' => 'پیش‌فرض', 'right' => 'راست', 'center' => 'وسط', 'left' => 'چپ'], 'selectors' => ['{{WRAPPER}} .art-coa-elementor-title, ' . $W . ' .art-coa-tl-title' => 'text-align: {{VALUE}};']]);
        $this->add_group_control($T, ['name' => 'tl_filter_typo', 'label' => 'تایپوگرافی دکمه‌های فیلتر', 'selector' => $W . ' .art-coa-tl-filter']);
        $color('tl_filter_color', 'رنگ متن فیلتر', $W . ' .art-coa-tl-filter:not(.is-active)');
        $color('tl_filter_bg', 'پس‌زمینهٔ فیلتر', $W . ' .art-coa-tl-filter:not(.is-active)', 'background-color');
        $color('tl_filter_active_bg', 'پس‌زمینهٔ فیلتر فعال', $W . ' .art-coa-tl-filter.is-active', 'background-color');
        $color('tl_year_bg', 'پس‌زمینهٔ نشان سال', $W . ' .art-coa-tl-year-badge', 'background-color');
        $color('tl_year_color', 'رنگ متن نشان سال', $W . ' .art-coa-tl-year-badge');
        $this->end_controls_section();

        /* card */
        $this->start_controls_section('tl_s_card', ['label' => 'کارت رویداد', 'tab' => $tab]);
        $color('tl_card_bg', 'پس‌زمینهٔ کارت', $W . ' .art-coa-tl-item', 'background-color');
        $color('tl_card_hover_bg', 'پس‌زمینه هنگام اشاره', $W . ' .art-coa-tl-item:hover', 'background-color');
        $color('tl_accent', 'رنگ تأکیدی (دکمه، نقطهٔ خط زمانی، فیلتر)', $W, '--art-coa-tl-accent');
        $this->add_control('tl_stripe_off', ['label' => 'حذف نوار رنگی کنار کارت', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'selectors' => [$W . ' .art-coa-tl-item' => 'border-inline-start-width: 1px;']]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'tl_card_border', 'selector' => $W . ' .art-coa-tl-item']);
        $this->add_responsive_control('tl_card_radius', ['label' => 'گردی گوشه', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => [$W => '--art-coa-tl-radius: {{SIZE}}{{UNIT}};', $W . ' .art-coa-tl-item' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), ['name' => 'tl_card_shadow', 'selector' => $W . ' .art-coa-tl-item']);
        $this->add_responsive_control('tl_card_padding', ['label' => 'فاصلهٔ داخلی کارت', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em'], 'selectors' => [$W . ' .art-coa-tl-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('tl_gap', ['label' => 'فاصلهٔ بین کارت‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => [$W => '--art-coa-tl-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('tl_max_width', ['label' => 'حداکثر عرض تایم‌لاین', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', '%'], 'range' => ['px' => ['min' => 320, 'max' => 1600], '%' => ['min' => 30, 'max' => 100]], 'selectors' => [$W => 'max-width: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        /* title + subtitle of every event */
        $this->start_controls_section('tl_s_text', ['label' => 'عنوان و زیرعنوان رویدادها', 'tab' => $tab]);
        $this->add_group_control($T, ['name' => 'tl_title_typo', 'label' => 'تایپوگرافی عنوان رویداد', 'selector' => $W . ' .art-coa-tl-item-title']);
        $color('tl_title_color', 'رنگ عنوان رویداد', $W . ' .art-coa-tl-item-title, ' . $W . ' .art-coa-tl-item-title a');
        $color('tl_title_hover', 'رنگ عنوان هنگام اشاره', $W . ' .art-coa-tl-item-title a:hover');
        $this->add_responsive_control('tl_title_space', ['label' => 'فاصلهٔ زیر عنوان', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 30]], 'selectors' => [$W . ' .art-coa-tl-item-title' => 'margin-bottom: {{SIZE}}{{UNIT}};']]);
        $this->add_group_control($T, ['name' => 'tl_sub_typo', 'label' => 'تایپوگرافی زیرعنوان', 'selector' => $W . ' .art-coa-tl-sub']);
        $color('tl_sub_color', 'رنگ زیرعنوان', $W . ' .art-coa-tl-sub');
        $this->add_responsive_control('tl_sub_space', ['label' => 'فاصلهٔ بالای زیرعنوان', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 30]], 'selectors' => [$W . ' .art-coa-tl-sub' => 'margin-top: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('tl_text_align', ['label' => 'چینش متن کارت', 'type' => Controls_Manager::SELECT, 'options' => ['' => 'پیش‌فرض', 'right' => 'راست', 'center' => 'وسط', 'left' => 'چپ'], 'selectors' => [$W . ' .art-coa-tl-body' => 'text-align: {{VALUE}};']]);
        $this->end_controls_section();

        /* meta, chips, price */
        $this->start_controls_section('tl_s_meta', ['label' => 'تاریخ، برچسب‌ها و جزئیات', 'tab' => $tab]);
        $this->add_group_control($T, ['name' => 'tl_date_typo', 'label' => 'تایپوگرافی تاریخ', 'selector' => $W . ' .art-coa-tl-date']);
        $color('tl_date_color', 'رنگ تاریخ', $W . ' .art-coa-tl-date');
        $this->add_group_control($T, ['name' => 'tl_chip_typo', 'label' => 'تایپوگرافی برچسب نوع و وضعیت', 'selector' => $W . ' .art-coa-tl-kind, ' . $W . ' .art-coa-tl-status']);
        $color('tl_chip_color', 'رنگ متن برچسب‌ها', $W . ' .art-coa-tl-kind, ' . $W . ' .art-coa-tl-status');
        $color('tl_chip_bg', 'پس‌زمینهٔ برچسب‌ها', $W . ' .art-coa-tl-kind, ' . $W . ' .art-coa-tl-status', 'background-color');
        $this->add_responsive_control('tl_chip_radius', ['label' => 'گردی گوشهٔ برچسب‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 30]], 'selectors' => [$W . ' .art-coa-tl-kind, ' . $W . ' .art-coa-tl-status' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $color('tl_count_color', 'رنگ شمارش معکوس', $W . ' .art-coa-tl-countdown');
        $this->add_group_control($T, ['name' => 'tl_fact_typo', 'label' => 'تایپوگرافی جزئیات (محل، پیشنهادها، لات)', 'selector' => $W . ' .art-coa-tl-fact']);
        $color('tl_fact_color', 'رنگ جزئیات', $W . ' .art-coa-tl-fact');
        $this->add_group_control($T, ['name' => 'tl_price_typo', 'label' => 'تایپوگرافی مبلغ', 'selector' => $W . ' .art-coa-tl-price strong']);
        $color('tl_price_color', 'رنگ مبلغ', $W . ' .art-coa-tl-price strong');
        $this->end_controls_section();

        /* image */
        $this->start_controls_section('tl_s_image', ['label' => 'تصویر و پوستر', 'tab' => $tab]);
        $this->add_responsive_control('tl_img_w', ['label' => 'عرض پوستر (چیدمان‌های لیستی)', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 40, 'max' => 260]], 'selectors' => [$W . ' .art-coa-tl-poster' => 'flex-basis: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('tl_img_h', ['label' => 'ارتفاع پوستر', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 50, 'max' => 500]], 'selectors' => [$W . ' .art-coa-tl-poster img' => 'height: {{SIZE}}{{UNIT}}; aspect-ratio: auto;']]);
        $this->add_control('tl_img_fit', ['label' => 'نحوهٔ جاگیری', 'type' => Controls_Manager::SELECT, 'options' => ['' => 'پیش‌فرض (برش)', 'contain' => 'نمایش کامل بدون برش'], 'selectors' => [$W . ' .art-coa-tl-poster img' => 'object-fit: {{VALUE}};']]);
        $this->add_responsive_control('tl_img_radius', ['label' => 'گردی گوشهٔ پوستر', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', '%'], 'range' => ['px' => ['min' => 0, 'max' => 40], '%' => ['min' => 0, 'max' => 50]], 'selectors' => [$W . ' .art-coa-tl-poster' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_control('tl_img_zoom_off', ['label' => 'خاموش‌کردن بزرگ‌شدن هنگام اشاره', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'selectors' => [$W . ' .art-coa-tl-item:hover .art-coa-tl-poster img' => 'transform: none;']]);
        $this->end_controls_section();

        /* line, markers, month headings */
        $this->start_controls_section('tl_s_line', ['label' => 'خط زمانی و عنوان ماه‌ها', 'tab' => $tab]);
        $color('tl_line_color', 'رنگ خط زمانی', $W . ' .art-coa-tl-list', 'border-color');
        $this->add_control('tl_alt_line_color', ['label' => 'رنگ خط وسط (چیدمان دوطرفه)', 'type' => Controls_Manager::COLOR, 'selectors' => [$W . '.art-coa-tl-layout-alt .art-coa-tl-list:before' => 'background: {{VALUE}};']]);
        $color('tl_marker_color', 'رنگ نقطه‌ها', $W . ' .art-coa-tl-item:before', 'border-color');
        $color('tl_marker_bg', 'پس‌زمینهٔ نقطه‌ها', $W . ' .art-coa-tl-item:before', 'background-color');
        $this->add_group_control($T, ['name' => 'tl_group_typo', 'label' => 'تایپوگرافی عنوان ماه', 'selector' => $W . ' .art-coa-tl-group-title']);
        $color('tl_group_color', 'رنگ عنوان ماه', $W . ' .art-coa-tl-group-title');
        $color('tl_group_rule', 'رنگ خط کنار عنوان ماه', $W . ' .art-coa-tl-group-title:after', 'background-color');
        $color('tl_group_count_bg', 'پس‌زمینهٔ شمارندهٔ ماه', $W . ' .art-coa-tl-group-count', 'background-color');
        $color('tl_group_count_color', 'رنگ شمارندهٔ ماه', $W . ' .art-coa-tl-group-count');
        $this->end_controls_section();

        /* button */
        $this->start_controls_section('tl_s_btn', ['label' => 'دکمهٔ کارت', 'tab' => $tab]);
        $this->add_group_control($T, ['name' => 'tl_btn_typo', 'label' => 'تایپوگرافی دکمه', 'selector' => $W . ' .art-coa-tl-btn']);
        $color('tl_btn_color', 'رنگ متن دکمه', $W . ' .art-coa-tl-btn');
        $color('tl_btn_bg', 'پس‌زمینهٔ دکمه', $W . ' .art-coa-tl-btn', 'background-color');
        $color('tl_btn_hover_bg', 'پس‌زمینه هنگام اشاره', $W . ' .art-coa-tl-btn:hover', 'background-color');
        $this->add_responsive_control('tl_btn_radius', ['label' => 'گردی گوشهٔ دکمه', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => [$W . ' .art-coa-tl-btn' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('tl_btn_padding', ['label' => 'فاصلهٔ داخلی دکمه', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em'], 'selectors' => [$W . ' .art-coa-tl-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();

        /* status colours */
        if ($statuses) {
            $this->start_controls_section('tl_s_status', ['label' => 'رنگ وضعیت‌ها', 'tab' => $tab]);
            foreach ($statuses as $key => $label) {
                $this->add_control('tl_status_' . $key, ['label' => 'رنگ «' . $label . '»', 'type' => Controls_Manager::COLOR, 'selectors' => [$W . ' .art-coa-tl-status-' . $key => '--art-coa-tl-ia: {{VALUE}};']]);
            }
            $this->end_controls_section();
        }
    }

    /**
     * Artists / galleries as a searchable picker (name list) instead of typing database ids.
     * @return array<int|string,string>
     */
    protected function entity_options(string $entity): array
    {
        $definitions = [
            'gallery' => ['table' => 'galleries', 'column' => 'name', 'where' => "status = 'active'"],
            'artist'  => ['table' => 'artists', 'column' => 'display_name', 'where' => "status = 'active'"],
            'artwork' => ['table' => 'artworks', 'column' => 'title', 'where' => "status IN ('approved','published','active','sold')"],
        ];
        $definition = $definitions[$entity] ?? $definitions['artist'];
        $table = $definition['table'];
        $col = $definition['column'];
        $where = $definition['where'];
        return \WPArtCOA\Cache::get('ec_options_' . $table . '_' . \WPArtCOA\Cache::stats_version(), static function () use ($table, $col, $where): array {
            global $wpdb;
            $rows = $wpdb->get_results('SELECT id, ' . $col . ' AS label FROM ' . \WPArtCOA\Database::table($table) . ' WHERE ' . $where . ' ORDER BY label ASC LIMIT 300') ?: [];
            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r->id] = (string) $r->label;
            }
            return $out;
        }, 300);
    }

    protected function entity_picker(string $name, string $entity, string $label, string $empty, array $condition = []): void
    {
        $this->add_control($name, [
            'label' => $label, 'type' => Controls_Manager::SELECT2, 'label_block' => true,
            'options' => ['' => $empty] + $this->entity_options($entity), 'default' => '',
        ] + ($condition ? ['condition' => $condition] : []));
    }

    /** A reusable, scoped arrow design panel for Elementor widgets. */
    protected function register_arrow_controls(string $targetSelector, string $prefix = 'arrow', string $hoverParentSelector = '', string $containerSelector = ''): void
    {
        $this->start_controls_section($prefix . '_design', ['label' => 'فلش‌ها و جهت‌نماها', 'tab' => Controls_Manager::TAB_STYLE]);

        $this->add_control($prefix . '_glyph', [
            'label' => 'طرح فلش', 'type' => Controls_Manager::SELECT, 'default' => 'arrow',
            'options' => [
                'arrow' => 'فلش کلاسیک ←', 'chevron' => 'چورون ‹', 'double' => 'دوگانه «',
                'long' => 'فلش بلند ⟵', 'triangle' => 'مثلث ◀', 'caret' => 'باریک ❮', 'minimal' => 'مینیمال ‹', 'none' => 'بدون فلش',
            ],
            'prefix_class' => 'artiey-arrow-glyph-',
        ]);
        $this->add_control($prefix . '_skin', [
            'label' => 'قاب فلش', 'type' => Controls_Manager::SELECT, 'default' => 'plain',
            'options' => [
                'plain' => 'بدون قاب', 'outline' => 'قاب خطی', 'circle' => 'دایره خطی',
                'filled-circle' => 'دایره توپر', 'rounded-square' => 'مربع گرد', 'soft-square' => 'مربع ملایم',
                'glass' => 'شیشه‌ای', 'pill' => 'کپسولی',
            ],
            'prefix_class' => 'artiey-arrow-skin-',
        ]);
        $this->add_control($prefix . '_motion', [
            'label' => 'حرکت هنگام اشاره', 'type' => Controls_Manager::SELECT, 'default' => 'lift',
            'options' => ['none' => 'بدون حرکت', 'lift' => 'حرکت کوتاه', 'scale' => 'بزرگ‌نمایی', 'slide' => 'لغزش جهت‌دار', 'rotate' => 'چرخش ظریف'],
            'prefix_class' => 'artiey-arrow-motion-',
        ]);

        $target = '{{WRAPPER}} ' . $targetSelector;
        $hoverTarget = $hoverParentSelector !== '' ? '{{WRAPPER}} ' . $hoverParentSelector . ' ' . $targetSelector : $target . ':hover';
        $focusTarget = $hoverParentSelector !== '' ? '{{WRAPPER}} ' . str_replace(':hover', ':focus-visible', $hoverParentSelector) . ' ' . $targetSelector : $target . ':focus-visible';
        $this->add_responsive_control($prefix . '_box_size', [
            'label' => 'اندازه قاب فلش', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', 'em'],
            'range' => ['px' => ['min' => 12, 'max' => 64], 'em' => ['min' => 1, 'max' => 4]],
            'selectors' => [$target => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}}; flex: 0 0 {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control($prefix . '_glyph_size', [
            'label' => 'اندازه خود فلش', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', 'em'],
            'range' => ['px' => ['min' => 8, 'max' => 48], 'em' => ['min' => .5, 'max' => 2.5]],
            'selectors' => [$target . '::before' => 'font-size: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_control($prefix . '_color', [
            'label' => 'رنگ فلش', 'type' => Controls_Manager::COLOR,
            'selectors' => [$target => '--artiey-arrow-color: {{VALUE}}; color: {{VALUE}};'],
        ]);
        $this->add_control($prefix . '_background', [
            'label' => 'پس‌زمینه قاب', 'type' => Controls_Manager::COLOR,
            'selectors' => [$target => '--artiey-arrow-background: {{VALUE}}; background-color: {{VALUE}};'],
        ]);
        $this->add_control($prefix . '_border_color', [
            'label' => 'رنگ حاشیه قاب', 'type' => Controls_Manager::COLOR,
            'selectors' => [$target => 'border-color: {{VALUE}};'],
        ]);
        $this->add_responsive_control($prefix . '_border_width', [
            'label' => 'ضخامت حاشیه', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 5]],
            'selectors' => [$target => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;'],
        ]);
        $this->add_responsive_control($prefix . '_radius', [
            'label' => 'گردی قاب', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', '%'],
            'range' => ['px' => ['min' => 0, 'max' => 40], '%' => ['min' => 0, 'max' => 50]],
            'selectors' => [$target => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_control($prefix . '_hover_color', [
            'label' => 'رنگ فلش هنگام اشاره', 'type' => Controls_Manager::COLOR,
            'selectors' => [$hoverTarget => '--artiey-arrow-color: {{VALUE}}; color: {{VALUE}};'],
        ]);
        $this->add_control($prefix . '_hover_background', [
            'label' => 'پس‌زمینه هنگام اشاره', 'type' => Controls_Manager::COLOR,
            'selectors' => [$hoverTarget => '--artiey-arrow-background: {{VALUE}}; background-color: {{VALUE}};'],
        ]);
        $this->add_control($prefix . '_focus_color', [
            'label' => 'رنگ حالت فوکوس دسترس‌پذیر', 'type' => Controls_Manager::COLOR,
            'selectors' => [$focusTarget => 'outline-color: {{VALUE}};'],
        ]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), [
            'name' => $prefix . '_shadow', 'label' => 'سایه قاب', 'selector' => $target,
        ]);
        if ($containerSelector !== '') {
            $this->add_responsive_control($prefix . '_gap', [
                'label' => 'فاصله فلش از متن', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', 'em'],
                'range' => ['px' => ['min' => 0, 'max' => 32], 'em' => ['min' => 0, 'max' => 2]],
                'selectors' => ['{{WRAPPER}} ' . $containerSelector => 'gap: {{SIZE}}{{UNIT}};'],
            ]);
        }
        $this->end_controls_section();
    }
}
