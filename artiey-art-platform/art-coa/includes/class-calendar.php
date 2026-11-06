<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Exhibition;

defined('ABSPATH') || exit;

/**
 * Weekly gallery exhibition calendar (inspired by Iranian art calendar UIs).
 */
final class Calendar
{
    /** Persian weekday labels (Sat → Fri) matching common IR gallery calendars */
    private const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    private const MONTHS_FA = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    /**
     * Render weekly calendar for a given Jalali month/year (default: current).
     *
     * @param array{year?:int,month?:int,week_start?:string} $args
     */
    public static function render(array $args = []): string
    {
        $j_year  = isset($args['year']) ? (int) $args['year'] : self::current_jalali_year();
        $j_month = isset($args['month']) ? (int) $args['month'] : self::current_jalali_month();
        $j_month = max(1, min(12, $j_month));

        // Convert first day of Jalali month to Gregorian for querying
        $g_start = self::jalali_to_gregorian($j_year, $j_month, 1);
        $days_in_month = self::jalali_days_in_month($j_year, $j_month);
        $g_end = self::jalali_to_gregorian($j_year, $j_month, $days_in_month);

        $events = Exhibition::in_range($g_start, $g_end, null);

        // Build day columns: for simplicity show one week centered on today or week 1
        // Full month as 7-column header (like screenshot)
        $header_days = [];
        for ($d = 1; $d <= min(7, $days_in_month); $d++) {
            $g = self::jalali_to_gregorian($j_year, $j_month, $d);
            $w = (int) date('w', strtotime($g)); // 0=Sun ... 6=Sat
            // Convert to Sat-first index: Sat=0
            $idx = ($w + 1) % 7;
            $header_days[] = [
                'day'       => $d,
                'weekday'   => self::WEEKDAYS[$idx] ?? '',
                'month_fa'  => self::MONTHS_FA[$j_month],
                'gregorian' => $g,
                'is_today'  => $g === gmdate('Y-m-d'),
            ];
        }

        // If month has more days, still use first 7 for the compact week strip like the screenshot
        // Map events to days they cover
        $bars = [];
        foreach ($events as $ev) {
            $start = $ev->start_date;
            $end   = $ev->end_date ?: $ev->start_date;
            $bars[] = [
                'title'        => $ev->title,
                'subtitle'     => $ev->subtitle ?: self::build_subtitle($ev),
                'gallery'      => $ev->gallery_name ?? '',
                'logo'         => $ev->gallery_logo ?? '',
                'slug'         => $ev->slug ?? '',
                'poster'       => Media::url($ev, 'poster', 'thumbnail'),
                'start'        => $start,
                'end'          => $end,
                'start_j_day'  => self::day_in_jalali_month($start, $j_year, $j_month),
                'end_j_day'    => self::day_in_jalali_month($end, $j_year, $j_month),
            ];
        }

        ob_start();
        ?>
        <div class="art-coa-calendar" dir="rtl">
            <div class="art-coa-cal-header">
                <?php foreach (array_reverse($header_days) as $i => $hd): ?>
                    <div class="art-coa-cal-day <?php echo $hd['is_today'] ? 'is-today' : ''; ?>">
                        <span class="wd"><?php echo esc_html($hd['weekday']); ?></span>
                        <span class="num"><?php echo esc_html(self::to_persian_digits((string) $hd['day'])); ?></span>
                        <span class="mo"><?php echo esc_html($hd['month_fa']); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="art-coa-cal-label">گالری</div>
            </div>
            <div class="art-coa-cal-body">
                <?php if (!$bars): ?>
                    <p class="art-coa-empty">نمایشگاهی در این بازه ثبت نشده است.</p>
                <?php else: ?>
                    <?php foreach ($bars as $bar): ?>
                        <?php
                        $start_col = max(1, (int) $bar['start_j_day']);
                        $end_col   = max($start_col, (int) $bar['end_j_day']);
                        // Clamp to 1–7 for week strip
                        $start_col = min(7, $start_col);
                        $end_col   = min(7, $end_col);
                        $span = $end_col - $start_col + 1;
                        // CSS grid: columns 1=gallery label side is last in RTL visual; we use LTR grid with reverse header
                        $grid_start = 8 - $end_col;
                        ?>
                        <div class="art-coa-cal-row">
                            <div class="art-coa-cal-bar"
                                 style="grid-column: <?php echo (int) $grid_start; ?> / span <?php echo (int) $span; ?>">
                                <div class="bar-inner">
                                    <?php if ($bar['poster']): ?><img class="bar-poster" src="<?php echo esc_url($bar['poster']); ?>" alt="" loading="lazy"><?php endif; ?>
                                    <strong class="bar-title"><?php if ($bar['slug']): ?><a href="<?php echo esc_url(home_url('/art-coa/exhibition/' . $bar['slug'])); ?>"><?php echo esc_html($bar['title']); ?></a><?php else: echo esc_html($bar['title']); endif; ?></strong>
                                    <?php if ($bar['subtitle']): ?>
                                        <span class="bar-sub"><?php echo esc_html($bar['subtitle']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="art-coa-cal-gallery">
                                <?php if ($bar['logo']): ?>
                                    <img src="<?php echo esc_url($bar['logo']); ?>" alt="" class="gal-logo">
                                <?php else: ?>
                                    <span class="gal-name"><?php echo esc_html($bar['gallery'] ?: '—'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="art-coa-cal-footer">
                <a href="#" class="art-coa-more-galleries">نمایش گالری‌های بیشتر</a>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function build_subtitle(object $ev): string
    {
        $parts = [];
        if (!empty($ev->artist_label)) {
            $parts[] = $ev->artist_label;
        }
        if (!empty($ev->gallery_name)) {
            $parts[] = $ev->gallery_name;
        }
        return implode(' / ', $parts);
    }

    private static function day_in_jalali_month(string $gregorian, int $jy, int $jm): int
    {
        $j = self::gregorian_to_jalali((int) substr($gregorian, 0, 4), (int) substr($gregorian, 5, 2), (int) substr($gregorian, 8, 2));
        if ($j[0] === $jy && $j[1] === $jm) {
            return $j[2];
        }
        // Outside month: clamp
        if ($j[0] < $jy || ($j[0] === $jy && $j[1] < $jm)) {
            return 1;
        }
        return self::jalali_days_in_month($jy, $jm);
    }

    public static function to_persian_digits(string $s): string
    {
        return strtr($s, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }

    public static function current_jalali_year(): int
    {
        $j = self::gregorian_to_jalali((int) gmdate('Y'), (int) gmdate('n'), (int) gmdate('j'));
        return $j[0];
    }

    public static function current_jalali_month(): int
    {
        $j = self::gregorian_to_jalali((int) gmdate('Y'), (int) gmdate('n'), (int) gmdate('j'));
        return $j[1];
    }

    public static function jalali_days_in_month(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }
        // Esfand
        return self::is_jalali_leap($jy) ? 30 : 29;
    }

    private static function is_jalali_leap(int $jy): bool
    {
        $breaks = [1,5,9,13,17,22,26,30];
        $jp = $jy - 979;
        $cycle = (int) ($jp / 33);
        $cy = $jp % 33;
        return in_array($cy, $breaks, true) || $cy === 0;
    }

    /** @return array{0:int,1:int,2:int} y,m,d */
    public static function gregorian_to_jalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2 = $gy - 1600;
        $gm2 = $gm - 1;
        $gd2 = $gd - 1;
        $g_day_no = 365 * $gy2 + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400);
        $g_day_no += $g_d_m[$gm2] + $gd2;
        if ($gm2 > 1 && (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0)) {
            $g_day_no++;
        }
        $j_day_no = $g_day_no - 79;
        $j_np = intdiv($j_day_no, 12053);
        $j_day_no %= 12053;
        $jy = 979 + 33 * $j_np + 4 * intdiv($j_day_no, 1461);
        $j_day_no %= 1461;
        if ($j_day_no >= 366) {
            $jy += intdiv($j_day_no - 1, 365);
            $j_day_no = ($j_day_no - 1) % 365;
        }
        $jm = ($j_day_no < 186) ? 1 + intdiv($j_day_no, 31) : 7 + intdiv($j_day_no - 186, 30);
        $jd = 1 + (($j_day_no < 186) ? ($j_day_no % 31) : (($j_day_no - 186) % 30));
        return [$jy, $jm, $jd];
    }

    public static function jalali_to_gregorian(int $jy, int $jm, int $jd): string
    {
        $jy2 = $jy - 979;
        $jm2 = $jm - 1;
        $jd2 = $jd - 1;
        $j_day_no = 365 * $jy2 + intdiv($jy2, 33) * 8 + intdiv($jy2 % 33 + 3, 4);
        $j_day_no += ($jm2 < 7) ? $jm2 * 31 : ($jm2 - 6) * 30 + 186;
        $j_day_no += $jd2;
        $g_day_no = $j_day_no + 79;
        $gy = 1600 + 400 * intdiv($g_day_no, 146097);
        $g_day_no %= 146097;
        $leap = 1;
        if ($g_day_no >= 36525) {
            $g_day_no--;
            $gy += 100 * intdiv($g_day_no, 36524);
            $g_day_no %= 36524;
            if ($g_day_no >= 365) {
                $g_day_no++;
            } else {
                $leap = 0;
            }
        }
        $gy += 4 * intdiv($g_day_no, 1461);
        $g_day_no %= 1461;
        if ($g_day_no >= 366) {
            $leap = 0;
            $g_day_no--;
            $gy += intdiv($g_day_no, 365);
            $g_day_no %= 365;
        }
        $g_d_m = [0,31, ($leap ? 29 : 28), 31,30,31,30,31,31,30,31,30,31];
        $gm = 0;
        for ($gm = 1; $gm <= 12; $gm++) {
            $v = $g_d_m[$gm];
            if ($g_day_no < $v) {
                break;
            }
            $g_day_no -= $v;
        }
        $gd = $g_day_no + 1;
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }
}
