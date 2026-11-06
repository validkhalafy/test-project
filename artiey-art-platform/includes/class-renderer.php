<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the exhibition timeline (shortcode, Elementor widget and REST navigation share this).
 */
final class Artiey_AET_Renderer {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'artiey_timeline', array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
	}

	public static function register_assets() {
		wp_register_style( 'artiey-aet', AET_URL . 'assets/css/timeline.css', array(), AET_VERSION );
		wp_register_script( 'artiey-aet', AET_URL . 'assets/js/timeline.js', array(), AET_VERSION, true );

		// Global look from the settings page. Same specificity as the base rules but
		// printed after them, so per-widget Elementor styles (more specific) still win.
		$css = Artiey_AET_Settings::inline_css();
		if ( $css ) {
			wp_add_inline_style( 'artiey-aet', $css );
		}
	}

	public static function defaults() {
		$o = Artiey_AET_Settings::all();
		return array(
			'days'         => $o['days'],
			'before_days'  => $o['before_days'],
			'start_mode'   => 'today', // today | custom.
			'custom_start' => '',
			'galleries'    => '',
			'max_rows'     => $o['max_rows'],
			'show_nav'     => $o['show_nav'] ? 'yes' : 'no',
			'more_text'    => $o['more_text'],
			'more_url'     => $o['more_url'],
			// Per-widget overrides: '' = follow the global setting, yes / no = force.
			'stripes'         => '',
			'highlight_fri'   => '',
			'highlight_today' => '',
			'status_colors'   => '',
			'show_range'      => '',
			// Elementor-only override; existing shortcodes still inherit global settings.
			'show_gallery_name' => 'no',
			'sub_fields_mode' => 'global',
			'sub_fields'      => array(),
			'sub_sep'         => '',
		);
	}

	/** Effective on/off for a display option: the widget/shortcode override wins over the global setting. */
	private static function flag( $args, $o, $key ) {
		$v = isset( $args[ $key ] ) ? $args[ $key ] : '';
		if ( 'yes' === $v ) {
			return true;
		}
		if ( 'no' === $v ) {
			return false;
		}
		return ! empty( $o[ $key ] );
	}

	/** Whitelist + clamp every argument (used for shortcode, widget and REST input). */
	public static function sanitize_args( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		// Blank values mean "use the global setting".
		foreach ( $raw as $k => $v ) {
			if ( '' === $v || null === $v ) {
				unset( $raw[ $k ] );
			}
		}
		$a = wp_parse_args( $raw, self::defaults() );

		$out                 = array();
		$out['days']         = max( 3, min( 31, absint( $a['days'] ) ) );
		$out['before_days']  = max( 0, min( 60, absint( $a['before_days'] ) ) );
		$out['start_mode']   = in_array( $a['start_mode'], array( 'today', 'custom' ), true ) ? $a['start_mode'] : 'today';
		$out['custom_start'] = sanitize_text_field( (string) $a['custom_start'] );

		$ids              = is_array( $a['galleries'] ) ? $a['galleries'] : explode( ',', (string) $a['galleries'] );
		$ids              = array_filter( array_map( 'absint', $ids ) );
		$out['galleries'] = implode( ',', $ids );

		$out['max_rows']  = max( 1, min( 50, absint( $a['max_rows'] ) ) );
		$nav              = strtolower( (string) $a['show_nav'] );
		$out['show_nav']  = in_array( $nav, array( 'yes', '1', 'true', 'on' ), true ) ? 'yes' : 'no';
		$out['more_text'] = sanitize_text_field( (string) $a['more_text'] );
		$out['more_url']  = esc_url_raw( (string) $a['more_url'] );
		foreach ( array( 'stripes', 'highlight_fri', 'highlight_today', 'status_colors', 'show_range', 'show_gallery_name' ) as $flag ) {
			$val         = strtolower( (string) $a[ $flag ] );
			$out[ $flag ] = in_array( $val, array( 'yes', '1', 'true', 'on' ), true ) ? 'yes' : ( in_array( $val, array( 'no', '0', 'false', 'off' ), true ) ? 'no' : '' );
		}
		// The gallery caption is a strict opt-in; malformed public/REST values must not enable it.
		if ( ! in_array( $out['show_gallery_name'], array( 'yes', 'no' ), true ) ) {
			$out['show_gallery_name'] = 'no';
		}

		$sub_mode = isset( $a['sub_fields_mode'] ) && is_scalar( $a['sub_fields_mode'] ) ? strtolower( (string) $a['sub_fields_mode'] ) : 'global';
		$out['sub_fields_mode'] = 'custom' === $sub_mode ? 'custom' : 'global';
		$allowed_sub_fields     = array( 'type', 'artist', 'gallery' );
		$raw_sub_fields         = is_array( $a['sub_fields'] ) ? $a['sub_fields'] : explode( ',', (string) $a['sub_fields'] );
		$clean_sub_fields       = array();
		foreach ( $raw_sub_fields as $sub_field ) {
			if ( ! is_scalar( $sub_field ) ) {
				continue;
			}
			$sub_field = sanitize_key( (string) $sub_field );
			if ( in_array( $sub_field, $allowed_sub_fields, true ) ) {
				$clean_sub_fields[] = $sub_field;
			}
		}
		$out['sub_fields'] = array_values( array_unique( $clean_sub_fields ) );
		$raw_sep = isset( $a['sub_sep'] ) && is_scalar( $a['sub_sep'] ) ? (string) $a['sub_sep'] : '';
		$clean_sep = trim( sanitize_text_field( $raw_sep ) );
		$out['sub_sep'] = '' === $clean_sep ? '' : ( preg_match( '/^\s/u', $raw_sep ) ? ' ' : '' ) . $clean_sep . ( preg_match( '/\s$/u', $raw_sep ) ? ' ' : '' );

		return $out;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( self::defaults(), (array) $atts, 'artiey_timeline' );
		return self::render( $atts );
	}

	/* ---------------------------------------------------------------- */
	/* REST (previous / next navigation without page reload)             */
	/* ---------------------------------------------------------------- */

	public static function register_rest() {
		register_rest_route(
			'artiey/v1',
			'/timeline',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_timeline' ),
			)
		);
	}

	public static function rest_timeline( $request ) {
		if ( class_exists( '\\WPArtCOA\\Security' ) && ! \WPArtCOA\Security::throttle( 'legacy-timeline', 120 ) ) {
			return new WP_Error( 'aet_rate_limited', __( 'Too many requests. Please try again shortly.', 'artiey-aet' ), array( 'status' => 429 ) );
		}
		$raw  = (string) $request->get_param( 'args' );
		$args = strlen( $raw ) <= 4000 ? json_decode( $raw, true ) : array();
		$args = self::sanitize_args( is_array( $args ) ? $args : array() );

		$offset = (int) $request->get_param( 'offset' );
		$offset = max( -3650, min( 3650, $offset ) );

		return rest_ensure_response(
			array(
				'html' => self::render_inner( $args, $offset ),
			)
		);
	}

	/* ---------------------------------------------------------------- */
	/* Rendering                                                         */
	/* ---------------------------------------------------------------- */

	public static function render( $args ) {
		$args = self::sanitize_args( $args );
		$o    = Artiey_AET_Settings::all();

		wp_enqueue_style( 'artiey-aet' );
		wp_enqueue_script( 'artiey-aet' );

		$cls = 'aet';
		if ( ! self::flag( $args, $o, 'stripes' ) ) {
			$cls .= ' aet-no-stripes';
		}
		if ( ! self::flag( $args, $o, 'highlight_fri' ) ) {
			$cls .= ' aet-no-fri';
		}
		if ( ! self::flag( $args, $o, 'highlight_today' ) ) {
			$cls .= ' aet-no-todaycol';
		}
		if ( self::flag( $args, $o, 'status_colors' ) ) {
			$cls .= ' aet-status-on';
		}

		$html  = '<div class="' . esc_attr( $cls ) . '" data-offset="0"';
		$html .= ' data-rest="' . esc_url( rest_url( 'artiey/v1/timeline' ) ) . '"';
		$html .= ' data-args="' . esc_attr( wp_json_encode( $args ) ) . '">';
		$html .= '<div class="aet-body">' . self::render_inner( $args, 0 ) . '</div>';
		$html .= '</div>';

		return $html;
	}

	private static function add_days( $ymd, $n ) {
		$d = new DateTimeImmutable( $ymd . ' 12:00:00', new DateTimeZone( 'UTC' ) );
		return $d->modify( ( $n >= 0 ? '+' : '' ) . (int) $n . ' days' )->format( 'Y-m-d' );
	}

	/** Number of days between $a and $b ($a - $b). */
	private static function diff_days( $a, $b ) {
		$utc = new DateTimeZone( 'UTC' );
		$ta  = ( new DateTimeImmutable( $a . ' 12:00:00', $utc ) )->getTimestamp();
		$tb  = ( new DateTimeImmutable( $b . ' 12:00:00', $utc ) )->getTimestamp();
		return (int) round( ( $ta - $tb ) / DAY_IN_SECONDS );
	}

	private static function fa( $n, $pad = 0 ) {
		$s = $pad ? sprintf( '%0' . (int) $pad . 'd', $n ) : (string) $n;
		return ( 'latin' === Artiey_AET_Settings::get( 'digits' ) ) ? $s : Artiey_AET_Jalali::to_persian_digits( $s );
	}

	public static function render_inner( $args, $offset = 0 ) {
		$minutes = (int) Artiey_AET_Settings::get( 'cache_minutes' );
		if ( $minutes > 0 ) {
			$key  = 'aet_' . md5( wp_json_encode( array( $args, (int) $offset, current_time( 'Y-m-d' ), Artiey_AET_Settings::version(), AET_VERSION ) ) );
			$hit  = get_transient( $key );
			if ( false !== $hit ) {
				return $hit;
			}
			$html = self::build_inner( $args, $offset );
			set_transient( $key, $html, $minutes * MINUTE_IN_SECONDS );
			return $html;
		}
		return self::build_inner( $args, $offset );
	}

	/** Build the date/galleries query; public so regression tests can verify the overlap logic. */
	public static function timeline_meta_query( $range_start, $range_end, $gallery_ids = array() ) {
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_ae_start',
				'value'   => $range_end,
				'compare' => '<=',
				'type'    => 'DATE',
			),
			array(
				'relation' => 'OR',
				array( 'key' => '_ae_end', 'value' => $range_start, 'compare' => '>=', 'type' => 'DATE' ),
				// An event without an end date is a one-day event. Keep it in the query only
				// when its start falls inside the visible range; otherwise old records can
				// consume posts_per_page slots before the renderer discards them.
				array(
					'relation' => 'AND',
					array( 'key' => '_ae_end', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_ae_start', 'value' => $range_start, 'compare' => '>=', 'type' => 'DATE' ),
				),
				array(
					'relation' => 'AND',
					array( 'key' => '_ae_end', 'value' => '', 'compare' => '=' ),
					array( 'key' => '_ae_start', 'value' => $range_start, 'compare' => '>=', 'type' => 'DATE' ),
				),
			),
		);
		$ids = is_array( $gallery_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $gallery_ids ) ) ) ) : array();
		if ( $ids ) {
			$meta_query[] = array(
				'key'     => '_ae_gallery',
				'value'   => $ids,
				'compare' => 'IN',
				'type'    => 'NUMERIC',
			);
		}
		return $meta_query;
	}

	private static function build_inner( $args, $offset = 0 ) {
		$o     = Artiey_AET_Settings::all();
		$latin = ( 'latin' === $o['digits'] );
		$days  = $args['days'];
		$today = current_time( 'Y-m-d' );

		// First visible day.
		$base = '';
		if ( 'custom' === $args['start_mode'] && $args['custom_start'] ) {
			$base = Artiey_AET_Jalali::jalali_to_ymd( $args['custom_start'] );
		}
		if ( ! $base ) {
			$base = self::add_days( $today, -1 * (int) $args['before_days'] );
		}
		$range_start = self::add_days( $base, (int) $offset );
		$range_end   = self::add_days( $range_start, $days - 1 );

		// Query exhibitions overlapping the visible range. Missing end dates count as one-day events.
		$gallery_filter_ids = $args['galleries'] ? array_map( 'absint', explode( ',', $args['galleries'] ) ) : array();
		$meta_query = self::timeline_meta_query( $range_start, $range_end, $gallery_filter_ids );

		$query = new WP_Query(
			array(
				'post_type'           => 'artiey_exhibition',
				'post_status'         => 'publish',
				'posts_per_page'      => $args['max_rows'],
				'meta_key'            => '_ae_start',
				'orderby'             => array(
					'meta_value' => 'ASC',
					'title'      => 'ASC',
				),
				'meta_type'           => 'DATE',
				'meta_query'          => $meta_query,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);

		$rows = $query->have_posts() ? count( $query->posts ) : 0;

		ob_start();

		// Navigation + range label.
		$show_nav   = ( 'yes' === $args['show_nav'] );
		$show_range = self::flag( $args, $o, 'show_range' );
		if ( $show_nav || $show_range ) {
			$step = max( 1, (int) $o['step'] );
			echo '<div class="aet-nav">';
			if ( $show_nav ) {
				echo '<button type="button" class="aet-nav-btn aet-nav-btn-prev" data-step="' . (int) ( -1 * $step ) . '" aria-label="بازهٔ قبلی"><span class="aet-nav-icon aet-nav-icon-prev" aria-hidden="true"></span><span class="aet-nav-label">قبل</span></button>';
			}
			if ( $show_range ) {
				echo '<span class="aet-range">' . esc_html( Artiey_AET_Jalali::format_long( $range_start, ! $latin ) . ' تا ' . Artiey_AET_Jalali::format_long( $range_end, ! $latin ) ) . '</span>';
			}
			if ( $show_nav ) {
				echo '<button type="button" class="aet-nav-btn aet-nav-btn-next" data-step="' . (int) $step . '" aria-label="بازهٔ بعدی"><span class="aet-nav-label">بعد</span><span class="aet-nav-icon aet-nav-icon-next" aria-hidden="true"></span></button>';
			}
			echo '</div>';
		}

		echo '<div class="aet-scroll"><div class="aet-grid aap-s-31de5d">';

		// Header: corner + day cells.
		echo '<div class="aet-corner">گالری</div>';
		for ( $i = 0; $i < $days; $i++ ) {
			$date = self::add_days( $range_start, $i );
			$p    = Artiey_AET_Jalali::ymd_to_parts( $date );
			$w    = (int) ( new DateTimeImmutable( $date . ' 12:00:00', new DateTimeZone( 'UTC' ) ) )->format( 'w' );

			$cls = 'aet-head';
			if ( $date === $today ) {
				$cls .= ' aet-today';
			}
			if ( 5 === $w ) {
				$cls .= ' aet-fri';
			}
			if ( 1 === $i % 2 ) {
				$cls .= ' aet-alt';
			}
			echo '<div class="' . esc_attr( $cls ) . ' aap-s-be6268">';
			echo '<div class="aet-dn">' . esc_html( Artiey_AET_Jalali::WEEKDAYS[ $w ] ) . '</div>';
			echo '<div class="aet-dd">' . esc_html( self::fa( $p[2], 2 ) ) . '</div>';
			echo '<div class="aet-dm">' . esc_html( Artiey_AET_Jalali::MONTHS[ $p[1] ] ) . '</div>';
			echo '</div>';
		}

		// Background day columns (stripes, today, Friday).
		for ( $i = 0; $i < $days; $i++ ) {
			$date = self::add_days( $range_start, $i );
			$w    = (int) ( new DateTimeImmutable( $date . ' 12:00:00', new DateTimeZone( 'UTC' ) ) )->format( 'w' );
			$cls  = 'aet-bg';
			if ( 1 === $i % 2 ) {
				$cls .= ' aet-alt';
			}
			if ( 5 === $w ) {
				$cls .= ' aet-fri';
			}
			if ( $date === $today ) {
				$cls .= ' aet-today-col';
			}
			echo '<div class="' . esc_attr( $cls ) . ' aap-s-d5bce6"></div>';
		}

		if ( $rows > 0 ) {
			$row_no = 2;
			foreach ( $query->posts as $post ) {
				$start = get_post_meta( $post->ID, '_ae_start', true );
				$end   = get_post_meta( $post->ID, '_ae_end', true );
				if ( ! $start ) {
					continue;
				}
				if ( ! $end ) {
					$end = $start;
				}

				$gid      = (int) get_post_meta( $post->ID, '_ae_gallery', true );
				$gal_post = $gid && 'artiey_gallery' === get_post_type( $gid ) ? get_post( $gid ) : null;
				$gal_name = $gal_post ? get_the_title( $gal_post ) : '';
				$gal_public = $gal_post && 'publish' === $gal_post->post_status;
				$color    = $gid && $gal_post ? get_post_meta( $gid, '_ag_color', true ) : '';

				$type   = get_post_meta( $post->ID, '_ae_type', true );
				$artist = get_post_meta( $post->ID, '_ae_artist', true );
				if ( '' === trim( (string) $artist ) ) {
					$rel = get_post_meta( $post->ID, '_ae_artists', true );
					if ( is_array( $rel ) && $rel ) {
						$names = array();
						foreach ( array_slice( $rel, 0, 4 ) as $aid ) {
							$t = get_the_title( (int) $aid );
							if ( $t ) {
								$names[] = $t;
							}
						}
						$artist = implode( '، ', $names );
					}
				}
				$link   = get_post_meta( $post->ID, '_ae_link', true );
				$link   = $link ? $link : get_permalink( $post );

				$parts = array();
				$custom_sub_fields = isset( $args['sub_fields_mode'] ) && 'custom' === $args['sub_fields_mode'];
				$want  = $custom_sub_fields ? (array) $args['sub_fields'] : (array) $o['sub_fields'];
				$sub_sep = $custom_sub_fields ? (string) $args['sub_sep'] : (string) $o['sub_sep'];
				if ( in_array( 'type', $want, true ) ) {
					$parts[] = $type;
				}
				if ( in_array( 'artist', $want, true ) ) {
					$parts[] = $artist;
				}
				if ( in_array( 'gallery', $want, true ) ) {
					$parts[] = $gal_name;
				}
				$sub = implode( $sub_sep, array_filter( $parts, static function ( $part ) { return '' !== (string) $part; } ) );

				$s_idx = max( 0, self::diff_days( $start, $range_start ) );
				$e_idx = min( $days - 1, self::diff_days( $end, $range_start ) );
				if ( $e_idx < $s_idx ) {
					continue;
				}

				$bar_cls = 'aet-bar';
				if ( $end < $today ) {
					$bar_cls .= ' aet-st-past';
				} elseif ( $start > $today ) {
					$bar_cls .= ' aet-st-upcoming';
				} else {
					$bar_cls .= ' aet-st-now';
				}
				if ( $start < $range_start ) {
					$bar_cls .= ' aet-cut-start';
				}
				if ( $end > $range_end ) {
					$bar_cls .= ' aet-cut-end';
				}

				$tip = get_the_title( $post ) . ' — ' . Artiey_AET_Jalali::format_long( $start, ! $latin ) . ' تا ' . Artiey_AET_Jalali::format_long( $end, ! $latin );

				// Logo cell.
				echo '<div class="aet-logocell aap-s-963a32' . ( $gal_post ? ' has-gallery' : ' no-gallery' ) . '">';
				if ( $gal_post ) {
					if ( $gal_public ) {
						echo '<a href="' . esc_url( get_permalink( $gal_post ) ) . '" title="' . esc_attr( $gal_name ) . '">';
					}
					if ( has_post_thumbnail( $gal_post ) ) {
						echo get_the_post_thumbnail(
							$gal_post,
							'medium',
							array(
								'class'   => 'aet-logo',
								'alt'     => $gal_name,
								'loading' => 'lazy',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						if ( 'yes' === ( $args['show_gallery_name'] ?? 'no' ) && '' !== $gal_name ) {
							echo '<span class="aet-logo-text aet-gallery-caption">' . esc_html( $gal_name ) . '</span>';
						}
					} else {
						echo '<span class="aet-logo-text">' . esc_html( $gal_name ) . '</span>';
					}
					if ( $gal_public ) {
						echo '</a>';
					}
				} else {
					echo '<span class="aet-logo-text aet-logo-unassigned">' . esc_html( 'بدون گالری' ) . '</span>';
				}
				echo '</div>';

				// Exhibition bar.
				$style = 'grid-row:' . (int) $row_no . ';grid-column:' . (int) ( $s_idx + 2 ) . ' / ' . (int) ( $e_idx + 3 ) . ';';
				if ( $color && ! self::flag( $args, $o, 'status_colors' ) ) {
					$style .= '--aet-bar:' . esc_attr( $color ) . ';';
				}
				$target = ( '_blank' === $o['link_target'] ) ? ' target="_blank" rel="noopener"' : '';
				echo '<a class="' . esc_attr( $bar_cls ) . ' aap-s-366eec" href="' . esc_url( $link ) . '" title="' . esc_attr( $tip ) . '"' . $target . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<span class="aet-title">' . esc_html( get_the_title( $post ) ) . '</span>';
				if ( $sub ) {
					echo '<span class="aet-sub">' . esc_html( $sub ) . '</span>';
				}
				echo '</a>';

				++$row_no;
			}
		} else {
			echo '<div class="aet-empty aap-s-85c1fb">' . esc_html( $o['empty_text'] ) . '</div>';
		}

		echo '</div></div>'; // .aet-grid, .aet-scroll.
		wp_reset_postdata();

		if ( $args['more_url'] ) {
			echo '<a class="aet-more" href="' . esc_url( $args['more_url'] ) . '">' . esc_html( $args['more_text'] ) . '</a>';
		}

		return ob_get_clean();
	}
}
