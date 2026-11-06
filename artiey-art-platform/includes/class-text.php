<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UTF-8 text helpers. The platform must remain usable on hosts where mbstring
 * is not installed; these methods use mbstring when available and otherwise
 * fall back to Unicode-aware PCRE operations.
 */
final class Artiey_AET_Text {

	public static function substr( $value, $start, $length = null ) {
		$value  = (string) $value;
		$start  = (int) $start;
		$length = null === $length ? null : (int) $length;
		if ( function_exists( 'mb_substr' ) ) {
			return null === $length ? mb_substr( $value, $start, null, 'UTF-8' ) : mb_substr( $value, $start, $length, 'UTF-8' );
		}
		if ( '' === $value ) {
			return '';
		}
		$count = preg_match_all( '/./us', $value, $matches );
		if ( false === $count ) {
			return null === $length ? substr( $value, $start ) : substr( $value, $start, $length );
		}
		$chars = $matches[0] ?? array();
		if ( $start < 0 ) {
			$start = max( 0, count( $chars ) + $start );
		}
		return implode( '', array_slice( $chars, $start, $length ) );
	}

	public static function length( $value ) {
		$value = (string) $value;
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}
		$count = preg_match_all( '/./us', $value, $unused );
		return false === $count ? strlen( $value ) : $count;
	}

	public static function strpos( $haystack, $needle, $offset = 0 ) {
		$haystack = (string) $haystack;
		$needle   = (string) $needle;
		if ( function_exists( 'mb_strpos' ) ) {
			return mb_strpos( $haystack, $needle, (int) $offset, 'UTF-8' );
		}
		if ( (int) $offset > 0 ) {
			$prefix = self::substr( $haystack, 0, (int) $offset );
			$tail   = substr( $haystack, strlen( $prefix ) );
			$pos    = strpos( $tail, $needle );
			return false === $pos ? false : (int) $offset + self::length( substr( $tail, 0, $pos ) );
		}
		$pos = strpos( $haystack, $needle );
		return false === $pos ? false : self::length( substr( $haystack, 0, $pos ) );
	}
}
