<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal Jalali (Shamsi) calendar helpers.
 * Dates are always STORED as Gregorian Y-m-d and DISPLAYED / ENTERED as Jalali.
 */
final class Artiey_AET_Jalali {

	const MONTHS = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);

	// Index = PHP "w" (0 = Sunday).
	const WEEKDAYS = array(
		0 => 'یکشنبه',
		1 => 'دوشنبه',
		2 => 'سه‌شنبه',
		3 => 'چهارشنبه',
		4 => 'پنجشنبه',
		5 => 'جمعه',
		6 => 'شنبه',
	);

	public static function g2j( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 ) + (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * (int) ( $days / 12053 ) );
		$days %= 12053;
		$jy   += 4 * (int) ( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy  += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}

	public static function j2g( $jy, $jm, $jd ) {
		$jy  += 1595;
		$days = -355668 + ( 365 * $jy ) + ( (int) ( $jy / 33 ) * 8 ) + (int) ( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd + ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
		$gy   = 400 * (int) ( $days / 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			--$days;
			$gy  += 100 * (int) ( $days / 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				++$days;
			}
		}
		$gy  += 4 * (int) ( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$gy  += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		$gd    = $days + 1;
		$leap  = ( ( $gy % 4 === 0 && $gy % 100 !== 0 ) || $gy % 400 === 0 ) ? 29 : 28;
		$sal_a = array( 0, 31, $leap, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		for ( $gm = 0; $gm < 13 && $gd > $sal_a[ $gm ]; $gm++ ) {
			$gd -= $sal_a[ $gm ];
		}
		return array( $gy, $gm, $gd );
	}

	/** Convert Persian / Arabic-Indic digits to Latin digits. */
	public static function to_latin_digits( $str ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $ar, $en, str_replace( $fa, $en, (string) $str ) );
	}

	/** Normalise digits for storage only when $convert is true; otherwise return the value untouched. */
	public static function to_latin_digits_if_numeric( $value, $convert ) {
		return $convert ? self::to_latin_digits( (string) $value ) : (string) $value;
	}

	/** Convert Latin digits to Persian digits. */
	public static function to_persian_digits( $str ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $en, $fa, (string) $str );
	}

	/** "2026-10-24" => array( 1405, 8, 2 ) */
	public static function ymd_to_parts( $ymd ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $ymd, $m ) ) {
			return null;
		}
		return self::g2j( (int) $m[1], (int) $m[2], (int) $m[3] );
	}

	/** "2026-10-24" => "1405/08/02" (Latin digits, for form fields). */
	public static function ymd_to_jalali( $ymd ) {
		$p = self::ymd_to_parts( $ymd );
		if ( ! $p ) {
			return '';
		}
		return sprintf( '%04d/%02d/%02d', $p[0], $p[1], $p[2] );
	}

	/**
	 * "1405/08/02" (Persian or Latin digits, separated by / or -) => "2026-10-24".
	 * Returns an empty string when the input is invalid.
	 */
	public static function jalali_to_ymd( $input ) {
		$input = self::to_latin_digits( trim( (string) $input ) );
		if ( ! preg_match( '/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $input, $m ) ) {
			return '';
		}
		$jy = (int) $m[1];
		$jm = (int) $m[2];
		$jd = (int) $m[3];
		if ( $jy < 1300 || $jy > 1600 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31 ) {
			return '';
		}
		if ( $jm > 6 && $jd > 30 ) {
			return '';
		}
		list( $gy, $gm, $gd ) = self::j2g( $jy, $jm, $jd );
		// Round-trip check rejects impossible dates such as 30 Esfand in a non-leap year.
		$back = self::g2j( $gy, $gm, $gd );
		if ( $back[0] !== $jy || $back[1] !== $jm || $back[2] !== $jd ) {
			return '';
		}
		return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
	}

	/** "2026-10-24" => "۲ آبان ۱۴۰۵" (or Latin digits when $persian is false). */
	public static function format_long( $ymd, $persian = true ) {
		$p = self::ymd_to_parts( $ymd );
		if ( ! $p ) {
			return '';
		}
		$out = $p[2] . ' ' . self::MONTHS[ $p[1] ] . ' ' . $p[0];
		return $persian ? self::to_persian_digits( $out ) : $out;
	}
}
