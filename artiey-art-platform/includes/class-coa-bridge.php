<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridge between the content model (galleries, artists, artworks as post types)
 * and the certificate model (WP COA Pro custom tables).
 *
 * Design rules:
 *  - Certificates are signed with COA's own table IDs, so the COA tables stay the
 *    source of truth for issuance. We never change or re-key them.
 *  - Content posts keep a *link* (post meta) to the COA row: `_ag_coa_gallery_id`,
 *    `_aa_coa_person_id`, `_aw_coa_certs`.
 *  - Everything here degrades gracefully when the COA module is unavailable.
 */
final class Artiey_AET_COA_Bridge {

	/** @var WP_COA_Pro_Repository|null */
	private static $repo = null;
	/** @var WP_COA_Pro_Signature|null */
	private static $sig = null;

	public static function init() {
		add_action( 'wp_coa_pro_certificate_issued', array( __CLASS__, 'on_issued' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'prefill_assets' ), 30 );

		add_filter( 'artiey_ap_gallery_tabs', array( __CLASS__, 'add_coa_tab' ) );
		add_filter( 'artiey_ap_artist_tabs', array( __CLASS__, 'add_coa_tab' ) );
		add_filter( 'artiey_ap_artwork_tabs', array( __CLASS__, 'add_coa_tab' ) );
		add_action( 'artiey_ap_gallery_panel_coa', array( __CLASS__, 'panel_gallery' ) );
		add_action( 'artiey_ap_artist_panel_coa', array( __CLASS__, 'panel_artist' ) );
		add_action( 'artiey_ap_artwork_panel_coa', array( __CLASS__, 'panel_artwork' ) );

		add_action( 'save_post_artiey_gallery', array( __CLASS__, 'save_gallery_link' ), 20 );
		add_action( 'save_post_artiey_artist', array( __CLASS__, 'save_artist_link' ), 20 );
	}

	/* ---------------------------------------------------------------- */
	/* Availability / services                                           */
	/* ---------------------------------------------------------------- */

	public static function available() {
		return class_exists( 'WP_COA_Pro_Repository' ) && class_exists( 'WP_COA_Pro_Signature' ) && class_exists( 'WP_COA_Pro_Ledger' );
	}

	public static function can_manage() {
		return self::available() && current_user_can( 'manage_coa_certificates' );
	}

	private static function repo() {
		if ( null === self::$repo ) {
			self::$sig  = new WP_COA_Pro_Signature();
			self::$repo = new WP_COA_Pro_Repository( self::$sig, new WP_COA_Pro_Ledger() );
		}
		return self::$repo;
	}

	private static function sig() {
		self::repo();
		return self::$sig;
	}

	private static function public_lookup_enabled() {
		if ( ! class_exists( 'WP_COA_Pro_Admin' ) ) {
			return false;
		}
		$s = WP_COA_Pro_Admin::get_settings();
		return ! empty( $s['enable_public_lookup'] );
	}

	public static function add_coa_tab( $tabs ) {
		if ( self::available() ) {
			$tabs['coa'] = 'گواهی اصالت';
		}
		return $tabs;
	}

	/* ---------------------------------------------------------------- */
	/* Counts + certificate summaries (read-only, public-safe)           */
	/* ---------------------------------------------------------------- */

	/** @return array{active:int,revoked:int,total:int} */
	public static function counts_for_coa_gallery( $coa_gallery_id ) {
		$out = array( 'active' => 0, 'revoked' => 0, 'total' => 0 );
		if ( ! self::available() || ! $coa_gallery_id ) {
			return $out;
		}
		$key = 'aet_coa_gal_' . (int) $coa_gallery_id . '_' . Artiey_AET_Settings::version() . '_' . get_option( 'wp_coa_pro_gallery_cache_generation', '1' );
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$repo = self::repo();
		$out['active']  = (int) $repo->count_records( array( 'gallery_id' => (int) $coa_gallery_id, 'status' => 'active' ) );
		$out['revoked'] = (int) $repo->count_records( array( 'gallery_id' => (int) $coa_gallery_id, 'status' => 'revoked' ) );
		$out['total']   = $out['active'] + $out['revoked'];
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** @return array{total:int,active:int,revoked:int} site-wide certificate totals. */
	public static function totals() {
		$out = array( 'total' => 0, 'active' => 0, 'revoked' => 0 );
		if ( ! self::available() ) {
			return $out;
		}
		$repo           = self::repo();
		$out['active']  = (int) $repo->count_records( array( 'status' => 'active' ) );
		$out['revoked'] = (int) $repo->count_records( array( 'status' => 'revoked' ) );
		$out['total']   = $out['active'] + $out['revoked'];
		return $out;
	}

	/** Latest certificates for the admin dashboard (admin-only, never exposed publicly). */
	public static function recent( $limit = 5 ) {
		if ( ! self::can_manage() ) {
			return array();
		}
		return self::repo()->list_records( array( 'per_page' => max( 1, min( 20, (int) $limit ) ), 'page' => 1 ) );
	}

	/** Label of the signing-key source ("constant" is the recommended one). */
	public static function key_source() {
		return self::available() ? (string) self::sig()->active_key_source() : '';
	}

	public static function active_count_for_gallery_post( $gallery_post_id ) {
		$id = (int) get_post_meta( $gallery_post_id, '_ag_coa_gallery_id', true );
		if ( ! $id || ! self::public_lookup_enabled() ) {
			return 0;
		}
		$c = self::counts_for_coa_gallery( $id );
		return (int) $c['active'];
	}

	/**
	 * Public-safe summaries of the certificates linked to an artwork.
	 * Only code, state and issue date are returned (no owner / notes data).
	 *
	 * @return array<int,array{code:string,state:string,label:string,url:string,issued:string}>
	 */
	public static function certificate_summaries( $artwork_id ) {
		$out = array();
		if ( ! self::available() ) {
			return $out;
		}
		$links = get_post_meta( $artwork_id, '_aw_coa_certs', true );
		if ( ! is_array( $links ) ) {
			return $out;
		}
		$repo   = self::repo();
		$sig    = self::sig();
		$lookup = self::public_lookup_enabled();

		foreach ( array_slice( $links, 0, 10 ) as $l ) {
			$row = isset( $l['id'] ) ? $repo->get_by_id( (int) $l['id'] ) : null;
			if ( ! $row ) {
				continue;
			}
			if ( 'revoked' === $row->status ) {
				$state = 'revoked';
				$label = 'گواهی ابطال شده است';
			} elseif ( ! $sig->verify( $row ) ) {
				$state = 'check';
				$label = 'گواهی نیازمند بررسی است';
			} else {
				$state = 'valid';
				$label = 'دارای گواهی اصالت معتبر';
			}
			$issued = '';
			if ( ! empty( $row->issue_date ) ) {
				$issued = Artiey_AET_Jalali::format_long( substr( (string) $row->issue_date, 0, 10 ) );
			}
			$out[] = array(
				'code'   => (string) $row->certificate_code,
				'state'  => $state,
				'label'  => $label,
				'url'    => $lookup ? WP_COA_Pro::get_verification_url( $row->certificate_code ) : '',
				'issued' => $issued,
			);
		}
		return $out;
	}

	/** Number of active certificates linked to artworks of this artist (via COA person profile). */
	public static function active_count_for_artist_post( $artist_post_id ) {
		$pid = (int) get_post_meta( $artist_post_id, '_aa_coa_person_id', true );
		if ( ! $pid || ! self::available() || ! self::public_lookup_enabled() ) {
			return 0;
		}
		global $wpdb;
		$key = 'aet_coa_art_' . $pid . '_' . Artiey_AET_Settings::version();
		$hit = get_transient( $key );
		if ( false !== $hit ) {
			return (int) $hit;
		}
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}coa_certificates WHERE artist_profile_id = %d AND status = 'active'", $pid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		set_transient( $key, $n, 10 * MINUTE_IN_SECONDS );
		return $n;
	}

	/* ---------------------------------------------------------------- */
	/* Admin panels (gallery / artist / artwork)                         */
	/* ---------------------------------------------------------------- */

	private static function admin_link( $page, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	public static function panel_gallery( $post ) {
		$linked   = (int) get_post_meta( $post->ID, '_ag_coa_gallery_id', true );
		$profiles = WP_COA_Pro_Galleries::list_profiles( false, true );
		$manage   = self::can_manage();

		echo '<p class="aetg-hint">هر گواهی اصالت با «پروفایل صادرکننده» (گالری/مؤسسه) امضا می‌شود. گالری این صفحه را به یک پروفایل صادرکننده وصل کنید تا گواهی‌های صادرشده در صفحه عمومی گالری شمارش شوند.</p>';
		echo '<div class="aetg-field"><label>پروفایل صادرکننده گواهی</label>';
		echo '<select name="aetg_coa[gallery]"' . ( $manage ? '' : ' disabled' ) . '><option value="0">— بدون اتصال —</option>';
		foreach ( $profiles as $p ) {
			$name = $p->name ? $p->name : $p->name_en;
			echo '<option value="' . esc_attr( $p->id ) . '" ' . selected( $linked, (int) $p->id, false ) . '>' . esc_html( $name . ( $p->name_en && $p->name ? ' — ' . $p->name_en : '' ) ) . '</option>';
		}
		echo '</select></div>';

		if ( $manage ) {
			echo '<div class="aetg-field"><label class="aap-inline"><input type="checkbox" name="aetg_coa[create]" value="1"> ساخت خودکار پروفایل صادرکننده از اطلاعات همین گالری (نام فارسی/لاتین، لوگو)</label>';
			echo '<div class="aetg-hint">پروفایل جدید ساخته و همین‌جا متصل می‌شود. برند، قالب گواهی و فیلدهای عمومی را بعداً از «گواهی‌های اصالت ← گالری‌ها و پروفایل‌ها» تنظیم کنید.</div></div>';
		} else {
			echo '<p class="aetg-hint">برای تغییر اتصال، دسترسی «مدیریت گواهی‌ها» لازم است.</p>';
		}

		if ( $linked ) {
			$c = self::counts_for_coa_gallery( $linked );
			echo '<p><strong>گواهی‌های این گالری:</strong> ' . esc_html( Artiey_AET_Jalali::to_persian_digits( $c['active'] ) ) . ' فعال، ' . esc_html( Artiey_AET_Jalali::to_persian_digits( $c['revoked'] ) ) . ' ابطال‌شده — ';
			if ( $manage ) {
				echo '<a href="' . esc_url( self::admin_link( 'wp-coa-pro', array( 'gallery_id' => $linked ) ) ) . '">مشاهده گواهی‌ها</a> · <a href="' . esc_url( self::admin_link( 'wp-coa-pro-galleries', array( 'edit_gallery' => $linked ) ) ) . '">ویرایش پروفایل صادرکننده</a>';
			}
			echo '</p>';
		}
	}

	public static function panel_artist( $post ) {
		$linked   = (int) get_post_meta( $post->ID, '_aa_coa_person_id', true );
		$profiles = WP_COA_Pro_Person_Profiles::list_profiles( 'artist', 'all', 500, '', false, 'admin' );
		$manage   = self::can_manage();

		echo '<p class="aetg-hint">پروفایل رسمی هنرمند در سامانه گواهی، در امضای گواهی ثبت می‌شود. این هنرمند را به آن وصل کنید تا گواهی‌های هنرمند شمارش و در صفحه عمومی نمایش داده شوند.</p>';
		echo '<div class="aetg-field"><label>پروفایل رسمی هنرمند در سامانه گواهی</label>';
		echo '<select name="aetg_coa[person]"' . ( $manage ? '' : ' disabled' ) . '><option value="0">— بدون اتصال —</option>';
		foreach ( $profiles as $p ) {
			echo '<option value="' . esc_attr( $p->id ) . '" ' . selected( $linked, (int) $p->id, false ) . '>' . esc_html( $p->display_name . ( $p->display_name_en ? ' — ' . $p->display_name_en : '' ) ) . '</option>';
		}
		echo '</select></div>';

		if ( $manage ) {
			echo '<div class="aetg-field"><label class="aap-inline"><input type="checkbox" name="aetg_coa[create]" value="1"> ساخت خودکار پروفایل رسمی از اطلاعات همین هنرمند</label>';
			echo '<div class="aetg-hint">نام‌ها، شهر، سال تولد، رسانه، تحصیلات، جوایز، اینستاگرام، وب‌سایت و عکس منتقل می‌شوند. ایمیل و تلفن هرگز منتقل نمی‌شوند.</div></div>';
		}
		if ( $linked ) {
			echo '<p><strong>گواهی‌های فعال این هنرمند:</strong> ' . esc_html( Artiey_AET_Jalali::to_persian_digits( self::active_count_for_artist_post( $post->ID ) ) );
			if ( $manage ) {
				echo ' — <a href="' . esc_url( self::admin_link( 'wp-coa-pro-people', array( 'edit_profile' => $linked ) ) ) . '">ویرایش پروفایل رسمی</a>';
			}
			echo '</p>';
		}
	}

	public static function panel_artwork( $post ) {
		$certs  = self::certificate_summaries( $post->ID );
		$manage = self::can_manage();

		if ( $certs ) {
			echo '<table class="widefat striped aap-s-371f3d"><thead><tr><th>کد گواهی</th><th>وضعیت</th><th>تاریخ صدور</th></tr></thead><tbody>';
			foreach ( $certs as $c ) {
				echo '<tr><td><code dir="ltr">' . esc_html( $c['code'] ) . '</code></td><td>' . esc_html( $c['label'] ) . '</td><td>' . esc_html( $c['issued'] ? $c['issued'] : '—' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>برای این اثر هنوز گواهی اصالتی ثبت نشده است.</p>';
		}

		if ( $manage && in_array( get_post_status( $post->ID ), array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ) {
			$url = self::admin_link(
				'wp-coa-pro',
				array(
					'aet_artwork' => $post->ID,
					'_aet_pf'     => wp_create_nonce( 'aet_prefill_' . $post->ID ),
				)
			);
			echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">صدور گواهی اصالت برای این اثر</a></p>';
			echo '<p class="aetg-hint">فرم صدور گواهی با اطلاعات همین اثر (عنوان، هنرمند، رسانه، ابعاد، سال، عکس و گالری) پر می‌شود. بعد از صدور، گواهی خودکار به این اثر وصل می‌شود. اطلاعات امضاشده بعد از صدور قابل ویرایش نیستند؛ قبل از صدور اطلاعات اثر را مرور کنید.</p>';
		} elseif ( ! $manage ) {
			echo '<p class="aetg-hint">برای صدور گواهی، دسترسی «مدیریت گواهی‌ها» لازم است.</p>';
		}
	}

	/* ---------------------------------------------------------------- */
	/* Saving links / creating COA profiles                              */
	/* ---------------------------------------------------------------- */

	private static function can_save_link( $post_id, $nonce_field, $nonce_action ) {
		if ( ! self::can_manage() ) {
			return false;
		}
		if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
			return false;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id ) && isset( $_POST['aetg_coa'] ) && is_array( $_POST['aetg_coa'] );
	}

	public static function save_gallery_link( $post_id ) {
		if ( ! self::can_save_link( $post_id, 'aet_gal_nonce', 'aet_gal_save' ) ) {
			return;
		}
		$in = wp_unslash( $_POST['aetg_coa'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised below.

		if ( ! empty( $in['create'] ) ) {
			$id = self::create_gallery_profile( $post_id );
			if ( $id ) {
				update_post_meta( $post_id, '_ag_coa_gallery_id', $id );
				return;
			}
		}
		$sel = isset( $in['gallery'] ) ? absint( $in['gallery'] ) : 0;
		if ( $sel && WP_COA_Pro_Galleries::get_profile( $sel ) ) {
			update_post_meta( $post_id, '_ag_coa_gallery_id', $sel );
		} else {
			delete_post_meta( $post_id, '_ag_coa_gallery_id' );
		}
		self::bust();
	}

	public static function save_artist_link( $post_id ) {
		if ( ! self::can_save_link( $post_id, 'aet_art_nonce', 'aet_art_save' ) ) {
			return;
		}
		$in = wp_unslash( $_POST['aetg_coa'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised below.

		if ( ! empty( $in['create'] ) ) {
			$id = self::create_person_profile( $post_id );
			if ( $id ) {
				update_post_meta( $post_id, '_aa_coa_person_id', $id );
				return;
			}
		}
		$sel = isset( $in['person'] ) ? absint( $in['person'] ) : 0;
		$p   = $sel ? WP_COA_Pro_Person_Profiles::get_profile( $sel, false ) : null;
		if ( $p && 'artist' === $p->profile_type ) {
			update_post_meta( $post_id, '_aa_coa_person_id', $sel );
		} else {
			delete_post_meta( $post_id, '_aa_coa_person_id' );
		}
		self::bust();
	}

	private static function bust() {
		Artiey_AET_Settings::bump();
		if ( class_exists( 'WP_COA_Pro_Page_Integration' ) ) {
			WP_COA_Pro_Page_Integration::invalidate_public_stats_cache();
		}
	}

	private static function unique_slug( $table, $base, $max ) {
		global $wpdb;
		$base = $base ? $base : 'item';
		$slug = substr( $base, 0, $max );
		$n    = 2;
		while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug ) ) > 0 ) { // phpcs:ignore WordPress.DB
			$slug = substr( $base, 0, $max - 6 ) . '-' . $n;
			++$n;
			if ( $n > 1000 ) {
				return '';
			}
		}
		return $slug;
	}

	/** Create a COA issuer profile from a gallery post. @return int new COA gallery id or 0. */
	public static function create_gallery_profile( $post_id ) {
		global $wpdb;
		$table   = WP_COA_Pro_Galleries::table_name();
		$name    = sanitize_text_field( get_the_title( $post_id ) );
		$name_en = sanitize_text_field( (string) get_post_meta( $post_id, '_ag_name_en', true ) );
		if ( '' === $name && '' === $name_en ) {
			return 0;
		}
		$slug = self::unique_slug( $table, Artiey_AET_Slug::from( '' !== $name_en ? $name_en : $name, 'gallery' ), 90 );
		if ( '' === $slug ) {
			return 0;
		}
		$brand = WP_COA_Pro_Admin::get_settings();
		$now   = current_time( 'mysql', true );

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'name'          => $name,
				'name_en'       => $name_en,
				'slug'          => $slug,
				'activity_type' => 'gallery',
				'logo_url'      => esc_url_raw( (string) get_the_post_thumbnail_url( $post_id, 'medium' ) ),
				'primary_color' => $brand['primary_color'],
				'accent_color'  => $brand['accent_color'],
				'status'        => 'active',
				'is_default'    => 0,
				'created_by'    => get_current_user_id(),
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
		if ( false === $ok ) {
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		WP_COA_Pro_Galleries::bust_public_cache();
		self::bust();
		return $id;
	}

	/** Create a COA artist profile from an artist post. @return int new person id or 0. */
	public static function create_person_profile( $post_id ) {
		global $wpdb;
		$table   = WP_COA_Pro_Person_Profiles::table_name();
		$name    = sanitize_text_field( get_the_title( $post_id ) );
		$name_en = sanitize_text_field( (string) get_post_meta( $post_id, '_aa_name_en', true ) );
		if ( '' === $name && '' === $name_en ) {
			return 0;
		}
		$slug = self::unique_slug( $table, Artiey_AET_Slug::from( '' !== $name_en ? $name_en : $name, 'artist' ), 170 );
		if ( '' === $slug ) {
			$slug = 'artist-' . wp_generate_password( 6, false, false );
		}
		$a   = Artiey_AET_Artist::get( $post_id );
		$now = current_time( 'mysql', true );
		$tid = (int) get_post_thumbnail_id( $post_id );

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'profile_type'      => 'artist',
				'entity_kind'       => 'person',
				'display_name'      => $name,
				'display_name_en'   => $name_en,
				'slug'              => $slug,
				'biography'         => trim( $a['short_bio'] . "\n\n" . $a['statement'] ),
				'profile_image_url' => esc_url_raw( (string) get_the_post_thumbnail_url( $post_id, 'large' ) ),
				'profile_image_id'  => $tid,
				'birth_year'        => $a['birth'] ? (string) $a['birth'] : '',
				'nationality'       => $a['nationality'],
				'city'              => $a['city'],
				'disciplines'       => $a['mediums'],
				'education'         => $a['education'],
				'awards'            => implode( "\n", $a['awards'] ),
				'website_url'       => esc_url_raw( $a['website'] ),
				'instagram_url'     => $a['instagram'] ? esc_url_raw( 'https://instagram.com/' . $a['instagram'] ) : '',
				'status'            => 'published',
				'created_by'        => get_current_user_id(),
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);
		if ( false === $ok ) {
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		WP_COA_Pro_Person_Profiles::bust_public_cache();
		self::bust();
		return $id;
	}

	/* ---------------------------------------------------------------- */
	/* Issue certificate from an artwork                                 */
	/* ---------------------------------------------------------------- */

	public static function prefill_assets( $hook ) {
		if ( ! self::can_manage() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below with a dedicated nonce.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'wp-coa-pro' !== $page || empty( $_GET['aet_artwork'] ) ) {
			return;
		}
		$id    = absint( $_GET['aet_artwork'] );
		$nonce = isset( $_GET['_aet_pf'] ) ? sanitize_text_field( wp_unslash( $_GET['_aet_pf'] ) ) : '';
		// phpcs:enable
		if ( ! $id || ! wp_verify_nonce( $nonce, 'aet_prefill_' . $id ) || 'artiey_artwork' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}

		wp_enqueue_script( 'artiey-ap-coa-prefill', AET_URL . 'assets/js/coa-prefill.js', array( 'jquery' ), AET_VERSION, true );
		wp_localize_script( 'artiey-ap-coa-prefill', 'aetPrefill', Artiey_AET_Artwork::coa_prefill( $id ) );
	}

	/** Called by the COA module right after a certificate is issued from its admin form. */
	public static function on_issued( $record, $data ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- COA verified the nonce for this request.
		$aw = isset( $_POST['aet_artwork_id'] ) ? absint( wp_unslash( $_POST['aet_artwork_id'] ) ) : 0;
		if ( ! $aw || 'artiey_artwork' !== get_post_type( $aw ) || ! current_user_can( 'edit_post', $aw ) || ! is_object( $record ) ) {
			return;
		}
		$list = get_post_meta( $aw, '_aw_coa_certs', true );
		$list = is_array( $list ) ? $list : array();
		foreach ( $list as $l ) {
			if ( isset( $l['id'] ) && (int) $l['id'] === (int) $record->id ) {
				return;
			}
		}
		$list[] = array(
			'id'   => (int) $record->id,
			'code' => (string) $record->certificate_code,
			'at'   => time(),
		);
		update_post_meta( $aw, '_aw_coa_certs', $list );
		self::bust();
	}
}
