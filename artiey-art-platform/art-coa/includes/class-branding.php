<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Branding
{
    public static function settings(): array
    {
        $s = get_option('wp_art_coa_settings', []);
        return [
            'primary_color'   => $s['primary_color'] ?? '#1a365d',
            'secondary_color' => $s['secondary_color'] ?? '#b32d2e',
            'accent_color'    => $s['accent_color'] ?? '#c9a227',
            'site_logo_url'   => $s['site_logo_url'] ?? '',
            'admin_sign_url'  => $s['admin_sign_url'] ?? '',
            'admin_sign_name' => $s['admin_sign_name'] ?? '',
            'admin_sign_title'=> $s['admin_sign_title'] ?? '',
            'cert_footer'     => $s['cert_footer'] ?? '',
        ];
    }

    public static function css_variables(?object $gallery = null): string
    {
        $b = self::settings();
        $primary = $b['primary_color'];
        $secondary = $b['secondary_color'];
        $accent = $b['accent_color'];

        // Gallery can override primary with its brand color if set
        if ($gallery && !empty($gallery->brand_color)) {
            $primary = $gallery->brand_color;
        }

        return sprintf(
            ':root{--art-coa-primary:%s;--art-coa-secondary:%s;--art-coa-accent:%s;--art-coa-primary-soft:%s22;}',
            esc_attr($primary),
            esc_attr($secondary),
            esc_attr($accent),
            esc_attr($primary)
        );
    }

    public static function inject_front_css(): void
    {
        if (!get_query_var('art_coa_type') && !get_query_var('art_coa_member') && !get_query_var('art_coa_portfolio') && empty($_GET['art_coa_verify'])) {
            // Still inject soft brand on pages with our shortcodes — handled via Assets
            return;
        }
        echo '<style id="art-coa-brand">' . self::css_variables() . '</style>';
    }
}
