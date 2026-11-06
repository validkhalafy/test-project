<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Read-only public timeline built from the plugin's existing auction,
 * secondary-market and ownership-history records. It never invents prices.
 */
final class ArtworkHistoryTimeline
{
    /**
     * @param array{source?:string,artwork_id?:int,artist_id?:int,current_artwork?:bool,limit?:int,layout?:string,show_image?:bool,show_price?:bool,show_owner?:bool} $args
     */
    public static function render(array $args = []): string
    {
        $source = sanitize_key((string) ($args['source'] ?? 'all'));
        $allowed = ['all', 'auction', 'ownership', 'sales', 'market'];
        if (!in_array($source, $allowed, true)) {
            $source = 'all';
        }
        $artwork_id = absint($args['artwork_id'] ?? 0);
        $artist_id = $artwork_id > 0 ? 0 : absint($args['artist_id'] ?? 0); // A specific artwork takes precedence over artist-wide history.
        $use_current_artwork = !array_key_exists('current_artwork', $args) || (bool) $args['current_artwork'];
        $route = class_exists('\\Artiey_AET_Elementor_Layouts') ? \Artiey_AET_Elementor_Layouts::context() : [];
        if ($artwork_id === 0 && $use_current_artwork) {
            $current_type = sanitize_key((string) ($route['entity_type'] ?? get_query_var('art_coa_type')));
            $current_slug = sanitize_title((string) ($route['slug'] ?? get_query_var('art_coa_slug')));
            if ($current_type === 'artwork' && $current_slug !== '') {
                $current_artwork = \WPArtCOA\Entities\Artwork::get_by_slug($current_slug);
                $artwork_id = $current_artwork ? (int) $current_artwork->id : 0;
            } elseif ($current_type === 'artist' && $current_slug !== '' && empty($args['artist_id'])) {
                $current_artist = \WPArtCOA\Entities\Artist::get_by_slug($current_slug);
                $artist_id = $current_artist ? (int) $current_artist->id : 0;
            }
        }
        $limit = max(1, min(50, absint($args['limit'] ?? 12)));
        $layout = sanitize_key((string) ($args['layout'] ?? 'line'));
        $layout = in_array($layout, ['line', 'cards'], true) ? $layout : 'line';
        $show_image = !array_key_exists('show_image', $args) || (bool) $args['show_image'];
        $show_price = !array_key_exists('show_price', $args) || (bool) $args['show_price'];
        $show_owner = !empty($args['show_owner']);

        $events = [];
        if (in_array($source, ['all', 'auction', 'sales'], true)) {
            $events = array_merge($events, self::auction_events($artwork_id, $limit, $source, $artist_id));
        }
        if (in_array($source, ['all', 'sales'], true)) {
            $events = array_merge($events, self::woocommerce_sale_events($artwork_id, $limit, $artist_id));
        }
        if (in_array($source, ['all', 'ownership'], true)) {
            $events = array_merge($events, self::ownership_events($artwork_id, $limit, $artist_id));
        }
        if (in_array($source, ['all', 'market', 'sales'], true)) {
            $events = array_merge($events, self::market_events($artwork_id, $limit, $source, $artist_id));
        }

        $asc = ($args['order'] ?? 'desc') === 'asc';
        usort($events, static function (array $a, array $b) use ($asc): int {
            return $asc ? strcmp((string) $a['date'], (string) $b['date']) : strcmp((string) $b['date'], (string) $a['date']);
        });
        $events = array_slice($events, 0, $limit);

        ob_start();
        ?>
        <section class="art-coa-artwork-history art-coa-ah-layout-<?php echo esc_attr($layout); ?>" dir="rtl">
            <div class="art-coa-ah-list <?php echo $layout === 'line' ? 'is-line' : 'is-cards'; ?>">
                <?php if (!$events): ?>
                    <div class="art-coa-ah-empty">
                        <span class="art-coa-ah-empty-mark" aria-hidden="true">◷</span>
                        <strong>هنوز سابقه‌ای برای نمایش ثبت نشده است.</strong>
                        <p>با ثبت رویدادهای حراج، عرضه در بازار ثانویه یا انتقال مالکیت، تاریخچه در این بخش نمایش داده می‌شود.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $image = $show_image ? Media::url((object) ['image_url' => $event['image_url'], 'image_id' => $event['image_id']], 'image', 'thumbnail') : '';
                        $artwork_url = $event['artwork_slug'] !== '' ? home_url('/art-coa/artwork/' . $event['artwork_slug']) : '';
                        $date_label = Dates::format($event['date'], true);
                        ?>
                        <article class="art-coa-ah-event art-coa-ah-event--<?php echo esc_attr($event['type']); ?>">
                            <span class="art-coa-ah-marker" aria-hidden="true"></span>
                            <?php if ($image): ?>
                                <div class="art-coa-ah-image">
                                    <?php if ($artwork_url): ?><a href="<?php echo esc_url($artwork_url); ?>" tabindex="-1" aria-hidden="true"><?php endif; ?>
                                    <img src="<?php echo esc_url($image); ?>" alt="" loading="lazy" decoding="async">
                                    <?php if ($artwork_url): ?></a><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="art-coa-ah-content">
                                <div class="art-coa-ah-topline">
                                    <span class="art-coa-ah-type"><?php echo esc_html($event['label']); ?></span>
                                    <time class="art-coa-ah-date"><?php echo esc_html($date_label); ?></time>
                                </div>
                                <h3 class="art-coa-ah-title">
                                    <?php if ($artwork_url): ?><a href="<?php echo esc_url($artwork_url); ?>"><?php endif; ?>
                                    <?php echo esc_html($event['artwork_title']); ?>
                                    <?php if ($artwork_url): ?></a><?php endif; ?>
                                </h3>
                                <?php if ($event['artist_name'] !== ''): ?><p class="art-coa-ah-artist"><?php echo esc_html($event['artist_name']); ?></p><?php endif; ?>
                                <?php if ($event['detail'] !== ''): ?><p class="art-coa-ah-detail"><?php echo esc_html($event['detail']); ?></p><?php endif; ?>
                                <?php if ($show_price && $event['amount'] !== null && $event['amount'] !== ''): ?>
                                    <div class="art-coa-ah-price">
                                        <span><?php echo esc_html($event['price_label']); ?></span>
                                        <strong><?php echo esc_html(Shop::format_price((float) $event['amount'], (string) ($event['currency'] ?: 'IRR'))); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($show_owner && $event['owner'] !== ''): ?><p class="art-coa-ah-owner">مالک ثبت‌شده: <?php echo esc_html($event['owner']); ?></p><?php endif; ?>
                                <?php if ($event['detail_url'] !== ''): ?><a class="art-coa-ah-link" href="<?php echo esc_url($event['detail_url']); ?>">مشاهده جزئیات حراج <span class="art-coa-ah-link-arrow" aria-hidden="true"></span></a><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="art-coa-ah-note">تاریخچه بر اساس اطلاعات ثبت‌شده در Artie است؛ انتقال مالکیت لزوماً به معنای فروش نیست و قیمت نهایی فقط در صورت ثبت معتبر نمایش داده می‌شود.</p>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /** @return array<int,array<string,mixed>> */
    private static function auction_events(int $artwork_id, int $limit, string $source, int $artist_id = 0): array
    {
        global $wpdb;
        $where = $source === 'sales'
            ? "au.status = 'sold'"
            : "au.status IN ('scheduled','active','ended','ended_reserve','sold')";
        $params = [];
        if ($artwork_id > 0) {
            $where .= ' AND au.artwork_id = %d';
            $params[] = $artwork_id;
        }
        if ($artist_id > 0) {
            $where .= ' AND a.artist_id = %d';
            $params[] = $artist_id;
        }
        $params[] = $limit;
        $sql = 'SELECT au.id, au.artwork_id, au.start_price, au.current_bid, au.currency, au.bid_count, au.status,
                       au.starts_at_utc, au.ends_at_utc, a.title AS artwork_title, a.slug AS artwork_slug,
                       a.image_url, a.image_id, ar.display_name AS artist_name
                FROM ' . Auction::listings_table() . ' au
                INNER JOIN ' . Database::table('artworks') . ' a ON a.id = au.artwork_id
                LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                WHERE ' . $where . " AND a.status IN ('approved','published','active','sold')" . '
                ORDER BY au.ends_at_utc DESC, au.created_at_utc DESC LIMIT %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
        $events = [];
        foreach ($rows as $row) {
            $sold = $row->status === 'sold';
            $active = in_array($row->status, ['active', 'scheduled'], true);
            $date = $active ? ($row->starts_at_utc ?: $row->ends_at_utc) : $row->ends_at_utc;
            if ($sold) {
                $label = 'نتیجه حراج: فروش ثبت‌شده';
                $detail = 'وضعیت حراج در سامانه فروش است؛ تسویه مالی یا تحویل اثر در این رکورد تأیید نمی‌شود. تعداد پیشنهادها: ' . Calendar::to_persian_digits((string) (int) $row->bid_count);
                $amount = $row->current_bid;
                $price_label = 'مبلغ نهایی ثبت‌شده در حراج';
                $type = 'sale';
            } elseif ($active) {
                $label = $row->status === 'scheduled' ? 'حراج برنامه‌ریزی‌شده' : 'حراج فعال';
                $detail = 'تعداد پیشنهادها: ' . Calendar::to_persian_digits((string) (int) $row->bid_count);
                $amount = $row->current_bid !== null ? $row->current_bid : $row->start_price;
                $price_label = $row->current_bid !== null ? 'آخرین پیشنهاد' : 'قیمت پایه';
                $type = 'auction';
            } elseif ($row->status === 'ended_reserve') {
                $label = 'پایان حراج؛ حد نصاب فروش محقق نشد';
                $detail = 'فروش قطعی از این رکورد قابل تأیید نیست.';
                $amount = $row->current_bid;
                $price_label = 'آخرین پیشنهاد';
                $type = 'auction';
            } else {
                $label = 'پایان حراج؛ فروش ثبت نشده';
                $detail = 'در داده‌های فعلی، فروش نهایی برای این حراج ثبت نشده است.';
                $amount = $row->current_bid;
                $price_label = 'آخرین پیشنهاد';
                $type = 'auction';
            }
            $events[] = self::event([
                'date' => (string) $date,
                'type' => $type,
                'label' => $label,
                'artwork_title' => (string) $row->artwork_title,
                'artwork_slug' => (string) ($row->artwork_slug ?? ''),
                'image_url' => (string) ($row->image_url ?? ''),
                'image_id' => (int) ($row->image_id ?? 0),
                'artist_name' => (string) ($row->artist_name ?? ''),
                'detail' => $detail,
                'amount' => $amount,
                'price_label' => $price_label,
                'currency' => (string) ($row->currency ?? 'IRR'),
                'owner' => '',
                'detail_url' => home_url('/art-coa/auction/' . (int) $row->id),
            ]);
        }
        return $events;
    }

    /**
     * Completed WooCommerce sale events are read from Artie's audit log.
     * Buyer details are never emitted; the order API is used only to find the
     * matching artwork line total when WooCommerce is available.
     * @return array<int,array<string,mixed>>
     */
    private static function woocommerce_sale_events(int $artwork_id, int $limit, int $artist_id = 0): array
    {
        global $wpdb;
        $where = "l.entity_type = 'artwork' AND l.action IN ('woo_sold','woo_released')";
        $params = [];
        if ($artwork_id > 0) {
            $where .= ' AND l.entity_id = %d';
            $params[] = $artwork_id;
        }
        if ($artist_id > 0) {
            $where .= ' AND a.artist_id = %d';
            $params[] = $artist_id;
        }
        // Read enough recent log events to pair a later cancellation/refund with its sale.
        $scan_limit = min(500, max(100, $limit * 20));
        $params[] = $scan_limit;
        $sql = 'SELECT l.id, l.entity_id, l.action, l.meta, l.created_at_utc,
                       a.title AS artwork_title, a.slug AS artwork_slug, a.image_url, a.image_id,
                       ar.display_name AS artist_name
                FROM ' . Database::table('audit_log') . ' l
                INNER JOIN ' . Database::table('artworks') . ' a ON a.id = l.entity_id
                LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                WHERE ' . $where . " AND a.status IN ('approved','published','active','sold')" . '
                ORDER BY l.created_at_utc DESC, l.id DESC LIMIT %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
        if (!$rows) {
            return [];
        }

        $released_at = [];
        foreach ($rows as $row) {
            if ($row->action !== 'woo_released') {
                continue;
            }
            $meta = json_decode((string) ($row->meta ?? ''), true);
            $order_id = is_array($meta) ? absint($meta['order_id'] ?? 0) : 0;
            if (!$order_id) {
                continue;
            }
            $key = (int) $row->entity_id . ':' . $order_id;
            if (!isset($released_at[$key]) || strcmp((string) $row->created_at_utc, $released_at[$key]) > 0) {
                $released_at[$key] = (string) $row->created_at_utc;
            }
        }

        $events = [];
        foreach ($rows as $row) {
            if ($row->action !== 'woo_sold') {
                continue;
            }
            $meta = json_decode((string) ($row->meta ?? ''), true);
            $order_id = is_array($meta) ? absint($meta['order_id'] ?? 0) : 0;
            $key = (int) $row->entity_id . ':' . $order_id;
            if (isset($released_at[$key]) && strcmp($released_at[$key], (string) $row->created_at_utc) > 0) {
                continue; // sale was later cancelled, failed or refunded
            }

            $amount = null;
            $currency = '';
            if ($order_id && function_exists('wc_get_order') && function_exists('get_post_meta')) {
                $order = wc_get_order($order_id);
                if ($order && method_exists($order, 'get_items')) {
                    foreach ($order->get_items() as $item) {
                        $product_id = method_exists($item, 'get_product_id') ? (int) $item->get_product_id() : 0;
                        $linked_artwork = $product_id ? (int) get_post_meta($product_id, '_art_coa_artwork_id', true) : 0;
                        if ($linked_artwork !== (int) $row->entity_id) {
                            continue;
                        }
                        if (method_exists($item, 'get_total')) {
                            $amount = (float) $item->get_total();
                            if (method_exists($item, 'get_total_tax')) {
                                $amount += (float) $item->get_total_tax();
                            }
                        }
                        $currency = method_exists($order, 'get_currency') ? (string) $order->get_currency() : '';
                        break;
                    }
                }
            }

            $events[] = self::event([
                'date' => (string) $row->created_at_utc,
                'type' => 'sale',
                'label' => 'فروش ثبت‌شده در فروشگاه اینترنتی',
                'artwork_title' => (string) $row->artwork_title,
                'artwork_slug' => (string) ($row->artwork_slug ?? ''),
                'image_url' => (string) ($row->image_url ?? ''),
                'image_id' => (int) ($row->image_id ?? 0),
                'artist_name' => (string) ($row->artist_name ?? ''),
                'detail' => 'رویداد فروش در سوابق سامانه ثبت شده است؛ اطلاعات هویتی خریدار نمایش داده نمی‌شود.',
                'amount' => $amount,
                'price_label' => 'مبلغ ثبت‌شده سفارش',
                'currency' => $currency,
                'owner' => '',
                'detail_url' => '',
            ]);
            if (count($events) >= $limit) {
                break;
            }
        }
        return $events;
    }

    /** @return array<int,array<string,mixed>> */
    private static function ownership_events(int $artwork_id, int $limit, int $artist_id = 0): array
    {
        global $wpdb;
        $where = '1=1';
        $params = [];
        if ($artwork_id > 0) {
            $where .= ' AND h.artwork_id = %d';
            $params[] = $artwork_id;
        }
        if ($artist_id > 0) {
            $where .= ' AND a.artist_id = %d';
            $params[] = $artist_id;
        }
        $params[] = $limit;
        $sql = 'SELECT h.id,
                       CASE WHEN c.id IS NOT NULL THEN c.display_name ELSE \'\' END AS public_owner,
                       h.transferred_at_utc,
                       a.title AS artwork_title, a.slug AS artwork_slug, a.image_url, a.image_id,
                       ar.display_name AS artist_name
                FROM ' . Database::table('ownership_history') . ' h
                INNER JOIN ' . Database::table('artworks') . ' a ON a.id = h.artwork_id
                LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                LEFT JOIN ' . Database::table('collectors') . ' c ON c.id = h.to_id AND h.to_type = \'collector\' AND c.is_public = 1 AND c.status = \'active\'
                WHERE ' . $where . " AND a.status IN ('approved','published','active','sold')" . '
                ORDER BY h.transferred_at_utc DESC LIMIT %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
        $events = [];
        foreach ($rows as $row) {
            $events[] = self::event([
                'date' => (string) $row->transferred_at_utc,
                'type' => 'ownership',
                'label' => 'انتقال مالکیت ثبت‌شده',
                'artwork_title' => (string) $row->artwork_title,
                'artwork_slug' => (string) ($row->artwork_slug ?? ''),
                'image_url' => (string) ($row->image_url ?? ''),
                'image_id' => (int) ($row->image_id ?? 0),
                'artist_name' => (string) ($row->artist_name ?? ''),
                'detail' => 'انتقال مالکیت در سامانه ثبت شده است؛ این رکورد به‌تنهایی به معنی فروش نیست و مبلغ معامله در آن موجود نیست.',
                'amount' => null,
                'price_label' => '',
                'currency' => '',
                'owner' => (string) ($row->public_owner ?? ''),
                'detail_url' => '',
            ]);
        }
        return $events;
    }

    /** @return array<int,array<string,mixed>> */
    private static function market_events(int $artwork_id, int $limit, string $source, int $artist_id = 0): array
    {
        global $wpdb;
        if ($source === 'sales') {
            $where = "l.status IN ('sold','completed') AND l.sold_at_utc IS NOT NULL";
        } elseif ($source === 'market') {
            $where = "(l.status = 'active' OR (l.status IN ('sold','completed') AND l.sold_at_utc IS NOT NULL))";
        } else {
            $where = "(l.status = 'active' OR (l.status IN ('sold','completed') AND l.sold_at_utc IS NOT NULL))";
        }
        $params = [];
        if ($artwork_id > 0) {
            $where .= ' AND l.artwork_id = %d';
            $params[] = $artwork_id;
        }
        if ($artist_id > 0) {
            $where .= ' AND a.artist_id = %d';
            $params[] = $artist_id;
        }
        $params[] = $limit;
        $sql = 'SELECT l.id, l.artwork_id, l.list_price, l.original_price, l.currency, l.status,
                       l.listed_at_utc, l.sold_at_utc, a.title AS artwork_title, a.slug AS artwork_slug,
                       a.image_url, a.image_id, ar.display_name AS artist_name
                FROM ' . SecondaryMarket::table() . ' l
                INNER JOIN ' . Database::table('artworks') . ' a ON a.id = l.artwork_id
                LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                WHERE ' . $where . " AND a.status IN ('approved','published','active','sold')" . '
                ORDER BY COALESCE(l.sold_at_utc, l.listed_at_utc) DESC LIMIT %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
        $events = [];
        foreach ($rows as $row) {
            $sold = in_array($row->status, ['sold', 'completed'], true) && !empty($row->sold_at_utc);
            $events[] = self::event([
                'date' => (string) ($sold ? $row->sold_at_utc : $row->listed_at_utc),
                'type' => $sold ? 'sale' : 'market',
                'label' => $sold ? 'فروش ثبت‌شده در بازار ثانویه' : 'عرضه در بازار ثانویه',
                'artwork_title' => (string) $row->artwork_title,
                'artwork_slug' => (string) ($row->artwork_slug ?? ''),
                'image_url' => (string) ($row->image_url ?? ''),
                'image_id' => (int) ($row->image_id ?? 0),
                'artist_name' => (string) ($row->artist_name ?? ''),
                'detail' => $sold
                    ? 'این آگهی به‌عنوان فروش تکمیل‌شده ثبت شده؛ مبلغ نهایی معامله در جدول موجود نیست.'
                    : 'اثر برای فروش مجدد عرضه شده است؛ این مبلغ قیمت درخواستی آگهی است، نه فروش قطعی.',
                'amount' => $row->list_price,
                'price_label' => $sold ? 'قیمت ثبت‌شده در آگهی' : 'قیمت عرضه',
                'currency' => (string) ($row->currency ?? 'IRR'),
                'owner' => '',
                'detail_url' => '',
            ]);
        }
        return $events;
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function event(array $data): array
    {
        return array_merge([
            'date' => '', 'type' => 'other', 'label' => '', 'artwork_title' => '', 'artwork_slug' => '',
            'image_url' => '', 'image_id' => 0, 'artist_name' => '', 'detail' => '', 'amount' => null,
            'price_label' => '', 'currency' => '', 'owner' => '', 'detail_url' => '',
        ], $data);
    }
}
