<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use WPArtCOA\Calendar;
use WPArtCOA\Shortcodes;

defined('ABSPATH') || exit;

final class Calendar_Widget extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string
    {
        return 'art_coa_calendar';
    }

    public function get_title(): string
    {
        return 'تقویم نمایشگاه گالری‌ها';
    }

    public function get_icon(): string
    {
        return 'eicon-calendar';
    }

    public function get_categories(): array
    {
        return ['art-coa'];
    }

    public function get_style_depends(): array
    {
        return ['wp-art-coa-front', 'wp-art-coa-dynamic'];
    }

    public function get_keywords(): array
    {
        return ['calendar', 'exhibition', 'gallery', 'تقویم', 'نمایشگاه', 'گالری'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => 'تنظیمات']);

        $this->add_control('show_title', ['label' => 'نمایش عنوان', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        $this->add_control('title', ['label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'default' => 'تقویم نمایشگاه‌ها', 'condition' => ['show_title' => 'yes']]);

        $this->add_control('year', [
            'label'       => 'سال شمسی (خالی = امسال)',
            'type'        => Controls_Manager::NUMBER,
            'default'     => '',
        ]);

        $this->add_control('month', [
            'label'   => 'ماه شمسی',
            'type'    => Controls_Manager::SELECT,
            'options' => [
                ''  => 'ماه جاری',
                '1' => 'فروردین', '2' => 'اردیبهشت', '3' => 'خرداد',
                '4' => 'تیر', '5' => 'مرداد', '6' => 'شهریور',
                '7' => 'مهر', '8' => 'آبان', '9' => 'آذر',
                '10'=> 'دی', '11'=> 'بهمن', '12'=> 'اسفند',
            ],
            'default' => '',
        ]);

        $this->end_controls_section();

        $this->register_design_controls('.art-coa-calendar', '.art-coa-elementor-title', '.art-coa-cal-bar', '');
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $args = [];
        if (!empty($s['year'])) {
            $args['year'] = max(1300, min(1600, absint($s['year'])));
        }
        if (!empty($s['month'])) {
            $args['month'] = max(1, min(12, absint($s['month'])));
        }
        if (!empty($s['show_title'])) {
            echo '<div class="art-coa-elementor-widget"><h2 class="art-coa-elementor-title">' . esc_html((string) ($s['title'] ?? 'تقویم نمایشگاه‌ها')) . '</h2>';
            echo Calendar::render($args) . '</div>';
        } else {
            echo Calendar::render($args);
        }
    }
}
