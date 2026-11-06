<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dynamic, cached counts for the public art content. */
final class Artiey_AET_Statistics {
	const CACHE_TTL = 60;

	public static function init() {
		add_shortcode( 'artiey_stats', array( __CLASS__, 'shortcode' ) );
		foreach ( array( 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' ) as $type ) {
			add_action( 'save_post_' . $type, array( __CLASS__, 'invalidate' ), 999 );
		}
		add_action( 'transition_post_status', array( __CLASS__, 'on_status_change' ), 10, 3 );
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms_change' ), 10, 6 );
		// Counts and relationship totals also depend on metadata, not only post saves.
		add_action( 'added_post_meta', array( __CLASS__, 'on_post_meta_change' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_post_meta_change' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_post_meta_change' ), 10, 4 );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_deleted' ), 10, 1 );
		foreach ( array( 'wp_coa_pro_certificate_issued', 'wp_coa_pro_certificate_revoked', 'wp_coa_pro_certificate_transferred', 'wp_art_coa_certificate_issued' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'invalidate' ), 1 );
		}
	}

	private static $invalidated_this_request = false;

	public static function invalidate( ...$ignored ) {
		if ( self::$invalidated_this_request ) {
			return;
		}
		self::$invalidated_this_request = true;
		$version = max( 1, (int) get_option( 'artiey_stats_version', 1 ) );
		update_option( 'artiey_stats_version', $version + 1, false );
	}

	/** Invalidate relationship/count caches when an entity's linked IDs or fields change. */
	public static function on_post_meta_change( $meta_id, $object_id, $meta_key, $meta_value = null ) {
		$post_type = get_post_type( (int) $object_id );
		if ( in_array( $post_type, array( 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' ), true ) ) {
			self::invalidate();
		}
	}

	/** Deletions change global and per-entity counts even when no save hook runs. */
	public static function on_post_deleted( $post_id ) {
		$post_type = get_post_type( (int) $post_id );
		if ( in_array( $post_type, array( 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' ), true ) ) {
			self::invalidate();
		}
	}

	public static function on_terms_change( $object_id ) {
		if ( in_array( get_post_type( (int) $object_id ), array( 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' ), true ) ) {
			self::invalidate();
		}
	}

	public static function on_status_change( $new, $old, $post ) {
		if ( $new !== $old && $post instanceof WP_Post && in_array( $post->post_type, array( 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' ), true ) ) {
			self::invalidate();
		}
	}

	private static function cached( $key, $callback ) {
		$version = (int) get_option( 'artiey_stats_version', 1 );
		$transient = 'aet_stats_' . $version . '_' . md5( $key );
		$value = get_transient( $transient );
		if ( false === $value ) {
			$value = call_user_func( $callback );
			set_transient( $transient, $value, self::CACHE_TTL );
		}
		return $value;
	}

	private static function count_posts( $type, $meta_query = array() ) {
		$q = new WP_Query( array(
			'post_type'              => $type,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => $meta_query,
		) );
		return (int) $q->found_posts;
	}

	private static function linked_ids( $meta_key, $id ) {
		$ids = get_post_meta( $id, $meta_key, true );
		$ids = is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
		if ( ! $ids ) {
			return array();
		}
		$published = get_posts( array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'post__in'       => $ids,
			'posts_per_page' => count( $ids ),
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		return array_map( 'intval', $published );
	}

	private static function serialized_relation_query( $id ) {
		return array( array( 'key' => '_ae_artists', 'value' => ';i:' . absint( $id ) . ';', 'compare' => 'LIKE' ) );
	}

	public static function for_gallery( $id ) {
		$id = absint( $id );
		return self::cached( 'gallery_' . $id, static function () use ( $id ) {
			return array(
				'artists'     => count( self::linked_ids( '_ag_artists', $id ) ),
				'artworks'    => self::count_posts( 'artiey_artwork', array( array( 'key' => '_aw_gallery', 'value' => $id, 'type' => 'NUMERIC' ) ) ),
				'exhibitions' => self::count_posts( 'artiey_exhibition', array( array( 'key' => '_ae_gallery', 'value' => $id, 'type' => 'NUMERIC' ) ) ),
				'certificates'=> Artiey_AET_COA_Bridge::active_count_for_gallery_post( $id ),
			);
		} );
	}

	public static function for_artist( $id ) {
		$id = absint( $id );
		return self::cached( 'artist_' . $id, static function () use ( $id ) {
			return array(
				'artworks'    => self::count_posts( 'artiey_artwork', array( array( 'key' => '_aw_artist', 'value' => $id, 'type' => 'NUMERIC' ) ) ),
				'exhibitions' => self::count_posts( 'artiey_exhibition', self::serialized_relation_query( $id ) ),
				'galleries'   => count( Artiey_AET_Artist::gallery_ids_of( $id ) ),
				'certificates'=> Artiey_AET_COA_Bridge::active_count_for_artist_post( $id ),
			);
		} );
	}

	public static function for_artwork( $id ) {
		$id = absint( $id );
		return self::cached( 'artwork_' . $id, static function () use ( $id ) {
			$a = Artiey_AET_Artwork::get( $id );
			return array(
				'photos'       => count( array_unique( array_merge( $a['photos'], has_post_thumbnail( $id ) ? array( (int) get_post_thumbnail_id( $id ) ) : array() ) ) ),
				'certificates' => count( Artiey_AET_COA_Bridge::certificate_summaries( $id ) ),
				'related'      => $a['artist'] ? max( 0, self::count_posts( 'artiey_artwork', array( array( 'key' => '_aw_artist', 'value' => $a['artist'], 'type' => 'NUMERIC' ) ) ) - ( 'publish' === get_post_status( $id ) ? 1 : 0 ) ) : 0,
			);
		} );
	}

	public static function for_exhibition( $id ) {
		$id = absint( $id );
		return self::cached( 'exhibition_' . $id, static function () use ( $id ) {
			$artists = self::linked_ids( '_ae_artists', $id );
			$start = get_post_meta( $id, '_ae_start', true );
			$end = get_post_meta( $id, '_ae_end', true );
			$days = 0;
			if ( $start && $end ) {
				$from = DateTime::createFromFormat( '!Y-m-d', $start );
				$to = DateTime::createFromFormat( '!Y-m-d', $end );
				if ( $from && $to && $to >= $from ) {
					$days = (int) $from->diff( $to )->days + 1;
				}
			}
			return array( 'artists' => count( $artists ), 'days' => $days );
		} );
	}

	private static function global_counts() {
		return self::cached( 'global', static function () {
			$counts = array(
				'galleries'   => self::published( 'artiey_gallery' ),
				'artists'     => self::published( 'artiey_artist' ),
				'artworks'    => self::published( 'artiey_artwork' ),
				'exhibitions' => self::published( 'artiey_exhibition' ),
			);
			$today = current_time( 'Y-m-d' );
			$counts['current'] = self::count_posts( 'artiey_exhibition', array(
				'relation' => 'AND',
				array( 'key' => '_ae_start', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ),
				array( 'key' => '_ae_end', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
			) );
			$counts['upcoming'] = self::count_posts( 'artiey_exhibition', array( array( 'key' => '_ae_start', 'value' => $today, 'compare' => '>', 'type' => 'DATE' ) ) );
			return $counts;
		} );
	}

	private static function published( $type ) {
		$counts = wp_count_posts( $type );
		return $counts && isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	private static function fa( $number ) {
		return Artiey_AET_Jalali::to_persian_digits( (string) absint( $number ) );
	}

	public static function profile_strip( $type, $id ) {
		$labels = array(
			'artiey_gallery' => array( 'artists' => 'هنرمند مرتبط', 'artworks' => 'اثر ثبت‌شده', 'exhibitions' => 'نمایشگاه', 'certificates' => 'گواهی فعال' ),
			'artiey_artist'  => array( 'artworks' => 'اثر ثبت‌شده', 'exhibitions' => 'نمایشگاه', 'galleries' => 'گالری همکار', 'certificates' => 'گواهی فعال' ),
			'artiey_artwork' => array( 'photos' => 'تصویر', 'certificates' => 'گواهی', 'related' => 'اثر دیگر هنرمند' ),
			'artiey_exhibition' => array( 'artists' => 'هنرمند شرکت‌کننده', 'days' => 'روز برگزاری' ),
		);
		$methods = array( 'artiey_gallery' => 'for_gallery', 'artiey_artist' => 'for_artist', 'artiey_artwork' => 'for_artwork', 'artiey_exhibition' => 'for_exhibition' );
		if ( ! isset( $methods[ $type ] ) ) {
			return '';
		}
		$data = call_user_func( array( __CLASS__, $methods[ $type ] ), $id );
		$html = '<div class="aetp-stats" aria-label="آمار این صفحه">';
		foreach ( $labels[ $type ] as $key => $label ) {
			if ( 'certificates' === $key && ! Artiey_AET_COA_Bridge::available() ) {
				continue;
			}
			$html .= '<div class="aetp-stat"><strong>' . esc_html( self::fa( $data[ $key ] ?? 0 ) ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
		}
		return $html . '</div>';
	}

	public static function shortcode( $atts ) {
		wp_enqueue_style( 'artiey-aet-profile' );
		$atts = shortcode_atts( array( 'metrics' => 'galleries,artists,artworks,exhibitions,current,upcoming', 'title' => 'yes' ), (array) $atts, 'artiey_stats' );
		$labels = array( 'galleries' => 'گالری', 'artists' => 'هنرمند', 'artworks' => 'اثر هنری', 'exhibitions' => 'نمایشگاه', 'current' => 'نمایشگاه جاری', 'upcoming' => 'نمایشگاه پیش‌رو' );
		$wanted = array_intersect( array_keys( $labels ), array_map( 'trim', explode( ',', (string) $atts['metrics'] ) ) );
		if ( ! $wanted ) {
			return '';
		}
		$data = self::global_counts();
		$html = '<div class="aetp aetp-global-stats" dir="rtl">';
		if ( 'no' !== $atts['title'] && '' !== trim( (string) $atts['title'] ) ) {
			$html .= '<h2 class="aetp-stats-title">' . esc_html( $atts['title'] ) . '</h2>';
		}
		$html .= '<div class="aetp-stats">';
		foreach ( $wanted as $key ) {
			$html .= '<div class="aetp-stat"><strong>' . esc_html( self::fa( $data[ $key ] ?? 0 ) ) . '</strong><span>' . esc_html( $labels[ $key ] ) . '</span></div>';
		}
		return $html . '</div></div>';
	}
}
