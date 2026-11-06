<?php
declare(strict_types=1);

namespace WPArtCOA\Rest;

use WPArtCOA\Database;
use WPArtCOA\Entities\Certificate;

defined('ABSPATH') || exit;

final class Api
{
    public static function register(): void
    {
        register_rest_route('art-coa/v1', '/verify/(?P<serial>[A-Za-z0-9\-]+)', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'verify'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('art-coa/v1', '/auction/(?P<id>\d+)/state', [
            'methods'             => 'GET',
            'callback'            => [\WPArtCOA\Auction::class, 'rest_state'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('art-coa/v1', '/auction/(?P<id>\d+)/bid', [
            'methods'             => 'POST',
            'callback'            => [\WPArtCOA\Auction::class, 'rest_bid'],
            'permission_callback' => static fn() => is_user_logged_in(),
            'args'                => ['amount' => ['required' => true], 'name' => [], 'phone' => []],
        ]);

        register_rest_route('art-coa/v1', '/certificates', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'list_certificates'],
            'permission_callback' => static function () {
                return current_user_can('manage_options') || current_user_can('art_view_certificates');
            },
        ]);
    }

    public static function verify(\WP_REST_Request $request): \WP_REST_Response
    {
        $serial = \WPArtCOA\Security::sanitize_serial((string) $request->get_param('serial'));
        $result = Certificate::verify($serial);
        return rest_ensure_response($result);
    }

    public static function list_certificates(): \WP_REST_Response
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT serial_number, issued_to_name, status, issued_at_utc, artwork_id
             FROM ' . Database::table('certificates') . '
             ORDER BY id DESC LIMIT 50'
        );
        return rest_ensure_response($rows);
    }
}
