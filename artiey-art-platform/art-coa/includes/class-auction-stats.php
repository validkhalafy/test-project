<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class AuctionStats
{
    public static function overview(): array
    {
        return Cache::get('auction_overview', static function (): array {
            global $wpdb;
            $a = Auction::listings_table();
            $b = Auction::bids_table();
            $oh = Database::table('ownership_history');

            return [
                'auctions_total'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$a}"),
                'auctions_active'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$a} WHERE status = 'active'"),
                'auctions_sold'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$a} WHERE status = 'sold'"),
                'bids_total'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$b}"),
                'volume_sold'      => (float) $wpdb->get_var("SELECT COALESCE(SUM(current_bid),0) FROM {$a} WHERE status = 'sold'"),
                'volume_active'    => (float) $wpdb->get_var("SELECT COALESCE(SUM(COALESCE(current_bid, start_price)),0) FROM {$a} WHERE status = 'active'"),
                'transfers_total'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$oh}"),
                'transfers_month'  => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$oh} WHERE transferred_at_utc >= %s",
                    gmdate('Y-m-01 00:00:00')
                )),
            ];
        }, 120);
    }

    /**
     * Monthly auction sales volume (sold auctions by end month).
     * @return array{labels: string[], volume: float[], count: int[], bids: int[]}
     */
    public static function monthly_dynamics(int $months = 12): array
    {
        return Cache::get('auction_monthly_' . $months, static function () use ($months): array {
            global $wpdb;
            $a = Auction::listings_table();
            $b = Auction::bids_table();

            $labels = [];
            $volume = [];
            $count = [];
            $bids = [];

            $start = new \DateTime('first day of this month', new \DateTimeZone('UTC'));
            $start->modify('-' . ($months - 1) . ' months');

            for ($i = 0; $i < $months; $i++) {
                $from = $start->format('Y-m-01 00:00:00');
                $to = $start->format('Y-m-t 23:59:59');

                $j = Calendar::gregorian_to_jalali(
                    (int) $start->format('Y'),
                    (int) $start->format('n'),
                    1
                );
                $month_fa = [
                    1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',
                    7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند',
                ];
                $month_en = [
                    1=>'Farvardin',2=>'Ordibehesht',3=>'Khordad',4=>'Tir',5=>'Mordad',6=>'Shahrivar',
                    7=>'Mehr',8=>'Aban',9=>'Azar',10=>'Dey',11=>'Bahman',12=>'Esfand',
                ];
                if (I18n::is_fa()) {
                    $labels[] = ($month_fa[$j[1]] ?? '') . ' ' . Calendar::to_persian_digits((string) $j[0]);
                } else {
                    $labels[] = ($month_en[$j[1]] ?? '') . ' ' . $j[0];
                }

                $volume[] = (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(current_bid),0) FROM {$a}
                     WHERE status = 'sold' AND ends_at_utc BETWEEN %s AND %s",
                    $from,
                    $to
                ));
                $count[] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$a} WHERE status = 'sold' AND ends_at_utc BETWEEN %s AND %s",
                    $from,
                    $to
                ));
                $bids[] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$b} WHERE created_at_utc BETWEEN %s AND %s",
                    $from,
                    $to
                ));

                $start->modify('+1 month');
            }

            return compact('labels', 'volume', 'count', 'bids');
        }, 180);
    }

    /** Bid activity last 14 days for line chart */
    public static function daily_bids(int $days = 14): array
    {
        return Cache::get('auction_daily_bids_' . $days, static function () use ($days): array {
            global $wpdb;
            $b = Auction::bids_table();
            $labels = [];
            $values = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $day = gmdate('Y-m-d', time() - $i * DAY_IN_SECONDS);
                $from = $day . ' 00:00:00';
                $to = $day . ' 23:59:59';
                $labels[] = I18n::is_fa()
                    ? Calendar::to_persian_digits(gmdate('m/d', strtotime($day . ' UTC')))
                    : gmdate('m/d', strtotime($day . ' UTC'));
                $values[] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$b} WHERE created_at_utc BETWEEN %s AND %s",
                    $from,
                    $to
                ));
            }
            return compact('labels', 'values');
        }, 120);
    }
}
