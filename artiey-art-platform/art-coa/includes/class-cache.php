<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Cache
{
    private const GROUP = 'wp_art_coa';
    private const TTL   = 300; // 5 minutes

    /** Marker that lets false/null/0/'' be cached: get_transient() returns false for a miss. */
    private const WRAP_KEY = '__art_coa_cached';

    public static function get(string $key, callable $callback, int $ttl = self::TTL)
    {
        $settings = get_option('wp_art_coa_settings', []);
        if (!empty($settings['cache_ttl'])) {
            $ttl = max(60, (int) $settings['cache_ttl']);
        }
        $found = false;
        $value = wp_cache_get($key, self::GROUP, false, $found);
        if ($found) {
            return self::unwrap($value);
        }

        // Transient fallback (object cache may be empty)
        $transient = get_transient('art_coa_' . $key);
        if ($transient !== false) {
            wp_cache_set($key, $transient, self::GROUP, $ttl);
            return self::unwrap($transient);
        }

        $value   = $callback();
        $wrapped = [self::WRAP_KEY => 1, 'v' => $value];
        wp_cache_set($key, $wrapped, self::GROUP, $ttl);
        set_transient('art_coa_' . $key, $wrapped, $ttl);
        return $value;
    }

    /** Accept both wrapped entries and plain values written by earlier plugin versions. */
    private static function unwrap($stored)
    {
        if (is_array($stored) && isset($stored[self::WRAP_KEY]) && array_key_exists('v', $stored)) {
            return $stored['v'];
        }
        return $stored;
    }

    public static function flush_stats(): void
    {
        $stats_version = (int) get_option('wp_art_coa_stats_cache_version', 1);
        update_option('wp_art_coa_stats_cache_version', $stats_version + 1, false);
        $keys = [
            'global_stats',
            'global_stats_admin',
            'global_stats_public',
            'monthly_sales_12',
            'trend_summary',
            'recent_sales_10',
            'auction_overview',
            'auction_monthly_12',
            'auction_daily_bids_14',
        ];
        foreach ($keys as $key) {
            wp_cache_delete($key, self::GROUP);
            delete_transient('art_coa_' . $key);
        }
        self::forget_catalog();
    }

    public static function stats_version(): int
    {
        return (int) get_option('wp_art_coa_stats_cache_version', 1);
    }

    /** Bump the anonymous catalog cache namespace; old entries expire naturally. */
    public static function forget_catalog(): void
    {
        $version = (int) get_option('wp_art_coa_catalog_cache_version', 1);
        update_option('wp_art_coa_catalog_cache_version', $version + 1, false);
        self::forget_facets();
    }

    /** Drop cached catalog filter lists (they are rebuilt on next view). */
    public static function forget_facets(): void
    {
        foreach (['artworks', 'artists', 'galleries', 'exhibitions'] as $t) {
            wp_cache_delete('facets_' . $t, self::GROUP);
            delete_transient('art_coa_facets_' . $t);
        }
    }

    public static function init(): void
    {
        // Bust cache when data changes
        $actions = [
            'wp_art_coa_certificate_issued',
        ];
        foreach ($actions as $action) {
            add_action($action, [self::class, 'flush_stats']);
        }

        // Also flush on admin saves via shutdown if our tables were written
        add_action('admin_init', static function (): void {
            if (!empty($_POST['art_coa_action'])) {
                add_action('shutdown', [self::class, 'flush_stats']);
            }
        });
    }
}
