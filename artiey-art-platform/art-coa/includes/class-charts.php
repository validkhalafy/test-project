<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Charts
{
    private const PUBLIC_ART = "('approved','published','active','sold')";

    /* ------------------------------------------------------------------ time windows (Jalali months) */

    /**
     * The last N Jalali months, oldest first. `to` is exclusive.
     * @return array<int,array{from:string,to:string,label:string,key:string}>
     */
    public static function window(int $months): array
    {
        $months = max(1, min(36, $months));
        $y = Calendar::current_jalali_year();
        $m = Calendar::current_jalali_month();
        $list = [];
        for ($i = 0; $i < $months; $i++) {
            array_unshift($list, [$y, $m]);
            if (--$m < 1) { $m = 12; $y--; }
        }
        $names_fa = [1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'];
        $names_en = [1=>'Farvardin',2=>'Ordibehesht',3=>'Khordad',4=>'Tir',5=>'Mordad',6=>'Shahrivar',7=>'Mehr',8=>'Aban',9=>'Azar',10=>'Dey',11=>'Bahman',12=>'Esfand'];
        $out = [];
        foreach ($list as [$jy, $jm]) {
            $ny = $jm === 12 ? $jy + 1 : $jy;
            $nm = $jm === 12 ? 1 : $jm + 1;
            $label = I18n::is_fa()
                ? $names_fa[$jm] . ' ' . Calendar::to_persian_digits((string) $jy)
                : $names_en[$jm] . ' ' . $jy;
            $out[] = [
                'from'  => Calendar::jalali_to_gregorian($jy, $jm, 1),
                'to'    => Calendar::jalali_to_gregorian($ny, $nm, 1),
                'label' => $label,
                'key'   => sprintf('%04d-%02d', $jy, $jm),
            ];
        }
        return $out;
    }

    /**
     * Run a "date, count" query (one row per day) and sum it into the window's buckets.
     * @param array<int,mixed> $params values for the %s/%d placeholders BEFORE the two window bounds
     * @return int[]
     */
    private static function bucketed(string $sql, array $params, array $window): array
    {
        global $wpdb;
        $values = array_fill(0, count($window), 0);
        if (!$window) {
            return $values;
        }
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...array_merge($params, [$window[0]['from'], $window[count($window) - 1]['to']]))) ?: [];
        foreach ($rows as $row) {
            $d = (string) $row->d;
            foreach ($window as $i => $w) {
                if ($d >= $w['from'] && $d < $w['to']) {
                    $values[$i] += (int) $row->n;
                    break;
                }
            }
        }
        return $values;
    }

    /**
     * Monthly sales (ownership transfers) for the last N Jalali months — one query instead of one per month.
     *
     * @return array{labels: string[], values: int[], by_type: array{collector: int[], buyer: int[], other: int[]}}
     */
    public static function monthly_sales(int $months = 12): array
    {
        $win = self::window($months);
        $t   = Database::table('ownership_history');
        $base = "SELECT DATE(transferred_at_utc) d, COUNT(*) n FROM {$t} WHERE transferred_at_utc >= %s AND transferred_at_utc < %s";
        $values = self::bucketed($base . ' GROUP BY d', [], $win);
        $by_type = ['collector' => [], 'buyer' => [], 'other' => []];
        $coll = self::bucketed("SELECT DATE(transferred_at_utc) d, COUNT(*) n FROM {$t} WHERE to_type = 'collector' AND transferred_at_utc >= %s AND transferred_at_utc < %s GROUP BY d", [], $win);
        $buy  = self::bucketed("SELECT DATE(transferred_at_utc) d, COUNT(*) n FROM {$t} WHERE to_type = 'buyer' AND transferred_at_utc >= %s AND transferred_at_utc < %s GROUP BY d", [], $win);
        foreach ($win as $i => $_) {
            $by_type['collector'][] = $coll[$i];
            $by_type['buyer'][]     = $buy[$i];
            $by_type['other'][]     = max(0, $values[$i] - $coll[$i] - $buy[$i]);
        }
        return ['labels' => array_column($win, 'label'), 'values' => $values, 'by_type' => $by_type];
    }

    /* ------------------------------------------------------------------ artist / gallery analytics */

    /**
     * Monthly activity of one artist: new works, exhibitions that opened, certificates issued.
     * Public mode only counts published data (same rule as the public profile counters).
     *
     * @return array{labels:string[],series:array<int,array{key:string,label:string,values:int[]}>}
     */
    public static function artist_activity(int $artist_id, int $months = 12, bool $public = true): array
    {
        return self::activity('artist', $artist_id, $months, $public);
    }

    /**
     * Monthly activity for one artist, one gallery or the whole platform (scope = artist|gallery|platform).
     * Artists: exhibitions that show their works. Galleries: exhibitions held at the gallery.
     *
     * @return array{labels:string[],series:array<int,array{key:string,label:string,values:int[]}>}
     */
    public static function activity(string $scope, int $id = 0, int $months = 12, bool $public = true): array
    {
        $win   = self::window($months);
        $a     = Database::table('artworks');
        $vis_a = $public ? ' AND a.status IN ' . self::PUBLIC_ART : '';
        $vis_e = $public ? " AND e.status IN ('published','active')" : '';
        $by_a  = '';
        $by_e  = '';
        $params = [];
        if ($scope === 'artist' && $id) {
            $by_a = ' AND a.artist_id = %d';
            $params = [$id];
        } elseif ($scope === 'gallery' && $id) {
            $by_a = ' AND a.gallery_id = %d';
            $by_e = ' AND e.gallery_id = %d';
            $params = [$id];
        }
        $works = self::bucketed(
            "SELECT DATE(a.created_at_utc) d, COUNT(*) n FROM {$a} a WHERE 1=1{$by_a}{$vis_a} AND a.created_at_utc >= %s AND a.created_at_utc < %s GROUP BY d",
            $params,
            $win
        );
        if ($scope === 'artist' && $id) {
            $ex = self::bucketed(
                'SELECT e.start_date d, COUNT(DISTINCT e.id) n FROM ' . Database::table('exhibitions') . ' e
                 INNER JOIN ' . Database::table('exhibition_artworks') . " ea ON ea.exhibition_id = e.id
                 INNER JOIN {$a} a ON a.id = ea.artwork_id
                 WHERE 1=1{$by_a}{$vis_a}{$vis_e} AND e.start_date >= %s AND e.start_date < %s GROUP BY e.start_date",
                $params,
                $win
            );
        } else {
            $ex = self::bucketed(
                'SELECT e.start_date d, COUNT(*) n FROM ' . Database::table('exhibitions') . " e
                 WHERE 1=1{$by_e}{$vis_e} AND e.start_date >= %s AND e.start_date < %s GROUP BY e.start_date",
                $params,
                $win
            );
        }
        $certs = self::bucketed(
            'SELECT DATE(c.issued_at_utc) d, COUNT(*) n FROM ' . Database::table('certificates') . " c
             INNER JOIN {$a} a ON a.id = c.artwork_id
             WHERE c.status = 'active'{$by_a}{$vis_a} AND c.issued_at_utc >= %s AND c.issued_at_utc < %s GROUP BY d",
            $params,
            $win
        );
        return [
            'labels' => array_column($win, 'label'),
            'series' => [
                ['key' => 'works', 'label' => I18n::t('ch_new_works'), 'values' => $works],
                ['key' => 'shows', 'label' => I18n::t('ch_exhibitions'), 'values' => $ex],
                ['key' => 'certs', 'label' => I18n::t('ch_certificates'), 'values' => $certs],
            ],
        ];
    }

    /**
     * Career progress: works per creation year plus the running total.
     *
     * @return array{labels:string[],values:int[],cumulative:int[],kpis:array<string,int>}
     */
    public static function artist_progress(int $artist_id, bool $public = true): array
    {
        global $wpdb;
        $a = Database::table('artworks');
        $vis = $public ? ' AND status IN ' . self::PUBLIC_ART : '';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT year_created y, COUNT(*) n FROM {$a} WHERE artist_id = %d AND year_created BETWEEN 1200 AND 2100{$vis} GROUP BY y ORDER BY y",
            $artist_id
        )) ?: [];
        $by = [];
        foreach ($rows as $r) {
            $by[(int) $r->y] = (int) $r->n;
        }
        $labels = $values = $cum = [];
        if ($by) {
            $min = max(min(array_keys($by)), max(array_keys($by)) - 39);
            $max = max(array_keys($by));
            $run = 0;
            foreach ($by as $y => $n) {
                if ($y < $min) { $run += $n; }
            }
            for ($y = $min; $y <= $max; $y++) {
                $run += $by[$y] ?? 0;
                $labels[] = I18n::is_fa() ? Calendar::to_persian_digits((string) $y) : (string) $y;
                $values[] = $by[$y] ?? 0;
                $cum[]    = $run;
            }
        }
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$a} WHERE artist_id = %d{$vis}", $artist_id));
        $certified = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(DISTINCT c.artwork_id) FROM ' . Database::table('certificates') . " c INNER JOIN {$a} a ON a.id = c.artwork_id WHERE a.artist_id = %d AND c.status = 'active'" . ($public ? ' AND a.status IN ' . self::PUBLIC_ART : ''),
            $artist_id
        ));
        $stats = Stats::artist($artist_id, $public);
        return [
            'labels' => $labels, 'values' => $values, 'cumulative' => $cum,
            'kpis' => ['works' => $total, 'certified' => $certified, 'exhibitions' => (int) $stats['exhibitions']],
        ];
    }

    /**
     * Works by status (published / sold / draft…); public mode hides drafts.
     * @return array<int,array{key:string,label:string,value:int}>
     */
    public static function status_breakdown(int $artist_id = 0, int $gallery_id = 0, bool $public = true): array
    {
        global $wpdb;
        $where = ['1=1'];
        $args  = [];
        if ($artist_id) { $where[] = 'artist_id = %d'; $args[] = $artist_id; }
        if ($gallery_id) { $where[] = 'gallery_id = %d'; $args[] = $gallery_id; }
        if ($public) { $where[] = 'status IN ' . self::PUBLIC_ART; }
        $sql = 'SELECT status, COUNT(*) n FROM ' . Database::table('artworks') . ' WHERE ' . implode(' AND ', $where) . ' GROUP BY status ORDER BY n DESC';
        $rows = $wpdb->get_results($args ? $wpdb->prepare($sql, ...$args) : $sql) ?: [];
        $labels = ['published' => 'ch_st_published', 'active' => 'ch_st_published', 'approved' => 'ch_st_published', 'sold' => 'ch_st_sold', 'draft' => 'ch_st_draft', 'pending' => 'ch_st_pending', 'rejected' => 'ch_st_rejected'];
        $merged = [];
        foreach ($rows as $r) {
            $k = $labels[$r->status] ?? 'ch_st_other';
            $merged[$k] = ($merged[$k] ?? 0) + (int) $r->n;
        }
        $out = [];
        foreach ($merged as $k => $n) {
            $out[] = ['key' => $k, 'label' => I18n::t($k), 'value' => $n];
        }
        return $out;
    }

    /**
     * Sales (ownership transfers) per Jalali month for an artist, a gallery or the whole platform, with the listed
     * value of the transferred works in the dominant currency. Private: callers must check can_view_sales().
     *
     * @return array{labels:string[],counts:int[],values:float[],currency:string,mixed_currency:bool}
     */
    public static function sales_series(int $artist_id = 0, int $gallery_id = 0, int $months = 12): array
    {
        global $wpdb;
        $win = self::window($months);
        $oh = Database::table('ownership_history');
        $a  = Database::table('artworks');
        $where = '';
        $args  = [];
        if ($artist_id) { $where .= ' AND a.artist_id = %d'; $args[] = $artist_id; }
        if ($gallery_id) { $where .= ' AND a.gallery_id = %d'; $args[] = $gallery_id; }

        $counts = self::bucketed(
            "SELECT DATE(oh.transferred_at_utc) d, COUNT(*) n FROM {$oh} oh INNER JOIN {$a} a ON a.id = oh.artwork_id WHERE oh.transferred_at_utc >= %s AND oh.transferred_at_utc < %s{$where} GROUP BY d",
            [],
            $win
        );
        // placeholders order: window bounds are appended last by bucketed(), so build the value query by hand.
        $sql = "SELECT DATE(oh.transferred_at_utc) d, COALESCE(NULLIF(a.currency, ''), 'IRR') cur, SUM(a.price) v
                FROM {$oh} oh INNER JOIN {$a} a ON a.id = oh.artwork_id
                WHERE a.price IS NOT NULL AND oh.transferred_at_utc >= %s AND oh.transferred_at_utc < %s{$where}
                GROUP BY d, cur";
        $params = array_merge([$win[0]['from'], $win[count($win) - 1]['to']], $args);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params)) ?: [];
        $per = [];
        $totals = [];
        foreach ($rows as $r) {
            $totals[$r->cur] = ($totals[$r->cur] ?? 0) + (float) $r->v;
            foreach ($win as $i => $w) {
                if ($r->d >= $w['from'] && $r->d < $w['to']) {
                    $per[$r->cur][$i] = ($per[$r->cur][$i] ?? 0) + (float) $r->v;
                    break;
                }
            }
        }
        arsort($totals);
        $currency = $totals ? (string) array_key_first($totals) : 'IRR';
        $values = [];
        foreach ($win as $i => $_) {
            $values[] = (float) ($per[$currency][$i] ?? 0);
        }
        return [
            'labels' => array_column($win, 'label'), 'counts' => $counts, 'values' => $values,
            'currency' => $currency, 'mixed_currency' => count($totals) > 1,
        ];
    }

    /**
     * How complete an artist profile is (0-100) and what is missing.
     * @return array{percent:int,done:string[],missing:string[]}
     */
    public static function profile_completeness(object $artist): array
    {
        $checks = [
            'ch_pf_avatar'    => !empty($artist->avatar_id) || !empty($artist->avatar_url),
            'ch_pf_cover'     => !empty($artist->cover_id) || !empty($artist->cover_url),
            'ch_pf_bio'       => !empty($artist->bio) || !empty($artist->short_bio),
            'ch_pf_statement' => !empty($artist->artistic_statement),
            'ch_pf_origin'    => !empty($artist->nationality) || !empty($artist->city),
            'ch_pf_birth'     => !empty($artist->birth_year),
            'ch_pf_mediums'   => !empty($artist->mediums),
            'ch_pf_education' => !empty($artist->education),
            'ch_pf_awards'    => !empty($artist->awards),
            'ch_pf_links'     => !empty($artist->website) || !empty($artist->instagram) || !empty($artist->telegram),
        ];
        $done = $missing = [];
        foreach ($checks as $k => $ok) {
            if ($ok) { $done[] = I18n::t($k); } else { $missing[] = I18n::t($k); }
        }
        return ['percent' => (int) round(count($done) / count($checks) * 100), 'done' => $done, 'missing' => $missing];
    }

    /** Sales numbers are private: admins, the artist themself and the gallery owner (or a site filter that opens them). */
    public static function can_view_sales(int $artist_id = 0, int $gallery_id = 0): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }
        $uid = get_current_user_id();
        if ($uid) {
            global $wpdb;
            if ($artist_id && (int) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM ' . Database::table('artists') . ' WHERE id = %d', $artist_id)) === $uid) {
                return true;
            }
            if ($gallery_id && (int) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM ' . Database::table('galleries') . ' WHERE id = %d', $gallery_id)) === $uid) {
                return true;
            }
        }
        return (bool) apply_filters('art_coa_public_sales_charts', false, $artist_id, $gallery_id);
    }

    /**
     * Recent transfers for dashboard table.
     */
    public static function recent_sales(int $limit = 10): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT oh.*, a.title AS artwork_title
             FROM ' . Database::table('ownership_history') . ' oh
             LEFT JOIN ' . Database::table('artworks') . ' a ON a.id = oh.artwork_id
             ORDER BY oh.transferred_at_utc DESC
             LIMIT %d',
            $limit
        )) ?: [];
    }

    /**
     * Trend summary: compare last 30 days vs previous 30 days.
     */
    public static function trend_summary(): array
    {
        global $wpdb;
        $table = Database::table('ownership_history');
        $now = gmdate('Y-m-d H:i:s');
        $d30 = gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
        $d60 = gmdate('Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS);

        $current = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE transferred_at_utc >= %s AND transferred_at_utc <= %s",
            $d30,
            $now
        ));
        $previous = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE transferred_at_utc >= %s AND transferred_at_utc < %s",
            $d60,
            $d30
        ));

        $change = 0.0;
        if ($previous > 0) {
            $change = round((($current - $previous) / $previous) * 100, 1);
        } elseif ($current > 0) {
            $change = 100.0;
        }

        return [
            'current_30'  => $current,
            'previous_30' => $previous,
            'change_pct'  => $change,
            'direction'   => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
        ];
    }
}
