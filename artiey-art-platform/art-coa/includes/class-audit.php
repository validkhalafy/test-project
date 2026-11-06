<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Audit
{
    public static function log(string $entity_type, int $entity_id, string $action, array $meta = []): void
    {
        global $wpdb;

        $wpdb->insert(
            Database::table('audit_log'),
            [
                'entity_type'    => sanitize_key($entity_type),
                'entity_id'      => $entity_id,
                'action'         => sanitize_key($action),
                'user_id'        => get_current_user_id() ?: null,
                'ip_address'     => self::client_ip(),
                'meta'           => $meta ? wp_json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'created_at_utc' => gmdate('Y-m-d H:i:s'),
            ],
            ['%s', '%d', '%s', '%d', '%s', '%s', '%s']
        );
    }

    private static function client_ip(): string
    {
        // REMOTE_ADDR only: forwarded headers are client-controlled and can be spoofed.
        return Security::client_ip();
    }
}
