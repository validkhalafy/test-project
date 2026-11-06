<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use WPArtCOA\ArtistBio;

defined('ABSPATH') || exit;

final class Artist_Bio_Widget extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string { return 'art_coa_artist_bio'; }
    public function get_title(): string { return 'بیوگرافی هنرمند'; }
    public function get_icon(): string { return 'eicon-person'; }
    public function get_categories(): array { return ['art-coa', 'artiey-art']; }
    public function get_style_depends(): array { return ['wp-art-coa-front', 'wp-art-coa-dynamic', 'artiey-aet-profile']; }
    public function get_script_depends(): array { return ['artiey-aet-profile']; }
    public function get_keywords(): array { return ['artist', 'bio', 'biography', 'هنرمند', 'بیوگرافی', 'درباره']; }

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
        $this->entity_picker('pick_artist', 'artist', 'هنرمند', 'هنرمندِ صفحهٔ جاری');
        $this->end_controls_section();

        $this->start_controls_section('bio_presentation', ['label' => 'چیدمان و عنوان', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('presentation', ['label' => 'نحوه نمایش بیوگرافی', 'type' => Controls_Manager::SELECT, 'options' => ['single' => 'یکپارچه', 'tabs' => 'تفکیک به تب‌های مستقل'], 'default' => 'single']);
        $this->add_control('bio_tabs', ['label' => 'بخش‌های داخل تب‌ها', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => ['biography' => 'بیوگرافی و معرفی کوتاه', 'facts' => 'مشخصات هنرمند', 'statement' => 'بیانیه هنری', 'education' => 'تحصیلات', 'awards' => 'جوایز و افتخارات'], 'default' => ['biography', 'facts', 'statement', 'education', 'awards'], 'condition' => ['presentation' => 'tabs']]);
        \Artiey_AET_Tabs::register_behavior_controls($this, 'presentation', 'tabs', ['biography' => 'بیوگرافی و معرفی کوتاه', 'facts' => 'مشخصات هنرمند', 'statement' => 'بیانیه هنری', 'education' => 'تحصیلات', 'awards' => 'جوایز و افتخارات']);
        $this->add_control('slug', ['label' => 'یا نامک هنرمند', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true]]);
        $this->yes('show_title', 'نمایش عنوان');
        $this->add_control('title', ['label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'default' => 'بیوگرافی', 'dynamic' => ['active' => true], 'condition' => ['show_title' => 'yes']]);
        $this->add_control('title_tag', ['label' => 'تگ عنوان', 'type' => Controls_Manager::SELECT, 'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div'], 'default' => 'h2', 'condition' => ['show_title' => 'yes']]);
        $this->add_control('layout', ['label' => 'چیدمان', 'type' => Controls_Manager::SELECT, 'options' => ['split' => 'متن و مشخصات کنار هم', 'stacked' => 'زیر هم'], 'default' => 'split']);
        $this->end_controls_section();

        $this->start_controls_section('bio_content_parts', ['label' => 'بخش‌های بیوگرافی', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->yes('show_lead', 'معرفی کوتاه');
        $this->yes('show_facts', 'مشخصات (تولد، شهر، ملیت، رسانه)');
        $this->yes('show_statement', 'بیانیهٔ هنری');
        $this->yes('show_education', 'تحصیلات');
        $this->yes('show_awards', 'جوایز و افتخارات');
        $this->yes('readmore', 'دکمهٔ «ادامهٔ بیوگرافی»');
        $this->add_control('paragraphs', ['label' => 'پاراگراف‌های نمایان', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 10, 'default' => 2, 'condition' => ['readmore' => 'yes']]);
        $this->add_control('more_label', ['label' => 'متن دکمه (باز)', 'type' => Controls_Manager::TEXT, 'default' => 'ادامهٔ بیوگرافی', 'condition' => ['readmore' => 'yes']]);
        $this->add_control('less_label', ['label' => 'متن دکمه (بسته)', 'type' => Controls_Manager::TEXT, 'default' => 'بستن', 'condition' => ['readmore' => 'yes']]);
        $this->end_controls_section();

        $this->start_controls_section('bio_style_box', ['label' => 'کادر و چیدمان', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->color('bio_bg', 'پس‌زمینه', '.art-coa-bio-sec', 'background-color');
        $this->add_group_control(Group_Control_Border::get_type(), ['name' => 'bio_border', 'selector' => '{{WRAPPER}} .art-coa-bio-sec']);
        $this->add_responsive_control('bio_radius', ['label' => 'گردی گوشه', 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 48]], 'selectors' => ['{{WRAPPER}} .art-coa-bio-sec' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('bio_padding', ['label' => 'فاصلهٔ درونی', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em'], 'selectors' => ['{{WRAPPER}} .art-coa-bio-sec' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name' => 'bio_shadow', 'selector' => '{{WRAPPER}} .art-coa-bio-sec']);
        $this->add_responsive_control('bio_side_width', ['label' => 'عرض ستون مشخصات', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 180, 'max' => 480]], 'selectors' => ['{{WRAPPER}} .art-coa-bio-sec' => '--art-coa-bio-side: {{SIZE}}{{UNIT}};'], 'condition' => ['layout' => 'split']]);
        $this->add_responsive_control('bio_gap', ['label' => 'فاصلهٔ بین بخش‌ها', 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 80]], 'selectors' => ['{{WRAPPER}} .art-coa-bio-sec' => '--art-coa-bio-gap: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('bio_style_text', ['label' => 'عنوان و متن', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->typo('bio_title_typo', 'تایپوگرافی عنوان', '.art-coa-bio-sec .art-coa-bio-title');
        $this->color('bio_title_color', 'رنگ عنوان', '.art-coa-bio-sec .art-coa-bio-title');
        $this->color('bio_title_line', 'رنگ خط زیر عنوان', '.art-coa-bio-sec .art-coa-bio-head', 'border-color');
        $this->typo('bio_lead_typo', 'تایپوگرافی معرفی کوتاه', '.art-coa-bio-sec .art-coa-bio-lead');
        $this->color('bio_lead_color', 'رنگ معرفی کوتاه', '.art-coa-bio-sec .art-coa-bio-lead');
        $this->typo('bio_text_typo', 'تایپوگرافی متن بیوگرافی', '.art-coa-bio-sec .art-coa-bio-text');
        $this->color('bio_text_color', 'رنگ متن بیوگرافی', '.art-coa-bio-sec .art-coa-bio-text');
        $this->typo('bio_sub_typo', 'تایپوگرافی عنوان‌های فرعی', '.art-coa-bio-sec .art-coa-bio-sub');
        $this->color('bio_sub_color', 'رنگ عنوان‌های فرعی', '.art-coa-bio-sec .art-coa-bio-sub');
        $this->color('bio_more_color', 'رنگ دکمهٔ ادامه', '.art-coa-bio-sec .art-coa-bio-more summary');
        $this->end_controls_section();

        $this->start_controls_section('bio_style_cards', ['label' => 'مشخصات، بیانیه و خط زمانی', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->color('bio_side_bg', 'پس‌زمینهٔ مشخصات', '.art-coa-bio-sec .art-coa-bio-side', 'background-color');
        $this->color('bio_fact_label', 'رنگ برچسب مشخصات', '.art-coa-bio-sec .art-coa-bio-fact dt');
        $this->color('bio_fact_value', 'رنگ مقدار مشخصات', '.art-coa-bio-sec .art-coa-bio-fact dd');
        $this->typo('bio_fact_typo', 'تایپوگرافی مشخصات', '.art-coa-bio-sec .art-coa-bio-fact dd');
        $this->color('bio_quote_bg', 'پس‌زمینهٔ بیانیه', '.art-coa-bio-sec .art-coa-bio-statement', 'background-color');
        $this->color('bio_quote_border', 'رنگ نوار بیانیه', '.art-coa-bio-sec .art-coa-bio-statement', 'border-color');
        $this->add_control('bio_tl_dot', ['label' => 'رنگ نقطه و خط زمانی', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-bio-sec .art-coa-bio-tl' => '--art-coa-bio-tl: {{VALUE}};']]);
        $this->color('bio_tl_year', 'رنگ سال', '.art-coa-bio-sec .art-coa-bio-tl-year');
        $this->typo('bio_tl_year_typo', 'تایپوگرافی سال', '.art-coa-bio-sec .art-coa-bio-tl-year');
        $this->color('bio_tl_text', 'رنگ متن خط زمانی', '.art-coa-bio-sec .art-coa-bio-tl-text');
        $this->end_controls_section();

        \Artiey_AET_Tabs::register_style_controls($this, 'artistbiotabs');
        $this->register_design_controls('.art-coa-bio-sec', '', '', '');
    }

    protected function render(): void
    {
        $s   = $this->get_settings_for_display();
        $route = class_exists('\\Artiey_AET_Elementor_Layouts') ? \Artiey_AET_Elementor_Layouts::context() : [];
        $route_slug = (($route['entity_type'] ?? '') === 'artist') ? (string) ($route['slug'] ?? '') : '';
        $row = ArtistBio::resolve(absint($s['pick_artist'] ?? 0), (string) ($s['slug'] ?? '') !== '' ? (string) $s['slug'] : $route_slug);
        if (!$row) {
            if ($this->is_edit()) {
                echo '<p class="art-coa-empty">برای دیدن پیش‌نمایش، یک هنرمند انتخاب کنید.</p>';
            }
            return;
        }
        $on = static fn(string $k): bool => !empty($s[$k]);
        $base = [
            'title' => (string) ($s['title'] ?? 'بیوگرافی'), 'title_tag' => (string) ($s['title_tag'] ?? 'h2'), 'layout' => (string) ($s['layout'] ?? 'split'),
            'show_title' => $on('show_title'), 'show_lead' => $on('show_lead'), 'show_facts' => $on('show_facts'), 'show_statement' => $on('show_statement'),
            'show_education' => $on('show_education'), 'show_awards' => $on('show_awards'), 'readmore' => $on('readmore'),
            'visible_paragraphs' => (int) ($s['paragraphs'] ?? 2), 'more_label' => (string) ($s['more_label'] ?? ''), 'less_label' => (string) ($s['less_label'] ?? ''),
        ];
        if (($s['presentation'] ?? 'single') !== 'tabs') {
            echo ArtistBio::render($row, $base); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ArtistBio.
            return;
        }

        $labels = [
            'biography' => 'بیوگرافی و معرفی کوتاه', 'facts' => 'مشخصات هنرمند', 'statement' => 'بیانیه هنری',
            'education' => 'تحصیلات', 'awards' => 'جوایز و افتخارات',
        ];
        $enabled = [
            'biography' => true, 'facts' => $on('show_facts'), 'statement' => $on('show_statement'),
            'education' => $on('show_education'), 'awards' => $on('show_awards'),
        ];
        $selected = array_values(array_intersect(array_keys($labels), array_map('sanitize_key', (array) ($s['bio_tabs'] ?? array_keys($labels)))));
        if (!$selected) { $selected = ['biography']; }
        $panels = [];
        foreach ($selected as $key) {
            if (empty($enabled[$key])) { continue; }
            $opts = array_merge($base, [
                'show_title' => false,
                'show_biography' => $key === 'biography',
                'show_lead' => $key === 'biography' && $on('show_lead'),
                'show_facts' => $key === 'facts',
                'show_statement' => $key === 'statement',
                'show_education' => $key === 'education',
                'show_awards' => $key === 'awards',
                'readmore' => $key === 'biography' && $on('readmore'),
            ]);
            $html = ArtistBio::render($row, $opts);
            if ($html !== '') { $panels[] = ['key' => $key, 'label' => $labels[$key], 'html' => $html]; }
        }
        echo '<div class="art-coa-artist-bio-widget">';
        if ($on('show_title')) {
            $tag = in_array((string) ($s['title_tag'] ?? 'h2'), ['h2', 'h3', 'h4', 'div'], true) ? (string) $s['title_tag'] : 'h2';
            echo '<' . $tag . ' class="art-coa-elementor-title">' . esc_html((string) ($s['title'] ?? 'بیوگرافی')) . '</' . $tag . '>';
        }
        $tab_settings = $s; $tab_settings['force_tabs'] = 'yes';
        $tabs_html = \Artiey_AET_Tabs::render($panels, $tab_settings, 'بخش‌های بیوگرافی هنرمند');
        echo $tabs_html !== '' ? $tabs_html : '<p class="art-coa-empty">برای این هنرمند در بخش‌های انتخاب‌شده اطلاعاتی ثبت نشده است.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated from sanitized renderer panels.
        echo '</div>';
    }

    private function is_edit(): bool
    {
        return class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }
}
