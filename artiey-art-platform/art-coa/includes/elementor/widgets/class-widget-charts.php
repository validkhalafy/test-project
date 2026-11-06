<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use WPArtCOA\ChartKit;
use WPArtCOA\I18n;

defined('ABSPATH') || exit;

/**
 * Charts for an artist, a gallery or the platform: activity, progress, collection growth, status mix,
 * profile completeness and (for the owner / admins only) sales.
 */
final class Charts_Widget extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string
    {
        return 'art_coa_charts';
    }

    public function get_title(): string
    {
        return 'نمودار پیشرفت، فعالیت و فروش';
    }

    public function get_icon(): string
    {
        return 'eicon-chart';
    }

    public function get_categories(): array
    {
        return ['art-coa', 'artiey-art'];
    }

    public function get_keywords(): array
    {
        return ['chart', 'graph', 'sales', 'progress', 'activity', 'artist', 'نمودار', 'فروش', 'پیشرفت', 'فعالیت', 'هنرمند'];
    }

    public function get_style_depends(): array
    {
        return ['wp-art-coa-front', 'wp-art-coa-dynamic', 'artiey-aet-profile'];
    }

    public function get_script_depends(): array
    {
        return ['wp-art-coa-charts', 'artiey-aet-profile'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => 'نمودار']);

        $this->add_control('chart_layout', ['label' => 'چیدمان نمودارها', 'type' => Controls_Manager::SELECT, 'options' => ['single' => 'نمودار تکی / مجموعه پیش‌فرض', 'tabs' => 'چند نمودار در تب‌های مستقل'], 'default' => 'single']);
        $this->add_control('charts_in_tabs', ['label' => 'نمودارهای قابل نمایش در تب‌ها', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => ['activity' => 'فعالیت ماهانه', 'progress' => 'پیشرفت سالانه', 'growth' => 'رشد مجموعه آثار', 'status' => 'وضعیت آثار', 'completeness' => 'کامل‌بودن پروفایل', 'sales' => 'تعداد فروش', 'sales_value' => 'ارزش فروش'], 'default' => ['activity', 'progress', 'growth', 'status'], 'condition' => ['chart_layout' => 'tabs']]);
        \Artiey_AET_Tabs::register_behavior_controls($this, 'chart_layout', 'tabs', ['activity' => 'فعالیت ماهانه', 'progress' => 'پیشرفت سالانه', 'growth' => 'رشد مجموعه آثار', 'status' => 'وضعیت آثار', 'completeness' => 'کامل‌بودن پروفایل', 'sales' => 'تعداد فروش', 'sales_value' => 'ارزش فروش']);
        $this->add_control('tabs_heading', ['label' => 'عنوان بالای تب‌های نمودار (اختیاری)', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'condition' => ['chart_layout' => 'tabs']]);

        $this->add_control('chart', [
            'label'   => 'نوع نمودار',
            'type'    => Controls_Manager::SELECT,
            'default' => 'panel',
            'options' => [
                'panel'        => 'مجموعهٔ کامل (مناسب پروفایل هنرمند یا گالری)',
                'activity'     => 'فعالیت ماهانه (آثار جدید، نمایشگاه، گواهی)',
                'progress'     => 'پیشرفت: تعداد آثار هر سال (هنرمند)',
                'growth'       => 'رشد مجموعه آثار (هنرمند)',
                'status'       => 'وضعیت آثار (منتشرشده، فروخته‌شده، …)',
                'completeness' => 'کامل‌بودن پروفایل (فقط مالک و مدیر)',
                'sales'        => 'فروش ماهانه — تعداد (فقط مالک و مدیر)',
                'sales_value'  => 'فروش ماهانه — ارزش (فقط مالک و مدیر)',
            ],
            'condition' => ['chart_layout' => 'single'],
        ]);
        $this->entity_picker('artist', 'artist', 'هنرمند', 'صفحهٔ جاری (یا کل پلتفرم)');
        $this->entity_picker('gallery', 'gallery', 'گالری', 'بدون گالری');
        $this->add_control('months', [
            'label' => 'بازهٔ زمانی (ماه شمسی)', 'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 3, 'max' => 36]], 'default' => ['size' => 12],
            'condition' => ['chart!' => ['progress', 'growth', 'status', 'completeness']],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('chart_display', ['label' => 'نمایش، عنوان و دسترس‌پذیری', 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('heading', ['label' => 'عنوان سفارشی', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'placeholder' => 'خالی = عنوان پیش‌فرض', 'condition' => ['chart!' => 'panel']]);
        $this->add_control('show_title', ['label' => 'نمایش عنوان', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => ['chart!' => 'panel']]);
        $this->add_control('show_legend', ['label' => 'نمایش راهنمای رنگ‌ها', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        $this->add_control('show_table', ['label' => 'جدول داده برای دسترس‌پذیری و سئو', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        $this->add_control('height', [
            'label' => 'ارتفاع نمودار', 'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 180, 'max' => 520]], 'default' => ['size' => 280],
        ]);
        $this->add_control('empty_text', ['label' => 'پیام وقتی داده‌ای نیست', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'placeholder' => 'هنوز داده‌ای برای این نمودار ثبت نشده است.']);
        $this->add_control('note', [
            'type' => Controls_Manager::RAW_HTML, 'content_classes' => 'elementor-descriptor',
            'raw' => 'نمودارهای فروش و کامل‌بودن پروفایل فقط برای مدیر سایت و صاحب پروفایل (کاربر متصل به هنرمند یا گالری) نمایش داده می‌شوند و برای بازدیدکنندهٔ عادی دیده نمی‌شوند.',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('palette', ['label' => 'رنگ نمودارها', 'tab' => Controls_Manager::TAB_STYLE]);
        $names = [1 => 'سری ۱ (آبی)', 2 => 'سری ۲ (کهربایی)', 3 => 'سری ۳ (سبز)', 4 => 'سری ۴ (قرمز)', 5 => 'سری ۵ (بنفش)', 6 => 'سری ۶ (خاکستری)'];
        foreach ($names as $i => $label) {
            $this->add_control('c' . $i, [
                'label' => $label, 'type' => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .art-coa-chart' => '--ac-c' . $i . ': {{VALUE}};'],
            ]);
        }
        $this->add_control('c_grid', ['label' => 'رنگ خطوط شبکه', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-chart' => '--ac-grid: {{VALUE}};']]);
        $this->add_control('c_axis', ['label' => 'رنگ برچسب محورها', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-chart' => '--ac-axis: {{VALUE}};']]);
        $this->add_control('c_card', ['label' => 'پس‌زمینهٔ کارت نمودار', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-chart' => '--ac-card: {{VALUE}};']]);
        $this->add_control('c_kpi', ['label' => 'رنگ عددهای شاخص‌ها', 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .art-coa-chart-kpi strong' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('panel_columns', [
            'label' => 'ستون‌های مجموعهٔ نمودارها', 'type' => Controls_Manager::SELECT,
            'options' => ['' => 'خودکار', '1' => '۱', '2' => '۲', '3' => '۳'],
            'selectors' => ['{{WRAPPER}} .art-coa-charts-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));'],
            'condition' => ['chart' => 'panel'],
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'kpi_typography', 'label' => 'تایپوگرافی شاخص‌ها', 'selector' => '{{WRAPPER}} .art-coa-chart-kpi strong']);
        $this->end_controls_section();

        \Artiey_AET_Tabs::register_style_controls($this, 'chartspro');
        $this->register_design_controls('.art-coa-chart', '.art-coa-chart-title', '', '.art-coa-charts-grid');
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $type = sanitize_key((string) ($s['chart'] ?? 'panel'));
        $months = max(3, min(36, (int) ($s['months']['size'] ?? 12)));
        $o = [
            'months'     => $months,
            'height'     => max(160, min(600, (int) ($s['height']['size'] ?? 280))),
            'legend'     => !empty($s['show_legend']),
            'table'      => !empty($s['show_table']),
            'empty_text' => sanitize_text_field((string) ($s['empty_text'] ?? '')),
        ];
        $artist  = !empty($s['artist']) ? (string) absint($s['artist']) : '';
        $gallery = !empty($s['gallery']) ? (string) absint($s['gallery']) : '';

        if (($s['chart_layout'] ?? 'single') === 'tabs') {
            $allowed = ['activity', 'progress', 'growth', 'status', 'completeness', 'sales', 'sales_value'];
            $selected = array_values(array_intersect($allowed, array_map('sanitize_key', (array) ($s['charts_in_tabs'] ?? []))));
            $labels = ['activity' => 'فعالیت ماهانه', 'progress' => 'پیشرفت سالانه', 'growth' => 'رشد آثار', 'status' => 'وضعیت آثار', 'completeness' => 'کامل‌بودن پروفایل', 'sales' => 'تعداد فروش', 'sales_value' => 'ارزش فروش'];
            $panels = [];
            foreach ($selected as $chart_type) {
                $args = $o + [
                    'type' => $chart_type,
                    'artist' => $artist,
                    'gallery' => $gallery,
                    'platform' => ($artist === '' && $gallery === ''),
                    'heading' => '',
                    'title' => false,
                ];
                $chart_html = ChartKit::render($args);
                if ($chart_html !== '') {
                    $panels[] = ['key' => $chart_type, 'label' => $labels[$chart_type], 'html' => $chart_html];
                }
            }
            if (!empty($s['tabs_heading'])) {
                echo '<h2 class="art-coa-elementor-title">' . esc_html(sanitize_text_field((string) $s['tabs_heading'])) . '</h2>';
            }
            $tabs_html = \Artiey_AET_Tabs::render($panels, $s, 'نمودارهای آرتی و COA');
            if ($tabs_html !== '') {
                echo $tabs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ChartKit renders escaped data.
            } else {
                echo $this->empty_note($o['empty_text']);
            }
            return;
        }

        if ($type === 'panel') {
            $subject = $artist !== '' ? ChartKit::resolve('artist', $artist) : null;
            $html = '';
            if ($subject) {
                $html = ChartKit::artist_panel($subject, $o);
            } elseif ($gallery !== '' && ($g = ChartKit::resolve('gallery', $gallery))) {
                $html = ChartKit::gallery_panel($g, $o);
            } elseif ($artist === '' && $gallery === '') {
                // Current profile page; on any other page show the platform-wide activity and status mix.
                if ($a = ChartKit::resolve('artist', '')) {
                    $html = ChartKit::artist_panel($a, $o);
                } elseif ($g = ChartKit::resolve('gallery', '')) {
                    $html = ChartKit::gallery_panel($g, $o);
                } else {
                    $html = '<div class="art-coa-charts-grid art-coa-charts-grid--public">'
                        . ChartKit::render(['type' => 'activity', 'platform' => true] + $o)
                        . ChartKit::render(['type' => 'status', 'platform' => true] + $o) . '</div>';
                }
            }
            echo $html !== '' ? $html : $this->empty_note($o['empty_text']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ChartKit.
            return;
        }

        $args = $o + [
            'type' => $type, 'artist' => $artist, 'gallery' => $gallery, 'platform' => false,
            'heading' => sanitize_text_field((string) ($s['heading'] ?? '')), 'title' => !empty($s['show_title']),
        ];
        $html = ChartKit::render($args);
        if ($html === '' && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            $html = '<p class="art-coa-empty">' . esc_html('این نمودار برای این هنرمند/گالری یا برای شما قابل نمایش نیست (مثلاً فروش فقط برای مالک و مدیر).') . '</p>';
        }
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ChartKit.
    }

    private function empty_note(string $text): string
    {
        if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return '<p class="art-coa-empty">' . esc_html('هنرمند یا گالری را انتخاب کنید، یا ویجت را داخل صفحهٔ پروفایل بگذارید.') . '</p>';
        }
        return $text !== '' ? '<p class="art-coa-empty">' . esc_html($text) . '</p>' : '';
    }
}
