<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public presentation of curators (cards, list, compact, featured) for the Elementor widget and the shortcode.
 *
 * Privacy: only ACTIVE curators are read, and only public columns are selected. Email, phone and the linked
 * WordPress user id are never loaded here, so they cannot leak into markup or structured data.
 */
final class Artiey_AET_Curator_View {

	const LAYOUTS = array( 'card', 'list', 'compact', 'featured' );
	const ORDERS  = array( 'newest', 'name', 'experience' );
	const PUBLIC_COLUMNS = 'id, display_name, slug, bio, specialties, curatorial_statement, affiliation, languages, years_experience, website, instagram_url, linkedin_url, avatar_url, cover_url';
	const TTL     = 10 * MINUTE_IN_SECONDS;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_shortcode( 'artiey_curators', array( __CLASS__, 'shortcode' ) );
	}

	public static function register_assets() {
		wp_register_style( 'artiey-aet-curator', AET_URL . 'assets/css/curator.css', array(), AET_VERSION );
	}

	/** Curators come from the certificate module's table; without it there is nothing to show. */
	public static function available() {
		return class_exists( '\\WPArtCOA\\Database' );
	}

	/* ---------------------------------------------------------------- */
	/* Data                                                              */
	/* ---------------------------------------------------------------- */

	/**
	 * @param array{id?:int,slug?:string,limit?:int,orderby?:string,specialty?:string} $args
	 * @return object[] rows with public columns only
	 */
	public static function fetch( array $args ) {
		if ( ! self::available() ) {
			return array();
		}
		global $wpdb;
		$table = \WPArtCOA\Database::table( 'curators' );
		$limit = max( 1, min( 48, (int) ( $args['limit'] ?? 12 ) ) );

		$where  = array( "status = 'active'" );
		$params = array();
		if ( ! empty( $args['id'] ) ) {
			$where[]  = 'id = %d';
			$params[] = (int) $args['id'];
		} elseif ( ! empty( $args['slug'] ) ) {
			$where[]  = 'slug = %s';
			$params[] = sanitize_title( (string) $args['slug'] );
		}
		$specialty = trim( (string) ( $args['specialty'] ?? '' ) );
		if ( '' !== $specialty ) {
			$like     = '%' . $wpdb->esc_like( mb_substr( $specialty, 0, 60 ) ) . '%';
			$where[]  = '(specialties LIKE %s OR bio LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		$order = array(
			'newest'     => 'id DESC',
			'name'       => 'display_name ASC',
			'experience' => 'years_experience DESC, id DESC',
		);
		$orderby  = $order[ $args['orderby'] ?? 'newest' ] ?? $order['newest'];
		$params[] = $limit;

		$key  = 'aet_cur_' . md5( wp_json_encode( array( $where, $params, $orderby, get_option( 'artiey_directory_cache_version', 1 ) ) ) );
		$rows = get_transient( $key );
		if ( is_array( $rows ) ) {
			return $rows;
		}
		$sql  = 'SELECT ' . self::PUBLIC_COLUMNS . " FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} LIMIT %d"; // phpcs:ignore WordPress.DB.PreparedSQL -- table and order come from fixed values
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$rows = is_array( $rows ) ? $rows : array();
		set_transient( $key, $rows, self::TTL );
		return $rows;
	}

	/** id => name, for the editor's curator picker. */
	public static function picker_options() {
		$out = array();
		foreach ( self::fetch( array( 'limit' => 48, 'orderby' => 'name' ) ) as $row ) {
			$out[ (int) $row->id ] = (string) $row->display_name;
		}
		return $out;
	}

	/* ---------------------------------------------------------------- */
	/* Helpers                                                           */
	/* ---------------------------------------------------------------- */

	/** @return string[] up to $max unique, trimmed specialties from a comma / newline separated text. */
	public static function chips( $text, $max = 8 ) {
		$parts = preg_split( '/[,،;؛\r\n]+/u', (string) $text ) ?: array();
		$parts = array_values( array_unique( array_filter( array_map( static function ( $p ) {
			return mb_substr( trim( wp_strip_all_tags( $p ) ), 0, 60 );
		}, $parts ), static function ( $p ) {
			return '' !== $p;
		} ) ) );
		return array_slice( $parts, 0, (int) $max );
	}

	public static function excerpt( $html, $words = 30 ) {
		$text  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $html ) ) );
		$words = max( 5, (int) $words );
		$list  = preg_split( '/\s+/u', $text ) ?: array();
		if ( count( $list ) <= $words ) {
			return $text;
		}
		return implode( ' ', array_slice( $list, 0, $words ) ) . '…';
	}

	public static function initials( $name ) {
		$parts = preg_split( '/\s+/u', trim( (string) $name ) ) ?: array();
		$out   = '';
		foreach ( array_slice( array_filter( $parts ), 0, 2 ) as $p ) {
			$out .= mb_substr( $p, 0, 1 );
		}
		return '' !== $out ? $out : '؟';
	}

	public static function profile_url( $slug ) {
		return home_url( '/art-coa/curator/' . rawurlencode( (string) $slug ) . '/' );
	}

	private static function http( $url ) {
		return Artiey_AET_Media_Gallery::safe_url( $url );
	}

	/** @return array<string,string> label => url, valid http(s) links only. */
	public static function social( $row ) {
		$out = array();
		foreach ( array( 'website' => 'وب‌سایت', 'instagram_url' => 'اینستاگرام', 'linkedin_url' => 'لینکدین' ) as $col => $label ) {
			$url = self::http( $row->{$col} ?? '' );
			if ( $url ) {
				$out[ $label ] = $url;
			}
		}
		return $out;
	}

	/** @return array<string,mixed> */
	public static function options( array $o ) {
		return array(
			'layout'       => in_array( $o['layout'] ?? '', self::LAYOUTS, true ) ? $o['layout'] : 'card',
			'avatar'       => ! array_key_exists( 'avatar', $o ) || ! empty( $o['avatar'] ),
			'cover'        => ! empty( $o['cover'] ),
			'affiliation'  => ! array_key_exists( 'affiliation', $o ) || ! empty( $o['affiliation'] ),
			'specialties'  => ! array_key_exists( 'specialties', $o ) || ! empty( $o['specialties'] ),
			'bio'          => ! array_key_exists( 'bio', $o ) || ! empty( $o['bio'] ),
			'bio_words'    => max( 5, min( 120, (int) ( $o['bio_words'] ?? 28 ) ) ),
			'meta'         => ! empty( $o['meta'] ),
			'social'       => ! empty( $o['social'] ),
			'button'       => ! array_key_exists( 'button', $o ) || ! empty( $o['button'] ),
			'button_text'  => '' !== trim( (string) ( $o['button_text'] ?? '' ) ) ? mb_substr( trim( (string) $o['button_text'] ), 0, 40 ) : 'مشاهده پروفایل',
			'name_tag'     => in_array( $o['name_tag'] ?? 'h3', array( 'h2', 'h3', 'h4', 'div' ), true ) ? $o['name_tag'] : 'h3',
			'avatar_shape' => in_array( $o['avatar_shape'] ?? 'circle', array( 'circle', 'rounded', 'square' ), true ) ? $o['avatar_shape'] : 'circle',
			'label'        => '' !== trim( (string) ( $o['label'] ?? '' ) ) ? mb_substr( trim( (string) $o['label'] ), 0, 120 ) : 'کیوریتورها',
		);
	}

	/* ---------------------------------------------------------------- */
	/* Markup                                                            */
	/* ---------------------------------------------------------------- */

	/** @param object[] $rows @param array<string,mixed> $opts */
	public static function render( array $rows, array $opts ) {
		if ( ! $rows ) {
			return '';
		}
		$o    = self::options( $opts );
		$html = '<div class="aetcu aetcu--' . esc_attr( $o['layout'] ) . ' aetcu-shape-' . esc_attr( $o['avatar_shape'] ) . '" role="list" aria-label="' . esc_attr( $o['label'] ) . '">';
		foreach ( $rows as $row ) {
			$html .= self::card( $row, $o );
		}
		return $html . '</div>';
	}

	private static function card( $row, array $o ) {
		$name  = (string) $row->display_name;
		$url   = self::profile_url( $row->slug );
		$tag   = $o['name_tag'];
		$cover = $o['cover'] || 'featured' === $o['layout'] ? self::http( $row->cover_url ?? '' ) : '';
		$av    = $o['avatar'] ? self::http( $row->avatar_url ?? '' ) : '';

		$h = '<article class="aetcu-item" role="listitem">';
		if ( $cover ) {
			$h .= '<div class="aetcu-cover"><img src="' . esc_url( $cover ) . '" alt="" loading="lazy" decoding="async"></div>';
		}
		if ( $o['avatar'] ) {
			$h .= '<a class="aetcu-avatar" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">'
				. ( $av ? '<img src="' . esc_url( $av ) . '" alt="" loading="lazy" decoding="async" width="160" height="160">' : '<span class="aetcu-initials">' . esc_html( self::initials( $name ) ) . '</span>' )
				. '</a>';
		}
		$h .= '<div class="aetcu-body">';
		$h .= '<' . $tag . ' class="aetcu-name"><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- tag is allow-listed
		if ( $o['affiliation'] && '' !== trim( (string) $row->affiliation ) ) {
			$h .= '<p class="aetcu-aff">' . esc_html( $row->affiliation ) . '</p>';
		}
		if ( $o['specialties'] ) {
			$chips = self::chips( $row->specialties );
			if ( $chips ) {
				$h .= '<ul class="aetcu-chips">';
				foreach ( $chips as $chip ) {
					$h .= '<li>' . esc_html( $chip ) . '</li>';
				}
				$h .= '</ul>';
			}
		}
		if ( $o['bio'] ) {
			$text = '' !== trim( (string) $row->curatorial_statement ) && 'featured' === $o['layout'] ? $row->curatorial_statement : $row->bio;
			$ex   = self::excerpt( $text, $o['bio_words'] );
			if ( '' !== $ex ) {
				$h .= '<p class="aetcu-bio">' . esc_html( $ex ) . '</p>';
			}
		}
		if ( $o['meta'] ) {
			$meta = array();
			if ( ! empty( $row->years_experience ) ) {
				$meta[] = sprintf( '%d سال تجربه', (int) $row->years_experience );
			}
			if ( '' !== trim( (string) $row->languages ) ) {
				$meta[] = 'زبان: ' . $row->languages;
			}
			if ( $meta ) {
				$h .= '<p class="aetcu-meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
			}
		}
		if ( $o['social'] ) {
			$links = self::social( $row );
			if ( $links ) {
				$h .= '<ul class="aetcu-social">';
				foreach ( $links as $label => $link ) {
					$h .= '<li><a href="' . esc_url( $link ) . '" target="_blank" rel="me nofollow noopener noreferrer" aria-label="' . esc_attr( $label . ' ' . $name ) . '">' . esc_html( $label ) . '</a></li>';
				}
				$h .= '</ul>';
			}
		}
		if ( $o['button'] ) {
			$h .= '<a class="aetcu-btn" href="' . esc_url( $url ) . '">' . esc_html( $o['button_text'] ) . '<span class="screen-reader-text"> ' . esc_html( $name ) . '</span></a>';
		}
		return $h . '</div></article>';
	}

	/* ---------------------------------------------------------------- */
	/* Structured data                                                   */
	/* ---------------------------------------------------------------- */

	/** @return array<string,mixed> schema.org Person for one curator. */
	public static function person( $row ) {
		$p = array(
			'@type'    => 'Person',
			'name'     => (string) $row->display_name,
			'url'      => self::profile_url( $row->slug ),
			'jobTitle' => 'کیوریتور',
		);
		$desc = self::excerpt( $row->bio, 40 );
		if ( '' !== $desc ) {
			$p['description'] = $desc;
		}
		$img = self::http( $row->avatar_url ?? '' );
		if ( $img ) {
			$p['image'] = $img;
		}
		if ( '' !== trim( (string) $row->affiliation ) ) {
			$p['worksFor'] = array( '@type' => 'Organization', 'name' => (string) $row->affiliation );
		}
		$chips = self::chips( $row->specialties, 12 );
		if ( $chips ) {
			$p['knowsAbout'] = $chips;
		}
		$same = array_values( array_filter( array( self::http( $row->instagram_url ?? '' ), self::http( $row->linkedin_url ?? '' ), self::http( $row->website ?? '' ) ) ) );
		if ( $same ) {
			$p['sameAs'] = $same;
		}
		return $p;
	}

	/** @param object[] $rows @return array<string,mixed> */
	public static function schema( array $rows ) {
		if ( ! $rows ) {
			return array();
		}
		if ( 1 === count( $rows ) ) {
			return array( '@context' => 'https://schema.org' ) + self::person( $rows[0] );
		}
		$list = array();
		foreach ( $rows as $i => $row ) {
			$list[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'item' => self::person( $row ) );
		}
		return array( '@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $list );
	}

	/* ---------------------------------------------------------------- */
	/* Shortcode                                                         */
	/* ---------------------------------------------------------------- */

	/** [artiey_curators limit="6" layout="card" columns="3" order="name" specialty="معاصر" curator="slug-or-id"] */
	public static function shortcode( $atts ) {
		$a = shortcode_atts( array(
			'limit' => '6', 'layout' => 'card', 'columns' => '', 'order' => 'newest', 'specialty' => '', 'curator' => '',
			'bio' => '1', 'social' => '0', 'schema' => '1',
		), (array) $atts, 'artiey_curators' );

		$args = array( 'limit' => (int) $a['limit'], 'orderby' => $a['order'], 'specialty' => $a['specialty'] );
		if ( '' !== $a['curator'] ) {
			if ( ctype_digit( (string) $a['curator'] ) ) {
				$args['id'] = (int) $a['curator'];
			} else {
				$args['slug'] = (string) $a['curator'];
			}
		}
		$rows = self::fetch( $args );
		if ( ! $rows ) {
			return '';
		}
		wp_enqueue_style( 'artiey-aet-curator' );
		$on   = static function ( $v ) {
			return ! in_array( strtolower( (string) $v ), array( '', '0', 'no', 'false', 'off' ), true );
		};
		$html = self::render( $rows, array( 'layout' => $a['layout'], 'bio' => $on( $a['bio'] ), 'social' => $on( $a['social'] ) ) );
		if ( preg_match( '/^[1-6]$/', (string) $a['columns'] ) ) {
			$html = '<div style="--aetcu-cols:' . (int) $a['columns'] . '">' . $html . '</div>';
		}
		if ( $on( $a['schema'] ) ) {
			$html .= Artiey_AET_Media_Gallery::schema_script( self::schema( $rows ) );
		}
		return $html;
	}
}
