<?php
declare(strict_types=1);

namespace WPArtCOA\Admin;

use WPArtCOA\I18n;

defined('ABSPATH') || exit;

final class Settings
{
    public const OPTION = 'wp_art_coa_settings';

    public static function defaults(): array
    {
        return [
            'language'        => 'fa',
            'jalali_dates'    => 1,
            'enable_seo'      => 1,
            'cache_ttl'       => 180,
            'primary_color'   => '#1a365d',
            'secondary_color' => '#b32d2e',
            'accent_color'    => '#c9a227',
            'site_logo_url'   => '',
            'admin_sign_url'  => '',
            'admin_sign_name' => '',
            'admin_sign_title'=> '',
            'cert_footer'     => '',
            'bio_title'       => 'بیوگرافی',
            'bio_layout'      => 'split',
            'bio_show_title'  => 1, 'bio_show_lead' => 1, 'bio_show_facts' => 1, 'bio_show_statement' => 1,
            'bio_show_education' => 1, 'bio_show_awards' => 1, 'bio_readmore' => 1,
            'bio_paragraphs'  => 2,
            'auction_require_login' => 1,
            'auction_proxy' => 1,
            'auction_payment_days' => 3,
        ];
    }

    public static function get(): array
    {
        return array_merge(self::defaults(), get_option(self::OPTION, []));
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'artiey-platform',
            I18n::t('settings'),
            I18n::t('settings'),
            'manage_options',
            'wp-art-coa-settings',
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!empty($_POST['art_coa_save_settings'])) {
            check_admin_referer('art_coa_settings');
            $settings = [
                'language'        => in_array($_POST['language'] ?? '', ['fa', 'en'], true) ? $_POST['language'] : 'fa',
                'jalali_dates'    => !empty($_POST['jalali_dates']) ? 1 : 0,
                'enable_seo'      => !empty($_POST['enable_seo']) ? 1 : 0,
                'cache_ttl'       => max(60, min(3600, absint($_POST['cache_ttl'] ?? 180))),
                'primary_color'   => sanitize_hex_color($_POST['primary_color'] ?? '#1a365d') ?: '#1a365d',
                'secondary_color' => sanitize_hex_color($_POST['secondary_color'] ?? '#b32d2e') ?: '#b32d2e',
                'accent_color'    => sanitize_hex_color($_POST['accent_color'] ?? '#c9a227') ?: '#c9a227',
                'site_logo_url'   => esc_url_raw($_POST['site_logo_url'] ?? ''),
                'admin_sign_url'  => esc_url_raw($_POST['admin_sign_url'] ?? ''),
                'admin_sign_name' => sanitize_text_field(\WPArtCOA\Security::input_scalar($_POST, 'admin_sign_name')),
                'admin_sign_title'=> sanitize_text_field(\WPArtCOA\Security::input_scalar($_POST, 'admin_sign_title')),
                'cert_footer'     => sanitize_textarea_field(\WPArtCOA\Security::input_scalar($_POST, 'cert_footer')),
                'bio_title'       => sanitize_text_field(\WPArtCOA\Security::input_scalar($_POST, 'bio_title', 'بیوگرافی')) ?: 'بیوگرافی',
                'bio_layout'      => in_array($_POST['bio_layout'] ?? '', ['split', 'stacked'], true) ? $_POST['bio_layout'] : 'split',
                'bio_paragraphs'  => max(1, min(10, absint($_POST['bio_paragraphs'] ?? 2))),
                'auction_require_login' => !empty($_POST['auction_require_login']) ? 1 : 0,
                'auction_proxy' => !empty($_POST['auction_proxy']) ? 1 : 0,
                'auction_payment_days' => max(1, min(30, absint($_POST['auction_payment_days'] ?? 3))),
            ];
            foreach (['show_title', 'show_lead', 'show_facts', 'show_statement', 'show_education', 'show_awards', 'readmore'] as $k) {
                $settings['bio_' . $k] = !empty($_POST['bio_' . $k]) ? 1 : 0;
            }
            update_option(self::OPTION, $settings);
            // Re-init language immediately
            I18n::init();
            \WPArtCOA\Cache::flush_stats();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(I18n::t('settings_saved')) . '</p></div>';
        }

        $s = self::get();
        $dir = I18n::is_fa() ? 'rtl' : 'ltr';
        ?>
        <div class="wrap art-coa-wrap" dir="<?php echo esc_attr($dir); ?>">
            <h1><?php echo esc_html(I18n::t('settings')); ?></h1>
            <form method="post" class="art-coa-form aap-s-3c6951">
                <?php wp_nonce_field('art_coa_settings'); ?>
                <input type="hidden" name="art_coa_save_settings" value="1">

                <h2><?php echo esc_html(I18n::t('general')); ?></h2>

                <p>
                    <label><strong><?php echo esc_html(I18n::t('language')); ?></strong></label><br>
                    <select name="language" class="widefat">
                        <option value="fa" <?php selected($s['language'], 'fa'); ?>><?php echo esc_html(I18n::t('language_fa')); ?></option>
                        <option value="en" <?php selected($s['language'], 'en'); ?>><?php echo esc_html(I18n::t('language_en')); ?></option>
                    </select>
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="jalali_dates" value="1" <?php checked(!empty($s['jalali_dates'])); ?>>
                        <strong><?php echo esc_html(I18n::t('jalali_dates')); ?></strong>
                    </label><br>
                    <span class="description"><?php echo esc_html(I18n::t('jalali_dates_help')); ?></span>
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="enable_seo" value="1" <?php checked(!empty($s['enable_seo'])); ?>>
                        <strong><?php echo esc_html(I18n::t('enable_seo')); ?></strong>
                    </label>
                </p>

                <p>
                    <label><strong><?php echo esc_html(I18n::t('cache_ttl')); ?></strong></label><br>
                    <input type="number" name="cache_ttl" value="<?php echo esc_attr((string) $s['cache_ttl']); ?>" min="60" max="3600" class="widefat">
                </p>

                <h2><?php echo I18n::is_fa() ? 'هویت بصری و گواهی' : 'Brand & Certificate'; ?></h2>
                <p>
                    <label><strong><?php echo I18n::is_fa() ? 'رنگ اصلی' : 'Primary color'; ?></strong></label><br>
                    <input type="color" name="primary_color" value="<?php echo esc_attr($s['primary_color'] ?? '#1a365d'); ?>">
                </p>
                <p>
                    <label><strong><?php echo I18n::is_fa() ? 'رنگ ثانویه' : 'Secondary color'; ?></strong></label><br>
                    <input type="color" name="secondary_color" value="<?php echo esc_attr($s['secondary_color'] ?? '#b32d2e'); ?>">
                </p>
                <p>
                    <label><strong><?php echo I18n::is_fa() ? 'رنگ تأکیدی' : 'Accent color'; ?></strong></label><br>
                    <input type="color" name="accent_color" value="<?php echo esc_attr($s['accent_color'] ?? '#c9a227'); ?>">
                </p>
                <p>
                    <label><strong><?php echo I18n::is_fa() ? 'لوگوی سامانه (URL)' : 'Platform logo URL'; ?></strong></label><br>
                    <input type="url" name="site_logo_url" value="<?php echo esc_attr($s['site_logo_url'] ?? ''); ?>" class="widefat">
                </p>
                <p>
                    <label><strong><?php echo I18n::is_fa() ? 'امضای مدیر سامانه (URL تصویر)' : 'Admin signature image URL'; ?></strong></label><br>
                    <input type="url" name="admin_sign_url" value="<?php echo esc_attr($s['admin_sign_url'] ?? ''); ?>" class="widefat">
                </p>
                <p>
                    <label><strong><?php echo I18n::is_fa() ? 'نام امضاکننده مدیر' : 'Admin signer name'; ?></strong></label><br>
                    <input type="text" name="admin_sign_name" value="<?php echo esc_attr($s['admin_sign_name'] ?? ''); ?>" class="widefat">
                </p>
                <p>
                    <label><strong><?php echo I18n::is_fa() ? 'سمت امضاکننده' : 'Admin signer title'; ?></strong></label><br>
                    <input type="text" name="admin_sign_title" value="<?php echo esc_attr($s['admin_sign_title'] ?? ''); ?>" class="widefat">
                </p>
                <p>
                    <label><strong><?php echo I18n::is_fa() ? 'متن پایین گواهی' : 'Certificate footer text'; ?></strong></label><br>
                    <textarea name="cert_footer" rows="2" class="widefat"><?php echo esc_textarea($s['cert_footer'] ?? ''); ?></textarea>
                </p>

                <h2><?php echo I18n::is_fa() ? 'بخش بیوگرافی هنرمند' : 'Artist biography section'; ?></h2>
                <p><label><strong><?php echo I18n::is_fa() ? 'عنوان بخش' : 'Section title'; ?></strong></label><br>
                    <input type="text" name="bio_title" value="<?php echo esc_attr((string) $s['bio_title']); ?>" class="widefat"></p>
                <p><label><strong><?php echo I18n::is_fa() ? 'چیدمان' : 'Layout'; ?></strong></label><br>
                    <select name="bio_layout" class="widefat">
                        <option value="split" <?php selected($s['bio_layout'], 'split'); ?>><?php echo I18n::is_fa() ? 'متن و مشخصات کنار هم' : 'Text and facts side by side'; ?></option>
                        <option value="stacked" <?php selected($s['bio_layout'], 'stacked'); ?>><?php echo I18n::is_fa() ? 'زیر هم' : 'Stacked'; ?></option>
                    </select></p>
                <p>
                    <?php foreach (['show_title' => ['نمایش عنوان', 'Title'], 'show_lead' => ['معرفی کوتاه', 'Short intro'], 'show_facts' => ['مشخصات', 'Facts'], 'show_statement' => ['بیانیهٔ هنری', 'Statement'], 'show_education' => ['تحصیلات', 'Education'], 'show_awards' => ['جوایز', 'Awards'], 'readmore' => ['دکمهٔ ادامهٔ بیوگرافی', 'Read more']] as $k => $l): ?>
                        <label style="display:inline-block;margin-inline-end:16px"><input type="checkbox" name="bio_<?php echo esc_attr($k); ?>" value="1" <?php checked(!empty($s['bio_' . $k])); ?>> <?php echo esc_html(I18n::is_fa() ? $l[0] : $l[1]); ?></label>
                    <?php endforeach; ?>
                </p>
                <p><label><strong><?php echo I18n::is_fa() ? 'پاراگراف‌های نمایان پیش از «ادامه»' : 'Visible paragraphs before "read more"'; ?></strong></label><br>
                    <input type="number" name="bio_paragraphs" value="<?php echo esc_attr((string) $s['bio_paragraphs']); ?>" min="1" max="10" class="small-text"></p>

                <h2><?php echo I18n::is_fa() ? 'حراج' : 'Auctions'; ?></h2>
                <p><label><input type="checkbox" name="auction_require_login" value="1" <?php checked(!empty($s['auction_require_login'])); ?>>
                    <strong><?php echo I18n::is_fa() ? 'ثبت پیشنهاد فقط برای کاربران واردشده' : 'Only signed-in users can bid'; ?></strong></label><br>
                    <span class="description"><?php echo I18n::is_fa() ? 'پیشنهاد با ایمیل تأییدشدهٔ حساب ثبت می‌شود و پیشنهاد جعلی سخت‌تر است. پیشنهاد فروشنده روی حراج خودش همیشه رد می‌شود.' : 'Bids use the verified account e-mail. Sellers can never bid on their own auction.'; ?></span></p>
                <p><label><input type="checkbox" name="auction_proxy" value="1" <?php checked(!empty($s['auction_proxy'])); ?>>
                    <strong><?php echo I18n::is_fa() ? 'پیشنهاد خودکار تا سقف (Proxy bidding)' : 'Proxy (maximum) bidding'; ?></strong></label><br>
                    <span class="description"><?php echo I18n::is_fa() ? 'مبلغ واردشده توسط پیشنهاددهنده «سقف» اوست؛ سیستم فقط به‌اندازهٔ لازم و با گام افزایش پیشنهاد می‌دهد و سقف هرگز نمایش داده نمی‌شود.' : 'The amount a bidder enters is their hidden maximum; the system bids only as much as needed, one increment at a time.'; ?></span></p>
                <p><label><strong><?php echo I18n::is_fa() ? 'مهلت پرداخت برنده (روز)' : 'Winner payment deadline (days)'; ?></strong></label><br>
                    <input type="number" name="auction_payment_days" value="<?php echo esc_attr((string) ($s['auction_payment_days'] ?? 3)); ?>" min="1" max="30" class="small-text"></p>

                <p>
                    <button type="submit" class="button button-primary"><?php echo esc_html(I18n::t('save_settings')); ?></button>
                </p>
            </form>
            <?php if (!empty($s['jalali_dates'])): ?>
                <p class="notice notice-info inline aap-s-a6cea7"><?php echo esc_html(I18n::t('settings_jalali_on')); ?> — <?php echo esc_html(\WPArtCOA\Dates::format(gmdate('Y-m-d H:i:s'), true)); ?></p>
            <?php endif; ?>

            <div class="art-coa-box aap-s-ac1c8b">
                <h2><?php echo esc_html(I18n::t('shortcodes')); ?></h2>
                <p><code>[art_coa_verify]</code></p>
                <p><code>[art_coa_certificate serial="COA-2026-XXXXXX"]</code></p>
                <p><code>[art_coa_calendar]</code> / <code>[art_coa_timeline]</code></p>
                <p><code>[art_coa_directory]</code></p>
                <p><code>[art_coa_register]</code> — <?php echo esc_html(I18n::t('reg_page')); ?></p>
                <p><code>[art_coa_certificate_full serial="COA-..."]</code></p>
                <p><code>[art_coa_sitemap]</code> — <a href="<?php echo esc_url(home_url('/art-coa/site-map/')); ?>" target="_blank"><?php echo I18n::is_fa() ? 'نقشه سایت' : 'Sitemap'; ?></a> · <a href="<?php echo esc_url(home_url('/art-coa-sitemap.xml')); ?>" target="_blank">XML</a></p>
                <p><code>[art_coa_calls]</code> — <a href="<?php echo esc_url(home_url('/art-coa/calls/')); ?>" target="_blank"><?php echo esc_html(I18n::t('calls_title')); ?></a></p>
                <p><code>[art_coa_auction]</code> — <a href="<?php echo esc_url(home_url('/art-coa/auctions/')); ?>" target="_blank"><?php echo esc_html(I18n::t('auction_title')); ?></a></p>
                <p><code>[art_coa_secondary]</code> — <a href="<?php echo esc_url(home_url('/art-coa/secondary-market/')); ?>" target="_blank"><?php echo esc_html(I18n::t('secondary_market')); ?></a></p>
                <p><code>[art_coa_shop]</code> — <a href="<?php echo esc_url(home_url('/art-coa/shop/')); ?>" target="_blank"><?php echo esc_html(I18n::t('shop')); ?></a></p>
                <p><code>[art_coa_portfolio]</code> — <?php echo esc_html(I18n::t('portfolio')); ?></p>
                <p><code>[art_coa_member_dashboard]</code> — <?php echo esc_html(I18n::t('member_dashboard')); ?></p>
                <?php
                $reg = get_option('wp_art_coa_page_register');
                $mem = get_option('wp_art_coa_page_member');
                if ($reg) echo '<p><a href="' . esc_url(get_permalink($reg)) . '" target="_blank">' . esc_html(I18n::t('reg_page')) . '</a></p>';
                if ($mem) echo '<p><a href="' . esc_url(get_permalink($mem)) . '" target="_blank">' . esc_html(I18n::t('member_dashboard')) . '</a></p>';
                ?>
            </div>
        </div>
        <?php
    }
}
