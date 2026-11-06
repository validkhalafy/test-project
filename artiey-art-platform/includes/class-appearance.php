<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide look & feel of every Artiey module from one screen.
 *
 * Output is a small block of CSS custom properties attached to the shared design-system stylesheet, so
 * Elementor style controls (which write more specific selectors) always win over these defaults.
 * Every value is validated; an empty value means "keep the module default".
 */
final class Artiey_AET_Appearance {

	const SLUG   = 'artiey-aet-appearance';
	const OPTION = 'artiey_aet_appearance';
	const SAVE   = 'artiey_aet_appearance_save';
	const RESET  = 'artiey_aet_appearance_reset';
	const HANDLE = 'artiey-ui-system';
	const CSS_OPTION = 'artiey_aet_appearance_css';

	const FONTS = array(
		''           => array( 'label' => 'پیش‌فرض (فونت قالب)', 'stack' => '' ),
		'system'     => array( 'label' => 'فونت سیستم', 'stack' => 'system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif' ),
		'vazirmatn'  => array( 'label' => 'وزیرمتن (در صورت نصب)', 'stack' => 'Vazirmatn,Vazir,"Segoe UI",Tahoma,sans-serif' ),
		'iransans'   => array( 'label' => 'ایران‌سنس (در صورت نصب)', 'stack' => '"IRANSansX",IRANSans,"Segoe UI",Tahoma,sans-serif' ),
		'serif'      => array( 'label' => 'سریف کلاسیک', 'stack' => 'Georgia,"Times New Roman","Noto Naskh Arabic",serif' ),
		'custom'     => array( 'label' => 'سفارشی', 'stack' => '' ),
	);

	const SHADOWS = array(
		''       => 'پیش‌فرض',
		'none'   => 'بدون سایه',
		'soft'   => 'ملایم',
		'strong' => 'پررنگ',
	);

	const SHADOW_CSS = array(
		'none'   => 'none',
		'soft'   => '0 1px 2px rgba(0,0,0,.06),0 4px 14px rgba(0,0,0,.06)',
		'strong' => '0 2px 4px rgba(0,0,0,.08),0 12px 32px rgba(0,0,0,.16)',
	);

	private static $inline_added = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'attach' ), 100 );
		add_action( 'wp_footer', array( __CLASS__, 'attach' ), 2 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 22 );
		add_action( 'admin_post_' . self::SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::RESET, array( __CLASS__, 'handle_reset' ) );
	}

	/* ---------------------------------------------------------------- */
	/* Data                                                              */
	/* ---------------------------------------------------------------- */

	public static function defaults() {
		return array(
			'primary'     => '',
			'accent'      => '',
			'radius'      => '',
			'shadow'      => '',
			'font'        => '',
			'custom_font' => '',
			'font_size'   => '',
			'max_width'   => '',
			'custom_css'  => '',
		);
	}

	public static function current() {
		$saved = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $saved ) ? $saved : array() );
	}

	private static function int_in_range( $value, $min, $max ) {
		if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
			return '';
		}
		return (string) max( $min, min( $max, (int) $value ) );
	}

	/** Whitelist-style validation. Unknown or invalid values collapse to '' (module default). */
	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$out = self::defaults();

		foreach ( array( 'primary', 'accent' ) as $key ) {
			$color       = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( (string) $raw[ $key ] ) : '';
			$out[ $key ] = preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ? strtolower( $color ) : '';
		}

		$out['radius']    = self::int_in_range( $raw['radius'] ?? '', 0, 32 );
		$out['font_size'] = self::int_in_range( $raw['font_size'] ?? '', 13, 20 );
		$out['max_width'] = self::int_in_range( $raw['max_width'] ?? '', 720, 1600 );

		$shadow         = isset( $raw['shadow'] ) && is_scalar( $raw['shadow'] ) ? (string) $raw['shadow'] : '';
		$out['shadow']  = isset( self::SHADOW_CSS[ $shadow ] ) ? $shadow : '';

		$font        = isset( $raw['font'] ) && is_scalar( $raw['font'] ) ? (string) $raw['font'] : '';
		$out['font'] = isset( self::FONTS[ $font ] ) ? $font : '';

		// Font-family list: letters (any script), digits, spaces, commas, hyphens, dots and quotes only.
		$custom = isset( $raw['custom_font'] ) && is_scalar( $raw['custom_font'] ) ? trim( (string) $raw['custom_font'] ) : '';
		$custom = preg_replace( '/[^\p{L}\p{N}\s,\-_."\']/u', '', $custom );
		$out['custom_font'] = substr( (string) $custom, 0, 200 );

		$css = isset( $raw['custom_css'] ) && is_scalar( $raw['custom_css'] ) ? (string) $raw['custom_css'] : '';
		$out['custom_css'] = self::clean_css( $css );
		return $out;
	}

	/** Strip anything that could break out of a <style> block or load remote/executable content. */
	public static function clean_css( $css ) {
		$css = wp_strip_all_tags( (string) $css );
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = preg_replace( '/@import[^;]*;?/i', '', $css );
		$css = preg_replace( '/expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding|<\s*\/?\s*style/i', '', $css );
		return trim( substr( (string) $css, 0, 8000 ) );
	}

	public static function font_stack( $s ) {
		if ( 'custom' === $s['font'] ) {
			return $s['custom_font'];
		}
		return self::FONTS[ $s['font'] ]['stack'] ?? '';
	}

	/* ---------------------------------------------------------------- */
	/* CSS                                                               */
	/* ---------------------------------------------------------------- */

	/** Build the override stylesheet; returns '' when nothing is customised. */
	public static function build_css( $s ) {
		$s   = self::sanitize( $s );
		$css = '';
		$root = array();

		$p = $s['primary'];
		$a = $s['accent'];

		if ( '' !== $p ) {
			$root[] = '--art-coa-primary:' . $p;
			$root[] = '--art-coa-primary-soft:' . self::soft( $p );
			$root[] = '--aap-primary:' . $p;
		}
		if ( '' !== $a ) {
			$root[] = '--art-coa-accent:' . $a;
			$root[] = '--aap-secondary:' . $a;
		}
		if ( '' !== $s['radius'] ) {
			$r      = (int) $s['radius'];
			$root[] = '--aap-r-sm:' . (int) round( $r * 0.6 ) . 'px';
			$root[] = '--aap-r:' . $r . 'px';
			$root[] = '--aap-r-lg:' . (int) round( $r * 1.6 ) . 'px';
			$root[] = '--ac-radius:' . $r . 'px';
		}
		if ( '' !== $s['shadow'] ) {
			$root[] = '--aap-shadow:' . self::SHADOW_CSS[ $s['shadow'] ];
		}
		if ( '' !== $s['font_size'] ) {
			$root[] = '--aap-fs:' . (int) $s['font_size'] . 'px';
		}
		if ( $root ) {
			$css .= ':root{' . implode( ';', $root ) . '}';
		}

		// Modules declare some variables on their own wrappers, so the root value is not inherited there.
		if ( '' !== $a ) {
			$css .= '.aetp{--aetp-accent:' . $a . '}';
		}
		if ( '' !== $p || '' !== $a ) {
			$scoped = array();
			if ( '' !== $p ) {
				$scoped[] = '--wp-coa-directory-primary:' . $p . ';--wp-coa-stats-primary:' . $p . ';--wp-coa-guide-primary:' . $p;
			}
			if ( '' !== $a ) {
				$scoped[] = '--wp-coa-directory-accent:' . $a . ';--wp-coa-stats-accent:' . $a . ';--wp-coa-guide-accent:' . $a;
			}
			$css .= '.wp-coa-directory,.wp-coa-public-stats,.wp-coa-certificate-guide{' . implode( ';', $scoped ) . '}';
		}
		if ( '' !== $p ) {
			$css .= '.art-coa-timeline{--art-coa-tl-accent:' . $p . '}';
			$css .= '.art-coa-artwork-history{--art-coa-ah-accent:' . $p . '}';
			$css .= '.art-coa-bio-sec{--art-coa-bio-tl:' . $p . '}';
		}
		if ( '' !== $s['radius'] ) {
			$r    = (int) $s['radius'];
			$css .= '.art-coa-profile-wrap{--ac-radius:' . $r . 'px}';
			$css .= '.art-coa-timeline{--art-coa-tl-radius:' . (int) round( $r * 1.6 ) . 'px}';
		}
		if ( '' !== $s['shadow'] ) {
			$css .= ':is(.art-coa-artist-profile,.art-coa-gallery-profile,.art-coa-curator-profile,.art-coa-exhibition-profile){--acp-shadow:' . self::SHADOW_CSS[ $s['shadow'] ] . '}';
		}
		if ( '' !== $s['max_width'] ) {
			$css .= ':is(.art-coa-artist-profile,.art-coa-gallery-profile,.art-coa-curator-profile,.art-coa-exhibition-profile){max-width:' . (int) $s['max_width'] . 'px}';
		}
		$stack = self::font_stack( $s );
		if ( '' !== $stack ) {
			$css .= ':where(.aetp,.aetd,.aet,[class*="art-coa-"],[class*="wp-coa-"]){font-family:' . $stack . '}';
		}
		if ( '' !== $s['font_size'] ) {
			$css .= ':where(.aetp,.aetd,.art-coa-profile-wrap,.art-coa-catalog){font-size:' . (int) $s['font_size'] . 'px}';
		}
		if ( '' !== $s['custom_css'] ) {
			$css .= $s['custom_css'];
		}
		return $css;
	}

	/** 8-digit hex with ~13% alpha, expanding #abc to #aabbcc first. */
	public static function soft( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return '#' . strtolower( $hex ) . '22';
	}

	/** Attach the override block to the shared stylesheet once it is on the page. */
	public static function attach() {
		if ( self::$inline_added || is_admin() ) {
			return;
		}
		if ( ! wp_style_is( self::HANDLE, 'enqueued' ) || wp_style_is( self::HANDLE, 'done' ) ) {
			return;
		}
		// Compiled once on save; a front-end request only reads one autoloaded option.
		$css = get_option( self::CSS_OPTION, null );
		if ( ! is_string( $css ) ) {
			$css = self::build_css( self::current() );
			update_option( self::CSS_OPTION, $css, true );
		}
		if ( '' === $css ) {
			return;
		}
		self::$inline_added = true;
		wp_add_inline_style( self::HANDLE, $css );
	}

	/* ---------------------------------------------------------------- */
	/* Admin                                                             */
	/* ---------------------------------------------------------------- */

	public static function menu() {
		$hook = add_submenu_page( 'artiey-platform', 'ظاهر و شخصی‌سازی', 'ظاهر و شخصی‌سازی', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
		if ( $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'load_assets' ) );
		}
	}

	public static function load_assets() {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'artiey-aet' ) );
		}
		check_admin_referer( self::SAVE );
		$raw = isset( $_POST['aap'] ) && is_array( $_POST['aap'] ) ? wp_unslash( $_POST['aap'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$clean = self::sanitize( $raw );
		update_option( self::OPTION, $clean, false );
		update_option( self::CSS_OPTION, self::build_css( $clean ), true );
		if ( class_exists( 'Artiey_AET_Settings' ) ) {
			Artiey_AET_Settings::bump(); // rendered widget output is cached against this version
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'artiey-aet' ) );
		}
		check_admin_referer( self::RESET );
		delete_option( self::OPTION );
		delete_option( self::CSS_OPTION );
		if ( class_exists( 'Artiey_AET_Settings' ) ) {
			Artiey_AET_Settings::bump();
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'reset' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::current();
		?>
		<div class="wrap" dir="rtl">
			<h1>ظاهر و شخصی‌سازی پلتفرم آرتی</h1>
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>تنظیمات ظاهر ذخیره شد.</p></div>
			<?php elseif ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>همهٔ تنظیمات ظاهر به پیش‌فرض برگشت.</p></div>
			<?php endif; ?>
			<p>این تنظیمات روی همهٔ ویجت‌ها و صفحه‌های پلتفرم اعمال می‌شود. فیلد خالی یعنی «پیش‌فرض». تنظیمات استایل هر ویجت در Elementor همچنان اولویت دارد.</p>
			<div style="display:flex;gap:32px;flex-wrap:wrap;align-items:flex-start">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="flex:1 1 420px;max-width:620px">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE ); ?>">
				<?php wp_nonce_field( self::SAVE ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th><label for="aap-primary">رنگ اصلی</label></th><td><input id="aap-primary" class="aap-color" data-var="p" type="text" name="aap[primary]" value="<?php echo esc_attr( $s['primary'] ); ?>" data-default-color="#1a365d"></td></tr>
					<tr><th><label for="aap-accent">رنگ تأکیدی</label></th><td><input id="aap-accent" class="aap-color" data-var="a" type="text" name="aap[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>" data-default-color="#c9a227"></td></tr>
					<tr><th><label for="aap-radius">گردی گوشه‌ها (۰ تا ۳۲ پیکسل)</label></th><td><input id="aap-radius" data-var="r" type="number" min="0" max="32" name="aap[radius]" value="<?php echo esc_attr( $s['radius'] ); ?>" placeholder="۱۰"></td></tr>
					<tr><th><label for="aap-shadow">سایهٔ کارت‌ها</label></th><td><select id="aap-shadow" data-var="sh" name="aap[shadow]">
						<?php foreach ( self::SHADOWS as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['shadow'], $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
					<tr><th><label for="aap-font">فونت</label></th><td>
						<select id="aap-font" data-var="f" name="aap[font]">
							<?php foreach ( self::FONTS as $k => $f ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" data-stack="<?php echo esc_attr( $f['stack'] ); ?>" <?php selected( $s['font'], $k ); ?>><?php echo esc_html( $f['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p><input type="text" dir="ltr" class="regular-text" name="aap[custom_font]" value="<?php echo esc_attr( $s['custom_font'] ); ?>" placeholder='Vazirmatn, Tahoma, sans-serif'></p>
						<p class="description">فونت فقط از میان فونت‌های نصب‌شده در قالب یا سیستم بازدیدکننده انتخاب می‌شود؛ فایل فونتی از بیرون بارگذاری نمی‌شود.</p>
					</td></tr>
					<tr><th><label for="aap-fs">اندازهٔ متن پایه (۱۳ تا ۲۰)</label></th><td><input id="aap-fs" type="number" min="13" max="20" name="aap[font_size]" value="<?php echo esc_attr( $s['font_size'] ); ?>" placeholder="۱۵"></td></tr>
					<tr><th><label for="aap-mw">حداکثر عرض پروفایل‌ها (۷۲۰ تا ۱۶۰۰)</label></th><td><input id="aap-mw" type="number" min="720" max="1600" step="10" name="aap[max_width]" value="<?php echo esc_attr( $s['max_width'] ); ?>" placeholder="۱۱۲۰"></td></tr>
					<tr><th><label for="aap-css">CSS سفارشی</label></th><td>
						<textarea id="aap-css" dir="ltr" rows="6" class="large-text code" name="aap[custom_css]"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
						<p class="description">برچسب HTML، <code>@import</code> و آدرس‌های اجرایی هنگام ذخیره حذف می‌شوند.</p>
					</td></tr>
				</tbody></table>
				<?php submit_button( 'ذخیرهٔ تنظیمات', 'primary', 'submit', false ); ?>
			</form>
			<div style="flex:0 1 320px">
				<h2>پیش‌نمایش زنده</h2>
				<div id="aap-prev" style="--p:#1a365d;--a:#c9a227;--r:10px;--sh:0 1px 2px rgba(0,0,0,.06),0 4px 14px rgba(0,0,0,.06);background:#fff;border:1px solid #dcdcde;border-radius:var(--r);box-shadow:var(--sh);padding:18px">
					<span style="display:inline-block;background:var(--a);color:#fff;border-radius:999px;padding:2px 10px;font-size:12px">نمایشگاه فعال</span>
					<h3 style="color:var(--p);margin:10px 0 6px">عنوان نمونه</h3>
					<p style="margin:0 0 12px;color:#50575e">متن توضیحی برای دیدن فونت، رنگ‌ها و گردی گوشه‌ها.</p>
					<a href="#" onclick="return false" style="display:inline-block;background:var(--p);color:#fff;border-radius:var(--r);padding:8px 16px;text-decoration:none">مشاهدهٔ جزئیات</a>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px" onsubmit="return confirm('همهٔ تنظیمات ظاهر به پیش‌فرض برگردد؟');">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::RESET ); ?>">
					<?php wp_nonce_field( self::RESET ); ?>
					<?php submit_button( 'بازگشت به پیش‌فرض', 'secondary', 'submit', false ); ?>
				</form>
			</div>
			</div>
		</div>
		<script>
		(function(){
			var box=document.getElementById('aap-prev');if(!box){return;}
			var shadows=<?php echo wp_json_encode( self::SHADOW_CSS ); ?>;
			function set(n,v,def){box.style.setProperty(n,v||def);}
			function sync(){
				var $=window.jQuery;
				var p=$('#aap-primary').val(),a=$('#aap-accent').val(),r=$('#aap-radius').val(),sh=$('#aap-shadow').val();
				set('--p',p,'#1a365d');set('--a',a,'#c9a227');
				set('--r',r!==''?r+'px':'','10px');
				set('--sh',shadows[sh]||'','0 1px 2px rgba(0,0,0,.06),0 4px 14px rgba(0,0,0,.06)');
				var opt=document.querySelector('#aap-font option:checked');
				box.style.fontFamily=(opt&&opt.dataset.stack)||'inherit';
			}
			function boot(){
				var $=window.jQuery;if(!$){return;}
				if($.fn.wpColorPicker){$('.aap-color').wpColorPicker({change:function(){setTimeout(sync,0);},clear:function(){setTimeout(sync,0);}});}
				$('#aap-radius,#aap-shadow,#aap-font').on('input change',sync);sync();
			}
			if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}
		})();
		</script>
		<?php
	}
}
