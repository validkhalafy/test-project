<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache hygiene for the whole platform:
 *  - keeps the two modules' caches in step (a change on the COA side also refreshes the platform side);
 *  - tells full-page caches not to store pages that carry per-visitor forms, nonces or live bids;
 *  - gives administrators one safe "rebuild routes and clear caches" action.
 */
final class Artiey_AET_Cache_Control {

	const SLUG   = 'artiey-aet-cache';
	const ACTION = 'artiey_aet_clear_cache';

	/** Shortcodes whose output depends on the visitor (nonces, sessions, live auction state). */
	const DYNAMIC_SHORTCODES = array(
		'art_coa_register',
		'art_coa_member_dashboard',
		'art_coa_member_profile',
		'art_coa_my_follows',
		'art_coa_follow_button',
		'art_coa_artist_portfolio_upload',
		'art_coa_shop',
		'art_coa_secondary',
		'art_coa_auction',
		'art_coa_calls',
	);

	/** Events that change public listings or counters on either module. */
	const CONTENT_EVENTS = array(
		'art_coa_artwork_changed',
		'art_coa_artwork_sold',
		'art_coa_auction_closed',
		'wp_coa_pro_certificate_issued',
		'wp_coa_pro_certificate_revoked',
		'wp_coa_pro_certificate_transferred',
		'wp_art_coa_certificate_issued',
	);

	private static $content_invalidated = false;
	private static $marked              = false;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_mark_page' ), 0 );
		// Purge only affected URLs on entity/page edits; never flush the whole cache per save.
		add_action( 'save_post', array( __CLASS__, 'on_post_saved' ), 100, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_deleted' ), 100, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_post_status_changed' ), 100, 3 );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'on_shortcode' ), 10, 2 );
		foreach ( self::CONTENT_EVENTS as $event ) {
			add_action( $event, array( __CLASS__, 'invalidate_content' ), 20, 0 );
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 21 );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_clear' ) );
	}

	/* ---------------------------------------------------------------- */
	/* Pure helpers (covered by tests)                                   */
	/* ---------------------------------------------------------------- */

	/** True when the text contains at least one visitor-dependent shortcode. */
	public static function content_has_dynamic_shortcode( $content ) {
		$content = (string) $content;
		if ( '' === $content || false === strpos( $content, '[' ) ) {
			return false;
		}
		foreach ( self::DYNAMIC_SHORTCODES as $tag ) {
			if ( preg_match( '/\[' . preg_quote( $tag, '/' ) . '(?=[\s\]\/])/', $content ) ) {
				return true;
			}
		}
		return false;
	}

	public static function is_dynamic_tag( $tag ) {
		return in_array( (string) $tag, self::DYNAMIC_SHORTCODES, true );
	}

	/* ---------------------------------------------------------------- */
	/* Page-cache exclusion                                              */
	/* ---------------------------------------------------------------- */

	/** Tell page-cache plugins, hosts and CDNs not to store the current response. */
	public static function mark_uncacheable() {
		if ( self::$marked ) {
			return;
		}
		self::$marked = true;
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() && function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
	}

	public static function maybe_mark_page() {
		if ( is_admin() || ! function_exists( 'is_singular' ) || ! is_singular() ) {
			return;
		}
		$post = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
		if ( ! is_object( $post ) || empty( $post->post_content ) ) {
			return;
		}
		if ( self::content_has_dynamic_shortcode( $post->post_content ) ) {
			self::mark_uncacheable();
		}
	}

	/** Covers Elementor shortcode widgets and dynamic tags, which are not visible in post_content. */
	public static function on_shortcode( $output, $tag ) {
		if ( ! is_admin() && self::is_dynamic_tag( $tag ) ) {
			self::mark_uncacheable();
		}
		return $output;
	}

	/* ---------------------------------------------------------------- */
	/* Invalidation                                                      */
	/* ---------------------------------------------------------------- */

	/** Entity/page post types whose public URLs may be held by a full-page cache. */
	private static function is_cache_relevant_post( $post ) {
		return $post instanceof WP_Post && in_array( $post->post_type, array( 'page', 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' ), true );
	}

	public static function on_post_saved( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! self::is_cache_relevant_post( $post ) ) {
			return;
		}
		self::purge_post_caches( (int) $post_id );
	}

	public static function on_post_deleted( $post_id, $post = null ) {
		if ( ! $post && function_exists( 'get_post' ) ) {
			$post = get_post( $post_id );
		}
		if ( self::is_cache_relevant_post( $post ) ) {
			self::purge_post_caches( (int) $post_id );
		}
	}

	public static function on_post_status_changed( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status && self::is_cache_relevant_post( $post ) ) {
			self::purge_post_caches( (int) $post->ID );
		}
	}

	/** Purge one URL using supported plugin APIs; also expose a stable integration hook. */
	private static function purge_post_caches( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $post_id );
		}
		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $post_id );
		}
		if ( has_action( 'litespeed_purge_post' ) ) {
			do_action( 'litespeed_purge_post', $post_id );
		}
		do_action( 'artiey_aet_purge_post_cache', $post_id );
	}

	/** Refresh the platform-side listing caches after a COA-side change (once per request). */
	public static function invalidate_content() {
		if ( self::$content_invalidated ) {
			return;
		}
		self::$content_invalidated = true;
		if ( class_exists( 'Artiey_AET_Directory' ) ) {
			Artiey_AET_Directory::invalidate_options();
		}
		if ( class_exists( 'Artiey_AET_Statistics' ) ) {
			Artiey_AET_Statistics::invalidate();
		}
		if ( class_exists( 'Artiey_AET_Settings' ) ) {
			Artiey_AET_Settings::bump();
		}
	}

	/**
	 * Rebuild rewrite rules and drop every plugin-owned cache. Page caches of other plugins are asked to purge
	 * through their public APIs when they exist; nothing is called blindly.
	 *
	 * @return string[] human-readable list of what was done
	 */
	public static function clear_all() {
		$done = array();

		if ( class_exists( 'Artiey_AET_Slug' ) ) {
			update_option( 'artiey_aet_flush', 1, false );
			Artiey_AET_Slug::maybe_refresh_routes(); // also runs purge_public_url_caches()
			$done[] = 'بازسازی مسیرها (Rewrite) و پاک‌سازی کش URLها';
		}
		if ( class_exists( 'Artiey_AET_Settings' ) ) {
			Artiey_AET_Settings::bump();
		}
		self::invalidate_content();
		if ( class_exists( '\\WPArtCOA\\Cache' ) ) {
			\WPArtCOA\Cache::flush_stats();
		}
		$done[] = 'پاک‌سازی کش فهرست‌ها، آمار، تایم‌لاین و ماژول گواهی';

		global $wpdb;
		if ( is_object( $wpdb ) && ! empty( $wpdb->options ) && method_exists( $wpdb, 'esc_like' ) ) {
			$like         = $wpdb->esc_like( '_transient_aet_' ) . '%';
			$timeout_like = $wpdb->esc_like( '_transient_timeout_aet_' ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout_like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		if ( function_exists( 'delete_expired_transients' ) ) {
			delete_expired_transients( true );
		}
		if ( function_exists( 'wp_cache_supports' ) && function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'wp_art_coa' );
		}

		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) && method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
			$done[] = 'پاک‌سازی کش CSS المنتور';
		}

		$done = array_merge( $done, self::purge_page_caches() );
		do_action( 'artiey_aet_cache_cleared' );
		return $done;
	}

	/** Ask known page-cache plugins to purge. Returns the names that were actually called. */
	private static function purge_page_caches() {
		$done = array();
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$done[] = 'WP Rocket';
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$done[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$done[] = 'WP Super Cache';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			$done[] = 'SiteGround Optimizer';
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$done[] = 'LiteSpeed Cache';
		}
		if ( has_action( 'artiey_aet_purge_page_cache' ) ) {
			do_action( 'artiey_aet_purge_page_cache' );
			$done[] = 'قلاب سفارشی artiey_aet_purge_page_cache';
		}
		return $done ? array( 'درخواست پاک‌سازی کش صفحه: ' . implode( '، ', $done ) ) : array();
	}

	/* ---------------------------------------------------------------- */
	/* Admin screen                                                      */
	/* ---------------------------------------------------------------- */

	public static function menu() {
		add_submenu_page( 'artiey-platform', 'کش و مسیرها', 'کش و مسیرها', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'artiey-aet' ) );
		}
		check_admin_referer( self::ACTION );
		$done = self::clear_all();
		set_transient( 'artiey_aet_cache_result_' . get_current_user_id(), $done, 120 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'cleared' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$result = array();
		if ( isset( $_GET['cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$result = get_transient( 'artiey_aet_cache_result_' . get_current_user_id() );
			delete_transient( 'artiey_aet_cache_result_' . get_current_user_id() );
			$result = is_array( $result ) ? $result : array();
		}
		$generation = max( 1, absint( get_option( 'artiey_aet_url_cache_generation', 1 ) ) );
		$dir_ver    = max( 1, absint( get_option( 'artiey_directory_cache_version', 1 ) ) );
		$stats_ver  = max( 1, absint( get_option( 'artiey_stats_version', 1 ) ) );
		?>
		<div class="wrap" dir="rtl">
			<h1>کش و مسیرهای پلتفرم آرتی</h1>
			<?php if ( $result ) : ?>
				<div class="notice notice-success"><p><strong>انجام شد:</strong></p><ul style="list-style:disc;margin-inline-start:22px">
					<?php foreach ( $result as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul></div>
			<?php endif; ?>
			<p>اگر پس از تغییر نامک‌ها، افزودن صفحه یا به‌روزرسانی افزونه هنوز آدرس قدیمی ۴۰۴ می‌دهد یا فهرست‌ها قدیمی نمایش داده می‌شوند، این ابزار مسیرهای وردپرس را بازسازی و کش‌های داخلی افزونه را پاک می‌کند. محتوای سایت حذف نمی‌شود و ریدایرکت‌های ۳۰۱ آدرس‌های قدیمی حفظ می‌مانند.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<?php submit_button( 'بازسازی مسیرها و پاک‌سازی کش', 'primary', 'submit', false ); ?>
			</form>
			<h2>وضعیت کش</h2>
			<table class="widefat striped" style="max-width:640px">
				<tbody>
					<tr><th>نسخهٔ کش آدرس‌ها</th><td><?php echo esc_html( (string) $generation ); ?></td></tr>
					<tr><th>نسخهٔ کش فهرست‌ها</th><td><?php echo esc_html( (string) $dir_ver ); ?></td></tr>
					<tr><th>نسخهٔ کش آمار</th><td><?php echo esc_html( (string) $stats_ver ); ?></td></tr>
					<tr><th>نسخهٔ طرح مسیرها</th><td><?php echo esc_html( (string) get_option( 'artiey_aet_rewrite_version', '—' ) ); ?></td></tr>
				</tbody>
			</table>
			<h2>کش صفحه، هاست و CDN</h2>
			<p>صفحاتی که فرم، توکن امنیتی یا پیشنهاد زندهٔ حراج دارند (ثبت‌نام، داشبورد اعضا، فروشگاه، حراج، فراخوان و بازار ثانویه) به‌طور خودکار با <code>DONOTCACHEPAGE</code> و هدرهای no-cache علامت می‌خورند. کش هاست و CDN (مثل Cloudflare) را فقط از پنل خودشان می‌توانید پاک کنید؛ اگر افزونهٔ کش شناخته‌شده‌ای فعال باشد، درخواست پاک‌سازی برایش ارسال می‌شود.</p>
		</div>
		<?php
	}
}
