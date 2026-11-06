<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Certificate;

defined('ABSPATH') || exit;

final class Shortcodes
{
    public static function register(): void
    {
        add_shortcode('art_coa_verify', [self::class, 'verify']);
        add_shortcode('art_coa_certificate', [self::class, 'certificate_card']);
        add_shortcode('art_coa_calendar', [self::class, 'calendar']);
        add_shortcode('art_coa_timeline', [self::class, 'timeline']);
        add_shortcode('art_coa_artwork_history', [self::class, 'artwork_history']);
        add_shortcode('art_coa_auction_timeline', [self::class, 'auction_timeline']);
        add_shortcode('art_coa_directory', [self::class, 'directory']);
        add_shortcode('art_coa_artist_bio', [ArtistBio::class, 'shortcode']);
        add_shortcode('art_coa_charts', [ChartKit::class, 'shortcode']);
        add_shortcode('artiey_charts', [ChartKit::class, 'shortcode']);
    }

    public static function calendar($atts = []): string
    {
        $atts = shortcode_atts([
            'year'  => '',
            'month' => '',
        ], $atts, 'art_coa_calendar');

        $args = [];
        if ($atts['year'] !== '') {
            $args['year'] = (int) $atts['year'];
        }
        if ($atts['month'] !== '') {
            $args['month'] = (int) $atts['month'];
        }
        return Calendar::render($args);
    }

    public static function timeline($atts = []): string
    {
        $atts = shortcode_atts([
            'year' => '', 'type' => 'all', 'range' => 'year', 'status' => 'all', 'layout' => 'cards', 'columns' => 3,
            'group' => 'yes', 'order' => 'desc', 'limit' => 60, 'filters' => 'yes', 'show_year' => 'yes',
            'show_kind' => 'yes', 'show_status' => 'no', 'show_date' => 'yes', 'show_countdown' => 'no',
            'show_subtitle' => 'yes', 'show_location' => 'no', 'show_poster' => 'yes', 'button' => '',
            'target' => '', 'item_tag' => 'h3', 'empty' => '', 'heading' => '',
        ], (array) $atts, 'art_coa_timeline');
        $yes = static fn(string $k): bool => $atts[$k] !== 'no';

        $year = $atts['year'] !== '' ? (int) $atts['year'] : (isset($_GET['art_coa_tl_year']) ? (int) $_GET['art_coa_tl_year'] : Calendar::current_jalali_year()); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $type = isset($_GET['art_coa_tl_type']) ? sanitize_key((string) $_GET['art_coa_tl_type']) : sanitize_key($atts['type']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        return Timeline::render([
            'year' => $year, 'type' => $type ?: 'all', 'range' => $atts['range'], 'status' => $atts['status'],
            'layout' => $atts['layout'], 'columns' => (int) $atts['columns'], 'group' => $yes('group'), 'order' => $atts['order'],
            'limit' => (int) $atts['limit'], 'show_filters' => $yes('filters'), 'show_year' => $yes('show_year'),
            'show_kind' => $yes('show_kind'), 'show_status' => $atts['show_status'] === 'yes', 'show_date' => $yes('show_date'),
            'show_countdown' => $atts['show_countdown'] === 'yes', 'show_subtitle' => $yes('show_subtitle'),
            'show_location' => $atts['show_location'] === 'yes', 'show_poster' => $yes('show_poster'),
            'show_button' => $atts['button'] !== '', 'button_text' => sanitize_text_field((string) $atts['button']),
            'link_target' => $atts['target'] === '_blank' ? '_blank' : '', 'item_tag' => $atts['item_tag'],
            'empty_text' => sanitize_text_field((string) $atts['empty']), 'heading_text' => $atts['heading'] === 'no' ? '' : sanitize_text_field((string) $atts['heading']),
            'show_heading' => $atts['heading'] !== 'no',
        ]);
    }

    public static function auction_timeline($atts = []): string
    {
        $atts = shortcode_atts([
            'status' => 'all', 'artist' => 0, 'gallery' => 0, 'layout' => 'line', 'columns' => 3, 'group' => 'no', 'order' => 'desc',
            'limit' => 12, 'show_image' => 'yes', 'show_artist' => 'yes', 'show_status' => 'yes', 'show_date' => 'yes',
            'show_countdown' => 'yes', 'show_price' => 'yes', 'show_bids' => 'yes', 'show_lot' => 'no', 'show_estimate' => 'no',
            'button' => 'مشاهده حراج', 'target' => '', 'item_tag' => 'h3', 'empty' => '',
        ], (array) $atts, 'art_coa_auction_timeline');
        $yes = static fn(string $k): bool => $atts[$k] === 'yes';

        return \WPArtCOA\AuctionTimeline::render([
            'status' => sanitize_key((string) $atts['status']), 'artist_id' => absint($atts['artist']), 'gallery_id' => absint($atts['gallery']),
            'layout' => $atts['layout'], 'columns' => (int) $atts['columns'], 'group' => $yes('group'), 'order' => $atts['order'],
            'limit' => (int) $atts['limit'], 'show_image' => $yes('show_image'), 'show_artist' => $yes('show_artist'),
            'show_status' => $yes('show_status'), 'show_date' => $yes('show_date'), 'show_countdown' => $yes('show_countdown'),
            'show_price' => $yes('show_price'), 'show_bids' => $yes('show_bids'), 'show_lot' => $yes('show_lot'),
            'show_estimate' => $yes('show_estimate'), 'button_text' => sanitize_text_field((string) $atts['button']),
            'link_target' => $atts['target'] === '_blank' ? '_blank' : '', 'item_tag' => $atts['item_tag'],
            'empty_text' => sanitize_text_field((string) $atts['empty']),
        ]);
    }

    public static function artwork_history($atts = []): string
    {
        $atts = shortcode_atts([
            'source' => 'all',
            'artwork_id' => 0,
            'current_artwork' => 'yes',
            'limit' => 12,
            'layout' => 'line',
            'show_image' => 'yes',
            'show_price' => 'yes',
            'show_owner' => 'no',
        ], (array) $atts, 'art_coa_artwork_history');

        return \WPArtCOA\ArtworkHistoryTimeline::render([
            'source' => sanitize_key((string) $atts['source']),
            'artwork_id' => absint($atts['artwork_id']),
            'current_artwork' => $atts['current_artwork'] === 'yes',
            'limit' => absint($atts['limit']),
            'layout' => sanitize_key((string) $atts['layout']),
            'show_image' => $atts['show_image'] === 'yes',
            'show_price' => $atts['show_price'] === 'yes',
            'show_owner' => $atts['show_owner'] === 'yes',
        ]);
    }

    public static function verify($atts = []): string
    {
        $atts = shortcode_atts(['title' => ''], (array) $atts, 'art_coa_verify');
        $title = sanitize_text_field((string) $atts['title']);
        $title = $title !== '' ? $title : \WPArtCOA\I18n::t('verify_title');
        $serial = sanitize_text_field(wp_unslash($_GET['art_coa_verify'] ?? ($_GET['coa_verify'] ?? '')));

        ob_start();
        ?>
        <div class="art-coa-verify">
            <h2>🎖️ <?php echo esc_html($title); ?></h2>
            <form method="get" class="aap-s-5c43c0">
                <input type="text" name="art_coa_verify" value="<?php echo esc_attr($serial); ?>"
                       placeholder="شماره سریال (مثلاً COA-2026-A7K9M2)" required>
                <button type="submit" class="button">تأیید</button>
            </form>
            <?php if ($serial): ?>
                <?php $result = Certificate::verify($serial); ?>
                <?php if (!empty($result['valid'])): ?>
                    <div class="art-coa-result valid">
                        <h3>✅ <?php echo esc_html(\WPArtCOA\I18n::t('cert_valid')); ?></h3>
                        <table>
                            <tr><td>سریال</td><td><code><?php echo esc_html($result['data']['serial_number']); ?></code></td></tr>
                            <tr><td>اثر</td><td><?php echo esc_html($result['data']['artwork_title']); ?></td></tr>
                            <tr><td>هنرمند</td><td><?php echo esc_html($result['data']['artist_name']); ?></td></tr>
                            <tr><td>مالک</td><td><?php echo esc_html($result['data']['issued_to']); ?></td></tr>
                            <tr><td>تاریخ صدور</td><td><?php echo esc_html(\WPArtCOA\Dates::format($result['data']['issued_at_utc'], true)); ?></td></tr>
                            <?php if (!empty($result['data']['medium'])): ?>
                                <tr><td>متریال</td><td><?php echo esc_html($result['data']['medium']); ?></td></tr>
                            <?php endif; ?>
                            <tr><td>وضعیت</td><td>فعال</td></tr>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="art-coa-result invalid">
                        <h3>❌ <?php echo esc_html(\WPArtCOA\I18n::t('cert_invalid')); ?></h3>
                        <p><?php echo esc_html($result['error'] ?? 'خطای ناشناخته'); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function certificate_card($atts = []): string
    {
        $atts = shortcode_atts(['serial' => ''], $atts, 'art_coa_certificate');
        $serial = sanitize_text_field($atts['serial']);
        if ($serial === '') {
            return '<p>سریال مشخص نشده.</p>';
        }

        $result = Certificate::verify($serial);
        if (empty($result['valid'])) {
            return '<div class="art-coa-result invalid"><p>' . esc_html($result['error'] ?? 'نامعتبر') . '</p></div>';
        }

        $d = $result['data'];
        ob_start();
        ?>
        <div class="art-coa-cert-card">
            <p class="serial"><strong>سریال:</strong> <?php echo esc_html($d['serial_number']); ?></p>
            <p><strong>اثر:</strong> <?php echo esc_html($d['artwork_title']); ?></p>
            <p><strong>هنرمند:</strong> <?php echo esc_html($d['artist_name']); ?></p>
            <p><strong>مالک:</strong> <?php echo esc_html($d['issued_to']); ?></p>
            <p><strong>صدور:</strong> <?php echo esc_html($d['issued_at_utc']); ?></p>
            <p class="aap-s-ea6b58">✓ معتبر</p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function directory($atts = []): string
    {
        $atts = shortcode_atts(['show' => 'all'], $atts, 'art_coa_directory');
        $g = \WPArtCOA\Stats::global();
        ob_start();
        echo '<div class="art-coa-directory" dir="rtl">';
        echo '<div class="art-coa-stats-bar">';
        foreach ([
            'هنرمندان' => $g['artists'],
            'گالری‌ها' => $g['galleries'],
            'آثار' => $g['artworks'],
            'نمایشگاه‌ها' => $g['exhibitions'],
            'گواهی فعال' => $g['active_certs'],
            'انتقال مالکیت' => $g['transfers'],
        ] as $l => $v) {
            echo '<div class="art-coa-stat-pill"><span class="val">' . esc_html(\WPArtCOA\Calendar::to_persian_digits((string)$v)) . '</span><span class="lbl">' . esc_html($l) . '</span></div>';
        }
        echo '</div>';
        echo '<div class="art-coa-dir-links">';
        echo '<a href="' . esc_url(\WPArtCOA\ContentSync::artist_archive_url()) . '">هنرمندان</a>';
        echo '<a href="' . esc_url(home_url('/art-coa/galleries')) . '">گالری‌ها</a>';
        echo '<a href="' . esc_url(home_url('/art-coa/exhibitions')) . '">نمایشگاه‌ها</a>';
        echo '<a href="' . esc_url(home_url('/art-coa/artworks')) . '">آثار</a>';
        echo '</div></div>';
        return (string) ob_get_clean();
    }

}
