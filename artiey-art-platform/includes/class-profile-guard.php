<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides when a profile (artist, gallery, artwork, exhibition) may be added automatically to the post content.
 *
 * A widget or shortcode that renders the profile on purpose is always honoured exactly as configured. The automatic
 * block is only a convenience for plain posts, so it must never be added next to a hand-built layout (that is what
 * duplicated sections and re-added the sections a widget had switched off).
 */
final class Artiey_AET_Profile_Guard {

	private const REPAIR_HOOK = 'artiey_aet_profile_guard_repair_batch';
	private const REPAIR_CURSOR = 'artiey_aet_profile_guard_repair_cursor';
	private const REPAIR_BATCH_SIZE = 12;

	/** @var array<int,bool> Posts whose profile was rendered explicitly during this request. */
	private static $explicit = array();

	public static function mark( $post_id ) {
		self::$explicit[ (int) $post_id ] = true;
	}

	public static function is_marked( $post_id ) {
		return ! empty( self::$explicit[ (int) $post_id ] );
	}

	/** True when the post (or the template it is shown in) is designed with Elementor. */
	public static function is_designed( $post_id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		try {
			$doc = \Elementor\Plugin::$instance->documents->get( (int) $post_id );
			if ( $doc && $doc->is_built_with_elementor() ) {
				return true;
			}
			// Elementor Pro theme builder: a "single" template is applied to this post.
			if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
				$docs = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_locations_manager()->get_documents_for_location( 'single' );
				if ( ! empty( $docs ) ) {
					return true;
				}
			}
		} catch ( \Throwable $e ) {
			return false;
		}
		return false;
	}

	/**
	 * Biography HTML for a profile.
	 *
	 * A post built with Elementor stores the HTML that its widgets printed in `post_content`; using that as a
	 * "biography" repeats the whole profile inside itself. For such posts only the hand-written excerpt is used.
	 *
	 * @param WP_Post $post Profile post.
	 * @return string Safe HTML ('' when there is no editorial text).
	 */
	public static function bio_html( $post ) {
		$text = self::bio_source( $post );
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			return '';
		}
		return wpautop( wp_kses_post( $text ) );
	}

	/**
	 * Clean editorial text of a profile post (no wpautop), used for the biography and for the mirrored tables.
	 *
	 * Classic post: its content, cleaned. Post built with Elementor: the hand-written excerpt is the canonical
	 * biography source; Elementor Text Editor widgets are a backwards-compatible fallback only when the excerpt
	 * is empty. Never use the whole stored Elementor layout as biography.
	 *
	 * @param WP_Post $post Profile post.
	 * @return string HTML ('' when there is no editorial text).
	 */
	public static function bio_source( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		$content = strip_shortcodes( (string) $post->post_content );
		if ( self::is_built_with_elementor( $post->ID ) ) {
			// The excerpt is the intentional biography field for Elementor-built profiles.
			// Fall back to old Text Editor widgets only for legacy entries with no excerpt.
			$excerpt = self::clean_markup( strip_shortcodes( (string) $post->post_excerpt ) );
			if ( '' !== trim( wp_strip_all_tags( $excerpt ) ) ) {
				return $excerpt;
			}
			return self::clean_markup( self::text_widgets( $content ) );
		}
		return self::clean_markup( $content );
	}

	/** Inner HTML of every Elementor Text Editor widget of a rendered page ('' when there is none). */
	private static function text_widgets( $html ) {
			$html = (string) $html;
			if ( '' === trim( $html ) || false === strpos( $html, 'elementor-widget-text-editor' ) ) {
				return '';
			}

			if ( class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' ) ) {
				$prev = libxml_use_internal_errors( true );
				$dom  = new DOMDocument( '1.0', 'UTF-8' );
				$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?><div id="aet-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
				if ( $loaded ) {
					$xp  = new DOMXPath( $dom );
					$out = '';
					$widgets = $xp->query( '//*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-text-editor ")]' );
					if ( $widgets ) {
						foreach ( $widgets as $widget ) {
							$container = $xp->query( './/*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-container ")]', $widget )->item( 0 );
							$node = $container ? $container : $widget;
							foreach ( iterator_to_array( $node->childNodes ) as $child ) {
								$out .= $dom->saveHTML( $child );
							}
						}
					}
					libxml_clear_errors();
					libxml_use_internal_errors( $prev );
					if ( '' !== trim( $out ) ) {
						return $out;
					}
				} else {
					libxml_clear_errors();
					libxml_use_internal_errors( $prev );
				}
			}

			// Dependency-free fallback: extract balanced div blocks for Text Editor widgets only.
			$out = '';
			$offset = 0;
			$len = strlen( $html );
			while ( $offset < $len && preg_match( '/<div\\b[^>]*class=("|\')[^"\']*\\belementor-widget-text-editor\\b[^"\']*\\1[^>]*>/i', $html, $open, PREG_OFFSET_CAPTURE, $offset ) ) {
				$start_tag = $open[0][0];
				$start_pos = $open[0][1];
				$body_pos  = $start_pos + strlen( $start_tag );
				$scan      = $body_pos;
				$depth     = 1;
				$close_pos = false;
				if ( preg_match_all( '/<\\/?div\\b[^>]*>/i', $html, $tags, PREG_OFFSET_CAPTURE, $body_pos ) ) {
					foreach ( $tags[0] as $tag ) {
						$token = $tag[0];
						$pos = $tag[1];
						if ( preg_match( '/^<\\/div/i', $token ) ) {
							--$depth;
						} elseif ( ! preg_match( '/\\/\\s*>$/', $token ) ) {
							++$depth;
						}
						if ( 0 === $depth ) {
							$close_pos = $pos;
							$scan = $pos + strlen( $token );
							break;
						}
					}
				}
				if ( false === $close_pos ) {
					break;
				}
				$inner = substr( $html, $body_pos, $close_pos - $body_pos );
				if ( preg_match( '/<div\\b[^>]*class=("|\')[^"\']*\\belementor-widget-container\\b[^"\']*\\1[^>]*>/i', $inner, $container_open, PREG_OFFSET_CAPTURE ) ) {
					$cstart = $container_open[0][1] + strlen( $container_open[0][0] );
					$cclose = strripos( $inner, '</div>' );
					if ( false !== $cclose && $cclose >= $cstart ) {
						$inner = substr( $inner, $cstart, $cclose - $cstart );
					}
				}
				$out .= $inner;
				$offset = $scan;
			}
			return $out;
		}
	/**
	 * Remove everything that is not editorial text from a stored biography: <style>/<script> blocks (their CSS
	 * text would otherwise be printed on the page), any block this plugin or Elementor printed earlier
	 * (class "aetp…" or "elementor…"), and paragraphs that were pasted in several times by nested saves.
	 *
	 * @param string $html Stored post_content / excerpt.
	 * @return string Clean HTML.
	 */
	public static function clean_markup( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return '';
		}
		$dirty = (bool) preg_match( '/<(style|script|noscript)\b|class="[^"]*\b(aetp|elementor)/i', $html );
		if ( $dirty && class_exists( 'DOMDocument' ) ) {
			$prev = libxml_use_internal_errors( true );
			$dom  = new DOMDocument( '1.0', 'UTF-8' );
			$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="aet-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			$xp = new DOMXPath( $dom );
			$nodes = $xp->query( '//style | //script | //noscript | //*[@class and (contains(concat(" ", normalize-space(@class)), " aetp") or contains(concat(" ", normalize-space(@class)), " elementor"))]' );
			$kill  = array();
			foreach ( $nodes as $n ) {
				$kill[] = $n;
			}
			foreach ( $kill as $n ) {
				if ( $n->parentNode ) {
					$n->parentNode->removeChild( $n );
				}
			}
			$root = $dom->getElementById( 'aet-root' );
			if ( ! $root ) {
				$root = $xp->query( '//div[@id="aet-root"]' )->item( 0 );
			}
			$out = '';
			if ( $root ) {
				foreach ( iterator_to_array( $root->childNodes ) as $child ) {
					$out .= $dom->saveHTML( $child );
				}
			}
			libxml_clear_errors();
			libxml_use_internal_errors( $prev );
			$html = '' !== $out ? $out : '';
		} elseif ( $dirty ) {
			$html = preg_replace( '#<(style|script|noscript)\b.*?</\1>#is', '', $html );
			$html = preg_replace( '#<div class="aetp\b.*#is', '', $html );
		}
		return self::dedupe_blocks( $html );
	}

	/** Drop paragraphs / blocks whose text already appeared (nested saves paste the same biography again). */
	private static function dedupe_blocks( $html ) {
		$parts = preg_split( '#(?<=</p>)|(?<=</div>)|\n{2,}#u', (string) $html, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $parts || count( $parts ) < 2 ) {
			return (string) $html;
		}
		$seen = array();
		$out  = array();
		foreach ( $parts as $part ) {
			$key = preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( $part ) ) );
			if ( Artiey_AET_Text::length( $key ) >= 40 ) {
				$hash = md5( $key );
				if ( isset( $seen[ $hash ] ) ) {
					continue;
				}
				$seen[ $hash ] = true;
			}
			$out[] = $part;
		}
		return implode( "\n\n", $out );
	}

	/**
	 * Keep our own output (and Elementor's CSS) out of post_content when a profile post is saved, so a page
	 * that was rendered while saving cannot store a copy of the profile inside itself.
	 *
	 * @param array $data Slashed-free post data about to be stored.
	 * @return array
	 */
	public static function filter_save( $data ) {
		$types = array( 'artiey_artist', 'artiey_gallery', 'artiey_artwork', 'artiey_exhibition' );
		if ( empty( $data['post_type'] ) || ! in_array( $data['post_type'], $types, true ) || empty( $data['post_content'] ) ) {
			return $data;
		}
		if ( ! preg_match( '/class=\\\\?"[^"]*\baetp/i', (string) $data['post_content'] ) ) {
			return $data;
		}
		$data['post_content'] = wp_slash( self::clean_markup( wp_unslash( $data['post_content'] ) ) );
		return $data;
	}

	/** Register the bounded repair worker. */
	public static function init() {
		add_action( self::REPAIR_HOOK, array( __CLASS__, 'run_repair_batch' ) );
	}

	/** Queue profile cleanup without parsing hundreds of post bodies during an admin/editor request. */
	public static function queue_repair() {
		if ( false === get_option( self::REPAIR_CURSOR, false ) ) {
			update_option( self::REPAIR_CURSOR, 0, false );
		}
		if ( ! wp_next_scheduled( self::REPAIR_HOOK ) ) {
			wp_schedule_single_event( time() + 20, self::REPAIR_HOOK );
		}
	}

	/** Process a small batch; each request examines at most twelve legacy profile posts. */
	public static function run_repair_batch() {
		global $wpdb;
		$cursor = max( 0, (int) get_option( self::REPAIR_CURSOR, 0 ) );
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('artiey_artist','artiey_gallery','artiey_artwork','artiey_exhibition') AND post_content LIKE %s AND ID > %d ORDER BY ID ASC LIMIT %d",
			'%aetp%',
			$cursor,
			self::REPAIR_BATCH_SIZE
		) );
		if ( ! empty( $wpdb->last_error ) ) {
			if ( ! wp_next_scheduled( self::REPAIR_HOOK ) ) {
				wp_schedule_single_event( time() + 120, self::REPAIR_HOOK );
			}
			return 0;
		}
		$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();
		$fixed = 0;
		foreach ( $ids as $id ) {
			if ( ! $id || self::is_built_with_elementor( $id ) ) {
				continue;
			}
			$content = (string) get_post_field( 'post_content', $id );
			$clean = self::clean_markup( $content );
			if ( $clean !== $content ) {
				$wpdb->update( $wpdb->posts, array( 'post_content' => $clean ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( $id );
				++$fixed;
			}
		}
		if ( count( $ids ) < self::REPAIR_BATCH_SIZE ) {
			delete_option( self::REPAIR_CURSOR );
		} else {
			update_option( self::REPAIR_CURSOR, (int) end( $ids ), false );
			if ( ! wp_next_scheduled( self::REPAIR_HOOK ) ) {
				wp_schedule_single_event( time() + 15, self::REPAIR_HOOK );
			}
		}
		return $fixed;
	}

	/** Backwards-compatible synchronous repair for explicit maintenance tools only. */
	public static function repair_all() {
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('artiey_artist','artiey_gallery','artiey_artwork','artiey_exhibition') AND post_content LIKE '%aetp%' LIMIT 300" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$fixed = 0;
		foreach ( (array) $ids as $id ) {
			$id = absint( $id );
			if ( ! $id || self::is_built_with_elementor( $id ) ) { continue; }
			$content = (string) get_post_field( 'post_content', $id );
			$clean = self::clean_markup( $content );
			if ( $clean !== $content ) {
				$wpdb->update( $wpdb->posts, array( 'post_content' => $clean ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( $id );
				++$fixed;
			}
		}
		return $fixed;
	}

	/** True when the post itself holds an Elementor layout. */
	public static function is_built_with_elementor( $post_id ) {
		return 'builder' === get_post_meta( (int) $post_id, '_elementor_edit_mode', true ) || '' !== (string) get_post_meta( (int) $post_id, '_elementor_data', true );
	}

	/**
	 * @param int $post_id Post being displayed.
	 * @return bool Whether the automatic profile block may be added to the_content.
	 */
	public static function auto_allowed( $post_id ) {
		$allowed = ! self::is_marked( $post_id ) && ! self::is_designed( $post_id );
		/**
		 * Filter: allow or block the automatic profile block for a post.
		 *
		 * @param bool $allowed Default decision.
		 * @param int  $post_id Post ID.
		 */
		return (bool) apply_filters( 'artiey_aet_auto_profile', $allowed, (int) $post_id );
	}
}
