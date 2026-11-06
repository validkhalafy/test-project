<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Ulid
{
    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $time = (int) (microtime(true) * 1000);
        $time_part = '';
        for ($i = 9; $i >= 0; $i--) {
            $time_part = self::CROCKFORD[$time % 32] . $time_part;
            $time = intdiv($time, 32);
        }

        $random = '';
        for ($i = 0; $i < 16; $i++) {
            $random .= self::CROCKFORD[random_int(0, 31)];
        }

        return $time_part . $random;
    }
}
