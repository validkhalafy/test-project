<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

/**
 * Elementor widget: open calls (فراخوان‌ها) as cards or a full archive with filters, search and pagination.
 * Deadlines count down live in the browser; the list can follow the gallery of the page it is placed on.
 */
class Artiey_AET_Elementor_Calls_Widget extends Widget_Base {

	public function get_name() {
		return 'artiey_calls';
	}

	public function get_title() {
		return 'فراخوان‌ها';
	}

	public function get_icon() {
		return 'eicon-call-to-action';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'فراخوان', 'رزیدنسی', 'رویداد', 'نمایشگاه', 'open call', 'call', 'residency', 'event' );
	}

	public function get_style_depends() {
		return array( 'artiey-calls', 'wp-art-coa-front', 'wp-art-coa-dynamic' );
	}

	public function get_script_depends() {
		return array( 'artiey-calls' );
	}

	protected function register_controls() {
		$yes = array( 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes' );

		$this->start_controls_section( 'section_query', array( 'label' => 'منبع فراخوان‌ها', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'mode', array(
			'label'   => 'حالت',
			'type'    => Controls_Manager::SELECT,
			'default' => 'cards',
			'options' => array( 'cards' => 'کارت‌ها (بدون فیلتر)', 'archive' => 'آرشیو کامل با فیلتر، جستجو و صفحه‌بندی' ),
		) );
		$this->add_control( 'source', array(
			'label'   => 'گالری',
			'type'    => Controls_Manager::SELECT,
			'default' => 'all',
			'options' => array( 'all' => 'همه گالری‌ها', 'current' => 'گالری همین صفحه', 'pick' => 'گالری مشخص' ),
		) );
		$this->add_control( 'gallery_post', array(
			'label'       => 'گالری',
			'type'        => Controls_Manager::SELECT2,
			'options'     => class_exists( 'Artiey_AET_Elementor_Options' ) ? Artiey_AET_Elementor_Options::posts( 'artiey_gallery' ) : array(),
			'label_block' => true,
			'condition'   => array( 'source' => 'pick' ),
		) );
		$types = array( '' => 'همه انواع' );
		foreach ( \WPArtCOA\Calls::TYPES as $t ) {
			$types[ $t ] = \WPArtCOA\Calls::type_label( $t );
		}
		$this->add_control( 'call_type', array( 'label' => 'نوع فراخوان', 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => $types ) );
		$this->add_control( 'status', array( 'label' => 'وضعیت', 'type' => Controls_Manager::SELECT, 'default' => 'open', 'options' => array( 'open' => 'باز', 'closed' => 'بسته (بایگانی)', 'all' => 'همه' ) ) );
		$this->add_control( 'featured', $yes + array( 'label' => 'فقط فراخوان‌های ویژه', 'default' => '', 'condition' => array( 'mode' => 'cards' ) ) );
		$this->add_control( 'limit', array( 'label' => 'تعداد', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 50, 'default' => 6 ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_layout', array( 'label' => 'چیدمان و اطلاعات', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'layout', array( 'label' => 'چیدمان', 'type' => Controls_Manager::SELECT, 'default' => 'grid', 'options' => array( 'grid' => 'شبکه‌ای', 'list' => 'ردیفی' ), 'condition' => array( 'mode' => 'cards' ) ) );
		$this->add_responsive_control( 'columns', array(
			'label'     => 'ستون‌ها',
			'type'      => Controls_Manager::SELECT,
			'default'   => '3',
			'options'   => array( '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴' ),
			'selectors' => array( '{{WRAPPER}} .aet-calls-widget .art-coa-call-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
			'condition' => array( 'mode' => 'cards', 'layout' => 'grid' ),
		) );
		$this->add_responsive_control( 'gap', array( 'label' => 'فاصله کارت‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( '{{WRAPPER}} .art-coa-call-grid' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'show_image', $yes + array( 'label' => 'تصویر کاور', 'default' => 'yes' ) );
		$this->add_control( 'show_countdown', $yes + array( 'label' => 'شمارش معکوس زنده مهلت', 'default' => 'yes' ) );
		$this->add_control( 'excerpt', array( 'label' => 'خلاصه توضیحات (تعداد کلمه، ۰ = بدون خلاصه)', 'type' => Controls_Manager::NUMBER, 'min' => 0, 'max' => 80, 'default' => 18 ) );
		$this->add_control( 'show_gallery', $yes + array( 'label' => 'نام گالری برگزارکننده', 'default' => 'yes' ) );
		$this->add_control( 'show_button', $yes + array( 'label' => 'دکمه مشاهده و درخواست', 'default' => 'yes' ) );
		$this->add_control( 'title_tag', array( 'label' => 'تگ عنوان کارت', 'type' => Controls_Manager::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ) ) );
		$this->add_control( 'heading', array( 'label' => 'عنوان بخش', 'type' => Controls_Manager::TEXT, 'label_block' => true, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'show_all_link', $yes + array( 'label' => 'پیوند «همه فراخوان‌ها»', 'default' => 'yes', 'condition' => array( 'mode' => 'cards' ) ) );
		$this->add_control( 'empty_text', array( 'label' => 'پیام وقتی فراخوانی نیست', 'type' => Controls_Manager::TEXT, 'default' => '', 'description' => 'خالی = پیام پیش‌فرض.' ) );
		$this->end_controls_section();

		$card = '{{WRAPPER}} .art-coa-call-card';
		$this->start_controls_section( 'style_card', array( 'label' => 'کارت', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'card_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( $card => 'background: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'card_border', 'selector' => $card ) );
		$this->add_responsive_control( 'card_radius', array( 'label' => 'گردی گوشه‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( $card => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'card_shadow', 'selector' => $card ) );
		$this->add_responsive_control( 'card_padding', array( 'label' => 'فاصلهٔ داخلی متن', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .art-coa-call-card-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'image_ratio', array( 'label' => 'نسبت تصویر', 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => '۱۶:۹', '4/3' => '۴:۳', '1/1' => 'مربع', '3/4' => 'پوستر' ), 'selectors' => array( '{{WRAPPER}} .art-coa-call-card-img' => 'aspect-ratio: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_text', array( 'label' => 'متن و نشان‌ها', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'title_typo', 'label' => 'تایپوگرافی عنوان', 'selector' => '{{WRAPPER}} .art-coa-call-card-title' ) );
		$this->add_control( 'title_color', array( 'label' => 'رنگ عنوان', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-call-card-title a' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'meta_color', array( 'label' => 'رنگ اطلاعات', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-call-card-body .meta, {{WRAPPER}} .aet-call-excerpt' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'countdown_color', array( 'label' => 'رنگ شمارش معکوس', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-call-countdown' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'badge_bg', array( 'label' => 'پس‌زمینه نشان نوع', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-shop-badge' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'badge_color', array( 'label' => 'رنگ نشان نوع', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-shop-badge' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'heading_typo', 'label' => 'تایپوگرافی عنوان بخش', 'selector' => '{{WRAPPER}} .aet-calls-heading' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_button', array( 'label' => 'دکمه', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'btn_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-call-card-cta .button' => 'background: {{VALUE}}; border-color: {{VALUE}};' ) ) );
		$this->add_control( 'btn_color', array( 'label' => 'رنگ متن', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-call-card-cta .button' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'btn_radius', array( 'label' => 'گردی گوشه‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .aet-call-card-cta .button' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'btn_typo', 'selector' => '{{WRAPPER}} .aet-call-card-cta .button' ) );
		$this->end_controls_section();
	}

	/** Art COA gallery row for the widget settings (0 = all galleries, -1 = wanted but not found). */
	public static function gallery_row( array $s ) {
		$source = $s['source'] ?? 'all';
		if ( 'pick' === $source ) {
			$row = class_exists( 'Artiey_AET_Curator_View' ) ? Artiey_AET_Curator_View::gallery_row_for_post( absint( $s['gallery_post'] ?? 0 ) ) : 0;
			return $row ? $row : -1;
		}
		if ( 'current' === $source ) {
			$row = class_exists( 'Artiey_AET_Curator_View' ) ? Artiey_AET_Curator_View::context_gallery_row() : 0;
			return $row ? $row : -1;
		}
		return 0;
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$editor  = class_exists( 'Artiey_AET_Elementor_Options' ) && Artiey_AET_Elementor_Options::is_editor_mode();
		$gallery = self::gallery_row( $s );
		$limit   = max( 1, min( 50, (int) ( $s['limit'] ?? 6 ) ) );
		\WPArtCOA\Calls::enqueue();

		$heading = trim( (string) ( $s['heading'] ?? '' ) );
		$layout  = 'list' === ( $s['layout'] ?? 'grid' ) ? 'list' : 'grid';
		echo '<div class="aet-calls-widget aet-calls-widget--' . esc_attr( $layout ) . '">';
		if ( '' !== $heading ) {
			echo '<h2 class="aet-calls-heading">' . esc_html( $heading ) . '</h2>';
		}
		if ( -1 === $gallery ) {
			if ( $editor ) {
				echo '<p class="art-coa-empty">' . esc_html( 'گالری این صفحه به ماژول گواهی متصل نیست یا انتخاب نشده است.' ) . '</p>';
			}
			echo '</div>';
			return;
		}
		if ( 'archive' === ( $s['mode'] ?? 'cards' ) ) {
			echo \WPArtCOA\Calls::render_list( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Calls
				'type'    => (string) ( $s['call_type'] ?? '' ),
				'status'  => (string) ( $s['status'] ?? 'open' ),
				'limit'   => $limit,
				'gallery' => $gallery,
				'heading' => '' === $heading ? '1' : '0',
			) );
			echo '</div>';
			return;
		}
		$result = \WPArtCOA\Calls::query( array(
			'type'     => (string) ( $s['call_type'] ?? '' ),
			'status'   => (string) ( $s['status'] ?? 'open' ),
			'limit'    => $limit,
			'gallery'  => $gallery,
			'featured' => ! empty( $s['featured'] ),
		) );
		if ( ! $result['items'] ) {
			$text = trim( (string) ( $s['empty_text'] ?? '' ) );
			echo '<p class="art-coa-empty">' . esc_html( '' !== $text ? $text : \WPArtCOA\I18n::t( 'calls_empty' ) ) . '</p></div>';
			return;
		}
		echo '<div class="art-coa-call-grid">';
		echo \WPArtCOA\Calls::cards_html( $result['items'], array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Calls
			'countdown'   => ! empty( $s['show_countdown'] ),
			'excerpt'     => (int) ( $s['excerpt'] ?? 18 ),
			'button'      => ! empty( $s['show_button'] ),
			'gallery'     => ! empty( $s['show_gallery'] ),
			'image'       => ! empty( $s['show_image'] ),
			'heading_tag' => (string) ( $s['title_tag'] ?? 'h3' ),
		) );
		echo '</div>';
		if ( ! empty( $s['show_all_link'] ) && $result['total'] > count( $result['items'] ) ) {
			echo '<p class="aet-calls-all"><a href="' . esc_url( home_url( '/art-coa/calls/' ) ) . '">' . esc_html( 'مشاهده همه فراخوان‌ها' ) . ' (' . esc_html( Artiey_AET_Jalali::to_persian_digits( (string) $result['total'] ) ) . ')</a></p>';
		}
		echo '</div>';
	}
}
