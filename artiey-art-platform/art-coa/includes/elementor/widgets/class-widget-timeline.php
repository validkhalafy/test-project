<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use WPArtCOA\Calendar;
use WPArtCOA\Timeline;

defined('ABSPATH') || exit;

final class Timeline_Widget extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string { return 'art_coa_timeline'; }
    public function get_title(): string { return 'تایم‌لاین کارت‌محور نمایشگاه‌ها و رویدادها'; }
    public function get_icon(): string { return 'eicon-time-line'; }
    public function get_categories(): array { return ['art-coa', 'artiey-art']; }
    public function get_style_depends(): array { return ['wp-art-coa-front', 'wp-art-coa-dynamic', 'artiey-aet-profile']; }
    public function get_script_depends(): array { return ['artiey-aet-profile']; }
    public function get_keywords(): array { return ['timeline', 'exhibition', 'artfair', 'auction', 'event', 'تایم‌لاین', 'نمایشگاه', 'حراج', 'رویداد']; }

    /** Canonical WordPress galleries, used as the connector for exhibition records. */
    private function gallery_options(): array
    {
        if (class_exists('Artiey_AET_Elementor_Options')) {
            return (array) \Artiey_AET_Elementor_Options::posts('artiey_gallery');
        }
        $rows = get_posts([
            'post_type' => 'artiey_gallery', 'post_status' => 'publish', 'numberposts' => 300,
            'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true,
        ]);
        $options = [];
        foreach ($rows as $row) {
            if ($row instanceof \WP_Post) {
                $options[(string) $row->ID] = (string) $row->post_title;
            }
        }
        return $options;
    }

    private function yes(string $id, string $label, bool $default = true, array $condition = []): void
    {
        $this->add_control($id, ['label' => $label, 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => $default ? 'yes' : ''] + ($condition ? ['condition' => $condition] : []));
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => 'محتوا']);
        $this->yes('show_title', 'نمایش عنوان اصلی');
        $this->add_control('title', ['label' => 'عنوان اصلی تایم‌لاین', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'default' => 'تقویم نمایشگاه‌های هنری', 'description' => 'این فیلد همیشه قابل ویرایش است؛ سوئیچ «نمایش عنوان اصلی» فقط نمایش عنوان را کنترل می‌کند.']);
        $this->add_control('intro_subtitle', ['label' => 'زیرعنوان اصلی تایم‌لاین', 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'dynamic' => ['active' => true], 'default' => '', 'placeholder' => 'توضیح کوتاه درباره نمایشگاه‌ها و رویدادها']);
        $this->add_control('title_tag', ['label' => 'تگ عنوان', 'type' => Controls_Manager::SELECT, 'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div'], 'default' => 'h2']);
        $this->add_control('range', ['label' => 'بازهٔ زمانی', 'type' => Controls_Manager::SELECT, 'options' => ['year' => 'یک سال شمسی', 'all' => 'همهٔ سال‌ها'], 'default' => 'year']);
        $this->add_control('year', ['label' => 'سال شمسی (خالی = امسال)', 'type' => Controls_Manager::NUMBER, 'default' => '', 'dynamic' => ['active' => true], 'condition' => ['range' => 'year']]);
        $this->add_control('type', [
            'label' => 'نوع رویداد', 'type' => Controls_Manager::SELECT,
            'options' => ['all' => 'همه', 'exhibition' => 'نمایشگاه‌ها', 'artfair' => 'آرت‌فرها', 'auction' => 'حراج‌ها'],
            'default' => 'all',
        ]);
        $this->add_control('status', [
            'label' => 'وضعیت', 'type' => Controls_Manager::SELECT,
            'options' => ['all' => 'همه', 'live' => 'فقط در حال برگزاری', 'upcoming' => 'فقط به‌زودی', 'past' => 'فقط برگزارشده‌ها'],
            'default' => 'all',
        ]);
        $this->add_control('gallery_filter', [
            'label' => 'فقط نمایشگاه‌های گالری‌های منتخب (اختیاری)',
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'label_block' => true,
            'options' => $this->gallery_options(),
            'description' => 'برای اتصال، در صفحه «نمایشگاه‌ها» فیلد «گالری برگزارکننده» را انتخاب و ذخیره کنید. این گزینه فقط رویدادهای همان گالری‌ها را فیلتر می‌کند.',
        ]);
        $this->add_control('order', ['label' => 'ترتیب', 'type' => Controls_Manager::SELECT, 'options' => ['desc' => 'جدیدترین ابتدا', 'asc' => 'قدیمی‌ترین ابتدا'], 'default' => 'desc']);
        $this->add_control('limit', ['label' => 'حداکثر تعداد رویداد', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 200, 'default' => 60]);
        $this->add_control('presentation', [
            'label' => 'نحوه ارائه', 'type' => Controls_Manager::SELECT, 'default' => 'single',
            'options' => ['single' => 'تایم‌لاین یکپارچه', 'tabs' => 'تب‌بندی بر اساس وضعیت نمایشگاه'],
        ]);
        $this->add_control('timeline_status_tabs', [
            'label' => 'تب‌های وضعیت', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true,
            'options' => ['live' => 'در حال برگزاری', 'upcoming' => 'به‌زودی', 'past' => 'برگزارشده'],
            'default' => ['live', 'upcoming', 'past'], 'condition' => ['presentation' => 'tabs'],
            'description' => 'برای هر وضعیت انتخاب‌شده یک تب مستقل می‌سازد.',
        ]);
        \Artiey_AET_Tabs::register_behavior_controls($this, 'presentation', 'tabs', ['live' => 'در حال برگزاری', 'upcoming' => 'به‌زودی', 'past' => 'برگزارشده']);
        $this->end_controls_section();

        $this->start_controls_section('layout_section', ['label' => 'چیدمان']);
        $this->add_control('layout', [
            'label' => 'چیدمان تایم‌لاین', 'type' => Controls_Manager::SELECT,
            'options' => ['cards' => 'کارت‌های مدرن', 'line' => 'خط زمانی عمودی', 'alt' => 'دوطرفه (چپ و راست)', 'grid' => 'شبکهٔ پوستری', 'compact' => 'فشرده'],
            'default' => 'cards',
        ]);
        $this->add_responsive_control('columns', [
            'label' => 'تعداد ستون‌ها', 'type' => Controls_Manager::SELECT, 'options' => ['1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3',
            'selectors' => ['{{WRAPPER}} .art-coa-timeline' => '--art-coa-tl-cols: {{VALUE}};'],
            'condition' => ['layout' => 'grid'],
        ]);
        $this->yes('group', 'گروه‌بندی بر اساس ماه');
        $this->yes('show_filters', 'نمایش فیلترهای قابل کلیک');
        $this->yes('show_year', 'نمایش نشان سال');
        $this->add_control('item_tag', ['label' => 'تگ عنوان هر رویداد', 'type' => Controls_Manager::SELECT, 'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'div' => 'div', 'p' => 'p'], 'default' => 'h3']);
        $this->end_controls_section();

        $this->start_controls_section('fields', ['label' => 'اطلاعات هر رویداد']);
        $this->yes('show_poster', 'پوستر رویداد');
        $this->yes('show_kind', 'برچسب نوع (نمایشگاه / آرت‌فر / حراج)');
        $this->yes('show_status', 'برچسب وضعیت (در حال برگزاری / به‌زودی / برگزار شد)', false);
        $this->yes('show_date', 'بازهٔ تاریخ رویداد');
        $this->yes('show_countdown', 'شمارش معکوس (روز تا شروع / پایان)', false);
        $this->yes('show_subtitle', 'توضیح کوتاه / هنرمند');
        $this->yes('show_location', 'محل برگزاری (گالری)', false);
        $this->yes('link_gallery', 'پیوند نام گالری به پروفایل گالری', true, ['show_location' => 'yes']);
        $this->yes('show_button', 'دکمهٔ مشاهده', false);
        $this->add_control('button_text', ['label' => 'متن دکمه', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'default' => 'مشاهدهٔ رویداد', 'condition' => ['show_button' => 'yes']]);
        $this->add_control('link_target', ['label' => 'بازشدن پیوندها', 'type' => Controls_Manager::SELECT, 'options' => ['' => 'همین تب', '_blank' => 'تب جدید'], 'default' => '']);
        $this->add_control('empty_text', ['label' => 'پیام وقتی رویدادی نیست', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'placeholder' => 'رویدادی برای این بازه و فیلتر یافت نشد.']);
        $this->yes('schema', 'داده ساختاریافتهٔ سئو (schema.org Event)');
        $this->end_controls_section();

        // Quick colours (kept from earlier releases so saved designs still apply).
        $this->start_controls_section('timeline_style', ['label' => 'رنگ‌های سریع', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('timeline_card_background', ['label' => 'پس‌زمینه کارت رویداد', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-timeline' => '--art-coa-tl-surface: {{VALUE}};']]);
        $this->add_control('timeline_line_color', ['label' => 'رنگ خط زمانی', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-tl-list.is-line' => 'border-color: {{VALUE}};']]);
        $this->add_control('timeline_meta_color', ['label' => 'رنگ تاریخ و توضیحات', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-tl-date, {{WRAPPER}} .art-coa-tl-sub' => 'color: {{VALUE}};']]);
        $this->end_controls_section();

        $this->start_controls_section('intro_style', ['label' => 'عنوان و زیرعنوان اصلی', 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'intro_title_typography', 'label' => 'تایپوگرافی عنوان اصلی', 'selector' => '{{WRAPPER}} .art-coa-timeline-heading-title']);
        $this->add_control('intro_title_color', ['label' => 'رنگ عنوان اصلی', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-timeline-heading-title' => 'color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'intro_subtitle_typography', 'label' => 'تایپوگرافی زیرعنوان اصلی', 'selector' => '{{WRAPPER}} .art-coa-timeline-intro-subtitle']);
        $this->add_control('intro_subtitle_color', ['label' => 'رنگ زیرعنوان اصلی', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-timeline-intro-subtitle' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('intro_heading_align', ['label' => 'چینش عنوان و زیرعنوان', 'type' => Controls_Manager::SELECT, 'options' => ['' => 'پیش‌فرض', 'right' => 'راست', 'center' => 'وسط', 'left' => 'چپ'], 'selectors' => ['{{WRAPPER}} .art-coa-timeline-heading' => 'text-align: {{VALUE}};']]);
        $this->add_responsive_control('intro_subtitle_gap', ['label' => 'فاصله عنوان تا زیرعنوان', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', 'em'], 'range' => ['px' => ['min' => 0, 'max' => 40], 'em' => ['min' => 0, 'max' => 3]], 'selectors' => ['{{WRAPPER}} .art-coa-timeline-intro-subtitle' => 'margin-top: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('intro_heading_bottom_gap', ['label' => 'فاصله عنوان از محتوای تایم‌لاین', 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', 'em'], 'range' => ['px' => ['min' => 0, 'max' => 80], 'em' => ['min' => 0, 'max' => 5]], 'selectors' => ['{{WRAPPER}} .art-coa-timeline-heading' => 'margin-bottom: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->timeline_style(['live' => 'در حال برگزاری', 'upcoming' => 'به‌زودی', 'past' => 'برگزارشده']);
        \Artiey_AET_Tabs::register_style_controls($this, 'exhibitiontimelinetabs');
        $this->register_design_controls('.art-coa-timeline', '', '', '');
        $this->register_arrow_controls('.art-coa-tl-arrow', 'timeline_arrow', '.art-coa-tl-btn:hover', '.art-coa-tl-btn');
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $flag = static fn(string $k): bool => !empty($s[$k]);
        $show_filters = $flag('show_filters');
        $allowed_types = ['all', 'exhibition', 'artfair', 'auction'];
        $settings_type = sanitize_key((string) ($s['type'] ?? 'all'));
        if (!in_array($settings_type, $allowed_types, true)) { $settings_type = 'all'; }
        $filter_key = substr(sanitize_key((string) $this->get_id()), 0, 16);
        $type_query_key = 'art_coa_tl_' . $filter_key . '_type';
        $year_query_key = 'art_coa_tl_' . $filter_key . '_year';
        $query_year = isset($_GET[$year_query_key]) && is_scalar($_GET[$year_query_key]) ? absint(wp_unslash($_GET[$year_query_key])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $year = !empty($s['year']) ? absint($s['year']) : (($show_filters && $query_year) ? $query_year : Calendar::current_jalali_year());
        $year = max(1300, min(1600, $year));
        $query_type = isset($_GET[$type_query_key]) && is_scalar($_GET[$type_query_key]) ? sanitize_key(wp_unslash($_GET[$type_query_key])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $type = ($show_filters && in_array($query_type, $allowed_types, true)) ? $query_type : $settings_type;

        $timeline_args = [
            'year' => $year, 'range' => (string) ($s['range'] ?? 'year'), 'type' => $type, 'status' => (string) ($s['status'] ?? 'all'),
            'gallery_ids' => array_values(array_unique(array_filter(array_map('absint', is_array($s['gallery_filter'] ?? null) ? $s['gallery_filter'] : [])))), 'link_gallery' => $flag('link_gallery'),
            'order' => (string) ($s['order'] ?? 'desc'), 'limit' => (int) ($s['limit'] ?? 60),
            'layout' => (string) ($s['layout'] ?? 'cards'), 'columns' => (int) ($s['columns'] ?? 3), 'group' => $flag('group'),
            'show_heading' => false, 'show_filters' => $show_filters, 'show_year' => $flag('show_year'),
            'show_poster' => $flag('show_poster'), 'show_kind' => $flag('show_kind'), 'show_status' => $flag('show_status'),
            'show_date' => $flag('show_date'), 'show_countdown' => $flag('show_countdown'), 'show_subtitle' => $flag('show_subtitle'),
            'show_location' => $flag('show_location'), 'show_button' => $flag('show_button'),
            'button_text' => (string) ($s['button_text'] ?? ''), 'link_target' => (string) ($s['link_target'] ?? ''),
            'item_tag' => (string) ($s['item_tag'] ?? 'h3'), 'empty_text' => (string) ($s['empty_text'] ?? ''),
            'schema' => $flag('schema'), 'filter_key' => $filter_key,
        ];
        echo '<div class="art-coa-elementor-widget art-coa-timeline-widget">';
        $heading_title_raw = $s['title'] ?? $s['heading_title'] ?? 'تقویم نمایشگاه‌های هنری';
        $heading_subtitle_raw = $s['intro_subtitle'] ?? $s['heading_subtitle'] ?? '';
        $heading_title = is_scalar($heading_title_raw) ? trim((string) $heading_title_raw) : '';
        $heading_subtitle = is_scalar($heading_subtitle_raw) ? trim((string) $heading_subtitle_raw) : '';
        $show_heading = !empty($s['show_title']) && $heading_title !== '';
        if ($show_heading || $heading_subtitle !== '') {
            echo '<div class="art-coa-timeline-heading" dir="rtl">';
            if ($show_heading) {
                $tag = in_array(($s['title_tag'] ?? 'h2'), ['h1', 'h2', 'h3', 'h4', 'div'], true) ? (string) $s['title_tag'] : 'h2';
                echo '<' . $tag . ' class="art-coa-elementor-title art-coa-timeline-heading-title">' . esc_html($heading_title) . '</' . $tag . '>';
            }
            if ($heading_subtitle !== '') {
                echo '<p class="art-coa-timeline-intro-subtitle">' . esc_html($heading_subtitle) . '</p>';
            }
            echo '</div>';
        }
        if (($s['presentation'] ?? 'single') === 'tabs') {
            $allowed_statuses = ['live', 'upcoming', 'past'];
            $selected_statuses = array_values(array_intersect($allowed_statuses, array_map('sanitize_key', (array) ($s['timeline_status_tabs'] ?? ['live', 'upcoming', 'past']))));
            $labels = ['live' => 'در حال برگزاری', 'upcoming' => 'به‌زودی', 'past' => 'برگزارشده'];
            $panels = [];
            foreach ($selected_statuses as $tab_status) {
                $tab_args = $timeline_args;
                $tab_args['status'] = $tab_status;
                // Each tab gets its own result set; avoid duplicating interactive filters inside every panel.
                $tab_args['show_filters'] = false;
                $tab_args['filter_key'] = $filter_key . '_' . $tab_status;
                $panel_html = Timeline::render($tab_args);
                if (is_string($panel_html) && trim($panel_html) !== '') {
                    $panels[] = ['key' => $tab_status, 'label' => $labels[$tab_status], 'html' => $panel_html];
                }
            }
            $tab_settings = $s;
            $tab_settings['force_tabs'] = 'yes';
            $tabs_html = \Artiey_AET_Tabs::render($panels, $tab_settings, 'وضعیت نمایشگاه‌ها');
            echo $tabs_html !== '' ? $tabs_html : '<p class="art-coa-empty">برای وضعیت‌های انتخاب‌شده رویدادی یافت نشد.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            $html = Timeline::render($timeline_args);
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Timeline::render escapes all dynamic source values.
        }
        echo '</div>';
    }
}
