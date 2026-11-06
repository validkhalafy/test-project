<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use WPArtCOA\Shortcodes;

defined('ABSPATH') || exit;

final class Verifier extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string
    {
        return 'art_coa_verifier';
    }

    public function get_title(): string
    {
        return 'تأیید گواهی اصالت';
    }

    public function get_icon(): string
    {
        return 'eicon-check-circle';
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
        return ['certificate', 'coa', 'verify', 'گواهی', 'اصالت'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', [
            'label' => 'محتوا',
        ]);

        $this->add_control('title', [
            'label'   => 'عنوان',
            'type'    => Controls_Manager::TEXT,
            'default' => 'تأیید گواهی اصالت',
        ]);

        $this->end_controls_section();

        $this->register_design_controls('.art-coa-verify', '.art-coa-verify h2', '.art-coa-result', '');
    }

    protected function render(): void
    {
        // Reuse shortcode logic
        $settings = $this->get_settings_for_display();
        echo Shortcodes::verify(['title' => sanitize_text_field((string) ($settings['title'] ?? ''))]);
    }
}
