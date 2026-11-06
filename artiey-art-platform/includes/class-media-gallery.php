<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multi-purpose image + video gallery (scroller, grid, masonry, mosaic) with an accessible lightbox.
 *
 * Everything that touches untrusted input lives here so it can be unit-tested without Elementor:
 *  - video URLs are accepted only for an allow-list of providers (YouTube, Vimeo, Aparat) or direct media files;
 *  - embeds use privacy-friendly hosts and are created in the browser only when the visitor opens an item;
 *  - the lightbox data is emitted as JSON with JSON_HEX_* flags, so it can never break out of its <script> tag;
 *  - schema.org ImageGallery / VideoObject markup is generated from the same sanitised items.
 */
final class Artiey_AET_Media_Gallery {

	const MAX_ITEMS  = 60;
	const LAYOUTS    = array( 'scroller', 'grid', 'masonry', 'mosaic' );
	const RATIOS     = array( 'auto', '1/1', '4/3', '3/2', '16/9', '3/4', '2/3' );
	const CAPTIONS   = array( 'none', 'below', 'overlay' );
	const FILE_EXTS  = array( 'mp4', 'webm', 'ogv', 'ogg', 'm4v' );

	/* ---------------------------------------------------------------- */
	/* Assets + shortcode                                                */
	/* ---------------------------------------------------------------- */

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_shortcode( 'artiey_media_gallery', array( __CLASS__, 'shortcode' ) );
	}

	/** Registered only; they are enqueued by the widget or shortcode that actually renders a gallery. */
	public static function register_assets() {
		wp_register_style( 'artiey-aet-media-gallery', AET_URL . 'assets/css/media-gallery.css', array(), AET_VERSION );
		wp_register_script( 'artiey-aet-media-gallery', AET_URL . 'assets/js/media-gallery.js', array(), AET_VERSION, true );
	}

	/**
	 * [artiey_media_gallery post="123" layout="grid" columns="4" ratio="1/1" caption="overlay" videos="https://youtu.be/…|https://vimeo.com/…"]
	 * Without post= the current post's images are used.
	 */
	public static function shortcode( $atts ) {
		$a = shortcode_atts( array(
			'post' => '', 'layout' => 'scroller', 'columns' => '', 'ratio' => '4/3', 'caption' => 'below', 'lightbox' => '1',
			'arrows' => '1', 'dots' => '0', 'autoplay' => '0', 'limit' => '24', 'videos' => '', 'label' => '', 'schema' => '1',
		), (array) $atts, 'artiey_media_gallery' );

		$limit   = max( 1, min( self::MAX_ITEMS, (int) $a['limit'] ) );
		$post_id = '' === $a['post'] ? (int) get_the_ID() : absint( $a['post'] );
		$rows    = self::collect_post_images( $post_id, $limit, true );
		foreach ( preg_split( '/[|\r\n]+/', (string) $a['videos'] ) as $url ) {
			if ( '' !== trim( $url ) ) {
				$rows[] = array( 'type' => 'video', 'video_url' => trim( $url ) );
			}
		}
		$items = self::normalize_items( $rows, $limit );
		if ( ! $items ) {
			return '';
		}
		wp_enqueue_style( 'artiey-aet-media-gallery' );
		wp_enqueue_script( 'artiey-aet-media-gallery' );

		$flag = static function ( $v ) {
			return ! in_array( strtolower( (string) $v ), array( '', '0', 'no', 'false', 'off' ), true );
		};
		$html = self::render( $items, array(
			'layout'   => $a['layout'], 'ratio' => $a['ratio'], 'caption' => $a['caption'], 'label' => $a['label'],
			'lightbox' => $flag( $a['lightbox'] ), 'arrows' => $flag( $a['arrows'] ), 'dots' => $flag( $a['dots'] ),
			'autoplay' => $flag( $a['autoplay'] ), 'loop' => true, 'id' => 'sc',
		) );
		if ( preg_match( '/^[1-6]$/', (string) $a['columns'] ) ) {
			$html = '<div style="--aetmg-cols:' . (int) $a['columns'] . '">' . $html . '</div>';
		}
		if ( $flag( $a['schema'] ) ) {
			$html .= self::schema_script( self::schema( $items, array(
				'name' => (string) get_the_title( $post_id ), 'url' => (string) get_permalink( $post_id ),
				'date' => $post_id ? (string) get_post_modified_time( 'c', true, $post_id ) : '',
			) ) );
		}
		return $html;
	}

	/* ---------------------------------------------------------------- */
	/* Video URLs                                                        */
	/* ---------------------------------------------------------------- */

	private static function url_parts( $url ) {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return is_array( $parts ) ? $parts : array();
	}

	/**
	 * @return array{provider:string,id:string,embed:string,watch:string,thumb:string,src:string,mime:string}|null
	 */
	public static function parse_video( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || strlen( $url ) > 2000 || ! preg_match( '#^https?://#i', $url ) ) {
			return null;
		}
		$p    = self::url_parts( $url );
		$host = strtolower( (string) ( $p['host'] ?? '' ) );
		$host = preg_replace( '/^(www|m)\./', '', $host );
		$path = (string) ( $p['path'] ?? '' );
		parse_str( (string) ( $p['query'] ?? '' ), $q );
		$out = array( 'provider' => '', 'id' => '', 'embed' => '', 'watch' => '', 'thumb' => '', 'src' => '', 'mime' => '' );

		if ( in_array( $host, array( 'youtube.com', 'youtube-nocookie.com', 'youtu.be' ), true ) ) {
			$id = '';
			if ( 'youtu.be' === $host ) {
				$id = ltrim( $path, '/' );
			} elseif ( preg_match( '#^/(?:embed|shorts|live|v)/([^/?]+)#', $path, $m ) ) {
				$id = $m[1];
			} elseif ( isset( $q['v'] ) && is_string( $q['v'] ) ) {
				$id = $q['v'];
			}
			$id = strtok( $id, '/' );
			if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', (string) $id ) ) {
				return null;
			}
			$start = isset( $q['t'] ) ? max( 0, (int) $q['t'] ) : ( isset( $q['start'] ) ? max( 0, (int) $q['start'] ) : 0 );
			$out   = array_merge( $out, array(
				'provider' => 'youtube',
				'id'       => $id,
				'embed'    => 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0&modestbranding=1&playsinline=1' . ( $start ? '&start=' . $start : '' ),
				'watch'    => 'https://www.youtube.com/watch?v=' . $id,
				'thumb'    => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
			) );
			return $out;
		}

		if ( in_array( $host, array( 'vimeo.com', 'player.vimeo.com' ), true ) ) {
			if ( ! preg_match( '#/(?:video/)?(\d{5,12})(?:/|$)#', $path, $m ) ) {
				return null;
			}
			$hash = isset( $q['h'] ) && is_string( $q['h'] ) && preg_match( '/^[A-Za-z0-9]{6,20}$/', $q['h'] ) ? $q['h'] : '';
			if ( '' === $hash && preg_match( '#/(\d{5,12})/([A-Za-z0-9]{6,20})/?$#', $path, $h ) ) {
				$hash = $h[2];
			}
			return array_merge( $out, array(
				'provider' => 'vimeo',
				'id'       => $m[1],
				'embed'    => 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1' . ( $hash ? '&h=' . $hash : '' ),
				'watch'    => 'https://vimeo.com/' . $m[1] . ( $hash ? '/' . $hash : '' ),
			) );
		}

		if ( 'aparat.com' === $host ) {
			if ( ! preg_match( '#^/(?:v|video/video/embed/videohash)/([A-Za-z0-9]{3,20})#', $path, $m ) ) {
				return null;
			}
			return array_merge( $out, array(
				'provider' => 'aparat',
				'id'       => $m[1],
				'embed'    => 'https://www.aparat.com/video/video/embed/videohash/' . $m[1] . '/vt/frame',
				'watch'    => 'https://www.aparat.com/v/' . $m[1],
			) );
		}

		// Direct media file (usually from the Media Library).
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, self::FILE_EXTS, true ) ) {
			$mime = array( 'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg', 'ogg' => 'video/ogg' );
			return array_merge( $out, array( 'provider' => 'file', 'src' => $url, 'watch' => $url, 'mime' => $mime[ $ext ] ) );
		}
		return null;
	}

	/** Only absolute http(s) URLs may be used for images. */
	public static function safe_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || strlen( $url ) > 2000 || ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		return function_exists( 'esc_url_raw' ) ? esc_url_raw( $url, array( 'http', 'https' ) ) : $url;
	}

	/* ---------------------------------------------------------------- */
	/* Items                                                             */
	/* ---------------------------------------------------------------- */

	private static function clean_text( $v, $max = 300 ) {
		$v = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $v ) : trim( strip_tags( (string) $v ) );
		return mb_substr( $v, 0, $max );
	}

	/**
	 * Turn raw rows (from a repeater, attachments or any other source) into safe items.
	 *
	 * @param array<int,array<string,mixed>> $raw
	 * @return array<int,array<string,mixed>>
	 */
	public static function normalize_items( array $raw, $max = self::MAX_ITEMS ) {
		$items = array();
		foreach ( $raw as $row ) {
			if ( count( $items ) >= (int) $max ) {
				break;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = isset( $row['type'] ) && 'video' === $row['type'] ? 'video' : 'image';
			$item = array(
				'type'    => $type,
				'src'     => self::safe_url( $row['src'] ?? '' ),
				'srcset'  => isset( $row['srcset'] ) ? self::clean_srcset( (string) $row['srcset'] ) : '',
				'width'   => max( 0, (int) ( $row['width'] ?? 0 ) ),
				'height'  => max( 0, (int) ( $row['height'] ?? 0 ) ),
				'alt'     => self::clean_text( $row['alt'] ?? '', 200 ),
				'title'   => self::clean_text( $row['title'] ?? '', 200 ),
				'caption' => self::clean_text( $row['caption'] ?? '', 400 ),
				'video'   => null,
				'poster'  => '',
			);
			if ( 'video' === $type ) {
				$video = self::parse_video( $row['video_url'] ?? '' );
				if ( ! $video ) {
					continue; // unsupported or unsafe video link
				}
				$item['video']  = $video;
				$item['poster'] = self::safe_url( $row['poster'] ?? '' ) ?: $video['thumb'];
				if ( '' === $item['src'] ) {
					$item['src'] = $item['poster'];
				}
			} elseif ( '' === $item['src'] ) {
				continue;
			}
			if ( '' === $item['alt'] ) {
				$item['alt'] = $item['title'] ?: $item['caption'];
			}
			$items[] = $item;
		}
		return $items;
	}

	/** Keep srcset entries that are absolute http(s) URLs with a width descriptor. */
	public static function clean_srcset( $srcset ) {
		$out = array();
		foreach ( explode( ',', $srcset ) as $part ) {
			$part = trim( $part );
			if ( preg_match( '#^(https?://\S+)\s+(\d{2,5}w)$#i', $part, $m ) ) {
				$out[] = self::safe_url( $m[1] ) . ' ' . $m[2];
			}
		}
		return implode( ', ', array_filter( $out ) );
	}

	/**
	 * Featured image plus attached images of a post (gallery, artist, artwork, exhibition…).
	 *
	 * @return array<int,array<string,mixed>> raw rows for normalize_items()
	 */
	public static function collect_post_images( $post_id, $limit = 24, $featured = true ) {
		$post_id = (int) $post_id;
		$rows    = array();
		$seen    = array();
		if ( $post_id <= 0 || 'publish' !== get_post_status( $post_id ) ) {
			return $rows;
		}
		$ids = array();
		if ( $featured && has_post_thumbnail( $post_id ) ) {
			$ids[] = (int) get_post_thumbnail_id( $post_id );
		}
		$attached = get_posts( array(
			'post_type'      => 'attachment',
			'post_parent'    => $post_id,
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => (int) $limit,
			'orderby'        => 'menu_order ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		foreach ( array_merge( $ids, (array) $attached ) as $att ) {
			$att = (int) $att;
			if ( isset( $seen[ $att ] ) ) {
				continue;
			}
			$seen[ $att ] = true;
			$rows[]       = self::attachment_row( $att, 'large' );
		}
		return array_slice( array_filter( $rows ), 0, (int) $limit );
	}

	/** Row for a Media Library image, with srcset and intrinsic size to avoid layout shift. */
	public static function attachment_row( $att_id, $size = 'large' ) {
		$att_id = (int) $att_id;
		$img    = $att_id ? wp_get_attachment_image_src( $att_id, $size ) : false;
		if ( ! $img ) {
			return array();
		}
		return array(
			'type'    => 'image',
			'src'     => $img[0],
			'width'   => $img[1],
			'height'  => $img[2],
			'srcset'  => (string) wp_get_attachment_image_srcset( $att_id, $size ),
			'alt'     => (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true ),
			'title'   => (string) get_the_title( $att_id ),
			'caption' => (string) wp_get_attachment_caption( $att_id ),
		);
	}

	/* ---------------------------------------------------------------- */
	/* Markup                                                            */
	/* ---------------------------------------------------------------- */

	/** @return array<string,mixed> */
	public static function options( array $o ) {
		$layout  = in_array( $o['layout'] ?? '', self::LAYOUTS, true ) ? $o['layout'] : 'scroller';
		$ratio   = in_array( $o['ratio'] ?? '', self::RATIOS, true ) ? $o['ratio'] : '4/3';
		$caption = in_array( $o['caption'] ?? '', self::CAPTIONS, true ) ? $o['caption'] : 'below';
		return array(
			'layout'      => $layout,
			'ratio'       => $ratio,
			'caption'     => $caption,
			'fit'         => 'contain' === ( $o['fit'] ?? '' ) ? 'contain' : 'cover',
			'lightbox'    => ! empty( $o['lightbox'] ),
			'arrows'      => ! empty( $o['arrows'] ),
			'dots'        => ! empty( $o['dots'] ),
			'autoplay'    => ! empty( $o['autoplay'] ),
			'interval'    => max( 2000, min( 20000, (int) ( $o['interval'] ?? 5000 ) ) ),
			'loop'        => ! empty( $o['loop'] ),
			'label'       => self::clean_text( $o['label'] ?? '', 120 ) ?: 'گالری تصاویر و ویدیوها',
			'eager_first' => ! empty( $o['eager_first'] ),
			'hover'       => in_array( $o['hover'] ?? '', array( 'none', 'zoom', 'lift' ), true ) ? $o['hover'] : 'zoom',
			'id'          => preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $o['id'] ?? 'x' ) ),
		);
	}

	/** @param array<int,array<string,mixed>> $items normalised items @param array<string,mixed> $opts */
	public static function render( array $items, array $opts ) {
		if ( ! $items ) {
			return '';
		}
		$o     = self::options( $opts );
		$count = count( $items );
		$cfg   = array(
			'autoplay' => $o['autoplay'] ? $o['interval'] : 0,
			'loop'     => $o['loop'],
			'lightbox' => $o['lightbox'],
		);
		$cls   = 'aetmg aetmg--' . $o['layout'] . ' aetmg-hover-' . $o['hover'] . ' aetmg-cap-' . $o['caption'] . ' aetmg-fit-' . $o['fit'];
		$style = '--aetmg-ratio:' . ( 'auto' === $o['ratio'] ? 'auto' : $o['ratio'] ) . ';';

		$html  = '<div class="' . esc_attr( $cls ) . '" style="' . esc_attr( $style ) . '" data-aetmg="' . esc_attr( wp_json_encode( $cfg ) ) . '"'
			. ' role="region" aria-roledescription="' . esc_attr( 'scroller' === $o['layout'] ? 'carousel' : 'gallery' ) . '" aria-label="' . esc_attr( $o['label'] ) . '">';
		$html .= '<div class="aetmg-viewport"' . ( 'scroller' === $o['layout'] ? ' tabindex="0"' : '' ) . '><ul class="aetmg-track">';

		foreach ( $items as $i => $it ) {
			$is_video = 'video' === $it['type'];
			$dims     = ( $it['width'] && $it['height'] ) ? ' width="' . (int) $it['width'] . '" height="' . (int) $it['height'] . '"' : '';
			$loading  = ( 0 === $i && $o['eager_first'] ) ? 'eager' : 'lazy';
			$thumb    = '' !== $it['src']
				? '<img src="' . esc_url( $it['src'] ) . '"' . ( $it['srcset'] ? ' srcset="' . esc_attr( $it['srcset'] ) . '" sizes="(max-width:600px) 90vw, (max-width:1024px) 45vw, 33vw"' : '' )
					. $dims . ' alt="' . esc_attr( $it['alt'] ) . '" loading="' . $loading . '" decoding="async">'
				: '<span class="aetmg-noimg" aria-hidden="true"></span>';
			$play     = $is_video ? '<span class="aetmg-play" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28" focusable="false"><path d="M8 5v14l11-7z" fill="currentColor"/></svg></span>' : '';
			$label    = $is_video
				? sprintf( 'پخش ویدیو: %s', $it['title'] ?: $it['alt'] ?: ( $i + 1 ) )
				: sprintf( 'نمایش بزرگ‌تر: %s', $it['title'] ?: $it['alt'] ?: ( $i + 1 ) );

			$html .= '<li class="aetmg-item" data-type="' . esc_attr( $it['type'] ) . '"><figure class="aetmg-fig">';
			if ( $o['lightbox'] || $is_video ) {
				$html .= '<button type="button" class="aetmg-open" data-index="' . (int) $i . '" aria-haspopup="dialog" aria-label="' . esc_attr( $label ) . '">' . $thumb . $play . '</button>';
			} else {
				$html .= '<span class="aetmg-open aetmg-static">' . $thumb . '</span>';
			}
			$cap = trim( $it['title'] . ( $it['title'] && $it['caption'] ? ' — ' : '' ) . $it['caption'] );
			if ( 'none' !== $o['caption'] && '' !== $cap ) {
				$html .= '<figcaption class="aetmg-cap">' . esc_html( $cap ) . '</figcaption>';
			}
			$html .= '</figure></li>';
		}
		$html .= '</ul></div>';

		if ( 'scroller' === $o['layout'] && $count > 1 ) {
			if ( $o['arrows'] ) {
				$html .= '<button type="button" class="aetmg-nav aetmg-prev" aria-label="قبلی"><svg viewBox="0 0 24 24" width="22" height="22" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
					. '<button type="button" class="aetmg-nav aetmg-next" aria-label="بعدی"><svg viewBox="0 0 24 24" width="22" height="22" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
			}
			if ( $o['dots'] ) {
				$html .= '<div class="aetmg-dots" role="tablist" aria-label="صفحه‌های گالری"></div>';
			}
		}
		$html .= '<script type="application/json" class="aetmg-data">' . self::lightbox_json( $items ) . '</script>';
		return $html . '</div>';
	}

	/** Items for the lightbox; encoded so no content can close the surrounding <script>. */
	public static function lightbox_json( array $items ) {
		$out = array();
		foreach ( $items as $it ) {
			$row = array(
				't'   => $it['type'],
				's'   => $it['src'],
				'a'   => $it['alt'],
				'c'   => trim( $it['title'] . ( $it['title'] && $it['caption'] ? ' — ' : '' ) . $it['caption'] ),
			);
			if ( 'video' === $it['type'] && $it['video'] ) {
				$row['p'] = $it['video']['provider'];
				$row['e'] = $it['video']['embed'];
				$row['f'] = $it['video']['src'];
				$row['m'] = $it['video']['mime'];
				$row['w'] = $it['video']['watch'];
				$row['o'] = $it['poster'];
			}
			$out[] = $row;
		}
		return wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	}

	/* ---------------------------------------------------------------- */
	/* Structured data                                                   */
	/* ---------------------------------------------------------------- */

	/**
	 * schema.org ImageGallery with ImageObject / VideoObject parts.
	 * Google requires name, thumbnailUrl and uploadDate for VideoObject, so videos missing a thumbnail are skipped.
	 *
	 * @param array<string,string> $ctx name, url, description, date (ISO 8601)
	 * @return array<string,mixed>
	 */
	public static function schema( array $items, array $ctx ) {
		$date  = ! empty( $ctx['date'] ) ? (string) $ctx['date'] : gmdate( 'c' );
		$parts = array();
		foreach ( $items as $it ) {
			$name = $it['title'] ?: ( $it['alt'] ?: ( $ctx['name'] ?? '' ) );
			if ( 'video' === $it['type'] && $it['video'] ) {
				$thumb = $it['poster'];
				if ( '' === $thumb || '' === $name ) {
					continue;
				}
				$v = array(
					'@type'        => 'VideoObject',
					'name'         => $name,
					'description'  => $it['caption'] ?: $name,
					'thumbnailUrl' => $thumb,
					'uploadDate'   => $date,
				);
				if ( 'file' === $it['video']['provider'] ) {
					$v['contentUrl'] = $it['video']['src'];
				} else {
					$v['embedUrl'] = $it['video']['embed'];
				}
				$parts[] = $v;
			} else {
				$img = array( '@type' => 'ImageObject', 'contentUrl' => $it['src'] );
				if ( $it['alt'] ) { $img['name'] = $it['alt']; }
				if ( $it['caption'] ) { $img['caption'] = $it['caption']; }
				if ( $it['width'] && $it['height'] ) { $img['width'] = (int) $it['width']; $img['height'] = (int) $it['height']; }
				$parts[] = $img;
			}
		}
		if ( ! $parts ) {
			return array();
		}
		$g = array( '@context' => 'https://schema.org', '@type' => 'ImageGallery', 'hasPart' => $parts );
		foreach ( array( 'name', 'url', 'description' ) as $k ) {
			if ( ! empty( $ctx[ $k ] ) ) {
				$g[ $k ] = (string) $ctx[ $k ];
			}
		}
		return $g;
	}

	public static function schema_script( array $schema ) {
		if ( ! $schema ) {
			return '';
		}
		return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
	}
}
