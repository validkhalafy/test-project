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
trait CallsPage
{
    public static function page_calls(): void
    {
        \WPArtCOA\Calls::maybe_migrate();
        self::handle_call_form();

        global $wpdb;
        $items = $wpdb->get_results(
            'SELECT c.*, g.name AS gallery_name,
                (SELECT COUNT(*) FROM ' . \WPArtCOA\Calls::applications_table() . ' ap WHERE ap.call_id = c.id) AS app_count
             FROM ' . \WPArtCOA\Calls::table() . ' c
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = c.gallery_id
             ORDER BY c.id DESC LIMIT 100'
        ) ?: [];
        $apps = $wpdb->get_results(
            'SELECT ap.*, c.title AS call_title FROM ' . \WPArtCOA\Calls::applications_table() . ' ap
             LEFT JOIN ' . \WPArtCOA\Calls::table() . ' c ON c.id = ap.call_id
             ORDER BY ap.id DESC LIMIT 50'
        ) ?: [];
        $galleries = \WPArtCOA\Entities\Gallery::all();
        $exhibitions = \WPArtCOA\Entities\Exhibition::all();
        ?>
        <div class="wrap art-coa-wrap" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <h1><?php echo esc_html(I18n::t('calls_title')); ?>
                <button type="button" class="button button-primary" onclick="document.getElementById('art-form-call').style.display='block'">+ <?php echo esc_html(I18n::t('add_call')); ?></button>
            </h1>
            <p><a href="<?php echo esc_url(home_url('/art-coa/calls/')); ?>" target="_blank"><?php echo esc_html(home_url('/art-coa/calls/')); ?></a></p>

            <div id="art-form-call" class="art-coa-form" style="display:none">
                <form method="post">
                    <?php wp_nonce_field('art_coa_save_call'); ?>
                    <input type="hidden" name="art_coa_action" value="save_call">
                    <p><label><?php echo esc_html(I18n::t('title')); ?> *<br><input name="title" required class="widefat"></label></p>
                    <p><label><?php echo esc_html(I18n::t('call_type')); ?><br>
                        <select name="call_type" class="widefat">
                            <option value="exhibition"><?php echo esc_html(I18n::t('call_type_exhibition')); ?></option>
                            <option value="event"><?php echo esc_html(I18n::t('call_type_event')); ?></option>
                            <option value="residency"><?php echo esc_html(I18n::t('call_type_residency')); ?></option>
                            <option value="open_studio"><?php echo esc_html(I18n::t('call_type_open_studio')); ?></option>
                        </select>
                    </label></p>
                    <p><label><?php echo esc_html(I18n::t('gallery')); ?><br>
                        <select name="gallery_id" class="widefat">
                            <option value="0"><?php echo esc_html(I18n::t('none')); ?></option>
                            <?php foreach ($galleries as $g): ?>
                                <option value="<?php echo (int)$g->id; ?>"><?php echo esc_html($g->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label></p>
                    <p><label><?php echo esc_html(I18n::t('exhibition')); ?><br>
                        <select name="exhibition_id" class="widefat">
                            <option value="0"><?php echo esc_html(I18n::t('none')); ?></option>
                            <?php foreach ($exhibitions as $e): ?>
                                <option value="<?php echo (int)$e->id; ?>"><?php echo esc_html($e->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label></p>
                    <p><label><?php echo esc_html(I18n::t('description')); ?><br><textarea name="description" rows="4" class="widefat"></textarea></label></p>
                    <p><label><?php echo esc_html(I18n::t('requirements')); ?><br><textarea name="requirements" rows="3" class="widefat"></textarea></label></p>
                    <p><label><?php echo esc_html(I18n::t('deadline')); ?> *<br><input type="datetime-local" name="deadline" required class="widefat"><br><span class="description"><?php echo esc_html(I18n::t('deadline_admin_help')); ?></span></label></p>
                    <p><label><?php echo esc_html(I18n::t('event_start')); ?><br><input type="datetime-local" name="event_start" class="widefat"><br><span class="description"><?php echo esc_html(I18n::t('event_time_admin_help')); ?></span></label></p>
                    <p><label><?php echo esc_html(I18n::t('event_end')); ?><br><input type="datetime-local" name="event_end" class="widefat"></label></p>
                    <p><label><?php echo esc_html(I18n::t('location')); ?><br><input name="location" class="widefat"></label></p>
                    <p><label><?php echo esc_html(I18n::t('city')); ?><br><input name="city" class="widefat"></label></p>
                    <p><label><?php echo esc_html(I18n::t('country')); ?><br><input name="country" class="widefat"></label></p>
                    <p><label><?php echo esc_html(I18n::t('email')); ?><br><input type="email" name="contact_email" class="widefat"></label></p>
                    <p><label><?php echo esc_html(I18n::t('entry_fee')); ?><br><input type="number" name="fee" step="any" min="0" class="widefat"></label></p>
                    <?php echo \WPArtCOA\Media::picker('cover', null, ['label' => I18n::t('cover_image'), 'shape' => 'wide']); // phpcs:ignore ?>
                    <p><label><input type="checkbox" name="is_featured" value="1"> <?php echo esc_html(I18n::t('featured')); ?></label></p>
                    <p>
                        <button class="button button-primary"><?php echo esc_html(I18n::t('save')); ?></button>
                        <button type="button" class="button" onclick="document.getElementById('art-form-call').style.display='none'"><?php echo esc_html(I18n::t('cancel')); ?></button>
                    </p>
                </form>
            </div>

            <h2><?php echo esc_html(I18n::t('calls_title')); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>ID</th>
                    <th><?php echo esc_html(I18n::t('title')); ?></th>
                    <th><?php echo esc_html(I18n::t('call_type')); ?></th>
                    <th><?php echo esc_html(I18n::t('gallery')); ?></th>
                    <th><?php echo esc_html(I18n::t('deadline')); ?></th>
                    <th><?php echo esc_html(I18n::t('event_dates')); ?></th>
                    <th><?php echo esc_html(I18n::t('status')); ?></th>
                    <th><?php echo esc_html(I18n::t('applications')); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="9" class="aap-center"><?php echo esc_html(I18n::t('calls_empty')); ?></td></tr>
                <?php else: foreach ($items as $row): ?>
                    <tr>
                        <td><?php echo (int)$row->id; ?></td>
                        <td><strong><?php echo esc_html($row->title); ?></strong>
                            <?php if ($row->is_featured): ?><span class="art-badge">★</span><?php endif; ?>
                        </td>
                        <td><?php echo esc_html(\WPArtCOA\Calls::type_label($row->call_type)); ?></td>
                        <td><?php echo esc_html($row->gallery_name ?: '—'); ?></td>
                        <td><?php echo $row->deadline_utc ? esc_html(Dates::format($row->deadline_utc, true)) : '—'; ?></td>
                        <td><?php echo $row->event_start_utc ? esc_html(Dates::format($row->event_start_utc, true)) : '—'; ?><?php if ($row->event_end_utc): ?> — <?php echo esc_html(Dates::format($row->event_end_utc, true)); ?><?php endif; ?></td>
                        <td><span class="art-badge"><?php echo esc_html($row->status); ?></span></td>
                        <td><?php echo (int)$row->app_count; ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(home_url('/art-coa/call/' . (string) $row->slug . '/')); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(I18n::t('view_page')); ?></a>
                            <?php if ($row->status === 'open'): ?>
                            <form method="post" class="aap-inline">
                                <?php wp_nonce_field('art_coa_save_call'); ?>
                                <input type="hidden" name="art_coa_action" value="close_call">
                                <input type="hidden" name="call_id" value="<?php echo (int)$row->id; ?>">
                                <button class="button button-small"><?php echo esc_html(I18n::t('call_closed')); ?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <h2 class="aap-s-b6e5ef"><?php echo esc_html(I18n::t('applications')); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th><?php echo esc_html(I18n::t('calls_title')); ?></th>
                    <th><?php echo esc_html(I18n::t('name')); ?></th>
                    <th><?php echo esc_html(I18n::t('email')); ?> / <?php echo esc_html(I18n::t('phone')); ?></th>
                    <th><?php echo esc_html(I18n::t('medium')); ?></th>
                    <th><?php echo esc_html(I18n::t('city')); ?></th>
                    <th><?php echo esc_html(I18n::t('date')); ?></th>
                    <th><?php echo esc_html(I18n::t('status')); ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$apps): ?>
                    <tr><td colspan="7" class="aap-center">—</td></tr>
                <?php else: foreach ($apps as $ap): ?>
                    <tr>
                        <td><?php echo esc_html($ap->call_title); ?></td>
                        <td>
                            <strong><?php echo esc_html($ap->applicant_name); ?></strong>
                            <?php if (!empty($ap->works_info)): ?>
                                <br><span class="description"><?php echo esc_html(wp_trim_words((string)$ap->works_info, 12)); ?></span>
                            <?php endif; ?>
                            <?php if ($ap->portfolio_url): ?>
                                <br><a href="<?php echo esc_url($ap->portfolio_url); ?>" target="_blank">portfolio</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="mailto:<?php echo esc_attr($ap->applicant_email); ?>"><?php echo esc_html($ap->applicant_email); ?></a>
                            <br><?php echo esc_html($ap->applicant_phone); ?>
                        </td>
                        <td><?php echo esc_html($ap->medium ?? '—'); ?></td>
                        <td><?php echo esc_html(trim(($ap->city ?? '') . ' ' . ($ap->country ?? '')) ?: '—'); ?></td>
                        <td><?php echo esc_html(Dates::format($ap->created_at_utc, true)); ?></td>
                        <td><?php echo esc_html($ap->status); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function handle_call_form(): void
    {
        if (empty($_POST['art_coa_action']) || !current_user_can('manage_options')) {
            return;
        }
        $action = sanitize_key(\WPArtCOA\Security::input_scalar($_POST, 'art_coa_action'));
        if (!in_array($action, ['save_call', 'close_call'], true)) {
            return;
        }
        check_admin_referer('art_coa_save_call');

        if ($action === 'close_call') {
            global $wpdb;
            $wpdb->update(\WPArtCOA\Calls::table(), ['status' => 'closed', 'updated_at_utc' => gmdate('Y-m-d H:i:s')], [
                'id' => absint($_POST['call_id'] ?? 0),
            ]);
            echo '<div class="notice notice-success"><p>' . esc_html(I18n::t('call_closed')) . '</p></div>';
            return;
        }

        $id = \WPArtCOA\Calls::create(wp_unslash($_POST));
        if ($id) {
            echo '<div class="notice notice-success"><p>' . esc_html(I18n::t('success')) . ' #' . (int)$id . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html(I18n::t('error')) . '</p></div>';
        }
    }
}
