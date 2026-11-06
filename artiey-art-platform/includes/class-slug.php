<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Finglish slugs for Persian titles, without changing unrelated Latin slugs. */
final class Artiey_AET_Slug {

	private const WORDS = array(
		'نمایشگاه' => 'namayeshgah', 'گالری' => 'gallery', 'هنرمند' => 'honarmand', 'هنرمندان' => 'honarmandan',
		'هنر' => 'honar', 'ایرانی' => 'irani', 'ایران' => 'iran', 'اثر' => 'asar', 'آثار' => 'asarha',
		'نقاشی' => 'naghashi', 'مجسمه' => 'mojasameh', 'عکس' => 'aks', 'معاصر' => 'moaser', 'مدرن' => 'modern',
		'کلاسیک' => 'classic', 'بینالمللی' => 'beynolmelali', 'فروشگاه' => 'foroshgah', 'حراج' => 'haraj',
		'حراجی' => 'haraji', 'مجموعه' => 'majmooeh', 'کیوریتور' => 'curator', 'معماری' => 'memari',
		'کتاب' => 'ketab', 'خانه' => 'khaneh', 'موزه' => 'mozeh', 'شماره' => 'shomareh', 'صفحه' => 'safheh',
		'راهنما' => 'rahnama', 'گزارش' => 'gozaresh', 'فراخوان' => 'farakhan', 'عضویت' => 'ozviyat',
		'تماس' => 'tamas', 'درباره' => 'darbareh', 'تاریخ' => 'tarikh', 'جایزه' => 'jayzeh', 'جوایز' => 'jayayez',
		'بین' => 'beyn', 'المللی' => 'olmelali', 'هنری' => 'honari', 'آنلاین' => 'online', 'فیزیکی' => 'fiziki',
	);

	private const LETTERS = array(
		'ا' => 'a', 'آ' => 'aa', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's', 'ج' => 'j', 'چ' => 'ch',
		'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's',
		'ش' => 'sh', 'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f',
		'ق' => 'gh', 'ک' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'و' => 'v', 'ه' => 'h',
		'ی' => 'y', 'ى' => 'y', 'ة' => 'h', 'ؤ' => 'v', 'ئ' => 'y', 'أ' => 'a', 'إ' => 'a', 'ٱ' => 'a',
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	);

	public static function init() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'filter_post_data' ), 10, 4 );
		add_filter( 'wp_insert_term_data', array( __CLASS__, 'filter_term_data' ), 10, 3 );
		add_filter( 'wp_update_term_data', array( __CLASS__, 'filter_term_data' ), 10, 4 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'request', array( __CLASS__, 'resolve_artist_route' ), 1 );
		add_action( 'init', array( __CLASS__, 'migrate_bases' ), 0 );
		add_action( 'init', array( __CLASS__, 'normalize_artist_base' ), 1 );
		add_action( 'init', array( __CLASS__, 'register_legacy_rules' ), 30 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_base' ), 1 );
		// Run after every module has registered its rewrite rules. This also repairs
		// sites upgraded from releases that kept a stale rewrite schema marker.
		add_action( 'init', array( __CLASS__, 'maybe_refresh_routes' ), 99 );
		add_action( 'admin_notices', array( __CLASS__, 'route_repair_notice' ) );
	}

	/** Convert Persian/Arabic text into a short, URL-safe Latin slug. */
	public static function from( $value, $fallback = 'item' ) {
		$value = trim( html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' ) );
		$value = str_replace( array( 'ك', 'ي', 'ى', 'ـ', '‌', '‍' ), array( 'ک', 'ی', 'ی', '', ' ', ' ' ), $value );
		$value = preg_replace( '/[\x{064B}-\x{065F}\x{0670}]/u', '', $value );
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY );
		$out   = array();

		foreach ( (array) $parts as $part ) {
			if ( isset( self::WORDS[ $part ] ) ) {
				$out[] = self::WORDS[ $part ];
			} elseif ( preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $part ) ) {
				$out[] = strtr( strtolower( $part ), self::LETTERS );
			} else {
				$out[] = sanitize_title( $part );
			}
		}

		$slug = sanitize_title( implode( '-', array_filter( $out ) ) );
		if ( '' === $slug ) {
			$slug = sanitize_title( (string) $fallback );
		}
		return self::shorten( $slug, 80 );
	}

	/** Cut a slug at a word boundary (never mid-word) so URLs stay short and readable. */
	public static function shorten( $slug, $max = 80 ) {
		$slug = (string) $slug;
		if ( strlen( $slug ) <= $max ) {
			return $slug;
		}
		$cut = substr( $slug, 0, $max );
		$pos = strrpos( $cut, '-' );
		if ( false !== $pos && $pos > (int) ( $max / 2 ) ) {
			$cut = substr( $cut, 0, $pos );
		}
		return trim( $cut, '-' );
	}

	/** URL bases of the post types; artist is deliberately fixed to keep its archive and singles unified. */
	public const BASES = array(
		'gallery'    => 'galleries',
		'exhibition' => 'exhibitions',
		'artist'     => 'artists',
		'artwork'    => 'artworks',
		'style'      => 'art-styles',
	);

	/** Previous default bases kept as 301 aliases during the URL migration. */
	private const LEGACY_BASES = array(
		'gallery'    => 'gallery',
		'exhibition' => 'exhibition',
		'artist'     => 'artist',
		'artwork'    => 'artwork',
		'style'      => 'art-style',
	);

	private const LEGACY_OPTION = 'artiey_aet_legacy_bases';
	private const MIGRATION_OPTION = 'artiey_aet_slug_migration_229';
	private const ARTIST_BASE_MIGRATION_OPTION = 'artiey_aet_artist_base_migration_23211';
	/** Bump whenever a public rewrite route or canonical URL dispatcher changes. */
	private const ROUTE_SCHEMA_VERSION = '2.32.32';
	private static $routes_refreshed_this_request = false;

	/** Words that would collide with WordPress or with this plugin's own routes. */
	private const RESERVED = array( 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'page', 'comments', 'embed', 'search', 'author', 'category', 'tag', 'art-coa', 'admin', 'login' );

	/** Clean one base value; returns the default when it is empty or reserved. */
	public static function clean_base( $value, $default ) {
		$parts = array();
		foreach ( explode( '/', trim( (string) $value, " 	/" ) ) as $p ) {
			$p = sanitize_title( $p );
			if ( '' !== $p ) {
				$parts[] = $p;
			}
		}
		$base = implode( '/', array_slice( $parts, 0, 2 ) );
		if ( '' === $base || in_array( $parts[0], self::RESERVED, true ) ) {
			return $default;
		}
		return substr( $base, 0, 60 );
	}

	/** Configured base for a post type key (gallery, exhibition, artist, artwork, style). */
	public static function base( $key ) {
		$default = self::BASES[ $key ] ?? sanitize_title( (string) $key );
		// The artist archive and artist singles share one canonical namespace.
		// Never allow an option/filter to split /artists/ from /artists/{slug}/.
		if ( 'artist' === $key ) {
			return self::BASES['artist'];
		}
		$saved = class_exists( 'Artiey_AET_Settings' ) ? Artiey_AET_Settings::get( 'base_' . $key ) : '';
		return self::clean_base( $saved, $default );
	}

	/**
	 * Upgrade default public bases to a consistent plural convention while preserving explicit custom bases.
	 * Old paths are persisted for compatibility redirects; no post_name values or records are rewritten here.
	 */
	public static function migrate_bases() {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return false;
		}

		$option = class_exists( 'Artiey_AET_Settings' ) ? Artiey_AET_Settings::OPTION : 'artiey_aet_options';
		$saved  = get_option( $option, array() );
		$saved  = is_array( $saved ) ? $saved : array();
		$aliases = get_option( self::LEGACY_OPTION, array() );
		$aliases = is_array( $aliases ) ? $aliases : array();
		$changed = false;

		foreach ( self::BASES as $key => $canonical ) {
			$setting_key = 'base_' . $key;
			$old_default = self::LEGACY_BASES[ $key ] ?? $canonical;
			$current     = isset( $saved[ $setting_key ] ) && is_string( $saved[ $setting_key ] ) ? trim( $saved[ $setting_key ] ) : '';
			$effective   = '' === $current ? $old_default : self::clean_base( $current, $canonical );

			if ( ! isset( $aliases[ $key ] ) || ! is_array( $aliases[ $key ] ) ) {
				$aliases[ $key ] = array();
			}
			foreach ( array( $old_default, $effective ) as $old ) {
				$old = self::clean_base( $old, $canonical );
				if ( '' !== $old && $old !== $canonical && ! in_array( $old, $aliases[ $key ], true ) ) {
					$aliases[ $key ][] = $old;
				}
			}

			// Default values from previous releases are upgraded; intentional custom values are retained.
			if ( '' === $current || $current === $old_default ) {
				if ( ( $saved[ $setting_key ] ?? null ) !== $canonical ) {
					$saved[ $setting_key ] = $canonical;
					$changed = true;
				}
			} else {
				$clean = self::clean_base( $current, $canonical );
				if ( $clean !== $current ) {
					$saved[ $setting_key ] = $clean;
					$changed = true;
				}
			}
		}

		update_option( self::LEGACY_OPTION, $aliases, false );
		if ( $changed || ! is_array( get_option( $option, null ) ) ) {
			update_option( $option, $saved, false );
			if ( class_exists( 'Artiey_AET_Settings' ) && method_exists( 'Artiey_AET_Settings', 'clear_cache' ) ) {
				Artiey_AET_Settings::clear_cache();
			}
		}
		update_option( self::MIGRATION_OPTION, 1, false );
		if ( $changed ) {
			update_option( 'artiey_aet_flush', 1, false );
		}
		return $changed;
	}

	/** Make /artists/{slug}/ the only canonical WordPress artist-profile base. */
	public static function normalize_artist_base() {
		if ( get_option( self::ARTIST_BASE_MIGRATION_OPTION ) ) { return false; }
		$option = class_exists( 'Artiey_AET_Settings' ) ? Artiey_AET_Settings::OPTION : 'artiey_aet_options';
		$saved = get_option( $option, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$current = isset( $saved['base_artist'] ) && is_string( $saved['base_artist'] ) ? trim( $saved['base_artist'] ) : '';
		$effective = '' === $current ? self::BASES['artist'] : self::clean_base( $current, self::BASES['artist'] );
		$changed = $effective !== self::BASES['artist'] || $current !== self::BASES['artist'];
		if ( $effective !== self::BASES['artist'] ) {
			// remember_alias() intentionally ignores the currently configured base;
			// this migration must capture that value before replacing it with /artists/.
			$aliases = get_option( self::LEGACY_OPTION, array() );
			$aliases = is_array( $aliases ) ? $aliases : array();
			if ( ! isset( $aliases['artist'] ) || ! is_array( $aliases['artist'] ) ) {
				$aliases['artist'] = array();
			}
			if ( ! in_array( $effective, $aliases['artist'], true ) ) {
				$aliases['artist'][] = $effective;
				update_option( self::LEGACY_OPTION, $aliases, false );
			}
		}
		if ( $changed ) {
			$saved['base_artist'] = self::BASES['artist'];
			update_option( $option, $saved, false );
			if ( class_exists( 'Artiey_AET_Settings' ) && method_exists( 'Artiey_AET_Settings', 'clear_cache' ) ) {
				Artiey_AET_Settings::clear_cache();
			}
			update_option( 'artiey_aet_flush', 1, false );
		}
		update_option( self::ARTIST_BASE_MIGRATION_OPTION, 1, false );
		return $changed;
	}

	/**
	 * Rebuild WordPress rewrite rules once for this route schema and invalidate only
	 * caches that can retain old public URLs or directory/profile output.
	 *
	 * Old public URLs remain valid 301 aliases; this clears stale route/cache state,
	 * it does not delete compatibility redirects needed by search engines.
	 */
	public static function maybe_refresh_routes() {
		$stored = (string) get_option( 'artiey_aet_rewrite_version', '' );
		$pending = (bool) get_option( 'artiey_aet_flush' );
		if ( self::ROUTE_SCHEMA_VERSION === $stored && ! $pending ) {
			return false;
		}

		// All rewrite providers run before priority 99 on init. Remove the cached
		// rewrite option first so both normal and persistent object caches are rebuilt.
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'rewrite_rules', 'options' );
		}
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
		delete_option( 'artiey_aet_flush' );
		update_option( 'artiey_aet_rewrite_version', self::ROUTE_SCHEMA_VERSION, false );
		self::$routes_refreshed_this_request = true;
		self::purge_public_url_caches();
		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'artiey_aet_routes_cache_purged_notice', 1, DAY_IN_SECONDS );
		}
		return true;
	}

	/** True when the shared rewrite table was rebuilt earlier in this request. */
	public static function routes_refreshed_this_request() {
		return (bool) self::$routes_refreshed_this_request;
	}

	/** Tell administrators that plugin and WordPress URL caches were rebuilt. */
	public static function route_repair_notice() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' )
			|| ! function_exists( 'get_transient' ) || ! get_transient( 'artiey_aet_routes_cache_purged_notice' ) ) {
			return;
		}
		delete_transient( 'artiey_aet_routes_cache_purged_notice' );
		$permalink_url = function_exists( 'admin_url' ) ? admin_url( 'options-permalink.php' ) : '';
		$message = 'مسیرهای وردپرس و کش داخلی URLهای پلتفرم آرتی بازسازی و پاک‌سازی شدند. اگر هنوز آدرس قدیمی ۴۰۴ می‌دهد، کش افزونهٔ کش، هاست و CDN (مانند Cloudflare) را نیز پاک کنید.';
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $message );
		if ( $permalink_url ) {
			echo ' <a href="' . esc_url( $permalink_url ) . '">' . esc_html( 'تنظیمات پیوندهای یکتا' ) . '</a>';
		}
		echo '</p></div>';
	}

	/** Invalidate URL-related caches without flushing the whole site's object cache. */
	public static function purge_public_url_caches() {
		$url_generation = max( 1, absint( get_option( 'artiey_aet_url_cache_generation', 1 ) ) );
		update_option( 'artiey_aet_url_cache_generation', $url_generation + 1, false );
		if ( class_exists( 'Artiey_AET_Settings' ) ) {
			Artiey_AET_Settings::clear_cache();
		}
		if ( class_exists( 'Artiey_AET_Directory' ) ) {
			Artiey_AET_Directory::invalidate_options();
		}
		if ( class_exists( 'Artiey_AET_Statistics' ) ) {
			Artiey_AET_Statistics::invalidate();
		}
		if ( class_exists( 'Artiey_AET_Elementor_Options' ) ) {
			foreach ( Artiey_AET_Elementor_Options::TYPES as $type ) {
				Artiey_AET_Elementor_Options::invalidate( 0, $type );
			}
		}
		if ( class_exists( 'WP_COA_Pro_Person_Profiles' ) && method_exists( 'WP_COA_Pro_Person_Profiles', 'bust_public_cache' ) ) {
			WP_COA_Pro_Person_Profiles::bust_public_cache();
		}
		if ( class_exists( 'WP_COA_Pro_Galleries' ) && method_exists( 'WP_COA_Pro_Galleries', 'bust_public_cache' ) ) {
			WP_COA_Pro_Galleries::bust_public_cache();
		}
		if ( class_exists( '\\WPArtCOA\\Cache' ) ) {
			// flush_stats() also advances the catalogue/facet cache generation.
			\WPArtCOA\Cache::flush_stats();
		}

		// Drop cached post/page objects used to resolve the canonical archive and managed COA pages.
		if ( function_exists( 'clean_post_cache' ) ) {
			$managed_ids = get_option( 'wp_coa_pro_managed_page_ids', array() );
			if ( is_array( $managed_ids ) ) {
				foreach ( $managed_ids as $page_id ) {
					$page_id = absint( $page_id );
					if ( $page_id ) { clean_post_cache( $page_id ); }
				}
			}
			if ( function_exists( 'get_page_by_path' ) ) {
				$artist_archive = get_page_by_path( 'artists', OBJECT, 'page' );
				if ( $artist_archive && isset( $artist_archive->ID ) ) { clean_post_cache( (int) $artist_archive->ID ); }
			}
		}

		// Delete legacy transient rows left in wp_options. Generation bumps above
		// also invalidate persistent object-cache entries without a global cache flush.
		global $wpdb;
		if ( is_object( $wpdb ) && ! empty( $wpdb->options ) && method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'query' ) ) {
			$prefixes = array(
				'aet_dir_', 'aet_stats_', 'aetp_rel_', 'aetp_gallery_row_', 'aet_elementor_options_',
				'wp_coa_smi_', 'wp_coa_smp_', 'wp_coa_pp_', 'wp_coa_gal_', 'wp_coa_galdir_',
				'wp_coa_pro_public_stats_cache', 'art_coa_facets_',
			);
			foreach ( $prefixes as $prefix ) {
				$like = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( '_transient_' . $prefix ) . '%' : '_transient_' . $prefix . '%';
				$timeout_like = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%' : '_transient_timeout_' . $prefix . '%';
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout_like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}
		return true;
	}

	/** Persist a previous base so links using it can keep redirecting after a later settings change. */
	public static function remember_alias( $key, $value ) {
		if ( ! isset( self::BASES[ $key ] ) ) {
			return false;
		}
		$base = self::clean_base( $value, self::BASES[ $key ] );
		if ( '' === $base || $base === self::base( $key ) ) {
			return false;
		}
		$aliases = get_option( self::LEGACY_OPTION, array() );
		$aliases = is_array( $aliases ) ? $aliases : array();
		if ( ! isset( $aliases[ $key ] ) || ! is_array( $aliases[ $key ] ) ) {
			$aliases[ $key ] = array();
		}
		if ( ! in_array( $base, $aliases[ $key ], true ) ) {
			$aliases[ $key ][] = $base;
			update_option( self::LEGACY_OPTION, $aliases, false );
		}
		return true;
	}

	/** Register old singular bases as compatibility routes to the canonical CPT URL. */
	public static function register_legacy_rules() {
		// A single dispatcher owns /artists/{slug}/. It serves the canonical WordPress
		// artist post when available and falls back to a published COA artist profile
		// during/after legacy-record migration. The /artists/ page itself remains the archive.
		add_rewrite_rule( '^artists/([^/]+)/?$', 'index.php?artiey_artist_route=$matches[1]', 'top' );

		$pt_map = array(
			'gallery' => 'artiey_gallery',
			'exhibition' => 'artiey_exhibition',
			'artist' => 'artiey_artist',
			'artwork' => 'artiey_artwork',
		);
		foreach ( $pt_map as $key => $post_type ) {
			$canonical = self::base( $key );
			foreach ( self::legacy_bases( $key ) as $legacy ) {
				if ( $legacy === $canonical ) {
					continue;
				}
				// This path is also the legacy COA person-profile endpoint. Let its
				// exact-slug bridge decide whether to redirect to a canonical artist.
				if ( 'artist' === $key && 'art-person-profile' === trim( $legacy, '/' ) ) {
					continue;
				}
				$pattern = '^' . preg_quote( trim( $legacy, '/' ), '#' ) . '/([^/]+)/?$';
				add_rewrite_rule( $pattern, 'index.php?post_type=' . $post_type . '&name=$matches[1]&artiey_legacy_base=1', 'top' );
			}
		}
		// Preserve old singular archive routes as 301 aliases to the configured archive URL.
		foreach ( array( 'gallery' => 'artiey_gallery', 'exhibition' => 'artiey_exhibition', 'artwork' => 'artiey_artwork' ) as $key => $post_type ) {
			foreach ( self::legacy_bases( $key ) as $legacy ) {
				add_rewrite_rule( '^' . preg_quote( trim( $legacy, '/' ), '#' ) . '/?$', 'index.php?post_type=' . $post_type . '&artiey_legacy_base=archive-' . $key, 'top' );
			}
		}
		// The content archive is a managed WordPress page, not a CPT archive. Keep old base roots as 301 aliases.
		foreach ( self::legacy_bases( 'artist' ) as $legacy ) {
			if ( 'art-person-profile' === trim( $legacy, '/' ) ) { continue; } // COA legacy profile route has priority here.
			add_rewrite_rule( '^' . preg_quote( trim( $legacy, '/' ), '#' ) . '/?$', 'index.php?pagename=artists&artiey_legacy_base=archive-artist', 'top' );
		}
		// Taxonomy routes use the same plural convention; old term URLs are redirected, never silently orphaned.
		$style_canonical = self::base( 'style' );
		foreach ( self::legacy_bases( 'style' ) as $legacy ) {
			if ( $legacy === $style_canonical ) {
				continue;
			}
			$pattern = '^' . preg_quote( trim( $legacy, '/' ), '#' ) . '/([^/]+)/?$';
			add_rewrite_rule( $pattern, 'index.php?artiey_style=$matches[1]&artiey_legacy_base=style', 'top' );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = 'artiey_legacy_base';
		$vars[] = 'artiey_artist_route';
		return $vars;
	}

	/**
	 * Resolve the canonical /artists/{slug}/ namespace without fuzzy name matching.
	 * WordPress CPT content wins; published legacy profile records are compatibility
	 * fallbacks until they are adopted by the canonical content bridge.
	 */
	public static function resolve_artist_route( $query_vars ) {
		if ( ! is_array( $query_vars ) || ! isset( $query_vars['artiey_artist_route'] ) ) {
			return $query_vars;
		}
		$raw = is_scalar( $query_vars['artiey_artist_route'] ) ? rawurldecode( (string) $query_vars['artiey_artist_route'] ) : '';
		$slug = sanitize_title( $raw );
		unset( $query_vars['artiey_artist_route'] );
		if ( '' === $slug ) {
			$query_vars['error'] = '404';
			return $query_vars;
		}

		global $wpdb;
		$post_id = absint( $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_name = %s LIMIT 1",
			'artiey_artist', 'publish', $slug
		) ) );
		if ( $post_id ) {
			// Return only query vars needed for the native single-CPT template and Elementor Theme Builder.
			unset( $query_vars['pagename'], $query_vars['page_id'], $query_vars['name'] );
			$query_vars['post_type'] = 'artiey_artist';
			$query_vars['name'] = $slug;
			return $query_vars;
		}

		// Compatibility for published structured profiles stored in the older WP COA profile table.
		if ( class_exists( 'WP_COA_Pro_Person_Profiles' ) ) {
			$profile = WP_COA_Pro_Person_Profiles::get_by_slug( $slug, 'artist' );
			if ( $profile && isset( $profile->status ) && 'published' === $profile->status ) {
				$ids = get_option( 'wp_coa_pro_managed_page_ids', array() );
				$page_id = is_array( $ids ) ? absint( $ids['profile_view'] ?? 0 ) : 0;
				$page = $page_id ? get_post( $page_id ) : null;
				$page_is_managed = $page
					&& isset( $page->ID, $page->post_type, $page->post_status )
					&& 'page' === $page->post_type
					&& 'publish' === $page->post_status
					&& 'profile_view' === get_post_meta( (int) $page->ID, '_wp_coa_pro_managed_page', true );
				if ( ! $page_is_managed && function_exists( 'get_page_by_path' ) ) {
					$page = get_page_by_path( 'art-person-profile', OBJECT, 'page' );
					if ( ! $page || get_post_meta( (int) $page->ID, '_wp_coa_pro_managed_page', true ) !== 'profile_view' ) { $page = null; }
				}
				if ( $page && isset( $page->ID, $page->post_type, $page->post_status )
					&& 'page' === $page->post_type && 'publish' === $page->post_status
					&& 'profile_view' === get_post_meta( (int) $page->ID, '_wp_coa_pro_managed_page', true ) ) {
					$page_id = absint( $page->ID );
					$path = get_page_uri( $page_id );
					if ( is_string( $path ) && '' !== $path ) {
						unset( $query_vars['name'], $query_vars['page_id'], $query_vars['post_type'] );
						$query_vars['pagename'] = trim( $path, '/' );
						$query_vars['coa_profile_slug'] = $slug;
						$query_vars['coa_profile_type'] = 'artist';
						return $query_vars;
					}
				}
			}
		}

		// Final compatibility layer for Art COA relational records not yet mirrored to a CPT.
		if ( class_exists( '\\WPArtCOA\\Database' ) ) {
			$table = \WPArtCOA\Database::table( 'artists' );
			$legacy_id = absint( $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE slug = %s AND status = %s LIMIT 1",
				$slug, 'active'
			) ) );
			if ( $legacy_id ) {
				unset( $query_vars['name'], $query_vars['page_id'], $query_vars['post_type'], $query_vars['pagename'] );
				$query_vars['art_coa_type'] = 'artist';
				$query_vars['art_coa_slug'] = $slug;
				return $query_vars;
			}
		}

		$query_vars['error'] = '404';
		return $query_vars;
	}

	public static function redirect_legacy_base() {
		$legacy = (string) get_query_var( 'artiey_legacy_base' );
		if ( '' === $legacy ) {
			return;
		}
		if ( 0 === strpos( $legacy, 'archive-' ) ) {
			$key = substr( $legacy, 8 );
			$archives = array( 'gallery' => 'artiey_gallery', 'exhibition' => 'artiey_exhibition', 'artwork' => 'artiey_artwork' );
			if ( 'artist' === $key ) {
				$target = class_exists( '\\WPArtCOA\\ContentSync' ) ? \WPArtCOA\ContentSync::artist_archive_url() : home_url( '/artists/' );
				if ( $target ) { wp_safe_redirect( $target, 301, 'Artiey Art Platform' ); exit; }
				return;
			}
			if ( isset( $archives[ $key ] ) ) {
				$target = get_post_type_archive_link( $archives[ $key ] );
				if ( $target && ! is_wp_error( $target ) ) {
					wp_safe_redirect( $target, 301, 'Artiey Art Platform' );
					exit;
				}
			}
			return;
		}
		$object = get_queried_object();
		if ( '1' === $legacy && 'artiey_artist' === (string) get_query_var( 'post_type' ) ) {
			$legacy_name = get_query_var( 'name' );
			$legacy_slug = is_scalar( $legacy_name ) ? sanitize_title( (string) $legacy_name ) : '';
			if ( '' !== $legacy_slug && class_exists( '\\WPArtCOA\\ContentSync' ) ) {
				$target = \WPArtCOA\ContentSync::artist_url( $legacy_slug );
				if ( $target ) { wp_safe_redirect( $target, 301, 'Artiey Art Platform' ); exit; }
			}
		}
		if ( $object instanceof WP_Term ) {
			$target = get_term_link( $object );
		} elseif ( $object instanceof WP_Post && in_array( $object->post_type, array( 'artiey_artist', 'artiey_gallery', 'artiey_artwork', 'artiey_exhibition' ), true ) ) {
			$target = get_permalink( $object );
		} else {
			return;
		}
		if ( $target && ! is_wp_error( $target ) ) {
			wp_safe_redirect( $target, 301, 'Artiey Art Platform' );
			exit;
		}
	}

	/** @return string[] */
	private static function legacy_bases( $key ) {
		$values = array( self::LEGACY_BASES[ $key ] ?? '' );
		$saved = get_option( self::LEGACY_OPTION, array() );
		if ( is_array( $saved ) && isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) {
			$values = array_merge( $values, $saved[ $key ] );
		}
		$canonical = self::base( $key );
		$values = array_map( static function ( $v ) use ( $canonical ) { return self::clean_base( $v, $canonical ); }, $values );
		return array_values( array_unique( array_filter( $values, static function ( $v ) use ( $canonical ) { return '' !== $v && $v !== $canonical; } ) ) );
	}

	/** Transliterate Persian titles before WordPress checks slug uniqueness. */
	public static function filter_post_data( $data, $postarr, $unsanitized_postarr, $update ) {
		$types = array( 'post', 'page', 'artiey_gallery', 'artiey_exhibition', 'artiey_artist', 'artiey_artwork' );
		if ( empty( $data['post_type'] ) || ! in_array( $data['post_type'], $types, true ) ) {
			return $data;
		}
		$arabic = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u';
		$title  = (string) ( $unsanitized_postarr['post_title'] ?? $postarr['post_title'] ?? '' );

		$source = trim( (string) ( $unsanitized_postarr['post_name'] ?? '' ) );
		if ( '' === $source ) {
			// WordPress derives the slug from the title when it is empty; the Block editor/REST API also send it percent-encoded.
			$source = trim( (string) ( $data['post_name'] ?? '' ) );
		}
		if ( preg_match( '/%[0-9a-f]{2}/i', $source ) ) {
			$source = rawurldecode( $source );
		}
		if ( ! $update && '' === trim( (string) ( $unsanitized_postarr['post_name'] ?? '' ) ) && preg_match( $arabic, $title ) ) {
			$source = $title; // new posts: build the slug from the title
		}
		if ( '' === $source || ! preg_match( $arabic, $source ) ) {
			return $data;
		}

		// On update only react when the slug really changed, so re-saving a post never silently moves its URL.
		$post_id = (int) ( $postarr['ID'] ?? 0 );
		if ( $update && $post_id ) {
			$stored = (string) get_post_field( 'post_name', $post_id );
			if ( '' !== $stored && ( $stored === (string) ( $data['post_name'] ?? '' ) || rawurldecode( $stored ) === $source ) ) {
				return $data;
			}
		}

		$slug = self::from( $source, '' );
		if ( '' === $slug || $slug === $source ) {
			return $data;
		}

		$data['post_name'] = wp_unique_post_slug(
			$slug,
			$post_id,
			$data['post_status'],
			$data['post_type'],
			(int) ( $data['post_parent'] ?? 0 )
		);
		return $data;
	}

	/** Same transliteration for term slugs (art styles): typed, percent-encoded or derived from the name. */
	public static function filter_term_data( $data, $arg2 = 0, $arg3 = '', $arg4 = array() ) {
		$term_id  = is_numeric( $arg2 ) ? (int) $arg2 : 0;           // wp_update_term_data( $data, $term_id, $taxonomy, $args )
		$taxonomy = is_string( $arg2 ) ? $arg2 : (string) $arg3;      // wp_insert_term_data( $data, $taxonomy, $args )
		if ( 'artiey_style' !== $taxonomy || ! is_array( $data ) ) {
			return $data;
		}
		$source = rawurldecode( trim( (string) ( $data['slug'] ?? '' ) ) );
		if ( '' === $source ) {
			$source = (string) ( $data['name'] ?? '' );
		}
		if ( ! preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $source ) ) {
			return $data;
		}
		if ( $term_id ) {
			$old = get_term( $term_id, $taxonomy );
			if ( $old && ! is_wp_error( $old ) && ( $old->slug === (string) ( $data['slug'] ?? '' ) || rawurldecode( $old->slug ) === $source ) ) {
				return $data;
			}
		}
		$slug = self::from( $source, '' );
		if ( '' === $slug ) {
			return $data;
		}
		$data['slug'] = function_exists( 'wp_unique_term_slug' )
			? wp_unique_term_slug( $slug, (object) array( 'taxonomy' => $taxonomy, 'term_id' => $term_id, 'parent' => (int) ( $data['parent'] ?? 0 ) ) )
			: $slug;
		return $data;
	}
}
