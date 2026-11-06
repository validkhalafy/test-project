<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Smart gallery scroller: one component that can show any site content as a professional scroller and
 * that visitors (or the page author) can flip between a flat scroller and two 3D modes.
 *
 * Slide sources
 *  - context : decides from the current page (gallery -> its artworks, artist -> their artworks,
 *              artwork -> more by the same artist, exhibition -> its gallery/artists' artworks, other -> latest works)
 *  - posts   : any public post type, optional taxonomy terms, explicit IDs, order, count
 *  - related : posts sharing an art style (or any chosen taxonomy term) with the current page
 *  - shortcodes : each shortcode's output becomes one slide (Elementor editors only)
 *
 * Only published, non-password-protected posts are ever listed.
 */
final class Artiey_AET_Smart_Scroller {

	const MODES    = array( 'flat', 'coverflow', 'cylinder' );
	const SOURCES  = array( 'context', 'posts', 'related', 'shortcodes' );
	const ORDERS   = array( 'date', 'modified', 'title', 'menu_order', 'rand', 'post__in' );
	const RATIOS   = array( '1/1', '4/3', '3/2', '16/9', '3/4', '2/3' );
	const MAX_SLIDES = 40;
	const CPT      = array( 'artiey_gallery', 'artiey_artist', 'artiey_exhibition', 'artiey_artwork' );

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_shortcode( 'artiey_smart_scroller', array( __CLASS__, 'shortcode' ) );
	}

	public static function register_assets() {
		wp_register_style( 'artiey-aet-smart-scroller', AET_URL . 'assets/css/smart-scroller.css', array(), AET_VERSION );
		wp_register_script( 'artiey-aet-smart-scroller', AET_URL . 'assets/js/smart-scroller.js', array(), AET_VERSION, true );
	}

	/* ---------------------------------------------------------------- */
	/* Option helpers                                                    */
	/* ---------------------------------------------------------------- */

	/** @return string[] public, queryable post types the editor may choose from. */
	public static function allowed_post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	/** Keep only values from an allow-list. */
	public static function pick( $value, array $allowed, $default ) {
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/** @return string[] slugs/ids cleaned from comma, space or Persian-comma separated text. */
	public static function split_list( $text, $max = 20 ) {
		$parts = preg_split( '/[,،;؛\s]+/u', (string) $text ) ?: array();
		$parts = array_values( array_unique( array_filter( array_map( 'trim', $parts ), 'strlen' ) ) );
		return array_slice( $parts, 0, (int) $max );
	}

	/* ---------------------------------------------------------------- */
	/* Context                                                           */
	/* ---------------------------------------------------------------- */

	/** Serialized-array meta (artist ID lists) matched by value, covering integer and string storage. */
	private static function list_meta_clause( $key, $id ) {
		$id = (int) $id;
		return array(
			'relation' => 'OR',
			array( 'key' => $key, 'value' => 'i:' . $id . ';', 'compare' => 'LIKE' ),
			array( 'key' => $key, 'value' => '"' . $id . '"', 'compare' => 'LIKE' ),
		);
	}

	/**
	 * What to list for the page being viewed.
	 *
	 * @param string $target auto | artworks | exhibitions | artists
	 * @return array{post_type:string[],meta_query:array,post__in?:int[],exclude:int[],resolved:string}
	 */
	public static function context_args( $post_id, $post_type, $target = 'auto' ) {
		$post_id = (int) $post_id;
		$base    = array( 'post_type' => array( 'artiey_artwork' ), 'meta_query' => array(), 'exclude' => array(), 'resolved' => 'latest_artworks' );
		if ( $post_id <= 0 ) {
			return $base;
		}
		$target = self::pick( $target, array( 'auto', 'artworks', 'exhibitions', 'artists' ), 'auto' );

		if ( 'artiey_gallery' === $post_type ) {
			if ( 'exhibitions' === $target ) {
				return array( 'post_type' => array( 'artiey_exhibition' ), 'meta_query' => array( array( 'key' => '_ae_gallery', 'value' => $post_id, 'compare' => '=' ) ), 'exclude' => array(), 'resolved' => 'gallery_exhibitions' );
			}
			if ( 'artists' === $target ) {
				$ids = self::int_list( get_post_meta( $post_id, '_ag_artists', true ) );
				return array( 'post_type' => array( 'artiey_artist' ), 'meta_query' => array(), 'post__in' => $ids ?: array( 0 ), 'exclude' => array(), 'resolved' => 'gallery_artists' );
			}
			return array( 'post_type' => array( 'artiey_artwork' ), 'meta_query' => array( array( 'key' => '_aw_gallery', 'value' => $post_id, 'compare' => '=' ) ), 'exclude' => array(), 'resolved' => 'gallery_artworks' );
		}

		if ( 'artiey_artist' === $post_type ) {
			if ( 'exhibitions' === $target ) {
				return array( 'post_type' => array( 'artiey_exhibition' ), 'meta_query' => array( self::list_meta_clause( '_ae_artists', $post_id ) ), 'exclude' => array(), 'resolved' => 'artist_exhibitions' );
			}
			return array( 'post_type' => array( 'artiey_artwork' ), 'meta_query' => array( array( 'key' => '_aw_artist', 'value' => $post_id, 'compare' => '=' ) ), 'exclude' => array(), 'resolved' => 'artist_artworks' );
		}

		if ( 'artiey_artwork' === $post_type ) {
			$artist = (int) get_post_meta( $post_id, '_aw_artist', true );
			if ( $artist ) {
				return array( 'post_type' => array( 'artiey_artwork' ), 'meta_query' => array( array( 'key' => '_aw_artist', 'value' => $artist, 'compare' => '=' ) ), 'exclude' => array( $post_id ), 'resolved' => 'more_by_artist' );
			}
			return array( 'post_type' => array( 'artiey_artwork' ), 'meta_query' => array(), 'exclude' => array( $post_id ), 'resolved' => 'latest_artworks' );
		}

		if ( 'artiey_exhibition' === $post_type ) {
			$gallery = (int) get_post_meta( $post_id, '_ae_gallery', true );
			$artists = self::int_list( get_post_meta( $post_id, '_ae_artists', true ) );
			$clauses = array( 'relation' => 'OR' );
			if ( $gallery ) {
				$clauses[] = array( 'key' => '_aw_gallery', 'value' => $gallery, 'compare' => '=' );
			}
			if ( $artists ) {
				$clauses[] = array( 'key' => '_aw_artist', 'value' => $artists, 'compare' => 'IN' );
			}
			return array( 'post_type' => array( 'artiey_artwork' ), 'meta_query' => count( $clauses ) > 1 ? array( $clauses ) : array(), 'exclude' => array(), 'resolved' => 'exhibition_artworks' );
		}
		return $base;
	}

	/** @return int[] positive ints from an array, a serialized array or a separated string. */
	public static function int_list( $raw ) {
		$value = $raw;
		if ( is_string( $raw ) && function_exists( 'maybe_unserialize' ) ) {
			$value = maybe_unserialize( $raw );
		}
		if ( ! is_array( $value ) ) {
			$value = is_scalar( $value ) ? preg_split( '/[,;\s]+/', (string) $value ) : array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	/* ---------------------------------------------------------------- */
	/* Query                                                             */
	/* ---------------------------------------------------------------- */

	/**
	 * WP_Query arguments for a settings array. Never returns private, draft or password-protected posts.
	 *
	 * @param array<string,mixed> $s
	 * @return array<string,mixed>
	 */
	public static function query_args( array $s, $current_id = 0, $current_type = '' ) {
		$source = self::pick( $s['source'] ?? 'context', self::SOURCES, 'context' );
		$limit  = max( 1, min( self::MAX_SLIDES, (int) ( $s['limit'] ?? 12 ) ) );
		$order  = self::pick( $s['orderby'] ?? 'date', self::ORDERS, 'date' );
		$args   = array(
			'post_status'            => 'publish',
			'has_password'           => false,
			'posts_per_page'         => $limit,
			'offset'                 => max( 0, min( 200, (int) ( $s['offset'] ?? 0 ) ) ),
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
			'orderby'                => $order,
			'order'                  => 'asc' === strtolower( (string) ( $s['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC',
		);

		$allowed = self::allowed_post_types();
		$types   = array();
		foreach ( (array) ( $s['post_types'] ?? array() ) as $t ) {
			if ( in_array( $t, $allowed, true ) ) {
				$types[] = $t;
			}
		}
		$meta = array();
		$exclude = array();

		if ( 'context' === $source ) {
			$ctx   = self::context_args( $current_id, $current_type, $s['context_target'] ?? 'auto' );
			$types = $ctx['post_type'];
			$meta  = $ctx['meta_query'];
			$exclude = $ctx['exclude'];
			if ( isset( $ctx['post__in'] ) ) {
				$args['post__in'] = $ctx['post__in'];
			}
		} elseif ( 'related' === $source && $current_id ) {
			$taxonomy = sanitize_key( (string) ( $s['related_taxonomy'] ?? 'artiey_style' ) ) ?: 'artiey_style';
			$term_ids = taxonomy_exists( $taxonomy ) ? wp_get_post_terms( (int) $current_id, $taxonomy, array( 'fields' => 'ids' ) ) : array();
			$args['tax_query'] = array( array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => is_array( $term_ids ) && $term_ids ? array_map( 'intval', $term_ids ) : array( 0 ) ) );
			$exclude[] = (int) $current_id;
		}

		$args['post_type'] = $types ?: ( 'posts' === $source ? array( 'post' ) : array( 'artiey_artwork' ) );

		// Explicit IDs.
		$ids = array_values( array_filter( array_map( 'absint', self::split_list( $s['include_ids'] ?? '' ) ) ) );
		if ( 'posts' === $source && $ids ) {
			$args['post__in'] = $ids;
			if ( 'post__in' === $order ) {
				$args['orderby'] = 'post__in';
			}
		}

		// Taxonomy filter (posts source).
		if ( 'posts' === $source && ! empty( $s['taxonomy'] ) ) {
			$tax   = sanitize_key( (string) $s['taxonomy'] );
			$terms = array_map( 'sanitize_title', self::split_list( $s['terms'] ?? '' ) );
			if ( $tax && $terms && taxonomy_exists( $tax ) ) {
				$args['tax_query'] = array( array( 'taxonomy' => $tax, 'field' => 'slug', 'terms' => $terms ) );
			}
		}

		if ( ! empty( $s['exclude_current'] ) && $current_id ) {
			$exclude[] = (int) $current_id;
		}
		if ( $exclude ) {
			$args['post__not_in'] = array_values( array_unique( array_map( 'absint', $exclude ) ) );
		}
		if ( ! empty( $s['only_with_image'] ) ) {
			$meta[] = array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' );
		}
		if ( $meta ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta );
		}
		return $args;
	}

	/* ---------------------------------------------------------------- */
	/* Slides                                                            */
	/* ---------------------------------------------------------------- */

	public static function badge_for( $post_type ) {
		$map = array(
			'artiey_gallery' => 'گالری', 'artiey_artist' => 'هنرمند', 'artiey_exhibition' => 'نمایشگاه', 'artiey_artwork' => 'اثر',
			'post' => 'نوشته', 'page' => 'برگه',
		);
		if ( isset( $map[ $post_type ] ) ) {
			return $map[ $post_type ];
		}
		$obj = get_post_type_object( $post_type );
		return $obj && isset( $obj->labels->singular_name ) ? (string) $obj->labels->singular_name : '';
	}

	/** One short descriptive line that depends on the kind of content. */
	public static function meta_line( $post ) {
		$id   = (int) $post->ID;
		$type = $post->post_type;
		if ( 'artiey_artwork' === $type ) {
			$artist = (int) get_post_meta( $id, '_aw_artist', true );
			$name   = $artist && 'publish' === get_post_status( $artist ) ? (string) get_the_title( $artist ) : '';
			$year   = (string) get_post_meta( $id, '_aw_year', true );
			return trim( $name . ( $name && $year ? ' · ' : '' ) . $year );
		}
		if ( 'artiey_exhibition' === $type ) {
			$a = (string) get_post_meta( $id, '_ae_start', true );
			$b = (string) get_post_meta( $id, '_ae_end', true );
			return $a && $b ? $a . ' — ' . $b : ( $a ?: $b );
		}
		if ( 'artiey_artist' === $type ) {
			return (string) get_post_meta( $id, '_aa_city', true );
		}
		if ( 'artiey_gallery' === $type ) {
			return (string) get_post_meta( $id, '_ag_city', true );
		}
		return '';
	}

	/** @return array<string,mixed>|null slide for a post, null if it should not be listed. */
	public static function slide_from_post( $post, $words = 22 ) {
		if ( ! is_object( $post ) || 'publish' !== $post->post_status || ! empty( $post->post_password ) ) {
			return null;
		}
		$id    = (int) $post->ID;
		$thumb = get_post_thumbnail_id( $id );
		$image = null;
		if ( $thumb ) {
			$row = Artiey_AET_Media_Gallery::attachment_row( $thumb, 'large' );
			if ( $row ) {
				$image = $row;
				$image['alt'] = $row['alt'] ?: (string) get_the_title( $id );
			}
		}
		if ( ! $image && 'artiey_artwork' === $post->post_type ) {
			$ext = Artiey_AET_Media_Gallery::safe_url( (string) get_post_meta( $id, '_aw_image_url', true ) );
			if ( $ext ) {
				$image = array( 'src' => $ext, 'srcset' => '', 'width' => 0, 'height' => 0, 'alt' => (string) get_the_title( $id ) );
			}
		}
		$short = (string) get_post_meta( $id, 'artiey_artist' === $post->post_type ? '_aa_short_bio' : ( 'artiey_gallery' === $post->post_type ? '_ag_short_bio' : '_none_' ), true );
		$text  = '' !== trim( $short ) ? $short : ( '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : $post->post_content );
		return array(
			'type'    => 'post',
			'id'      => $id,
			'title'   => (string) get_the_title( $id ),
			'url'     => (string) get_permalink( $id ),
			'badge'   => self::badge_for( $post->post_type ),
			'meta'    => self::meta_line( $post ),
			'excerpt' => wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $text ) ), (int) $words, '…' ),
			'image'   => $image,
			'html'    => '',
		);
	}

	/** Each non-empty shortcode becomes one HTML slide. Only called for trusted editor input. */
	public static function shortcode_slides( array $list, $max = 12 ) {
		$out = array();
		foreach ( $list as $raw ) {
			if ( count( $out ) >= (int) $max ) {
				break;
			}
			$code = trim( (string) $raw );
			if ( '' === $code || strlen( $code ) > 2000 || false === strpos( $code, '[' ) ) {
				continue;
			}
			$html = (string) do_shortcode( $code );
			if ( '' === trim( wp_strip_all_tags( $html ) ) && false === stripos( $html, '<img' ) && false === stripos( $html, '<iframe' ) ) {
				continue; // empty output: nothing to show
			}
			$out[] = array( 'type' => 'html', 'id' => 0, 'title' => '', 'url' => '', 'badge' => '', 'meta' => '', 'excerpt' => '', 'image' => null, 'html' => $html );
		}
		return $out;
	}

	/**
	 * Build the slides for a settings array.
	 *
	 * @param array<string,mixed> $s settings (see query_args) plus shortcodes[] and shortcode_position
	 * @return array<int,array<string,mixed>>
	 */
	public static function build_slides( array $s, $current_id = 0, $current_type = '', $allow_shortcodes = false ) {
		$source = self::pick( $s['source'] ?? 'context', self::SOURCES, 'context' );
		$slides = array();

		if ( 'shortcodes' !== $source ) {
			$query = new WP_Query( self::query_args( $s, $current_id, $current_type ) );
			if ( function_exists( 'update_post_thumbnail_cache' ) ) {
				update_post_thumbnail_cache( $query );
			}
			foreach ( $query->posts as $post ) {
				$slide = self::slide_from_post( $post, (int) ( $s['excerpt_words'] ?? 22 ) );
				if ( $slide ) {
					$slides[] = $slide;
				}
			}
		}
		if ( $allow_shortcodes && ( 'shortcodes' === $source || ! empty( $s['merge_shortcodes'] ) ) ) {
			$extra = self::shortcode_slides( (array) ( $s['shortcodes'] ?? array() ), 12 );
			$slides = 'start' === ( $s['shortcode_position'] ?? 'end' ) ? array_merge( $extra, $slides ) : array_merge( $slides, $extra );
		}
		$slides = (array) apply_filters( 'artiey_aet_smart_scroller_slides', $slides, $s );
		return array_slice( array_values( $slides ), 0, self::MAX_SLIDES );
	}

	/* ---------------------------------------------------------------- */
	/* Markup                                                            */
	/* ---------------------------------------------------------------- */

	/** @return array<string,mixed> */
	public static function options( array $o ) {
		$mode = self::pick( $o['mode'] ?? 'coverflow', self::MODES, 'coverflow' );
		return array(
			'mode'        => $mode,
			'toggle'      => ! empty( $o['toggle'] ),
			'ratio'       => self::pick( $o['ratio'] ?? '4/3', self::RATIOS, '4/3' ),
			'badge'       => ! array_key_exists( 'badge', $o ) || ! empty( $o['badge'] ),
			'title'       => ! array_key_exists( 'title', $o ) || ! empty( $o['title'] ),
			'meta'        => ! array_key_exists( 'meta', $o ) || ! empty( $o['meta'] ),
			'excerpt'     => ! empty( $o['excerpt'] ),
			'cta'         => ! empty( $o['cta'] ),
			'cta_text'    => '' !== trim( (string) ( $o['cta_text'] ?? '' ) ) ? mb_substr( trim( (string) $o['cta_text'] ), 0, 40 ) : 'مشاهده',
			'arrows'      => ! array_key_exists( 'arrows', $o ) || ! empty( $o['arrows'] ),
			'dots'        => ! empty( $o['dots'] ),
			'autoplay'    => ! empty( $o['autoplay'] ) ? max( 2000, min( 20000, (int) ( $o['interval'] ?? 5000 ) ) ) : 0,
			'loop'        => ! array_key_exists( 'loop', $o ) || ! empty( $o['loop'] ),
			'drag'        => ! array_key_exists( 'drag', $o ) || ! empty( $o['drag'] ),
			'wheel'       => ! empty( $o['wheel'] ),
			'mobile3d'    => ! empty( $o['mobile3d'] ),
			'depth'       => max( 0, min( 600, (int) ( $o['depth'] ?? 260 ) ) ),
			'spread'      => max( 20, min( 100, (int) ( $o['spread'] ?? 55 ) ) ),
			'angle'       => max( 0, min( 75, (int) ( $o['angle'] ?? 42 ) ) ),
			'perspective' => max( 400, min( 3000, (int) ( $o['perspective'] ?? 1200 ) ) ),
			'start'       => max( 0, (int) ( $o['start'] ?? 0 ) ),
			'label'       => '' !== trim( (string) ( $o['label'] ?? '' ) ) ? mb_substr( trim( (string) $o['label'] ), 0, 120 ) : 'گالری',
			'eager'       => max( 0, min( 4, (int) ( $o['eager'] ?? 1 ) ) ),
			'name_tag'    => self::pick( $o['name_tag'] ?? 'h3', array( 'h2', 'h3', 'h4', 'div' ), 'h3' ),
		);
	}

	/** @param array<int,array<string,mixed>> $slides @param array<string,mixed> $opts */
	public static function render( array $slides, array $opts ) {
		if ( ! $slides ) {
			return '';
		}
		$o     = self::options( $opts );
		$n     = count( $slides );
		$three = $n >= 3; // 3D needs at least three slides to read as 3D
		$mode  = ( ! $three && 'flat' !== $o['mode'] ) ? 'flat' : $o['mode'];
		$cfg   = array(
			'mode' => $mode, 'toggle' => $o['toggle'] && $three, 'autoplay' => $o['autoplay'], 'loop' => $o['loop'], 'drag' => $o['drag'],
			'wheel' => $o['wheel'], 'mobile3d' => $o['mobile3d'], 'depth' => $o['depth'], 'spread' => $o['spread'] / 100,
			'angle' => $o['angle'], 'start' => min( $o['start'], $n - 1 ),
		);
		$style = '--aetss-ratio:' . $o['ratio'] . ';--aetss-persp:' . (int) $o['perspective'] . 'px;';

		$h  = '<section class="aetss aetss--' . esc_attr( $mode ) . '" style="' . esc_attr( $style ) . '" data-aetss="' . esc_attr( wp_json_encode( $cfg ) ) . '"'
			. ' role="region" aria-roledescription="carousel" aria-label="' . esc_attr( $o['label'] ) . '">';
		if ( $cfg['toggle'] ) {
			$h .= '<div class="aetss-toolbar"><button type="button" class="aetss-mode" aria-pressed="' . ( 'flat' === $mode ? 'false' : 'true' ) . '">'
				. '<span class="aetss-mode-3d">۳ بعدی</span><span class="aetss-mode-2d">۲ بعدی</span></button></div>';
		}
		$h .= '<div class="aetss-stage" tabindex="0"><ul class="aetss-track">';
		foreach ( $slides as $i => $s ) {
			$label = sprintf( 'اسلاید %d از %d', $i + 1, $n );
			$h    .= '<li class="aetss-slide' . ( 'html' === $s['type'] ? ' aetss-slide--html' : '' ) . '" role="group" aria-roledescription="slide" aria-label="' . esc_attr( $label ) . '">';
			$h    .= 'html' === $s['type'] ? '<div class="aetss-card aetss-card--html">' . $s['html'] . '</div>' : self::card( $s, $o, $i ); // phpcs:ignore WordPress.Security.EscapeOutput -- shortcode output is trusted editor content
			$h    .= '</li>';
		}
		$h .= '</ul></div>';
		if ( $o['arrows'] && $n > 1 ) {
			$h .= '<button type="button" class="aetss-nav aetss-prev" aria-label="قبلی"><svg viewBox="0 0 24 24" width="22" height="22" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
				. '<button type="button" class="aetss-nav aetss-next" aria-label="بعدی"><svg viewBox="0 0 24 24" width="22" height="22" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
		}
		if ( $o['dots'] && $n > 1 ) {
			$h .= '<div class="aetss-dots" role="tablist" aria-label="اسلایدها"></div>';
		}
		return $h . '<p class="aetss-live screen-reader-text" aria-live="polite" aria-atomic="true"></p></section>';
	}

	private static function card( array $s, array $o, $index ) {
		$img  = $s['image'];
		$url  = $s['url'];
		$tag  = $o['name_tag'];
		$h    = '<article class="aetss-card">';
		$media = '';
		if ( $img && ! empty( $img['src'] ) ) {
			$dims  = ( ! empty( $img['width'] ) && ! empty( $img['height'] ) ) ? ' width="' . (int) $img['width'] . '" height="' . (int) $img['height'] . '"' : '';
			$srcset = ! empty( $img['srcset'] ) ? ' srcset="' . esc_attr( Artiey_AET_Media_Gallery::clean_srcset( $img['srcset'] ) ) . '" sizes="(max-width:600px) 80vw, 420px"' : '';
			$media = '<img src="' . esc_url( $img['src'] ) . '"' . $srcset . $dims . ' alt="' . esc_attr( $img['alt'] ?? '' ) . '" loading="' . ( $index < $o['eager'] ? 'eager' : 'lazy' ) . '" decoding="async" draggable="false">';
		} else {
			$media = '<span class="aetss-noimg" aria-hidden="true"></span>';
		}
		$h .= $url
			? '<a class="aetss-media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $media . '</a>'
			: '<div class="aetss-media">' . $media . '</div>';
		if ( $o['badge'] && '' !== $s['badge'] ) {
			$h .= '<span class="aetss-badge">' . esc_html( $s['badge'] ) . '</span>';
		}
		$h .= '<div class="aetss-body">';
		if ( $o['title'] && '' !== $s['title'] ) {
			$h .= '<' . $tag . ' class="aetss-title">' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $s['title'] ) . '</a>' : esc_html( $s['title'] ) ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- tag is allow-listed
		}
		if ( $o['meta'] && '' !== $s['meta'] ) {
			$h .= '<p class="aetss-meta">' . esc_html( $s['meta'] ) . '</p>';
		}
		if ( $o['excerpt'] && '' !== $s['excerpt'] ) {
			$h .= '<p class="aetss-excerpt">' . esc_html( $s['excerpt'] ) . '</p>';
		}
		if ( $o['cta'] && $url ) {
			$h .= '<a class="aetss-cta" href="' . esc_url( $url ) . '">' . esc_html( $o['cta_text'] ) . '<span class="screen-reader-text"> ' . esc_html( $s['title'] ) . '</span></a>';
		}
		return $h . '</div></article>';
	}

	/* ---------------------------------------------------------------- */
	/* Shortcode (no nested shortcodes: those are Elementor-editor only)  */
	/* ---------------------------------------------------------------- */

	/** [artiey_smart_scroller source="context|posts|related" post_type="artiey_artwork" mode="coverflow" limit="12" toggle="1" ...] */
	public static function shortcode( $atts ) {
		$a = shortcode_atts( array(
			'source' => 'context', 'post_type' => '', 'limit' => '12', 'order' => 'desc', 'orderby' => 'date', 'ids' => '', 'taxonomy' => '', 'terms' => '',
			'target' => 'auto', 'mode' => 'coverflow', 'toggle' => '1', 'ratio' => '4/3', 'autoplay' => '0', 'excerpt' => '0', 'cta' => '0', 'dots' => '0',
			'only_image' => '1', 'exclude_current' => '1', 'label' => '',
		), (array) $atts, 'artiey_smart_scroller' );
		$on = static function ( $v ) {
			return ! in_array( strtolower( (string) $v ), array( '', '0', 'no', 'false', 'off' ), true );
		};
		$s = array(
			'source' => 'shortcodes' === $a['source'] ? 'context' : $a['source'], 'post_types' => self::split_list( $a['post_type'] ), 'limit' => $a['limit'], 'order' => $a['order'],
			'orderby' => $a['orderby'], 'include_ids' => $a['ids'], 'taxonomy' => $a['taxonomy'], 'terms' => $a['terms'], 'context_target' => $a['target'],
			'only_with_image' => $on( $a['only_image'] ), 'exclude_current' => $on( $a['exclude_current'] ),
		);
		$slides = self::build_slides( $s, (int) get_the_ID(), (string) get_post_type(), false );
		if ( ! $slides ) {
			return '';
		}
		wp_enqueue_style( 'artiey-aet-smart-scroller' );
		wp_enqueue_script( 'artiey-aet-smart-scroller' );
		return self::render( $slides, array(
			'mode' => $a['mode'], 'toggle' => $on( $a['toggle'] ), 'ratio' => $a['ratio'], 'autoplay' => $on( $a['autoplay'] ), 'excerpt' => $on( $a['excerpt'] ),
			'cta' => $on( $a['cta'] ), 'dots' => $on( $a['dots'] ), 'label' => $a['label'],
		) );
	}
}
