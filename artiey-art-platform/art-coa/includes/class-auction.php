<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Online auction layer for secondary market (collector resale).
 */
final class Auction
{
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'maybe_migrate']);
        add_shortcode('art_coa_auction', [self::class, 'shortcode']);
        add_action('init', [self::class, 'rewrites']);
        add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        add_action('init', [self::class, 'schedule_expiry'], 20);
        add_action('art_coa_close_expired_auctions', [self::class, 'close_expired']);
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'art_coa_auction';
            $vars[] = 'art_coa_auction_id';
            return $vars;
        });
        add_action('template_redirect', [self::class, 'maybe_render']);
        add_action('init', [self::class, 'handle_create']);
        add_action('init', [self::class, 'handle_bid']);
        add_action('wp_head', [self::class, 'print_seo'], 3);
        add_filter('pre_get_document_title', [self::class, 'document_title']);
    }

    /** Lot being rendered on /art-coa/auction/{id}/ (set by render_single for the head tags). */
    private static ?object $seo_row = null;

    public static function document_title(string $title): string
    {
        if (!self::$seo_row) {
            return $title;
        }
        return sanitize_text_field((string) self::$seo_row->title) . ' — ' . (I18n::is_fa() ? 'حراج' : 'Auction') . ' | ' . get_bloginfo('name');
    }

    /** Canonical, description, Open Graph and Product/Offer JSON-LD for a public auction lot. */
    public static function print_seo(): void
    {
        $settings = get_option('wp_art_coa_settings', []);
        if (is_array($settings) && isset($settings['enable_seo']) && empty($settings['enable_seo'])) {
            return;
        }
        $mode = (string) get_query_var('art_coa_auction');
        if ($mode === 'list') {
            echo '<link rel="canonical" href="' . esc_url(home_url('/art-coa/auctions/')) . '" />' . "\n";
            echo '<meta name="description" content="' . esc_attr(I18n::t('auction_intro')) . '" />' . "\n";
            return;
        }
        $row = self::$seo_row;
        if (!$row || $mode !== 'single') {
            return;
        }
        $url = home_url('/art-coa/auction/' . (int) $row->id . '/');
        $artist = ($row->artist_status ?? '') === 'active' ? (string) ($row->artist_name ?: trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''))) : '';
        $price = $row->current_bid !== null ? (float) $row->current_bid : (float) $row->start_price;
        $desc = trim(implode(' — ', array_filter([
            (string) $row->title, $artist, (string) ($row->medium ?? ''),
            (I18n::is_fa() ? 'قیمت فعلی: ' : 'Current price: ') . Shop::format_price($price, (string) $row->currency),
        ])));
        $image = Media::url($row, 'image', 'large');
        echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
        echo '<meta name="description" content="' . esc_attr(\Artiey_AET_Text::substr($desc, 0, 160)) . '" />' . "\n";
        echo '<meta property="og:type" content="product" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr((string) $row->title) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
        }
        $live = $row->status === 'active' && strtotime((string) $row->ends_at_utc . ' UTC') > time();
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => ['Product', 'VisualArtwork'],
            'name' => (string) $row->title,
            'url' => $url,
            'offers' => [
                '@type' => 'Offer',
                'price' => (string) $price,
                'priceCurrency' => (string) ($row->currency ?: 'IRR'),
                'availability' => $live ? 'https://schema.org/InStock' : ($row->status === 'sold' ? 'https://schema.org/SoldOut' : 'https://schema.org/OutOfStock'),
                'priceValidUntil' => gmdate('Y-m-d', (int) strtotime((string) $row->ends_at_utc . ' UTC')),
                'url' => $url,
            ],
        ];
        if ($image) {
            $schema['image'] = $image;
        }
        if ($artist !== '') {
            $schema['creator'] = ['@type' => 'Person', 'name' => $artist];
        }
        if (!empty($row->medium)) {
            $schema['artMedium'] = (string) $row->medium;
        }
        $certs = class_exists('\\Artiey_AET_COA_Bridge') ? \Artiey_AET_COA_Bridge::certificate_summaries(0, (int) $row->artwork_id) : [];
        if ($certs && $certs[0]['state'] === 'valid') {
            $schema['additionalProperty'] = ['@type' => 'PropertyValue', 'name' => I18n::is_fa() ? 'گواهی اصالت' : 'Certificate of authenticity', 'value' => $certs[0]['code']];
        }
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
    }

    public static function register_cron_schedule(array $schedules): array
    {
        if (!isset($schedules['art_coa_five_minutes'])) {
            $schedules['art_coa_five_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __('Every five minutes', 'artiey-aet'),
            ];
        }

        return $schedules;
    }

    public static function schedule_expiry(): void
    {
        if (!wp_next_scheduled('art_coa_close_expired_auctions')) {
            wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'art_coa_five_minutes', 'art_coa_close_expired_auctions');
        }
    }

    public static function listings_table(): string
    {
        return Database::table('auctions');
    }

    public static function bids_table(): string
    {
        return Database::table('auction_bids');
    }

    public static function maybe_migrate(): void
    {
        if (get_option('art_coa_dbv_auction') === '2.3.5') {
            return;
        }
        self::run_migration();
        update_option('art_coa_dbv_auction', '2.3.5', false);
    }

    private static function run_migration(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $a = self::listings_table();
        dbDelta("CREATE TABLE {$a} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            artwork_id BIGINT UNSIGNED NOT NULL,
            collector_id BIGINT UNSIGNED NOT NULL,
            seller_user_id BIGINT UNSIGNED NULL,
            start_price DECIMAL(12,2) NOT NULL,
            reserve_price DECIMAL(12,2) NULL,
            current_bid DECIMAL(12,2) NULL,
            current_bidder_name VARCHAR(190) NULL,
            current_bidder_email VARCHAR(190) NULL,
            bid_count INT UNSIGNED NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'IRR',
            min_increment DECIMAL(12,2) NOT NULL DEFAULT 1,
            original_price DECIMAL(12,2) NULL,
            lot_number VARCHAR(50) NULL,
            auction_house VARCHAR(190) NULL,
            auction_location VARCHAR(190) NULL,
            estimate_low DECIMAL(12,2) NULL,
            estimate_high DECIMAL(12,2) NULL,
            condition_report TEXT NULL,
            provenance TEXT NULL,
            authenticity TEXT NULL,
            shipping_terms TEXT NULL,
            viewing_info TEXT NULL,
            note TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            starts_at_utc DATETIME NOT NULL,
            ends_at_utc DATETIME NOT NULL,
            created_at_utc DATETIME NOT NULL,
            leader_max DECIMAL(12,2) NULL,
            payment_status VARCHAR(20) NULL,
            payment_due_utc DATETIME NULL,
            paid_at_utc DATETIME NULL,
            PRIMARY KEY (id),
            KEY artwork_id (artwork_id),
            KEY collector_id (collector_id),
            KEY status (status),
            KEY ends_at_utc (ends_at_utc),
            KEY status_end (status, ends_at_utc)
        ) {$charset};");

        $b = self::bids_table();
        dbDelta("CREATE TABLE {$b} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            auction_id BIGINT UNSIGNED NOT NULL,
            bidder_name VARCHAR(190) NOT NULL,
            bidder_email VARCHAR(190) NOT NULL,
            bidder_phone VARCHAR(50) NULL,
            bidder_user_id BIGINT UNSIGNED NULL,
            amount DECIMAL(12,2) NOT NULL,
            max_amount DECIMAL(12,2) NULL,
            is_auto TINYINT(1) NOT NULL DEFAULT 0,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY auction_id (auction_id)
        ) {$charset};");
    }

    public static function rewrites(): void
    {
        add_rewrite_rule('^art-coa/auctions/?$', 'index.php?art_coa_auction=list', 'top');
        add_rewrite_rule('^art-coa/auction/([0-9]+)/?$', 'index.php?art_coa_auction=single&art_coa_auction_id=$matches[1]', 'top');
    }

    public static function maybe_render(): void
    {
        $mode = get_query_var('art_coa_auction');
        if (!$mode) {
            return;
        }
        if (!in_array((string) $mode, ['single', 'list'], true)) {
            global $wp_query;
            if ($wp_query instanceof \WP_Query) {
                $wp_query->set_404();
            }
            status_header(404);
            add_filter('wp_robots', 'wp_robots_no_robots');
            return;
        }
        self::close_expired();
        nocache_headers();
        $auction_id = $mode === 'single' ? absint(get_query_var('art_coa_auction_id')) : 0;
        $content = $mode === 'single' ? ($auction_id ? self::render_single($auction_id) : '') : self::render_list([]);
        $not_found = $mode === 'single' && $content === '';
        if ($not_found) {
            global $wp_query;
            if ($wp_query instanceof \WP_Query) {
                $wp_query->set_404();
            }
            status_header(404);
            add_filter('wp_robots', 'wp_robots_no_robots');
            $content = '<p class="art-coa-empty">' . esc_html(I18n::t('no_items')) . '</p>';
        } else {
            status_header(200);
        }
        $route_key = $not_found ? '' : ($mode === 'single' ? 'auction_single' : 'auction_archive');
        if ($route_key !== '' && class_exists('\\Artiey_AET_Elementor_Layouts')) {
            $content = \Artiey_AET_Elementor_Layouts::render_route($route_key, [
                'module' => 'auction', 'entity_type' => 'auction', 'mode' => $mode === 'single' ? 'single' : 'archive', 'id' => $auction_id,
            ], $content);
        }
        get_header();
        echo '<div class="art-coa-profile-wrap" dir="' . (I18n::is_fa() ? 'rtl' : 'ltr') . '">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted auction renderer/assigned Elementor template.
        get_footer();
        exit;
    }

    public static function shortcode($atts = []): string
    {
        self::close_expired();
        $atts = shortcode_atts(['id' => 0, 'limit' => 24, 'q' => '', 'sort' => 'ending', 'columns' => 3, 'title' => '', 'show_title' => 'yes', 'intro' => 'yes',
            'filters' => 'yes', 'badge' => 'yes', 'artist' => 'yes', 'lot' => 'yes', 'estimate' => 'yes', 'house' => 'yes', 'bids' => 'yes', 'ends' => 'yes',
            'button' => '', 'countdown' => 'no'], (array) $atts, 'art_coa_auction');
        if (!empty($atts['id'])) {
            $single = self::render_single(absint($atts['id']));
            return $single !== '' ? $single : '<p class="art-coa-empty">' . esc_html(I18n::t('no_items')) . '</p>';
        }
        $yes = static fn(string $k): bool => in_array(strtolower((string) $atts[$k]), ['yes', '1', 'true'], true);
        return self::render_list([
            'limit' => $atts['limit'], 'q' => $atts['q'], 'sort' => $atts['sort'], 'columns' => (int) $atts['columns'],
            'title' => sanitize_text_field((string) $atts['title']), 'show_title' => $yes('show_title'), 'show_intro' => $yes('intro'),
            'show_filters' => $yes('filters'), 'show_badge' => $yes('badge'), 'show_artist' => $yes('artist'), 'show_lot' => $yes('lot'),
            'show_estimate' => $yes('estimate'), 'show_house' => $yes('house'), 'show_bids' => $yes('bids'), 'show_ends' => $yes('ends'),
            'button_text' => sanitize_text_field((string) $atts['button']), 'show_countdown' => $yes('countdown'),
        ]);
    }

    public static function close_expired(): void
    {
        self::maybe_migrate();
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::listings_table() . " WHERE status = 'active' AND ends_at_utc < %s ORDER BY ends_at_utc ASC LIMIT 50",
            $now
        )) ?: [];
        foreach ($rows as $row) {
            self::finalize($row);
        }
    }

    /**
     * Pure rule: outcome of a closed auction.
     * A leader whose hidden maximum reaches the reserve buys at the reserve even if the shown bid is lower.
     *
     * @return array{status:string,final:?float}
     */
    public static function settle_outcome(?float $current, float $reserve, float $leader_max): array
    {
        if ($current === null || $current <= 0) {
            return ['status' => 'ended', 'final' => null];
        }
        if ($reserve > 0 && $current < $reserve && $leader_max >= $reserve) {
            $current = $reserve;
        }
        return ['status' => ($reserve <= 0 || $current >= $reserve) ? 'sold' : 'ended_reserve', 'final' => $current];
    }

    /** Closes one active listing (cron, page view or admin). Returns the new status, or '' if someone else closed it first. */
    public static function finalize(object $row): string
    {
        global $wpdb;
        $out = self::settle_outcome(
            $row->current_bid !== null ? (float) $row->current_bid : null,
            (float) ($row->reserve_price ?? 0),
            (float) ($row->leader_max ?? 0)
        );
        $status = $out['status'];
        $data = ['status' => $status];
        if ($out['final'] !== null) {
            $data['current_bid'] = $out['final'];
            $row->current_bid = $out['final'];
        }
        if ($status === 'sold') {
            $data['payment_status'] = 'pending';
            $data['payment_due_utc'] = gmdate('Y-m-d H:i:s', time() + self::payment_days() * DAY_IN_SECONDS);
            $row->payment_due_utc = $data['payment_due_utc'];
        }
        if (!$wpdb->update(self::listings_table(), $data, ['id' => (int) $row->id, 'status' => 'active'])) {
            return ''; // already closed by another request
        }
        Audit::log('artwork', (int) $row->artwork_id, 'auction_closed', [
            'auction_id' => (int) $row->id,
            'status'     => $status,
            'final_bid'  => $row->current_bid,
        ]);
        if ($status === 'sold') {
            // The artwork leaves the catalogue as sold; payment and the transfer are tracked in the admin.
            $wpdb->update(Database::table('artworks'), ['status' => 'sold'], ['id' => (int) $row->artwork_id]);
            self::notify_closed($row);
        }
        Cache::flush_stats();
        do_action('art_coa_auction_closed', (int) $row->id, $status, $row);
        return $status;
    }

    public static function proxy_enabled(): bool
    {
        $g = class_exists('\\WPArtCOA\\Admin\\Settings') ? \WPArtCOA\Admin\Settings::get() : [];
        return !array_key_exists('auction_proxy', $g) || !empty($g['auction_proxy']);
    }

    public static function payment_days(): int
    {
        $g = class_exists('\\WPArtCOA\\Admin\\Settings') ? \WPArtCOA\Admin\Settings::get() : [];
        return max(1, min(30, (int) ($g['auction_payment_days'] ?? 3)));
    }

    /**
     * Pure rule: proxy (maximum) bidding.
     * $new_max is what the bidder typed. Visible price moves only as far as needed, one increment above the loser.
     * Equal maximums: the earlier bidder keeps the lead.
     *
     * @return array{winner:string,visible:float,leader_max:float,rows:array<int,array{who:string,amount:float,auto:int}>}
     *   winner: 'new' | 'old'; rows are the bid rows to store (who: new|old).
     */
    public static function resolve_proxy(bool $proxy, bool $has_leader, bool $same_leader, float $current, float $leader_max, float $start, float $inc, float $new_max): array
    {
        $inc = $inc > 0 ? $inc : 1.0;
        if (!$proxy) {
            return ['winner' => 'new', 'visible' => $new_max, 'leader_max' => $new_max, 'rows' => [['who' => 'new', 'amount' => $new_max, 'auto' => 0]]];
        }
        if (!$has_leader) {
            return ['winner' => 'new', 'visible' => $start, 'leader_max' => $new_max, 'rows' => [['who' => 'new', 'amount' => $start, 'auto' => 0]]];
        }
        if ($same_leader) {
            return ['winner' => 'new', 'visible' => $current, 'leader_max' => max($leader_max, $new_max), 'rows' => []];
        }
        if ($new_max > $leader_max) {
            $visible = min($new_max, $leader_max + $inc);
            $rows = [];
            if ($leader_max > $current) {
                $rows[] = ['who' => 'old', 'amount' => $leader_max, 'auto' => 1];
            }
            $rows[] = ['who' => 'new', 'amount' => $visible, 'auto' => 0];
            return ['winner' => 'new', 'visible' => $visible, 'leader_max' => $new_max, 'rows' => $rows];
        }
        $visible = $new_max < $leader_max ? min($leader_max, $new_max + $inc) : $leader_max;
        $rows = [['who' => 'new', 'amount' => $new_max, 'auto' => 0]];
        if ($visible > $new_max) {
            $rows[] = ['who' => 'old', 'amount' => $visible, 'auto' => 1];
        }
        return ['winner' => 'old', 'visible' => $visible, 'leader_max' => $leader_max, 'rows' => $rows];
    }

    /** Admin: payment received. Optionally moves the artwork to the winner and records the transfer. @return string collector|buyer|none */
    public static function mark_paid(object $row, bool $transfer): string
    {
        global $wpdb;
        $wpdb->update(self::listings_table(), ['payment_status' => 'paid', 'paid_at_utc' => gmdate('Y-m-d H:i:s')], ['id' => (int) $row->id]);
        $kind = 'none';
        if ($transfer) {
            $aw = $wpdb->get_row($wpdb->prepare('SELECT current_owner_type, current_owner_id FROM ' . Database::table('artworks') . ' WHERE id = %d', (int) $row->artwork_id));
            $collector_id = 0;
            $u = !empty($row->current_bidder_email) ? get_user_by('email', (string) $row->current_bidder_email) : false;
            if ($u) {
                $collector_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Database::table('collectors') . ' WHERE user_id = %d LIMIT 1', (int) $u->ID));
            }
            $kind = $collector_id ? 'collector' : 'buyer';
            $wpdb->insert(Database::table('ownership_history'), [
                'artwork_id' => (int) $row->artwork_id,
                'from_type' => $aw->current_owner_type ?? null, 'from_id' => $aw->current_owner_id ?? null,
                'to_type' => $kind, 'to_id' => $collector_id ?: null, 'to_name' => (string) $row->current_bidder_name,
                'transferred_at_utc' => gmdate('Y-m-d H:i:s'),
                'notes' => 'auction #' . (int) $row->id,
            ]);
            if ($collector_id) {
                $wpdb->update(Database::table('artworks'), ['current_owner_type' => 'collector', 'current_owner_id' => $collector_id], ['id' => (int) $row->artwork_id]);
            }
        }
        Audit::log('artwork', (int) $row->artwork_id, 'auction_paid', ['auction_id' => (int) $row->id, 'transfer' => $kind]);
        self::mail_winner($row, 'paid');
        Cache::flush_stats();
        return $kind;
    }

    /** Admin: winner did not pay. The sale is cancelled and the artwork returns to the catalogue. */
    public static function mark_default(object $row): void
    {
        global $wpdb;
        $wpdb->update(self::listings_table(), ['payment_status' => 'defaulted', 'status' => 'defaulted'], ['id' => (int) $row->id]);
        $wpdb->update(Database::table('artworks'), ['status' => 'published'], ['id' => (int) $row->artwork_id, 'status' => 'sold']);
        Audit::log('artwork', (int) $row->artwork_id, 'auction_defaulted', ['auction_id' => (int) $row->id]);
        self::mail_winner($row, 'default');
        Cache::flush_stats();
    }

    private static function mail_winner(object $row, string $kind): void
    {
        if (empty($row->current_bidder_email) || !is_email((string) $row->current_bidder_email)) {
            return;
        }
        global $wpdb;
        $title = (string) $wpdb->get_var($wpdb->prepare('SELECT title FROM ' . Database::table('artworks') . ' WHERE id = %d', (int) $row->artwork_id));
        wp_mail((string) $row->current_bidder_email, '[' . I18n::t('auction_title') . '] ' . I18n::t('au_mail_' . $kind . '_subject'),
            I18n::t('au_mail_' . $kind . '_body', ['name' => (string) $row->current_bidder_name, 'title' => $title, 'amount' => Shop::format_price($row->current_bid, (string) $row->currency), 'url' => home_url('/art-coa/auction/' . (int) $row->id)]),
            ['Content-Type: text/plain; charset=UTF-8']);
    }

    /** E-mails the winner and the seller when an auction is sold. */
    private static function notify_closed(object $row): void
    {
        global $wpdb;
        $title = (string) $wpdb->get_var($wpdb->prepare('SELECT title FROM ' . Database::table('artworks') . ' WHERE id = %d', (int) $row->artwork_id));
        $amount = Shop::format_price($row->current_bid, (string) $row->currency);
        $url = home_url('/art-coa/auction/' . (int) $row->id);
        $head = ['Content-Type: text/plain; charset=UTF-8'];
        if (!empty($row->current_bidder_email) && is_email((string) $row->current_bidder_email)) {
            wp_mail((string) $row->current_bidder_email, '[' . I18n::t('auction_title') . '] ' . I18n::t('au_mail_won_subject'),
                I18n::t('au_mail_won_body', ['name' => (string) $row->current_bidder_name, 'title' => $title, 'amount' => $amount, 'url' => $url, 'due' => !empty($row->payment_due_utc) ? Dates::format($row->payment_due_utc, true) : '—']), $head);
        }
        if (!empty($row->seller_user_id)) {
            $u = get_userdata((int) $row->seller_user_id);
            if ($u && $u->user_email) {
                wp_mail($u->user_email, '[' . I18n::t('auction_title') . '] ' . I18n::t('au_mail_sold_subject'),
                    I18n::t('au_mail_sold_body', ['title' => $title, 'amount' => $amount, 'url' => $url]), $head);
            }
        }
    }

    /** Pure rule: is this bidder the seller (by account or by e-mail)? */
    public static function bidder_is_seller(array $seller_user_ids, array $seller_emails, int $user_id, string $email): bool
    {
        $email = strtolower(trim($email));
        if ($user_id > 0 && in_array($user_id, array_map('intval', $seller_user_ids), true)) {
            return true;
        }
        foreach ($seller_emails as $e) {
            if ($email !== '' && strtolower(trim((string) $e)) === $email) {
                return true;
            }
        }
        return false;
    }

    /** @return array{0:int[],1:string[]} accounts and e-mails that belong to the seller of a listing */
    private static function seller_identity(object $row): array
    {
        global $wpdb;
        $ids = [];
        $emails = [];
        if (!empty($row->seller_user_id)) {
            $ids[] = (int) $row->seller_user_id;
        }
        if (!empty($row->collector_id)) {
            $uid = (int) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM ' . Database::table('collectors') . ' WHERE id = %d', (int) $row->collector_id));
            if ($uid) {
                $ids[] = $uid;
            }
        }
        foreach ($ids as $uid) {
            $u = get_userdata($uid);
            if ($u && !empty($u->user_email)) {
                $emails[] = (string) $u->user_email;
            }
        }
        return [$ids, $emails];
    }

    /** Live state of a listing for the polling endpoint (nothing private). @return array<string,mixed> */
    public static function state_payload(object $row, string $viewer_email = ''): array
    {
        $ends = strtotime((string) $row->ends_at_utc . ' UTC');
        $live = $row->status === 'active' && $ends > time();
        $min  = self::min_next_bid($row);
        $cur  = $row->current_bid !== null ? $row->current_bid : $row->start_price;
        return [
            'id' => (int) $row->id, 'status' => (string) $row->status, 'live' => $live,
            'bid' => Shop::format_price($cur, (string) $row->currency), 'has_bid' => $row->current_bid !== null,
            'count' => (int) $row->bid_count, 'min' => $min, 'min_label' => Shop::format_price($min, (string) $row->currency),
            'ends' => (int) $ends, 'left' => max(0, $ends - time()), 'ends_label' => Dates::format($row->ends_at_utc, true),
            'step' => (float) ($row->min_increment ?: 1),
            // Reserve: only whether it is met, never the amount.
            'has_reserve' => (float) ($row->reserve_price ?? 0) > 0,
            'reserve_met' => (float) ($row->reserve_price ?? 0) > 0 && $row->current_bid !== null && (float) $row->current_bid >= (float) $row->reserve_price,
            'you_lead' => $viewer_email !== '' && !empty($row->current_bidder_email) && strtolower((string) $row->current_bidder_email) === strtolower($viewer_email),
            'proxy' => self::proxy_enabled(),
        ] + (self::viewer_leads($row, $viewer_email) && (float) ($row->leader_max ?? 0) > 0
            ? ['your_max' => I18n::t('au_your_max', ['amount' => Shop::format_price((float) $row->leader_max, (string) $row->currency)])]
            : []);
    }

    /** True when the (verified, signed-in) viewer is the current leader. The hidden maximum is shown only to them. */
    private static function viewer_leads(object $row, string $viewer_email): bool
    {
        return $viewer_email !== '' && !empty($row->current_bidder_email) && strtolower((string) $row->current_bidder_email) === strtolower($viewer_email);
    }

    public static function rest_state(\WP_REST_Request $request): \WP_REST_Response
    {
        self::maybe_migrate();
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::listings_table() . ' WHERE id = %d', absint($request->get_param('id'))));
        if (!$row) {
            return new \WP_REST_Response(['error' => 'not_found'], 404);
        }
        if ($row->status === 'active' && strtotime((string) $row->ends_at_utc . ' UTC') <= time()) {
            self::close_expired();
            $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::listings_table() . ' WHERE id = %d', (int) $row->id)) ?: $row;
        }
        $viewer = is_user_logged_in() ? (string) wp_get_current_user()->user_email : '';
        $res = new \WP_REST_Response(self::state_payload($row, $viewer));
        $res->header('Cache-Control', 'no-store');
        return $res;
    }

    public static function render_list(array $args): string
    {
        self::maybe_migrate();
        $limit = max(1, min(50, absint($args['limit'] ?? 24)));
        global $wpdb;
        $search_raw = isset($_GET['art_coa_auction_q']) && is_scalar($_GET['art_coa_auction_q']) ? wp_unslash($_GET['art_coa_auction_q']) : ($args['q'] ?? '');
        $search = sanitize_text_field((string) $search_raw);
        $search = \Artiey_AET_Text::substr($search, 0, 100);
        $sort_raw = empty($args['ignore_request_sort']) && isset($_GET['art_coa_auction_sort']) && is_scalar($_GET['art_coa_auction_sort']) ? wp_unslash($_GET['art_coa_auction_sort']) : ($args['sort'] ?? 'ending');
        $sort = sanitize_key((string) $sort_raw);
        $sorts = [
            'ending' => 'au.ends_at_utc ASC',
            'newest' => 'au.created_at_utc DESC',
            'bids'   => 'au.bid_count DESC, au.ends_at_utc ASC',
            'price_high' => 'COALESCE(au.current_bid, au.start_price) DESC',
            'price_low'  => 'COALESCE(au.current_bid, au.start_price) ASC',
        ];
        if (!isset($sorts[$sort])) {
            $sort = 'ending';
        }
        $page = max(1, absint($args['page'] ?? ($_GET['art_coa_auction_page'] ?? 1)));
        $offset = ($page - 1) * $limit;
        $where = "WHERE au.status IN ('active','scheduled')";
        $params = [];
        if (!current_user_can('manage_options')) {
            $public_statuses = \WPArtCOA\Entities\Artwork::public_statuses();
            $where .= ' AND a.status IN (' . implode(',', array_fill(0, count($public_statuses), '%s')) . ')';
            $params = array_merge($params, $public_statuses);
        }
        if ($search !== '') {
            $where .= ' AND (a.title LIKE %s OR ar.display_name LIKE %s OR au.auction_house LIKE %s OR au.lot_number LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            // Append: replacing the array dropped the public-status values and broke visitor searches.
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        $sql = "SELECT au.*, a.title, a.status AS artwork_status, a.image_url, a.image_id, a.year_created,
                    ar.display_name AS artist_name, ar.slug AS artist_slug, ar.first_name, ar.last_name, ar.status AS artist_status,
                    c.display_name AS collector_name, c.slug AS collector_slug, c.is_public AS collector_is_public, c.status AS collector_status
             FROM " . self::listings_table() . " au
             INNER JOIN " . Database::table('artworks') . " a ON a.id = au.artwork_id
             LEFT JOIN " . Database::table('artists') . " ar ON ar.id = a.artist_id
             LEFT JOIN " . Database::table('collectors') . " c ON c.id = au.collector_id
             {$where}
             ORDER BY {$sorts[$sort]}
             LIMIT %d OFFSET %d";
        $items = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [$limit, $offset]))) ?: [];
        $count_sql = "SELECT COUNT(*) FROM " . self::listings_table() . " au
             INNER JOIN " . Database::table('artworks') . " a ON a.id = au.artwork_id
             LEFT JOIN " . Database::table('artists') . " ar ON ar.id = a.artist_id {$where}";
        $count_query = $params ? $wpdb->prepare($count_sql, $params) : $count_sql;
        $total = (int) $wpdb->get_var($count_query);
        $pages = (int) ceil($total / $limit);

        $flash = self::get_flash();
        $o = $args + ['show_title' => true, 'title' => '', 'show_intro' => true, 'show_filters' => true, 'columns' => 3];
        $cols = max(1, min(6, (int) $o['columns']));
        ob_start();
        ?>
        <div class="art-coa-auction-list is-cols-<?php echo (int) $cols; ?>" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <?php if (!empty($o['show_title'])): ?><h2 class="art-coa-auction-title"><?php echo esc_html($o['title'] !== '' ? (string) $o['title'] : I18n::t('auction_title')); ?></h2><?php endif; ?>
            <?php if (!empty($o['show_intro'])): ?><p class="art-coa-shop-intro art-coa-auction-intro"><?php echo esc_html(I18n::t('auction_intro')); ?></p><?php endif; ?>
            <?php if (!empty($o['show_filters'])): ?>
            <form class="art-coa-auction-filters" method="get" action="<?php echo esc_url(is_singular() ? get_permalink() : home_url('/art-coa/auctions/')); ?>">
                <label><span><?php echo I18n::is_fa() ? 'جستجوی اثر، هنرمند یا حراجی' : 'Search artwork, artist or auction house'; ?></span>
                    <input type="search" name="art_coa_auction_q" value="<?php echo esc_attr($search); ?>" maxlength="100"></label>
                <label><span><?php echo I18n::is_fa() ? 'مرتب‌سازی' : 'Sort by'; ?></span>
                    <select name="art_coa_auction_sort">
                        <?php foreach (['ending' => (I18n::is_fa() ? 'نزدیک‌ترین پایان' : 'Ending soon'), 'newest' => (I18n::is_fa() ? 'جدیدترین' : 'Newest'), 'bids' => (I18n::is_fa() ? 'بیشترین پیشنهاد' : 'Most bids'), 'price_high' => (I18n::is_fa() ? 'بالاترین قیمت' : 'Highest bid'), 'price_low' => (I18n::is_fa() ? 'پایین‌ترین قیمت' : 'Lowest bid')] as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($sort, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <input type="hidden" name="art_coa_auction_page" value="1">
                <button class="button button-primary" type="submit"><?php echo I18n::is_fa() ? 'اعمال فیلتر' : 'Apply'; ?></button>
            </form>
            <?php endif; ?>
            <?php if ($flash): ?>
                <div class="art-coa-result <?php echo $flash['type'] === 'ok' ? 'valid' : 'invalid'; ?>">
                    <p><?php echo esc_html($flash['msg']); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!$items): ?>
                <p class="art-coa-empty"><?php echo esc_html(I18n::t('auction_empty')); ?></p>
            <?php else: ?>
                <p class="art-coa-auction-result-count"><?php echo esc_html(I18n::is_fa() ? (number_format_i18n($total) . ' حراج فعال') : (number_format_i18n($total) . ' active auctions')); ?></p>
                <div class="art-coa-auction-grid">
                    <?php foreach ($items as $row):
                        echo self::card_html($row, $o);
                    endforeach; ?>
                </div>
                <?php if ($pages > 1): ?><nav class="art-coa-auction-pager" aria-label="<?php echo esc_attr(I18n::is_fa() ? 'صفحه‌بندی حراج‌ها' : 'Auction pages'); ?>">
                    <?php if ($page > 1): ?><a class="button" href="<?php echo esc_url(add_query_arg(['art_coa_auction_q' => $search, 'art_coa_auction_sort' => $sort, 'art_coa_auction_page' => $page - 1], is_singular() ? get_permalink() : home_url('/art-coa/auctions/'))); ?>">← <?php echo I18n::is_fa() ? 'قبلی' : 'Previous'; ?></a><?php endif; ?>
                    <span><?php echo esc_html(number_format_i18n($page) . ' / ' . number_format_i18n($pages)); ?></span>
                    <?php if ($page < $pages): ?><a class="button" href="<?php echo esc_url(add_query_arg(['art_coa_auction_q' => $search, 'art_coa_auction_sort' => $sort, 'art_coa_auction_page' => $page + 1], is_singular() ? get_permalink() : home_url('/art-coa/auctions/'))); ?>"><?php echo I18n::is_fa() ? 'بعدی' : 'Next'; ?> →</a><?php endif; ?>
                </nav><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_single(int $id): string
    {
        self::maybe_migrate();
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT au.*, a.title, a.status AS artwork_status, a.image_url, a.image_id, a.year_created, a.medium, a.dimensions, a.gallery_ids,
                    ar.display_name AS artist_name, ar.slug AS artist_slug, ar.first_name, ar.last_name, ar.status AS artist_status,
                    c.display_name AS collector_name, c.slug AS collector_slug, c.is_public AS collector_is_public, c.status AS collector_status
             FROM " . self::listings_table() . " au
             INNER JOIN " . Database::table('artworks') . " a ON a.id = au.artwork_id
             LEFT JOIN " . Database::table('artists') . " ar ON ar.id = a.artist_id
             LEFT JOIN " . Database::table('collectors') . " c ON c.id = au.collector_id
             WHERE au.id = %d",
            $id
        ));
        if (!$row) {
            return '';
        }
        if (!current_user_can('manage_options')
            && !in_array((string) ($row->artwork_status ?? ''), \WPArtCOA\Entities\Artwork::public_statuses(), true)) {
            return '';
        }

        if (get_query_var('art_coa_auction') === 'single') {
            self::$seo_row = $row;
        }
        $bids = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::bids_table() . ' WHERE auction_id = %d ORDER BY amount DESC, id DESC LIMIT 20',
            $id
        )) ?: [];

        $is_live = $row->status === 'active' && strtotime($row->ends_at_utc . ' UTC') > time();
        $min_next = self::min_next_bid($row);
        $flash = self::get_flash();
        $artist_is_public = ($row->artist_status ?? '') === 'active';
        $an = $artist_is_public ? ($row->artist_name ?: trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''))) : '';
        $artwork_photos = Media::gallery_items($row->gallery_ids ?? '');

        ob_start();
        ?>
        <div class="art-coa-auction-single" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>" data-auction-id="<?php echo (int) $row->id; ?>"<?php echo $is_live ? ' data-live="1"' : ''; ?>>
            <?php if ($flash): ?>
                <div class="art-coa-result <?php echo $flash['type'] === 'ok' ? 'valid' : 'invalid'; ?>">
                    <p><?php echo esc_html($flash['msg']); ?></p>
                </div>
            <?php endif; ?>

            <div class="art-coa-auction-layout">
                <div class="art-coa-auction-media">
                    <?php if (\WPArtCOA\Media::url($row, 'image', 'large')): ?>
                        <a class="art-coa-aw-main" href="<?php echo esc_url(\WPArtCOA\Media::url($row, 'image', 'full')); ?>" data-art-lightbox="auction" aria-label="<?php echo esc_attr(I18n::is_fa() ? 'بزرگ‌نمایی تصویر اثر' : 'Zoom artwork image'); ?>">
                            <?php echo Media::img($row, 'image', 'large', ['alt' => (string) $row->title, 'loading' => 'eager']); ?>
                        </a>
                        <?php if ($artwork_photos): ?><div class="art-coa-aw-thumbs">
                            <?php foreach ($artwork_photos as $photo): ?><a href="<?php echo esc_url($photo['full']); ?>" data-art-lightbox="auction"><img src="<?php echo esc_url($photo['thumb']); ?>" alt="<?php echo esc_attr($photo['alt']); ?>" loading="lazy"></a><?php endforeach; ?>
                        </div><?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="art-coa-auction-info">
                    <span class="art-coa-shop-badge"><?php echo esc_html($is_live ? I18n::t('auction_live') : ($row->status === 'sold' ? I18n::t('cat_sold') : I18n::t('auction_closed'))); ?></span>
                    <h1><?php echo esc_html($row->title); ?></h1>
                    <?php if ($an): ?>
                        <p class="meta"><?php if ($row->artist_slug): ?><a href="<?php echo esc_url(ContentSync::artist_url((string) $row->artist_slug)); ?>"><?php endif; ?><?php echo esc_html($an); ?><?php if ($row->artist_slug): ?></a><?php endif; ?></p>
                    <?php endif; ?>
                    <?php if (!empty($row->lot_number)): ?><p class="meta"><?php echo I18n::is_fa() ? 'شماره لات' : 'Lot'; ?>: <?php echo esc_html($row->lot_number); ?></p><?php endif; ?>
                    <?php if (!empty($row->medium) || !empty($row->dimensions) || !empty($row->year_created)): ?>
                        <dl class="art-coa-auction-artwork-facts">
                            <?php if (!empty($row->year_created)): ?><div><dt><?php echo I18n::is_fa() ? 'سال خلق' : 'Year'; ?></dt><dd><?php echo esc_html((string) $row->year_created); ?></dd></div><?php endif; ?>
                            <?php if (!empty($row->medium)): ?><div><dt><?php echo I18n::is_fa() ? 'تکنیک' : 'Medium'; ?></dt><dd><?php echo esc_html($row->medium); ?></dd></div><?php endif; ?>
                            <?php if (!empty($row->dimensions)): ?><div><dt><?php echo I18n::is_fa() ? 'ابعاد' : 'Dimensions'; ?></dt><dd><?php echo esc_html($row->dimensions); ?></dd></div><?php endif; ?>
                        </dl>
                    <?php endif; ?>
                    <p class="meta"><?php echo esc_html(I18n::t('seller')); ?>:
                        <?php echo esc_html(((int) ($row->collector_is_public ?? 0) === 1 && ($row->collector_status ?? '') === 'active') ? (string) $row->collector_name : (I18n::is_fa() ? 'مجموعه‌دار خصوصی' : 'Private collector')); ?>
                    </p>

                    <?php $viewer_mail = is_user_logged_in() ? (string) wp_get_current_user()->user_email : ''; $st = self::state_payload($row, $viewer_mail); ?>
                    <p class="art-coa-au-lead" data-au="lead" role="status"<?php echo $st['you_lead'] ? '' : ' hidden'; ?>><?php echo esc_html(I18n::t('au_you_lead')); ?></p>
                    <p class="art-coa-au-yourmax" data-au="yourmax"<?php echo !empty($st['your_max']) ? '' : ' hidden'; ?>><?php echo esc_html((string) ($st['your_max'] ?? '')); ?></p>
                    <?php if ($st['has_reserve']): ?><p class="art-coa-au-reserve <?php echo $st['reserve_met'] ? 'is-met' : 'is-unmet'; ?>" data-au="reserve" data-met-text="<?php echo esc_attr(I18n::t('au_reserve_met')); ?>" data-unmet-text="<?php echo esc_attr(I18n::t('au_reserve_unmet')); ?>"><?php echo esc_html($st['reserve_met'] ? I18n::t('au_reserve_met') : I18n::t('au_reserve_unmet')); ?></p><?php endif; ?>
                    <div class="art-coa-auction-stats" aria-live="polite" aria-atomic="false">
                        <div>
                            <span class="lbl"><?php echo esc_html(I18n::t('current_bid')); ?></span>
                            <span class="val" data-au="bid"><?php echo esc_html($row->current_bid !== null
                                ? Shop::format_price($row->current_bid, $row->currency)
                                : Shop::format_price($row->start_price, $row->currency) . ' (' . I18n::t('start_price') . ')'); ?></span>
                        </div>
                        <div>
                            <span class="lbl"><?php echo esc_html(I18n::t('bid_count')); ?></span>
                            <span class="val" data-au="count"><?php echo esc_html((string) (int) $row->bid_count); ?></span>
                        </div>
                        <div>
                            <span class="lbl"><?php echo esc_html(I18n::t('ends_at')); ?></span>
                            <span class="val art-coa-countdown" data-au="ends" data-ends="<?php echo esc_attr($row->ends_at_utc); ?>" data-ends-ts="<?php echo (int) strtotime((string) $row->ends_at_utc . ' UTC'); ?>">
                                <?php echo esc_html(Dates::format($row->ends_at_utc, true)); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($row->original_price): ?>
                        <p class="meta"><?php echo esc_html(I18n::t('original_price')); ?>:
                            <?php echo esc_html(Shop::format_price($row->original_price, $row->currency)); ?>
                            <?php
                            $base = $row->current_bid ?? $row->start_price;
                            $ch = SecondaryMarket::price_change($row->original_price, $base);
                            if ($ch !== null) {
                                echo ' <span class="art-coa-price-change ' . ($ch >= 0 ? 'up' : 'down') . '">'
                                    . ($ch >= 0 ? '▲' : '▼') . esc_html((string) abs($ch)) . '%</span>';
                            }
                            ?>
                        </p>
                    <?php endif; ?>

                    <?php
                    $lot_certs = class_exists('\\Artiey_AET_COA_Bridge') ? \Artiey_AET_COA_Bridge::certificate_summaries(0, (int) $row->artwork_id) : [];
                    if ($lot_certs): ?>
                        <section class="art-coa-section art-coa-auction-certificate">
                            <h2><?php echo esc_html(I18n::is_fa() ? 'گواهی اصالت اثر' : 'Certificate of authenticity'); ?></h2>
                            <?php echo \Artiey_AET_COA_Bridge::badge_html($lot_certs, 'panel'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </section>
                    <?php endif; ?>
                    <?php
                    $auction_details = [
                        'auction_house' => I18n::is_fa() ? 'برگزارکننده' : 'Auction house',
                        'auction_location' => I18n::is_fa() ? 'محل حراج' : 'Location',
                        'condition_report' => I18n::is_fa() ? 'وضعیت اثر' : 'Condition report',
                        'provenance' => I18n::is_fa() ? 'پیشینه و مالکیت' : 'Provenance',
                        'authenticity' => I18n::is_fa() ? 'اصالت و مستندات' : 'Authenticity',
                        'viewing_info' => I18n::is_fa() ? 'زمان و شیوه بازدید' : 'Viewing information',
                        'shipping_terms' => I18n::is_fa() ? 'ارسال و تحویل' : 'Shipping and collection',
                        'note' => I18n::is_fa() ? 'شرایط و توضیحات حراج' : 'Auction terms and notes',
                    ];
                    $has_details = false;
                    foreach ($auction_details as $field => $label) {
                        if (!empty($row->{$field})) { $has_details = true; break; }
                    }
                    ?>
                    <?php if ($has_details || !empty($row->estimate_low) || !empty($row->estimate_high)): ?>
                        <section class="art-coa-section art-coa-auction-details">
                            <h2><?php echo I18n::is_fa() ? 'اطلاعات کامل لات' : 'Lot details'; ?></h2>
                            <?php if (!empty($row->estimate_low) || !empty($row->estimate_high)): ?>
                                <p class="art-coa-auction-estimate"><strong><?php echo I18n::is_fa() ? 'برآورد قیمت' : 'Estimate'; ?>:</strong>
                                    <?php if (!empty($row->estimate_low)): echo esc_html(Shop::format_price($row->estimate_low, $row->currency)); endif; ?>
                                    <?php if (!empty($row->estimate_low) && !empty($row->estimate_high)): echo ' – '; endif; ?>
                                    <?php if (!empty($row->estimate_high)): echo esc_html(Shop::format_price($row->estimate_high, $row->currency)); endif; ?>
                                </p>
                            <?php endif; ?>
                            <dl class="art-coa-auction-detail-grid">
                                <?php foreach ($auction_details as $field => $label): if (!empty($row->{$field})): ?>
                                    <div><dt><?php echo esc_html($label); ?></dt><dd><?php echo nl2br(esc_html((string) $row->{$field})); ?></dd></div>
                                <?php endif; endforeach; ?>
                            </dl>
                        </section>
                    <?php endif; ?>

                    <?php if ($is_live && self::login_required() && !is_user_logged_in()): ?>
                        <div class="art-coa-bid-form art-coa-bid-login">
                            <p><?php echo esc_html(I18n::t('au_login_required')); ?></p>
                            <p><a class="button button-primary" href="<?php echo esc_url(wp_login_url(home_url('/art-coa/auction/' . (int) $row->id))); ?>"><?php echo esc_html(I18n::t('au_login_button')); ?></a></p>
                        </div>
                    <?php elseif ($is_live): ?>
                        <form method="post" class="art-coa-bid-form">
                            <?php wp_nonce_field('art_coa_auction_bid'); ?>
                            <input type="hidden" name="art_coa_auction_bid" value="1">
                            <input type="hidden" name="auction_id" value="<?php echo (int) $row->id; ?>">
                            <p><label><?php echo esc_html(self::proxy_enabled() ? I18n::t('au_your_max_label') : I18n::t('your_bid')); ?> *
                                (<?php echo esc_html(I18n::t('min_bid')); ?>: <span class="art-coa-bid-min"><?php echo esc_html(Shop::format_price($min_next, $row->currency)); ?></span>)
                                <br><input type="number" name="amount" step="any" min="<?php echo esc_attr((string) $min_next); ?>" required class="widefat"></label></p>
                            <?php if (self::proxy_enabled()): ?><p class="meta art-coa-bid-hint"><?php echo esc_html(I18n::t('au_proxy_hint')); ?></p><?php endif; ?>
                            <p><label><?php echo esc_html(I18n::t('name')); ?> *<br>
                                <input type="text" name="bidder_name" required class="widefat" value="<?php echo esc_attr(is_user_logged_in() ? (string) wp_get_current_user()->display_name : ''); ?>"></label></p>
                            <?php if (is_user_logged_in()): $cu = wp_get_current_user(); ?>
                                <p class="meta"><?php echo esc_html(I18n::t('email')); ?>: <bdi><?php echo esc_html((string) $cu->user_email); ?></bdi></p>
                                <input type="hidden" name="bidder_email" value="<?php echo esc_attr((string) $cu->user_email); ?>">
                            <?php else: ?>
                            <p><label><?php echo esc_html(I18n::t('email')); ?> *<br>
                                <input type="email" name="bidder_email" required class="widefat"></label></p>
                            <?php endif; ?>
                            <p><label><?php echo esc_html(I18n::t('phone')); ?><br>
                                <input type="text" name="bidder_phone" class="widefat"></label></p>
                            <?php $step = (float) ($row->min_increment ?: 1); ?>
                            <div class="art-coa-bid-quick" data-min="<?php echo esc_attr((string) $min_next); ?>" data-step="<?php echo esc_attr((string) $step); ?>" role="group" aria-label="<?php echo esc_attr(I18n::t('au_quick_bid')); ?>">
                                <?php foreach ([0, 1, 2, 5] as $k): $v = $min_next + $k * $step; ?>
                                    <button type="button" class="art-coa-bid-chip" data-amount="<?php echo esc_attr((string) $v); ?>" data-k="<?php echo (int) $k; ?>"><?php echo esc_html(Shop::format_price($v, $row->currency)); ?></button>
                                <?php endforeach; ?>
                            </div>
                            <p><button type="submit" class="button button-primary"><?php echo esc_html(I18n::t('place_bid')); ?></button></p>
                            <p class="art-coa-bid-msg" data-au="msg" role="status" aria-live="polite"></p>
                        </form>
                    <?php else: ?>
                        <?php $status_labels = ['sold' => I18n::t('cat_sold'), 'closed' => I18n::t('auction_closed'), 'ended' => I18n::t('auction_closed'), 'unsold' => (I18n::is_fa() ? 'بدون فروش' : 'Unsold'), 'cancelled' => (I18n::is_fa() ? 'لغو شده' : 'Cancelled'), 'scheduled' => (I18n::is_fa() ? 'هنوز آغاز نشده' : 'Not started yet'), 'pending_payment' => (I18n::is_fa() ? 'در انتظار پرداخت برنده' : 'Awaiting payment'), 'defaulted' => (I18n::is_fa() ? 'عدم پرداخت برنده' : 'Winner defaulted')]; ?>
                        <p class="art-coa-auction-closed"><strong><?php echo esc_html($row->status === 'scheduled' ? $status_labels['scheduled'] : I18n::t('auction_closed')); ?></strong>
                            <?php if ($row->status !== 'scheduled'): ?>(<?php echo esc_html($status_labels[(string) $row->status] ?? (string) $row->status); ?>)<?php endif; ?></p>
                        <?php if ($row->status === 'sold' && $row->current_bidder_name): ?>
                            <p><?php echo esc_html(I18n::t('winner')); ?>: <?php echo esc_html(self::mask_name((string) $row->current_bidder_name)); ?>
                                — <?php echo esc_html(Shop::format_price($row->current_bid, $row->currency)); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <section class="art-coa-section">
                <h2><?php echo esc_html(I18n::t('bid_history')); ?></h2>
                <?php if (!$bids): ?>
                    <p class="art-coa-empty"><?php echo esc_html(I18n::t('no_bids')); ?></p>
                <?php else: ?>
                    <table class="art-coa-cert-table">
                        <thead><tr>
                            <th><?php echo esc_html(I18n::t('amount')); ?></th>
                            <th><?php echo esc_html(I18n::t('bidder')); ?></th>
                            <th><?php echo esc_html(I18n::t('date')); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($bids as $b): ?>
                            <tr>
                                <td><?php echo esc_html(Shop::format_price($b->amount, $row->currency)); ?></td>
                                <td><?php echo esc_html(self::mask_name($b->bidder_name)); ?></td>
                                <td><?php echo esc_html(Dates::format($b->created_at_utc, true)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            <p><a href="<?php echo esc_url(home_url('/art-coa/auctions/')); ?>">← <?php echo esc_html(I18n::t('auction_title')); ?></a></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function card_html(object $row, array $o = []): string
    {
        $o  += ['show_badge' => true, 'show_artist' => true, 'show_lot' => true, 'show_estimate' => true, 'show_house' => true, 'show_bids' => true, 'show_ends' => true, 'button_text' => '', 'show_countdown' => false];
        $artist_is_public = ($row->artist_status ?? '') === 'active';
        $an  = $artist_is_public ? ($row->artist_name ?: trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''))) : '';
        $bid = $row->current_bid !== null ? $row->current_bid : $row->start_price;
        $url = home_url('/art-coa/auction/' . (int) $row->id . '/');
        $img = \WPArtCOA\Media::url($row, 'image', 'large');
        $scheduled = ($row->status ?? '') === 'scheduled' || (!empty($row->starts_at_utc) && strtotime((string) $row->starts_at_utc . ' UTC') > time());
        $certs = class_exists('\\Artiey_AET_COA_Bridge') ? \Artiey_AET_COA_Bridge::certificate_summaries(0, (int) ($row->artwork_id ?? 0)) : [];
        $est = [];
        if (!empty($row->estimate_low)) { $est[] = Shop::format_price($row->estimate_low, $row->currency); }
        if (!empty($row->estimate_high)) { $est[] = Shop::format_price($row->estimate_high, $row->currency); }
        $left = '';
        if (!empty($o['show_countdown']) && !empty($row->ends_at_utc)) {
            $secs = strtotime((string) $row->ends_at_utc . ' UTC') - time();
            if ($secs > 0) {
                $d = (int) floor($secs / 86400);
                $h = (int) floor(($secs % 86400) / 3600);
                $left = $d > 0 ? I18n::t('au_left', ['d' => Catalog::n($d), 'h' => Catalog::n($h)]) : I18n::t('au_left_hours', ['h' => Catalog::n($h)]);
            }
        }
        ob_start();
        ?>
        <article class="art-coa-au-card">
            <a class="art-coa-au-media" href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true">
                <?php if ($img): ?><?php echo Media::img($row, 'image', 'medium_large', ['alt' => '']); ?><?php else: ?><span class="art-coa-c-ph">🖼</span><?php endif; ?>
                <?php if (!empty($o['show_badge'])): ?><span class="art-coa-au-badge<?php echo $scheduled ? ' is-scheduled' : ''; ?>"><?php echo esc_html($scheduled ? (I18n::is_fa() ? 'به‌زودی' : 'Upcoming') : I18n::t('auction_live')); ?></span><?php endif; ?>
            </a>
            <div class="art-coa-au-body">
                <h3 class="art-coa-au-title"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($row->title); ?></a></h3>
                <?php if ($certs && class_exists('\\Artiey_AET_COA_Bridge')): ?><p class="art-coa-au-cert"><?php echo \Artiey_AET_COA_Bridge::badge_html($certs, 'badge'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p><?php endif; ?>
                <?php if (!empty($o['show_artist']) && $an && $row->artist_slug): ?>
                    <p class="art-coa-au-artist"><a href="<?php echo esc_url(ContentSync::artist_url((string) $row->artist_slug)); ?>"><?php echo esc_html($an); ?></a></p>
                <?php endif; ?>
                <p class="art-coa-au-price"><span class="art-coa-au-price-label"><?php echo esc_html($row->current_bid !== null ? I18n::t('au_current_bid') : I18n::t('au_start_price')); ?></span><strong><?php echo esc_html(Shop::format_price($bid, $row->currency)); ?></strong></p>
                <ul class="art-coa-au-meta">
                    <?php if (!empty($o['show_lot']) && !empty($row->lot_number)): ?><li><?php echo esc_html(I18n::t('au_lot')); ?> <?php echo esc_html($row->lot_number); ?></li><?php endif; ?>
                    <?php if (!empty($o['show_estimate']) && $est): ?><li><?php echo esc_html(I18n::t('au_estimate')); ?>: <?php echo esc_html(implode(' – ', $est)); ?></li><?php endif; ?>
                    <?php if (!empty($o['show_house']) && !empty($row->auction_house)): ?><li><?php echo esc_html($row->auction_house); ?></li><?php endif; ?>
                    <?php if (!empty($o['show_bids'])): ?><li><?php echo esc_html(I18n::t('bid_count')); ?>: <?php echo esc_html(Catalog::n((int) $row->bid_count)); ?></li><?php endif; ?>
                    <?php if (!empty($o['show_ends'])): ?><li><?php echo esc_html(I18n::t('ends_at')); ?>: <?php echo esc_html(Dates::format($row->ends_at_utc, true)); ?></li><?php endif; ?>
                    <?php if ($left !== ''): ?><li class="art-coa-au-left"><?php echo esc_html($left); ?></li><?php endif; ?>
                </ul>
                <a class="art-coa-au-btn" href="<?php echo esc_url($url); ?>"><?php echo esc_html($o['button_text'] !== '' ? (string) $o['button_text'] : ($scheduled ? I18n::t('view_page') : I18n::t('place_bid'))); ?><span class="screen-reader-text"> — <?php echo esc_html((string) $row->title); ?></span></a>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /** Collector dashboard: start auction */
    public static function collector_panel(int $collector_id): string
    {
        self::maybe_migrate();
        global $wpdb;
        $mine = $wpdb->get_results($wpdb->prepare(
            'SELECT au.*, a.title FROM ' . self::listings_table() . ' au
             LEFT JOIN ' . Database::table('artworks') . ' a ON a.id = au.artwork_id
             WHERE au.collector_id = %d ORDER BY au.id DESC LIMIT 20',
            $collector_id
        )) ?: [];

        ob_start();
        echo '<div class="art-coa-sec-panel art-coa-auction-panel">';
        echo '<h3>' . esc_html(I18n::t('auction_title')) . '</h3>';
        echo '<p class="description">' . esc_html(I18n::t('auction_admin_only')) . '</p>';
        echo '<p><a class="button" href="' . esc_url(home_url('/art-coa/auctions/')) . '">' . esc_html(I18n::t('auction_title')) . '</a></p>';
        if ($mine) {
            echo '<h4>' . esc_html(I18n::t('auction_my')) . '</h4><ul class="art-coa-link-list">';
            foreach ($mine as $mrow) {
                echo '<li><a href="' . esc_url(home_url('/art-coa/auction/' . (int) $mrow->id)) . '"><strong>'
                    . esc_html($mrow->title) . '</strong></a> — ' . esc_html($mrow->status)
                    . ' — ' . esc_html(Shop::format_price($mrow->current_bid ?? $mrow->start_price, $mrow->currency))
                    . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    public static function handle_create(): void
    {
        if (empty($_POST['art_coa_auction_create']) || !is_user_logged_in()) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field((string) ($_POST['_wpnonce'] ?? '')), 'art_coa_auction_create')) {
            return;
        }

        $user_id = get_current_user_id();
        $collector_id = absint($_POST['collector_id'] ?? 0);
        $artwork_id = absint($_POST['artwork_id'] ?? 0);
        $start = (float) ($_POST['start_price'] ?? 0);
        $reserve = ($_POST['reserve_price'] ?? '') !== '' ? (float) $_POST['reserve_price'] : null;
        $inc = max(0.01, (float) ($_POST['min_increment'] ?? 1));
        $currency = sanitize_text_field($_POST['currency'] ?? 'IRR') ?: 'IRR';
        $days = max(1, min(30, absint($_POST['days'] ?? 7)));
        $note = sanitize_textarea_field($_POST['note'] ?? '');
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

        // Only site administrator manages auctions
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $owns = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . Database::table('artworks') . "
             WHERE id = %d AND current_owner_type = 'collector' AND current_owner_id = %d",
            $artwork_id,
            $collector_id
        ));
        if (!$owns || $start <= 0 || ($estimate_low !== null && $estimate_high !== null && $estimate_low > $estimate_high)) {
            self::set_flash('err', I18n::t('error'));
            wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/member-dashboard/'));
            exit;
        }

        $aw = $wpdb->get_row($wpdb->prepare(
            'SELECT price FROM ' . Database::table('artworks') . ' WHERE id = %d',
            $artwork_id
        ));

        $now = gmdate('Y-m-d H:i:s');
        $ends = gmdate('Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS);

        $inserted = $wpdb->insert(self::listings_table(), [
            'artwork_id'         => $artwork_id,
            'collector_id'       => $collector_id,
            'seller_user_id'     => $user_id,
            'start_price'        => $start,
            'reserve_price'      => $reserve,
            'current_bid'        => null,
            'bid_count'          => 0,
            'currency'           => $currency,
            'min_increment'      => $inc,
            'original_price'     => $aw->price !== null ? (float) $aw->price : null,
            'lot_number'         => $lot_number,
            'auction_house'      => $auction_house,
            'auction_location'   => $auction_location,
            'estimate_low'       => $estimate_low,
            'estimate_high'      => $estimate_high,
            'condition_report'   => $condition_report,
            'provenance'         => $provenance,
            'authenticity'       => $authenticity,
            'shipping_terms'     => $shipping_terms,
            'viewing_info'       => $viewing_info,
            'note'               => $note,
            'status'             => 'active',
            'starts_at_utc'      => $now,
            'ends_at_utc'        => $ends,
            'created_at_utc'     => $now,
        ]);
        if (false === $inserted) {
            self::set_flash('err', I18n::t('error'));
            wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/auctions/'));
            exit;
        }
        $aid = (int) $wpdb->insert_id;
        Audit::log('artwork', $artwork_id, 'auction_started', ['auction_id' => $aid, 'start' => $start]);
        self::set_flash('ok', I18n::t('auction_started'));
        wp_safe_redirect(home_url('/art-coa/auction/' . $aid));
        exit;
    }

    /** Classic (no-JavaScript) bid form: validates the nonce, places the bid, flashes the result and redirects. */
    public static function handle_bid(): void
    {
        if (empty($_POST['art_coa_auction_bid'])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field((string) ($_POST['_wpnonce'] ?? '')), 'art_coa_auction_bid')) {
            return;
        }
        $auction_id = absint($_POST['auction_id'] ?? 0);
        $res = self::place_bid(
            $auction_id,
            round((float) wp_unslash($_POST['amount'] ?? 0), 2),
            sanitize_text_field(wp_unslash($_POST['bidder_name'] ?? '')),
            sanitize_email(wp_unslash($_POST['bidder_email'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['bidder_phone'] ?? '')),
            is_user_logged_in() ? get_current_user_id() : 0
        );
        self::set_flash($res['ok'] ? 'ok' : 'err', $res['msg']);
        $back = home_url('/art-coa/auction/' . $auction_id);
        wp_safe_redirect($res['code'] === 'login' ? wp_login_url($back) : $back);
        exit;
    }

    /** AJAX bid (progressive enhancement): POST /wp-json/art-coa/v1/auction/{id}/bid, signed-in users, X-WP-Nonce checked by WordPress. */
    public static function rest_bid(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = absint($request->get_param('id'));
        $res = self::place_bid(
            $id,
            round((float) $request->get_param('amount'), 2),
            sanitize_text_field((string) $request->get_param('name')),
            '',
            sanitize_text_field((string) $request->get_param('phone')),
            get_current_user_id()
        );
        $out = ['ok' => $res['ok'], 'code' => $res['code'], 'message' => $res['msg']];
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::listings_table() . ' WHERE id = %d', $id));
        if ($row) {
            $out['state'] = self::state_payload($row, wp_get_current_user()->user_email ?? '');
        }
        $http = $res['ok'] ? 200 : ($res['code'] === 'rate' ? 429 : ($res['code'] === 'login' ? 401 : 422));
        $r = new \WP_REST_Response($out, $http);
        $r->header('Cache-Control', 'no-store');
        return $r;
    }

    /**
     * The single place where a bid is validated and stored (transaction + row lock), shared by the form and the AJAX route.
     *
     * @return array{ok:bool,code:string,msg:string} code: ok | invalid | login | rate | closed | seller | low | error
     */
    public static function place_bid(int $auction_id, float $amount, string $name, string $email, string $phone, int $user_id): array
    {
        $fail = static fn(string $code, string $msg): array => ['ok' => false, 'code' => $code, 'msg' => $msg];

        if (self::login_required() && $user_id <= 0) {
            return $fail('login', I18n::t('au_login_required'));
        }
        if ($user_id > 0) {
            // A signed-in bidder is identified by the account's verified e-mail, not by what was typed.
            $cu = get_userdata($user_id);
            if ($cu && !empty($cu->user_email)) {
                $email = (string) $cu->user_email;
            }
            if ($name === '' && $cu) {
                $name = (string) $cu->display_name;
            }
        }
        if (!$auction_id || $amount <= 0 || $amount > 9999999999 || $name === '' || !is_email($email)) {
            return $fail('invalid', I18n::t('reg_required'));
        }

        // Rate limit bids (per user when known, otherwise per IP)
        $rk   = 'art_coa_bid_' . md5($user_id > 0 ? 'u' . $user_id : Security::client_ip());
        $hits = (int) get_transient($rk);
        if ($hits >= 20) {
            return $fail('rate', I18n::t('sec_rate_limit'));
        }
        set_transient($rk, $hits + 1, 10 * MINUTE_IN_SECONDS);

        self::maybe_migrate();
        global $wpdb;
        if (false === $wpdb->query('START TRANSACTION')) {
            return $fail('error', I18n::t('error'));
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::listings_table() . ' WHERE id = %d FOR UPDATE', $auction_id));
        if (!$row || $row->status !== 'active' || strtotime($row->ends_at_utc . ' UTC') <= time()) {
            $wpdb->query('ROLLBACK');
            self::close_expired();
            return $fail('closed', I18n::t('auction_closed'));
        }
        [$seller_ids, $seller_emails] = self::seller_identity($row);
        if (self::bidder_is_seller($seller_ids, $seller_emails, $user_id, $email)) {
            $wpdb->query('ROLLBACK');
            return $fail('seller', I18n::t('au_seller_cannot_bid'));
        }
        $min = self::min_next_bid($row);
        $proxy = self::proxy_enabled();
        $has_leader = $row->current_bid !== null && !empty($row->current_bidder_email);
        $same_leader = $has_leader && strtolower((string) $row->current_bidder_email) === strtolower($email);
        $leader_max = ($row->leader_max ?? null) !== null ? (float) $row->leader_max : (float) ($row->current_bid ?? 0);
        if ($amount < $min) {
            $wpdb->query('ROLLBACK');
            return $fail('low', I18n::t('bid_too_low') . ' ' . Shop::format_price($min, $row->currency));
        }
        if ($proxy && $same_leader && $amount <= $leader_max) {
            $wpdb->query('ROLLBACK');
            return $fail('low', I18n::t('au_raise_max'));
        }
        $res = self::resolve_proxy($proxy, $has_leader, $same_leader, (float) ($row->current_bid ?? 0), $leader_max, (float) $row->start_price, (float) ($row->min_increment ?: 1), $amount);
        $stamp = gmdate('Y-m-d H:i:s');
        foreach ($res['rows'] as $r) {
            $mine = $r['who'] === 'new';
            $inserted = $wpdb->insert(self::bids_table(), [
                'auction_id'     => $auction_id,
                'bidder_name'    => $mine ? $name : (string) $row->current_bidder_name,
                'bidder_email'   => $mine ? $email : (string) $row->current_bidder_email,
                'bidder_phone'   => $mine ? $phone : null,
                'bidder_user_id' => $mine && $user_id > 0 ? $user_id : null,
                'amount'         => $r['amount'],
                'max_amount'     => $mine ? $amount : $leader_max,
                'is_auto'        => $r['auto'],
                'created_at_utc' => $stamp,
            ]);
            if (false === $inserted) {
                $wpdb->query('ROLLBACK');
                return $fail('error', I18n::t('error'));
            }
        }
        $new_leads = $res['winner'] === 'new';
        $updates = [
            'current_bid' => $res['visible'],
            'current_bidder_name' => $new_leads ? $name : (string) $row->current_bidder_name,
            'current_bidder_email' => $new_leads ? $email : (string) $row->current_bidder_email,
            'leader_max' => $res['leader_max'],
            'bid_count' => (int) $row->bid_count + count($res['rows']),
        ];
        $raise_only = $proxy && $same_leader;
        // Soft anti-snipe: if less than 2 minutes are left, extend to 2 minutes from now
        if (!$raise_only && strtotime($row->ends_at_utc . ' UTC') - time() < 120) {
            $updates['ends_at_utc'] = gmdate('Y-m-d H:i:s', time() + 120);
        }
        if (false === $wpdb->update(self::listings_table(), $updates, ['id' => $auction_id])) {
            $wpdb->query('ROLLBACK');
            return $fail('error', I18n::t('error'));
        }
        if (false === $wpdb->query('COMMIT')) {
            $wpdb->query('ROLLBACK');
            return $fail('error', I18n::t('error'));
        }

        $visible = $res['visible'];
        $url = home_url('/art-coa/auction/' . $auction_id);
        // Tell the bidder who has just been outbid (only when the lead really changed hands)
        if ($new_leads && $has_leader && !$same_leader && is_email((string) $row->current_bidder_email)) {
            wp_mail((string) $row->current_bidder_email, '[' . I18n::t('auction_title') . '] ' . I18n::t('au_mail_outbid_subject'),
                I18n::t('au_mail_outbid_body', ['name' => (string) $row->current_bidder_name, 'amount' => Shop::format_price($visible, (string) $row->currency), 'url' => $url]),
                ['Content-Type: text/plain; charset=UTF-8']);
        }
        // Notify seller
        if ($row->seller_user_id && !$raise_only) {
            $u = get_userdata((int) $row->seller_user_id);
            if ($u && $u->user_email) {
                wp_mail($u->user_email, '[' . I18n::t('auction_title') . '] ' . I18n::t('new_bid'),
                    "New bid: {$visible} {$row->currency}\nAuction #{$auction_id}\nBidder: {$name}", ['Content-Type: text/plain; charset=UTF-8']);
            }
        }
        Audit::log('artwork', (int) $row->artwork_id, 'auction_bid', ['auction_id' => $auction_id, 'amount' => $visible, 'auto' => $proxy ? 1 : 0]);
        do_action('art_coa_auction_bid', $auction_id, $visible, $user_id);

        if ($raise_only) {
            return ['ok' => true, 'code' => 'ok', 'msg' => I18n::t('au_max_raised')];
        }
        if (!$new_leads) {
            return ['ok' => true, 'code' => 'outbid', 'msg' => I18n::t('au_proxy_outbid')];
        }
        return ['ok' => true, 'code' => 'ok', 'msg' => I18n::t('bid_placed')];
    }

    /** Settings > "bidding requires a signed-in account" (on by default). */
    public static function login_required(): bool
    {
        $g = class_exists('\\WPArtCOA\\Admin\\Settings') ? \WPArtCOA\Admin\Settings::get() : [];
        return !array_key_exists('auction_require_login', $g) || !empty($g['auction_require_login']);
    }

    public static function min_next_bid(object $row): float
    {
        $base = $row->current_bid !== null ? (float) $row->current_bid : (float) $row->start_price;
        $inc = (float) ($row->min_increment ?: 1);
        if ($row->current_bid === null) {
            return $base; // first bid can equal start
        }
        return $base + $inc;
    }

    private static function mask_name(string $name): string
    {
        $name = trim($name);
        $length = class_exists('\\Artiey_AET_Text') ? \Artiey_AET_Text::length($name) : (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name));
        if ($length <= 2) {
            return $name;
        }
        $first = class_exists('\\Artiey_AET_Text') ? \Artiey_AET_Text::substr($name, 0, 1) : (function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1));
        return $first . str_repeat('*', min(4, $length - 1));
    }

    private static function set_flash(string $type, string $msg): void
    {
        set_transient('art_coa_auc_flash_' . self::guest_key(), ['type' => $type, 'msg' => $msg], 120);
    }

    private static function get_flash(): ?array
    {
        $k = 'art_coa_auc_flash_' . self::guest_key();
        $d = get_transient($k);
        if ($d) {
            delete_transient($k);
            return is_array($d) ? $d : null;
        }
        return null;
    }

    private static function guest_key(): string
    {
        return substr(md5($_SERVER['REMOTE_ADDR'] ?? 'x'), 0, 12);
    }
}
