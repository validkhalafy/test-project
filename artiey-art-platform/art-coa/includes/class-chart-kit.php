<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Public charts for artists, galleries and the platform: progress, activity and sales.
 *
 * Everything is rendered on the server as inline SVG (no chart library, no external request, crawlable), with an
 * accessible data table behind every chart. Colours come from CSS variables (--ac-c1 … --ac-c6), so themes and
 * Elementor widgets can restyle them. Sales figures are private: they are only drawn for site admins, the artist /
 * gallery owner, or when the site opts in through the `art_coa_public_sales_charts` filter.
 */
final class ChartKit
{
    public const TYPES = [
        'activity', 'progress', 'growth', 'status', 'completeness', 'sales', 'sales_value',
    ];

    private static bool $assets = false;
    private static int $uid = 0;

    /* ------------------------------------------------------------------ assets */

    public static function assets(): void
    {
        if (self::$assets) {
            return;
        }
        self::$assets = true;
        wp_enqueue_style('wp-art-coa-front');
        wp_enqueue_style('wp-art-coa-dynamic');
        wp_enqueue_script('wp-art-coa-charts', WP_ART_COA_URL . 'assets/js/charts.js', [], defined('AET_VERSION') ? (string) AET_VERSION : WP_ART_COA_VERSION, true);
    }

    /* ------------------------------------------------------------------ number formatting */

    private static function n($v): string
    {
        $s = is_float($v) && floor($v) !== $v ? number_format($v, 1, '.', ',') : number_format((float) $v, 0, '.', ',');
        return I18n::is_fa() ? Calendar::to_persian_digits(str_replace(',', '٬', $s)) : $s;
    }

    /** 1 200 000 -> "1.2 میلیون" (fa) / "1.2M" (en). */
    private static function compact(float $v): string
    {
        $abs = abs($v);
        $units = I18n::is_fa()
            ? [[1e12, ' همت'], [1e9, ' میلیارد'], [1e6, ' میلیون'], [1e3, ' هزار']]
            : [[1e12, 'T'], [1e9, 'B'], [1e6, 'M'], [1e3, 'K']];
        foreach ($units as [$size, $suffix]) {
            if ($abs >= $size) {
                $x = round($v / $size, 1);
                $txt = ($x == (int) $x ? (string) (int) $x : (string) $x);
                return (I18n::is_fa() ? Calendar::to_persian_digits($txt) : $txt) . $suffix;
            }
        }
        return self::n($v);
    }

    private static function money(float $v, string $currency): string
    {
        $names = I18n::is_fa()
            ? ['IRR' => 'ریال', 'IRT' => 'تومان', 'USD' => 'دلار', 'EUR' => 'یورو']
            : ['IRR' => 'IRR', 'IRT' => 'IRT', 'USD' => 'USD', 'EUR' => 'EUR'];
        return self::n($v) . ' ' . ($names[$currency] ?? $currency);
    }

    /** Round the axis maximum up to a "nice" value and return [max, step]. */
    private static function scale(float $max, int $ticks = 4): array
    {
        if ($max <= 0) {
            return [$ticks, 1.0];
        }
        $raw  = $max / $ticks;
        $pow  = 10 ** floor(log10($raw));
        $frac = $raw / $pow;
        $step = ($frac <= 1 ? 1 : ($frac <= 2 ? 2 : ($frac <= 5 ? 5 : 10))) * $pow;
        return [$step * $ticks, (float) $step];
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /* ------------------------------------------------------------------ frame, legend, data table */

    /**
     * @param array<int,array{label:string,values:array<int,float|int>,fmt?:string}> $series
     */
    private static function frame(string $title, string $summary, string $svg, array $legend, string $table, array $o): string
    {
        self::assets();
        $id = 'ackc' . (++self::$uid);
        $h  = '<figure class="art-coa-chart' . (!empty($o['class']) ? ' ' . self::esc((string) $o['class']) : '') . '" role="group" aria-labelledby="' . $id . '-t">';
        if ($title !== '' && ($o['title'] ?? true)) {
            $h .= '<figcaption class="art-coa-chart-title" id="' . $id . '-t">' . self::esc($title) . '</figcaption>';
        } else {
            $h .= '<span class="screen-reader-text aap-sr" id="' . $id . '-t">' . self::esc($title) . '</span>';
        }
        $h .= '<div class="art-coa-chart-plot" data-art-chart>' . str_replace('{{ID}}', $id, $svg) . '<div class="art-coa-chart-tip" role="status" aria-live="polite" hidden></div></div>';
        if ($legend && ($o['legend'] ?? true)) {
            $h .= '<ul class="art-coa-chart-legend">';
            foreach ($legend as $i => $l) {
                $h .= '<li><span class="art-coa-chart-key ackf' . (($i % 6) + 1) . '" aria-hidden="true"></span>' . self::esc($l) . '</li>';
            }
            $h .= '</ul>';
        }
        $h .= '<p class="art-coa-chart-summary">' . self::esc($summary) . '</p>';
        if ($table !== '' && ($o['table'] ?? true)) {
            $h .= '<details class="art-coa-chart-data"><summary>' . self::esc(I18n::t('ch_data')) . '</summary>' . $table . '</details>';
        }
        return $h . '</figure>';
    }

    /** @param array<int,array{label:string,values:array<int,float|int>}> $series */
    private static function table(string $caption, array $labels, array $series, ?callable $fmt = null): string
    {
        $fmt = $fmt ?? static fn($v) => self::n($v);
        $h = '<div class="art-coa-chart-table-wrap"><table><caption class="screen-reader-text aap-sr">' . self::esc($caption) . '</caption><thead><tr><th scope="col"></th>';
        foreach ($series as $s) {
            $h .= '<th scope="col">' . self::esc($s['label']) . '</th>';
        }
        $h .= '</tr></thead><tbody>';
        foreach ($labels as $i => $l) {
            $h .= '<tr><th scope="row">' . self::esc((string) $l) . '</th>';
            foreach ($series as $s) {
                $h .= '<td>' . self::esc($fmt($s['values'][$i] ?? 0)) . '</td>';
            }
            $h .= '</tr>';
        }
        return $h . '</tbody></table></div>';
    }

    private static function empty_chart(string $title, array $o): string
    {
        self::assets();
        $msg = (string) ($o['empty_text'] ?? '') !== '' ? (string) $o['empty_text'] : I18n::t('ch_no_data');
        return '<figure class="art-coa-chart art-coa-chart--empty"><figcaption class="art-coa-chart-title">' . self::esc($title) . '</figcaption><p class="art-coa-empty">' . self::esc($msg) . '</p></figure>';
    }

    /* ------------------------------------------------------------------ SVG primitives */

    /**
     * Grouped / stacked bars.
     * @param string[] $labels
     * @param array<int,array{label:string,values:array<int,float|int>}> $series
     */
    public static function bars(string $title, array $labels, array $series, array $o = []): string
    {
        $total = 0;
        foreach ($series as $s) {
            $total += array_sum($s['values']);
        }
        if (!$labels || !$series || $total <= 0) {
            return self::empty_chart($title, $o);
        }
        $fmt     = $o['fmt'] ?? null;
        $stacked = !empty($o['stacked']);
        $W = 640; $H = (int) ($o['height'] ?? 280);
        $L = 46; $R = 12; $T = 14; $B = 58;
        $pw = $W - $L - $R; $ph = $H - $T - $B;
        $count = count($labels);
        $max = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $sum = 0.0; $peak = 0.0;
            foreach ($series as $s) {
                $v = (float) ($s['values'][$i] ?? 0);
                $sum += $v; $peak = max($peak, $v);
            }
            $max = max($max, $stacked ? $sum : $peak);
        }
        [$top, $step] = self::scale($max);
        $svg = '<svg class="art-coa-chart-svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-labelledby="{{ID}}-t" focusable="false" preserveAspectRatio="xMidYMid meet">';
        for ($g = 0; $g <= 4; $g++) {
            $val = $step * $g;
            $y = $T + $ph - ($top > 0 ? $val / $top * $ph : 0);
            $svg .= '<line class="ack-grid" x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
            $svg .= '<text class="ack-axis" x="' . ($L - 6) . '" y="' . round($y + 3.5, 1) . '" text-anchor="end">' . self::esc($fmt ? self::compact($val) : self::n($val)) . '</text>';
        }
        $slot = $pw / $count;
        $every = $count > 24 ? 3 : ($count > 14 ? 2 : 1);
        $ns = count($series);
        $barw = $stacked ? min(34, $slot * 0.62) : min(26, ($slot * 0.74) / $ns);
        for ($i = 0; $i < $count; $i++) {
            $cx = $L + $slot * $i + $slot / 2;
            $stack_y = $T + $ph;
            $tip = [];
            foreach ($series as $k => $s) {
                $v = (float) ($s['values'][$i] ?? 0);
                $tip[] = $s['label'] . ': ' . ($fmt ? self::money($v, (string) ($o['currency'] ?? 'IRR')) : self::n($v));
                if ($v <= 0) { continue; }
                $h = $top > 0 ? $v / $top * $ph : 0;
                if ($stacked) {
                    $x = $cx - $barw / 2;
                    $stack_y -= $h;
                    $y = $stack_y;
                } else {
                    $x = $cx - ($ns * $barw) / 2 + $k * $barw;
                    $y = $T + $ph - $h;
                }
                $svg .= '<rect class="ackf' . (($k % 6) + 1) . '" x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round(max(2, $barw - 2), 1) . '" height="' . round(max(1, $h), 1) . '" rx="3"/>';
            }
            // One transparent hit area per slot: tooltip for hover, focus and touch.
            $svg .= '<rect class="ack-hit" tabindex="0" x="' . round($L + $slot * $i, 1) . '" y="' . $T . '" width="' . round($slot, 1) . '" height="' . $ph . '" data-tip="' . self::esc((string) $labels[$i] . ' — ' . implode('، ', $tip)) . '"><title>' . self::esc((string) $labels[$i] . ' — ' . implode('، ', $tip)) . '</title></rect>';
            if ($i % $every === 0) {
                $lx = round($cx, 1); $ly = $T + $ph + 14;
                $svg .= '<text class="ack-axis ack-xl" x="' . $lx . '" y="' . $ly . '" text-anchor="end" transform="rotate(-38 ' . $lx . ' ' . $ly . ')">' . self::esc((string) $labels[$i]) . '</text>';
            }
        }
        $svg .= '</svg>';
        $summary = $title . ': ' . implode('، ', array_map(static fn($s) => $s['label'] . ' ' . ($fmt ? self::money((float) array_sum($s['values']), (string) ($o['currency'] ?? 'IRR')) : self::n(array_sum($s['values']))), $series));
        $table = self::table($title, $labels, $series, $fmt ? static fn($v) => self::money((float) $v, (string) ($o['currency'] ?? 'IRR')) : null);
        return self::frame($title, $summary, $svg, count($series) > 1 || !empty($o['legend_always']) ? array_column($series, 'label') : [], $table, $o);
    }

    /**
     * Line (optionally area) chart.
     * @param string[] $labels
     * @param array<int,array{label:string,values:array<int,float|int>}> $series
     */
    public static function line(string $title, array $labels, array $series, array $o = []): string
    {
        $total = 0;
        foreach ($series as $s) {
            $total += array_sum($s['values']);
        }
        if (count($labels) < 2 || !$series || $total <= 0) {
            return self::empty_chart($title, $o);
        }
        $fmt = !empty($o['money']);
        $W = 640; $H = (int) ($o['height'] ?? 280);
        $L = 52; $R = 14; $T = 14; $B = 58;
        $pw = $W - $L - $R; $ph = $H - $T - $B;
        $count = count($labels);
        $max = 0.0;
        foreach ($series as $s) {
            $max = max($max, (float) max($s['values']));
        }
        [$top, $step] = self::scale($max);
        $svg = '<svg class="art-coa-chart-svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-labelledby="{{ID}}-t" focusable="false" preserveAspectRatio="xMidYMid meet">';
        for ($g = 0; $g <= 4; $g++) {
            $val = $step * $g;
            $y = $T + $ph - ($top > 0 ? $val / $top * $ph : 0);
            $svg .= '<line class="ack-grid" x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
            $svg .= '<text class="ack-axis" x="' . ($L - 6) . '" y="' . round($y + 3.5, 1) . '" text-anchor="end">' . self::esc($fmt ? self::compact($val) : self::n($val)) . '</text>';
        }
        $dx = $pw / ($count - 1);
        $every = $count > 24 ? 3 : ($count > 14 ? 2 : 1);
        $xy = static fn(int $i, float $v): array => [$L + $dx * $i, $T + $ph - ($top > 0 ? $v / $top * $ph : 0)];
        foreach ($series as $k => $s) {
            $pts = [];
            foreach ($labels as $i => $_) {
                $pts[] = $xy($i, (float) ($s['values'][$i] ?? 0));
            }
            $d = '';
            foreach ($pts as $i => [$x, $y]) {
                $d .= ($i ? 'L' : 'M') . round($x, 1) . ' ' . round($y, 1);
            }
            if (!empty($o['area']) && $k === 0) {
                $svg .= '<path class="ackf' . (($k % 6) + 1) . ' ack-area" d="' . $d . 'L' . round($pts[$count - 1][0], 1) . ' ' . ($T + $ph) . 'L' . round($pts[0][0], 1) . ' ' . ($T + $ph) . 'Z"/>';
            }
            $svg .= '<path class="ack-line ackstroke' . (($k % 6) + 1) . '" d="' . $d . '" fill="none"/>';
            foreach ($pts as $i => [$x, $y]) {
                $svg .= '<circle class="ack-dot ackf' . (($k % 6) + 1) . '" cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="3.4"/>';
            }
        }
        foreach ($labels as $i => $label) {
            $tip = [];
            foreach ($series as $s) {
                $v = (float) ($s['values'][$i] ?? 0);
                $tip[] = $s['label'] . ': ' . ($fmt ? self::money($v, (string) ($o['currency'] ?? 'IRR')) : self::n($v));
            }
            $cx = $L + $dx * $i;
            $svg .= '<rect class="ack-hit" tabindex="0" x="' . round($cx - $dx / 2, 1) . '" y="' . $T . '" width="' . round($dx, 1) . '" height="' . $ph . '" data-tip="' . self::esc((string) $label . ' — ' . implode('، ', $tip)) . '"><title>' . self::esc((string) $label . ' — ' . implode('، ', $tip)) . '</title></rect>';
            if ($i % $every === 0) {
                $ly = $T + $ph + 14;
                $svg .= '<text class="ack-axis ack-xl" x="' . round($cx, 1) . '" y="' . $ly . '" text-anchor="end" transform="rotate(-38 ' . round($cx, 1) . ' ' . $ly . ')">' . self::esc((string) $label) . '</text>';
            }
        }
        $svg .= '</svg>';
        $last = end($series);
        $summary = $title . ': ' . ($fmt ? self::money((float) end($last['values']), (string) ($o['currency'] ?? 'IRR')) : self::n(end($last['values'])));
        $table = self::table($title, $labels, $series, $fmt ? static fn($v) => self::money((float) $v, (string) ($o['currency'] ?? 'IRR')) : null);
        return self::frame($title, $summary, $svg, count($series) > 1 ? array_column($series, 'label') : [], $table, $o);
    }

    /**
     * Donut chart.
     * @param array<int,array{label:string,value:int}> $items
     */
    public static function donut(string $title, array $items, array $o = []): string
    {
        $items = array_values(array_filter($items, static fn($i) => $i['value'] > 0));
        $sum = array_sum(array_column($items, 'value'));
        if (!$items || $sum <= 0) {
            return self::empty_chart($title, $o);
        }
        $r = 70.0; $c = 2 * M_PI * $r;
        $svg = '<svg class="art-coa-chart-svg art-coa-chart-svg--donut" viewBox="0 0 220 220" role="img" aria-labelledby="{{ID}}-t" focusable="false">';
        $offset = 0.0;
        foreach ($items as $i => $it) {
            $len = $it['value'] / $sum * $c;
            $tip = $it['label'] . ': ' . self::n($it['value']) . ' (' . self::n(round($it['value'] / $sum * 100)) . '٪)';
            $svg .= '<circle class="ackstroke' . (($i % 6) + 1) . ' ack-slice" tabindex="0" cx="110" cy="110" r="' . $r . '" fill="none" stroke-width="30" stroke-dasharray="' . round(max(0, $len - 1.5), 2) . ' ' . round($c, 2) . '" stroke-dashoffset="' . round(-$offset, 2) . '" transform="rotate(-90 110 110)" data-tip="' . self::esc($tip) . '"><title>' . self::esc($tip) . '</title></circle>';
            $offset += $len;
        }
        $svg .= '<text class="ack-center" x="110" y="108" text-anchor="middle">' . self::esc(self::n($sum)) . '</text><text class="ack-axis" x="110" y="128" text-anchor="middle">' . self::esc(I18n::t('ch_total')) . '</text></svg>';
        $series = [['label' => $title, 'values' => array_column($items, 'value')]];
        $table = self::table($title, array_column($items, 'label'), $series);
        $summary = $title . ': ' . implode('، ', array_map(static fn($i) => $i['label'] . ' ' . self::n($i['value']), $items));
        return self::frame($title, $summary, $svg, array_column($items, 'label'), $table, $o);
    }

    /** Percent ring + checklist (profile completeness). */
    public static function ring(string $title, int $percent, array $done, array $missing, array $o = []): string
    {
        $percent = max(0, min(100, $percent));
        $r = 54.0; $c = 2 * M_PI * $r;
        $svg = '<svg class="art-coa-chart-svg art-coa-chart-svg--ring" viewBox="0 0 140 140" role="img" aria-labelledby="{{ID}}-t" focusable="false">'
            . '<circle class="ack-track" cx="70" cy="70" r="' . $r . '" fill="none" stroke-width="14"/>'
            . '<circle class="ackstroke1" cx="70" cy="70" r="' . $r . '" fill="none" stroke-width="14" stroke-linecap="round" stroke-dasharray="' . round($percent / 100 * $c, 2) . ' ' . round($c, 2) . '" transform="rotate(-90 70 70)"/>'
            . '<text class="ack-center" x="70" y="78" text-anchor="middle">' . self::esc(self::n($percent) . '٪') . '</text></svg>';
        $list = '';
        if ($missing) {
            $list = '<p class="art-coa-chart-missing"><strong>' . self::esc(I18n::t('ch_missing')) . ':</strong> ' . self::esc(implode('، ', $missing)) . '</p>';
        }
        $o['legend'] = false;
        $o['table']  = false;
        return str_replace('</figure>', $list . '</figure>', self::frame($title, $title . ': ' . self::n($percent) . '٪', $svg, [], '', $o));
    }

    /** @param array<string,string|int> $kpis label => value */
    public static function kpis(array $kpis, array $o = []): string
    {
        self::assets();
        $h = '<div class="art-coa-chart-kpis">';
        foreach ($kpis as $label => $value) {
            $h .= '<div class="art-coa-chart-kpi"><strong>' . self::esc(is_int($value) ? self::n($value) : (string) $value) . '</strong><span>' . self::esc((string) $label) . '</span></div>';
        }
        return $h . '</div>';
    }

    /* ------------------------------------------------------------------ data access (cached) */

    private static function cached(string $type, array $key, callable $cb)
    {
        $k = 'ck_' . $type . '_' . Cache::stats_version() . '_' . md5(wp_json_encode($key) . '|' . (I18n::is_fa() ? 'fa' : 'en'));
        return Cache::get($k, $cb, 300);
    }

    /** Artist or gallery row from an id, a slug, or (empty) the page being viewed. */
    public static function resolve(string $entity, $ref): ?object
    {
        global $wpdb;
        $table = Database::table($entity === 'gallery' ? 'galleries' : 'artists');
        $row = null;
        $ref = is_scalar($ref) ? trim((string) $ref) : '';
        if ($ref !== '' && ctype_digit($ref)) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $ref));
        } elseif ($ref !== '') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", sanitize_title($ref)));
        } else {
            $slug = (string) get_query_var('art_coa_slug');
            if ($slug !== '' && get_query_var('art_coa_type') === $entity) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", sanitize_title($slug)));
            } elseif (is_singular($entity === 'gallery' ? 'artiey_gallery' : 'artiey_artist')) {
                $post_id = (int) get_queried_object_id();
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE cpt_post_id = %d", $post_id));
                if (!$row && class_exists('\WPArtCOA\ContentBridge')) {
                    $table_key = $entity === 'gallery' ? 'galleries' : 'artists';
                    $row_id = \WPArtCOA\ContentBridge::row_id_for_post($table_key, $post_id);
                    if ($row_id > 0) {
                        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $row_id));
                    }
                }
            }
        }
        if (!$row) {
            return null;
        }
        // Inactive profiles are not public.
        if ($row->status !== 'active' && !current_user_can('manage_options')) {
            return null;
        }
        return $row;
    }

    /** Site admins and the user linked to the artist profile. */
    private static function is_owner(object $artist): bool
    {
        return current_user_can('manage_options') || (is_user_logged_in() && (int) ($artist->user_id ?? 0) === get_current_user_id());
    }

    /* ------------------------------------------------------------------ one chart by type */

    /**
     * @param array<string,mixed> $o type, artist, gallery, months, title(bool), table(bool), legend(bool), height, empty_text, heading
     */
    public static function render(array $o): string
    {
        $type   = in_array($o['type'] ?? '', self::TYPES, true) ? (string) $o['type'] : 'activity';
        $months = max(3, min(36, (int) ($o['months'] ?? 12)));
        $public = !current_user_can('manage_options');
        $artist = !empty($o['artist']) ? self::resolve('artist', $o['artist']) : null;
        $gallery = !$artist && !empty($o['gallery']) ? self::resolve('gallery', $o['gallery']) : null;
        $explicit = !empty($o['artist']) || !empty($o['gallery']);
        if ($explicit && !$artist && !$gallery) {
            return ''; // asked for a specific profile that does not exist or is not public
        }
        if (!$explicit && empty($o['platform'])) {
            // No explicit subject: use the profile page that is being viewed, else the whole platform.
            $artist  = self::resolve('artist', '');
            $gallery = $artist ? null : self::resolve('gallery', '');
        }
        $aid = $artist ? (int) $artist->id : 0;
        $gid = $gallery ? (int) $gallery->id : 0;
        $scope = $aid ? 'artist' : ($gid ? 'gallery' : 'platform');
        $sid = $aid ?: $gid;
        $heading = (string) ($o['heading'] ?? '');
        $o['height'] = (int) ($o['height'] ?? 280) ?: 280;

        switch ($type) {
            case 'progress':
                if (!$aid) { return ''; }
                $d = self::cached('progress', [$aid, $public], static fn() => Charts::artist_progress($aid, $public));
                return self::bars($heading ?: I18n::t('ch_progress_title'), $d['labels'], [['label' => I18n::t('ch_new_works'), 'values' => $d['values']]], $o);
            case 'growth':
                if (!$aid) { return ''; }
                $d = self::cached('progress', [$aid, $public], static fn() => Charts::artist_progress($aid, $public));
                return self::line($heading ?: I18n::t('ch_growth_title'), $d['labels'], [['label' => I18n::t('ch_total_works'), 'values' => $d['cumulative']]], $o + ['area' => true]);
            case 'status':
                $d = self::cached('status', [$aid, $gid, $public], static fn() => Charts::status_breakdown($aid, $gid, $public));
                return self::donut($heading ?: I18n::t('ch_status_title'), $d, $o);
            case 'completeness':
                // Private: a nudge for the owner, not public information.
                if (!$artist || !self::is_owner($artist)) { return ''; }
                $d = Charts::profile_completeness($artist);
                return self::ring($heading ?: I18n::t('ch_completeness_title'), $d['percent'], $d['done'], $d['missing'], $o);
            case 'sales':
            case 'sales_value':
                if (!Charts::can_view_sales($aid, $gid)) { return ''; }
                $d = self::cached('sales', [$aid, $gid, $months], static fn() => Charts::sales_series($aid, $gid, $months));
                if ($type === 'sales') {
                    return self::bars($heading ?: I18n::t('ch_sales_title'), $d['labels'], [['label' => I18n::t('ch_sales_count'), 'values' => $d['counts']]], $o);
                }
                $title = $heading ?: I18n::t('ch_sales_value_title');
                $html = self::line($title, $d['labels'], [['label' => I18n::t('ch_sales_value'), 'values' => $d['values']]], $o + ['money' => true, 'currency' => $d['currency'], 'area' => true]);
                if ($d['mixed_currency']) {
                    $html .= '<p class="art-coa-chart-note">' . self::esc(I18n::t('ch_mixed_currency')) . '</p>';
                }
                return $html;
            case 'activity':
            default:
                $d = self::cached('activity', [$scope, $sid, $months, $public], static fn() => Charts::activity($scope, $sid, $months, $public));
                return self::bars($heading ?: I18n::t('ch_activity_title'), $d['labels'], $d['series'], $o);
        }
    }

    /* ------------------------------------------------------------------ ready-made panels */

    /** Progress + activity (+ sales for the owner) block for an artist profile. */
    public static function artist_panel(object $artist, array $o = []): string
    {
        $aid    = (int) $artist->id;
        $public = !current_user_can('manage_options');
        $prog   = self::cached('progress', [$aid, $public], static fn() => Charts::artist_progress($aid, $public));
        $base   = ['artist' => (string) $aid, 'months' => (int) ($o['months'] ?? 12)] + $o;
        $out = '';
        $k = $prog['kpis'];
        if ($k['works'] > 0) {
            $out .= self::kpis([
                I18n::t('ch_works_kpi')       => (int) $k['works'],
                I18n::t('ch_certified_kpi')   => (int) $k['certified'],
                I18n::t('ch_exhibitions_kpi') => (int) $k['exhibitions'],
            ]);
        }
        $grid = '';
        foreach (['activity', 'progress', 'growth', 'status', 'completeness', 'sales', 'sales_value'] as $t) {
            $grid .= self::render(['type' => $t] + $base);
        }
        if (strpos($grid, 'art-coa-chart--empty') !== false && strpos(str_replace('art-coa-chart--empty', '', $grid), '<svg') === false) {
            return $out . '';
        }
        return $out . '<div class="art-coa-charts-grid art-coa-charts-grid--public">' . $grid . '</div>';
    }

    public static function gallery_panel(object $gallery, array $o = []): string
    {
        $base = ['gallery' => (string) (int) $gallery->id, 'months' => (int) ($o['months'] ?? 12)] + $o;
        $grid = '';
        foreach (['activity', 'status', 'sales', 'sales_value'] as $t) {
            $grid .= self::render(['type' => $t] + $base);
        }
        if (strpos(str_replace('art-coa-chart--empty', '', $grid), '<svg') === false) {
            return '';
        }
        return '<div class="art-coa-charts-grid art-coa-charts-grid--public">' . $grid . '</div>';
    }

    /** [art_coa_charts] shortcode. */
    public static function shortcode($atts = []): string
    {
        $a = shortcode_atts([
            'type' => 'activity', 'artist' => '', 'gallery' => '', 'months' => '12', 'title' => 'yes', 'table' => 'yes',
            'legend' => 'yes', 'height' => '280', 'heading' => '', 'empty' => '', 'platform' => '', 'class' => '',
        ], (array) $atts, 'art_coa_charts');
        return self::render([
            'type' => sanitize_key((string) $a['type']), 'artist' => sanitize_text_field((string) $a['artist']),
            'gallery' => sanitize_text_field((string) $a['gallery']), 'months' => (int) $a['months'],
            'title' => $a['title'] !== 'no', 'table' => $a['table'] !== 'no', 'legend' => $a['legend'] !== 'no',
            'height' => max(160, min(600, (int) $a['height'])), 'heading' => sanitize_text_field((string) $a['heading']),
            'empty_text' => sanitize_text_field((string) $a['empty']), 'platform' => $a['platform'] === 'yes',
            'class' => sanitize_html_class((string) $a['class']),
        ]);
    }
}
