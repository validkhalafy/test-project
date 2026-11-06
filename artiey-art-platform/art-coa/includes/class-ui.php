<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Loads the shared design system stylesheet (tokens, accessibility base, utilities) for every module,
 * on the front end only when one of the modules' stylesheets is present, and in the admin only on our screens.
 */
final class UI
{
    public const HANDLE = 'artiey-ui-system';

    /** Handles of the module stylesheets that mean "an Artiey page is being rendered". */
    private const MODULE_STYLES = [
        'wp-art-coa-front', 'wp-art-coa-dynamic', 'artiey-aet', 'artiey-aet-profile',
        'wp-coa-pro-components', 'wp-coa-pro-public', 'wp-art-coa-member-forms',
    ];

    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'front'], 99);
        add_action('wp_footer', [self::class, 'front'], 1);
        add_action('admin_enqueue_scripts', [self::class, 'admin']);
    }

    private static function url(): string
    {
        return plugins_url('assets/css/ui-system.css', dirname(__DIR__, 2) . '/artiey-art-platform.php');
    }

    private static function ver(): string
    {
        return defined('AET_VERSION') ? (string) AET_VERSION : WP_ART_COA_VERSION;
    }

    public static function front(): void
    {
        if (wp_style_is(self::HANDLE, 'enqueued')) {
            return;
        }
        foreach (self::MODULE_STYLES as $h) {
            if (wp_style_is($h, 'enqueued')) {
                wp_enqueue_style(self::HANDLE, self::url(), self::deps(), self::ver());
                return;
            }
        }
    }

    public static function admin(string $hook): void
    {
        $ours = false;
        foreach (['artiey', 'wp-art-coa', 'wp-coa'] as $needle) {
            if (strpos($hook, $needle) !== false) {
                $ours = true;
                break;
            }
        }
        if (!$ours && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            $ours = $screen && !empty($screen->post_type) && strpos((string) $screen->post_type, 'artiey_') === 0;
        }
        if ($ours) {
            wp_enqueue_style(self::HANDLE, self::url(), [], self::ver());
        }
    }

    /** @return string[] module styles that are actually enqueued, so this file always prints after them */
    private static function deps(): array
    {
        $d = [];
        foreach (self::MODULE_STYLES as $h) {
            if (wp_style_is($h, 'enqueued')) {
                $d[] = $h;
            }
        }
        return $d;
    }
}
