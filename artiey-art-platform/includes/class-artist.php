<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Professional artist profile.
 */
final class Artiey_AET_Artist {

	const ALL_SECTIONS = array( 'header', 'short_bio', 'statement', 'bio', 'details', 'awards', 'career_timeline', 'contact', 'works', 'galleries', 'exhibitions', 'activity_chart', 'sales_chart', 'artwork_history', 'interviews', 'certificate_stats' );

	/** Request-local relationship cache; Elementor tab mode can render profile sections independently. */
	private static $related_artworks_cache = array();
	private static $related_gallery_ids_cache = array();
	private static $related_exhibitions_cache = array();
	private static $coa_artist_id_cache = array();
	private static $profile_cache = array();

	public static function init() {
		add_shortcode( 'artiey_artist_profile', array( __CLASS__, 'shortcode_profile' ) );
		add_shortcode( 'artiey_artist_field', array( __CLASS__, 'shortcode_field' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
	}

	private static function fa( $v ) {
		return Artiey_AET_Jalali::to_persian_digits( (string) $v );
	}

	private static function meta( $id, $key ) {
		return get_post_meta( $id, '_aa_' . $key, true );
	}

	/** A public profile is public only when its canonical WordPress post is published. */
	private static function can_view( $id ) {
		$status = get_post_status( $id );
		return 'publish' === $status || ( function_exists( 'current_user_can' ) && current_user_can( 'edit_post', absint( $id ) ) );
	}

	public static function invalidate_profile( $id = 0 ) {
		if ( $id ) { unset( self::$profile_cache[ absint( $id ) ] ); } else { self::$profile_cache = array(); }
	}

	public static function get( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return array();
		}
		if ( isset( self::$profile_cache[ $id ] ) ) {
			return self::$profile_cache[ $id ];
		}

		$awards = preg_split( '/\R+/u', (string) self::meta( $id, 'awards' ) );
		$awards = array_values( array_filter( array_map( 'trim', (array) $awards ), 'strlen' ) );
		$works  = self::meta( $id, 'works' );
		// Older versions or imports may have stored the portfolio as a comma-separated string.
		if ( is_string( $works ) ) {
			$works = preg_split( '/[,;\s]+/', $works );
		}
		$candidate_work_ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $works ) ? $works : array() ) ) ) );
		$candidate_work_ids = array_slice( $candidate_work_ids, 0, 60 );
		$clean_works = array();
		if ( $candidate_work_ids ) {
			// One bounded attachment query replaces repeated post-type and MIME lookups per image.
			$clean_works = get_posts( array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'image',
				'numberposts'            => count( $candidate_work_ids ),
				'post__in'               => $candidate_work_ids,
				'orderby'                => 'post__in',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			) );
			$clean_works = array_values( array_filter( array_map( static function ( $attachment ) {
				return $attachment instanceof WP_Post ? absint( $attachment->ID ) : absint( $attachment );
			}, (array) $clean_works ) ) );
		}

		self::$profile_cache[ $id ] = array(
			'id'        => $id,
			'name'      => get_the_title( $id ),
			'name_en'   => (string) self::meta( $id, 'name_en' ),
			'birth'     => (int) self::meta( $id, 'birth' ),
			'city'      => (string) self::meta( $id, 'city' ),
			'nationality' => (string) self::meta( $id, 'nationality' ),
			'short_bio' => (string) self::meta( $id, 'short_bio' ),
			'statement' => (string) self::meta( $id, 'statement' ),
			'mediums'   => (string) self::meta( $id, 'mediums' ),
			'education' => (string) self::meta( $id, 'education' ),
			'timeline'  => (string) self::meta( $id, 'timeline' ),
			'interviews' => (string) self::meta( $id, 'interviews' ),
			'related_media' => (string) self::meta( $id, 'related_media' ),
			'awards'    => $awards,
			'instagram' => (string) self::meta( $id, 'instagram' ),
			'telegram'  => (string) self::meta( $id, 'telegram' ),
			'website'   => (string) self::meta( $id, 'website' ),
			'email'     => (string) self::meta( $id, 'email' ),
			'cover'     => (int) self::meta( $id, 'cover' ),
			'works'     => $clean_works,
		);
		return self::$profile_cache[ $id ];
	}

	/** Published catalogue records directly linked to this artist. */
	public static function artworks_of( $artist_id, $limit = 24, $include_coa = true ) {
		$artist_id = absint( $artist_id );
		$limit     = max( 1, min( 48, absint( $limit ) ) );
		if ( ! $artist_id ) {
			return array();
		}
		$key = $artist_id . ':' . $limit . ':' . ( $include_coa ? 'coa' : 'wp' );
		if ( ! array_key_exists( $key, self::$related_artworks_cache ) ) {
			$posts = get_posts( array(
				'post_type'              => 'artiey_artwork',
				'post_status'            => 'publish',
				'numberposts'            => $limit,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
				'meta_query'             => array( array( 'key' => '_aw_artist', 'value' => $artist_id, 'type' => 'NUMERIC' ) ),
			) );
			$posts = is_array( $posts ) ? $posts : array();
			$known = array();
			foreach ( $posts as $post ) { if ( $post instanceof WP_Post ) { $known[ $post->ID ] = $post; } }

			// Repair a missing WordPress mirror link from the canonical COA artist→artwork relation.
			$coa_artist_id = $include_coa ? self::coa_artist_id( $artist_id ) : 0;
			if ( $include_coa && $coa_artist_id && count( $known ) < $limit && class_exists( '\\WPArtCOA\\Database' ) ) {
				global $wpdb;
				$sql = 'SELECT cpt_post_id, slug FROM ' . \WPArtCOA\Database::table( 'artworks' ) . ' '
					. "WHERE artist_id = %d AND status IN ('approved','published','active','sold') "
					. 'ORDER BY id DESC LIMIT 200';
				$rows = $wpdb->get_results( $wpdb->prepare( $sql, $coa_artist_id ) );
				$id_slugs = array();
				$slugs = array();
				foreach ( (array) $rows as $row ) {
					$post_id = absint( $row->cpt_post_id ?? 0 );
					$slug = sanitize_title( (string) ( $row->slug ?? '' ) );
					if ( $post_id && $slug ) { $id_slugs[ $post_id ] = $slug; }
					if ( $slug ) { $slugs[] = $slug; }
				}
				$queries = array();
				if ( $id_slugs ) {
					$queries[] = get_posts( array( 'post_type' => 'artiey_artwork', 'post_status' => 'publish', 'numberposts' => 200, 'post__in' => array_keys( $id_slugs ), 'no_found_rows' => true, 'update_post_meta_cache' => true, 'update_post_term_cache' => true ) );
				}
				if ( $slugs ) {
					$queries[] = get_posts( array( 'post_type' => 'artiey_artwork', 'post_status' => 'publish', 'numberposts' => 200, 'post_name__in' => array_values( array_unique( $slugs ) ), 'no_found_rows' => true, 'update_post_meta_cache' => true, 'update_post_term_cache' => true ) );
				}
				foreach ( $queries as $query_posts ) {
					foreach ( (array) $query_posts as $post ) {
						if ( ! ( $post instanceof WP_Post ) || 'publish' !== get_post_status( $post ) ) { continue; }
						$expected_slug = $id_slugs[ $post->ID ] ?? '';
						if ( $expected_slug && $expected_slug !== sanitize_title( (string) $post->post_name ) ) { continue; }
						$known[ $post->ID ] = $post;
						if ( count( $known ) >= $limit ) { break 2; }
					}
				}
			}
			self::$related_artworks_cache[ $key ] = array_slice( array_values( $known ), 0, $limit );
		}
		return is_array( self::$related_artworks_cache[ $key ] ) ? self::$related_artworks_cache[ $key ] : array();
	}

	/**
	 * Galleries connected either directly through the gallery artist list or indirectly
	 * through a published catalogue artwork whose _aw_gallery points to that gallery.
	 * This makes the artist → artwork → gallery relationship visible without guessing
	 * links from matching names or free-text metadata.
	 */
	public static function gallery_ids_of( $artist_id, $limit = 500, $include_coa = true ) {
		$artist_id = absint( $artist_id );
		$limit     = max( 1, min( 1000, absint( $limit ) ) );
		if ( ! $artist_id ) {
			return array();
		}
		$key = $artist_id . ':' . $limit . ':' . ( $include_coa ? 'coa' : 'wp' );
		if ( array_key_exists( $key, self::$related_gallery_ids_cache ) ) {
			return self::$related_gallery_ids_cache[ $key ];
		}
		$ids = get_posts( array(
			'post_type'              => 'artiey_gallery',
			'post_status'            => 'publish',
			'numberposts'            => $limit,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( array( 'key' => '_ag_artists', 'value' => ';i:' . $artist_id . ';', 'compare' => 'LIKE' ) ),
		) );
		$ids = array_map( 'absint', (array) $ids );

		// Prime the artwork meta cache in one query; never infer a gallery from an artist name.
		$work_fetch_limit = min( 500, max( 24, $limit * 4 ) );
		$work_ids = get_posts( array(
			'post_type'              => 'artiey_artwork',
			'post_status'            => 'publish',
			'numberposts'            => $work_fetch_limit,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'meta_query'             => array( array( 'key' => '_aw_artist', 'value' => $artist_id, 'type' => 'NUMERIC' ) ),
		) );
		foreach ( (array) $work_ids as $work_id ) {
			$gallery_id = absint( get_post_meta( $work_id, '_aw_gallery', true ) );
			if ( $gallery_id && ! in_array( $gallery_id, $ids, true ) && 'artiey_gallery' === get_post_type( $gallery_id ) && 'publish' === get_post_status( $gallery_id ) ) {
				$ids[] = $gallery_id;
				if ( count( $ids ) >= $limit ) {
					break;
				}
			}
		}

		// COA may hold a valid artwork→gallery relation before every mirror meta has been repaired.
		$coa_artist_id = $include_coa ? self::coa_artist_id( $artist_id ) : 0;
		if ( $include_coa && $coa_artist_id && class_exists( '\\WPArtCOA\\Database' ) && count( $ids ) < $limit ) {
			global $wpdb;
			$sql = 'SELECT DISTINCT g.cpt_post_id, g.slug '
				. 'FROM ' . \WPArtCOA\Database::table( 'galleries' ) . ' g '
				. 'INNER JOIN ' . \WPArtCOA\Database::table( 'artworks' ) . ' a ON a.gallery_id = g.id '
				. "WHERE a.artist_id = %d AND a.status IN ('approved','published','active','sold') AND g.status = 'active' "
				. 'ORDER BY g.name ASC LIMIT 200';
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $coa_artist_id ) );
			$ids_by_slug = array();
			$slugs = array();
			foreach ( (array) $rows as $row ) {
				$post_id = absint( $row->cpt_post_id ?? 0 );
				$slug = sanitize_title( (string) ( $row->slug ?? '' ) );
				if ( $post_id && $slug ) { $ids_by_slug[ $post_id ] = $slug; }
				if ( $slug ) { $slugs[] = $slug; }
			}
			$slugs = array_values( array_unique( $slugs ) );
			$coa_queries = array();
			if ( $ids_by_slug ) {
				$coa_queries[] = get_posts( array( 'post_type' => 'artiey_gallery', 'post_status' => 'publish', 'numberposts' => 200, 'post__in' => array_keys( $ids_by_slug ), 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) );
			}
			if ( $slugs ) {
				$coa_queries[] = get_posts( array( 'post_type' => 'artiey_gallery', 'post_status' => 'publish', 'numberposts' => 200, 'post_name__in' => $slugs, 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) );
			}
			foreach ( $coa_queries as $gallery_posts ) {
				foreach ( (array) $gallery_posts as $gallery_post ) {
					if ( ! ( $gallery_post instanceof WP_Post ) || 'publish' !== get_post_status( $gallery_post ) ) { continue; }
					$expected_slug = $ids_by_slug[ $gallery_post->ID ] ?? '';
					if ( $expected_slug && $expected_slug !== sanitize_title( (string) $gallery_post->post_name ) ) { continue; }
					if ( ! in_array( $gallery_post->ID, $ids, true ) ) { $ids[] = $gallery_post->ID; }
					if ( count( $ids ) >= $limit ) { break 2; }
				}
			}
		}
		self::$related_gallery_ids_cache[ $key ] = array_slice( array_values( array_unique( array_filter( $ids ) ) ), 0, $limit );
		return self::$related_gallery_ids_cache[ $key ];
	}

	/** All published galleries connected to this artist, directly or through catalogue artworks. */
	public static function galleries_of( $artist_id, $limit = 40, $include_coa = true ) {
		$limit = max( 1, min( 40, absint( $limit ) ) );
		$ids = self::gallery_ids_of( $artist_id, $limit, $include_coa );
		if ( ! $ids ) {
			return array();
		}
		return get_posts( array(
			'post_type'              => 'artiey_gallery',
			'post_status'            => 'publish',
			'numberposts'            => min( $limit, count( $ids ) ),
			'post__in'               => $ids,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		) );
	}

	/** Compact card for lists / directory. */
	public static function card( $p ) {
		$en    = get_post_meta( $p->ID, '_aa_name_en', true );
		$city  = get_post_meta( $p->ID, '_aa_city', true );
		$terms = get_the_terms( $p->ID, 'artiey_style' );
		$style = ( $terms && ! is_wp_error( $terms ) ) ? implode( '، ', wp_list_pluck( array_slice( $terms, 0, 2 ), 'name' ) ) : '';

		$h  = '<a class="aetp-card aetp-artist" href="' . esc_url( get_permalink( $p ) ) . '">';
		$h .= '<span class="aetp-card-img">' . ( has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'medium', array( 'loading' => 'lazy', 'alt' => get_the_title( $p ) ) ) : '<span class="aetp-noposter">' . esc_html( Artiey_AET_Text::substr( get_the_title( $p ), 0, 1 ) ) . '</span>' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$h .= '<span class="aetp-card-title">' . esc_html( get_the_title( $p ) ) . '</span>';
		if ( $en ) {
			$h .= '<span class="aetp-card-sub" dir="ltr">' . esc_html( $en ) . '</span>';
		}
		if ( $city ) {
			$h .= '<span class="aetp-card-sub">' . esc_html( $city ) . '</span>';
		}
		if ( $style ) {
			$h .= '<span class="aetp-card-sub aetp-card-style">' . esc_html( $style ) . '</span>';
		}
		return $h . '</a>';
	}

	/* ---------------------------------------------------------------- */
	/* Sections                                                          */
	/* ---------------------------------------------------------------- */

	private static function style_chips( $id ) {
		$terms = get_the_terms( $id, 'artiey_style' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$h = '';
		foreach ( $terms as $t ) {
			$h .= '<a class="aetp-chip" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a> ';
		}
		return $h;
	}

	private static function age_text( $a ) {
		if ( ! $a['birth'] ) {
			return '';
		}
		$now = (int) Artiey_AET_Jalali::ymd_to_parts( current_time( 'Y-m-d' ) )[0];
		$txt = 'متولد ' . self::fa( $a['birth'] );
		if ( $now > $a['birth'] ) {
			$txt .= ' (' . self::fa( $now - $a['birth'] ) . ' ساله)';
		}
		return $txt;
	}

	private static function s_header( $a, $show_title, $options = array() ) {
		$id = $a['id'];
		$show = static function ( $key ) use ( $options ) {
			return ! array_key_exists( $key, $options ) || 'yes' === $options[ $key ];
		};
		$cover = ( $show( 'header_show_cover' ) && $a['cover'] ) ? wp_get_attachment_image( $a['cover'], 'large', false, array( 'class' => 'aetp-cover', 'alt' => '', 'loading' => 'lazy' ) ) : '';

		$h  = '<header class="aetp-hero aetp-hero-artist' . ( $cover ? ' has-cover' : '' ) . '">';
		$h .= '<div class="aetp-cover-wrap">' . $cover . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$h .= '<div class="aetp-hero-inner">';
		if ( $show( 'header_show_avatar' ) && has_post_thumbnail( $id ) ) {
			$h .= '<div class="aetp-avatar">' . get_the_post_thumbnail( $id, 'medium', array( 'alt' => $a['name'], 'loading' => 'lazy' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$h .= '<div class="aetp-names">';
		if ( $show_title ) {
			$title_tag = isset( $options['header_title_tag'] ) && in_array( $options['header_title_tag'], array( 'h1', 'h2', 'h3', 'h4', 'div' ), true ) ? $options['header_title_tag'] : 'h2';
			$h .= '<' . $title_tag . ' class="aetp-title">' . esc_html( $a['name'] ) . '</' . $title_tag . '>';
		}
		if ( $show( 'header_show_name_en' ) && $a['name_en'] ) {
			$h .= '<div class="aetp-title-en" dir="ltr">' . esc_html( $a['name_en'] ) . '</div>';
		}
		$meta = array();
		if ( $show( 'header_show_city' ) && $a['city'] ) {
			$meta[] = esc_html( $a['city'] );
		}
		$age = $show( 'header_show_age' ) ? self::age_text( $a ) : '';
		if ( $age ) {
			$meta[] = esc_html( $age );
		}
		if ( $meta ) {
			$h .= '<div class="aetp-meta">' . implode( ' <span class="aetp-dot">·</span> ', $meta ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$chips = $show( 'header_show_styles' ) ? self::style_chips( $id ) : '';
		if ( $chips ) {
			$h .= '<div class="aetp-hero-chips">' . $chips . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$h .= '</div>';
		if ( $show( 'header_show_follow' ) && empty( $options['elementor_light_preview'] ) && class_exists( '\\WPArtCOA\\Follows' ) ) {
			$subject = \WPArtCOA\Follows::subject_for_post( (int) $id );
			if ( $subject ) {
				$h .= '<div class="aetp-hero-follow">' . \WPArtCOA\Follows::button_html( $subject[0], (int) $subject[1] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		return $h . '</div></header>';
	}

	private static function s_short_bio( $a ) {
		return '' === trim( $a['short_bio'] ) ? '' : '<section class="aetp-sec aetp-short-bio"><h3>معرفی کوتاه</h3><p class="aetp-lead">' . esc_html( $a['short_bio'] ) . '</p></section>';
	}

	/** Editorial statement is independent so Elementor users can toggle it without disabling the short bio. */
	private static function s_statement( $a ) {
		if ( '' === trim( $a['statement'] ) ) {
			return '';
		}
		return '<section class="aetp-sec aetp-statement"><h3>بیانیهٔ هنری</h3><div class="aetp-bio-text">' . nl2br( esc_html( $a['statement'] ) ) . '</div></section>';
	}

	/** Split cleaned biography markup into semantic blocks for the progressive read-more control. */
	private static function bio_blocks( $html ) {
		$html = (string) $html;
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return array();
		}
		if ( preg_match_all( '#<(p|ul|ol|blockquote|h[2-6])\b[^>]*>.*?</\1>#isu', $html, $matches ) && ! empty( $matches[0] ) ) {
			return array_values( $matches[0] );
		}
		return array( $html );
	}

	private static function s_bio( $a, $options = array() ) {
		$html = Artiey_AET_Profile_Guard::bio_html( get_post( $a['id'] ) );
		$blocks = self::bio_blocks( $html );
		if ( ! $blocks ) {
			return '';
		}
		$readmore = ! isset( $options['bio_readmore'] ) || 'yes' === $options['bio_readmore'];
		$visible = isset( $options['bio_visible_paragraphs'] ) ? max( 1, min( 12, absint( $options['bio_visible_paragraphs'] ) ) ) : 2;
		$more = isset( $options['bio_more_label'] ) && '' !== trim( (string) $options['bio_more_label'] ) ? sanitize_text_field( $options['bio_more_label'] ) : 'ادامهٔ بیوگرافی';
		$less = isset( $options['bio_less_label'] ) && '' !== trim( (string) $options['bio_less_label'] ) ? sanitize_text_field( $options['bio_less_label'] ) : 'بستن بیوگرافی';
		// Long biographies entered as a single paragraph still need a usable read-more control.
		if ( $readmore && 1 === count( $blocks ) ) {
			$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $blocks[0] ) ) );
			$length = class_exists( 'Artiey_AET_Text' ) ? Artiey_AET_Text::length( $plain ) : strlen( $plain );
			if ( $length > 700 ) {
				$preview = wp_trim_words( $plain, 55, '…' );
				$body = '<details class="aetp-bio-more aetp-bio-single-long"><summary><span class="when-closed"><span class="aetp-single-preview">' . esc_html( $preview ) . '</span><span class="aetp-single-continue">' . esc_html( $more ) . '</span></span><span class="when-open">' . esc_html( $less ) . '</span></summary><div class="aetp-bio-text">' . $blocks[0] . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- source markup was cleaned by Profile_Guard.
				return '<section class="aetp-sec aetp-bio"><h3>بیوگرافی کامل</h3>' . $body . '</section>';
			}
		}
		$first = array_slice( $blocks, 0, $visible );
		$rest  = array_slice( $blocks, $visible );
		$body  = '<div class="aetp-bio-text">' . implode( '', $first ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup was cleaned by Profile_Guard.
		if ( $rest ) {
			if ( $readmore ) {
				$body .= '<details class="aetp-bio-more"><summary><span class="when-closed">' . esc_html( $more ) . '</span><span class="when-open">' . esc_html( $less ) . '</span></summary><div class="aetp-bio-text">' . implode( '', $rest ) . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cleaned biography blocks.
			} else {
				$body .= '<div class="aetp-bio-text">' . implode( '', $rest ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cleaned biography blocks.
			}
		}
		return '<section class="aetp-sec aetp-bio"><h3>بیوگرافی کامل</h3>' . $body . '</section>';
	}

	private static function s_details( $a, $options = array() ) {
		$rows = array( 'نام فارسی' => esc_html( $a['name'] ) );
		if ( $a['name_en'] ) {
			$rows['نام لاتین'] = '<span dir="ltr">' . esc_html( $a['name_en'] ) . '</span>';
		}
		if ( $a['birth'] ) {
			$rows['سال تولد'] = esc_html( self::fa( $a['birth'] ) );
		}
		if ( $a['city'] ) {
			$rows['شهر'] = esc_html( $a['city'] );
		}
		if ( $a['nationality'] ) {
			$rows['ملیت / خاستگاه فرهنگی'] = esc_html( $a['nationality'] );
		}
		if ( $a['mediums'] ) {
			$rows['رسانه و تکنیک'] = esc_html( $a['mediums'] );
		}
		if ( empty( $options['elementor_light_preview'] ) && Artiey_AET_Settings::get( 'show_certificates' ) ) {
			$certs = Artiey_AET_COA_Bridge::active_count_for_artist_post( $a['id'] );
			if ( $certs > 0 ) {
				$rows['گواهی‌های اصالت فعال'] = esc_html( self::fa( $certs ) );
			}
		}
		if ( $a['education'] ) {
			$rows['تحصیلات'] = esc_html( $a['education'] );
		}
		$chips = self::style_chips( $a['id'] );
		if ( $chips ) {
			$rows['سبک هنری'] = $chips;
		}
		if ( count( $rows ) < 2 ) {
			return '';
		}
		$h = '<section class="aetp-sec aetp-details"><h3>مشخصات</h3><dl class="aetp-dl">';
		foreach ( $rows as $k => $v ) {
			$h .= '<dt>' . esc_html( $k ) . '</dt><dd>' . $v . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $h . '</dl></section>';
	}

	private static function s_awards( $a ) {
		if ( ! $a['awards'] ) {
			return '';
		}
		$h = '<section class="aetp-sec aetp-awards"><h3>جوایز و افتخارات</h3><ul class="aetp-list">';
		foreach ( $a['awards'] as $line ) {
			$h .= '<li>' . esc_html( $line ) . '</li>';
		}
		return $h . '</ul></section>';
	}


	/** Parse one entry per line; a leading year can be written as "۱۴۰۰ - شرح". */
	private static function timeline_items( $text, $kind ) {
		$items = array();
		$lines = preg_split( '/\R+/u', trim( (string) $text ) );
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$year = '';
			if ( preg_match( '/^([0-9۰-۹٠-٩]{4})\s*(?:[-–—:：|،.]\s*)?(.+)$/u', $line, $m ) ) {
				$year = self::fa( $m[1] );
				$line = trim( $m[2] );
			}
			$items[] = array( 'year' => $year, 'text' => $line, 'kind' => $kind );
		}
		return $items;
	}

	private static function s_career_timeline( $a ) {
		$items = array_merge(
			self::timeline_items( $a['education'], 'تحصیلات' ),
			self::timeline_items( $a['timeline'], 'سوابق حرفه‌ای' )
		);
		if ( ! $items ) {
			return '';
		}
		// Sort dated entries chronologically; entries without a year remain in their original order at the end.
		foreach ( $items as $index => &$item ) {
			$item['_order'] = $index;
			$item['_year_sort'] = '' !== $item['year'] ? absint( Artiey_AET_Jalali::to_latin_digits( $item['year'] ) ) : 0;
		}
		unset( $item );
		usort( $items, static function ( $left, $right ) {
			if ( $left['_year_sort'] && $right['_year_sort'] && $left['_year_sort'] !== $right['_year_sort'] ) {
				return $left['_year_sort'] <=> $right['_year_sort'];
			}
			if ( ! $left['_year_sort'] && $right['_year_sort'] ) { return 1; }
			if ( $left['_year_sort'] && ! $right['_year_sort'] ) { return -1; }
			return $left['_order'] <=> $right['_order'];
		} );
		$h = '<section class="aetp-sec aetp-career-timeline"><h3>خط زمانی تحصیلات و جوایز</h3><ol class="aetp-career-list">';
		foreach ( $items as $item ) {
			$h .= '<li class="aetp-career-item"><span class="aetp-career-kind">' . esc_html( $item['kind'] ) . '</span>';
			if ( '' !== $item['year'] ) {
				$h .= '<time class="aetp-career-year">' . esc_html( $item['year'] ) . '</time>';
			}
			$h .= '<span class="aetp-career-text">' . esc_html( $item['text'] ) . '</span></li>';
		}
		return $h . '</ol></section>';
	}

	/** Curated interview videos and related editorial links, one entry per line: title | URL. */
	private static function s_interviews( $a ) {
		$rows = preg_split( '/\R+/u', (string) ( $a['interviews'] . "\n" . $a['related_media'] ) );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$parts = array_map( 'trim', explode( '|', (string) $row, 2 ) );
			if ( count( $parts ) < 2 || '' === $parts[1] ) { continue; }
			$url = esc_url_raw( $parts[1] );
			if ( ! $url || ! wp_http_validate_url( $url ) ) { continue; }
			$items[] = array( 'title' => $parts[0] ?: 'مصاحبه / مطلب مرتبط', 'url' => $url );
		}
		if ( ! $items ) { return ''; }
		$html = '<section class="aetp-sec aetp-artist-media"><h3>مصاحبه‌ها و مطالب مرتبط</h3><div class="aetp-artist-media-grid">';
		foreach ( $items as $item ) {
			$embed = wp_oembed_get( $item['url'], array( 'width' => 720 ) );
			$html .= '<article class="aetp-artist-media-item"><h4>' . esc_html( $item['title'] ) . '</h4>';
			if ( $embed ) { $html .= '<div class="aetp-video-frame">' . $embed . '</div>'; }
			else { $html .= '<a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener noreferrer">مشاهده مطلب / ویدیو</a>'; }
			$html .= '</article>';
		}
		return $html . '</div></section>';
	}

	private static function s_certificate_stats( $a ) {
		if ( ! class_exists( 'Artiey_AET_COA_Bridge' ) || ! Artiey_AET_COA_Bridge::available() ) { return ''; }
		$count = absint( Artiey_AET_COA_Bridge::active_count_for_artist_post( $a['id'] ) );
		return '<section class="aetp-sec aetp-artist-certificate-stats"><h3>آمار گواهی اصالت آثار</h3><div class="aetp-stats"><div class="aetp-stat"><strong>' . esc_html( self::fa( $count ) ) . '</strong><span>گواهی اصالت فعال</span></div></div></section>';
	}

	/** Resolve a COA artist row by canonical WordPress post ID, never by ambiguous numeric slug. */
	private static function coa_artist_id( $artist_post_id ) {
		$artist_post_id = absint( $artist_post_id );
		if ( $artist_post_id <= 0 ) {
			return 0;
		}
		if ( array_key_exists( $artist_post_id, self::$coa_artist_id_cache ) ) {
			return self::$coa_artist_id_cache[ $artist_post_id ];
		}
		$coa_id = 0;
		if ( class_exists( '\\WPArtCOA\\ContentBridge' ) ) {
			$coa_id = absint( \WPArtCOA\ContentBridge::row_id_for_post( 'artists', $artist_post_id ) );
		} elseif ( class_exists( '\\WPArtCOA\\Database' ) ) {
			global $wpdb;
			$table = \WPArtCOA\Database::table( 'artists' );
			$coa_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE cpt_post_id = %d ORDER BY id ASC LIMIT 1", $artist_post_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name comes from Database::table.
		}
		self::$coa_artist_id_cache[ $artist_post_id ] = $coa_id;
		return $coa_id;
	}

	/** Validate chart-only shortcode input and replace per-artist placeholders safely. */
	private static function prepare_chart_shortcode( $raw, $artist_post_id, $coa_artist_id, $months ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) { return ''; }
		if ( ! preg_match_all( '/\[([a-zA-Z0-9_-]+)\b[^\]]*\]/', $raw, $matches, PREG_SET_ORDER ) ) { return ''; }
		$allowed = array( 'art_coa_charts', 'artiey_charts' );
		foreach ( $matches as $match ) {
			if ( empty( $match[1] ) || ! in_array( $match[1], $allowed, true ) ) { return ''; }
		}
		$remaining = preg_replace( '/\[(?:art_coa_charts|artiey_charts)\b[^\]]*\]/', '', $raw );
		if ( null === $remaining || '' !== trim( $remaining ) ) { return ''; }
		if ( false !== strpos( $raw, '{artist_id}' ) && absint( $coa_artist_id ) <= 0 ) { return ''; }
		$replace = array(
			'{artist_id}'      => (string) absint( $coa_artist_id ),
			'{artist_post_id}' => (string) absint( $artist_post_id ),
			'{months}'         => (string) max( 3, min( 36, absint( $months ) ) ),
		);
		return strtr( $raw, $replace );
	}

	/** Show configuration diagnostics only to editors; never leak setup details to public visitors. */
	private static function chart_source_notice( $message ) {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) ) { return ''; }
		return '<div class="aetp-chart-source-notice" role="status">' . esc_html( (string) $message ) . '</div>';
	}

	/** Render only trusted chart shortcodes; arbitrary shortcode execution is intentionally blocked. */
	private static function render_chart_shortcode( $raw, $artist_post_id, $options = array() ) {
		$months_value = $options['chart_months'] ?? 12;
		$months = is_array( $months_value ) ? absint( $months_value['size'] ?? 12 ) : absint( $months_value );
		$coa_id = self::coa_artist_id( $artist_post_id );
		$prepared = self::prepare_chart_shortcode( $raw, $artist_post_id, $coa_id, $months );
		if ( '' === trim( (string) $raw ) ) { return self::chart_source_notice( 'حالت شورت‌کد فعال است، اما فیلد شورت‌کد نمودار خالی است.' ); }
		if ( '' === $prepared || ! function_exists( 'do_shortcode' ) ) { return self::chart_source_notice( 'شورت‌کد نمودار معتبر نیست. فقط [art_coa_charts] یا [artiey_charts] مجاز است؛ توکن {artist_id} نیز به اتصال COA نیاز دارد.' ); }
		if ( function_exists( 'shortcode_exists' ) ) {
			preg_match_all( '/\[([a-zA-Z0-9_-]+)\b[^\]]*\]/', $prepared, $tags, PREG_SET_ORDER );
			foreach ( $tags as $tag ) {
				if ( empty( $tag[1] ) || ! shortcode_exists( $tag[1] ) ) { return self::chart_source_notice( 'شورت‌کد نمودار در این سایت ثبت نشده است. فعال‌بودن ماژول COA را بررسی کنید.' ); }
			}
		}
		$out = do_shortcode( $prepared );
		$out = is_string( $out ) ? trim( $out ) : '';
		return '' !== $out ? $out : self::chart_source_notice( 'شورت‌کد اجرا شد اما خروجی تولید نکرد؛ شناسه هنرمند، نوع نمودار و محدودیت دسترسی فروش را بررسی کنید.' );
	}

	private static function editor_preview_notice( $title, $message ) {
		return '<div class="aetp-sec aetp-editor-preview-note" role="status"><strong>' . esc_html( $title ) . '</strong><p>' . esc_html( $message ) . '</p></div>';
	}

	private static function s_activity_chart( $a, $options = array() ) {
		if ( ! empty( $options['elementor_light_preview'] ) ) {
			return self::editor_preview_notice( 'پیش‌نمایش نمودار فعالیت', 'برای سرعت ویرایشگر، داده‌های زندهٔ COA پس از ذخیره و در پیش‌نمایش نهایی صفحه بارگذاری می‌شوند.' );
		}
		if ( isset( $options['activity_chart_connection'] ) && 'shortcode' === $options['activity_chart_connection'] ) {
			$chart = self::render_chart_shortcode( $options['activity_chart_shortcode'] ?? '', $a['id'], $options );
			return $chart ? '<section class="aetp-sec aetp-artist-chart aetp-artist-activity-chart aetp-chart-connection-shortcode">' . $chart . '</section>' : '';
		}
		if ( ! class_exists( '\\WPArtCOA\\ChartKit' ) ) {
			return self::chart_source_notice( 'ماژول نمودار COA در دسترس نیست؛ وضعیت فعال‌بودن افزونه را بررسی کنید.' );
		}
		$coa_id = self::coa_artist_id( $a['id'] );
		if ( ! $coa_id ) {
			return self::chart_source_notice( 'این هنرمند هنوز به رکورد هنرمند در COA متصل نشده است. همگام‌سازی هنرمندان را بررسی کنید یا روش اتصال شورت‌کد را انتخاب کنید.' );
		}
		$months_value = $options['chart_months'] ?? 12;
		$months = is_array( $months_value ) ? absint( $months_value['size'] ?? 12 ) : absint( $months_value );
		$height = isset( $options['chart_height']['size'] ) ? absint( $options['chart_height']['size'] ) : 280;
		$chart = \WPArtCOA\ChartKit::render( array(
			'type'       => 'activity',
			'artist'     => (string) $coa_id,
			'months'     => max( 3, min( 36, $months ) ),
			'height'     => max( 180, min( 520, $height ) ),
			'title'      => ! isset( $options['chart_show_title'] ) || 'yes' === $options['chart_show_title'],
			'table'      => ! isset( $options['chart_show_table'] ) || 'yes' === $options['chart_show_table'],
			'legend'     => ! isset( $options['chart_show_legend'] ) || 'yes' === $options['chart_show_legend'],
			'empty_text' => 'هنوز داده‌ای برای فعالیت هنرمند ثبت نشده است.',
			'heading'    => isset( $options['activity_chart_title'] ) && '' !== trim( (string) $options['activity_chart_title'] ) ? sanitize_text_field( $options['activity_chart_title'] ) : 'نمودار فعالیت هنرمند',
		) );
		return $chart ? '<section class="aetp-sec aetp-artist-chart aetp-artist-activity-chart">' . $chart . '</section>' : '';
	}

	private static function s_sales_chart( $a, $options = array() ) {
		if ( ! empty( $options['elementor_light_preview'] ) ) {
			return self::editor_preview_notice( 'پیش‌نمایش نمودار فروش', 'نمودار فروش و بررسی مجوزهای COA در پیش‌نمایش نهایی صفحه اجرا می‌شوند.' );
		}
		if ( isset( $options['sales_chart_connection'] ) && 'shortcode' === $options['sales_chart_connection'] ) {
			$chart = self::render_chart_shortcode( $options['sales_chart_shortcode'] ?? '', $a['id'], $options );
			return $chart ? '<section class="aetp-sec aetp-artist-chart aetp-artist-sales-chart aetp-chart-connection-shortcode">' . $chart . '</section>' : '';
		}
		if ( ! class_exists( '\\WPArtCOA\\ChartKit' ) ) {
			return self::chart_source_notice( 'ماژول نمودار COA در دسترس نیست؛ وضعیت فعال‌بودن افزونه را بررسی کنید.' );
		}
		$coa_id = self::coa_artist_id( $a['id'] );
		if ( ! $coa_id ) {
			return self::chart_source_notice( 'این هنرمند هنوز به رکورد هنرمند در COA متصل نشده است. همگام‌سازی هنرمندان را بررسی کنید یا روش اتصال شورت‌کد را انتخاب کنید.' );
		}
		$months_value = $options['chart_months'] ?? 12;
		$months = is_array( $months_value ) ? absint( $months_value['size'] ?? 12 ) : absint( $months_value );
		$height = isset( $options['chart_height']['size'] ) ? absint( $options['chart_height']['size'] ) : 280;
		// ChartKit itself enforces the site's sales-chart privacy policy.
		$chart = \WPArtCOA\ChartKit::render( array(
			'type'       => 'sales_value',
			'artist'     => (string) $coa_id,
			'months'     => max( 3, min( 36, $months ) ),
			'height'     => max( 180, min( 520, $height ) ),
			'title'      => ! isset( $options['chart_show_title'] ) || 'yes' === $options['chart_show_title'],
			'table'      => ! isset( $options['chart_show_table'] ) || 'yes' === $options['chart_show_table'],
			'legend'     => ! isset( $options['chart_show_legend'] ) || 'yes' === $options['chart_show_legend'],
			'empty_text' => 'هنوز داده‌ای برای فروش قابل نمایش ثبت نشده است.',
			'heading'    => isset( $options['sales_chart_title'] ) && '' !== trim( (string) $options['sales_chart_title'] ) ? sanitize_text_field( $options['sales_chart_title'] ) : 'نمودار فروش هنرمند',
		) );
		return $chart ? '<section class="aetp-sec aetp-artist-chart aetp-artist-sales-chart">' . $chart . '</section>' : '';
	}

	private static function s_artwork_history( $a, $options = array() ) {
		if ( ! empty( $options['elementor_light_preview'] ) ) {
			return self::editor_preview_notice( 'پیش‌نمایش تاریخچه آثار', 'رویدادهای حراج، فروش و مالکیت برای کاهش زمان بارگذاری در پیش‌نمایش زنده اجرا نمی‌شوند.' );
		}
		if ( ! class_exists( '\\WPArtCOA\\ArtworkHistoryTimeline' ) || ! class_exists( '\\WPArtCOA\\Database' ) ) {
			return '';
		}
		$coa_id = self::coa_artist_id( $a['id'] );
		if ( ! $coa_id ) {
			return '';
		}
		wp_enqueue_style( 'wp-art-coa-front' );
		wp_enqueue_style( 'wp-art-coa-dynamic' );
		$limit  = isset( $options['history_limit'] ) ? absint( $options['history_limit'] ) : 12;
		$source = isset( $options['history_source'] ) ? sanitize_key( $options['history_source'] ) : 'all';
		$layout = isset( $options['history_layout'] ) ? sanitize_key( $options['history_layout'] ) : 'line';
		$history = \WPArtCOA\ArtworkHistoryTimeline::render( array(
			'artist_id'  => $coa_id,
			'limit'      => max( 1, min( 50, $limit ) ),
			'source'     => $source,
			'layout'     => $layout,
			'show_image' => ! isset( $options['history_show_image'] ) || 'yes' === $options['history_show_image'],
			'show_price' => ! isset( $options['history_show_price'] ) || 'yes' === $options['history_show_price'],
			'show_owner' => isset( $options['history_show_owner'] ) && 'yes' === $options['history_show_owner'],
		) );
		if ( ! $history ) {
			return '';
		}
		$show_title = ! isset( $options['history_show_title'] ) || 'yes' === $options['history_show_title'];
		$title = isset( $options['history_title'] ) && '' !== trim( (string) $options['history_title'] ) ? sanitize_text_field( $options['history_title'] ) : 'تاریخچهٔ آثار هنرمند';
		return '<section class="aetp-sec aetp-artist-history">' . ( $show_title ? '<h3>' . esc_html( $title ) . '</h3>' : '' ) . $history . '</section>';
	}

	private static function s_contact( $a ) {
		$social = '';
		if ( $a['instagram'] ) {
			$social .= '<a class="aetp-pill aetp-ig" href="' . esc_url( 'https://instagram.com/' . $a['instagram'] ) . '" target="_blank" rel="noopener">اینستاگرام</a>';
		}
		if ( $a['telegram'] ) {
			$social .= '<a class="aetp-pill aetp-tg" href="' . esc_url( 'https://t.me/' . $a['telegram'] ) . '" target="_blank" rel="noopener">تلگرام</a>';
		}
		if ( $a['website'] ) {
			$social .= '<a class="aetp-pill" href="' . esc_url( $a['website'] ) . '" target="_blank" rel="noopener">وب‌سایت</a>';
		}
		if ( $a['email'] ) {
			$social .= '<a class="aetp-pill" href="' . esc_url( 'mailto:' . $a['email'] ) . '">ایمیل</a>';
		}
		if ( '' === $social ) {
			return '';
		}
		return '<section class="aetp-sec aetp-contact"><h3>شبکه‌های ارتباطی</h3><div class="aetp-social">' . $social . '</div></section>';
	}

	private static function s_works( $a, $options = array() ) {
		$limit = isset( $options['works_limit'] ) ? max( 1, min( 48, absint( $options['works_limit'] ) ) ) : 12;
		if ( ! empty( $options['elementor_light_preview'] ) ) {
			$limit = min( 6, $limit );
		}
		$catalogue = self::artworks_of( $a['id'], $limit, empty( $options['elementor_light_preview'] ) );
		$photos    = is_array( $a['works'] ) ? $a['works'] : array();
		if ( ! empty( $options['elementor_light_preview'] ) ) {
			$photos = array_slice( $photos, 0, 8 );
		}
		if ( ! $catalogue && ! $photos ) {
			return '';
		}
		$h = '<section class="aetp-sec aetp-photos"><h3>آثار هنرمند</h3>';
		if ( $catalogue && class_exists( 'Artiey_AET_Artwork' ) ) {
			$h .= '<div class="aetp-artist-catalogue"><h4 class="aetp-sub-h">آثار ثبت‌شده در کاتالوگ</h4><div class="aetp-grid aetp-artist-artworks">';
			foreach ( $catalogue as $work ) {
				$h .= Artiey_AET_Artwork::card( $work );
			}
			$h .= '</div></div>';
		}
		if ( $photos ) {
			$h .= '<div class="aetp-artist-selected-works">';
			if ( $catalogue ) {
				$h .= '<h4 class="aetp-sub-h">تصاویر منتخب</h4>';
			}
			$h .= ! empty( $options['works_carousel'] ) ? '<div class="aetp-photo-grid aetp-photo-carousel">' : '<div class="aetp-photo-grid">';
			foreach ( $photos as $pid ) {
				$img = wp_get_attachment_image( $pid, 'medium_large', false, array( 'loading' => 'lazy', 'decoding' => 'async' ) );
				if ( $img ) {
					$target = isset( $options['works_link_target'] ) && '_self' === $options['works_link_target'] ? '_self' : '_blank';
					$h .= '<figure class="aetp-work-item"><a href="' . esc_url( wp_get_attachment_url( $pid ) ) . '" target="' . esc_attr( $target ) . '"' . ( '_blank' === $target ? ' rel="noopener noreferrer"' : '' ) . '>' . $img . '</a>';
					if ( ! empty( $options['works_show_caption'] ) && 'yes' === $options['works_show_caption'] ) {
						$attachment = get_post( $pid );
						$caption = $attachment instanceof WP_Post ? ( $attachment->post_excerpt ? $attachment->post_excerpt : $attachment->post_title ) : '';
						if ( '' !== trim( (string) $caption ) ) {
							$h .= '<figcaption class="aetp-work-caption">' . esc_html( $caption ) . '</figcaption>';
						}
					}
					$h .= '</figure>';
				}
			}
			$h .= '</div></div>';
		}
		return $h . '</section>';
	}

	private static function s_galleries( $a, $options = array() ) {
		$light_preview = ! empty( $options['elementor_light_preview'] );
		$limit = $light_preview ? 6 : 40;
		$list = self::galleries_of( $a['id'], $limit, ! $light_preview );
		if ( ! $list ) {
			return '';
		}
		$h = '<section class="aetp-sec"><h3>گالری‌های همکار</h3><div class="aetp-grid aap-s-65d673">';
		foreach ( $list as $p ) {
			$h .= Artiey_AET_Gallery::card( $p );
		}
		return $h . '</div></section>';
	}

	/**
	 * Published exhibitions linked directly in WordPress or through the COA
	 * exhibition_artworks bridge. We only render canonical published WP posts;
	 * a shared gallery or matching artist name is never treated as participation.
	 */
	private static function exhibitions_of( $artist_post_id, $include_coa = true ) {
		$artist_post_id = absint( $artist_post_id );
		if ( ! $artist_post_id ) {
			return array( 'now' => array(), 'upcoming' => array(), 'past' => array() );
		}
		$cache_key = $artist_post_id . ':' . ( $include_coa ? 'coa' : 'wp' );
		if ( isset( self::$related_exhibitions_cache[ $cache_key ] ) ) {
			return self::$related_exhibitions_cache[ $cache_key ];
		}

		$posts = array();
		$direct = Artiey_AET_Exhibition::for_artist( $artist_post_id );
		foreach ( array( 'now', 'upcoming', 'past' ) as $state ) {
			foreach ( (array) ( $direct[ $state ] ?? array() ) as $post ) {
				if ( $post instanceof WP_Post && 'publish' === get_post_status( $post ) ) {
					$posts[ $post->ID ] = $post;
				}
			}
		}

		$coa_artist_id = $include_coa ? self::coa_artist_id( $artist_post_id ) : 0;
		if ( $include_coa && $coa_artist_id && class_exists( '\\WPArtCOA\\Database' ) ) {
			global $wpdb;
			$sql = 'SELECT DISTINCT e.cpt_post_id, e.slug '
				. 'FROM ' . \WPArtCOA\Database::table( 'exhibitions' ) . ' e '
				. 'INNER JOIN ' . \WPArtCOA\Database::table( 'exhibition_artworks' ) . ' ea ON ea.exhibition_id = e.id '
				. 'INNER JOIN ' . \WPArtCOA\Database::table( 'artworks' ) . ' a ON a.id = ea.artwork_id '
				. "WHERE a.artist_id = %d AND e.status IN ('published','active') "
				. 'ORDER BY e.start_date DESC, e.id DESC LIMIT 100';
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $coa_artist_id ) );
			$ids = array();
			$id_slugs = array();
			$slugs = array();
			foreach ( (array) $rows as $row ) {
				$post_id = absint( $row->cpt_post_id ?? 0 );
				$slug = sanitize_title( (string) ( $row->slug ?? '' ) );
				if ( $post_id && $slug ) { $ids[] = $post_id; $id_slugs[ $post_id ] = $slug; }
				if ( $slug ) { $slugs[] = $slug; }
			}
			$ids = array_values( array_unique( $ids ) );
			$slugs = array_values( array_unique( $slugs ) );
			$queries = array();
			if ( $ids ) {
				$queries[] = get_posts( array(
					'post_type' => 'artiey_exhibition', 'post_status' => 'publish', 'numberposts' => 100,
					'post__in' => $ids, 'orderby' => 'post__in', 'no_found_rows' => true,
					'update_post_meta_cache' => true, 'update_post_term_cache' => false,
				) );
			}
			if ( $slugs ) {
				$queries[] = get_posts( array(
					'post_type' => 'artiey_exhibition', 'post_status' => 'publish', 'numberposts' => 100,
					'post_name__in' => $slugs, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true,
					'update_post_meta_cache' => true, 'update_post_term_cache' => false,
				) );
			}
			foreach ( $queries as $query_posts ) {
				foreach ( (array) $query_posts as $post ) {
					if ( $post instanceof WP_Post && 'publish' === get_post_status( $post ) ) {
						$expected_slug = $id_slugs[ $post->ID ] ?? '';
						if ( $expected_slug && $expected_slug !== sanitize_title( (string) $post->post_name ) ) { continue; }
						$posts[ $post->ID ] = $post;
					}
				}
			}
		}

		self::$related_exhibitions_cache[ $cache_key ] = Artiey_AET_Exhibition::group( array_values( $posts ) );
		return self::$related_exhibitions_cache[ $cache_key ];
	}

	private static function s_exhibitions( $a, $options = array() ) {
		return Artiey_AET_Exhibition::render_groups(
			self::exhibitions_of( $a['id'], empty( $options['elementor_light_preview'] ) ),
			array( 'show_gallery' => true, 'show_curator' => true, 'heading' => 'نمایشگاه‌ها' )
		);
	}

	/* ---------------------------------------------------------------- */
	/* Public                                                            */
	/* ---------------------------------------------------------------- */

	public static function render_profile( $id, $sections = null, $show_title = true, $options = array() ) {
		$id = (int) $id;
		if ( ! $id || 'artiey_artist' !== get_post_type( $id ) || ! self::can_view( $id ) ) {
			return '';
		}
		$sections = is_array( $sections ) ? array_values( array_intersect( self::ALL_SECTIONS, $sections ) ) : self::ALL_SECTIONS;
		if ( ! $sections ) {
			return '';
		}
		wp_enqueue_style( 'artiey-aet-profile' );

		$a    = self::get( $id );
		$light_preview = ! empty( $options['elementor_light_preview'] );
		$html = ( in_array( 'header', $sections, true ) && ! $light_preview ) ? Artiey_AET_Statistics::profile_strip( 'artiey_artist', $id ) : '';
		foreach ( self::ALL_SECTIONS as $key ) {
			if ( ! in_array( $key, $sections, true ) ) {
				continue;
			}
			switch ( $key ) {
				case 'header':
					$html .= self::s_header( $a, $show_title, $options );
					break;
				case 'short_bio':
					$html .= self::s_short_bio( $a );
					break;
				case 'statement':
					$html .= self::s_statement( $a );
					break;
				case 'bio':
					$html .= self::s_bio( $a, $options );
					break;
				case 'details':
					$html .= self::s_details( $a, $options );
					break;
				case 'awards':
					$html .= self::s_awards( $a );
					break;
				case 'career_timeline':
					$html .= self::s_career_timeline( $a );
					break;
				case 'contact':
					$html .= self::s_contact( $a );
					break;
				case 'works':
					$html .= self::s_works( $a, $options );
					break;
				case 'galleries':
					$html .= self::s_galleries( $a, $options );
					break;
				case 'exhibitions':
					$html .= self::s_exhibitions( $a, $options );
					break;
				case 'activity_chart':
					$html .= self::s_activity_chart( $a, $options );
					break;
				case 'sales_chart':
					$html .= self::s_sales_chart( $a, $options );
					break;
				case 'artwork_history':
					$html .= self::s_artwork_history( $a, $options );
					break;
				case 'interviews':
					$html .= self::s_interviews( $a );
					break;
				case 'certificate_stats':
					$html .= self::s_certificate_stats( $a );
					break;
			}
		}
		return '<div class="aetp" dir="rtl">' . $html . '</div>';
	}

	public static function filter_content( $content ) {
		if ( class_exists( 'Artiey_AET_Elementor_Options' ) && Artiey_AET_Elementor_Options::is_elementor_preview() ) {
			return $content;
		}
		if ( ! is_singular( 'artiey_artist' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! Artiey_AET_Profile_Guard::auto_allowed( get_the_ID() ) ) {
			return $content;
		}
		if ( ! Artiey_AET_Settings::get( 'artist_auto' ) ) {
			return $content;
		}
		$wanted = (array) Artiey_AET_Settings::get( 'artist_sections' );
		$id     = get_the_ID();

		$before = array_values( array_intersect( array( 'header', 'short_bio', 'statement' ), $wanted ) );
		$after  = array_values( array_intersect( array( 'details', 'awards', 'career_timeline', 'contact', 'works', 'galleries', 'exhibitions', 'activity_chart', 'sales_chart', 'artwork_history', 'interviews', 'certificate_stats' ), $wanted ) );

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

	public static function shortcode_profile( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'sections' => '', 'show_title' => 'yes' ), (array) $atts, 'artiey_artist_profile' );
		$id   = absint( $atts['id'] ) ? absint( $atts['id'] ) : (int) get_the_ID();
		$secs = null;
		if ( '' !== trim( (string) $atts['sections'] ) ) {
			$secs = array_intersect( self::ALL_SECTIONS, array_map( 'trim', explode( ',', (string) $atts['sections'] ) ) );
		}
		Artiey_AET_Profile_Guard::mark( $id );
		return self::render_profile( $id, $secs, 'no' !== $atts['show_title'] );
	}

	public static function shortcode_field( $atts ) {
		$atts = shortcode_atts( array( 'field' => '', 'id' => 0, 'link' => 'yes' ), (array) $atts, 'artiey_artist_field' );
		$id   = absint( $atts['id'] ) ? absint( $atts['id'] ) : (int) get_the_ID();
		if ( ! $id || 'artiey_artist' !== get_post_type( $id ) || ! self::can_view( $id ) ) {
			return '';
		}
		$a    = self::get( $id );
		$link = ( 'no' !== $atts['link'] );

		switch ( $atts['field'] ) {
			case 'name':
				return esc_html( $a['name'] );
			case 'name_en':
			case 'city':
			case 'mediums':
			case 'education':
			case 'short_bio':
				return esc_html( $a[ $atts['field'] ] );
			case 'birth':
				return $a['birth'] ? esc_html( self::fa( $a['birth'] ) ) : '';
			case 'age':
				$now = (int) Artiey_AET_Jalali::ymd_to_parts( current_time( 'Y-m-d' ) )[0];
				return $a['birth'] ? esc_html( self::fa( max( 0, $now - $a['birth'] ) ) ) : '';
			case 'instagram':
				if ( ! $a['instagram'] ) {
					return '';
				}
				return $link ? '<a href="' . esc_url( 'https://instagram.com/' . $a['instagram'] ) . '" target="_blank" rel="noopener" dir="ltr">@' . esc_html( $a['instagram'] ) . '</a>' : '@' . esc_html( $a['instagram'] );
			case 'telegram':
				if ( ! $a['telegram'] ) {
					return '';
				}
				return $link ? '<a href="' . esc_url( 'https://t.me/' . $a['telegram'] ) . '" target="_blank" rel="noopener" dir="ltr">@' . esc_html( $a['telegram'] ) . '</a>' : '@' . esc_html( $a['telegram'] );
			case 'website':
				return $a['website'] ? ( $link ? '<a href="' . esc_url( $a['website'] ) . '" target="_blank" rel="noopener" dir="ltr">' . esc_html( $a['website'] ) . '</a>' : esc_html( $a['website'] ) ) : '';
			case 'email':
				return $a['email'] ? ( $link ? '<a href="' . esc_url( 'mailto:' . $a['email'] ) . '" dir="ltr">' . esc_html( $a['email'] ) . '</a>' : esc_html( $a['email'] ) ) : '';
			case 'styles':
				$terms = get_the_terms( $id, 'artiey_style' );
				return ( $terms && ! is_wp_error( $terms ) ) ? esc_html( implode( '، ', wp_list_pluck( $terms, 'name' ) ) ) : '';
		}
		return '';
	}
}
