<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI: settings page (submenu of "نمایشگاه‌ها"), dashboard widget, plugin row links.
 */
final class Artiey_AET_Admin {

	const PAGE = 'artiey-aet-settings';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_artiey_aet_reset', array( __CLASS__, 'handle_reset' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_setup' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AET_FILE ), array( __CLASS__, 'plugin_links' ) );
	}

	public static function settings_url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	public static function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::settings_url() ) . '">تنظیمات</a>' );
		return $links;
	}

	public static function menu() {
		self::$hook = add_submenu_page(
			Artiey_AET_Platform_Admin::SLUG,
			'تنظیمات تایم‌لاین نمایشگاه‌ها',
			'تنظیمات پلتفرم',
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_setting() {
		register_setting(
			'artiey_aet_group',
			Artiey_AET_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Artiey_AET_Settings', 'sanitize' ),
				'default'           => Artiey_AET_Settings::defaults(),
			)
		);
	}

	public static function assets( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'artiey-aet-admin', AET_URL . 'assets/css/admin.css', array(), AET_VERSION );
		wp_enqueue_script( 'artiey-aet-admin', AET_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), AET_VERSION, true );
	}

	public static function handle_reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'artiey_aet_reset' );
		delete_option( Artiey_AET_Settings::OPTION );
		Artiey_AET_Settings::bump();
		wp_safe_redirect( add_query_arg( 'aet_reset', '1', self::settings_url() ) );
		exit;
	}

	/* ---------------------------------------------------------------- */
	/* Field helpers                                                     */
	/* ---------------------------------------------------------------- */

	private static function name( $key ) {
		return Artiey_AET_Settings::OPTION . '[' . $key . ']';
	}

	private static function row( $label, $html, $desc = '' ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts below.
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function f_number( $key, $label, $min, $max, $unit = '', $desc = '' ) {
		$o    = Artiey_AET_Settings::all();
		$html = '<input type="number" class="small-text" dir="ltr" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $o[ $key ] ) . '" min="' . (int) $min . '" max="' . (int) $max . '"> ' . esc_html( $unit );
		self::row( $label, $html, $desc );
	}

	private static function f_text( $key, $label, $desc = '', $dir = 'rtl', $class = 'regular-text' ) {
		$o    = Artiey_AET_Settings::all();
		$html = '<input type="text" class="' . esc_attr( $class ) . '" dir="' . esc_attr( $dir ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $o[ $key ] ) . '">';
		self::row( $label, $html, $desc );
	}

	private static function f_check( $key, $label, $text, $desc = '' ) {
		$o    = Artiey_AET_Settings::all();
		$html = '<label><input type="checkbox" name="' . esc_attr( self::name( $key ) ) . '" value="1" ' . checked( ! empty( $o[ $key ] ), true, false ) . '> ' . esc_html( $text ) . '</label>';
		self::row( $label, $html, $desc );
	}

	private static function f_color( $key, $label, $desc = '' ) {
		$o    = Artiey_AET_Settings::all();
		$html = '<input type="text" class="aet-color" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $o[ $key ] ) . '" data-default-color="' . esc_attr( Artiey_AET_Settings::defaults()[ $key ] ) . '">';
		self::row( $label, $html, $desc );
	}

	private static function f_select( $key, $label, $choices, $desc = '' ) {
		$o    = Artiey_AET_Settings::all();
		$html = '<select name="' . esc_attr( self::name( $key ) ) . '">';
		foreach ( $choices as $value => $text ) {
			$html .= '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $o[ $key ], (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		$html .= '</select>';
		self::row( $label, $html, $desc );
	}

	/* ---------------------------------------------------------------- */
	/* Settings page                                                     */
	/* ---------------------------------------------------------------- */

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = Artiey_AET_Settings::all();

		echo '<div class="wrap aet-admin">';
		echo '<h1>تنظیمات تایم‌لاین نمایشگاه‌ها <span class="aet-ver">نسخه ' . esc_html( AET_VERSION ) . '</span></h1>';

		if ( isset( $_GET['aet_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>تنظیمات به حالت پیش‌فرض برگشت.</p></div>';
		}
		settings_errors();

		echo '<h2 class="nav-tab-wrapper aet-tabs">';
		$tabs = array(
			'general'    => 'عمومی',
			'content'    => 'محتوا و رفتار',
			'appearance' => 'ظاهر و رنگ‌ها',
			'profile'    => 'پروفایل گالری',
			'artist'     => 'هنرمند، نمایشگاه و فهرست‌ها',
			'artwork'    => 'آثار و گواهی اصالت',
			'advanced'   => 'پیشرفته',
			'help'       => 'راهنما',
		);
		foreach ( $tabs as $id => $label ) {
			echo '<a href="#' . esc_attr( $id ) . '" class="nav-tab" data-tab="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		echo '<form method="post" action="options.php">';
		settings_fields( 'artiey_aet_group' );

		/* ---- General ---- */
		echo '<div class="aet-panel" data-panel="general"><table class="form-table" role="presentation">';
		self::f_number( 'days', 'تعداد روزهای تایم‌لاین', 3, 31, 'روز', 'پیش‌فرض برای همه ویجت‌ها و شورت‌کدها (قابل تغییر در هر ویجت).' );
		self::f_number( 'before_days', 'شروع از چند روز قبل از امروز', 0, 60, 'روز', 'مثلاً ۶ یعنی بازه از ۶ روز پیش شروع شود تا امروز وسط جدول بیفتد.' );
		self::f_number( 'max_rows', 'حداکثر تعداد ردیف (نمایشگاه)', 1, 50, 'ردیف' );
		self::f_number( 'step', 'گام دکمه‌های قبل / بعد', 1, 31, 'روز', 'با هر کلیک بازه چند روز جابه‌جا شود (۷ = یک هفته).' );
		self::f_check( 'show_nav', 'دکمه‌های قبل / بعد', 'نمایش داده شود' );
		self::f_check( 'show_range', 'برچسب بازه تاریخ', 'بازه شمسی (مثلاً «۲ آبان ۱۴۰۵ تا ۱۵ آبان ۱۴۰۵») بالای جدول نمایش داده شود' );
		self::f_select( 'digits', 'نوع ارقام', array( 'fa' => 'فارسی (۱۲۳)', 'latin' => 'انگلیسی (123)' ) );
		echo '</table></div>';

		/* ---- Content ---- */
		echo '<div class="aet-panel" data-panel="content"><table class="form-table" role="presentation">';
		$checked = (array) $o['sub_fields'];
		$labels  = array( 'type' => 'نوع نمایشگاه', 'artist' => 'نام هنرمند', 'gallery' => 'نام گالری' );
		$html    = '';
		foreach ( $labels as $key => $text ) {
			$html .= '<label class="aap-s-861ef7"><input type="checkbox" name="' . esc_attr( self::name( 'sub_fields' ) ) . '[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $checked, true ), true, false ) . '> ' . esc_html( $text ) . '</label>';
		}
		self::row( 'زیرعنوان داخل نوار', $html, 'کدام اطلاعات زیر عنوان نمایشگاه نوشته شود.' );
		self::f_text( 'sub_sep', 'جداکننده زیرعنوان', 'مثلاً / یا | یا ·', 'ltr', 'small-text' );
		self::f_select( 'link_target', 'باز شدن لینک نمایشگاه', array( '_self' => 'در همین تب', '_blank' => 'در تب جدید' ) );
		self::f_text( 'empty_text', 'متن حالت خالی', 'وقتی در بازه انتخابی نمایشگاهی نیست.' );
		self::f_text( 'more_text', 'متن دکمه پایین جدول' );
		self::f_text( 'more_url', 'لینک دکمه پایین جدول', 'مثلاً صفحه آرشیو نمایشگاه‌ها. خالی = دکمه نمایش داده نشود.', 'ltr' );
		echo '</table></div>';

		/* ---- Appearance ---- */
		echo '<div class="aet-panel" data-panel="appearance"><h2 class="title">رنگ‌ها</h2><table class="form-table" role="presentation">';
		self::f_color( 'color_bar', 'رنگ نوار نمایشگاه' );
		self::f_color( 'color_bar_text', 'رنگ متن نوار' );
		self::f_color( 'color_today', 'رنگ نشانگر امروز' );
		self::f_color( 'color_head_bg', 'پس‌زمینه سربرگ روزها' );
		self::f_color( 'color_head_alt', 'پس‌زمینه سربرگ (روزهای یک‌درمیان)' );
		self::f_color( 'color_bg', 'پس‌زمینه ستون روزها' );
		self::f_color( 'color_bg_alt', 'پس‌زمینه ستون (یک‌درمیان)' );
		self::f_color( 'color_bg_fri', 'پس‌زمینه ستون جمعه' );
		self::f_color( 'color_fri_text', 'رنگ نام روز جمعه' );
		echo '</table><h2 class="title">رنگ‌بندی بر اساس وضعیت</h2><table class="form-table" role="presentation">';
		self::f_check( 'status_colors', 'فعال‌سازی', 'رنگ نوار بر اساس وضعیت نمایشگاه (گذشته / در حال برگزاری / آینده) باشد', 'اگر فعال باشد، رنگ اختصاصی گالری نادیده گرفته می‌شود.' );
		self::f_color( 'color_now', 'در حال برگزاری' );
		self::f_color( 'color_upcoming', 'آینده' );
		self::f_color( 'color_past', 'پایان‌یافته' );
		echo '</table><h2 class="title">ابعاد و تایپوگرافی</h2><table class="form-table" role="presentation">';
		self::f_number( 'logo_width', 'عرض ستون لوگو', 50, 200, 'px' );
		self::f_number( 'logo_max_height', 'حداکثر ارتفاع لوگو', 16, 100, 'px' );
		self::f_number( 'col_width', 'حداقل عرض هر روز', 36, 140, 'px', 'اگر مجموع عرض‌ها از صفحه بیشتر شود، جدول اسکرول افقی می‌گیرد.' );
		self::f_number( 'row_height', 'ارتفاع هر ردیف', 40, 120, 'px' );
		self::f_number( 'bar_gap', 'فاصله عمودی نوارها', 0, 20, 'px' );
		self::f_number( 'bar_radius', 'گردی گوشه نوارها', 0, 30, 'px' );
		self::f_number( 'title_size', 'اندازه فونت عنوان', 10, 28, 'px' );
		self::f_number( 'sub_size', 'اندازه فونت زیرعنوان', 8, 20, 'px' );
		echo '</table><h2 class="title">جلوه‌ها</h2><table class="form-table" role="presentation">';
		self::f_check( 'stripes', 'ستون‌های راه‌راه', 'ستون روزها یک‌درمیان رنگ متفاوت داشته باشند' );
		self::f_check( 'highlight_fri', 'تأکید روی جمعه', 'ستون و نام جمعه متمایز شود' );
		self::f_check( 'highlight_today', 'تأکید روی امروز', 'ستون امروز کمی رنگی شود' );
		echo '</table></div>';

		/* ---- Gallery profile ---- */
		echo '<div class="aet-panel" data-panel="profile"><table class="form-table" role="presentation">';
		self::f_check( 'profile_auto', 'نمایش خودکار', 'در صفحه تک‌گالری، پروفایل کامل خودکار به صفحه اضافه شود', 'اگر صفحه گالری را با Theme Builder المنتور طراحی می‌کنید، این گزینه را خاموش کنید و از ویجت «پروفایل گالری» یا شورت‌کدها استفاده کنید.' );
		$sec_labels = array(
			'header'      => 'سربرگ (کاور، لوگو، نام‌ها، وضعیت باز/بسته)',
			'short_bio'   => 'بیوگرافی کوتاه',
			'details'     => 'مشخصات (نام‌ها، سال تأسیس، شهر، سبک هنری)',
			'contact'     => 'راه‌های ارتباطی و شبکه‌های اجتماعی',
			'hours'       => 'ساعات کاری',
			'map'         => 'آدرس و نقشه',
			'photos'      => 'تصاویر گالری',
			'statement'   => 'بیانیه گالری',
			'critics'     => 'نقد و نظر منتقدان',
			'awards'      => 'جوایز و افتخارات',
			'team'        => 'تیم گالری',
			'press'       => 'بازتاب رسانه‌ای',
			'follow'      => 'دنبال‌کنندگان',
			'artists'     => 'هنرمندان گالری',
			'curators'    => 'کیوریتورهای همکار',
			'collectors'  => 'مجموعه‌داران عمومی',
			'auctions'    => 'حراج‌های مرتبط',
			'calls'       => 'فراخوان‌های باز گالری',
			'exhibitions' => 'نمایشگاه‌های جاری و پیش‌رو',
			'past_exhibitions' => 'پوستر نمایشگاه‌های برگزارشده',
		);
		$have = (array) $o['profile_sections'];
		$html = '';
		foreach ( $sec_labels as $key => $text ) {
			$html .= '<label class="aap-s-04e3cc"><input type="checkbox" name="' . esc_attr( self::name( 'profile_sections' ) ) . '[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $have, true ), true, false ) . '> ' . esc_html( $text ) . '</label>';
		}
		self::row( 'بخش‌های نمایش‌داده‌شده', $html, 'بیوگرافی کامل همان متن ویرایشگر اصلی است و همیشه نمایش داده می‌شود.' );
		self::f_number( 'map_height', 'ارتفاع نقشه', 200, 700, 'px' );
		self::f_color( 'color_accent', 'رنگ تأکیدی پروفایل' );
		echo '</table></div>';

		/* ---- Artists / exhibitions / directories ---- */
		echo '<div class="aet-panel" data-panel="artist"><h2 class="title">پروفایل هنرمند</h2><table class="form-table" role="presentation">';
		self::f_check( 'artist_auto', 'نمایش خودکار', 'در صفحه تک‌هنرمند، پروفایل کامل خودکار اضافه شود', 'برای طراحی با Theme Builder المنتور این گزینه را خاموش کنید و از ویجت «پروفایل هنرمند» استفاده کنید.' );
		$a_labels = array(
			'header'      => 'سربرگ (کاور، آواتار، نام‌ها، شهر، سن، سبک)',
			'short_bio'   => 'معرفی کوتاه',
			'statement'   => 'بیانیهٔ هنری',
			'details'     => 'مشخصات (سال تولد، شهر، ملیت، رسانه، تحصیلات و سبک)',
			'awards'      => 'جوایز و افتخارات',
			'career_timeline' => 'خط زمانی تحصیلات و جوایز',
			'contact'     => 'شبکه‌های ارتباطی',
			'works'       => 'نمونه آثار / Portfolio',
			'galleries'   => 'گالری‌های همکار',
			'exhibitions' => 'نمایشگاه‌های هنرمند',
			'activity_chart' => 'نمودار فعالیت هنرمند',
			'sales_chart' => 'نمودار فروش (با رعایت دسترسی)',
			'artwork_history' => 'تاریخچهٔ آثار، حراج و مالکیت',
		);
		$a_have = (array) $o['artist_sections'];
		$html   = '';
		foreach ( $a_labels as $key => $text ) {
			$html .= '<label class="aap-s-04e3cc"><input type="checkbox" name="' . esc_attr( self::name( 'artist_sections' ) ) . '[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $a_have, true ), true, false ) . '> ' . esc_html( $text ) . '</label>';
		}
		self::row( 'بخش‌های نمایش‌داده‌شده', $html );
		echo '</table><h2 class="title">صفحه نمایشگاه</h2><table class="form-table" role="presentation">';
		self::f_check( 'exhibition_auto', 'نمایش خودکار', 'در صفحه تک‌نمایشگاه، پوستر و اطلاعات (تاریخ شمسی، گالری، هنرمندان، آدرس) اضافه شود' );
		echo '</table><h2 class="title">فهرست‌ها و فیلترها</h2><table class="form-table" role="presentation">';
		self::f_number( 'directory_per_page', 'تعداد در هر صفحه', 1, 60, 'مورد' );
		self::f_number( 'directory_columns', 'تعداد ستون‌ها', 1, 6, 'ستون' );
		self::f_check( 'directory_ajax', 'فیلتر بدون بارگذاری مجدد', 'فیلترها و صفحه‌بندی با AJAX انجام شود', 'بدون جاوااسکریپت هم فیلترها با آدرس (GET) کار می‌کنند و قابل اشتراک‌گذاری‌اند.' );
		echo '</table><h2 class="title">سئو</h2><table class="form-table" role="presentation">';
		self::f_check( 'schema', 'داده ساختاریافته', 'کد Schema.org (JSON-LD) برای گالری، نمایشگاه و هنرمند در صفحه‌ها اضافه شود', 'به گوگل کمک می‌کند ساعات کاری، آدرس، تاریخ نمایشگاه و ... را بهتر نمایش دهد. اگر افزونه سئوی شما همین را تولید می‌کند خاموشش کنید.' );
		echo '</table></div>';

		/* ---- Artworks / certificates ---- */
		echo '<div class="aet-panel" data-panel="artwork"><h2 class="title">صفحه اثر هنری</h2><table class="form-table" role="presentation">';
		self::f_check( 'artwork_auto', 'نمایش خودکار', 'در صفحه تک‌اثر، تصویر، مشخصات، قیمت و گواهی اصالت خودکار اضافه شود', 'برای طراحی با Theme Builder المنتور خاموش کنید و از ویجت «پروفایل اثر» یا شورت‌کدها استفاده کنید.' );
		$w_labels = array(
			'main'    => 'بخش اصلی (تصویر، مشخصات، قیمت، گواهی)',
			'photos'  => 'تصاویر بیشتر اثر',
			'related' => 'آثار دیگر همان هنرمند',
		);
		$w_have = (array) $o['artwork_sections'];
		$html   = '';
		foreach ( $w_labels as $key => $text ) {
			$html .= '<label class="aap-s-04e3cc"><input type="checkbox" name="' . esc_attr( self::name( 'artwork_sections' ) ) . '[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $w_have, true ), true, false ) . '> ' . esc_html( $text ) . '</label>';
		}
		self::row( 'بخش‌های نمایش‌داده‌شده', $html );
		self::f_text( 'currency', 'واحد پول', 'مثلاً تومان یا ریال؛ فقط برای نمایش (در داده ساختاریافته تومان به ریال تبدیل می‌شود).', 'rtl', 'small-text' );
		echo '</table><h2 class="title">گواهی اصالت (WP COA Pro)</h2><table class="form-table" role="presentation">';
		self::f_check( 'show_certificates', 'نمایش عمومی', 'وضعیت گواهی اصالت (معتبر / ابطال‌شده) و تعداد گواهی‌های فعال در صفحه اثر، گالری و هنرمند نمایش داده شود', 'فقط کد، وضعیت و تاریخ صدور نمایش داده می‌شود؛ اطلاعات مالک و یادداشت‌ها هرگز نمایش داده نمی‌شوند. اگر «استعلام عمومی» در تنظیمات گواهی خاموش باشد، لینک استعلام و شمارنده‌ها نمایش داده نمی‌شوند.' );
		if ( class_exists( 'WP_COA_Pro_Admin' ) && current_user_can( 'manage_coa_certificates' ) ) {
			$links  = '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wp-coa-pro' ) ) . '">صدور و مدیریت گواهی‌ها</a> ';
			$links .= '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wp-coa-pro-settings' ) ) . '">تنظیمات گواهی (برند، استعلام، API)</a> ';
			$links .= '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wp-coa-pro-galleries' ) ) . '">پروفایل‌های صادرکننده</a>';
			self::row( 'میان‌بُر', $links );
		}
		echo '</table></div>';

		/* ---- Advanced ---- */
		echo '<div class="aet-panel" data-panel="advanced"><table class="form-table" role="presentation">';
		echo '<tr><td colspan="2"><h2 class="title">آدرس‌ها (پیوند یکتا)</h2><p class="description">مسیر آرشیو و پروفایل هنرمندان برای یکپارچگی لینک‌ها ثابت است. مسیر سایر انواع محتوا قابل تنظیم است؛ فقط حروف انگلیسی، عدد و خط تیره وارد کنید. پس از ذخیره، پیوندهای یکتا به‌روزرسانی می‌شوند.</p></td></tr>';
		self::row( 'هنرمندان', '<code dir="ltr">/artists/</code> و <code dir="ltr">/artists/{slug}/</code>', 'مسیر اصلی و ثابت آرشیو و پروفایل همهٔ هنرمندان.' );
		self::f_text( 'base_gallery', 'گالری', 'پیش‌فرض: galleries', 'ltr', 'regular-text code' );
		self::f_text( 'base_exhibition', 'نمایشگاه', 'پیش‌فرض: exhibitions', 'ltr', 'regular-text code' );
		self::f_text( 'base_artwork', 'اثر هنری', 'پیش‌فرض: artworks', 'ltr', 'regular-text code' );
		self::f_text( 'base_style', 'سبک هنری', 'پیش‌فرض: art-styles', 'ltr', 'regular-text code' );
		self::f_number( 'cache_minutes', 'کش خروجی', 0, 1440, 'دقیقه', '۰ = غیرفعال. برای سایت‌های پرترافیک مفید است؛ با ذخیره هر نمایشگاه یا گالری خودکار پاک می‌شود.' );

		$css = '<textarea name="' . esc_attr( self::name( 'custom_css' ) ) . '" rows="9" class="large-text code" dir="ltr" placeholder=".aet-title { letter-spacing: 0; }">' . esc_textarea( $o['custom_css'] ) . '</textarea>';
		self::row( 'CSS سفارشی', $css, 'روی همه تایم‌لاین‌های سایت اعمال می‌شود. کلاس‌ها: .aet  .aet-bar  .aet-title  .aet-sub  .aet-head  .aet-logo' );

		self::f_check( 'delete_on_uninstall', 'حذف اطلاعات', 'هنگام حذف افزونه، تنظیمات و تمام نمایشگاه‌ها و گالری‌ها هم پاک شوند', 'هشدار: این کار غیرقابل بازگشت است. اگر فقط افزونه را غیرفعال کنید چیزی پاک نمی‌شود.' );
		echo '</table></div>';

		/* ---- Help ---- */
		echo '<div class="aet-panel" data-panel="help">';
		echo '<h2 class="title">شروع سریع</h2><ol>';
		echo '<li>از منوی «گالری‌ها» گالری بسازید و لوگو را به‌عنوان «لوگوی گالری» بگذارید.</li>';
		echo '<li>از منوی «نمایشگاه‌ها» نمایشگاه بسازید، گالری و تاریخ شمسی شروع و پایان را وارد کنید.</li>';
		echo '<li>در المنتور ویجت «تایم‌لاین نمایشگاه‌ها» را بکشید یا شورت‌کد زیر را در هر برگه بگذارید.</li></ol>';
		echo '<h2 class="title">شورت‌کد</h2>';
		echo '<p><code dir="ltr">[artiey_timeline days="14" before_days="6" max_rows="10" show_nav="yes" more_url="/exhibitions/"]</code></p>';
		echo '<table class="widefat striped aet-help"><thead><tr><th>پارامتر</th><th>توضیح</th></tr></thead><tbody>';
		$help = array(
			'days'         => 'تعداد روز (۳ تا ۳۱)',
			'before_days'  => 'شروع از چند روز قبل از امروز',
			'start_mode'   => 'today یا custom',
			'custom_start' => 'تاریخ شروع شمسی وقتی start_mode=custom، مثل 1405/08/01',
			'galleries'    => 'شناسه گالری‌ها با کاما، مثل "12,15" (خالی = همه)',
			'max_rows'     => 'حداکثر ردیف',
			'show_nav'     => 'yes یا no',
			'more_text'    => 'متن دکمه پایین',
			'more_url'     => 'لینک دکمه پایین',
		);
		foreach ( $help as $k => $v ) {
			echo '<tr><td><code dir="ltr">' . esc_html( $k ) . '</code></td><td>' . esc_html( $v ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2 class="title">شورت‌کدهای گالری</h2>';
		echo '<p><code dir="ltr">[artiey_gallery_profile id="12" sections="header,contact,hours,map"]</code> — پروفایل گالری (بدون id، گالری جاری)</p>';
		echo '<p><code dir="ltr">[artiey_gallery_field field="phone"]</code> — یک فیلد تکی؛ field: name_en, founded, city, address, phone, mobile, whatsapp, instagram, telegram, email, website, status, styles, map_link, short_bio</p>';
		echo '<p><code dir="ltr">[artiey_directory type="galleries" filters="search,city,style,open,sort" per_page="12" columns="3"]</code> — فهرست فیلترپذیر؛ type: galleries | exhibitions | artists | artworks</p>';
		echo '<p><code dir="ltr">[artiey_directory type="exhibitions" status="upcoming" filters="city,style,gallery"]</code> — نمایشگاه‌های آینده با فیلتر شهر، سبک و گالری</p>';
		echo '<p><code dir="ltr">[artiey_directory type="artists" filters="search,city,style,gallery"]</code> — فهرست هنرمندان</p>';
		echo '<p><code dir="ltr">[artiey_related_exhibitions gallery="12" status="now,upcoming" mode="tabs"]</code> — پوستر نمایشگاه‌های یک گالری یا هنرمند (بدون پارامتر: صفحه جاری)؛ status: now, upcoming, past</p>';
		echo '<p><code dir="ltr">[artiey_artist_profile id="30"]</code> و <code dir="ltr">[artiey_artist_field field="instagram"]</code> — پروفایل و فیلد تکی هنرمند</p>';
		echo '<p><code dir="ltr">[artiey_stats metrics="galleries,artists,artworks,exhibitions,current,upcoming"]</code> — آمار کلی پویا؛ در صفحه هر پروفایل نیز شمارهای مرتبط به‌صورت خودکار نمایش داده می‌شوند.</p>';
		echo '<p><code dir="ltr">[artiey_galleries count="12" columns="3"]</code> — شبکه ساده گالری‌ها (سازگار با نسخه‌های قبل)</p>';
		echo '<p class="description">پارامترهای خالی از همین صفحه تنظیمات گرفته می‌شوند. «امروز» بر اساس منطقه زمانی سایت است؛ در <em>تنظیمات ← همگانی</em> آن را روی Asia/Tehran بگذارید.</p>';
		echo '</div>';

		submit_button( 'ذخیره تنظیمات' );
		echo '</form>';

		// Reset (separate form).
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aet-reset" onsubmit="return confirm(\'همه تنظیمات به حالت پیش‌فرض برگردد؟\');">';
		echo '<input type="hidden" name="action" value="artiey_aet_reset">';
		wp_nonce_field( 'artiey_aet_reset' );
		echo '<button type="submit" class="button button-link-delete">بازنشانی همه تنظیمات به پیش‌فرض</button>';
		echo '</form>';

		// Demo data tools.
		if ( isset( $_GET['aet_demo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$flag = sanitize_key( wp_unslash( $_GET['aet_demo'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'created' === $flag ) {
				echo '<div class="notice notice-success is-dismissible"><p>داده نمونه ساخته شد: ۲ گالری، ۳ هنرمند، ۴ اثر و ۶ نمایشگاه.</p></div>';
			} elseif ( 'removed' === $flag ) {
				echo '<div class="notice notice-success is-dismissible"><p>داده‌های نمونه حذف شد.</p></div>';
			}
		}
		echo '<div class="aet-demo"><h2 class="title">داده نمونه</h2><p class="description">برای دیدن سریع امکانات افزونه (تایم‌لاین، فیلترها، پروفایل‌ها) چند گالری، هنرمند و نمایشگاه نمونه بسازید. فقط داده‌های نمونه حذف می‌شوند و محتوای واقعی شما دست‌نخورده می‌ماند.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aap-s-170baa">';
		echo '<input type="hidden" name="action" value="artiey_aet_demo"><input type="hidden" name="do" value="create">';
		wp_nonce_field( 'artiey_aet_demo' );
		echo '<button type="submit" class="button">ساخت داده نمونه</button></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'همه داده‌های نمونه حذف شود؟\');" class="aap-s-b69883">';
		echo '<input type="hidden" name="action" value="artiey_aet_demo"><input type="hidden" name="do" value="remove">';
		wp_nonce_field( 'artiey_aet_demo' );
		echo '<button type="submit" class="button">حذف داده‌های نمونه</button></form></div>';

		echo '</div>';
	}

	/* ---------------------------------------------------------------- */
	/* Dashboard widget                                                  */
	/* ---------------------------------------------------------------- */

	public static function dashboard_setup() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'artiey_aet_dashboard', 'نمایشگاه‌های هنری', array( __CLASS__, 'dashboard_widget' ) );
	}

	private static function dash_list( $posts ) {
		if ( ! $posts ) {
			echo '<p class="aap-s-0a8e53">موردی نیست.</p>';
			return;
		}
		echo '<ul class="aap-s-067f1e">';
		foreach ( $posts as $p ) {
			$gid  = (int) get_post_meta( $p->ID, '_ae_gallery', true );
			$gal  = $gid ? get_the_title( $gid ) : '';
			$from = Artiey_AET_Jalali::format_long( get_post_meta( $p->ID, '_ae_start', true ) );
			$to   = Artiey_AET_Jalali::format_long( get_post_meta( $p->ID, '_ae_end', true ) );

			echo '<li class="aap-s-8c4d98">';
			echo '<a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '"><strong>' . esc_html( get_the_title( $p ) ) . '</strong></a>';
			if ( $gal ) {
				echo ' <span class="aap-s-58ea9b">— ' . esc_html( $gal ) . '</span>';
			}
			echo '<br><span class="aap-s-1eb7b7">' . esc_html( $from . ( $to && $to !== $from ? ' تا ' . $to : '' ) ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
	}

	public static function dashboard_widget() {
		$today = current_time( 'Y-m-d' );

		$now = get_posts(
			array(
				'post_type'   => 'artiey_exhibition',
				'post_status' => 'publish',
				'numberposts' => 5,
				'meta_key'    => '_ae_end',
				'orderby'     => 'meta_value',
				'meta_type'   => 'DATE',
				'order'       => 'ASC',
				'meta_query'  => array(
					'relation' => 'AND',
					array( 'key' => '_ae_start', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ),
					array( 'key' => '_ae_end', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
				),
			)
		);

		$upcoming = get_posts(
			array(
				'post_type'   => 'artiey_exhibition',
				'post_status' => 'publish',
				'numberposts' => 5,
				'meta_key'    => '_ae_start',
				'orderby'     => 'meta_value',
				'meta_type'   => 'DATE',
				'order'       => 'ASC',
				'meta_query'  => array(
					array( 'key' => '_ae_start', 'value' => $today, 'compare' => '>', 'type' => 'DATE' ),
				),
			)
		);

		$no_date = new WP_Query(
			array(
				'post_type'      => 'artiey_exhibition',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => '_ae_start', 'compare' => 'NOT EXISTS' ) ),
			)
		);

		$ex_count  = (int) wp_count_posts( 'artiey_exhibition' )->publish;
		$gal_count = (int) wp_count_posts( 'artiey_gallery' )->publish;

		echo '<div dir="rtl">';
		echo '<p class="aap-s-45e6a6"><strong>' . esc_html( Artiey_AET_Jalali::to_persian_digits( $ex_count ) ) . '</strong> نمایشگاه و <strong>' . esc_html( Artiey_AET_Jalali::to_persian_digits( $gal_count ) ) . '</strong> گالری منتشرشده</p>';

		if ( (int) $no_date->found_posts > 0 ) {
			echo '<div class="aap-s-40d135">';
			echo esc_html( Artiey_AET_Jalali::to_persian_digits( (int) $no_date->found_posts ) ) . ' نمایشگاه منتشرشده تاریخ ندارد و در تایم‌لاین دیده نمی‌شود.';
			echo '</div>';
		}

		echo '<h4 class="aap-s-e29ab3">در حال برگزاری</h4>';
		self::dash_list( $now );
		echo '<h4 class="aap-s-e29ab3">به‌زودی</h4>';
		self::dash_list( $upcoming );

		echo '<p class="aap-s-b12c74">';
		echo '<a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=artiey_exhibition' ) ) . '">نمایشگاه جدید</a> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'post-new.php?post_type=artiey_gallery' ) ) . '">گالری جدید</a> ';
		if ( current_user_can( 'manage_options' ) ) {
			echo '<a class="button" href="' . esc_url( self::settings_url() ) . '">تنظیمات</a>';
		}
		echo '</p></div>';
	}
}
