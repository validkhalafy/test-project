<?php
declare(strict_types=1);

namespace WPArtCOA\Admin\Pages;

use WPArtCOA\Database;
use WPArtCOA\I18n;
use WPArtCOA\Dates;

defined('ABSPATH') || exit;

/** Admin screens split out of Menus (used as a trait so existing self:: calls keep working). */
trait CallsPage
{
    public static function page_calls(): void
    {
        \WPArtCOA\Calls::maybe_migrate();
        $notice = self::handle_call_form();

        global $wpdb;
        $calls_table = \WPArtCOA\Calls::table();
        $apps_table = \WPArtCOA\Calls::applications_table();
        $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selector
        $filter_call = isset($_GET['call']) ? absint($_GET['call']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filter_status = isset($_GET['app_status']) ? sanitize_key(wp_unslash((string) $_GET['app_status'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filter_status = in_array($filter_status, \WPArtCOA\Calls::APPLICATION_STATUSES, true) ? $filter_status : '';
        $edit = $edit_id ? \WPArtCOA\Calls::get($edit_id) : null;

        $items = $wpdb->get_results(
            'SELECT c.*, g.name AS gallery_name,
                (SELECT COUNT(*) FROM ' . $apps_table . ' ap WHERE ap.call_id = c.id) AS app_count,
                (SELECT COUNT(*) FROM ' . $apps_table . " ap WHERE ap.call_id = c.id AND ap.status = 'pending') AS app_pending
             FROM " . $calls_table . ' c
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = c.gallery_id
             ORDER BY c.id DESC LIMIT 200'
        ) ?: [];
        $where = ' WHERE 1=1';
        $params = [];
        if ($filter_call) {
            $where .= ' AND ap.call_id = %d';
            $params[] = $filter_call;
        }
        if ($filter_status !== '') {
            $where .= ' AND ap.status = %s';
            $params[] = $filter_status;
        }
        $apps_sql = 'SELECT ap.*, c.title AS call_title FROM ' . $apps_table . ' ap
             LEFT JOIN ' . $calls_table . ' c ON c.id = ap.call_id' . $where . ' ORDER BY ap.id DESC LIMIT 200';
        $apps = $wpdb->get_results($params ? $wpdb->prepare($apps_sql, ...$params) : $apps_sql) ?: [];
        $galleries = \WPArtCOA\Entities\Gallery::all();
        $exhibitions = \WPArtCOA\Entities\Exhibition::all();
        $page_url = admin_url('admin.php?page=' . (isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : 'wp-art-coa-calls')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $fa = I18n::is_fa();
        $v = static function (?object $row, string $key, string $default = '') {
            return $row && isset($row->{$key}) && $row->{$key} !== null ? (string) $row->{$key} : $default;
        };
        ?>
        <div class="wrap art-coa-wrap" dir="<?php echo $fa ? 'rtl' : 'ltr'; ?>">
            <h1 class="wp-heading-inline"><?php echo esc_html(I18n::t('calls_title')); ?></h1>
            <button type="button" class="page-title-action" onclick="var f=document.getElementById('art-form-call');f.style.display='block';f.scrollIntoView({behavior:'smooth'});">+ <?php echo esc_html(I18n::t('add_call')); ?></button>
            <hr class="wp-header-end">
            <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_html below ?>
            <p><a href="<?php echo esc_url(home_url('/art-coa/calls/')); ?>" target="_blank" rel="noopener"><?php echo esc_html(home_url('/art-coa/calls/')); ?></a>
               · <code>[art_coa_calls type="" status="open" limit="12" gallery=""]</code></p>

            <div id="art-form-call" class="art-coa-form" style="<?php echo $edit ? '' : 'display:none'; ?>">
                <h2><?php echo esc_html($edit ? ($fa ? 'ویرایش فراخوان' : 'Edit call') . ' #' . (int) $edit->id : I18n::t('add_call')); ?></h2>
                <form method="post" action="<?php echo esc_url($page_url); ?>">
                    <?php wp_nonce_field('art_coa_save_call'); ?>
                    <input type="hidden" name="art_coa_action" value="<?php echo $edit ? 'update_call' : 'save_call'; ?>">
                    <?php if ($edit): ?><input type="hidden" name="call_id" value="<?php echo (int) $edit->id; ?>"><?php endif; ?>
                    <p><label><?php echo esc_html(I18n::t('title')); ?> *<br><input name="title" required class="widefat" value="<?php echo esc_attr($v($edit, 'title')); ?>"></label></p>
                    <p><label><?php echo esc_html($fa ? 'نامک (آدرس)' : 'Slug'); ?><br><input name="slug" class="widefat" dir="ltr" value="<?php echo esc_attr($v($edit, 'slug')); ?>"><br><span class="description"><?php echo esc_html($fa ? 'خالی = از روی عنوان ساخته می‌شود. تغییر نامک، آدرس قبلی را از کار می‌اندازد.' : 'Empty = generated from the title.'); ?></span></label></p>
                    <div class="aap-grid-2">
                    <p><label><?php echo esc_html(I18n::t('call_type')); ?><br>
                        <select name="call_type" class="widefat">
                            <?php foreach (\WPArtCOA\Calls::TYPES as $t): ?>
                                <option value="<?php echo esc_attr($t); ?>" <?php selected($v($edit, 'call_type', 'exhibition'), $t); ?>><?php echo esc_html(\WPArtCOA\Calls::type_label($t)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label></p>
                    <p><label><?php echo esc_html(I18n::t('status')); ?><br>
                        <select name="status" class="widefat">
                            <option value="open" <?php selected($v($edit, 'status', 'open'), 'open'); ?>><?php echo esc_html(I18n::t('call_open')); ?></option>
                            <option value="closed" <?php selected($v($edit, 'status', 'open'), 'closed'); ?>><?php echo esc_html(I18n::t('call_closed')); ?></option>
                        </select>
                    </label></p>
                    <p><label><?php echo esc_html(I18n::t('gallery')); ?><br>
                        <select name="gallery_id" class="widefat">
                            <option value="0"><?php echo esc_html(I18n::t('none')); ?></option>
                            <?php foreach ($galleries as $g): ?>
                                <option value="<?php echo (int) $g->id; ?>" <?php selected((int) $v($edit, 'gallery_id', '0'), (int) $g->id); ?>><?php echo esc_html($g->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label></p>
                    <p><label><?php echo esc_html(I18n::t('exhibition')); ?><br>
                        <select name="exhibition_id" class="widefat">
                            <option value="0"><?php echo esc_html(I18n::t('none')); ?></option>
                            <?php foreach ($exhibitions as $e): ?>
                                <option value="<?php echo (int) $e->id; ?>" <?php selected((int) $v($edit, 'exhibition_id', '0'), (int) $e->id); ?>><?php echo esc_html($e->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label></p>
                    </div>
                    <div><label for="artcalldescription"><strong><?php echo esc_html(I18n::t('description')); ?></strong></label>
                        <?php
                        if (function_exists('wp_editor')) {
                            wp_editor($v($edit, 'description'), 'artcalldescription', ['textarea_name' => 'description', 'textarea_rows' => 8, 'media_buttons' => true, 'teeny' => false]);
                        } else {
                            echo '<textarea id="artcalldescription" name="description" rows="6" class="widefat">' . esc_textarea($v($edit, 'description')) . '</textarea>';
                        }
                        ?>
                    </div>
                    <p><label><?php echo esc_html(I18n::t('requirements')); ?><br><textarea name="requirements" rows="4" class="widefat"><?php echo esc_textarea($v($edit, 'requirements')); ?></textarea></label></p>
                    <div class="aap-grid-2">
                    <p><label><?php echo esc_html(I18n::t('deadline')); ?> *<br><input type="datetime-local" name="deadline" required class="widefat" value="<?php echo esc_attr(\WPArtCOA\Calls::to_local_input($edit->deadline_utc ?? null)); ?>"><br><span class="description"><?php echo esc_html(I18n::t('deadline_admin_help')); ?></span></label></p>
                    <p><label><?php echo esc_html($fa ? 'حداکثر تعداد اثر برای هر هنرمند' : 'Max. works per artist'); ?><br><input type="number" min="0" name="max_works" class="widefat" value="<?php echo esc_attr($v($edit, 'max_works')); ?>"></label></p>
                    <p><label><?php echo esc_html(I18n::t('event_start')); ?><br><input type="datetime-local" name="event_start" class="widefat" value="<?php echo esc_attr(\WPArtCOA\Calls::to_local_input($edit->event_start_utc ?? null)); ?>"><br><span class="description"><?php echo esc_html(I18n::t('event_time_admin_help')); ?></span></label></p>
                    <p><label><?php echo esc_html(I18n::t('event_end')); ?><br><input type="datetime-local" name="event_end" class="widefat" value="<?php echo esc_attr(\WPArtCOA\Calls::to_local_input($edit->event_end_utc ?? null)); ?>"></label></p>
                    <p><label><?php echo esc_html(I18n::t('location')); ?><br><input name="location" class="widefat" value="<?php echo esc_attr($v($edit, 'location')); ?>"></label></p>
                    <p><label><?php echo esc_html(I18n::t('city')); ?><br><input name="city" class="widefat" value="<?php echo esc_attr($v($edit, 'city')); ?>"></label></p>
                    <p><label><?php echo esc_html(I18n::t('country')); ?><br><input name="country" class="widefat" value="<?php echo esc_attr($v($edit, 'country')); ?>"></label></p>
                    <p><label><?php echo esc_html(I18n::t('email')); ?><br><input type="email" name="contact_email" class="widefat" dir="ltr" value="<?php echo esc_attr($v($edit, 'contact_email')); ?>"><br><span class="description"><?php echo esc_html($fa ? 'درخواست‌ها به این نشانی ارسال می‌شوند (در سایت نمایش داده نمی‌شود).' : 'Applications are emailed here (never shown publicly).'); ?></span></label></p>
                    <p><label><?php echo esc_html(I18n::t('entry_fee')); ?><br><input type="number" name="fee" step="any" min="0" class="widefat" value="<?php echo esc_attr($v($edit, 'fee')); ?>"></label></p>
                    <p><label><?php echo esc_html($fa ? 'واحد پول' : 'Currency'); ?><br><input name="currency" class="widefat" dir="ltr" maxlength="10" value="<?php echo esc_attr($v($edit, 'currency', 'IRR')); ?>"></label></p>
                    </div>
                    <p><label><?php echo esc_html($fa ? 'پیوند ثبت‌نام خارجی (اختیاری)' : 'External application URL (optional)'); ?><br><input type="url" name="apply_url" class="widefat" dir="ltr" placeholder="https://" value="<?php echo esc_attr($v($edit, 'apply_url')); ?>"><br><span class="description"><?php echo esc_html($fa ? 'اگر پر شود، به‌جای فرم داخلی دکمهٔ ثبت‌نام در سایت برگزارکننده نمایش داده می‌شود.' : 'When set, a button to the organizer site replaces the built-in form.'); ?></span></label></p>
                    <?php echo \WPArtCOA\Media::picker('cover', $edit, ['label' => I18n::t('cover_image'), 'shape' => 'wide']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <p><label><input type="checkbox" name="is_featured" value="1" <?php checked((int) $v($edit, 'is_featured', '0'), 1); ?>> <?php echo esc_html(I18n::t('featured')); ?></label></p>
                    <p>
                        <button class="button button-primary"><?php echo esc_html(I18n::t('save')); ?></button>
                        <a class="button" href="<?php echo esc_url($page_url); ?>"><?php echo esc_html(I18n::t('cancel')); ?></a>
                    </p>
                </form>
            </div>

            <h2><?php echo esc_html(I18n::t('calls_title')); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th class="aap-w-60">ID</th>
                    <th><?php echo esc_html(I18n::t('title')); ?></th>
                    <th><?php echo esc_html(I18n::t('call_type')); ?></th>
                    <th><?php echo esc_html(I18n::t('gallery')); ?></th>
                    <th><?php echo esc_html(I18n::t('deadline')); ?></th>
                    <th><?php echo esc_html(I18n::t('status')); ?></th>
                    <th><?php echo esc_html(I18n::t('applications')); ?></th>
                    <th><?php echo esc_html($fa ? 'عملیات' : 'Actions'); ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="8" class="aap-center"><?php echo esc_html(I18n::t('calls_empty')); ?></td></tr>
                <?php else: foreach ($items as $row): $is_open = \WPArtCOA\Calls::is_open($row); ?>
                    <tr>
                        <td><?php echo (int) $row->id; ?></td>
                        <td><strong><a href="<?php echo esc_url(add_query_arg('edit', (int) $row->id, $page_url)); ?>"><?php echo esc_html($row->title); ?></a></strong>
                            <?php if ($row->is_featured): ?><span class="art-badge">★</span><?php endif; ?>
                            <br><code dir="ltr"><?php echo esc_html((string) $row->slug); ?></code>
                        </td>
                        <td><?php echo esc_html(\WPArtCOA\Calls::type_label((string) $row->call_type)); ?></td>
                        <td><?php echo esc_html($row->gallery_name ?: '—'); ?></td>
                        <td><?php echo $row->deadline_utc ? esc_html(Dates::format($row->deadline_utc, true)) : '—'; ?></td>
                        <td><span class="art-badge"><?php echo esc_html($is_open ? I18n::t('call_open') : I18n::t('call_closed')); ?></span></td>
                        <td><a href="<?php echo esc_url(add_query_arg('call', (int) $row->id, $page_url) . '#art-call-apps'); ?>"><?php echo (int) $row->app_count; ?></a><?php if ((int) $row->app_pending): ?> <span class="awaiting-mod"><?php echo (int) $row->app_pending; ?></span><?php endif; ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(\WPArtCOA\Calls::url($row)); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(I18n::t('view_page')); ?></a>
                            <a class="button button-small" href="<?php echo esc_url(add_query_arg('edit', (int) $row->id, $page_url)); ?>"><?php echo esc_html($fa ? 'ویرایش' : 'Edit'); ?></a>
                            <form method="post" class="aap-inline" action="<?php echo esc_url($page_url); ?>">
                                <?php wp_nonce_field('art_coa_save_call'); ?>
                                <input type="hidden" name="call_id" value="<?php echo (int) $row->id; ?>">
                                <?php if ($row->status === 'open'): ?>
                                    <button class="button button-small" name="art_coa_action" value="close_call"><?php echo esc_html($fa ? 'بستن' : 'Close'); ?></button>
                                <?php else: ?>
                                    <button class="button button-small" name="art_coa_action" value="reopen_call"><?php echo esc_html($fa ? 'بازگشایی' : 'Reopen'); ?></button>
                                <?php endif; ?>
                                <button class="button button-small button-link-delete" name="art_coa_action" value="delete_call" onclick="return confirm('<?php echo esc_js($fa ? 'فراخوان و همهٔ درخواست‌هایش حذف شود؟' : 'Delete this call and all its applications?'); ?>');"><?php echo esc_html($fa ? 'حذف' : 'Delete'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <h2 class="aap-s-b6e5ef" id="art-call-apps"><?php echo esc_html(I18n::t('applications')); ?></h2>
            <form method="get" class="aap-inline" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr(isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : 'wp-art-coa-calls'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
                <select name="call">
                    <option value="0"><?php echo esc_html($fa ? 'همهٔ فراخوان‌ها' : 'All calls'); ?></option>
                    <?php foreach ($items as $row): ?><option value="<?php echo (int) $row->id; ?>" <?php selected($filter_call, (int) $row->id); ?>><?php echo esc_html($row->title); ?></option><?php endforeach; ?>
                </select>
                <select name="app_status">
                    <option value=""><?php echo esc_html($fa ? 'همهٔ وضعیت‌ها' : 'All statuses'); ?></option>
                    <?php foreach (\WPArtCOA\Calls::APPLICATION_STATUSES as $st): ?><option value="<?php echo esc_attr($st); ?>" <?php selected($filter_status, $st); ?>><?php echo esc_html(\WPArtCOA\Calls::status_label($st)); ?></option><?php endforeach; ?>
                </select>
                <button class="button"><?php echo esc_html($fa ? 'فیلتر' : 'Filter'); ?></button>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['art_coa_export_apps' => 1, 'call' => $filter_call, 'app_status' => $filter_status], $page_url), 'art_coa_export_apps')); ?>"><?php echo esc_html($fa ? 'خروجی CSV' : 'Export CSV'); ?></a>
            </form>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th><?php echo esc_html(I18n::t('calls_title')); ?></th>
                    <th><?php echo esc_html(I18n::t('name')); ?></th>
                    <th><?php echo esc_html(I18n::t('email')); ?> / <?php echo esc_html(I18n::t('phone')); ?></th>
                    <th><?php echo esc_html(I18n::t('medium')); ?></th>
                    <th><?php echo esc_html(I18n::t('date')); ?></th>
                    <th><?php echo esc_html(I18n::t('status')); ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$apps): ?>
                    <tr><td colspan="6" class="aap-center">—</td></tr>
                <?php else: foreach ($apps as $ap): ?>
                    <tr>
                        <td><?php echo esc_html((string) $ap->call_title); ?></td>
                        <td>
                            <strong><?php echo esc_html($ap->applicant_name); ?></strong>
                            <?php if (!empty($ap->artist_id)): ?> <span class="art-badge"><?php echo esc_html($fa ? 'هنرمند عضو' : 'Member artist'); ?></span><?php endif; ?>
                            <details><summary><?php echo esc_html($fa ? 'جزئیات' : 'Details'); ?></summary>
                                <?php if (!empty($ap->works_info)): ?><p><strong><?php echo esc_html(I18n::t('works_info')); ?>:</strong><br><?php echo nl2br(esc_html((string) $ap->works_info)); ?></p><?php endif; ?>
                                <?php if (!empty($ap->bio)): ?><p><strong><?php echo esc_html(I18n::t('bio')); ?>:</strong><br><?php echo nl2br(esc_html((string) $ap->bio)); ?></p><?php endif; ?>
                                <?php if (!empty($ap->message)): ?><p><strong><?php echo esc_html(I18n::t('message')); ?>:</strong><br><?php echo nl2br(esc_html((string) $ap->message)); ?></p><?php endif; ?>
                                <p><?php foreach (['website' => I18n::t('website'), 'portfolio_url' => I18n::t('portfolio_url')] as $col => $label): if (!empty($ap->{$col})): ?><a href="<?php echo esc_url((string) $ap->{$col}); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?></a> <?php endif; endforeach; ?>
                                <?php if (!empty($ap->instagram)): ?><span dir="ltr"><?php echo esc_html((string) $ap->instagram); ?></span><?php endif; ?></p>
                                <p><?php echo esc_html(trim(($ap->city ?? '') . ' ' . ($ap->country ?? ''))); ?><?php if (!empty($ap->birth_year)): ?> · <?php echo (int) $ap->birth_year; ?><?php endif; ?></p>
                            </details>
                        </td>
                        <td dir="ltr">
                            <a href="mailto:<?php echo esc_attr($ap->applicant_email); ?>"><?php echo esc_html($ap->applicant_email); ?></a>
                            <br><?php echo esc_html($ap->applicant_phone); ?>
                        </td>
                        <td><?php echo esc_html($ap->medium ?: '—'); ?></td>
                        <td><?php echo esc_html(Dates::format($ap->created_at_utc, true)); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(add_query_arg(['call' => $filter_call, 'app_status' => $filter_status], $page_url)); ?>">
                                <?php wp_nonce_field('art_coa_save_call'); ?>
                                <input type="hidden" name="art_coa_action" value="application_status">
                                <input type="hidden" name="application_id" value="<?php echo (int) $ap->id; ?>">
                                <select name="application_status">
                                    <?php foreach (\WPArtCOA\Calls::APPLICATION_STATUSES as $st): ?><option value="<?php echo esc_attr($st); ?>" <?php selected((string) $ap->status, $st); ?>><?php echo esc_html(\WPArtCOA\Calls::status_label($st)); ?></option><?php endforeach; ?>
                                </select>
                                <textarea name="admin_note" rows="2" class="widefat" placeholder="<?php echo esc_attr($fa ? 'یادداشت داخلی' : 'Internal note'); ?>"><?php echo esc_textarea((string) ($ap->admin_note ?? '')); ?></textarea>
                                <label><input type="checkbox" name="notify" value="1"> <?php echo esc_html($fa ? 'اطلاع‌رسانی ایمیلی به متقاضی' : 'Email the applicant'); ?></label>
                                <button class="button button-small"><?php echo esc_html(I18n::t('save')); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /** @return string admin notice HTML */
    private static function handle_call_form(): string
    {
        if (empty($_POST['art_coa_action']) || !current_user_can('manage_options')) {
            return '';
        }
        $action = sanitize_key(\WPArtCOA\Security::input_scalar($_POST, 'art_coa_action'));
        if (!in_array($action, ['save_call', 'update_call', 'close_call', 'reopen_call', 'delete_call', 'application_status'], true)) {
            return '';
        }
        check_admin_referer('art_coa_save_call');
        $ok = static fn(string $m): string => '<div class="notice notice-success is-dismissible"><p>' . esc_html($m) . '</p></div>';
        $err = static fn(string $m): string => '<div class="notice notice-error"><p>' . esc_html($m) . '</p></div>';
        $fa = I18n::is_fa();
        $call_id = absint($_POST['call_id'] ?? 0);
        global $wpdb;

        switch ($action) {
            case 'close_call':
            case 'reopen_call':
                $wpdb->update(\WPArtCOA\Calls::table(), ['status' => $action === 'close_call' ? 'closed' : 'open', 'updated_at_utc' => gmdate('Y-m-d H:i:s')], ['id' => $call_id]);
                return $ok($action === 'close_call' ? I18n::t('call_closed') : ($fa ? 'فراخوان دوباره باز شد. اگر مهلت گذشته باشد، ابتدا مهلت را ویرایش کنید.' : 'Call reopened. Extend the deadline if it has passed.'));
            case 'delete_call':
                return \WPArtCOA\Calls::delete($call_id) ? $ok($fa ? 'فراخوان حذف شد.' : 'Call deleted.') : $err(I18n::t('error'));
            case 'application_status':
                $saved = \WPArtCOA\Calls::set_application_status(
                    absint($_POST['application_id'] ?? 0),
                    sanitize_key(\WPArtCOA\Security::input_scalar($_POST, 'application_status')),
                    (string) wp_unslash(\WPArtCOA\Security::input_scalar($_POST, 'admin_note')),
                    !empty($_POST['notify'])
                );
                return $saved ? $ok(I18n::t('success')) : $err(I18n::t('error'));
            case 'update_call':
                $data = wp_unslash($_POST); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Calls
                return \WPArtCOA\Calls::update($call_id, is_array($data) ? $data : [])
                    ? $ok(I18n::t('success') . ' #' . $call_id)
                    : $err($fa ? 'ذخیره نشد: عنوان، نوع و مهلت معتبر الزامی است و تاریخ پایان رویداد نباید پیش از شروع باشد.' : 'Not saved: title, type and a valid deadline are required.');
        }
        $data = wp_unslash($_POST); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Calls
        $id = \WPArtCOA\Calls::create(is_array($data) ? $data : []);
        return $id
            ? $ok(I18n::t('success') . ' #' . (int) $id)
            : $err($fa ? 'ذخیره نشد: عنوان، نوع و مهلت معتبر الزامی است و تاریخ پایان رویداد نباید پیش از شروع باشد.' : 'Not saved: title, type and a valid deadline are required.');
    }
}
