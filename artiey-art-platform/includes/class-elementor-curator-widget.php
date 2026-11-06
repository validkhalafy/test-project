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
 * Elementor widget: curators as cards, list, compact rows or a featured profile banner.
 * Data and markup come from Artiey_AET_Curator_View (active curators only, no private contact fields).
 */
class Artiey_AET_Elementor_Curator_Widget extends Widget_Base {

	public function get_name() {
		return 'artiey_curator';
	}

	public function get_title() {
		return 'کیوریتور';
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'کیوریتور', 'گردآورنده', 'نمایشگاه', 'curator', 'profile', 'team' );
	}

	public function get_style_depends() {
		return array( 'artiey-aet-curator' );
	}

	protected function register_controls() {
		$yes = array( 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes' );

		/* ---------------- Content: source ---------------- */
		$this->start_controls_section( 'section_source', array( 'label' => 'منبع و فیلتر', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'mode', array(
			'label'   => 'حالت',
			'type'    => Controls_Manager::SELECT,
			'default' => 'list',
			'options' => array( 'list' => 'فهرست کیوریتورها', 'single' => 'یک کیوریتور مشخص' ),
		) );
		$this->add_control( 'curator_id', array(
			'label'       => 'کیوریتور',
			'type'        => Controls_Manager::SELECT,
			'options'     => array( '' => '— انتخاب کنید —' ) + Artiey_AET_Curator_View::picker_options(),
			'default'     => '',
			'label_block' => true,
			'condition'   => array( 'mode' => 'single' ),
			'description' => 'فقط کیوریتورهای فعال (تأییدشده) در فهرست دیده می‌شوند.',
		) );
		$this->add_control( 'curator_slug', array(
			'label'       => 'یا نامک کیوریتور',
			'type'        => Controls_Manager::TEXT,
			'dynamic'     => array( 'active' => true ),
			'condition'   => array( 'mode' => 'single' ),
			'description' => 'اگر کیوریتور بالا انتخاب نشده باشد از این نامک استفاده می‌شود.',
		) );
		$this->add_control( 'limit', array( 'label' => 'تعداد', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 48, 'default' => 6, 'condition' => array( 'mode' => 'list' ) ) );
		$this->add_control( 'orderby', array(
			'label'     => 'ترتیب',
			'type'      => Controls_Manager::SELECT,
			'default'   => 'newest',
			'options'   => array( 'newest' => 'جدیدترین', 'name' => 'نام (الفبایی)', 'experience' => 'بیشترین سابقه' ),
			'condition' => array( 'mode' => 'list' ),
		) );
		$this->add_control( 'specialty', array( 'label' => 'فیلتر تخصص (متن)', 'type' => Controls_Manager::TEXT, 'condition' => array( 'mode' => 'list' ), 'description' => 'فقط کیوریتورهایی که این عبارت در تخصص یا بیوگرافی‌شان هست.' ) );
		$this->end_controls_section();

		/* ---------------- Content: layout ---------------- */
		$this->start_controls_section( 'section_layout', array( 'label' => 'چیدمان', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'layout', array(
			'label'   => 'سبک نمایش',
			'type'    => Controls_Manager::SELECT,
			'default' => 'card',
			'options' => array(
				'card'     => 'کارت (آواتار وسط)',
				'list'     => 'ردیفی (افقی)',
				'compact'  => 'فشرده (آواتار و نام)',
				'featured' => 'ویژه (بنر کاور و آواتار)',
			),
		) );
		$this->add_responsive_control( 'columns', array(
			'label'          => 'تعداد ستون',
			'type'           => Controls_Manager::SELECT,
			'default'        => '3',
			'tablet_default' => '2',
			'mobile_default' => '1',
			'options'        => array( '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶' ),
			'selectors'      => array( '{{WRAPPER}} .aetcu' => '--aetcu-cols: {{VALUE}};' ),
			'condition'      => array( 'layout' => array( 'card', 'compact' ) ),
		) );
		$this->add_responsive_control( 'gap', array(
			'label' => 'فاصلهٔ کارت‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ),
			'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'default' => array( 'size' => 20, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .aetcu' => '--aetcu-gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'avatar_shape', array( 'label' => 'شکل آواتار', 'type' => Controls_Manager::SELECT, 'default' => 'circle', 'options' => array( 'circle' => 'دایره', 'rounded' => 'گوشهٔ گرد', 'square' => 'مربع' ) ) );
		$this->add_control( 'name_tag', array( 'label' => 'تگ نام', 'type' => Controls_Manager::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ) ) );
		$this->end_controls_section();

		/* ---------------- Content: what to show ---------------- */
		$this->start_controls_section( 'section_show', array( 'label' => 'اطلاعات نمایشی', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'show_avatar', $yes + array( 'label' => 'آواتار', 'default' => 'yes' ) );
		$this->add_control( 'show_cover', $yes + array( 'label' => 'تصویر کاور', 'default' => '', 'description' => 'در سبک «ویژه» همیشه نمایش داده می‌شود.' ) );
		$this->add_control( 'show_affiliation', $yes + array( 'label' => 'وابستگی سازمانی', 'default' => 'yes' ) );
		$this->add_control( 'show_specialties', $yes + array( 'label' => 'تخصص‌ها (چیپ)', 'default' => 'yes' ) );
		$this->add_control( 'show_bio', $yes + array( 'label' => 'خلاصهٔ بیوگرافی', 'default' => 'yes' ) );
		$this->add_control( 'bio_words', array( 'label' => 'تعداد کلمات خلاصه', 'type' => Controls_Manager::NUMBER, 'min' => 5, 'max' => 120, 'default' => 28, 'condition' => array( 'show_bio' => 'yes' ) ) );
		$this->add_control( 'show_meta', $yes + array( 'label' => 'سابقه و زبان‌ها', 'default' => '' ) );
		$this->add_control( 'show_social', $yes + array( 'label' => 'پیوندهای وب‌سایت و شبکه‌ها', 'default' => '', 'description' => 'ایمیل و تلفن هرگز نمایش داده نمی‌شوند.' ) );
		$this->add_control( 'show_button', $yes + array( 'label' => 'دکمهٔ مشاهدهٔ پروفایل', 'default' => 'yes' ) );
		$this->add_control( 'button_text', array( 'label' => 'متن دکمه', 'type' => Controls_Manager::TEXT, 'default' => 'مشاهده پروفایل', 'condition' => array( 'show_button' => 'yes' ) ) );
		$this->end_controls_section();

		/* ---------------- Content: heading + SEO ---------------- */
		$this->start_controls_section( 'section_heading', array( 'label' => 'عنوان و سئو', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'heading', array( 'label' => 'عنوان بخش', 'type' => Controls_Manager::TEXT, 'label_block' => true, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'heading_tag', array( 'label' => 'تگ عنوان', 'type' => Controls_Manager::SELECT, 'default' => 'h2', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ), 'condition' => array( 'heading!' => '' ) ) );
		$this->add_control( 'aria_label', array( 'label' => 'برچسب دسترس‌پذیری', 'type' => Controls_Manager::TEXT, 'default' => 'کیوریتورها' ) );
		$this->add_control( 'schema', $yes + array( 'label' => 'داده ساختاریافته (Person / ItemList)', 'default' => 'yes', 'description' => 'نام، وابستگی، تخصص‌ها و شبکه‌ها را برای گوگل مشخص می‌کند.' ) );
		$this->add_control( 'empty_text', array( 'label' => 'پیام وقتی کیوریتوری نیست', 'type' => Controls_Manager::TEXT, 'default' => '', 'description' => 'خالی = در سایت چیزی نمایش داده نمی‌شود.' ) );
		$this->end_controls_section();

		/* ---------------- Style ---------------- */
		$item = '{{WRAPPER}} .aetcu-item';

		$this->start_controls_section( 'style_card', array( 'label' => 'کارت', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'card_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( $item => 'background: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'card_border', 'selector' => $item ) );
		$this->add_responsive_control( 'card_radius', array( 'label' => 'گردی گوشه‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .aetcu' => '--aetcu-r: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'card_shadow', 'selector' => $item ) );
		$this->add_responsive_control( 'card_padding', array( 'label' => 'فاصلهٔ داخلی', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( $item => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'hover_lift', array( 'label' => 'بالا آمدن هنگام اشاره', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'selectors_dictionary' => array( 'yes' => 'translateY(-3px)', '' => 'none' ), 'selectors' => array( $item . ':hover' => 'transform: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_avatar', array( 'label' => 'آواتار', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'avatar_size', array( 'label' => 'اندازه', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 40, 'max' => 240 ) ), 'selectors' => array( '{{WRAPPER}} .aetcu' => '--aetcu-av: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'avatar_bg', array( 'label' => 'پس‌زمینهٔ حروف اول', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-avatar' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'avatar_color', array( 'label' => 'رنگ حروف اول', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-avatar' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'avatar_border', 'selector' => '{{WRAPPER}} .aetcu-avatar' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_text', array( 'label' => 'متن‌ها', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'name_typo', 'label' => 'تایپوگرافی نام', 'selector' => '{{WRAPPER}} .aetcu-name' ) );
		$this->add_control( 'name_color', array( 'label' => 'رنگ نام', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-name a' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'aff_typo', 'label' => 'تایپوگرافی وابستگی', 'selector' => '{{WRAPPER}} .aetcu-aff' ) );
		$this->add_control( 'aff_color', array( 'label' => 'رنگ وابستگی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-aff' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'bio_typo', 'label' => 'تایپوگرافی بیوگرافی', 'selector' => '{{WRAPPER}} .aetcu-bio' ) );
		$this->add_control( 'bio_color', array( 'label' => 'رنگ بیوگرافی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-bio' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'meta_color', array( 'label' => 'رنگ سابقه و زبان', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-meta' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'social_color', array( 'label' => 'رنگ پیوندهای شبکه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-social a' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_chips', array( 'label' => 'چیپ‌های تخصص', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'chip_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-chips li' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'chip_color', array( 'label' => 'رنگ متن', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-chips li' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'chip_typo', 'selector' => '{{WRAPPER}} .aetcu-chips li' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_button', array( 'label' => 'دکمه', 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'show_button' => 'yes' ) ) );
		$this->add_control( 'btn_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-btn' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'btn_color', array( 'label' => 'رنگ متن', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-btn' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'btn_bg_hover', array( 'label' => 'پس‌زمینه هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-btn:hover' => 'background: {{VALUE}}; filter: none;' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'btn_typo', 'selector' => '{{WRAPPER}} .aetcu-btn' ) );
		$this->add_responsive_control( 'btn_radius', array( 'label' => 'گردی گوشه‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .aetcu-btn' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_heading', array( 'label' => 'عنوان بخش', 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'heading!' => '' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'heading_typo', 'selector' => '{{WRAPPER}} .aetcu-heading' ) );
		$this->add_control( 'heading_color', array( 'label' => 'رنگ', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetcu-heading' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'heading_gap', array( 'label' => 'فاصله تا کارت‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( '{{WRAPPER}} .aetcu-heading' => 'margin: 0 0 {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$edit = \Elementor\Plugin::$instance->editor->is_edit_mode();

		if ( ! Artiey_AET_Curator_View::available() ) {
			if ( $edit ) {
				echo '<div style="padding:18px;border:2px dashed #c3c8d0;border-radius:12px;text-align:center">' . esc_html( 'برای نمایش کیوریتورها ماژول گواهی اصالت (COA) باید فعال باشد.' ) . '</div>';
			}
			return;
		}

		$args = array( 'limit' => 'single' === ( $s['mode'] ?? 'list' ) ? 1 : (int) ( $s['limit'] ?? 6 ), 'orderby' => $s['orderby'] ?? 'newest', 'specialty' => $s['specialty'] ?? '' );
		if ( 'single' === ( $s['mode'] ?? 'list' ) ) {
			$args['specialty'] = '';
			if ( ! empty( $s['curator_id'] ) ) {
				$args['id'] = (int) $s['curator_id'];
			} elseif ( ! empty( $s['curator_slug'] ) ) {
				$args['slug'] = (string) $s['curator_slug'];
			} else {
				$args = array();
			}
		}
		$rows = $args ? Artiey_AET_Curator_View::fetch( $args ) : array();

		if ( ! $rows ) {
			$text = trim( (string) ( $s['empty_text'] ?? '' ) );
			if ( '' !== $text ) {
				echo '<p class="aetcu-empty">' . esc_html( $text ) . '</p>';
			} elseif ( $edit ) {
				echo '<div style="padding:18px;border:2px dashed #c3c8d0;border-radius:12px;text-align:center;color:#5b6470">' . esc_html( 'کیوریتور فعالی برای نمایش پیدا نشد. یک کیوریتور را انتخاب یا تأیید کنید.' ) . '</div>';
			}
			return;
		}

		$heading = trim( (string) ( $s['heading'] ?? '' ) );
		if ( '' !== $heading ) {
			$tag = in_array( $s['heading_tag'] ?? 'h2', array( 'h2', 'h3', 'h4', 'div' ), true ) ? $s['heading_tag'] : 'h2';
			echo '<' . $tag . ' class="aetcu-heading">' . esc_html( $heading ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- tag is allow-listed
		}
		echo Artiey_AET_Curator_View::render( $rows, array( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer
			'layout'       => $s['layout'] ?? 'card',
			'avatar'       => ! empty( $s['show_avatar'] ),
			'cover'        => ! empty( $s['show_cover'] ),
			'affiliation'  => ! empty( $s['show_affiliation'] ),
			'specialties'  => ! empty( $s['show_specialties'] ),
			'bio'          => ! empty( $s['show_bio'] ),
			'bio_words'    => $s['bio_words'] ?? 28,
			'meta'         => ! empty( $s['show_meta'] ),
			'social'       => ! empty( $s['show_social'] ),
			'button'       => ! empty( $s['show_button'] ),
			'button_text'  => $s['button_text'] ?? '',
			'name_tag'     => $s['name_tag'] ?? 'h3',
			'avatar_shape' => $s['avatar_shape'] ?? 'circle',
			'label'        => $s['aria_label'] ?? '',
		) );

		if ( ! empty( $s['schema'] ) && ! $edit ) {
			echo Artiey_AET_Media_Gallery::schema_script( Artiey_AET_Curator_View::schema( $rows ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
