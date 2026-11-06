<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

/**
 * Elementor widget: multi-purpose gallery (photos + video) with scroller / grid / masonry / mosaic layouts
 * and an accessible lightbox. All markup, validation and schema live in Artiey_AET_Media_Gallery.
 */
class Artiey_AET_Elementor_Media_Gallery_Widget extends Widget_Base {

	public function get_name() {
		return 'artiey_media_gallery';
	}

	public function get_title() {
		return 'گالری چندرسانه‌ای (عکس و ویدیو)';
	}

	public function get_icon() {
		return 'eicon-slider-album';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'گالری', 'عکس', 'ویدیو', 'اسلایدر', 'اسکرول', 'gallery', 'video', 'carousel', 'lightbox', 'media' );
	}

	public function get_style_depends() {
		return array( 'artiey-aet-media-gallery' );
	}

	public function get_script_depends() {
		return array( 'artiey-aet-media-gallery' );
	}

	protected function register_controls() {
		/* ---------------- Content: source ---------------- */
		$this->start_controls_section( 'section_source', array( 'label' => 'منبع تصاویر و ویدیوها', 'tab' => Controls_Manager::TAB_CONTENT ) );

		$this->add_control( 'source', array(
			'label'   => 'منبع',
			'type'    => Controls_Manager::SELECT,
			'default' => 'manual',
			'options' => array(
				'manual'  => 'دستی (افزودن عکس و ویدیو)',
				'current' => 'تصاویر نوشتهٔ جاری (گالری، اثر، هنرمند، نمایشگاه)',
				'post'    => 'تصاویر یک نوشتهٔ مشخص (با شناسه)',
			),
		) );
		$this->add_control( 'post_id', array(
			'label'     => 'شناسهٔ نوشته',
			'type'      => Controls_Manager::NUMBER,
			'min'       => 1,
			'dynamic'   => array( 'active' => true ),
			'condition' => array( 'source' => 'post' ),
		) );
		$this->add_control( 'include_featured', array(
			'label'        => 'شامل تصویر شاخص',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'condition'    => array( 'source' => array( 'current', 'post' ) ),
		) );
		$this->add_control( 'extra_videos', array(
			'label'       => 'ویدیوهای افزوده (هر خط یک نشانی)',
			'type'        => Controls_Manager::TEXTAREA,
			'rows'        => 3,
			'placeholder' => "https://www.youtube.com/watch?v=...\nhttps://vimeo.com/...\nhttps://www.aparat.com/v/...",
			'description' => 'یوتیوب، ویمئو، آپارات یا فایل mp4/webm. نشانی‌های دیگر نادیده گرفته می‌شوند.',
			'condition'   => array( 'source' => array( 'current', 'post' ) ),
		) );

		$rep = new Repeater();
		$rep->add_control( 'item_type', array(
			'label'   => 'نوع',
			'type'    => Controls_Manager::SELECT,
			'default' => 'image',
			'options' => array( 'image' => 'عکس', 'video' => 'ویدیو' ),
		) );
		$rep->add_control( 'image', array(
			'label'     => 'تصویر',
			'type'      => Controls_Manager::MEDIA,
			'dynamic'   => array( 'active' => true ),
			'condition' => array( 'item_type' => 'image' ),
		) );
		$rep->add_control( 'video_source', array(
			'label'     => 'منبع ویدیو',
			'type'      => Controls_Manager::SELECT,
			'default'   => 'link',
			'options'   => array( 'link' => 'پیوند (یوتیوب / ویمئو / آپارات / فایل)', 'file' => 'فایل کتابخانهٔ رسانه' ),
			'condition' => array( 'item_type' => 'video' ),
		) );
		$rep->add_control( 'video_url', array(
			'label'       => 'نشانی ویدیو',
			'type'        => Controls_Manager::URL,
			'dynamic'     => array( 'active' => true ),
			'placeholder' => 'https://www.youtube.com/watch?v=...',
			'condition'   => array( 'item_type' => 'video', 'video_source' => 'link' ),
		) );
		$rep->add_control( 'video_file', array(
			'label'      => 'فایل ویدیو',
			'type'       => Controls_Manager::MEDIA,
			'media_types' => array( 'video' ),
			'condition'  => array( 'item_type' => 'video', 'video_source' => 'file' ),
		) );
		$rep->add_control( 'poster', array(
			'label'       => 'تصویر پیش‌نمایش ویدیو',
			'type'        => Controls_Manager::MEDIA,
			'description' => 'برای یوتیوب خودکار است؛ برای ویمئو، آپارات و فایل، پیش‌نمایش را بارگذاری کنید (برای سئو لازم است).',
			'condition'   => array( 'item_type' => 'video' ),
		) );
		$rep->add_control( 'item_title', array( 'label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'label_block' => true, 'dynamic' => array( 'active' => true ) ) );
		$rep->add_control( 'item_caption', array( 'label' => 'توضیح', 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'dynamic' => array( 'active' => true ) ) );
		$rep->add_control( 'item_alt', array( 'label' => 'متن جایگزین (alt)', 'type' => Controls_Manager::TEXT, 'label_block' => true, 'description' => 'اگر خالی باشد از عنوان استفاده می‌شود.' ) );

		$this->add_control( 'items', array(
			'label'       => 'آیتم‌ها',
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'title_field' => '{{{ item_type === "video" ? "▶ " : "🖼 " }}}{{{ item_title || "آیتم" }}}',
			'condition'   => array( 'source' => 'manual' ),
			'default'     => array(),
		) );
		$this->add_control( 'max_items', array(
			'label'   => 'حداکثر تعداد آیتم',
			'type'    => Controls_Manager::NUMBER,
			'min'     => 1,
			'max'     => Artiey_AET_Media_Gallery::MAX_ITEMS,
			'default' => 24,
		) );
		$this->end_controls_section();

		/* ---------------- Content: layout ---------------- */
		$this->start_controls_section( 'section_layout', array( 'label' => 'چیدمان و نمایش', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'layout', array(
			'label'   => 'چیدمان',
			'type'    => Controls_Manager::SELECT,
			'default' => 'scroller',
			'options' => array(
				'scroller' => 'اسکرول افقی (کشیدنی، با فلش)',
				'grid'     => 'شبکه‌ای منظم',
				'masonry'  => 'آجری (ارتفاع آزاد)',
				'mosaic'   => 'موزاییک (اولین آیتم بزرگ)',
			),
		) );
		$this->add_responsive_control( 'columns', array(
			'label'           => 'تعداد ستون / آیتم در دید',
			'type'            => Controls_Manager::SELECT,
			'default'         => '3',
			'tablet_default'  => '2',
			'mobile_default'  => '1',
			'options'         => array( '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶' ),
			'selectors'       => array( '{{WRAPPER}} .aetmg' => '--aetmg-cols: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'gap', array(
			'label'      => 'فاصلهٔ آیتم‌ها',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'default'    => array( 'size' => 16, 'unit' => 'px' ),
			'selectors'  => array( '{{WRAPPER}} .aetmg' => '--aetmg-gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'ratio', array(
			'label'     => 'نسبت تصویر',
			'type'      => Controls_Manager::SELECT,
			'default'   => '4/3',
			'options'   => array( 'auto' => 'طبیعی (بدون برش)', '1/1' => '۱ : ۱ مربع', '4/3' => '۴ : ۳', '3/2' => '۳ : ۲', '16/9' => '۱۶ : ۹ سینمایی', '3/4' => '۳ : ۴ پرتره', '2/3' => '۲ : ۳ پرتره بلند' ),
			'condition' => array( 'layout!' => 'masonry' ),
		) );
		$this->add_control( 'fit', array(
			'label'   => 'جا شدن تصویر',
			'type'    => Controls_Manager::SELECT,
			'default' => 'cover',
			'options' => array( 'cover' => 'پر کردن قاب (برش)', 'contain' => 'نمایش کامل (بدون برش)' ),
		) );
		$this->add_control( 'caption', array(
			'label'   => 'نمایش عنوان و توضیح',
			'type'    => Controls_Manager::SELECT,
			'default' => 'below',
			'options' => array( 'none' => 'نمایش داده نشود', 'below' => 'زیر تصویر', 'overlay' => 'روی تصویر' ),
		) );
		$this->add_control( 'hover', array(
			'label'   => 'جلوهٔ اشاره',
			'type'    => Controls_Manager::SELECT,
			'default' => 'zoom',
			'options' => array( 'none' => 'بدون جلوه', 'zoom' => 'بزرگ‌نمایی ملایم', 'lift' => 'بالا آمدن با سایه' ),
		) );
		$this->add_control( 'lightbox', array( 'label' => 'بزرگ‌نمایی عکس‌ها (لایت‌باکس)', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'description' => 'ویدیوها همیشه داخل پنجرهٔ پخش باز می‌شوند.' ) );
		$this->add_control( 'arrows', array( 'label' => 'فلش‌های قبلی/بعدی', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'layout' => 'scroller' ) ) );
		$this->add_control( 'dots', array( 'label' => 'نقطه‌های صفحه', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '', 'condition' => array( 'layout' => 'scroller' ) ) );
		$this->add_control( 'autoplay', array( 'label' => 'حرکت خودکار', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '', 'description' => 'با اشاره، فوکوس یا لمس متوقف می‌شود و برای کاربرانی که حرکت کم را فعال کرده‌اند اجرا نمی‌شود.', 'condition' => array( 'layout' => 'scroller' ) ) );
		$this->add_control( 'interval', array( 'label' => 'فاصلهٔ حرکت (میلی‌ثانیه)', 'type' => Controls_Manager::NUMBER, 'min' => 2000, 'max' => 20000, 'step' => 500, 'default' => 5000, 'condition' => array( 'layout' => 'scroller', 'autoplay' => 'yes' ) ) );
		$this->add_control( 'loop', array( 'label' => 'چرخشی (بازگشت به اول)', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'layout' => 'scroller' ) ) );
		$this->add_control( 'eager_first', array( 'label' => 'بارگذاری فوری تصویر اول (بالای صفحه)', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '', 'description' => 'اگر گالری اولین چیز صفحه است روشن کنید تا LCP بهتر شود.' ) );
		$this->end_controls_section();

		/* ---------------- Content: heading + SEO ---------------- */
		$this->start_controls_section( 'section_heading', array( 'label' => 'عنوان و سئو', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'heading', array( 'label' => 'عنوان گالری', 'type' => Controls_Manager::TEXT, 'label_block' => true, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'heading_tag', array( 'label' => 'تگ عنوان', 'type' => Controls_Manager::SELECT, 'default' => 'h2', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ), 'condition' => array( 'heading!' => '' ) ) );
		$this->add_control( 'aria_label', array( 'label' => 'برچسب دسترس‌پذیری', 'type' => Controls_Manager::TEXT, 'default' => 'گالری تصاویر و ویدیوها', 'description' => 'توسط صفحه‌خوان‌ها خوانده می‌شود.' ) );
		$this->add_control( 'schema', array(
			'label'        => 'داده ساختاریافته (ImageGallery / VideoObject)',
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'description'  => 'برای نمایش بهتر عکس و ویدیو در گوگل. ویدیو بدون پیش‌نمایش و عنوان در داده ساختاریافته نمی‌آید.',
		) );
		$this->end_controls_section();

		/* ---------------- Style ---------------- */
		$tile = '{{WRAPPER}} .aetmg-open';

		$this->start_controls_section( 'style_tiles', array( 'label' => 'کاشی‌ها', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'tile_radius', array(
			'label' => 'گردی گوشه‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ),
			'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
			'selectors' => array( '{{WRAPPER}} .aetmg' => '--aetmg-r: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'tile_bg', array( 'label' => 'پس‌زمینهٔ قاب (پشت تصویر)', 'type' => Controls_Manager::COLOR, 'selectors' => array( $tile => 'background: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'tile_border', 'selector' => $tile ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'tile_shadow', 'selector' => $tile ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_caption', array( 'label' => 'عنوان و توضیح', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'caption_typo', 'selector' => '{{WRAPPER}} .aetmg-cap' ) );
		$this->add_control( 'caption_color', array( 'label' => 'رنگ متن', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetmg-cap' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'caption_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetmg-cap-below .aetmg-cap' => 'background: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'caption_pad', array( 'label' => 'فاصلهٔ داخلی', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .aetmg-cap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_nav', array( 'label' => 'فلش‌ها و نقطه‌ها', 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'layout' => 'scroller' ) ) );
		$this->add_control( 'nav_color', array( 'label' => 'رنگ آیکن فلش', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetmg' => '--aetmg-nav-fg: {{VALUE}};' ) ) );
		$this->add_control( 'nav_bg', array( 'label' => 'پس‌زمینهٔ فلش', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetmg' => '--aetmg-nav-bg: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'nav_size', array( 'label' => 'اندازهٔ فلش', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 28, 'max' => 72 ) ), 'selectors' => array( '{{WRAPPER}} .aetmg-nav' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; inset-block-start: calc(50% - {{SIZE}}{{UNIT}} / 2);' ) ) );
		$this->add_control( 'dot_active', array( 'label' => 'رنگ نقطهٔ فعال و تأکید', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetmg' => '--aetmg-accent: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_play', array( 'label' => 'دکمهٔ پخش ویدیو', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'play_color', array( 'label' => 'رنگ آیکن', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetmg-play' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'play_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetmg-play' => 'background: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'play_size', array( 'label' => 'اندازه', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 36, 'max' => 120 ) ), 'selectors' => array( '{{WRAPPER}} .aetmg-play' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_heading', array( 'label' => 'عنوان گالری', 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'heading!' => '' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'heading_typo', 'selector' => '{{WRAPPER}} .aetmg-heading' ) );
		$this->add_control( 'heading_color', array( 'label' => 'رنگ', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetmg-heading' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'heading_gap', array( 'label' => 'فاصله تا گالری', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( '{{WRAPPER}} .aetmg-heading' => 'margin: 0 0 {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	/** Raw rows (before validation) for the chosen source. */
	private function collect_rows( array $s ) {
		$max    = max( 1, min( Artiey_AET_Media_Gallery::MAX_ITEMS, (int) ( $s['max_items'] ?? 24 ) ) );
		$source = $s['source'] ?? 'manual';
		$rows   = array();

		if ( 'manual' === $source ) {
			foreach ( (array) ( $s['items'] ?? array() ) as $it ) {
				$is_video = ( $it['item_type'] ?? 'image' ) === 'video';
				$row      = array(
					'type'    => $is_video ? 'video' : 'image',
					'title'   => $it['item_title'] ?? '',
					'caption' => $it['item_caption'] ?? '',
					'alt'     => $it['item_alt'] ?? '',
				);
				if ( $is_video ) {
					$use_file          = ( $it['video_source'] ?? 'link' ) === 'file';
					$row['video_url']  = $use_file ? ( $it['video_file']['url'] ?? '' ) : ( $it['video_url']['url'] ?? '' );
					$poster_id         = (int) ( $it['poster']['id'] ?? 0 );
					$row['poster']     = $poster_id ? (string) wp_get_attachment_image_url( $poster_id, 'large' ) : (string) ( $it['poster']['url'] ?? '' );
				} else {
					$id = (int) ( $it['image']['id'] ?? 0 );
					if ( $id ) {
						$att = Artiey_AET_Media_Gallery::attachment_row( $id, 'large' );
						$row = array_merge( $row, $att, array_filter( array( 'title' => $row['title'], 'caption' => $row['caption'], 'alt' => $row['alt'] ) ) );
					} else {
						$row['src'] = $it['image']['url'] ?? '';
					}
				}
				$rows[] = $row;
			}
			return $rows;
		}

		$post_id = 'post' === $source ? (int) ( $s['post_id'] ?? 0 ) : (int) get_the_ID();
		$rows    = Artiey_AET_Media_Gallery::collect_post_images( $post_id, $max, ! empty( $s['include_featured'] ) );
		foreach ( preg_split( '/\R+/', (string) ( $s['extra_videos'] ?? '' ) ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$rows[] = array( 'type' => 'video', 'video_url' => $line );
			}
		}
		return $rows;
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$max   = max( 1, min( Artiey_AET_Media_Gallery::MAX_ITEMS, (int) ( $s['max_items'] ?? 24 ) ) );
		$items = Artiey_AET_Media_Gallery::normalize_items( $this->collect_rows( $s ), $max );

		if ( ! $items ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="aetmg-empty" style="padding:24px;border:2px dashed #c3c8d0;border-radius:12px;text-align:center;color:#5b6470">'
					. esc_html( 'آیتمی برای نمایش نیست. در حالت «دستی» عکس یا ویدیو اضافه کنید، یا منبع را روی نوشتهٔ جاری بگذارید.' ) . '</div>';
			}
			return;
		}

		$opts = array(
			'layout'      => $s['layout'] ?? 'scroller',
			'ratio'       => $s['ratio'] ?? '4/3',
			'caption'     => $s['caption'] ?? 'below',
			'fit'         => $s['fit'] ?? 'cover',
			'hover'       => $s['hover'] ?? 'zoom',
			'lightbox'    => ! empty( $s['lightbox'] ),
			'arrows'      => ! empty( $s['arrows'] ),
			'dots'        => ! empty( $s['dots'] ),
			'autoplay'    => ! empty( $s['autoplay'] ),
			'interval'    => $s['interval'] ?? 5000,
			'loop'        => ! empty( $s['loop'] ),
			'label'       => $s['aria_label'] ?? '',
			'eager_first' => ! empty( $s['eager_first'] ),
			'id'          => $this->get_id(),
		);

		$heading = trim( (string) ( $s['heading'] ?? '' ) );
		if ( '' !== $heading ) {
			$tag = in_array( $s['heading_tag'] ?? 'h2', array( 'h2', 'h3', 'h4', 'div' ), true ) ? $s['heading_tag'] : 'h2';
			echo '<' . $tag . ' class="aetmg-heading">' . esc_html( $heading ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- tag is allow-listed
		}
		echo Artiey_AET_Media_Gallery::render( $items, $opts ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer

		if ( ! empty( $s['schema'] ) && ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$post_id = (int) get_the_ID();
			echo Artiey_AET_Media_Gallery::schema_script( Artiey_AET_Media_Gallery::schema( $items, array( // phpcs:ignore WordPress.Security.EscapeOutput
				'name'        => '' !== $heading ? $heading : (string) get_the_title( $post_id ),
				'url'         => (string) get_permalink( $post_id ),
				'description' => '',
				'date'        => $post_id ? (string) get_post_modified_time( 'c', true, $post_id ) : '',
			) ) );
		}
	}
}
