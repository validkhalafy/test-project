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
 * Elementor widget: smart gallery scroller (flat / 3D coverflow / 3D cylinder) fed from any content on the site.
 * Place it in a Theme Builder template or a global widget and it adapts to every page it appears on.
 */
class Artiey_AET_Elementor_Smart_Scroller_Widget extends Widget_Base {

	public function get_name() {
		return 'artiey_smart_scroller';
	}

	public function get_title() {
		return 'اسکرول هوشمند گالری (۲بعدی / ۳بعدی)';
	}

	public function get_icon() {
		return 'eicon-slider-3d';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'اسکرول', 'اسلایدر', 'سه بعدی', '3d', 'coverflow', 'carousel', 'گالری', 'پویا', 'هوشمند', 'scroller' );
	}

	public function get_style_depends() {
		return array( 'artiey-aet-smart-scroller' );
	}

	public function get_script_depends() {
		return array( 'artiey-aet-smart-scroller' );
	}

	/** @return array<string,string> slug => label for public post types. */
	private function post_type_options() {
		$out = array();
		foreach ( Artiey_AET_Smart_Scroller::allowed_post_types() as $slug ) {
			$obj          = get_post_type_object( $slug );
			$out[ $slug ] = $obj && isset( $obj->labels->name ) ? $obj->labels->name . ' (' . $slug . ')' : $slug;
		}
		return $out;
	}

	/** @return array<string,string> slug => label for public taxonomies. */
	private function taxonomy_options() {
		$out = array( '' => '— بدون فیلتر —' );
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $slug => $obj ) {
			$out[ $slug ] = ( $obj->labels->name ?? $slug ) . ' (' . $slug . ')';
		}
		return $out;
	}

	protected function register_controls() {
		$yes = array( 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes' );

		/* ================= Content: source ================= */
		$this->start_controls_section( 'section_source', array( 'label' => 'منبع محتوا', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'source', array(
			'label'       => 'منبع',
			'type'        => Controls_Manager::SELECT,
			'default'     => 'context',
			'options'     => array(
				'context'    => 'هوشمند (بر اساس صفحهٔ جاری)',
				'posts'      => 'نوشته‌ها و نوع‌نوشته‌ها (انتخابی)',
				'related'    => 'مرتبط با صفحهٔ جاری (هم‌سبک / هم‌دسته)',
				'shortcodes' => 'شورت‌کدها (هر شورت‌کد = یک اسلاید)',
			),
			'description' => 'حالت هوشمند یک بار تنظیم می‌شود و روی همهٔ صفحات (قالب، ویجت سراسری) خودش را با صفحه تطبیق می‌دهد.',
		) );
		$this->add_control( 'context_target', array(
			'label'       => 'در حالت هوشمند چه نشان داده شود؟',
			'type'        => Controls_Manager::SELECT,
			'default'     => 'auto',
			'options'     => array( 'auto' => 'خودکار', 'artworks' => 'آثار', 'exhibitions' => 'نمایشگاه‌ها', 'artists' => 'هنرمندان (در صفحهٔ گالری)' ),
			'condition'   => array( 'source' => 'context' ),
			'description' => 'گالری ← آثارش؛ هنرمند ← آثارش؛ اثر ← آثار بیشتر از همان هنرمند؛ نمایشگاه ← آثار گالری و هنرمندانش؛ سایر صفحات ← تازه‌ترین آثار.',
		) );
		$this->add_control( 'post_types', array(
			'label'       => 'نوع نوشته',
			'type'        => Controls_Manager::SELECT2,
			'multiple'    => true,
			'options'     => $this->post_type_options(),
			'default'     => array( 'artiey_artwork' ),
			'label_block' => true,
			'condition'   => array( 'source' => array( 'posts', 'related' ) ),
		) );
		$this->add_control( 'include_ids', array( 'label' => 'فقط این شناسه‌ها', 'type' => Controls_Manager::TEXT, 'placeholder' => '12, 15, 18', 'condition' => array( 'source' => 'posts' ), 'description' => 'اختیاری؛ با ویرگول جدا کنید.' ) );
		$this->add_control( 'taxonomy', array( 'label' => 'فیلتر طبقه‌بندی', 'type' => Controls_Manager::SELECT, 'options' => $this->taxonomy_options(), 'default' => '', 'condition' => array( 'source' => 'posts' ) ) );
		$this->add_control( 'terms', array( 'label' => 'نامک ترم‌ها', 'type' => Controls_Manager::TEXT, 'placeholder' => 'impressionism, cubism', 'condition' => array( 'source' => 'posts', 'taxonomy!' => '' ) ) );
		$this->add_control( 'related_taxonomy', array( 'label' => 'ارتباط بر اساس', 'type' => Controls_Manager::SELECT, 'options' => $this->taxonomy_options(), 'default' => 'artiey_style', 'condition' => array( 'source' => 'related' ) ) );

		$this->add_control( 'limit', array( 'label' => 'تعداد اسلاید', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => Artiey_AET_Smart_Scroller::MAX_SLIDES, 'default' => 12, 'condition' => array( 'source!' => 'shortcodes' ) ) );
		$this->add_control( 'offset', array( 'label' => 'شروع از (رد کردن چند مورد)', 'type' => Controls_Manager::NUMBER, 'min' => 0, 'max' => 200, 'default' => 0, 'condition' => array( 'source' => 'posts' ) ) );
		$this->add_control( 'orderby', array(
			'label'     => 'مرتب‌سازی',
			'type'      => Controls_Manager::SELECT,
			'default'   => 'date',
			'options'   => array( 'date' => 'تاریخ انتشار', 'modified' => 'آخرین ویرایش', 'title' => 'عنوان', 'menu_order' => 'ترتیب دستی', 'rand' => 'تصادفی', 'post__in' => 'ترتیب شناسه‌ها' ),
			'condition' => array( 'source!' => 'shortcodes' ),
		) );
		$this->add_control( 'order', array( 'label' => 'جهت', 'type' => Controls_Manager::SELECT, 'default' => 'desc', 'options' => array( 'desc' => 'نزولی', 'asc' => 'صعودی' ), 'condition' => array( 'source!' => 'shortcodes' ) ) );
		$this->add_control( 'exclude_current', $yes + array( 'label' => 'صفحهٔ جاری نمایش داده نشود', 'default' => 'yes', 'condition' => array( 'source' => 'posts' ) ) );
		$this->add_control( 'only_with_image', $yes + array( 'label' => 'فقط موارد دارای تصویر', 'default' => 'yes', 'condition' => array( 'source!' => 'shortcodes' ) ) );

		$rep = new Repeater();
		$rep->add_control( 'shortcode', array(
			'label'       => 'شورت‌کد',
			'type'        => Controls_Manager::TEXTAREA,
			'rows'        => 2,
			'placeholder' => '[artiey_stats]  یا  [art_coa_verify]',
			'description' => 'خروجی این شورت‌کد داخل یک کارت قرار می‌گیرد.',
		) );
		$this->add_control( 'shortcodes', array(
			'label'       => 'شورت‌کدها',
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'title_field' => '{{{ shortcode }}}',
			'default'     => array(),
			'condition'   => array( 'source' => 'shortcodes' ),
		) );
		$this->add_control( 'merge_shortcodes', $yes + array( 'label' => 'افزودن شورت‌کدها به اسلایدهای بالا', 'default' => '', 'condition' => array( 'source!' => 'shortcodes' ) ) );
		$this->add_control( 'merge_list', array(
			'label'       => 'شورت‌کدهای افزوده',
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'title_field' => '{{{ shortcode }}}',
			'default'     => array(),
			'condition'   => array( 'source!' => 'shortcodes', 'merge_shortcodes' => 'yes' ),
		) );
		$this->add_control( 'shortcode_position', array( 'label' => 'جای شورت‌کدها', 'type' => Controls_Manager::SELECT, 'default' => 'end', 'options' => array( 'start' => 'اول', 'end' => 'آخر' ), 'condition' => array( 'source!' => 'shortcodes', 'merge_shortcodes' => 'yes' ) ) );
		$this->end_controls_section();

		/* ================= Content: card ================= */
		$this->start_controls_section( 'section_card', array( 'label' => 'کارت اسلاید', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'ratio', array( 'label' => 'نسبت تصویر', 'type' => Controls_Manager::SELECT, 'default' => '4/3', 'options' => array( '1/1' => '۱ : ۱', '4/3' => '۴ : ۳', '3/2' => '۳ : ۲', '16/9' => '۱۶ : ۹', '3/4' => '۳ : ۴ پرتره', '2/3' => '۲ : ۳ پرتره بلند' ) ) );
		$this->add_control( 'show_badge', $yes + array( 'label' => 'برچسب نوع (اثر / هنرمند / …)', 'default' => 'yes' ) );
		$this->add_control( 'show_title', $yes + array( 'label' => 'عنوان', 'default' => 'yes' ) );
		$this->add_control( 'show_meta', $yes + array( 'label' => 'خط توضیح کوتاه (هنرمند، سال، تاریخ، شهر)', 'default' => 'yes' ) );
		$this->add_control( 'show_excerpt', $yes + array( 'label' => 'خلاصه', 'default' => '' ) );
		$this->add_control( 'excerpt_words', array( 'label' => 'تعداد کلمات خلاصه', 'type' => Controls_Manager::NUMBER, 'min' => 5, 'max' => 80, 'default' => 22, 'condition' => array( 'show_excerpt' => 'yes' ) ) );
		$this->add_control( 'show_cta', $yes + array( 'label' => 'دکمه', 'default' => '' ) );
		$this->add_control( 'cta_text', array( 'label' => 'متن دکمه', 'type' => Controls_Manager::TEXT, 'default' => 'مشاهده', 'condition' => array( 'show_cta' => 'yes' ) ) );
		$this->add_control( 'name_tag', array( 'label' => 'تگ عنوان کارت', 'type' => Controls_Manager::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ) ) );
		$this->add_responsive_control( 'slide_width', array(
			'label' => 'عرض اسلاید', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ),
			'range' => array( 'px' => array( 'min' => 180, 'max' => 640 ) ), 'default' => array( 'size' => 320, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .aetss' => '--aetss-w: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'gap', array(
			'label' => 'فاصلهٔ اسلایدها (حالت تخت)', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ),
			'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'default' => array( 'size' => 18, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .aetss' => '--aetss-gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'eager', array( 'label' => 'تعداد تصویر بارگذاری فوری', 'type' => Controls_Manager::NUMBER, 'min' => 0, 'max' => 4, 'default' => 1, 'description' => 'اگر اسکرول اولین چیز صفحه است ۱ تا ۳؛ در غیر این صورت ۰.' ) );
		$this->end_controls_section();

		/* ================= Content: 3D ================= */
		$this->start_controls_section( 'section_3d', array( 'label' => 'حالت نمایش و ۳بعدی', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'mode', array(
			'label'   => 'حالت پیش‌فرض',
			'type'    => Controls_Manager::SELECT,
			'default' => 'coverflow',
			'options' => array( 'flat' => 'تخت (اسکرول افقی)', 'coverflow' => '۳بعدی — کاورفلو', 'cylinder' => '۳بعدی — استوانه‌ای' ),
		) );
		$this->add_control( 'toggle', $yes + array( 'label' => 'دکمهٔ تعویض ۲بعدی/۳بعدی برای بازدیدکننده', 'default' => 'yes', 'description' => 'با کمتر از ۳ اسلاید، «حرکت کم» فعال یا مرورگر بدون پشتیبانی ۳بعدی، حالت تخت خودکار است.' ) );
		$this->add_control( 'mobile3d', $yes + array( 'label' => '۳بعدی در موبایل (عرض کمتر از ۶۰۰)', 'default' => '', 'description' => 'پیش‌فرض خاموش تا موبایل سریع و خوانا بماند.' ) );
		$this->add_control( 'angle', array( 'label' => 'زاویهٔ چرخش کارت‌های کناری (کاورفلو)', 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 75 ) ), 'default' => array( 'size' => 42 ), 'condition' => array( 'mode' => 'coverflow' ) ) );
		$this->add_control( 'depth', array( 'label' => 'عمق', 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 600 ) ), 'default' => array( 'size' => 260 ), 'condition' => array( 'mode' => 'coverflow' ) ) );
		$this->add_control( 'spread', array( 'label' => 'فاصلهٔ کارت‌ها (درصد عرض)', 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 20, 'max' => 100 ) ), 'default' => array( 'size' => 55 ), 'condition' => array( 'mode' => 'coverflow' ) ) );
		$this->add_control( 'perspective', array( 'label' => 'پرسپکتیو', 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 400, 'max' => 3000, 'step' => 50 ) ), 'default' => array( 'size' => 1200 ) ) );
		$this->add_control( 'start', array( 'label' => 'اسلاید شروع (شماره از صفر)', 'type' => Controls_Manager::NUMBER, 'min' => 0, 'default' => 0 ) );
		$this->end_controls_section();

		/* ================= Content: behaviour ================= */
		$this->start_controls_section( 'section_behaviour', array( 'label' => 'رفتار', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'arrows', $yes + array( 'label' => 'فلش‌ها', 'default' => 'yes' ) );
		$this->add_control( 'dots', $yes + array( 'label' => 'نقطه‌ها', 'default' => '', 'description' => 'برای حداکثر ۱۲ اسلاید.' ) );
		$this->add_control( 'loop', $yes + array( 'label' => 'چرخشی', 'default' => 'yes' ) );
		$this->add_control( 'drag', $yes + array( 'label' => 'کشیدن با ماوس/انگشت (۳بعدی)', 'default' => 'yes' ) );
		$this->add_control( 'wheel', $yes + array( 'label' => 'چرخ/تاچ‌پد افقی (۳بعدی)', 'default' => '', 'description' => 'فقط حرکت افقی را می‌گیرد؛ اسکرول عمودی صفحه مختل نمی‌شود.' ) );
		$this->add_control( 'autoplay', $yes + array( 'label' => 'حرکت خودکار', 'default' => '', 'description' => 'با اشاره، فوکوس، لمس و خارج بودن از دید متوقف می‌شود؛ با «حرکت کم» اجرا نمی‌شود.' ) );
		$this->add_control( 'interval', array( 'label' => 'فاصلهٔ حرکت (میلی‌ثانیه)', 'type' => Controls_Manager::NUMBER, 'min' => 2000, 'max' => 20000, 'step' => 500, 'default' => 5000, 'condition' => array( 'autoplay' => 'yes' ) ) );
		$this->end_controls_section();

		/* ================= Content: heading + a11y ================= */
		$this->start_controls_section( 'section_heading', array( 'label' => 'عنوان و دسترس‌پذیری', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'heading', array( 'label' => 'عنوان بخش', 'type' => Controls_Manager::TEXT, 'label_block' => true, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'heading_tag', array( 'label' => 'تگ عنوان', 'type' => Controls_Manager::SELECT, 'default' => 'h2', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ), 'condition' => array( 'heading!' => '' ) ) );
		$this->add_control( 'aria_label', array( 'label' => 'برچسب دسترس‌پذیری', 'type' => Controls_Manager::TEXT, 'default' => 'گالری' ) );
		$this->add_control( 'empty_text', array( 'label' => 'پیام وقتی محتوایی نیست', 'type' => Controls_Manager::TEXT, 'description' => 'خالی = در سایت چیزی نمایش داده نمی‌شود.' ) );
		$this->end_controls_section();

		/* ================= Style ================= */
		$card = '{{WRAPPER}} .aetss-card';

		$this->start_controls_section( 'style_card', array( 'label' => 'کارت', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'card_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( $card => 'background: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'card_border', 'selector' => $card ) );
		$this->add_responsive_control( 'card_radius', array( 'label' => 'گردی گوشه‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .aetss' => '--aetss-r: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'card_shadow', 'selector' => $card ) );
		$this->add_responsive_control( 'body_padding', array( 'label' => 'فاصلهٔ داخلی متن', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .aetss-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_text', array( 'label' => 'متن‌ها', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'title_typo', 'label' => 'تایپوگرافی عنوان', 'selector' => '{{WRAPPER}} .aetss-title' ) );
		$this->add_control( 'title_color', array( 'label' => 'رنگ عنوان', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss-title a, {{WRAPPER}} .aetss-title' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'meta_typo', 'label' => 'تایپوگرافی خط توضیح', 'selector' => '{{WRAPPER}} .aetss-meta' ) );
		$this->add_control( 'meta_color', array( 'label' => 'رنگ خط توضیح', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss-meta' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'excerpt_typo', 'label' => 'تایپوگرافی خلاصه', 'selector' => '{{WRAPPER}} .aetss-excerpt' ) );
		$this->add_control( 'excerpt_color', array( 'label' => 'رنگ خلاصه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss-excerpt' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_badge', array( 'label' => 'برچسب نوع', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'badge_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss-badge' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'badge_color', array( 'label' => 'رنگ متن', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss-badge' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'badge_typo', 'selector' => '{{WRAPPER}} .aetss-badge' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_cta', array( 'label' => 'دکمهٔ کارت', 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'show_cta' => 'yes' ) ) );
		$this->add_control( 'cta_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss-cta' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'cta_color', array( 'label' => 'رنگ متن', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss-cta' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'cta_typo', 'selector' => '{{WRAPPER}} .aetss-cta' ) );
		$this->add_responsive_control( 'cta_radius', array( 'label' => 'گردی گوشه‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .aetss-cta' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_nav', array( 'label' => 'فلش‌ها، نقطه‌ها و دکمهٔ ۳بعدی', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'nav_color', array( 'label' => 'رنگ آیکن فلش', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss' => '--aetss-nav-fg: {{VALUE}};' ) ) );
		$this->add_control( 'nav_bg', array( 'label' => 'پس‌زمینهٔ فلش', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss' => '--aetss-nav-bg: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'nav_size', array( 'label' => 'اندازهٔ فلش', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 28, 'max' => 72 ) ), 'selectors' => array( '{{WRAPPER}} .aetss-nav' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; inset-block-start: calc(50% - {{SIZE}}{{UNIT}} / 2);' ) ) );
		$this->add_control( 'accent', array( 'label' => 'رنگ تأکید (نقطهٔ فعال، دکمهٔ ۳بعدی، عنوان)', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss' => '--aetss-accent: {{VALUE}};' ) ) );
		$this->add_control( 'gold', array( 'label' => 'رنگ برچسب و فوکوس', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss' => '--aetss-gold: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'mode_typo', 'label' => 'تایپوگرافی دکمهٔ ۳بعدی', 'selector' => '{{WRAPPER}} .aetss-mode' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_heading', array( 'label' => 'عنوان بخش', 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'heading!' => '' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'heading_typo', 'selector' => '{{WRAPPER}} .aetss-heading' ) );
		$this->add_control( 'heading_color', array( 'label' => 'رنگ', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetss-heading' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'heading_gap', array( 'label' => 'فاصله تا اسکرول', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( '{{WRAPPER}} .aetss-heading' => 'margin: 0 0 {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	/** @return string[] shortcode strings from a repeater value. */
	private static function shortcodes_from( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( is_array( $row ) && ! empty( $row['shortcode'] ) ) {
				$out[] = (string) $row['shortcode'];
			}
		}
		return $out;
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$edit = \Elementor\Plugin::$instance->editor->is_edit_mode();
		$src  = $s['source'] ?? 'context';

		$settings = array(
			'source'             => $src,
			'context_target'     => $s['context_target'] ?? 'auto',
			'post_types'         => (array) ( $s['post_types'] ?? array() ),
			'include_ids'        => $s['include_ids'] ?? '',
			'taxonomy'           => $s['taxonomy'] ?? '',
			'terms'              => $s['terms'] ?? '',
			'related_taxonomy'   => $s['related_taxonomy'] ?? 'artiey_style',
			'limit'              => $s['limit'] ?? 12,
			'offset'             => $s['offset'] ?? 0,
			'orderby'            => $s['orderby'] ?? 'date',
			'order'              => $s['order'] ?? 'desc',
			'exclude_current'    => ! empty( $s['exclude_current'] ),
			'only_with_image'    => ! empty( $s['only_with_image'] ),
			'excerpt_words'      => $s['excerpt_words'] ?? 22,
			'merge_shortcodes'   => ! empty( $s['merge_shortcodes'] ),
			'shortcode_position' => $s['shortcode_position'] ?? 'end',
			'shortcodes'         => self::shortcodes_from( 'shortcodes' === $src ? ( $s['shortcodes'] ?? array() ) : ( $s['merge_list'] ?? array() ) ),
		);
		$slides = Artiey_AET_Smart_Scroller::build_slides( $settings, (int) get_the_ID(), (string) get_post_type(), true );

		if ( ! $slides ) {
			$text = trim( (string) ( $s['empty_text'] ?? '' ) );
			if ( '' !== $text ) {
				echo '<p class="aetss-empty">' . esc_html( $text ) . '</p>';
			} elseif ( $edit ) {
				echo '<div style="padding:24px;border:2px dashed #c3c8d0;border-radius:12px;text-align:center;color:#5b6470">' . esc_html( 'برای این منبع و صفحه محتوایی پیدا نشد. منبع را عوض کنید یا «فقط موارد دارای تصویر» را خاموش کنید.' ) . '</div>';
			}
			return;
		}

		$num = static function ( $v, $d ) {
			return is_array( $v ) ? ( $v['size'] ?? $d ) : ( $v ?: $d );
		};
		$heading = trim( (string) ( $s['heading'] ?? '' ) );
		if ( '' !== $heading ) {
			$tag = in_array( $s['heading_tag'] ?? 'h2', array( 'h2', 'h3', 'h4', 'div' ), true ) ? $s['heading_tag'] : 'h2';
			echo '<' . $tag . ' class="aetss-heading">' . esc_html( $heading ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- tag is allow-listed
		}
		echo Artiey_AET_Smart_Scroller::render( $slides, array( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer
			'mode'        => $s['mode'] ?? 'coverflow',
			'toggle'      => ! empty( $s['toggle'] ),
			'ratio'       => $s['ratio'] ?? '4/3',
			'badge'       => ! empty( $s['show_badge'] ),
			'title'       => ! empty( $s['show_title'] ),
			'meta'        => ! empty( $s['show_meta'] ),
			'excerpt'     => ! empty( $s['show_excerpt'] ),
			'cta'         => ! empty( $s['show_cta'] ),
			'cta_text'    => $s['cta_text'] ?? '',
			'arrows'      => ! empty( $s['arrows'] ),
			'dots'        => ! empty( $s['dots'] ),
			'autoplay'    => ! empty( $s['autoplay'] ),
			'interval'    => $s['interval'] ?? 5000,
			'loop'        => ! empty( $s['loop'] ),
			'drag'        => ! empty( $s['drag'] ),
			'wheel'       => ! empty( $s['wheel'] ),
			'mobile3d'    => ! empty( $s['mobile3d'] ),
			'depth'       => $num( $s['depth'] ?? null, 260 ),
			'spread'      => $num( $s['spread'] ?? null, 55 ),
			'angle'       => $num( $s['angle'] ?? null, 42 ),
			'perspective' => $num( $s['perspective'] ?? null, 1200 ),
			'start'       => $s['start'] ?? 0,
			'label'       => $s['aria_label'] ?? '',
			'eager'       => $s['eager'] ?? 1,
			'name_tag'    => $s['name_tag'] ?? 'h3',
		) );
	}
}
