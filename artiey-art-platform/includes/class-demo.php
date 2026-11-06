<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional sample data so the plugin can be previewed quickly. Everything created is flagged and removable.
 */
final class Artiey_AET_Demo {

	public static function init() {
		add_action( 'admin_post_artiey_aet_demo', array( __CLASS__, 'handle' ) );
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'artiey_aet_demo' );

		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'create' === $do ) {
			self::create();
			$flag = 'created';
		} elseif ( 'remove' === $do ) {
			self::remove();
			$flag = 'removed';
		} else {
			$flag = '';
		}
		Artiey_AET_Settings::bump();
		wp_safe_redirect( add_query_arg( 'aet_demo', $flag, Artiey_AET_Admin::settings_url() ) );
		exit;
	}

	private static function add( $type, $title, $meta = array(), $terms = array() ) {
		$id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => 'این یک محتوای نمونه است و می‌توانید آن را ویرایش یا حذف کنید.',
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_aet_demo', 1 );
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		if ( $terms ) {
			wp_set_object_terms( $id, $terms, 'artiey_style' );
		}
		return (int) $id;
	}

	private static function day( $offset ) {
		$d = new DateTimeImmutable( current_time( 'Y-m-d' ) . ' 12:00:00', new DateTimeZone( 'UTC' ) );
		return $d->modify( ( $offset >= 0 ? '+' : '' ) . (int) $offset . ' days' )->format( 'Y-m-d' );
	}

	private static function create() {
		$hours = array();
		for ( $w = 0; $w <= 6; $w++ ) {
			$hours[ $w ] = array(
				'closed' => ( 5 === $w ) ? 1 : 0,
				'o1'     => '10:00',
				'c1'     => '13:00',
				'o2'     => '16:00',
				'c2'     => '20:00',
			);
		}

		$artists = array(
			self::add( 'artiey_artist', 'سارا نمونه', array( '_aa_name_en' => 'Sara Sample', '_aa_birth' => 1362, '_aa_city' => 'تهران', '_aa_mediums' => 'رنگ روغن روی بوم', '_aa_short_bio' => 'هنرمند نمونه برای نمایش پروفایل حرفه‌ای.', '_aa_awards' => "جایزه نمونه ۱۳۹۸\nنامزد جایزه نمونه ۱۴۰۱", '_aa_instagram' => 'sample_artist' ), array( 'نقاشی' ) ),
			self::add( 'artiey_artist', 'علی نمونه', array( '_aa_name_en' => 'Ali Sample', '_aa_birth' => 1355, '_aa_city' => 'اصفهان', '_aa_mediums' => 'مجسمه، چیدمان' ), array( 'مجسمه' ) ),
			self::add( 'artiey_artist', 'مریم نمونه', array( '_aa_name_en' => 'Maryam Sample', '_aa_birth' => 1370, '_aa_city' => 'تهران', '_aa_mediums' => 'عکاسی' ), array( 'عکاسی' ) ),
		);

		$g1 = self::add(
			'artiey_gallery',
			'گالری نمونه یک',
			array(
				'_ag_name_en' => 'Sample Gallery One', '_ag_founded' => 1385, '_ag_city' => 'تهران', '_ag_short_bio' => 'گالری نمونه برای نمایش امکانات افزونه.',
				'_ag_phone' => '021-12345678', '_ag_mobile' => '09121234567', '_ag_whatsapp' => '09121234567', '_ag_instagram' => 'sample_gallery',
				'_ag_email' => 'info@example.com', '_ag_address' => 'تهران، خیابان نمونه، پلاک ۱', '_ag_lat' => '35.7219', '_ag_lng' => '51.3347',
				'_ag_hours' => $hours, '_ag_artists' => array_slice( $artists, 0, 2 ),
			),
			array( 'هنر معاصر', 'نقاشی' )
		);
		$g2 = self::add(
			'artiey_gallery',
			'گالری نمونه دو',
			array(
				'_ag_name_en' => 'Sample Gallery Two', '_ag_founded' => 1395, '_ag_city' => 'اصفهان', '_ag_short_bio' => 'گالری دوم برای تست فیلتر شهر.',
				'_ag_status' => 'appointment', '_ag_address' => 'اصفهان، خیابان نمونه', '_ag_artists' => array( $artists[1], $artists[2] ),
			),
			array( 'مجسمه', 'عکاسی' )
		);

		$works = array(
			array( 'اثر نمونه یک', $artists[0], $g1, 'available', 120000000, 'رنگ روغن روی بوم', '100 × 70 سانتی‌متر', '1402' ),
			array( 'اثر نمونه دو', $artists[0], $g1, 'sold', 90000000, 'اکریلیک روی بوم', '80 × 80 سانتی‌متر', '1401' ),
			array( 'مجسمه نمونه', $artists[1], $g2, 'reserved', 250000000, 'برنز', '45 × 20 × 20 سانتی‌متر', '1400' ),
			array( 'عکس نمونه', $artists[2], $g2, 'available', 35000000, 'چاپ آرشیوی', '60 × 90 سانتی‌متر', '1403' ),
		);
		foreach ( $works as $w ) {
			self::add(
				'artiey_artwork',
				$w[0],
				array(
					'_aw_artist'     => $w[1],
					'_aw_gallery'    => $w[2],
					'_aw_sale'       => $w[3],
					'_aw_price'      => $w[4],
					'_aw_price_mode' => 'show',
					'_aw_medium'     => $w[5],
					'_aw_dimensions' => $w[6],
					'_aw_year'       => $w[7],
				)
			);
		}

		$ex = array(
			array( 'نمایشگاه گذشته نمونه', $g1, -40, -20, $artists[0], 'نمایشگاه انفرادی نقاشی' ),
			array( 'نمایشگاه جاری نمونه', $g1, -3, 10, $artists[0], 'نمایشگاه انفرادی نقاشی' ),
			array( 'نمایشگاه آینده نمونه', $g1, 12, 26, $artists[1], 'نمایشگاه گروهی' ),
			array( 'نمایشگاه جاری اصفهان', $g2, -1, 8, $artists[1], 'نمایشگاه انفرادی حجم' ),
			array( 'نمایشگاه عکس آینده', $g2, 5, 15, $artists[2], 'نمایشگاه عکس' ),
			array( 'نمایشگاه گذشته اصفهان', $g2, -60, -30, $artists[2], 'نمایشگاه عکس' ),
		);
		foreach ( $ex as $e ) {
			self::add(
				'artiey_exhibition',
				$e[0],
				array(
					'_ae_gallery' => $e[1],
					'_ae_start'   => self::day( $e[2] ),
					'_ae_end'     => self::day( $e[3] ),
					'_ae_artists' => array( $e[4] ),
					'_ae_type'    => $e[5],
				)
			);
		}
	}

	private static function remove() {
		$ids = get_posts(
			array(
				'post_type'   => array( 'artiey_gallery', 'artiey_exhibition', 'artiey_artist', 'artiey_artwork' ),
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_aet_demo',
				'meta_value'  => '1',
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}
}
