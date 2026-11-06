<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor layout assignments for database-backed Art COA routes.
 * Native WordPress CPT singles/archives remain Theme Builder locations;
 * these assignments cover the custom rewrite routes which bypass WP templates.
 */
final class Artiey_AET_Elementor_Layouts {
	const OPTION = 'artiey_aet_elementor_route_layouts';
	const CONTEXT_GLOBAL = 'artiey_aet_elementor_route_context';
	const CONTENT_GLOBAL = 'artiey_aet_elementor_route_content';

	/** Human-readable routes available for Elementor layout assignment. */
	public static function routes() {
		return array(
			'profile_artist'       => 'پروفایل هنرمند (Art COA)',
			'profile_curator'      => 'پروفایل کیوریتور',
			'profile_gallery'      => 'پروفایل گالری (Art COA)',
			'profile_exhibition'   => 'پروفایل نمایشگاه (Art COA)',
			'profile_collector'    => 'پروفایل مجموعه‌دار',
			'profile_artwork'      => 'پروفایل اثر (Art COA)',
			'profile_certificate'  => 'صفحهٔ گواهی اصالت',
			'archive_curators'     => 'آرشیو کیوریتورها',
			'archive_galleries'    => 'آرشیو گالری‌های Art COA',
			'archive_exhibitions'  => 'آرشیو نمایشگاه‌های Art COA',
			'archive_artworks'     => 'آرشیو آثار Art COA',
			'auction_archive'      => 'آرشیو حراج‌ها',
			'auction_single'       => 'صفحهٔ تکی حراج',
			'calls_archive'        => 'آرشیو فراخوان‌ها',
			'calls_single'         => 'صفحهٔ تکی فراخوان',
			'portfolio_archive'    => 'آرشیو پورتفولیو',
			'portfolio_artist'     => 'پورتفولیوی هنرمند',
			'portfolio_gallery'    => 'پورتفولیوی گالری',
			'shop_archive'         => 'آرشیو فروشگاه هنری',
			'shop_artist'          => 'فروش آثار هنرمند',
			'secondary_market'     => 'بازار ثانویه',
		);
	}


	/** Routes are grouped in the admin UI so profile/archive assignments remain manageable. */
	public static function route_groups() {
		return array(
			'پروفایل‌های تکی' => array( 'profile_artist', 'profile_curator', 'profile_gallery', 'profile_exhibition', 'profile_collector', 'profile_artwork', 'profile_certificate' ),
			'آرشیوها و فهرست‌ها' => array( 'archive_curators', 'archive_galleries', 'archive_exhibitions', 'archive_artworks' ),
			'حراج، فراخوان و بازار' => array( 'auction_archive', 'auction_single', 'calls_archive', 'calls_single', 'portfolio_archive', 'portfolio_artist', 'portfolio_gallery', 'shop_archive', 'shop_artist', 'secondary_market' ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 25 );
		add_shortcode( 'artiey_route_content', array( __CLASS__, 'route_content_shortcode' ) );
	}

	public static function admin_menu() {
		add_submenu_page(
			'artiey-platform',
			'قالب‌های اختصاصی صفحات Artiey',
			'قالب‌های صفحات Elementor',
			'manage_options',
			'artiey-elementor-layouts',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/** Return only published Elementor library documents which the current administrator can edit. */
	public static function available_templates() {
		if ( ! post_type_exists( 'elementor_library' ) ) {
			return array();
		}
		$posts = get_posts( array(
			'post_type'              => 'elementor_library',
			'post_status'            => 'publish',
			'numberposts'            => 250,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );
		$options = array( 0 => '— استفاده از نمایش پیش‌فرض —' );
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post || 'elementor_library' !== $post->post_type || 'publish' !== $post->post_status ) {
				continue;
			}
			$options[ (int) $post->ID ] = sprintf( '%s (ID: %d)', $post->post_title ? $post->post_title : 'قالب بدون عنوان', (int) $post->ID );
		}
		return $options;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازهٔ دسترسی به این صفحه را ندارید.', 'artiey-aet' ) );
		}
		if ( isset( $_POST['artiey_layouts_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by nonce below.
			check_admin_referer( 'artiey_elementor_layouts_save', 'artiey_elementor_layouts_nonce' );
			$posted = isset( $_POST['route_templates'] ) && is_array( $_POST['route_templates'] ) ? wp_unslash( $_POST['route_templates'] ) : array();
			$clean  = array();
			$available = self::available_templates();
			foreach ( self::routes() as $key => $label ) {
				$id = isset( $posted[ $key ] ) && is_scalar( $posted[ $key ] ) ? absint( $posted[ $key ] ) : 0;
				if ( $id && isset( $available[ $id ] ) && current_user_can( 'edit_post', $id ) ) {
					$clean[ $key ] = $id;
				}
			}
			update_option( self::OPTION, $clean, false );
			echo '<div class="notice notice-success is-dismissible"><p>قالب‌های صفحات ذخیره شدند.</p></div>';
		}

		$assigned = get_option( self::OPTION, array() );
		$assigned = is_array( $assigned ) ? $assigned : array();
		$templates = self::available_templates();
		$create_url = admin_url( 'post-new.php?post_type=elementor_library' );
		?>
		<div class="wrap artiey-layout-manager" dir="rtl">
			<style>
			.artiey-layout-manager{max-width:1500px}.artiey-layout-manager .aet-layout-intro{display:flex;gap:18px;align-items:center;justify-content:space-between;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px 24px;margin:18px 0}.artiey-layout-manager .aet-layout-intro h1{margin:0 0 8px;font-size:23px}.artiey-layout-manager .aet-layout-intro p{margin:0;color:#646970;line-height:1.9}.artiey-layout-manager .aet-layout-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:16px;margin:18px 0}.artiey-layout-manager .aet-layout-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;overflow:hidden}.artiey-layout-manager .aet-layout-card h2{font-size:15px;margin:0;padding:15px 18px;border-bottom:1px solid #eee;background:#f8f9fa}.artiey-layout-manager .aet-layout-card table{margin:0;border:0}.artiey-layout-manager .aet-layout-card th,.artiey-layout-manager .aet-layout-card td{display:block;padding:10px 16px 5px;border:0}.artiey-layout-manager .aet-layout-card th{padding-bottom:3px}.artiey-layout-manager .aet-layout-card td{padding-top:3px;padding-bottom:14px}.artiey-layout-manager .aet-layout-card select{width:100%;max-width:none}.artiey-layout-manager .aet-layout-card tr+tr{border-top:1px solid #f0f0f1}.artiey-layout-manager .aet-layout-card .description{margin:5px 0 0;line-height:1.7}.artiey-layout-manager .aet-layout-guide{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px 22px;margin:18px 0}.artiey-layout-manager .aet-layout-guide h2{margin-top:0}.artiey-layout-manager .aet-relation-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}.artiey-layout-manager .aet-relation-grid>div{background:#f8f9fa;border:1px solid #eee;border-radius:8px;padding:12px;line-height:1.8}.artiey-layout-manager .aet-layout-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}@media(max-width:782px){.artiey-layout-manager .aet-layout-intro{display:block}.artiey-layout-manager .aet-layout-actions{margin-top:14px}}
			</style>
			<div class="aet-layout-intro">
				<div><h1>مرکز طراحی صفحات و قالب‌های Artiey</h1><p>قالب‌های تکی و آرشیوی را یک‌بار در Elementor بسازید و از اینجا به مسیرهای دیتابیس‌محور Art COA متصل کنید. قالب‌های بومی وردپرس و آرشیو محتوایی هنرمندان نیز از مسیرهای استاندارد Elementor ویرایش می‌شوند.</p></div>
				<div class="aet-layout-actions"><a class="button button-primary" href="<?php echo esc_url( $create_url ); ?>">ساخت قالب جدید در Elementor</a><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=elementor_library' ) ); ?>">مدیریت قالب‌های ذخیره‌شده</a></div>
			</div>
			<?php if ( count( $templates ) <= 1 ) : ?>
				<div class="notice notice-warning"><p>هنوز قالب منتشرشده‌ای در کتابخانهٔ Elementor پیدا نشد. ابتدا یک قالب بسازید و منتشر کنید؛ سپس آن را به صفحهٔ مربوط اختصاص دهید.</p></div>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'artiey_elementor_layouts_save', 'artiey_elementor_layouts_nonce' ); ?>
				<div class="aet-layout-grid">
				<?php foreach ( self::route_groups() as $group_label => $route_keys ) : ?>
					<section class="aet-layout-card"><h2><?php echo esc_html( $group_label ); ?></h2><table class="form-table" role="presentation"><tbody>
					<?php foreach ( $route_keys as $key ) : if ( ! isset( self::routes()[ $key ] ) ) { continue; } ?>
						<tr><th scope="row"><label for="route-template-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( self::routes()[ $key ] ); ?></label></th><td><select id="route-template-<?php echo esc_attr( $key ); ?>" name="route_templates[<?php echo esc_attr( $key ); ?>]"><?php foreach ( $templates as $id => $title ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( absint( $assigned[ $key ] ?? 0 ), (int) $id ); ?>><?php echo esc_html( $title ); ?></option><?php endforeach; ?></select><p class="description">پیش‌فرض = نمایش فعلی افزونه. برای چیدمان سفارشی، ویجت «محتوای صفحهٔ جاری Artiey» را در محل دلخواه قرار دهید.</p></td></tr>
					<?php endforeach; ?>
					</tbody></table></section>
				<?php endforeach; ?>
				</div>
				<p><button type="submit" name="artiey_layouts_save" value="1" class="button button-primary button-hero">ذخیرهٔ تخصیص قالب‌ها</button></p>
			</form>

			<section class="aet-layout-guide"><h2>قالب‌های بومی وردپرس و Elementor Theme Builder</h2><p>این نوع‌محتواها مستقیماً در Elementor قابل ویرایش‌اند. برای قالب تکی/آرشیو شرط نمایش (Display Conditions) در Theme Builder از Elementor Pro استفاده کنید؛ برگهٔ <code>/artists/</code> یک برگهٔ عادی است و با «ویرایش با Elementor» طراحی می‌شود.</p><div class="aet-relation-grid">
				<div><strong>هنرمندان</strong><br><code>artiey_artist</code><br>Single Template: مسیر <code>/artists/{slug}/</code></div>
				<div><strong>گالری‌ها</strong><br><code>artiey_gallery</code><br>Single + Archive: <code>/galleries/</code></div>
				<div><strong>آثار هنری</strong><br><code>artiey_artwork</code><br>Single + Archive: <code>/artworks/</code></div>
				<div><strong>نمایشگاه‌ها</strong><br><code>artiey_exhibition</code><br>Single + Archive: <code>/exhibitions/</code></div>
				<div><strong>سبک‌های هنری</strong><br><code>artiey_style</code><br>آرشیو taxonomy و فیلتر سبک</div>
				<div><strong>آرشیو هنرمندان</strong><br><code>/artists/</code><br>برگهٔ مرکزی با آرشیو و فیلترهای هنرمندان</div>
				</div></section>
			<section class="aet-layout-guide"><h2>روابط محتوایی که باید در قالب‌ها حفظ شوند</h2><div class="aet-relation-grid">
				<div><strong>هنرمند</strong><br>آثار، گالری‌های همکار، نمایشگاه‌ها، کیوریتورها، حراج‌ها، گواهی‌ها و تاریخچهٔ هنری</div>
				<div><strong>گالری</strong><br>هنرمندان، آثار، نمایشگاه‌ها، حراج‌ها و پورتفولیوی گالری</div>
				<div><strong>اثر هنری</strong><br>هنرمند، گالری، گواهی اصالت، حراج، فروش و زنجیرهٔ منشأ/مالکیت</div>
				<div><strong>نمایشگاه</strong><br>گالری میزبان، هنرمندان شرکت‌کننده، آثار و کیوریتور</div>
				<div><strong>کیوریتور</strong><br>نمایشگاه‌ها و هنرمندان/گالری‌های مرتبط</div>
				<div><strong>مجموعه‌دار</strong><br>آثار و سابقهٔ مالکیت عمومی؛ اطلاعات خصوصی مالکیت برای عموم مخفی می‌ماند</div>
				<div><strong>حراج و فراخوان</strong><br>آثار، هنرمندان، برگزارکننده، وضعیت، زمان‌بندی و نتایج ثبت‌شده</div>
				</div><p class="description">ویجت‌های اختصاصی Artiey و COA را می‌توانید داخل قالب بگذارید؛ در قالب‌های مسیرهای دیتابیس‌محور، ویجت «محتوای صفحهٔ جاری Artiey» خروجی اصلی و روابط امن قبلی را در جای دلخواه نمایش می‌دهد.</p></section>
		</div>
		<?php
	}

	/**
	 * Render an assigned Elementor template around a database-backed route.
	 * The native route HTML remains the safe fallback and is also available via a widget.
	 */
	public static function render_route( $route_key, $context, $default_html ) {
		$route_key = sanitize_key( (string) $route_key );
		if ( ! isset( self::routes()[ $route_key ] ) || ! is_string( $default_html ) ) {
			return $default_html;
		}
		$old_context = isset( $GLOBALS[ self::CONTEXT_GLOBAL ] ) ? $GLOBALS[ self::CONTEXT_GLOBAL ] : null;
		$old_content = isset( $GLOBALS[ self::CONTENT_GLOBAL ] ) ? $GLOBALS[ self::CONTENT_GLOBAL ] : null;
		$context = is_array( $context ) ? $context : array();
		$context['route_key'] = $route_key;
		$GLOBALS[ self::CONTEXT_GLOBAL ] = $context;
		$GLOBALS[ self::CONTENT_GLOBAL ] = $default_html;

		$template_id = self::assigned_template_id( $route_key );
		$output = '';
		if ( $template_id && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->frontend )
			&& method_exists( \Elementor\Plugin::$instance->frontend, 'get_builder_content_for_display' ) ) {
			try {
				$output = (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id, true );
			} catch ( \Throwable $e ) {
				$output = '';
			}
		}

		if ( '' !== trim( $output ) ) {
			// Keep the original relational/profile content unless the editor explicitly inserted the route-content widget.
			if ( false === strpos( $output, '<!--artiey-route-content-->' ) ) {
				$output .= '<div class="artiey-route-fallback">' . $default_html . '</div>';
			}
			$output = '<div class="artiey-elementor-route artiey-route-' . esc_attr( $route_key ) . '" data-artiey-route="' . esc_attr( $route_key ) . '">' . $output . '</div>';
		} else {
			$output = $default_html;
		}

		if ( null === $old_context ) {
			unset( $GLOBALS[ self::CONTEXT_GLOBAL ] );
		} else {
			$GLOBALS[ self::CONTEXT_GLOBAL ] = $old_context;
		}
		if ( null === $old_content ) {
			unset( $GLOBALS[ self::CONTENT_GLOBAL ] );
		} else {
			$GLOBALS[ self::CONTENT_GLOBAL ] = $old_content;
		}
		return $output;
	}

	public static function assigned_template_id( $route_key ) {
		$route_key = sanitize_key( (string) $route_key );
		if ( ! isset( self::routes()[ $route_key ] ) ) {
			return 0;
		}
		$map = get_option( self::OPTION, array() );
		$id  = is_array( $map ) ? absint( $map[ $route_key ] ?? 0 ) : 0;
		if ( ! $id ) {
			return 0;
		}
		$template = get_post( $id );
		if ( ! $template || 'elementor_library' !== $template->post_type || 'publish' !== $template->post_status ) {
			return 0;
		}
		return $id;
	}

	public static function context( $key = null ) {
		$context = isset( $GLOBALS[ self::CONTEXT_GLOBAL ] ) && is_array( $GLOBALS[ self::CONTEXT_GLOBAL ] ) ? $GLOBALS[ self::CONTEXT_GLOBAL ] : array();
		if ( empty( $context ) ) {
			$context = self::detect_current_context();
		}
		if ( null === $key ) {
			return $context;
		}
		return isset( $context[ $key ] ) && is_scalar( $context[ $key ] ) ? $context[ $key ] : '';
	}

	/**
	 * Detect the entity displayed by a native WordPress single page or a legacy COA profile page.
	 * This lets relationship widgets work automatically inside Elementor Theme Builder templates.
	 */
	private static function detect_current_context() {
		if ( function_exists( 'get_query_var' ) ) {
			$legacy_type = sanitize_key( (string) get_query_var( 'coa_profile_type' ) );
			$legacy_slug = sanitize_title( (string) get_query_var( 'coa_profile_slug' ) );
			if ( '' !== $legacy_type && '' !== $legacy_slug && in_array( $legacy_type, array( 'artist', 'gallery', 'curator', 'collector' ), true ) ) {
				return array( 'module' => 'wp-coa', 'entity_type' => $legacy_type, 'slug' => $legacy_slug, 'mode' => 'single' );
			}
			$route_type = sanitize_key( (string) get_query_var( 'art_coa_type' ) );
			$route_slug = sanitize_title( (string) get_query_var( 'art_coa_slug' ) );
			if ( '' !== $route_type && '' !== $route_slug && in_array( $route_type, array( 'artist', 'gallery', 'artwork', 'exhibition', 'curator', 'collector' ), true ) ) {
				return array( 'module' => 'art-coa', 'entity_type' => $route_type, 'slug' => $route_slug, 'mode' => 'single' );
			}
		}

		$types = array(
			'artiey_artist' => 'artist',
			'artiey_gallery' => 'gallery',
			'artiey_artwork' => 'artwork',
			'artiey_exhibition' => 'exhibition',
		);
		foreach ( $types as $post_type => $entity_type ) {
			$post_id = 0;
			if ( class_exists( 'Artiey_AET_Elementor_Options' ) ) {
				$post_id = absint( Artiey_AET_Elementor_Options::current_post_id( $post_type ) );
			} elseif ( function_exists( 'get_queried_object_id' ) ) {
				$candidate = absint( get_queried_object_id() );
				$post_id = $candidate && function_exists( 'get_post_type' ) && $post_type === get_post_type( $candidate ) ? $candidate : 0;
			}
			if ( ! $post_id || ! function_exists( 'get_post' ) ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post || ! isset( $post->ID, $post->post_type, $post->post_name ) || $post_type !== $post->post_type ) {
				continue;
			}
			return array(
				'module' => 'wordpress',
				'entity_type' => $entity_type,
				'slug' => sanitize_title( (string) $post->post_name ),
				'post_id' => (int) $post->ID,
				'mode' => 'single',
			);
		}
		return array();
	}

	public static function current_route_content() {
		$content = $GLOBALS[ self::CONTENT_GLOBAL ] ?? '';
		if ( ! is_string( $content ) ) {
			return '';
		}
		return '<!--artiey-route-content-->' . $content;
	}

	public static function route_content_shortcode() {
		return self::current_route_content();
	}
}
