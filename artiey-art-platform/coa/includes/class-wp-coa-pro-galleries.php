<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Gallery/institution profiles. Existing issued certificates keep a signed branding snapshot. */
final class WP_COA_Pro_Galleries {
    const DEFAULT_TEMPLATE = 'classic';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_post_wp_coa_save_gallery', array(__CLASS__, 'handle_save'));
        add_action('admin_post_wp_coa_toggle_gallery', array(__CLASS__, 'handle_toggle'));
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coa_galleries';
    }

    public static function activity_types() {
        return array(
            'gallery' => array('گالری هنری', 'Art Gallery'),
            'museum' => array('موزه / مؤسسه فرهنگی', 'Museum / Cultural Institution'),
            'artist_studio' => array('آتلیه / استودیوی هنرمند', 'Artist Studio'),
            'auction_house' => array('حراجی آثار هنری', 'Auction House'),
            'art_fair' => array('آرت‌فر / نمایشگاه فروش', 'Art Fair'),
            'art_school' => array('آموزشگاه / مرکز آموزش هنر', 'Art School / Education'),
            'collector' => array('مجموعه‌دار / مجموعه خصوصی', 'Collector / Private Collection'),
            'conservation' => array('مرمت و حفاظت آثار', 'Conservation / Restoration'),
            'archive' => array('آرشیو / مرکز اسناد هنری', 'Art Archive'),
            'digital_art' => array('هنر دیجیتال / ثبت دیجیتال', 'Digital Art Registry'),
            'art_advisor' => array('مشاور / کارشناس هنری', 'Art Advisor / Expert'),
            'other' => array('سایر', 'Other')
        );
    }

    public static function activity_label($key, $language = 'fa') {
        $types = self::activity_types();
        if (!isset($types[$key])) {
            return (string) $key;
        }
        if ($language === 'fa') { return $types[$key][0]; }
        if ($language === 'en') { return $types[$key][1]; }
        return $types[$key][0] . ' / ' . $types[$key][1];
    }

    public static function templates() {
        return array(
            'classic' => array('کلاسیک موزه‌ای', 'Museum Classic'),
            'modern' => array('مدرن', 'Modern'),
            'minimal' => array('مینیمال', 'Minimal'),
            'archive' => array('آرشیوی / کلکسیونی', 'Archive / Collection')
        );
    }

    public static function ensure_default_profile() {
        global $wpdb;
        $table = self::table_name();
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 0) {
            // Make sure an existing install always has one selectable default profile.
            $default_id = (int) $wpdb->get_var("SELECT id FROM {$table} WHERE is_default = 1 ORDER BY id ASC LIMIT 1");
            if (!$default_id) {
                $first_id = (int) $wpdb->get_var("SELECT id FROM {$table} ORDER BY id ASC LIMIT 1");
                if ($first_id) {
                    // A recovered default must remain selectable for certificate issuance.
                    $wpdb->update($table, array('is_default' => 1, 'status' => 'active'), array('id' => $first_id), array('%d', '%s'), array('%d'));
                }
            } else {
                $wpdb->update($table, array('status' => 'active'), array('id' => $default_id), array('%s'), array('%d'));
            }
            return;
        }

        $saved = get_option('wp_coa_pro_settings', array());
        if (!is_array($saved)) { $saved = array(); }
        $name = isset($saved['issuer_name']) && is_scalar($saved['issuer_name']) ? sanitize_text_field($saved['issuer_name']) : '';
        if ($name === '') { $name = sanitize_text_field(get_bloginfo('name')); }
        $name_en = isset($saved['issuer_name_en']) && is_scalar($saved['issuer_name_en']) ? sanitize_text_field($saved['issuer_name_en']) : '';
        $primary = isset($saved['primary_color']) && sanitize_hex_color($saved['primary_color']) ? $saved['primary_color'] : '#65513b';
        $accent = isset($saved['accent_color']) && sanitize_hex_color($saved['accent_color']) ? $saved['accent_color'] : '#b29a68';
        $wpdb->insert($table, array(
            'name' => $name,
            'name_en' => $name_en,
            'slug' => 'default-institution',
            'activity_type' => 'gallery',
            'logo_url' => isset($saved['logo_url']) ? esc_url_raw($saved['logo_url']) : '',
            'primary_color' => $primary,
            'accent_color' => $accent,
            'certificate_title' => 'گواهی اصالت اثر هنری',
            'certificate_title_en' => 'Certificate of Authenticity',
            'footer' => isset($saved['certificate_footer']) ? sanitize_textarea_field($saved['certificate_footer']) : 'صادرشده با سامانه ثبت و اعتبارسنجی آثار هنری.',
            'footer_en' => isset($saved['certificate_footer_en']) ? sanitize_textarea_field($saved['certificate_footer_en']) : 'Issued and managed using WP COA Pro.',
            'template_style' => 'classic',
            'show_logo_public' => isset($saved['show_logo_public']) ? (int) !empty($saved['show_logo_public']) : 1,
            'show_artwork_title' => isset($saved['show_artwork_title']) ? (int) !empty($saved['show_artwork_title']) : 1,
            'show_artwork_image_public' => isset($saved['show_artwork_image_public']) ? (int) !empty($saved['show_artwork_image_public']) : 1,
            'show_artist_name' => isset($saved['show_artist_name']) ? (int) !empty($saved['show_artist_name']) : 1,
            'show_medium' => isset($saved['show_medium']) ? (int) !empty($saved['show_medium']) : 1,
            'show_year_created' => isset($saved['show_year_created']) ? (int) !empty($saved['show_year_created']) : 1,
            'show_dimensions' => isset($saved['show_dimensions']) ? (int) !empty($saved['show_dimensions']) : 1,
            'show_accession_number' => isset($saved['show_accession_number']) ? (int) !empty($saved['show_accession_number']) : 1,
            'show_issuer' => isset($saved['show_issuer']) ? (int) !empty($saved['show_issuer']) : 1,
            'show_issue_date' => isset($saved['show_issue_date']) ? (int) !empty($saved['show_issue_date']) : 1,
            'show_description' => isset($saved['show_description']) ? (int) !empty($saved['show_description']) : 1,
            'show_collector_public' => isset($saved['show_collector_public']) ? (int) !empty($saved['show_collector_public']) : 0,
            'show_current_owner_public' => isset($saved['show_current_owner_public']) ? (int) !empty($saved['show_current_owner_public']) : 0,
            'show_previous_owner_public' => isset($saved['show_previous_owner_public']) ? (int) !empty($saved['show_previous_owner_public']) : 0,
            'status' => 'active',
            'is_default' => 1,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true)
        ));
    }

    /** Invalidate cached gallery lists after profile mutations. */
    public static function bust_public_cache() {
        $generation = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : (string) (microtime(true) . wp_rand());
        update_option('wp_coa_pro_gallery_cache_generation', sanitize_key($generation), false);
    }

    public static function list_profiles($active_only = false, $summary_only = false) {
        global $wpdb;
        $table = self::table_name();
        $generation = sanitize_key((string) get_option('wp_coa_pro_gallery_cache_generation', '1'));
        if ($generation === '') { $generation = '1'; }
        $cache_key = 'wp_coa_gal_' . $generation . '_' . ($active_only ? 'a' : 'all') . '_' . ($summary_only ? 's' : 'f');
        $cached = get_transient($cache_key);
        if (is_array($cached)) { return $cached; }
        $where = $active_only ? "WHERE status = 'active'" : 'WHERE 1=1';
        $columns = $summary_only
            ? 'id, name, name_en, slug, activity_type, logo_url, primary_color, accent_color, certificate_title, certificate_title_en, template_style, status, is_default'
            : '*';
        $rows = $wpdb->get_results("SELECT {$columns} FROM {$table} {$where} ORDER BY is_default DESC, name ASC, id ASC");
        $rows = is_array($rows) ? $rows : array();
        set_transient($cache_key, $rows, 10 * MINUTE_IN_SECONDS);
        return $rows;
    }

    /**
     * Fetch only the rows needed by the public gallery directory.
     * Filtering and limiting in SQL avoids loading the entire gallery table into PHP
     * on every directory render. Search results are deliberately not transient-cached
     * so arbitrary query strings cannot create an unbounded number of cache entries.
     */
    public static function list_public_directory($limit = 48, $activity_type = '', $search = '') {
        global $wpdb;

        $limit = max(1, min(100, absint($limit)));
        $activity_type = sanitize_key((string) $activity_type);
        if (!isset(self::activity_types()[$activity_type])) {
            $activity_type = '';
        }
        $search = sanitize_text_field((string) $search);
        if (class_exists('Artiey_AET_Text')) {
            $search = Artiey_AET_Text::substr($search, 0, 120);
        } else {
            $search = function_exists('mb_substr') ? mb_substr($search, 0, 120) : substr($search, 0, 120);
        }

        $generation = sanitize_key((string) get_option('wp_coa_pro_gallery_cache_generation', '1'));
        if ($generation === '') {
            $generation = '1';
        }
        $cache_key = 'wp_coa_galdir_' . $generation . '_' . substr(hash('sha256', serialize(array($limit, $activity_type))), 0, 24);
        if ($search === '') {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $where = array("status = 'active'");
        $values = array();
        if ($activity_type !== '') {
            $where[] = 'activity_type = %s';
            $values[] = $activity_type;
        }
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(name LIKE %s OR name_en LIKE %s OR slug LIKE %s)';
            array_push($values, $like, $like, $like);
        }

        $columns = 'id, name, name_en, slug, activity_type, logo_url, primary_color, accent_color, certificate_title, certificate_title_en, template_style, status, is_default';
        $sql = "SELECT {$columns} FROM " . self::table_name() . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY is_default DESC, name ASC, id ASC LIMIT {$limit}";
        $prepared = $values ? $wpdb->prepare($sql, $values) : $sql;
        $rows = $wpdb->get_results($prepared);
        $rows = is_array($rows) ? $rows : array();

        if ($search === '') {
            set_transient($cache_key, $rows, 10 * MINUTE_IN_SECONDS);
        }
        return $rows;
    }

    /** Resolve an active gallery by its unique slug without scanning every profile. */
    public static function get_active_profile_by_slug($slug) {
        global $wpdb;
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table_name() . " WHERE slug = %s AND status = 'active' LIMIT 1",
            $slug
        ));
    }

    public static function get_profile($id) {
        global $wpdb;
        $id = absint($id);
        if (!$id) { return self::get_default_profile(); }
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table_name() . ' WHERE id = %d LIMIT 1', $id));
    }

    public static function get_default_profile() {
        global $wpdb;
        $table = self::table_name();
        $row = $wpdb->get_row("SELECT * FROM {$table} WHERE is_default = 1 ORDER BY id ASC LIMIT 1");
        if (!$row) { $row = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id ASC LIMIT 1"); }
        return $row;
    }

    /** Build the canonical branding/display snapshot copied to and signed with each newly issued certificate. */
    public static function snapshot_data($profile) {
        if (!$profile || !is_object($profile)) { return array(); }
        $keys = array(
            'id', 'name', 'name_en', 'slug', 'activity_type', 'logo_url', 'primary_color', 'accent_color',
            'certificate_title', 'certificate_title_en', 'footer', 'footer_en', 'template_style',
            'show_logo_public', 'show_artwork_title', 'show_artwork_image_public', 'show_artist_name', 'show_medium', 'show_year_created',
            'show_dimensions', 'show_accession_number', 'show_issuer', 'show_issue_date', 'show_description',
            'show_collector_public', 'show_current_owner_public', 'show_previous_owner_public'
        );
        $data = array();
        foreach ($keys as $key) {
            if ($key === 'id') { $data[$key] = isset($profile->id) ? absint($profile->id) : 0; }
            elseif (in_array($key, array('show_logo_public','show_artwork_title','show_artwork_image_public','show_artist_name','show_medium','show_year_created','show_dimensions','show_accession_number','show_issuer','show_issue_date','show_description','show_collector_public','show_current_owner_public','show_previous_owner_public'), true)) {
                $data[$key] = isset($profile->{$key}) ? (int) !empty($profile->{$key}) : 0;
            } else { $data[$key] = isset($profile->{$key}) ? (string) $profile->{$key} : ''; }
        }
        return $data;
    }

    public static function encode_snapshot($profile) {
        $data = self::snapshot_data($profile);
        return wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public static function snapshot_json($profile_id) {
        $profile = self::get_profile($profile_id);
        if (!$profile || $profile->status !== 'active') { $profile = self::get_default_profile(); }
        return self::encode_snapshot($profile);
    }

    public static function snapshot_for_record($record, $base_settings = array()) {
        $snapshot = array();
        // Only trust the profile snapshot when its current signature validates. The
        // snapshot affects printed/public branding, so using it after a failed HMAC
        // check could let altered database data impersonate an institution.
        $signature_version = is_object($record) && isset($record->signature_version) ? (int) $record->signature_version : 0;
        $snapshot_trusted = false;
        if (in_array($signature_version, array(4, 5, WP_COA_Pro_Signature::CURRENT_VERSION), true) && is_object($record)) {
            $signature = new WP_COA_Pro_Signature();
            $snapshot_trusted = $signature->verify($record);
        }
        if ($snapshot_trusted && !empty($record->gallery_profile_snapshot)) {
            $decoded = json_decode((string) $record->gallery_profile_snapshot, true);
            if (is_array($decoded) && !empty($decoded['id'])) { $snapshot = $decoded; }
        }
        // Do not apply a live gallery profile to an older record without a signed
        // snapshot. Otherwise changing the gallery_id could alter its presentation
        // without being covered by that record's historical signature schema.
        if (!$snapshot && $base_settings) {
            $snapshot = array(
                'id' => 0, 'name' => isset($base_settings['issuer_name']) ? $base_settings['issuer_name'] : get_bloginfo('name'),
                'name_en' => isset($base_settings['issuer_name_en']) ? $base_settings['issuer_name_en'] : '',
                'logo_url' => isset($base_settings['logo_url']) ? $base_settings['logo_url'] : '',
                'primary_color' => isset($base_settings['primary_color']) ? $base_settings['primary_color'] : '#65513b',
                'accent_color' => isset($base_settings['accent_color']) ? $base_settings['accent_color'] : '#b29a68',
                'footer' => isset($base_settings['certificate_footer']) ? $base_settings['certificate_footer'] : '',
                'footer_en' => isset($base_settings['certificate_footer_en']) ? $base_settings['certificate_footer_en'] : '',
                'template_style' => 'classic'
            );
            foreach (array('show_logo_public','show_artwork_title','show_artwork_image_public','show_artist_name','show_medium','show_year_created','show_dimensions','show_accession_number','show_issuer','show_issue_date','show_description','show_collector_public','show_current_owner_public','show_previous_owner_public') as $key) {
                $snapshot[$key] = isset($base_settings[$key]) ? (int) !empty($base_settings[$key]) : 0;
            }
            $snapshot['certificate_title'] = 'گواهی اصالت اثر هنری';
            $snapshot['certificate_title_en'] = 'Certificate of Authenticity';
        }
        return $snapshot;
    }

    public static function settings_for_record($record, $base_settings) {
        $snapshot = self::snapshot_for_record($record, $base_settings);
        $settings = is_array($base_settings) ? $base_settings : array();
        $allowed = array('logo_url','primary_color','accent_color','footer','footer_en','name','name_en','certificate_title','certificate_title_en','template_style','activity_type');
        foreach ($allowed as $key) {
            if (array_key_exists($key, $snapshot)) { $settings[$key] = $snapshot[$key]; }
        }
        if (isset($snapshot['footer'])) { $settings['certificate_footer'] = $snapshot['footer']; }
        if (isset($snapshot['footer_en'])) { $settings['certificate_footer_en'] = $snapshot['footer_en']; }
        if (is_object($record)) {
            if (isset($record->issuer_name)) { $settings['issuer_name'] = (string) $record->issuer_name; }
            if (isset($record->issuer_name_en)) { $settings['issuer_name_en'] = (string) $record->issuer_name_en; }
        }
        $visibility_keys = array('show_logo_public','show_artwork_title','show_artwork_image_public','show_artist_name','show_medium','show_year_created','show_dimensions','show_accession_number','show_issuer','show_issue_date','show_description','show_collector_public','show_current_owner_public','show_previous_owner_public');
        foreach ($visibility_keys as $key) {
            if (array_key_exists($key, $snapshot)) { $settings[$key] = (int) !empty($snapshot[$key]); }
        }
        $settings['show_artwork_image_public'] = (!empty($base_settings['show_artwork_image_public']) && !empty($snapshot['show_artwork_image_public'])) ? 1 : 0;
        return $settings;
    }

    public static function register_menu() {
        add_submenu_page('artiey-platform', __('Galleries & Activity Profiles', 'wp-coa-pro'), __('Galleries & Profiles', 'wp-coa-pro'), 'manage_coa_certificates', 'wp-coa-pro-galleries', array(__CLASS__, 'render_page'));
    }

    public static function handle_save() {
        if (!current_user_can('manage_coa_certificates')) { wp_die(esc_html__('You do not have permission to manage gallery profiles.', 'wp-coa-pro')); }
        check_admin_referer('wp_coa_save_gallery');
        global $wpdb;
        $table = self::table_name();
        $id = isset($_POST['gallery_id']) ? absint($_POST['gallery_id']) : 0;
        $name = sanitize_text_field(self::post_scalar('name'));
        $name_en = sanitize_text_field(self::post_scalar('name_en'));
        if ($name === '' && $name_en === '') { self::redirect(array('coa_gallery_notice' => 'missing_name')); }
        $activity_types = self::activity_types();
        $activity = sanitize_key(self::post_scalar('activity_type')); if ($activity === '') { $activity = 'other'; }
        if (!isset($activity_types[$activity])) { $activity = 'other'; }
        $template = sanitize_key(self::post_scalar('template_style')); if ($template === '') { $template = self::DEFAULT_TEMPLATE; }
        if (!isset(self::templates()[$template])) { $template = self::DEFAULT_TEMPLATE; }
        $primary = sanitize_hex_color(self::post_scalar('primary_color')); if (!$primary) { $primary = '#65513b'; }
        $accent = sanitize_hex_color(self::post_scalar('accent_color')); if (!$accent) { $accent = '#b29a68'; }
        if (!$primary) { $primary = '#65513b'; }
        if (!$accent) { $accent = '#b29a68'; }
        $slug_raw = sanitize_title(self::post_scalar('slug'));
        if ($slug_raw === '') { $slug_raw = sanitize_title($name_en !== '' ? $name_en : $name); }
        if ($slug_raw === '') { $slug_raw = 'institution'; }
        $slug = $slug_raw;
        $suffix = 2;
        while ((int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s AND id != %d LIMIT 1", $slug, $id)) > 0) {
            $slug = substr($slug_raw, 0, 85) . '-' . $suffix;
            $suffix++;
            if ($suffix > 1000) { self::redirect(array('coa_gallery_notice' => 'save_failed')); }
        }
        $status = self::post_scalar('status') === 'inactive' ? 'inactive' : 'active';
        $checkboxes = array('show_logo_public','show_artwork_title','show_artwork_image_public','show_artist_name','show_medium','show_year_created','show_dimensions','show_accession_number','show_issuer','show_issue_date','show_description','show_collector_public','show_current_owner_public','show_previous_owner_public');
        $row = array(
            'name' => $name, 'name_en' => $name_en, 'slug' => $slug, 'activity_type' => $activity,
            'logo_url' => esc_url_raw(self::post_scalar('logo_url')),
            'primary_color' => $primary, 'accent_color' => $accent,
            'certificate_title' => sanitize_text_field(self::post_scalar('certificate_title')) !== '' ? sanitize_text_field(self::post_scalar('certificate_title')) : 'گواهی اصالت اثر هنری',
            'certificate_title_en' => sanitize_text_field(self::post_scalar('certificate_title_en')) !== '' ? sanitize_text_field(self::post_scalar('certificate_title_en')) : 'Certificate of Authenticity',
            'footer' => sanitize_textarea_field(self::post_scalar('footer')),
            'footer_en' => sanitize_textarea_field(self::post_scalar('footer_en')),
            'template_style' => $template, 'status' => $status, 'updated_at' => current_time('mysql', true)
        );
        foreach ($checkboxes as $key) { $row[$key] = isset($_POST[$key]) && is_scalar($_POST[$key]) ? 1 : 0; }
        if ($id) {
            $existing = self::get_profile($id);
            if (!$existing) { self::redirect(array('coa_gallery_notice' => 'save_failed')); }
            if (!empty($existing->is_default)) { $row['status'] = 'active'; }
            $formats = array_merge(array_fill(0, 14, '%s'), array_fill(0, count($checkboxes), '%d'));
            $updated = $wpdb->update($table, $row, array('id' => $id), $formats, array('%d'));
            if ($updated === false) { self::redirect(array('coa_gallery_notice' => 'save_failed')); }
        } else {
            $row['is_default'] = 0;
            $row['created_by'] = get_current_user_id();
            $row['created_at'] = current_time('mysql', true);
            $inserted = $wpdb->insert($table, $row);
            if ($inserted === false) { self::redirect(array('coa_gallery_notice' => 'save_failed')); }
            $id = (int) $wpdb->insert_id;
        }
        self::bust_public_cache();
        if (class_exists('WP_COA_Pro_Page_Integration')) { WP_COA_Pro_Page_Integration::invalidate_public_stats_cache(); }
        self::redirect(array('coa_gallery_notice' => 'saved', 'edit_gallery' => $id));
    }

    public static function handle_toggle() {
        if (!current_user_can('manage_coa_certificates')) { wp_die(esc_html__('You do not have permission to manage gallery profiles.', 'wp-coa-pro')); }
        $id = isset($_GET['gallery_id']) ? absint($_GET['gallery_id']) : 0;
        check_admin_referer('wp_coa_toggle_gallery_' . $id);
        $profile = self::get_profile($id);
        if (!$profile || !empty($profile->is_default)) { self::redirect(array('coa_gallery_notice' => 'toggle_failed')); }
        global $wpdb;
        $status = $profile->status === 'active' ? 'inactive' : 'active';
        $ok = $wpdb->update(self::table_name(), array('status' => $status, 'updated_at' => current_time('mysql', true)), array('id' => $id), array('%s','%s'), array('%d'));
        if ($ok !== false) {
            self::bust_public_cache();
            if (class_exists('WP_COA_Pro_Page_Integration')) { WP_COA_Pro_Page_Integration::invalidate_public_stats_cache(); }
        }
        self::redirect(array('coa_gallery_notice' => $ok === false ? 'toggle_failed' : 'toggled')); 
    }

    public static function render_page() {
        if (!current_user_can('manage_coa_certificates')) { return; }
        global $wpdb;
        $edit_id = isset($_GET['edit_gallery']) ? absint($_GET['edit_gallery']) : 0;
        $profile = $edit_id ? self::get_profile($edit_id) : null;
        $editing = $profile ? true : false;
        $defaults = array(
            'name' => '', 'name_en' => '', 'slug' => '', 'activity_type' => 'gallery', 'logo_url' => '',
            'primary_color' => '#65513b', 'accent_color' => '#b29a68', 'certificate_title' => 'گواهی اصالت اثر هنری',
            'certificate_title_en' => 'Certificate of Authenticity', 'footer' => '', 'footer_en' => '', 'template_style' => 'classic',
            'status' => 'active', 'is_default' => 0
        );
        foreach (array('show_logo_public','show_artwork_title','show_artwork_image_public','show_artist_name','show_medium','show_year_created','show_dimensions','show_accession_number','show_issuer','show_issue_date','show_description') as $key) { $defaults[$key] = 1; }
        foreach (array('show_collector_public','show_current_owner_public','show_previous_owner_public') as $key) { $defaults[$key] = 0; }
        $values = $profile ? array_merge($defaults, (array) $profile) : $defaults;
        $profiles = self::list_profiles(false);
        $activities = self::activity_types();
        $templates = self::templates();
        $notice = isset($_GET['coa_gallery_notice']) ? sanitize_key(wp_unslash($_GET['coa_gallery_notice'])) : '';
        $notice_map = array(
            'saved' => array('success', 'پروفایل مؤسسه ذخیره شد.'),
            'toggled' => array('success', 'وضعیت پروفایل تغییر کرد.'),
            'missing_name' => array('error', 'نام فارسی یا انگلیسی مؤسسه الزامی است.'),
            'save_failed' => array('error', 'ذخیره پروفایل ناموفق بود. خطای پایگاه داده را بررسی کنید.'),
            'toggle_failed' => array('error', 'تغییر وضعیت انجام نشد؛ پروفایل پیش‌فرض قابل غیرفعال‌کردن نیست.')
        );
        ?>
        <div class="wrap">
            <h1>گالری‌ها و پروفایل‌های صدور گواهی / Galleries & Activity Profiles</h1>
            <p>برای هر گالری، موزه، آتلیه، حراجی یا مجموعه، هویت بصری و الگوی مستقل تعریف کنید. تنظیمات هر پروفایل هنگام صدور گواهی به‌صورت snapshot داخل همان گواهی ثبت می‌شود؛ تغییر پروفایل در آینده، گواهی‌های قبلی را بی‌سروصدا تغییر نمی‌دهد.</p>
            <?php if ($notice && isset($notice_map[$notice])) : ?><div class="notice notice-<?php echo esc_attr($notice_map[$notice][0]); ?> is-dismissible"><p><?php echo esc_html($notice_map[$notice][1]); ?></p></div><?php endif; ?>
            <div class="aap-s-e21d82">
                <div class="postbox aap-s-1b3c88">
                    <h2 class="aap-s-45e6a6"><?php echo $editing ? 'ویرایش پروفایل / Edit Profile' : 'افزودن پروفایل جدید / Add Profile'; ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="wp_coa_save_gallery"><input type="hidden" name="gallery_id" value="<?php echo (int) ($editing ? $profile->id : 0); ?>">
                        <?php wp_nonce_field('wp_coa_save_gallery'); ?>
                        <p><label for="coa-gallery-name"><strong>نام مؤسسه / نام فارسی</strong></label><br><input class="widefat" id="coa-gallery-name" name="name" value="<?php echo esc_attr($values['name']); ?>" maxlength="255"><br><small>حداقل یکی از نام فارسی یا انگلیسی باید وارد شود.</small></p>
                        <p><label for="coa-gallery-name-en"><strong>Institution / English name</strong></label><br><input class="widefat" id="coa-gallery-name-en" name="name_en" value="<?php echo esc_attr($values['name_en']); ?>" maxlength="255"></p>
                        <p><label for="coa-gallery-slug"><strong>شناسه یکتا (Slug)</strong></label><br><input class="regular-text" id="coa-gallery-slug" name="slug" value="<?php echo esc_attr($values['slug']); ?>" maxlength="100"><br><small>در صورت خالی‌بودن، از نام مؤسسه ساخته می‌شود.</small></p>
                        <p><label for="coa-gallery-activity"><strong>نوع فعالیت مؤسسه</strong></label><br><select class="widefat" id="coa-gallery-activity" name="activity_type"><?php foreach ($activities as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($values['activity_type'], $key); ?>><?php echo esc_html($label[0] . ' / ' . $label[1]); ?></option><?php endforeach; ?></select></p>
                        <p><label for="coa-gallery-logo"><strong>نشانی لوگو (URL)</strong></label><br><input class="widefat" type="url" id="coa-gallery-logo" name="logo_url" value="<?php echo esc_attr($values['logo_url']); ?>" placeholder="https://example.com/logo.png"></p>
                        <div class="aap-s-a1bf87"><p><label for="coa-gallery-primary"><strong>رنگ اصلی</strong></label><br><input id="coa-gallery-primary" type="color" name="primary_color" value="<?php echo esc_attr($values['primary_color']); ?>"></p><p><label for="coa-gallery-accent"><strong>رنگ مکمل</strong></label><br><input id="coa-gallery-accent" type="color" name="accent_color" value="<?php echo esc_attr($values['accent_color']); ?>"></p></div>
                        <p><label for="coa-gallery-template"><strong>قالب گواهی</strong></label><br><select class="widefat" id="coa-gallery-template" name="template_style"><?php foreach ($templates as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($values['template_style'], $key); ?>><?php echo esc_html($label[0] . ' / ' . $label[1]); ?></option><?php endforeach; ?></select></p>
                        <p><label for="coa-gallery-title"><strong>عنوان گواهی / فارسی</strong></label><br><input class="widefat" id="coa-gallery-title" name="certificate_title" value="<?php echo esc_attr($values['certificate_title']); ?>" maxlength="255"></p>
                        <p><label for="coa-gallery-title-en"><strong>Certificate title / English</strong></label><br><input class="widefat" id="coa-gallery-title-en" name="certificate_title_en" value="<?php echo esc_attr($values['certificate_title_en']); ?>" maxlength="255"></p>
                        <p><label for="coa-gallery-footer"><strong>پاورقی گواهی / فارسی</strong></label><br><textarea class="widefat" rows="2" id="coa-gallery-footer" name="footer"><?php echo esc_textarea($values['footer']); ?></textarea></p>
                        <p><label for="coa-gallery-footer-en"><strong>Certificate footer / English</strong></label><br><textarea class="widefat" rows="2" id="coa-gallery-footer-en" name="footer_en"><?php echo esc_textarea($values['footer_en']); ?></textarea></p>
                        <?php if (empty($values['is_default'])) : ?><p><label for="coa-gallery-status"><strong>وضعیت پروفایل</strong></label><br><select id="coa-gallery-status" name="status"><option value="active" <?php selected($values['status'], 'active'); ?>>فعال / Active</option><option value="inactive" <?php selected($values['status'], 'inactive'); ?>>غیرفعال / Inactive</option></select></p><?php else : ?><p><strong>پروفایل پیش‌فرض:</strong> این پروفایل همیشه برای صدور گواهی در دسترس می‌ماند.</p><?php endif; ?>
                        <hr><h3>فیلدهای قابل نمایش در استعلام عمومی</h3><p class="description">اطلاعات مالک و مجموعه‌دار به‌صورت مستقل و پیش‌فرض خصوصی می‌مانند.</p>
                        <?php
                        $visibility_labels = array(
                            'show_logo_public' => 'نمایش لوگو', 'show_artwork_title' => 'عنوان اثر', 'show_artwork_image_public' => 'تصویر اصل اثر در استعلام عمومی', 'show_artist_name' => 'نام هنرمند',
                            'show_medium' => 'تکنیک و مواد', 'show_year_created' => 'سال خلق', 'show_dimensions' => 'ابعاد',
                            'show_accession_number' => 'شماره ثبت', 'show_issuer' => 'مؤسسه صادرکننده', 'show_issue_date' => 'تاریخ صدور',
                            'show_description' => 'توضیحات اثر', 'show_collector_public' => 'نام مجموعه‌دار / مجموعه',
                            'show_current_owner_public' => 'مالک فعلی', 'show_previous_owner_public' => 'مالک پیشین'
                        );
                        foreach ($visibility_labels as $key => $label) : ?>
                            <label class="aap-s-5edb17"><input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(!empty($values[$key])); ?>> <?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                        <p><?php submit_button($editing ? 'ذخیره تغییرات / Save Profile' : 'افزودن پروفایل / Add Profile', 'primary', 'submit', false); ?> <?php if ($editing) : ?> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wp-coa-pro-galleries')); ?>">لغو / Cancel</a><?php endif; ?></p>
                    </form>
                </div>
                <div class="aap-s-19cd86">
                    <h2>پروفایل‌های ثبت‌شده / Registered Profiles</h2>
                    <table class="widefat striped"><thead><tr><th>مؤسسه</th><th>نوع فعالیت</th><th>قالب</th><th>گواهی‌ها</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
                    <?php if ($profiles) : foreach ($profiles as $item) :
                        $count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $wpdb->prefix . 'coa_certificates WHERE gallery_id = %d', (int) $item->id));
                        $toggle_url = wp_nonce_url(add_query_arg(array('action' => 'wp_coa_toggle_gallery', 'gallery_id' => (int) $item->id), admin_url('admin-post.php')), 'wp_coa_toggle_gallery_' . (int) $item->id);
                        ?>
                        <tr><td><strong><?php echo esc_html($item->name); ?></strong><?php if ($item->name_en !== '') : ?><br><small dir="ltr"><?php echo esc_html($item->name_en); ?></small><?php endif; ?><?php if (!empty($item->is_default)) : ?><br><span class="dashicons dashicons-admin-home" aria-hidden="true"></span> <small>پیش‌فرض</small><?php endif; ?></td>
                        <td><?php echo esc_html(self::activity_label($item->activity_type)); ?></td><td><?php echo isset($templates[$item->template_style]) ? esc_html($templates[$item->template_style][0]) : esc_html($item->template_style); ?></td><td><?php echo (int) $count; ?></td><td><?php echo $item->status === 'active' ? '<span class="aap-s-eb34d1">فعال</span>' : '<span class="aap-s-2e3ebe">غیرفعال</span>'; ?></td>
                        <td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page' => 'wp-coa-pro-galleries', 'edit_gallery' => (int) $item->id), admin_url('admin.php'))); ?>">ویرایش</a> <?php if (empty($item->is_default)) : ?><a class="button button-small" href="<?php echo esc_url($toggle_url); ?>" onclick="return confirm('وضعیت این پروفایل تغییر کند؟');"><?php echo $item->status === 'active' ? 'غیرفعال‌سازی' : 'فعال‌سازی'; ?></a><?php endif; ?></td></tr>
                    <?php endforeach; else : ?><tr><td colspan="6">هنوز پروفایلی ثبت نشده است.</td></tr><?php endif; ?>
                    </tbody></table>
                    <p class="description">حذف سخت پروفایل‌ها ارائه نشده تا ارجاع گواهی‌های قبلی و تاریخچه هویتی حفظ شود. به‌جای حذف، پروفایل را غیرفعال کنید.</p>
                </div>
            </div>
        </div>
        <?php
    }

    private static function post_scalar($key) {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) { return ''; }
        return (string) wp_unslash($_POST[$key]);
    }

    private static function redirect($args = array()) {
        $url = add_query_arg(array_merge(array('page' => 'wp-coa-pro-galleries'), $args), admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
