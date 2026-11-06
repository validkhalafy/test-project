<?php
if (!defined('ABSPATH')) {
    exit;
}

/** App-level append-only event history. The hash chain is tamper-evident, not immutable. */
final class WP_COA_Pro_Ledger {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'coa_events';
    }

    public function append($certificate_id, $event_type, $actor_id, $details = array(), $created_at = null) {
        global $wpdb;
        $certificate_id = absint($certificate_id);
        $event_type = sanitize_key($event_type);
        $actor_id = absint($actor_id);
        $created_at = $created_at ? $created_at : current_time('mysql', true);
        $details_json = wp_json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($details_json)) {
            return false;
        }

        $previous_hash = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT event_hash FROM {$this->table} WHERE certificate_id = %d ORDER BY id DESC LIMIT 1",
            $certificate_id
        ));
        $canonical = wp_json_encode(array(
            'certificate_id' => $certificate_id,
            'event_type' => $event_type,
            'actor_id' => $actor_id,
            'event_payload' => $details_json,
            'previous_hash' => $previous_hash,
            'created_at' => $created_at,
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($canonical)) {
            return false;
        }

        $event_hash = hash('sha256', $canonical);
        $inserted = $wpdb->insert($this->table, array(
            'certificate_id' => $certificate_id,
            'event_type' => $event_type,
            'actor_id' => $actor_id,
            'event_payload' => $details_json,
            'previous_hash' => $previous_hash,
            'event_hash' => $event_hash,
            'created_at' => $created_at,
        ), array('%d', '%s', '%d', '%s', '%s', '%s', '%s'));
        return $inserted !== false;
    }

    public function for_certificate($certificate_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, event_type, actor_id, event_payload, previous_hash, event_hash, created_at FROM {$this->table} WHERE certificate_id = %d ORDER BY id DESC LIMIT 100",
            absint($certificate_id)
        ));
    }


    public function verify_chain($certificate_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT certificate_id, event_type, actor_id, event_payload, previous_hash, event_hash, created_at FROM {$this->table} WHERE certificate_id = %d ORDER BY id ASC",
            absint($certificate_id)
        ));
        if (!$rows) {
            return false;
        }
        $expected_previous = '';
        foreach ($rows as $row) {
            $stored_previous = (string) $row->previous_hash;
            if (!hash_equals($expected_previous, $stored_previous)) {
                return false;
            }
            $canonical = wp_json_encode(array(
                'certificate_id' => (int) $row->certificate_id,
                'event_type' => (string) $row->event_type,
                'actor_id' => (int) $row->actor_id,
                'event_payload' => (string) $row->event_payload,
                'previous_hash' => $stored_previous,
                'created_at' => (string) $row->created_at,
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (!is_string($canonical)) {
                return false;
            }
            $calculated = hash('sha256', $canonical);
            if (!hash_equals($calculated, (string) $row->event_hash)) {
                return false;
            }
            $expected_previous = $calculated;
        }
        return true;
    }

    /**
     * Compare the certificate's current lifecycle fields with its tamper-evident
     * event chain. Returns true/false; null means no history exists (legacy data).
     * This is tamper evidence, not protection against an attacker who can rewrite
     * both the database and all event hashes.
     */
    public function verify_certificate_state($record) {
        if (!is_object($record) || empty($record->id) || !isset($record->status)) {
            return false;
        }
        global $wpdb;
        $latest = $wpdb->get_row($wpdb->prepare(
            "SELECT event_type, event_payload FROM {$this->table} WHERE certificate_id = %d AND event_type IN (%s, %s) ORDER BY id DESC LIMIT 1",
            absint($record->id),
            'issued',
            'revoked'
        ));
        if (!$latest) {
            return null;
        }
        if (!$this->verify_chain(absint($record->id))) {
            return false;
        }
        if ($record->status === 'active') {
            return $latest->event_type === 'issued';
        }
        if ($record->status === 'revoked') {
            if ($latest->event_type !== 'revoked') {
                return false;
            }
            $payload = json_decode((string) $latest->event_payload, true);
            if (!is_array($payload) || !isset($payload['reason']) || !isset($record->revocation_reason)) {
                return false;
            }
            return hash_equals((string) $payload['reason'], (string) $record->revocation_reason);
        }
        return false;
    }

    public function recent($limit = 100) {
        global $wpdb;
        $certificates = $wpdb->prefix . 'coa_certificates';
        $limit = max(1, min(250, absint($limit)));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.certificate_id, e.event_type, e.actor_id, e.event_payload, e.previous_hash, e.event_hash, e.created_at, c.certificate_code, c.artwork_title FROM {$this->table} e LEFT JOIN {$certificates} c ON c.id = e.certificate_id ORDER BY e.id DESC LIMIT %d",
            $limit
        ));
    }
}
