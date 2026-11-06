<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filterable directories: galleries, exhibitions, artists and artworks.
 * Works with plain GET (aet_* parameters) and upgrades to AJAX through the REST route.
 */
final class Artiey_AET_Directory {

	const TYPES   = array( 'galleries', 'exhibitions', 'artists', 'artworks' );
	const FILTERS = array( 'search', 'city', 'style', 'status', 'gallery', 'artist', 'open', 'sort', 'venue', 'nationality', 'medium', 'works', 'exhibitions', 'curator' );
	private static $artist_relation_ids_cache = array();
	private static $directory_cache_invalidated = false;
	private const PUBLIC_HTML_CACHE_TTL = 90;

	public static function init() {
		add_shortcode( 'artiey_directory', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'artiey_galleries', array( __CLASS__, 'shortcode_galleries' ) );
		add_shortcode( 'artiey_artist_archive', array( __CLASS__, 'shortcode_artist_archive' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
		foreach ( array( 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' ) as $type ) {
			add_action( 'save_post_' . $type, array( __CLASS__, 'invalidate_options' ), 999 );
		}
		add_action( 'deleted_post', array( __CLASS__, 'invalidate_options' ), 10, 1 );
		add_action( 'set_object_terms', array( __CLASS__, 'invalidate_on_terms' ), 10, 6 );
		// Directory cards depend on post meta (city, image, artist/gallery links,
		// status and display fields). Invalidate cached HTML when those values change.
		add_action( 'added_post_meta', array( __CLASS__, 'invalidate_on_post_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'invalidate_on_post_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'invalidate_on_post_meta' ), 10, 4 );
		add_action( 'transition_post_status', array( __CLASS__, 'invalidate_on_status' ), 10, 3 );
	}

	public static function invalidate_options( $post_id = 0 ) {
		// Multiple save/status/term hooks can fire for one edit. One version bump
		// per request is sufficient and avoids repeated options-table writes.
		if ( self::$directory_cache_invalidated ) {
			return;
		}
		self::$directory_cache_invalidated = true;
		$version = (int) get_option( 'artiey_directory_cache_version', 1 );
		update_option( 'artiey_directory_cache_version', $version + 1, false );
	}

	public static function invalidate_on_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( 'artiey_style' === $taxonomy ) {
			self::invalidate_options( $object_id );
		}
	}

	public static function invalidate_on_status( $new, $old, $post ) {
		if ( $new !== $old && $post instanceof WP_Post && in_array( $post->post_type, array( 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' ), true ) ) {
			self::invalidate_options( $post->ID );
		}
	}

	/** Invalidate public directory HTML when a displayed post's metadata changes. */
	public static function invalidate_on_post_meta( $meta_id, $object_id, $meta_key, $meta_value = null ) {
		$post = get_post( (int) $object_id );
		if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' ), true ) ) {
			self::invalidate_options( $post->ID );
		}
	}

	private static function cache_key( $key ) {
		return 'aet_dir_' . (int) get_option( 'artiey_directory_cache_version', 1 ) . '_' . md5( $key );
	}

	private static function fa( $v ) {
		return Artiey_AET_Jalali::to_persian_digits( (string) $v );
	}

	/* ---------------------------------------------------------------- */
	/* Arguments                                                         */
	/* ---------------------------------------------------------------- */

	public static function default_filters( $type ) {
		switch ( $type ) {
			case 'exhibitions':
				return array( 'search', 'city', 'style', 'status', 'gallery', 'artist', 'sort' );
			case 'artists':
				return array( 'search', 'city', 'nationality', 'style', 'medium', 'gallery', 'works', 'exhibitions', 'curator', 'sort' );
			case 'artworks':
				return array( 'search', 'city', 'style', 'artist', 'gallery', 'status', 'sort' );
		}
		return array( 'search', 'city', 'style', 'open', 'sort', 'venue' );
	}

	public static function sanitize_args( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		// Public REST callers may send arrays/objects where scalar attributes are expected.
		// Normalize those values before casts and sanitizers to avoid PHP notices and unsafe comparisons.
		foreach ( array( 'type', 'columns', 'per_page', 'style', 'city', 'status', 'venue', 'gallery', 'ajax', 'url', 'sort', 'empty_text', 'show_count', 'card_style', 'nationality', 'medium', 'works', 'exhibitions', 'curator' ) as $scalar_key ) {
			if ( array_key_exists( $scalar_key, $raw ) && ! is_scalar( $raw[ $scalar_key ] ) && null !== $raw[ $scalar_key ] ) {
				$raw[ $scalar_key ] = '';
			}
		}
		foreach ( $raw as $k => $v ) {
			if ( '' === $v || null === $v ) {
				unset( $raw[ $k ] );
			}
		}
		$a = wp_parse_args(
			$raw,
			array(
				'type'     => 'galleries',
				'columns'  => (int) Artiey_AET_Settings::get( 'directory_columns' ),
				'per_page' => (int) Artiey_AET_Settings::get( 'directory_per_page' ),
				'filters'  => '',
				'style'    => '',
				'city'     => '',
				'status'   => '',
				'venue'    => '',
				'gallery'  => 0,
				'ajax'     => Artiey_AET_Settings::get( 'directory_ajax' ) ? 'yes' : 'no',
				'url'      => '',
				'sort'     => '',
				'include'  => '',
				'exclude'  => '',
				'empty_text' => '',
				'show_count' => 'yes',
				'card_style' => 'standard',
				'nationality' => '',
				'medium' => '',
				'works' => '',
				'exhibitions' => '',
				'curator' => 0,
			)
		);

		$o            = array();
		$o['type']    = in_array( $a['type'], self::TYPES, true ) ? $a['type'] : 'galleries';
		$o['columns'] = max( 1, min( 6, absint( $a['columns'] ) ) );
		$o['per_page'] = max( 1, min( 60, absint( $a['per_page'] ) ) );

		$f = is_array( $a['filters'] ) ? $a['filters'] : explode( ',', (string) $a['filters'] );
		$f = array_values( array_intersect( self::FILTERS, array_map( static function ( $value ) { return is_scalar( $value ) ? trim( (string) $value ) : ''; }, $f ) ) );
		if ( ! $f && 'none' !== trim( (string) $a['filters'] ) ) {
			$f = self::default_filters( $o['type'] );
		}
		$o['filters'] = implode( ',', $f );

		$o['style']   = sanitize_title( (string) $a['style'] );
		$o['city']    = sanitize_text_field( (string) $a['city'] );
		$o['status']  = in_array( $a['status'], self::all_statuses(), true ) ? $a['status'] : '';
		$o['venue']   = in_array( $a['venue'], array( 'physical', 'online', 'hybrid' ), true ) ? $a['venue'] : '';
		$o['gallery'] = absint( $a['gallery'] );
		$o['ajax']    = ( 'yes' === $a['ajax'] || '1' === (string) $a['ajax'] ) ? 'yes' : 'no';
		$o['url']     = $a['url'] ? esc_url_raw( (string) $a['url'] ) : '';
		$o['sort']    = sanitize_key( (string) $a['sort'] );
		$o['include'] = self::id_list( $a['include'] );
		$o['exclude'] = self::id_list( $a['exclude'] );
		$o['empty_text'] = self::limit( sanitize_text_field( (string) $a['empty_text'] ), 200 );
		$o['show_count'] = ( 'no' === $a['show_count'] || '0' === (string) $a['show_count'] ) ? 'no' : 'yes';
		$o['card_style'] = in_array( (string) $a['card_style'], array( 'standard', 'editorial', 'compact', 'framed' ), true ) ? (string) $a['card_style'] : 'standard';
		$o['nationality'] = self::limit( sanitize_text_field( (string) $a['nationality'] ), 120 );
		$o['medium'] = self::limit( sanitize_text_field( (string) $a['medium'] ), 150 );
		$o['works'] = in_array( (string) $a['works'], array( 'yes', 'no' ), true ) ? (string) $a['works'] : '';
		$o['exhibitions'] = in_array( (string) $a['exhibitions'], array( 'yes', 'no' ), true ) ? (string) $a['exhibitions'] : '';
		$o['curator'] = absint( $a['curator'] );

		return $o;
	}

	/** Up to 100 unique positive ids from "1, 2" or an array. */
	private static function id_list( $v ) {
		$list = is_array( $v ) ? $v : explode( ',', is_scalar( $v ) ? (string) $v : '' );
		$list = array_filter( $list, 'is_scalar' );
		$list = array_values( array_unique( array_filter( array_map( 'absint', $list ) ) ) );
		return array_slice( $list, 0, 100 );
	}

	private static function limit( $text, $len ) {
		return Artiey_AET_Text::substr( $text, 0, $len );
	}

	private static function Exhibition_states() {
		return array( 'now', 'upcoming', 'past' );
	}

	/** Exhibition states + artwork sale states (the same "status" filter serves both types). */
	private static function all_statuses() {
		return array_merge( self::Exhibition_states(), array_keys( Artiey_AET_Artwork::SALE ) );
	}

	/** Sanitised request values (aet_* keys, no prefix in the result). */
	public static function request_from( $source ) {
		$get = function ( $key ) use ( $source ) {
			return isset( $source[ 'aet_' . $key ] ) && is_scalar( $source[ 'aet_' . $key ] ) ? wp_unslash( (string) $source[ 'aet_' . $key ] ) : '';
		};
		$q = preg_replace( '/\s+/u', ' ', trim( sanitize_text_field( $get( 'q' ) ) ) );
		$q = Artiey_AET_Text::substr( $q, 0, 100 );
		// One-character searches generate broad LIKE scans across several meta fields.
		// Ignore them to protect shared hosting while keeping normal search behaviour.
		if ( ! preg_match( '/^.{2,}$/us', $q ) ) {
			$q = '';
		}
		return array(
			'q'       => $q,
			'city'    => sanitize_text_field( $get( 'city' ) ),
			'venue'   => in_array( $get( 'venue' ), array( 'physical', 'online', 'hybrid' ), true ) ? $get( 'venue' ) : '',
			'style'   => sanitize_title( $get( 'style' ) ),
			'status'  => in_array( $get( 'status' ), self::all_statuses(), true ) ? $get( 'status' ) : '',
			'gallery' => absint( $get( 'gallery' ) ),
			'artist'  => absint( $get( 'artist' ) ),
			'nationality' => self::limit( sanitize_text_field( $get( 'nationality' ) ), 120 ),
			'medium' => self::limit( sanitize_text_field( $get( 'medium' ) ), 150 ),
			'works' => in_array( $get( 'works' ), array( 'yes', 'no' ), true ) ? $get( 'works' ) : '',
			'exhibitions' => in_array( $get( 'exhibitions' ), array( 'yes', 'no' ), true ) ? $get( 'exhibitions' ) : '',
			'curator' => absint( $get( 'curator' ) ),
			'open'    => '1' === $get( 'open' ) ? '1' : '',
			'sort'    => sanitize_key( $get( 'sort' ) ),
			'page'    => min( 1000, max( 1, absint( $get( 'page' ) ) ) ),
		);
	}

	private static function current_url() {
		if ( is_singular() ) {
			return get_permalink();
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return $host ? ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri : home_url( '/' );
	}

	private static function base_url( $args ) {
		$url = $args['url'] ? wp_validate_redirect( $args['url'], home_url( '/' ) ) : self::current_url();
		return remove_query_arg( array( 'aet_q', 'aet_city', 'aet_style', 'aet_status', 'aet_gallery', 'aet_artist', 'aet_open', 'aet_sort', 'aet_venue', 'aet_page', 'aet_nationality', 'aet_medium', 'aet_works', 'aet_exhibitions', 'aet_curator' ), $url );
	}

	/* ---------------------------------------------------------------- */
	/* Shortcodes + REST                                                 */
	/* ---------------------------------------------------------------- */

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'     => 'galleries',
				'columns'  => '',
				'per_page' => '',
				'filters'  => '',
				'style'    => '',
				'city'     => '',
				'status'   => '',
				'gallery'  => '',
				'nationality' => '',
				'medium' => '',
				'works' => '',
				'exhibitions' => '',
				'venue'    => '',
				'ajax'     => '',
				'sort'     => '',
				'include'  => '',
				'exclude'  => '',
				'empty_text' => '',
				'show_count' => 'yes',
			),
			(array) $atts,
			'artiey_directory'
		);
		return self::render( $atts );
	}

	/** Backwards compatible alias: [artiey_galleries count="12" columns="3" style="..." city="..."]. */
	public static function shortcode_galleries( $atts ) {
		$atts = (array) $atts;
		if ( isset( $atts['count'] ) && ! isset( $atts['per_page'] ) ) {
			$atts['per_page'] = $atts['count'];
		}
		$atts['type'] = 'galleries';
		if ( ! isset( $atts['filters'] ) ) {
			$atts['filters'] = 'none';
		}
		return self::shortcode( $atts );
	}

	/** Dedicated content archive rendered on the managed /artists/ page. */
	public static function shortcode_artist_archive( $atts ) {
		$atts = shortcode_atts(
			array(
				'heading' => 'آرشیو هنرمندان',
				'description' => 'هنرمندان را بر اساس شهر، ملیت، سبک، رسانه، گالری و سوابق هنری جست‌وجو و فیلتر کنید.',
				'show_heading' => 'yes',
				'columns' => 3,
				'per_page' => 12,
				'filters' => implode( ',', self::default_filters( 'artists' ) ),
				'ajax' => 'yes',
				'card_style' => 'editorial',
				'show_count' => 'yes',
				'sort' => 'title',
			),
			(array) $atts,
			'artiey_artist_archive'
		);
		$heading = self::limit( sanitize_text_field( (string) $atts['heading'] ), 120 );
		$description = self::limit( sanitize_text_field( (string) $atts['description'] ), 300 );
		$show_heading = ! in_array( (string) $atts['show_heading'], array( 'no', '0', 'false' ), true );
		$args = array(
			'type' => 'artists',
			'columns' => $atts['columns'],
			'per_page' => $atts['per_page'],
			'filters' => $atts['filters'],
			'ajax' => $atts['ajax'],
			'card_style' => $atts['card_style'],
			'show_count' => $atts['show_count'],
			'sort' => $atts['sort'],
		);
		$intro = '';
		if ( $show_heading && ( $heading || $description ) ) {
			$intro = '<header class="aetd-artist-archive-head" dir="rtl">';
			if ( $heading ) { $intro .= '<h1>' . esc_html( $heading ) . '</h1>'; }
			if ( $description ) { $intro .= '<p>' . esc_html( $description ) . '</p>'; }
			$intro .= '</header>';
		}
		return '<section class="aetd-artist-archive" dir="rtl">' . $intro . self::render( $args ) . '</section>';
	}

	public static function register_rest() {
		register_rest_route(
			'artiey/v1',
			'/directory',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_directory' ),
			)
		);
	}

	public static function rest_directory( $request ) {
		if ( class_exists( '\WPArtCOA\Security' ) && ! \WPArtCOA\Security::throttle( 'legacy-directory', 120 ) ) {
			return new WP_Error( 'aet_rate_limited', __( 'Too many requests. Please try again shortly.', 'artiey-aet' ), array( 'status' => 429 ) );
		}
		$raw  = (string) $request->get_param( 'args' );
		$args = strlen( $raw ) <= 4000 ? json_decode( $raw, true ) : array();
		$args = self::sanitize_args( is_array( $args ) ? $args : array() );
		$req  = self::request_from( $request->get_params() );
		return rest_ensure_response( array( 'html' => self::build( $args, $req ) ) );
	}

	/* ---------------------------------------------------------------- */
	/* Render                                                            */
	/* ---------------------------------------------------------------- */

	public static function render( $raw_args ) {
		$args = self::sanitize_args( $raw_args );
		if ( ! $args['url'] ) {
			$args['url'] = self::base_url( $args );
		}
		$req = self::request_from( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public filters, sanitised.

		wp_enqueue_style( 'artiey-aet-profile' );
		wp_enqueue_script( 'artiey-aet-profile' );

		$h  = '<div class="aetp aetd aetd-card-' . esc_attr( $args['card_style'] ) . '" dir="rtl" data-ajax="' . esc_attr( $args['ajax'] ) . '"';
		$h .= ' data-rest="' . esc_url( rest_url( 'artiey/v1/directory' ) ) . '"';
		$h .= ' data-args="' . esc_attr( wp_json_encode( $args ) ) . '">';
		$h .= '<div class="aetd-inner">' . self::build( $args, $req ) . '</div></div>';
		return $h;
	}

	/* ---------------------------------------------------------------- */
	/* Options for filter selects                                        */
	/* ---------------------------------------------------------------- */

	private static function cities( $type ) {
		$key = 'cities_' . $type;
		$rows = get_transient( self::cache_key( $key ) );
		if ( false === $rows ) {
			global $wpdb;
			$meta_key = ( 'artists' === $type ) ? '_aa_city' : '_ag_city';
			$ptype    = ( 'artists' === $type ) ? 'artiey_artist' : 'artiey_gallery';
			$rows     = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value <> '' AND p.post_type = %s AND p.post_status = 'publish' ORDER BY pm.meta_value ASC LIMIT 200",
					$meta_key,
					$ptype
				)
			);
			$rows = is_array( $rows ) ? $rows : array();
			set_transient( self::cache_key( $key ), $rows, 10 * MINUTE_IN_SECONDS );
		}
		return is_array( $rows ) ? $rows : array();
	}

	private static function styles() {
		$terms = get_transient( self::cache_key( 'styles' ) );
		if ( false === $terms ) {
			$terms = get_terms( array( 'taxonomy' => 'artiey_style', 'hide_empty' => true ) );
			$terms = is_array( $terms ) ? $terms : array();
			set_transient( self::cache_key( 'styles' ), $terms, 10 * MINUTE_IN_SECONDS );
		}
		return is_array( $terms ) ? $terms : array();
	}

	private static function gallery_options() {
		$cached = get_transient( self::cache_key( 'galleries' ) );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$list = get_posts(
			array(
				'post_type'   => 'artiey_gallery',
				'post_status' => 'publish',
				'numberposts' => 300,
				'orderby'     => 'title',
				'order'       => 'ASC',
				'no_found_rows' => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$out = array();
		foreach ( $list as $p ) {
			$out[ $p->ID ] = $p->post_title;
		}
		set_transient( self::cache_key( 'galleries' ), $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	private static function artist_options() {
		$cached = get_transient( self::cache_key( 'artists' ) );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$list = get_posts(
			array(
				'post_type'   => 'artiey_artist',
				'post_status' => 'publish',
				'numberposts' => 500,
				'orderby'     => 'title',
				'order'       => 'ASC',
				'no_found_rows' => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$out = array();
		foreach ( $list as $p ) {
			$out[ $p->ID ] = $p->post_title;
		}
		set_transient( self::cache_key( 'artists' ), $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** Distinct public artist meta choices used by the archive filters. */
	private static function artist_meta_values( $meta_key ) {
		$meta_key = in_array( $meta_key, array( '_aa_nationality', '_aa_mediums' ), true ) ? $meta_key : '';
		if ( '' === $meta_key ) { return array(); }
		$cache_key = self::cache_key( 'artist_meta_values_' . $meta_key );
		$values = get_transient( $cache_key );
		if ( false !== $values && is_array( $values ) ) { return $values; }
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value <> '' AND p.post_type = %s AND p.post_status = 'publish' ORDER BY pm.meta_value ASC LIMIT 300",
			$meta_key,
			'artiey_artist'
		) );
		$values = array();
		foreach ( (array) $rows as $raw ) {
			$parts = ( '_aa_mediums' === $meta_key ) ? preg_split( '/[,،;|\/]+/u', (string) $raw ) : array( (string) $raw );
			foreach ( (array) $parts as $part ) {
				$value = trim( sanitize_text_field( (string) $part ) );
				if ( '' !== $value ) { $values[ $value ] = $value; }
			}
		}
		$values = array_slice( array_values( $values ), 0, 150 );
		set_transient( $cache_key, $values, 10 * MINUTE_IN_SECONDS );
		return $values;
	}

	private static function artist_nationalities() { return self::artist_meta_values( '_aa_nationality' ); }
	private static function artist_mediums() { return self::artist_meta_values( '_aa_mediums' ); }

	/** Validate relationship IDs against published canonical artist posts. */
	private static function published_artist_ids( $ids ) {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) ), 0, 5000 );
		if ( ! $ids ) { return array(); }
		return array_map( 'absint', (array) get_posts( array(
			'post_type' => 'artiey_artist', 'post_status' => 'publish', 'posts_per_page' => count( $ids ),
			'numberposts' => count( $ids ), 'fields' => 'ids', 'post__in' => $ids, 'no_found_rows' => true,
			'update_post_meta_cache' => false, 'update_post_term_cache' => false,
		) ) );
	}

	/** Decode the serialized artist ID list used by exhibition meta without trusting object payloads. */
	private static function decode_artist_ids( $raw ) {
		$value = $raw;
		if ( is_string( $raw ) && function_exists( 'maybe_unserialize' ) ) {
			$value = maybe_unserialize( $raw );
		} elseif ( is_string( $raw ) && preg_match( '/^a:\\d+:/', $raw ) ) {
			$decoded = @unserialize( $raw, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- only primitive IDs from plugin-owned post meta.
			$value = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $value ) ) { $value = is_scalar( $value ) ? preg_split( '/[,;\\s]+/', (string) $value ) : array(); }
		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	/**
	 * Resolve artist relationships from canonical public records, not from matching names.
	 * COA relations are included when the content artist has a valid COA person link.
	 */
	private static function artist_relation_ids( $relation, $gallery_id = 0 ) {
		$relation = in_array( $relation, array( 'works', 'exhibitions', 'gallery' ), true ) ? $relation : '';
		$gallery_id = absint( $gallery_id );
		if ( '' === $relation || ( 'gallery' === $relation && ! $gallery_id ) ) { return array(); }
		$cache_token = $relation . ':' . $gallery_id;
		if ( array_key_exists( $cache_token, self::$artist_relation_ids_cache ) ) { return self::$artist_relation_ids_cache[ $cache_token ]; }
		$transient_key = self::cache_key( 'artist_relation_' . $cache_token );
		$cached = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			self::$artist_relation_ids_cache[ $cache_token ] = $cached;
			return $cached;
		}

		global $wpdb;
		$ids = array();
		if ( 'gallery' === $relation ) {
			// Direct gallery → artists selections.
			$ids = array_merge( $ids, self::decode_artist_ids( get_post_meta( $gallery_id, '_ag_artists', true ) ) );
			// Indirect gallery → published artwork → artist relationship.
			$sql = "SELECT DISTINCT CAST(am.meta_value AS UNSIGNED) FROM {$wpdb->posts} w
				INNER JOIN {$wpdb->postmeta} gm ON gm.post_id = w.ID AND gm.meta_key = '_aw_gallery'
				INNER JOIN {$wpdb->postmeta} am ON am.post_id = w.ID AND am.meta_key = '_aw_artist'
				INNER JOIN {$wpdb->posts} a ON a.ID = CAST(am.meta_value AS UNSIGNED)
				WHERE w.post_type = 'artiey_artwork' AND w.post_status = 'publish'
				AND a.post_type = 'artiey_artist' AND a.post_status = 'publish'
				AND CAST(gm.meta_value AS UNSIGNED) = %d LIMIT 5000";
			$ids = array_merge( $ids, (array) $wpdb->get_col( $wpdb->prepare( $sql, $gallery_id ) ) );
		} elseif ( 'works' === $relation ) {
			$sql = "SELECT DISTINCT CAST(am.meta_value AS UNSIGNED) FROM {$wpdb->posts} w
				INNER JOIN {$wpdb->postmeta} am ON am.post_id = w.ID AND am.meta_key = '_aw_artist'
				INNER JOIN {$wpdb->posts} a ON a.ID = CAST(am.meta_value AS UNSIGNED)
				WHERE w.post_type = 'artiey_artwork' AND w.post_status = 'publish'
				AND a.post_type = 'artiey_artist' AND a.post_status = 'publish' LIMIT 5000";
			$ids = array_merge( $ids, (array) $wpdb->get_col( $sql ) );
		} else {
			// The native exhibition mirror stores _ae_artists as an array of WP artist IDs.
			$sql = "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} e ON e.ID = pm.post_id
				WHERE pm.meta_key = '_ae_artists' AND e.post_type = 'artiey_exhibition' AND e.post_status = 'publish' LIMIT 2000";
			foreach ( (array) $wpdb->get_col( $sql ) as $raw_ids ) {
				$ids = array_merge( $ids, self::decode_artist_ids( $raw_ids ) );
			}
		}

		// COA catalogue / exhibition relationships, bridged by the canonical _aa_coa_person_id.
		if ( class_exists( '\\\\WPArtCOA\\\\Database' ) ) {
			$artist_table = \WPArtCOA\Database::table( 'artists' );
			$artwork_table = \WPArtCOA\Database::table( 'artworks' );
			$meta_table = $wpdb->postmeta;
			$posts_table = $wpdb->posts;
			$join = "FROM {$meta_table} link INNER JOIN {$posts_table} p ON p.ID = link.post_id AND p.post_type = 'artiey_artist' AND p.post_status = 'publish'
				INNER JOIN {$artist_table} ca ON ca.id = CAST(link.meta_value AS UNSIGNED) AND ca.status = 'active'";
			$where = "WHERE link.meta_key = '_aa_coa_person_id'";
			if ( 'gallery' === $relation ) {
				$coa_gallery_id = absint( get_post_meta( $gallery_id, '_ag_coa_gallery_id', true ) );
				if ( $coa_gallery_id ) {
					$sql = "SELECT DISTINCT p.ID {$join} INNER JOIN {$artwork_table} aw ON aw.artist_id = ca.id {$where} AND aw.gallery_id = %d AND aw.status IN ('published','approved','active','sold') LIMIT 5000";
					$ids = array_merge( $ids, (array) $wpdb->get_col( $wpdb->prepare( $sql, $coa_gallery_id ) ) );
				}
			} elseif ( 'works' === $relation ) {
				$sql = "SELECT DISTINCT p.ID {$join} INNER JOIN {$artwork_table} aw ON aw.artist_id = ca.id {$where} AND aw.status IN ('published','approved','active','sold') LIMIT 5000";
				$ids = array_merge( $ids, (array) $wpdb->get_col( $sql ) );
			} else {
			$exhibition_table = \WPArtCOA\Database::table( 'exhibitions' );
			$bridge_table = \WPArtCOA\Database::table( 'exhibition_artworks' );
			$sql = "SELECT DISTINCT p.ID {$join}
				INNER JOIN {$artwork_table} aw ON aw.artist_id = ca.id
				INNER JOIN {$bridge_table} ea ON ea.artwork_id = aw.id
				INNER JOIN {$exhibition_table} e ON e.id = ea.exhibition_id
				{$where} AND aw.status IN ('published','approved','active','sold') AND e.status IN ('published','active') LIMIT 5000";
			$ids = array_merge( $ids, (array) $wpdb->get_col( $sql ) );
			}
		}

		$ids = self::published_artist_ids( $ids );
		self::$artist_relation_ids_cache[ $cache_token ] = $ids;
		set_transient( $transient_key, $ids, 5 * MINUTE_IN_SECONDS );
		return $ids;
	}

	/** Active public curators available through the Art COA relational catalogue. */
	private static function curator_options() {
		if ( ! class_exists( '\\WPArtCOA\\Database' ) ) { return array(); }
		$key = self::cache_key( 'active_curator_options' );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) { return $cached; }
		global $wpdb;
		$table = \WPArtCOA\Database::table( 'curators' );
		$rows = $wpdb->get_results( "SELECT id, display_name FROM {$table} WHERE status = 'active' AND display_name <> '' ORDER BY display_name ASC LIMIT 300" );
		$options = array();
		foreach ( (array) $rows as $row ) {
			$id = absint( $row->id ?? 0 );
			$name = sanitize_text_field( (string) ( $row->display_name ?? '' ) );
			if ( $id && '' !== $name ) { $options[ $id ] = $name; }
		}
		set_transient( $key, $options, 10 * MINUTE_IN_SECONDS );
		return $options;
	}

	/** Resolve artist posts linked to a curator through published exhibitions and their artworks. */
	private static function artist_ids_for_curator( $curator_id ) {
		$curator_id = absint( $curator_id );
		if ( ! $curator_id ) { return array(); }
		$key = 'artist_curator_' . $curator_id;
		$cached = get_transient( self::cache_key( $key ) );
		if ( is_array( $cached ) ) { return $cached; }
		$ids = array();
		global $wpdb;

		// First resolve mirrored WordPress exhibition records (no fuzzy name matching).
		$native_sql = "SELECT artists.meta_value FROM {$wpdb->postmeta} curator
			INNER JOIN {$wpdb->posts} e ON e.ID = curator.post_id AND e.post_type = 'artiey_exhibition' AND e.post_status = 'publish'
			INNER JOIN {$wpdb->postmeta} artists ON artists.post_id = e.ID AND artists.meta_key = '_ae_artists'
			WHERE curator.meta_key = '_ae_curator_id' AND CAST(curator.meta_value AS UNSIGNED) = %d LIMIT 2000";
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( $native_sql, $curator_id ) ) as $raw_ids ) {
			$ids = array_merge( $ids, self::decode_artist_ids( $raw_ids ) );
		}

		// Also resolve the canonical Art COA chain: artist → artwork → exhibition → curator.
		if ( class_exists( '\\WPArtCOA\\Database' ) ) {
			$artist_table = \WPArtCOA\Database::table( 'artists' );
			$artwork_table = \WPArtCOA\Database::table( 'artworks' );
			$exhibition_table = \WPArtCOA\Database::table( 'exhibitions' );
			$bridge_table = \WPArtCOA\Database::table( 'exhibition_artworks' );
			$sql = "SELECT DISTINCT p.ID FROM {$wpdb->postmeta} link
				INNER JOIN {$wpdb->posts} p ON p.ID = link.post_id AND p.post_type = 'artiey_artist' AND p.post_status = 'publish'
				INNER JOIN {$artist_table} ca ON ca.id = CAST(link.meta_value AS UNSIGNED) AND ca.status = 'active'
				INNER JOIN {$artwork_table} aw ON aw.artist_id = ca.id AND aw.status IN ('published','approved','active','sold')
				INNER JOIN {$bridge_table} ea ON ea.artwork_id = aw.id
				INNER JOIN {$exhibition_table} e ON e.id = ea.exhibition_id AND e.curator_id = %d AND e.status IN ('published','active')
				WHERE link.meta_key = '_aa_coa_person_id' LIMIT 5000";
			$ids = array_merge( $ids, (array) $wpdb->get_col( $wpdb->prepare( $sql, $curator_id ) ) );
		}

		$ids = self::published_artist_ids( $ids );
		set_transient( self::cache_key( $key ), $ids, 5 * MINUTE_IN_SECONDS );
		return $ids;
	}

	private static function gallery_ids_for_city( $city ) {
		$key = self::cache_key( 'gallery_ids_city_' . $city );
		$ids = get_transient( $key );
		if ( false === $ids ) {
			$ids = get_posts( array(
				'post_type'              => 'artiey_gallery',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				// Bound each variant query: Persian/Arabic spelling variants can otherwise
				// materialize thousands of IDs before the final directory query runs.
				'numberposts'            => 250,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( array( 'key' => '_ag_city', 'value' => $city ) ),
			) );
			$ids = array_map( 'absint', $ids );
			set_transient( $key, $ids, 10 * MINUTE_IN_SECONDS );
		}
		return is_array( $ids ) ? $ids : array();
	}

	private static function sort_options( $type ) {
		switch ( $type ) {
			case 'exhibitions':
				return array( 'date_desc' => 'جدیدترین', 'date_asc' => 'قدیمی‌ترین', 'title' => 'الفبایی' );
			case 'artists':
				return array( 'title' => 'الفبایی', 'newest' => 'جدیدترین ثبت' );
			case 'artworks':
				return array( 'newest' => 'جدیدترین', 'price_asc' => 'ارزان‌ترین', 'price_desc' => 'گران‌ترین', 'title' => 'الفبایی' );
		}
		return array( 'title' => 'الفبایی', 'newest' => 'جدیدترین ثبت', 'founded' => 'قدیمی‌ترین تأسیس' );
	}

	/* ---------------------------------------------------------------- */
	/* Querying                                                          */
	/* ---------------------------------------------------------------- */

	private static function search_ids( $ptype, $q, $meta_keys ) {
		$cache_key = self::cache_key( 'search_' . $ptype . '_' . $q . '_' . implode( ',', $meta_keys ) );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		// Persian and Arabic keyboard forms are common; search both spellings.
		$variants = array_unique( array_filter( array(
			$q,
			str_replace( array( 'ي', 'ى', 'ك' ), array( 'ی', 'ی', 'ک' ), $q ),
			str_replace( array( 'ی', 'ک' ), array( 'ي', 'ك' ), $q ),
			Artiey_AET_Jalali::to_latin_digits( $q ),
			Artiey_AET_Jalali::to_persian_digits( $q ),
		), static function ( $value ) { return '' !== $value; } ) );
		$ids = array();
		foreach ( $variants as $term ) {
			$common = array(
				'post_type'              => $ptype,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'numberposts'            => 250,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);
			$ids = array_merge( $ids, get_posts( $common + array( 's' => $term ) ) );
			$or = array( 'relation' => 'OR' );
			foreach ( $meta_keys as $key ) {
				$or[] = array( 'key' => $key, 'value' => $term, 'compare' => 'LIKE' );
			}
			$ids = array_merge( $ids, get_posts( $common + array( 'meta_query' => $or ) ) );
		}
		// Match names through linked artist/gallery records on exhibitions and artworks too.
		$related = array();
		if ( in_array( $ptype, array( 'artiey_exhibition', 'artiey_artwork' ), true ) ) {
			$artist_ids = self::search_ids( 'artiey_artist', $q, array( '_aa_name_en', '_aa_city', '_aa_nationality', '_aa_mediums', '_aa_education', '_aa_awards', '_aa_statement' ) );
			$gallery_ids = self::search_ids( 'artiey_gallery', $q, array( '_ag_name_en', '_ag_city', '_ag_address', '_ag_short_bio', '_ag_venue_type' ) );
			if ( $artist_ids ) {
				if ( 'artiey_artwork' === $ptype ) {
					$related[] = array( 'key' => '_aw_artist', 'value' => $artist_ids, 'compare' => 'IN', 'type' => 'NUMERIC' );
				} else {
					foreach ( array_slice( $artist_ids, 0, 200 ) as $artist_id ) {
						$related[] = array( 'key' => '_ae_artists', 'value' => ';i:' . (int) $artist_id . ';', 'compare' => 'LIKE' );
					}
				}
			}
			if ( $gallery_ids ) {
				$related[] = array( 'key' => ( 'artiey_artwork' === $ptype ) ? '_aw_gallery' : '_ae_gallery', 'value' => $gallery_ids, 'compare' => 'IN', 'type' => 'NUMERIC' );
			}
		}
		if ( $related ) {
			$ids = array_merge( $ids, get_posts( array(
				'post_type'              => $ptype,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'numberposts'            => 1000,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array_merge( array( 'relation' => 'OR' ), $related ),
			) ) );
		}
		$ids = array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, 1000 );
		set_transient( $cache_key, $ids, 5 * MINUTE_IN_SECONDS );
		return $ids;
	}

	private static function intersect( &$in, $ids ) {
		$ids = array_values( array_map( 'absint', (array) $ids ) );
		$in  = ( null === $in ) ? $ids : array_values( array_intersect( $in, $ids ) );
	}

	private static function build_query( $args, $r, $paged = true ) {
		$type = $args['type'];
		$ptype = array(
			'galleries'   => 'artiey_gallery',
			'exhibitions' => 'artiey_exhibition',
			'artists'     => 'artiey_artist',
			'artworks'    => 'artiey_artwork',
		)[ $type ];

		$city   = $r['city'] ? $r['city'] : $args['city'];
		$style  = $r['style'] ? $r['style'] : $args['style'];
		$status = $r['status'] ? $r['status'] : $args['status'];
		$gal    = $r['gallery'] ? $r['gallery'] : $args['gallery'];
		$artist = $r['artist'];
		$venue  = $r['venue'] ? $r['venue'] : $args['venue'];

		$q = array(
			'post_type'      => $ptype,
			'post_status'    => 'publish',
			'posts_per_page' => $paged ? $args['per_page'] : -1,
			'paged'          => $paged ? $r['page'] : 1,
			'meta_query'     => array( 'relation' => 'AND' ),
		);
		$in = null;
		$not_in = array();

		if ( $style ) {
			$q['tax_query'] = array( array( 'taxonomy' => 'artiey_style', 'field' => 'slug', 'terms' => array( $style ) ) );
		}

		if ( $r['q'] ) {
			$keys = array(
				'galleries'   => array( '_ag_name_en', '_ag_city', '_ag_address', '_ag_short_bio' ),
				'exhibitions' => array( '_ae_type', '_ae_artist', '_ae_artists', '_ae_curator', '_ae_admission', '_ae_opening' ),
				'artists'     => array( '_aa_name_en', '_aa_city', '_aa_nationality', '_aa_mediums', '_aa_education', '_aa_awards', '_aa_statement' ),
				'artworks'    => array( '_aw_title_en', '_aw_medium', '_aw_artist_text', '_aw_year', '_aw_dimensions', '_aw_accession', '_aw_material', '_aw_provenance' ),
			);
			self::intersect( $in, self::search_ids( $ptype, $r['q'], $keys[ $type ] ) );
		}

		$sort = $r['sort'];

		if ( 'galleries' === $type ) {
			if ( $venue ) {
				$q['meta_query'][] = array( 'key' => '_ag_venue_type', 'value' => $venue );
			}
			if ( $city ) {
				$q['meta_query'][] = array( 'key' => '_ag_city', 'value' => $city );
			}
			if ( 'newest' === $sort ) {
				$q['orderby'] = 'date';
				$q['order']   = 'DESC';
			} elseif ( 'founded' === $sort ) {
				$q['meta_key'] = '_ag_founded';
				$q['orderby']  = 'meta_value_num';
				$q['order']    = 'ASC';
			} else {
				$q['orderby'] = 'title';
				$q['order']   = 'ASC';
			}
		} elseif ( 'artists' === $type ) {
			if ( $city ) {
				$q['meta_query'][] = array( 'key' => '_aa_city', 'value' => $city );
			}
			$nationality = $r['nationality'] ? $r['nationality'] : $args['nationality'];
			$medium = $r['medium'] ? $r['medium'] : $args['medium'];
			if ( $nationality ) { $q['meta_query'][] = array( 'key' => '_aa_nationality', 'value' => $nationality ); }
			if ( $medium ) { $q['meta_query'][] = array( 'key' => '_aa_mediums', 'value' => $medium, 'compare' => 'LIKE' ); }
			if ( $gal ) { self::intersect( $in, self::artist_relation_ids( 'gallery', $gal ) ); }
			if ( ! empty( $r['curator'] ) ) { self::intersect( $in, self::artist_ids_for_curator( $r['curator'] ) ); }
			if ( 'yes' === $r['works'] ) { self::intersect( $in, self::artist_relation_ids( 'works' ) ); }
			elseif ( 'no' === $r['works'] ) { $not_in = array_merge( $not_in, self::artist_relation_ids( 'works' ) ); }
			if ( 'yes' === $r['exhibitions'] ) { self::intersect( $in, self::artist_relation_ids( 'exhibitions' ) ); }
			elseif ( 'no' === $r['exhibitions'] ) { $not_in = array_merge( $not_in, self::artist_relation_ids( 'exhibitions' ) ); }
			if ( 'newest' === $sort ) {
				$q['orderby'] = 'date';
				$q['order']   = 'DESC';
			} else {
				$q['orderby'] = 'title';
				$q['order']   = 'ASC';
			}
		} elseif ( 'artworks' === $type ) {
			if ( $gal ) {
				$q['meta_query'][] = array( 'key' => '_aw_gallery', 'value' => $gal, 'type' => 'NUMERIC' );
			}
			if ( $artist ) {
				$q['meta_query'][] = array( 'key' => '_aw_artist', 'value' => $artist, 'type' => 'NUMERIC' );
			}
			if ( $city ) {
				$gids = self::gallery_ids_for_city( $city );
				$q['meta_query'][] = array( 'key' => '_aw_gallery', 'value' => $gids ? $gids : array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' );
			}
			if ( $status && isset( Artiey_AET_Artwork::SALE[ $status ] ) ) {
				if ( 'available' === $status ) {
					$q['meta_query'][] = array(
						'relation' => 'OR',
						array( 'key' => '_aw_sale', 'value' => 'available' ),
						array( 'key' => '_aw_sale', 'compare' => 'NOT EXISTS' ),
						array( 'key' => '_aw_sale', 'value' => '' ),
					);
				} else {
					$q['meta_query'][] = array( 'key' => '_aw_sale', 'value' => $status );
				}
			}
			if ( 'price_asc' === $sort || 'price_desc' === $sort ) {
				$q['meta_query'][] = array( 'key' => '_aw_price', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' );
				$q['meta_key']     = '_aw_price';
				$q['orderby']      = 'meta_value_num';
				$q['order']        = ( 'price_asc' === $sort ) ? 'ASC' : 'DESC';
			} elseif ( 'title' === $sort ) {
				$q['orderby'] = 'title';
				$q['order']   = 'ASC';
			} else {
				$q['orderby'] = 'date';
				$q['order']   = 'DESC';
			}
		} else { // exhibitions.
			$today = current_time( 'Y-m-d' );
			if ( $gal ) {
				$q['meta_query'][] = array( 'key' => '_ae_gallery', 'value' => $gal, 'type' => 'NUMERIC' );
			}
			if ( $city ) {
				$gids = self::gallery_ids_for_city( $city );
				$q['meta_query'][] = array( 'key' => '_ae_gallery', 'value' => $gids ? $gids : array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' );
			}
			if ( $artist ) {
				$q['meta_query'][] = array( 'key' => '_ae_artists', 'value' => ';i:' . absint( $artist ) . ';', 'compare' => 'LIKE' );
			}
			if ( 'now' === $status ) {
				$q['meta_query'][] = array( 'key' => '_ae_start', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' );
				$q['meta_query'][] = array( 'key' => '_ae_end', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' );
				$q['meta_key']     = '_ae_end';
				$q['orderby']      = 'meta_value';
				$q['meta_type']    = 'DATE';
				$q['order']        = 'ASC';
			} elseif ( 'upcoming' === $status ) {
				$q['meta_query'][] = array( 'key' => '_ae_start', 'value' => $today, 'compare' => '>', 'type' => 'DATE' );
				$q['meta_key']     = '_ae_start';
				$q['orderby']      = 'meta_value';
				$q['meta_type']    = 'DATE';
				$q['order']        = 'ASC';
			} elseif ( 'past' === $status ) {
				$q['meta_query'][] = array( 'key' => '_ae_end', 'value' => $today, 'compare' => '<', 'type' => 'DATE' );
				$q['meta_key']     = '_ae_end';
				$q['orderby']      = 'meta_value';
				$q['meta_type']    = 'DATE';
				$q['order']        = 'DESC';
			} elseif ( 'title' === $sort ) {
				$q['meta_query'][] = array( 'key' => '_ae_start', 'compare' => 'EXISTS' );
				$q['orderby']      = 'title';
				$q['order']        = 'ASC';
			} else {
				$q['meta_key']  = '_ae_start';
				$q['orderby']   = 'meta_value';
				$q['meta_type'] = 'DATE';
				$q['order']     = ( 'date_asc' === $sort ) ? 'ASC' : 'DESC';
			}
		}

		if ( ! empty( $args['include'] ) ) {
			self::intersect( $in, $args['include'] );
		}
		if ( ! empty( $args['exclude'] ) ) { $not_in = array_merge( $not_in, $args['exclude'] ); }
		$not_in = array_values( array_unique( array_filter( array_map( 'absint', $not_in ) ) ) );
		if ( $not_in ) { $q['post__not_in'] = $not_in; }
		if ( count( $q['meta_query'] ) < 2 ) {
			unset( $q['meta_query'] );
		}
		if ( null !== $in ) {
			$q['post__in'] = $in ? $in : array( 0 );
		}
		if ( 'manual' === $sort && ! empty( $args['include'] ) ) {
			// Keep the order the editor picked the records in.
			$q['post__in'] = $in ? $in : array( 0 );
			$q['orderby']  = 'post__in';
			unset( $q['meta_key'], $q['meta_type'], $q['order'] );
		}
		return $q;
	}

	private static function run_query( $args, $r ) {
		$q = self::build_query( $args, $r, true );

		// "Open now" is calculated, so resolve it first and feed IDs back.
		if ( 'galleries' === $args['type'] && '1' === $r['open'] ) {
			// Business hours rarely change; reuse the expensive full gallery scan briefly.
			// Keep the cache key scoped to filters that affect the source gallery set.
			$cache_key = self::cache_key( 'open_galleries_' . wp_json_encode( array( $r['q'], $r['city'] ? $r['city'] : $args['city'], $r['style'] ? $r['style'] : $args['style'] ) ) );
			$open      = get_transient( $cache_key );
			if ( false === $open ) {
				$all                 = self::build_query( $args, $r, false );
				$all['fields']       = 'ids';
				$all['no_found_rows'] = true;
				$all['update_post_term_cache'] = false;
				$open = array();
				foreach ( get_posts( $all ) as $gid ) {
					$st = Artiey_AET_Gallery::status( Artiey_AET_Gallery::get( $gid ) );
					if ( 'open' === $st['state'] ) {
						$open[] = (int) $gid;
					}
				}
				set_transient( $cache_key, $open, MINUTE_IN_SECONDS );
			}
			$q['post__in'] = $open ? $open : array( 0 );
		}
		return new WP_Query( $q );
	}

	/* ---------------------------------------------------------------- */
	/* Markup                                                            */
	/* ---------------------------------------------------------------- */

	private static function select( $name, $label, $options, $current ) {
		$h = '<label class="aetd-f"><span class="aetd-lbl">' . esc_html( $label ) . '</span><select name="aet_' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $text ) {
			$h .= '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		return $h . '</select></label>';
	}

	private static function filter_form( $args, $r, $base ) {
		$active = array_filter( explode( ',', $args['filters'] ) );
		if ( ! $active ) {
			return '';
		}
		$type = $args['type'];

		$h  = '<form class="aetd-filters" method="get" action="' . esc_url( $base ) . '">';

		// Keep non-plugin query vars (plain permalinks: ?page_id=5).
		$parts = wp_parse_url( $base );
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $qv );
			foreach ( $qv as $k => $v ) {
				if ( is_scalar( $v ) ) {
					$h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
				}
			}
		}

		if ( in_array( 'search', $active, true ) ) {
			$h .= '<label class="aetd-f aetd-f-search"><span class="aetd-lbl">جستجو</span><input type="search" name="aet_q" value="' . esc_attr( $r['q'] ) . '" placeholder="نام را بنویسید..."></label>';
		}
		if ( in_array( 'city', $active, true ) && in_array( $type, array( 'galleries', 'exhibitions', 'artists', 'artworks' ), true ) ) {
			$opts = array( '' => 'همه شهرها' );
			foreach ( self::cities( $type ) as $c ) {
				$opts[ $c ] = $c;
			}
			if ( count( $opts ) > 1 ) {
				$h .= self::select( 'city', 'شهر', $opts, $r['city'] );
			}
		}
		if ( in_array( 'venue', $active, true ) && 'galleries' === $type ) {
			$h .= self::select( 'venue', 'نوع گالری', array( '' => 'همه انواع', 'physical' => 'فیزیکی', 'online' => 'آنلاین', 'hybrid' => 'فیزیکی و آنلاین' ), $r['venue'] );
		}
		if ( in_array( 'nationality', $active, true ) && 'artists' === $type ) {
			$opts = array( '' => 'همه ملیت‌ها' );
			foreach ( self::artist_nationalities() as $value ) { $opts[ $value ] = $value; }
			if ( count( $opts ) > 1 ) { $h .= self::select( 'nationality', 'ملیت / خاستگاه', $opts, $r['nationality'] ); }
		}
		if ( in_array( 'medium', $active, true ) && 'artists' === $type ) {
			$opts = array( '' => 'همه رسانه‌ها و تکنیک‌ها' );
			foreach ( self::artist_mediums() as $value ) { $opts[ $value ] = $value; }
			if ( count( $opts ) > 1 ) { $h .= self::select( 'medium', 'رسانه / تکنیک', $opts, $r['medium'] ); }
		}
		if ( in_array( 'style', $active, true ) ) {
			$opts = array( '' => 'همه سبک‌ها' );
			foreach ( self::styles() as $t ) {
				$opts[ $t->slug ] = $t->name;
			}
			if ( count( $opts ) > 1 ) {
				$h .= self::select( 'style', 'سبک هنری', $opts, $r['style'] );
			}
		}
		if ( in_array( 'status', $active, true ) && 'artworks' === $type ) {
			$opts = array( '' => 'همه وضعیت‌ها' );
			foreach ( Artiey_AET_Artwork::SALE as $k => $label ) {
				$opts[ $k ] = $label;
			}
			$h .= self::select( 'status', 'وضعیت فروش', $opts, $r['status'] );
		}
		if ( in_array( 'artist', $active, true ) && in_array( $type, array( 'artworks', 'exhibitions' ), true ) ) {
			$opts = array( '' => 'همه هنرمندان' );
			foreach ( self::artist_options() as $id => $title ) {
				$opts[ $id ] = $title;
			}
			if ( count( $opts ) > 1 ) {
				$h .= self::select( 'artist', 'هنرمند', $opts, $r['artist'] ? $r['artist'] : '' );
			}
		}
		if ( in_array( 'status', $active, true ) && 'exhibitions' === $type ) {
			$h .= self::select(
				'status',
				'وضعیت',
				array(
					''         => 'همه',
					'now'      => 'در حال برگزاری',
					'upcoming' => 'به‌زودی',
					'past'     => 'گذشته',
				),
				$r['status']
			);
		}
		if ( in_array( 'gallery', $active, true ) && in_array( $type, array( 'exhibitions', 'artists', 'artworks' ), true ) ) {
			$opts = array( '' => 'همه گالری‌ها' );
			foreach ( self::gallery_options() as $id => $title ) {
				$opts[ $id ] = $title;
			}
			if ( count( $opts ) > 1 ) {
				$h .= self::select( 'gallery', 'گالری', $opts, $r['gallery'] ? $r['gallery'] : '' );
			}
		}
		if ( 'artists' === $type && in_array( 'works', $active, true ) ) {
			$h .= self::select( 'works', 'آثار ثبت‌شده', array( '' => 'همه هنرمندان', 'yes' => 'دارای اثر ثبت‌شده', 'no' => 'بدون اثر ثبت‌شده' ), $r['works'] );
		}
		if ( 'artists' === $type && in_array( 'exhibitions', $active, true ) ) {
			$h .= self::select( 'exhibitions', 'سوابق نمایشگاهی', array( '' => 'همه هنرمندان', 'yes' => 'دارای نمایشگاه', 'no' => 'بدون نمایشگاه ثبت‌شده' ), $r['exhibitions'] );
		}
		if ( 'artists' === $type && in_array( 'curator', $active, true ) ) {
			$curators = array( '' => 'همه کیوریتورها' ) + self::curator_options();
			if ( count( $curators ) > 1 ) { $h .= self::select( 'curator', 'کیوریتور نمایشگاه', $curators, $r['curator'] ? $r['curator'] : '' ); }
		}
		if ( in_array( 'sort', $active, true ) ) {
			$h .= self::select( 'sort', 'مرتب‌سازی', self::sort_options( $type ), $r['sort'] );
		}
		if ( in_array( 'open', $active, true ) && 'galleries' === $type ) {
			$h .= '<label class="aetd-f aetd-f-check"><input type="checkbox" name="aet_open" value="1" ' . checked( '1', $r['open'], false ) . '> فقط گالری‌های بازِ هم‌اکنون</label>';
		}

		$h .= '<div class="aetd-actions"><button type="submit" class="aetd-btn">اعمال فیلتر</button> <a class="aetd-reset" href="' . esc_url( $base ) . '">حذف فیلترها</a></div>';
		return $h . '</form>';
	}

	private static function pager( $q, $r, $base ) {
		$total = (int) $q->max_num_pages;
		if ( $total < 2 ) {
			return '';
		}
		$params = array();
		foreach ( array( 'q', 'city', 'style', 'status', 'gallery', 'artist', 'open', 'sort', 'venue', 'nationality', 'medium', 'works', 'exhibitions', 'curator' ) as $k ) {
			if ( '' !== (string) $r[ $k ] && 0 !== $r[ $k ] ) {
				$params[ 'aet_' . $k ] = $r[ $k ];
			}
		}
		$tpl   = add_query_arg( array_merge( $params, array( 'aet_page' => 999999999 ) ), $base );
		$links = paginate_links(
			array(
				'base'      => str_replace( '999999999', '%#%', $tpl ),
				'format'    => '',
				'current'   => $r['page'],
				'total'     => $total,
				'type'      => 'array',
				'prev_text' => 'قبلی',
				'next_text' => 'بعدی',
				'mid_size'  => 1,
			)
		);
		if ( ! $links ) {
			return '';
		}
		$h = '<nav class="aetd-pager" aria-label="صفحه‌بندی"><ul>';
		foreach ( $links as $l ) {
			$h .= '<li>' . $l . '</li>';
		}
		return $h . '</ul></nav>';
	}

	public static function build( $args, $r ) {
		$base = self::base_url( $args );
		$type = $args['type'];

		// Public directory HTML is identical for anonymous visitors. Cache the
		// rendered cards + pagination briefly so repeat visits avoid the main
		// WP_Query, relation lookups and per-card meta work. Never cache logged-in
		// output or preview/editor requests. The generation-based key is bumped
		// by content, status and taxonomy changes above.
		$cacheable = ! is_user_logged_in() && ! is_preview() && ! ( defined( 'ELEMENTOR_VERSION' ) && isset( $_GET['elementor-preview'] ) );
		// Free-text filters are attacker-controlled and unbounded: caching every distinct
		// value would let a crawler fill wp_options with one transient per request.
		if ( ! empty( $r['q'] ) || ! empty( $r['nationality'] ) || ! empty( $r['medium'] ) ) {
			$cacheable = false;
		}

		// Default sort per type.
		$valid = self::sort_options( $type );
		if ( ! array_key_exists( $r['sort'], $valid ) ) {
			// Visitor did not pick one: use the widget/shortcode default when it fits this type.
			$configured = $args['sort'];
			$defaults   = array( 'exhibitions' => 'date_desc', 'artworks' => 'newest' );
			if ( array_key_exists( $configured, $valid ) || ( 'manual' === $configured && ! empty( $args['include'] ) ) ) {
				$r['sort'] = $configured;
			} else {
				$r['sort'] = isset( $defaults[ $type ] ) ? $defaults[ $type ] : 'title';
			}
		}

		$public_cache_key = '';
		if ( $cacheable ) {
			$public_cache_key = self::cache_key( 'html_' . md5( wp_json_encode( array( $args, $r, $base, get_locale(), current_time( 'Y-m-d' ) ) ) ) );
			$cached_html = wp_using_ext_object_cache()
				? wp_cache_get( $public_cache_key, 'artiey_directory_html' )
				: get_transient( $public_cache_key );
			if ( false !== $cached_html && is_string( $cached_html ) ) {
				return $cached_html;
			}
		}

		$q = self::run_query( $args, $r );

		$nouns = array(
			'galleries'   => 'گالری',
			'exhibitions' => 'نمایشگاه',
			'artists'     => 'هنرمند',
			'artworks'    => 'اثر',
		);

		$h  = self::filter_form( $args, $r, $base );
		if ( 'no' !== $args['show_count'] ) {
			$h .= '<div class="aetd-count">' . esc_html( self::fa( (int) $q->found_posts ) . ' ' . $nouns[ $type ] . ' یافت شد' ) . '</div>';
		}

		if ( ! $q->have_posts() ) {
			$h .= '<p class="aetd-empty">' . esc_html( $args['empty_text'] ? $args['empty_text'] : 'موردی با این فیلترها پیدا نشد.' ) . '</p>';
			if ( $public_cache_key ) {
				if ( wp_using_ext_object_cache() ) {
					wp_cache_set( $public_cache_key, $h, 'artiey_directory_html', self::PUBLIC_HTML_CACHE_TTL );
				} else {
					set_transient( $public_cache_key, $h, self::PUBLIC_HTML_CACHE_TTL );
				}
			}
			return $h;
		}

		$h .= '<div class="aetp-grid aap-s-a0dcbe" style="--aetp-cols:' . esc_attr( (string) $args['columns'] ) . '">';
		foreach ( $q->posts as $p ) {
			if ( 'galleries' === $type ) {
				$h .= Artiey_AET_Gallery::card( $p );
			} elseif ( 'exhibitions' === $type ) {
				$h .= Artiey_AET_Exhibition::card( $p, array( 'show_gallery' => true ) );
			} elseif ( 'artworks' === $type ) {
				$h .= Artiey_AET_Artwork::card( $p );
			} else {
				$h .= Artiey_AET_Artist::card( $p );
			}
		}
		$h .= '</div>';
		$h .= self::pager( $q, $r, $base );
		wp_reset_postdata();
		if ( $public_cache_key ) {
			if ( wp_using_ext_object_cache() ) {
				wp_cache_set( $public_cache_key, $h, 'artiey_directory_html', self::PUBLIC_HTML_CACHE_TTL );
			} else {
				set_transient( $public_cache_key, $h, self::PUBLIC_HTML_CACHE_TTL );
			}
		}
		return $h;
	}
}
