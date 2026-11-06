<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WP_COA_Pro {
    private static $instance = null;
    private $signature;
    private $ledger;
    private $repository;

    public static function init() {
        if (self::$instance === null) {
            WP_COA_Pro_Installer::maybe_upgrade();
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->signature = new WP_COA_Pro_Signature();
        $this->ledger = new WP_COA_Pro_Ledger();
        $this->repository = new WP_COA_Pro_Repository($this->signature, $this->ledger);

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_certificate_admin_assets'));
        add_action('template_redirect', array($this, 'handle_public_verification_route'), 1);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_qr_script'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'register_qr_script'));
        add_action('admin_post_wp_coa_create_certificate', array($this, 'handle_create_certificate'));
        add_action('admin_post_wp_coa_revoke_certificate', array($this, 'handle_revoke_certificate'));
        add_action('admin_post_wp_coa_transfer_owner', array($this, 'handle_transfer_owner'));
        add_action('admin_post_wp_coa_print_certificate', array($this, 'handle_print_certificate'));
        add_action('admin_post_wp_coa_export_csv', array($this, 'handle_export_csv'));
        add_shortcode('coa_verify', array($this, 'verification_shortcode'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        if (!function_exists('artiey_ap_is_elementor_request') || !artiey_ap_is_elementor_request()) {
            WP_COA_Pro_Admin::init();
        }
        WP_COA_Pro_Galleries::init();
        WP_COA_Pro_Person_Profiles::init();
        WP_COA_Pro_Elementor_Widget::init();
        WP_COA_Pro_Page_Integration::init();
    }

    /** Register the QR library only during WordPress asset enqueue phases. */
    public static function register_qr_script() {
        if (!wp_script_is('wp-coa-pro-qr', 'registered')) {
            wp_register_script('wp-coa-pro-qr', WP_COA_PRO_URL . 'assets/js/wp-coa-pro-qr.js', array(), WP_COA_PRO_VERSION, true);
        }
    }

    /** Public bridge used by the optional Elementor widget. */
    public static function render_verification($attributes = array()) {
        if (self::$instance === null) {
            return '';
        }
        return self::$instance->verification_shortcode($attributes);
    }

    public function enqueue_certificate_admin_assets($hook_suffix) {
        if (!is_admin() || !current_user_can('manage_coa_certificates')) {
            return;
        }
        $page = isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if ($page !== 'wp-coa-pro' && $hook_suffix !== 'toplevel_page_wp-coa-pro') {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('wp-coa-pro-issuance', WP_COA_PRO_URL . 'assets/js/wp-coa-pro-issuance.js', array('jquery', 'media-editor'), defined('AET_VERSION') ? AET_VERSION : WP_COA_PRO_VERSION, true);
    }

    /**
     * Normalise a certificate code typed or pasted by a visitor: Persian/Arabic digits become ASCII,
     * typographic dashes become "-", whitespace and invisible direction marks are removed, case is folded.
     */
    public static function normalize_code($raw) {
        if (!is_scalar($raw)) { return ''; }
        $code = trim((string) $raw);
        if ($code === '') { return ''; }
        $code = strtr($code, array(
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '‐' => '-', '‑' => '-', '‒' => '-', '–' => '-', '—' => '-', '―' => '-', '−' => '-', '﹣' => '-', '－' => '-', 'ـ' => '-',
        ));
        $code = preg_replace('/[\s\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u', '', $code);
        $code = is_string($code) ? $code : '';
        return strtoupper(sanitize_text_field($code));
    }

    /** Create a QR target that works even if no managed verification page has been created. */
    public static function get_verification_url($code) {
        $code = strtoupper(sanitize_text_field((string) $code));
        if (!preg_match('/^COA-[A-Z0-9]{4,12}-[A-Z0-9]{4,16}$/', $code)) {
            return '';
        }
        if (get_option('permalink_structure')) {
            return home_url('/coa-verify/' . rawurlencode($code) . '/');
        }
        return add_query_arg('wp_coa_verify_code', $code, home_url('/'));
    }

    /** Serve a lightweight themed verification page for QR requests. */
    public function handle_public_verification_route() {
        $code = get_query_var('wp_coa_verify_code');
        if (!is_string($code) || $code === '') {
            return;
        }
        $code = strtoupper(sanitize_text_field($code));
        if (!preg_match('/^COA-[A-Z0-9]{4,12}-[A-Z0-9]{4,16}$/', $code)) {
            status_header(404);
            nocache_headers();
            wp_die(esc_html__('The certificate code in this URL is invalid.', 'wp-coa-pro'), esc_html__('Certificate not found', 'wp-coa-pro'), array('response' => 404));
        }
        $_GET['coa_code'] = $code;
        // A well-formed but unknown code should not be indexed as a successful page.
        $route_record = $this->repository->get_by_code($code);
        status_header($route_record ? 200 : 404);
        nocache_headers();
        global $wp_query;
        if (is_object($wp_query)) {
            $wp_query->is_404 = false;
        }
        get_header();
        echo '<main id="primary" class="site-main wp-coa-pro-qr-verification aap-s-eb4b09">';
        echo $this->verification_shortcode(array('language' => 'both'));
        echo '</main>';
        get_footer();
        exit;
    }

    public function admin_menu() {
        // Keep the familiar page slugs for compatibility, but register every page
        // beneath the single Artiey Platform top-level menu.
        add_submenu_page(
            'artiey-platform',
            __('COA Certificates', 'wp-coa-pro'),
            __('مدیریت گواهی‌ها', 'wp-coa-pro'),
            'manage_coa_certificates',
            'wp-coa-pro',
            array($this, 'admin_page')
        );
        add_submenu_page(
            'artiey-platform',
            __('COA Event History', 'wp-coa-pro'),
            __('تاریخچه گواهی‌ها', 'wp-coa-pro'),
            'manage_coa_certificates',
            'wp-coa-pro-events',
            array($this, 'events_page')
        );
    }

    public function admin_page() {
        $this->require_admin_permission();
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        if (!in_array($status_filter, array('', 'active', 'revoked'), true)) {
            $status_filter = '';
        }
        $gallery_filter = isset($_GET['gallery_id']) ? absint($_GET['gallery_id']) : 0;
        $activity_filter = isset($_GET['activity_type']) ? sanitize_key(wp_unslash($_GET['activity_type'])) : '';
        if ($activity_filter !== '' && !isset(WP_COA_Pro_Galleries::activity_types()[$activity_filter])) { $activity_filter = ''; }
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;
        $query_args = array('search' => $search, 'status' => $status_filter, 'gallery_id' => $gallery_filter, 'activity_type' => $activity_filter);
        $rows = $this->repository->list_records(array_merge($query_args, array('page' => $page, 'per_page' => $per_page)));
        $total = $this->repository->count_records($query_args);
        $total_pages = max(1, (int) ceil($total / $per_page));
        $gallery_profiles = WP_COA_Pro_Galleries::list_profiles(false);
        $active_gallery_profiles = WP_COA_Pro_Galleries::list_profiles(true);
        $gallery_by_id = array();
        foreach ($gallery_profiles as $gallery_profile_row) { $gallery_by_id[(int) $gallery_profile_row->id] = $gallery_profile_row; }
        $default_gallery = WP_COA_Pro_Galleries::get_default_profile();
        ?>
        <div class="wrap wp-coa-admin">
            <h1><?php echo esc_html__('WP COA Pro — Certificate Management', 'wp-coa-pro'); ?></h1>
            <p><?php echo esc_html__('Issue identifiable artwork certificates, check record integrity, revoke invalidated certificates, and keep an administrative event history.', 'wp-coa-pro'); ?></p>
            <?php $this->render_admin_notice(); ?>
            <div class="notice notice-info inline"><p>
                <strong><?php echo esc_html__('Public lookup shortcode:', 'wp-coa-pro'); ?></strong> <code>[coa_verify]</code>
                &nbsp; <strong><?php echo esc_html__('Read-only REST endpoint:', 'wp-coa-pro'); ?></strong> <code><?php echo esc_html(rest_url('wp-coa-pro/v1/certificates/{code}')); ?></code>
            </p></div>

            <h2><?php echo esc_html__('Issue a certificate', 'wp-coa-pro'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wp_coa_create_certificate">
                <?php wp_nonce_field('wp_coa_create_certificate'); ?>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th scope="row"><label for="gallery_id">گالری / مؤسسه صادرکننده</label></th><td><select class="regular-text" id="gallery_id" name="gallery_id" required><?php foreach ($active_gallery_profiles as $gallery_option) : ?><option value="<?php echo (int) $gallery_option->id; ?>" <?php selected((int) $gallery_option->id, $default_gallery ? (int) $default_gallery->id : 0); ?>><?php echo esc_html($gallery_option->name . ($gallery_option->name_en !== '' ? ' / ' . $gallery_option->name_en : '') . ' — ' . WP_COA_Pro_Galleries::activity_label($gallery_option->activity_type)); ?></option><?php endforeach; ?></select> <a href="<?php echo esc_url(admin_url('admin.php?page=wp-coa-pro-galleries')); ?>" target="_blank" rel="noopener noreferrer">مدیریت پروفایل‌ها</a><p class="description">لوگو، رنگ، قالب چاپ و فیلدهای عمومی گواهی براساس این پروفایل تعیین می‌شود و تنظیمات آن در گواهی امضا می‌شود.</p></td></tr>
                    <tr><th scope="row"><label for="artwork_title"><?php esc_html_e('Artwork title (Persian / primary)', 'wp-coa-pro'); ?> *</label></th><td><input required class="regular-text" type="text" id="artwork_title" name="artwork_title" maxlength="255"></td></tr>
                    <tr><th scope="row"><label for="artist_name"><?php esc_html_e('Artist name (Persian / primary)', 'wp-coa-pro'); ?> *</label></th><td><input required class="regular-text" type="text" id="artist_name" name="artist_name" maxlength="255"></td></tr>
                    <tr><th scope="row"><label for="artwork_title_en"><?php esc_html_e('Artwork title (English)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="artwork_title_en" name="artwork_title_en" maxlength="255"></td></tr>
                    <tr><th scope="row"><label for="artist_name_en"><?php esc_html_e('Artist name (English)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="artist_name_en" name="artist_name_en" maxlength="255"></td></tr>
                    <tr><th scope="row"><label for="artist_profile_id">پروفایل رسمی هنرمند</label></th><td><select class="regular-text" id="artist_profile_id" name="artist_profile_id"><option value="0">بدون اتصال به پروفایل</option><?php foreach ((array) WP_COA_Pro_Person_Profiles::list_profiles('artist', 'published', 300) as $artist_profile) : ?><option value="<?php echo (int) $artist_profile->id; ?>" data-name="<?php echo esc_attr($artist_profile->display_name); ?>" data-name-en="<?php echo esc_attr($artist_profile->display_name_en); ?>"><?php echo esc_html($artist_profile->display_name . ($artist_profile->display_name_en !== '' ? ' / ' . $artist_profile->display_name_en : '')); ?></option><?php endforeach; ?></select> <a href="<?php echo esc_url(admin_url('admin.php?page=wp-coa-pro-people')); ?>" target="_blank" rel="noopener noreferrer">مدیریت پروفایل‌ها</a><p class="description">با انتخاب پروفایل منتشرشده، شناسه آن در امضای دیجیتال گواهی ثبت می‌شود و گواهی به شناسنامه هنرمند متصل خواهد شد.</p></td></tr>
                    <tr><th scope="row"><label for="artwork_image_id">تصویر اصل اثر هنری</label></th><td><input type="hidden" id="artwork_image_id" name="artwork_image_id" value="0"><button type="button" class="button" id="wp-coa-select-artwork-image">انتخاب یا بارگذاری تصویر</button> <button type="button" class="button" id="wp-coa-remove-artwork-image" hidden>حذف تصویر</button><div id="wp-coa-artwork-image-preview" class="aap-s-94cfd7"></div><p class="description">تصویر را از کتابخانه رسانه وردپرس انتخاب یا بارگذاری کن. فرمت‌های JPEG، PNG، WebP، GIF و در نسخه‌های سازگار WordPress، AVIF تا سقف ۲۵ مگابایت پذیرفته می‌شوند؛ هش SHA-256 فایل هنگام صدور محاسبه و همراه گواهی امضا می‌شود. این فیلد اختیاری است.</p></td></tr>
                    <tr><th scope="row"><label for="medium"><?php esc_html_e('Medium / materials (Persian / primary)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="medium" name="medium" maxlength="255"></td></tr>
                    <tr><th scope="row"><label for="medium_en"><?php esc_html_e('Medium / materials (English)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="medium_en" name="medium_en" maxlength="255" placeholder="e.g. Oil and pigment on linen"></td></tr>
                    <tr><th scope="row"><label for="year_created"><?php esc_html_e('Year created', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="year_created" name="year_created" maxlength="20" placeholder="e.g. 2024, circa 1920, or undated"></td></tr>
                    <tr><th scope="row"><label for="dimensions"><?php esc_html_e('Dimensions', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="dimensions" name="dimensions" maxlength="255" placeholder="e.g. 100 × 70 cm"></td></tr>
                    <tr><th scope="row"><label for="accession_number"><?php esc_html_e('Inventory / accession number', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="accession_number" name="accession_number" maxlength="100"></td></tr>
                    <tr><th scope="row"><label for="issuer_name"><?php esc_html_e('Issuing gallery / institution (Persian / primary)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="issuer_name" name="issuer_name" maxlength="255" placeholder="خالی بگذارید تا نام پروفایل انتخاب‌شده استفاده شود"></td></tr>
                    <tr><th scope="row"><label for="issuer_name_en"><?php esc_html_e('Issuing gallery / institution (English)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="issuer_name_en" name="issuer_name_en" maxlength="255" placeholder="Leave blank to use the selected profile"></td></tr>
                    <tr><th scope="row"><label for="artwork_description"><?php esc_html_e('Public artwork description (Persian / primary)', 'wp-coa-pro'); ?></label></th><td><textarea class="large-text" rows="3" id="artwork_description" name="artwork_description" maxlength="5000"></textarea><p class="description"><?php esc_html_e('This description is included in the certificate integrity signature. Do not enter private information.', 'wp-coa-pro'); ?></p></td></tr>
                    <tr><th scope="row"><label for="artwork_description_en"><?php esc_html_e('Public artwork description (English)', 'wp-coa-pro'); ?></label></th><td><textarea class="large-text" rows="3" id="artwork_description_en" name="artwork_description_en" maxlength="5000"></textarea></td></tr>
                    <tr><th scope="row"><label for="collector_name"><?php esc_html_e('Collector / collection (Persian / primary)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="collector_name" name="collector_name" maxlength="255"><p class="description"><?php esc_html_e('Collector identity is private on public pages by default. You can enable visibility in Settings.', 'wp-coa-pro'); ?></p></td></tr>
                    <tr><th scope="row"><label for="collector_name_en"><?php esc_html_e('Collector / collection (English)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="collector_name_en" name="collector_name_en" maxlength="255"></td></tr>
                    <tr><th scope="row"><label for="collector_profile_id">پروفایل کلکتور / مجموعه</label></th><td><select class="regular-text" id="collector_profile_id" name="collector_profile_id"><option value="0">بدون اتصال به پروفایل</option><?php foreach ((array) WP_COA_Pro_Person_Profiles::list_profiles('collector', 'published', 300) as $collector_profile) : ?><option value="<?php echo (int) $collector_profile->id; ?>" data-name="<?php echo esc_attr($collector_profile->display_name); ?>" data-name-en="<?php echo esc_attr($collector_profile->display_name_en); ?>"><?php echo esc_html($collector_profile->display_name . ($collector_profile->display_name_en !== '' ? ' / ' . $collector_profile->display_name_en : '')); ?></option><?php endforeach; ?></select> <p class="description">اتصال اختیاری به شناسنامه کلکتور یا مجموعه هنری؛ هویت کلکتور در صفحه عمومی به‌طور پیش‌فرض پنهان می‌ماند.</p></td></tr>
                    <tr><th scope="row"><label for="current_owner_name"><?php esc_html_e('Current owner (Persian / primary)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="current_owner_name" name="current_owner_name" maxlength="255"></td></tr>
                    <tr><th scope="row"><label for="current_owner_name_en"><?php esc_html_e('Current owner (English)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="current_owner_name_en" name="current_owner_name_en" maxlength="255"></td></tr>
                    <tr><th scope="row"><label for="previous_owner_name"><?php esc_html_e('Previous owner (Persian / primary)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="previous_owner_name" name="previous_owner_name" maxlength="255"></td></tr>
                    <tr><th scope="row"><label for="previous_owner_name_en"><?php esc_html_e('Previous owner (English)', 'wp-coa-pro'); ?></label></th><td><input class="regular-text" type="text" id="previous_owner_name_en" name="previous_owner_name_en" maxlength="255"></td></tr>
                    <tr><th scope="row"><label for="notes"><?php esc_html_e('Internal notes', 'wp-coa-pro'); ?></label></th><td><textarea class="large-text" rows="3" id="notes" name="notes" maxlength="5000"></textarea><p class="description"><?php esc_html_e('Internal notes are not exposed on the public verification page or CSV export.', 'wp-coa-pro'); ?></p></td></tr>
                </tbody></table>
                <script>(function(){function bind(selectId,nameId,enId){var sel=document.getElementById(selectId);if(!sel){return;}sel.addEventListener('change',function(){var opt=sel.options[sel.selectedIndex];if(!opt||!opt.value){return;}var name=document.getElementById(nameId),en=document.getElementById(enId);if(name){name.value=opt.getAttribute('data-name')||name.value;}if(en){en.value=opt.getAttribute('data-name-en')||en.value;}});}bind('artist_profile_id','artist_name','artist_name_en');bind('collector_profile_id','collector_name','collector_name_en');})();</script>
                <?php submit_button(__('Issue certificate', 'wp-coa-pro')); ?>
            </form>

            <hr>
            <div class="wp-coa-list-header aap-s-03115d">
                <h2><?php esc_html_e('Certificates', 'wp-coa-pro'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wp_coa_export_csv">
                    <?php wp_nonce_field('wp_coa_export_csv'); ?>
                    <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                    <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
                    <input type="hidden" name="gallery_id" value="<?php echo (int) $gallery_filter; ?>">
                    <input type="hidden" name="activity_type" value="<?php echo esc_attr($activity_filter); ?>">
                    <?php submit_button(__('Export filtered public metadata (CSV)', 'wp-coa-pro'), 'secondary', 'submit', false); ?>
                </form>
            </div>
            <form method="get" class="aap-s-3752cd">
                <input type="hidden" name="page" value="wp-coa-pro">
                <label class="screen-reader-text" for="wp-coa-search"><?php esc_html_e('Search certificates', 'wp-coa-pro'); ?></label>
                <input id="wp-coa-search" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Code, artwork, artist, inventory no.', 'wp-coa-pro'); ?>">
                <label for="wp-coa-status-filter"><?php esc_html_e('Status', 'wp-coa-pro'); ?></label>
                <select id="wp-coa-status-filter" name="status">
                    <option value="" <?php selected($status_filter, ''); ?>><?php esc_html_e('All', 'wp-coa-pro'); ?></option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active', 'wp-coa-pro'); ?></option>
                    <option value="revoked" <?php selected($status_filter, 'revoked'); ?>><?php esc_html_e('Revoked', 'wp-coa-pro'); ?></option>
                </select>
                <label for="wp-coa-gallery-filter">گالری</label>
                <select id="wp-coa-gallery-filter" name="gallery_id"><option value="0">همه گالری‌ها</option><?php foreach ($gallery_profiles as $gallery_option) : ?><option value="<?php echo (int) $gallery_option->id; ?>" <?php selected($gallery_filter, (int) $gallery_option->id); ?>><?php echo esc_html($gallery_option->name); ?></option><?php endforeach; ?></select>
                <label for="wp-coa-activity-filter">نوع فعالیت</label>
                <select id="wp-coa-activity-filter" name="activity_type"><option value="">همه فعالیت‌ها</option><?php foreach (WP_COA_Pro_Galleries::activity_types() as $activity_key => $activity_label) : ?><option value="<?php echo esc_attr($activity_key); ?>" <?php selected($activity_filter, $activity_key); ?>><?php echo esc_html($activity_label[0] . ' / ' . $activity_label[1]); ?></option><?php endforeach; ?></select>
                <?php submit_button(__('Filter', 'wp-coa-pro'), 'secondary', '', false); ?>
            </form>

            <table class="widefat striped"><thead><tr>
                <th><?php esc_html_e('Certificate code', 'wp-coa-pro'); ?></th>
                <th><?php esc_html_e('Artwork', 'wp-coa-pro'); ?></th>
                <th><?php esc_html_e('Artist', 'wp-coa-pro'); ?></th>
                <th>گالری / مؤسسه</th>
                <th>نوع فعالیت</th>
                <th><?php esc_html_e('Collector / collection', 'wp-coa-pro'); ?></th>
                <th><?php esc_html_e('Current owner', 'wp-coa-pro'); ?></th>
                <th><?php esc_html_e('Issued (UTC)', 'wp-coa-pro'); ?></th>
                <th><?php esc_html_e('Status', 'wp-coa-pro'); ?></th>
                <th><?php esc_html_e('Integrity', 'wp-coa-pro'); ?></th>
                <th><?php esc_html_e('Actions', 'wp-coa-pro'); ?></th>
            </tr></thead><tbody>
            <?php if ($rows) : foreach ($rows as $row) : ?>
                <tr>
                    <td><code><?php echo esc_html($row->certificate_code); ?></code></td>
                    <td><?php echo esc_html($row->artwork_title); ?><?php if (!empty($row->accession_number)) : ?><br><small><?php echo esc_html($row->accession_number); ?></small><?php endif; ?></td>
                    <td><?php echo esc_html($row->artist_name); ?><?php if (!empty($row->artist_name_en)) : ?><br><small dir="ltr"><?php echo esc_html($row->artist_name_en); ?></small><?php endif; ?></td>
                    <td><?php $row_gallery = isset($gallery_by_id[(int) $row->gallery_id]) ? $gallery_by_id[(int) $row->gallery_id] : null; echo $row_gallery ? esc_html($row_gallery->name) : (!empty($row->issuer_name) ? esc_html($row->issuer_name) : '&mdash;'); ?></td>
                    <td><?php echo esc_html(WP_COA_Pro_Galleries::activity_label(isset($row->activity_type) ? $row->activity_type : 'gallery')); ?></td>
                    <td><?php echo !empty($row->collector_name) ? esc_html($row->collector_name) : '&mdash;'; ?><?php if (!empty($row->collector_name_en)) : ?><br><small dir="ltr"><?php echo esc_html($row->collector_name_en); ?></small><?php endif; ?></td>
                    <td><?php echo !empty($row->current_owner_name) ? esc_html($row->current_owner_name) : (!empty($row->current_owner_name_en) ? esc_html($row->current_owner_name_en) : '&mdash;'); ?></td>
                    <td><?php echo esc_html($row->issue_date); ?></td>
                    <td><?php echo $row->status === 'active' ? '<span class="aap-s-eb34d1">' . esc_html__('Active', 'wp-coa-pro') . '</span>' : '<span class="aap-s-2e3ebe">' . esc_html__('Revoked', 'wp-coa-pro') . '</span>'; ?></td>
                    <td><?php echo $this->signature->verify($row) ? '<span class="aap-s-eb34d1">' . esc_html__('Verified', 'wp-coa-pro') . '</span>' : '<span class="aap-s-2e3ebe"><strong>' . esc_html__('CHECK FAILED', 'wp-coa-pro') . '</strong></span>'; ?></td>
                    <td>
                        <?php $print_url = wp_nonce_url(add_query_arg(array('action' => 'wp_coa_print_certificate', 'id' => (int) $row->id), admin_url('admin-post.php')), 'wp_coa_print_certificate_' . (int) $row->id); ?>
                        <a class="button button-small" href="<?php echo esc_url($print_url); ?>"><?php esc_html_e('Print / PDF', 'wp-coa-pro'); ?></a>
                        <a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page' => 'wp-coa-pro-events', 'certificate_id' => (int) $row->id), admin_url('admin.php'))); ?>"><?php esc_html_e('History', 'wp-coa-pro'); ?></a>
                        <?php if ($row->status === 'active') : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:6px">
                                <input type="hidden" name="action" value="wp_coa_revoke_certificate">
                                <input type="hidden" name="id" value="<?php echo (int) $row->id; ?>">
                                <?php wp_nonce_field('wp_coa_revoke_certificate_' . (int) $row->id); ?>
                                <label class="screen-reader-text" for="coa-reason-<?php echo (int) $row->id; ?>"><?php esc_html_e('Revocation reason', 'wp-coa-pro'); ?></label>
                                <input required type="text" id="coa-reason-<?php echo (int) $row->id; ?>" name="reason" maxlength="500" placeholder="<?php esc_attr_e('Reason for revocation', 'wp-coa-pro'); ?>" style="width:180px;max-width:100%">
                                <button class="button button-small" type="submit" onclick="return confirm('<?php echo esc_js(__('Revoke this certificate? This action cannot be undone from this screen.', 'wp-coa-pro')); ?>')"><?php esc_html_e('Revoke', 'wp-coa-pro'); ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($row->status === 'active') : ?>
                            <details class="aap-s-7b99a7">
                                <summary><?php esc_html_e('Transfer ownership', 'wp-coa-pro'); ?></summary>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px">
                                    <input type="hidden" name="action" value="wp_coa_transfer_owner">
                                    <input type="hidden" name="id" value="<?php echo (int) $row->id; ?>">
                                    <?php wp_nonce_field('wp_coa_transfer_owner_' . (int) $row->id); ?>
                                    <label for="coa-owner-<?php echo (int) $row->id; ?>"><?php esc_html_e('New owner (Persian / primary)', 'wp-coa-pro'); ?></label>
                                    <input type="text" id="coa-owner-<?php echo (int) $row->id; ?>" name="new_owner_name" maxlength="255" value="" style="width:190px;max-width:100%">
                                    <label for="coa-owner-en-<?php echo (int) $row->id; ?>"><?php esc_html_e('New owner (English)', 'wp-coa-pro'); ?></label>
                                    <input type="text" id="coa-owner-en-<?php echo (int) $row->id; ?>" name="new_owner_name_en" maxlength="255" value="" style="width:190px;max-width:100%">
                                    <p class="description"><?php esc_html_e('The previous owner is retained in the signed record and audit event.', 'wp-coa-pro'); ?></p>
                                    <button class="button button-small" type="submit" onclick="return confirm('<?php echo esc_js(__('Record this ownership transfer?', 'wp-coa-pro')); ?>')"><?php esc_html_e('Record transfer', 'wp-coa-pro'); ?></button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="11"><?php esc_html_e('No certificates match the current filters.', 'wp-coa-pro'); ?></td></tr>
            <?php endif; ?>
            </tbody></table>
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav"><div class="tablenav-pages">
                    <?php
                    echo wp_kses_post(paginate_links(array(
                        'base' => add_query_arg(array('page' => 'wp-coa-pro', 's' => $search, 'status' => $status_filter, 'gallery_id' => $gallery_filter, 'activity_type' => $activity_filter, 'paged' => '%#%'), admin_url('admin.php')),
                        'format' => '', 'current' => $page, 'total' => $total_pages,
                        'prev_text' => __('&laquo; Previous', 'wp-coa-pro'), 'next_text' => __('Next &raquo;', 'wp-coa-pro'),
                    )));
                    ?>
                </div></div>
            <?php endif; ?>
            <p class="description"><?php esc_html_e('A signature check detects changes to signed certificate metadata. It does not independently prove the physical artwork is authentic. The event history is hash-chained for tamper evidence, not guaranteed immutable storage.', 'wp-coa-pro'); ?></p>
        </div>
        <?php
    }

    public function events_page() {
        $this->require_admin_permission();
        $certificate_id = isset($_GET['certificate_id']) ? absint($_GET['certificate_id']) : 0;
        $rows = $certificate_id ? $this->ledger->for_certificate($certificate_id) : $this->ledger->recent(100);
        $certificate = $certificate_id ? $this->repository->get_by_id($certificate_id) : null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('COA Event History', 'wp-coa-pro'); ?></h1>
            <?php if ($certificate_id) : ?>
                <p><?php echo esc_html__('Certificate:', 'wp-coa-pro'); ?> <code><?php echo $certificate ? esc_html($certificate->certificate_code) : esc_html__('Not found', 'wp-coa-pro'); ?></code>
                <?php if ($certificate) : ?> — <?php echo esc_html($certificate->artwork_title); ?><?php endif; ?>
                &nbsp; <a href="<?php echo esc_url(admin_url('admin.php?page=wp-coa-pro')); ?>"><?php esc_html_e('Back to certificates', 'wp-coa-pro'); ?></a></p>
                <?php if ($certificate) : $chain_valid = $this->ledger->verify_chain($certificate_id); ?>
                    <div class="notice notice-<?php echo $chain_valid ? 'success' : 'error'; ?> inline"><p><strong><?php esc_html_e('Event chain integrity:', 'wp-coa-pro'); ?></strong> <?php echo $chain_valid ? esc_html__('Passed — hashes link in sequence.', 'wp-coa-pro') : esc_html__('Failed or no events exist. Investigate before relying on the history.', 'wp-coa-pro'); ?></p></div>
                <?php endif; ?>
            <?php else : ?>
                <p><?php esc_html_e('Most recent 100 certificate events. Details are visible to authorized administrators only.', 'wp-coa-pro'); ?></p>
            <?php endif; ?>
            <table class="widefat striped"><thead><tr>
                <th><?php esc_html_e('Date (UTC)', 'wp-coa-pro'); ?></th><th><?php esc_html_e('Certificate', 'wp-coa-pro'); ?></th><th><?php esc_html_e('Event', 'wp-coa-pro'); ?></th><th><?php esc_html_e('Actor', 'wp-coa-pro'); ?></th><th><?php esc_html_e('Details', 'wp-coa-pro'); ?></th><th><?php esc_html_e('Event hash', 'wp-coa-pro'); ?></th>
            </tr></thead><tbody>
            <?php if ($rows) : foreach ($rows as $event) :
                $details = json_decode((string) $event->event_payload, true);
                if (!is_array($details)) { $details = array(); }
                $actor = $event->actor_id ? get_userdata((int) $event->actor_id) : false;
                ?>
                <tr>
                    <td><?php echo esc_html($event->created_at); ?></td>
                    <td><?php if (isset($event->certificate_code)) : ?><code><?php echo esc_html($event->certificate_code); ?></code><br><?php echo esc_html(isset($event->artwork_title) ? $event->artwork_title : ''); ?><?php elseif ($certificate) : ?><code><?php echo esc_html($certificate->certificate_code); ?></code><?php else : ?>#<?php echo (int) $event->certificate_id; ?><?php endif; ?></td>
                    <td><?php echo esc_html($event->event_type); ?></td>
                    <td><?php echo $actor ? esc_html($actor->display_name) : (int) $event->actor_id; ?></td>
                    <td><?php echo esc_html(wp_json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></td>
                    <td><code title="<?php echo esc_attr($event->event_hash); ?>"><?php echo esc_html(substr($event->event_hash, 0, 16)); ?>…</code></td>
                </tr>
            <?php endforeach; else : ?><tr><td colspan="6"><?php esc_html_e('No events found.', 'wp-coa-pro'); ?></td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <?php
    }

    public function handle_create_certificate() {
        $this->require_admin_permission();
        check_admin_referer('wp_coa_create_certificate');

        $data = array(
            'gallery_id' => isset($_POST['gallery_id']) ? absint($_POST['gallery_id']) : 0,
            'artist_profile_id' => isset($_POST['artist_profile_id']) && is_scalar($_POST['artist_profile_id']) ? absint(wp_unslash($_POST['artist_profile_id'])) : 0,
            'collector_profile_id' => isset($_POST['collector_profile_id']) && is_scalar($_POST['collector_profile_id']) ? absint(wp_unslash($_POST['collector_profile_id'])) : 0,
            'artwork_image_id' => isset($_POST['artwork_image_id']) && is_scalar($_POST['artwork_image_id']) ? absint(wp_unslash($_POST['artwork_image_id'])) : 0,
            'artwork_title' => $this->post_text('artwork_title', 255),
            'artwork_title_en' => $this->post_text('artwork_title_en', 255),
            'artist_name' => $this->post_text('artist_name', 255),
            'artist_name_en' => $this->post_text('artist_name_en', 255),
            'medium' => $this->post_text('medium', 255),
            'medium_en' => $this->post_text('medium_en', 255),
            'year_created' => $this->post_text('year_created', 20),
            'dimensions' => $this->post_text('dimensions', 255),
            'accession_number' => $this->post_text('accession_number', 100),
            'issuer_name' => $this->post_text('issuer_name', 255),
            'issuer_name_en' => $this->post_text('issuer_name_en', 255),
            'artwork_description' => $this->post_textarea('artwork_description', 5000),
            'artwork_description_en' => $this->post_textarea('artwork_description_en', 5000),
            'collector_name' => $this->post_text('collector_name', 255),
            'collector_name_en' => $this->post_text('collector_name_en', 255),
            'current_owner_name' => $this->post_text('current_owner_name', 255),
            'current_owner_name_en' => $this->post_text('current_owner_name_en', 255),
            'previous_owner_name' => $this->post_text('previous_owner_name', 255),
            'previous_owner_name_en' => $this->post_text('previous_owner_name_en', 255),
            'notes' => $this->post_textarea('notes', 5000),
        );
        if ($data['artist_profile_id']) {
            $artist_profile = WP_COA_Pro_Person_Profiles::get_profile($data['artist_profile_id'], true);
            if (!$artist_profile || $artist_profile->profile_type !== 'artist') { $this->redirect_admin(array('coa_notice' => 'artist_profile_unavailable')); }
            $data['artist_name'] = sanitize_text_field($artist_profile->display_name);
            $data['artist_name_en'] = sanitize_text_field($artist_profile->display_name_en);
        }
        if ($data['collector_profile_id']) {
            $collector_profile = WP_COA_Pro_Person_Profiles::get_profile($data['collector_profile_id'], true);
            if (!$collector_profile || $collector_profile->profile_type !== 'collector') { $this->redirect_admin(array('coa_notice' => 'collector_profile_unavailable')); }
            $data['collector_name'] = sanitize_text_field($collector_profile->display_name);
            $data['collector_name_en'] = sanitize_text_field($collector_profile->display_name_en);
        }
        $coa_settings = WP_COA_Pro_Admin::get_settings();
        $selected_gallery = WP_COA_Pro_Galleries::get_profile($data['gallery_id']);
        if (!$selected_gallery || $selected_gallery->status !== 'active') {
            $this->redirect_admin(array('coa_notice' => 'gallery_profile_unavailable'));
        }
        if ($data['issuer_name'] === '') {
            $data['issuer_name'] = sanitize_text_field($selected_gallery->name !== '' ? $selected_gallery->name : ($coa_settings['issuer_name'] !== '' ? $coa_settings['issuer_name'] : get_bloginfo('name')));
        }
        if ($data['issuer_name_en'] === '') {
            $data['issuer_name_en'] = sanitize_text_field($selected_gallery->name_en !== '' ? $selected_gallery->name_en : $coa_settings['issuer_name_en']);
        }
        if ($data['artwork_title'] === '' || $data['artist_name'] === '') {
            $this->redirect_admin(array('coa_notice' => 'missing_fields'));
        }

        $result = $this->repository->issue($data);
        if (is_wp_error($result)) {
            error_log('[WP COA Pro] Certificate issue failed: ' . $result->get_error_code());
            $image_errors = array('coa_artwork_image_invalid', 'coa_artwork_image_forbidden', 'coa_artwork_image_unavailable', 'coa_artwork_image_too_large', 'coa_artwork_image_hash_failed');
            $this->redirect_admin(array('coa_notice' => in_array($result->get_error_code(), $image_errors, true) ? 'image_invalid' : 'create_failed'));
        }
        WP_COA_Pro_Page_Integration::invalidate_public_stats_cache();
        /**
         * Artiey Art Platform hook: lets other modules react (e.g. link the new certificate to an artwork).
         * The request has already passed capability and nonce checks at this point.
         */
        do_action('wp_coa_pro_certificate_issued', $result, $data);
        $this->redirect_admin(array('coa_notice' => 'created', 'coa_id' => (int) $result->id));
    }

    public function handle_revoke_certificate() {
        $this->require_admin_permission();
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        check_admin_referer('wp_coa_revoke_certificate_' . $id);
        $reason = $this->post_text('reason', 500);
        if (!$id || $reason === '') {
            $this->redirect_admin(array('coa_notice' => 'revoke_failed'));
        }
        $result = $this->repository->revoke($id, $reason);
        if (is_wp_error($result)) {
            error_log('[WP COA Pro] Certificate revocation failed: ' . $result->get_error_code());
            $this->redirect_admin(array('coa_notice' => 'revoke_failed'));
        }
        WP_COA_Pro_Page_Integration::invalidate_public_stats_cache();
        $this->redirect_admin(array('coa_notice' => 'revoked'));
    }

    public function handle_transfer_owner() {
        $this->require_admin_permission();
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        check_admin_referer('wp_coa_transfer_owner_' . $id);
        $new_owner = $this->post_text('new_owner_name', 255);
        $new_owner_en = $this->post_text('new_owner_name_en', 255);
        if (!$id || ($new_owner === '' && $new_owner_en === '')) {
            $this->redirect_admin(array('coa_notice' => 'owner_transfer_failed'));
        }
        $result = $this->repository->transfer_owner($id, $new_owner, $new_owner_en);
        if (is_wp_error($result)) {
            error_log('[WP COA Pro] Ownership transfer failed: ' . $result->get_error_code());
            $this->redirect_admin(array('coa_notice' => 'owner_transfer_failed'));
        }
        $this->redirect_admin(array('coa_notice' => 'ownership_transferred'));
    }

    public function handle_print_certificate() {
        $this->require_admin_permission();
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('wp_coa_print_certificate_' . $id);
        $record = $this->repository->get_by_id($id);
        if (!$record) {
            wp_die(esc_html__('Certificate not found.', 'wp-coa-pro'));
        }
        $integrity_valid = $this->signature->verify($record);
        $lifecycle_valid = $this->ledger->verify_certificate_state($record);
        $coa_settings = WP_COA_Pro_Galleries::settings_for_record($record, WP_COA_Pro_Admin::get_settings());
        $primary_color = sanitize_hex_color($coa_settings['primary_color']) ? $coa_settings['primary_color'] : '#65513b';
        $accent_color = sanitize_hex_color($coa_settings['accent_color']) ? $coa_settings['accent_color'] : '#b29a68';
        $template_style = isset($coa_settings['template_style']) && isset(WP_COA_Pro_Galleries::templates()[$coa_settings['template_style']]) ? $coa_settings['template_style'] : 'classic';
        $certificate_title = isset($coa_settings['certificate_title']) && $coa_settings['certificate_title'] !== '' ? $coa_settings['certificate_title'] : 'گواهی اصالت اثر هنری';
        $certificate_title_en = isset($coa_settings['certificate_title_en']) && $coa_settings['certificate_title_en'] !== '' ? $coa_settings['certificate_title_en'] : 'Certificate of Authenticity';
        $artwork_image_url = !empty($record->artwork_image_id) ? wp_get_attachment_image_url((int) $record->artwork_image_id, 'large') : false;
        $verification_url = self::get_verification_url($record->certificate_code);
        nocache_headers();
        status_header(200);
        ?><!doctype html>
        <html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html($record->certificate_code); ?> — گواهی اصالت اثر هنری / Certificate of Authenticity</title>
        <style>
            body{font-family:Georgia,"Times New Roman",serif;color:#202020;background:#f1f1f1;margin:0;padding:30px}.coa-sheet{box-sizing:border-box;max-width:900px;min-height:620px;margin:0 auto;background:#fff;padding:58px;border:1px solid #d6d0c4;outline:8px double <?php echo esc_html($accent_color); ?>;outline-offset:-22px}.coa-kicker{text-transform:uppercase;letter-spacing:.22em;font:12px Arial,sans-serif;color:<?php echo esc_html($primary_color); ?>;text-align:center}.coa-title{text-align:center;font-size:32px;font-weight:normal;margin:14px 0 8px}.coa-subtitle{text-align:center;font:13px Arial,sans-serif;color:#555;margin-bottom:38px}.coa-code{text-align:center;font:14px monospace;letter-spacing:.08em;margin:20px 0 24px}.coa-media-row{display:flex;align-items:center;justify-content:center;gap:34px;margin:12px auto 28px;flex-wrap:wrap}.coa-artwork-photo{display:block;max-width:260px;max-height:220px;object-fit:contain;border:1px solid #ddd;padding:5px;background:#fff}.coa-qr-wrap{width:126px;text-align:center;font:10px Arial,sans-serif;color:#555}.wp-coa-qr{width:126px;height:126px;margin:0 auto 6px}.wp-coa-qr svg{display:block;width:100%;height:100%}.coa-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px 38px}.coa-field label{display:block;font:10px Arial,sans-serif;text-transform:uppercase;letter-spacing:.12em;color:#777;margin-bottom:7px}.coa-field div{font-size:17px;overflow-wrap:anywhere}.coa-description{margin-top:30px;font-size:14px;line-height:1.65;white-space:pre-wrap}.coa-status{margin-top:32px;padding-top:14px;border-top:1px solid #ddd;font:12px Arial,sans-serif}.coa-footer{display:flex;justify-content:space-between;gap:20px;margin-top:42px;padding-top:14px;border-top:1px solid #ddd;font:11px Arial,sans-serif;color:#666}.coa-warning{color:#a12622;font-weight:bold}.coa-sheet.coa-template-modern{outline:none;border:0;border-top:12px solid <?php echo esc_html($primary_color); ?>;box-shadow:0 10px 30px rgba(0,0,0,.08);padding-top:46px}.coa-sheet.coa-template-minimal{outline:none;border:0;border-left:5px solid <?php echo esc_html($primary_color); ?>;box-shadow:none;padding:46px}.coa-sheet.coa-template-archive{outline:8px double <?php echo esc_html($accent_color); ?>;background:#fffdf7;border:1px solid <?php echo esc_html($accent_color); ?>}.coa-actions{text-align:center;margin:30px}.coa-actions button{padding:10px 16px;cursor:pointer}@media(max-width:650px){body{padding:10px}.coa-sheet{padding:38px 26px;min-height:0}.coa-artwork-photo{max-width:200px;max-height:180px}.coa-grid{grid-template-columns:1fr}.coa-footer{display:block}}@media print{body{background:#fff;padding:0}.coa-sheet{max-width:none;min-height:0;border:1px solid #b6a77d;outline:6px double #b6a77d;outline-offset:-18px;box-shadow:none;margin:10mm;padding:18mm}.coa-actions{display:none}@page{size:A4;margin:8mm}}
        </style></head><body>
        <main class="coa-sheet coa-template-<?php echo esc_attr($template_style); ?>">
            <?php if (!empty($coa_settings['logo_url'])) : ?><div style="text-align:center;margin-bottom:14px"><img src="<?php echo esc_url($coa_settings['logo_url']); ?>" alt="" style="max-width:160px;max-height:70px;object-fit:contain"></div><?php endif; ?>
            <div class="coa-kicker"><?php echo esc_html(isset($coa_settings['name']) && $coa_settings['name'] !== '' ? $coa_settings['name'] : 'ثبت آثار هنری / Artwork Registry'); ?><?php if (!empty($coa_settings['name_en'])) : ?><br><span dir="ltr"><?php echo esc_html($coa_settings['name_en']); ?></span><?php endif; ?></div>
            <h1 class="coa-title"><?php echo esc_html($certificate_title); ?><?php if ($certificate_title_en !== '' && $certificate_title_en !== $certificate_title) : ?><div dir="ltr" style="font-size:.55em;margin-top:7px"><?php echo esc_html($certificate_title_en); ?></div><?php endif; ?></h1>
            <div class="coa-subtitle"><?php echo esc_html($record->issuer_name); ?><?php if (!empty($record->issuer_name_en)) : ?><br><span dir="ltr"><?php echo esc_html($record->issuer_name_en); ?></span><?php endif; ?><br><span style="font-size:11px;color:<?php echo esc_html($accent_color); ?>"><?php echo esc_html(WP_COA_Pro_Galleries::activity_label(isset($record->activity_type) ? $record->activity_type : 'gallery')); ?></span></div>
            <div class="coa-code"><?php echo esc_html($record->certificate_code); ?></div>
            <div class="coa-media-row">
                <?php if ($artwork_image_url) : ?><img class="coa-artwork-photo" src="<?php echo esc_url($artwork_image_url); ?>" alt="<?php echo esc_attr($record->artwork_title); ?>" loading="eager"> <?php endif; ?>
                <?php if ($verification_url !== '') : ?><div class="coa-qr-wrap"><div class="wp-coa-qr" data-wp-coa-qr="<?php echo esc_attr($verification_url); ?>" aria-label="QR Code certificate verification"></div><span>برای استعلام، اسکن کنید<br>Scan to verify</span></div><?php endif; ?>
            </div>
            <div class="coa-grid">
                <?php $this->print_dual_field('عنوان اثر / Artwork title', $record->artwork_title, isset($record->artwork_title_en) ? $record->artwork_title_en : ''); ?>
                <?php $this->print_dual_field('هنرمند / Artist', $record->artist_name, isset($record->artist_name_en) ? $record->artist_name_en : ''); ?>
                <?php if ($record->medium !== '' || !empty($record->medium_en)) : $this->print_dual_field('تکنیک و مواد / Medium & materials', $record->medium, isset($record->medium_en) ? $record->medium_en : ''); endif; ?>
                <?php if ($record->year_created !== '') : $this->print_field('سال خلق / Year created', $record->year_created); endif; ?>
                <?php if ($record->dimensions !== '') : $this->print_field('ابعاد / Dimensions', $record->dimensions); endif; ?>
                <?php if ($record->accession_number !== '') : $this->print_field('شماره ثبت / Inventory number', $record->accession_number); endif; ?>
                <?php if (!empty($record->collector_name) || !empty($record->collector_name_en)) : $this->print_dual_field('مجموعه‌دار / Collector', $record->collector_name, isset($record->collector_name_en) ? $record->collector_name_en : ''); endif; ?>
                <?php if (!empty($record->current_owner_name) || !empty($record->current_owner_name_en)) : $this->print_dual_field('مالک فعلی / Current owner', $record->current_owner_name, isset($record->current_owner_name_en) ? $record->current_owner_name_en : ''); endif; ?>
                <?php if (!empty($record->previous_owner_name) || !empty($record->previous_owner_name_en)) : $this->print_dual_field('مالک پیشین / Previous owner', $record->previous_owner_name, isset($record->previous_owner_name_en) ? $record->previous_owner_name_en : ''); endif; ?>
                <?php $this->print_field('تاریخ صدور (UTC) / Issue date (UTC)', $record->issue_date); ?>
                <?php $this->print_field('وضعیت گواهی / Certificate status', $record->status === 'active' ? 'فعال / Active' : 'باطل‌شده / Revoked'); ?>
            </div>
            <?php if ($record->artwork_description !== '' || !empty($record->artwork_description_en)) : ?><div class="coa-description"><?php echo nl2br(esc_html($record->artwork_description)); ?><?php if (!empty($record->artwork_description_en)) : ?><div dir="ltr" style="margin-top:8px"><?php echo nl2br(esc_html($record->artwork_description_en)); ?></div><?php endif; ?></div><?php endif; ?>
            <div class="coa-status">
                <?php if ($integrity_valid) : ?><strong>بررسی یکپارچگی رکورد موفق بود / Record integrity check: passed</strong><?php else : ?><strong class="coa-warning">هشدار: بررسی یکپارچگی رکورد ناموفق بود؛ پیش از اتکا بررسی کنید / WARNING: Record integrity check failed; review before relying on this printout.</strong><?php endif; ?>
                <p><?php if ($lifecycle_valid === true) : ?><strong>بررسی چرخه عمر و تاریخچه ممیزی موفق بود / Lifecycle and audit history: passed</strong><?php elseif ($lifecycle_valid === false) : ?><strong class="coa-warning">هشدار: وضعیت با تاریخچه ممیزی مطابقت ندارد / WARNING: Lifecycle state does not match the audit history.</strong><?php else : ?><span>تاریخچه چرخه عمر برای این رکورد قدیمی موجود نیست / Lifecycle audit history is unavailable for this legacy record.</span><?php endif; ?></p>
                <?php if ($record->status !== 'active') : ?><p class="coa-warning">این گواهی باطل شده و نباید معتبر معرفی شود / This certificate has been revoked and must not be presented as currently valid.</p><?php endif; ?>
                <p>وضعیت را از صفحه استعلام عمومی مؤسسه صادرکننده بررسی کنید. رکورد دیجیتال به‌تنهایی اصالت فیزیکی اثر را اثبات نمی‌کند. / Verify current status on the issuer’s public page. A digital record alone does not prove the physical artwork authentic.</p>
            </div>
            <div class="coa-footer"><span><?php echo esc_html(home_url('/')); ?></span><span><?php echo esc_html($coa_settings['certificate_footer']); ?><?php if (!empty($coa_settings['certificate_footer_en'])) : ?><br><span dir="ltr"><?php echo esc_html($coa_settings['certificate_footer_en']); ?></span><?php endif; ?></span></div>
        </main>
        <script src="<?php echo esc_url(WP_COA_PRO_URL . 'assets/js/wp-coa-pro-qr.js?ver=' . rawurlencode(WP_COA_PRO_VERSION)); ?>"></script>
        <div class="coa-actions"><button onclick="window.print()" type="button"><?php esc_html_e('Print / Save as PDF', 'wp-coa-pro'); ?></button> <button onclick="window.close()" type="button"><?php esc_html_e('Close', 'wp-coa-pro'); ?></button></div>
        </body></html><?php
        exit;
    }

    public function handle_export_csv() {
        $this->require_admin_permission();
        check_admin_referer('wp_coa_export_csv');
        $export_args = array(
            'search' => isset($_POST['s']) && is_scalar($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '',
            'status' => isset($_POST['status']) && is_scalar($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '',
            'gallery_id' => isset($_POST['gallery_id']) && is_scalar($_POST['gallery_id']) ? absint($_POST['gallery_id']) : 0,
            'activity_type' => isset($_POST['activity_type']) && is_scalar($_POST['activity_type']) ? sanitize_key(wp_unslash($_POST['activity_type'])) : '',
        );
        $rows = $this->repository->all_for_export($export_args);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="wp-coa-public-metadata-' . gmdate('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        if ($output === false) {
            wp_die(esc_html__('Could not open CSV output.', 'wp-coa-pro'));
        }
        // UTF-8 BOM helps spreadsheet applications detect Persian text. Formula-leading strings are neutralized.
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, array('certificate_code', 'artwork_title', 'artwork_title_en', 'artist_name', 'artist_name_en', 'gallery_id', 'gallery_name', 'gallery_name_en', 'activity_type', 'activity_label', 'certificate_template', 'medium', 'medium_en', 'year_created', 'dimensions', 'accession_number', 'artwork_description', 'artwork_description_en', 'issuer_name', 'issuer_name_en', 'issue_date_utc', 'status', 'integrity_check'));
        foreach ($rows as $row) {
            $gallery_snapshot = WP_COA_Pro_Galleries::snapshot_for_record($row, array());
            $gallery_name = isset($gallery_snapshot['name']) ? $gallery_snapshot['name'] : $row->issuer_name;
            $gallery_name_en = isset($gallery_snapshot['name_en']) ? $gallery_snapshot['name_en'] : $row->issuer_name_en;
            $cells = array(
                $row->certificate_code, $row->artwork_title, $row->artwork_title_en, $row->artist_name, $row->artist_name_en,
                isset($row->gallery_id) ? $row->gallery_id : 0, $gallery_name, $gallery_name_en,
                isset($row->activity_type) ? $row->activity_type : 'gallery',
                WP_COA_Pro_Galleries::activity_label(isset($row->activity_type) ? $row->activity_type : 'gallery', 'en'),
                isset($row->certificate_template) ? $row->certificate_template : 'classic',
                $row->medium, $row->medium_en, $row->year_created, $row->dimensions, $row->accession_number,
                $row->artwork_description, $row->artwork_description_en, $row->issuer_name, $row->issuer_name_en,
                $row->issue_date, $row->status,
                $this->signature->verify($row) ? 'passed' : 'failed',
            );
            foreach ($cells as &$cell) {
                $cell = $this->csv_safe_cell((string) $cell);
            }
            unset($cell);
            fputcsv($output, $cells);
        }
        fclose($output);
        exit;
    }

    public function verification_shortcode($attributes = array()) {
        $settings = WP_COA_Pro_Admin::get_settings();
        $attributes = shortcode_atts(array(
            'title' => '',
            'title_en' => '',
            'description' => '',
            'description_en' => '',
            'placeholder' => '',
            'placeholder_en' => '',
            'button_text' => '',
            'button_text_en' => '',
            'primary_color' => $settings['primary_color'],
            'border_color' => '#dedbd5',
            'border_radius' => 8,
            'show_title' => '1',
            'hide_description' => '0',
            'language' => $settings['display_language'],
        ), is_array($attributes) ? $attributes : array(), 'coa_verify');

        $requested_language = isset($_GET['coa_lang']) && !empty($settings['enable_language_switcher'])
            ? sanitize_key(wp_unslash($_GET['coa_lang']))
            : sanitize_key((string) $attributes['language']);
        $language = $this->resolve_public_language($requested_language, $settings);
        $primary_color = sanitize_hex_color($attributes['primary_color']) ? $attributes['primary_color'] : $settings['primary_color'];
        $border_color = sanitize_hex_color($attributes['border_color']) ? $attributes['border_color'] : '#dedbd5';
        $border_radius = max(0, min(32, absint($attributes['border_radius'])));
        $input_id = function_exists('wp_unique_id') ? wp_unique_id('wp-coa-code-') : 'wp-coa-code-' . wp_rand(1000, 999999);
        $code = isset($_GET['coa_code']) ? self::normalize_code(wp_unslash($_GET['coa_code'])) : '';
        if (strlen($code) > 64 || ($code !== '' && !preg_match('/^COA-[A-Z0-9]{4,12}-[A-Z0-9]{4,16}$/', $code))) {
            $code = '';
            $invalid_code = true;
        } else {
            $invalid_code = false;
        }
        $lookup_record = ($code !== '' && !$invalid_code) ? $this->repository->get_by_code($code) : null;
        $result_settings = $lookup_record ? WP_COA_Pro_Galleries::settings_for_record($lookup_record, $settings) : $settings;
        if ($lookup_record && isset($result_settings['primary_color']) && sanitize_hex_color($result_settings['primary_color'])) {
            $primary_color = $result_settings['primary_color'];
        }

        $title = $attributes['title'] !== '' ? $this->localized_override(sanitize_text_field($attributes['title']), sanitize_text_field($attributes['title_en']), $language) : $this->localized_setting($settings, 'verification_title', $language);
        $description = $attributes['description'] !== '' ? $this->localized_override(sanitize_textarea_field($attributes['description']), sanitize_textarea_field($attributes['description_en']), $language) : $this->localized_setting($settings, 'verification_description', $language);
        $placeholder = $attributes['placeholder'] !== '' ? $this->localized_override(sanitize_text_field($attributes['placeholder']), sanitize_text_field($attributes['placeholder_en']), $language) : $this->localized_setting($settings, 'verification_placeholder', $language);
        $button_text = $attributes['button_text'] !== '' ? $this->localized_override(sanitize_text_field($attributes['button_text']), sanitize_text_field($attributes['button_text_en']), $language) : $this->localized_setting($settings, 'verification_button', $language);
        $dir = $language === 'fa' ? 'rtl' : 'ltr';
        // The GET form must come back to a page that really contains the shortcode. On the QR route there is no
        // current post, and with plain permalinks the page id lives in the query string that a GET form would drop.
        $form_action = get_permalink();
        if (!$form_action && class_exists('WP_COA_Pro_Page_Integration')) {
            $form_action = WP_COA_Pro_Page_Integration::get_managed_page_url('verification');
        }
        if (!$form_action) { $form_action = home_url('/'); }
        $form_hidden = array();
        $form_query = (string) wp_parse_url($form_action, PHP_URL_QUERY);
        if ($form_query !== '') {
            parse_str($form_query, $form_hidden);
            $form_action = strtok($form_action, '?');
        }
        if ($lookup_record && $this->signature) {
            wp_enqueue_script('wp-coa-pro-qr');
        }

        ob_start(); ?>
        <div class="wp-coa-verification" dir="<?php echo esc_attr($dir); ?>" lang="<?php echo esc_attr($language === 'both' ? 'fa' : $language); ?>" style="--wp-coa-primary:<?php echo esc_attr($primary_color); ?>;--wp-coa-border:<?php echo esc_attr($border_color); ?>;--wp-coa-radius:<?php echo (int) $border_radius; ?>px">
            <style>.wp-coa-verification{max-width:760px;margin:1.5rem auto;line-height:1.7;text-align:inherit;color:var(--wp-coa-widget-content-text,inherit)}.wp-coa-verification form{display:flex;gap:.5rem;flex-wrap:wrap;align-items:end;margin-bottom:1.25rem}.wp-coa-verification label{display:block;flex-basis:100%;font-weight:600}.wp-coa-verification input,.wp-coa-verification select{flex:1;min-width:150px;padding:.65rem;border:1px solid var(--wp-coa-border);border-radius:var(--wp-coa-radius);max-width:100%;box-sizing:border-box;background:var(--wp-coa-widget-input-bg,#fff);color:var(--wp-coa-widget-input-text,inherit)}.wp-coa-verification input[name="coa_code"]{direction:ltr;text-align:left}.wp-coa-verification button{padding:.7rem 1rem;cursor:pointer;background:var(--wp-coa-primary);border:1px solid var(--wp-coa-primary);color:var(--wp-coa-widget-button-text,#fff);border-radius:var(--wp-coa-radius)}.wp-coa-result{border:1px solid var(--wp-coa-border);border-radius:var(--wp-coa-radius);padding:var(--wp-coa-widget-panel-padding,1.1rem);background:var(--wp-coa-widget-panel,#fff);color:var(--wp-coa-widget-content-text,inherit);text-align:inherit}.wp-coa-result dl{display:grid;grid-template-columns:minmax(130px,.35fr) 1fr;gap:.45rem 1rem}.wp-coa-result dt{font-weight:600}.wp-coa-result dd{margin:0;overflow-wrap:anywhere}.wp-coa-value-en{display:block;direction:ltr;text-align:inherit;font-size:.95em;opacity:.9;margin-top:.12rem}.wp-coa-status-active{color:#087443}.wp-coa-status-revoked,.wp-coa-integrity-failed{color:#a12622;font-weight:700}.wp-coa-integrity-ok{color:#087443;font-weight:700}.wp-coa-disclaimer{font-size:.9em;color:#555;border-top:1px solid #eee;margin-top:1rem;padding-top:.75rem}@media(max-width:520px){.wp-coa-result dl{grid-template-columns:1fr;gap:.15rem}.wp-coa-result dd{margin-bottom:.5rem}}</style>
            <?php if (empty($lookup_record) && !empty($settings['show_logo_public']) && !empty($settings['logo_url'])) : ?><div class="wp-coa-verification-brand aap-s-5c2773"><img src="<?php echo esc_url($settings['logo_url']); ?>" alt="<?php echo esc_attr($this->localized_setting($settings, 'issuer_name', $language)); ?>" style="max-width:180px;max-height:72px;object-fit:contain"></div><?php endif; ?>
            <?php if (empty($settings['enable_public_lookup'])) : ?><p role="status"><?php echo esc_html($this->public_label('lookup_disabled', $language)); ?></p></div><?php return ob_get_clean(); endif; ?>
            <?php if ($attributes['show_title'] !== '0' && $title !== '') : ?><h2><?php echo esc_html($title); ?></h2><?php endif; ?>
            <?php if ($attributes['hide_description'] !== '1' && $description !== '') : ?><p><?php echo esc_html($description); ?></p><?php endif; ?>
            <form class="wp-coa-verification-form" method="get" action="<?php echo esc_url($form_action); ?>">
                <?php foreach ($form_hidden as $hidden_key => $hidden_value) : if (!is_scalar($hidden_value) || in_array($hidden_key, array('coa_code', 'coa_lang'), true)) { continue; } ?><input type="hidden" name="<?php echo esc_attr((string) $hidden_key); ?>" value="<?php echo esc_attr((string) $hidden_value); ?>"><?php endforeach; ?>
                <label for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($this->public_label('code', $language)); ?></label>
                <input type="text" id="<?php echo esc_attr($input_id); ?>" name="coa_code" value="<?php echo esc_attr($code); ?>" maxlength="64" placeholder="<?php echo esc_attr($placeholder); ?>" required autocomplete="off">
                <?php if (!empty($settings['enable_language_switcher'])) : ?>
                    <label for="<?php echo esc_attr($input_id); ?>-language"><?php echo esc_html($this->public_label('language', $language)); ?></label>
                    <select id="<?php echo esc_attr($input_id); ?>-language" name="coa_lang">
                        <option value="fa" <?php selected($language, 'fa'); ?>>فارسی</option>
                        <option value="en" <?php selected($language, 'en'); ?>>English</option>
                        <option value="both" <?php selected($language, 'both'); ?>>فارسی / English</option>
                    </select>
                <?php endif; ?>
                <button type="submit"><?php echo esc_html($button_text); ?></button>
            </form>
            <?php if ($invalid_code) : ?><p role="alert"><?php echo esc_html($this->public_label('invalid_code', $language)); ?></p><?php endif; ?>
            <?php if ($code !== '' && !$invalid_code) : $record = $lookup_record; ?>
                <?php if (!$record) : ?><p role="status"><?php echo esc_html($this->public_label('not_found', $language)); ?></p>
                <?php else : $integrity_valid = $this->signature->verify($record); ?>
                    <section class="wp-coa-result" aria-live="polite" style="--wp-coa-primary:<?php echo esc_attr(sanitize_hex_color($result_settings['primary_color']) ? $result_settings['primary_color'] : '#65513b'); ?>;--wp-coa-border:<?php echo esc_attr(sanitize_hex_color($result_settings['accent_color']) ? $result_settings['accent_color'] : '#dedbd5'); ?>">
                        <?php if (!empty($result_settings['show_logo_public']) && !empty($result_settings['logo_url'])) : ?><div class="wp-coa-verification-brand aap-s-5c2773"><img src="<?php echo esc_url($result_settings['logo_url']); ?>" alt="<?php echo esc_attr($record->issuer_name); ?>" style="max-width:180px;max-height:72px;object-fit:contain"></div><?php endif; ?>
                        <h2><?php echo esc_html($this->public_label('record', $language)); ?><?php if (!empty($result_settings['certificate_title'])) : ?> — <?php echo esc_html($this->localized_override($result_settings['certificate_title'], isset($result_settings['certificate_title_en']) ? $result_settings['certificate_title_en'] : '', $language)); ?><?php endif; ?></h2>
                        <?php if (!empty($record->activity_type)) : ?><p class="wp-coa-activity-label"><strong><?php echo esc_html($this->public_label('issuer', $language)); ?>:</strong> <?php echo esc_html($this->localized_override($record->issuer_name, isset($record->issuer_name_en) ? $record->issuer_name_en : '', $language)); ?> <span>· <?php echo esc_html(WP_COA_Pro_Galleries::activity_label($record->activity_type, $language === 'both' ? 'both' : $language)); ?></span></p><?php endif; ?>
                        <p><strong><?php echo esc_html($this->public_label('status', $language)); ?>:</strong>
                            <?php if ($record->status === 'active') : ?><span class="wp-coa-status-active"><?php echo esc_html($this->public_label('active', $language)); ?></span>
                            <?php else : ?><span class="wp-coa-status-revoked"><?php echo esc_html($this->public_label('revoked', $language)); ?></span><?php endif; ?>
                        </p>
                        <p><strong><?php echo esc_html($this->public_label('integrity', $language)); ?>:</strong>
                            <?php if ($integrity_valid) : ?><span class="wp-coa-integrity-ok"><?php echo esc_html($this->public_label('signature_passed', $language)); ?></span>
                            <?php else : ?><span class="wp-coa-integrity-failed"><?php echo esc_html($this->public_label('signature_failed', $language)); ?></span><?php endif; ?>
                        </p>
                        <?php $lifecycle_valid = $this->ledger->verify_certificate_state($record); ?>
                        <p><strong><?php echo esc_html($this->public_label('lifecycle', $language)); ?>:</strong>
                            <?php if ($lifecycle_valid === true) : ?><span class="wp-coa-integrity-ok"><?php echo esc_html($this->public_label('consistent', $language)); ?></span>
                            <?php elseif ($lifecycle_valid === false) : ?><span class="wp-coa-integrity-failed"><?php echo esc_html($this->public_label('inconsistent', $language)); ?></span>
                            <?php else : ?><span><?php echo esc_html($this->public_label('legacy_history', $language)); ?></span><?php endif; ?>
                        </p>
                        <?php $public_artwork_url = !empty($result_settings['show_artwork_image_public']) && !empty($record->artwork_image_id) ? wp_get_attachment_image_url((int) $record->artwork_image_id, 'large') : false; ?>
                        <?php if ($public_artwork_url) : ?><figure class="wp-coa-artwork-public-image aap-s-7db12d"><img src="<?php echo esc_url($public_artwork_url); ?>" alt="<?php echo esc_attr($record->artwork_title); ?>" loading="lazy" style="max-width:100%;height:auto;max-height:520px;object-fit:contain;border-radius:8px"><figcaption><?php echo esc_html($this->localized_override('تصویر ثبت‌شده اثر', 'Registered artwork image', $language)); ?></figcaption></figure><?php endif; ?>
                        <dl>
                            <?php $this->render_public_field('code', 'کد گواهی', 'Certificate code', $record->certificate_code, '', $language); ?>
                            <?php if (!empty($result_settings['show_artwork_title'])) : $this->render_public_field('artwork', 'عنوان اثر', 'Artwork title', $record->artwork_title, isset($record->artwork_title_en) ? $record->artwork_title_en : '', $language); endif; ?>
                            <?php if (!empty($result_settings['show_artist_name'])) : $this->render_public_linked_person_field('artist', 'هنرمند', 'Artist', $record->artist_name, isset($record->artist_name_en) ? $record->artist_name_en : '', $language, 'artist', isset($record->artist_profile_id) ? $record->artist_profile_id : 0, $integrity_valid); endif; ?>
                            <?php if (!empty($result_settings['show_medium']) && $record->medium !== '') : $this->render_public_field('medium', 'تکنیک و مواد', 'Medium / materials', $record->medium, isset($record->medium_en) ? $record->medium_en : '', $language); endif; ?>
                            <?php if (!empty($result_settings['show_year_created']) && $record->year_created !== '') : $this->render_public_field('year', 'سال خلق', 'Year created', $record->year_created, '', $language); endif; ?>
                            <?php if (!empty($result_settings['show_dimensions']) && $record->dimensions !== '') : $this->render_public_field('dimensions', 'ابعاد', 'Dimensions', $record->dimensions, '', $language); endif; ?>
                            <?php if (!empty($result_settings['show_accession_number']) && $record->accession_number !== '') : $this->render_public_field('accession', 'شماره ثبت', 'Accession number', $record->accession_number, '', $language); endif; ?>
                            <?php if (!empty($result_settings['show_issuer']) && $record->issuer_name !== '') : $this->render_public_field('issuer', 'مؤسسه صادرکننده', 'Issuing institution', $record->issuer_name, isset($record->issuer_name_en) ? $record->issuer_name_en : '', $language); endif; ?>
                            <?php if (!empty($result_settings['show_issue_date'])) : $this->render_public_field('date', 'تاریخ صدور (UTC)', 'Issue date (UTC)', $record->issue_date, '', $language); endif; ?>
                            <?php if (!empty($result_settings['show_collector_public']) && (!empty($record->collector_name) || !empty($record->collector_name_en))) : $this->render_public_linked_person_field('collector', 'مجموعه‌دار / مجموعه', 'Collector / collection', $record->collector_name, isset($record->collector_name_en) ? $record->collector_name_en : '', $language, 'collector', isset($record->collector_profile_id) ? $record->collector_profile_id : 0, $integrity_valid); endif; ?>
                            <?php if (!empty($result_settings['show_current_owner_public']) && (!empty($record->current_owner_name) || !empty($record->current_owner_name_en))) : $this->render_public_field('current_owner', 'مالک فعلی', 'Current owner', $record->current_owner_name, $record->current_owner_name_en, $language); endif; ?>
                            <?php if (!empty($result_settings['show_previous_owner_public']) && (!empty($record->previous_owner_name) || !empty($record->previous_owner_name_en))) : $this->render_public_field('previous_owner', 'مالک پیشین', 'Previous owner', $record->previous_owner_name, $record->previous_owner_name_en, $language); endif; ?>
                        </dl>
                        <?php if (!empty($result_settings['show_description']) && (!empty($record->artwork_description) || !empty($record->artwork_description_en))) : ?>
                            <div><strong><?php echo esc_html($this->public_label('description', $language)); ?></strong><p><?php echo nl2br(esc_html($this->localized_value($record->artwork_description, isset($record->artwork_description_en) ? $record->artwork_description_en : '', $language))); ?><?php if ($language === 'both' && !empty($record->artwork_description_en) && $record->artwork_description !== '') : ?><br><span class="wp-coa-value-en" dir="ltr"><?php echo nl2br(esc_html($record->artwork_description_en)); ?></span><?php endif; ?></p></div>
                        <?php endif; ?>
                        <?php $public_verify_url = self::get_verification_url($record->certificate_code); if ($public_verify_url !== '') : ?><div class="wp-coa-public-qr aap-s-eb62c4"><div class="wp-coa-qr" data-wp-coa-qr="<?php echo esc_attr($public_verify_url); ?>" style="width:120px;height:120px;margin:0 auto 8px"></div><small><?php echo esc_html($this->localized_override('کد QR برای بازگشت به صفحه استعلام', 'QR code to reopen this verification page', $language)); ?></small></div><?php endif; ?>
                        <p class="wp-coa-disclaimer"><?php echo esc_html($this->localized_setting($settings, 'public_disclaimer', $language)); ?></p>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function resolve_public_language($requested, $settings) {
        $allowed = array('fa', 'en', 'both', 'site');
        if (!in_array($requested, $allowed, true)) {
            $requested = isset($settings['display_language']) ? $settings['display_language'] : 'fa';
        }
        if ($requested === 'site') {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            $requested = (strpos(strtolower($locale), 'fa') === 0) ? 'fa' : 'en';
        }
        return in_array($requested, array('fa', 'en', 'both'), true) ? $requested : 'fa';
    }

    private function localized_value($primary, $english, $language) {
        $primary = (string) $primary;
        $english = (string) $english;
        if ($language === 'en') {
            return $english !== '' ? $english : $primary;
        }
        if ($language === 'fa') {
            return $primary !== '' ? $primary : $english;
        }
        return $primary !== '' ? $primary : $english;
    }

    private function localized_override($primary, $english, $language) {
        if ($language === 'en') {
            return $english !== '' ? $english : $primary;
        }
        if ($language === 'both' && $english !== '' && $primary !== '') {
            return $primary . ' / ' . $english;
        }
        return $primary !== '' ? $primary : $english;
    }

    private function localized_setting($settings, $key, $language) {
        $primary = isset($settings[$key]) ? (string) $settings[$key] : '';
        $english_key = $key . '_en';
        $english = isset($settings[$english_key]) ? (string) $settings[$english_key] : '';
        if ($language === 'en') {
            return $english !== '' ? $english : $primary;
        }
        if ($language === 'both' && $english !== '' && $primary !== '') {
            return $primary . ' / ' . $english;
        }
        return $primary !== '' ? $primary : $english;
    }

    private function public_label($key, $language) {
        $labels = array(
            'lookup_disabled' => array('استعلام عمومی گواهی در حال حاضر غیرفعال است.', 'Public certificate lookup is currently disabled.'),
            'code' => array('کد گواهی', 'Certificate code'),
            'language' => array('زبان نمایش', 'Display language'),
            'invalid_code' => array('کد گواهی را با قالب صحیح وارد کنید.', 'Enter a certificate code in the expected format.'),
            'not_found' => array('گواهی‌ای با این کد پیدا نشد.', 'No certificate was found for that code.'),
            'record' => array('شناسنامه گواهی', 'Certificate record'),
            'status' => array('وضعیت', 'Status'),
            'active' => array('فعال در سامانه صادرکننده', 'Active in the issuer registry'),
            'revoked' => array('باطل‌شده — در حال حاضر معتبر نیست', 'REVOKED — not currently valid'),
            'integrity' => array('یکپارچگی فراداده', 'Metadata integrity'),
            'signature_passed' => array('اعتبارسنجی امضا موفق بود', 'Signature check passed'),
            'signature_failed' => array('خطای اعتبارسنجی؛ پیش از اتکا به این رکورد بررسی بیشتر لازم است', 'CHECK FAILED — further review is required before relying on this record'),
            'lifecycle' => array('وضعیت چرخه عمر و تاریخچه', 'Lifecycle and audit history'),
            'consistent' => array('سازگار', 'Consistent'),
            'inconsistent' => array('ناسازگار — با مؤسسه صادرکننده تماس بگیرید', 'INCONSISTENT — contact the issuing institution'),
            'legacy_history' => array('تاریخچه برای این رکورد قدیمی در دسترس نیست', 'History is unavailable for this legacy record'),
            'artwork' => array('عنوان اثر', 'Artwork title'),
            'artist' => array('هنرمند', 'Artist'),
            'medium' => array('تکنیک و مواد', 'Medium / materials'),
            'year' => array('سال خلق', 'Year created'),
            'dimensions' => array('ابعاد', 'Dimensions'),
            'accession' => array('شماره ثبت', 'Accession number'),
            'issuer' => array('مؤسسه صادرکننده', 'Issuing institution'),
            'date' => array('تاریخ صدور (UTC)', 'Issue date (UTC)'),
            'collector' => array('مجموعه‌دار / مجموعه', 'Collector / collection'),
            'current_owner' => array('مالک فعلی', 'Current owner'),
            'previous_owner' => array('مالک پیشین', 'Previous owner'),
            'description' => array('توضیحات', 'Description'),
        );
        if (!isset($labels[$key])) {
            return $key;
        }
        if ($language === 'fa') {
            return $labels[$key][0];
        }
        if ($language === 'en') {
            return $labels[$key][1];
        }
        return $labels[$key][0] . ' / ' . $labels[$key][1];
    }

    private function render_public_field($key, $fa_label, $en_label, $primary, $english, $language) {
        $value = $this->localized_value($primary, $english, $language);
        $label = $this->public_label($key, $language);
        echo '<dt>' . esc_html($label !== $key ? $label : ($language === 'en' ? $en_label : ($language === 'both' ? $fa_label . ' / ' . $en_label : $fa_label))) . '</dt><dd>';
        if ($language === 'both' && (string) $primary !== '' && (string) $english !== '' && (string) $primary !== (string) $english) {
            echo esc_html((string) $primary) . '<span class="wp-coa-value-en" dir="ltr">' . esc_html((string) $english) . '</span>';
        } else {
            echo esc_html($value);
        }
        echo '</dd>';
    }

    /** Render linked artist/collector names as profile links only when the certificate signature validates. */
    private function render_public_linked_person_field($key, $fa_label, $en_label, $primary, $english, $language, $profile_type, $profile_id, $integrity_valid) {
        $label = $this->public_label($key, $language);
        echo '<dt>' . esc_html($label !== $key ? $label : ($language === 'en' ? $en_label : ($language === 'both' ? $fa_label . ' / ' . $en_label : $fa_label))) . '</dt><dd>';
        $profile = null;
        if ($integrity_valid && absint($profile_id) && class_exists('WP_COA_Pro_Person_Profiles')) {
            $candidate = WP_COA_Pro_Person_Profiles::get_profile(absint($profile_id), true);
            if ($candidate && $candidate->profile_type === $profile_type) { $profile = $candidate; }
        }
        $value = $this->localized_value($primary, $english, $language);
        if ($profile) {
            echo '<a class="wp-coa-person-inline-link" href="' . esc_url(WP_COA_Pro_Person_Profiles::profile_url($profile)) . '">';
        }
        if ($language === 'both' && (string) $primary !== '' && (string) $english !== '' && (string) $primary !== (string) $english) {
            echo esc_html((string) $primary) . '<span class="wp-coa-value-en" dir="ltr">' . esc_html((string) $english) . '</span>';
        } else { echo esc_html($value); }
        if ($profile) { echo '</a>'; }
        echo '</dd>';
    }

    public function register_rest_routes() {
        $coa_settings = WP_COA_Pro_Admin::get_settings();
        if (empty($coa_settings['enable_public_api'])) {
            return;
        }
        register_rest_route('wp-coa-pro/v1', '/certificates/(?P<code>[A-Za-z0-9\-]{1,64})', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_verify_certificate'),
            'permission_callback' => '__return_true',
            'args' => array(
                'code' => array(
                    'required' => true,
                    'sanitize_callback' => function ($value) { return strtoupper(sanitize_text_field($value)); },
                    'validate_callback' => function ($value) { return is_string($value) && preg_match('/^COA-[A-Z0-9]{4,12}-[A-Z0-9]{4,16}$/i', $value); },
                ),
            ),
        ));
    }

    public function rest_verify_certificate($request) {
        $code = strtoupper((string) $request->get_param('code'));
        $record = $this->repository->get_by_code($code);
        if (!$record) {
            return new WP_REST_Response(array('code' => 'coa_not_found', 'message' => __('Certificate not found.', 'wp-coa-pro')), 404);
        }
        $settings = WP_COA_Pro_Galleries::settings_for_record($record, WP_COA_Pro_Admin::get_settings());
        $artwork = array();
        if (!empty($settings['show_artwork_title'])) { $artwork['title'] = (string) $record->artwork_title; if (!empty($record->artwork_title_en)) { $artwork['title_en'] = (string) $record->artwork_title_en; } }
        if (!empty($settings['show_artist_name'])) { $artwork['artist'] = (string) $record->artist_name; if (!empty($record->artist_name_en)) { $artwork['artist_en'] = (string) $record->artist_name_en; } }
        if (!empty($settings['show_medium'])) { $artwork['medium'] = (string) $record->medium; if (!empty($record->medium_en)) { $artwork['medium_en'] = (string) $record->medium_en; } }
        if (!empty($settings['show_year_created'])) { $artwork['year_created'] = (string) $record->year_created; }
        if (!empty($settings['show_dimensions'])) { $artwork['dimensions'] = (string) $record->dimensions; }
        if (!empty($settings['show_accession_number'])) { $artwork['accession_number'] = (string) $record->accession_number; }
        if (!empty($settings['show_description'])) { $artwork['description'] = (string) $record->artwork_description; if (!empty($record->artwork_description_en)) { $artwork['description_en'] = (string) $record->artwork_description_en; } }
        if (!empty($settings['show_collector_public']) && (!empty($record->collector_name) || !empty($record->collector_name_en))) { $artwork['collector'] = (string) $record->collector_name; if (!empty($record->collector_name_en)) { $artwork['collector_en'] = (string) $record->collector_name_en; } }
        if (!empty($settings['show_current_owner_public']) && (!empty($record->current_owner_name) || !empty($record->current_owner_name_en))) { $artwork['current_owner'] = (string) $record->current_owner_name; if (!empty($record->current_owner_name_en)) { $artwork['current_owner_en'] = (string) $record->current_owner_name_en; } }
        if (!empty($settings['show_previous_owner_public']) && (!empty($record->previous_owner_name) || !empty($record->previous_owner_name_en))) { $artwork['previous_owner'] = (string) $record->previous_owner_name; if (!empty($record->previous_owner_name_en)) { $artwork['previous_owner_en'] = (string) $record->previous_owner_name_en; } }
        $response = array(
            'certificate_code' => (string) $record->certificate_code,
            'gallery_id' => isset($record->gallery_id) ? (int) $record->gallery_id : 0,
            'activity_type' => isset($record->activity_type) ? (string) $record->activity_type : 'gallery',
            'activity_label' => WP_COA_Pro_Galleries::activity_label(isset($record->activity_type) ? $record->activity_type : 'gallery', 'en'),
            'status' => (string) $record->status,
            'integrity_check' => $this->signature->verify($record) ? 'passed' : 'failed',
            'lifecycle_check' => $this->ledger->verify_certificate_state($record),
            'artwork' => $artwork,
            'disclaimer' => (string) $settings['public_disclaimer'],
            'disclaimer_en' => (string) $settings['public_disclaimer_en'],
        );
        if (!empty($settings['show_artwork_image_public']) && !empty($record->artwork_image_id)) {
            $image_url = wp_get_attachment_image_url((int) $record->artwork_image_id, 'large');
            if ($image_url) { $response['artwork']['image_url'] = esc_url_raw($image_url); }
            if (!empty($record->artwork_image_sha256)) { $response['artwork']['image_sha256'] = (string) $record->artwork_image_sha256; }
        }
        if (!empty($settings['show_issuer'])) { $response['issuer'] = (string) $record->issuer_name; if (!empty($record->issuer_name_en)) { $response['issuer_en'] = (string) $record->issuer_name_en; } }
        if (!empty($settings['show_issue_date'])) { $response['issue_date_utc'] = (string) $record->issue_date; }
        return new WP_REST_Response($response, 200);
    }

    private function render_admin_notice() {
        if (!isset($_GET['coa_notice'])) {
            return;
        }
        $notice = sanitize_key(wp_unslash($_GET['coa_notice']));
        $messages = array(
            'created' => array('success', __('Certificate issued successfully. The issue event was recorded.', 'wp-coa-pro')),
            'revoked' => array('success', __('Certificate revoked and the event recorded.', 'wp-coa-pro')),
            'ownership_transferred' => array('success', __('Ownership transferred; previous owner retained and the event was recorded.', 'wp-coa-pro')),
            'owner_transfer_failed' => array('error', __('Ownership transfer failed. Check that the certificate is active, the new owner is provided, and the audit event can be saved.', 'wp-coa-pro')),
            'missing_fields' => array('error', __('Artwork title and artist are required.', 'wp-coa-pro')),
            'create_failed' => array('error', __('Certificate creation failed. Check the PHP error log and database health.', 'wp-coa-pro')),
            'image_invalid' => array('error', 'تصویر انتخاب‌شده معتبر، در دسترس یا مجاز نیست؛ یک تصویر رستری از کتابخانه رسانه انتخاب کن.'),
            'gallery_profile_unavailable' => array('error', 'پروفایل گالری انتخاب‌شده غیرفعال یا نامعتبر است.'),
            'artist_profile_unavailable' => array('error', 'پروفایل هنرمند انتخاب‌شده منتشر نشده یا معتبر نیست.'),
            'collector_profile_unavailable' => array('error', 'پروفایل کلکتور یا مجموعه انتخاب‌شده منتشر نشده یا معتبر نیست.'),
            'revoke_failed' => array('error', __('Revocation failed. The certificate may already be revoked or the audit event could not be saved.', 'wp-coa-pro')),
        );
        if (!isset($messages[$notice])) {
            return;
        }
        echo '<div class="notice notice-' . esc_attr($messages[$notice][0]) . ' is-dismissible"><p>' . esc_html($messages[$notice][1]) . '</p>';
        if ($notice === 'created' && isset($_GET['coa_id'])) {
            $created_record = $this->repository->get_by_id(absint($_GET['coa_id']));
            if ($created_record) {
                $print_url = wp_nonce_url(add_query_arg(array('action' => 'wp_coa_print_certificate', 'id' => (int) $created_record->id), admin_url('admin-post.php')), 'wp_coa_print_certificate_' . (int) $created_record->id);
                echo '<p><strong>کد یکتای گواهی:</strong> <code>' . esc_html($created_record->certificate_code) . '</code> &nbsp; <a class="button button-primary" target="_blank" rel="noopener noreferrer" href="' . esc_url($print_url) . '">نمایش گواهی، تصویر و QR Code</a></p>';
            }
        }
        echo '</div>';
    }

    private function post_text($key, $max_chars) {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
            return '';
        }
        $value = sanitize_text_field(wp_unslash((string) $_POST[$key]));
        return $this->truncate_text($value, $max_chars);
    }

    private function post_textarea($key, $max_chars) {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
            return '';
        }
        $value = sanitize_textarea_field(wp_unslash((string) $_POST[$key]));
        return $this->truncate_text($value, $max_chars);
    }

    private function truncate_text($value, $max_chars) {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max_chars, 'UTF-8');
        }
        if (preg_match_all('/./us', $value, $matches)) {
            return implode('', array_slice($matches[0], 0, $max_chars));
        }
        return substr($value, 0, $max_chars);
    }

    private function csv_safe_cell($value) {
        // Prevent spreadsheet formula injection when opening the export in Excel/LibreOffice.
        if ($value !== '' && preg_match('/^[\s\x00-\x20]*[=+\-@]/u', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    private function print_dual_field($label, $primary, $english = '') {
        echo '<div class="coa-field"><label>' . esc_html($label) . '</label><div>' . esc_html((string) $primary);
        if ((string) $english !== '' && (string) $english !== (string) $primary) {
            echo '<div dir="ltr" class="aap-s-fee479">' . esc_html((string) $english) . '</div>';
        }
        echo '</div></div>';
    }

    private function print_field($label, $value) {
        ?><div class="coa-field"><label><?php echo esc_html($label); ?></label><div><?php echo esc_html($value); ?></div></div><?php
    }

    private function require_admin_permission() {
        if (!current_user_can('manage_coa_certificates')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'wp-coa-pro'), '', array('response' => 403));
        }
    }

    private function redirect_admin($args = array()) {
        $url = add_query_arg(array_merge(array('page' => 'wp-coa-pro'), $args), admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
