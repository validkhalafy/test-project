<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified admin: top-level "پلتفرم آرتی" menu with a dashboard (stats, shortcuts, health checks).
 * Core post types and the COA tools live under this single navigation root.
 */
final class Artiey_AET_Platform_Admin {

	const SLUG = 'artiey-platform';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		// Rebuild only this plugin's submenu after every bundled module has registered its pages.
		add_action( 'admin_menu', array( __CLASS__, 'organize_menu' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		self::$hook = add_menu_page(
			'پلتفرم آرتی',
			'پلتفرم آرتی',
			'edit_posts',
			self::SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-art',
			24
		);
		// Rename the auto-created first submenu item.
		add_submenu_page( self::SLUG, 'پیشخوان پلتفرم آرتی', 'پیشخوان', 'edit_posts', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function assets( $hook ) {
		// Keep the larger dashboard stylesheet restricted to the Artiey dashboard.
		// The navigation CSS/JS are intentionally tiny (about 8 KB combined) and must
		// remain available on every wp-admin screen so the Artiey submenu keeps the
		// same grouped/collapsible appearance on Plugins, Settings, Elementor, etc.
		$is_artiey_screen = 0 === strpos( (string) $hook, 'artiey-platform' );
		if ( ! $is_artiey_screen && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen ) {
				$post_type = isset( $screen->post_type ) ? (string) $screen->post_type : '';
				$taxonomy  = isset( $screen->taxonomy ) ? (string) $screen->taxonomy : '';
				$is_artiey_screen = 0 === strpos( $post_type, 'artiey_' ) || 0 === strpos( $taxonomy, 'artiey_' );
			}
		}

		// The dashboard stylesheet is needed only on the platform dashboard.
		if ( $is_artiey_screen && $hook === self::$hook ) {
			wp_enqueue_style( 'artiey-ap-admin', AET_URL . 'assets/css/platform-admin.css', array(), AET_VERSION );
		}

		// Navigation styling/accordion is deliberately separate from dashboard styles.
		// Load it on all admin screens to avoid a raw, ungrouped submenu on other pages.
		wp_enqueue_style( 'artiey-platform-menu', AET_URL . 'assets/css/platform-menu.css', array(), AET_VERSION );
		wp_enqueue_script( 'artiey-platform-menu', AET_URL . 'assets/js/platform-menu.js', array(), AET_VERSION, true );
		wp_localize_script(
			'artiey-platform-menu',
			'ArtieyPlatformMenu',
			array(
				'rootId'        => 'toplevel_page_' . self::SLUG,
				'sectionMap'    => self::client_section_map(),
				'sectionLabels' => self::section_labels(),
				'dashboardKey'  => 'page:' . self::SLUG,
			)
		);
	}

	/** Descriptive groups for the one and only Artiey sidebar navigation. */
	private static function section_definitions() {
		return array(
			'content' => array(
				'label' => 'محتوا و پروفایل‌ها',
				'items' => array(
					'post_type:artiey_gallery', 'new_post_type:artiey_gallery',
					'post_type:artiey_artist', 'new_post_type:artiey_artist',
					'post_type:artiey_artwork', 'new_post_type:artiey_artwork',
					'post_type:artiey_exhibition', 'new_post_type:artiey_exhibition',
					'taxonomy:artiey_style',
				),
			),
			'certificates' => array(
				'label' => 'سامانه گواهی اصالت',
				'items' => array(
					'page:wp-art-coa',
					'page:wp-coa-pro',
					'page:wp-coa-pro-events',
					'page:wp-art-coa-certificates',
					'page:wp-coa-pro-galleries',
					'page:wp-coa-pro-people',
					'page:wp-art-coa-curators',
					'page:wp-art-coa-collectors',
				),
			),
			'market' => array(
				'label' => 'بازار و فرصت‌های هنری',
				'items' => array( 'page:wp-art-coa-auctions', 'page:wp-art-coa-calls' ),
			),
			'settings' => array(
				'label' => 'تنظیمات و ابزارها',
				'items' => array(
					'page:artiey-aet-settings',
					'page:artiey-elementor-layouts',
					'page:wp-art-coa-settings',
					'page:wp-coa-pro-settings',
					'page:wp-coa-pro-pages',
					'page:artiey-aet-pages',
					'page:artiey-aet-appearance',
					'page:artiey-aet-cache',
				),
			),
			'other' => array(
				'label' => 'سایر ابزارهای آرتی',
				'items' => array(),
			),
		);
	}

	private static function section_labels() {
		$labels = array();
		foreach ( self::section_definitions() as $key => $section ) {
			$labels[ $key ] = $section['label'];
		}
		return $labels;
	}

	/** Convert a WordPress submenu slug into a stable identifier used for ordering. */
	private static function menu_key( $slug ) {
		$slug = html_entity_decode( trim( (string) $slug ), ENT_QUOTES, 'UTF-8' );
		if ( '' === $slug ) {
			return '';
		}
		$path  = (string) wp_parse_url( $slug, PHP_URL_PATH );
		$query = (string) wp_parse_url( $slug, PHP_URL_QUERY );
		$args  = array();
		if ( '' !== $query ) {
			parse_str( $query, $args );
		}
		if ( isset( $args['page'] ) && '' !== (string) $args['page'] ) {
			return 'page:' . sanitize_key( (string) $args['page'] );
		}
		if ( false === strpos( $slug, '?' ) && false === strpos( $slug, '/' ) && false === strpos( $slug, '.' ) ) {
			return 'page:' . sanitize_key( $slug );
		}
		$filename = basename( $path );
		if ( 'edit.php' === $filename && ! empty( $args['post_type'] ) ) {
			return 'post_type:' . sanitize_key( (string) $args['post_type'] );
		}
		if ( 'post-new.php' === $filename && ! empty( $args['post_type'] ) ) {
			return 'new_post_type:' . sanitize_key( (string) $args['post_type'] );
		}
		if ( isset( $args['taxonomy'] ) && '' !== (string) $args['taxonomy'] ) {
			return 'taxonomy:' . sanitize_key( (string) $args['taxonomy'] );
		}
		ksort( $args );
		return 'url:' . ltrim( $path, '/' ) . ( $args ? '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) : '' );
	}

	/** Normalize only actual destinations; separate list and add-new pages remain distinct. */
	private static function menu_identity( $slug ) {
		$key = self::menu_key( $slug );
		if ( 0 === strpos( $key, 'page:' ) ) {
			return $key;
		}
		$slug = html_entity_decode( trim( (string) $slug ), ENT_QUOTES, 'UTF-8' );
		$path = (string) wp_parse_url( $slug, PHP_URL_PATH );
		$args = array();
		$query = (string) wp_parse_url( $slug, PHP_URL_QUERY );
		if ( '' !== $query ) {
			parse_str( $query, $args );
		}
		if ( isset( $args['page'] ) ) {
			return 'page:' . sanitize_key( (string) $args['page'] );
		}
		if ( 'edit-tags.php' === basename( $path ) && ! empty( $args['taxonomy'] ) ) {
			// A shared taxonomy should have one visible menu entry, not one per post type.
			return 'taxonomy:' . sanitize_key( (string) $args['taxonomy'] );
		}
		ksort( $args );
		return 'url:' . ltrim( $path, '/' ) . ( $args ? '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) : '' );
	}

	private static function section_for_key( $key ) {
		if ( 'page:' . self::SLUG === $key ) {
			return 'dashboard';
		}
		foreach ( self::section_definitions() as $section_key => $section ) {
			if ( in_array( $key, $section['items'], true ) ) {
				return $section_key;
			}
		}
		if ( 0 === strpos( $key, 'post_type:' ) || 0 === strpos( $key, 'new_post_type:' ) || 0 === strpos( $key, 'taxonomy:' ) ) {
			return 'content';
		}
		if ( 0 === strpos( $key, 'page:wp-art-coa-' ) || 0 === strpos( $key, 'page:wp-coa-pro' ) ) {
			return 'certificates';
		}
		return 'other';
	}

	private static function item_labels() {
		return array(
			'page:' . self::SLUG              => 'پیشخوان',
			'post_type:artiey_gallery'       => 'گالری‌ها',
			'post_type:artiey_artist'        => 'هنرمندان',
			'post_type:artiey_artwork'       => 'آثار هنری',
			'post_type:artiey_exhibition'    => 'نمایشگاه‌ها',
			'taxonomy:artiey_style'          => 'سبک‌های هنری',
			'page:wp-art-coa'                => 'داشبورد گواهی اصالت',
			'page:wp-coa-pro'                => 'صدور و مدیریت گواهی‌ها',
			'page:wp-coa-pro-events'         => 'تاریخچه رویدادهای گواهی',
			'page:wp-art-coa-certificates'   => 'گواهی‌های ثبت‌شده در Art COA',
			'page:wp-coa-pro-galleries'      => 'پروفایل صادرکنندگان گواهی',
			'page:wp-coa-pro-people'         => 'پروفایل رسمی هنرمندان و کلکتورها',
			'page:wp-art-coa-curators'       => 'کیوریتورها',
			'page:wp-art-coa-collectors'     => 'مجموعه‌داران',
			'page:wp-art-coa-auctions'       => 'حراج آنلاین',
			'page:wp-art-coa-calls'          => 'فراخوان‌ها و رویدادهای هنری',
			'page:artiey-aet-settings'       => 'تنظیمات پلتفرم',
			'page:artiey-elementor-layouts' => 'قالب‌های صفحات Elementor',
			'page:wp-art-coa-settings'       => 'تنظیمات Art COA',
			'page:wp-coa-pro-settings'       => 'تنظیمات صدور گواهی',
			'page:wp-coa-pro-pages'          => 'برگه‌ها و ابزارک‌های Elementor',
		);
	}

	/**
	 * Remove repeated destinations and rebuild only the Artiey submenu in groups.
	 * Pages with similar names but different destinations are preserved and renamed clearly.
	 */
	public static function organize_menu() {
		global $submenu;
		if ( ! isset( $submenu[ self::SLUG ] ) || ! is_array( $submenu[ self::SLUG ] ) ) {
			return;
		}

		$items = array();
		$seen  = array();
		foreach ( array_values( $submenu[ self::SLUG ] ) as $index => $item ) {
			if ( ! is_array( $item ) || ! isset( $item[2] ) ) {
				continue;
			}
			$identity = self::menu_identity( $item[2] );
			if ( '' === $identity ) {
				$identity = 'unknown:' . $index;
			}
			if ( isset( $seen[ $identity ] ) ) {
				$existing_index = $seen[ $identity ];
				$existing_slug  = (string) $items[ $existing_index ]['item'][2];
				$current_slug  = (string) $item[2];
				// Prefer the real page slug over an accidental "admin.php?page=..." submenu slug.
				$existing_malformed = 0 === strpos( $existing_slug, 'admin.php?page=' );
				$current_malformed  = 0 === strpos( $current_slug, 'admin.php?page=' );
				if ( $existing_malformed && ! $current_malformed ) {
					$items[ $existing_index ]['item'] = $item;
					$items[ $existing_index ]['key']  = self::menu_key( $current_slug );
				}
				continue;
			}
			$seen[ $identity ] = count( $items );
			$items[] = array(
				'item'     => $item,
				'key'      => self::menu_key( $item[2] ),
				'identity' => $identity,
			);
		}

		$ordered = array();
		$used    = array();
		// Dashboard is always the first and only landing entry.
		foreach ( $items as $i => $info ) {
			if ( 'page:' . self::SLUG === $info['key'] ) {
				$ordered[] = $info;
				$used[ $i ] = true;
				break;
			}
		}

		$definitions = self::section_definitions();
		foreach ( array( 'content', 'certificates', 'market', 'settings', 'other' ) as $section_key ) {
			$definition = $definitions[ $section_key ];
			// First follow a deliberate, stable order for familiar items.
			foreach ( $definition['items'] as $expected_key ) {
				foreach ( $items as $i => $info ) {
					if ( isset( $used[ $i ] ) || $expected_key !== $info['key'] ) {
						continue;
					}
					$ordered[] = $info;
					$used[ $i ] = true;
				}
			}
			// Preserve every remaining unique page and place it in its appropriate group.
			foreach ( $items as $i => $info ) {
				if ( isset( $used[ $i ] ) || self::section_for_key( $info['key'] ) !== $section_key ) {
					continue;
				}
				$ordered[] = $info;
				$used[ $i ] = true;
			}
		}
		// Last-resort preservation for any unfamiliar third-party page attached to the Artiey root.
		foreach ( $items as $i => $info ) {
			if ( ! isset( $used[ $i ] ) ) {
				$ordered[] = $info;
			}
		}

		$labels = self::item_labels();
		$final  = array();
		foreach ( $ordered as $info ) {
			$item = $info['item'];
			if ( isset( $labels[ $info['key'] ] ) ) {
				$item[0] = $labels[ $info['key'] ];
			}
			$final[] = $item;
		}
		$submenu[ self::SLUG ] = $final;
	}

	private static function client_section_map() {
		global $submenu;
		$map = array();
		if ( isset( $submenu[ self::SLUG ] ) && is_array( $submenu[ self::SLUG ] ) ) {
			foreach ( $submenu[ self::SLUG ] as $item ) {
				if ( ! is_array( $item ) || ! isset( $item[2] ) ) {
					continue;
				}
				$key = self::menu_key( $item[2] );
				$map[ $key ] = self::section_for_key( $key );
			}
		}
		return $map;
	}

	/* ---------------------------------------------------------------- */
	/* Data                                                              */
	/* ---------------------------------------------------------------- */

	private static function fa( $n ) {
		return Artiey_AET_Jalali::to_persian_digits( (string) (int) $n );
	}

	private static function published( $type ) {
		$c = wp_count_posts( $type );
		return $c && isset( $c->publish ) ? (int) $c->publish : 0;
	}

	private static function count_query( $type, $meta_query ) {
		$q = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => $meta_query,
			)
		);
		return (int) $q->found_posts;
	}

	private static function exhibition_counts() {
		$today = current_time( 'Y-m-d' );
		return array(
			'now'      => self::count_query(
				'artiey_exhibition',
				array(
					'relation' => 'AND',
					array( 'key' => '_ae_start', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ),
					array( 'key' => '_ae_end', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
				)
			),
			'upcoming' => self::count_query( 'artiey_exhibition', array( array( 'key' => '_ae_start', 'value' => $today, 'compare' => '>', 'type' => 'DATE' ) ) ),
			'past'     => self::count_query( 'artiey_exhibition', array( array( 'key' => '_ae_end', 'value' => $today, 'compare' => '<', 'type' => 'DATE' ) ) ),
		);
	}

	private static function health() {
		$items = array();
		$add   = function ( $level, $title, $hint = '', $link = '', $link_label = '' ) use ( &$items ) {
			$items[] = compact( 'level', 'title', 'hint', 'link', 'link_label' );
		};

		// Permalinks (needed for /coa-verify/CODE/ QR links and clean URLs).
		if ( get_option( 'permalink_structure' ) ) {
			$add( 'ok', 'پیوندهای یکتا فعال است' );
		} else {
			$add( 'warn', 'پیوندهای یکتا خاموش است', 'نشانی استعلام گواهی (QR) و آدرس‌های تمیز بدون پیوند یکتا به‌صورت query ساخته می‌شوند. ساختار «نام نوشته» را فعال کنید.', admin_url( 'options-permalink.php' ), 'تنظیمات پیوندهای یکتا' );
		}

		// Timezone (status "open now", exhibition today/upcoming).
		$tz = wp_timezone_string();
		if ( 'Asia/Tehran' === $tz ) {
			$add( 'ok', 'منطقه زمانی: Asia/Tehran' );
		} else {
			$add( 'warn', 'منطقه زمانی سایت Asia/Tehran نیست (' . $tz . ')', 'وضعیت «هم‌اکنون باز است» و نمایشگاه‌های جاری/آینده بر اساس منطقه زمانی سایت حساب می‌شوند.', admin_url( 'options-general.php' ), 'تنظیمات همگانی' );
		}

		// mbstring.
		if ( function_exists( 'mb_substr' ) && function_exists( 'mb_strpos' ) ) {
			$add( 'ok', 'افزونه PHP به نام mbstring فعال است' );
		} else {
			$add( 'info', 'mbstring در PHP فعال نیست؛ جایگزین UTF-8 فعال است', 'پردازش متون فارسی با جایگزین داخلی ادامه می‌یابد؛ فعال‌بودن mbstring برای کارایی و پوشش بهتر زبان‌ها توصیه می‌شود.' );
		}

		// COA signing key.
		if ( Artiey_AET_COA_Bridge::available() ) {
			if ( 'constant' === Artiey_AET_COA_Bridge::key_source() ) {
				$add( 'ok', 'کلید امضای گواهی‌ها در wp-config.php نگهداری می‌شود' );
			} else {
				$add( 'warn', 'کلید امضای گواهی‌ها در دیتابیس است', 'برای امنیت بیشتر، ثابت WP_COA_PRO_SIGNING_KEY را در wp-config.php تعریف کنید؛ اما فقط پیش از صدور گواهی جدید و با پشتیبان‌گیری از کلید فعلی (تغییر کلید، اعتبارسنجی گواهی‌های قبلی را مختل می‌کند).' );
			}
		}

		// Elementor.
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$add( 'ok', 'المنتور فعال است؛ ابزارک‌ها در دسته «پلتفرم آرتی» و «WP COA Pro» هستند' );
		} else {
			$add( 'info', 'المنتور فعال نیست', 'پلتفرم بدون المنتور هم کار می‌کند (شورت‌کد و صفحات خودکار).' );
		}

		// Canonical content/COA relationship health. Ambiguous rows are never auto-deleted or merged,
		// because certificates, artwork history and ownership events may reference their numeric IDs.
		$bridge_final = get_option( 'artiey_aet_bridge_final_failed_rows', array() );
		$bridge_final = is_array( $bridge_final ) ? $bridge_final : array();
		if ( $bridge_final ) {
			$ids = array();
			foreach ( array_slice( $bridge_final, 0, 5 ) as $failure ) {
				if ( is_array( $failure ) && isset( $failure['table'], $failure['id'] ) ) {
					$ids[] = sanitize_key( (string) $failure['table'] ) . '#' . absint( $failure['id'] );
				}
			}
			$hint = 'برای حفظ شناسه‌های گواهی/مالکیت، رکوردها خودکار حذف یا ادغام نشده‌اند. شناسه‌ها: ' . implode( '، ', $ids );
			$add( 'warn', self::fa( count( $bridge_final ) ) . ' رکورد COA برای اتصال به محتوای اصلی نیازمند بررسی است', $hint, admin_url( 'edit.php?post_type=artiey_artist' ), 'بررسی پروفایل‌های هنرمندان' );
		}
		$sync_final = get_option( 'artiey_aet_sync_final_failed_posts', array() );
		$sync_final = is_array( $sync_final ) ? $sync_final : array();
		if ( $sync_final ) {
			$types = array_values( array_unique( array_filter( array_map( static function ( $failure ) {
				return is_array( $failure ) ? sanitize_key( (string) ( $failure['post_type'] ?? '' ) ) : '';
			}, $sync_final ) ) ) );
			$link_type = in_array( 'artiey_artist', $types, true ) ? 'artiey_artist' : ( $types[0] ?? 'artiey_artist' );
			$add( 'warn', self::fa( count( $sync_final ) ) . ' پست به لایهٔ رابطه‌ای COA همگام نشده است', 'سامانه چند بار تلاش کرده است؛ خطاهای باقی‌مانده را بررسی کنید. دادهٔ پست وردپرس حذف نشده است.', admin_url( 'edit.php?post_type=' . rawurlencode( $link_type ) ), 'بررسی محتوا' );
		}

		// Content health.
		$no_date = self::count_query( 'artiey_exhibition', array( array( 'key' => '_ae_start', 'compare' => 'NOT EXISTS' ) ) );
		if ( $no_date ) {
			$add( 'warn', self::fa( $no_date ) . ' نمایشگاه منتشرشده تاریخ ندارد', 'این نمایشگاه‌ها در تایم‌لاین و فیلتر وضعیت دیده نمی‌شوند.', admin_url( 'edit.php?post_type=artiey_exhibition' ), 'نمایشگاه‌ها' );
		}
		if ( Artiey_AET_COA_Bridge::available() ) {
			$g_unlinked = self::count_query( 'artiey_gallery', array( array( 'key' => '_ag_coa_gallery_id', 'compare' => 'NOT EXISTS' ) ) );
			if ( $g_unlinked ) {
				$add( 'info', self::fa( $g_unlinked ) . ' گالری به پروفایل صادرکننده گواهی وصل نیست', 'در ویرایش هر گالری، تب «گواهی اصالت» را باز کنید.', admin_url( 'edit.php?post_type=artiey_gallery' ), 'گالری‌ها' );
			}
			$a_unlinked = self::count_query( 'artiey_artist', array( array( 'key' => '_aa_coa_person_id', 'compare' => 'NOT EXISTS' ) ) );
			if ( $a_unlinked ) {
				$add( 'info', self::fa( $a_unlinked ) . ' هنرمند به پروفایل رسمی گواهی وصل نیست', 'در ویرایش هر هنرمند، تب «گواهی اصالت» را باز کنید.', admin_url( 'edit.php?post_type=artiey_artist' ), 'هنرمندان' );
			}
		}
		$no_img = new WP_Query(
			array(
				'post_type'      => 'artiey_artwork',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ),
			)
		);
		if ( (int) $no_img->found_posts ) {
			$add( 'warn', self::fa( (int) $no_img->found_posts ) . ' اثر منتشرشده تصویر ندارد', 'تصویر اثر برای نمایش و صدور گواهی لازم است.', admin_url( 'edit.php?post_type=artiey_artwork' ), 'آثار' );
		}

		return $items;
	}

	/* ---------------------------------------------------------------- */
	/* Rendering                                                         */
	/* ---------------------------------------------------------------- */

	private static function card( $title, $number, $sub, $url, $class = '' ) {
		$h  = '<a class="aetpa-card ' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">';
		$h .= '<span class="aetpa-card-title">' . esc_html( $title ) . '</span>';
		$h .= '<span class="aetpa-card-num">' . esc_html( self::fa( $number ) ) . '</span>';
		$h .= '<span class="aetpa-card-sub">' . esc_html( $sub ) . '</span></a>';
		return $h;
	}

	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$ex     = self::exhibition_counts();
		$certs  = Artiey_AET_COA_Bridge::totals();
		$manage = Artiey_AET_COA_Bridge::can_manage();
		$sold   = self::count_query( 'artiey_artwork', array( array( 'key' => '_aw_sale', 'value' => 'sold' ) ) );
		$art    = self::published( 'artiey_artwork' );

		echo '<div class="wrap aetpa">';
		echo '<div class="aetpa-hero"><div><h1>پلتفرم جامع آرتی</h1><p>مدیریت یکپارچه گالری‌ها، هنرمندان، آثار، نمایشگاه‌ها و گواهی اصالت</p></div>';
		echo '<span class="aetpa-ver">نسخه ' . esc_html( AET_VERSION ) . '</span></div>';

		/* Stats */
		echo '<div class="aetpa-cards">';
		echo self::card( 'گالری‌ها', self::published( 'artiey_gallery' ), 'منتشرشده', admin_url( 'edit.php?post_type=artiey_gallery' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::card( 'هنرمندان', self::published( 'artiey_artist' ), 'منتشرشده', admin_url( 'edit.php?post_type=artiey_artist' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::card( 'آثار هنری', $art, self::fa( $sold ) . ' فروخته‌شده', admin_url( 'edit.php?post_type=artiey_artwork' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::card( 'نمایشگاه‌های جاری', $ex['now'], self::fa( $ex['upcoming'] ) . ' به‌زودی · ' . self::fa( $ex['past'] ) . ' گذشته', admin_url( 'edit.php?post_type=artiey_exhibition' ), 'is-accent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( Artiey_AET_COA_Bridge::available() ) {
			echo self::card( 'گواهی‌های فعال', $certs['active'], self::fa( $certs['revoked'] ) . ' ابطال‌شده', $manage ? admin_url( 'admin.php?page=wp-coa-pro' ) : '#', 'is-gold' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		/* Shortcuts */
		echo '<div class="aetpa-panel"><h2>میان‌بُرها</h2><div class="aetpa-actions">';
		$actions = array(
			array( 'نمایشگاه جدید', admin_url( 'post-new.php?post_type=artiey_exhibition' ), true ),
			array( 'گالری جدید', admin_url( 'post-new.php?post_type=artiey_gallery' ), false ),
			array( 'هنرمند جدید', admin_url( 'post-new.php?post_type=artiey_artist' ), false ),
			array( 'اثر جدید', admin_url( 'post-new.php?post_type=artiey_artwork' ), false ),
		);
		foreach ( $actions as $a ) {
			echo '<a class="button' . ( $a[2] ? ' button-primary' : '' ) . '" href="' . esc_url( $a[1] ) . '">' . esc_html( $a[0] ) . '</a>';
		}
		if ( $manage ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wp-coa-pro' ) ) . '">صدور گواهی اصالت</a>';
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wp-coa-pro-events' ) ) . '">تاریخچه رویدادها</a>';
		}
		if ( current_user_can( 'manage_options' ) ) {
			echo '<a class="button" href="' . esc_url( Artiey_AET_Admin::settings_url() ) . '">تنظیمات پلتفرم</a>';
		}
		echo '</div></div>';

		echo '<div class="aetpa-cols">';

		/* Health */
		echo '<div class="aetpa-panel"><h2>سلامت سیستم</h2><ul class="aetpa-health">';
		foreach ( self::health() as $it ) {
			echo '<li class="is-' . esc_attr( $it['level'] ) . '"><span class="aetpa-dot"></span><div><strong>' . esc_html( $it['title'] ) . '</strong>';
			if ( $it['hint'] ) {
				echo '<p>' . esc_html( $it['hint'] ) . '</p>';
			}
			if ( $it['link'] ) {
				echo '<a href="' . esc_url( $it['link'] ) . '">' . esc_html( $it['link_label'] ) . '</a>';
			}
			echo '</div></li>';
		}
		echo '</ul></div>';

		/* Recent certificates + quick start */
		echo '<div class="aetpa-side">';
		$recent = Artiey_AET_COA_Bridge::recent( 5 );
		if ( $recent ) {
			echo '<div class="aetpa-panel"><h2>آخرین گواهی‌ها</h2><table class="widefat striped"><tbody>';
			foreach ( $recent as $r ) {
				echo '<tr><td><code dir="ltr">' . esc_html( $r->certificate_code ) . '</code></td><td>' . esc_html( $r->artwork_title ) . '</td><td>' . esc_html( 'active' === $r->status ? 'فعال' : 'ابطال‌شده' ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}

		echo '<div class="aetpa-panel"><h2>شروع سریع</h2><ol class="aetpa-steps">';
		echo '<li><strong>گالری و هنرمند بسازید</strong> و در تب «گواهی اصالت» هر کدام، پروفایل رسمی گواهی را بسازید یا وصل کنید.</li>';
		echo '<li><strong>اثر را ثبت کنید</strong> (تصویر، مشخصات، قیمت، وضعیت فروش).</li>';
		echo '<li>در ویرایش اثر، تب «گواهی اصالت» ← <strong>صدور گواهی</strong>؛ فرم با اطلاعات اثر پر می‌شود و گواهی خودکار به اثر وصل می‌شود.</li>';
		echo '<li><strong>نمایشگاه ثبت کنید</strong> و با <code>[artiey_directory]</code> یا ابزارک «فهرست با فیلتر» در المنتور نمایش دهید.</li>';
		echo '<li>برای دیدن همه چیز، از «تنظیمات پلتفرم» <strong>داده نمونه</strong> بسازید.</li>';
		echo '</ol></div>';
		echo '</div></div></div>';
	}
}
