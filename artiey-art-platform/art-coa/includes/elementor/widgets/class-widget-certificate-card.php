<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use WPArtCOA\Shortcodes;

defined('ABSPATH') || exit;

final class Certificate_Card extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string
    {
        return 'art_coa_certificate_card';
    }

    public function get_title(): string
    {
        return 'کارت گواهی اصالت';
    }

    public function get_icon(): string
    {
        return 'eicon-document-file';
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
        return ['certificate', 'coa', 'card', 'گواهی'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', [
            'label' => 'محتوا',
        ]);

        $this->add_control('heading', ['label' => 'عنوان (اختیاری)', 'type' => Controls_Manager::TEXT, 'default' => '', 'dynamic' => ['active' => true]]);

        $this->add_control('serial', [
            'label'       => 'شماره سریال',
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'COA-2026-XXXXXX',
            'default'     => '',
            'dynamic'     => ['active' => true],
        ]);
        $this->add_control('serial_from_query', [
            'label' => 'اگر خالی بود، سریال را از نشانی بخوان (?serial=)', 'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes', 'default' => '',
        ]);

        $this->end_controls_section();

        $this->register_design_controls('.art-coa-cert-card', '.art-coa-elementor-title', '.art-coa-cert-card', '');
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $serial   = $settings['serial'] ?? '';
        if ($serial === '' && !empty($settings['serial_from_query']) && isset($_GET['serial']) && is_scalar($_GET['serial'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only lookup, sanitised below.
            $serial = wp_unslash((string) $_GET['serial']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        $heading = sanitize_text_field((string) ($settings['heading'] ?? ''));
        if ($heading !== '') { echo '<h2 class="art-coa-elementor-title">' . esc_html($heading) . '</h2>'; }
        echo Shortcodes::certificate_card(['serial' => sanitize_text_field((string) $serial)]);
    }
}
