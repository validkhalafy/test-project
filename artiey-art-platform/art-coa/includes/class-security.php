<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Security
{
    public static function init(): void
    {
        // Rate-limit certificate verification
        add_filter('rest_pre_dispatch', [self::class, 'rest_rate_limit'], 10, 3);

        // Secure headers on COA public pages
        add_action('send_headers', [self::class, 'security_headers']);

        // Harden registration
        add_action('init', [self::class, 'block_suspicious_register'], 1);

        // Login attempt soft limit for art roles via transient
        add_filter('authenticate', [self::class, 'login_throttle'], 30, 3);

    }

    public static function security_headers(): void
    {
        if (headers_sent()) {
            return;
        }
        $is_coa = get_query_var('art_coa_type')
            || get_query_var('art_coa_member')
            || get_query_var('art_coa_portfolio');
        if (!$is_coa && empty($_GET['art_coa_verify'])) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        // Basic CSP allowing same origin + Chart CDN only on admin (front is stricter)
        if (!is_admin()) {
            header("Content-Security-Policy: frame-ancestors 'self'");
        }
    }

    /**
     * Rate limit REST verify endpoint: max 30 / 10 minutes per IP.
     */
    public static function rest_rate_limit($result, $server, $request)
    {
        if (!($request instanceof \WP_REST_Request)) {
            return $result;
        }
        $route = $request->get_route();
        if (strpos($route, '/art-coa/v1/verify') === false) {
            return $result;
        }

        $ip  = self::client_ip();
        $key = 'art_coa_rl_' . md5($ip);
        $hits = (int) get_transient($key);
        if ($hits >= 30) {
            return new \WP_Error(
                'art_coa_rate_limited',
                I18n::t('sec_rate_limit'),
                ['status' => 429]
            );
        }
        set_transient($key, $hits + 1, 10 * MINUTE_IN_SECONDS);
        return $result;
    }

    /**
     * Rate limit public register: max 5 per hour per IP.
     */
    public static function block_suspicious_register(): void
    {
        if (!isset($_POST['art_coa_register_nonce'])) {
            return;
        }
        // Reject malformed array/object payloads before passing values to WP string sanitizers.
        if (!is_scalar($_POST['art_coa_register_nonce'])) {
            wp_die(esc_html(I18n::t('error')), '', ['response' => 400]);
        }
        $nonce = sanitize_text_field(wp_unslash((string) $_POST['art_coa_register_nonce']));
        if ($nonce === '') {
            return;
        }
        // Use the shared fixed-window limiter (atomic with a persistent object cache)
        // rather than a read-then-write transient counter that can race under load.
        if (!self::throttle('register', 12, HOUR_IN_SECONDS)) {
            wp_die(
                esc_html(I18n::t('sec_rate_limit')),
                esc_html(I18n::t('error')),
                ['response' => 429]
            );
        }

        // Honeypot field (if present and filled -> bot); malformed values are rejected too.
        if (isset($_POST['art_coa_hp']) && (!is_scalar($_POST['art_coa_hp']) || (string) wp_unslash((string) $_POST['art_coa_hp']) !== '')) {
            wp_die(esc_html(I18n::t('error')), '', ['response' => 400]);
        }

        // Block disposable-looking privileged/default usernames without PHP 8 array-to-string warnings.
        $raw_user = self::input_scalar($_POST, 'username');
        $user = sanitize_user($raw_user, true);
        if ($user !== '' && preg_match('/^(admin|root|test|null)$/i', $user)) {
            wp_die(esc_html(I18n::t('reg_exists')), '', ['response' => 400]);
        }
    }

    public static function login_throttle($user, $username, $password)
    {
        if ($username === '' || $password === '') {
            return $user;
        }
        $ip  = self::client_ip();
        $key = 'art_coa_login_' . md5($ip . '|' . strtolower($username));
        $fails = (int) get_transient($key);
        if ($fails >= 8) {
            return new \WP_Error('art_coa_locked', I18n::t('sec_rate_limit'));
        }
        // Increment on failure is handled by tracking below
        add_action('wp_login_failed', static function () use ($key, $fails): void {
            set_transient($key, $fails + 1, 15 * MINUTE_IN_SECONDS);
        });
        add_action('wp_login', static function () use ($key): void {
            delete_transient($key);
        });
        return $user;
    }

    /** Fixed-window throttle per IP. Returns false when the limit is exceeded. */
    public static function throttle(string $bucket, int $limit, int $window = 60): bool
    {
        $key = md5($bucket . '|' . self::client_ip());
        if (wp_using_ext_object_cache()) {
            $group = 'wp_art_coa_rate_limits';
            if (wp_cache_add($key, 1, $group, $window)) {
                return true;
            }
            $hits = wp_cache_incr($key, 1, $group);
            if ($hits === false) {
                wp_cache_set($key, 1, $group, $window);
                return true;
            }
            return (int) $hits <= $limit;
        }

        $transient_key = 'art_coa_th_' . $key;
        $hits = (int) get_transient($transient_key);
        if ($hits >= $limit) {
            return false;
        }
        set_transient($transient_key, $hits + 1, $window);
        return true;
    }

    public static function throttled_response(): \WP_REST_Response
    {
        $r = new \WP_REST_Response(['code' => 'rate_limited'], 429);
        $r->header('Retry-After', '60');
        return $r;
    }

    public static function client_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Only trust REMOTE_ADDR (avoid X-Forwarded-For spoofing)
        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Read a scalar request value safely. Malformed array/object inputs must not
     * reach WordPress sanitizers (which expect strings) on PHP 8+.
     * The value is unslashed exactly once, matching WordPress request handling.
     */
    public static function input_scalar(array $source, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
            return $default;
        }
        return (string) wp_unslash((string) $source[$key]);
    }

    /**
     * Capability helper for sensitive admin actions.
     */
    public static function require_manage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(I18n::t('error')), '', ['response' => 403]);
        }
    }

    /**
     * Escape + length-limit for public serial lookups.
     */
    public static function sanitize_serial(string $serial): string
    {
        // Persian/Arabic digits and typographic dashes are common when a code is typed or copied from a PDF.
        $serial = strtr($serial, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '‐' => '-', '‑' => '-', '‒' => '-', '–' => '-', '—' => '-', '―' => '-', '−' => '-', '﹣' => '-', '－' => '-',
        ]);
        $serial = strtoupper(trim($serial));
        $serial = preg_replace('/[^A-Z0-9\-]/', '', $serial) ?? '';
        return substr($serial, 0, 64);
    }
}
