<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Canonical, versioned HMAC snapshots. Legacy v1-v4 records remain verifiable. */
final class WP_COA_Pro_Signature {
    const CURRENT_VERSION = 6;

    /** v6 payload extends the v5 metadata with a Media Library attachment ID and original-file SHA-256. */
    public function payload($record) {
        $legacy = json_decode($this->payload_v5($record), true);
        if (!is_array($legacy)) {
            return false;
        }
        $legacy['schema'] = 6;
        $legacy['artwork_image_id'] = (int) $this->value($record, 'artwork_image_id');
        $legacy['artwork_image_sha256'] = (string) $this->value($record, 'artwork_image_sha256');
        return wp_json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** Exact canonical v5 payload retained for existing records. */
    private function payload_v5($record) {
        $fields = array(
            'schema' => 5,
            'certificate_code' => (string) $this->value($record, 'certificate_code'),
            'artwork_title' => (string) $this->value($record, 'artwork_title'),
            'artwork_title_en' => (string) $this->value($record, 'artwork_title_en'),
            'artist_name' => (string) $this->value($record, 'artist_name'),
            'artist_name_en' => (string) $this->value($record, 'artist_name_en'),
            'medium' => (string) $this->value($record, 'medium'),
            'medium_en' => (string) $this->value($record, 'medium_en'),
            'year_created' => (string) $this->value($record, 'year_created'),
            'dimensions' => (string) $this->value($record, 'dimensions'),
            'accession_number' => (string) $this->value($record, 'accession_number'),
            'artwork_description' => (string) $this->value($record, 'artwork_description'),
            'artwork_description_en' => (string) $this->value($record, 'artwork_description_en'),
            'collector_name' => (string) $this->value($record, 'collector_name'),
            'collector_name_en' => (string) $this->value($record, 'collector_name_en'),
            'current_owner_name' => (string) $this->value($record, 'current_owner_name'),
            'current_owner_name_en' => (string) $this->value($record, 'current_owner_name_en'),
            'previous_owner_name' => (string) $this->value($record, 'previous_owner_name'),
            'previous_owner_name_en' => (string) $this->value($record, 'previous_owner_name_en'),
            'issuer_name' => (string) $this->value($record, 'issuer_name'),
            'issuer_name_en' => (string) $this->value($record, 'issuer_name_en'),
            'issue_date' => (string) $this->value($record, 'issue_date'),
            'gallery_id' => (int) $this->value($record, 'gallery_id'),
            'activity_type' => (string) $this->value($record, 'activity_type'),
            'certificate_template' => (string) $this->value($record, 'certificate_template'),
            'gallery_profile_snapshot' => (string) $this->value($record, 'gallery_profile_snapshot'),
            'artist_profile_id' => (int) $this->value($record, 'artist_profile_id'),
            'collector_profile_id' => (int) $this->value($record, 'collector_profile_id')
        );
        return wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** Exact canonical v4 schema, retained for certificates issued before person-profile linking. */
    private function payload_v4($record) {
        $fields = array(
            'schema' => 4,
            'certificate_code' => (string) $this->value($record, 'certificate_code'),
            'artwork_title' => (string) $this->value($record, 'artwork_title'),
            'artwork_title_en' => (string) $this->value($record, 'artwork_title_en'),
            'artist_name' => (string) $this->value($record, 'artist_name'),
            'artist_name_en' => (string) $this->value($record, 'artist_name_en'),
            'medium' => (string) $this->value($record, 'medium'),
            'medium_en' => (string) $this->value($record, 'medium_en'),
            'year_created' => (string) $this->value($record, 'year_created'),
            'dimensions' => (string) $this->value($record, 'dimensions'),
            'accession_number' => (string) $this->value($record, 'accession_number'),
            'artwork_description' => (string) $this->value($record, 'artwork_description'),
            'artwork_description_en' => (string) $this->value($record, 'artwork_description_en'),
            'collector_name' => (string) $this->value($record, 'collector_name'),
            'collector_name_en' => (string) $this->value($record, 'collector_name_en'),
            'current_owner_name' => (string) $this->value($record, 'current_owner_name'),
            'current_owner_name_en' => (string) $this->value($record, 'current_owner_name_en'),
            'previous_owner_name' => (string) $this->value($record, 'previous_owner_name'),
            'previous_owner_name_en' => (string) $this->value($record, 'previous_owner_name_en'),
            'issuer_name' => (string) $this->value($record, 'issuer_name'),
            'issuer_name_en' => (string) $this->value($record, 'issuer_name_en'),
            'issue_date' => (string) $this->value($record, 'issue_date'),
            'gallery_id' => (int) $this->value($record, 'gallery_id'),
            'activity_type' => (string) $this->value($record, 'activity_type'),
            'certificate_template' => (string) $this->value($record, 'certificate_template'),
            'gallery_profile_snapshot' => (string) $this->value($record, 'gallery_profile_snapshot')
        );
        return wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** Exact canonical v3 schema, retained for existing certificates. */
    private function payload_v3($record) {
        $fields = array(
            'schema' => 3,
            'certificate_code' => (string) $this->value($record, 'certificate_code'),
            'artwork_title' => (string) $this->value($record, 'artwork_title'),
            'artwork_title_en' => (string) $this->value($record, 'artwork_title_en'),
            'artist_name' => (string) $this->value($record, 'artist_name'),
            'artist_name_en' => (string) $this->value($record, 'artist_name_en'),
            'medium' => (string) $this->value($record, 'medium'),
            'medium_en' => (string) $this->value($record, 'medium_en'),
            'year_created' => (string) $this->value($record, 'year_created'),
            'dimensions' => (string) $this->value($record, 'dimensions'),
            'accession_number' => (string) $this->value($record, 'accession_number'),
            'artwork_description' => (string) $this->value($record, 'artwork_description'),
            'artwork_description_en' => (string) $this->value($record, 'artwork_description_en'),
            'collector_name' => (string) $this->value($record, 'collector_name'),
            'collector_name_en' => (string) $this->value($record, 'collector_name_en'),
            'current_owner_name' => (string) $this->value($record, 'current_owner_name'),
            'current_owner_name_en' => (string) $this->value($record, 'current_owner_name_en'),
            'previous_owner_name' => (string) $this->value($record, 'previous_owner_name'),
            'previous_owner_name_en' => (string) $this->value($record, 'previous_owner_name_en'),
            'issuer_name' => (string) $this->value($record, 'issuer_name'),
            'issuer_name_en' => (string) $this->value($record, 'issuer_name_en'),
            'issue_date' => (string) $this->value($record, 'issue_date')
        );
        return wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** Exact v2 canonical schema retained for older records. */
    private function payload_v2($record) {
        $fields = array(
            'schema' => 2,
            'certificate_code' => (string) $this->value($record, 'certificate_code'),
            'artwork_title' => (string) $this->value($record, 'artwork_title'),
            'artist_name' => (string) $this->value($record, 'artist_name'),
            'medium' => (string) $this->value($record, 'medium'),
            'year_created' => (string) $this->value($record, 'year_created'),
            'dimensions' => (string) $this->value($record, 'dimensions'),
            'accession_number' => (string) $this->value($record, 'accession_number'),
            'artwork_description' => (string) $this->value($record, 'artwork_description'),
            'issuer_name' => (string) $this->value($record, 'issuer_name'),
            'issue_date' => (string) $this->value($record, 'issue_date')
        );
        return wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function sign($payload) {
        $source = $this->active_key_source();
        $key = $this->key_for_source($source);
        if ($key === '') {
            return new WP_Error('coa_signing_key_missing', __('The certificate signing key is not configured.', 'wp-coa-pro'));
        }
        return array('hash' => hash_hmac('sha256', (string) $payload, $key), 'key_source' => $source, 'version' => self::CURRENT_VERSION);
    }

    public function verify($record) {
        if (!$record || empty($record->integrity_hash)) { return false; }
        $version = isset($record->signature_version) ? (int) $record->signature_version : 1;
        if ($version < 2) {
            $legacy_payload = implode('|', array(
                (string) $this->value($record, 'certificate_code'), (string) $this->value($record, 'artwork_title'),
                (string) $this->value($record, 'artist_name'), (string) $this->value($record, 'medium'),
                (string) $this->value($record, 'year_created'), (string) $this->value($record, 'issuer_name'),
                (string) $this->value($record, 'issue_date')
            ));
            return hash_equals(hash_hmac('sha256', $legacy_payload, wp_salt('auth')), (string) $record->integrity_hash);
        }
        if (!in_array($version, array(2, 3, 4, 5, 6), true)) { return false; }
        $stored_payload = isset($record->integrity_payload) ? (string) $record->integrity_payload : '';
        if ($stored_payload === '') { return false; }
        if ($version === 2) { $expected_payload = $this->payload_v2($record); }
        elseif ($version === 3) { $expected_payload = $this->payload_v3($record); }
        elseif ($version === 4) { $expected_payload = $this->payload_v4($record); }
        elseif ($version === 5) { $expected_payload = $this->payload_v5($record); }
        else { $expected_payload = $this->payload($record); }
        if (!is_string($expected_payload) || !hash_equals($stored_payload, $expected_payload)) { return false; }
        // For v6 certificates, the attached original image is part of the evidence snapshot.
        // A deleted or replaced file fails integrity validation rather than silently passing.
        if ($version >= 6) {
            $image_id = (int) $this->value($record, 'artwork_image_id');
            $signed_file_hash = (string) $this->value($record, 'artwork_image_sha256');
            if ($image_id > 0) {
                if ($signed_file_hash === '' || !function_exists('get_attached_file')) { return false; }
                $file = get_attached_file($image_id);
                if (!$file || !is_file($file) || !is_readable($file)) { return false; }
                $current_file_hash = hash_file('sha256', $file);
                if (!is_string($current_file_hash) || !hash_equals($signed_file_hash, $current_file_hash)) { return false; }
            } elseif ($signed_file_hash !== '') {
                return false;
            }
        }
        $source = isset($record->integrity_key_source) ? (string) $record->integrity_key_source : '';
        $key = $this->key_for_source($source);
        if ($key === '') { return false; }
        return hash_equals(hash_hmac('sha256', $stored_payload, $key), (string) $record->integrity_hash);
    }

    public function active_key_source() {
        if (defined('WP_COA_PRO_SIGNING_KEY') && is_string(WP_COA_PRO_SIGNING_KEY) && strlen(WP_COA_PRO_SIGNING_KEY) >= 32) { return 'constant'; }
        WP_COA_Pro_Installer::ensure_signing_key();
        return 'option';
    }

    private function key_for_source($source) {
        if ($source === 'constant') {
            return defined('WP_COA_PRO_SIGNING_KEY') && is_string(WP_COA_PRO_SIGNING_KEY) && strlen(WP_COA_PRO_SIGNING_KEY) >= 32 ? WP_COA_PRO_SIGNING_KEY : '';
        }
        if ($source === 'option') {
            $key = get_option('wp_coa_pro_signing_key', '');
            return is_string($key) ? $key : '';
        }
        return '';
    }

    private function value($record, $key) {
        if (is_object($record)) { return isset($record->{$key}) ? $record->{$key} : ''; }
        if (is_array($record)) { return isset($record[$key]) ? $record[$key] : ''; }
        return '';
    }
}
