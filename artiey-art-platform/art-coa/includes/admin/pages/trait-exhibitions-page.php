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
trait ExhibitionsPage
{
    public static function page_exhibitions(): void
    {
        self::handle_exhibition_form();
        $edit  = self::editing(Exhibition::class);
        $items = Exhibition::all();
        $cats = Exhibition::categorized();
        $curators = Curator::all();
        $type_labels = [
            'exhibition' => I18n::t('exhibition'),
            'artfair'    => I18n::t('artfair'),
            'auction'    => I18n::t('auction'),
        ];
        $render_ex_table = static function (array $list, string $heading) use ($type_labels): void {
            echo '<h2 class="art-coa-dash-heading">' . esc_html($heading) . ' (' . count($list) . ')</h2>';
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
            echo '<th class="aap-s-191371"></th>';
            echo '<th>' . esc_html(I18n::t('title')) . '</th>';
            echo '<th>' . esc_html(I18n::t('type')) . '</th>';
            echo '<th>' . esc_html(I18n::t('related_gallery')) . '</th>';
            echo '<th>' . esc_html(I18n::t('related_artists')) . '</th>';
            echo '<th>' . esc_html(I18n::t('start_date')) . '</th>';
            echo '<th>' . esc_html(I18n::t('end_date')) . '</th>';
            echo '<th>' . esc_html(I18n::t('status')) . '</th>';
            echo '<th>' . esc_html(I18n::t('actions')) . '</th>';
            echo '</tr></thead><tbody>';
            if (!$list) {
                echo '<tr><td colspan="9" class="aap-center">' . esc_html(I18n::t('no_exhibitions')) . '</td></tr>';
            } else {
                foreach ($list as $e) {
                    $artists = Exhibition::related_artists((int) $e->id);
                    echo '<tr>';
                    echo '<td>' . self::thumb($e, 'poster') . '</td>';
                    echo '<td><strong>' . esc_html($e->title) . '</strong>';
                    if (!empty($e->subtitle)) {
                        echo '<br><small>' . esc_html($e->subtitle) . '</small>';
                    }
                    if (!empty($e->artist_label)) {
                        echo '<br><small>' . esc_html(I18n::t('artist')) . ': ' . esc_html($e->artist_label) . '</small>';
                    }
                    echo '</td>';
                    echo '<td>' . esc_html($type_labels[$e->event_type ?? 'exhibition'] ?? ($e->event_type ?? '')) . '</td>';
                    echo '<td>';
                    if (!empty($e->gallery_name) && !empty($e->gallery_slug)) {
                        echo '<a href="' . esc_url(home_url('/art-coa/gallery/' . $e->gallery_slug)) . '" target="_blank">' . esc_html($e->gallery_name) . '</a>';
                    } else {
                        echo esc_html($e->gallery_name ?? I18n::t('none'));
                    }
                    echo '</td>';
                    echo '<td>';
                    if ($artists) {
                        $links = [];
                        foreach ($artists as $ar) {
                            $nm = $ar->display_name ?: trim(($ar->first_name ?? '') . ' ' . ($ar->last_name ?? ''));
                            $links[] = '<a href="' . esc_url(\WPArtCOA\ContentSync::artist_url((string) $ar->slug)) . '" target="_blank">' . esc_html($nm) . '</a>';
                        }
                        echo implode('، ', $links);
                    } elseif (!empty($e->artist_label)) {
                        echo esc_html($e->artist_label);
                    } else {
                        echo esc_html(I18n::t('none'));
                    }
                    echo '</td>';
                    echo '<td>' . esc_html(Dates::format($e->start_date ?? null)) . '</td>';
                    echo '<td>' . esc_html(Dates::format($e->end_date ?? null)) . '</td>';
                    echo '<td><span class="art-badge art-badge-' . esc_attr((string) $e->status) . '">' . esc_html((string) $e->status) . '</span></td>';
                    echo '<td><a class="button button-small" href="' . esc_url(add_query_arg('edit', (int) $e->id)) . '">' . esc_html(I18n::t('edit')) . '</a> ';
                    echo '<a class="button button-small" href="' . esc_url(home_url('/art-coa/exhibition/' . $e->slug)) . '" target="_blank">' . esc_html(I18n::t('view_page')) . '</a>';
                    if (in_array($e->status, ['pending', 'draft', 'rejected'], true)) {
                        foreach (['approve_exhibition' => ['approve', 'button-primary'], 'reject_exhibition' => ['reject', '']] as $act => [$lbl, $cls]) {
                            if ($act === 'reject_exhibition' && $e->status === 'rejected') {
                                continue;
                            }
                            echo ' <form method="post" class="aap-inline">';
                            wp_nonce_field('art_coa_moderate_ex');
                            echo '<input type="hidden" name="art_coa_action" value="' . esc_attr($act) . '"><input type="hidden" name="item_id" value="' . (int) $e->id . '">';
                            echo '<button class="button button-small ' . esc_attr($cls) . '">' . esc_html(I18n::t($lbl)) . '</button></form>';
                        }
                    }
                    echo '</td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table>';
        };
        ?>
        <div class="wrap art-coa-wrap" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <h1><?php echo esc_html(I18n::t('exhibitions')); ?>
                <button type="button" class="button button-primary" onclick="document.getElementById('art-form-ex').style.display='block'">+ <?php echo esc_html(I18n::t('add_exhibition')); ?></button>
            </h1>

            <?php
            $ex_types = ['exhibition' => I18n::t('exhibition'), 'artfair' => I18n::t('artfair'), 'auction' => I18n::t('auction')];
            $ex_statuses = [
                'published' => I18n::t('published'), 'pending' => I18n::t('status_pending'), 'draft' => I18n::t('draft'), 'rejected' => I18n::t('status_rejected'),
            ];
            $cur_opts = ['' => I18n::t('none')];
            foreach ($curators as $c) {
                $cur_opts[(int) $c->id] = $c->display_name;
            }
            self::form_open('ex', 'exhibition', $edit, $edit ? $edit->title : I18n::t('add_exhibition'));
            self::field('title', I18n::t('title'), $edit->title ?? '', ['required' => true]);
            self::field('subtitle', I18n::t('subtitle'), $edit->subtitle ?? '');
            self::field('artist_label', I18n::t('artist_label'), $edit->artist_label ?? '');
            self::select('event_type', I18n::t('event_type'), $ex_types, $edit->event_type ?? 'exhibition');
            self::select('gallery_id', I18n::t('gallery'), self::gallery_options(I18n::t('none')), $edit->gallery_id ?? '');
            self::select('curator_id', I18n::t('curator'), $cur_opts, $edit->curator_id ?? '');
            self::field('start_date', I18n::t('start_date_g'), $edit->start_date ?? '', ['type' => 'date', 'required' => true]);
            echo '<p class="description aap-s-cca226">' . esc_html(I18n::t('date_hint_jalali')) . '</p>';
            self::field('end_date', I18n::t('end_date_g'), $edit->end_date ?? '', ['type' => 'date']);
            echo '<div class="art-coa-form-grid">';
            echo \WPArtCOA\Media::picker('poster', $edit, ['label' => I18n::t('exhibition_poster'), 'shape' => 'portrait', 'help' => I18n::t('help_poster')]); // phpcs:ignore
            echo \WPArtCOA\Media::multi_picker('gallery_ids', $edit->gallery_ids ?? '', ['label' => I18n::t('exhibition_photos'), 'help' => I18n::t('help_exhibition_photos')]); // phpcs:ignore
            echo '</div>';
            self::field('description', I18n::t('description'), $edit->description ?? '', ['type' => 'textarea']);
            self::select('status', I18n::t('status'), $ex_statuses, $edit->status ?? 'published');
            self::form_close('ex', $edit);
            ?>

            <?php
            $render_ex_table($cats['ongoing'], I18n::t('exhibitions_ongoing'));
            $render_ex_table($cats['upcoming'], I18n::t('exhibitions_upcoming'));
            $render_ex_table($cats['past'], I18n::t('exhibitions_past'));
            ?>

            <h2 class="aap-s-19d332"><?php echo esc_html(I18n::t('link_artwork')); ?></h2>
            <form method="post" class="art-coa-form">
                <?php wp_nonce_field('art_coa_link_ex_art'); ?>
                <input type="hidden" name="art_coa_action" value="link_ex_art">
                <p><label><?php echo esc_html(I18n::t('select_exhibition')); ?><br>
                    <select name="exhibition_id" required class="widefat">
                        <option value=""><?php echo esc_html(I18n::t('select')); ?></option>
                        <?php foreach ($items as $e): ?>
                            <option value="<?php echo (int)$e->id; ?>"><?php echo esc_html($e->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label></p>
                <p><label><?php echo esc_html(I18n::t('select_artwork')); ?><br>
                    <select name="artwork_id" required class="widefat">
                        <option value=""><?php echo esc_html(I18n::t('select')); ?></option>
                        <?php foreach (Artwork::all() as $art): ?>
                            <option value="<?php echo (int)$art->id; ?>"><?php echo esc_html($art->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label></p>
                <p><button class="button button-primary"><?php echo esc_html(I18n::t('link')); ?></button></p>
            </form>

            <h2 class="aap-s-19d332"><?php echo esc_html(I18n::t('transfer_ownership')); ?></h2>
            <form method="post" class="art-coa-form">
                <?php wp_nonce_field('art_coa_transfer'); ?>
                <input type="hidden" name="art_coa_action" value="transfer_ownership">
                <p><label><?php echo esc_html(I18n::t('select_artwork')); ?> *<br>
                    <select name="artwork_id" required class="widefat">
                        <option value=""><?php echo esc_html(I18n::t('select')); ?></option>
                        <?php foreach (Artwork::all() as $art): ?>
                            <option value="<?php echo (int)$art->id; ?>"><?php echo esc_html($art->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label></p>
                <p><label><?php echo esc_html(I18n::t('owner_type')); ?><br>
                    <select name="to_type" class="widefat">
                        <option value="collector"><?php echo esc_html(I18n::t('collector')); ?></option>
                        <option value="buyer"><?php echo esc_html(I18n::t('buyer')); ?></option>
                        <option value="person"><?php echo esc_html(I18n::t('person')); ?></option>
                        <option value="gallery"><?php echo esc_html(I18n::t('gallery')); ?></option>
                    </select>
                </label></p>
                <p><label><?php echo esc_html(I18n::t('issued_to')); ?> *<br><input name="to_name" required class="widefat"></label></p>
                <p><label><?php echo esc_html(I18n::t('owner_id')); ?><br><input type="number" name="to_id" class="widefat"></label></p>
                <p><label><?php echo esc_html(I18n::t('notes')); ?><br><textarea name="notes" rows="2" class="widefat"></textarea></label></p>
                <p><button class="button button-primary"><?php echo esc_html(I18n::t('register_transfer')); ?></button></p>
            </form>
        </div>
        <?php
    }

    // --- Form handlers ---
    private static function handle_exhibition_form(): void
    {
        if (!empty($_POST['art_coa_action']) && $_POST['art_coa_action'] === 'link_ex_art') {
            check_admin_referer('art_coa_link_ex_art');
            if (!current_user_can('manage_options')) return;
            $res = \WPArtCOA\Entities\Ownership::link_exhibition_artwork(
                absint($_POST['exhibition_id'] ?? 0),
                absint($_POST['artwork_id'] ?? 0)
            );
            if (empty($res['message'])) { $res['message'] = 'اثر به نمایشگاه وصل شد.'; }
            self::notice($res);
            return;
        }
        if (!empty($_POST['art_coa_action']) && $_POST['art_coa_action'] === 'transfer_ownership') {
            check_admin_referer('art_coa_transfer');
            if (!current_user_can('manage_options')) return;
            $res = \WPArtCOA\Entities\Ownership::transfer($_POST);
            self::notice($res);
            return;
        }
        if (!empty($_POST['art_coa_action']) && in_array($_POST['art_coa_action'], ['approve_exhibition', 'reject_exhibition'], true)) {
            check_admin_referer('art_coa_moderate_ex');
            if (!current_user_can('manage_options')) return;
            $ok = Exhibition::set_status(absint($_POST['item_id'] ?? 0), $_POST['art_coa_action'] === 'approve_exhibition' ? 'published' : 'rejected');
            self::notice(['success' => $ok, 'message' => $ok ? I18n::t('success') : I18n::t('error')]);
            return;
        }
        self::handle_entity('exhibition', Exhibition::class);
    }
}
