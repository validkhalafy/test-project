<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Timeline of auctions (live, upcoming, closed, sold) using the same markup/classes as the event timeline,
 * so one set of styles and Elementor controls serves both. Bidder identities are never printed.
 */
final class AuctionTimeline
{
    private const STATUS_GROUPS = [
        'live'     => ['active'],
        'upcoming' => ['scheduled'],
        'closed'   => ['ended', 'ended_reserve', 'sold'],
        'sold'     => ['sold'],
        'all'      => ['scheduled', 'active', 'ended', 'ended_reserve', 'sold'],
    ];

    private const LABELS = [
        'active' => 'حراج زنده', 'scheduled' => 'به‌زودی', 'ended' => 'پایان یافته',
        'ended_reserve' => 'پایان بدون رسیدن به حد نصاب', 'sold' => 'فروخته شد',
    ];

    public static function remaining(?string $ends_utc, ?int $now = null): string
    {
        if (!$ends_utc) {
            return '';
        }
        $left = strtotime($ends_utc . ' UTC') - ($now ?? time());
        if ($left <= 0) {
            return '';
        }
        $d = intdiv($left, 86400);
        $h = intdiv($left % 86400, 3600);
        $m = intdiv($left % 3600, 60);
        $fa = static fn(int $n): string => Calendar::to_persian_digits((string) $n);
        if ($d > 0) {
            return $fa($d) . ' روز' . ($h > 0 ? ' و ' . $fa($h) . ' ساعت' : '') . ' مانده';
        }
        if ($h > 0) {
            return $fa($h) . ' ساعت' . ($m > 0 ? ' و ' . $fa($m) . ' دقیقه' : '') . ' مانده';
        }
        return $fa(max(1, $m)) . ' دقیقه مانده';
    }

    /** @param array<string,mixed> $args */
    public static function render(array $args = []): string
    {
        Auction::maybe_migrate();
        global $wpdb;
        $flag = static fn(string $k, bool $d = true): bool => array_key_exists($k, $args) ? (bool) $args[$k] : $d;

        $status = sanitize_key((string) ($args['status'] ?? 'all'));
        $status = isset(self::STATUS_GROUPS[$status]) ? $status : 'all';
        $layout = Timeline::layout($args['layout'] ?? 'line', 'line');
        $order  = ($args['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $limit  = max(1, min(60, (int) ($args['limit'] ?? 12)));
        $columns = max(1, min(4, (int) ($args['columns'] ?? 3)));
        $group  = $flag('group', false);
        $artist_id  = absint($args['artist_id'] ?? 0);
        $gallery_id = absint($args['gallery_id'] ?? 0);
        $empty = (string) ($args['empty_text'] ?? '') !== '' ? (string) $args['empty_text'] : 'حراجی برای نمایش وجود ندارد.';
        $button = (string) ($args['button_text'] ?? '') !== '' ? (string) $args['button_text'] : '';
        $target = ($args['link_target'] ?? '') === '_blank' ? ' target="_blank" rel="noopener"' : '';
        $tag = Timeline::tag($args['item_tag'] ?? 'h3');

        $statuses = self::STATUS_GROUPS[$status];
        $in = implode(',', array_fill(0, count($statuses), '%s'));
        $where = "au.status IN ({$in}) AND a.status IN ('approved','published','active','sold')";
        $params = $statuses;
        if ($artist_id > 0) {
            $where .= ' AND a.artist_id = %d';
            $params[] = $artist_id;
        }
        if ($gallery_id > 0) {
            $where .= ' AND a.gallery_id = %d';
            $params[] = $gallery_id;
        }
        $params[] = $limit;
        $dir = $order === 'asc' ? 'ASC' : 'DESC';
        $sql = 'SELECT au.id, au.start_price, au.current_bid, au.currency, au.bid_count, au.status, au.lot_number, au.auction_house,
                       au.estimate_low, au.estimate_high, au.starts_at_utc, au.ends_at_utc,
                       a.title, a.slug AS artwork_slug, a.image_url, a.image_id, ar.display_name AS artist_name
                FROM ' . Auction::listings_table() . ' au
                INNER JOIN ' . Database::table('artworks') . ' a ON a.id = au.artwork_id
                LEFT JOIN ' . Database::table('artists') . " ar ON ar.id = a.artist_id
                WHERE {$where} ORDER BY au.starts_at_utc {$dir}, au.id {$dir} LIMIT %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];

        $cols = [
            'price' => $flag('show_price'), 'bids' => $flag('show_bids'), 'image' => $flag('show_image'),
            'artist' => $flag('show_artist'), 'status' => $flag('show_status'), 'date' => $flag('show_date'),
            'countdown' => $flag('show_countdown'), 'lot' => $flag('show_lot', false), 'estimate' => $flag('show_estimate', false),
        ];
        $fa = static fn($n): string => Calendar::to_persian_digits((string) $n);

        $groups = [];
        foreach ($rows as $r) {
            $j = Calendar::gregorian_to_jalali((int) substr((string) $r->starts_at_utc, 0, 4), (int) substr((string) $r->starts_at_utc, 5, 2), (int) substr((string) $r->starts_at_utc, 8, 2));
            $groups[$group ? sprintf('%04d-%02d', $j[0], $j[1]) : 'all'][] = ['row' => $r, 'j' => $j];
        }
        $months = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

        ob_start();
        ?>
        <section class="<?php echo esc_attr(Timeline::wrapper_classes($layout, $columns, 'art-coa-auction-timeline')); ?>" dir="rtl" style="--art-coa-tl-cols:<?php echo (int) $columns; ?>">
            <div class="art-coa-tl-list is-<?php echo esc_attr($layout === 'line' ? 'line' : 'cards'); ?> is-<?php echo esc_attr($layout); ?>">
                <?php if (!$rows): ?>
                    <p class="art-coa-empty"><?php echo esc_html($empty); ?></p>
                <?php else: foreach ($groups as $list): ?>
                    <div class="art-coa-tl-group"><?php if ($group): ?><h3 class="art-coa-tl-group-title"><?php echo esc_html($months[$list[0]['j'][1]] . ' ' . $fa($list[0]['j'][0])); ?> <span class="art-coa-tl-group-count"><?php echo esc_html($fa(count($list))); ?></span></h3><?php endif; ?><div class="art-coa-tl-group-items">
                    <?php foreach ($list as $entry):
                        $r = $entry['row'];
                        $url = home_url('/art-coa/auction/' . (int) $r->id);
                        $img = $cols['image'] ? Media::url((object) ['image_url' => $r->image_url, 'image_id' => $r->image_id], 'image', $layout === 'grid' ? 'large' : 'medium') : '';
                        $live = $r->status === 'active';
                        $left = $live && $cols['countdown'] ? self::remaining((string) $r->ends_at_utc) : '';
                        $amount = $r->current_bid !== null && (float) $r->current_bid > 0 ? $r->current_bid : $r->start_price;
                        $price_label = $r->status === 'sold' ? 'مبلغ نهایی' : ($r->current_bid !== null && (float) $r->current_bid > 0 ? 'آخرین پیشنهاد' : 'قیمت پایه');
                        $cls = 'art-coa-tl-status-' . ($live ? 'live' : ($r->status === 'scheduled' ? 'upcoming' : ($r->status === 'sold' ? 'sold' : 'past')));
                        ?>
                        <article class="art-coa-tl-item art-coa-tl-type-auction <?php echo esc_attr($cls); ?><?php echo $img ? ' has-poster' : ''; ?>">
                            <?php if ($img): ?><a class="art-coa-tl-poster" href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true"<?php echo $target; // phpcs:ignore ?>><img src="<?php echo esc_url($img); ?>" alt="" loading="lazy" decoding="async"></a><?php endif; ?>
                            <div class="art-coa-tl-body">
                                <div class="art-coa-tl-meta">
                                    <?php if ($cols['status']): ?><span class="art-coa-tl-status"><?php echo esc_html(self::LABELS[$r->status] ?? ''); ?></span><?php endif; ?>
                                    <?php if ($cols['date']): ?><time class="art-coa-tl-date" datetime="<?php echo esc_attr(substr((string) $r->starts_at_utc, 0, 10)); ?>"><?php echo esc_html(Dates::format((string) $r->starts_at_utc) . ' – ' . Dates::format((string) $r->ends_at_utc)); ?></time><?php endif; ?>
                                    <?php if ($left !== ''): ?><span class="art-coa-tl-countdown"><?php echo esc_html($left); ?></span><?php endif; ?>
                                </div>
                                <<?php echo $tag; ?> class="art-coa-tl-item-title"><a href="<?php echo esc_url($url); ?>"<?php echo $target; // phpcs:ignore ?>><?php echo esc_html((string) $r->title); ?></a></<?php echo $tag; ?>>
                                <?php if ($cols['artist'] && (string) $r->artist_name !== ''): ?><p class="art-coa-tl-sub"><?php echo esc_html((string) $r->artist_name . ((string) $r->auction_house !== '' ? ' / ' . $r->auction_house : '')); ?></p><?php endif; ?>
                                <?php if ($cols['price'] && $amount !== null && (float) $amount > 0): ?>
                                    <p class="art-coa-tl-price"><span><?php echo esc_html($price_label); ?></span> <strong><?php echo esc_html(Shop::format_price((float) $amount, (string) ($r->currency ?: 'IRR'))); ?></strong></p>
                                <?php endif; ?>
                                <?php if ($cols['estimate'] && ((float) $r->estimate_low > 0 || (float) $r->estimate_high > 0)): ?>
                                    <p class="art-coa-tl-fact">برآورد: <?php echo esc_html(((float) $r->estimate_low > 0 ? Shop::format_price((float) $r->estimate_low, (string) $r->currency) : '') . ((float) $r->estimate_low > 0 && (float) $r->estimate_high > 0 ? ' – ' : '') . ((float) $r->estimate_high > 0 ? Shop::format_price((float) $r->estimate_high, (string) $r->currency) : '')); ?></p>
                                <?php endif; ?>
                                <?php if (($cols['bids'] && (int) $r->bid_count > 0) || ($cols['lot'] && (string) $r->lot_number !== '')): ?>
                                    <p class="art-coa-tl-fact"><?php
                                        $facts = [];
                                        if ($cols['bids'] && (int) $r->bid_count > 0) { $facts[] = $fa((int) $r->bid_count) . ' پیشنهاد'; }
                                        if ($cols['lot'] && (string) $r->lot_number !== '') { $facts[] = 'لات ' . $r->lot_number; }
                                        echo esc_html(implode(' · ', $facts));
                                    ?></p>
                                <?php endif; ?>
                                <?php if ($button !== ''): ?><a class="art-coa-tl-btn" href="<?php echo esc_url($url); ?>"<?php echo $target; // phpcs:ignore ?>><?php echo esc_html($button); ?> <span class="art-coa-tl-arrow" aria-hidden="true"></span></a><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div></div>
                <?php endforeach; endif; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
