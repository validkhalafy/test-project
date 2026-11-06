<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens for galleries (full profile) and artists.
 */
final class Artiey_AET_Gallery_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_artiey_gallery', array( __CLASS__, 'save_gallery' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_filter( 'manage_artiey_gallery_posts_columns', array( __CLASS__, 'gallery_columns' ) );
		add_action( 'manage_artiey_gallery_posts_custom_column', array( __CLASS__, 'gallery_column_content' ), 10, 2 );
	}

	public static function boxes() {
		add_meta_box( 'aetg_box', 'پروفایل گالری', array( __CLASS__, 'render_gallery' ), 'artiey_gallery', 'normal', 'high' );
	}

	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'artiey_gallery', 'artiey_exhibition', 'artiey_artist', 'artiey_artwork' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'artiey-aet-gadmin', AET_URL . 'assets/css/gallery-admin.css', array(), AET_VERSION );

		// Leaflet is used only by the gallery location map. Keep it optional and
		// out of the media-picker dependencies: a blocked or slow CDN must not
		// prevent cover / portfolio selection from working.
		if ( 'artiey_gallery' === $screen->post_type ) {
			wp_enqueue_style( 'aet-leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css', array(), '1.9.4' );
			wp_enqueue_script( 'aet-leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js', array(), '1.9.4', true );
		}
		wp_enqueue_script( 'artiey-aet-gadmin', AET_URL . 'assets/js/gallery-admin.js', array( 'jquery' ), AET_VERSION, true );
	}

	/* ---------------------------------------------------------------- */
	/* Shared field helpers                                              */
	/* ---------------------------------------------------------------- */

	private static function val( $id, $key ) {
		return get_post_meta( $id, '_ag_' . $key, true );
	}

	private static function text( $name, $value, $label, $opts = array() ) {
		$o = wp_parse_args(
			$opts,
			array(
				'dir'         => 'rtl',
				'placeholder' => '',
				'hint'        => '',
				'type'        => 'text',
			)
		);
		echo '<div class="aetg-field"><label>' . esc_html( $label ) . '</label>';
		echo '<input type="' . esc_attr( $o['type'] ) . '" name="aetg[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" dir="' . esc_attr( $o['dir'] ) . '" placeholder="' . esc_attr( $o['placeholder'] ) . '">';
		if ( $o['hint'] ) {
			echo '<div class="aetg-hint">' . esc_html( $o['hint'] ) . '</div>';
		}
		echo '</div>';
	}

	private static function media_single( $name, $attachment_id, $label, $hint = '' ) {
		$img = $attachment_id ? wp_get_attachment_image( $attachment_id, 'medium' ) : '';
		echo '<div class="aetg-field aetg-media" data-multiple="0"><label>' . esc_html( $label ) . '</label>';
		echo '<div class="aetg-preview">' . $img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="aetg[' . esc_attr( $name ) . ']" value="' . esc_attr( (int) $attachment_id ) . '" class="aetg-media-input">';
		echo '<button type="button" class="button aetg-media-pick">انتخاب تصویر</button> ';
		echo '<button type="button" class="button-link-delete aetg-media-clear">حذف</button>';
		if ( $hint ) {
			echo '<div class="aetg-hint">' . esc_html( $hint ) . '</div>';
		}
		echo '</div>';
	}

	private static function media_multi( $name, $ids, $label, $hint = '' ) {
		echo '<div class="aetg-field aetg-media" data-multiple="1"><label>' . esc_html( $label ) . '</label>';
		echo '<div class="aetg-preview aetg-preview-multi">';
		foreach ( (array) $ids as $aid ) {
			$img = wp_get_attachment_image( $aid, 'thumbnail' );
			if ( $img ) {
				echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo '</div>';
		echo '<input type="hidden" name="aetg[' . esc_attr( $name ) . ']" value="' . esc_attr( implode( ',', array_map( 'absint', (array) $ids ) ) ) . '" class="aetg-media-input">';
		echo '<button type="button" class="button aetg-media-pick">انتخاب تصاویر</button> ';
		echo '<button type="button" class="button-link-delete aetg-media-clear">پاک کردن همه</button>';
		if ( $hint ) {
			echo '<div class="aetg-hint">' . esc_html( $hint ) . '</div>';
		}
		echo '</div>';
	}

	/**
	 * Repeatable rows editor (add / remove / move) used for critics, press and team.
	 * Every field is posted as aetg[{name}][{row}][{key}] and re-sanitised in save_gallery().
	 *
	 * @param array<int,array<string,string>> $rows
	 * @param array<string,array<string,mixed>> $fields
	 */
	private static function repeater( $name, $label, $hint, $rows, $fields, $max, $add_label ) {
		echo '<div class="aetg-field aetg-repeater" data-max="' . (int) $max . '" data-name="' . esc_attr( $name ) . '">';
		echo '<label>' . esc_html( $label ) . ' <span class="aetg-rep-count">(' . esc_html( (string) count( $rows ) ) . '/' . (int) $max . ')</span></label>';
		if ( $hint ) {
			echo '<div class="aetg-hint">' . esc_html( $hint ) . '</div>';
		}
		echo '<div class="aetg-rep-rows">';
		foreach ( array_values( $rows ) as $i => $row ) {
			self::repeater_row( $name, (string) $i, $row, $fields );
		}
		echo '</div>';
		echo '<template class="aetg-rep-tpl">';
		self::repeater_row( $name, '__i__', array(), $fields );
		echo '</template>';
		echo '<button type="button" class="button aetg-rep-add">+ ' . esc_html( $add_label ) . '</button>';
		echo '<input type="hidden" name="aetg[' . esc_attr( $name ) . '_present]" value="1">';
		echo '</div>';
	}

	private static function repeater_row( $name, $index, $row, $fields ) {
		echo '<div class="aetg-rep-row">';
		echo '<div class="aetg-rep-tools"><span class="aetg-rep-handle" aria-hidden="true">⋮⋮</span>';
		echo '<button type="button" class="button-link aetg-rep-up" aria-label="انتقال به بالا">▲</button>';
		echo '<button type="button" class="button-link aetg-rep-down" aria-label="انتقال به پایین">▼</button>';
		echo '<button type="button" class="button-link-delete aetg-rep-remove">حذف</button></div>';
		echo '<div class="aetg-rep-fields">';
		foreach ( $fields as $key => $f ) {
			$field_name = 'aetg[' . $name . '][' . $index . '][' . $key . ']';
			$value      = isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
			$type       = isset( $f['type'] ) ? $f['type'] : 'text';
			echo '<div class="aetg-rep-field' . ( ! empty( $f['wide'] ) || 'image' === $type ? ' is-wide' : '' ) . '"><label>' . esc_html( $f['label'] );
			if ( 'textarea' === $type ) {
				echo '<textarea name="' . esc_attr( $field_name ) . '" rows="4">' . esc_textarea( $value ) . '</textarea>';
			} elseif ( 'image' === $type ) {
				$aid = absint( $value );
				$img = $aid ? wp_get_attachment_image( $aid, 'thumbnail' ) : '';
				echo '</label><div class="aetg-media aetg-media-inline" data-multiple="0"><div class="aetg-preview">' . $img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<input type="hidden" class="aetg-media-input" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $aid ? (string) $aid : '' ) . '">';
				echo '<button type="button" class="button button-small aetg-media-pick">انتخاب تصویر</button> <button type="button" class="button-link-delete aetg-media-clear">حذف</button></div></div>';
				continue;
			} else {
				echo '<input type="' . esc_attr( in_array( $type, array( 'url', 'text' ), true ) ? $type : 'text' ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '"'
					. ( ! empty( $f['dir'] ) ? ' dir="' . esc_attr( $f['dir'] ) . '"' : '' )
					. ( ! empty( $f['placeholder'] ) ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '' ) . '>';
			}
			echo '</label></div>';
		}
		echo '</div></div>';
	}

	/**
	 * Sanitise one posted repeater list.
	 * @param array<string,string> $schema key => text|textarea|url|image
	 * @return array<int,array<string,string|int>>
	 */
	public static function sanitize_rows( $raw, array $schema, $max, array $required ) {
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $index => $row ) {
			if ( '__i__' === (string) $index || ! is_array( $row ) ) {
				continue;
			}
			$clean = array();
			foreach ( $schema as $key => $type ) {
				$v = isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? (string) $row[ $key ] : '';
				switch ( $type ) {
					case 'textarea':
						$clean[ $key ] = Artiey_AET_Text::substr( sanitize_textarea_field( $v ), 0, 4000 );
						break;
					case 'url':
						$url           = esc_url_raw( trim( $v ), array( 'http', 'https' ) );
						$clean[ $key ] = $url ? $url : '';
						break;
					case 'image':
						$aid           = absint( $v );
						$clean[ $key ] = ( $aid && 'attachment' === get_post_type( $aid ) && wp_attachment_is_image( $aid ) ) ? $aid : '';
						break;
					default:
						$clean[ $key ] = Artiey_AET_Text::substr( sanitize_text_field( $v ), 0, 200 );
				}
			}
			$ok = true;
			foreach ( $required as $req ) {
				if ( '' === trim( (string) $clean[ $req ] ) ) {
					$ok = false;
				}
			}
			if ( ! $ok ) {
				continue;
			}
			$out[] = $clean;
			if ( count( $out ) >= (int) $max ) {
				break;
			}
		}
		return $out;
	}

	/** Searchable checkbox list of artists. */
	public static function artist_checklist( $field_name, $selected ) {
		$artists  = get_posts(
			array(
				'post_type'   => 'artiey_artist',
				'post_status' => array( 'publish', 'draft', 'private' ),
				'numberposts' => 500,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$selected = array_map( 'absint', (array) $selected );

		echo '<div class="aetg-checklist-wrap">';
		if ( ! $artists ) {
			echo '<p class="aetg-hint">هنوز هنرمندی ثبت نشده است. از منوی «هنرمندان» هنرمند بسازید.</p></div>';
			return;
		}
		echo '<input type="search" class="aetg-filter aap-s-a0fcd2" placeholder="جستجوی هنرمند...">';
		echo '<div class="aetg-checklist">';
		foreach ( $artists as $a ) {
			echo '<label><input type="checkbox" name="' . esc_attr( $field_name ) . '[]" value="' . esc_attr( $a->ID ) . '" ' . checked( in_array( $a->ID, $selected, true ), true, false ) . '> <span>' . esc_html( $a->post_title ) . '</span></label>';
		}
		echo '</div></div>';
	}

	/* ---------------------------------------------------------------- */
	/* Gallery box                                                       */
	/* ---------------------------------------------------------------- */

	public static function render_gallery( $post ) {
		wp_nonce_field( 'aet_gal_save', 'aet_gal_nonce' );
		$id = $post->ID;

		$hours = Artiey_AET_Gallery::normalize_hours( self::val( $id, 'hours' ) );
		$color = self::val( $id, 'color' );
		$stat  = self::val( $id, 'status' );

		$tabs = apply_filters(
			'artiey_ap_gallery_tabs',
			array(
				'identity'  => 'شناسنامه',
				'statement' => 'بیانیه و منتقدان',
				'press'     => 'رسانه، جوایز و تیم',
				'contact'   => 'تماس و شبکه‌ها',
				'location' => 'آدرس و نقشه',
				'hours'    => 'وضعیت و ساعات کاری',
				'images'   => 'تصاویر',
				'artists'  => 'هنرمندان',
				'look'     => 'ظاهر',
			)
		);

		echo '<div class="aetg">';
		echo '<div class="aetg-tabs">';
		foreach ( $tabs as $key => $label ) {
			echo '<button type="button" class="aetg-tab" data-tab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</div>';

		// Identity.
		echo '<div class="aetg-panel" data-panel="identity">';
		echo '<p class="aetg-hint">نام فارسی گالری همان «عنوان» نوشته در بالای صفحه است. بیوگرافی کامل را در ویرایشگر اصلی پایین عنوان بنویسید و لوگو را از ستون کناری («لوگوی گالری») انتخاب کنید.</p>';
		self::text( 'name_en', self::val( $id, 'name_en' ), 'نام لاتین گالری', array( 'dir' => 'ltr', 'placeholder' => 'Etemad Gallery' ) );
		self::text( 'founded', self::val( $id, 'founded' ), 'سال تأسیس (شمسی)', array( 'dir' => 'ltr', 'placeholder' => '1385', 'type' => 'number' ) );
		self::text( 'city', self::val( $id, 'city' ), 'شهر', array( 'placeholder' => 'تهران' ) );
		echo '<div class="aetg-field"><label>نوع گالری</label><select name="aetg[venue_type]">';
		$venue_types = array( '' => 'انتخاب نشده', 'physical' => 'گالری فیزیکی', 'online' => 'گالری آنلاین', 'hybrid' => 'فیزیکی و آنلاین' );
		foreach ( $venue_types as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( self::val( $id, 'venue_type' ), $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><div class="aetg-hint">این مشخصه در پروفایل عمومی و فیلتر فهرست گالری‌ها استفاده می‌شود.</div></div>';
		echo '<div class="aetg-field"><label>اطلاعات بازدیدکنندگان</label><textarea name="aetg[visitor_info]" rows="3" maxlength="1200">' . esc_textarea( self::val( $id, 'visitor_info' ) ) . '</textarea><div class="aetg-hint">شرایط دسترسی، مناسب‌سازی، پارکینگ یا نکته‌های مهم مراجعه را بنویسید.</div></div>';
		echo '<div class="aetg-field"><label>بیوگرافی کوتاه</label>';
		echo '<textarea name="aetg[short_bio]" rows="3" maxlength="400">' . esc_textarea( self::val( $id, 'short_bio' ) ) . '</textarea>';
		echo '<div class="aetg-hint">حداکثر ۴۰۰ حرف؛ در بالای صفحه گالری و کارت‌ها نمایش داده می‌شود.</div></div>';
		echo '<p class="aetg-hint">سبک هنری گالری را از کادر کناری «سبک هنری» انتخاب یا اضافه کنید (چند مورد ممکن است).</p>';
		echo '</div>';

		// Statement + critics.
		echo '<div class="aetg-panel" data-panel="statement">';
		echo '<div class="aetg-field"><label for="aetgstatement">بیانیه گالری</label>';
		echo '<div class="aetg-hint">مانیفست، رویکرد هنری و مأموریت گالری؛ در پروفایل به‌صورت نقل‌قول برجسته نمایش داده می‌شود.</div>';
		if ( function_exists( 'wp_editor' ) ) {
			wp_editor(
				(string) self::val( $id, 'statement' ),
				'aetgstatement',
				array(
					'textarea_name' => 'aetg[statement]',
					'textarea_rows' => 8,
					'media_buttons' => false,
					'teeny'         => true,
					'quicktags'     => array( 'buttons' => 'strong,em,link,ul,ol,li,close' ),
				)
			);
		} else {
			echo '<textarea id="aetgstatement" name="aetg[statement]" rows="8">' . esc_textarea( (string) self::val( $id, 'statement' ) ) . '</textarea>';
		}
		echo '</div><div class="aetg-row">';
		self::text( 'statement_author', self::val( $id, 'statement_author' ), 'امضای بیانیه (نام)', array( 'placeholder' => 'مثلاً: نام مدیر هنری' ) );
		self::text( 'statement_role', self::val( $id, 'statement_role' ), 'سمت امضاکننده', array( 'placeholder' => 'مدیر هنری گالری' ) );
		echo '</div>';
		self::repeater(
			'critics',
			'نقد و نظر منتقدان',
			'متن نقد، نام منتقد و منبع انتشار را وارد کنید. ردیف‌ها را با دکمه‌های بالا/پایین مرتب کنید؛ ترتیب همین‌جا در سایت رعایت می‌شود.',
			Artiey_AET_Gallery::rows( self::val( $id, 'critics' ), array( 'name', 'role', 'text', 'source', 'url', 'date', 'photo' ), Artiey_AET_Gallery::MAX_CRITICS ),
			array(
				'name'   => array( 'label' => 'نام منتقد *', 'type' => 'text' ),
				'role'   => array( 'label' => 'عنوان / تخصص', 'type' => 'text', 'placeholder' => 'منتقد هنری، پژوهشگر…' ),
				'text'   => array( 'label' => 'متن نقد *', 'type' => 'textarea', 'wide' => true ),
				'source' => array( 'label' => 'منبع (نشریه، کتاب، سایت)', 'type' => 'text' ),
				'url'    => array( 'label' => 'پیوند منبع', 'type' => 'url', 'dir' => 'ltr', 'placeholder' => 'https://' ),
				'date'   => array( 'label' => 'تاریخ', 'type' => 'text', 'placeholder' => 'مهر ۱۴۰۲' ),
				'photo'  => array( 'label' => 'تصویر منتقد', 'type' => 'image' ),
			),
			Artiey_AET_Gallery::MAX_CRITICS,
			'افزودن نقد'
		);
		echo '</div>';

		// Press, awards, team.
		echo '<div class="aetg-panel" data-panel="press">';
		self::repeater(
			'press',
			'بازتاب رسانه‌ای',
			'گزارش‌ها، مصاحبه‌ها و مقاله‌هایی که درباره گالری منتشر شده‌اند.',
			Artiey_AET_Gallery::rows( self::val( $id, 'press' ), array( 'title', 'outlet', 'url', 'date', 'excerpt' ), Artiey_AET_Gallery::MAX_PRESS ),
			array(
				'title'   => array( 'label' => 'عنوان مطلب *', 'type' => 'text', 'wide' => true ),
				'outlet'  => array( 'label' => 'رسانه', 'type' => 'text', 'placeholder' => 'روزنامه، مجله، خبرگزاری…' ),
				'date'    => array( 'label' => 'تاریخ', 'type' => 'text' ),
				'url'     => array( 'label' => 'پیوند', 'type' => 'url', 'dir' => 'ltr', 'placeholder' => 'https://' ),
				'excerpt' => array( 'label' => 'خلاصه (اختیاری)', 'type' => 'textarea', 'wide' => true ),
			),
			Artiey_AET_Gallery::MAX_PRESS,
			'افزودن مطلب'
		);
		echo '<div class="aetg-field"><label for="aetg-awards">جوایز و افتخارات</label>';
		echo '<textarea id="aetg-awards" name="aetg[awards]" rows="5" placeholder="۱۴۰۱ | جایزه بهترین گالری سال&#10;۱۳۹۸ - عضویت در انجمن گالری‌داران">' . esc_textarea( (string) self::val( $id, 'awards' ) ) . '</textarea>';
		echo '<div class="aetg-hint">هر مورد در یک سطر. اگر سطر با سال شروع شود (مثلاً «۱۴۰۱ | عنوان»)، سال جداگانه و به‌صورت خط زمانی نمایش داده می‌شود.</div></div>';
		self::repeater(
			'team',
			'تیم گالری',
			'مدیر، کیوریتورهای ثابت و اعضای تیم را با سمت معرفی کنید.',
			Artiey_AET_Gallery::rows( self::val( $id, 'team' ), array( 'name', 'role', 'photo', 'url' ), Artiey_AET_Gallery::MAX_TEAM ),
			array(
				'name'  => array( 'label' => 'نام *', 'type' => 'text' ),
				'role'  => array( 'label' => 'سمت', 'type' => 'text' ),
				'url'   => array( 'label' => 'پیوند پروفایل', 'type' => 'url', 'dir' => 'ltr', 'placeholder' => 'https://' ),
				'photo' => array( 'label' => 'تصویر', 'type' => 'image' ),
			),
			Artiey_AET_Gallery::MAX_TEAM,
			'افزودن عضو'
		);
		echo '</div>';

		// Contact.
		echo '<div class="aetg-panel" data-panel="contact">';
		self::text( 'phone', self::val( $id, 'phone' ), 'تلفن', array( 'dir' => 'ltr', 'placeholder' => '021-12345678', 'hint' => 'چند شماره را با ویرگول جدا کنید.' ) );
		self::text( 'mobile', self::val( $id, 'mobile' ), 'موبایل', array( 'dir' => 'ltr', 'placeholder' => '09121234567' ) );
		self::text( 'whatsapp', self::val( $id, 'whatsapp' ), 'واتس‌اپ', array( 'dir' => 'ltr', 'placeholder' => '09121234567', 'hint' => 'شماره‌ای که واتس‌اپ دارد؛ به‌صورت خودکار به لینک wa.me تبدیل می‌شود.' ) );
		self::text( 'instagram', self::val( $id, 'instagram' ), 'اینستاگرام', array( 'dir' => 'ltr', 'placeholder' => 'username', 'hint' => 'نام کاربری یا لینک کامل پروفایل.' ) );
		self::text( 'telegram', self::val( $id, 'telegram' ), 'تلگرام', array( 'dir' => 'ltr', 'placeholder' => 'username' ) );
		self::text( 'email', self::val( $id, 'email' ), 'ایمیل', array( 'dir' => 'ltr', 'placeholder' => 'info@example.com', 'type' => 'email' ) );
		self::text( 'website', self::val( $id, 'website' ), 'وب‌سایت', array( 'dir' => 'ltr', 'placeholder' => 'https://', 'type' => 'url' ) );
		echo '</div>';

		// Location.
		echo '<div class="aetg-panel" data-panel="location">';
		echo '<div class="aetg-field"><label>آدرس کامل</label>';
		echo '<textarea name="aetg[address]" rows="3" id="aetg-address">' . esc_textarea( self::val( $id, 'address' ) ) . '</textarea></div>';
		echo '<div class="aetg-row">';
		self::text( 'lat', self::val( $id, 'lat' ), 'عرض جغرافیایی (Lat)', array( 'dir' => 'ltr', 'placeholder' => '35.7219' ) );
		self::text( 'lng', self::val( $id, 'lng' ), 'طول جغرافیایی (Lng)', array( 'dir' => 'ltr', 'placeholder' => '51.3347' ) );
		echo '</div>';
		echo '<div class="aetg-field"><button type="button" class="button" id="aetg-geocode">جستجوی آدرس روی نقشه</button> <span id="aetg-geo-msg" class="aetg-hint"></span></div>';
		echo '<div id="aetg-map" class="aetg-map" data-default-lat="35.6892" data-default-lng="51.3890"></div>';
		echo '<div class="aetg-hint">روی نقشه کلیک کنید یا نشانگر را بکشید تا مختصات دقیق ثبت شود. اگر نقشه بارگذاری نشد (مثلاً اینترنت یا فیلترینگ)، مختصات را از گوگل‌مپ کپی و دستی وارد کنید.</div>';
		echo '</div>';

		// Status + hours.
		echo '<div class="aetg-panel" data-panel="hours">';
		echo '<div class="aetg-field"><label>وضعیت گالری</label><select name="aetg[status]">';
		$statuses = array(
			''            => 'فعال (طبق ساعات کاری)',
			'appointment' => 'فقط با هماهنگی قبلی',
			'temp_closed' => 'موقتاً تعطیل',
			'closed'      => 'برای همیشه بسته شده',
		);
		foreach ( $statuses as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $stat, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></div>';

		echo '<table class="aetg-hours widefat"><thead><tr><th>روز</th><th>تعطیل</th><th>شیفت اول: از</th><th>تا</th><th>شیفت دوم: از</th><th>تا</th></tr></thead><tbody>';
		foreach ( Artiey_AET_Gallery::WEEK_ORDER as $w ) {
			$d = $hours[ $w ];
			echo '<tr><td><strong>' . esc_html( Artiey_AET_Jalali::WEEKDAYS[ $w ] ) . '</strong></td>';
			echo '<td><input type="checkbox" name="aetg[hours][' . (int) $w . '][closed]" value="1" ' . checked( ! empty( $d['closed'] ), true, false ) . '></td>';
			foreach ( array( 'o1', 'c1', 'o2', 'c2' ) as $f ) {
				echo '<td><input type="time" dir="ltr" name="aetg[hours][' . (int) $w . '][' . esc_attr( $f ) . ']" value="' . esc_attr( $d[ $f ] ) . '"></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<div class="aetg-field aap-s-94cfd7"><label>توضیح ساعات کاری</label>';
		echo '<input type="text" name="aetg[hours_note]" value="' . esc_attr( self::val( $id, 'hours_note' ) ) . '" placeholder="مثلاً: در ایام افتتاحیه تا ساعت ۲۱ باز است"></div>';
		echo '<div class="aetg-hint">شیفت دوم اختیاری است (برای گالری‌هایی که ظهر تعطیل‌اند). برچسب «هم‌اکنون باز/بسته است» از روی همین جدول و منطقه زمانی سایت محاسبه می‌شود.</div>';
		echo '</div>';

		// Images.
		echo '<div class="aetg-panel" data-panel="images">';
		self::media_single( 'cover', (int) self::val( $id, 'cover' ), 'عکس کاور', 'تصویر عریض بالای صفحه گالری؛ پیشنهاد: ۱۶۰۰×۶۰۰ پیکسل یا بزرگ‌تر.' );
		$photos = self::val( $id, 'photos' );
		self::media_multi( 'photos', is_array( $photos ) ? $photos : array(), 'تصاویر فضای گالری', 'چند عکس از فضای داخلی و بیرونی گالری.' );
		echo '</div>';

		// Artists.
		echo '<div class="aetg-panel" data-panel="artists">';
		echo '<p class="aetg-hint">هنرمندانی که با این گالری همکاری دارند را انتخاب کنید. هنرمندان را از منوی «هنرمندان» بسازید.</p>';
		$sel = self::val( $id, 'artists' );
		self::artist_checklist( 'aetg[artists]', is_array( $sel ) ? $sel : array() );
		echo '</div>';

		// Look.
		echo '<div class="aetg-panel" data-panel="look">';
		echo '<div class="aetg-field"><label>رنگ نوار نمایشگاه‌های این گالری در تایم‌لاین (اختیاری)</label>';
		echo '<input type="color" name="aetg[color]" value="' . esc_attr( $color ? $color : '#666666' ) . '"> ';
		echo '<label class="aap-inline"><input type="checkbox" name="aetg[color_on]" value="1" ' . checked( ! empty( $color ), true, false ) . '> استفاده از رنگ اختصاصی</label></div>';
		echo '</div>';

		// Extension panels (e.g. certificates from the COA bridge).
		foreach ( $tabs as $key => $label ) {
			if ( in_array( $key, array( 'identity', 'statement', 'press', 'contact', 'location', 'hours', 'images', 'artists', 'look' ), true ) ) {
				continue;
			}
			echo '<div class="aetg-panel" data-panel="' . esc_attr( $key ) . '">';
			do_action( 'artiey_ap_gallery_panel_' . $key, $post );
			echo '</div>';
		}

		echo '</div>';
	}

	public static function save_gallery( $post_id ) {
		if ( 'artiey_gallery' !== get_post_type( $post_id ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['aetg'] ) || ! is_array( $_POST['aetg'] ) ) {
			return;
		}
		if ( ! isset( $_POST['aet_gal_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aet_gal_nonce'] ) ), 'aet_gal_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$in = isset( $_POST['aetg'] ) && is_array( $_POST['aetg'] ) ? wp_unslash( $_POST['aetg'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.

		$text = function ( $key ) use ( $in ) {
			return isset( $in[ $key ] ) && is_string( $in[ $key ] ) ? sanitize_text_field( $in[ $key ] ) : '';
		};

		update_post_meta( $post_id, '_ag_name_en', $text( 'name_en' ) );
		update_post_meta( $post_id, '_ag_city', $text( 'city' ) );
		$venue_type = isset( $in['venue_type'] ) ? sanitize_key( $in['venue_type'] ) : '';
		update_post_meta( $post_id, '_ag_venue_type', in_array( $venue_type, array( 'physical', 'online', 'hybrid' ), true ) ? $venue_type : '' );
		update_post_meta( $post_id, '_ag_visitor_info', isset( $in['visitor_info'] ) && is_string( $in['visitor_info'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['visitor_info'] ), 0, 1200 ) : '' );
		update_post_meta( $post_id, '_ag_short_bio', isset( $in['short_bio'] ) && is_string( $in['short_bio'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['short_bio'] ), 0, 400 ) : '' );
		update_post_meta( $post_id, '_ag_phone', $text( 'phone' ) );
		update_post_meta( $post_id, '_ag_mobile', $text( 'mobile' ) );
		update_post_meta( $post_id, '_ag_whatsapp', $text( 'whatsapp' ) );
		update_post_meta( $post_id, '_ag_instagram', Artiey_AET_Gallery::clean_handle( $text( 'instagram' ) ) );
		update_post_meta( $post_id, '_ag_telegram', Artiey_AET_Gallery::clean_handle( $text( 'telegram' ) ) );
		update_post_meta( $post_id, '_ag_email', isset( $in['email'] ) && is_string( $in['email'] ) ? sanitize_email( $in['email'] ) : '' );
		update_post_meta( $post_id, '_ag_website', isset( $in['website'] ) && is_string( $in['website'] ) ? esc_url_raw( trim( $in['website'] ) ) : '' );
		update_post_meta( $post_id, '_ag_address', isset( $in['address'] ) && is_string( $in['address'] ) ? sanitize_textarea_field( $in['address'] ) : '' );
		update_post_meta( $post_id, '_ag_hours_note', $text( 'hours_note' ) );

		// Founded year (Jalali).
		$founded = isset( $in['founded'] ) ? (int) Artiey_AET_Jalali::to_latin_digits( (string) $in['founded'] ) : 0;
		update_post_meta( $post_id, '_ag_founded', ( $founded >= 1200 && $founded <= 1600 ) ? $founded : '' );

		// Coordinates.
		$lat = isset( $in['lat'] ) ? Artiey_AET_Jalali::to_latin_digits( trim( (string) $in['lat'] ) ) : '';
		$lng = isset( $in['lng'] ) ? Artiey_AET_Jalali::to_latin_digits( trim( (string) $in['lng'] ) ) : '';
		if ( is_numeric( $lat ) && is_numeric( $lng ) && abs( (float) $lat ) <= 90 && abs( (float) $lng ) <= 180 ) {
			update_post_meta( $post_id, '_ag_lat', (string) round( (float) $lat, 6 ) );
			update_post_meta( $post_id, '_ag_lng', (string) round( (float) $lng, 6 ) );
		} else {
			update_post_meta( $post_id, '_ag_lat', '' );
			update_post_meta( $post_id, '_ag_lng', '' );
		}

		// Status.
		$status = isset( $in['status'] ) ? (string) $in['status'] : '';
		update_post_meta( $post_id, '_ag_status', in_array( $status, array( 'appointment', 'temp_closed', 'closed' ), true ) ? $status : '' );

		// Hours.
		$time  = function ( $v ) {
			$v = Artiey_AET_Jalali::to_latin_digits( trim( (string) $v ) );
			return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) ? $v : '';
		};
		$hours = array();
		for ( $w = 0; $w <= 6; $w++ ) {
			$d           = isset( $in['hours'][ $w ] ) && is_array( $in['hours'][ $w ] ) ? $in['hours'][ $w ] : array();
			$hours[ $w ] = array(
				'closed' => ! empty( $d['closed'] ) ? 1 : 0,
				'o1'     => $time( isset( $d['o1'] ) ? $d['o1'] : '' ),
				'c1'     => $time( isset( $d['c1'] ) ? $d['c1'] : '' ),
				'o2'     => $time( isset( $d['o2'] ) ? $d['o2'] : '' ),
				'c2'     => $time( isset( $d['c2'] ) ? $d['c2'] : '' ),
			);
		}
		update_post_meta( $post_id, '_ag_hours', $hours );

		// Images.
		if ( array_key_exists( 'cover', $in ) ) {
			$cover = absint( $in['cover'] );
			update_post_meta( $post_id, '_ag_cover', ( $cover && 'attachment' === get_post_type( $cover ) && wp_attachment_is_image( $cover ) ) ? $cover : '' );
		}

		if ( array_key_exists( 'photos', $in ) ) {
			$photos = array();
			if ( is_string( $in['photos'] ) ) {
				foreach ( array_filter( array_map( 'absint', preg_split( '/[,;\s]+/', $in['photos'] ) ) ) as $aid ) {
					if ( 'attachment' === get_post_type( $aid ) && wp_attachment_is_image( $aid ) && ! in_array( $aid, $photos, true ) ) {
						$photos[] = $aid;
					}
					if ( count( $photos ) >= 40 ) {
						break;
					}
				}
			}
			update_post_meta( $post_id, '_ag_photos', $photos );
		}

		// Artists.
		$artists = array();
		if ( isset( $in['artists'] ) && is_array( $in['artists'] ) ) {
			foreach ( array_filter( array_map( 'absint', $in['artists'] ) ) as $aid ) {
				if ( 'artiey_artist' === get_post_type( $aid ) ) {
					$artists[] = $aid;
				}
			}
		}
		update_post_meta( $post_id, '_ag_artists', $artists );

		// Statement, critics, press, awards, team.
		if ( array_key_exists( 'statement', $in ) ) {
			$statement = is_string( $in['statement'] ) ? wp_kses_post( $in['statement'] ) : '';
			update_post_meta( $post_id, '_ag_statement', Artiey_AET_Text::substr( $statement, 0, 12000 ) );
			update_post_meta( $post_id, '_ag_statement_author', $text( 'statement_author' ) );
			update_post_meta( $post_id, '_ag_statement_role', $text( 'statement_role' ) );
		}
		if ( ! empty( $in['critics_present'] ) ) {
			update_post_meta( $post_id, '_ag_critics', self::sanitize_rows( $in['critics'] ?? array(), array( 'name' => 'text', 'role' => 'text', 'text' => 'textarea', 'source' => 'text', 'url' => 'url', 'date' => 'text', 'photo' => 'image' ), Artiey_AET_Gallery::MAX_CRITICS, array( 'name', 'text' ) ) );
		}
		if ( ! empty( $in['press_present'] ) ) {
			update_post_meta( $post_id, '_ag_press', self::sanitize_rows( $in['press'] ?? array(), array( 'title' => 'text', 'outlet' => 'text', 'url' => 'url', 'date' => 'text', 'excerpt' => 'textarea' ), Artiey_AET_Gallery::MAX_PRESS, array( 'title' ) ) );
		}
		if ( ! empty( $in['team_present'] ) ) {
			update_post_meta( $post_id, '_ag_team', self::sanitize_rows( $in['team'] ?? array(), array( 'name' => 'text', 'role' => 'text', 'url' => 'url', 'photo' => 'image' ), Artiey_AET_Gallery::MAX_TEAM, array( 'name' ) ) );
		}
		if ( array_key_exists( 'awards', $in ) ) {
			update_post_meta( $post_id, '_ag_awards', is_string( $in['awards'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['awards'] ), 0, 6000 ) : '' );
		}

		// Timeline colour.
		$color = '';
		if ( ! empty( $in['color_on'] ) && isset( $in['color'] ) ) {
			$c     = sanitize_hex_color( (string) $in['color'] );
			$color = $c ? $c : '';
		}
		update_post_meta( $post_id, '_ag_color', $color );
	}

	/* ---------------------------------------------------------------- */
	/* Gallery list columns                                              */
	/* ---------------------------------------------------------------- */

	public static function gallery_columns( $cols ) {
		$new = array();
		foreach ( $cols as $key => $label ) {
			if ( 'title' === $key ) {
				$new['aetg_logo'] = 'لوگو';
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['aetg_en']     = 'نام لاتین';
				$new['aetg_city']   = 'شهر';
				$new['aetg_phone']  = 'تماس';
				$new['aetg_ex']     = 'نمایشگاه‌ها';
				$new['aetg_status'] = 'وضعیت';
			}
		}
		return $new;
	}

	public static function gallery_column_content( $col, $post_id ) {
		switch ( $col ) {
			case 'aetg_logo':
				echo has_post_thumbnail( $post_id ) ? get_the_post_thumbnail( $post_id, array( 48, 48 ) ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'aetg_en':
				$v = get_post_meta( $post_id, '_ag_name_en', true );
				echo $v ? '<span dir="ltr">' . esc_html( $v ) . '</span>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'aetg_city':
				$v = get_post_meta( $post_id, '_ag_city', true );
				echo $v ? esc_html( $v ) : '—';
				break;
			case 'aetg_phone':
				$p = Artiey_AET_Gallery::split_numbers( get_post_meta( $post_id, '_ag_phone', true ) );
				$m = Artiey_AET_Gallery::split_numbers( get_post_meta( $post_id, '_ag_mobile', true ) );
				$all = array_merge( $p, $m );
				echo $all ? '<span dir="ltr">' . esc_html( $all[0] ) . '</span>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'aetg_ex':
				$c = Artiey_AET_Exhibition::counts( Artiey_AET_Exhibition::for_gallery( $post_id ) );
				echo esc_html( 'جاری ' . Artiey_AET_Jalali::to_persian_digits( $c['now'] ) . ' · آینده ' . Artiey_AET_Jalali::to_persian_digits( $c['upcoming'] ) . ' · گذشته ' . Artiey_AET_Jalali::to_persian_digits( $c['past'] ) );
				break;
			case 'aetg_status':
				$st = Artiey_AET_Gallery::status( Artiey_AET_Gallery::get( $post_id ) );
				echo esc_html( $st['label'] );
				break;
		}
	}
}
