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
trait ArtworksPage
{
    public static function page_artworks(): void
    {
        self::handle_artwork_moderation();
        self::handle_entity('artwork', Artwork::class);
        $edit     = self::editing(Artwork::class);
        $artworks = Artwork::all();
        $currencies = ['IRR' => 'IRR / ریال', 'IRT' => 'IRT / تومان', 'USD' => 'USD', 'EUR' => 'EUR'];
        $statuses = [
            'pending' => I18n::t('status_pending'), 'approved' => I18n::t('status_approved'), 'published' => I18n::t('published'),
            'draft' => I18n::t('draft'), 'rejected' => I18n::t('status_rejected'), 'sold' => I18n::t('cat_sold'),
        ];
        ?>
        <div class="wrap art-coa-wrap">
            <h1><?php echo esc_html(I18n::t('artworks')); ?> <button type="button" class="button button-primary" onclick="document.getElementById('art-form-artwork').style.display='block'">+ <?php echo esc_html(I18n::t('add_artwork')); ?></button></h1>
            <?php
            self::form_open('artwork', 'artwork', $edit, $edit ? $edit->title : I18n::t('add_artwork'));
            if (!$edit) {
                echo '<input type="hidden" name="status" value="pending">';
            }
            self::field('title', I18n::t('title'), $edit->title ?? '', ['required' => true]);
            self::select('artist_id', I18n::t('artist'), self::artist_options(), $edit->artist_id ?? '', true);
            self::select('gallery_id', I18n::t('gallery'), self::gallery_options(), $edit->gallery_id ?? '');
            self::field('medium', I18n::t('medium'), $edit->medium ?? '');
            self::field('year_created', I18n::t('year_created'), $edit->year_created ?? '', ['type' => 'number']);
            self::field('dimensions', I18n::t('dimensions'), $edit->dimensions ?? '');
            echo \WPArtCOA\Media::picker('image', $edit, ['label' => I18n::t('artwork_image'), 'shape' => 'portrait', 'help' => I18n::t('help_artwork_image')]); // phpcs:ignore
            echo \WPArtCOA\Media::multi_picker('gallery_ids', $edit->gallery_ids ?? '', ['label' => I18n::t('artwork_more_images'), 'help' => I18n::t('help_artwork_more')]); // phpcs:ignore
            self::checkbox('for_sale', I18n::t('for_sale'), !empty($edit->for_sale));
            self::field('price', I18n::t('price'), $edit->price ?? '', ['type' => 'number', 'attrs' => 'step="any" min="0" placeholder="0"']);
            self::select('currency', I18n::t('currency'), $currencies, $edit->currency ?? 'IRR');
            self::field('sale_note', I18n::t('sale_note'), $edit->sale_note ?? '', ['type' => 'textarea', 'rows' => 2]);
            self::field('description', I18n::t('description'), $edit->description ?? '', ['type' => 'textarea']);
            if ($edit) {
                self::select('status', I18n::t('status'), $statuses, $edit->status);
            }
            self::form_close('artwork', $edit);
            echo '<form method="post" class="aap-mt-2">' . wp_nonce_field('art_coa_moderate', '_wpnonce', true, false)
                . '<input type="hidden" name="art_coa_action" value="sync_cpt"><input type="hidden" name="item_id" value="0">'
                . '<button class="button">' . esc_html(I18n::t('sync_cpt')) . '</button> <span class="description">' . esc_html(I18n::t('sync_cpt_help')) . '</span></form>';
            if (\WPArtCOA\WooBridge::active()) {
                echo '<form method="post" class="aap-s-dce500">' . wp_nonce_field('art_coa_moderate', '_wpnonce', true, false)
                    . '<input type="hidden" name="art_coa_action" value="wc_sync_all"><input type="hidden" name="item_id" value="0">'
                    . '<button class="button">' . esc_html(I18n::t('wc_sync_all')) . '</button></form>';
            }
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th class="aap-s-a57414">ID</th><th class="aap-s-191371"></th><th>عنوان</th><th>هنرمند</th><th>سال</th><th>وضعیت</th><?php if (\WPArtCOA\WooBridge::active()): ?><th>WooCommerce</th><?php endif; ?><th><?php echo esc_html(I18n::t('actions')); ?></th></tr></thead>
                <tbody>
                <?php if (!$artworks): ?>
                    <tr><td colspan="8" class="aap-center">اثری ثبت نشده</td></tr>
                <?php else: foreach ($artworks as $art): ?>
                    <tr>
                        <td><?php echo (int)$art->id; ?></td>
                        <td><?php echo self::thumb($art, 'image'); // phpcs:ignore ?></td>
                        <td><strong><?php echo esc_html($art->title); ?></strong>
                            <br><a href="<?php echo esc_url(home_url('/art-coa/artwork/' . $art->slug)); ?>" target="_blank"><?php echo esc_html(I18n::t('view_page')); ?></a>
                        </td>
                        <td><?php echo esc_html($art->artist_name ?: ($art->first_name.' '.$art->last_name)); ?></td>
                        <td><?php echo esc_html((string)$art->year_created); ?></td>
                        <td><span class="art-badge art-badge-<?php echo esc_attr($art->status); ?>"><?php echo esc_html($art->status); ?></span></td>
                        <?php if (\WPArtCOA\WooBridge::active()): ?><td><?php echo \WPArtCOA\WooBridge::admin_cell($art); // phpcs:ignore ?></td><?php endif; ?>
                        <td>
                            <a class="button button-small" href="<?php echo self::edit_url((int) $art->id); // phpcs:ignore ?>"><?php echo esc_html(I18n::t('edit')); ?></a>
                            <?php if (in_array($art->status, ['pending','draft','rejected'], true)): ?>
                                <form method="post" class="aap-inline"><?php wp_nonce_field('art_coa_moderate'); ?>
                                    <input type="hidden" name="art_coa_action" value="approve_artwork">
                                    <input type="hidden" name="item_id" value="<?php echo (int)$art->id; ?>">
                                    <button class="button button-small button-primary"><?php echo esc_html(I18n::t('approve')); ?></button>
                                </form>
                                <form method="post" class="aap-inline"><?php wp_nonce_field('art_coa_moderate'); ?>
                                    <input type="hidden" name="art_coa_action" value="reject_artwork">
                                    <input type="hidden" name="item_id" value="<?php echo (int)$art->id; ?>">
                                    <button class="button button-small"><?php echo esc_html(I18n::t('reject')); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php
            \WPArtCOA\ArtistPortfolio::maybe_migrate();
            global $wpdb;
            $pf_items = $wpdb->get_results(
                'SELECT p.*, ar.display_name, ar.first_name, ar.last_name
                 FROM ' . \WPArtCOA\ArtistPortfolio::table() . ' p
                 LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = p.artist_id
                 ORDER BY p.id DESC LIMIT 50'
            ) ?: [];
            ?>
            <h2 class="aap-s-b6e5ef"><?php echo esc_html(I18n::t('portfolio_moderation')); ?></h2>
            <p class="description"><?php echo esc_html(I18n::t('portfolio_moderation_help')); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>ID</th>
                    <th><?php echo esc_html(I18n::t('image_url')); ?></th>
                    <th><?php echo esc_html(I18n::t('title')); ?></th>
                    <th><?php echo esc_html(I18n::t('artist')); ?></th>
                    <th><?php echo esc_html(I18n::t('status')); ?></th>
                    <th><?php echo esc_html(I18n::t('actions')); ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$pf_items): ?>
                    <tr><td colspan="6" class="aap-center">—</td></tr>
                <?php else: foreach ($pf_items as $pfi): ?>
                    <tr>
                        <td><?php echo (int)$pfi->id; ?></td>
                        <td><img src="<?php echo esc_url(\WPArtCOA\Media::url($pfi, 'image', 'thumbnail')); ?>" style="max-height:60px;border-radius:4px" alt=""></td>
                        <td><?php echo esc_html($pfi->title ?: '—'); ?></td>
                        <td><?php echo esc_html($pfi->display_name ?: trim(($pfi->first_name ?? '') . ' ' . ($pfi->last_name ?? ''))); ?></td>
                        <td><span class="art-badge"><?php echo esc_html($pfi->status); ?></span></td>
                        <td>
                            <?php if ($pfi->status === 'pending'): ?>
                                <form method="post" class="aap-inline"><?php wp_nonce_field('art_coa_moderate'); ?>
                                    <input type="hidden" name="art_coa_action" value="approve_portfolio">
                                    <input type="hidden" name="item_id" value="<?php echo (int)$pfi->id; ?>">
                                    <button class="button button-small button-primary"><?php echo esc_html(I18n::t('approve')); ?></button>
                                </form>
                                <form method="post" class="aap-inline"><?php wp_nonce_field('art_coa_moderate'); ?>
                                    <input type="hidden" name="art_coa_action" value="reject_portfolio">
                                    <input type="hidden" name="item_id" value="<?php echo (int)$pfi->id; ?>">
                                    <button class="button button-small"><?php echo esc_html(I18n::t('reject')); ?></button>
                                </form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function handle_artwork_moderation(): void
    {
        if (empty($_POST['art_coa_action']) || !current_user_can('manage_options')) {
            return;
        }
        $action = sanitize_key(\WPArtCOA\Security::input_scalar($_POST, 'art_coa_action'));
        if (!in_array($action, ['approve_artwork', 'reject_artwork', 'approve_portfolio', 'reject_portfolio', 'wc_sync_artwork', 'wc_sync_all', 'sync_cpt'], true)) {
            return;
        }
        check_admin_referer('art_coa_moderate');
        $id = absint($_POST['item_id'] ?? 0);
        if ($action === 'sync_cpt') {
            $n = \WPArtCOA\ContentSync::sync_all();
            echo '<div class="notice notice-success"><p>' . esc_html(I18n::t('sync_cpt_done', [
                'a' => $n['artists'], 'g' => $n['galleries'], 'w' => $n['artworks'], 'e' => $n['exhibitions'],
            ])) . '</p></div>';
        } elseif ($action === 'wc_sync_artwork') {
            $r = \WPArtCOA\WooBridge::sync($id);
            echo '<div class="notice notice-' . (!empty($r['success']) ? 'success' : 'error') . '"><p>' . esc_html($r['message']) . '</p></div>';
        } elseif ($action === 'wc_sync_all') {
            $n = \WPArtCOA\WooBridge::sync_all();
            echo '<div class="notice notice-success"><p>' . esc_html(I18n::t('wc_synced_n', ['n' => $n])) . '</p></div>';
        } elseif ($action === 'approve_artwork') {
            \WPArtCOA\Entities\Artwork::set_status($id, 'approved');
            echo '<div class="notice notice-success"><p>' . esc_html(I18n::t('artwork_approved')) . '</p></div>';
        } elseif ($action === 'reject_artwork') {
            \WPArtCOA\Entities\Artwork::set_status($id, 'rejected');
            echo '<div class="notice notice-warning"><p>' . esc_html(I18n::t('artwork_rejected')) . '</p></div>';
        } elseif ($action === 'approve_portfolio') {
            \WPArtCOA\ArtistPortfolio::set_status($id, 'approved');
            echo '<div class="notice notice-success"><p>' . esc_html(I18n::t('portfolio_approved')) . '</p></div>';
        } elseif ($action === 'reject_portfolio') {
            \WPArtCOA\ArtistPortfolio::set_status($id, 'rejected');
            echo '<div class="notice notice-warning"><p>' . esc_html(I18n::t('portfolio_rejected')) . '</p></div>';
        }
    }
}
