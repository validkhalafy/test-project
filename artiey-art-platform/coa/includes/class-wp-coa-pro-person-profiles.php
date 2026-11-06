<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Structured, bilingual public profiles for artists and collectors/collections.
 * Contact data is private unless a profile explicitly opts into public display.
 */
final class WP_COA_Pro_Person_Profiles {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'), 35);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        add_action('admin_post_wp_coa_save_person_profile', array(__CLASS__, 'handle_save'));
        add_shortcode('coa_profile_directory', array(__CLASS__, 'render_directory'));
        add_shortcode('coa_artist_directory', array(__CLASS__, 'render_artist_directory'));
        add_shortcode('coa_collector_directory', array(__CLASS__, 'render_collector_directory'));
        add_shortcode('coa_profile_view', array(__CLASS__, 'render_profile_view'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'), 25);
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coa_person_profiles';
    }

    /** Increment the cache generation instead of attempting to enumerate transient keys. */
    public static function bust_public_cache() {
        $generation = max(1, absint(get_option('wp_coa_pro_profile_cache_generation', 1)));
        update_option('wp_coa_pro_profile_cache_generation', $generation + 1, false);
    }

    /** Invalidate cached signature-verified related artwork lists after certificate changes. */
    public static function bust_linked_works_cache() {
        $generation = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : (string) (microtime(true) . wp_rand());
        update_option('wp_coa_pro_linked_works_generation', sanitize_key($generation), false);
    }

    /** Render responsive WordPress-generated image sizes whenever a media attachment is known. */
    private static function profile_image_markup($url, $attachment_id, $size, $alt, $class = '', $loading = 'lazy', $sizes = '100vw', $fetchpriority = '') {
        $url = esc_url((string) $url, array('http', 'https'));
        if ($url === '') { return ''; }
        $attachment_id = absint($attachment_id);
        if ($attachment_id && function_exists('wp_get_attachment_image') && wp_attachment_is_image($attachment_id)) {
            $attributes = array('class' => (string) $class, 'alt' => (string) $alt, 'loading' => in_array($loading, array('lazy', 'eager'), true) ? $loading : 'lazy', 'decoding' => 'async', 'sizes' => (string) $sizes);
            if ($fetchpriority === 'high') { $attributes['fetchpriority'] = 'high'; }
            $image = wp_get_attachment_image($attachment_id, $size, false, $attributes);
            if (is_string($image) && $image !== '') { return $image; }
        }
        $attributes = ' src="' . esc_url($url) . '" alt="' . esc_attr((string) $alt) . '" class="' . esc_attr((string) $class) . '" loading="' . esc_attr(in_array($loading, array('lazy', 'eager'), true) ? $loading : 'lazy') . '" decoding="async"';
        if ((string) $class === '') { $attributes .= ' width="480" height="360"'; }
        if ($fetchpriority === 'high') { $attributes .= ' fetchpriority="high"'; }
        return '<img' . $attributes . '>';
    }

    /** Resolve a media ID on form submission rather than issuing a lookup on every public page view. */
    private static function resolve_image_attachment_id($field_name, $url) {
        $url = esc_url_raw((string) $url, array('http', 'https'));
        $attachment_id = absint(self::post_scalar($field_name));
        if ($attachment_id && function_exists('wp_attachment_is_image') && wp_attachment_is_image($attachment_id)) {
            $attachment_url = function_exists('wp_get_attachment_url') ? (string) wp_get_attachment_url($attachment_id) : '';
            // Do not pair a stale attachment ID with a different URL entered in the form.
            if ($url === '' || $url === $attachment_url || (function_exists('attachment_url_to_postid') && absint(attachment_url_to_postid($url)) === $attachment_id)) {
                return $attachment_id;
            }
        }
        if ($url !== '' && function_exists('attachment_url_to_postid')) {
            $url_id = absint(attachment_url_to_postid($url));
            if ($url_id && function_exists('wp_attachment_is_image') && wp_attachment_is_image($url_id)) { return $url_id; }
        }
        return 0;
    }

    private static function cache_key($scope, $args) {
        $generation = max(1, absint(get_option('wp_coa_pro_profile_cache_generation', 1)));
        return 'wp_coa_pp_' . sanitize_key($scope) . '_' . substr(hash('sha256', serialize(array($generation, $args))), 0, 40);
    }

    public static function types() {
        return array(
            'artist' => array('هنرمند', 'Artist'),
            'collector' => array('کلکتور / مجموعه‌دار', 'Collector / Collection'),
        );
    }

    public static function entity_kinds() {
        return array(
            'person' => array('شخص حقیقی', 'Individual'),
            'collection' => array('مجموعه هنری', 'Art Collection'),
            'foundation' => array('بنیاد / مؤسسه', 'Foundation / Institution'),
            'corporate' => array('مجموعه سازمانی', 'Corporate Collection'),
        );
    }

    public static function get_profile($id, $public_only = false) {
        global $wpdb;
        $id = absint($id);
        if (!$id) { return null; }
        $sql = 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d';
        if ($public_only) { $sql .= " AND status = 'published'"; }
        $sql .= ' LIMIT 1';
        return $wpdb->get_row($wpdb->prepare($sql, $id));
    }

    public static function get_by_slug($slug, $type = '') {
        global $wpdb;
        $slug = sanitize_title((string) $slug);
        if ($slug === '') { return null; }
        $type = ($type !== '' && isset(self::types()[$type])) ? $type : '';
        $cache_key = self::cache_key('slug', array($slug, $type));
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['not_found'])) { return null; }
        if (is_object($cached)) { return $cached; }

        $sql = 'SELECT * FROM ' . self::table_name() . " WHERE slug = %s AND status = 'published'";
        $values = array($slug);
        if ($type !== '') {
            $sql .= ' AND profile_type = %s';
            $values[] = $type;
        }
        $sql .= ' LIMIT 1';
        $profile = $wpdb->get_row($wpdb->prepare($sql, $values));
        // Avoid creating one database transient for every arbitrary/missing public slug.
        if ($profile) { set_transient($cache_key, $profile, 10 * MINUTE_IN_SECONDS); }
        return $profile;
    }

    public static function list_profiles($type = '', $status = 'published', $limit = 100, $search = '', $featured_only = false, $summary_only = false) {
        global $wpdb;
        $where = array('1=1');
        $values = array();
        if ($type !== '' && isset(self::types()[$type])) {
            $where[] = 'profile_type = %s';
            $values[] = $type;
        }
        if (in_array($status, array('published', 'draft', 'private'), true)) {
            $where[] = 'status = %s';
            $values[] = $status;
        } elseif ($status !== 'all') {
            $where[] = "status = 'published'";
            $status = 'published';
        }
        if ($featured_only) { $where[] = 'is_featured = 1'; }
        $search = sanitize_text_field((string) $search);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(display_name LIKE %s OR display_name_en LIKE %s OR tagline LIKE %s OR tagline_en LIKE %s OR disciplines LIKE %s OR disciplines_en LIKE %s OR collection_focus LIKE %s OR collection_focus_en LIKE %s)';
            array_push($values, $like, $like, $like, $like, $like, $like, $like, $like);
        }
        $limit = max(1, min(500, absint($limit)));
        // Cache only low-cardinality public directory queries. Never persist arbitrary search
        // strings as transients: crawlers/bots could otherwise create unbounded transient rows.
        $cache_key = '';
        if ($status === 'published' && $search === '') {
            $cache_key = self::cache_key('list', array($type, $status, $limit, $featured_only, $summary_only));
            $cached = get_transient($cache_key);
            if (is_array($cached)) { return $cached; }
        }
        // Directory cards do not need all biography, education, awards, and exhibition fields.
        if ($summary_only === 'admin') {
            $columns = 'id, profile_type, entity_kind, display_name, display_name_en, slug, status, is_verified, is_featured, updated_at';
        } elseif ($summary_only === 'no_bio') {
            // If excerpts are disabled, do not retrieve biography columns at all.
            $columns = 'id, profile_type, entity_kind, display_name, display_name_en, slug, tagline, tagline_en, profile_image_url, profile_image_id, city, country, city_en, country_en, show_location_public, is_verified, is_featured, status';
        } elseif ($summary_only === true) {
            // A short excerpt is enough for directory cards; do not transfer multi-page bios per card.
            $columns = 'id, profile_type, entity_kind, display_name, display_name_en, slug, tagline, tagline_en, LEFT(biography, 600) AS biography, LEFT(biography_en, 600) AS biography_en, profile_image_url, profile_image_id, city, country, city_en, country_en, show_location_public, is_verified, is_featured, status';
        } else {
            $columns = '*';
        }
        $sql = 'SELECT ' . $columns . ' FROM ' . self::table_name() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY is_featured DESC, is_verified DESC, display_name ASC, id DESC LIMIT ' . $limit;
        $results = $values ? $wpdb->get_results($wpdb->prepare($sql, $values)) : $wpdb->get_results($sql);
        $results = is_array($results) ? $results : array();
        if ($cache_key !== '') { set_transient($cache_key, $results, 10 * MINUTE_IN_SECONDS); }
        return $results;
    }

    public static function count_profiles($type = '', $status = 'published') {
        global $wpdb;
        $where = array('1=1');
        $values = array();
        if ($type !== '' && isset(self::types()[$type])) { $where[] = 'profile_type = %s'; $values[] = $type; }
        if ($status !== 'all') { $where[] = 'status = %s'; $values[] = $status; }
        $cache_key = '';
        if ($status === 'published') {
            $cache_key = self::cache_key('count', array($type, $status));
            $cached = get_transient($cache_key);
            if (is_numeric($cached)) { return (int) $cached; }
        }
        $sql = 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE ' . implode(' AND ', $where);
        $count = (int) $wpdb->get_var($values ? $wpdb->prepare($sql, $values) : $sql);
        if ($cache_key !== '') { set_transient($cache_key, $count, 10 * MINUTE_IN_SECONDS); }
        return $count;
    }

    public static function linked_work_count($profile) {
        global $wpdb;
        if (!$profile || empty($profile->id)) { return 0; }
        $column = $profile->profile_type === 'artist' ? 'artist_profile_id' : 'collector_profile_id';
        $table = $wpdb->prefix . 'coa_certificates';
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = %d AND status = 'active'", absint($profile->id)));
    }

    private static function linked_works($profile, $limit = 24) {
        global $wpdb;
        if (!$profile || empty($profile->id)) { return array(); }
        $column = $profile->profile_type === 'artist' ? 'artist_profile_id' : 'collector_profile_id';
        $table = $wpdb->prefix . 'coa_certificates';
        $limit = max(1, min(50, absint($limit)));
        $generation = sanitize_key((string) get_option('wp_coa_pro_linked_works_generation', '1'));
        if ($generation === '') { $generation = '1'; }
        $cache_key = 'wp_coa_lw_' . $generation . '_' . md5($profile->profile_type . ':' . absint($profile->id) . ':' . $limit);
        $cached = get_transient($cache_key);
        if (is_array($cached)) { return $cached; }
        $signature_columns = 'id, gallery_id, activity_type, certificate_template, gallery_profile_snapshot, artist_profile_id, collector_profile_id, certificate_code, artwork_title, artwork_title_en, artist_name, artist_name_en, medium, medium_en, year_created, dimensions, accession_number, artwork_description, artwork_description_en, collector_name, collector_name_en, current_owner_name, current_owner_name_en, previous_owner_name, previous_owner_name_en, issuer_name, issuer_name_en, issue_date, status, integrity_hash, integrity_payload, signature_version, integrity_key_source';
        $records = $wpdb->get_results($wpdb->prepare("SELECT {$signature_columns} FROM {$table} WHERE {$column} = %d AND status = 'active' ORDER BY issue_date DESC LIMIT {$limit}", absint($profile->id)));
        $safe = array();
        $signature = new WP_COA_Pro_Signature();
        foreach ((array) $records as $record) {
            // A database row must have a valid signature before its linked profile relationship is trusted.
            if (!$signature->verify($record)) { continue; }
            $safe[] = (object) array(
                'certificate_code' => (string) $record->certificate_code,
                'artwork_title' => (string) $record->artwork_title,
                'artwork_title_en' => (string) $record->artwork_title_en,
                'medium' => (string) $record->medium,
                'medium_en' => (string) $record->medium_en,
                'year_created' => (string) $record->year_created,
                'dimensions' => (string) $record->dimensions,
            );
        }
        set_transient($cache_key, $safe, 10 * MINUTE_IN_SECONDS);
        return $safe;
    }

    /** Aggregate linked certificate counts for a list without issuing one query per profile. */
    private static function linked_work_counts_for_profiles($profiles) {
        global $wpdb;
        $ids = array();
        foreach ((array) $profiles as $profile) { if (!empty($profile->id)) { $ids[] = absint($profile->id); } }
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) { return array(); }
        $table = $wpdb->prefix . 'coa_certificates';
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "SELECT profile_id, SUM(total) AS total FROM (SELECT artist_profile_id AS profile_id, COUNT(*) AS total FROM {$table} WHERE status = 'active' AND artist_profile_id IN ({$placeholders}) GROUP BY artist_profile_id UNION ALL SELECT collector_profile_id AS profile_id, COUNT(*) AS total FROM {$table} WHERE status = 'active' AND collector_profile_id IN ({$placeholders}) GROUP BY collector_profile_id) AS linked GROUP BY profile_id";
        $values = array_merge($ids, $ids);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $values));
        $counts = array();
        foreach ((array) $rows as $row) { $counts[(int) $row->profile_id] = (int) $row->total; }
        return $counts;
    }

    public static function enqueue_admin_assets($hook_suffix) {
        if (!isset($_GET['page']) || !is_scalar($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== 'wp-coa-pro-people') {
            return;
        }
        if (!current_user_can('manage_coa_certificates')) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        $script = <<<'JS'
jQuery(function($) {
    'use strict';
    function statusFor($button, message, isError) {
        var $cell = $button.closest('td');
        var $status = $cell.find('.wp-coa-profile-media-status').first();
        if (!$status.length) {
            $status = $('<p class="wp-coa-profile-media-status description" role="status" aria-live="polite"></p>').appendTo($cell);
        }
        $status.text(message || '').toggleClass('is-error', !!isError);
    }
    function mediaReady() { return !!(window.wp && window.wp.media); }
    function attachmentIsImage(attachment) {
        if (!attachment) { return false; }
        var mime = String(attachment.mime || attachment.mime_type || '');
        return !mime || /^image\//i.test(mime);
    }
    function restoreFrameSelection(frame, idTarget) {
        var state = frame && frame.state ? frame.state() : null;
        var selection = state && state.get ? state.get('selection') : null;
        if (!selection || !selection.reset || !selection.add) { return; }
        selection.reset();
        var id = idTarget ? parseInt(idTarget.value, 10) || 0 : 0;
        if (id > 0) {
            var attachment = wp.media.attachment(id);
            selection.add(attachment);
            if (attachment && attachment.fetch) { attachment.fetch(); }
        }
    }
    $(document).on('click', '.wp-coa-profile-media-picker', function(event) {
        event.preventDefault();
        var $button = $(this);
        var target = document.getElementById($button.data('target'));
        var preview = document.getElementById($button.data('preview'));
        var idTarget = target && document.getElementById(target.id.replace('_url', '_id'));
        if (!mediaReady()) {
            statusFor($button, 'کتابخانه رسانه وردپرس در دسترس نیست. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.', true);
            return;
        }
        if (!target) { statusFor($button, 'فیلد تصویر پیدا نشد؛ صفحه را تازه‌سازی کنید.', true); return; }
        var frame = $button.data('wpCoaMediaFrame');
        if (!frame) {
            frame = wp.media({
                title: 'انتخاب تصویر پروفایل',
                button: { text: 'استفاده از این تصویر' },
                library: { type: 'image' },
                multiple: false
            });
            frame.on('select', function() {
                var selection = frame.state().get('selection');
                var model = selection && selection.first ? selection.first() : null;
                var attachment = model && model.toJSON ? model.toJSON() : null;
                if (!attachment || !attachment.id || !attachment.url || !attachmentIsImage(attachment)) {
                    statusFor($button, 'لطفاً یک فایل تصویری معتبر انتخاب کنید.', true);
                    return;
                }
                if (idTarget) { idTarget.value = String(attachment.id); }
                target.value = attachment.url;
                target.dispatchEvent(new Event('change', { bubbles: true }));
                target.dispatchEvent(new Event('input', { bubbles: true }));
                // Restore the selected ID after bubbling events; this is the authoritative attachment reference.
                if (idTarget) { idTarget.value = String(attachment.id); }
                var previewUrl = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) ? attachment.sizes.medium.url : attachment.url;
                if (preview) {
                    preview.src = previewUrl;
                    preview.alt = attachment.alt || attachment.title || '';
                    preview.style.display = 'block';
                }
                statusFor($button, '', false);
            });
            frame.on('open', function() { restoreFrameSelection(frame, idTarget); });
            $button.data('wpCoaMediaFrame', frame);
        }
        statusFor($button, '', false);
        frame.open();
    });
    $(document).on('input', '#profile_image_url, #cover_image_url', function() {
        var idTarget = document.getElementById(this.id.replace('_url', '_id'));
        if (idTarget) { idTarget.value = '0'; }
    });
    $(document).on('click', '.wp-coa-profile-media-clear', function(event) {
        event.preventDefault();
        var $button = $(this);
        var target = document.getElementById($button.data('target'));
        var preview = document.getElementById($button.data('preview'));
        var idTarget = target && document.getElementById(target.id.replace('_url', '_id'));
        if (target) {
            target.value = '';
            target.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (idTarget) { idTarget.value = '0'; }
        if (preview) { preview.removeAttribute('src'); preview.style.display = 'none'; }
        statusFor($button, '', false);
    });
});
JS;
        wp_add_inline_script('jquery', $script);
    }

    public static function register_menu() {
        add_submenu_page('artiey-platform', 'پروفایل هنرمندان و کلکتورها', 'هنرمندان و کلکتورها', 'manage_coa_certificates', 'wp-coa-pro-people', array(__CLASS__, 'render_admin_page'));
    }

    public static function handle_save() {
        if (!current_user_can('manage_coa_certificates')) {
            wp_die(esc_html__('You do not have permission to manage artist and collector profiles.', 'wp-coa-pro'));
        }
        check_admin_referer('wp_coa_save_person_profile');
        global $wpdb;
        $id = isset($_POST['profile_id']) && is_scalar($_POST['profile_id']) ? absint(wp_unslash($_POST['profile_id'])) : 0;
        $old = $id ? self::get_profile($id, false) : null;
        if ($id && !$old) { self::redirect_admin(array('profile_error' => 'not_found')); }

        $type = sanitize_key(self::post_scalar('profile_type'));
        if ($type === '') { $type = 'artist'; }
        if (!isset(self::types()[$type])) { $type = 'artist'; }
        if ($old && $old->profile_type !== $type) {
            global $wpdb;
            $linked_column = $old->profile_type === 'artist' ? 'artist_profile_id' : 'collector_profile_id';
            $linked_total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $wpdb->prefix . 'coa_certificates WHERE ' . $linked_column . ' = %d', $id));
            if ($linked_total > 0) { self::redirect_admin(array('profile_error' => 'type_locked', 'edit_profile' => $id)); }
        }
        $kind = sanitize_key(self::post_scalar('entity_kind'));
        if ($kind === '') { $kind = 'person'; }
        if (!isset(self::entity_kinds()[$kind])) { $kind = 'person'; }
        if ($type === 'artist') { $kind = 'person'; }

        $name = self::post_text('display_name', 255);
        $name_en = self::post_text('display_name_en', 255);
        if ($name === '' && $name_en === '') { self::redirect_admin(array('profile_error' => 'missing_name')); }

        $slug_input = isset($_POST['slug']) && is_scalar($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        $slug_base = $slug_input !== '' ? $slug_input : sanitize_title($name_en !== '' ? $name_en : $name);
        if ($slug_base === '') { $slug_base = ($type === 'artist' ? 'artist' : 'collector') . '-' . wp_generate_password(6, false, false); }
        $slug = $slug_base;
        $suffix = 2;
        while ((int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table_name() . ' WHERE slug = %s AND id != %d LIMIT 1', $slug, $id)) > 0) {
            $slug = substr($slug_base, 0, 160) . '-' . $suffix;
            $suffix++;
            if ($suffix > 1000) { self::redirect_admin(array('profile_error' => 'slug_conflict')); }
        }

        $requested_status = sanitize_key(self::post_scalar('status'));
        $status = in_array($requested_status, array('draft', 'private', 'published'), true) ? $requested_status : 'draft';
        $is_verified = current_user_can('manage_options') ? (self::post_checkbox('is_verified') ? 1 : 0) : (int) ($old ? $old->is_verified : 0);
        $data = array(
            'profile_type' => $type,
            'entity_kind' => $kind,
            'display_name' => $name,
            'display_name_en' => $name_en,
            'slug' => $slug,
            'tagline' => self::post_text('tagline', 500),
            'tagline_en' => self::post_text('tagline_en', 500),
            'seo_title' => self::post_text('seo_title', 255),
            'seo_description' => self::post_textarea('seo_description', 500),
            'biography' => self::post_textarea('biography', 20000),
            'biography_en' => self::post_textarea('biography_en', 20000),
            'profile_image_url' => self::post_url('profile_image_url'),
            'profile_image_id' => self::resolve_image_attachment_id('profile_image_id', self::post_url('profile_image_url')),
            'cover_image_url' => self::post_url('cover_image_url'),
            'cover_image_id' => self::resolve_image_attachment_id('cover_image_id', self::post_url('cover_image_url')),
            'birth_year' => self::post_text('birth_year', 20),
            'death_year' => self::post_text('death_year', 20),
            'founded_year' => self::post_text('founded_year', 20),
            'nationality' => self::post_text('nationality', 255),
            'nationality_en' => self::post_text('nationality_en', 255),
            'country' => self::post_text('country', 255),
            'country_en' => self::post_text('country_en', 255),
            'city' => self::post_text('city', 255),
            'city_en' => self::post_text('city_en', 255),
            'disciplines' => self::post_textarea('disciplines', 5000),
            'disciplines_en' => self::post_textarea('disciplines_en', 5000),
            'collection_focus' => self::post_textarea('collection_focus', 5000),
            'collection_focus_en' => self::post_textarea('collection_focus_en', 5000),
            'education' => self::post_textarea('education', 10000),
            'education_en' => self::post_textarea('education_en', 10000),
            'awards' => self::post_textarea('awards', 10000),
            'awards_en' => self::post_textarea('awards_en', 10000),
            'exhibitions' => self::post_textarea('exhibitions', 12000),
            'exhibitions_en' => self::post_textarea('exhibitions_en', 12000),
            'website_url' => self::post_url('website_url'),
            'portfolio_url' => self::post_url('portfolio_url'),
            'contact_email' => sanitize_email(self::post_text('contact_email', 190)),
            'contact_phone' => self::post_text('contact_phone', 80),
            'instagram_url' => self::post_url('instagram_url'),
            'linkedin_url' => self::post_url('linkedin_url'),
            'facebook_url' => self::post_url('facebook_url'),
            'other_social_url' => self::post_url('other_social_url'),
            'show_email_public' => self::post_checkbox('show_email_public') ? 1 : 0,
            'show_phone_public' => self::post_checkbox('show_phone_public') ? 1 : 0,
            'show_location_public' => self::post_checkbox('show_location_public') ? 1 : 0,
            'show_works_public' => self::post_checkbox('show_works_public') ? 1 : 0,
            'is_verified' => $is_verified,
            'is_featured' => self::post_checkbox('is_featured') ? 1 : 0,
            'status' => $status,
            'updated_at' => current_time('mysql', true),
        );
        if (!$old) {
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = current_time('mysql', true);
            $saved = $wpdb->insert(self::table_name(), $data);
            $id = (int) $wpdb->insert_id;
        } else {
            $saved = $wpdb->update(self::table_name(), $data, array('id' => $id));
        }
        if ($saved === false || !$id) { self::redirect_admin(array('profile_error' => 'save_failed')); }
        delete_transient('wp_coa_pro_dashboard_integrity_health');
        self::bust_public_cache();
        self::redirect_admin(array('profile_saved' => $id));
    }

    public static function render_admin_page() {
        if (!current_user_can('manage_coa_certificates')) { wp_die(esc_html__('You do not have permission to manage artist and collector profiles.', 'wp-coa-pro')); }
        $edit_id = isset($_GET['edit_profile']) ? absint($_GET['edit_profile']) : 0;
        $profile = $edit_id ? self::get_profile($edit_id, false) : null;
        $profiles = self::list_profiles('', 'all', 500, '', false, 'admin');
        $linked_counts = self::linked_work_counts_for_profiles($profiles);
        $v = function($key, $default = '') use ($profile) { return $profile && isset($profile->{$key}) ? (string) $profile->{$key} : (string) $default; };
        $checked = function($key, $default = false) use ($profile) { return $profile && isset($profile->{$key}) ? !empty($profile->{$key}) : $default; };
        ?>
        <div class="wrap wp-coa-person-admin" dir="rtl">
            <h1>پروفایل هنرمندان و کلکتورها</h1>
            <p>شناسنامه حرفه‌ای دوزبانه برای هنرمندان، مجموعه‌داران و مجموعه‌های هنری بسازید. ارتباط پروفایل با گواهی‌ها با شناسه مستقل ثبت می‌شود؛ اطلاعات تماس به‌طور پیش‌فرض عمومی نیست.</p>
            <?php if (isset($_GET['profile_saved'])) : ?><div class="notice notice-success is-dismissible"><p>پروفایل با شناسه <?php echo absint($_GET['profile_saved']); ?> ذخیره شد.</p></div><?php endif; ?>
            <?php if (isset($_GET['profile_error'])) : ?><div class="notice notice-error"><p><?php echo isset($_GET['profile_error']) && sanitize_key(is_scalar($_GET['profile_error']) ? (string) wp_unslash($_GET['profile_error']) : '') === 'type_locked' ? 'نوع پروفایل قابل تغییر نیست؛ این شناسه به گواهی‌های قبلی متصل است. برای حفظ تاریخچه، پروفایل جدیدی با نوع دیگر بسازید.' : 'ذخیره‌سازی انجام نشد. نام فارسی یا انگلیسی و یک نامک معتبر وارد کنید؛ سپس دوباره تلاش کنید.'; ?></p></div><?php endif; ?>
            <div class="card aap-s-e183b0">
                <h2><?php echo $profile ? 'ویرایش پروفایل' : 'ایجاد پروفایل جدید'; ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wp_coa_save_person_profile"><input type="hidden" name="profile_id" value="<?php echo (int) ($profile ? $profile->id : 0); ?>"><?php wp_nonce_field('wp_coa_save_person_profile'); ?>
                    <h3>هویت و انتشار</h3><table class="form-table"><tbody>
                    <tr><th><label for="profile_type">نوع پروفایل</label></th><td><select id="profile_type" name="profile_type"><option value="artist" <?php selected($v('profile_type', 'artist'), 'artist'); ?>>هنرمند</option><option value="collector" <?php selected($v('profile_type'), 'collector'); ?>>کلکتور / مجموعه‌دار</option></select> <select name="entity_kind" aria-label="نوع مجموعه"><option value="person" <?php selected($v('entity_kind', 'person'), 'person'); ?>>شخص حقیقی</option><?php foreach (self::entity_kinds() as $key => $labels) : if ($key === 'person') { continue; } ?><option value="<?php echo esc_attr($key); ?>" <?php selected($v('entity_kind', 'person'), $key); ?>><?php echo esc_html($labels[0]); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th><label for="display_name">نام نمایشی فارسی *</label></th><td><input required class="regular-text" maxlength="255" id="display_name" name="display_name" value="<?php echo esc_attr($v('display_name')); ?>"></td></tr>
                    <tr><th><label for="display_name_en">نام نمایشی انگلیسی</label></th><td><input class="regular-text" maxlength="255" id="display_name_en" name="display_name_en" dir="ltr" value="<?php echo esc_attr($v('display_name_en')); ?>"></td></tr>
                    <tr><th><label for="slug">نامک عمومی</label></th><td><input class="regular-text" maxlength="180" id="slug" name="slug" dir="ltr" value="<?php echo esc_attr($v('slug')); ?>"><p class="description">در صورت خالی بودن، از نام پروفایل ساخته می‌شود.</p></td></tr>
                    <tr><th><label for="status">وضعیت</label></th><td><select id="status" name="status"><option value="draft" <?php selected($v('status', 'draft'), 'draft'); ?>>پیش‌نویس</option><option value="published" <?php selected($v('status'), 'published'); ?>>انتشار عمومی</option><option value="private" <?php selected($v('status'), 'private'); ?>>خصوصی</option></select> <label><input type="checkbox" name="is_featured" value="1" <?php checked($checked('is_featured')); ?>> پروفایل ویژه</label> <?php if (current_user_can('manage_options')) : ?> <label><input type="checkbox" name="is_verified" value="1" <?php checked($checked('is_verified')); ?>> تأییدشده توسط مدیر</label><?php endif; ?></td></tr>
                    <tr><th><label for="tagline">عنوان کوتاه / معرفی فارسی</label></th><td><input class="large-text" maxlength="500" id="tagline" name="tagline" value="<?php echo esc_attr($v('tagline')); ?>"></td></tr>
                    <tr><th><label for="tagline_en">عنوان کوتاه انگلیسی</label></th><td><input class="large-text" maxlength="500" id="tagline_en" name="tagline_en" dir="ltr" value="<?php echo esc_attr($v('tagline_en')); ?>"></td></tr>
                    <tr><th><label for="seo_title">عنوان سئو (اختیاری)</label></th><td><input class="large-text" maxlength="255" id="seo_title" name="seo_title" value="<?php echo esc_attr($v('seo_title')); ?>"><p class="description">در صورت خالی‌بودن، عنوان از نام و نوع پروفایل ساخته می‌شود. پیشنهاد: عنوان مشخص و طبیعی، حدود ۵۰ تا ۶۵ نویسه.</p></td></tr>
                    <tr><th><label for="seo_description">توضیحات سئو (اختیاری)</label></th><td><textarea class="large-text" rows="3" maxlength="500" id="seo_description" name="seo_description"><?php echo esc_textarea($v('seo_description')); ?></textarea><p class="description">در صورت خالی‌بودن از معرفی کوتاه و زندگی‌نامه استفاده می‌شود؛ توضیح خروجی برای موتورهای جست‌وجو به حدود ۱۶۰ نویسه محدود می‌شود.</p></td></tr>
                    <tr><th><label for="profile_image_url">تصویر پروفایل / پرتره</label></th><td><input class="large-text" type="url" id="profile_image_url" name="profile_image_url" dir="ltr" value="<?php echo esc_attr($v('profile_image_url')); ?>"><input type="hidden" id="profile_image_id" name="profile_image_id" value="<?php echo absint($v('profile_image_id')); ?>"><p><button type="button" class="button wp-coa-profile-media-picker" data-target="profile_image_url" data-preview="profile_image_preview">انتخاب تصویر از کتابخانه رسانه</button> <button type="button" class="button wp-coa-profile-media-clear" data-target="profile_image_url" data-preview="profile_image_preview">حذف تصویر</button></p><img id="profile_image_preview" src="<?php echo esc_url($v('profile_image_url')); ?>" alt="" style="<?php echo $v('profile_image_url') !== '' ? 'display:block;' : 'display:none;'; ?>max-width:160px;max-height:160px;object-fit:cover;border-radius:12px;border:1px solid #ddd;padding:3px"></td></tr>
                    <tr><th><label for="cover_image_url">تصویر کاور / بنر</label></th><td><input class="large-text" type="url" id="cover_image_url" name="cover_image_url" dir="ltr" value="<?php echo esc_attr($v('cover_image_url')); ?>"><input type="hidden" id="cover_image_id" name="cover_image_id" value="<?php echo absint($v('cover_image_id')); ?>"><p><button type="button" class="button wp-coa-profile-media-picker" data-target="cover_image_url" data-preview="cover_image_preview">انتخاب تصویر کاور از کتابخانه</button> <button type="button" class="button wp-coa-profile-media-clear" data-target="cover_image_url" data-preview="cover_image_preview">حذف کاور</button></p><img id="cover_image_preview" src="<?php echo esc_url($v('cover_image_url')); ?>" alt="" style="<?php echo $v('cover_image_url') !== '' ? 'display:block;' : 'display:none;'; ?>max-width:420px;max-height:180px;object-fit:cover;border-radius:12px;border:1px solid #ddd;padding:3px"></td></tr>
                    </tbody></table>
                    <h3>زندگی‌نامه و زمینه فعالیت</h3><table class="form-table"><tbody>
                    <?php foreach (array('biography' => 'زندگی‌نامه / معرفی فارسی', 'biography_en' => 'Biography / English', 'disciplines' => 'رشته‌ها و رسانه‌های هنری فارسی', 'disciplines_en' => 'Disciplines and media / English', 'education' => 'تحصیلات و آموزش فارسی', 'education_en' => 'Education / English', 'awards' => 'جوایز و افتخارات فارسی', 'awards_en' => 'Awards and distinctions / English', 'exhibitions' => 'نمایشگاه‌ها و سوابق حرفه‌ای فارسی', 'exhibitions_en' => 'Exhibitions and career history / English', 'collection_focus' => 'حوزه و رویکرد مجموعه‌داری فارسی', 'collection_focus_en' => 'Collection focus / English') as $key => $label) : ?>
                    <tr><th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><textarea class="large-text" rows="3" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" <?php echo substr($key, -3) === '_en' ? 'dir="ltr"' : ''; ?>><?php echo esc_textarea($v($key)); ?></textarea></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                    <h3>اطلاعات زیستی و جغرافیایی</h3><table class="form-table"><tbody>
                    <?php foreach (array('birth_year' => 'سال تولد', 'death_year' => 'سال درگذشت (در صورت کاربرد)', 'founded_year' => 'سال تأسیس مجموعه', 'nationality' => 'ملیت / فارسی', 'nationality_en' => 'Nationality / English', 'city' => 'شهر / فارسی', 'city_en' => 'City / English', 'country' => 'کشور / فارسی', 'country_en' => 'Country / English') as $key => $label) : ?><tr><th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" maxlength="255" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($v($key)); ?>" <?php echo substr($key, -3) === '_en' ? 'dir="ltr"' : ''; ?>></td></tr><?php endforeach; ?>
                    </tbody></table>
                    <h3>وب‌سایت، پورتفولیو و ارتباطات</h3><table class="form-table"><tbody>
                    <?php foreach (array('website_url' => 'وب‌سایت', 'portfolio_url' => 'پورتفولیو / کاتالوگ آنلاین', 'instagram_url' => 'اینستاگرام', 'linkedin_url' => 'لینکدین', 'facebook_url' => 'فیسبوک', 'other_social_url' => 'شبکه اجتماعی یا لینک دیگر') as $key => $label) : ?><tr><th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input type="url" class="large-text" maxlength="500" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" dir="ltr" value="<?php echo esc_attr($v($key)); ?>"></td></tr><?php endforeach; ?>
                    <tr><th><label for="contact_email">ایمیل تماس (خصوصی تا زمان فعال‌سازی)</label></th><td><input type="email" class="regular-text" maxlength="190" id="contact_email" name="contact_email" dir="ltr" value="<?php echo esc_attr($v('contact_email')); ?>"></td></tr>
                    <tr><th><label for="contact_phone">شماره تماس (خصوصی تا زمان فعال‌سازی)</label></th><td><input class="regular-text" maxlength="80" id="contact_phone" name="contact_phone" dir="ltr" value="<?php echo esc_attr($v('contact_phone')); ?>"></td></tr>
                    <tr><th>حریم خصوصی</th><td><label><input type="checkbox" name="show_location_public" value="1" <?php checked($checked('show_location_public', true)); ?>> نمایش شهر و کشور</label><br><label><input type="checkbox" name="show_email_public" value="1" <?php checked($checked('show_email_public')); ?>> نمایش عمومی ایمیل</label><br><label><input type="checkbox" name="show_phone_public" value="1" <?php checked($checked('show_phone_public')); ?>> نمایش عمومی شماره تماس</label><br><label><input type="checkbox" name="show_works_public" value="1" <?php checked($checked('show_works_public', true)); ?>> نمایش آثار متصل به گواهی‌های فعال</label><p class="description">نمایش ایمیل و تلفن غیرفعال است مگر اینکه صریحاً فعال شود.</p></td></tr>
                    </tbody></table>
                    <?php submit_button($profile ? 'ذخیره تغییرات پروفایل' : 'ایجاد پروفایل'); ?>
                    <?php if ($profile) : ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wp-coa-pro-people')); ?>">ایجاد پروفایل جدید</a><?php endif; ?>
                </form>
            </div>
            <h2>پروفایل‌های ثبت‌شده</h2><table class="widefat striped"><thead><tr><th>نام</th><th>نوع</th><th>نامک</th><th>وضعیت</th><th>تأیید</th><th>گواهی‌های فعال متصل</th><th>عملیات</th></tr></thead><tbody>
            <?php if ($profiles) : foreach ($profiles as $item) : ?><tr><td><strong><?php echo esc_html($item->display_name); ?></strong><?php if ($item->display_name_en !== '') : ?><br><span dir="ltr"><?php echo esc_html($item->display_name_en); ?></span><?php endif; ?></td><td><?php echo esc_html(self::types()[$item->profile_type][0]); ?></td><td><code><?php echo esc_html($item->slug); ?></code></td><td><?php echo esc_html(array('published' => 'منتشرشده', 'draft' => 'پیش‌نویس', 'private' => 'خصوصی')[$item->status] ?? $item->status); ?></td><td><?php echo !empty($item->is_verified) ? '✓' : '—'; ?></td><td><?php echo isset($linked_counts[(int) $item->id]) ? (int) $linked_counts[(int) $item->id] : 0; ?></td><td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page' => 'wp-coa-pro-people', 'edit_profile' => (int) $item->id), admin_url('admin.php'))); ?>">ویرایش</a> <?php if ($item->status === 'published') : ?><a class="button button-small" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url(self::profile_url($item)); ?>">نمایش عمومی</a><?php endif; ?></td></tr><?php endforeach; else : ?><tr><td colspan="7">هنوز پروفایلی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody></table>
            <div class="card aap-s-12e97e"><h3>اتصال به برگه‌ها و Elementor</h3><p>فهرست هنرمندان: <code>[coa_artist_directory]</code> · فهرست کلکتورها: <code>[coa_collector_directory]</code> · فهرست نوع‌پذیر: <code>[coa_profile_directory type="artist" columns="3" language="both"]</code> · نمای پروفایل: <code>[coa_profile_view]</code></p><p>در بخش Elementor، ویجت‌های «COA — Artist & Collector Directory» و «COA — Profile Details» را جست‌وجو کنید. برای لینک‌های مستقل پروفایل، برگه‌های پیشنهادی را از بخش «Pages & Elementor» ایجاد کنید.</p></div>
        </div>
        <?php
    }

    public static function register_assets() {
        // Registration is cheap; actual enqueuing is delegated to page/widget detection.
        if (!wp_style_is('wp-coa-pro-components', 'registered')) {
            if (class_exists('WP_COA_Pro_Page_Integration')) { WP_COA_Pro_Page_Integration::register_assets(); }
        }
    }

    public static function render_artist_directory($attributes = array()) {
        $attributes['type'] = 'artist';
        return self::render_directory($attributes);
    }

    public static function render_collector_directory($attributes = array()) {
        $attributes['type'] = 'collector';
        return self::render_directory($attributes);
    }

    public static function render_directory($attributes = array()) {
        $attributes = shortcode_atts(array(
            'type' => 'artist', 'title' => '', 'language' => 'fa', 'columns' => 3, 'limit' => 48,
            'featured_only' => '0', 'show_search' => '1', 'show_bio' => '1', 'show_location' => '1', 'show_verified' => '1',
        ), $attributes, 'coa_profile_directory');
        $type = isset(self::types()[$attributes['type']]) ? $attributes['type'] : 'artist';

        // One source of truth: all artist directories (shortcode and legacy Elementor widget)
        // now render the canonical WordPress artist CPT archive. Collectors retain COA profiles.
        if ($type === 'artist' && class_exists('Artiey_AET_Directory')) {
            $filters = Artiey_AET_Directory::default_filters('artists');
            if ((string) $attributes['show_search'] !== '1') {
                $filters = array_values(array_diff($filters, array('search')));
            }
            $heading = trim((string) $attributes['title']);
            return Artiey_AET_Directory::shortcode_artist_archive(array(
                'heading' => $heading,
                'show_heading' => $heading !== '' ? 'yes' : 'no',
                'columns' => max(1, min(6, absint($attributes['columns']))),
                'per_page' => max(1, min(60, absint($attributes['limit']))),
                'filters' => implode(',', $filters),
                'ajax' => 'yes',
                'card_style' => 'editorial',
                'show_count' => 'yes',
                'sort' => 'title',
            ));
        }

        if (!class_exists('WP_COA_Pro_Page_Integration')) { return ''; }
        WP_COA_Pro_Page_Integration::enqueue_public_assets();
        $language = self::resolve_language($attributes['language']);
        $columns = max(1, min(4, absint($attributes['columns'])));
        $limit = max(1, min(100, absint($attributes['limit'])));
        $search = isset($_GET['coa_profile_search']) && is_scalar($_GET['coa_profile_search']) ? sanitize_text_field(wp_unslash($_GET['coa_profile_search'])) : '';
        $summary_fields = $attributes['show_bio'] === '1' ? true : 'no_bio';
        $profiles = self::list_profiles($type, 'published', $limit, $search, $attributes['featured_only'] === '1', $summary_fields);
        $labels = self::types()[$type];
        $title = trim((string) $attributes['title']) !== '' ? (string) $attributes['title'] : ($type === 'artist' ? 'هنرمندان / Artists' : 'کلکتورها و مجموعه‌ها / Collectors & Collections');
        $search_id = function_exists('wp_unique_id') ? wp_unique_id('coa-profile-search-') : 'coa-profile-search-' . wp_rand(1000, 999999);
        ob_start();
        ?>
        <section class="wp-coa-person-directory" dir="<?php echo $language === 'en' ? 'ltr' : 'auto'; ?>" style="--wp-coa-person-columns:<?php echo (int) $columns; ?>">
            <?php if ($title !== '') : ?><header class="wp-coa-person-directory-header"><h2><?php echo esc_html($title); ?></h2><p><?php echo esc_html($language === 'en' ? $labels[1] . ' directory' : ($language === 'both' ? $labels[0] . ' / ' . $labels[1] : 'فهرست ' . $labels[0])); ?></p></header><?php endif; ?>
            <?php if ($attributes['show_search'] === '1') : ?><form class="wp-coa-person-search" method="get" action="<?php echo esc_url(remove_query_arg('coa_profile_slug')); ?>"><label class="screen-reader-text" for="<?php echo esc_attr($search_id); ?>">جست‌وجوی پروفایل</label><input id="<?php echo esc_attr($search_id); ?>" type="search" name="coa_profile_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr($language === 'en' ? 'Search profiles…' : 'جست‌وجو در نام و زمینه فعالیت…'); ?>"><button type="submit"><?php echo esc_html($language === 'en' ? 'Search' : 'جست‌وجو'); ?></button><?php if ($search !== '') : ?><a href="<?php echo esc_url(remove_query_arg('coa_profile_search')); ?>"><?php echo esc_html($language === 'en' ? 'Reset' : 'حذف فیلتر'); ?></a><?php endif; ?></form><?php endif; ?>
            <div class="wp-coa-person-grid">
            <?php if ($profiles) : foreach ($profiles as $item) : ?>
                <article class="wp-coa-person-card">
                    <a class="wp-coa-person-card-link" href="<?php echo esc_url(self::profile_url($item)); ?>" aria-label="<?php echo esc_attr('مشاهده پروفایل ' . $item->display_name); ?>">
                        <div class="wp-coa-person-card-image"><?php if ($item->profile_image_url !== '') : ?><?php echo self::profile_image_markup($item->profile_image_url, isset($item->profile_image_id) ? $item->profile_image_id : 0, 'medium', $item->display_name, '', 'lazy', '(max-width: 600px) 100vw, 33vw'); ?><?php else : ?><span class="wp-coa-person-placeholder" aria-hidden="true"><?php echo esc_html((class_exists('\Artiey_AET_Text') ? \Artiey_AET_Text::substr($item->display_name, 0, 1) : '✦')); ?></span><?php endif; ?></div>
                        <div class="wp-coa-person-card-content">
                            <div class="wp-coa-person-badges"><?php if ($attributes['show_verified'] === '1' && !empty($item->is_verified)) : ?><span class="wp-coa-person-verified">تأییدشده</span><?php endif; ?><?php if (!empty($item->is_featured)) : ?><span class="wp-coa-person-featured">ویژه</span><?php endif; ?></div>
                            <h3><?php echo esc_html(self::localized($item->display_name, $item->display_name_en, $language)); ?></h3>
                            <?php if ($item->tagline !== '' || $item->tagline_en !== '') : ?><p class="wp-coa-person-tagline"><?php echo esc_html(self::localized($item->tagline, $item->tagline_en, $language)); ?></p><?php endif; ?>
                            <?php if ($attributes['show_location'] === '1' && !empty($item->show_location_public) && ($item->city !== '' || $item->country !== '')) : ?><p class="wp-coa-person-location"><?php echo esc_html(self::localized(trim($item->city . ($item->country ? '، ' . $item->country : '')), trim($item->city_en . ($item->country_en ? ', ' . $item->country_en : '')), $language)); ?></p><?php endif; ?>
                            <?php if ($attributes['show_bio'] === '1' && ($item->biography !== '' || $item->biography_en !== '')) : ?><p class="wp-coa-person-excerpt"><?php echo esc_html(wp_trim_words(wp_strip_all_tags(self::localized($item->biography, $item->biography_en, $language)), 24)); ?></p><?php endif; ?>
                            <span class="wp-coa-person-read-more"><?php echo esc_html($language === 'en' ? 'View profile →' : 'مشاهده شناسنامه ←'); ?></span>
                        </div>
                    </a>
                </article>
            <?php endforeach; else : ?><div class="wp-coa-person-empty"><?php echo esc_html($language === 'en' ? 'No public profiles are available yet.' : 'در حال حاضر پروفایل عمومی منتشرشده‌ای برای این بخش وجود ندارد.'); ?></div><?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function render_profile_view($attributes = array()) {
        if (class_exists('WP_COA_Pro_Page_Integration')) { WP_COA_Pro_Page_Integration::enqueue_public_assets(); }
        $attributes = shortcode_atts(array('type' => '', 'slug' => '', 'id' => 0, 'language' => 'fa', 'show_works' => '1', 'show_contact' => '1', 'show_social' => '1'), $attributes, 'coa_profile_view');
        $type = isset(self::types()[$attributes['type']]) ? $attributes['type'] : '';
        $profile = null;
        if (!empty($attributes['id'])) { $profile = self::get_profile(absint($attributes['id']), true); }
        elseif (!empty($attributes['slug'])) { $profile = self::get_by_slug($attributes['slug'], $type); }
        else {
            $route_type = function_exists('get_query_var') ? get_query_var('coa_profile_type') : '';
            $route_slug = function_exists('get_query_var') ? get_query_var('coa_profile_slug') : '';
            $query_type = isset($_GET['coa_profile_type']) && is_scalar($_GET['coa_profile_type']) ? sanitize_key(wp_unslash($_GET['coa_profile_type'])) : (is_scalar($route_type) && $route_type !== '' ? sanitize_key((string) $route_type) : $type);
            $query_slug = isset($_GET['coa_profile_slug']) && is_scalar($_GET['coa_profile_slug']) ? sanitize_title(wp_unslash($_GET['coa_profile_slug'])) : (is_scalar($route_slug) ? sanitize_title((string) $route_slug) : '');
            if ($query_slug !== '') { $profile = self::get_by_slug($query_slug, isset(self::types()[$query_type]) ? $query_type : ''); }
        }
        if (!$profile || $profile->status !== 'published') {
            return '<div class="wp-coa-person-not-found">' . esc_html('پروفایل عمومی یافت نشد یا هنوز منتشر نشده است.') . '</div>';
        }
        $language = self::resolve_language($attributes['language']);
        $is_artist = $profile->profile_type === 'artist';
        $works = ($attributes['show_works'] === '1' && !empty($profile->show_works_public)) ? self::linked_works($profile) : array();
        $place = trim($profile->city . ($profile->country !== '' ? '، ' . $profile->country : ''));
        $place_en = trim($profile->city_en . ($profile->country_en !== '' ? ', ' . $profile->country_en : ''));
        ob_start();
        ?>
        <article class="wp-coa-person-profile" dir="<?php echo $language === 'en' ? 'ltr' : 'auto'; ?>">
            <?php if ($profile->cover_image_url !== '') : ?><div class="wp-coa-person-cover"><?php echo self::profile_image_markup($profile->cover_image_url, isset($profile->cover_image_id) ? $profile->cover_image_id : 0, 'large', '', 'wp-coa-person-cover-image', 'eager', '(max-width: 900px) 100vw, 1200px', 'high'); ?></div><?php endif; ?>
            <header class="wp-coa-person-profile-header">
                <?php if ($profile->profile_image_url !== '') : ?><?php echo self::profile_image_markup($profile->profile_image_url, isset($profile->profile_image_id) ? $profile->profile_image_id : 0, 'medium', $profile->display_name, 'wp-coa-person-avatar', 'eager', '160px', $profile->cover_image_url === '' ? 'high' : ''); ?><?php endif; ?>
                <div class="wp-coa-person-profile-heading"><p class="wp-coa-person-kicker"><?php echo esc_html($is_artist ? ($language === 'en' ? 'ARTIST PROFILE' : 'شناسنامه هنرمند') : ($language === 'en' ? 'COLLECTOR / COLLECTION PROFILE' : 'شناسنامه کلکتور / مجموعه')); ?></p>
                    <h1><?php echo esc_html(self::localized($profile->display_name, $profile->display_name_en, $language)); ?> <?php if (!empty($profile->is_verified)) : ?><span class="wp-coa-person-verified" title="تأییدشده توسط مدیر">✓ تأییدشده</span><?php endif; ?></h1>
                    <?php if ($profile->tagline !== '' || $profile->tagline_en !== '') : ?><p class="wp-coa-person-profile-tagline"><?php echo esc_html(self::localized($profile->tagline, $profile->tagline_en, $language)); ?></p><?php endif; ?>
                    <?php if (!empty($profile->show_location_public) && ($place !== '' || $place_en !== '')) : ?><p class="wp-coa-person-location"><?php echo esc_html(self::localized($place, $place_en, $language)); ?></p><?php endif; ?>
                </div>
            </header>
            <div class="wp-coa-person-profile-body">
                <?php $bio = self::localized($profile->biography, $profile->biography_en, $language); if ($bio !== '') : ?><section><h2><?php echo esc_html($is_artist ? ($language === 'en' ? 'Biography' : 'زندگی‌نامه') : ($language === 'en' ? 'About the collection' : 'درباره کلکسیون / مجموعه')); ?></h2><div class="wp-coa-person-prose"><?php echo nl2br(esc_html($bio)); ?></div></section><?php endif; ?>
                <?php $discipline = self::localized($profile->disciplines, $profile->disciplines_en, $language); if ($discipline !== '') : ?><section><h2><?php echo esc_html($language === 'en' ? 'Artistic practice and media' : 'زمینه فعالیت و رسانه‌های هنری'); ?></h2><div class="wp-coa-person-prose"><?php echo nl2br(esc_html($discipline)); ?></div></section><?php endif; ?>
                <?php $focus = self::localized($profile->collection_focus, $profile->collection_focus_en, $language); if (!$is_artist && $focus !== '') : ?><section><h2><?php echo esc_html($language === 'en' ? 'Collection focus' : 'رویکرد و تمرکز مجموعه'); ?></h2><div class="wp-coa-person-prose"><?php echo nl2br(esc_html($focus)); ?></div></section><?php endif; ?>
                <?php $education = self::localized($profile->education, $profile->education_en, $language); if ($education !== '') : ?><section><h2><?php echo esc_html($language === 'en' ? 'Education' : 'تحصیلات و آموزش'); ?></h2><div class="wp-coa-person-prose"><?php echo nl2br(esc_html($education)); ?></div></section><?php endif; ?>
                <?php $awards = self::localized($profile->awards, $profile->awards_en, $language); if ($awards !== '') : ?><section><h2><?php echo esc_html($language === 'en' ? 'Awards and distinctions' : 'جوایز و افتخارات'); ?></h2><div class="wp-coa-person-prose"><?php echo nl2br(esc_html($awards)); ?></div></section><?php endif; ?>
                <?php $exhibitions = self::localized($profile->exhibitions, $profile->exhibitions_en, $language); if ($exhibitions !== '') : ?><section><h2><?php echo esc_html($language === 'en' ? 'Exhibitions and professional history' : 'نمایشگاه‌ها و سوابق حرفه‌ای'); ?></h2><div class="wp-coa-person-prose"><?php echo nl2br(esc_html($exhibitions)); ?></div></section><?php endif; ?>
                <section class="wp-coa-person-facts"><h2><?php echo esc_html($language === 'en' ? 'Profile facts' : 'اطلاعات تکمیلی'); ?></h2><dl>
                    <?php if (!$is_artist && isset(self::entity_kinds()[$profile->entity_kind])) : ?><div><dt><?php echo esc_html($language === 'en' ? 'Entity type' : 'نوع مجموعه'); ?></dt><dd><?php echo esc_html(self::entity_kinds()[$profile->entity_kind][$language === 'en' ? 1 : 0]); ?></dd></div><?php endif; ?>
                    <?php foreach (array('birth_year' => array('سال تولد', 'Born'), 'death_year' => array('سال درگذشت', 'Died'), 'founded_year' => array('سال تأسیس', 'Founded'), 'nationality' => array('ملیت', 'Nationality')) as $key => $labels) : $val = self::localized($profile->{$key}, isset($profile->{$key . '_en'}) ? $profile->{$key . '_en'} : '', $language); if ($val !== '') : ?><div><dt><?php echo esc_html($language === 'en' ? $labels[1] : $labels[0]); ?></dt><dd><?php echo esc_html($val); ?></dd></div><?php endif; endforeach; ?>
                </dl></section>
                <?php if ($attributes['show_contact'] === '1' && (!empty($profile->website_url) || (!empty($profile->show_email_public) && !empty($profile->contact_email)) || (!empty($profile->show_phone_public) && !empty($profile->contact_phone)) || !empty($profile->portfolio_url))) : ?><section><h2><?php echo esc_html($language === 'en' ? 'Contact and resources' : 'راه‌های ارتباطی و منابع'); ?></h2><ul class="wp-coa-person-links">
                    <?php if ($profile->website_url !== '') : ?><li><a href="<?php echo esc_url($profile->website_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($language === 'en' ? 'Official website' : 'وب‌سایت رسمی'); ?></a></li><?php endif; ?>
                    <?php if ($profile->portfolio_url !== '') : ?><li><a href="<?php echo esc_url($profile->portfolio_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($language === 'en' ? 'Portfolio / catalogue' : 'پورتفولیو / کاتالوگ'); ?></a></li><?php endif; ?>
                    <?php if (!empty($profile->show_email_public) && is_email($profile->contact_email)) : ?><li><a href="mailto:<?php echo esc_attr(antispambot($profile->contact_email)); ?>"><?php echo esc_html(antispambot($profile->contact_email)); ?></a></li><?php endif; ?>
                    <?php if (!empty($profile->show_phone_public) && $profile->contact_phone !== '') : ?><li><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $profile->contact_phone)); ?>"><?php echo esc_html($profile->contact_phone); ?></a></li><?php endif; ?>
                </ul></section><?php endif; ?>
                <?php if ($attributes['show_social'] === '1' && ($profile->instagram_url || $profile->linkedin_url || $profile->facebook_url || $profile->other_social_url)) : ?><section><h2><?php echo esc_html($language === 'en' ? 'Social profiles' : 'شبکه‌های اجتماعی'); ?></h2><ul class="wp-coa-person-links">
                    <?php foreach (array('instagram_url' => 'Instagram', 'linkedin_url' => 'LinkedIn', 'facebook_url' => 'Facebook', 'other_social_url' => ($language === 'en' ? 'Other link' : 'پیوند دیگر')) as $key => $label) : if (!empty($profile->{$key})) : ?><li><a href="<?php echo esc_url($profile->{$key}); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?></a></li><?php endif; endforeach; ?>
                </ul></section><?php endif; ?>
                <?php if ($works) : ?><section class="wp-coa-person-works"><h2><?php echo esc_html($language === 'en' ? 'Registered works' : 'آثار ثبت‌شده در سامانه'); ?></h2><div class="wp-coa-person-work-grid"><?php foreach ($works as $work) : ?><article><h3><?php echo esc_html(self::localized($work->artwork_title, $work->artwork_title_en, $language)); ?></h3><?php if ($work->medium || $work->medium_en) : ?><p><?php echo esc_html(self::localized($work->medium, $work->medium_en, $language)); ?></p><?php endif; ?><?php if ($work->year_created !== '') : ?><span><?php echo esc_html($work->year_created); ?></span><?php endif; ?></article><?php endforeach; ?></div></section><?php endif; ?>
                <p class="wp-coa-person-disclaimer"><?php echo esc_html($language === 'en' ? 'This profile contains information supplied to the registry. A profile or a linked certificate is not, by itself, a legal opinion or a substitute for physical examination of an artwork.' : 'اطلاعات این شناسنامه بر اساس داده‌های ثبت‌شده در سامانه نمایش داده می‌شود. وجود پروفایل یا گواهی متصل، به‌تنهایی نظر حقوقی یا جایگزین بررسی فیزیکی اثر نیست.'); ?></p>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    public static function profile_url($profile) {
        if (!$profile || empty($profile->slug)) { return ''; }
        if (class_exists('WP_COA_Pro_SEO')) {
            return WP_COA_Pro_SEO::profile_url($profile);
        }
        $base = class_exists('WP_COA_Pro_Page_Integration') ? WP_COA_Pro_Page_Integration::get_managed_page_url('profile_view') : '';
        if (!$base) { $base = home_url('/'); }
        return add_query_arg(array('coa_profile_type' => $profile->profile_type, 'coa_profile_slug' => $profile->slug), $base);
    }

    private static function resolve_language($language) {
        $language = sanitize_key((string) $language);
        if ($language === 'site') { $locale = function_exists('determine_locale') ? determine_locale() : get_locale(); return strpos(strtolower($locale), 'fa') === 0 ? 'fa' : 'en'; }
        return in_array($language, array('fa', 'en', 'both'), true) ? $language : 'fa';
    }

    private static function localized($primary, $english, $language) {
        $primary = is_scalar($primary) ? (string) $primary : '';
        $english = is_scalar($english) ? (string) $english : '';
        if ($language === 'en') { return $english !== '' ? $english : $primary; }
        if ($language === 'both' && $primary !== '' && $english !== '') { return $primary . ' / ' . $english; }
        return $primary !== '' ? $primary : $english;
    }

    private static function post_scalar($key) {
        return isset($_POST[$key]) && is_scalar($_POST[$key]) ? trim((string) wp_unslash($_POST[$key])) : '';
    }
    private static function post_text($key, $max) {
        return self::unicode_limit(sanitize_text_field(self::post_scalar($key)), $max);
    }
    private static function post_textarea($key, $max) {
        return self::unicode_limit(sanitize_textarea_field(self::post_scalar($key)), $max);
    }
    private static function unicode_limit($value, $max) {
        $value = (string) $value;
        $max = max(0, absint($max));
        if (function_exists('mb_substr')) { return mb_substr($value, 0, $max, 'UTF-8'); }
        if (function_exists('iconv_substr')) {
            $limited = iconv_substr($value, 0, $max, 'UTF-8');
            if ($limited !== false) { return $limited; }
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($characters) ? implode('', array_slice($characters, 0, $max)) : substr($value, 0, $max);
    }
    private static function post_checkbox($key) {
        return self::post_scalar($key) === '1' || self::post_scalar($key) === 'yes' || self::post_scalar($key) === 'on';
    }
    private static function post_url($key) {
        $url = esc_url_raw(self::post_scalar($key), array('http', 'https'));
        return is_string($url) ? $url : '';
    }
    private static function redirect_admin($args = array()) {
        $url = add_query_arg(array_merge(array('page' => 'wp-coa-pro-people'), $args), admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
