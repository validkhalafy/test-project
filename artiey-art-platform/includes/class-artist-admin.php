<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin: full artist profile fields.
 */
final class Artiey_AET_Artist_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_artiey_artist', array( __CLASS__, 'save' ) );
		add_filter( 'manage_artiey_artist_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_artiey_artist_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function boxes() {
		add_meta_box( 'aeta_box', 'پروفایل هنرمند', array( __CLASS__, 'render' ), 'artiey_artist', 'normal', 'high' );
	}

	private static function val( $id, $key ) {
		return get_post_meta( $id, '_aa_' . $key, true );
	}

	/** True only for attachment records that are actually images. */
	private static function is_image_attachment( $id ) {
		$id = absint( $id );
		return $id > 0 && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id );
	}

	/** Normalize current or legacy media metadata into unique image attachment IDs. */
	private static function image_ids( $value, $limit = 60 ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,;\s]+/', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array();
		foreach ( $value as $candidate ) {
			$id = absint( $candidate );
			if ( $id && ! in_array( $id, $ids, true ) && self::is_image_attachment( $id ) ) {
				$ids[] = $id;
				if ( count( $ids ) >= absint( $limit ) ) {
					break;
				}
			}
		}
		return $ids;
	}

	private static function text( $name, $value, $label, $opts = array() ) {
		$o = wp_parse_args( $opts, array( 'dir' => 'rtl', 'placeholder' => '', 'hint' => '', 'type' => 'text' ) );
		echo '<div class="aetg-field"><label>' . esc_html( $label ) . '</label>';
		echo '<input type="' . esc_attr( $o['type'] ) . '" name="aeta[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" dir="' . esc_attr( $o['dir'] ) . '" placeholder="' . esc_attr( $o['placeholder'] ) . '">';
		if ( $o['hint'] ) {
			echo '<div class="aetg-hint">' . esc_html( $o['hint'] ) . '</div>';
		}
		echo '</div>';
	}

	public static function render( $post ) {
		wp_nonce_field( 'aet_art_save', 'aet_art_nonce' );
		$id = $post->ID;

		$tabs = apply_filters(
			'artiey_ap_artist_tabs',
			array(
				'identity' => 'شناسنامه',
				'career'   => 'سوابق',
				'social'   => 'شبکه‌های ارتباطی',
				'images'   => 'تصاویر و آثار',
				'media'    => 'مصاحبه و مطالب مرتبط',
			)
		);

		echo '<div class="aetg"><div class="aetg-tabs">';
		foreach ( $tabs as $key => $label ) {
			echo '<button type="button" class="aetg-tab" data-tab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</div>';

		echo '<div class="aetg-panel" data-panel="identity">';
		echo '<p class="aetg-hint">نام فارسی هنرمند همان «عنوان» است؛ بیوگرافی کامل را در ویرایشگر اصلی بنویسید و عکس هنرمند را از ستون کناری («عکس هنرمند») انتخاب کنید. سبک هنری را از کادر کناری انتخاب کنید.</p>';
		self::text( 'first_name', self::val( $id, 'first_name' ), 'نام (اختیاری)' );
		self::text( 'last_name', self::val( $id, 'last_name' ), 'نام خانوادگی (اختیاری)' );
		self::text( 'name_en', self::val( $id, 'name_en' ), 'نام لاتین', array( 'dir' => 'ltr', 'placeholder' => 'Artist Name' ) );
		self::text( 'birth', self::val( $id, 'birth' ), 'سال تولد (شمسی)', array( 'dir' => 'ltr', 'placeholder' => '1360', 'type' => 'number' ) );
		self::text( 'city', self::val( $id, 'city' ), 'شهر', array( 'placeholder' => 'تهران' ) );
		self::text( 'nationality', self::val( $id, 'nationality' ), 'ملیت / خاستگاه فرهنگی', array( 'placeholder' => 'ایرانی' ) );
		echo '<div class="aetg-field"><label>بیوگرافی کوتاه</label><textarea name="aeta[short_bio]" rows="3" maxlength="400">' . esc_textarea( self::val( $id, 'short_bio' ) ) . '</textarea><div class="aetg-hint">حداکثر ۴۰۰ حرف.</div></div>';
		echo '<div class="aetg-field"><label>بیانیه هنری</label><textarea name="aeta[statement]" rows="5" maxlength="3000">' . esc_textarea( self::val( $id, 'statement' ) ) . '</textarea><div class="aetg-hint">نگاه، دغدغه‌ها و رویکرد هنری را در چند جمله بنویسید.</div></div>';
		echo '</div>';

		echo '<div class="aetg-panel" data-panel="career">';
		self::text( 'mediums', self::val( $id, 'mediums' ), 'رسانه و تکنیک', array( 'placeholder' => 'رنگ روغن روی بوم، چیدمان' ) );
		echo '<div class="aetg-field"><label>تحصیلات (برای خط زمانی)</label><textarea name="aeta[education]" rows=4 placeholder="۱۳۹۵ - کارشناسی نقاشی، دانشگاه هنر&#10;۱۳۹۸ - کارشناسی ارشد هنرهای تجسمی">' . esc_textarea( self::val( $id, 'education' ) ) . '</textarea><div class="aetg-hint">هر دوره در یک خط؛ برای نمایش سال در خط زمانی از قالب «سال - شرح» استفاده کنید. متن‌های قدیمی حفظ می‌شوند.</div></div>';
		echo '<div class="aetg-field"><label>جوایز و افتخارات</label><textarea name="aeta[awards]" rows="6" placeholder="هر مورد در یک خط">' . esc_textarea( self::val( $id, 'awards' ) ) . '</textarea><div class="aetg-hint">این فهرست مستقل است و دیگر به‌صورت خودکار در خط زمانی تکرار نمی‌شود.</div></div>';
		echo '<div class="aetg-field"><label>رویدادهای مستقل خط زمانی حرفه‌ای</label><textarea name="aeta[timeline]" rows="5" placeholder="۱۴۰۰ - آغاز مجموعه جدید">' . esc_textarea( self::val( $id, 'timeline' ) ) . '</textarea><div class="aetg-hint">هر رویداد در یک خط؛ این بخش جدا از جوایز و تحصیلات است.</div></div>'; 
		echo '</div>';

		echo '<div class="aetg-panel" data-panel="social">';
		self::text( 'instagram', self::val( $id, 'instagram' ), 'اینستاگرام', array( 'dir' => 'ltr', 'placeholder' => 'username', 'hint' => 'نام کاربری یا لینک کامل پروفایل.' ) );
		self::text( 'telegram', self::val( $id, 'telegram' ), 'تلگرام', array( 'dir' => 'ltr', 'placeholder' => 'username' ) );
		self::text( 'website', self::val( $id, 'website' ), 'وب‌سایت', array( 'dir' => 'ltr', 'placeholder' => 'https://', 'type' => 'url' ) );
		self::text( 'email', self::val( $id, 'email' ), 'ایمیل (عمومی)', array( 'dir' => 'ltr', 'type' => 'email', 'hint' => 'در صفحه هنرمند نمایش داده می‌شود؛ اگر نمی‌خواهید عمومی باشد خالی بگذارید.' ) );
		self::text( 'phone', self::val( $id, 'phone' ), 'تلفن تماس (اختیاری)', array( 'dir' => 'ltr', 'type' => 'tel' ) );
		self::text( 'national_id', self::val( $id, 'national_id' ), 'شناسه ملی / کد شناسایی (فقط مدیریتی)', array( 'dir' => 'ltr', 'hint' => 'برای تطبیق سوابق و صدور گواهی؛ به عنوان اطلاعات عمومی نمایش داده نمی‌شود.' ) );
		echo '</div>';

		echo '<div class="aetg-panel" data-panel="media">';
		echo '<div class="aetg-field"><label>مصاحبه‌های ویدیویی</label><textarea name="aeta[interviews]" rows="6" placeholder="عنوان مصاحبه | https://www.youtube.com/watch?v=...">' . esc_textarea( self::val( $id, 'interviews' ) ) . '</textarea><div class="aetg-hint">هر مورد در یک خط و با قالب «عنوان | لینک». لینک‌های قابل‌تعبیه مانند YouTube و Vimeo در پلیر نمایش داده می‌شوند.</div></div>';
		echo '<div class="aetg-field"><label>مطالب و لینک‌های مرتبط</label><textarea name="aeta[related_media]" rows="6" placeholder="گفت‌وگو با هنرمند | https://example.com/article">' . esc_textarea( self::val( $id, 'related_media' ) ) . '</textarea><div class="aetg-hint">لینک مقاله، نقد، خبر یا محتوای رسانه‌ای را با همان قالب وارد کنید.</div></div>';
		echo '</div>';

		echo '<div class="aetg-panel" data-panel="images">';
		$cover = absint( self::val( $id, 'cover' ) );
		if ( ! self::is_image_attachment( $cover ) ) {
			$cover = 0;
		}
		echo '<div class="aetg-field aetg-media" data-multiple="0"><label>عکس کاور</label><div class="aetg-preview">' . ( $cover ? wp_get_attachment_image( $cover, 'medium' ) : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="aeta[cover]" value="' . esc_attr( $cover ) . '" class="aetg-media-input"><button type="button" class="button aetg-media-pick">انتخاب تصویر</button> <button type="button" class="button-link-delete aetg-media-clear">حذف</button><div class="aetg-hint">تصویر عریض بالای صفحه هنرمند (اختیاری).</div></div>';

		$works = self::image_ids( self::val( $id, 'works' ) );
		echo '<div class="aetg-field aetg-media" data-multiple="1"><label>نمونه آثار</label><div class="aetg-preview aetg-preview-multi">';
		foreach ( $works as $wid ) {
			echo wp_get_attachment_image( (int) $wid, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div><input type="hidden" name="aeta[works]" value="' . esc_attr( implode( ',', array_map( 'absint', $works ) ) ) . '" class="aetg-media-input"><button type="button" class="button aetg-media-pick">انتخاب تصاویر</button> <button type="button" class="button-link-delete aetg-media-clear">پاک کردن همه</button></div>';
		echo '</div>';

		foreach ( $tabs as $key => $label ) {
			if ( in_array( $key, array( 'identity', 'career', 'social', 'images' ), true ) ) {
				continue;
			}
			echo '<div class="aetg-panel" data-panel="' . esc_attr( $key ) . '">';
			do_action( 'artiey_ap_artist_panel_' . $key, $post );
			echo '</div>';
		}

		echo '</div>';
	}

	public static function save( $post_id ) {
		if ( 'artiey_artist' !== get_post_type( $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['aet_art_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aet_art_nonce'] ) ), 'aet_art_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Do not blank existing metadata if an external editor omits this meta box payload.
		if ( ! isset( $_POST['aeta'] ) || ! is_array( $_POST['aeta'] ) ) {
			return;
		}
		$in = wp_unslash( $_POST['aeta'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.

		$text = function ( $k ) use ( $in ) {
			return isset( $in[ $k ] ) && is_string( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		};

		update_post_meta( $post_id, '_aa_first_name', $text( 'first_name' ) );
		update_post_meta( $post_id, '_aa_last_name', $text( 'last_name' ) );
		update_post_meta( $post_id, '_aa_national_id', $text( 'national_id' ) );
		update_post_meta( $post_id, '_aa_phone', $text( 'phone' ) );
		update_post_meta( $post_id, '_aa_name_en', $text( 'name_en' ) );
		update_post_meta( $post_id, '_aa_city', $text( 'city' ) );
		update_post_meta( $post_id, '_aa_nationality', $text( 'nationality' ) );
		update_post_meta( $post_id, '_aa_mediums', $text( 'mediums' ) );
		update_post_meta( $post_id, '_aa_education', isset( $in['education'] ) && is_string( $in['education'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['education'] ), 0, 4000 ) : '' );
		update_post_meta( $post_id, '_aa_short_bio', isset( $in['short_bio'] ) && is_string( $in['short_bio'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['short_bio'] ), 0, 400 ) : '' );
		update_post_meta( $post_id, '_aa_statement', isset( $in['statement'] ) && is_string( $in['statement'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['statement'] ), 0, 3000 ) : '' );
		update_post_meta( $post_id, '_aa_awards', isset( $in['awards'] ) && is_string( $in['awards'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['awards'] ), 0, 4000 ) : '' );
		update_post_meta( $post_id, '_aa_timeline', isset( $in['timeline'] ) && is_string( $in['timeline'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['timeline'] ), 0, 4000 ) : '' );
		update_post_meta( $post_id, '_aa_interviews', isset( $in['interviews'] ) && is_string( $in['interviews'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['interviews'] ), 0, 8000 ) : '' );
		update_post_meta( $post_id, '_aa_related_media', isset( $in['related_media'] ) && is_string( $in['related_media'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['related_media'] ), 0, 8000 ) : '' );
		update_post_meta( $post_id, '_aa_instagram', Artiey_AET_Gallery::clean_handle( $text( 'instagram' ) ) );
		update_post_meta( $post_id, '_aa_telegram', Artiey_AET_Gallery::clean_handle( $text( 'telegram' ) ) );
		update_post_meta( $post_id, '_aa_website', isset( $in['website'] ) && is_string( $in['website'] ) ? esc_url_raw( trim( $in['website'] ) ) : '' );
		update_post_meta( $post_id, '_aa_email', isset( $in['email'] ) && is_string( $in['email'] ) ? sanitize_email( $in['email'] ) : '' );

		$birth = isset( $in['birth'] ) ? (int) Artiey_AET_Jalali::to_latin_digits( (string) $in['birth'] ) : 0;
		update_post_meta( $post_id, '_aa_birth', ( $birth >= 1200 && $birth <= 1600 ) ? $birth : '' );

		$cover = isset( $in['cover'] ) ? absint( $in['cover'] ) : 0;
		update_post_meta( $post_id, '_aa_cover', self::is_image_attachment( $cover ) ? $cover : '' );

		$works = isset( $in['works'] ) && is_string( $in['works'] ) ? self::image_ids( $in['works'], 60 ) : array();
		update_post_meta( $post_id, '_aa_works', $works );

		// If a user fills the dedicated first/last-name fields but leaves the editor title blank,
		// create a usable canonical title instead of a nameless artist record.
		$current_post = get_post( $post_id );
		$fallback_title = trim( $text( 'first_name' ) . ' ' . $text( 'last_name' ) );
		if ( $current_post instanceof WP_Post && trim( (string) $current_post->post_title ) === '' && $fallback_title !== '' ) {
			remove_action( 'save_post_artiey_artist', array( __CLASS__, 'save' ) );
			wp_update_post(
				wp_slash(
					array(
						'ID'         => $post_id,
						'post_title' => $fallback_title,
						'post_name'  => Artiey_AET_Slug::from( $fallback_title, 'artist' ),
					)
				)
			);
			add_action( 'save_post_artiey_artist', array( __CLASS__, 'save' ) );
		}
		if ( class_exists( 'Artiey_AET_Artist' ) ) { Artiey_AET_Artist::invalidate_profile( $post_id ); }
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $key => $label ) {
			if ( 'title' === $key ) {
				$new['aeta_photo'] = 'عکس';
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['aeta_en']   = 'نام لاتین';
				$new['aeta_city'] = 'شهر';
				$new['aeta_gal']  = 'گالری‌ها';
			}
		}
		return $new;
	}

	public static function column_content( $col, $post_id ) {
		switch ( $col ) {
			case 'aeta_photo':
				echo has_post_thumbnail( $post_id ) ? get_the_post_thumbnail( $post_id, array( 48, 48 ) ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'aeta_en':
				$v = get_post_meta( $post_id, '_aa_name_en', true );
				echo $v ? '<span dir="ltr">' . esc_html( $v ) . '</span>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'aeta_city':
				$v = get_post_meta( $post_id, '_aa_city', true );
				echo $v ? esc_html( $v ) : '—';
				break;
			case 'aeta_gal':
				$list = Artiey_AET_Artist::galleries_of( $post_id );
				echo $list ? esc_html( implode( '، ', wp_list_pluck( $list, 'post_title' ) ) ) : '—';
				break;
		}
	}
}
