<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google image sitemap (https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps)
 * for the platform's public post types. WordPress core sitemaps cannot carry <image:image> entries.
 *
 * URL: /art-image-sitemap.xml  (and /art-image-sitemap-2.xml ... when there are more than PER_PAGE posts)
 * It is not registered when a dedicated SEO plugin is active, because those already publish image sitemaps.
 */
final class Artiey_AET_Image_Sitemap {

	const QUERY_VAR = 'artiey_image_sitemap';
	const PER_PAGE  = 500;
	const MAX_IMAGES_PER_URL = 6;
	const TTL       = 6 * HOUR_IN_SECONDS;
	const TYPES     = array( 'artiey_artwork', 'artiey_exhibition', 'artiey_gallery', 'artiey_artist' );

	public static function init() {
		if ( ! self::enabled() ) {
			return;
		}
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'init', array( __CLASS__, 'add_rewrite' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 0 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 20, 2 );
	}

	public static function enabled() {
		$seo_plugin = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
		return (bool) apply_filters( 'artiey_aet_image_sitemap_enabled', ! $seo_plugin );
	}

	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function add_rewrite() {
		add_rewrite_rule( '^art-image-sitemap(?:-([0-9]+))?\.xml$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	public static function url( $page = 1 ) {
		return home_url( $page > 1 ? '/art-image-sitemap-' . (int) $page . '.xml' : '/art-image-sitemap.xml' );
	}

	public static function robots( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		$line = 'Sitemap: ' . self::url();
		return false === strpos( (string) $output, $line ) ? rtrim( (string) $output ) . "\n" . $line . "\n" : $output;
	}

	/* ---------------------------------------------------------------- */

	/** Escape text for XML 1.0 (also drops characters XML forbids). */
	public static function esc( $text ) {
		$text = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $text );
		return htmlspecialchars( (string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * @param array<int,array{loc:string,lastmod?:string,images:array<int,array{loc:string,title?:string,caption?:string}>}> $entries
	 */
	public static function build_xml( array $entries ) {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
		foreach ( $entries as $entry ) {
			if ( empty( $entry['loc'] ) || empty( $entry['images'] ) ) {
				continue;
			}
			$xml .= "\t<url>\n\t\t<loc>" . self::esc( $entry['loc'] ) . "</loc>\n";
			if ( ! empty( $entry['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . self::esc( $entry['lastmod'] ) . "</lastmod>\n";
			}
			foreach ( array_slice( $entry['images'], 0, self::MAX_IMAGES_PER_URL ) as $img ) {
				if ( empty( $img['loc'] ) ) {
					continue;
				}
				$xml .= "\t\t<image:image>\n\t\t\t<image:loc>" . self::esc( $img['loc'] ) . "</image:loc>\n";
				if ( ! empty( $img['title'] ) ) {
					$xml .= "\t\t\t<image:title>" . self::esc( $img['title'] ) . "</image:title>\n";
				}
				if ( ! empty( $img['caption'] ) ) {
					$xml .= "\t\t\t<image:caption>" . self::esc( $img['caption'] ) . "</image:caption>\n";
				}
				$xml .= "\t\t</image:image>\n";
			}
			$xml .= "\t</url>\n";
		}
		return $xml . "</urlset>\n";
	}

	/** Only absolute http(s) image URLs are valid in a sitemap. */
	public static function usable_image( $url ) {
		return is_string( $url ) && preg_match( '#^https?://#i', $url ) ? $url : '';
	}

	/** Collect entries for one page: featured image plus a few attached images, using batched queries. */
	public static function entries( $page ) {
		$query = new WP_Query( array(
			'post_type'              => self::TYPES,
			'post_status'            => 'publish',
			'has_password'           => false,
			'posts_per_page'         => self::PER_PAGE,
			'paged'                  => max( 1, (int) $page ),
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		) );
		if ( function_exists( 'update_post_thumbnail_cache' ) ) {
			update_post_thumbnail_cache( $query );
		}
		$ids = wp_list_pluck( $query->posts, 'ID' );

		// One query for all attached images instead of one per post.
		$attached = array();
		if ( $ids ) {
			global $wpdb;
			$in   = implode( ',', array_map( 'intval', $ids ) );
			$rows = $wpdb->get_results( "SELECT ID, post_parent, post_title, post_excerpt FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' AND post_parent IN ({$in}) ORDER BY menu_order ASC, ID ASC LIMIT 5000" ); // phpcs:ignore WordPress.DB.PreparedSQL
			foreach ( (array) $rows as $row ) {
				$attached[ (int) $row->post_parent ][] = $row;
			}
		}

		$entries = array();
		foreach ( $query->posts as $post ) {
			$images = array();
			$seen   = array();
			$thumb  = get_post_thumbnail_id( $post );
			$alt    = (string) get_the_title( $post );
			if ( $thumb ) {
				$loc = self::usable_image( (string) wp_get_attachment_url( $thumb ) );
				if ( $loc ) {
					$seen[ $loc ] = true;
					$images[]     = array( 'loc' => $loc, 'title' => $alt, 'caption' => (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) );
				}
			}
			foreach ( $attached[ (int) $post->ID ] ?? array() as $att ) {
				$loc = self::usable_image( (string) wp_get_attachment_url( (int) $att->ID ) );
				if ( $loc && ! isset( $seen[ $loc ] ) ) {
					$seen[ $loc ] = true;
					$images[]     = array( 'loc' => $loc, 'title' => $att->post_title ?: $alt, 'caption' => (string) $att->post_excerpt );
				}
			}
			if ( $images ) {
				$entries[] = array(
					'loc'     => (string) get_permalink( $post ),
					'lastmod' => gmdate( 'c', strtotime( (string) $post->post_modified_gmt . ' UTC' ) ),
					'images'  => $images,
				);
			}
		}
		return array( 'entries' => $entries, 'max_pages' => (int) $query->max_num_pages );
	}

	public static function maybe_serve() {
		$var = get_query_var( self::QUERY_VAR, null );
		if ( null === $var || false === $var || ! isset( $GLOBALS['wp_query']->query_vars[ self::QUERY_VAR ] ) ) {
			return;
		}
		$page = max( 1, (int) $var );
		$key  = 'aet_imgmap_' . $page . '_' . md5( (string) get_option( 'artiey_directory_cache_version', 1 ) . '|' . get_option( 'artiey_aet_url_cache_generation', 1 ) );
		$xml  = get_transient( $key );
		if ( ! is_string( $xml ) ) {
			$data = self::entries( $page );
			if ( $page > 1 && ! $data['entries'] ) {
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				return;
			}
			$xml = self::build_xml( $data['entries'] );
			set_transient( $key, $xml, self::TTL );
		}
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		header( 'Cache-Control: public, max-age=3600' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in build_xml()
		exit;
	}
}
