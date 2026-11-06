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
trait Dashboard
{
    public static function page_dashboard(): void
    {
        global $wpdb;
        $stats = \WPArtCOA\Stats::global();
        $verify_page = get_option('wp_art_coa_page_verify');
        ?>
        <div class="wrap art-coa-wrap">
            <h1>🎖️ <?php echo esc_html(I18n::t('dashboard')); ?> — <?php echo esc_html(I18n::t('plugin_name')); ?></h1>

            <h2 class="art-coa-dash-heading"><?php echo esc_html(I18n::t('stats_artworks')); ?></h2>
            <div class="art-coa-stats">
                <div class="art-coa-stat art-coa-stat-primary"><span class="val"><?php echo esc_html((string)$stats['artworks']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('total_artworks')); ?></span></div>
                <div class="art-coa-stat success"><span class="val"><?php echo esc_html((string)$stats['artworks_published']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('published')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['artworks_draft']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('draft')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['artworks_owned']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('owned')); ?></span></div>
            </div>

            <h2 class="art-coa-dash-heading"><?php echo esc_html(I18n::t('stats_sales')); ?></h2>
            <?php
            $trend = \WPArtCOA\Cache::get('trend_summary', static fn() => \WPArtCOA\Charts::trend_summary(), 180);
            $monthly = \WPArtCOA\Cache::get('monthly_sales_12', static fn() => \WPArtCOA\Charts::monthly_sales(12), 180);
            $recent = \WPArtCOA\Cache::get('recent_sales_10', static fn() => \WPArtCOA\Charts::recent_sales(10), 120);
            $arrow = $trend['direction'] === 'up' ? '▲' : ($trend['direction'] === 'down' ? '▼' : '●');
            $trend_class = $trend['direction'] === 'up' ? 'trend-up' : ($trend['direction'] === 'down' ? 'trend-down' : 'trend-flat');
            ?>
            <div class="art-coa-stats">
                <div class="art-coa-stat art-coa-stat-sales"><span class="val"><?php echo esc_html((string)$stats['sales_total']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('sales_total')); ?></span></div>
                <div class="art-coa-stat art-coa-stat-sales"><span class="val"><?php echo esc_html((string)$stats['sales_month']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('sales_month')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['sales_to_collector']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('to_collector')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['sales_to_buyer']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('to_buyer')); ?></span></div>
            </div>

            <div class="art-coa-trend-box <?php echo esc_attr($trend_class); ?>">
                <strong><?php echo esc_html(I18n::t('trend_30')); ?>:</strong>
                <?php echo esc_html((string)$trend['current_30']); ?> <?php echo esc_html(I18n::t('sales')); ?>
                <span class="art-coa-trend-arrow"><?php echo esc_html($arrow); ?>
                    <?php echo esc_html((string)$trend['change_pct']); ?>٪
                </span>
                <?php echo esc_html(I18n::t('vs_previous')); ?> (<?php echo esc_html((string)$trend['previous_30']); ?>)
            </div>

            <div class="art-coa-charts-grid">
                <div class="art-coa-chart-card">
                    <h3><?php echo esc_html(I18n::t('chart_monthly')); ?></h3>
                    <canvas id="artCoaSalesChart" height="220"></canvas>
                </div>
                <div class="art-coa-chart-card">
                    <h3><?php echo esc_html(I18n::t('chart_stack')); ?></h3>
                    <canvas id="artCoaSalesStackChart" height="220"></canvas>
                </div>
            </div>

            <h3 class="aap-s-ac1c8b"><?php echo esc_html(I18n::t('recent_transfers')); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th><?php echo esc_html(I18n::t('date')); ?></th><th><?php echo esc_html(I18n::t('artwork')); ?></th><th><?php echo esc_html(I18n::t('new_owner')); ?></th><th><?php echo esc_html(I18n::t('type')); ?></th></tr></thead>
                <tbody>
                <?php if (!$recent): ?>
                    <tr><td colspan="4" class="aap-center"><?php echo esc_html(I18n::t('no_transfers')); ?></td></tr>
                <?php else: foreach ($recent as $row): ?>
                    <tr>
                        <td><?php echo esc_html(Dates::format($row->transferred_at_utc, true)); ?></td>
                        <td><?php echo esc_html($row->artwork_title ?: ('#' . $row->artwork_id)); ?></td>
                        <td><?php echo esc_html($row->to_name); ?></td>
                        <td><?php echo esc_html($row->to_type); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <script>
            window.artCoaChartData = <?php echo wp_json_encode([
                'labels' => $monthly['labels'],
                'values' => $monthly['values'],
                'byType' => $monthly['by_type'],
                'i18n'   => [
                    'sales'     => I18n::t('sales_total'),
                    'collector' => I18n::t('collector'),
                    'buyer'     => I18n::t('buyer'),
                    'other'     => I18n::is_fa() ? 'سایر' : 'Other',
                ],
            ], JSON_UNESCAPED_UNICODE); ?>;
            </script>

            <?php
            $auc_stats = \WPArtCOA\AuctionStats::overview();
            $auc_monthly = \WPArtCOA\AuctionStats::monthly_dynamics(12);
            $auc_daily = \WPArtCOA\AuctionStats::daily_bids(14);
            ?>
            <h2 class="art-coa-dash-heading"><?php echo esc_html(I18n::t('auction_title')); ?> — <?php echo esc_html(I18n::t('stats_sales')); ?></h2>
            <div class="art-coa-stats">
                <div class="art-coa-stat"><span class="val"><?php echo (int)$auc_stats['auctions_active']; ?></span><span class="lbl"><?php echo esc_html(I18n::t('auctions_active')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo (int)$auc_stats['auctions_sold']; ?></span><span class="lbl"><?php echo esc_html(I18n::t('auctions_sold')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo (int)$auc_stats['bids_total']; ?></span><span class="lbl"><?php echo esc_html(I18n::t('bids_total')); ?></span></div>
                <div class="art-coa-stat art-coa-stat-sales"><span class="val"><?php echo (int)$auc_stats['transfers_month']; ?></span><span class="lbl"><?php echo esc_html(I18n::t('sales_month')); ?></span></div>
            </div>
            <div class="art-coa-charts-grid">
                <div class="art-coa-chart-card">
                    <h3><?php echo esc_html(I18n::t('chart_auction_volume')); ?></h3>
                    <canvas id="artCoaAuctionVolumeChart" height="220"></canvas>
                </div>
                <div class="art-coa-chart-card">
                    <h3><?php echo esc_html(I18n::t('chart_auction_bids')); ?></h3>
                    <canvas id="artCoaAuctionBidsChart" height="220"></canvas>
                </div>
            </div>
            <script>
            window.artCoaAuctionChartData = <?php echo wp_json_encode([
                'monthly' => $auc_monthly,
                'daily'   => $auc_daily,
                'i18n'    => [
                    'volume' => I18n::t('auction_volume'),
                    'sold'   => I18n::t('auctions_sold'),
                    'bids'   => I18n::t('bids_total'),
                ],
            ], JSON_UNESCAPED_UNICODE); ?>;
            </script>

            <h2 class="art-coa-dash-heading"><?php echo esc_html(I18n::t('stats_ecosystem')); ?></h2>
            <div class="art-coa-stats">
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['artists']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('ecosystem_artists')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['galleries']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('ecosystem_galleries')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['exhibitions']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('ecosystem_exhibitions')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['certificates']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('ecosystem_certs')); ?></span></div>
                <div class="art-coa-stat success"><span class="val"><?php echo esc_html((string)$stats['active_certs']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('ecosystem_active')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['collectors']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('ecosystem_collectors')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['buyers']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('ecosystem_buyers')); ?></span></div>
                <div class="art-coa-stat"><span class="val"><?php echo esc_html((string)$stats['curators']); ?></span><span class="lbl"><?php echo esc_html(I18n::t('ecosystem_curators')); ?></span></div>
            </div>

            <div class="art-coa-dir-links aap-s-39bb9c">
                <a class="button" href="<?php echo esc_url(\WPArtCOA\ContentSync::artist_archive_url()); ?>" target="_blank"><?php echo esc_html(I18n::t('page_artists')); ?></a>
                <a class="button" href="<?php echo esc_url(home_url('/art-coa/galleries')); ?>" target="_blank"><?php echo esc_html(I18n::t('page_galleries')); ?></a>
                <a class="button" href="<?php echo esc_url(home_url('/art-coa/exhibitions')); ?>" target="_blank"><?php echo esc_html(I18n::t('page_exhibitions')); ?></a>
                <a class="button" href="<?php echo esc_url(home_url('/art-coa/artworks')); ?>" target="_blank"><?php echo esc_html(I18n::t('page_artworks')); ?></a>
            </div>
            <div class="art-coa-box">
                <h2><?php echo esc_html(I18n::t('shortcodes')); ?></h2>
                <p><code>[art_coa_verify]</code> — فرم تأیید گواهی</p>
                <p><code>[art_coa_certificate serial="COA-2026-XXXXXX"]</code> — نمایش یک گواهی</p>
                <p><code>[art_coa_calendar]</code> یا <code>[art_coa_calendar month="8" year="1405"]</code> — تقویم هفتگی گالری‌ها</p>
                <p><code>[art_coa_timeline]</code> یا <code>[art_coa_timeline year="1405"]</code> — تایم‌لاین رویدادها</p>
                <?php if ($verify_page): ?>
                    <p>صفحه تأیید: <a href="<?php echo esc_url(get_permalink($verify_page)); ?>" target="_blank"><?php echo esc_html(get_the_title($verify_page)); ?></a></p>
                <?php endif; ?>
                <p>ویجت‌های المنتور در دسته <strong>Art COA</strong> در دسترس هستند.</p>
            </div>
        </div>
        <?php
    }
}
