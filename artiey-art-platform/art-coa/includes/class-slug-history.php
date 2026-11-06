<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Remembers renamed slugs of mirrored content so an old /art-coa/... link answers with a 301 to the new one
 * instead of a 404 (links shared on social media, search-engine results, bookmarks).
 */
final class SlugHistory
{
    private const OPTION = 'art_coa_slug_history';
    private const LIMIT  = 1500;

    /** Table for each profile type served under /art-coa/{type}/{slug}. */
    public const TABLES = [
        'artist' => 'artists', 'curator' => 'curators', 'gallery' => 'galleries',
        'exhibition' => 'exhibitions', 'artwork' => 'artworks', 'collector' => 'collectors',
    ];

    public static function record(string $table, string $old, string $new): void
    {
        if ($old === '' || $new === '' || $old === $new) {
            return;
        }
        $h = (array) get_option(self::OPTION, []);
        foreach ($h as $k => $target) {
            // Chains collapse: a -> b and now b -> c becomes a -> c.
            if ($target === $new && str_starts_with((string) $k, $table . ':')) {
                unset($h[$k]);
            } elseif ($target === $old && str_starts_with((string) $k, $table . ':')) {
                $h[$k] = $new;
            }
        }
        unset($h[$table . ':' . $new]); // returning to an older slug cancels its redirect
        $h[$table . ':' . $old] = $new;
        if (count($h) > self::LIMIT) {
            $h = array_slice($h, -self::LIMIT, null, true);
        }
        update_option(self::OPTION, $h, false);
    }

    public static function resolve(string $table, string $old): string
    {
        $h = (array) get_option(self::OPTION, []);
        return (string) ($h[$table . ':' . $old] ?? '');
    }

    /** Row with this slug (id + current slug), tolerant of case and of encoded Persian text. */
    public static function find(string $table, string $raw): ?object
    {
        global $wpdb;
        $t = Database::table($table);
        $candidates = array_unique(array_filter([sanitize_title($raw), strtolower($raw), $raw]));
        foreach ($candidates as $c) {
            $columns = $table === 'collectors' ? 'id, slug, status, is_public' : 'id, slug, status';
            $row = $wpdb->get_row($wpdb->prepare("SELECT {$columns} FROM {$t} WHERE slug = %s", $c));
            if ($row) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Called before an /art-coa/{type}/{slug} page renders.
     * Redirects renamed or differently-cased slugs with 301 and answers a real 404 for unknown ones.
     * Returns true when the page may render.
     */
    /** Whether an entity row is safe to expose to a public visitor. */
    public static function is_public_row(string $type, object $row, bool $is_admin = false): bool
    {
        if ($is_admin) {
            return true;
        }

        $status = sanitize_key((string) ($row->status ?? ''));
        $allowed = match ($type) {
            'artist', 'curator', 'gallery' => ['active'],
            'exhibition' => ['published', 'active'],
            'artwork' => ['approved', 'published', 'active', 'sold'],
            'collector' => ['active'],
            default => [],
        };
        if (!$allowed || !in_array($status, $allowed, true)) {
            return false;
        }

        // A collector's profile is public only when the explicit privacy switch is on.
        if ($type === 'collector' && (int) ($row->is_public ?? 0) !== 1) {
            return false;
        }
        return true;
    }

    private static function mark_not_found(): void
    {
        global $wp_query;
        if (is_object($wp_query) && method_exists($wp_query, 'set_404')) {
            $wp_query->set_404();
        }
        status_header(404);
        add_filter('wp_robots', 'wp_robots_no_robots');
    }

    public static function guard(string $type, string $raw): bool
    {
        $table = self::TABLES[$type] ?? '';
        if ($table === '') {
            return true;
        }
        $row = self::find($table, $raw);
        $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
        if ($row) {
            if (!self::is_public_row($type, $row, $is_admin)) {
                self::mark_not_found();
                return false;
            }
            if (rawurldecode((string) $row->slug) !== $raw) {
                self::redirect(home_url('/art-coa/' . $type . '/' . $row->slug));
            }
            return true;
        }
        $new = self::resolve($table, sanitize_title($raw)) ?: self::resolve($table, $raw);
        if ($new !== '') {
            $target = self::find($table, $new);
            if ($target && self::is_public_row($type, $target, $is_admin)) {
                self::redirect(Seo::canonical_url($type, $new));
            }
        }
        self::mark_not_found();
        return false;
    }

    private static function redirect(string $url): void
    {
        wp_safe_redirect($url, 301, 'Artiey');
        exit;
    }
}
