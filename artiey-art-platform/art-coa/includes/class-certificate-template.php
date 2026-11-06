<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Certificate;

defined('ABSPATH') || exit;

/**
 * Dynamic printable / public Certificate of Authenticity.
 * Layers: gallery brand + gallery signature + site logo + admin signature.
 */
final class CertificateTemplate
{
    public static function init(): void
    {
        add_shortcode('art_coa_certificate_full', [self::class, 'shortcode']);
        add_action('template_redirect', [self::class, 'print_view']);
    }

    public static function print_view(): void
    {
        if (get_query_var('art_coa_type') !== 'certificate') {
            return;
        }
        // Enhanced profile already exists; we enhance via filter in profiles or replace render
    }

    public static function shortcode($atts = []): string
    {
        $atts = shortcode_atts(['serial' => ''], $atts, 'art_coa_certificate_full');
        $serial = Security::sanitize_serial($atts['serial'] ?: (string) ($_GET['serial'] ?? ''));
        if ($serial === '') {
            return '<p class="art-coa-empty">' . esc_html(I18n::t('serial')) . '</p>';
        }
        return self::render($serial);
    }

    public static function render(string $serial, bool $print_mode = false): string
    {
        $result = Certificate::verify($serial);
        $brand = Branding::settings();

        if (empty($result['valid'])) {
            return '<div class="art-coa-cert-doc invalid"><h2>❌ ' . esc_html(I18n::t('cert_invalid')) . '</h2><p>' . esc_html($result['message'] ?? '') . '</p></div>';
        }

        $d = $result['data'];
        $gallery = self::load_gallery_for_artwork((int) ($d['artwork_id'] ?? 0));
        $primary = ($gallery && !empty($gallery->brand_color)) ? $gallery->brand_color : $brand['primary_color'];
        $secondary = ($gallery && !empty($gallery->brand_color_2)) ? $gallery->brand_color_2 : $brand['secondary_color'];

        $gallery_logo = $gallery->logo_url ?? '';
        $gallery_sign = $gallery->signature_url ?? '';
        $gallery_sign_name = $gallery->signature_name ?? ($gallery->name ?? '');
        $gallery_name = $gallery->name ?? '';

        $site_logo = $brand['site_logo_url'];
        $admin_sign = $brand['admin_sign_url'];
        $admin_name = $brand['admin_sign_name'] ?: get_bloginfo('name');
        $admin_title = $brand['admin_sign_title'] ?: (I18n::is_fa() ? 'مدیر سامانه' : 'Platform Administrator');
        $footer = $brand['cert_footer'] ?: (I18n::is_fa()
            ? 'این گواهی به‌صورت دیجیتال صادر شده و با امضای رمزنگاری‌شده قابل استعلام است.'
            : 'This certificate is digitally issued and verifiable via cryptographic signature.');

        $dir = I18n::is_fa() ? 'rtl' : 'ltr';
        $issued = Dates::format($d['issued_at_utc'] ?? null, true);

        ob_start();
        ?>
        <div class="art-coa-cert-doc <?php echo $print_mode ? 'print-mode' : ''; ?>" dir="<?php echo esc_attr($dir); ?>"
             style="--cert-primary:<?php echo esc_attr($primary); ?>;--cert-secondary:<?php echo esc_attr($secondary); ?>">

            <div class="art-coa-cert-frame">
                <!-- Header logos -->
                <div class="art-coa-cert-header">
                    <div class="art-coa-cert-logo-slot">
                        <?php if ($site_logo): ?>
                            <img src="<?php echo esc_url($site_logo); ?>" alt="site" class="art-coa-cert-logo">
                        <?php else: ?>
                            <span class="art-coa-cert-logo-text"><?php echo esc_html(get_bloginfo('name')); ?></span>
                        <?php endif; ?>
                        <span class="art-coa-cert-logo-label"><?php echo esc_html(I18n::is_fa() ? 'سامانه' : 'Platform'); ?></span>
                    </div>
                    <div class="art-coa-cert-title-block">
                        <h1><?php echo esc_html(I18n::is_fa() ? 'گواهی اصالت اثر هنری' : 'Certificate of Authenticity'); ?></h1>
                        <p class="art-coa-cert-serial"><code><?php echo esc_html($d['serial_number']); ?></code></p>
                    </div>
                    <div class="art-coa-cert-logo-slot">
                        <?php if ($gallery_logo): ?>
                            <img src="<?php echo esc_url($gallery_logo); ?>" alt="gallery" class="art-coa-cert-logo">
                        <?php elseif ($gallery_name): ?>
                            <span class="art-coa-cert-logo-text"><?php echo esc_html($gallery_name); ?></span>
                        <?php endif; ?>
                        <?php if ($gallery_name): ?>
                            <span class="art-coa-cert-logo-label"><?php echo esc_html(I18n::t('gallery')); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="art-coa-cert-badge valid">✓ <?php echo esc_html(I18n::t('cert_valid')); ?></div>

                <?php if (!empty($d['image_url'])): ?>
                <div class="art-coa-cert-artwork-img aap-s-0dda54">
                    <img src="<?php echo esc_url($d['image_url']); ?>" alt="<?php echo esc_attr($d['artwork_title'] ?? ''); ?>" loading="lazy"
                         style="display:block;margin:0 auto;max-width:min(420px,90%);max-height:300px;width:auto;height:auto;object-fit:contain;">
                    <span class="art-coa-cert-artwork-caption aap-s-eecd49"><?php echo esc_html($d['artwork_title'] ?? ''); ?></span>
                </div>
                <?php endif; ?>

                <div class="art-coa-cert-qr-hash">
                    <div class="art-coa-cert-qr">
                        <?php
                        $qr_url = (string) ($d['qr_code_data'] ?? home_url('/art-coa/certificate/' . rawurlencode($d['serial_number'])));
                        // Local, offline QR (COA Pro script): no third-party request, no data leaves the site.
                        wp_enqueue_script('wp-coa-pro-qr');
                        ?>
                        <div class="art-coa-qr-img" data-wp-coa-qr="<?php echo esc_attr($qr_url); ?>" data-size="140" role="img" aria-label="QR"></div>
                        <span><?php echo esc_html(I18n::is_fa() ? 'اسکن برای استعلام' : 'Scan to verify'); ?></span>
                    </div>
                    <div class="art-coa-cert-hash">
                        <strong><?php echo esc_html(I18n::is_fa() ? 'اثر انگشت دیجیتال گواهی' : 'Certificate digital fingerprint'); ?></strong>
                        <code class="art-coa-hash-code"><?php echo esc_html($d['hmac_fingerprint'] ?? $d['hmac_signature'] ?? '—'); ?></code>
                        <p class="meta"><?php echo esc_html(I18n::is_fa()
                            ? 'امضای یکپارچگی و دفتر ثبت رویدادها در COA Pro؛ هر دستکاری در داده گواهی را نامعتبر می‌کند.'
                            : 'Integrity signature and event ledger in COA Pro; any data tampering invalidates the certificate.'); ?></p>
                    </div>
                </div>

                <table class="art-coa-cert-table">
                    <tr>
                        <th><?php echo esc_html(I18n::t('artwork')); ?></th>
                        <td><?php echo esc_html($d['artwork_title'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html(I18n::t('artist')); ?></th>
                        <td><?php echo esc_html($d['artist_name'] ?? ''); ?></td>
                    </tr>
                    <?php if (!empty($d['year_created'])): ?>
                    <tr>
                        <th><?php echo esc_html(I18n::t('year')); ?></th>
                        <td><?php echo esc_html((string) $d['year_created']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($d['medium'])): ?>
                    <tr>
                        <th><?php echo esc_html(I18n::t('medium')); ?></th>
                        <td><?php echo esc_html($d['medium']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($d['dimensions'])): ?>
                    <tr>
                        <th><?php echo esc_html(I18n::t('dimensions')); ?></th>
                        <td><?php echo esc_html($d['dimensions']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php echo esc_html(I18n::t('owner')); ?></th>
                        <td><?php echo esc_html($d['issued_to_name'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html(I18n::t('issued_at')); ?></th>
                        <td><?php echo esc_html($issued); ?></td>
                    </tr>
                    <?php if ($gallery_name): ?>
                    <tr>
                        <th><?php echo esc_html(I18n::t('gallery')); ?></th>
                        <td><?php echo esc_html($gallery_name); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <!-- Dual signatures -->
                <div class="art-coa-cert-signatures">
                    <div class="art-coa-cert-sign-box">
                        <?php if ($gallery_sign): ?>
                            <img src="<?php echo esc_url($gallery_sign); ?>" alt="gallery signature" class="art-coa-cert-sign-img">
                        <?php else: ?>
                            <div class="art-coa-cert-sign-placeholder"></div>
                        <?php endif; ?>
                        <div class="art-coa-cert-sign-line"></div>
                        <strong><?php echo esc_html($gallery_sign_name ?: I18n::t('gallery')); ?></strong>
                        <span><?php echo esc_html(I18n::is_fa() ? 'امضا و مهر گالری' : 'Gallery signature & seal'); ?></span>
                    </div>
                    <div class="art-coa-cert-sign-box">
                        <?php if ($admin_sign): ?>
                            <img src="<?php echo esc_url($admin_sign); ?>" alt="admin signature" class="art-coa-cert-sign-img">
                        <?php else: ?>
                            <div class="art-coa-cert-sign-placeholder"></div>
                        <?php endif; ?>
                        <div class="art-coa-cert-sign-line"></div>
                        <strong><?php echo esc_html($admin_name); ?></strong>
                        <span><?php echo esc_html($admin_title); ?></span>
                    </div>
                </div>

                <div class="art-coa-cert-footer">
                    <p><?php echo esc_html($footer); ?></p>
                    <p class="art-coa-cert-verify-url">
                        <?php echo esc_html(I18n::is_fa() ? 'استعلام آنلاین:' : 'Verify online:'); ?>
                        <a href="<?php echo esc_url(home_url('/art-coa/certificate/' . rawurlencode($d['serial_number']))); ?>">
                            <?php echo esc_html(home_url('/art-coa/certificate/' . rawurlencode($d['serial_number']))); ?>
                        </a>
                    </p>
                </div>

                <div class="art-coa-cert-actions no-print">
                    <button type="button" class="button button-primary" onclick="window.print()"><?php echo esc_html(I18n::is_fa() ? 'چاپ / PDF' : 'Print / PDF'); ?></button>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function load_gallery_for_artwork(int $artwork_id): ?object
    {
        if ($artwork_id <= 0) {
            return null;
        }
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT g.* FROM ' . Database::table('galleries') . ' g
             INNER JOIN ' . Database::table('artworks') . ' a ON a.gallery_id = g.id
             WHERE a.id = %d LIMIT 1',
            $artwork_id
        )) ?: null;
    }

    /**
     * Ensure gallery table has brand/signature columns.
     */
    public static function maybe_migrate_gallery_columns(): void
    {
        global $wpdb;
        $table = Database::table('galleries');
        $cols = $wpdb->get_col("DESCRIBE {$table}", 0);
        $add = [
            'brand_color'     => "VARCHAR(20) NULL",
            'brand_color_2'   => "VARCHAR(20) NULL",
            'signature_url'   => "TEXT NULL",
            'signature_name'  => "VARCHAR(190) NULL",
        ];
        foreach ($add as $col => $def) {
            if (!in_array($col, $cols, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$col} {$def}");
            }
        }
    }
}
