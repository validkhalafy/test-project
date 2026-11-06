<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and repairs the public WordPress pages that host the platform's shortcodes.
 *
 * Rules that keep it safe on live sites:
 *  - a page that already shows the shortcode (classic content or Elementor) is adopted, never duplicated;
 *  - an unrelated page that merely has the same slug is never taken over;
 *  - nothing is deleted; trashed pages are restored by "repair";
 *  - galleries / exhibitions / artworks already have CPT archives, and /artists/ is managed by the COA module.
 */
final class Artiey_AET_Page_Builder {

	const SLUG          = 'artiey-aet-pages';
	const ACTION        = 'artiey_aet_pages';
	const MAP_OPTION    = 'artiey_aet_pages';
	const OVERRIDES     = 'artiey_aet_page_overrides';
	const VERSION_OPT   = 'artiey_aet_pages_version';
	const VERSION       = '2.32.23';
	const META_KEY      = '_artiey_aet_page';
	const STATUSES      = array( 'publish', 'draft', 'private', 'pending' );

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_auto_create' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/* ---------------------------------------------------------------- */
	/* Definitions                                                       */
	/* ---------------------------------------------------------------- */

	/**
	 * @return array<string,array{title:string,slug:string,tag:string,shortcode:string,desc:string,auto:bool,legacy?:string}>
	 */
	public static function definitions() {
		$defs = array(
			'verify'    => array( 'title' => 'تأیید گواهی اصالت', 'slug' => 'art-verify', 'tag' => 'art_coa_verify', 'shortcode' => '[art_coa_verify]', 'desc' => 'استعلام گواهی اصالت با کد یا QR', 'auto' => true, 'legacy' => 'wp_art_coa_page_verify' ),
			'register'  => array( 'title' => 'ثبت‌نام اعضا', 'slug' => 'art-register', 'tag' => 'art_coa_register', 'shortcode' => '[art_coa_register]', 'desc' => 'ثبت‌نام هنرمند، گالری، مجموعه‌دار و خریدار', 'auto' => true, 'legacy' => 'wp_art_coa_page_register' ),
			'member'    => array( 'title' => 'داشبورد اعضا', 'slug' => 'art-member', 'tag' => 'art_coa_member_dashboard', 'shortcode' => '[art_coa_member_dashboard]', 'desc' => 'پیشخوان شخصی هر عضو', 'auto' => true, 'legacy' => 'wp_art_coa_page_member' ),
			'catalog'   => array( 'title' => 'کاتالوگ آثار', 'slug' => 'art-catalog', 'tag' => 'art_coa_catalog', 'shortcode' => '[art_coa_catalog type="artworks" per_page="12" filters="1"]', 'desc' => 'کاتالوگ فیلترپذیر آثار', 'auto' => true ),
			'events'    => array( 'title' => 'رویدادهای هنری', 'slug' => 'art-events', 'tag' => 'art_coa_timeline', 'shortcode' => '[art_coa_timeline layout="cards" columns="3"]', 'desc' => 'تایم‌لاین نمایشگاه‌ها، حراج‌ها و فراخوان‌ها', 'auto' => true ),
			'calendar'  => array( 'title' => 'تقویم رویدادهای هنری', 'slug' => 'art-calendar', 'tag' => 'art_coa_calendar', 'shortcode' => '[art_coa_calendar]', 'desc' => 'تقویم ماهانهٔ شمسی', 'auto' => true ),
			'auctions'  => array( 'title' => 'حراج‌های هنری', 'slug' => 'art-auctions', 'tag' => 'art_coa_auction', 'shortcode' => '[art_coa_auction limit="24" columns="3"]', 'desc' => 'فهرست حراج‌های فعال و پیش‌رو', 'auto' => true ),
			'calls'     => array( 'title' => 'فراخوان‌های هنری', 'slug' => 'art-calls', 'tag' => 'art_coa_calls', 'shortcode' => '[art_coa_calls status="open" limit="20"]', 'desc' => 'فراخوان‌های باز', 'auto' => true ),
			'shop'      => array( 'title' => 'فروشگاه آثار', 'slug' => 'art-shop', 'tag' => 'art_coa_shop', 'shortcode' => '[art_coa_shop limit="24" columns="3"]', 'desc' => 'آثار دارای قیمت و درخواست خرید', 'auto' => true ),
			'secondary' => array( 'title' => 'بازار ثانویه', 'slug' => 'art-secondary-market', 'tag' => 'art_coa_secondary', 'shortcode' => '[art_coa_secondary limit="24" columns="3"]', 'desc' => 'آثار عرضه‌شده توسط مجموعه‌داران', 'auto' => false ),
			'stats'     => array( 'title' => 'آمار پلتفرم', 'slug' => 'art-stats', 'tag' => 'artiey_stats', 'shortcode' => '[artiey_stats]', 'desc' => 'شمارندهٔ گالری‌ها، هنرمندان، آثار و نمایشگاه‌ها', 'auto' => false ),
			'timeline'  => array( 'title' => 'تایم‌لاین نمایشگاه‌ها', 'slug' => 'art-exhibition-timeline', 'tag' => 'artiey_timeline', 'shortcode' => '[artiey_timeline]', 'desc' => 'نمای گانت نمایشگاه‌ها به تفکیک گالری', 'auto' => false ),
			'search'    => array( 'title' => 'جست‌وجوی هنری', 'slug' => 'art-search', 'tag' => 'art_coa_search', 'shortcode' => '[art_coa_search]', 'desc' => 'جست‌وجوی زندهٔ هنرمند، اثر و گالری', 'auto' => false ),
			'follows'   => array( 'title' => 'دنبال‌شده‌های من', 'slug' => 'art-my-follows', 'tag' => 'art_coa_my_follows', 'shortcode' => '[art_coa_my_follows]', 'desc' => 'فهرست هنرمندان و گالری‌های دنبال‌شده', 'auto' => false ),
			'profile'   => array( 'title' => 'ویرایش پروفایل', 'slug' => 'art-my-profile', 'tag' => 'art_coa_member_profile', 'shortcode' => '[art_coa_member_profile]', 'desc' => 'ویرایش اطلاعات پروفایل عضو (در داشبورد هم هست)', 'auto' => false ),
			'portfolio' => array( 'title' => 'ارسال نمونه‌کار', 'slug' => 'art-portfolio-upload', 'tag' => 'art_coa_artist_portfolio_upload', 'shortcode' => '[art_coa_artist_portfolio_upload]', 'desc' => 'فرم بارگذاری نمونه‌کار هنرمند', 'auto' => false ),
			'sitemap'   => array( 'title' => 'نقشهٔ سایت هنری', 'slug' => 'art-sitemap', 'tag' => 'art_coa_sitemap', 'shortcode' => '[art_coa_sitemap]', 'desc' => 'فهرست HTML همهٔ پروفایل‌ها', 'auto' => false ),
		);
		return apply_filters( 'artiey_aet_page_definitions', $defs );
	}

	/** Slugs that must never be used by a generated page because routes already live there. */
	public static function reserved_slugs() {
		$reserved = array( 'art-coa', 'wp-admin', 'wp-json', 'feed', 'page', 'attachment' );
		if ( class_exists( 'Artiey_AET_Slug' ) ) {
			foreach ( array( 'gallery', 'exhibition', 'artist', 'artwork', 'style' ) as $key ) {
				$reserved[] = trim( (string) Artiey_AET_Slug::base( $key ), '/' );
			}
		}
		return array_values( array_unique( array_filter( $reserved ) ) );
	}

	/** Clean a user-edited slug. Returns '' when it cannot be used. */
	public static function clean_slug( $slug, $fallback = '' ) {
		$slug = sanitize_title( (string) $slug );
		$slug = trim( $slug, '-' );
		if ( '' === $slug ) {
			$slug = (string) $fallback;
		}
		if ( in_array( $slug, self::reserved_slugs(), true ) ) {
			$slug .= '-page';
		}
		return $slug;
	}

	/** Definition merged with the administrator's saved title/slug overrides. */
	public static function resolved( $key ) {
		$defs = self::definitions();
		if ( ! isset( $defs[ $key ] ) ) {
			return null;
		}
		$def       = $defs[ $key ];
		$overrides = get_option( self::OVERRIDES, array() );
		$ov        = is_array( $overrides ) && isset( $overrides[ $key ] ) && is_array( $overrides[ $key ] ) ? $overrides[ $key ] : array();
		if ( ! empty( $ov['title'] ) ) {
			$def['title'] = sanitize_text_field( (string) $ov['title'] );
		}
		if ( ! empty( $ov['slug'] ) ) {
			$def['slug'] = self::clean_slug( $ov['slug'], $def['slug'] );
		}
		$def['slug'] = self::clean_slug( $def['slug'], 'art-page' );
		return $def;
	}

	/** Block-editor content: a heading-free wrapper that only carries the shortcode, so themes control the title. */
	public static function content_for( $def ) {
		return '<!-- wp:shortcode -->' . $def['shortcode'] . '<!-- /wp:shortcode -->';
	}

	/* ---------------------------------------------------------------- */
	/* Detection                                                         */
	/* ---------------------------------------------------------------- */

	private static function is_usable( $post ) {
		return is_object( $post ) && isset( $post->post_type, $post->post_status )
			&& 'page' === $post->post_type && in_array( $post->post_status, self::STATUSES, true );
	}

	/** Find the page that serves $key, or null. Never returns a page that does not show the shortcode or carry our marker. */
	public static function find( $key ) {
		$def = self::resolved( $key );
		if ( ! $def ) {
			return null;
		}
		$map = get_option( self::MAP_OPTION, array() );
		$map = is_array( $map ) ? $map : array();

		$candidates = array();
		if ( ! empty( $map[ $key ] ) ) {
			$candidates[] = absint( $map[ $key ] );
		}
		if ( ! empty( $def['legacy'] ) ) {
			$legacy_id = absint( get_option( $def['legacy'], 0 ) );
			if ( $legacy_id ) {
				$candidates[] = $legacy_id;
			}
		}
		foreach ( $candidates as $id ) {
			$post = $id ? get_post( $id ) : null;
			if ( self::is_usable( $post ) && ( get_post_meta( (int) $post->ID, self::META_KEY, true ) === $key || self::shows_shortcode( $post, $def['tag'] ) ) ) {
				return $post;
			}
		}

		$marked = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => self::STATUSES,
			'posts_per_page' => 1,
			'meta_key'       => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );
		if ( ! empty( $marked ) && self::is_usable( $marked[0] ) ) {
			return $marked[0];
		}

		$by_shortcode = self::find_by_shortcode( $def['tag'] );
		return $by_shortcode ? $by_shortcode : null;
	}

	/** Does the page show this shortcode (classic content or Elementor data)? */
	public static function shows_shortcode( $post, $tag ) {
		$needle = '[' . $tag;
		if ( false !== strpos( (string) $post->post_content, $needle ) ) {
			return true;
		}
		$data = get_post_meta( (int) $post->ID, '_elementor_data', true );
		return is_string( $data ) && false !== strpos( $data, $needle );
	}

	private static function find_by_shortcode( $tag ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'esc_like' ) ) {
			return null;
		}
		$like     = '%' . $wpdb->esc_like( '[' . $tag ) . '%';
		$statuses = "'" . implode( "','", self::STATUSES ) . "'";
		$id       = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
			 WHERE p.post_type = 'page' AND p.post_status IN ({$statuses})
			   AND ( p.post_content LIKE %s OR m.meta_value LIKE %s )
			 ORDER BY p.ID ASC LIMIT 1",
			$like,
			$like
		) );
		$post = $id ? get_post( (int) $id ) : null;
		return self::is_usable( $post ) ? $post : null;
	}

	/** Status label for the admin table: ok | draft | trash | missing. */
	public static function status( $key ) {
		$post = self::find( $key );
		if ( $post ) {
			return 'publish' === $post->post_status ? 'ok' : 'draft';
		}
		$map = get_option( self::MAP_OPTION, array() );
		if ( is_array( $map ) && ! empty( $map[ $key ] ) ) {
			$post = get_post( absint( $map[ $key ] ) );
			if ( is_object( $post ) && 'trash' === ( $post->post_status ?? '' ) ) {
				return 'trash';
			}
		}
		return 'missing';
	}

	/* ---------------------------------------------------------------- */
	/* Create / repair                                                   */
	/* ---------------------------------------------------------------- */

	private static function remember( $key, $id ) {
		$map         = get_option( self::MAP_OPTION, array() );
		$map         = is_array( $map ) ? $map : array();
		$map[ $key ] = (int) $id;
		update_option( self::MAP_OPTION, $map, false );
		$def = self::definitions();
		if ( ! empty( $def[ $key ]['legacy'] ) ) {
			update_option( $def[ $key ]['legacy'], (int) $id );
		}
	}

	/**
	 * Make sure the page exists. Returns array( 'result' => created|existing|restored|skipped|error, 'id' => int ).
	 */
	public static function ensure( $key, $repair = false ) {
		$def = self::resolved( $key );
		if ( ! $def ) {
			return array( 'result' => 'error', 'id' => 0 );
		}

		$post = self::find( $key );
		if ( $post ) {
			self::remember( $key, $post->ID );
			if ( ! get_post_meta( (int) $post->ID, self::META_KEY, true ) ) {
				update_post_meta( (int) $post->ID, self::META_KEY, $key );
			}
			if ( $repair && 'publish' !== $post->post_status && 'private' !== $post->post_status ) {
				wp_update_post( array( 'ID' => (int) $post->ID, 'post_status' => 'publish' ) );
				return array( 'result' => 'restored', 'id' => (int) $post->ID );
			}
			return array( 'result' => 'existing', 'id' => (int) $post->ID );
		}

		// A page the administrator moved to the trash is respected: it is restored only on an explicit repair
		// and is never silently re-created by the automatic run.
		$map = get_option( self::MAP_OPTION, array() );
		if ( is_array( $map ) && ! empty( $map[ $key ] ) ) {
			$trashed = get_post( absint( $map[ $key ] ) );
			if ( is_object( $trashed ) && 'page' === ( $trashed->post_type ?? '' ) && 'trash' === ( $trashed->post_status ?? '' ) ) {
				if ( ! $repair ) {
					return array( 'result' => 'skipped', 'id' => (int) $trashed->ID );
				}
				if ( function_exists( 'wp_untrash_post' ) ) {
					wp_untrash_post( (int) $trashed->ID );
					wp_update_post( array( 'ID' => (int) $trashed->ID, 'post_status' => 'publish' ) );
					return array( 'result' => 'restored', 'id' => (int) $trashed->ID );
				}
			}
		}

		$id = wp_insert_post( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => $def['title'],
			'post_name'      => $def['slug'],
			'post_content'   => self::content_for( $def ),
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'meta_input'     => array( self::META_KEY => $key ),
		), true );
		if ( is_wp_error( $id ) || ! $id ) {
			return array( 'result' => 'error', 'id' => 0 );
		}
		self::remember( $key, (int) $id );
		return array( 'result' => 'created', 'id' => (int) $id );
	}

	/** Public URL of a generated page, or '' when it does not exist yet. */
	public static function url( $key ) {
		$post = self::find( $key );
		return ( $post && 'publish' === $post->post_status ) ? get_permalink( (int) $post->ID ) : '';
	}

	/** Create the essential pages once per release; never runs on the front end. */
	public static function maybe_auto_create() {
		if ( get_option( self::VERSION_OPT ) === self::VERSION || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( self::definitions() as $key => $def ) {
			if ( ! empty( $def['auto'] ) ) {
				self::ensure( $key, false );
			}
		}
		update_option( self::VERSION_OPT, self::VERSION, false );
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
	}

	/* ---------------------------------------------------------------- */
	/* Admin                                                             */
	/* ---------------------------------------------------------------- */

	public static function menu() {
		add_submenu_page( 'artiey-platform', 'ساخت صفحات', 'ساخت صفحات', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'artiey-aet' ) );
		}
		check_admin_referer( self::ACTION );

		$defs      = self::definitions();
		$posted    = isset( $_POST['pages'] ) && is_array( $_POST['pages'] ) ? wp_unslash( $_POST['pages'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$overrides = array();
		foreach ( $defs as $key => $def ) {
			$row = isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ? $posted[ $key ] : array();
			$ov  = array();
			if ( isset( $row['title'] ) && '' !== trim( (string) $row['title'] ) ) {
				$ov['title'] = sanitize_text_field( (string) $row['title'] );
			}
			if ( isset( $row['slug'] ) && '' !== trim( (string) $row['slug'] ) ) {
				$ov['slug'] = self::clean_slug( (string) $row['slug'], $def['slug'] );
			}
			if ( $ov ) {
				$overrides[ $key ] = $ov;
			}
		}
		update_option( self::OVERRIDES, $overrides, false );

		$do     = isset( $_POST['do'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['do'] ) ) : 'save'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$counts = array( 'created' => 0, 'existing' => 0, 'restored' => 0, 'skipped' => 0, 'error' => 0 );
		$target = array();
		if ( 'create_all' === $do ) {
			$target = array_keys( $defs );
		} elseif ( 0 === strpos( $do, 'one:' ) || 0 === strpos( $do, 'repair:' ) ) {
			list( , $key ) = explode( ':', $do, 2 );
			if ( isset( $defs[ $key ] ) ) {
				$target = array( $key );
			}
		}
		$repair = ( 'create_all' === $do ) || 0 === strpos( $do, 'repair:' );
		foreach ( $target as $key ) {
			$res = self::ensure( $key, $repair );
			$counts[ $res['result'] ] = ( $counts[ $res['result'] ] ?? 0 ) + 1;
		}
		if ( $counts['created'] || $counts['restored'] ) {
			flush_rewrite_rules( false );
			if ( class_exists( 'Artiey_AET_Cache_Control' ) ) {
				Artiey_AET_Cache_Control::invalidate_content();
			}
		}
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => self::SLUG ), $counts ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$labels = array( 'ok' => 'منتشر شده', 'draft' => 'پیش‌نویس / خصوصی', 'trash' => 'در زباله‌دان', 'missing' => 'ساخته نشده' );
		?>
		<div class="wrap" dir="rtl">
			<h1>ساخت و ترمیم صفحات پلتفرم</h1>
			<?php
			if ( isset( $_GET['created'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				printf(
					'<div class="notice notice-success"><p>ساخته‌شده: %1$d — از قبل موجود: %2$d — بازیابی‌شده: %3$d%4$s</p></div>',
					absint( $_GET['created'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					absint( $_GET['existing'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					absint( $_GET['restored'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					absint( $_GET['error'] ?? 0 ) ? ' — خطا: ' . absint( $_GET['error'] ) : '' // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				);
			}
			?>
			<p>هر صفحه فقط شورت‌کد مربوط را دارد؛ عنوان و ظاهر را قالب یا Elementor مدیریت می‌کند. اگر صفحه‌ای با همان شورت‌کد از قبل دارید، همان استفاده می‌شود و صفحهٔ تکراری ساخته نمی‌شود. گالری‌ها، نمایشگاه‌ها و آثار صفحهٔ آرشیو خودشان را دارند و <code>/artists/</code> را ماژول گواهی مدیریت می‌کند.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<table class="widefat striped">
					<thead><tr><th>صفحه</th><th>عنوان</th><th>نامک</th><th>شورت‌کد</th><th>وضعیت</th><th>عملیات</th></tr></thead>
					<tbody>
					<?php foreach ( self::definitions() as $key => $_def ) :
						$def    = self::resolved( $key );
						$status = self::status( $key );
						$post   = self::find( $key );
						?>
						<tr>
							<td><strong><?php echo esc_html( $_def['title'] ); ?></strong><br><span class="description"><?php echo esc_html( $def['desc'] ); ?></span></td>
							<td><input type="text" class="regular-text" name="pages[<?php echo esc_attr( $key ); ?>][title]" value="<?php echo esc_attr( $def['title'] ); ?>"></td>
							<td><input type="text" dir="ltr" class="regular-text" name="pages[<?php echo esc_attr( $key ); ?>][slug]" value="<?php echo esc_attr( $def['slug'] ); ?>"></td>
							<td><code dir="ltr"><?php echo esc_html( $def['shortcode'] ); ?></code></td>
							<td><?php echo esc_html( $labels[ $status ] ); ?></td>
							<td>
								<?php if ( $post ) : ?>
									<a href="<?php echo esc_url( get_permalink( (int) $post->ID ) ); ?>" target="_blank" rel="noopener">مشاهده</a> |
									<a href="<?php echo esc_url( get_edit_post_link( (int) $post->ID ) ); ?>">ویرایش</a>
									<?php if ( 'ok' !== $status ) : ?>
										| <button class="button-link" name="do" value="repair:<?php echo esc_attr( $key ); ?>">ترمیم</button>
									<?php endif; ?>
								<?php elseif ( 'trash' === $status ) : ?>
									<button class="button-link" name="do" value="repair:<?php echo esc_attr( $key ); ?>">بازیابی</button>
								<?php else : ?>
									<button class="button" name="do" value="one:<?php echo esc_attr( $key ); ?>">ساخت صفحه</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button class="button button-primary" name="do" value="create_all">ساخت / ترمیم همهٔ صفحات</button>
					<button class="button" name="do" value="save">فقط ذخیرهٔ عنوان‌ها و نامک‌ها</button>
				</p>
				<p class="description">تغییر عنوان یا نامک روی صفحه‌های جدید اعمال می‌شود؛ نام صفحه‌های موجود را از ویرایشگر وردپرس تغییر دهید.</p>
			</form>
		</div>
		<?php
	}
}
