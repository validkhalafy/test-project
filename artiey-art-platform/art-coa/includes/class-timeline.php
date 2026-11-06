<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Exhibition;

defined('ABSPATH') || exit;

/**
 * Timeline of exhibitions, art fairs and auction events.
 *
 * The markup (classes `art-coa-timeline`, `art-coa-tl-*`) is shared with AuctionTimeline, so one stylesheet and
 * one set of Elementor style controls serve both. Every dynamic value is escaped here.
 */
final class Timeline
{
    public const LAYOUTS = ['line', 'cards', 'grid', 'compact', 'alt'];

    private const TYPE_LABELS = [
        'all'        => 'همه',
        'artfair'    => 'آرت‌فرها',
        'auction'    => 'حراج‌ها',
        'exhibition' => 'نمایشگاه‌ها',
    ];

    private const KIND_LABELS = ['exhibition' => 'نمایشگاه', 'artfair' => 'آرت‌فر', 'auction' => 'حراج'];

    private const STATUS_LABELS = ['live' => 'در حال برگزاری', 'upcoming' => 'به‌زودی', 'past' => 'برگزار شد'];

    private const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    /* ------------------------------------------------------------------ shared helpers */

    public static function layout($value, string $default = 'cards'): string
    {
        $value = sanitize_key((string) $value);
        return in_array($value, self::LAYOUTS, true) ? $value : $default;
    }

    /** Heading tag for item titles (never arbitrary markup). */
    public static function tag($value, string $default = 'h3'): string
    {
        $value = strtolower((string) $value);
        return in_array($value, ['h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span'], true) ? $value : $default;
    }

    public static function wrapper_classes(string $layout, int $columns = 3, string $extra = ''): string
    {
        $classes = ['art-coa-timeline', 'art-coa-tl-layout-' . self::layout($layout, 'line'), 'art-coa-tl-cols-' . max(1, min(4, $columns))];
        foreach (preg_split('/\s+/', trim($extra)) ?: [] as $c) {
            if ($c !== '') {
                $classes[] = sanitize_html_class($c);
            }
        }
        return implode(' ', $classes);
    }

    /** live / upcoming / past for a start and optional end date (Y-m-d). */
    public static function status(string $start, string $end, string $today): string
    {
        if ($start !== '' && $start > $today) {
            return 'upcoming';
        }
        if ($end !== '' && $end < $today) {
            return 'past';
        }
        return 'live';
    }

    public static function days_between(string $from, string $to): int
    {
        $a = strtotime($from . ' 00:00:00 UTC');
        $b = strtotime($to . ' 00:00:00 UTC');
        return ($a === false || $b === false) ? 0 : (int) round(($b - $a) / 86400);
    }

    /** "۱۲ مهر" or "۱۲ مهر ۱۴۰۵" for a Y-m-d date. */
    public static function jalali_label(string $ymd, bool $with_year = false): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
            return '';
        }
        $j = Calendar::gregorian_to_jalali((int) $m[1], (int) $m[2], (int) $m[3]);
        $label = Calendar::to_persian_digits((string) $j[2]) . ' ' . (self::MONTHS[$j[1]] ?? '');
        return $with_year ? $label . ' ' . Calendar::to_persian_digits((string) $j[0]) : $label;
    }

    /** "۱۲ مهر تا ۲۰ آبان" (or a single day). */
    public static function range_label(string $start, string $end): string
    {
        $s = self::jalali_label($start);
        $e = $end !== '' && $end !== $start ? self::jalali_label($end) : '';
        return $e !== '' ? $s . ' تا ' . $e : $s;
    }

    private static function countdown(string $status, string $start, string $end, string $today): string
    {
        $fa = static fn(int $n): string => Calendar::to_persian_digits((string) $n);
        if ($status === 'upcoming' && $start !== '') {
            $d = self::days_between($today, $start);
            return $d <= 1 ? 'از فردا شروع می‌شود' : $fa($d) . ' روز تا شروع';
        }
        if ($status === 'live' && $end !== '') {
            $d = self::days_between($today, $end);
            return $d <= 0 ? 'آخرین روز' : $fa($d) . ' روز تا پایان';
        }
        return '';
    }

    /* ------------------------------------------------------------------ render */

    /**
     * @param array<string,mixed> $args year, range(year|all), type, status(all|live|upcoming|past), layout, columns, group,
     *   order(asc|desc), limit, show_heading, show_filters, show_year, show_kind, show_status, show_date, show_countdown,
     *   show_subtitle, show_location, show_poster, show_button, button_text, link_target, item_tag, filter_key,
     *   empty_text, schema, heading_text
     */
    public static function render(array $args = []): string
    {
        $flag = static fn(string $k, bool $d = true): bool => array_key_exists($k, $args) ? (bool) $args[$k] : $d;

        $year = isset($args['year']) ? (int) $args['year'] : Calendar::current_jalali_year();
        $year = max(1300, min(1600, $year));
        $type = sanitize_key((string) ($args['type'] ?? 'all'));
        if (!array_key_exists($type, self::TYPE_LABELS)) {
            $type = 'all';
        }
        $range  = ($args['range'] ?? 'year') === 'all' ? 'all' : 'year';
        $layout = self::layout($args['layout'] ?? 'cards', 'cards');
        $columns = max(1, min(4, (int) ($args['columns'] ?? 3)));
        $order  = ($args['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $limit  = max(1, min(200, (int) ($args['limit'] ?? 60)));
        $status_filter = sanitize_key((string) ($args['status'] ?? 'all'));
        $status_filter = in_array($status_filter, ['live', 'upcoming', 'past'], true) ? $status_filter : 'all';
        $group  = $flag('group', true);
        $tag    = self::tag($args['item_tag'] ?? 'h3');
        $target = ($args['link_target'] ?? '') === '_blank' ? ' target="_blank" rel="noopener"' : '';
        $button = trim((string) ($args['button_text'] ?? ''));
        $show_button = $flag('show_button', false) && $button !== '';
        $empty  = trim((string) ($args['empty_text'] ?? '')) !== '' ? (string) $args['empty_text'] : 'رویدادی برای این بازه و فیلتر انتخاب‌شده یافت نشد.';

        $show = [
            'heading'   => $flag('show_heading'), 'filters' => $flag('show_filters'), 'year' => $flag('show_year'),
            'kind'      => $flag('show_kind'), 'status' => $flag('show_status', false), 'date' => $flag('show_date'),
            'countdown' => $flag('show_countdown', false), 'subtitle' => $flag('show_subtitle'),
            'location'  => $flag('show_location', false), 'poster' => $flag('show_poster'),
        ];

        $query_type = $type === 'all' ? null : $type;
        $gallery_post_ids = is_array($args['gallery_ids'] ?? null) ? array_values(array_unique(array_filter(array_map('absint', $args['gallery_ids'])))) : [];
        $events = $range === 'all'
            ? Exhibition::in_range('1900-01-01', '2200-12-31', $query_type, $gallery_post_ids)
            : Exhibition::by_year($year, $query_type, $gallery_post_ids);

        $today = function_exists('current_time') ? (string) current_time('Y-m-d') : gmdate('Y-m-d');
        $items = [];
        foreach ($events as $ev) {
            $start = substr((string) ($ev->start_date ?? ''), 0, 10);
            if ($start === '') {
                continue;
            }
            $end = substr((string) ($ev->end_date ?? ''), 0, 10);
            $st  = self::status($start, $end, $today);
            if ($status_filter !== 'all' && $st !== $status_filter) {
                continue;
            }
            $kind = in_array(($ev->event_type ?? 'exhibition'), ['exhibition', 'artfair', 'auction'], true) ? (string) $ev->event_type : 'exhibition';
            $event_post_id = absint($ev->cpt_post_id ?? 0);
            $gallery_post_id = absint($ev->gallery_post_id ?? 0);
            $event_url = ($event_post_id && get_post_status($event_post_id) === 'publish')
                ? (string) get_permalink($event_post_id)
                : (($ev->slug ?? '') !== '' ? home_url('/art-coa/exhibition/' . rawurlencode((string) $ev->slug)) : '');
            $gallery_url = ($gallery_post_id && get_post_status($gallery_post_id) === 'publish')
                ? (string) get_permalink($gallery_post_id)
                : (($ev->gallery_slug ?? '') !== '' ? home_url('/art-coa/gallery/' . rawurlencode((string) $ev->gallery_slug)) : '');
            $items[] = [
                'title'       => (string) ($ev->title ?? ''),
                'subtitle'    => (string) ($ev->subtitle ?: ($ev->artist_label ?? '')),
                'gallery'     => (string) ($ev->gallery_name ?? ''),
                'gallery_url' => $gallery_url,
                'event_url'   => $event_url,
                'start'       => $start,
                'end'         => $end,
                'status'      => $st,
                'type'        => $kind,
                'slug'        => (string) ($ev->slug ?? ''),
                'poster'      => $show['poster'] ? Media::url($ev, 'poster', $layout === 'grid' ? 'large' : 'medium') : '',
            ];
        }
        usort($items, static fn(array $a, array $b): int => $order === 'asc' ? strcmp($a['start'], $b['start']) : strcmp($b['start'], $a['start']));
        $items = array_slice($items, 0, $limit);

        $grouped = [];
        foreach ($items as $it) {
            $j = Calendar::gregorian_to_jalali((int) substr($it['start'], 0, 4), (int) substr($it['start'], 5, 2), (int) substr($it['start'], 8, 2));
            $key = $group ? sprintf('%04d-%02d', $j[0], $j[1]) : 'all';
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['year' => $j[0], 'month' => $j[1], 'items' => []];
            }
            $grouped[$key]['items'][] = $it;
        }

        $filter_key = sanitize_key((string) ($args['filter_key'] ?? ''));
        $type_query_key = $filter_key !== '' ? 'art_coa_tl_' . $filter_key . '_type' : 'art_coa_tl_type';
        $year_query_key = $filter_key !== '' ? 'art_coa_tl_' . $filter_key . '_year' : 'art_coa_tl_year';
        $remove_keys = [$type_query_key, $year_query_key];
        if ($filter_key !== '') {
            $remove_keys = array_merge($remove_keys, ['art_coa_tl_type', 'art_coa_tl_year']);
        }
        $base_url = remove_query_arg($remove_keys);
        $filter_url = static fn(string $t, int $y): string => add_query_arg([$type_query_key => $t, $year_query_key => $y], $base_url);

        $fa = static fn($n): string => Calendar::to_persian_digits((string) $n);
        $schema = $flag('schema') && $items ? self::schema($items) : '';

        ob_start();
        ?>
        <section class="<?php echo esc_attr(self::wrapper_classes($layout, $columns)); ?>" dir="rtl" style="--art-coa-tl-cols:<?php echo (int) $columns; ?>">
            <?php if ($show['heading']): ?>
                <h2 class="art-coa-tl-title"><?php echo esc_html((string) (($args['heading_text'] ?? '') !== '' ? $args['heading_text'] : 'تایم‌لاین رویدادهای هنری')); ?></h2>
            <?php endif; ?>

            <?php if ($show['filters']): ?>
                <nav class="art-coa-tl-filters" aria-label="فیلتر رویدادهای هنری">
                    <?php foreach (self::TYPE_LABELS as $key => $label): ?>
                        <a href="<?php echo esc_url($filter_url($key, $year)); ?>" class="art-coa-tl-filter<?php echo $type === $key ? ' is-active' : ''; ?>"<?php echo $type === $key ? ' aria-current="true"' : ''; ?>><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <?php if ($show['year'] && $range === 'year'): ?>
                <div class="art-coa-tl-year">
                    <span class="art-coa-tl-year-badge"><?php echo esc_html($fa($year)); ?></span>
                    <span class="art-coa-tl-year-caption"><?php echo esc_html($fa(count($items)) . ' رویداد در این سال'); ?></span>
                </div>
            <?php endif; ?>

            <div class="art-coa-tl-list is-<?php echo esc_attr($layout === 'line' ? 'line' : 'cards'); ?> is-<?php echo esc_attr($layout); ?>">
                <?php if (!$items): ?>
                    <p class="art-coa-empty"><?php echo esc_html($empty); ?></p>
                <?php else: foreach ($grouped as $g): ?>
                    <div class="art-coa-tl-group">
                        <?php if ($group): ?>
                            <h3 class="art-coa-tl-group-title"><?php echo esc_html((self::MONTHS[$g['month']] ?? '') . ' ' . $fa($g['year'])); ?> <span class="art-coa-tl-group-count"><?php echo esc_html($fa(count($g['items']))); ?></span></h3>
                        <?php endif; ?>
                        <div class="art-coa-tl-group-items">
                        <?php foreach ($g['items'] as $it):
                            $url = (string) ($it['event_url'] ?? '');
                            $cd  = $show['countdown'] ? self::countdown($it['status'], $it['start'], $it['end'], $today) : '';
                            ?>
                            <article class="art-coa-tl-item art-coa-tl-type-<?php echo esc_attr($it['type']); ?> art-coa-tl-status-<?php echo esc_attr($it['status']); ?><?php echo $it['poster'] ? ' has-poster' : ''; ?>">
                                <?php if ($it['poster']): ?>
                                    <?php if ($url): ?><a class="art-coa-tl-poster" href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true"<?php echo $target; // phpcs:ignore ?>><?php else: ?><span class="art-coa-tl-poster"><?php endif; ?>
                                        <img src="<?php echo esc_url($it['poster']); ?>" alt="" loading="lazy" decoding="async">
                                    <?php echo $url ? '</a>' : '</span>'; ?>
                                <?php endif; ?>
                                <div class="art-coa-tl-body">
                                    <div class="art-coa-tl-meta">
                                        <?php if ($show['kind']): ?><span class="art-coa-tl-kind"><?php echo esc_html(self::KIND_LABELS[$it['type']] ?? 'نمایشگاه'); ?></span><?php endif; ?>
                                        <?php if ($show['status']): ?><span class="art-coa-tl-status"><?php echo esc_html(self::STATUS_LABELS[$it['status']] ?? ''); ?></span><?php endif; ?>
                                        <?php if ($show['date']): ?><time class="art-coa-tl-date" datetime="<?php echo esc_attr($it['start']); ?>"><?php echo esc_html(self::range_label($it['start'], $it['end'])); ?></time><?php endif; ?>
                                        <?php if ($cd !== ''): ?><span class="art-coa-tl-countdown"><?php echo esc_html($cd); ?></span><?php endif; ?>
                                    </div>
                                    <<?php echo $tag; ?> class="art-coa-tl-item-title"><?php if ($url): ?><a href="<?php echo esc_url($url); ?>"<?php echo $target; // phpcs:ignore ?>><?php echo esc_html($it['title']); ?></a><?php else: echo esc_html($it['title']); endif; ?></<?php echo $tag; ?>>
                                    <?php if ($show['subtitle'] && $it['subtitle'] !== ''): ?><p class="art-coa-tl-sub"><?php echo esc_html($it['subtitle']); ?></p><?php endif; ?>
                                    <?php if ($show['location'] && $it['gallery'] !== ''): ?><p class="art-coa-tl-fact art-coa-tl-place"><?php echo esc_html('محل برگزاری: '); ?><?php if (!empty($args['link_gallery']) && !empty($it['gallery_url'])): ?><a class="art-coa-tl-gallery-link" href="<?php echo esc_url($it['gallery_url']); ?>"><?php echo esc_html($it['gallery']); ?></a><?php else: echo esc_html($it['gallery']); endif; ?></p><?php endif; ?>
                                    <?php if ($show_button && $url): ?><a class="art-coa-tl-btn" href="<?php echo esc_url($url); ?>"<?php echo $target; // phpcs:ignore ?>><?php echo esc_html($button); ?> <span class="art-coa-tl-arrow" aria-hidden="true"></span></a><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <?php echo $schema; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with HEX flags. ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /** schema.org ItemList of Events for search engines (first 30 items). */
    private static function schema(array $items): string
    {
        $list = [];
        foreach (array_slice($items, 0, 30) as $i => $it) {
            $event = [
                '@type' => 'Event',
                'name' => $it['title'],
                'startDate' => $it['start'],
                'eventStatus' => 'https://schema.org/EventScheduled',
            ];
            if ($it['end'] !== '') {
                $event['endDate'] = $it['end'];
            }
            if ($it['slug'] !== '') {
                $event['url'] = home_url('/art-coa/exhibition/' . $it['slug']);
            }
            if ($it['gallery'] !== '') {
                $event['location'] = ['@type' => 'Place', 'name' => $it['gallery']];
            }
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $event];
        }
        $json = wp_json_encode(['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $list], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        return $json ? '<script type="application/ld+json">' . $json . '</script>' : '';
    }
}
