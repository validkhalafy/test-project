<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Administrative settings and WordPress dashboard integration. */
final class WP_COA_Pro_Admin {
    const OPTION_KEY = 'wp_coa_pro_settings';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_styles'));
        add_action('wp_dashboard_setup', array(__CLASS__, 'register_dashboard_widget'));
        add_action('init', array(__CLASS__, 'maybe_migrate_persian_default'), 1);
    }

    /** One-time transition from the previous shipped bilingual default to Persian-first display. */
    public static function maybe_migrate_persian_default() {
        if (get_option('wp_coa_pro_v17_persian_default_migrated')) {
            return;
        }
        $saved = get_option(self::OPTION_KEY, array());
        if (is_array($saved)) {
            $changed = false;
            if (isset($saved['display_language']) && $saved['display_language'] === 'both') {
                // Version 1.6.0 shipped 'both' as the default; Persian is now primary by request.
                // The administrator can still select English or Persian + English in Settings.
                $saved['display_language'] = 'fa';
                $changed = true;
            }

            // Translate only the exact stock English defaults from earlier releases; custom copy is preserved.
            $legacy_defaults = array(
                'certificate_footer' => array('Issued and managed using WP COA Pro.', 'صادرشده و مدیریت‌شده با WP COA Pro.'),
                'verification_title' => array('Verify an Artwork Certificate', 'استعلام گواهی اصالت اثر هنری'),
                'verification_description' => array('Enter the certificate code to check its current status and the integrity of the issuer record.', 'کد گواهی را وارد کنید تا وضعیت فعلی آن و یکپارچگی اطلاعات ثبت‌شده توسط صادرکننده بررسی شود.'),
                'verification_button' => array('Verify certificate', 'استعلام گواهی'),
                'public_disclaimer' => array('This lookup reports the status and integrity of the issuer’s database record. It does not independently establish the physical identity, provenance, or authenticity of an artwork. Contact the issuing institution for expert confirmation.', 'این استعلام وضعیت و یکپارچگی رکورد پایگاه داده صادرکننده را گزارش می‌کند؛ اما به‌تنهایی هویت فیزیکی، پیشینه یا اصالت اثر هنری را اثبات نمی‌کند. برای تأیید کارشناسی با مؤسسه صادرکننده تماس بگیرید.'),
            );
            foreach ($legacy_defaults as $key => $pair) {
                if (isset($saved[$key]) && is_string($saved[$key]) && $saved[$key] === $pair[0]) {
                    $saved[$key] = $pair[1];
                    $changed = true;
                }
            }
            if ($changed) {
                update_option(self::OPTION_KEY, $saved, false);
            }
        }
        update_option('wp_coa_pro_v17_persian_default_migrated', 1, false);
    }

    public static function defaults() {
        return array(
            'issuer_name' => get_bloginfo('name'),
            'issuer_name_en' => '',
            'logo_url' => '',
            'certificate_footer' => 'صادرشده و مدیریت‌شده با WP COA Pro.',
            'certificate_footer_en' => 'Issued and managed using WP COA Pro.',
            'primary_color' => '#65513b',
            'accent_color' => '#b29a68',
            'verification_title' => 'استعلام گواهی اصالت اثر هنری',
            'verification_title_en' => 'Verify an Artwork Certificate',
            'verification_description' => 'کد گواهی را وارد کنید تا وضعیت ثبت و یکپارچگی اطلاعات آن بررسی شود.',
            'verification_description_en' => 'Enter the certificate code to check its current status and the integrity of the issuer record.',
            'verification_placeholder' => 'COA-XXXXXXXX-XXXXXXXXXXXX',
            'verification_placeholder_en' => 'COA-XXXXXXXX-XXXXXXXXXXXX',
            'verification_button' => 'استعلام گواهی',
            'verification_button_en' => 'Verify certificate',
            'public_disclaimer' => 'این استعلام وضعیت و یکپارچگی رکورد پایگاه داده صادرکننده را گزارش می‌کند؛ اما به‌تنهایی هویت فیزیکی، پیشینه یا اصالت اثر هنری را اثبات نمی‌کند. برای تأیید کارشناسی با مؤسسه صادرکننده تماس بگیرید.',
            'public_disclaimer_en' => 'This lookup reports the status and integrity of the issuer’s database record. It does not independently establish the physical identity, provenance, or authenticity of an artwork. Contact the issuing institution for expert confirmation.',
            'display_language' => 'fa',
            'enable_language_switcher' => 1,
            'enable_public_lookup' => 1,
            'enable_public_api' => 1,
            'show_logo_public' => 1,
            'show_artwork_title' => 1,
            'show_artwork_image_public' => 1,
            'show_artist_name' => 1,
            'show_medium' => 1,
            'show_year_created' => 1,
            'show_dimensions' => 1,
            'show_accession_number' => 1,
            'show_issuer' => 1,
            'show_issue_date' => 1,
            'show_description' => 1,
            'show_collector_public' => 0,
            'show_current_owner_public' => 0,
            'show_previous_owner_public' => 0,
        );
    }

    public static function get_settings() {
        $saved = get_option(self::OPTION_KEY, array());
        if (!is_array($saved)) {
            $saved = array();
        }
        $settings = wp_parse_args($saved, self::defaults());
        if (!isset($settings['display_language']) || !in_array($settings['display_language'], array('both', 'fa', 'en', 'site'), true)) {
            $settings['display_language'] = 'fa';
        }
        foreach (array('primary_color', 'accent_color') as $key) {
            if (!is_string($settings[$key]) || !sanitize_hex_color($settings[$key])) {
                $settings[$key] = self::defaults()[$key];
            }
        }
        foreach (array('logo_url', 'issuer_name', 'issuer_name_en', 'certificate_footer', 'certificate_footer_en', 'verification_title', 'verification_title_en', 'verification_description', 'verification_description_en', 'verification_placeholder', 'verification_placeholder_en', 'verification_button', 'verification_button_en', 'public_disclaimer', 'public_disclaimer_en') as $key) {
            if (!is_scalar($settings[$key])) {
                $settings[$key] = self::defaults()[$key];
            } else {
                $settings[$key] = (string) $settings[$key];
            }
        }
        $settings['logo_url'] = esc_url_raw($settings['logo_url']);
        foreach (array('enable_public_lookup', 'enable_public_api', 'enable_language_switcher', 'show_logo_public', 'show_artwork_title', 'show_artwork_image_public', 'show_artist_name', 'show_medium', 'show_year_created', 'show_dimensions', 'show_accession_number', 'show_issuer', 'show_issue_date', 'show_description', 'show_collector_public', 'show_current_owner_public', 'show_previous_owner_public') as $key) {
            $settings[$key] = empty($settings[$key]) ? 0 : 1;
        }
        return $settings;
    }

    public static function register_menu() {
        add_submenu_page(
            'artiey-platform',
            __('WP COA Pro Settings', 'wp-coa-pro'),
            __('Settings', 'wp-coa-pro'),
            'manage_coa_certificates',
            'wp-coa-pro-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    public static function register_settings() {
        register_setting('wp_coa_pro_settings_group', self::OPTION_KEY, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
            'default' => self::defaults(),
            'show_in_rest' => false,
        ));

        add_settings_section('wp_coa_general', __('Institution and certificate defaults', 'wp-coa-pro'), array(__CLASS__, 'section_general'), 'wp-coa-pro-settings');
        self::add_field('issuer_name', __('Default issuing institution (Persian / primary)', 'wp-coa-pro'), 'text', 'wp_coa_general', __('Used as the default issuer when creating new certificates.', 'wp-coa-pro'));
        self::add_field('issuer_name_en', __('Default issuing institution (English)', 'wp-coa-pro'), 'text', 'wp_coa_general');
        self::add_field('logo_url', __('Institution logo URL', 'wp-coa-pro'), 'url', 'wp_coa_general', __('Optional HTTPS image URL. The logo is displayed on printed certificates and verification UI.', 'wp-coa-pro'));
        self::add_field('certificate_footer', __('Certificate footer (Persian / primary)', 'wp-coa-pro'), 'textarea', 'wp_coa_general', __('Short line displayed in the certificate footer.', 'wp-coa-pro'));
        self::add_field('certificate_footer_en', __('Certificate footer (English)', 'wp-coa-pro'), 'textarea', 'wp_coa_general');

        add_settings_section('wp_coa_branding', __('Brand identity', 'wp-coa-pro'), array(__CLASS__, 'section_branding'), 'wp-coa-pro-settings');
        self::add_field('primary_color', __('Primary color', 'wp-coa-pro'), 'color', 'wp_coa_branding', __('Main action and heading color.', 'wp-coa-pro'));
        self::add_field('accent_color', __('Accent color', 'wp-coa-pro'), 'color', 'wp_coa_branding', __('Certificate frame and highlight color.', 'wp-coa_branding'));

        add_settings_section('wp_coa_public', __('Public verification experience', 'wp-coa-pro'), array(__CLASS__, 'section_public'), 'wp-coa-pro-settings');
        self::add_field('display_language', __('Default public display language', 'wp-coa-pro'), 'select', 'wp_coa_public', __('Visitors can choose another language when the language switcher is enabled. Existing records remain unchanged.', 'wp-coa-pro'));
        self::add_field('enable_language_switcher', __('Allow visitors to change display language', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('verification_title', __('Verification heading (Persian / primary)', 'wp-coa-pro'), 'text', 'wp_coa_public');
        self::add_field('verification_title_en', __('Verification heading (English)', 'wp-coa-pro'), 'text', 'wp_coa_public');
        self::add_field('verification_description', __('Introduction text (Persian / primary)', 'wp-coa-pro'), 'textarea', 'wp_coa_public');
        self::add_field('verification_description_en', __('Introduction text (English)', 'wp-coa-pro'), 'textarea', 'wp_coa_public');
        self::add_field('verification_placeholder', __('Code field placeholder (Persian / primary)', 'wp-coa-pro'), 'text', 'wp_coa_public');
        self::add_field('verification_placeholder_en', __('Code field placeholder (English)', 'wp-coa-pro'), 'text', 'wp_coa_public');
        self::add_field('verification_button', __('Submit button label (Persian / primary)', 'wp-coa-pro'), 'text', 'wp_coa_public');
        self::add_field('verification_button_en', __('Submit button label (English)', 'wp-coa-pro'), 'text', 'wp_coa_public');
        self::add_field('public_disclaimer', __('Public disclaimer (Persian / primary)', 'wp-coa-pro'), 'textarea', 'wp_coa_public', __('Explain the limits of a digital record clearly; do not imply that a database check independently proves physical authenticity.', 'wp-coa-pro'));
        self::add_field('public_disclaimer_en', __('Public disclaimer (English)', 'wp-coa-pro'), 'textarea', 'wp_coa_public');
        self::add_field('enable_public_lookup', __('Enable public lookup', 'wp-coa-pro'), 'checkbox', 'wp_coa_public', __('When disabled, the shortcode displays a notice instead of exposing lookup results.', 'wp-coa-pro'));
        self::add_field('show_logo_public', __('Show institution logo on public lookup', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_artwork_title', __('Show artwork title in public results', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_artwork_image_public', __('Show original artwork image in public results', 'wp-coa-pro'), 'checkbox', 'wp_coa_public', __('The signed image remains part of the certificate record even when public display is disabled.', 'wp-coa-pro'));
        self::add_field('show_artist_name', __('Show artist name in public results', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_medium', __('Show medium / materials', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_year_created', __('Show year created', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_dimensions', __('Show dimensions', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_accession_number', __('Show inventory number', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_issuer', __('Show issuing institution', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_issue_date', __('Show issue date', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_description', __('Show public artwork description', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_collector_public', __('Show collector name publicly (privacy-sensitive)', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_current_owner_public', __('Show current owner publicly (privacy-sensitive)', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');
        self::add_field('show_previous_owner_public', __('Show previous owner publicly (privacy-sensitive)', 'wp-coa-pro'), 'checkbox', 'wp_coa_public');

        add_settings_section('wp_coa_integrations', __('API and integrations', 'wp-coa-pro'), array(__CLASS__, 'section_integrations'), 'wp-coa-pro-settings');
        self::add_field('enable_public_api', __('Enable read-only public REST API', 'wp-coa-pro'), 'checkbox', 'wp_coa_integrations', __('Controls the public GET endpoint /wp-json/wp-coa-pro/v1/certificates/{CODE}.', 'wp-coa-pro'));
    }

    private static function add_field($key, $label, $type, $section, $description = '') {
        add_settings_field($key, $label, array(__CLASS__, 'render_field'), 'wp-coa-pro-settings', $section, array(
            'key' => $key,
            'type' => $type,
            'description' => $description,
        ));
    }

    public static function section_general() { echo '<p>' . esc_html__('Set the default institutional identity used by this site.', 'wp-coa-pro') . '</p>'; }
    public static function section_branding() { echo '<p>' . esc_html__('These colors are used by the public verification form, Elementor widget, and printed certificate.', 'wp-coa-pro') . '</p>'; }
    public static function section_public() { echo '<p>' . esc_html__('Control the public lookup form and which certificate fields are shown. Internal notes and private revocation reasons are never displayed here.', 'wp-coa-pro') . '</p>'; }
    public static function section_integrations() { echo '<p>' . esc_html__('Only enable public endpoints that your website needs. The API is read-only and returns public certificate metadata.', 'wp-coa-pro') . '</p>'; }

    public static function render_field($args) {
        $settings = self::get_settings();
        $key = $args['key'];
        $type = $args['type'];
        $value = isset($settings[$key]) ? $settings[$key] : '';
        $name = self::OPTION_KEY . '[' . $key . ']';
        $id = 'wp-coa-' . esc_attr($key);
        if ($type === 'select' && $key === 'display_language') {
            echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">';
            foreach (array('both' => __('Persian and English', 'wp-coa-pro'), 'fa' => __('Persian only', 'wp-coa-pro'), 'en' => __('English only', 'wp-coa-pro'), 'site' => __('Follow WordPress site locale', 'wp-coa-pro')) as $option_value => $option_label) {
                echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'checkbox') {
            printf('<label for="%1$s"><input id="%1$s" type="checkbox" name="%2$s" value="1" %3$s /> %4$s</label>', esc_attr($id), esc_attr($name), checked((int) $value, 1, false), esc_html__('Enabled', 'wp-coa-pro'));
        } elseif ($key === 'logo_url') {
            printf('<div class="wp-coa-logo-field"><input class="regular-text" id="%1$s" type="url" name="%2$s" value="%3$s" maxlength="2048" /><button type="button" class="button wp-coa-select-logo" data-target="%1$s">%4$s</button><button type="button" class="button-link-delete wp-coa-remove-logo" data-target="%1$s">%5$s</button></div><div class="wp-coa-logo-preview" data-preview-for="%1$s">%6$s</div>', esc_attr($id), esc_attr($name), esc_url($value), esc_html__('Choose image', 'wp-coa-pro'), esc_html__('Remove', 'wp-coa-pro'), $value ? '<img src="' . esc_url($value) . '" alt="" class="aap-s-769bba">' : '');
        } elseif ($type === 'textarea') {
            printf('<textarea class="large-text" rows="3" id="%1$s" name="%2$s">%3$s</textarea>', esc_attr($id), esc_attr($name), esc_textarea($value));
        } elseif ($type === 'color') {
            printf('<input class="regular-text aap-s-7a7b0e" id="%1$s" type="color" name="%2$s" value="%3$s" /> <code>%3$s</code>', esc_attr($id), esc_attr($name), esc_attr(sanitize_hex_color($value) ? $value : '#65513b'));
        } else {
            printf('<input class="regular-text" id="%1$s" type="%2$s" name="%3$s" value="%4$s" maxlength="%5$d" />', esc_attr($id), $type === 'url' ? 'url' : 'text', esc_attr($name), esc_attr($value), $type === 'url' ? 2048 : 500);
        }
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public static function sanitize_settings($input) {
        $defaults = self::defaults();
        if (!is_array($input)) {
            return $defaults;
        }
        $output = array();
        $text_keys = array('issuer_name', 'issuer_name_en', 'verification_title', 'verification_title_en', 'verification_placeholder', 'verification_placeholder_en', 'verification_button', 'verification_button_en');
        foreach ($text_keys as $key) {
            $value = isset($input[$key]) && is_scalar($input[$key]) ? sanitize_text_field(wp_unslash((string) $input[$key])) : '';
            $output[$key] = \Artiey_AET_Text::substr($value, 0, 500);
        }
        $textarea_keys = array('certificate_footer', 'certificate_footer_en', 'verification_description', 'verification_description_en', 'public_disclaimer', 'public_disclaimer_en');
        foreach ($textarea_keys as $key) {
            $value = isset($input[$key]) && is_scalar($input[$key]) ? sanitize_textarea_field(wp_unslash((string) $input[$key])) : '';
            $output[$key] = \Artiey_AET_Text::substr($value, 0, 3000);
        }
        $output['logo_url'] = isset($input['logo_url']) && is_scalar($input['logo_url']) ? esc_url_raw(trim(wp_unslash((string) $input['logo_url']))) : '';
        $output['display_language'] = isset($input['display_language']) && in_array($input['display_language'], array('both', 'fa', 'en', 'site'), true) ? $input['display_language'] : $defaults['display_language'];
        foreach (array('primary_color', 'accent_color') as $key) {
            $color = isset($input[$key]) && is_scalar($input[$key]) ? sanitize_hex_color(wp_unslash((string) $input[$key])) : false;
            $output[$key] = $color ? $color : $defaults[$key];
        }
        $checkboxes = array('enable_public_lookup', 'enable_public_api', 'enable_language_switcher', 'show_logo_public', 'show_artwork_title', 'show_artwork_image_public', 'show_artist_name', 'show_medium', 'show_year_created', 'show_dimensions', 'show_accession_number', 'show_issuer', 'show_issue_date', 'show_description', 'show_collector_public', 'show_current_owner_public', 'show_previous_owner_public');
        foreach ($checkboxes as $key) {
            $output[$key] = isset($input[$key]) && (string) $input[$key] === '1' ? 1 : 0;
        }
        if ($output['issuer_name'] === '') {
            $output['issuer_name'] = get_bloginfo('name');
        }
        if ($output['verification_title'] === '') {
            $output['verification_title'] = $defaults['verification_title'];
        }
        if ($output['verification_placeholder'] === '') {
            $output['verification_placeholder'] = $defaults['verification_placeholder'];
        }
        if ($output['verification_button'] === '') {
            $output['verification_button'] = $defaults['verification_button'];
        }
        return $output;
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_coa_certificates')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'wp-coa-pro'), '', array('response' => 403));
        }
        $settings = self::get_settings();
        ?>
        <div class="wrap wp-coa-settings-wrap">
            <h1><?php esc_html_e('WP COA Pro — Professional Settings', 'wp-coa-pro'); ?></h1>
            <p class="wp-coa-settings-intro"><?php esc_html_e('Configure institutional identity, certificate styling, public lookup visibility, and API access. Changes apply to the public verification form and newly rendered certificates.', 'wp-coa-pro'); ?></p>
            <?php settings_errors(); ?>
            <div class="wp-coa-settings-layout">
                <main class="wp-coa-settings-main">
                    <form method="post" action="options.php">
                        <?php settings_fields('wp_coa_pro_settings_group'); ?>
                        <?php do_settings_sections('wp-coa-pro-settings'); ?>
                        <?php submit_button(__('Save COA settings', 'wp-coa-pro')); ?>
                    </form>
                </main>
                <aside class="wp-coa-settings-aside">
                    <h2><?php esc_html_e('Quick integration', 'wp-coa-pro'); ?></h2>
                    <p><?php esc_html_e('Add the verification form to any page using the shortcode:', 'wp-coa-pro'); ?></p>
                    <p><code>[coa_verify]</code></p>
                    <p><?php esc_html_e('Elementor users can search for “WP COA Certificate Verification” in the widget panel.', 'wp-coa-pro'); ?></p>
                    <hr>
                    <h3><?php esc_html_e('Public API endpoint', 'wp-coa-pro'); ?></h3>
                    <code class="wp-coa-api-code"><?php echo esc_html(rest_url('wp-coa-pro/v1/certificates/COA-XXXXXXXX-XXXXXXXXXXXX')); ?></code>
                    <p class="description"><?php esc_html_e('The API exposes only public certificate metadata. Do not put secrets or private notes in public fields.', 'wp-coa-pro'); ?></p>
                    <hr>
                    <h3><?php esc_html_e('Current brand preview', 'wp-coa-pro'); ?></h3>
                    <div class="wp-coa-brand-preview" style="--coa-primary:<?php echo esc_attr($settings['primary_color']); ?>;--coa-accent:<?php echo esc_attr($settings['accent_color']); ?>">
                        <?php if ($settings['logo_url'] !== '') : ?><img src="<?php echo esc_url($settings['logo_url']); ?>" alt="" /><?php endif; ?>
                        <strong><?php echo esc_html($settings['issuer_name']); ?></strong>
                        <span><?php esc_html_e('Certificate verification', 'wp-coa-pro'); ?></span>
                        <span class="wp-coa-preview-button"><?php echo esc_html($settings['verification_button']); ?></span>
                    </div>
                </aside>
            </div>
        </div>
        <?php
    }

    public static function register_dashboard_widget() {
        if (current_user_can('manage_coa_certificates')) {
            wp_add_dashboard_widget('wp_coa_pro_dashboard_widget', __('WP COA Pro — Registry Overview', 'wp-coa-pro'), array(__CLASS__, 'render_dashboard_widget'));
        }
    }

    public static function render_dashboard_widget() {
        if (!current_user_can('manage_coa_certificates')) {
            return;
        }
        global $wpdb;
        $cert_table = $wpdb->prefix . 'coa_certificates';
        $event_table = $wpdb->prefix . 'coa_events';
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($cert_table))) === $cert_table;
        if (!$table_exists) {
            echo '<p>' . esc_html__('The certificate database is not initialized yet. Activate or reactivate the plugin, then reload this page.', 'wp-coa-pro') . '</p>';
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=wp-coa-pro')) . '">' . esc_html__('Open COA management', 'wp-coa-pro') . '</a></p>';
            return;
        }
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$cert_table}");
        $active = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$cert_table} WHERE status = %s", 'active'));
        $revoked = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$cert_table} WHERE status = %s", 'revoked'));
        $since_30_days = gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));
        $issued_30_days = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$cert_table} WHERE created_at >= %s", $since_30_days));
        $recent = $wpdb->get_results("SELECT id, certificate_code, artwork_title, artist_name, status, issue_date FROM {$cert_table} ORDER BY id DESC LIMIT 5");
        $integrity_health = get_transient('wp_coa_pro_dashboard_integrity_health');
        if (!is_array($integrity_health)) {
            $integrity_health = array('checked' => 0, 'failed' => 0);
            $integrity_rows = $wpdb->get_results("SELECT * FROM {$cert_table} ORDER BY id DESC LIMIT 200");
            if (is_array($integrity_rows)) {
                $signature = new WP_COA_Pro_Signature();
                foreach ($integrity_rows as $integrity_row) {
                    $integrity_health['checked']++;
                    if (!$signature->verify($integrity_row)) {
                        $integrity_health['failed']++;
                    }
                }
            }
            set_transient('wp_coa_pro_dashboard_integrity_health', $integrity_health, 5 * MINUTE_IN_SECONDS);
        }
        $event_count = 0;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($event_table))) === $event_table) {
            $event_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$event_table}");
        }
        ?>
        <div class="wp-coa-dashboard-widget">
            <div class="wp-coa-dashboard-metrics">
                <div class="wp-coa-metric"><span><?php esc_html_e('Total certificates', 'wp-coa-pro'); ?></span><strong><?php echo esc_html(number_format_i18n($total)); ?></strong></div>
                <div class="wp-coa-metric is-active"><span><?php esc_html_e('Active', 'wp-coa-pro'); ?></span><strong><?php echo esc_html(number_format_i18n($active)); ?></strong></div>
                <div class="wp-coa-metric is-revoked"><span><?php esc_html_e('Revoked', 'wp-coa-pro'); ?></span><strong><?php echo esc_html(number_format_i18n($revoked)); ?></strong></div>
                <div class="wp-coa-metric"><span><?php esc_html_e('Audit events', 'wp-coa-pro'); ?></span><strong><?php echo esc_html(number_format_i18n($event_count)); ?></strong></div>
                <div class="wp-coa-metric"><span><?php esc_html_e('Issued in last 30 days', 'wp-coa-pro'); ?></span><strong><?php echo esc_html(number_format_i18n($issued_30_days)); ?></strong></div>
                <div class="wp-coa-metric <?php echo $integrity_health['failed'] > 0 ? 'is-revoked' : 'is-active'; ?>"><span><?php esc_html_e('Recent integrity failures', 'wp-coa-pro'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $integrity_health['failed'])); ?></strong></div>
            </div>
            <p class="description"><?php printf(esc_html__('Integrity scan: %1$s of the latest 200 certificates inspected. This summary is cached for five minutes and is not a complete forensic audit.', 'wp-coa-pro'), esc_html(number_format_i18n((int) $integrity_health['checked']))); ?></p>
            <h3><?php esc_html_e('Recently issued certificates', 'wp-coa-pro'); ?></h3>
            <?php if ($recent) : ?>
                <table class="widefat striped"><thead><tr><th><?php esc_html_e('Certificate', 'wp-coa-pro'); ?></th><th><?php esc_html_e('Artwork / artist', 'wp-coa-pro'); ?></th><th><?php esc_html_e('Status', 'wp-coa-pro'); ?></th></tr></thead><tbody>
                <?php foreach ($recent as $row) : ?>
                    <tr><td><code><?php echo esc_html($row->certificate_code); ?></code><br><small><?php echo esc_html($row->issue_date); ?></small></td><td><?php echo esc_html($row->artwork_title); ?><br><span class="description"><?php echo esc_html($row->artist_name); ?></span></td><td><span class="wp-coa-status-pill <?php echo $row->status === 'active' ? 'is-active' : 'is-revoked'; ?>"><?php echo esc_html($row->status === 'active' ? __('Active', 'wp-coa-pro') : __('Revoked', 'wp-coa-pro')); ?></span></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php else : ?>
                <p><?php esc_html_e('No certificates have been issued yet.', 'wp-coa-pro'); ?></p>
            <?php endif; ?>
            <p class="wp-coa-dashboard-actions"><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=wp-coa-pro')); ?>"><?php esc_html_e('Manage certificates', 'wp-coa-pro'); ?></a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wp-coa-pro-settings')); ?>"><?php esc_html_e('Settings', 'wp-coa-pro'); ?></a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wp-coa-pro-events')); ?>"><?php esc_html_e('Audit history', 'wp-coa-pro'); ?></a></p>
            <p class="description"><?php esc_html_e('Record integrity checks confirm consistency of signed metadata; they are not independent proof of the physical artwork’s authenticity.', 'wp-coa-pro'); ?></p>
        </div>
        <?php
    }

    public static function enqueue_admin_styles($hook) {
        if (strpos((string) $hook, 'wp-coa-pro') === false && $hook !== 'index.php') {
            return;
        }
        wp_register_style('wp-coa-pro-admin', false, array(), defined('WP_COA_PRO_VERSION') ? WP_COA_PRO_VERSION : null);
        wp_enqueue_style('wp-coa-pro-admin');
        if (strpos((string) $hook, 'wp-coa-pro-settings') !== false) {
            wp_enqueue_media();
            wp_enqueue_script('jquery');
            $media_js = <<<'JS'
jQuery(function($) {
    'use strict';
    function previewFor(target) {
        return $('.wp-coa-logo-preview').filter(function() { return $(this).data('preview-for') === target; }).first();
    }
    function status($preview, message, isError) {
        var $status = $preview.find('.wp-coa-logo-media-status').first();
        if (!$status.length) { $status = $('<p class="wp-coa-logo-media-status description" role="status" aria-live="polite"></p>').appendTo($preview); }
        $status.text(message || '').toggleClass('is-error', !!isError);
    }
    function drawPreview($preview, url) {
        $preview.find('img').remove();
        if (url && /^https?:\/\//i.test(url)) {
            $('<img>', {src: url, alt: '', loading: 'lazy'}).css({maxWidth: '180px', maxHeight: '80px', marginTop: '8px', objectFit: 'contain'}).prependTo($preview);
        }
    }
    $(document).on('click', '.wp-coa-select-logo', function(e) {
        e.preventDefault();
        var $button = $(this);
        var target = String($button.data('target') || '');
        var input = document.getElementById(target);
        var $preview = previewFor(target);
        if (!window.wp || !wp.media) { status($preview, 'کتابخانه رسانه در دسترس نیست؛ صفحه را تازه‌سازی کنید.', true); return; }
        if (!input) { status($preview, 'فیلد لوگو پیدا نشد؛ صفحه را تازه‌سازی کنید.', true); return; }
        var frame = $button.data('wpCoaLogoFrame');
        if (!frame) {
            frame = wp.media({title: 'Choose institution logo', button: {text: 'Use this image'}, multiple: false, library: {type: 'image'}});
            frame.on('select', function() {
                var selection = frame.state().get('selection');
                var model = selection && selection.first ? selection.first() : null;
                var item = model && model.toJSON ? model.toJSON() : null;
                if (!item || !item.url || (item.mime && !/^image\//i.test(item.mime))) { status($preview, 'لطفاً یک فایل تصویری معتبر انتخاب کنید.', true); return; }
                $(input).val(item.url).trigger('change');
                status($preview, '', false);
            });
            $button.data('wpCoaLogoFrame', frame);
        }
        status($preview, '', false);
        frame.open();
    });
    $(document).on('input change', '.wp-coa-logo-field input[type="url"]', function() {
        var target = this.id;
        drawPreview(previewFor(target), String($(this).val() || '').trim());
    });
    $(document).on('click', '.wp-coa-remove-logo', function(e) {
        e.preventDefault();
        var target = String($(this).data('target') || '');
        var input = document.getElementById(target);
        var $preview = previewFor(target);
        if (input) { $(input).val('').trigger('input').trigger('change'); }
        $preview.find('img').remove();
        status($preview, '', false);
    });
});
JS;
            wp_add_inline_script('jquery', $media_js);
        }
        $css = '
        .wp-coa-settings-intro{max-width:900px;color:#50575e;font-size:14px}.wp-coa-settings-layout{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:24px;align-items:start;max-width:1320px;margin-top:22px}.wp-coa-settings-main{background:#fff;border:1px solid #dcdcde;padding:20px 24px;min-width:0}.wp-coa-settings-main h2{margin-top:24px;padding-bottom:10px;border-bottom:1px solid #eee}.wp-coa-settings-main h2:first-child{margin-top:0}.wp-coa-settings-aside{background:#fff;border:1px solid #dcdcde;padding:20px;overflow-wrap:anywhere}.wp-coa-logo-field{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.wp-coa-logo-field input{max-width:100%}.wp-coa-logo-preview img{display:block}.wp-coa-settings-aside h2{margin-top:0}.wp-coa-api-code{display:block;white-space:normal;overflow-wrap:anywhere}.wp-coa-brand-preview{display:flex;flex-direction:column;gap:8px;border-top:4px solid var(--coa-accent);padding:18px;background:#faf9f6;color:#27231e}.wp-coa-brand-preview img{max-width:100%;max-height:64px;object-fit:contain;object-position:left}.wp-coa-brand-preview strong{font-size:18px}.wp-coa-brand-preview .wp-coa-preview-button{align-self:flex-start;padding:8px 12px;background:var(--coa-primary);color:#fff;border-radius:4px}.wp-coa-dashboard-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:18px}.wp-coa-metric{padding:12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:5px;min-width:0}.wp-coa-metric span{display:block;font-size:11px;color:#50575e;line-height:1.4}.wp-coa-metric strong{display:block;font-size:24px;margin-top:5px;color:#1d2327}.wp-coa-metric.is-active{border-top:3px solid #16834a}.wp-coa-metric.is-revoked{border-top:3px solid #b32d2e}.wp-coa-status-pill{display:inline-block;border-radius:20px;padding:3px 8px;font-size:11px;font-weight:600}.wp-coa-status-pill.is-active{background:#edfaef;color:#116329}.wp-coa-status-pill.is-revoked{background:#fcf0f1;color:#8a2424}.wp-coa-dashboard-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:14px}.wp-coa-dashboard-widget .widefat td{vertical-align:middle}.wp-coa-dashboard-widget code{font-size:11px}@media(max-width:1100px){.wp-coa-settings-layout{grid-template-columns:1fr}.wp-coa-settings-aside{max-width:none}}@media(max-width:782px){.wp-coa-dashboard-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.wp-coa-settings-main{padding:15px}}
        ';
        wp_add_inline_style('wp-coa-pro-admin', $css);
    }
}
