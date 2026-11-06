<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Dates
{
    private const MONTHS_FA = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    private const MONTHS_EN = [
        1 => 'Farvardin', 2 => 'Ordibehesht', 3 => 'Khordad', 4 => 'Tir',
        5 => 'Mordad', 6 => 'Shahrivar', 7 => 'Mehr', 8 => 'Aban',
        9 => 'Azar', 10 => 'Dey', 11 => 'Bahman', 12 => 'Esfand',
    ];

    public static function use_jalali(): bool
    {
        $opt = get_option('wp_art_coa_settings', []);
        return !isset($opt['jalali_dates']) || !empty($opt['jalali_dates']);
    }

    /**
     * Format a datetime/date string from DB for display.
     */
    public static function format(?string $datetime, bool $with_time = false): string
    {
        if (!$datetime) {
            return '—';
        }

        try {
            $local = (new \DateTimeImmutable($datetime, new \DateTimeZone('UTC')))->setTimezone(wp_timezone());
        } catch (\Exception $exception) {
            return esc_html($datetime);
        }

        if (!self::use_jalali()) {
            return $with_time
                ? $local->format('Y-m-d H:i')
                : $local->format('Y-m-d');
        }

        $j = Calendar::gregorian_to_jalali(
            (int) $local->format('Y'),
            (int) $local->format('n'),
            (int) $local->format('j')
        );

        $lang = I18n::lang();
        $month = $lang === 'en'
            ? (self::MONTHS_EN[$j[1]] ?? (string) $j[1])
            : (self::MONTHS_FA[$j[1]] ?? (string) $j[1]);

        $day = Calendar::to_persian_digits((string) $j[2]);
        $year = Calendar::to_persian_digits((string) $j[0]);

        if ($lang === 'en') {
            $day = (string) $j[2];
            $year = (string) $j[0];
        }

        $out = $lang === 'en'
            ? sprintf('%s %s %s', $day, $month, $year)
            : sprintf('%s %s %s', $day, $month, $year);

        if ($with_time) {
            $time = $local->format('H:i');
            if ($lang === 'fa') {
                $time = Calendar::to_persian_digits($time);
            }
            $out .= ' — ' . $time;
        }

        return $out;
    }

    /** Short numeric Jalali: 1405/08/03 */
    public static function format_numeric(?string $datetime): string
    {
        if (!$datetime) {
            return '—';
        }
        try {
            $local = (new \DateTimeImmutable($datetime, new \DateTimeZone('UTC')))->setTimezone(wp_timezone());
        } catch (\Exception $exception) {
            return $datetime;
        }
        if (!self::use_jalali()) {
            return $local->format('Y-m-d');
        }
        $j = Calendar::gregorian_to_jalali(
            (int) $local->format('Y'),
            (int) $local->format('n'),
            (int) $local->format('j')
        );
        $s = sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);
        return I18n::is_fa() ? Calendar::to_persian_digits($s) : $s;
    }
}
