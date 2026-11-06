<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types: artiey_gallery (logo = featured image) and artiey_exhibition.
 */
final class Artiey_AET_Post_Types {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_artiey_exhibition', array( __CLASS__, 'save_exhibition' ) );
		add_filter( 'manage_artiey_exhibition_posts_columns', array( __CLASS__, 'exhibition_columns' ) );
		add_action( 'manage_artiey_exhibition_posts_custom_column', array( __CLASS__, 'exhibition_column_content' ), 10, 2 );
		add_filter( 'manage_edit-artiey_exhibition_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_sorting' ) );
		add_action( 'admin_notices', array( __CLASS__, 'date_notice' ) );
	}

	/** Shown after saving an exhibition whose date could not be understood. */
	public static function date_notice() {
		$key = 'aet_bad_date_' . get_current_user_id();
		$msg = get_transient( $key );
		if ( $msg ) {
			delete_transient( $key );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	public static function register() {
		register_post_type(
			'artiey_gallery',
			array(
				'labels'        => array(
					'name'               => 'گالری‌ها',
					'singular_name'      => 'گالری',
					'add_new'            => 'افزودن گالری',
					'add_new_item'       => 'افزودن گالری جدید',
					'edit_item'          => 'ویرایش گالری',
					'new_item'           => 'گالری جدید',
					'view_item'          => 'مشاهده گالری',
					'search_items'       => 'جستجوی گالری',
					'not_found'          => 'گالری‌ای یافت نشد',
					'all_items'          => 'همه گالری‌ها',
					'menu_name'          => 'گالری‌ها',
					'featured_image'     => 'لوگوی گالری',
					'set_featured_image' => 'انتخاب لوگوی گالری',
					'remove_featured_image' => 'حذف لوگو',
					'use_featured_image' => 'استفاده به‌عنوان لوگو',
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_in_rest'        => true,
				'show_in_menu'  => 'artiey-platform',
				'has_archive'   => Artiey_AET_Slug::base( 'gallery' ),
				'rewrite'       => array( 'slug' => Artiey_AET_Slug::base( 'gallery' ) ),
				'menu_icon'     => 'dashicons-store',
				'menu_position' => 26,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions', 'custom-fields' ),
			)
		);

		register_post_type(
			'artiey_exhibition',
			array(
				'labels'        => array(
					'name'          => 'نمایشگاه‌ها',
					'singular_name' => 'نمایشگاه',
					'add_new'       => 'افزودن نمایشگاه',
					'add_new_item'  => 'افزودن نمایشگاه جدید',
					'edit_item'     => 'ویرایش نمایشگاه',
					'new_item'      => 'نمایشگاه جدید',
					'view_item'     => 'مشاهده نمایشگاه',
					'search_items'  => 'جستجوی نمایشگاه',
					'not_found'     => 'نمایشگاهی یافت نشد',
					'all_items'     => 'همه نمایشگاه‌ها',
					'menu_name'     => 'نمایشگاه‌ها',
					'featured_image'        => 'پوستر نمایشگاه',
					'set_featured_image'    => 'انتخاب پوستر نمایشگاه',
					'remove_featured_image' => 'حذف پوستر',
					'use_featured_image'    => 'استفاده به‌عنوان پوستر',
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_in_rest'        => true,
				'show_in_menu'  => 'artiey-platform',
				'has_archive'   => Artiey_AET_Slug::base( 'exhibition' ),
				'rewrite'       => array( 'slug' => Artiey_AET_Slug::base( 'exhibition' ) ),
				'menu_icon'     => 'dashicons-calendar-alt',
				'menu_position' => 27,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions', 'custom-fields' ),
			)
		);

		register_post_type(
			'artiey_artist',
			array(
				'labels'        => array(
					'name'                  => 'هنرمندان',
					'singular_name'         => 'هنرمند',
					'add_new'               => 'افزودن هنرمند',
					'add_new_item'          => 'افزودن هنرمند جدید',
					'edit_item'             => 'ویرایش هنرمند',
					'new_item'              => 'هنرمند جدید',
					'view_item'             => 'مشاهده هنرمند',
					'search_items'          => 'جستجوی هنرمند',
					'not_found'             => 'هنرمندی یافت نشد',
					'all_items'             => 'همه هنرمندان',
					'menu_name'             => 'هنرمندان',
					'featured_image'        => 'عکس هنرمند',
					'set_featured_image'    => 'انتخاب عکس هنرمند',
					'remove_featured_image' => 'حذف عکس',
					'use_featured_image'    => 'استفاده به‌عنوان عکس هنرمند',
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_in_rest'        => true,
				'show_in_menu'  => 'artiey-platform',
				// The managed /artists/ WordPress page is the canonical content archive; keep has_archive false. Singles use /artists/{slug}/.
				// so it coexists with single artist URLs at /artists/{slug}/ without a duplicate archive.
				'has_archive'   => false,
				'rewrite'       => array( 'slug' => Artiey_AET_Slug::base( 'artist' ) ),
				'menu_icon'     => 'dashicons-admin-users',
				'menu_position' => 28,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions', 'custom-fields' ),
			)
		);

		register_post_type(
			'artiey_artwork',
			array(
				'labels'        => array(
					'name'                  => 'آثار هنری',
					'singular_name'         => 'اثر هنری',
					'add_new'               => 'افزودن اثر',
					'add_new_item'          => 'افزودن اثر جدید',
					'edit_item'             => 'ویرایش اثر',
					'new_item'              => 'اثر جدید',
					'view_item'             => 'مشاهده اثر',
					'search_items'          => 'جستجوی اثر',
					'not_found'             => 'اثری یافت نشد',
					'all_items'             => 'همه آثار',
					'menu_name'             => 'آثار هنری',
					'featured_image'        => 'تصویر اثر',
					'set_featured_image'    => 'انتخاب تصویر اثر',
					'remove_featured_image' => 'حذف تصویر',
					'use_featured_image'    => 'استفاده به‌عنوان تصویر اثر',
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_in_rest'        => true,
				'show_in_menu'  => 'artiey-platform',
				'has_archive'   => Artiey_AET_Slug::base( 'artwork' ),
				'rewrite'       => array( 'slug' => Artiey_AET_Slug::base( 'artwork' ) ),
				'menu_icon'     => 'dashicons-format-image',
				'menu_position' => 29,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions', 'custom-fields' ),
			)
		);

		register_taxonomy(
			'artiey_style',
			array( 'artiey_gallery', 'artiey_artist', 'artiey_exhibition', 'artiey_artwork' ),
			array(
				'labels'            => array(
					'name'                       => 'سبک‌های هنری',
					'singular_name'              => 'سبک هنری',
					'menu_name'                  => 'سبک هنری',
					'all_items'                  => 'همه سبک‌ها',
					'edit_item'                  => 'ویرایش سبک',
					'add_new_item'               => 'افزودن سبک هنری',
					'search_items'               => 'جستجوی سبک',
					'popular_items'              => 'سبک‌های پرکاربرد',
					'separate_items_with_commas' => 'سبک‌ها را با ویرگول جدا کنید',
					'add_or_remove_items'        => 'افزودن یا حذف سبک',
					'choose_from_most_used'      => 'انتخاب از سبک‌های پرکاربرد',
				),
				'hierarchical'      => false,
				'public'            => true,
				'publicly_queryable' => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => Artiey_AET_Slug::base( 'style' ) ),
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box( 'aet_exhibition_box', 'اطلاعات نمایشگاه', array( __CLASS__, 'render_exhibition_box' ), 'artiey_exhibition', 'normal', 'high' );
	}

	/* ---------------------------------------------------------------- */
	/* Exhibition meta box                                               */
	/* ---------------------------------------------------------------- */

	public static function render_exhibition_box( $post ) {
		wp_nonce_field( 'aet_ex_save', 'aet_ex_nonce' );

		$gallery_id = (int) get_post_meta( $post->ID, '_ae_gallery', true );
		$type       = get_post_meta( $post->ID, '_ae_type', true );
		$artist     = get_post_meta( $post->ID, '_ae_artist', true );
		$start      = Artiey_AET_Jalali::ymd_to_jalali( get_post_meta( $post->ID, '_ae_start', true ) );
		$end        = Artiey_AET_Jalali::ymd_to_jalali( get_post_meta( $post->ID, '_ae_end', true ) );
		$link       = get_post_meta( $post->ID, '_ae_link', true );
		$opening    = get_post_meta( $post->ID, '_ae_opening', true );
		$curator    = get_post_meta( $post->ID, '_ae_curator', true );
		$admission  = get_post_meta( $post->ID, '_ae_admission', true );
		$ex_artists = get_post_meta( $post->ID, '_ae_artists', true );

		$galleries = get_posts(
			array(
				'post_type'   => 'artiey_gallery',
				'post_status' => array( 'publish', 'draft', 'private' ),
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		?>
		<style>
			.aet-field{margin:0 0 14px}
			.aet-field label{display:block;font-weight:600;margin-bottom:4px}
			.aet-field input[type=text],.aet-field input[type=url],.aet-field select{width:100%;max-width:520px}
			.aet-row2{display:flex;gap:20px;flex-wrap:wrap}
			.aet-row2 .aet-field{flex:1;min-width:200px}
			.aet-hint{color:#666;font-size:12px;margin-top:3px}
		</style>

		<div class="aet-field">
			<label for="aet_gallery">گالری برگزارکننده</label>
			<select name="aet_gallery" id="aet_gallery">
				<option value="0">— انتخاب گالری —</option>
				<?php foreach ( $galleries as $g ) : ?>
					<option value="<?php echo esc_attr( $g->ID ); ?>" <?php selected( $gallery_id, $g->ID ); ?>><?php echo esc_html( $g->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<div class="aet-hint">اگر گالری در فهرست نیست، ابتدا از منوی «گالری‌ها» آن را بسازید و لوگو را به‌عنوان تصویر شاخص قرار دهید.</div>
		</div>

		<div class="aet-row2">
			<div class="aet-field">
				<label for="aet_start">تاریخ شروع (شمسی)</label>
				<input type="text" name="aet_start" id="aet_start" value="<?php echo esc_attr( $start ); ?>" placeholder="1405/08/02" dir="ltr" autocomplete="off">
			</div>
			<div class="aet-field">
				<label for="aet_end">تاریخ پایان (شمسی)</label>
				<input type="text" name="aet_end" id="aet_end" value="<?php echo esc_attr( $end ); ?>" placeholder="1405/08/16" dir="ltr" autocomplete="off">
			</div>
		</div>
		<div class="aet-hint aap-s-7dfd2f">فرمت: سال/ماه/روز، مثلاً 1405/08/02 (ارقام فارسی هم پذیرفته می‌شود). اگر تاریخ پایان خالی باشد، نمایشگاه یک‌روزه در نظر گرفته می‌شود.</div>

		<div class="aet-row2">
			<div class="aet-field">
				<label for="aet_type">نوع نمایشگاه</label>
				<input type="text" name="aet_type" id="aet_type" value="<?php echo esc_attr( $type ); ?>" placeholder="نمایشگاه انفرادی نقاشی">
			</div>
			<div class="aet-field">
				<label for="aet_artist">هنرمند / هنرمندان</label>
				<input type="text" name="aet_artist" id="aet_artist" value="<?php echo esc_attr( $artist ); ?>" placeholder="فرناز باغبان">
			</div>
		</div>

		<div class="aet-field">
			<label for="aet_opening">زمان افتتاحیه (اختیاری)</label>
			<input type="text" name="aet_opening" id="aet_opening" value="<?php echo esc_attr( $opening ); ?>" placeholder="جمعه ۲ آبان، ساعت ۱۷ تا ۲۰">
		</div>
		<div class="aet-row2">
			<div class="aet-field"><label for="aet_curator">کیوریتور / گردآورنده</label><input type="text" name="aet_curator" id="aet_curator" value="<?php echo esc_attr( $curator ); ?>" placeholder="نام کیوریتور"></div>
			<div class="aet-field"><label for="aet_admission">ورود و بازدید</label><input type="text" name="aet_admission" id="aet_admission" value="<?php echo esc_attr( $admission ); ?>" placeholder="رایگان / با بلیت / با هماهنگی"></div>
		</div>

		<div class="aet-field">
			<label>هنرمندان این نمایشگاه (از فهرست هنرمندان)</label>
			<?php Artiey_AET_Gallery_Admin::artist_checklist( 'aet_artists', is_array( $ex_artists ) ? $ex_artists : array() ); ?>
			<div class="aet-hint">اگر کادر «هنرمند / هنرمندان» بالا خالی باشد، نام این هنرمندان در تایم‌لاین نوشته می‌شود. پوستر نمایشگاه را از ستون کناری («پوستر نمایشگاه») انتخاب کنید.</div>
		</div>

		<div class="aet-field">
			<label for="aet_link">لینک اختصاصی (اختیاری)</label>
			<input type="url" name="aet_link" id="aet_link" value="<?php echo esc_attr( $link ); ?>" dir="ltr" placeholder="https://">
			<div class="aet-hint">اگر خالی باشد، نوار نمایشگاه به صفحه همین نمایشگاه در سایت لینک می‌شود. عنوان نوار همان «عنوان» نوشته است.</div>
		</div>
		<?php
	}

	public static function save_exhibition( $post_id ) {
		if ( ! isset( $_POST['aet_ex_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aet_ex_nonce'] ) ), 'aet_ex_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$gallery   = isset( $_POST['aet_gallery'] ) ? absint( $_POST['aet_gallery'] ) : 0;
		if ( $gallery && 'artiey_gallery' !== get_post_type( $gallery ) ) {
			$gallery = 0;
		}
		$raw_start = isset( $_POST['aet_start'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['aet_start'] ) ) ) : '';
		$raw_end   = isset( $_POST['aet_end'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['aet_end'] ) ) ) : '';
		$start     = Artiey_AET_Jalali::jalali_to_ymd( $raw_start );
		$end       = Artiey_AET_Jalali::jalali_to_ymd( $raw_end );
		$date_valid = true;

		if ( ( '' !== $raw_start && ! $start ) || ( '' !== $raw_end && ! $end ) ) {
			set_transient( 'aet_bad_date_' . get_current_user_id(), 'تاریخ نمایشگاه معتبر نبود و ذخیره نشد. از فرمت سال/ماه/روز مثل 1405/08/02 استفاده کنید (ماه‌های ۷ تا ۱۲ حداکثر ۳۰ روز دارند).', 60 );
			$date_valid = false;
		} elseif ( '' === $raw_start ) {
			set_transient( 'aet_bad_date_' . get_current_user_id(), 'تاریخ شروع خالی است؛ تا تاریخ ثبت نشود، نمایشگاه در تایم‌لاین نمایش داده نمی‌شود.', 60 );
		}

		if ( $start && $end && $end < $start ) {
			set_transient( 'aet_bad_date_' . get_current_user_id(), 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد؛ تاریخ‌های قبلی حفظ شدند.', 60 );
			$date_valid = false;
		}

		if ( $start && ! $end ) {
			$end = $start;
		}

		update_post_meta( $post_id, '_ae_gallery', $gallery );
		update_post_meta( $post_id, '_ae_type', isset( $_POST['aet_type'] ) ? sanitize_text_field( wp_unslash( $_POST['aet_type'] ) ) : '' );
		update_post_meta( $post_id, '_ae_artist', isset( $_POST['aet_artist'] ) ? sanitize_text_field( wp_unslash( $_POST['aet_artist'] ) ) : '' );
		update_post_meta( $post_id, '_ae_link', isset( $_POST['aet_link'] ) ? esc_url_raw( wp_unslash( $_POST['aet_link'] ) ) : '' );
		update_post_meta( $post_id, '_ae_opening', isset( $_POST['aet_opening'] ) ? sanitize_text_field( wp_unslash( $_POST['aet_opening'] ) ) : '' );
		update_post_meta( $post_id, '_ae_curator', isset( $_POST['aet_curator'] ) ? sanitize_text_field( wp_unslash( $_POST['aet_curator'] ) ) : '' );
		update_post_meta( $post_id, '_ae_admission', isset( $_POST['aet_admission'] ) ? sanitize_text_field( wp_unslash( $_POST['aet_admission'] ) ) : '' );

		$ex_artists = array();
		if ( isset( $_POST['aet_artists'] ) && is_array( $_POST['aet_artists'] ) ) {
			foreach ( array_filter( array_map( 'absint', wp_unslash( $_POST['aet_artists'] ) ) ) as $aid ) {
				if ( 'artiey_artist' === get_post_type( $aid ) ) {
					$ex_artists[] = $aid;
				}
			}
		}
		update_post_meta( $post_id, '_ae_artists', $ex_artists );

		if ( $date_valid ) {
			if ( $start ) {
				update_post_meta( $post_id, '_ae_start', $start );
				update_post_meta( $post_id, '_ae_end', $end );
			} else {
				delete_post_meta( $post_id, '_ae_start' );
				delete_post_meta( $post_id, '_ae_end' );
			}
		}
	}

	/* ---------------------------------------------------------------- */
	/* Admin list columns                                                */
	/* ---------------------------------------------------------------- */

	public static function exhibition_columns( $cols ) {
		$new = array();
		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['aet_gallery'] = 'گالری';
				$new['aet_start']   = 'شروع';
				$new['aet_end']     = 'پایان';
			}
		}
		return $new;
	}

	public static function exhibition_column_content( $col, $post_id ) {
		if ( 'aet_gallery' === $col ) {
			$gid = (int) get_post_meta( $post_id, '_ae_gallery', true );
			echo $gid ? esc_html( get_the_title( $gid ) ) : '—';
		} elseif ( 'aet_start' === $col ) {
			$d = Artiey_AET_Jalali::format_long( get_post_meta( $post_id, '_ae_start', true ) );
			echo $d ? esc_html( $d ) : '—';
		} elseif ( 'aet_end' === $col ) {
			$d = Artiey_AET_Jalali::format_long( get_post_meta( $post_id, '_ae_end', true ) );
			echo $d ? esc_html( $d ) : '—';
		}
	}

	public static function sortable_columns( $cols ) {
		$cols['aet_start'] = 'aet_start';
		$cols['aet_end']   = 'aet_end';
		return $cols;
	}

	public static function admin_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		if ( 'aet_start' === $orderby ) {
			$query->set( 'meta_key', '_ae_start' );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'aet_end' === $orderby ) {
			$query->set( 'meta_key', '_ae_end' );
			$query->set( 'orderby', 'meta_value' );
		}
	}
}
