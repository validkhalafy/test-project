<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exhibitions: status (now / upcoming / past), poster cards, grouped tabs, single profile.
 */
final class Artiey_AET_Exhibition {

	const STATES = array( 'now', 'upcoming', 'past' );
	private static $curator_profiles_cache = array();

	public static function init() {
		add_shortcode( 'artiey_related_exhibitions', array( __CLASS__, 'shortcode_related' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
	}

	public static function label( $state ) {
		$map = array(
			'now'      => 'در حال برگزاری',
			'upcoming' => 'به‌زودی',
			'past'     => 'پایان‌یافته',
		);
		return isset( $map[ $state ] ) ? $map[ $state ] : '';
	}

	private static function fa( $v ) {
		return Artiey_AET_Jalali::to_persian_digits( (string) $v );
	}

	/** @return string now|upcoming|past|'' (no date) */
	public static function state( $post_id, $today = '' ) {
		$today = $today ? $today : current_time( 'Y-m-d' );
		$s     = get_post_meta( $post_id, '_ae_start', true );
		$e     = get_post_meta( $post_id, '_ae_end', true );
		if ( ! $s ) {
			return '';
		}
		$e = $e ? $e : $s;
		if ( $e < $today ) {
			return 'past';
		}
		if ( $s > $today ) {
			return 'upcoming';
		}
		return 'now';
	}

	public static function dates_text( $post_id ) {
		$from = Artiey_AET_Jalali::format_long( get_post_meta( $post_id, '_ae_start', true ) );
		$to   = Artiey_AET_Jalali::format_long( get_post_meta( $post_id, '_ae_end', true ) );
		if ( ! $from ) {
			return '';
		}
		return ( $to && $to !== $from ) ? $from . ' تا ' . $to : $from;
	}

	/* ---------------------------------------------------------------- */
	/* Data                                                              */
	/* ---------------------------------------------------------------- */

	private static function fetch( $meta_query, $limit = 80 ) {
		return get_posts(
			array(
				'post_type'   => 'artiey_exhibition',
				'post_status' => 'publish',
				'numberposts' => $limit,
				'meta_key'    => '_ae_start',
				'orderby'     => 'meta_value',
				'meta_type'   => 'DATE',
				'order'       => 'DESC',
				'meta_query'  => $meta_query,
			)
		);
	}

	public static function group( $posts ) {
		$today  = current_time( 'Y-m-d' );
		$groups = array( 'now' => array(), 'upcoming' => array(), 'past' => array() );
		foreach ( $posts as $p ) {
			$st = self::state( $p->ID, $today );
			if ( $st ) {
				$groups[ $st ][] = $p;
			}
		}
		// Now: ending soonest first. Upcoming: starting soonest first. Past: newest first.
		usort(
			$groups['now'],
			function ( $a, $b ) {
				return strcmp( (string) get_post_meta( $a->ID, '_ae_end', true ), (string) get_post_meta( $b->ID, '_ae_end', true ) );
			}
		);
		usort(
			$groups['upcoming'],
			function ( $a, $b ) {
				return strcmp( (string) get_post_meta( $a->ID, '_ae_start', true ), (string) get_post_meta( $b->ID, '_ae_start', true ) );
			}
		);
		// Merged sources (WordPress meta + COA artwork bridge) are not guaranteed to arrive pre-sorted.
		usort(
			$groups['past'],
			function ( $a, $b ) {
				$by_date = strcmp( (string) get_post_meta( $b->ID, '_ae_start', true ), (string) get_post_meta( $a->ID, '_ae_start', true ) );
				return 0 !== $by_date ? $by_date : ( (int) $b->ID <=> (int) $a->ID );
			}
		);
		return $groups;
	}

	public static function for_gallery( $gallery_id ) {
		return self::group( self::fetch( array( array( 'key' => '_ae_gallery', 'value' => (int) $gallery_id, 'type' => 'NUMERIC' ) ) ) );
	}

	public static function for_artist( $artist_id ) {
		return self::group( self::fetch( array( array( 'key' => '_ae_artists', 'value' => ';i:' . (int) $artist_id . ';', 'compare' => 'LIKE' ) ) ) );
	}

	public static function counts( $groups ) {
		return array(
			'now'      => count( $groups['now'] ),
			'upcoming' => count( $groups['upcoming'] ),
			'past'     => count( $groups['past'] ),
		);
	}

	/* ---------------------------------------------------------------- */
	/* Cards and groups                                                  */
	/* ---------------------------------------------------------------- */

	/**
	 * Fetch active curator profiles in one bounded query and cache both hits and misses.
	 * Curators live in the COA relation table rather than a WordPress post type.
	 */
	private static function curator_profiles( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( ! $ids || ! class_exists( '\\WPArtCOA\\Database' ) ) {
			return array();
		}
		$missing = array();
		foreach ( $ids as $id ) {
			if ( ! array_key_exists( $id, self::$curator_profiles_cache ) ) {
				$missing[] = $id;
			}
		}
		if ( $missing ) {
			global $wpdb;
			$table = \WPArtCOA\Database::table( 'curators' );
			$placeholders = implode( ', ', array_fill( 0, count( $missing ), '%d' ) );
			$sql = "SELECT id, display_name, slug FROM {$table} WHERE status = 'active' AND id IN ({$placeholders})";
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $missing ) );
			foreach ( $missing as $id ) {
				self::$curator_profiles_cache[ $id ] = null;
			}
			foreach ( (array) $rows as $row ) {
				$id = absint( $row->id ?? 0 );
				$slug = sanitize_title( (string) ( $row->slug ?? '' ) );
				if ( $id && $slug && trim( (string) ( $row->display_name ?? '' ) ) !== '' ) {
					self::$curator_profiles_cache[ $id ] = array(
						'name' => (string) $row->display_name,
						'url'  => home_url( '/art-coa/curator/' . rawurlencode( $slug ) . '/' ),
					);
				}
			}
		}
		$out = array();
		foreach ( $ids as $id ) {
			if ( is_array( self::$curator_profiles_cache[ $id ] ?? null ) ) {
				$out[ $id ] = self::$curator_profiles_cache[ $id ];
			}
		}
		return $out;
	}

	/**
	 * Poster card.
	 *
	 * @param WP_Post $p
	 * @param array   $o show_gallery (bool), show_curator (bool)
	 */
	public static function card( $p, $o = array() ) {
		$o     = wp_parse_args( $o, array( 'show_gallery' => false, 'show_curator' => false, 'curator_profiles' => array() ) );
		$state = self::state( $p->ID );
		$type  = get_post_meta( $p->ID, '_ae_type', true );

		$img = has_post_thumbnail( $p )
			? get_the_post_thumbnail( $p, 'medium_large', array( 'loading' => 'lazy', 'alt' => get_the_title( $p ) ) )
			: '<span class="aetp-noposter">' . esc_html( Artiey_AET_Text::substr( get_the_title( $p ), 0, 2 ) ) . '</span>';

		$curator_id = absint( get_post_meta( $p->ID, '_ae_curator_id', true ) );
		$curator = trim( (string) get_post_meta( $p->ID, '_ae_curator', true ) );
		$profiles = is_array( $o['curator_profiles'] ) ? $o['curator_profiles'] : array();
		if ( ! empty( $o['show_curator'] ) && $curator_id && ! array_key_exists( $curator_id, $profiles ) ) {
			$profiles = self::curator_profiles( array( $curator_id ) );
		}
		$curator_profile = $curator_id && isset( $profiles[ $curator_id ] ) ? $profiles[ $curator_id ] : null;
		if ( $curator_profile && ! empty( $curator_profile['name'] ) ) {
			$curator = $curator_profile['name'];
		}

		// Keep curator profile navigation outside the exhibition anchor (nested anchors are invalid HTML).
		$h  = ! empty( $o['show_curator'] ) ? '<div class="aetp-exhibition-card-wrap">' : '';
		$h .= '<a class="aetp-card aetp-poster" href="' . esc_url( get_permalink( $p ) ) . '">';
		$h .= '<span class="aetp-card-img aetp-ratio-poster">' . $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $state ) {
			$h .= '<span class="aetp-ribbon aetp-rb-' . esc_attr( $state ) . '">' . esc_html( self::label( $state ) ) . '</span>';
		}
		$h .= '</span>';
		$h .= '<span class="aetp-card-title">' . esc_html( get_the_title( $p ) ) . '</span>';

		if ( $o['show_gallery'] ) {
			$gid = (int) get_post_meta( $p->ID, '_ae_gallery', true );
			if ( $gid && 'publish' === get_post_status( $gid ) && 'artiey_gallery' === get_post_type( $gid ) ) {
				$h .= '<span class="aetp-card-sub aetp-card-gal">' . esc_html( get_the_title( $gid ) ) . '</span>';
			}
		}
		if ( $o['show_curator'] && $curator ) {
			$h .= '<span class="aetp-card-sub aetp-card-curator">کیوریتور: ' . esc_html( $curator ) . '</span>';
		}
		$dt = self::dates_text( $p->ID );
		if ( $dt ) {
			$h .= '<span class="aetp-card-sub">' . esc_html( $dt ) . '</span>';
		}
		if ( $type ) {
			$h .= '<span class="aetp-card-sub">' . esc_html( $type ) . '</span>';
		}
		$h .= '</a>';
		if ( ! empty( $o['show_curator'] ) && $curator_profile && ! empty( $curator_profile['url'] ) ) {
			$h .= '<a class="aetp-card-curator-link" href="' . esc_url( $curator_profile['url'] ) . '">مشاهدهٔ پروفایل کیوریتور</a>';
		}
		if ( ! empty( $o['show_curator'] ) ) {
			$h .= '</div>';
		}
		return $h;
	}

	/**
	 * Render grouped exhibitions.
	 *
	 * @param array $groups now/upcoming/past => WP_Post[]
	 * @param array $o      states (array), mode (tabs|sections), show_gallery/show_curator (bool), columns (int), heading (string)
	 */
	public static function render_groups( $groups, $o = array() ) {
		$o = wp_parse_args(
			$o,
			array(
				'states'       => self::STATES,
				'mode'         => 'tabs',
				'show_gallery' => false,
				'show_curator' => false,
				'columns'      => 0,
				'heading'      => 'نمایشگاه‌ها',
			)
		);

		$visible = array();
		foreach ( self::STATES as $st ) {
			if ( in_array( $st, (array) $o['states'], true ) && ! empty( $groups[ $st ] ) ) {
				$visible[ $st ] = $groups[ $st ];
			}
		}
		if ( ! $visible ) {
			return '';
		}

		wp_enqueue_style( 'artiey-aet-profile' );
		wp_enqueue_script( 'artiey-aet-profile' );

		$curator_profiles = array();
		if ( $o['show_curator'] ) {
			$curator_ids = array();
			foreach ( $visible as $list ) {
				foreach ( $list as $post ) {
					$curator_id = absint( get_post_meta( $post->ID, '_ae_curator_id', true ) );
					if ( $curator_id ) { $curator_ids[] = $curator_id; }
				}
			}
			$curator_profiles = self::curator_profiles( $curator_ids );
		}
		$grid = function ( $list ) use ( $o, $curator_profiles ) {
			$cls   = $o['columns'] ? 'aetp-grid' : 'aetp-cards';
			$style = $o['columns'] ? ' style="--aetp-cols:' . (int) $o['columns'] . '"' : '';
			$h     = '<div class="' . $cls . '"' . $style . '>';
			foreach ( $list as $p ) {
				$h .= self::card( $p, array( 'show_gallery' => $o['show_gallery'], 'show_curator' => $o['show_curator'], 'curator_profiles' => $curator_profiles ) );
			}
			return $h . '</div>';
		};

		$h = '<section class="aetp-sec aetp-exhibitions">';
		if ( $o['heading'] ) {
			$h .= '<h3>' . esc_html( $o['heading'] ) . '</h3>';
		}

		if ( 'tabs' === $o['mode'] ) {
			$first = key( $visible );
			$h    .= '<div class="aetp-tabs" data-aetp-tabs><div class="aetp-tablist" role="tablist">';
			foreach ( $visible as $st => $list ) {
				$h .= '<button type="button" role="tab" class="aetp-tabbtn aetp-tb-' . esc_attr( $st ) . ( $st === $first ? ' is-active' : '' ) . '" data-t="' . esc_attr( $st ) . '">' . esc_html( self::label( $st ) ) . ' <em>' . esc_html( self::fa( count( $list ) ) ) . '</em></button>';
			}
			$h .= '</div>';
			foreach ( $visible as $st => $list ) {
				$h .= '<div class="aetp-tabpanel' . ( $st === $first ? ' is-active' : '' ) . '" data-t="' . esc_attr( $st ) . '">' . $grid( $list ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			$h .= '</div>';
		} else {
			foreach ( $visible as $st => $list ) {
				$h .= '<h4 class="aetp-sub-h aetp-g-' . esc_attr( $st ) . '">' . esc_html( self::label( $st ) ) . ' <em>' . esc_html( self::fa( count( $list ) ) ) . '</em></h4>' . $grid( $list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		return $h . '</section>';
	}

	/* ---------------------------------------------------------------- */
	/* Shortcode: exhibitions of a gallery or artist                     */
	/* ---------------------------------------------------------------- */

	public static function shortcode_related( $atts ) {
		$atts = shortcode_atts(
			array(
				'gallery' => 0,
				'artist'  => 0,
				'status'  => 'now,upcoming,past',
				'mode'    => 'tabs',
				'columns' => 0,
				'heading' => 'نمایشگاه‌ها',
			),
			(array) $atts,
			'artiey_related_exhibitions'
		);

		$gid = absint( $atts['gallery'] );
		$aid = absint( $atts['artist'] );
		if ( ! $gid && ! $aid ) {
			$cur = (int) get_the_ID();
			if ( 'artiey_gallery' === get_post_type( $cur ) ) {
				$gid = $cur;
			} elseif ( 'artiey_artist' === get_post_type( $cur ) ) {
				$aid = $cur;
			}
		}
		if ( ! $gid && ! $aid ) {
			return '';
		}

		$groups = $gid ? self::for_gallery( $gid ) : self::for_artist( $aid );
		$states = array_values( array_intersect( self::STATES, array_map( 'trim', explode( ',', (string) $atts['status'] ) ) ) );

		$html = self::render_groups(
			$groups,
			array(
				'states'       => $states ? $states : self::STATES,
				'mode'         => ( 'sections' === $atts['mode'] ) ? 'sections' : 'tabs',
				'show_gallery' => (bool) $aid,
				'columns'      => max( 0, min( 6, absint( $atts['columns'] ) ) ),
				'heading'      => sanitize_text_field( $atts['heading'] ),
			)
		);
		return $html ? '<div class="aetp" dir="rtl">' . $html . '</div>' : '';
	}

	/* ---------------------------------------------------------------- */
	/* Single exhibition profile                                         */
	/* ---------------------------------------------------------------- */

	/** Render a single semantic exhibition profile section for Elementor's reusable tab system. */
	public static function render_profile_section( $id, $section, $show_title = true ) {
		$id = absint( $id );
		$section = sanitize_key( (string) $section );
		if ( ! $id || 'artiey_exhibition' !== get_post_type( $id ) ) { return ''; }
		wp_enqueue_style( 'artiey-aet-profile' );
		$title = get_the_title( $id );
		$state = self::state( $id );
		$gid = absint( get_post_meta( $id, '_ae_gallery', true ) );
		$type = get_post_meta( $id, '_ae_type', true );
		$artist_text = trim( (string) get_post_meta( $id, '_ae_artist', true ) );
		$opening = trim( (string) get_post_meta( $id, '_ae_opening', true ) );
		$curator = trim( (string) get_post_meta( $id, '_ae_curator', true ) );
		$admission = trim( (string) get_post_meta( $id, '_ae_admission', true ) );
		$artist_ids = get_post_meta( $id, '_ae_artists', true );
		$artist_ids = is_array( $artist_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $artist_ids ) ) ) ) : array();
		if ( 'header' === $section ) {
			$poster = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'large', array( 'alt' => $title, 'loading' => 'lazy' ) ) : '';
			$html = '<section class="aetp aetp-ex aetp-ex-header" dir="rtl">' . Artiey_AET_Statistics::profile_strip( 'artiey_exhibition', $id );
			if ( $show_title ) { $html .= '<h2 class="aetp-title aetp-ex-title">' . esc_html( $title ) . '</h2>'; }
			if ( $poster ) { $html .= '<div class="aetp-ex-poster">' . $poster; if ( $state ) { $html .= '<span class="aetp-ribbon aetp-rb-' . esc_attr( $state ) . '">' . esc_html( self::label( $state ) ) . '</span>'; } $html .= '</div>'; }
			elseif ( $state ) { $html .= '<span class="aetp-badge aetp-exb-' . esc_attr( $state ) . '">' . esc_html( self::label( $state ) ) . '</span>'; }
			$summary = trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $id ) ) );
			if ( $summary ) { $html .= '<p class="aetp-lead aetp-ex-summary">' . esc_html( $summary ) . '</p>'; }
			return $html . '</section>';
		}
		if ( 'details' === $section ) {
			$rows = array();
			if ( $type ) { $rows['نوع نمایشگاه'] = esc_html( $type ); }
			$dates = self::dates_text( $id ); if ( $dates ) { $rows['تاریخ برگزاری'] = esc_html( $dates ); }
			if ( $opening ) { $rows['افتتاحیه'] = esc_html( $opening ); }
			if ( $curator ) { $rows['کیوریتور'] = esc_html( $curator ); }
			if ( $admission ) { $rows['شرایط بازدید'] = esc_html( $admission ); }
			if ( ! $rows ) { return ''; }
			$html = '<section class="aetp aetp-ex aetp-sec aetp-ex-details" dir="rtl"><h3>جزئیات نمایشگاه</h3><dl class="aetp-dl">';
			foreach ( $rows as $label => $value ) { $html .= '<dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd>'; }
			return $html . '</dl></section>';
		}
		if ( 'artists' === $section ) {
			$items = array();
			foreach ( $artist_ids as $artist_id ) {
				if ( 'publish' !== get_post_status( $artist_id ) || 'artiey_artist' !== get_post_type( $artist_id ) ) { continue; }
				$items[] = '<li class="aetp-ex-artist"><a href="' . esc_url( get_permalink( $artist_id ) ) . '">' . esc_html( get_the_title( $artist_id ) ) . '</a></li>';
			}
			if ( ! $items && $artist_text ) { $items[] = '<li class="aetp-ex-artist">' . esc_html( $artist_text ) . '</li>'; }
			if ( ! $items ) { return ''; }
			return '<section class="aetp aetp-sec aetp-ex-artists" dir="rtl"><h3>هنرمندان شرکت‌کننده</h3><ul class="aetp-list">' . implode( '', $items ) . '</ul></section>';
		}
		if ( 'gallery' === $section ) {
			if ( ! $gid || 'publish' !== get_post_status( $gid ) || 'artiey_gallery' !== get_post_type( $gid ) ) { return ''; }
			$g = Artiey_AET_Gallery::get( $gid );
			if ( ! is_array( $g ) || empty( $g['name'] ) ) { return ''; }
			$html = '<section class="aetp aetp-sec aetp-ex-gallery" dir="rtl"><h3>گالری برگزارکننده</h3><article class="aetp-card aetp-ex-gallery-card">';
			if ( has_post_thumbnail( $gid ) ) { $html .= '<a class="aetp-ex-gallery-image" href="' . esc_url( get_permalink( $gid ) ) . '">' . get_the_post_thumbnail( $gid, 'medium', array( 'loading' => 'lazy' ) ) . '</a>'; }
			$html .= '<div class="aetp-card-body"><h4 class="aetp-card-title"><a href="' . esc_url( get_permalink( $gid ) ) . '">' . esc_html( $g['name'] ) . '</a></h4>';
			if ( ! empty( $g['city'] ) ) { $html .= '<p class="aetp-card-sub">' . esc_html( $g['city'] ) . '</p>'; }
			if ( ! empty( $g['address'] ) ) { $html .= '<p>' . esc_html( $g['address'] ) . '</p>'; $map = Artiey_AET_Gallery::map_link( $g ); if ( $map ) { $html .= '<a class="aetp-pill" href="' . esc_url( $map ) . '" target="_blank" rel="noopener noreferrer">مشاهده روی نقشه</a>'; } }
			return $html . '</div></article></section>';
		}
		if ( 'styles' === $section ) {
			$terms = get_the_terms( $id, 'artiey_style' );
			if ( ! $terms || is_wp_error( $terms ) ) { return ''; }
			$chips = '';
			foreach ( $terms as $term ) { $url = get_term_link( $term ); $chips .= '<span class="aetp-chip">' . esc_html( $term->name ) . '</span>'; }
			return $chips ? '<section class="aetp aetp-sec aetp-ex-styles" dir="rtl"><h3>سبک‌های هنری نمایشگاه</h3><div class="aetp-hero-chips">' . $chips . '</div></section>' : '';
		}
		return '';
	}

	public static function render_profile( $id ) {
		$id = (int) $id;
		if ( ! $id || 'artiey_exhibition' !== get_post_type( $id ) ) {
			return '';
		}
		wp_enqueue_style( 'artiey-aet-profile' );

		$state  = self::state( $id );
		$gid    = (int) get_post_meta( $id, '_ae_gallery', true );
		$type   = get_post_meta( $id, '_ae_type', true );
		$artist = get_post_meta( $id, '_ae_artist', true );
		$open   = get_post_meta( $id, '_ae_opening', true );
		$curator = get_post_meta( $id, '_ae_curator', true );
		$admission = get_post_meta( $id, '_ae_admission', true );
		$arts   = get_post_meta( $id, '_ae_artists', true );
		$dates  = self::dates_text( $id );

		$rows = array();
		if ( $type ) {
			$rows['نوع نمایشگاه'] = esc_html( $type );
		}
		if ( $dates ) {
			$rows['تاریخ برگزاری'] = esc_html( $dates );
		}
		if ( $open ) {
			$rows['افتتاحیه'] = esc_html( $open );
		}
		if ( $curator ) {
			$rows['کیوریتور'] = esc_html( $curator );
		}
		if ( $admission ) {
			$rows['شرایط بازدید'] = esc_html( $admission );
		}

		$names = array();
		if ( is_array( $arts ) ) {
			foreach ( $arts as $aid ) {
				if ( 'publish' === get_post_status( (int) $aid ) ) {
					$names[] = '<a href="' . esc_url( get_permalink( (int) $aid ) ) . '">' . esc_html( get_the_title( (int) $aid ) ) . '</a>';
				}
			}
		}
		if ( $names ) {
			$rows['هنرمندان'] = implode( '، ', $names );
		} elseif ( $artist ) {
			$rows['هنرمند'] = esc_html( $artist );
		}

		if ( $gid && 'publish' === get_post_status( $gid ) ) {
			$g   = Artiey_AET_Gallery::get( $gid );
			$gal = '<a href="' . esc_url( get_permalink( $gid ) ) . '">' . esc_html( $g['name'] ) . '</a>';
			if ( $g['city'] ) {
				$gal .= ' <span class="aetp-muted">— ' . esc_html( $g['city'] ) . '</span>';
			}
			$rows['گالری'] = $gal;
			if ( $g['address'] ) {
				$addr = esc_html( $g['address'] );
				$ml   = Artiey_AET_Gallery::map_link( $g );
				if ( $ml ) {
					$addr .= ' <a class="aetp-pill" href="' . esc_url( $ml ) . '" target="_blank" rel="noopener">نقشه</a>';
				}
				$rows['آدرس'] = $addr;
			}
		}

		$terms = get_the_terms( $id, 'artiey_style' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$chips = '';
			foreach ( $terms as $t ) {
				$chips .= '<a class="aetp-chip" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a> ';
			}
			$rows['سبک هنری'] = $chips;
		}

		$poster = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'large', array( 'alt' => get_the_title( $id ) ) ) : '';

		$h  = '<div class="aetp aetp-ex" dir="rtl">' . Artiey_AET_Statistics::profile_strip( 'artiey_exhibition', $id ) . '<div class="aetp-ex-wrap' . ( $poster ? ' has-poster' : '' ) . '">';
		if ( $poster ) {
			$h .= '<div class="aetp-ex-poster">' . $poster; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $state ) {
				$h .= '<span class="aetp-ribbon aetp-rb-' . esc_attr( $state ) . '">' . esc_html( self::label( $state ) ) . '</span>';
			}
			$h .= '</div>';
		}
		$h .= '<div class="aetp-ex-info">';
		if ( $state && ! $poster ) {
			$h .= '<span class="aetp-badge aetp-exb-' . esc_attr( $state ) . '">' . esc_html( self::label( $state ) ) . '</span>';
		}
		$h .= '<dl class="aetp-dl">';
		foreach ( $rows as $k => $v ) {
			$h .= '<dt>' . esc_html( $k ) . '</dt><dd>' . $v . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$h .= '</dl></div></div></div>';
		return $h;
	}

	public static function filter_content( $content ) {
		if ( ! is_singular( 'artiey_exhibition' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! Artiey_AET_Profile_Guard::auto_allowed( get_the_ID() ) ) {
			return $content;
		}
		if ( ! Artiey_AET_Settings::get( 'exhibition_auto' ) ) {
			return $content;
		}
		return self::render_profile( get_the_ID() ) . $content;
	}
}
