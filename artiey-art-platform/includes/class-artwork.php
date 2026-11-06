<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Artworks: data, admin meta box, public profile, cards, shortcodes.
 * Certificates are linked through Artiey_AET_COA_Bridge.
 */
final class Artiey_AET_Artwork {

	const SALE = array(
		'available'    => 'موجود',
		'reserved'     => 'رزرو شده',
		'sold'         => 'فروخته شد',
		'not_for_sale' => 'غیرقابل فروش',
	);

	const SECTIONS = array( 'main', 'certificate', 'market', 'photos', 'related' );

	const SECTION_LABELS = array(
		'main'        => 'تصویر، مشخصات و قیمت',
		'certificate' => 'گواهی اصالت (وضعیت، کد، استعلام)',
		'market'      => 'خرید، حراج و درخواست خرید',
		'photos'      => 'تصاویر بیشتر',
		'related'     => 'آثار دیگر هنرمند و گالری',
	);

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_artiey_artwork', array( __CLASS__, 'save' ) );
		add_filter( 'manage_artiey_artwork_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_artiey_artwork_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );

		add_shortcode( 'artiey_artwork_profile', array( __CLASS__, 'shortcode_profile' ) );
		add_shortcode( 'artiey_artwork_field', array( __CLASS__, 'shortcode_field' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
	}

	private static function fa( $v ) {
		return Artiey_AET_Jalali::to_persian_digits( (string) $v );
	}

	private static function meta( $id, $key ) {
		return get_post_meta( $id, '_aw_' . $key, true );
	}

	/** Normalize legacy CSV or array photo metadata to unique image attachment IDs. */
	private static function photo_ids( $value, $limit = 30 ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,;\s]+/', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array();
		foreach ( $value as $candidate ) {
			$id = absint( $candidate );
			if ( $id && ! in_array( $id, $ids, true ) && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id ) ) {
				$ids[] = $id;
				if ( count( $ids ) >= absint( $limit ) ) {
					break;
				}
			}
		}
		return $ids;
	}

	/* ---------------------------------------------------------------- */
	/* Data                                                              */
	/* ---------------------------------------------------------------- */

	public static function get( $id ) {
		$id     = (int) $id;
		$photos = self::meta( $id, 'photos' );
		$sale   = (string) self::meta( $id, 'sale' );

		return array(
			'id'          => $id,
			'title'       => get_the_title( $id ),
			'title_en'    => (string) self::meta( $id, 'title_en' ),
			'artist'      => (int) self::meta( $id, 'artist' ),
			'artist_text' => (string) self::meta( $id, 'artist_text' ),
			'gallery'     => (int) self::meta( $id, 'gallery' ),
			'year'        => (string) self::meta( $id, 'year' ),
			'medium'      => (string) self::meta( $id, 'medium' ),
			'dimensions'  => (string) self::meta( $id, 'dimensions' ),
			'material'    => (string) self::meta( $id, 'material' ),
			'condition'   => (string) self::meta( $id, 'condition' ),
			'provenance'  => (string) self::meta( $id, 'provenance' ),
			'edition'     => (string) self::meta( $id, 'edition' ),
			'accession'   => (string) self::meta( $id, 'accession' ),
			'sale'        => isset( self::SALE[ $sale ] ) ? $sale : 'available',
			'price'       => (int) self::meta( $id, 'price' ),
			'price_mode'  => in_array( (string) self::meta( $id, 'price_mode' ), array( 'show', 'hide', 'on_request' ), true ) ? (string) self::meta( $id, 'price_mode' ) : 'show',
			'photos'      => self::photo_ids( $photos, 30 ),
		);
	}

	public static function artist_name( $a ) {
		if ( $a['artist'] && 'publish' === get_post_status( $a['artist'] ) ) {
			return get_the_title( $a['artist'] );
		}
		return $a['artist_text'];
	}

	private static function price_text( $a ) {
		if ( 'sold' === $a['sale'] || 'not_for_sale' === $a['sale'] ) {
			return '';
		}
		if ( 'on_request' === $a['price_mode'] ) {
			return 'قیمت: با درخواست';
		}
		if ( 'hide' === $a['price_mode'] || $a['price'] <= 0 ) {
			return '';
		}
		$n = str_replace( ',', '٬', number_format( $a['price'] ) );
		return self::fa( $n ) . ' ' . Artiey_AET_Settings::get( 'currency' );
	}

	/* ---------------------------------------------------------------- */
	/* Admin                                                             */
	/* ---------------------------------------------------------------- */

	public static function boxes() {
		add_meta_box( 'aetw_box', 'اطلاعات اثر', array( __CLASS__, 'render_box' ), 'artiey_artwork', 'normal', 'high' );
	}

	private static function field( $name, $value, $label, $opts = array() ) {
		$o = wp_parse_args( $opts, array( 'dir' => 'rtl', 'placeholder' => '', 'hint' => '', 'type' => 'text' ) );
		echo '<div class="aetg-field"><label>' . esc_html( $label ) . '</label>';
		echo '<input type="' . esc_attr( $o['type'] ) . '" name="aetw[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" dir="' . esc_attr( $o['dir'] ) . '" placeholder="' . esc_attr( $o['placeholder'] ) . '">';
		if ( $o['hint'] ) {
			echo '<div class="aetg-hint">' . esc_html( $o['hint'] ) . '</div>';
		}
		echo '</div>';
	}

	private static function post_select( $name, $type, $current, $label, $none ) {
		$list = get_posts(
			array(
				'post_type'   => $type,
				'post_status' => array( 'publish', 'draft', 'private' ),
				'numberposts' => 500,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		echo '<div class="aetg-field"><label>' . esc_html( $label ) . '</label><select name="aetw[' . esc_attr( $name ) . ']"><option value="0">' . esc_html( $none ) . '</option>';
		foreach ( $list as $p ) {
			echo '<option value="' . esc_attr( $p->ID ) . '" ' . selected( (int) $current, (int) $p->ID, false ) . '>' . esc_html( $p->post_title ) . '</option>';
		}
		echo '</select></div>';
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'aet_aw_save', 'aet_aw_nonce' );
		$a = self::get( $post->ID );

		$tabs = apply_filters(
			'artiey_ap_artwork_tabs',
			array(
				'info'   => 'مشخصات اثر',
				'sale'   => 'گالری و فروش',
				'images' => 'تصاویر',
			)
		);

		echo '<div class="aetg"><div class="aetg-tabs">';
		foreach ( $tabs as $key => $label ) {
			echo '<button type="button" class="aetg-tab" data-tab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</div>';

		echo '<div class="aetg-panel" data-panel="info">';
		echo '<p class="aetg-hint">عنوان فارسی اثر همان «عنوان» نوشته است؛ تصویر اصلی اثر را از ستون کناری («تصویر اثر») انتخاب و توضیحات اثر را در ویرایشگر اصلی بنویسید. سبک هنری را از کادر کناری انتخاب کنید.</p>';
		self::field( 'title_en', $a['title_en'], 'عنوان لاتین اثر', array( 'dir' => 'ltr', 'placeholder' => 'Untitled' ) );
		self::post_select( 'artist', 'artiey_artist', $a['artist'], 'هنرمند (از فهرست هنرمندان)', '— انتخاب هنرمند —' );
		self::field( 'artist_text', $a['artist_text'], 'نام هنرمند (اگر در فهرست نیست)', array( 'hint' => 'فقط وقتی پر شود که هنرمند بالا انتخاب نشده باشد.' ) );
		echo '<div class="aetg-row">';
		self::field( 'year', $a['year'], 'سال خلق اثر', array( 'placeholder' => '1403', 'dir' => 'ltr' ) );
		self::field( 'medium', $a['medium'], 'رسانه و تکنیک', array( 'placeholder' => 'رنگ روغن روی بوم' ) );
		echo '</div><div class="aetg-row">';
		self::field( 'dimensions', $a['dimensions'], 'ابعاد', array( 'placeholder' => '100 × 70 سانتی‌متر' ) );
		self::field( 'edition', $a['edition'], 'ادیشن (اختیاری)', array( 'placeholder' => '1/5', 'dir' => 'ltr' ) );
		echo '</div>';
		self::field( 'accession', $a['accession'], 'شماره اینونتوری / ثبت اثر', array( 'dir' => 'ltr' ) );
		self::field( 'material', $a['material'], 'مواد و مصالح', array( 'placeholder' => 'بوم، رنگ روغن، ورق طلا' ) );
		self::field( 'condition', $a['condition'], 'وضعیت فیزیکی اثر', array( 'placeholder' => 'سالم؛ نیازمند قاب‌گیری' ) );
		echo '<div class="aetg-field"><label>پیشینه / مالکیت</label><textarea name="aetw[provenance]" rows="3" maxlength="2000">' . esc_textarea( $a['provenance'] ) . '</textarea><div class="aetg-hint">سوابق مالکیت یا نمایش اثر را فقط در صورت داشتن اطلاعات مستند ثبت کنید.</div></div>';
		echo '</div>';

		echo '<div class="aetg-panel" data-panel="sale">';
		self::post_select( 'gallery', 'artiey_gallery', $a['gallery'], 'گالری عرضه‌کننده', '— بدون گالری —' );
		echo '<div class="aetg-field"><label>وضعیت فروش</label><select name="aetw[sale]">';
		foreach ( self::SALE as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $a['sale'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="aetg-row">';
		self::field( 'price', $a['price'] ? $a['price'] : '', 'قیمت (' . Artiey_AET_Settings::get( 'currency' ) . ')', array( 'dir' => 'ltr', 'type' => 'number', 'placeholder' => '150000000' ) );
		echo '<div class="aetg-field"><label>نمایش قیمت</label><select name="aetw[price_mode]">';
		foreach ( array( 'show' => 'نمایش قیمت', 'on_request' => 'قیمت با درخواست', 'hide' => 'مخفی' ) as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $a['price_mode'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div></div>';
		echo '<div class="aetg-hint">قیمت فقط وقتی نمایش داده می‌شود که اثر «موجود» یا «رزرو شده» باشد.</div>';
		echo '</div>';

		echo '<div class="aetg-panel" data-panel="images">';
		echo '<div class="aetg-field aetg-media" data-multiple="1"><label>تصاویر بیشتر اثر (جزئیات، نمای نصب‌شده، پشت اثر)</label><div class="aetg-preview aetg-preview-multi">';
		foreach ( $a['photos'] as $pid ) {
			echo wp_get_attachment_image( $pid, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div><input type="hidden" name="aetw[photos]" value="' . esc_attr( implode( ',', $a['photos'] ) ) . '" class="aetg-media-input"><button type="button" class="button aetg-media-pick">انتخاب تصاویر</button> <button type="button" class="button-link-delete aetg-media-clear">پاک کردن همه</button></div>';
		echo '</div>';

		// Extension panels (e.g. certificates from the COA bridge).
		foreach ( $tabs as $key => $label ) {
			if ( in_array( $key, array( 'info', 'sale', 'images' ), true ) ) {
				continue;
			}
			echo '<div class="aetg-panel" data-panel="' . esc_attr( $key ) . '">';
			do_action( 'artiey_ap_artwork_panel_' . $key, $post );
			echo '</div>';
		}
		echo '</div>';
	}

	public static function save( $post_id ) {
		if ( 'artiey_artwork' !== get_post_type( $post_id ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['aet_aw_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aet_aw_nonce'] ) ), 'aet_aw_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['aetw'] ) || ! is_array( $_POST['aetw'] ) ) {
			return;
		}
		$in = wp_unslash( $_POST['aetw'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field.

		$text = function ( $k ) use ( $in ) {
			return isset( $in[ $k ] ) && is_string( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		};
		$post_ref = function ( $k, $type ) use ( $in ) {
			$id = isset( $in[ $k ] ) ? absint( $in[ $k ] ) : 0;
			return ( $id && $type === get_post_type( $id ) ) ? $id : '';
		};

		foreach ( array( 'title_en', 'artist_text', 'year', 'medium', 'dimensions', 'edition', 'accession', 'material', 'condition' ) as $k ) {
			update_post_meta( $post_id, '_aw_' . $k, Artiey_AET_Jalali::to_latin_digits_if_numeric( $text( $k ), in_array( $k, array( 'year', 'edition' ), true ) ) );
		}
		update_post_meta( $post_id, '_aw_provenance', isset( $in['provenance'] ) && is_string( $in['provenance'] ) ? Artiey_AET_Text::substr( sanitize_textarea_field( $in['provenance'] ), 0, 2000 ) : '' );
		update_post_meta( $post_id, '_aw_artist', $post_ref( 'artist', 'artiey_artist' ) );
		update_post_meta( $post_id, '_aw_gallery', $post_ref( 'gallery', 'artiey_gallery' ) );

		$sale = isset( $in['sale'] ) ? (string) $in['sale'] : 'available';
		update_post_meta( $post_id, '_aw_sale', isset( self::SALE[ $sale ] ) ? $sale : 'available' );

		$mode = isset( $in['price_mode'] ) ? (string) $in['price_mode'] : 'show';
		update_post_meta( $post_id, '_aw_price_mode', in_array( $mode, array( 'show', 'hide', 'on_request' ), true ) ? $mode : 'show' );

		$price = isset( $in['price'] ) ? (int) preg_replace( '/\D+/', '', Artiey_AET_Jalali::to_latin_digits( (string) $in['price'] ) ) : 0;
		update_post_meta( $post_id, '_aw_price', max( 0, $price ) );

		if ( array_key_exists( 'photos', $in ) ) {
			update_post_meta( $post_id, '_aw_photos', self::photo_ids( $in['photos'], 30 ) );
		}
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $key => $label ) {
			if ( 'title' === $key ) {
				$new['aetw_img'] = 'تصویر';
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['aetw_artist'] = 'هنرمند';
				$new['aetw_gal']    = 'گالری';
				$new['aetw_sale']   = 'فروش';
				$new['aetw_coa']    = 'گواهی';
			}
		}
		return $new;
	}

	public static function column_content( $col, $post_id ) {
		$a = self::get( $post_id );
		switch ( $col ) {
			case 'aetw_img':
				echo has_post_thumbnail( $post_id ) ? get_the_post_thumbnail( $post_id, array( 48, 48 ) ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'aetw_artist':
				$n = self::artist_name( $a );
				echo $n ? esc_html( $n ) : '—';
				break;
			case 'aetw_gal':
				echo $a['gallery'] ? esc_html( get_the_title( $a['gallery'] ) ) : '—';
				break;
			case 'aetw_sale':
				echo esc_html( self::SALE[ $a['sale'] ] );
				break;
			case 'aetw_coa':
				$links = get_post_meta( $post_id, '_aw_coa_certs', true );
				echo is_array( $links ) && $links ? esc_html( self::fa( count( $links ) ) . ' گواهی' ) : '—';
				break;
		}
	}

	/* ---------------------------------------------------------------- */
	/* Prefill data for the COA issuance form                            */
	/* ---------------------------------------------------------------- */

	public static function coa_prefill( $id ) {
		$a      = self::get( $id );
		$artist = $a['artist'] ? $a['artist'] : 0;

		$desc = has_excerpt( $id ) ? get_the_excerpt( $id ) : wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ), 60, '…' );

		$gallery_coa = $a['gallery'] ? (int) get_post_meta( $a['gallery'], '_ag_coa_gallery_id', true ) : 0;
		$person_coa  = $artist ? (int) get_post_meta( $artist, '_aa_coa_person_id', true ) : 0;

		$image_id  = (int) get_post_thumbnail_id( $id );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

		return array(
			'artworkId' => (int) $id,
			'banner'    => 'فرم با اطلاعات اثر «' . $a['title'] . '» پر شد. قبل از صدور، همه فیلدها را مرور کنید؛ اطلاعات امضاشده بعد از صدور قابل ویرایش نیستند.',
			'editUrl'   => get_edit_post_link( $id, 'raw' ),
			'fields'    => array(
				'artwork_title'       => $a['title'],
				'artwork_title_en'    => $a['title_en'],
				'artist_name'         => self::artist_name( $a ),
				'artist_name_en'      => $artist ? (string) get_post_meta( $artist, '_aa_name_en', true ) : '',
				'medium'              => $a['medium'],
				'year_created'        => $a['year'],
				'dimensions'          => $a['dimensions'],
				'accession_number'    => $a['accession'],
				'artwork_description' => $desc,
			),
			'selects'   => array(
				'gallery_id'        => $gallery_coa,
				'artist_profile_id' => $person_coa,
			),
			'imageId'   => $image_id,
			'imageUrl'  => $image_url ? $image_url : '',
		);
	}

	/* ---------------------------------------------------------------- */
	/* Certificate + market (shop / auction / inquiry)                   */
	/* ---------------------------------------------------------------- */

	/** Visitors only see published artworks; editors can preview drafts. */
	public static function can_view( $id ) {
		$status = get_post_status( $id );
		if ( 'publish' === $status && ! post_password_required( $id ) ) {
			return true;
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $id );
	}

	/** Mirrored Art COA catalogue row of this artwork post (read-only lookup, never syncs on view). */
	public static function art_row( $post_id ) {
		static $cache = array();
		$post_id = absint( $post_id );
		if ( ! $post_id || ! class_exists( '\\WPArtCOA\\Database' ) || ! get_option( 'artiey_aet_content_bridge_schema_version' ) ) {
			return null;
		}
		if ( array_key_exists( $post_id, $cache ) ) {
			return $cache[ $post_id ];
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . \WPArtCOA\Database::table( 'artworks' ) . ' WHERE cpt_post_id = %d LIMIT 2', $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cache[ $post_id ] = is_array( $rows ) && 1 === count( $rows ) ? $rows[0] : null;
		return $cache[ $post_id ];
	}

	/** Live or upcoming auction of this artwork, if any. */
	public static function auction_for( $post_id ) {
		$row = self::art_row( $post_id );
		if ( ! $row || ! class_exists( '\\WPArtCOA\\Auction' ) ) {
			return null;
		}
		global $wpdb;
		$au = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . \WPArtCOA\Auction::listings_table() . " WHERE artwork_id = %d AND status IN ('active','scheduled') ORDER BY ends_at_utc ASC LIMIT 1", (int) $row->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $au ? $au : null;
	}

	public static function certificates( $id ) {
		if ( ! Artiey_AET_Settings::get( 'show_certificates' ) || ! class_exists( 'Artiey_AET_COA_Bridge' ) ) {
			return array();
		}
		return Artiey_AET_COA_Bridge::certificate_summaries( $id );
	}

	private static function s_certificate( $a ) {
		$certs = self::certificates( $a['id'] );
		if ( ! $certs ) {
			return '';
		}
		return '<section class="aetp-sec aetp-aw-certificate"><h3>گواهی اصالت</h3>' . Artiey_AET_COA_Bridge::badge_html( $certs, 'panel' ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the bridge
	}

	private static function s_market( $a ) {
		$id      = $a['id'];
		$sale    = $a['sale'];
		$price   = self::price_text( $a );
		$row     = self::art_row( $id );
		$auction = self::auction_for( $id );
		$buy     = ( $row && class_exists( '\\WPArtCOA\\WooBridge' ) ) ? \WPArtCOA\WooBridge::buy_url( $row ) : '';

		$h  = '<section class="aetp-sec aetp-aw-market"><h3>خرید و عرضه</h3><div class="aetp-market-card">';
		$h .= '<div class="aetp-market-status"><span class="aetp-ribbon aetp-sale-' . esc_attr( $sale ) . '">' . esc_html( self::SALE[ $sale ] ) . '</span>';
		if ( $price ) {
			$h .= '<strong class="aetp-market-price">' . esc_html( $price ) . '</strong>';
		}
		if ( self::certificates( $id ) && Artiey_AET_COA_Bridge::has_valid_certificate( $id ) ) {
			$h .= '<span class="aet-coa-badge is-valid"><span aria-hidden="true">✓</span> همراه با گواهی اصالت معتبر</span>';
		}
		$h .= '</div>';

		if ( $auction && class_exists( '\\WPArtCOA\\Shop' ) ) {
			$current = null !== $auction->current_bid ? $auction->current_bid : $auction->start_price;
			$live    = 'active' === $auction->status && strtotime( $auction->ends_at_utc . ' UTC' ) > time();
			$h      .= '<div class="aetp-market-auction"><span class="aetp-market-label">' . esc_html( $live ? 'این اثر در حراج است' : 'حراج به‌زودی' ) . '</span>';
			$h      .= '<span>' . esc_html( ( null !== $auction->current_bid ? 'بالاترین پیشنهاد: ' : 'قیمت پایه: ' ) . \WPArtCOA\Shop::format_price( $current, (string) $auction->currency ) ) . '</span>';
			$h      .= '<span class="aet-call-countdown" data-aet-deadline="' . esc_attr( gmdate( 'c', (int) strtotime( $auction->ends_at_utc . ' UTC' ) ) ) . '"></span>';
			$h      .= '<a class="aetp-btn aetp-btn-primary" href="' . esc_url( home_url( '/art-coa/auction/' . (int) $auction->id . '/' ) ) . '">' . esc_html( $live ? 'ثبت پیشنهاد در حراج' : 'مشاهده حراج' ) . '</a></div>';
			if ( class_exists( '\\WPArtCOA\\Calls' ) ) {
				\WPArtCOA\Calls::enqueue();
			}
		} elseif ( $buy && in_array( $sale, array( 'available' ), true ) ) {
			$h .= '<a class="aetp-btn aetp-btn-primary" href="' . esc_url( $buy ) . '">خرید آنلاین</a>';
		}

		if ( in_array( $sale, array( 'available', 'reserved' ), true ) && ! $auction ) {
			if ( $row && class_exists( '\\WPArtCOA\\Shop' ) ) {
				$h .= '<details class="aetp-market-inquiry"' . ( $buy ? '' : ' open' ) . '><summary>' . esc_html( 'reserved' === $sale ? 'درخواست اطلاع از وضعیت اثر' : 'درخواست خرید یا اطلاعات بیشتر' ) . '</summary>' . \WPArtCOA\Shop::inquiry_form( (int) $row->id ) . '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Shop
			} elseif ( $a['gallery'] && 'publish' === get_post_status( $a['gallery'] ) ) {
				$h .= '<a class="aetp-btn" href="' . esc_url( get_permalink( $a['gallery'] ) . '#contact' ) . '">تماس با گالری عرضه‌کننده</a>';
			}
		}
		if ( 'sold' === $sale ) {
			$h .= '<p class="aetp-muted">این اثر فروخته شده است. برای آثار مشابه، دیگر آثار هنرمند را ببینید.</p>';
		}
		return $h . '</div></section>';
	}

	/* ---------------------------------------------------------------- */
	/* Front-end                                                         */
	/* ---------------------------------------------------------------- */

	/** Card for directories and "related" lists. */
	public static function card( $p ) {
		$a      = self::get( $p->ID );
		$artist = self::artist_name( $a );
		$price  = self::price_text( $a );

		$img = has_post_thumbnail( $p )
			? get_the_post_thumbnail( $p, 'medium_large', array( 'loading' => 'lazy', 'alt' => get_the_title( $p ) ) )
			: '<span class="aetp-noposter">' . esc_html( Artiey_AET_Text::substr( get_the_title( $p ), 0, 2 ) ) . '</span>';

		$h  = '<a class="aetp-card aetp-artwork" href="' . esc_url( get_permalink( $p ) ) . '">';
		$h .= '<span class="aetp-card-img aetp-ratio-art">' . $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$h .= '<span class="aetp-ribbon aetp-sale-' . esc_attr( $a['sale'] ) . '">' . esc_html( self::SALE[ $a['sale'] ] ) . '</span>';
		if ( Artiey_AET_Settings::get( 'show_certificates' ) && class_exists( 'Artiey_AET_COA_Bridge' ) && Artiey_AET_COA_Bridge::has_valid_certificate( $p->ID ) ) {
			$h .= '<span class="aetp-coa-dot" title="دارای گواهی اصالت معتبر">✓</span>';
		}
		$h .= '</span><span class="aetp-card-title">' . esc_html( get_the_title( $p ) ) . '</span>';
		if ( $artist ) {
			$h .= '<span class="aetp-card-sub">' . esc_html( $artist ) . '</span>';
		}
		if ( $price ) {
			$h .= '<span class="aetp-card-price">' . esc_html( $price ) . '</span>';
		}
		return $h . '</a>';
	}

	private static function s_main( $a, $show_title, $compact_certificates = false ) {
		$id = $a['id'];

		$media = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'large', array( 'alt' => $a['title'] . ( self::artist_name( $a ) ? ' — ' . self::artist_name( $a ) : '' ), 'loading' => 'eager', 'fetchpriority' => 'high' ) ) : '';
		if ( $media ) {
			$full  = (string) wp_get_attachment_url( (int) get_post_thumbnail_id( $id ) );
			$media = $full ? '<a href="' . esc_url( $full ) . '" data-aetp-lightbox="aw-' . (int) $id . '" aria-label="بزرگ‌نمایی تصویر اثر">' . $media . '</a>' : $media;
		}
		$h     = '<div class="aetp-aw-wrap' . ( $media ? ' has-media' : '' ) . '">';
		if ( $media ) {
			$h .= '<div class="aetp-aw-media">' . $media . '<span class="aetp-ribbon aetp-sale-' . esc_attr( $a['sale'] ) . '">' . esc_html( self::SALE[ $a['sale'] ] ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$h .= '<div class="aetp-aw-info">';
		if ( $show_title ) {
			$h .= '<h2 class="aetp-title aetp-aw-title">' . esc_html( $a['title'] ) . '</h2>';
		}
		if ( $a['title_en'] ) {
			$h .= '<div class="aetp-title-en aetp-aw-title-en" dir="ltr">' . esc_html( $a['title_en'] ) . '</div>';
		}

		$rows   = array();
		$artist = self::artist_name( $a );
		if ( $artist ) {
			$rows['هنرمند'] = ( $a['artist'] && 'publish' === get_post_status( $a['artist'] ) )
				? '<a href="' . esc_url( get_permalink( $a['artist'] ) ) . '">' . esc_html( $artist ) . '</a>'
				: esc_html( $artist );
		}
		if ( $a['gallery'] && 'publish' === get_post_status( $a['gallery'] ) ) {
			$rows['گالری'] = '<a href="' . esc_url( get_permalink( $a['gallery'] ) ) . '">' . esc_html( get_the_title( $a['gallery'] ) ) . '</a>';
		}
		if ( $a['year'] ) {
			$rows['سال خلق'] = esc_html( self::fa( $a['year'] ) );
		}
		if ( $a['medium'] ) {
			$rows['رسانه و تکنیک'] = esc_html( $a['medium'] );
		}
		if ( $a['dimensions'] ) {
			$rows['ابعاد'] = esc_html( self::fa( $a['dimensions'] ) );
		}
		if ( $a['material'] ) {
			$rows['مواد و مصالح'] = esc_html( $a['material'] );
		}
		if ( $a['condition'] ) {
			$rows['وضعیت فیزیکی'] = esc_html( $a['condition'] );
		}
		if ( $a['provenance'] ) {
			$rows['پیشینه / مالکیت'] = nl2br( esc_html( $a['provenance'] ) );
		}
		if ( $a['edition'] ) {
			$rows['ادیشن'] = '<span dir="ltr">' . esc_html( $a['edition'] ) . '</span>';
		}
		if ( $a['accession'] ) {
			$rows['شماره اثر'] = '<span dir="ltr">' . esc_html( $a['accession'] ) . '</span>';
		}
		$terms = get_the_terms( $id, 'artiey_style' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$chips = '';
			foreach ( $terms as $t ) {
				$chips .= '<a class="aetp-chip" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a> ';
			}
			$rows['سبک هنری'] = $chips;
		}
		if ( $rows ) {
			$h .= '<dl class="aetp-dl">';
			foreach ( $rows as $k => $v ) {
				$h .= '<dt>' . esc_html( $k ) . '</dt><dd>' . $v . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			$h .= '</dl>';
		}

		$price = self::price_text( $a );
		if ( $price ) {
			$h .= '<div class="aetp-price">' . esc_html( $price ) . '</div>';
		}

		// Certificates (read-only, public-safe). With a separate certificate section, show only the compact badge here.
		$certs = self::certificates( $id );
		if ( $compact_certificates ) {
			$h    .= Artiey_AET_COA_Bridge::badge_html( $certs, 'badge' );
			$certs = array();
		}
		foreach ( $certs as $c ) {
			$h .= '<div class="aetp-coa aetp-coa-' . esc_attr( $c['state'] ) . '"><span class="aetp-coa-title">' . esc_html( $c['label'] ) . '</span>';
			$h .= '<span class="aetp-coa-code" dir="ltr">' . esc_html( $c['code'] ) . '</span>';
			if ( $c['issued'] ) {
				$h .= '<span class="aetp-coa-date">صدور: ' . esc_html( $c['issued'] ) . '</span>';
			}
			if ( $c['url'] ) {
				$h .= '<a class="aetp-pill" href="' . esc_url( $c['url'] ) . '" target="_blank" rel="noopener">استعلام گواهی</a>';
			}
			$h .= '</div>';
		}

		return $h . '</div></div>';
	}

	private static function s_photos( $a ) {
		if ( ! $a['photos'] ) {
			return '';
		}
		$h = '<section class="aetp-sec aetp-photos"><h3>تصاویر بیشتر</h3><div class="aetp-photo-grid">';
		foreach ( $a['photos'] as $pid ) {
			$alt = trim( (string) get_post_meta( $pid, '_wp_attachment_image_alt', true ) );
			$img = wp_get_attachment_image( $pid, 'medium_large', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' !== $alt ? $alt : $a['title'] ) );
			if ( $img ) {
				$h .= '<a href="' . esc_url( wp_get_attachment_url( $pid ) ) . '" target="_blank" rel="noopener" data-aetp-lightbox="aw-' . (int) $a['id'] . '">' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		return $h . '</div></section>';
	}

	private static function s_related( $a ) {
		$heading = 'آثار دیگر این هنرمند';
		$list    = array();
		foreach ( array( 'artist' => '_aw_artist', 'gallery' => '_aw_gallery' ) as $field => $key ) {
			if ( $list || ! $a[ $field ] ) {
				continue;
			}
			$list = get_posts(
				array(
					'post_type'     => 'artiey_artwork',
					'post_status'   => 'publish',
					'numberposts'   => 4,
					'no_found_rows' => true,
					'post__not_in'  => array( $a['id'] ),
					'meta_query'    => array( array( 'key' => $key, 'value' => $a[ $field ], 'type' => 'NUMERIC' ) ),
				)
			);
			if ( $list && 'gallery' === $field ) {
				$heading = 'آثار دیگر این گالری';
			}
		}
		if ( ! $list ) {
			return '';
		}
		$h = '<section class="aetp-sec"><h3>' . esc_html( $heading ) . '</h3><div class="aetp-grid aap-s-0143d0">';
		foreach ( $list as $p ) {
			$h .= self::card( $p );
		}
		return $h . '</div></section>';
	}

	public static function render_profile( $id, $sections = null, $show_title = true ) {
		$id = (int) $id;
		if ( ! $id || 'artiey_artwork' !== get_post_type( $id ) || ! self::can_view( $id ) ) {
			return '';
		}
		$sections = is_array( $sections ) ? array_values( array_intersect( self::SECTIONS, $sections ) ) : self::SECTIONS;
		wp_enqueue_style( 'artiey-aet-profile' );
		wp_enqueue_script( 'artiey-aet-profile' );

		$a    = self::get( $id );
		$html = in_array( 'main', $sections, true ) ? Artiey_AET_Statistics::profile_strip( 'artiey_artwork', $id ) : '';
		foreach ( $sections as $section ) {
			switch ( $section ) {
				case 'main':
					$html .= self::s_main( $a, $show_title, in_array( 'certificate', $sections, true ) );
					break;
				case 'certificate':
					$html .= self::s_certificate( $a );
					break;
				case 'market':
					$html .= self::s_market( $a );
					break;
				case 'photos':
					$html .= self::s_photos( $a );
					break;
				case 'related':
					$html .= self::s_related( $a );
					break;
			}
		}
		return '' === $html ? '' : '<div class="aetp aetp-aw" dir="rtl">' . $html . '</div>';
	}

	/** Widgets saved with the 3.0 default selection gain the certificate and market sections. */
	public static function upgrade_default_sections( $sections ) {
		if ( is_array( $sections ) && count( $sections ) === 3 && ! array_diff( array( 'main', 'photos', 'related' ), $sections ) ) {
			return self::SECTIONS;
		}
		return $sections;
	}

	public static function filter_content( $content ) {
		if ( ! is_singular( 'artiey_artwork' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! Artiey_AET_Profile_Guard::auto_allowed( get_the_ID() ) ) {
			return $content;
		}
		if ( ! Artiey_AET_Settings::get( 'artwork_auto' ) ) {
			return $content;
		}
		$wanted = (array) Artiey_AET_Settings::get( 'artwork_sections' );
		$id     = get_the_ID();

		$wanted = self::upgrade_default_sections( $wanted );
		$before = in_array( 'main', $wanted, true ) ? self::render_profile( $id, array_values( array_intersect( array( 'main', 'certificate' ), $wanted ) ), false ) : '';
		$rest   = array_values( array_intersect( array( 'market', 'photos', 'related' ), $wanted ) );
		$after  = $rest ? self::render_profile( $id, array_merge( in_array( 'main', $wanted, true ) ? array() : array_intersect( array( 'certificate' ), $wanted ), $rest ), false ) : '';
		return $before . $content . $after;
	}

	public static function shortcode_profile( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'sections' => '', 'show_title' => 'yes' ), (array) $atts, 'artiey_artwork_profile' );
		$id   = absint( $atts['id'] ) ? absint( $atts['id'] ) : (int) get_the_ID();
		$secs = null;
		if ( '' !== trim( (string) $atts['sections'] ) ) {
			$secs = array_intersect( self::SECTIONS, array_map( 'trim', explode( ',', (string) $atts['sections'] ) ) );
		}
		Artiey_AET_Profile_Guard::mark( $id );
		return self::render_profile( $id, $secs, 'no' !== $atts['show_title'] );
	}

	public static function shortcode_field( $atts ) {
		$atts = shortcode_atts( array( 'field' => '', 'id' => 0 ), (array) $atts, 'artiey_artwork_field' );
		$id   = absint( $atts['id'] ) ? absint( $atts['id'] ) : (int) get_the_ID();
		if ( ! $id || 'artiey_artwork' !== get_post_type( $id ) ) {
			return '';
		}
		if ( ! self::can_view( $id ) ) {
			return '';
		}
		$a = self::get( $id );
		switch ( $atts['field'] ) {
			case 'certificate_badge':
				return Artiey_AET_COA_Bridge::badge_html( self::certificates( $id ), 'badge' );
			case 'certificate_panel':
				return Artiey_AET_COA_Bridge::badge_html( self::certificates( $id ), 'panel' );
			case 'title_en':
			case 'medium':
			case 'edition':
			case 'accession':
				return esc_html( $a[ $atts['field'] ] );
			case 'year':
			case 'dimensions':
				return esc_html( self::fa( $a[ $atts['field'] ] ) );
			case 'artist':
				return esc_html( self::artist_name( $a ) );
			case 'gallery':
				return $a['gallery'] ? esc_html( get_the_title( $a['gallery'] ) ) : '';
			case 'price':
				return esc_html( self::price_text( $a ) );
			case 'status':
				return esc_html( self::SALE[ $a['sale'] ] );
			case 'certificate':
				$c = Artiey_AET_COA_Bridge::certificate_summaries( $id );
				return $c ? esc_html( $c[0]['label'] ) : '';
			case 'certificate_code':
				$c = Artiey_AET_COA_Bridge::certificate_summaries( $id );
				return $c ? esc_html( $c[0]['code'] ) : '';
			case 'certificate_url':
				$c = Artiey_AET_COA_Bridge::certificate_summaries( $id );
				return ( $c && $c[0]['url'] ) ? esc_url( $c[0]['url'] ) : '';
		}
		return '';
	}
}
