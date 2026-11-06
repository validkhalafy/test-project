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
trait AuctionsPage
{
    public static function page_auctions(): void
    {
        \WPArtCOA\Auction::maybe_migrate();
        self::handle_admin_auction();

        global $wpdb;
        $items = $wpdb->get_results(
            'SELECT au.*, a.title AS artwork_title, c.display_name AS collector_name
             FROM ' . \WPArtCOA\Auction::listings_table() . ' au
             LEFT JOIN ' . Database::table('artworks') . ' a ON a.id = au.artwork_id
             LEFT JOIN ' . Database::table('collectors') . ' c ON c.id = au.collector_id
             ORDER BY au.id DESC LIMIT 100'
        ) ?: [];
        $artworks = \WPArtCOA\Entities\Artwork::all();
        $collectors = \WPArtCOA\Entities\Collector::all();
        $stats = \WPArtCOA\AuctionStats::overview();
        ?>
        <div class="wrap art-coa-wrap" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <h1><?php echo esc_html(I18n::t('auction_title')); ?>
                <button type="button" class="button button-primary" onclick="document.getElementById('art-form-auction').style.display='block'">+ <?php echo esc_html(I18n::t('start_auction')); ?></button>
            </h1>
            <p><?php echo esc_html(I18n::t('auction_admin_only')); ?></p>

            <div class="art-coa-stats">
                <div class="art-coa-stat"><span class="val"><?php echo (int)$stats['auctions_active']; ?></span><span class="lbl"><?php echo esc_html(I18n::t('auctions_active')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo (int)$stats['auctions_sold']; ?></span><span class="lbl"><?php echo esc_html(I18n::t('auctions_sold')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo (int)$stats['bids_total']; ?></span><span class="lbl"><?php echo esc_html(I18n::t('bids_total')); ?></span></div>
                <div class="art-coa-stat art-coa-stat-sales"><span class="val"><?php echo esc_html(\WPArtCOA\Shop::format_price($stats['volume_sold'], 'IRR')); ?></span><span class="lbl"><?php echo esc_html(I18n::t('auction_volume')); ?></span></div>
            </div>

            <div id="art-form-auction" class="art-coa-form" style="display:none">
                <form method="post">
                    <?php wp_nonce_field('art_coa_admin_auction'); ?>
                    <input type="hidden" name="art_coa_action" value="create_auction">
                    <p><label><?php echo esc_html(I18n::t('artwork')); ?> *<br>
                        <select name="artwork_id" required class="widefat">
                            <option value=""><?php echo esc_html(I18n::t('select')); ?></option>
                            <?php foreach ($artworks as $art): ?>
                                <option value="<?php echo (int)$art->id; ?>"><?php echo esc_html($art->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label></p>
                    <p><label><?php echo esc_html(I18n::t('collector')); ?> (<?php echo I18n::is_fa() ? 'مالک فعلی / فروشنده' : 'current owner / seller'; ?>)<br>
                        <select name="collector_id" class="widefat">
                            <option value="0"><?php echo esc_html(I18n::t('none')); ?></option>
                            <?php foreach ($collectors as $c): ?>
                                <option value="<?php echo (int)$c->id; ?>"><?php echo esc_html($c->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label></p>
                    <p><label><?php echo esc_html(I18n::t('start_price')); ?> *<br><input type="number" name="start_price" step="any" min="0" required class="widefat"></label></p>
                    <p><label><?php echo esc_html(I18n::t('reserve_price')); ?><br><input type="number" name="reserve_price" step="any" min="0" class="widefat"></label></p>
                    <p><label><?php echo I18n::is_fa() ? 'برآورد حداقل' : 'Low estimate'; ?><br><input type="number" name="estimate_low" step="any" min="0" class="widefat"></label></p>
                    <p><label><?php echo I18n::is_fa() ? 'برآورد حداکثر' : 'High estimate'; ?><br><input type="number" name="estimate_high" step="any" min="0" class="widefat"></label></p>
                    <p><label><?php echo I18n::is_fa() ? 'شماره لات' : 'Lot number'; ?><br><input type="text" name="lot_number" maxlength="50" class="widefat"></label></p>
                    <p><label><?php echo I18n::is_fa() ? 'نام حراجی / برگزارکننده' : 'Auction house'; ?><br><input type="text" name="auction_house" maxlength="190" class="widefat"></label></p>
                    <p><label><?php echo I18n::is_fa() ? 'محل برگزاری یا تحویل' : 'Auction / collection location'; ?><br><input type="text" name="auction_location" maxlength="190" class="widefat"></label></p>
                    <p><label><?php echo esc_html(I18n::t('min_increment')); ?><br><input type="number" name="min_increment" step="any" min="1" value="1" class="widefat"></label></p>
                    <p><label><?php echo esc_html(I18n::t('currency')); ?><br>
                        <select name="currency" class="widefat">
                            <option value="IRR">IRR</option><option value="IRT">IRT</option>
                            <option value="USD">USD</option><option value="EUR">EUR</option>
                        </select>
                    </label></p>
                    <p><label><?php echo esc_html(I18n::t('auction_days')); ?> *<br><input type="number" name="days" min="1" max="30" value="7" required class="widefat"></label></p>
                    <p><label><?php echo esc_html(I18n::t('sale_note')); ?><br><textarea name="note" rows="2" class="widefat"></textarea></label></p>
                    <details class="art-coa-auction-extra"><summary><?php echo I18n::is_fa() ? 'گزارش و اطلاعات تکمیلی لات' : 'Lot reports and additional information'; ?></summary>
                        <p><label><?php echo I18n::is_fa() ? 'گزارش وضعیت و سلامت اثر' : 'Condition report'; ?><br><textarea name="condition_report" rows="3" class="widefat"></textarea></label></p>
                        <p><label><?php echo I18n::is_fa() ? 'پیشینه و زنجیره مالکیت' : 'Provenance and ownership history'; ?><br><textarea name="provenance" rows="3" class="widefat"></textarea></label></p>
                        <p><label><?php echo I18n::is_fa() ? 'اطلاعات اصالت و مستندات' : 'Authenticity and documentation'; ?><br><textarea name="authenticity" rows="3" class="widefat"></textarea></label></p>
                        <p><label><?php echo I18n::is_fa() ? 'زمان و شیوه بازدید' : 'Viewing schedule'; ?><br><textarea name="viewing_info" rows="2" class="widefat"></textarea></label></p>
                        <p><label><?php echo I18n::is_fa() ? 'هزینه، ارسال و تحویل اثر' : 'Shipping, fees and collection'; ?><br><textarea name="shipping_terms" rows="3" class="widefat"></textarea></label></p>
                    </details>
                    <p>
                        <button class="button button-primary"><?php echo esc_html(I18n::t('start_auction')); ?></button>
                        <button type="button" class="button" onclick="document.getElementById('art-form-auction').style.display='none'"><?php echo esc_html(I18n::t('cancel')); ?></button>
                    </p>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>ID</th>
                    <th><?php echo esc_html(I18n::t('artwork')); ?></th>
                    <th><?php echo esc_html(I18n::t('collector')); ?></th>
                    <th><?php echo I18n::is_fa() ? 'لات / حراجی' : 'Lot / Auction house'; ?></th>
                    <th><?php echo esc_html(I18n::t('current_bid')); ?></th>
                    <th><?php echo esc_html(I18n::t('bid_count')); ?></th>
                    <th><?php echo esc_html(I18n::t('status')); ?></th>
                    <th><?php echo esc_html(I18n::t('ends_at')); ?></th>
                    <th><?php echo I18n::is_fa() ? 'پرداخت برنده' : 'Winner payment'; ?></th>
                    <th><?php echo esc_html(I18n::t('actions')); ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="10" class="aap-center"><?php echo esc_html(I18n::t('auction_empty')); ?></td></tr>
                <?php else: foreach ($items as $row): ?>
                    <tr>
                        <td><?php echo (int)$row->id; ?></td>
                        <td><strong><?php echo esc_html($row->artwork_title); ?></strong></td>
                        <td><?php echo esc_html($row->collector_name ?: '—'); ?></td>
                        <td><?php echo esc_html(trim(($row->lot_number ? '#' . $row->lot_number : '') . ($row->auction_house ? ' · ' . $row->auction_house : '')) ?: '—'); ?></td>
                        <td><?php echo esc_html(\WPArtCOA\Shop::format_price($row->current_bid ?? $row->start_price, $row->currency)); ?></td>
                        <td><?php echo (int)$row->bid_count; ?></td>
                        <td><span class="art-badge"><?php echo esc_html($row->status); ?></span></td>
                        <td><?php echo esc_html(Dates::format($row->ends_at_utc, true)); ?></td>
                        <td>
                            <?php
                            $pay = (string) ($row->payment_status ?? '');
                            $pay_labels = I18n::is_fa()
                                ? ['pending' => 'در انتظار پرداخت', 'paid' => 'پرداخت‌شده', 'defaulted' => 'عدم پرداخت']
                                : ['pending' => 'Awaiting payment', 'paid' => 'Paid', 'defaulted' => 'Defaulted'];
                            if ($pay !== '') {
                                echo '<span class="art-badge">' . esc_html($pay_labels[$pay] ?? $pay) . '</span>';
                                if ($row->current_bidder_name) { echo '<br><small>' . esc_html($row->current_bidder_name) . ' · <bdi>' . esc_html((string) $row->current_bidder_email) . '</bdi></small>'; }
                                if ($pay === 'pending' && !empty($row->payment_due_utc)) {
                                    $overdue = strtotime($row->payment_due_utc . ' UTC') < time();
                                    echo '<br><small' . ($overdue ? ' style="color:#b32d2e"' : '') . '>' . esc_html((I18n::is_fa() ? 'مهلت: ' : 'Due: ') . Dates::format($row->payment_due_utc, true) . ($overdue ? (I18n::is_fa() ? ' (گذشته)' : ' (overdue)') : '')) . '</small>';
                                }
                                if ($pay === 'paid' && !empty($row->paid_at_utc)) { echo '<br><small>' . esc_html(Dates::format($row->paid_at_utc, true)) . '</small>'; }
                            } else { echo '—'; }
                            ?>
                        </td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(home_url('/art-coa/auction/' . (int)$row->id)); ?>" target="_blank"><?php echo esc_html(I18n::t('view_page')); ?></a>
                            <?php if ($row->status === 'active'): ?>
                            <form method="post" onsubmit="return confirm('OK?');" class="aap-inline">
                                <?php wp_nonce_field('art_coa_admin_auction'); ?>
                                <input type="hidden" name="art_coa_action" value="close_auction">
                                <input type="hidden" name="auction_id" value="<?php echo (int)$row->id; ?>">
                                <button class="button button-small"><?php echo esc_html(I18n::t('auction_closed')); ?></button>
                            </form>
                            <?php endif; ?>
                            <?php if (($row->payment_status ?? '') === 'pending'): ?>
                            <form method="post" class="aap-inline" onsubmit="return confirm('<?php echo esc_js(I18n::is_fa() ? 'پرداخت دریافت شد؟' : 'Payment received?'); ?>');">
                                <?php wp_nonce_field('art_coa_admin_auction'); ?>
                                <input type="hidden" name="art_coa_action" value="mark_paid">
                                <input type="hidden" name="auction_id" value="<?php echo (int)$row->id; ?>">
                                <label><input type="checkbox" name="transfer" value="1" checked> <?php echo I18n::is_fa() ? 'ثبت انتقال مالکیت' : 'Record ownership transfer'; ?></label>
                                <button class="button button-small button-primary"><?php echo I18n::is_fa() ? 'ثبت پرداخت' : 'Mark paid'; ?></button>
                            </form>
                            <form method="post" class="aap-inline" onsubmit="return confirm('<?php echo esc_js(I18n::is_fa() ? 'فروش لغو و اثر به کاتالوگ برگردانده شود؟' : 'Cancel the sale and return the artwork to the catalogue?'); ?>');">
                                <?php wp_nonce_field('art_coa_admin_auction'); ?>
                                <input type="hidden" name="art_coa_action" value="mark_default">
                                <input type="hidden" name="auction_id" value="<?php echo (int)$row->id; ?>">
                                <button class="button button-small"><?php echo I18n::is_fa() ? 'عدم پرداخت' : 'Not paid'; ?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function handle_admin_auction(): void
    {
        if (empty($_POST['art_coa_action']) || !current_user_can('manage_options')) {
            return;
        }
        $action = sanitize_key(\WPArtCOA\Security::input_scalar($_POST, 'art_coa_action'));
        if (!in_array($action, ['create_auction', 'close_auction', 'mark_paid', 'mark_default'], true)) {
            return;
        }
        check_admin_referer('art_coa_admin_auction');

        if (in_array($action, ['close_auction', 'mark_paid', 'mark_default'], true)) {
            global $wpdb;
            $id = absint($_POST['auction_id'] ?? 0);
            $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . \WPArtCOA\Auction::listings_table() . ' WHERE id = %d', $id));
            $msg = '';
            if ($row && $action === 'close_auction' && $row->status === 'active') {
                \WPArtCOA\Auction::finalize($row);
                $msg = I18n::t('auction_closed');
            } elseif ($row && $action === 'mark_paid' && ($row->payment_status ?? '') === 'pending') {
                $kind = \WPArtCOA\Auction::mark_paid($row, !empty($_POST['transfer']));
                $msg = I18n::is_fa()
                    ? ($kind === 'collector' ? 'پرداخت ثبت و مالکیت به مجموعه‌دار برنده منتقل شد.' : ($kind === 'buyer' ? 'پرداخت ثبت شد. برنده مجموعه‌دار ثبت‌شده‌ای ندارد؛ انتقال فقط در تاریخچه ثبت شد و مالک اثر را دستی تنظیم کنید.' : 'پرداخت ثبت شد.'))
                    : ($kind === 'collector' ? 'Payment recorded and ownership moved to the winning collector.' : ($kind === 'buyer' ? 'Payment recorded. The winner has no collector profile; the transfer was logged only, set the owner manually.' : 'Payment recorded.'));
            } elseif ($row && $action === 'mark_default' && ($row->payment_status ?? '') === 'pending') {
                \WPArtCOA\Auction::mark_default($row);
                $msg = I18n::is_fa() ? 'فروش لغو و اثر به کاتالوگ برگشت.' : 'Sale cancelled and the artwork returned to the catalogue.';
            }
            if ($msg !== '') {
                echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
            }
            return;
        }

        // create_auction — post to Auction handler fields
        $_POST['art_coa_auction_create'] = '1';
        $_POST['_wpnonce'] = wp_create_nonce('art_coa_auction_create');
        // Simulate: call create logic inline
        $artwork_id = absint($_POST['artwork_id'] ?? 0);
        $collector_id = absint($_POST['collector_id'] ?? 0);
        $start = (float)($_POST['start_price'] ?? 0);
        $reserve = ($_POST['reserve_price'] ?? '') !== '' ? (float)$_POST['reserve_price'] : null;
        $inc = max(0.01, (float)($_POST['min_increment'] ?? 1));
        $currency = sanitize_text_field(\WPArtCOA\Security::input_scalar($_POST, 'currency', 'IRR')) ?: 'IRR';
        $days = max(1, min(30, absint($_POST['days'] ?? 7)));
        $note = sanitize_textarea_field(\WPArtCOA\Security::input_scalar($_POST, 'note'));
        $post_text = static function (string $key): string {
            $value = isset($_POST[$key]) && is_scalar($_POST[$key]) ? sanitize_text_field(wp_unslash((string) $_POST[$key])) : '';
            return \Artiey_AET_Text::substr($value, 0, 190);
        };
        $post_area = static function (string $key): string {
            $value = isset($_POST[$key]) && is_scalar($_POST[$key]) ? sanitize_textarea_field(wp_unslash((string) $_POST[$key])) : '';
            return \Artiey_AET_Text::substr($value, 0, 5000);
        };
        $post_amount = static fn(string $key): ?float => isset($_POST[$key]) && is_scalar($_POST[$key]) && '' !== (string) $_POST[$key] ? round(min(9999999999.99, max(0, (float) wp_unslash($_POST[$key]))), 2) : null;
        $lot_number = \Artiey_AET_Text::substr($post_text('lot_number'), 0, 50);
        $auction_house = $post_text('auction_house');
        $auction_location = $post_text('auction_location');
        $condition_report = $post_area('condition_report');
        $provenance = $post_area('provenance');
        $authenticity = $post_area('authenticity');
        $shipping_terms = $post_area('shipping_terms');
        $viewing_info = $post_area('viewing_info');
        $estimate_low = $post_amount('estimate_low');
        $estimate_high = $post_amount('estimate_high');
        $currency = in_array($currency, ['IRR', 'IRT', 'USD', 'EUR'], true) ? $currency : 'IRR';
        if (!$artwork_id || $start <= 0 || ($estimate_low !== null && $estimate_high !== null && $estimate_low > $estimate_high)) {
            echo '<div class="notice notice-error"><p>' . esc_html(I18n::t('error')) . '</p></div>';
            return;
        }
        global $wpdb;
        $aw = $wpdb->get_row($wpdb->prepare(
            'SELECT price, current_owner_type, current_owner_id FROM ' . Database::table('artworks') . ' WHERE id = %d',
            $artwork_id
        ));
        if (!$collector_id && $aw && $aw->current_owner_type === 'collector') {
            $collector_id = (int) $aw->current_owner_id;
        }
        $now = gmdate('Y-m-d H:i:s');
        $ends = gmdate('Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS);
        $inserted = $wpdb->insert(\WPArtCOA\Auction::listings_table(), [
            'artwork_id'     => $artwork_id,
            'collector_id'   => $collector_id ?: 0,
            'seller_user_id' => get_current_user_id(),
            'start_price'    => $start,
            'reserve_price'  => $reserve,
            'currency'       => $currency,
            'min_increment'  => $inc,
            'original_price' => $aw && $aw->price !== null ? (float)$aw->price : null,
            'lot_number' => $lot_number,
            'auction_house' => $auction_house,
            'auction_location' => $auction_location,
            'estimate_low' => $estimate_low,
            'estimate_high' => $estimate_high,
            'condition_report' => $condition_report,
            'provenance' => $provenance,
            'authenticity' => $authenticity,
            'shipping_terms' => $shipping_terms,
            'viewing_info' => $viewing_info,
            'note'           => $note,
            'status'         => 'active',
            'starts_at_utc'  => $now,
            'ends_at_utc'    => $ends,
            'created_at_utc' => $now,
            'bid_count'      => 0,
        ]);
        if (false === $inserted) {
            echo '<div class="notice notice-error"><p>' . esc_html(I18n::t('error')) . '</p></div>';
            return;
        }
        \WPArtCOA\Audit::log('artwork', $artwork_id, 'auction_started_by_admin', ['auction_id' => (int)$wpdb->insert_id]);
        \WPArtCOA\Cache::flush_stats();
        echo '<div class="notice notice-success"><p>' . esc_html(I18n::t('auction_started')) . '</p></div>';
    }
}
