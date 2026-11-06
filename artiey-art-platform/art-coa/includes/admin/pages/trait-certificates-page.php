<?php
declare(strict_types=1);

namespace WPArtCOA\Admin\Pages;

use WPArtCOA\Database;
use WPArtCOA\Entities\Artist;
use WPArtCOA\Entities\Artwork;
use WPArtCOA\Entities\Certificate;
use WPArtCOA\Entities\Collector;
use WPArtCOA\Entities\Curator;
use WPArtCOA\Entities\Gallery;
use WPArtCOA\Entities\Exhibition;
use WPArtCOA\I18n;
use WPArtCOA\Dates;

defined('ABSPATH') || exit;

/** Admin screens split out of Menus (used as a trait so existing self:: calls keep working). */
trait CertificatesPage
{
    public static function page_certificates(): void
    {
        self::handle_certificate_actions();
        global $wpdb;
        // COA Pro is the single certificate engine; this screen lists its records and links them to Art COA artworks.
        $coa_table = $wpdb->prefix . 'coa_certificates';
        $rows = $wpdb->get_results(
            'SELECT c.*, m.artwork_id AS linked_artwork_id, a.title AS linked_title
             FROM ' . $coa_table . ' c
             LEFT JOIN ' . Database::table('certificates') . ' m ON m.coa_id = c.id
             LEFT JOIN ' . Database::table('artworks') . ' a ON a.id = m.artwork_id
             ORDER BY c.id DESC LIMIT 100'
        ) ?: [];
        $artworks = Artwork::all();
        $issue_url = admin_url('admin.php?page=wp-coa-pro');
        ?>
        <div class="wrap art-coa-wrap">
            <h1><?php echo esc_html(I18n::t('certificates')); ?>
                <a class="button button-primary" href="<?php echo esc_url($issue_url); ?>">+ <?php echo esc_html(I18n::t('add_certificate')); ?></a></h1>
            <p class="description"><?php echo esc_html(I18n::t('cert_engine_note')); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th><?php echo esc_html(I18n::t('serial')); ?></th>
                    <th><?php echo esc_html(I18n::t('artwork')); ?></th>
                    <th><?php echo esc_html(I18n::t('artist')); ?></th>
                    <th><?php echo esc_html(I18n::t('cert_linked_artwork')); ?></th>
                    <th><?php echo esc_html(I18n::t('status')); ?></th>
                    <th><?php echo esc_html(I18n::t('date')); ?></th>
                    <th><?php echo esc_html(I18n::t('actions')); ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="aap-center"><?php echo esc_html(I18n::t('cert_none')); ?></td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><code><?php echo esc_html($r->certificate_code); ?></code></td>
                        <td><?php echo esc_html($r->artwork_title); ?></td>
                        <td><?php echo esc_html($r->artist_name); ?></td>
                        <td>
                            <form method="post" class="aap-inline">
                                <?php wp_nonce_field('art_coa_link_cert'); ?>
                                <input type="hidden" name="art_coa_action" value="link_cert">
                                <input type="hidden" name="coa_id" value="<?php echo (int) $r->id; ?>">
                                <label class="aap-sr-only" for="lk<?php echo (int) $r->id; ?>"><?php echo esc_html(I18n::t('cert_linked_artwork')); ?></label>
                                <select id="lk<?php echo (int) $r->id; ?>" name="artwork_id">
                                    <option value="">—</option>
                                    <?php foreach ($artworks as $art): ?>
                                        <option value="<?php echo (int) $art->id; ?>"<?php selected((int) $r->linked_artwork_id, (int) $art->id); ?>><?php echo esc_html($art->title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button button-small"><?php echo esc_html(I18n::t('cert_link')); ?></button>
                            </form>
                        </td>
                        <td><span class="art-badge art-badge-<?php echo esc_attr($r->status === 'active' ? 'approved' : 'rejected'); ?>"><?php echo esc_html($r->status); ?></span></td>
                        <td><?php echo esc_html((string) $r->issue_date); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(home_url('/art-coa/certificate/' . rawurlencode($r->certificate_code))); ?>" target="_blank" rel="noopener"><?php echo esc_html(I18n::t('verify')); ?></a>
                            <a class="button button-small" href="<?php echo esc_url($issue_url); ?>"><?php echo esc_html(I18n::t('hub_manage')); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function handle_certificate_actions(): void
    {
        if (empty($_POST['art_coa_action']) || $_POST['art_coa_action'] !== 'link_cert' || !current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('art_coa_link_cert');
        $ok = \WPArtCOA\CertificateMirror::link(absint($_POST['coa_id'] ?? 0), absint($_POST['artwork_id'] ?? 0));
        echo '<div class="notice notice-' . ($ok ? 'success' : 'error') . '"><p>' . esc_html(I18n::t($ok ? 'cert_linked' : 'error')) . '</p></div>';
    }
}
