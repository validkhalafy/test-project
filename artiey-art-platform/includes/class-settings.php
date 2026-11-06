<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global plugin settings (single option array) + cache invalidation.
 */
final class Artiey_AET_Settings {

	const OPTION     = 'artiey_aet_options';
	const VER_OPTION = 'artiey_aet_cache_ver';

	private static $cache = null;

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'maybe_bump_by_post' ), 99 );
		add_action( 'trashed_post', array( __CLASS__, 'maybe_bump_by_id' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'maybe_bump_by_id' ) );
		add_action( 'deleted_post', array( __CLASS__, 'maybe_bump_by_id' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'on_update' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'remember_old_bases' ), 10, 3 );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'on_update' ) );
		add_action( 'delete_option_' . self::OPTION, array( __CLASS__, 'on_update' ) );
	}

	/** Invalidate the in-request settings cache after a migration writes options directly. */
	public static function clear_cache() {
		self::$cache = null;
	}

	public static function defaults() {
		return array(
			// General / content.
			'days'               => 14,
			'before_days'        => 0,
			'max_rows'           => 10,
			'step'               => 7,
			'show_nav'           => 1,
			'show_range'         => 1,
			'more_text'          => 'نمایش گالری‌های بیشتر',
			'more_url'           => '',
			'empty_text'         => 'نمایشگاهی در این بازه زمانی ثبت نشده است.',
			'link_target'        => '_self',
			'digits'             => 'fa',
			'sub_fields'         => array( 'type', 'artist', 'gallery' ),
			'sub_sep'            => ' / ',
			'cache_minutes'      => 0,
			// Appearance.
			'color_bar'          => '#6b6b6b',
			'color_bar_text'     => '#ffffff',
			'color_today'        => '#ff6a2b',
			'color_head_bg'      => '#f4f4f4',
			'color_head_alt'     => '#ececec',
			'color_bg'           => '#fbfbfb',
			'color_bg_alt'       => '#f3f3f3',
			'color_bg_fri'       => '#efefef',
			'color_fri_text'     => '#c0392b',
			'status_colors'      => 0,
			'color_past'         => '#b5b5b5',
			'color_now'          => '#2e7d32',
			'color_upcoming'     => '#1565c0',
			'logo_width'         => 90,
			'col_width'          => 72,
			'row_height'         => 60,
			'logo_max_height'    => 44,
			'bar_radius'         => 0,
			'bar_gap'            => 5,
			'title_size'         => 15,
			'sub_size'           => 11,
			'stripes'            => 1,
			'highlight_fri'      => 1,
			'highlight_today'    => 1,
			// Gallery profile.
			'profile_auto'       => 1,
			'profile_sections'   => array( 'header', 'short_bio', 'statement', 'critics', 'details', 'awards', 'team', 'contact', 'hours', 'map', 'photos', 'press', 'follow', 'artists', 'curators', 'collectors', 'auctions', 'calls', 'exhibitions', 'past_exhibitions' ),
			'map_height'         => 320,
			'color_accent'       => '#ff6a2b',
			// Artists, exhibitions, directories, SEO.
			'artist_auto'        => 1,
			'artist_sections'    => array( 'header', 'short_bio', 'statement', 'details', 'awards', 'career_timeline', 'contact', 'works', 'galleries', 'exhibitions', 'activity_chart', 'sales_chart', 'artwork_history' ),
			'exhibition_auto'    => 1,
			'directory_per_page' => 12,
			'directory_columns'  => 3,
			'directory_ajax'     => 1,
			'schema'             => 1,
			'artwork_auto'       => 1,
			'artwork_sections'   => array( 'main', 'photos', 'related' ),
			'currency'           => 'تومان',
			'show_certificates'  => 1,
			// Advanced.
			'custom_css'         => '',
			'base_gallery'       => 'galleries',
			'base_exhibition'    => 'exhibitions',
			'base_artist'        => 'artists',
			'base_artwork'       => 'artworks',
			'base_style'         => 'art-styles',
			'delete_on_uninstall' => 0,
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
			// Migrate older profile-section options once. A separate marker prevents disabled sections from being re-enabled later.
			$migration_key = 'artiey_aet_profile_sections_migrated_2299';
			if ( is_array( $saved ) && ! empty( $saved ) && ! get_option( $migration_key, false ) ) {
				$changed = false;
				if ( isset( $saved['profile_sections'] ) && is_array( $saved['profile_sections'] ) && ! in_array( 'past_exhibitions', $saved['profile_sections'], true ) ) {
					self::$cache['profile_sections'][] = 'past_exhibitions';
					$changed = true;
				}
				if ( isset( $saved['artist_sections'] ) && is_array( $saved['artist_sections'] ) ) {
					foreach ( array( 'statement', 'career_timeline', 'activity_chart', 'sales_chart', 'artwork_history' ) as $new_section ) {
						if ( ! in_array( $new_section, $saved['artist_sections'], true ) ) {
							self::$cache['artist_sections'][] = $new_section;
							$changed = true;
						}
					}
					self::$cache['artist_sections'] = array_values( array_unique( self::$cache['artist_sections'] ) );
				}
				if ( $changed ) {
					$migrated = self::$cache;
					update_option( self::OPTION, $migrated, false );
					// update_option triggers the cache invalidation hook; restore the migrated value in this request.
					self::$cache = $migrated;
				}
				update_option( $migration_key, 1, false );
			}
			// 3.1: gallery statement, critics, press, awards, team, followers and open calls join the auto profile once.
			$migration_310 = 'artiey_aet_profile_sections_migrated_310';
			if ( is_array( $saved ) && ! empty( $saved['profile_sections'] ) && is_array( $saved['profile_sections'] ) && ! get_option( $migration_310, false ) ) {
				$merged = array_values( array_unique( array_merge( (array) self::$cache['profile_sections'], array( 'statement', 'critics', 'awards', 'team', 'press', 'follow', 'calls' ) ) ) );
				if ( $merged !== self::$cache['profile_sections'] ) {
					self::$cache['profile_sections'] = $merged;
					$migrated = self::$cache;
					update_option( self::OPTION, $migrated, false );
					self::$cache = $migrated;
				}
				update_option( $migration_310, 1, false );
			}
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/** Remember a previous custom base before settings replace it, so its URLs remain 301-compatible. */
	public static function remember_old_bases( $old_value, $new_value, $option = '' ) {
		if ( ! is_array( $old_value ) || ! is_array( $new_value ) || ! class_exists( 'Artiey_AET_Slug' ) ) {
			return;
		}
		$defaults = self::defaults();
		foreach ( array( 'gallery', 'exhibition', 'artist', 'artwork', 'style' ) as $key ) {
			$setting = 'base_' . $key;
			$default = $defaults[ $setting ] ?? Artiey_AET_Slug::BASES[ $key ];
			$old_base = Artiey_AET_Slug::clean_base( $old_value[ $setting ] ?? ( $key === 'style' ? 'art-style' : $default ), $default );
			$new_base = Artiey_AET_Slug::clean_base( $new_value[ $setting ] ?? $default, $default );
			if ( $old_base !== $new_base ) {
				Artiey_AET_Slug::remember_alias( $key, $old_base );
			}
		}
	}

	public static function on_update() {
		// URL bases may have changed: flush rewrite rules once on the next request.
		update_option( 'artiey_aet_flush', 1, false );
		self::$cache = null;
		self::bump();
	}

	/** Invalidate every cached timeline. */
	public static function bump() {
		update_option( self::VER_OPTION, (string) microtime( true ), false );
	}

	public static function version() {
		return (string) get_option( self::VER_OPTION, '0' );
	}

	public static function maybe_bump_by_post( $post_id ) {
		self::maybe_bump_by_id( $post_id );
	}

	public static function maybe_bump_by_id( $post_id ) {
		$type = get_post_type( $post_id );
		if ( 'artiey_exhibition' === $type || 'artiey_gallery' === $type ) {
			self::bump();
		}
	}

	/* ---------------------------------------------------------------- */
	/* Sanitization                                                      */
	/* ---------------------------------------------------------------- */

	public static function sanitize( $in ) {
		$d  = self::defaults();
		$in = is_array( $in ) ? $in : array();
		$o  = array();

		$ints = array(
			'days'            => array( 3, 31 ),
			'before_days'     => array( 0, 60 ),
			'max_rows'        => array( 1, 50 ),
			'step'            => array( 1, 31 ),
			'cache_minutes'   => array( 0, 1440 ),
			'logo_width'      => array( 50, 200 ),
			'col_width'       => array( 36, 140 ),
			'row_height'      => array( 40, 120 ),
			'logo_max_height' => array( 16, 100 ),
			'bar_radius'      => array( 0, 30 ),
			'bar_gap'         => array( 0, 20 ),
			'title_size'      => array( 10, 28 ),
			'sub_size'        => array( 8, 20 ),
			'map_height'      => array( 200, 700 ),
			'directory_per_page' => array( 1, 60 ),
			'directory_columns'  => array( 1, 6 ),
		);
		foreach ( $ints as $key => $range ) {
			$v         = isset( $in[ $key ] ) && '' !== $in[ $key ] ? (int) $in[ $key ] : $d[ $key ];
			$o[ $key ] = max( $range[0], min( $range[1], $v ) );
		}

		foreach ( array( 'color_bar', 'color_bar_text', 'color_today', 'color_head_bg', 'color_head_alt', 'color_bg', 'color_bg_alt', 'color_bg_fri', 'color_fri_text', 'color_past', 'color_now', 'color_upcoming', 'color_accent' ) as $key ) {
			$c         = isset( $in[ $key ] ) ? sanitize_hex_color( $in[ $key ] ) : null;
			$o[ $key ] = $c ? $c : $d[ $key ];
		}

		foreach ( array( 'show_nav', 'show_range', 'status_colors', 'stripes', 'highlight_fri', 'highlight_today', 'delete_on_uninstall', 'profile_auto', 'artist_auto', 'exhibition_auto', 'directory_ajax', 'schema', 'artwork_auto', 'show_certificates' ) as $key ) {
			$o[ $key ] = ! empty( $in[ $key ] ) ? 1 : 0;
		}

		$seen = array();
		foreach ( Artiey_AET_Slug::BASES as $k => $def ) {
			// Artist archive and singles are permanently unified at /artists/.
			$b = ( 'artist' === $k ) ? $def : Artiey_AET_Slug::clean_base( $in[ 'base_' . $k ] ?? $def, $def );
			if ( in_array( $b, $seen, true ) ) {
				$b = $def; // two types may not share the same base.
			}
			$seen[]            = $b;
			$o[ 'base_' . $k ] = $b;
		}

		$o['more_text']  = isset( $in['more_text'] ) ? sanitize_text_field( $in['more_text'] ) : $d['more_text'];
		$o['empty_text'] = isset( $in['empty_text'] ) ? sanitize_text_field( $in['empty_text'] ) : $d['empty_text'];
		$o['more_url']   = isset( $in['more_url'] ) ? esc_url_raw( trim( $in['more_url'] ) ) : '';

		$o['link_target'] = ( isset( $in['link_target'] ) && '_blank' === $in['link_target'] ) ? '_blank' : '_self';
		$o['digits']      = ( isset( $in['digits'] ) && 'latin' === $in['digits'] ) ? 'latin' : 'fa';

		$fields = isset( $in['sub_fields'] ) && is_array( $in['sub_fields'] ) ? $in['sub_fields'] : array();
		$o['sub_fields'] = array_values( array_intersect( array( 'type', 'artist', 'gallery' ), $fields ) );

		$sep = isset( $in['sub_sep'] ) ? preg_replace( '/[^\p{L}\p{N}\s\-\/|·•,،:؛]/u', '', (string) $in['sub_sep'] ) : '';
		$sep = Artiey_AET_Text::substr( $sep, 0, 8 );
		$o['sub_sep'] = ( '' === trim( $sep ) ) ? $d['sub_sep'] : ' ' . trim( $sep ) . ' ';

		$secs = isset( $in['profile_sections'] ) && is_array( $in['profile_sections'] ) ? $in['profile_sections'] : array();
		$o['profile_sections'] = array_values( array_intersect( array( 'header', 'short_bio', 'statement', 'critics', 'details', 'awards', 'team', 'contact', 'hours', 'map', 'photos', 'press', 'follow', 'artists', 'curators', 'collectors', 'auctions', 'calls', 'exhibitions', 'past_exhibitions' ), $secs ) );

		$asecs = isset( $in['artist_sections'] ) && is_array( $in['artist_sections'] ) ? $in['artist_sections'] : array();
		$o['artist_sections'] = array_values( array_intersect( array( 'header', 'short_bio', 'statement', 'details', 'awards', 'career_timeline', 'contact', 'works', 'galleries', 'exhibitions', 'activity_chart', 'sales_chart', 'artwork_history' ), $asecs ) );

		$wsecs = isset( $in['artwork_sections'] ) && is_array( $in['artwork_sections'] ) ? $in['artwork_sections'] : array();
		$o['artwork_sections'] = array_values( array_intersect( array( 'main', 'photos', 'related' ), $wsecs ) );

		$cur = isset( $in['currency'] ) ? sanitize_text_field( $in['currency'] ) : '';
		$o['currency'] = ( '' !== $cur ) ? Artiey_AET_Text::substr( $cur, 0, 12 ) : $d['currency'];

		$css = isset( $in['custom_css'] ) ? wp_strip_all_tags( (string) $in['custom_css'] ) : '';
		$o['custom_css'] = Artiey_AET_Text::substr( $css, 0, 6000 );

		return $o;
	}

	/* ---------------------------------------------------------------- */
	/* Front-end CSS variables                                           */
	/* ---------------------------------------------------------------- */

	public static function inline_css() {
		$s = self::all();
		$d = self::defaults();

		$color = function ( $key ) use ( $s, $d ) {
			$c = sanitize_hex_color( $s[ $key ] );
			return $c ? $c : $d[ $key ];
		};
		$px = function ( $key ) use ( $s, $d ) {
			return absint( isset( $s[ $key ] ) ? $s[ $key ] : $d[ $key ] ) . 'px';
		};

		$vars = array(
			'--aet-bar'        => $color( 'color_bar' ),
			'--aet-bar-text'   => $color( 'color_bar_text' ),
			'--aet-today'      => $color( 'color_today' ),
			'--aet-head-bg'    => $color( 'color_head_bg' ),
			'--aet-head-alt'   => $color( 'color_head_alt' ),
			'--aet-bg'         => $color( 'color_bg' ),
			'--aet-bg-alt'     => $color( 'color_bg_alt' ),
			'--aet-bg-fri'     => $color( 'color_bg_fri' ),
			'--aet-fri-text'   => $color( 'color_fri_text' ),
			'--aet-past'       => $color( 'color_past' ),
			'--aet-now'        => $color( 'color_now' ),
			'--aet-upcoming'   => $color( 'color_upcoming' ),
			'--aet-logo'       => $px( 'logo_width' ),
			'--aet-col'        => $px( 'col_width' ),
			'--aet-row'        => $px( 'row_height' ),
			'--aet-logo-h'     => $px( 'logo_max_height' ),
			'--aet-radius'     => $px( 'bar_radius' ),
			'--aet-gap'        => $px( 'bar_gap' ),
			'--aet-title-size' => $px( 'title_size' ),
			'--aet-sub-size'   => $px( 'sub_size' ),
		);

		$css = '.aet{';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}
		$css .= '}';

		if ( ! empty( $s['custom_css'] ) ) {
			$css .= "\n" . wp_strip_all_tags( $s['custom_css'] );
		}
		return $css;
	}
}
