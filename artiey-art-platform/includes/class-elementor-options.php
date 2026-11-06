<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Cached post options used by Elementor controls. */
final class Artiey_AET_Elementor_Options {
	const TYPES = array( 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' );
	private static $request_cache = array();
	private static $current_post_cache = array();

	public static function init() {
		foreach ( self::TYPES as $type ) {
			add_action( 'save_post_' . $type, array( __CLASS__, 'invalidate' ), 10, 1 );
		}
		add_action( 'deleted_post', array( __CLASS__, 'deleted' ), 10, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'status_changed' ), 10, 3 );
	}

	public static function posts( $type ) {
		$type = sanitize_key( $type );
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return array();
		}
		if ( isset( self::$request_cache[ $type ] ) ) {
			return self::$request_cache[ $type ];
		}
		$key  = 'aet_elementor_options_' . md5( $type );
		$rows = get_transient( $key );
		if ( false === $rows ) {
			$posts = get_posts( array(
				'post_type'              => $type,
				'post_status'            => 'publish',
				'numberposts'            => 300,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );
			$rows = array();
			foreach ( $posts as $post ) {
				$rows[ (int) $post->ID ] = $post->post_title;
			}
			set_transient( $key, $rows, HOUR_IN_SECONDS );
		}
		self::$request_cache[ $type ] = is_array( $rows ) ? $rows : array();
		return self::$request_cache[ $type ];
	}

	public static function current_post_id( $type ) {
		$type       = sanitize_key( $type );
		if ( isset( self::$current_post_cache[ $type ] ) ) {
			return self::$current_post_cache[ $type ];
		}
		$candidates = array();
		$in_editor  = self::is_elementor_preview();

		// In Elementor, the active document/request is authoritative. WordPress globals
		// may temporarily point at a template, loop item, or stale preview post.
		if ( $in_editor ) {
			if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
				try {
					$documents = \Elementor\Plugin::$instance->documents;
					$document  = ( is_object( $documents ) && method_exists( $documents, 'get_current' ) ) ? $documents->get_current() : null;
					if ( $document && method_exists( $document, 'get_main_id' ) ) {
						$candidates[] = absint( $document->get_main_id() );
					} elseif ( $document && method_exists( $document, 'get_id' ) ) {
						$candidates[] = absint( $document->get_id() );
					}
				} catch ( \Throwable $e ) {
					// Fall back to the authenticated request IDs below.
				}
			}

			// The editor shell commonly carries post=ID; the preview iframe uses elementor-preview=ID.
			foreach ( array( 'elementor-preview', 'editor_post_id', 'post_id', 'post' ) as $request_key ) {
				if ( isset( $_REQUEST[ $request_key ] ) && is_scalar( $_REQUEST[ $request_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, then type/capability checked.
					$candidates[] = absint( wp_unslash( $_REQUEST[ $request_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
			}
		}

		// Outside Elementor, use the normal WordPress queried/global post context.
		if ( function_exists( 'get_the_ID' ) ) {
			$id = absint( get_the_ID() );
			if ( $id ) { $candidates[] = $id; }
		}
		if ( function_exists( 'get_queried_object_id' ) ) {
			$id = absint( get_queried_object_id() );
			if ( $id ) { $candidates[] = $id; }
		}
		global $post;
		if ( $post instanceof WP_Post ) {
			$candidates[] = absint( $post->ID );
		}

		foreach ( array_unique( array_filter( $candidates ) ) as $candidate ) {
			if ( $type !== get_post_type( $candidate ) ) {
				continue;
			}
			$status = get_post_status( $candidate );
			if ( 'publish' === $status || ( function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $candidate ) ) ) {
				if ( $in_editor ) {
					self::$current_post_cache[ $type ] = (int) $candidate;
				}
				return (int) $candidate;
			}
		}
		return 0;
	}

	public static function sections( $settings, $key, $allowed ) {
		if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
			return null;
		}
		$raw = $settings[ $key ];
		if ( is_string( $raw ) ) {
			$selected = '' === trim( $raw ) ? array() : preg_split( '/\\s*,\\s*/', trim( $raw ) );
		} elseif ( is_array( $raw ) ) {
			$selected = $raw;
		} else {
			$selected = array();
		}
		$selected = array_values( array_unique( array_filter( array_map( static function ( $value ) {
			return is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
		}, $selected ) ) ) );
		$allowed = array_values( array_unique( array_map( 'sanitize_key', (array) $allowed ) ) );
		return array_values( array_intersect( $allowed, $selected ) );
	}

	/** True for the Elementor editor shell and its authenticated preview/AJAX document requests. */
	public static function is_elementor_preview() {
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance ) ) {
			$plugin = \Elementor\Plugin::$instance;
			try {
				if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
					return true;
				}
				if ( isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
					return true;
				}
			} catch ( \Throwable $e ) {
				// Fall through to the guarded request checks.
			}
		}
		$action = isset( $_REQUEST['action'] ) && is_scalar( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
		if ( function_exists( 'is_admin' ) && is_admin() && in_array( $action, array( 'elementor', 'elementor_ajax', 'elementor_ajax_save' ), true ) && function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
			return true;
		}
		$preview_id = isset( $_REQUEST['elementor-preview'] ) && is_scalar( $_REQUEST['elementor-preview'] ) ? absint( wp_unslash( $_REQUEST['elementor-preview'] ) ) : 0;
		return $preview_id > 0 && function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $preview_id );
	}

	public static function is_editor_mode() {
		return self::is_elementor_preview();
	}

	public static function deleted( $post_id, $post = null ) {
		$type = ( $post instanceof WP_Post ) ? $post->post_type : '';
		self::invalidate( $post_id, $type );
	}

	public static function invalidate( $post_id = 0, $post_type = '' ) {
		$type = $post_type ? sanitize_key( $post_type ) : ( $post_id ? get_post_type( $post_id ) : '' );
		if ( $type && in_array( $type, self::TYPES, true ) ) {
			unset( self::$request_cache[ $type ] );
			delete_transient( 'aet_elementor_options_' . md5( $type ) );
		}
	}

	public static function status_changed( $new, $old, $post ) {
		if ( $new !== $old && $post instanceof WP_Post ) {
			self::invalidate( $post->ID );
		}
	}
}
