<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Role-aware member registration and self-service profile editing.
 *
 * - One schema describes every role (artist, gallery, curator, collector, buyer); the register form, the
 *   profile editor, validation and the entity mapping are all generated from it.
 * - Fields are namespaced as f[role][field] and the inactive role panels are <fieldset disabled>, so two roles
 *   can share a field name without one overwriting the other.
 * - The role, entity and ownership always come from the server (user meta + entity.user_id), never from a
 *   request value, and members can never pick an administrative role.
 */
final class MemberForms
{
    public const OPTION        = 'art_coa_member_form';
    public const NONCE_REG     = 'art_coa_register_v2';
    public const NONCE_PROFILE = 'art_coa_member_profile';
    public const NONCE_RESEND  = 'art_coa_resend_confirm';
    public const CRON_HOOK     = 'art_coa_purge_unverified';
    public const TOKEN_TTL     = 172800; // 48 h
    public const PENDING_TTL   = 604800; // unverified accounts are removed after 7 days

    /** @var array<string,string> request-local state shared between the init handler and the shortcode */
    private static array $errors = [];
    /** @var array<string,mixed> */
    private static array $old = [];
    private static string $notice = '';
    private static string $notice_type = 'info';
    private static bool $assets_printed = false;
    /** @var array<string,array<string,mixed>>|null per-request caches (the schema runs a filter and builds ~60 arrays) */
    private static ?array $schema_cache = null;
    private static ?array $settings_cache = null;
    private const PROOF_COOKIE = 'art_coa_reg_proof';

    /** Drop per-request caches (also called when the settings option changes). */
    public static function flush_cache(): void
    {
        self::$schema_cache = null;
        self::$settings_cache = null;
    }

    /* ================================================================ */
    /* Bootstrap                                                         */
    /* ================================================================ */

    public static function init(): void
    {
        add_action('update_option_' . self::OPTION, [self::class, 'flush_cache']);
        add_action('init', [self::class, 'handle_register'], 10);
        add_action('init', [self::class, 'handle_resend'], 10);
        add_action('init', [self::class, 'handle_profile_save'], 25);
        add_action('init', [self::class, 'schedule_cleanup'], 30);
        add_action('template_redirect', [self::class, 'handle_confirm'], 1);
        add_action(self::CRON_HOOK, [self::class, 'purge_unverified']);
        add_filter('authenticate', [self::class, 'block_unverified_login'], 40, 3);
        add_filter('show_admin_bar', [self::class, 'maybe_hide_admin_bar']);
        add_action('admin_init', [self::class, 'keep_members_out_of_admin']);
        add_filter('wp_robots', [self::class, 'robots']);
        add_action('wp_head', [self::class, 'head_meta'], 4);
        add_shortcode('art_coa_member_profile', [self::class, 'render_profile_shortcode']);
        add_action('admin_post_art_coa_member_form_save', [MemberFormsAdmin::class, 'save']);
        add_action('admin_post_art_coa_set_person_status', [MemberFormsAdmin::class, 'set_status']);
        add_action('admin_menu', [MemberFormsAdmin::class, 'menu'], 23);
    }

    /** Persian or English text depending on the plugin language. */
    public static function t(string $fa, string $en): string
    {
        return I18n::is_fa() ? $fa : $en;
    }

    /* ================================================================ */
    /* Schema                                                            */
    /* ================================================================ */

    /** @param array<string,mixed> $o */
    private static function f(string $name, string $fa, string $en, string $type = 'text', array $o = []): array
    {
        return array_merge([
            'name' => $name, 'label' => [$fa, $en], 'type' => $type, 'required' => false, 'locked' => false,
            'max' => 200, 'help' => ['', ''], 'autocomplete' => 'off', 'recommended' => false,
        ], $o);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function schema(): array
    {
        if (self::$schema_cache !== null) {
            return self::$schema_cache;
        }
        $f = [self::class, 'f'];
        $roles = [
            'artist' => [
                'wp_role' => 'art_artist', 'icon' => '🎨', 'public' => true,
                'label' => ['هنرمند', 'Artist'],
                'desc'  => ['نمایش آثار، ثبت سوابق و دریافت گواهی اصالت', 'Showcase your work, record your career and request certificates'],
                'fields' => [
                    $f('first_name', 'نام', 'First name', 'text', ['required' => true, 'locked' => true, 'autocomplete' => 'given-name', 'max' => 80]),
                    $f('last_name', 'نام خانوادگی', 'Last name', 'text', ['required' => true, 'locked' => true, 'autocomplete' => 'family-name', 'max' => 80]),
                    $f('display_name', 'نام نمایشی', 'Display name', 'text', ['max' => 120, 'help' => ['اگر خالی بگذارید نام و نام خانوادگی نمایش داده می‌شود.', 'Defaults to your full name.']]),
                    $f('phone', 'تلفن', 'Phone', 'tel', ['autocomplete' => 'tel', 'max' => 20]),
                    $f('city', 'شهر', 'City', 'text', ['autocomplete' => 'address-level2', 'max' => 100, 'recommended' => true]),
                    $f('birth_year', 'سال تولد', 'Birth year', 'year', ['max' => 4]),
                    $f('mediums', 'رسانه‌ها و تکنیک‌ها', 'Mediums', 'tags', ['max' => 300, 'help' => ['با ویرگول جدا کنید؛ مثل رنگ روغن، عکاسی', 'Comma separated']]),
                    $f('short_bio', 'معرفی کوتاه', 'Short bio', 'textarea', ['max' => 500, 'recommended' => true, 'help' => ['حداکثر ۵۰۰ نویسه؛ در صفحهٔ شما و نتایج جست‌وجو دیده می‌شود.', 'Up to 500 characters; shown on your page and in search results.']]),
                    $f('website', 'وب‌سایت', 'Website', 'url', ['autocomplete' => 'url', 'max' => 300]),
                    $f('instagram', 'اینستاگرام', 'Instagram', 'social', ['max' => 60, 'help' => ['فقط نام کاربری، بدون @', 'Username only']]),
                ],
            ],
            'gallery' => [
                'wp_role' => 'art_gallery', 'icon' => '🏛', 'public' => true,
                'label' => ['گالری‌دار', 'Gallery'],
                'desc'  => ['معرفی گالری، ثبت نمایشگاه و مدیریت آثار', 'Present your gallery, publish exhibitions and manage works'],
                'fields' => [
                    $f('gallery_name', 'نام گالری', 'Gallery name', 'text', ['required' => true, 'locked' => true, 'autocomplete' => 'organization', 'max' => 150]),
                    $f('city', 'شهر', 'City', 'text', ['required' => true, 'autocomplete' => 'address-level2', 'max' => 100]),
                    $f('country', 'کشور', 'Country', 'text', ['autocomplete' => 'country-name', 'max' => 80]),
                    $f('address', 'نشانی', 'Address', 'textarea', ['autocomplete' => 'street-address', 'max' => 300, 'recommended' => true]),
                    $f('phone', 'تلفن', 'Phone', 'tel', ['autocomplete' => 'tel', 'max' => 20, 'recommended' => true]),
                    $f('website', 'وب‌سایت', 'Website', 'url', ['autocomplete' => 'url', 'max' => 300]),
                    $f('about', 'دربارهٔ گالری', 'About', 'textarea', ['max' => 2000, 'recommended' => true]),
                ],
            ],
            'curator' => [
                'wp_role' => 'art_curator', 'icon' => '🧭', 'public' => true,
                'label' => ['کیوریتور', 'Curator'],
                'desc'  => ['معرفی سوابق کیوریتوری و همکاری با نمایشگاه‌ها', 'Present your curatorial work and join exhibitions'],
                'fields' => [
                    $f('display_name', 'نام و نام خانوادگی', 'Full name', 'text', ['required' => true, 'locked' => true, 'autocomplete' => 'name', 'max' => 120]),
                    $f('affiliation', 'وابستگی سازمانی', 'Affiliation', 'text', ['autocomplete' => 'organization', 'max' => 150]),
                    $f('specialties', 'حوزه‌های تخصصی', 'Specialties', 'textarea', ['max' => 500, 'recommended' => true]),
                    $f('years_experience', 'سال‌های تجربه', 'Years of experience', 'number', ['max' => 2, 'num_max' => 80]),
                    $f('phone', 'تلفن', 'Phone', 'tel', ['autocomplete' => 'tel', 'max' => 20]),
                    $f('website', 'وب‌سایت', 'Website', 'url', ['autocomplete' => 'url', 'max' => 300]),
                    $f('instagram_url', 'نشانی اینستاگرام', 'Instagram URL', 'url', ['max' => 300]),
                    $f('linkedin_url', 'نشانی لینکدین', 'LinkedIn URL', 'url', ['max' => 300]),
                    $f('bio', 'بیوگرافی', 'Biography', 'textarea', ['max' => 3000, 'recommended' => true]),
                ],
            ],
            'collector' => [
                'wp_role' => 'art_collector', 'icon' => '🗂', 'public' => true,
                'label' => ['مجموعه‌دار', 'Collector'],
                'desc'  => ['ثبت مجموعهٔ شخصی، پیگیری گواهی‌ها و بازار ثانویه', 'Record your collection, follow certificates and the secondary market'],
                'fields' => [
                    $f('display_name', 'نام و نام خانوادگی', 'Full name', 'text', ['required' => true, 'locked' => true, 'autocomplete' => 'name', 'max' => 120]),
                    $f('collection_name', 'نام مجموعه', 'Collection name', 'text', ['max' => 150, 'recommended' => true]),
                    $f('bio', 'دربارهٔ مجموعه', 'About the collection', 'textarea', ['max' => 2000]),
                    $f('is_public', 'پروفایل من عمومی باشد', 'Make my profile public', 'checkbox', ['help' => ['در غیر این صورت فقط خودتان آن را می‌بینید.', 'Otherwise only you can see it.']]),
                ],
            ],
            'buyer' => [
                'wp_role' => 'art_buyer', 'icon' => '🛒', 'public' => false,
                'label' => ['خریدار', 'Buyer'],
                'desc'  => ['خرید آثار، پیگیری سفارش و دریافت گواهی', 'Buy works, follow orders and receive certificates'],
                'fields' => [
                    $f('display_name', 'نام و نام خانوادگی', 'Full name', 'text', ['required' => true, 'locked' => true, 'autocomplete' => 'name', 'max' => 120]),
                    $f('phone', 'تلفن', 'Phone', 'tel', ['autocomplete' => 'tel', 'max' => 20]),
                ],
            ],
        ];
        /** Add or adjust roles/fields. Entries need the same shape as the built-in ones and an entity handler. */
        return self::$schema_cache = (array) apply_filters('artiey_aet_member_roles', $roles);
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'moderation'    => 1,   // new public profiles wait for approval
            'verify_email'  => 1,
            'notify_admin'  => 1,
            'min_password'  => 10,
            'terms_page'    => 0,
            'roles'         => [],  // role => ['enabled'=>0|1,'label'=>'','desc'=>'']
            'fields'        => [],  // role => field => ['enabled'=>0|1,'required'=>0|1,'label'=>'']
            'custom_fields' => [],  // role => validated administrator-defined field definitions
        ];
    }

    /** @return array<string,mixed> */
    public static function settings(): array
    {
        if (self::$settings_cache !== null) {
            return self::$settings_cache;
        }
        $saved = get_option(self::OPTION, []);
        return self::$settings_cache = self::sanitize_settings(is_array($saved) ? $saved : []);
    }

    /**
     * Whitelist-validate settings. Locked fields can never be disabled or made optional, and unknown roles
     * or fields are dropped.
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    public static function sanitize_settings(array $raw): array
    {
        $out = self::defaults();
        foreach (['moderation', 'verify_email', 'notify_admin'] as $flag) {
            if (array_key_exists($flag, $raw)) {
                $out[$flag] = empty($raw[$flag]) ? 0 : 1;
            }
        }
        if (isset($raw['min_password']) && is_numeric($raw['min_password'])) {
            $out['min_password'] = max(8, min(64, (int) $raw['min_password']));
        }
        $out['terms_page'] = isset($raw['terms_page']) ? absint($raw['terms_page']) : 0;

        $schema = self::schema();
        foreach ($schema as $role => $def) {
            $r = isset($raw['roles'][$role]) && is_array($raw['roles'][$role]) ? $raw['roles'][$role] : [];
            $out['roles'][$role] = [
                'enabled' => array_key_exists('enabled', $r) ? (empty($r['enabled']) ? 0 : 1) : 1,
                'label'   => isset($r['label']) && is_scalar($r['label']) ? sanitize_text_field((string) $r['label']) : '',
                'desc'    => isset($r['desc']) && is_scalar($r['desc']) ? sanitize_text_field((string) $r['desc']) : '',
            ];
            foreach ($def['fields'] as $field) {
                $name = $field['name'];
                $fr   = isset($raw['fields'][$role][$name]) && is_array($raw['fields'][$role][$name]) ? $raw['fields'][$role][$name] : [];
                $locked = !empty($field['locked']);
                $out['fields'][$role][$name] = [
                    'enabled'  => $locked ? 1 : (array_key_exists('enabled', $fr) ? (empty($fr['enabled']) ? 0 : 1) : 1),
                    'required' => $locked ? 1 : (array_key_exists('required', $fr) ? (empty($fr['required']) ? 0 : 1) : (int) !empty($field['required'])),
                    'label'    => isset($fr['label']) && is_scalar($fr['label']) ? sanitize_text_field((string) $fr['label']) : '',
                ];
                // A disabled field cannot be required.
                if (!$out['fields'][$role][$name]['enabled']) {
                    $out['fields'][$role][$name]['required'] = 0;
                }
            }
        }
        // Admin-defined fields are intentionally limited to a strict schema; arbitrary HTML is never accepted.
        $allowed_types = ['text', 'email', 'tel', 'url', 'textarea', 'number', 'date', 'select', 'checkbox'];
        foreach ($schema as $role => $def) {
            $definitions = $raw['custom_fields'][$role] ?? [];
            if (is_string($definitions)) {
                $decoded = json_decode($definitions, true);
                $definitions = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($definitions)) {
                $definitions = [];
            }
            $safe = [];
            foreach (array_slice($definitions, 0, 20) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = sanitize_key((string) ($item['name'] ?? ''));
                $label = sanitize_text_field((string) ($item['label'] ?? ''));
                $type = sanitize_key((string) ($item['type'] ?? 'text'));
                if (!$name || !preg_match('/^[a-z][a-z0-9_]{1,39}$/', $name) || !$label || !in_array($type, $allowed_types, true)) {
                    continue;
                }
                // Keep the first occurrence only; duplicate names would otherwise silently overwrite data.
                if (isset($safe[$name])) {
                    continue;
                }
                if (in_array($name, array_column($def['fields'], 'name'), true) || in_array($name, ['email','password','password_confirm'], true)) {
                    continue;
                }
                $options = [];
                if ($type === 'select' && isset($item['options']) && is_array($item['options'])) {
                    foreach (array_slice($item['options'], 0, 30) as $key => $value) {
                        $key = sanitize_key((string) $key);
                        $value = sanitize_text_field((string) $value);
                        if ($key !== '' && $value !== '') $options[$key] = $value;
                    }
                    if (!$options) continue;
                }
                $safe[$name] = [
                    'name' => $name, 'label' => [$label, $label], 'type' => $type,
                    'required' => !empty($item['required']), 'locked' => false,
                    'max' => max(1, min(2000, absint($item['max'] ?? 200))),
                    'help' => ['', ''], 'autocomplete' => 'off', 'recommended' => false,
                    'options' => $options,
                    'num_max' => 999999999999,
                ];
            }
            $out['custom_fields'][$role] = $safe;
        }
        return $out;
    }

    /**
     * Roles visible on the public form, in order, after admin settings and an optional shortcode whitelist.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function active_roles(string $only = '', ?array $settings = null): array
    {
        $settings = $settings ?? self::settings();
        $allow = $only !== '' ? array_filter(array_map('sanitize_key', explode(',', $only))) : [];
        $out = [];
        foreach (self::schema() as $key => $def) {
            if (empty($settings['roles'][$key]['enabled'])) {
                continue;
            }
            if ($allow && !in_array($key, $allow, true)) {
                continue;
            }
            $out[$key] = self::resolve_role($key, $def, $settings);
        }
        return $out;
    }

    /** @param array<string,mixed> $def @param array<string,mixed> $settings @return array<string,mixed> */
    private static function resolve_role(string $key, array $def, array $settings): array
    {
        $idx = I18n::is_fa() ? 0 : 1;
        $def['key'] = $key;
        $label = (string) ($settings['roles'][$key]['label'] ?? '');
        $desc  = (string) ($settings['roles'][$key]['desc'] ?? '');
        $def['title'] = $label !== '' ? $label : (string) $def['label'][$idx];
        $def['description'] = $desc !== '' ? $desc : (string) $def['desc'][$idx];
        $fields = [];
        foreach ($def['fields'] as $field) {
            $fs = $settings['fields'][$key][$field['name']] ?? ['enabled' => 1, 'required' => (int) $field['required'], 'label' => ''];
            if (empty($fs['enabled'])) {
                continue;
            }
            $field['required'] = !empty($fs['required']);
            $field['title'] = ($fs['label'] ?? '') !== '' ? (string) $fs['label'] : (string) $field['label'][$idx];
            $field['hint'] = (string) ($field['help'][$idx] ?? '');
            $fields[] = $field;
        }
        foreach ((array) ($settings['custom_fields'][$key] ?? []) as $custom) {
            if (!is_array($custom) || empty($custom['name'])) continue;
            $custom['title'] = (string) ($custom['label'][0] ?? $custom['name']);
            $custom['hint'] = '';
            $fields[] = $custom;
        }
        $def['fields'] = $fields;
        return $def;
    }

    /* ================================================================ */
    /* Validation                                                        */
    /* ================================================================ */

    /** Unicode-safe helpers; mbstring is optional on many shared WordPress hosts. */
    private static function text_length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        $count = preg_match_all('/./us', $value, $matches);
        return $count === false ? strlen($value) : $count;
    }

    private static function text_substr(string $value, int $start, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, $start, $length, 'UTF-8');
        }
        if ($value === '') {
            return '';
        }
        $count = preg_match_all('/./us', $value, $matches);
        if ($count === false) {
            return substr($value, $start, $length);
        }
        return implode('', array_slice($matches[0] ?? [], $start, $length));
    }

    public static function normalize_digits(string $s): string
    {
        return strtr($s, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /**
     * Validate one role's submitted values.
     *
     * @param array<string,mixed> $role  resolved role (see active_roles)
     * @param array<string,mixed> $raw   submitted f[role] array
     * @return array{0:array<string,mixed>,1:array<string,string>} cleaned values and field => message
     */
    public static function validate_role(array $role, array $raw): array
    {
        $clean = [];
        $errors = [];
        foreach ($role['fields'] as $field) {
            $name = (string) $field['name'];
            $title = (string) $field['title'];
            $val = $raw[$name] ?? '';
            $val = is_scalar($val) ? (string) $val : '';
            $max = (int) $field['max'];

            if ($field['type'] === 'checkbox') {
                $clean[$name] = $val !== '' && $val !== '0' ? 1 : 0;
                if (!empty($field['required']) && !$clean[$name]) {
                    $errors[$name] = sprintf(self::t('«%s» باید تأیید شود.', '"%s" must be confirmed.'), $title);
                }
                continue;
            }

            // Input is already unslashed by the caller; unslashing twice would eat real backslashes.
            $val = $field['type'] === 'textarea' ? sanitize_textarea_field($val) : sanitize_text_field($val);
            $val = trim($val);

            if ($val === '') {
                if (!empty($field['required'])) {
                    $errors[$name] = sprintf(self::t('«%s» الزامی است.', '"%s" is required.'), $title);
                }
                $clean[$name] = '';
                continue;
            }
            if (self::text_length($val) > $max && $field['type'] !== 'year' && $field['type'] !== 'number') {
                $errors[$name] = sprintf(self::t('«%s» حداکثر %d نویسه می‌تواند باشد.', '"%s" can be at most %d characters.'), $title, $max);
                $clean[$name] = self::text_substr($val, 0, $max);
                continue;
            }

            switch ($field['type']) {
                case 'tel':
                    $digits = preg_replace('/[^0-9+]/', '', self::normalize_digits($val)) ?? '';
                    $count = strlen(str_replace('+', '', $digits));
                    if ($count < 7 || $count > 15 || strpos(substr($digits, 1), '+') !== false) {
                        $errors[$name] = sprintf(self::t('«%s» معتبر نیست.', '"%s" is not valid.'), $title);
                    }
                    $clean[$name] = $digits;
                    break;
                case 'url':
                    $url = esc_url_raw($val, ['http', 'https']);
                    if ($url === '' || !preg_match('#^https?://[^\s/$.?\#].[^\s]*$#i', $url)) {
                        $errors[$name] = sprintf(self::t('«%s» باید نشانی کامل با http یا https باشد.', '"%s" must be a full http(s) URL.'), $title);
                        $url = '';
                    }
                    $clean[$name] = $url;
                    break;
                case 'year':
                    $y = (int) self::normalize_digits($val);
                    $now = (int) gmdate('Y');
                    // Accept Jalali (1200-1500) and Gregorian (1800-now) years.
                    $valid = ($y >= 1200 && $y <= 1500) || ($y >= 1800 && $y <= $now);
                    if (!$valid) {
                        $errors[$name] = sprintf(self::t('«%s» معتبر نیست.', '"%s" is not valid.'), $title);
                    }
                    $clean[$name] = $valid ? (string) $y : '';
                    break;
                case 'number':
                    $n = self::normalize_digits($val);
                    $num_max = (int) ($field['num_max'] ?? 80);
                    if (!ctype_digit($n) || strlen($n) > 15 || (int) $n > $num_max) {
                        $errors[$name] = sprintf(self::t('«%s» باید عددی بین ۰ تا %s باشد.', '"%s" must be a number between 0 and %s.'), $title, number_format($num_max, 0, '.', ''));
                        $n = '';
                    }
                    $clean[$name] = $n;
                    break;
                case 'social':
                    $h = ltrim(trim($val), '@');
                    $h = preg_replace('#^https?://(www\.)?(instagram\.com|t\.me)/#i', '', $h) ?? $h;
                    $h = trim($h, '/');
                    if (!preg_match('/^[A-Za-z0-9._]{1,60}$/', $h)) {
                        $errors[$name] = sprintf(self::t('«%s» فقط نام کاربری لاتین باشد.', '"%s" must be a plain username.'), $title);
                        $h = '';
                    }
                    $clean[$name] = $h;
                    break;
                case 'tags':
                    $parts = preg_split('/[,،;؛]+/u', $val) ?: [];
                    $parts = array_values(array_unique(array_filter(array_map('trim', $parts), static fn($p): bool => $p !== '')));
                    $clean[$name] = implode(', ', array_slice($parts, 0, 12));
                    break;
                case 'email':
                    $email_value = sanitize_email($val);
                    if (!$email_value || !is_email($email_value)) {
                        $errors[$name] = sprintf(self::t('«%s» باید ایمیل معتبر باشد.', '"%s" must be a valid email.'), $title);
                        $email_value = '';
                    }
                    $clean[$name] = $email_value;
                    break;
                case 'date':
                    $date_value = self::normalize_digits($val);
                    $date_obj = \DateTime::createFromFormat('!Y-m-d', $date_value);
                    if (!$date_obj || $date_obj->format('Y-m-d') !== $date_value) {
                        $errors[$name] = sprintf(self::t('«%s» باید تاریخ معتبر باشد.', '"%s" must be a valid date.'), $title);
                        $date_value = '';
                    }
                    $clean[$name] = $date_value;
                    break;
                case 'select':
                    $allowed = array_keys((array) ($field['options'] ?? []));
                    if (!in_array($val, array_map('strval', $allowed), true)) {
                        $errors[$name] = sprintf(self::t('«%s» معتبر نیست.', '"%s" is not valid.'), $title);
                        $val = '';
                    }
                    $clean[$name] = $val;
                    break;
                default:
                    $clean[$name] = $val;
            }
        }
        return [$clean, $errors];
    }

    /** @return string[] problems with a password, empty when acceptable */
    public static function password_problems(string $password, string $email, int $min): array
    {
        $problems = [];
        if (self::text_length($password) < $min) {
            $problems[] = sprintf(self::t('رمز عبور دست‌کم %d نویسه باشد.', 'Use at least %d characters.'), $min);
        }
        if (!preg_match('/\pL/u', $password) || !preg_match('/\d/', self::normalize_digits($password))) {
            $problems[] = self::t('رمز عبور باید هم حرف و هم عدد داشته باشد.', 'Include both letters and digits.');
        }
        $local = strtolower((string) strstr($email, '@', true));
        if ($email !== '' && (strtolower($password) === strtolower($email) || ($local !== '' && strtolower($password) === $local))) {
            $problems[] = self::t('رمز عبور نباید با ایمیل یکسان باشد.', 'Do not reuse your email as the password.');
        }
        if (preg_match('/^(.)\1+$/u', $password) || in_array(strtolower($password), ['password123', '12345678a', 'qwerty123', 'abc12345'], true)) {
            $problems[] = self::t('رمز عبور بسیار ساده است.', 'This password is too common.');
        }
        return $problems;
    }

    /**
     * A random login. It is deliberately NOT derived from the email: WordPress exposes logins through author
     * archives and ?author=N, so an email-based login would leak part of the address. Members sign in with their email.
     */
    public static function username_from_email(string $email = ''): string
    {
        for ($i = 0; $i < 10; $i++) {
            $name = 'm' . bin2hex(random_bytes(5));
            if (!username_exists($name)) {
                return $name;
            }
        }
        return 'm' . bin2hex(random_bytes(10));
    }

    /* ================================================================ */
    /* Anti-bot token                                                    */
    /* ================================================================ */

    public static function issue_time_token(?int $now = null): string
    {
        $now = $now ?? time();
        return $now . '.' . hash_hmac('sha256', 'acm|' . $now, wp_salt('nonce'));
    }

    /** True when the token is genuine and the form was open for at least $min seconds (and under 2 hours). */
    public static function time_token_ok(string $token, int $min = 3, ?int $now = null): bool
    {
        $now = $now ?? time();
        if (!preg_match('/^(\d{9,11})\.([a-f0-9]{64})$/', $token, $m)) {
            return false;
        }
        if (!hash_equals(hash_hmac('sha256', 'acm|' . $m[1], wp_salt('nonce')), $m[2])) {
            return false;
        }
        $age = $now - (int) $m[1];
        return $age >= $min && $age <= 7200;
    }

    /* ================================================================ */
    /* Entities                                                          */
    /* ================================================================ */

    /** @return array<string,array{table:string,fields:string[]}> */
    private static function entity_info(): array
    {
        return [
            'artist'    => ['table' => 'artists'],
            'gallery'   => ['table' => 'galleries'],
            'curator'   => ['table' => 'curators'],
            'collector' => ['table' => 'collectors'],
            'buyer'     => ['table' => 'buyers'],
        ];
    }

    /**
     * Map cleaned form values to the entity's create/update array.
     *
     * @param array<string,mixed> $c
     * @return array<string,mixed>
     */
    public static function entity_data(string $role, array $c, int $user_id, string $email): array
    {
        $common = ['user_id' => $user_id, 'email' => $email];
        switch ($role) {
            case 'artist':
                return $common + [
                    'first_name' => (string) ($c['first_name'] ?? ''), 'last_name' => (string) ($c['last_name'] ?? ''),
                    'display_name' => (string) ($c['display_name'] ?? ''), 'phone' => (string) ($c['phone'] ?? ''),
                    'city' => (string) ($c['city'] ?? ''), 'birth_year' => (string) ($c['birth_year'] ?? ''),
                    'mediums' => (string) ($c['mediums'] ?? ''), 'short_bio' => (string) ($c['short_bio'] ?? ''),
                    'website' => (string) ($c['website'] ?? ''), 'instagram' => (string) ($c['instagram'] ?? ''),
                ];
            case 'gallery':
                return $common + [
                    'name' => (string) ($c['gallery_name'] ?? ''), 'city' => (string) ($c['city'] ?? ''),
                    'country' => (string) ($c['country'] ?? ''), 'address' => (string) ($c['address'] ?? ''),
                    'phone' => (string) ($c['phone'] ?? ''), 'website' => (string) ($c['website'] ?? ''),
                    'about' => (string) ($c['about'] ?? ''),
                ];
            case 'curator':
                return $common + [
                    'display_name' => (string) ($c['display_name'] ?? ''), 'affiliation' => (string) ($c['affiliation'] ?? ''),
                    'specialties' => (string) ($c['specialties'] ?? ''), 'years_experience' => (string) ($c['years_experience'] ?? ''),
                    'phone' => (string) ($c['phone'] ?? ''), 'website' => (string) ($c['website'] ?? ''),
                    'instagram_url' => (string) ($c['instagram_url'] ?? ''), 'linkedin_url' => (string) ($c['linkedin_url'] ?? ''),
                    'bio' => (string) ($c['bio'] ?? ''),
                ];
            case 'collector':
                return [
                    'user_id' => $user_id, 'display_name' => (string) ($c['display_name'] ?? ''),
                    'collection_name' => (string) ($c['collection_name'] ?? ''), 'bio' => (string) ($c['bio'] ?? ''),
                    'is_public' => !empty($c['is_public']),
                ];
            case 'buyer':
                return $common + ['display_name' => (string) ($c['display_name'] ?? ''), 'phone' => (string) ($c['phone'] ?? '')];
        }
        return [];
    }

    /** @param array<string,mixed> $data @return array{success:bool,id?:int,slug?:string,message?:string} */
    private static function create_entity(string $role, array $data, bool $moderate): array
    {
        if ($moderate && in_array($role, ['artist', 'gallery'], true)) {
            $data['status'] = 'pending';
        }
        $res = match ($role) {
            'artist'    => Artist::create($data),
            'gallery'   => Gallery::create($data),
            'curator'   => Curator::create($data),
            'collector' => Collector::create($data),
            'buyer'     => Buyer::create($data),
            default     => ['success' => false, 'message' => 'role'],
        };
        if (!empty($res['success']) && $moderate && in_array($role, ['curator', 'collector'], true)) {
            global $wpdb;
            $wpdb->update(Database::table(self::entity_info()[$role]['table']), ['status' => 'pending'], ['id' => (int) $res['id']]);
        }
        return $res;
    }

    /** Persist the entity link on the WP user. */
    private static function link_user(int $user_id, string $role, int $entity_id, string $slug): void
    {
        update_user_meta($user_id, 'art_coa_role', $role);
        update_user_meta($user_id, 'art_coa_entity_id', $entity_id);
        if ($slug !== '') {
            update_user_meta($user_id, 'art_coa_entity_slug', $slug);
        }
    }

    /* ================================================================ */
    /* Access control                                                    */
    /* ================================================================ */

    /**
     * The current member's role and entity, verified against the entity's own user_id column.
     * Returns null when the user has no (or a foreign) entity.
     *
     * @return array{user_id:int,role:string,entity_id:int}|null
     */
    public static function current_member(): ?array
    {
        if (!is_user_logged_in()) {
            return null;
        }
        $uid = get_current_user_id();
        $role = (string) get_user_meta($uid, 'art_coa_role', true);
        $id = (int) get_user_meta($uid, 'art_coa_entity_id', true);
        $info = self::entity_info();
        if ($id <= 0 || !isset($info[$role])) {
            return null;
        }
        global $wpdb;
        $owner = $wpdb->get_var($wpdb->prepare('SELECT user_id FROM ' . Database::table($info[$role]['table']) . ' WHERE id = %d', $id));
        if ($owner === null && !self::row_exists($info[$role]['table'], $id)) {
            return null;
        }
        // A row bound to a different account is never editable by this user.
        if ($owner !== null && (int) $owner !== 0 && (int) $owner !== $uid) {
            return null;
        }
        return ['user_id' => $uid, 'role' => $role, 'entity_id' => $id];
    }

    private static function row_exists(string $table, int $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Database::table($table) . ' WHERE id = %d', $id));
    }

    /** True for accounts whose only roles are the Artiey member roles. */
    public static function is_member_only(\WP_User $user): bool
    {
        $roles = array_values((array) $user->roles);
        if (!$roles) {
            return false;
        }
        $member_roles = array_column(self::schema(), 'wp_role');
        return !array_diff($roles, $member_roles);
    }

    public static function maybe_hide_admin_bar($show)
    {
        $user = wp_get_current_user();
        return ($user instanceof \WP_User && $user->exists() && self::is_member_only($user)) ? false : $show;
    }

    /** Members use the front-end dashboard; keep them away from wp-admin screens (AJAX and admin-post stay open). */
    public static function keep_members_out_of_admin(): void
    {
        if ((defined('DOING_AJAX') && DOING_AJAX) || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }
        $user = wp_get_current_user();
        if (!($user instanceof \WP_User) || !$user->exists() || !self::is_member_only($user)) {
            return;
        }
        $script = isset($_SERVER['SCRIPT_NAME']) ? basename((string) $_SERVER['SCRIPT_NAME']) : '';
        // profile.php stays reachable: it is the only place members can change their password or email.
        if (in_array($script, ['admin-post.php', 'admin-ajax.php', 'profile.php'], true)) {
            return;
        }
        wp_safe_redirect(self::dashboard_url());
        exit;
    }

    /* ================================================================ */
    /* URLs                                                              */
    /* ================================================================ */

    public static function dashboard_url(): string
    {
        $id = absint(get_option('wp_art_coa_page_member'));
        if ($id && get_post_status($id) === 'publish') {
            return (string) get_permalink($id);
        }
        return home_url('/art-coa/member-dashboard/');
    }

    public static function register_url(): string
    {
        $id = absint(get_option('wp_art_coa_page_register'));
        if ($id && get_post_status($id) === 'publish') {
            return (string) get_permalink($id);
        }
        return home_url('/');
    }

    /* ================================================================ */
    /* Registration                                                      */
    /* ================================================================ */

    public static function handle_register(): void
    {
        if (empty($_POST['art_coa_register_nonce'])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['art_coa_register_nonce'])), self::NONCE_REG)) {
            self::fail_notice(self::t('نشست فرم منقضی شده است؛ صفحه را دوباره بارگذاری کنید.', 'Your session expired. Reload the page and try again.'));
            return;
        }
        $settings = self::settings();
        $roles = self::active_roles('', $settings);
        $role_key = sanitize_key((string) wp_unslash($_POST['member_role'] ?? ''));
        if (!isset($roles[$role_key])) {
            self::fail_notice(self::t('نقش انتخاب‌شده معتبر نیست.', 'The selected role is not available.'));
            return;
        }
        if (!self::time_token_ok((string) wp_unslash($_POST['art_coa_ts'] ?? ''))) {
            self::fail_notice(self::t('فرم خیلی سریع ارسال شد یا منقضی شده است؛ دوباره تلاش کنید.', 'The form was submitted too quickly or expired. Please try again.'));
            return;
        }

        $role = $roles[$role_key];
        $raw_fields = isset($_POST['f'][$role_key]) && is_array($_POST['f'][$role_key]) ? (array) wp_unslash($_POST['f'][$role_key]) : [];
        [$clean, $errors] = self::validate_role($role, $raw_fields);

        $email = sanitize_email((string) wp_unslash($_POST['email'] ?? ''));
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $confirm = (string) wp_unslash($_POST['password_confirm'] ?? '');
        if ($email === '' || !is_email($email)) {
            $errors['email'] = self::t('ایمیل معتبر وارد کنید.', 'Enter a valid email address.');
        }
        foreach (self::password_problems($password, $email, (int) $settings['min_password']) as $problem) {
            $errors['password'] = $problem;
            break;
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = self::t('تکرار رمز عبور با رمز عبور یکسان نیست.', 'The passwords do not match.');
        }
        if (!empty($settings['terms_page']) && empty($_POST['accept_terms'])) {
            $errors['accept_terms'] = self::t('پذیرش قوانین الزامی است.', 'You must accept the terms.');
        }

        self::$old = ['member_role' => $role_key, 'email' => $email, 'fields' => [$role_key => $clean]];
        if ($errors) {
            self::$errors = $errors;
            self::fail_notice(self::t('لطفاً موارد مشخص‌شده را اصلاح کنید.', 'Please fix the highlighted fields.'));
            return;
        }

        $verify = !empty($settings['verify_email']);
        $moderate = !empty($settings['moderation']);

        if (email_exists($email)) {
            if ($verify) {
                // Do not reveal whether an address is registered: show the same screen as a new signup.
                self::redirect_done('pending');
                return;
            }
            self::$errors = ['email' => self::t('این ایمیل قبلاً ثبت شده است؛ وارد شوید یا رمز را بازیابی کنید.', 'This email is already registered. Log in or reset your password.')];
            self::fail_notice(self::t('لطفاً موارد مشخص‌شده را اصلاح کنید.', 'Please fix the highlighted fields.'));
            return;
        }
        if (!$verify && $role_key === 'artist' && self::artist_email_claimed($email)) {
            // An existing artist profile with this email would be adopted by the new account; that needs a verified email.
            self::fail_notice(self::t('برای این ایمیل پروفایل هنرمند وجود دارد؛ با پشتیبانی تماس بگیرید.', 'A profile already uses this email. Please contact support.'));
            return;
        }

        if (!get_role($role['wp_role'])) {
            Roles::register_roles();
        }
        // The fields hold '' (not null) when left empty, so ?? would never fall through to the next choice.
        $shown_name = '';
        foreach ([(string) ($clean['display_name'] ?? ''), trim(($clean['first_name'] ?? '') . ' ' . ($clean['last_name'] ?? '')), (string) ($clean['gallery_name'] ?? '')] as $candidate) {
            if ($candidate !== '') {
                $shown_name = $candidate;
                break;
            }
        }
        if ($shown_name === '') {
            $shown_name = self::t('عضو', 'Member') . ' ' . substr(bin2hex(random_bytes(3)), 0, 4);
        }
        $user_id = wp_insert_user([
            'user_login' => self::username_from_email($email),
            'user_email' => $email,
            'user_pass'  => $password,
            'role'       => (string) $role['wp_role'],  // from the server-side schema only
            'display_name' => $shown_name,
            'nickname'   => $shown_name,   // WordPress would otherwise default the public nickname to the login
            'show_admin_bar_front' => 'false',
        ]);
        if (is_wp_error($user_id)) {
            self::fail_notice(self::t('ساخت حساب انجام نشد؛ دوباره تلاش کنید.', 'The account could not be created. Please try again.'));
            return;
        }
        $user_id = (int) $user_id;
        $data = self::entity_data($role_key, $clean, $user_id, $email);
        foreach ((array) ($settings['custom_fields'][$role_key] ?? []) as $custom_name => $custom_def) {
            if (array_key_exists($custom_name, $clean)) {
                update_user_meta($user_id, '_artiey_custom_' . $custom_name, $clean[$custom_name]);
            }
        }

        if ($verify) {
            $token = self::start_verification($user_id, $role_key, $data);
            self::issue_proof($user_id);
            if (!self::send_confirmation_mail($user_id, $email, $token)) {
                self::rollback_user($user_id);
                self::fail_notice(self::t('ارسال ایمیل تأیید ممکن نشد؛ کمی بعد دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.', 'We could not send the confirmation email. Try again shortly or contact support.'));
                return;
            }
            Audit::log($role_key, 0, 'registration_pending', ['user_id' => $user_id]);
            self::redirect_done('pending');
            return;
        }

        $entity = self::create_entity($role_key, $data, $moderate);
        if (empty($entity['success'])) {
            self::rollback_user($user_id);
            self::fail_notice(self::t('ثبت پروفایل انجام نشد؛ حساب ساخته نشد، دوباره تلاش کنید.', 'The profile could not be saved, so no account was created. Please try again.'));
            return;
        }
        self::link_user($user_id, $role_key, (int) $entity['id'], (string) ($entity['slug'] ?? ''));
        Audit::log($role_key, (int) $entity['id'], 'registered', ['user_id' => $user_id]);
        self::notify_admin($role_key, $user_id, (int) $entity['id'], $moderate);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        wp_safe_redirect(add_query_arg('art_coa_welcome', $moderate && $role['public'] ? 'pending' : 'ok', self::dashboard_url()));
        exit;
    }

    private static function fail_notice(string $message): void
    {
        self::$notice = $message;
        self::$notice_type = 'error';
    }

    private static function redirect_done(string $state): void
    {
        wp_safe_redirect(add_query_arg('art_coa_registered', $state, self::register_url()));
        exit;
    }

    private static function artist_email_claimed(string $email): bool
    {
        global $wpdb;
        $hit = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Database::table('artists') . ' WHERE email = %s LIMIT 1', $email));
        if ($hit) {
            return true;
        }
        $meta = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_aa_email' AND meta_value = %s LIMIT 1", $email));
        return (bool) $meta;
    }

    private static function rollback_user(int $user_id): void
    {
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        wp_delete_user($user_id);
    }

    /** @param array<string,mixed> $data */
    private static function start_verification(int $user_id, string $role, array $data): string
    {
        $token = wp_generate_password(40, false, false);
        update_user_meta($user_id, 'art_coa_email_pending', time());
        update_user_meta($user_id, 'art_coa_email_token', hash('sha256', $token));
        update_user_meta($user_id, 'art_coa_email_token_exp', time() + self::TOKEN_TTL);
        update_user_meta($user_id, 'art_coa_pending_profile', wp_json_encode(['role' => $role, 'data' => $data]));
        return $token;
    }

    /**
     * Bind the pending signup to the browser that submitted it. A confirmation link opened somewhere else
     * (for example by the owner of an address someone else typed in) still confirms the email but resets the
     * password, so a pre-registered attacker password never becomes usable.
     */
    private static function issue_proof(int $user_id): void
    {
        $proof = bin2hex(random_bytes(16));
        update_user_meta($user_id, 'art_coa_email_proof', hash('sha256', $proof));
        $_COOKIE[self::PROOF_COOKIE] = $proof;
        if (!headers_sent()) {
            setcookie(self::PROOF_COOKIE, $proof, [
                'expires' => time() + self::TOKEN_TTL, 'path' => defined('COOKIEPATH') ? COOKIEPATH : '/',
                'domain' => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
    }

    private static function has_proof(int $user_id): bool
    {
        $stored = (string) get_user_meta($user_id, 'art_coa_email_proof', true);
        $cookie = isset($_COOKIE[self::PROOF_COOKIE]) && is_string($_COOKIE[self::PROOF_COOKIE]) ? $_COOKIE[self::PROOF_COOKIE] : '';
        return $stored !== '' && $cookie !== '' && hash_equals($stored, hash('sha256', $cookie));
    }

    private static function send_confirmation_mail(int $user_id, string $email, string $token): bool
    {
        $link = add_query_arg(['art_coa_confirm_email' => $user_id, 'key' => $token], self::register_url());
        $site = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
        $subject = sprintf(self::t('تأیید ایمیل در %s', 'Confirm your email at %s'), $site);
        $body = self::t("برای فعال‌سازی حساب روی پیوند زیر بزنید (تا ۴۸ ساعت معتبر است):\n\n", "Open this link to activate your account (valid for 48 hours):\n\n")
            . $link . "\n\n" . self::t('اگر شما ثبت‌نام نکرده‌اید، این پیام را نادیده بگیرید.', 'If you did not sign up, ignore this message.');
        return (bool) wp_mail($email, $subject, $body);
    }

    /** Confirmation link landing: verifies the token, then creates the entity and links the account. */
    public static function handle_confirm(): void
    {
        if (empty($_GET['art_coa_confirm_email']) || empty($_GET['key'])) {
            return;
        }
        $uid = absint($_GET['art_coa_confirm_email']);
        $key = sanitize_text_field(wp_unslash((string) $_GET['key']));
        $stored = (string) get_user_meta($uid, 'art_coa_email_token', true);
        $exp = (int) get_user_meta($uid, 'art_coa_email_token_exp', true);
        $bad = static function () {
            wp_safe_redirect(add_query_arg('art_coa_registered', 'invalid', self::register_url()));
            exit;
        };
        if ($uid <= 0 || $stored === '' || !hash_equals($stored, hash('sha256', $key)) || $exp < time()) {
            $bad();
        }
        $pending = json_decode((string) get_user_meta($uid, 'art_coa_pending_profile', true), true);
        $role = is_array($pending) ? (string) ($pending['role'] ?? '') : '';
        $data = is_array($pending) && is_array($pending['data'] ?? null) ? $pending['data'] : [];
        if (!isset(self::entity_info()[$role]) || !$data) {
            $bad();
        }
        $moderate = !empty(self::settings()['moderation']);
        $entity = self::create_entity($role, $data, $moderate);
        if (empty($entity['success'])) {
            wp_safe_redirect(add_query_arg('art_coa_registered', 'error', self::register_url()));
            exit;
        }
        self::link_user($uid, $role, (int) $entity['id'], (string) ($entity['slug'] ?? ''));
        foreach (['art_coa_email_pending', 'art_coa_email_token', 'art_coa_email_token_exp', 'art_coa_pending_profile'] as $meta) {
            delete_user_meta($uid, $meta);
        }
        update_user_meta($uid, 'art_coa_email_verified', time());
        Audit::log($role, (int) $entity['id'], 'registered', ['user_id' => $uid]);
        self::notify_admin($role, $uid, (int) $entity['id'], $moderate);
        $same_browser = self::has_proof($uid);
        delete_user_meta($uid, 'art_coa_email_proof');
        if (!$same_browser) {
            // Opened outside the browser that signed up: do not trust the password chosen at signup.
            $user = get_userdata($uid);
            if ($user) {
                wp_set_password(wp_generate_password(32, true, true), $uid);
                $key = get_password_reset_key($user);
                if (!is_wp_error($key)) {
                    wp_safe_redirect(network_site_url('wp-login.php?action=rp&key=' . rawurlencode((string) $key) . '&login=' . rawurlencode((string) $user->user_login), 'login'));
                    exit;
                }
            }
        }
        wp_safe_redirect(add_query_arg('art_coa_registered', 'verified', self::register_url()));
        exit;
    }

    /** Unverified accounts cannot log in. */
    public static function block_unverified_login($user, $username = '', $password = '')
    {
        if ($user instanceof \WP_User && get_user_meta($user->ID, 'art_coa_email_pending', true)) {
            return new \WP_Error('art_coa_unverified', self::t('ابتدا ایمیل خود را از پیوند ارسال‌شده تأیید کنید.', 'Please confirm your email using the link we sent you.'));
        }
        return $user;
    }

    /** Resend the confirmation mail; the answer never reveals whether the address exists. */
    public static function handle_resend(): void
    {
        if (empty($_POST['art_coa_resend_email']) || empty($_POST['art_coa_resend_nonce'])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['art_coa_resend_nonce'])), self::NONCE_RESEND)) {
            return;
        }
        $email = sanitize_email(wp_unslash((string) $_POST['art_coa_resend_email']));
        $limit_key = 'art_coa_resend_' . md5(strtolower($email) . '|' . Security::client_ip());
        $ip_key = 'art_coa_resend_ip_' . md5(Security::client_ip());
        $ip_hits = (int) get_transient($ip_key);
        if ($email !== '' && is_email($email) && !get_transient($limit_key) && $ip_hits < 5) {
            set_transient($limit_key, 1, 5 * MINUTE_IN_SECONDS);
            set_transient($ip_key, $ip_hits + 1, HOUR_IN_SECONDS);
            $user = get_user_by('email', $email);
            if ($user && get_user_meta($user->ID, 'art_coa_email_pending', true)) {
                $pending = json_decode((string) get_user_meta($user->ID, 'art_coa_pending_profile', true), true);
                if (is_array($pending)) {
                    $token = self::start_verification((int) $user->ID, (string) ($pending['role'] ?? ''), (array) ($pending['data'] ?? []));
                    self::send_confirmation_mail((int) $user->ID, $email, $token);
                }
            }
        }
        self::redirect_done('resent');
    }

    private static function notify_admin(string $role, int $user_id, int $entity_id, bool $moderate): void
    {
        if (empty(self::settings()['notify_admin'])) {
            return;
        }
        $user = get_userdata($user_id);
        $label = self::schema()[$role]['label'][I18n::is_fa() ? 0 : 1] ?? $role;
        $subject = sprintf(self::t('ثبت‌نام جدید (%s)', 'New registration (%s)'), $label);
        $body = sprintf(self::t("یک %s جدید ثبت‌نام کرد.\nایمیل: %s\nوضعیت: %s\n", "A new %s signed up.\nEmail: %s\nStatus: %s\n"), $label, $user ? $user->user_email : '', $moderate ? self::t('در انتظار تأیید', 'pending approval') : self::t('فعال', 'active'))
            . self::approval_url($role);
        wp_mail((string) get_option('admin_email'), $subject, $body);
    }

    /** Where an administrator approves a new profile of this role. */
    private static function approval_url(string $role): string
    {
        return match ($role) {
            'curator'   => admin_url('admin.php?page=wp-art-coa-curators'),
            'collector' => admin_url('admin.php?page=wp-art-coa-collectors'),
            'artist'    => admin_url('edit.php?post_status=pending&post_type=artiey_artist'),
            'gallery'   => admin_url('edit.php?post_status=pending&post_type=artiey_gallery'),
            default     => admin_url('admin.php?page=artiey-platform'),
        };
    }

    public static function schedule_cleanup(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /** Remove accounts that never confirmed their email. Only member-role accounts are touched. */
    public static function purge_unverified(): void
    {
        $ids = get_users([
            'meta_key' => 'art_coa_email_pending', 'meta_value' => time() - self::PENDING_TTL,
            'meta_compare' => '<', 'meta_type' => 'NUMERIC', 'fields' => 'ID', 'number' => 200,
        ]);
        foreach ((array) $ids as $id) {
            $user = get_userdata((int) $id);
            if ($user && self::is_member_only($user)) {
                self::rollback_user((int) $id);
            }
        }
    }

    /* ================================================================ */
    /* Rendering                                                         */
    /* ================================================================ */

    private static function print_assets(): string
    {
        if (self::$assets_printed) {
            return '';
        }
        self::$assets_printed = true;
        wp_enqueue_style('wp-art-coa-member-forms');
        return '';
    }

    /** @param array<string,mixed> $field */
    private static function render_field(string $role, array $field, $value, string $error, bool $required_attr): string
    {
        $name = (string) $field['name'];
        $id = 'acm-' . $role . '-' . $name;
        $input_name = 'f[' . $role . '][' . $name . ']';
        $desc_ids = [];
        $err_id = $id . '-err';
        $hint_id = $id . '-hint';
        if ($field['hint'] !== '') { $desc_ids[] = $hint_id; }
        if ($error !== '') { $desc_ids[] = $err_id; }
        $attrs = ' id="' . esc_attr($id) . '" name="' . esc_attr($input_name) . '"'
            . ($required_attr && !empty($field['required']) ? ' required' : '')
            . ($error !== '' ? ' aria-invalid="true"' : '')
            . ($desc_ids ? ' aria-describedby="' . esc_attr(implode(' ', $desc_ids)) . '"' : '')
            . ' data-required="' . (!empty($field['required']) ? '1' : '0') . '"';
        $type = (string) $field['type'];
        $max = (int) $field['max'];
        $ltr = in_array($type, ['tel', 'url', 'year', 'number', 'social'], true) ? ' dir="ltr"' : '';
        $html = '<div class="acm-field' . ($error !== '' ? ' has-error' : '') . ($type === 'checkbox' ? ' acm-field--check' : '') . '">';

        if ($type === 'checkbox') {
            $html .= '<label for="' . esc_attr($id) . '"><input type="checkbox" value="1"' . $attrs . checked(!empty($value), true, false) . '> <span>' . esc_html((string) $field['title']) . '</span></label>';
        } else {
            $html .= '<label for="' . esc_attr($id) . '">' . esc_html((string) $field['title']) . (!empty($field['required']) ? ' <span class="acm-req" aria-hidden="true">*</span><span class="screen-reader-text"> ' . esc_html(self::t('الزامی', 'required')) . '</span>' : '') . '</label>';
            $ac = (string) $field['autocomplete'];
            if ($type === 'textarea') {
                $html .= '<textarea rows="4" maxlength="' . $max . '"' . $attrs . '>' . esc_textarea((string) $value) . '</textarea>';
            } elseif ($type === 'select') {
                $html .= '<select' . $attrs . '><option value=""></option>';
                foreach ((array) ($field['options'] ?? []) as $k => $label) {
                    $html .= '<option value="' . esc_attr((string) $k) . '"' . selected((string) $value, (string) $k, false) . '>' . esc_html((string) $label) . '</option>';
                }
                $html .= '</select>';
            } else {
                $html_type = match ($type) { 'tel' => 'tel', 'url' => 'url', 'email' => 'email', 'date' => 'date', 'number' => 'number', default => 'text' };
                $inputmode = match ($type) { 'tel' => 'tel', 'year', 'number' => 'numeric', 'email' => 'email', default => 'text' };
                $html .= '<input type="' . $html_type . '" inputmode="' . $inputmode . '" maxlength="' . ($type === 'number' ? 2 : $max) . '" autocomplete="' . esc_attr($ac) . '"' . $ltr . $attrs . ' value="' . esc_attr((string) $value) . '">';
            }
        }
        if ($field['hint'] !== '') {
            $html .= '<p class="acm-hint" id="' . esc_attr($hint_id) . '">' . esc_html((string) $field['hint']) . '</p>';
        }
        if ($error !== '') {
            $html .= '<p class="acm-error" id="' . esc_attr($err_id) . '" role="alert">' . esc_html($error) . '</p>';
        }
        return $html . '</div>';
    }

    private static function account_field(string $name, string $label, string $type, string $ac, string $error, string $value = '', string $hint = ''): string
    {
        $id = 'acm-' . $name;
        $err_id = $id . '-err';
        $html = '<div class="acm-field' . ($error !== '' ? ' has-error' : '') . '"><label for="' . esc_attr($id) . '">' . esc_html($label) . ' <span class="acm-req" aria-hidden="true">*</span></label>'
            . '<input type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" required autocomplete="' . esc_attr($ac) . '" dir="ltr"'
            . ($type === 'email' ? ' value="' . esc_attr($value) . '"' : '')
            . ($error !== '' ? ' aria-invalid="true" aria-describedby="' . esc_attr($err_id) . '"' : '') . '>';
        if ($hint !== '') {
            $html .= '<p class="acm-hint">' . esc_html($hint) . '</p>';
        }
        if ($error !== '') {
            $html .= '<p class="acm-error" id="' . esc_attr($err_id) . '" role="alert">' . esc_html($error) . '</p>';
        }
        return $html . '</div>';
    }

    /** @param array<string,mixed>|string $atts */
    public static function render_register($atts = []): string
    {
        self::print_assets();
        $atts = shortcode_atts(['roles' => '', 'default' => '', 'title' => '', 'intro' => ''], (array) $atts, 'art_coa_register');
        $rtl = I18n::is_fa() ? 'rtl' : 'ltr';

        if (is_user_logged_in()) {
            return '<div class="art-coa-register acm" dir="' . $rtl . '"><p class="acm-notice"><a href="' . esc_url(self::dashboard_url()) . '">' . esc_html(self::t('ورود به داشبورد من', 'Go to my dashboard')) . '</a></p></div>';
        }

        $state = isset($_GET['art_coa_registered']) ? sanitize_key((string) $_GET['art_coa_registered']) : '';
        $messages = [
            'pending'  => ['ok', self::t('اگر ایمیل شما قبلاً ثبت نشده باشد، پیام تأیید برایتان ارسال شد. پیوند داخل آن را باز کنید تا حساب فعال شود (پوشهٔ هرزنامه را هم ببینید).', 'If this address is new, we sent a confirmation email. Open the link inside to activate your account (check spam too).')],
            'resent'   => ['ok', self::t('اگر حسابی در انتظار تأیید با این ایمیل وجود داشته باشد، پیام تأیید دوباره ارسال شد.', 'If a pending account exists for this address, we resent the confirmation email.')],
            'verified' => ['ok', self::t('ایمیل شما تأیید شد. اکنون می‌توانید وارد شوید.', 'Your email is confirmed. You can log in now.')],
            'invalid'  => ['error', self::t('پیوند تأیید نامعتبر یا منقضی است. ایمیل خود را در فرم «ارسال دوباره» وارد کنید.', 'This confirmation link is invalid or expired. Use the resend form below.')],
            'error'    => ['error', self::t('ثبت پروفایل کامل نشد؛ لطفاً با پشتیبانی تماس بگیرید.', 'We could not finish your profile. Please contact support.')],
        ];
        if (isset($messages[$state])) {
            [$type, $text] = $messages[$state];
            $out = '<div class="art-coa-register acm" dir="' . $rtl . '"><div class="acm-notice acm-notice--' . esc_attr($type) . '" role="status">' . esc_html($text) . '</div>';
            if ($state === 'verified') {
                $out .= '<p><a class="acm-btn" href="' . esc_url(wp_login_url(self::dashboard_url())) . '">' . esc_html(self::t('ورود', 'Log in')) . '</a></p>';
            }
            if (in_array($state, ['invalid', 'pending', 'resent'], true)) {
                $out .= self::resend_form();
            }
            return $out . '</div>';
        }

        $settings = self::settings();
        $roles = self::active_roles((string) $atts['roles'], $settings);
        if (!$roles) {
            return '<div class="art-coa-register acm" dir="' . $rtl . '"><p class="acm-notice">' . esc_html(self::t('ثبت‌نام فعلاً در دسترس نیست.', 'Registration is currently unavailable.')) . '</p></div>';
        }
        $selected = sanitize_key((string) ($_POST['member_role'] ?? $_GET['role'] ?? $atts['default']));
        if (!isset($roles[$selected])) {
            $selected = (string) array_key_first($roles);
        }

        $old = self::$old;
        $errors = self::$errors;
        $title = $atts['title'] !== '' ? (string) $atts['title'] : self::t('ثبت‌نام در پلتفرم هنری', 'Join the art platform');

        ob_start();
        ?>
        <div class="art-coa-register acm" dir="<?php echo esc_attr($rtl); ?>">
            <h2 class="acm-title"><?php echo esc_html($title); ?></h2>
            <?php if ($atts['intro'] !== '') : ?><p class="acm-intro"><?php echo esc_html((string) $atts['intro']); ?></p><?php endif; ?>
            <?php if (self::$notice !== '') : ?>
                <div class="acm-notice acm-notice--<?php echo esc_attr(self::$notice_type); ?>" role="alert" tabindex="-1" id="acm-summary"><?php echo esc_html(self::$notice); ?></div>
            <?php endif; ?>
            <form method="post" class="acm-form" id="acm-form" novalidate action="<?php echo esc_url(self::register_url()); ?>">
                <?php wp_nonce_field(self::NONCE_REG, 'art_coa_register_nonce'); ?>
                <input type="hidden" name="art_coa_ts" value="<?php echo esc_attr(self::issue_time_token()); ?>">

                <fieldset class="acm-roles">
                    <legend><?php echo esc_html(self::t('نقش خود را انتخاب کنید', 'Choose your role')); ?></legend>
                    <div class="acm-role-grid">
                    <?php foreach ($roles as $key => $role) : ?>
                        <label class="acm-role" data-role="<?php echo esc_attr($key); ?>">
                            <input type="radio" name="member_role" value="<?php echo esc_attr($key); ?>" <?php checked($selected, $key); ?>>
                            <span class="acm-role-icon" aria-hidden="true"><?php echo esc_html((string) $role['icon']); ?></span>
                            <strong><?php echo esc_html((string) $role['title']); ?></strong>
                            <small><?php echo esc_html((string) $role['description']); ?></small>
                        </label>
                    <?php endforeach; ?>
                    </div>
                    <noscript><p class="acm-hint"><?php echo esc_html(self::t('بدون جاوااسکریپت از این پیوندها نقش را عوض کنید:', 'Without JavaScript, switch role here:')); ?>
                        <?php foreach ($roles as $key => $role) : ?><a href="<?php echo esc_url(add_query_arg('role', $key, self::register_url())); ?>"><?php echo esc_html((string) $role['title']); ?></a> <?php endforeach; ?></p></noscript>
                </fieldset>

                <fieldset class="acm-account">
                    <legend><?php echo esc_html(self::t('اطلاعات ورود', 'Account')); ?></legend>
                    <?php
                    echo self::account_field('email', self::t('ایمیل', 'Email'), 'email', 'email', $errors['email'] ?? '', (string) ($old['email'] ?? '')); // phpcs:ignore WordPress.Security.EscapeOutput
                    echo self::account_field('password', self::t('رمز عبور', 'Password'), 'password', 'new-password', $errors['password'] ?? '', '', sprintf(self::t('دست‌کم %d نویسه، شامل حرف و عدد', 'At least %d characters with letters and digits'), (int) $settings['min_password'])); // phpcs:ignore WordPress.Security.EscapeOutput
                    echo self::account_field('password_confirm', self::t('تکرار رمز عبور', 'Repeat password'), 'password', 'new-password', $errors['password_confirm'] ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput
                    ?>
                </fieldset>

                <?php foreach ($roles as $key => $role) :
                    $active = $key === $selected;
                    $values = $active ? (array) ($old['fields'][$key] ?? []) : [];
                    ?>
                    <fieldset class="acm-panel" id="acm-panel-<?php echo esc_attr($key); ?>" data-role="<?php echo esc_attr($key); ?>" <?php echo $active ? '' : 'hidden disabled'; ?>>
                        <legend><?php echo esc_html(sprintf(self::t('اطلاعات %s', '%s details'), (string) $role['title'])); ?></legend>
                        <?php foreach ($role['fields'] as $field) {
                            echo self::render_field($key, $field, $values[$field['name']] ?? '', $active ? ($errors[$field['name']] ?? '') : '', true); // phpcs:ignore WordPress.Security.EscapeOutput
                        } ?>
                    </fieldset>
                <?php endforeach; ?>

                <?php if (!empty($settings['terms_page'])) : $terms_url = get_permalink((int) $settings['terms_page']); ?>
                    <div class="acm-field acm-field--check<?php echo isset($errors['accept_terms']) ? ' has-error' : ''; ?>">
                        <label><input type="checkbox" name="accept_terms" value="1" required> <span><?php echo esc_html(self::t('قوانین و حریم خصوصی را می‌پذیرم.', 'I accept the terms and privacy policy.')); ?>
                            <?php if ($terms_url) : ?><a href="<?php echo esc_url((string) $terms_url); ?>" target="_blank" rel="noopener"><?php echo esc_html(self::t('مشاهده', 'Read')); ?></a><?php endif; ?></span></label>
                        <?php if (isset($errors['accept_terms'])) : ?><p class="acm-error" role="alert"><?php echo esc_html($errors['accept_terms']); ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>

                <p class="acm-trap" aria-hidden="true"><label>Leave empty<input type="text" name="art_coa_hp" value="" tabindex="-1" autocomplete="off"></label></p>
                <p><button type="submit" class="acm-btn"><?php echo esc_html(self::t('ثبت‌نام', 'Create account')); ?></button></p>
                <p class="acm-hint"><?php echo wp_kses_post(sprintf(self::t('قبلاً ثبت‌نام کرده‌اید؟ <a href="%s">وارد شوید</a>', 'Already registered? <a href="%s">Log in</a>'), esc_url(wp_login_url(self::dashboard_url())))); ?></p>
            </form>
        </div>
        <script>
        (function(){
            var form=document.getElementById('acm-form'); if(!form){return;}
            function activate(role){
                form.querySelectorAll('.acm-panel').forEach(function(p){
                    var on=p.getAttribute('data-role')===role;
                    p.hidden=!on; p.disabled=!on;
                    p.querySelectorAll('[data-required="1"]').forEach(function(el){ if(on){el.setAttribute('required','');}else{el.removeAttribute('required');} });
                });
                form.querySelectorAll('.acm-role').forEach(function(l){ l.classList.toggle('is-active', l.getAttribute('data-role')===role); });
            }
            form.querySelectorAll('input[name="member_role"]').forEach(function(r){ r.addEventListener('change',function(){activate(r.value);}); });
            var c=form.querySelector('input[name="member_role"]:checked'); if(c){activate(c.value);}
            form.removeAttribute('novalidate');
            var s=document.getElementById('acm-summary'); if(s){s.focus();}
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    private static function resend_form(): string
    {
        return '<form method="post" class="acm-form acm-resend" action="' . esc_url(self::register_url()) . '">'
            . wp_nonce_field(self::NONCE_RESEND, 'art_coa_resend_nonce', true, false)
            . '<div class="acm-field"><label for="acm-resend-email">' . esc_html(self::t('ایمیل تأیید نرسید؟ ایمیل خود را وارد کنید', 'No email? Enter your address')) . '</label>'
            . '<input type="email" id="acm-resend-email" name="art_coa_resend_email" required autocomplete="email" dir="ltr"></div>'
            . '<p><button type="submit" class="acm-btn acm-btn--ghost">' . esc_html(self::t('ارسال دوباره', 'Resend')) . '</button></p></form>';
    }

    /* ================================================================ */
    /* Profile editor                                                    */
    /* ================================================================ */

    /** @return array<string,mixed> current values keyed by form field name */
    private static function load_values(string $role, int $id): array
    {
        $row = match ($role) {
            'artist'    => Artist::get($id),
            'gallery'   => Gallery::get($id),
            'curator'   => Curator::get($id),
            'collector' => Collector::get($id),
            'buyer'     => self::buyer_row($id),
            default     => null,
        };
        if (!$row) {
            return [];
        }
        $vals = [];
        foreach (self::schema()[$role]['fields'] as $field) {
            $name = $field['name'];
            $col = ($role === 'gallery' && $name === 'gallery_name') ? 'name' : $name;
            $vals[$name] = isset($row->{$col}) && is_scalar($row->{$col}) ? (string) $row->{$col} : '';
        }
        $user_id = get_current_user_id();
        foreach ((array) (self::settings()['custom_fields'][$role] ?? []) as $name => $field) {
            $meta = get_user_meta($user_id, '_artiey_custom_' . $name, true);
            $vals[$name] = is_scalar($meta) ? (string) $meta : '';
        }
        return $vals;
    }

    private static function buyer_row(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Database::table('buyers') . ' WHERE id = %d', $id)) ?: null;
    }

    /** Share of enabled fields that have a value, as a whole percent. */
    public static function completeness(array $role, array $values): int
    {
        $total = 0;
        $filled = 0;
        foreach ($role['fields'] as $field) {
            if ($field['type'] === 'checkbox') {
                continue;
            }
            $total++;
            if (trim((string) ($values[$field['name']] ?? '')) !== '') {
                $filled++;
            }
        }
        return $total ? (int) round($filled * 100 / $total) : 100;
    }

    public static function render_profile_shortcode($atts = []): string
    {
        return self::render_profile();
    }

    public static function render_profile(): string
    {
        self::print_assets();
        $rtl = I18n::is_fa() ? 'rtl' : 'ltr';
        $me = self::current_member();
        if (!$me) {
            return '<div class="acm" dir="' . $rtl . '"><p class="acm-notice">' . esc_html(self::t('برای ویرایش پروفایل باید با حساب عضو وارد شوید.', 'Log in with a member account to edit your profile.')) . '</p></div>';
        }
        $settings = self::settings();
        $schema = self::schema();
        if (!isset($schema[$me['role']])) {
            return '';
        }
        $role = self::resolve_role($me['role'], $schema[$me['role']], $settings);
        $values = self::load_values($me['role'], $me['entity_id']);
        $errors = [];
        if (isset(self::$old['profile_values'])) {
            $values = array_merge($values, self::$old['profile_values']);
            $errors = self::$errors;
        }
        $percent = self::completeness($role, $values);
        $saved = isset($_GET['art_coa_profile']) && sanitize_key((string) $_GET['art_coa_profile']) === 'saved';

        ob_start();
        ?>
        <section class="acm acm-profile" dir="<?php echo esc_attr($rtl); ?>" aria-labelledby="acm-profile-title">
            <h3 id="acm-profile-title"><?php echo esc_html(self::t('ویرایش پروفایل', 'Edit profile')); ?></h3>
            <div class="acm-progress" role="img" aria-label="<?php echo esc_attr(sprintf(self::t('تکمیل پروفایل: %d٪', 'Profile completeness: %d%%'), $percent)); ?>">
                <span style="width:<?php echo (int) $percent; ?>%"></span>
            </div>
            <p class="acm-hint"><?php echo esc_html(sprintf(self::t('پروفایل شما %d٪ کامل است؛ پروفایل کامل‌تر در جست‌وجو بهتر دیده می‌شود.', 'Your profile is %d%% complete; fuller profiles rank better in search.'), $percent)); ?></p>
            <?php if ($saved) : ?><div class="acm-notice acm-notice--ok" role="status"><?php echo esc_html(self::t('تغییرات ذخیره شد.', 'Changes saved.')); ?></div><?php endif; ?>
            <?php if (self::$notice !== '' && self::$notice_type === 'error') : ?><div class="acm-notice acm-notice--error" role="alert"><?php echo esc_html(self::$notice); ?></div><?php endif; ?>
            <form method="post" class="acm-form" action="<?php echo esc_url((string) (get_permalink() ?: self::dashboard_url())); ?>">
                <?php wp_nonce_field(self::NONCE_PROFILE, 'art_coa_profile_nonce'); ?>
                <input type="hidden" name="art_coa_profile_save" value="1">
                <?php foreach ($role['fields'] as $field) {
                    echo self::render_field($me['role'], $field, $values[$field['name']] ?? '', $errors[$field['name']] ?? '', true); // phpcs:ignore WordPress.Security.EscapeOutput
                } ?>
                <p><button type="submit" class="acm-btn"><?php echo esc_html(self::t('ذخیرهٔ تغییرات', 'Save changes')); ?></button>
                    <a class="acm-btn acm-btn--ghost" href="<?php echo esc_url(admin_url('profile.php')); ?>"><?php echo esc_html(self::t('تغییر رمز عبور و ایمیل', 'Change password and email')); ?></a></p>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /** Save the current member's own profile. The target entity comes from the verified account, never from the request. */
    public static function handle_profile_save(): void
    {
        if (empty($_POST['art_coa_profile_save']) || !is_user_logged_in()) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_POST['art_coa_profile_nonce'] ?? ''))), self::NONCE_PROFILE)) {
            self::fail_notice(self::t('نشست فرم منقضی شده است؛ صفحه را دوباره بارگذاری کنید.', 'Your session expired. Reload the page.'));
            return;
        }
        $me = self::current_member();
        $schema = self::schema();
        if (!$me || !isset($schema[$me['role']])) {
            self::fail_notice(self::t('پروفایلی برای این حساب پیدا نشد.', 'No profile is linked to this account.'));
            return;
        }
        $role = self::resolve_role($me['role'], $schema[$me['role']], self::settings());
        $raw = isset($_POST['f'][$me['role']]) && is_array($_POST['f'][$me['role']]) ? (array) wp_unslash($_POST['f'][$me['role']]) : [];
        [$clean, $errors] = self::validate_role($role, $raw);
        if ($errors) {
            self::$errors = $errors;
            self::$old = ['profile_values' => $clean];
            self::fail_notice(self::t('لطفاً موارد مشخص‌شده را اصلاح کنید.', 'Please fix the highlighted fields.'));
            return;
        }
        $user = wp_get_current_user();
        $data = self::entity_data($me['role'], $clean, $me['user_id'], (string) $user->user_email);
        unset($data['user_id'], $data['email']); // identity fields are never editable here
        foreach ((array) (self::settings()['custom_fields'][$me['role']] ?? []) as $custom_name => $custom_def) {
            if (array_key_exists($custom_name, $clean)) {
                update_user_meta($me['user_id'], '_artiey_custom_' . $custom_name, $clean[$custom_name]);
                unset($data[$custom_name]);
            }
        }
        if ($me['role'] === 'artist' && ($data['display_name'] ?? '') === '') {
            unset($data['display_name']); // let the post title follow the edited first/last name
        }
        $res = match ($me['role']) {
            'artist'    => Artist::update($me['entity_id'], $data),
            'gallery'   => Gallery::update($me['entity_id'], $data),
            'curator'   => Curator::update($me['entity_id'], $data),
            'collector' => Collector::update($me['entity_id'], $data),
            'buyer'     => self::update_buyer($me['entity_id'], $data),
            default     => ['success' => false],
        };
        if (empty($res['success'])) {
            self::fail_notice(self::t('ذخیره انجام نشد؛ دوباره تلاش کنید.', 'Saving failed. Please try again.'));
            return;
        }
        Audit::log($me['role'], $me['entity_id'], 'profile_updated', ['user_id' => $me['user_id']]);
        wp_safe_redirect(add_query_arg('art_coa_profile', 'saved', remove_query_arg('art_coa_profile', wp_get_referer() ?: self::dashboard_url())));
        exit;
    }

    /** @param array<string,mixed> $data @return array{success:bool} */
    private static function update_buyer(int $id, array $data): array
    {
        global $wpdb;
        $ok = $wpdb->update(Database::table('buyers'), [
            'display_name' => sanitize_text_field((string) ($data['display_name'] ?? '')),
            'phone' => sanitize_text_field((string) ($data['phone'] ?? '')),
        ], ['id' => $id]);
        return ['success' => $ok !== false];
    }

    /* ================================================================ */
    /* SEO                                                               */
    /* ================================================================ */

    /** Pages that hold personal or transient member state must stay out of search results. */
    private static function is_private_member_request(): bool
    {
        if (get_query_var('art_coa_member') || isset($_GET['art_coa_confirm_email']) || isset($_GET['art_coa_registered']) || isset($_GET['art_coa_welcome'])) {
            return true;
        }
        if (is_singular()) {
            $post = get_queried_object();
            if (is_object($post) && !empty($post->post_content)
                && (strpos((string) $post->post_content, '[art_coa_member_dashboard') !== false || strpos((string) $post->post_content, '[art_coa_member_profile') !== false)) {
                return true;
            }
        }
        return false;
    }

    public static function robots(array $robots): array
    {
        if (self::is_private_member_request()) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            unset($robots['max-image-preview']);
        }
        return $robots;
    }

    private static function is_register_page(): bool
    {
        if (!is_singular()) {
            return false;
        }
        $post = get_queried_object();
        return is_object($post) && !empty($post->post_content) && strpos((string) $post->post_content, '[art_coa_register') !== false;
    }

    /** Meta description and JSON-LD for the registration page, unless a dedicated SEO plugin owns the head. */
    public static function head_meta(): void
    {
        if (!self::is_register_page() || self::is_private_member_request()) {
            return;
        }
        $roles = self::active_roles();
        if (!$roles) {
            return;
        }
        $names = array_map(static fn($r): string => (string) $r['title'], array_values($roles));
        $desc = sprintf(self::t('ثبت‌نام در پلتفرم هنری به‌عنوان %s؛ ساخت پروفایل تأییدشده، نمایش آثار و دریافت گواهی اصالت.', 'Sign up to the art platform as %s: a verified profile, showcase and certificates of authenticity.'), implode(self::t('، ', ', '), $names));
        $seo_plugin = defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION');
        if (!$seo_plugin) {
            echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
        }
        $ld = [
            '@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => wp_get_document_title(),
            'description' => $desc, 'inLanguage' => I18n::is_fa() ? 'fa-IR' : 'en',
            'url' => (string) get_permalink(),
            'isPartOf' => ['@type' => 'WebSite', 'name' => (string) get_bloginfo('name'), 'url' => home_url('/')],
            'potentialAction' => ['@type' => 'RegisterAction', 'target' => (string) get_permalink()],
        ];
        echo '<script type="application/ld+json">' . wp_json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . "</script>\n";
    }
}
