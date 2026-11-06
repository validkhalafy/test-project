<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery profile: data access, front-end rendering, shortcodes.
 */
final class Artiey_AET_Gallery {

	// PHP "w" numbers, Persian week order (Saturday first).
	const WEEK_ORDER = array( 6, 0, 1, 2, 3, 4, 5 );

	const ALL_SECTIONS = array( 'header', 'short_bio', 'bio', 'details', 'contact', 'hours', 'map', 'photos', 'artists', 'curators', 'collectors', 'auctions', 'exhibitions', 'past_exhibitions' );

	/** Request-local schema cache avoids repeating SHOW TABLES / SHOW COLUMNS for every relation block. */
	private static $schema_cache = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'artiey_gallery_profile', array( __CLASS__, 'shortcode_profile' ) );
		add_shortcode( 'artiey_gallery_field', array( __CLASS__, 'shortcode_field' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
	}

	public static function register_assets() {
		wp_register_style( 'artiey-aet-profile', AET_URL . 'assets/css/profile.css', array(), AET_VERSION );
		wp_register_script( 'artiey-aet-profile', AET_URL . 'assets/js/profile.js', array(), AET_VERSION, true );
		$accent = sanitize_hex_color( Artiey_AET_Settings::get( 'color_accent' ) );
		$css    = '.aetp{--aetp-accent:' . ( $accent ? $accent : '#ff6a2b' ) . ';--aetp-map-h:' . absint( Artiey_AET_Settings::get( 'map_height' ) ) . 'px}';
		wp_add_inline_style( 'artiey-aet-profile', $css );
	}

	/* ---------------------------------------------------------------- */
	/* Helpers                                                           */
	/* ---------------------------------------------------------------- */

	public static function split_numbers( $str ) {
		$parts = preg_split( '/[,\n;،]+/u', (string) $str );
		$parts = array_map( 'trim', (array) $parts );
		return array_values( array_filter( $parts, 'strlen' ) );
	}

	/** Digits only, Latin. */
	public static function digits_only( $str ) {
		return preg_replace( '/\D+/', '', Artiey_AET_Jalali::to_latin_digits( (string) $str ) );
	}

	public static function tel_href( $num ) {
		$d = self::digits_only( $num );
		if ( '' === $d ) {
			return '';
		}
		if ( 0 === strpos( $d, '98' ) && strlen( $d ) > 10 ) {
			return 'tel:+' . $d;
		}
		if ( 0 === strpos( $d, '0' ) ) {
			return 'tel:+98' . substr( $d, 1 );
		}
		return 'tel:' . $d;
	}

	public static function wa_href( $num ) {
		$d = self::digits_only( $num );
		if ( '' === $d ) {
			return '';
		}
		if ( 0 === strpos( $d, '00' ) ) {
			$d = substr( $d, 2 );
		} elseif ( 0 === strpos( $d, '0' ) ) {
			$d = '98' . substr( $d, 1 );
		} elseif ( 10 === strlen( $d ) && 0 === strpos( $d, '9' ) ) {
			$d = '98' . $d;
		}
		return 'https://wa.me/' . $d;
	}

	public static function clean_handle( $in ) {
		$in = trim( (string) $in );
		$in = preg_replace( '#^https?://(www\.)?(instagram\.com|t\.me|telegram\.me)/#i', '', $in );
		$in = ltrim( $in, '@/' );
		$in = preg_replace( '#[/?].*$#', '', $in );
		return preg_replace( '/[^A-Za-z0-9._]/', '', $in );
	}

	private static function fa( $str ) {
		return Artiey_AET_Jalali::to_persian_digits( (string) $str );
	}

	private static function meta( $id, $key ) {
		return get_post_meta( $id, '_ag_' . $key, true );
	}

	private static function id_list( $value ) {
		return is_array( $value ) ? array_values( array_filter( array_map( 'absint', $value ) ) ) : array();
	}

	public static function normalize_hours( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		for ( $w = 0; $w <= 6; $w++ ) {
			$d         = isset( $raw[ $w ] ) && is_array( $raw[ $w ] ) ? $raw[ $w ] : array();
			$out[ $w ] = array(
				'closed' => ! empty( $d['closed'] ) ? 1 : 0,
				'o1'     => isset( $d['o1'] ) ? (string) $d['o1'] : '',
				'c1'     => isset( $d['c1'] ) ? (string) $d['c1'] : '',
				'o2'     => isset( $d['o2'] ) ? (string) $d['o2'] : '',
				'c2'     => isset( $d['c2'] ) ? (string) $d['c2'] : '',
			);
		}
		return $out;
	}

	public static function get( $id ) {
		$id = (int) $id;
		return array(
			'id'         => $id,
			'name'       => get_the_title( $id ),
			'name_en'    => (string) self::meta( $id, 'name_en' ),
			'founded'    => (int) self::meta( $id, 'founded' ),
			'venue_type' => (string) self::meta( $id, 'venue_type' ),
			'short_bio'  => (string) self::meta( $id, 'short_bio' ),
			'phones'     => self::split_numbers( self::meta( $id, 'phone' ) ),
			'mobiles'    => self::split_numbers( self::meta( $id, 'mobile' ) ),
			'whatsapp'   => (string) self::meta( $id, 'whatsapp' ),
			'instagram'  => (string) self::meta( $id, 'instagram' ),
			'telegram'   => (string) self::meta( $id, 'telegram' ),
			'email'      => (string) self::meta( $id, 'email' ),
			'website'    => (string) self::meta( $id, 'website' ),
			'city'       => (string) self::meta( $id, 'city' ),
			'visitor_info' => (string) self::meta( $id, 'visitor_info' ),
			'address'    => (string) self::meta( $id, 'address' ),
			'lat'        => (string) self::meta( $id, 'lat' ),
			'lng'        => (string) self::meta( $id, 'lng' ),
			'status'     => (string) self::meta( $id, 'status' ),
			'hours'      => self::normalize_hours( self::meta( $id, 'hours' ) ),
			'hours_note' => (string) self::meta( $id, 'hours_note' ),
			'cover'      => (int) self::meta( $id, 'cover' ),
			'photos'     => self::id_list( self::meta( $id, 'photos' ) ),
			'artists'    => self::id_list( self::meta( $id, 'artists' ) ),
		);
	}

	private static function has_hours( $g ) {
		foreach ( $g['hours'] as $d ) {
			if ( ! $d['closed'] && '' !== $d['o1'] && '' !== $d['c1'] ) {
				return true;
			}
		}
		return false;
	}

	/** @return array{state:string,label:string} */
	public static function status( $g ) {
		switch ( $g['status'] ) {
			case 'temp_closed':
				return array( 'state' => 'closed', 'label' => 'موقتاً تعطیل است' );
			case 'closed':
				return array( 'state' => 'permanent', 'label' => 'برای همیشه بسته شده' );
			case 'appointment':
				return array( 'state' => 'appointment', 'label' => 'فقط با هماهنگی قبلی' );
		}

		if ( ! self::has_hours( $g ) ) {
			return array( 'state' => 'active', 'label' => 'فعال' );
		}

		$w   = (int) wp_date( 'w' );
		$now = wp_date( 'H:i' );
		$d   = $g['hours'][ $w ];
		$open = false;
		if ( ! $d['closed'] ) {
			if ( '' !== $d['o1'] && '' !== $d['c1'] && $now >= $d['o1'] && $now < $d['c1'] ) {
				$open = true;
			}
			if ( '' !== $d['o2'] && '' !== $d['c2'] && $now >= $d['o2'] && $now < $d['c2'] ) {
				$open = true;
			}
		}
		return $open
			? array( 'state' => 'open', 'label' => 'هم‌اکنون باز است' )
			: array( 'state' => 'shut', 'label' => 'هم‌اکنون بسته است' );
	}

	private static function badge( $g ) {
		$st = self::status( $g );
		return '<span class="aetp-badge aetp-st-' . esc_attr( $st['state'] ) . '">' . esc_html( $st['label'] ) . '</span>';
	}

	private static function dates_text( $post_id ) {
		$from = Artiey_AET_Jalali::format_long( get_post_meta( $post_id, '_ae_start', true ) );
		$to   = Artiey_AET_Jalali::format_long( get_post_meta( $post_id, '_ae_end', true ) );
		if ( ! $from ) {
			return '';
		}
		return ( $to && $to !== $from ) ? $from . ' تا ' . $to : $from;
	}

	public static function map_link( $g ) {
		if ( '' === $g['lat'] || '' === $g['lng'] ) {
			return '';
		}
		return 'https://www.google.com/maps?q=' . rawurlencode( $g['lat'] . ',' . $g['lng'] );
	}

	/* ---------------------------------------------------------------- */
	/* Section renderers                                                 */
	/* ---------------------------------------------------------------- */

	private static function s_header( $g, $show_title ) {
		$id    = $g['id'];
		$cover = $g['cover'] ? wp_get_attachment_image( $g['cover'], 'large', false, array( 'class' => 'aetp-cover', 'alt' => '', 'loading' => 'lazy' ) ) : '';

		$h  = '<header class="aetp-hero' . ( $cover ? ' has-cover' : '' ) . '">';
		$h .= '<div class="aetp-cover-wrap">' . $cover . '</div>';
		$h .= '<div class="aetp-hero-inner">';
		if ( has_post_thumbnail( $id ) ) {
			$h .= '<div class="aetp-logo">' . get_the_post_thumbnail( $id, 'medium', array( 'alt' => $g['name'], 'loading' => 'lazy' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$h .= '<div class="aetp-names">';
		if ( $show_title ) {
			$h .= '<h2 class="aetp-title">' . esc_html( $g['name'] ) . '</h2>';
		}
		if ( $g['name_en'] ) {
			$h .= '<div class="aetp-title-en" dir="ltr">' . esc_html( $g['name_en'] ) . '</div>';
		}
		$meta = array();
		if ( $g['city'] ) {
			$meta[] = esc_html( $g['city'] );
		}
		if ( $g['founded'] ) {
			$years = max( 0, (int) Artiey_AET_Jalali::ymd_to_parts( current_time( 'Y-m-d' ) )[0] - $g['founded'] );
			$txt   = 'تأسیس ' . self::fa( $g['founded'] );
			if ( $years > 0 ) {
				$txt .= ' (' . self::fa( $years ) . ' سال سابقه)';
			}
			$meta[] = esc_html( $txt );
		}
		if ( $meta ) {
			$h .= '<div class="aetp-meta">' . implode( ' <span class="aetp-dot">·</span> ', $meta ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$h .= self::badge( $g );
		$h .= '</div></div></header>';
		return $h;
	}

	private static function s_short_bio( $g ) {
		if ( '' === trim( $g['short_bio'] ) ) {
			return '';
		}
		return '<p class="aetp-lead">' . esc_html( $g['short_bio'] ) . '</p>';
	}

	private static function s_bio( $g ) {
		$html = Artiey_AET_Profile_Guard::bio_html( get_post( $g['id'] ) );
		if ( '' === $html ) {
			return '';
		}
		return '<section class="aetp-sec aetp-bio"><h3>درباره گالری</h3><div class="aetp-bio-text">' . $html . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function s_details( $g ) {
		$rows = array();
		$rows['نام فارسی'] = esc_html( $g['name'] );
		if ( $g['name_en'] ) {
			$rows['نام لاتین'] = '<span dir="ltr">' . esc_html( $g['name_en'] ) . '</span>';
		}
		if ( $g['founded'] ) {
			$rows['سال تأسیس'] = esc_html( self::fa( $g['founded'] ) );
		}
		if ( $g['city'] ) {
			$rows['شهر'] = esc_html( $g['city'] );
		}
		$venue_labels = array( 'physical' => 'گالری فیزیکی', 'online' => 'گالری آنلاین', 'hybrid' => 'فیزیکی و آنلاین' );
		if ( isset( $venue_labels[ $g['venue_type'] ] ) ) {
			$rows['نوع گالری'] = esc_html( $venue_labels[ $g['venue_type'] ] );
		}
		if ( $g['visitor_info'] ) {
			$rows['اطلاعات بازدید'] = nl2br( esc_html( $g['visitor_info'] ) );
		}
		if ( Artiey_AET_Settings::get( 'show_certificates' ) ) {
			$certs = Artiey_AET_COA_Bridge::active_count_for_gallery_post( $g['id'] );
			if ( $certs > 0 ) {
				$rows['گواهی‌های اصالت فعال'] = esc_html( self::fa( $certs ) );
			}
		}
		$terms = get_the_terms( $g['id'], 'artiey_style' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$links = array();
			foreach ( $terms as $t ) {
				$links[] = '<a class="aetp-chip" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>';
			}
			$rows['سبک هنری'] = implode( ' ', $links );
		}
		if ( count( $rows ) < 2 ) {
			return '';
		}
		$h = '<section class="aetp-sec aetp-details"><h3>مشخصات</h3><dl class="aetp-dl">';
		foreach ( $rows as $k => $v ) {
			$h .= '<dt>' . esc_html( $k ) . '</dt><dd>' . $v . '</dd>';
		}
		return $h . '</dl></section>';
	}

	private static function s_contact( $g ) {
		$rows = array();
		foreach ( $g['phones'] as $n ) {
			$rows[] = array( 'تلفن', '<a href="' . esc_url( self::tel_href( $n ) ) . '" dir="ltr">' . esc_html( self::fa( $n ) ) . '</a>' );
		}
		foreach ( $g['mobiles'] as $n ) {
			$rows[] = array( 'موبایل', '<a href="' . esc_url( self::tel_href( $n ) ) . '" dir="ltr">' . esc_html( self::fa( $n ) ) . '</a>' );
		}
		if ( $g['email'] ) {
			$rows[] = array( 'ایمیل', '<a href="' . esc_url( 'mailto:' . $g['email'] ) . '" dir="ltr">' . esc_html( $g['email'] ) . '</a>' );
		}
		if ( $g['website'] ) {
			$rows[] = array( 'وب‌سایت', '<a href="' . esc_url( $g['website'] ) . '" target="_blank" rel="noopener" dir="ltr">' . esc_html( preg_replace( '#^https?://(www\.)?#i', '', rtrim( $g['website'], '/' ) ) ) . '</a>' );
		}

		$social = '';
		if ( $g['whatsapp'] && self::wa_href( $g['whatsapp'] ) ) {
			$social .= '<a class="aetp-pill aetp-wa" href="' . esc_url( self::wa_href( $g['whatsapp'] ) ) . '" target="_blank" rel="noopener">واتس‌اپ</a>';
		}
		if ( $g['instagram'] ) {
			$social .= '<a class="aetp-pill aetp-ig" href="' . esc_url( 'https://instagram.com/' . $g['instagram'] ) . '" target="_blank" rel="noopener">اینستاگرام</a>';
		}
		if ( $g['telegram'] ) {
			$social .= '<a class="aetp-pill aetp-tg" href="' . esc_url( 'https://t.me/' . $g['telegram'] ) . '" target="_blank" rel="noopener">تلگرام</a>';
		}

		if ( ! $rows && ! $social ) {
			return '';
		}
		$h = '<section class="aetp-sec aetp-contact"><h3>راه‌های ارتباطی</h3>';
		if ( $rows ) {
			$h .= '<dl class="aetp-dl">';
			foreach ( $rows as $r ) {
				$h .= '<dt>' . esc_html( $r[0] ) . '</dt><dd>' . $r[1] . '</dd>';
			}
			$h .= '</dl>';
		}
		if ( $social ) {
			$h .= '<div class="aetp-social">' . $social . '</div>';
		}
		return $h . '</section>';
	}

	private static function s_hours( $g ) {
		if ( ! self::has_hours( $g ) && '' === trim( $g['hours_note'] ) ) {
			return '';
		}
		$days  = Artiey_AET_Jalali::WEEKDAYS;
		$today = (int) wp_date( 'w' );

		$h  = '<section class="aetp-sec aetp-hours"><h3>ساعات کاری</h3>';
		$h .= '<div class="aetp-hours-head">' . self::badge( $g ) . '</div>';
		if ( self::has_hours( $g ) ) {
			$h .= '<table class="aetp-table"><tbody>';
			foreach ( self::WEEK_ORDER as $w ) {
				$d = $g['hours'][ $w ];
				if ( $d['closed'] || '' === $d['o1'] || '' === $d['c1'] ) {
					$val = '<span class="aetp-muted">تعطیل</span>';
				} else {
					$val = esc_html( self::fa( $d['o1'] . ' تا ' . $d['c1'] ) );
					if ( '' !== $d['o2'] && '' !== $d['c2'] ) {
						$val .= ' <span class="aetp-muted">و</span> ' . esc_html( self::fa( $d['o2'] . ' تا ' . $d['c2'] ) );
					}
				}
				$h .= '<tr' . ( $w === $today ? ' class="is-today"' : '' ) . '><th>' . esc_html( $days[ $w ] ) . '</th><td>' . $val . '</td></tr>';
			}
			$h .= '</tbody></table>';
		}
		if ( $g['hours_note'] ) {
			$h .= '<p class="aetp-note">' . esc_html( $g['hours_note'] ) . '</p>';
		}
		return $h . '</section>';
	}

	private static function s_map( $g ) {
		$has_pos = ( '' !== $g['lat'] && '' !== $g['lng'] );
		if ( ! $has_pos && '' === trim( $g['address'] ) ) {
			return '';
		}
		$h = '<section class="aetp-sec aetp-map"><h3>آدرس و نقشه</h3>';
		if ( $g['address'] ) {
			$h .= '<p class="aetp-address">' . esc_html( $g['address'] ) . '</p>';
		}
		if ( $has_pos ) {
			$lat  = (float) $g['lat'];
			$lng  = (float) $g['lng'];
			$bbox = sprintf( '%.6F,%.6F,%.6F,%.6F', $lng - 0.004, $lat - 0.0025, $lng + 0.004, $lat + 0.0025 );
			$src  = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode( $bbox ) . '&layer=mapnik&marker=' . rawurlencode( sprintf( '%.6F,%.6F', $lat, $lng ) );
			$h   .= '<div class="aetp-map-frame"><iframe src="' . esc_url( $src ) . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" title="نقشه ' . esc_attr( $g['name'] ) . '" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
			$h   .= '<div class="aetp-social">';
			$h   .= '<a class="aetp-pill" href="' . esc_url( self::map_link( $g ) ) . '" target="_blank" rel="noopener">باز کردن در گوگل‌مپ</a>';
			$h   .= '<a class="aetp-pill" href="' . esc_url( 'https://waze.com/ul?ll=' . rawurlencode( $lat . ',' . $lng ) . '&navigate=yes' ) . '" target="_blank" rel="noopener">مسیریابی با Waze</a>';
			$h   .= '</div>';
		}
		return $h . '</section>';
	}

	/** Prime a bounded group of posts and their metadata in one query before rendering image cards. */
	private static function prime_post_caches( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( $ids && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, true );
		}
	}

	private static function s_photos( $g ) {
		if ( ! $g['photos'] ) {
			return '';
		}
		// wp_get_attachment_image() otherwise has to fetch each attachment and its
		// metadata separately on a cold object cache (classic N+1 query pattern).
		self::prime_post_caches( $g['photos'] );
		$h = '<section class="aetp-sec aetp-photos"><h3>تصاویر گالری</h3><div class="aetp-photo-grid">';
		foreach ( $g['photos'] as $pid ) {
			$img = wp_get_attachment_image( $pid, 'medium_large', false, array( 'loading' => 'lazy' ) );
			if ( ! $img ) {
				continue;
			}
			$full = wp_get_attachment_url( $pid );
			$h   .= '<a href="' . esc_url( $full ) . '" target="_blank" rel="noopener">' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $h . '</div></section>';
	}

	/** Resolve a mirrored Art COA table only when it exists; this keeps older installs graceful. */
	private static function art_table( $name ) {
		if ( ! class_exists( '\\WPArtCOA\\Database' ) ) {
			return '';
		}
		$key = 'art-table:' . sanitize_key( (string) $name );
		if ( array_key_exists( $key, self::$schema_cache ) ) { return self::$schema_cache[ $key ]; }
		$table = \WPArtCOA\Database::table( $name );
		self::$schema_cache[ $key ] = self::table_exists( $table ) ? $table : '';
		return self::$schema_cache[ $key ];
	}

	/** Resolve the Art COA mirror row for this WordPress gallery without guessing by title. */
	private static function coa_gallery_id( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return 0;
		}
		$generation = max( 1, absint( get_option( 'artiey_aet_url_cache_generation', 1 ) ) );
		$cache_key = 'aetp_gallery_row_' . $generation . '_' . $post_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}
		$table = self::art_table( 'galleries' );
		$id    = 0;
		if ( $table ) {
			global $wpdb;
			$column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'cpt_post_id' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $column ) {
				$id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE cpt_post_id = %d LIMIT 1", $post_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		set_transient( $cache_key, $id, 5 * MINUTE_IN_SECONDS );
		return $id;
	}

	/** Cache bounded public relationship queries; content mirrors are refreshed independently. */
	private static function related_rows( $key, $callback, $ttl = 180 ) {
		$generation = max( 1, absint( get_option( 'artiey_aet_url_cache_generation', 1 ) ) );
		$cache_key = 'aetp_rel_' . $generation . '_' . md5( (string) $key );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$rows = is_callable( $callback ) ? call_user_func( $callback ) : array();
		$rows = is_array( $rows ) ? $rows : array();
		set_transient( $cache_key, $rows, max( 30, absint( $ttl ) ) );
		return $rows;
	}

	private static function related_limit( $extras = array() ) {
		$limit = isset( $extras['related_items_limit'] ) ? absint( $extras['related_items_limit'] ) : 8;
		return max( 2, min( 24, $limit ? $limit : 8 ) );
	}

	private static function related_mode( $extras = array() ) {
		return isset( $extras['related_card_layout'] ) && 'compact' === $extras['related_card_layout'] ? 'is-compact' : 'is-grid';
	}

	private static function related_meta_enabled( $extras = array() ) {
		if ( ! array_key_exists( 'show_related_meta', $extras ) ) { return true; }
		return in_array( $extras['show_related_meta'], array( 'yes', '1', 1, true ), true );
	}

	private static function related_image( $url, $alt, $class = '' ) {
		$url = is_string( $url ) ? $url : '';
		if ( '' === $url ) {
			return '<span class="aetp-related-placeholder" aria-hidden="true">✦</span>';
		}
		return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
	}

	private static function related_card( $item, $kind, $extras = array() ) {
		$title = isset( $item['title'] ) ? (string) $item['title'] : '';
		$url   = isset( $item['url'] ) ? (string) $item['url'] : '';
		if ( '' === $title || '' === $url ) {
			return '';
		}
		$image = self::related_image( $item['image'] ?? '', $title );
		$sub   = isset( $item['sub'] ) ? (string) $item['sub'] : '';
		$meta  = isset( $item['meta'] ) ? (string) $item['meta'] : '';
		$badge = isset( $item['badge'] ) ? (string) $item['badge'] : '';
		$html  = '<a class="aetp-related-card aetp-related-' . esc_attr( $kind ) . '" href="' . esc_url( $url ) . '">';
		$html .= '<span class="aetp-related-media">' . $image . '</span>';
		$html .= '<span class="aetp-related-content"><strong class="aetp-related-title">' . esc_html( $title ) . '</strong>';
		if ( '' !== $sub ) {
			$html .= '<span class="aetp-related-sub">' . esc_html( $sub ) . '</span>';
		}
		if ( self::related_meta_enabled( $extras ) && '' !== $meta ) {
			$html .= '<span class="aetp-related-meta">' . esc_html( $meta ) . '</span>';
		}
		if ( '' !== $badge ) {
			$html .= '<span class="aetp-related-badge">' . esc_html( $badge ) . '</span>';
		}
		$html .= '</span></a>';
		return $html;
	}

	private static function related_section( $class, $heading, $items, $kind, $extras = array() ) {
		if ( ! $items ) {
			return '';
		}
		$mode = self::related_mode( $extras );
		$html = '<section class="aetp-sec aetp-related-section aetp-related-' . esc_attr( $class ) . '"><div class="aetp-related-heading"><h3>' . esc_html( $heading ) . '</h3><span class="aetp-related-count">' . esc_html( number_format_i18n( count( $items ) ) ) . '</span></div><div class="aetp-related-grid ' . esc_attr( $mode ) . '">';
		foreach ( $items as $item ) {
			$html .= self::related_card( $item, $kind, $extras );
		}
		return $html . '</div></section>';
	}

	private static function s_artists( $g, $extras = array() ) {
		$limit   = self::related_limit( $extras );
		$items   = array();
		$seen    = array();
		$ids     = array_slice( self::id_list( $g['artists'] ), 0, 60 );
		$posts   = array();
		if ( $ids ) {
			$posts = get_posts( array(
				'post_type'              => 'artiey_artist',
				'post_status'            => 'publish',
				'post__in'               => $ids,
				'orderby'                => 'post__in',
				'numberposts'            => $limit,
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			) );
		}
		foreach ( (array) $posts as $post ) {
			$items[] = array(
				'title' => get_the_title( $post ),
				'url'   => get_permalink( $post ),
				'image' => get_the_post_thumbnail_url( $post, 'medium' ) ?: (string) get_post_meta( $post->ID, '_aa_avatar_url', true ),
				'sub'   => (string) get_post_meta( $post->ID, '_aa_name_en', true ),
				'meta'  => 'هنرمند همکار',
			);
			$seen[ 'post-' . (int) $post->ID ] = true;
		}

		$gallery_row = self::coa_gallery_id( $g['id'] );
		$artists      = self::art_table( 'artists' );
		$artworks     = self::art_table( 'artworks' );
		if ( $gallery_row && $artists && $artworks && count( $items ) < $limit ) {
			$rows = self::related_rows( 'artists-' . $gallery_row, static function () use ( $gallery_row, $artists, $artworks ) {
				global $wpdb;
				$has_cpt_link  = self::column_exists( $artists, 'cpt_post_id' );
				$has_avatar_id = self::column_exists( $artists, 'avatar_id' );
				$cpt_select   = $has_cpt_link ? 'ar.cpt_post_id AS artist_post_id' : '0 AS artist_post_id';
				$avatar_select = $has_avatar_id ? 'ar.avatar_id AS artist_avatar_id' : '0 AS artist_avatar_id';
				$sql = "SELECT ar.id, ar.display_name, ar.slug, ar.avatar_url, {$cpt_select}, {$avatar_select}, COUNT(DISTINCT aw.id) AS artwork_count
					FROM `{$artists}` ar INNER JOIN `{$artworks}` aw ON aw.artist_id = ar.id
					WHERE aw.gallery_id = %d AND aw.status IN ('published','approved','active') AND ar.status = 'active'
					GROUP BY ar.id, ar.display_name, ar.slug, ar.avatar_url, artist_post_id, artist_avatar_id
					ORDER BY artwork_count DESC, ar.display_name ASC LIMIT 60";
				return $wpdb->get_results( $wpdb->prepare( $sql, $gallery_row ) ) ?: array();
			} );
			$prime_ids = array();
			foreach ( $rows as $row ) {
				$prime_ids[] = absint( $row->artist_post_id ?? 0 );
				$prime_ids[] = absint( $row->artist_avatar_id ?? 0 );
			}
			self::prime_post_caches( $prime_ids );
			foreach ( $rows as $row ) {
				$post_id = absint( $row->artist_post_id ?? 0 );
				$key     = $post_id ? 'post-' . $post_id : 'coa-' . absint( $row->id );
				if ( isset( $seen[ $key ] ) || count( $items ) >= $limit ) { continue; }
				$post = $post_id ? get_post( $post_id ) : null;
				if ( $post && 'artiey_artist' === $post->post_type && 'publish' === $post->post_status ) {
					$url   = get_permalink( $post );
					$title = get_the_title( $post );
					$image = get_the_post_thumbnail_url( $post, 'medium' ) ?: ( ! empty( $row->artist_avatar_id ) ? wp_get_attachment_image_url( absint( $row->artist_avatar_id ), 'medium' ) : (string) ( $row->avatar_url ?? '' ) );
				} else {
					$url   = ! empty( $row->slug ) ? ( class_exists( '\\WPArtCOA\\ContentSync' ) ? \WPArtCOA\ContentSync::artist_url( (string) $row->slug ) : home_url( '/artists/' . rawurlencode( (string) $row->slug ) . '/' ) ) : '';
					$title = (string) ( $row->display_name ?? '' );
					$image = ! empty( $row->artist_avatar_id ) ? wp_get_attachment_image_url( absint( $row->artist_avatar_id ), 'medium' ) : (string) ( $row->avatar_url ?? '' );
				}
				if ( '' === $url || '' === $title ) { continue; }
				$items[] = array( 'title' => $title, 'url' => $url, 'image' => $image, 'sub' => '', 'meta' => number_format_i18n( (int) ( $row->artwork_count ?? 0 ) ) . ' اثر ثبت‌شده' );
				$seen[ $key ] = true;
			}
		}
		return self::related_section( 'artists', 'هنرمندان مرتبط با گالری', $items, 'artist', $extras );
	}

	private static function s_curators( $g, $extras = array() ) {
		$gallery_row = self::coa_gallery_id( $g['id'] );
		$curators    = self::art_table( 'curators' );
		$exhibitions = self::art_table( 'exhibitions' );
		if ( ! $gallery_row || ! $curators || ! $exhibitions ) { return ''; }
		$limit = self::related_limit( $extras );
		$rows  = self::related_rows( 'curators-' . $gallery_row, static function () use ( $gallery_row, $curators, $exhibitions ) {
			global $wpdb;
			$has_avatar_id = self::column_exists( $curators, 'avatar_id' );
			$avatar_select = $has_avatar_id ? 'c.avatar_id AS curator_avatar_id' : '0 AS curator_avatar_id';
			$sql = "SELECT c.id, c.display_name, c.slug, c.avatar_url, {$avatar_select}, c.affiliation, c.specialties,
				COUNT(DISTINCT e.id) AS exhibition_count
				FROM `{$exhibitions}` e INNER JOIN `{$curators}` c ON c.id = e.curator_id
				WHERE e.gallery_id = %d AND e.status IN ('active','published') AND c.status = 'active'
				GROUP BY c.id, c.display_name, c.slug, c.avatar_url, curator_avatar_id, c.affiliation, c.specialties
				ORDER BY exhibition_count DESC, c.display_name ASC LIMIT 60";
			return $wpdb->get_results( $wpdb->prepare( $sql, $gallery_row ) ) ?: array();
		} );
		self::prime_post_caches( array_map( static function ( $row ) { return absint( $row->curator_avatar_id ?? 0 ); }, array_slice( $rows, 0, $limit ) ) );
		$items = array();
		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			if ( empty( $row->slug ) || empty( $row->display_name ) ) { continue; }
			$image = ! empty( $row->curator_avatar_id ) ? wp_get_attachment_image_url( absint( $row->curator_avatar_id ), 'medium' ) : (string) ( $row->avatar_url ?? '' );
			$sub   = (string) ( $row->specialties ?? '' );
			if ( '' === $sub ) { $sub = (string) ( $row->affiliation ?? '' ); }
			$items[] = array(
				'title' => (string) $row->display_name,
				'url'   => home_url( '/art-coa/curator/' . rawurlencode( (string) $row->slug ) . '/' ),
				'image' => $image,
				'sub'   => $sub,
				'meta'  => number_format_i18n( (int) ( $row->exhibition_count ?? 0 ) ) . ' نمایشگاه کیوریت‌شده',
			);
		}
		return self::related_section( 'curators', 'کیوریتورهای همکار', $items, 'curator', $extras );
	}

	private static function s_collectors( $g, $extras = array() ) {
		$gallery_row = self::coa_gallery_id( $g['id'] );
		$collectors  = self::art_table( 'collectors' );
		$artworks    = self::art_table( 'artworks' );
		if ( ! $gallery_row || ! $collectors || ! $artworks ) { return ''; }
		$auctions = class_exists( '\\WPArtCOA\\Auction' ) && method_exists( '\\WPArtCOA\\Auction', 'listings_table' ) ? \WPArtCOA\Auction::listings_table() : '';
		$has_auctions = $auctions && self::table_exists( $auctions );
		$limit = self::related_limit( $extras );
		$rows  = self::related_rows( 'collectors-' . $gallery_row, static function () use ( $gallery_row, $collectors, $artworks, $auctions, $has_auctions ) {
			global $wpdb;
			$source = "SELECT current_owner_id AS collector_id, id AS artwork_id FROM `{$artworks}` WHERE gallery_id = %d AND current_owner_type = 'collector' AND current_owner_id > 0 AND status IN ('published','approved','active')";
			$params = array( $gallery_row );
			if ( $has_auctions ) {
				$source .= " UNION SELECT au.collector_id, au.artwork_id FROM `{$auctions}` au INNER JOIN `{$artworks}` ax ON ax.id = au.artwork_id WHERE ax.gallery_id = %d AND au.status IN ('active','scheduled')";
				$params[] = $gallery_row;
			}
			$has_avatar_id = self::column_exists( $collectors, 'avatar_id' );
			$avatar_select = $has_avatar_id ? 'c.avatar_id AS collector_avatar_id' : '0 AS collector_avatar_id';
			$sql = "SELECT c.id, c.display_name, c.slug, c.collection_name, c.avatar_url, {$avatar_select}, COUNT(DISTINCT rel.artwork_id) AS artwork_count
				FROM ({$source}) rel INNER JOIN `{$collectors}` c ON c.id = rel.collector_id
				WHERE c.status = 'active' AND c.is_public = 1
				GROUP BY c.id, c.display_name, c.slug, c.collection_name, c.avatar_url, collector_avatar_id
				ORDER BY artwork_count DESC, c.display_name ASC LIMIT 60";
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) ?: array();
		} );
		self::prime_post_caches( array_map( static function ( $row ) { return absint( $row->collector_avatar_id ?? 0 ); }, array_slice( $rows, 0, $limit ) ) );
		$items = array();
		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			if ( empty( $row->slug ) || empty( $row->display_name ) ) { continue; }
			$image = ! empty( $row->collector_avatar_id ) ? wp_get_attachment_image_url( absint( $row->collector_avatar_id ), 'medium' ) : (string) ( $row->avatar_url ?? '' );
			$items[] = array(
				'title' => (string) $row->display_name,
				'url'   => home_url( '/art-coa/collector/' . rawurlencode( (string) $row->slug ) . '/' ),
				'image' => $image,
				'sub'   => (string) ( $row->collection_name ?? '' ),
				'meta'  => number_format_i18n( (int) ( $row->artwork_count ?? 0 ) ) . ' اثر مرتبط',
				'badge' => 'مجموعه‌دار عمومی',
			);
		}
		return self::related_section( 'collectors', 'مجموعه‌داران مرتبط', $items, 'collector', $extras );
	}

	private static function column_exists( $table, $column ) {
		global $wpdb;
		if ( ! is_string( $table ) || '' === $table || ! preg_match( '/^[A-Za-z0-9_]+$/', (string) $column ) ) { return false; }
		$key = 'column:' . $table . ':' . $column;
		if ( array_key_exists( $key, self::$schema_cache ) ) { return self::$schema_cache[ $key ]; }
		self::$schema_cache[ $key ] = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return self::$schema_cache[ $key ];
	}

	private static function table_exists( $table ) {
		global $wpdb;
		if ( ! is_string( $table ) || '' === $table || ! method_exists( $wpdb, 'esc_like' ) ) { return false; }
		$key = 'table:' . $table;
		if ( array_key_exists( $key, self::$schema_cache ) ) { return self::$schema_cache[ $key ]; }
		self::$schema_cache[ $key ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
		return self::$schema_cache[ $key ];
	}

	private static function s_auctions( $g, $extras = array() ) {
		$gallery_row = self::coa_gallery_id( $g['id'] );
		$artworks    = self::art_table( 'artworks' );
		$artists     = self::art_table( 'artists' );
		if ( ! $gallery_row || ! $artworks || ! class_exists( '\\WPArtCOA\\Auction' ) || ! method_exists( '\\WPArtCOA\\Auction', 'listings_table' ) ) { return ''; }
		$auctions = \WPArtCOA\Auction::listings_table();
		if ( ! self::table_exists( $auctions ) ) { return ''; }
		$limit = self::related_limit( $extras );
		$rows  = self::related_rows( 'auctions-' . $gallery_row, static function () use ( $gallery_row, $artworks, $artists, $auctions ) {
			global $wpdb;
			$join_artist = $artists ? " LEFT JOIN `{$artists}` ar ON ar.id = a.artist_id " : '';
			$artist_cols = $artists ? 'ar.display_name AS artist_name' : "'' AS artist_name";
			$image_select = self::column_exists( $artworks, 'image_id' ) ? 'a.image_id AS artwork_image_id' : '0 AS artwork_image_id';
			$sql = "SELECT au.id, au.lot_number, au.auction_house, au.current_bid, au.start_price, au.currency, au.bid_count,
				au.ends_at_utc, au.status, a.title AS artwork_title, a.image_url, {$image_select}, {$artist_cols}
				FROM `{$auctions}` au INNER JOIN `{$artworks}` a ON a.id = au.artwork_id {$join_artist}
				WHERE a.gallery_id = %d AND a.status IN ('published','approved','active','sold') AND au.status IN ('active','scheduled','ended','ended_reserve','sold')
				ORDER BY CASE WHEN au.status = 'active' THEN 0 WHEN au.status = 'scheduled' THEN 1 ELSE 2 END ASC, au.ends_at_utc DESC, au.id DESC LIMIT 60";
			return $wpdb->get_results( $wpdb->prepare( $sql, $gallery_row ) ) ?: array();
		} );
		self::prime_post_caches( array_map( static function ( $row ) { return absint( $row->artwork_image_id ?? 0 ); }, array_slice( $rows, 0, $limit ) ) );
		$items = array();
		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			$title = (string) ( $row->artwork_title ?? '' );
			if ( '' === $title ) { continue; }
			$image = ! empty( $row->artwork_image_id ) ? wp_get_attachment_image_url( absint( $row->artwork_image_id ), 'medium' ) : (string) ( $row->image_url ?? '' );
			$has_bid    = null !== ( $row->current_bid ?? null );
			$price      = $has_bid ? $row->current_bid : $row->start_price;
			$price_text = class_exists( '\\WPArtCOA\\Shop' ) ? \WPArtCOA\Shop::format_price( $price, (string) ( $row->currency ?? 'IRR' ) ) : number_format_i18n( (float) $price ) . ' ' . (string) ( $row->currency ?? '' );
			$price_label = 'sold' === (string) $row->status ? 'قیمت نهایی' : ( $has_bid ? 'آخرین پیشنهاد' : 'قیمت پایه' );
			$meta = '';
			if ( ! empty( $row->artist_name ) ) { $meta = 'هنرمند: ' . (string) $row->artist_name; }
			if ( ! empty( $row->auction_house ) ) { $meta .= ( '' !== $meta ? ' • ' : '' ) . 'حراج‌گزار: ' . (string) $row->auction_house; }
			if ( ! empty( $row->bid_count ) ) { $meta .= ( '' !== $meta ? ' • ' : '' ) . number_format_i18n( (int) $row->bid_count ) . ' پیشنهاد'; }
			$end_date = ! empty( $row->ends_at_utc ) ? Artiey_AET_Jalali::format_long( substr( (string) $row->ends_at_utc, 0, 10 ) ) : '';
			if ( $end_date ) { $meta .= ( '' !== $meta ? ' • ' : '' ) . 'پایان: ' . $end_date; }
			$status_labels = array( 'active' => 'در حال برگزاری', 'scheduled' => 'به‌زودی', 'sold' => 'فروخته شد', 'ended' => 'پایان یافته', 'ended_reserve' => 'پایان یافته' );
			$items[] = array(
				'title' => $title,
				'url'   => home_url( '/art-coa/auction/' . absint( $row->id ) . '/' ),
				'image' => $image,
				'sub'   => ( ! empty( $row->lot_number ) ? 'شماره لات ' . (string) $row->lot_number . ' • ' : '' ) . $price_label . ': ' . $price_text,
				'meta'  => $meta,
				'badge' => $status_labels[ (string) $row->status ] ?? 'حراج',
			);
		}
		return self::related_section( 'auctions', 'حراج‌های مرتبط با آثار گالری', $items, 'auction', $extras );
	}

	private static function s_exhibitions( $g ) {
		return Artiey_AET_Exhibition::render_groups(
			Artiey_AET_Exhibition::for_gallery( $g['id'] ),
			array( 'heading' => 'نمایشگاه‌های جاری و پیش‌رو', 'states' => array( 'now', 'upcoming' ) )
		);
	}

	private static function s_past_exhibitions( $g ) {
		return Artiey_AET_Exhibition::render_groups(
			Artiey_AET_Exhibition::for_gallery( $g['id'] ),
			array( 'heading' => 'پوستر نمایشگاه‌های برگزارشده', 'states' => array( 'past' ) )
		);
	}

	/* ---------------------------------------------------------------- */
	/* Public renderers                                                  */
	/* ---------------------------------------------------------------- */

	/**
	 * @param int      $id         Gallery post ID.
	 * @param string[] $sections   Section keys (see ALL_SECTIONS), rendered in canonical order.
	 * @param bool     $show_title Print the Persian name as heading.
	 */
	/** Add newly introduced relationship sections to widgets that still hold the old full-default selection. */
	public static function upgrade_default_sections( $sections ) {
		if ( ! is_array( $sections ) ) {
			return $sections;
		}
		$legacy_defaults = array( 'header', 'short_bio', 'bio', 'details', 'contact', 'hours', 'map', 'photos', 'artists', 'exhibitions', 'past_exhibitions' );
		$selected = array_values( array_intersect( self::ALL_SECTIONS, $sections ) );
		if ( count( $selected ) === count( $legacy_defaults ) && empty( array_diff( $legacy_defaults, $selected ) ) ) {
			return self::ALL_SECTIONS;
		}
		return $selected;
	}

	public static function render_profile( $id, $sections = null, $show_title = true, $extras = array() ) {
		$id = (int) $id;
		if ( ! $id || 'artiey_gallery' !== get_post_type( $id ) ) {
			return '';
		}
		$sections = is_array( $sections ) ? array_values( array_intersect( self::ALL_SECTIONS, $sections ) ) : self::ALL_SECTIONS;
		if ( ! $sections ) {
			return '';
		}
		wp_enqueue_style( 'artiey-aet-profile' );

		$g    = self::get( $id );
		$html = in_array( 'header', $sections, true ) ? Artiey_AET_Statistics::profile_strip( 'artiey_gallery', $id ) : '';
		foreach ( self::ALL_SECTIONS as $key ) {
			if ( ! in_array( $key, $sections, true ) ) {
				continue;
			}
			switch ( $key ) {
				case 'header':
					$html .= self::s_header( $g, $show_title );
					break;
				case 'short_bio':
					$html .= self::s_short_bio( $g );
					break;
				case 'bio':
					$html .= self::s_bio( $g );
					break;
				case 'details':
					$html .= self::s_details( $g );
					break;
				case 'contact':
					$html .= self::s_contact( $g );
					break;
				case 'hours':
					$html .= self::s_hours( $g );
					break;
				case 'map':
					$html .= self::s_map( $g );
					break;
				case 'photos':
					$html .= self::s_photos( $g );
					break;
				case 'artists':
					$html .= self::s_artists( $g, $extras );
					break;
				case 'curators':
					$html .= self::s_curators( $g, $extras );
					break;
				case 'collectors':
					$html .= self::s_collectors( $g, $extras );
					break;
				case 'auctions':
					$html .= self::s_auctions( $g, $extras );
					break;
				case 'exhibitions':
					$html .= self::s_exhibitions( $g );
					break;
				case 'past_exhibitions':
					$html .= self::s_past_exhibitions( $g );
					break;
			}
		}
		return '<div class="aetp" dir="rtl">' . $html . '</div>';
	}

	public static function filter_content( $content ) {
		if ( ! is_singular( 'artiey_gallery' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! Artiey_AET_Profile_Guard::auto_allowed( get_the_ID() ) ) {
			return $content;
		}
		if ( ! Artiey_AET_Settings::get( 'profile_auto' ) ) {
			return $content;
		}
		$wanted = (array) Artiey_AET_Settings::get( 'profile_sections' );
		$id     = get_the_ID();

		$before = array_values( array_intersect( array( 'header', 'short_bio' ), $wanted ) );
		$after  = array_values( array_intersect( array( 'details', 'contact', 'hours', 'map', 'photos', 'artists', 'exhibitions', 'past_exhibitions' ), $wanted ) );

		$out = '';
		if ( $before ) {
			$out .= self::render_profile( $id, $before, false );
		}
		$out .= $content;
		if ( $after ) {
			$out .= self::render_profile( $id, $after, false );
		}
		return $out;
	}

	/* ---------------------------------------------------------------- */
	/* Shortcodes                                                        */
	/* ---------------------------------------------------------------- */

	public static function shortcode_profile( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'         => 0,
				'sections'   => '',
				'show_title' => 'yes',
			),
			(array) $atts,
			'artiey_gallery_profile'
		);
		$id = absint( $atts['id'] );
		if ( ! $id ) {
			$id = (int) get_the_ID();
		}
		$sections = null;
		if ( '' !== trim( (string) $atts['sections'] ) ) {
			$sections = array_intersect( self::ALL_SECTIONS, array_map( 'trim', explode( ',', (string) $atts['sections'] ) ) );
		}
		Artiey_AET_Profile_Guard::mark( $id );
		return self::render_profile( $id, $sections, 'no' !== $atts['show_title'] );
	}

	public static function shortcode_field( $atts ) {
		$atts = shortcode_atts(
			array(
				'field' => '',
				'id'    => 0,
				'link'  => 'yes',
			),
			(array) $atts,
			'artiey_gallery_field'
		);
		$id = absint( $atts['id'] ) ? absint( $atts['id'] ) : (int) get_the_ID();
		if ( ! $id || 'artiey_gallery' !== get_post_type( $id ) ) {
			return '';
		}
		$g    = self::get( $id );
		$link = ( 'no' !== $atts['link'] );

		switch ( $atts['field'] ) {
			case 'name':
				return esc_html( $g['name'] );
			case 'name_en':
			case 'city':
			case 'address':
			case 'short_bio':
			case 'hours_note':
				return esc_html( $g[ $atts['field'] ] );
			case 'founded':
				return $g['founded'] ? esc_html( self::fa( $g['founded'] ) ) : '';
			case 'phone':
			case 'mobile':
				$list = ( 'phone' === $atts['field'] ) ? $g['phones'] : $g['mobiles'];
				$out  = array();
				foreach ( $list as $n ) {
					$out[] = $link ? '<a href="' . esc_url( self::tel_href( $n ) ) . '" dir="ltr">' . esc_html( self::fa( $n ) ) . '</a>' : esc_html( self::fa( $n ) );
				}
				return implode( '، ', $out );
			case 'whatsapp':
				if ( ! $g['whatsapp'] ) {
					return '';
				}
				return $link ? '<a href="' . esc_url( self::wa_href( $g['whatsapp'] ) ) . '" target="_blank" rel="noopener" dir="ltr">' . esc_html( self::fa( $g['whatsapp'] ) ) . '</a>' : esc_html( self::fa( $g['whatsapp'] ) );
			case 'instagram':
				if ( ! $g['instagram'] ) {
					return '';
				}
				return $link ? '<a href="' . esc_url( 'https://instagram.com/' . $g['instagram'] ) . '" target="_blank" rel="noopener" dir="ltr">@' . esc_html( $g['instagram'] ) . '</a>' : '@' . esc_html( $g['instagram'] );
			case 'telegram':
				if ( ! $g['telegram'] ) {
					return '';
				}
				return $link ? '<a href="' . esc_url( 'https://t.me/' . $g['telegram'] ) . '" target="_blank" rel="noopener" dir="ltr">@' . esc_html( $g['telegram'] ) . '</a>' : '@' . esc_html( $g['telegram'] );
			case 'email':
				if ( ! $g['email'] ) {
					return '';
				}
				return $link ? '<a href="' . esc_url( 'mailto:' . $g['email'] ) . '" dir="ltr">' . esc_html( $g['email'] ) . '</a>' : esc_html( $g['email'] );
			case 'website':
				if ( ! $g['website'] ) {
					return '';
				}
				return $link ? '<a href="' . esc_url( $g['website'] ) . '" target="_blank" rel="noopener" dir="ltr">' . esc_html( $g['website'] ) . '</a>' : esc_html( $g['website'] );
			case 'status':
				$st = self::status( $g );
				return esc_html( $st['label'] );
			case 'map_link':
				return esc_url( self::map_link( $g ) );
			case 'styles':
				$terms = get_the_terms( $id, 'artiey_style' );
				if ( ! $terms || is_wp_error( $terms ) ) {
					return '';
				}
				return esc_html( implode( '، ', wp_list_pluck( $terms, 'name' ) ) );
		}
		return '';
	}

	/** Gallery card (directory / related lists). */
	public static function card( $p ) {
		$g     = self::get( $p->ID );
		$cover = $g['cover'] ? wp_get_attachment_image( $g['cover'], 'medium_large', false, array( 'loading' => 'lazy', 'alt' => '' ) ) : '';
		$c     = Artiey_AET_Exhibition::counts( Artiey_AET_Exhibition::for_gallery( $p->ID ) );

		$h  = '<a class="aetp-gcard" href="' . esc_url( get_permalink( $p ) ) . '">';
		$h .= '<span class="aetp-gcard-cover">' . $cover . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$h .= '<span class="aetp-gcard-body">';
		if ( has_post_thumbnail( $p ) ) {
			$h .= '<span class="aetp-gcard-logo">' . get_the_post_thumbnail( $p, 'thumbnail', array( 'alt' => $g['name'], 'loading' => 'lazy' ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$h .= '<span class="aetp-card-title">' . esc_html( $g['name'] ) . '</span>';
		if ( $g['name_en'] ) {
			$h .= '<span class="aetp-card-sub" dir="ltr">' . esc_html( $g['name_en'] ) . '</span>';
		}
		if ( $g['city'] ) {
			$h .= '<span class="aetp-card-sub">' . esc_html( $g['city'] ) . '</span>';
		}
		$h .= '<span class="aetp-gcard-foot">' . self::badge( $g );
		if ( $c['now'] ) {
			$h .= '<span class="aetp-mini aetp-mini-now">' . esc_html( self::fa( $c['now'] ) . ' نمایشگاه جاری' ) . '</span>';
		} elseif ( $c['upcoming'] ) {
			$h .= '<span class="aetp-mini aetp-mini-up">' . esc_html( self::fa( $c['upcoming'] ) . ' نمایشگاه پیش‌رو' ) . '</span>';
		}
		$h .= '</span></span></a>';
		return $h;
	}
}
