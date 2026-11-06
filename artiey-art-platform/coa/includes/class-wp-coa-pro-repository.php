<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WP_COA_Pro_Repository {
    private $table;
    private $signature;
    private $ledger;

    public function __construct(WP_COA_Pro_Signature $signature, WP_COA_Pro_Ledger $ledger) {
        global $wpdb;
        $this->table = $wpdb->prefix . 'coa_certificates';
        $this->signature = $signature;
        $this->ledger = $ledger;
    }

    public function issue($data) {
        global $wpdb;
        $now = current_time('mysql', true);
        $gallery_id = isset($data['gallery_id']) ? absint($data['gallery_id']) : 0;
        $gallery_profile = class_exists('WP_COA_Pro_Galleries') ? WP_COA_Pro_Galleries::get_profile($gallery_id) : null;
        if (!$gallery_profile || $gallery_profile->status !== 'active') {
            return new WP_Error('coa_gallery_profile_unavailable', __('Select an active gallery or institution profile before issuing the certificate.', 'wp-coa-pro'));
        }
        $gallery_id = (int) $gallery_profile->id;
        $image_id = isset($data['artwork_image_id']) ? absint($data['artwork_image_id']) : 0;
        $image_sha256 = '';
        if ($image_id > 0) {
            $attachment = get_post($image_id);
            $mime_type = $attachment ? strtolower((string) get_post_mime_type($image_id)) : '';
            $allowed_image_types = array('image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif');
            $file = function_exists('get_attached_file') ? get_attached_file($image_id) : '';
            if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($image_id) || !in_array($mime_type, $allowed_image_types, true)) {
                return new WP_Error('coa_artwork_image_invalid', __('Choose a valid raster image from the WordPress Media Library. SVG files are not accepted.', 'wp-coa-pro'));
            }
            if (!current_user_can('upload_files') && !current_user_can('edit_post', $image_id)) {
                return new WP_Error('coa_artwork_image_forbidden', __('You do not have permission to use this image.', 'wp-coa-pro'));
            }
            if (!$file || !is_file($file) || !is_readable($file)) {
                return new WP_Error('coa_artwork_image_unavailable', __('The original image file cannot be read from the media library.', 'wp-coa-pro'));
            }
            $file_size = filesize($file);
            if ($file_size === false || $file_size > 25 * 1024 * 1024) {
                return new WP_Error('coa_artwork_image_too_large', __('The original artwork image must be 25 MB or smaller.', 'wp-coa-pro'));
            }
            $computed_hash = hash_file('sha256', $file);
            if (!is_string($computed_hash) || !preg_match('/^[a-f0-9]{64}$/', $computed_hash)) {
                return new WP_Error('coa_artwork_image_hash_failed', __('The original artwork image could not be fingerprinted.', 'wp-coa-pro'));
            }
            $image_sha256 = $computed_hash;
        }
        $profile_snapshot = WP_COA_Pro_Galleries::encode_snapshot($gallery_profile);
        if (!is_string($profile_snapshot) || $profile_snapshot === '') {
            return new WP_Error('coa_gallery_snapshot_failed', __('The gallery branding profile could not be snapshotted.', 'wp-coa-pro'));
        }
        $record = array(
            'gallery_id' => $gallery_id,
            'activity_type' => (string) $gallery_profile->activity_type,
            'certificate_template' => (string) $gallery_profile->template_style,
            'gallery_profile_snapshot' => $profile_snapshot,
            'artist_profile_id' => isset($data['artist_profile_id']) ? absint($data['artist_profile_id']) : 0,
            'collector_profile_id' => isset($data['collector_profile_id']) ? absint($data['collector_profile_id']) : 0,
            'certificate_code' => $this->generate_code(),
            'artwork_image_id' => $image_id,
            'artwork_image_sha256' => $image_sha256,
            'artwork_title' => $data['artwork_title'],
            'artwork_title_en' => isset($data['artwork_title_en']) ? $data['artwork_title_en'] : '',
            'artist_name' => $data['artist_name'],
            'artist_name_en' => isset($data['artist_name_en']) ? $data['artist_name_en'] : '',
            'medium' => $data['medium'],
            'medium_en' => isset($data['medium_en']) ? $data['medium_en'] : '',
            'year_created' => $data['year_created'],
            'dimensions' => $data['dimensions'],
            'accession_number' => $data['accession_number'],
            'artwork_description' => $data['artwork_description'],
            'artwork_description_en' => isset($data['artwork_description_en']) ? $data['artwork_description_en'] : '',
            'collector_name' => isset($data['collector_name']) ? $data['collector_name'] : '',
            'collector_name_en' => isset($data['collector_name_en']) ? $data['collector_name_en'] : '',
            'current_owner_name' => isset($data['current_owner_name']) ? $data['current_owner_name'] : '',
            'current_owner_name_en' => isset($data['current_owner_name_en']) ? $data['current_owner_name_en'] : '',
            'previous_owner_name' => isset($data['previous_owner_name']) ? $data['previous_owner_name'] : '',
            'previous_owner_name_en' => isset($data['previous_owner_name_en']) ? $data['previous_owner_name_en'] : '',
            'issuer_name' => $data['issuer_name'],
            'issuer_name_en' => isset($data['issuer_name_en']) ? $data['issuer_name_en'] : '',
            'issue_date' => $now,
        );
        $payload = $this->signature->payload($record);
        if (!is_string($payload)) {
            return new WP_Error('coa_payload_error', __('Certificate metadata could not be serialized.', 'wp-coa-pro'));
        }
        $signed = $this->signature->sign($payload);
        if (is_wp_error($signed)) {
            return $signed;
        }

        $wpdb->query('START TRANSACTION');
        $inserted = $wpdb->insert($this->table, array(
            'gallery_id' => $record['gallery_id'],
            'activity_type' => $record['activity_type'],
            'certificate_template' => $record['certificate_template'],
            'gallery_profile_snapshot' => $record['gallery_profile_snapshot'],
            'artist_profile_id' => $record['artist_profile_id'],
            'collector_profile_id' => $record['collector_profile_id'],
            'certificate_code' => $record['certificate_code'],
            'artwork_image_id' => $record['artwork_image_id'],
            'artwork_image_sha256' => $record['artwork_image_sha256'],
            'artwork_title' => $record['artwork_title'],
            'artwork_title_en' => $record['artwork_title_en'],
            'artist_name' => $record['artist_name'],
            'artist_name_en' => $record['artist_name_en'],
            'medium' => $record['medium'],
            'medium_en' => $record['medium_en'],
            'year_created' => $record['year_created'],
            'dimensions' => $record['dimensions'],
            'accession_number' => $record['accession_number'],
            'artwork_description' => $record['artwork_description'],
            'artwork_description_en' => $record['artwork_description_en'],
            'collector_name' => $record['collector_name'],
            'collector_name_en' => $record['collector_name_en'],
            'current_owner_name' => $record['current_owner_name'],
            'current_owner_name_en' => $record['current_owner_name_en'],
            'previous_owner_name' => $record['previous_owner_name'],
            'previous_owner_name_en' => $record['previous_owner_name_en'],
            'issuer_name' => $record['issuer_name'],
            'issuer_name_en' => $record['issuer_name_en'],
            'issue_date' => $now,
            'status' => 'active',
            'integrity_hash' => $signed['hash'],
            'integrity_payload' => $payload,
            'signature_version' => $signed['version'],
            'integrity_key_source' => $signed['key_source'],
            'notes' => $data['notes'],
            'created_by' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ));

        if ($inserted === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_database_insert_failed', __('The certificate could not be saved. Check the database error log.', 'wp-coa-pro'));
        }
        $certificate_id = (int) $wpdb->insert_id;
        if (!$this->ledger->append($certificate_id, 'issued', get_current_user_id(), array('certificate_code' => $record['certificate_code'], 'gallery_id' => $record['gallery_id'], 'activity_type' => $record['activity_type'], 'certificate_template' => $record['certificate_template']), $now)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_ledger_insert_failed', __('The certificate audit event could not be saved; the operation was rolled back.', 'wp-coa-pro'));
        }
        $wpdb->query('COMMIT');
        delete_transient('wp_coa_pro_dashboard_integrity_health');
        if (class_exists('WP_COA_Pro_Person_Profiles')) { WP_COA_Pro_Person_Profiles::bust_linked_works_cache(); }
        if (class_exists('WP_COA_Pro_Page_Integration')) { WP_COA_Pro_Page_Integration::invalidate_public_stats_cache(); }
        return $this->get_by_id($certificate_id);
    }

    public function get_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", absint($id)));
    }

    public function get_by_code($code) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE certificate_code = %s LIMIT 1", $code));
    }

    public function list_records($args = array()) {
        global $wpdb;
        $defaults = array('search' => '', 'status' => '', 'gallery_id' => 0, 'activity_type' => '', 'per_page' => 20, 'page' => 1);
        $args = wp_parse_args($args, $defaults);
        $per_page = max(1, min(100, absint($args['per_page'])));
        $page = max(1, absint($args['page']));
        $offset = ($page - 1) * $per_page;
        $where = array('1=1');
        $values = array();

        if ($args['search'] !== '') {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(certificate_code LIKE %s OR artwork_title LIKE %s OR artwork_title_en LIKE %s OR artist_name LIKE %s OR artist_name_en LIKE %s OR accession_number LIKE %s OR collector_name LIKE %s OR current_owner_name LIKE %s)';
            array_push($values, $like, $like, $like, $like, $like, $like, $like, $like);
        }
        if (in_array($args['status'], array('active', 'revoked'), true)) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if (!empty($args['gallery_id'])) {
            $where[] = 'gallery_id = %d';
            $values[] = absint($args['gallery_id']);
        }
        if (isset($args['activity_type']) && $args['activity_type'] !== '' && class_exists('WP_COA_Pro_Galleries') && isset(WP_COA_Pro_Galleries::activity_types()[$args['activity_type']])) {
            $where[] = 'activity_type = %s';
            $values[] = $args['activity_type'];
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $values[] = $per_page;
        $values[] = $offset;
        $prepared = $wpdb->prepare($sql, $values);
        return $wpdb->get_results($prepared);
    }

    public function count_records($args = array()) {
        global $wpdb;
        $where = array('1=1');
        $values = array();
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(certificate_code LIKE %s OR artwork_title LIKE %s OR artwork_title_en LIKE %s OR artist_name LIKE %s OR artist_name_en LIKE %s OR accession_number LIKE %s OR collector_name LIKE %s OR current_owner_name LIKE %s)';
            array_push($values, $like, $like, $like, $like, $like, $like, $like, $like);
        }
        if (isset($args['status']) && in_array($args['status'], array('active', 'revoked'), true)) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if (!empty($args['gallery_id'])) {
            $where[] = 'gallery_id = %d';
            $values[] = absint($args['gallery_id']);
        }
        if (isset($args['activity_type']) && $args['activity_type'] !== '' && class_exists('WP_COA_Pro_Galleries') && isset(WP_COA_Pro_Galleries::activity_types()[$args['activity_type']])) {
            $where[] = 'activity_type = %s';
            $values[] = $args['activity_type'];
        }
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $where);
        return (int) $wpdb->get_var($values ? $wpdb->prepare($sql, $values) : $sql);
    }

    /**
     * Record a collection transfer. The previous owner is retained in the signed
     * certificate metadata and the transfer itself is appended to the audit chain.
     */
    public function transfer_owner($id, $new_owner, $new_owner_en) {
        global $wpdb;
        $id = absint($id);
        $new_owner = is_scalar($new_owner) ? trim((string) $new_owner) : '';
        $new_owner_en = is_scalar($new_owner_en) ? trim((string) $new_owner_en) : '';
        if (!$id) {
            return new WP_Error('coa_not_found', __('Certificate not found.', 'wp-coa-pro'));
        }

        // Lock the certificate row before validating and changing ownership to avoid
        // two concurrent transfers both using the same stale owner as their source.
        if ($wpdb->query('START TRANSACTION') === false) {
            return new WP_Error('coa_transaction_failed', __('A database transaction could not be started.', 'wp-coa-pro'));
        }
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d FOR UPDATE", $id));
        if (!$record) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_not_found', __('Certificate not found.', 'wp-coa-pro'));
        }
        if ($record->status !== 'active') {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_not_active', __('Ownership cannot be transferred for a revoked certificate.', 'wp-coa-pro'));
        }
        if ($new_owner === '' && $new_owner_en === '') {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_owner_required', __('Enter the new owner name in at least one language.', 'wp-coa-pro'));
        }
        if ((string) $record->current_owner_name === $new_owner && (string) $record->current_owner_name_en === $new_owner_en) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_owner_unchanged', __('The new owner matches the current owner.', 'wp-coa-pro'));
        }

        $now = current_time('mysql', true);
        $old_owner = (string) $record->current_owner_name;
        $old_owner_en = (string) $record->current_owner_name_en;
        $previous_owner = $old_owner !== '' ? $old_owner : (string) $record->previous_owner_name;
        $previous_owner_en = $old_owner_en !== '' ? $old_owner_en : (string) $record->previous_owner_name_en;

        $updated = $wpdb->update($this->table, array(
            'previous_owner_name' => $previous_owner,
            'previous_owner_name_en' => $previous_owner_en,
            'current_owner_name' => $new_owner,
            'current_owner_name_en' => $new_owner_en,
            'updated_at' => $now,
        ), array('id' => $id, 'status' => 'active'));
        if ($updated !== 1) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_owner_update_failed', __('The ownership record could not be updated.', 'wp-coa-pro'));
        }

        $updated_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id));
        $payload = $updated_record ? $this->signature->payload($updated_record) : false;
        $signed = is_string($payload) ? $this->signature->sign($payload) : new WP_Error('coa_payload_error', __('Certificate metadata could not be serialized.', 'wp-coa-pro'));
        if (is_wp_error($signed)) {
            $wpdb->query('ROLLBACK');
            return $signed;
        }
        $signature_updated = $wpdb->update($this->table, array(
            'integrity_payload' => $payload,
            'integrity_hash' => $signed['hash'],
            'signature_version' => $signed['version'],
            'integrity_key_source' => $signed['key_source'],
        ), array('id' => $id));
        if ($signature_updated !== 1) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_signature_update_failed', __('The certificate signature could not be updated.', 'wp-coa-pro'));
        }

        $event_data = array(
            'previous_owner_name' => $previous_owner,
            'previous_owner_name_en' => $previous_owner_en,
            'current_owner_name' => $new_owner,
            'current_owner_name_en' => $new_owner_en,
        );
        if (!$this->ledger->append($id, 'ownership_transferred', get_current_user_id(), $event_data, $now)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_ledger_insert_failed', __('The ownership change could not be written to the audit history.', 'wp-coa-pro'));
        }
        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_transaction_commit_failed', __('The ownership transfer could not be committed.', 'wp-coa-pro'));
        }
        delete_transient('wp_coa_pro_dashboard_integrity_health');
        if (class_exists('WP_COA_Pro_Person_Profiles')) { WP_COA_Pro_Person_Profiles::bust_linked_works_cache(); }
        $updated_record = $this->get_by_id($id);
        do_action('wp_coa_pro_certificate_transferred', (int) $id, $updated_record);
        return $updated_record;
    }

    public function revoke($id, $reason) {
        global $wpdb;
        $record = $this->get_by_id($id);
        if (!$record) {
            return new WP_Error('coa_not_found', __('Certificate not found.', 'wp-coa-pro'));
        }
        if ($record->status !== 'active') {
            return new WP_Error('coa_not_active', __('Only an active certificate can be revoked.', 'wp-coa-pro'));
        }

        $now = current_time('mysql', true);
        $wpdb->query('START TRANSACTION');
        $updated = $wpdb->update($this->table, array(
            'status' => 'revoked',
            'updated_at' => $now,
            'revoked_at' => $now,
            'revoked_by' => get_current_user_id(),
            'revocation_reason' => $reason,
        ), array('id' => (int) $id, 'status' => 'active'), array('%s', '%s', '%s', '%d', '%s'), array('%d', '%s'));

        if ($updated !== 1) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_revoke_race', __('The certificate could not be revoked; its status may have changed. Refresh the page and try again.', 'wp-coa-pro'));
        }
        if (!$this->ledger->append($id, 'revoked', get_current_user_id(), array('reason' => $reason), $now)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('coa_ledger_insert_failed', __('The revocation history could not be saved; the operation was rolled back.', 'wp-coa-pro'));
        }
        $wpdb->query('COMMIT');
        delete_transient('wp_coa_pro_dashboard_integrity_health');
        if (class_exists('WP_COA_Pro_Person_Profiles')) { WP_COA_Pro_Person_Profiles::bust_linked_works_cache(); }
        if (class_exists('WP_COA_Pro_Page_Integration')) { WP_COA_Pro_Page_Integration::invalidate_public_stats_cache(); }
        do_action('wp_coa_pro_certificate_revoked', (int) $id, (string) $reason);
        return true;
    }

    public function all_for_export($args = array()) {
        global $wpdb;
        $defaults = array('search' => '', 'status' => '', 'gallery_id' => 0, 'activity_type' => '');
        $args = wp_parse_args($args, $defaults);
        $where = array('1=1');
        $values = array();
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[] = '(certificate_code LIKE %s OR artwork_title LIKE %s OR artwork_title_en LIKE %s OR artist_name LIKE %s OR artist_name_en LIKE %s OR accession_number LIKE %s OR collector_name LIKE %s OR current_owner_name LIKE %s)';
            array_push($values, $like, $like, $like, $like, $like, $like, $like, $like);
        }
        if (in_array($args['status'], array('active', 'revoked'), true)) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if (!empty($args['gallery_id'])) {
            $where[] = 'gallery_id = %d';
            $values[] = absint($args['gallery_id']);
        }
        if (isset($args['activity_type']) && $args['activity_type'] !== '' && class_exists('WP_COA_Pro_Galleries') && isset(WP_COA_Pro_Galleries::activity_types()[$args['activity_type']])) {
            $where[] = 'activity_type = %s';
            $values[] = $args['activity_type'];
        }
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . ' ORDER BY id ASC';
        return $wpdb->get_results($values ? $wpdb->prepare($sql, $values) : $sql);
    }

    private function generate_code() {
        global $wpdb;
        do {
            try {
                $random = strtoupper(bin2hex(random_bytes(10)));
            } catch (Exception $exception) {
                $random = strtoupper(wp_generate_password(20, false, false));
            }
            $code = 'COA-' . substr($random, 0, 8) . '-' . substr($random, 8, 12);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table} WHERE certificate_code = %s LIMIT 1", $code));
        } while ($exists);
        return $code;
    }
}
