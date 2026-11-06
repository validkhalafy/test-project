<?php
declare(strict_types=1);

namespace WPArtCOA\Entities;

use WPArtCOA\Audit;
use WPArtCOA\Database;
use WPArtCOA\I18n;
use WPArtCOA\Media;

defined('ABSPATH') || exit;

/**
 * Shared, schema-driven partial update used by all entities.
 * Only keys present in $data are written, so a front-end form that sends a
 * subset of fields never wipes the others.
 */
final class Updater
{
    /**
     * @param string                $entity  audit entity type + table key (e.g. "artist" → art_artists)
     * @param array<string,string>  $schema  field => text|text!|email|url|html|textarea|int|bool|color
     * @param string[]              $images  image fields handled through Media (avatar, cover, poster…)
     * @param string[]              $multi   CSV attachment-id fields (gallery_ids…)
     */
    public static function apply(string $entity, string $table, int $id, array $data, array $schema, array $images = [], array $multi = []): array
    {
        global $wpdb;
        $table = Database::table($table);

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %d", $id)); // phpcs:ignore
        if (!$exists) {
            return ['success' => false, 'message' => I18n::t('not_found')];
        }

        $f = [];
        foreach ($schema as $field => $type) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $raw      = $data[$field];
            $required = str_ends_with($type, '!');
            $type     = rtrim($type, '!');
            $val = match ($type) {
                'email'    => sanitize_email((string) $raw),
                'url'      => esc_url_raw((string) $raw),
                'html'     => wp_kses_post((string) $raw),
                'textarea' => sanitize_textarea_field((string) $raw),
                'int'      => absint($raw) ?: null,
                'bool'     => !empty($raw) ? 1 : 0,
                'color'    => sanitize_hex_color((string) $raw) ?: null,
                'key'      => sanitize_key((string) $raw),
                'raw'      => $raw,   // already normalised by the caller (e.g. dates)
                default    => sanitize_text_field((string) $raw),
            };
            if ($required && ($val === '' || $val === null)) {
                return ['success' => false, 'message' => I18n::t('required_missing')];
            }
            $f[$field] = $val;
        }
        foreach ($images as $img) {
            if (Media::has($data, $img)) {
                $f += Media::columns($data, $img);
            }
        }
        foreach ($multi as $m) {
            if (array_key_exists($m, $data)) {
                $f[$m] = Media::sanitize_ids($data[$m]);
            }
        }
        if (!$f) {
            return ['success' => true, 'id' => $id];
        }

        $f['updated_at_utc'] = gmdate('Y-m-d H:i:s');
        if ($wpdb->update($table, $f, ['id' => $id]) === false) {
            return ['success' => false, 'message' => $wpdb->last_error ?: I18n::t('error')];
        }
        Audit::log($entity, $id, 'updated');
        if (class_exists('\WPArtCOA\Cache')) {
            \WPArtCOA\Cache::flush_stats();
        }
        return ['success' => true, 'id' => $id];
    }
}
