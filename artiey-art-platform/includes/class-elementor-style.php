<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

/**
 * One shared "look" panel for every Artiey Elementor widget, so each widget can be restyled the same way
 * (colour scheme, colours, typography, cards, images, buttons, spacing) without copy-pasting controls.
 * Control names of the previous releases (accent, surface_color, text_color, card_color, card_radius, grid_gap,
 * profile_columns, profile_heading_typography) are kept so existing pages keep their saved design.
 */
trait Artiey_AET_Elementor_Style {

	/** Cards that share one chrome: gallery cards plus the plain artist/artwork/exhibition cards. */
	private function aet_cards() {
		return '{{WRAPPER}} .aetp-gcard, {{WRAPPER}} .aetp-card';
	}

	/**
	 * @param array $o Shared feature flags: cards, hero, stats, filters, images, map, sections, tabs.
	 */
	protected function aet_style_controls( $o = array() ) {
		$o = wp_parse_args(
			$o,
			array(
				'cards'   => true,
				'hero'    => false,
				'stats'   => false,
				'filters' => false,
				'images'  => true,
				'map'     => false,
				'sections' => false,
				'tabs'     => false,
			)
		);

		/* A common external-shortcode slot is exposed by every widget using this shared trait. */
		$this->start_controls_section( 'aet_extra_shortcode_section', array( 'label' => 'شورت‌کد و محتوای افزونه‌های دیگر', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'aet_extra_shortcode', array( 'label' => 'شورت‌کد دلخواه', 'type' => Controls_Manager::TEXTAREA, 'rows' => 4, 'dynamic' => array( 'active' => true ), 'placeholder' => '[your_shortcode attribute="value"]', 'description' => 'خروجی هر شورت‌کد ثبت‌شده در وردپرس، افزونه یا قالب را پس از محتوای این ویجت نمایش می‌دهد. برای شورت‌کدهای حساس، سطح دسترسی کاربران و خود افزونهٔ ارائه‌دهنده را بررسی کنید.' ) );
		$this->end_controls_section();

		/* ---------------- General ---------------- */
		$this->start_controls_section( 'section_style', array( 'label' => 'ظاهر کلی', 'tab' => Controls_Manager::TAB_STYLE ) );

		$this->add_control(
			'color_scheme',
			array(
				'label'        => 'طرح رنگی',
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					''     => 'روشن',
					'dark' => 'تیره',
					'auto' => 'خودکار (مطابق دستگاه بازدیدکننده)',
				),
				'default'      => '',
				'prefix_class' => 'aetp-scheme-',
			)
		);
		$this->add_control(
			'widget_design_preset',
			array(
				'label' => 'قالب طراحی آماده',
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'classic' => 'کلاسیک (حفظ ظاهر فعلی)',
					'editorial' => 'مجله‌ای / کیوریتوری',
					'luxury' => 'لوکس و گالری‌محور',
					'minimal' => 'مینیمال',
					'framed' => 'قاب‌دار',
				),
				'default' => 'classic',
				'prefix_class' => 'aetp-design-',
				'description' => 'قالب آماده فقط ظاهر را تغییر می‌دهد؛ داده‌ها و ارتباطات هنرمند، گالری، اثر و COA دست‌نخورده می‌مانند.',
			)
		);
		$this->add_control( 'accent', array( 'label' => 'رنگ تأکیدی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp, {{WRAPPER}} .aetd' => '--aetp-accent: {{VALUE}};' ) ) );
		$this->add_control( 'surface_color', array( 'label' => 'رنگ پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp, {{WRAPPER}} .aetd' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'رنگ متن', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp, {{WRAPPER}} .aetd' => 'color: {{VALUE}};' ) ) );
		$this->add_control(
			'muted_color',
			array(
				'label'     => 'رنگ متن‌های فرعی',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .aetp .aetp-card-sub, {{WRAPPER}} .aetp .aetp-meta, {{WRAPPER}} .aetp .aetp-stat span, {{WRAPPER}} .aetp .aetd-count, {{WRAPPER}} .aetp .aetd-lbl' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control( 'link_color', array( 'label' => 'رنگ پیوندها', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-sec a:not(.aetp-card):not(.aetp-gcard):not(.page-numbers)' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'link_hover_color', array( 'label' => 'رنگ پیوندها هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-sec a:not(.aetp-card):not(.aetp-gcard):not(.page-numbers):hover' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'body_typography', 'label' => 'تایپوگرافی متن', 'selector' => '{{WRAPPER}} .aetp' ) );
		$this->add_responsive_control(
			'box_padding',
			array(
				'label'      => 'فاصلهٔ داخلی',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array( '{{WRAPPER}} .aetp, {{WRAPPER}} .aetd' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'box_radius',
			array(
				'label'      => 'گردی گوشه‌های کادر',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .aetp, {{WRAPPER}} .aetd' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'content_max_width',
			array(
				'label' => 'حداکثر عرض محتوای ویجت',
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range' => array( 'px' => array( 'min' => 320, 'max' => 1600, 'step' => 20 ), '%' => array( 'min' => 50, 'max' => 100 ) ),
				'selectors' => array( '{{WRAPPER}} .aetp, {{WRAPPER}} .aetd' => 'max-width: {{SIZE}}{{UNIT}}; width: 100%; margin-inline: auto;' ),
			)
		);
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'widget_container_border', 'label' => 'حاشیهٔ کادر اصلی', 'selector' => '{{WRAPPER}} .aetp, {{WRAPPER}} .aetd' ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'widget_container_shadow', 'label' => 'سایهٔ کادر اصلی', 'selector' => '{{WRAPPER}} .aetp, {{WRAPPER}} .aetd' ) );
		if ( $o['map'] ) {
			$this->add_responsive_control(
				'map_h',
				array(
					'label'      => 'ارتفاع نقشه',
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array( 'px' => array( 'min' => 200, 'max' => 700 ) ),
					'selectors'  => array( '{{WRAPPER}} .aetp' => '--aetp-map-h: {{SIZE}}{{UNIT}};' ),
				)
			);
		}
		$this->end_controls_section();

		/* ---------------- Headings ---------------- */
		$this->start_controls_section( 'section_style_headings', array( 'label' => 'تیترها', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'profile_heading_typography', 'label' => 'تایپوگرافی تیترها', 'selector' => '{{WRAPPER}} .aetp-title, {{WRAPPER}} .aetp-sec>h3, {{WRAPPER}} .aetp-stats-title' ) );
		$this->add_control( 'heading_color', array( 'label' => 'رنگ تیتر بخش‌ها', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-sec>h3, {{WRAPPER}} .aetp-stats-title' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'heading_line_color', array( 'label' => 'رنگ خط زیر تیتر', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-sec>h3' => 'border-bottom-color: {{VALUE}};' ) ) );
		$this->add_control( 'heading_alignment', array( 'label' => 'تراز عنوان بخش‌ها', 'type' => Controls_Manager::SELECT, 'options' => array( '' => 'پیش‌فرض', 'right' => 'راست', 'center' => 'وسط', 'left' => 'چپ' ), 'default' => '', 'selectors' => array( '{{WRAPPER}} .aetp-sec>h3, {{WRAPPER}} .aetp-stats-title, {{WRAPPER}} .aetp-title' => 'text-align: {{VALUE}};' ) ) );
		$this->add_control( 'heading_decoration', array( 'label' => 'سبک تزئین تیترها', 'type' => Controls_Manager::SELECT, 'options' => array( 'accent' => 'خط تأکیدی', 'underline' => 'خط کامل', 'plain' => 'مینیمال بدون خط', 'boxed' => 'تیتر در کادر' ), 'default' => 'accent', 'prefix_class' => 'aetp-heading-' ) );
		$this->add_responsive_control(
			'section_gap',
			array(
				'label'      => 'فاصلهٔ بین بخش‌ها',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .aetp-sec' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();


		/* ---------------- Profile sections (text, lists, chips, photos) ---------------- */
		if ( $o['sections'] ) {
			$this->start_controls_section( 'section_style_sections', array( 'label' => 'بخش‌های پروفایل', 'tab' => Controls_Manager::TAB_STYLE ) );
			$this->add_control( 'sec_bg', array( 'label' => 'پس‌زمینهٔ هر بخش', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-sec' => 'background-color: {{VALUE}};' ) ) );
			$this->add_responsive_control( 'sec_padding', array( 'label' => 'فاصلهٔ داخلی هر بخش', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-sec' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
			$this->add_responsive_control( 'sec_radius', array( 'label' => 'گردی گوشهٔ بخش‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-sec' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'sec_border', 'selector' => '{{WRAPPER}} .aetp .aetp-sec' ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'bio_typography', 'label' => 'تایپوگرافی بیوگرافی و بیانیه', 'selector' => '{{WRAPPER}} .aetp .aetp-bio-text' ) );
			$this->add_control( 'bio_color', array( 'label' => 'رنگ بیوگرافی و بیانیه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-bio-text' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'lead_typography', 'label' => 'تایپوگرافی بیوگرافی کوتاه', 'selector' => '{{WRAPPER}} .aetp .aetp-lead' ) );
			$this->add_control( 'lead_color', array( 'label' => 'رنگ بیوگرافی کوتاه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-lead' => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'lead_line', array( 'label' => 'رنگ خط کنار بیوگرافی کوتاه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-lead' => 'border-right-color: {{VALUE}};' ) ) );
			$this->add_control( 'dt_color', array( 'label' => 'رنگ عنوان مشخصات', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-dl dt' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'dt_typography', 'label' => 'تایپوگرافی عنوان مشخصات', 'selector' => '{{WRAPPER}} .aetp .aetp-dl dt' ) );
			$this->add_control( 'dd_color', array( 'label' => 'رنگ مقدار مشخصات', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-dl dd' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'dd_typography', 'label' => 'تایپوگرافی مقدار مشخصات', 'selector' => '{{WRAPPER}} .aetp .aetp-dl dd' ) );
			$this->add_responsive_control( 'dl_gap', array( 'label' => 'فاصلهٔ ردیف‌های مشخصات', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-dl' => 'row-gap: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_control( 'list_color', array( 'label' => 'رنگ فهرست جوایز', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-list' => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'chip_bg', array( 'label' => 'پس‌زمینهٔ برچسب سبک', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-chip' => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( 'chip_color', array( 'label' => 'رنگ متن برچسب سبک', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-chip' => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'pill_bg', array( 'label' => 'پس‌زمینهٔ دکمه‌های شبکه‌های اجتماعی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-pill' => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( 'pill_color', array( 'label' => 'رنگ متن دکمه‌های شبکه‌های اجتماعی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-pill' => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'pill_hover_bg', array( 'label' => 'پس‌زمینهٔ دکمه‌ها هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-pill:hover' => 'background-color: {{VALUE}};' ) ) );
			$this->add_responsive_control( 'pill_radius', array( 'label' => 'گردی گوشهٔ دکمه‌ها و برچسب‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-pill, {{WRAPPER}} .aetp .aetp-chip' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_responsive_control( 'photo_cols', array( 'label' => 'ستون‌های نمونه آثار / تصاویر', 'type' => Controls_Manager::SELECT, 'options' => array( '' => 'خودکار', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶' ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-photo-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ) ) );
			$this->add_responsive_control( 'photo_height', array( 'label' => 'ارتفاع تصاویر نمونه آثار', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 80, 'max' => 500 ) ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-photo-grid img' => 'height: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_responsive_control( 'photo_radius', array( 'label' => 'گردی گوشهٔ تصاویر نمونه آثار', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-photo-grid img' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_responsive_control( 'photo_gap', array( 'label' => 'فاصلهٔ تصاویر', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-photo-grid' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_responsive_control( 'avatar_size', array( 'label' => 'اندازهٔ تصویر شاخص (آواتار)', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 60, 'max' => 260 ) ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );

			/* Connected gallery ecosystem cards (artists, curators, public collectors, auction lots). */
			$this->add_control( 'related_card_bg', array( 'label' => 'پس‌زمینهٔ کارت‌های ارتباطی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-related-card' => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( 'related_card_border_color', array( 'label' => 'رنگ حاشیهٔ کارت‌های ارتباطی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-related-card' => 'border-color: {{VALUE}};' ) ) );
			$this->add_responsive_control( 'related_card_radius', array( 'label' => 'گردی گوشهٔ کارت‌های ارتباطی', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 36 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-related-card' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'related_card_shadow', 'label' => 'سایهٔ کارت‌های ارتباطی', 'selector' => '{{WRAPPER}} .aetp-related-card' ) );
			$this->add_responsive_control( 'related_columns', array( 'label' => 'ستون‌های کارت‌های ارتباطی', 'type' => Controls_Manager::SELECT, 'options' => array( '' => 'پیش‌فرض', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶' ), 'selectors' => array( '{{WRAPPER}} .aetp-related-grid.is-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ) ) );
			$this->add_responsive_control( 'related_grid_gap', array( 'label' => 'فاصلهٔ بین کارت‌های ارتباطی', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 4, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-related-grid' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_responsive_control( 'related_media_height', array( 'label' => 'ارتفاع تصویر کارت‌های ارتباطی', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 90, 'max' => 360 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-related-media' => 'height: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_control( 'related_title_color', array( 'label' => 'رنگ عنوان کارت‌های ارتباطی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-related-title' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'related_title_typography', 'label' => 'تایپوگرافی عنوان کارت‌ها', 'selector' => '{{WRAPPER}} .aetp-related-title' ) );
			$this->add_control( 'related_meta_color', array( 'label' => 'رنگ جزئیات کارت‌های ارتباطی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-related-meta, {{WRAPPER}} .aetp-related-sub' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'related_meta_typography', 'label' => 'تایپوگرافی جزئیات کارت‌ها', 'selector' => '{{WRAPPER}} .aetp-related-meta, {{WRAPPER}} .aetp-related-sub' ) );
			$this->add_control( 'en_color', array( 'label' => 'رنگ نام لاتین و اطلاعات سربرگ', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-title-en, {{WRAPPER}} .aetp .aetp-meta' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'en_typography', 'label' => 'تایپوگرافی نام لاتین', 'selector' => '{{WRAPPER}} .aetp .aetp-title-en' ) );
			$this->end_controls_section();
		}

		/* ---------------- Hero ---------------- */
		if ( $o['hero'] ) {
			$this->start_controls_section( 'section_style_hero', array( 'label' => 'سربرگ', 'tab' => Controls_Manager::TAB_STYLE ) );
			$this->add_control( 'hero_title_color', array( 'label' => 'رنگ نام', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-title' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'hero_title_typography', 'label' => 'تایپوگرافی نام در سربرگ', 'selector' => '{{WRAPPER}} .aetp-title' ) );
			$this->add_control( 'hero_meta_color', array( 'label' => 'رنگ شهر و اطلاعات سربرگ', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-meta, {{WRAPPER}} .aetp-title-en' => 'color: {{VALUE}};' ) ) );
			$this->add_responsive_control( 'hero_cover_position', array( 'label' => 'جایگاه برش تصویر کاور', 'type' => Controls_Manager::SELECT, 'options' => array( 'center center' => 'وسط', 'center top' => 'بالا', 'center bottom' => 'پایین', 'right center' => 'راست', 'left center' => 'چپ' ), 'default' => 'center center', 'selectors' => array( '{{WRAPPER}} .aetp-cover' => 'object-position: {{VALUE}};' ) ) );
			$this->add_responsive_control(
				'hero_height',
				array(
					'label'      => 'حداقل ارتفاع سربرگ',
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'vh' ),
					'range'      => array( 'px' => array( 'min' => 120, 'max' => 700 ), 'vh' => array( 'min' => 10, 'max' => 90 ) ),
					'selectors'  => array( '{{WRAPPER}} .aetp-hero-inner' => 'min-height: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'hero_radius',
				array(
					'label'      => 'گردی گوشه‌های سربرگ',
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
					'selectors'  => array( '{{WRAPPER}} .aetp-hero' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control( 'hero_overlay', array( 'label' => 'رنگ لایهٔ روی کاور', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-hero.has-cover::after' => 'background: {{VALUE}};' ) ) );
			$this->end_controls_section();
		}

		/* ---------------- Cards ---------------- */
		if ( $o['cards'] ) {
			$cards = $this->aet_cards();
			$this->start_controls_section( 'section_style_cards', array( 'label' => 'کارت‌ها', 'tab' => Controls_Manager::TAB_STYLE ) );
			$this->add_responsive_control( 'profile_columns', array( 'label' => 'تعداد ستون‌های کارت', 'type' => Controls_Manager::SELECT, 'options' => array( '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶' ), 'selectors' => array( '{{WRAPPER}} .aetp-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ) ) );
			$this->add_responsive_control( 'grid_gap', array( 'label' => 'فاصلهٔ کارت‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 4, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-grid, {{WRAPPER}} .aetp-stats' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_control( 'card_color', array( 'label' => 'پس‌زمینهٔ کارت‌ها', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-stat, ' . $cards => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( 'card_title_color', array( 'label' => 'رنگ عنوان کارت', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-card-title' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'card_title_typography', 'label' => 'تایپوگرافی عنوان کارت', 'selector' => '{{WRAPPER}} .aetp-card-title' ) );
			$this->add_control( 'card_subtitle_color', array( 'label' => 'رنگ توضیح کارت', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-card-sub, {{WRAPPER}} .aetp-card-gal' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'card_subtitle_typography', 'label' => 'تایپوگرافی توضیح کارت', 'selector' => '{{WRAPPER}} .aetp-card-sub, {{WRAPPER}} .aetp-card-gal' ) );
			$this->add_responsive_control( 'card_content_padding', array( 'label' => 'فاصلهٔ داخلی کارت', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .aetp-card, {{WRAPPER}} .aetp-gcard-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
			$this->add_responsive_control( 'card_text_alignment', array( 'label' => 'تراز متن کارت', 'type' => Controls_Manager::SELECT, 'options' => array( '' => 'پیش‌فرض', 'right' => 'راست', 'center' => 'وسط', 'left' => 'چپ' ), 'default' => '', 'selectors' => array( '{{WRAPPER}} .aetp-card, {{WRAPPER}} .aetp-gcard-body' => 'text-align: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'card_border', 'selector' => '{{WRAPPER}} .aetp-gcard, {{WRAPPER}} .aetp-card, {{WRAPPER}} .aetp-stat' ) );
			$this->add_responsive_control( 'card_radius', array( 'label' => 'گردی گوشهٔ کارت‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-stat, ' . $cards => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .aetp-gcard, {{WRAPPER}} .aetp-card, {{WRAPPER}} .aetp-stat' ) );
			$this->add_control(
				'card_hover',
				array(
					'label'        => 'جلوهٔ اشاره روی کارت',
					'type'         => Controls_Manager::SELECT,
					'options'      => array(
						'' => 'پیش‌فرض',
						'lift' => 'بالا آمدن کارت',
						'scale' => 'بزرگ‌نمایی ملایم',
						'glow' => 'درخشش سایه',
						'none' => 'بدون جلوه',
					),
					'default'      => '',
					'prefix_class' => 'aetp-hover-',
				)
			);
			$this->end_controls_section();
		}

		/* ---------------- Images ---------------- */
		if ( $o['images'] ) {
			$this->start_controls_section( 'section_style_images', array( 'label' => 'تصاویر', 'tab' => Controls_Manager::TAB_STYLE ) );
			$this->add_responsive_control(
				'image_ratio',
				array(
					'label'     => 'نسبت تصویر کارت',
					'type'      => Controls_Manager::SELECT,
					'options'   => array(
						''     => 'پیش‌فرض',
						'1/1'  => '۱ : ۱',
						'4/5'  => '۴ : ۵',
						'3/4'  => '۳ : ۴',
						'4/3'  => '۴ : ۳',
						'16/9' => '۱۶ : ۹',
					),
					'selectors' => array( '{{WRAPPER}} .aetp-card-img' => 'aspect-ratio: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'image_fit',
				array(
					'label'     => 'نحوهٔ جاگیری تصویر',
					'type'      => Controls_Manager::SELECT,
					'options'   => array( '' => 'پیش‌فرض (برش)', 'contain' => 'نمایش کامل بدون برش' ),
					'selectors' => array( '{{WRAPPER}} .aetp-card-img img' => 'object-fit: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'image_radius',
				array(
					'label'      => 'گردی گوشهٔ تصاویر',
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ), '%' => array( 'min' => 0, 'max' => 50 ) ),
					'selectors'  => array( '{{WRAPPER}} .aetp-card-img, {{WRAPPER}} .aetp-ex-poster, {{WRAPPER}} .aetp-aw-media, {{WRAPPER}} .aetp-avatar, {{WRAPPER}} .aetp-logo' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'image_hover',
				array(
					'label'        => 'بزرگ‌شدن تصویر هنگام اشاره',
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'off',
					'label_off'    => 'روشن',
					'label_on'     => 'خاموش',
					'selectors'    => array( '{{WRAPPER}} .aetp-card:hover .aetp-card-img img' => 'transform: none;' ),
					'description'  => 'با فعال‌کردن این گزینه، جلوهٔ بزرگ‌نمایی تصویر کارت‌ها خاموش می‌شود.',
				)
			);
			$this->end_controls_section();
		}

		/* ---------------- Tabs ---------------- */
		if ( $o['tabs'] ) {
			$this->start_controls_section( 'section_style_tabs', array( 'label' => 'تب‌ها و پنل‌ها', 'tab' => Controls_Manager::TAB_STYLE ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'tab_typography', 'label' => 'تایپوگرافی تب‌ها', 'selector' => '{{WRAPPER}} .aetp-tabs .aetp-tabbtn' ) );
			$this->add_control( 'tab_visual_style', array( 'label' => 'شکل تب‌ها', 'type' => Controls_Manager::SELECT, 'options' => array( 'pill' => 'کپسولی', 'square' => 'مستطیلی', 'underline' => 'زیرخط‌دار' ), 'default' => 'pill', 'prefix_class' => 'aetp-tab-style-' ) );
			$this->add_control( 'tab_indicator_style', array( 'label' => 'نشانگر تب فعال', 'type' => Controls_Manager::SELECT, 'options' => array( 'none' => 'بدون نشانگر', 'bottom' => 'خط زیر تب', 'side' => 'نوار کناری', 'glow' => 'درخشش ظریف' ), 'default' => 'bottom', 'prefix_class' => 'aetp-tab-indicator-' ) );
			$this->add_control( 'tab_nav_background', array( 'label' => 'پس‌زمینه نوار تب‌ها', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tablist' => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( 'tab_hover_color', array( 'label' => 'رنگ متن هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn:hover' => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'tab_hover_background', array( 'label' => 'پس‌زمینه هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn:hover' => 'background-color: {{VALUE}};' ) ) );
			$this->add_responsive_control( 'tab_nav_width', array( 'label' => 'عرض نوار تب‌های عمودی', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => 140, 'max' => 380 ), '%' => array( 'min' => 20, 'max' => 45 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-tabs.is-vertical.js' => 'grid-template-columns: minmax(0, {{SIZE}}{{UNIT}}) minmax(0, 1fr);' ) ) );
			$this->add_responsive_control( 'tab_button_alignment', array( 'label' => 'جایگاه محتوای تب‌ها', 'type' => Controls_Manager::SELECT, 'options' => array( 'flex-start' => 'شروع', 'center' => 'وسط', 'flex-end' => 'پایان' ), 'default' => 'flex-start', 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn' => 'justify-content: {{VALUE}};' ) ) );
			$this->add_responsive_control( 'tab_button_padding', array( 'label' => 'فاصله داخلی تب‌ها', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
			$this->add_control( 'tab_text_color', array( 'label' => 'رنگ متن تب', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn' => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'tab_background', array( 'label' => 'پس‌زمینه تب', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn' => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( 'tab_active_color', array( 'label' => 'رنگ متن تب فعال', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn.is-active' => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'tab_active_background', array( 'label' => 'پس‌زمینه تب فعال', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn.is-active' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ) ) );
			$this->add_control( 'tab_border_color', array( 'label' => 'رنگ حاشیه تب', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn' => 'border-color: {{VALUE}};' ) ) );
			$this->add_responsive_control( 'tab_radius', array( 'label' => 'گردی گوشه تب‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_responsive_control( 'tab_gap', array( 'label' => 'فاصله تب‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tablist' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_control( 'tab_panel_background', array( 'label' => 'پس‌زمینه پنل محتوا', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabpanel' => 'background-color: {{VALUE}};' ) ) );
			$this->add_responsive_control( 'tab_panel_radius', array( 'label' => 'گردی گوشه پنل محتوا', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabpanel' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'tab_panel_border', 'label' => 'حاشیه پنل محتوا', 'selector' => '{{WRAPPER}} .aetp-tabs .aetp-tabpanel' ) );
			$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'tab_panel_shadow', 'label' => 'سایه پنل محتوا', 'selector' => '{{WRAPPER}} .aetp-tabs .aetp-tabpanel' ) );
			$this->add_responsive_control( 'tab_panel_padding', array( 'label' => 'فاصله داخلی پنل', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em', '%' ), 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabpanel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
			$this->end_controls_section();
		}

		/* ---------------- Shared action elements ---------------- */
		$this->start_controls_section( 'section_style_actions', array( 'label' => 'دکمه‌ها و پیوندهای تعاملی', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'aetp_action_text_color', array( 'label' => 'رنگ متن دکمه‌ها', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-pill, {{WRAPPER}} .aetp .aetp-bio-more>summary, {{WRAPPER}} .aetd .aetd-btn' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'aetp_action_background', array( 'label' => 'پس‌زمینهٔ دکمه‌ها', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-pill, {{WRAPPER}} .aetp .aetp-bio-more>summary, {{WRAPPER}} .aetd .aetd-btn' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'aetp_action_hover_text', array( 'label' => 'رنگ متن در حالت اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-pill:hover, {{WRAPPER}} .aetp .aetp-bio-more>summary:hover, {{WRAPPER}} .aetd .aetd-btn:hover' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'aetp_action_hover_background', array( 'label' => 'پس‌زمینه در حالت اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-pill:hover, {{WRAPPER}} .aetp .aetp-bio-more>summary:hover, {{WRAPPER}} .aetd .aetd-btn:hover' => 'background-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'aetp_action_typography', 'label' => 'تایپوگرافی دکمه‌ها', 'selector' => '{{WRAPPER}} .aetp .aetp-pill, {{WRAPPER}} .aetp .aetp-bio-more>summary, {{WRAPPER}} .aetd .aetd-btn' ) );
		$this->add_responsive_control( 'aetp_action_radius', array( 'label' => 'گردی دکمه‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ), '%' => array( 'min' => 0, 'max' => 50 ) ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-pill, {{WRAPPER}} .aetp .aetp-bio-more>summary, {{WRAPPER}} .aetd .aetd-btn' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'aetp_action_padding', array( 'label' => 'فاصلهٔ داخلی دکمه‌ها', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-pill, {{WRAPPER}} .aetp .aetp-bio-more>summary, {{WRAPPER}} .aetd .aetd-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		/* ---------------- Stats ---------------- */
		if ( $o['stats'] ) {
			$this->start_controls_section( 'section_style_stats', array( 'label' => 'شمارنده‌ها', 'tab' => Controls_Manager::TAB_STYLE ) );
			$this->add_control( 'stats_number', array( 'label' => 'رنگ عددها', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-stat strong' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'stats_number_typography', 'label' => 'تایپوگرافی عددها', 'selector' => '{{WRAPPER}} .aetp-stat strong' ) );
			$this->add_control( 'stats_text', array( 'label' => 'رنگ برچسب‌ها', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-stat span' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'stats_label_typography', 'label' => 'تایپوگرافی برچسب‌ها', 'selector' => '{{WRAPPER}} .aetp-stat span' ) );
			$this->add_responsive_control( 'stats_columns', array( 'label' => 'ستون‌های شمارنده', 'type' => Controls_Manager::SELECT, 'options' => array( '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶' ), 'selectors' => array( '{{WRAPPER}} .aetp-stats' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ) ) );
			$this->add_control( 'stats_surface', array( 'label' => 'پس‌زمینهٔ نوار شمارنده', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-stats' => 'background-color: {{VALUE}};' ) ) );
			$this->end_controls_section();
		}

		/* ---------------- Filters / buttons ---------------- */
		if ( $o['filters'] ) {
			$this->start_controls_section( 'section_style_filters', array( 'label' => 'فیلترها و دکمه‌ها', 'tab' => Controls_Manager::TAB_STYLE ) );
			$this->add_control( 'filters_bg', array( 'label' => 'پس‌زمینهٔ نوار فیلتر', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetd-filters' => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( 'filters_border', array( 'label' => 'رنگ حاشیهٔ فیلدها', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetd-f select, {{WRAPPER}} .aetd-f input[type=search]' => 'border-color: {{VALUE}};' ) ) );
			$this->add_control( 'button_bg', array( 'label' => 'پس‌زمینهٔ دکمه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetd-btn, {{WRAPPER}} .aetd-pager .page-numbers.current' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ) ) );
			$this->add_control( 'button_color', array( 'label' => 'رنگ متن دکمه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetd-btn, {{WRAPPER}} .aetd-pager .page-numbers.current' => 'color: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'button_typography', 'label' => 'تایپوگرافی دکمه', 'selector' => '{{WRAPPER}} .aetd-btn' ) );
			$this->add_responsive_control( 'button_radius', array( 'label' => 'گردی گوشهٔ دکمه و فیلدها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'selectors' => array( '{{WRAPPER}} .aetd-btn, {{WRAPPER}} .aetd-f select, {{WRAPPER}} .aetd-f input[type=search]' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_control( 'empty_color', array( 'label' => 'رنگ پیام «موردی یافت نشد»', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetd-empty' => 'color: {{VALUE}};' ) ) );
			$this->end_controls_section();
		}
	}

	/**
	 * Render the selected sections as accessible Elementor tabs. The default stacked layout remains unchanged.
	 * This deliberately delegates each section to the canonical profile renderer so all links/data stay connected.
	 *
	 * @param int    $id         Post ID.
	 * @param string $type       artiey_artist|artiey_gallery|artiey_artwork.
	 * @param array  $sections   Selected section keys.
	 * @param bool   $show_title Whether the primary heading should be shown.
	 * @return string
	 */
	protected function aet_render_profile_tabs( $id, $type, $sections, $show_title = true, $extras = array() ) {
		$maps = array(
			'artiey_artist' => array(
				'header' => 'سربرگ', 'short_bio' => 'معرفی کوتاه', 'statement' => 'بیانیه هنری', 'bio' => 'بیوگرافی',
				'details' => 'مشخصات', 'awards' => 'جوایز و افتخارات', 'career_timeline' => 'خط زمانی تحصیلات و جوایز',
				'contact' => 'ارتباط با هنرمند', 'works' => 'نمونه آثار', 'galleries' => 'گالری‌های همکار',
				'exhibitions' => 'نمایشگاه‌ها', 'activity_chart' => 'نمودار فعالیت', 'sales_chart' => 'نمودار فروش', 'artwork_history' => 'تاریخچه آثار',
			),
			'artiey_gallery' => array(
				'header' => 'سربرگ', 'short_bio' => 'معرفی کوتاه', 'bio' => 'بیوگرافی', 'details' => 'مشخصات', 'contact' => 'راه‌های ارتباطی',
				'hours' => 'ساعات کاری', 'map' => 'آدرس و نقشه', 'photos' => 'تصاویر گالری', 'artists' => 'هنرمندان',
				'curators' => 'کیوریتورها', 'collectors' => 'مجموعه‌داران عمومی', 'auctions' => 'حراج‌های مرتبط',
				'exhibitions' => 'نمایشگاه‌های جاری و پیش‌رو', 'past_exhibitions' => 'نمایشگاه‌های گذشته',
			),
			'artiey_artwork' => array( 'main' => 'مشخصات اثر', 'photos' => 'تصاویر اثر', 'related' => 'آثار دیگر هنرمند' ),
			'artiey_exhibition' => array( 'header' => 'معرفی نمایشگاه', 'details' => 'جزئیات و تاریخ‌ها', 'artists' => 'هنرمندان شرکت‌کننده', 'gallery' => 'گالری برگزارکننده', 'styles' => 'سبک‌های هنری' ),
		);
		$classes = array(
			'artiey_artist' => 'Artiey_AET_Artist',
			'artiey_gallery' => 'Artiey_AET_Gallery',
			'artiey_artwork' => 'Artiey_AET_Artwork',
			'artiey_exhibition' => 'Artiey_AET_Exhibition',
		);
		if ( empty( $maps[ $type ] ) || empty( $classes[ $type ] ) ) {
			return '';
		}
		// `null` means "the widget never saved a section list" (older pages): show every section, exactly like
		// the stacked layout does. An explicitly empty selection still renders nothing.
		if ( null === $sections ) {
			$sections = array_keys( $maps[ $type ] );
		}
		if ( ! is_array( $sections ) ) {
			return '';
		}
		$panels = array();
		foreach ( $sections as $section ) {
			if ( ! isset( $maps[ $type ][ $section ] ) ) {
				continue;
			}
			if ( 'artiey_exhibition' === $type ) {
				$renderer = array( $classes[ $type ], 'render_profile_section' );
				if ( ! is_callable( $renderer ) ) { continue; }
				$html = call_user_func( $renderer, (int) $id, $section, ( 'header' === $section && $show_title ) );
			} else {
				$renderer = array( $classes[ $type ], 'render_profile' );
				if ( ! is_callable( $renderer ) ) { continue; }
				if ( in_array( $type, array( 'artiey_artist', 'artiey_gallery' ), true ) ) {
					$html = call_user_func( $renderer, (int) $id, array( $section ), ( 'header' === $section || 'main' === $section ) ? $show_title : false, $extras );
				} else {
					$html = call_user_func( $renderer, (int) $id, array( $section ), ( 'header' === $section || 'main' === $section ) ? $show_title : false );
				}
			}
			if ( ! is_string( $html ) || '' === trim( $html ) ) { continue; }
			$panels[] = array( 'key' => $section, 'label' => $maps[ $type ][ $section ], 'html' => $html );
		}
		return class_exists( 'Artiey_AET_Tabs' ) ? Artiey_AET_Tabs::render( $panels, $extras, 'بخش‌های پروفایل' ) : ( $panels ? implode( '', array_column( $panels, 'html' ) ) : '' );
	}

	/**
	 * Shared "pick one record" source control + the editor hint used by every profile widget.
	 *
	 * @param string $key    Control name (gallery, artist, artwork, exhibition).
	 * @param string $type   Post type.
	 * @param string $label  Control label.
	 * @param string $current_label Label of the empty option.
	 */
	protected function aet_source_control( $key, $type, $label, $current_label ) {
		$options    = array( '' => $current_label ) + Artiey_AET_Elementor_Options::posts( $type );
		$current_id = Artiey_AET_Elementor_Options::current_post_id( $type );
		// The picker list is deliberately capped for large sites. Always append the
		// active artist/gallery/etc. so the current record is available even past that cap.
		if ( $current_id && ! isset( $options[ $current_id ] ) ) {
			$options[ $current_id ] = get_the_title( $current_id );
		}
		$this->add_control(
			$key,
			array(
				'label'       => $label,
				'type'        => Controls_Manager::SELECT2,
				'options'     => $options,
				'default'     => '',
				'label_block' => true,
			)
		);
		$this->add_control(
			$key . '_by_query',
			array(
				'label'        => 'خواندن از نشانی (پارامتر ?id=)',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => 'اگر روشن باشد و در نشانی صفحه ‎?aet_id=123‎ باشد، همان رکورد نمایش داده می‌شود؛ برای صفحه‌های قالب‌محور و لینک‌های دینامیک.',
			)
		);
	}

	/**
	 * Resolve the record a profile widget should show: explicit pick, then ?aet_id=, then the current post.
	 */
	protected function aet_resolve_post( $settings, $key, $type ) {
		$id = ! empty( $settings[ $key ] ) ? absint( $settings[ $key ] ) : 0;
		if ( ! $id && ! empty( $settings[ $key . '_by_query' ] ) && isset( $_GET['aet_id'] ) && is_scalar( $_GET['aet_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only selector, validated below.
			$id = absint( wp_unslash( $_GET['aet_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! $id ) {
			$id = Artiey_AET_Elementor_Options::current_post_id( $type );
		}
		if ( ! $id || get_post_type( $id ) !== $type ) {
			return 0;
		}
		// A visitor must never see drafts/private records through the picker or the query parameter.
		if ( 'publish' !== get_post_status( $id ) && ! current_user_can( 'edit_post', $id ) ) {
			return 0;
		}
		return $id;
	}

	/** Text shown when a profile widget has nothing to render (editor: hint, visitors: optional custom text). */
	protected function aet_empty_message( $settings, $editor_hint ) {
		if ( Artiey_AET_Elementor_Options::is_editor_mode() ) {
			echo '<p class="aap-s-d1822b">' . esc_html( $editor_hint ) . '</p>';
			return;
		}
		if ( ! empty( $settings['empty_text'] ) ) {
			echo '<p class="aetp-empty">' . esc_html( (string) $settings['empty_text'] ) . '</p>';
		}
	}

	protected function aet_empty_text_control() {
		$this->add_control(
			'empty_text',
			array(
				'label'       => 'پیام وقتی چیزی برای نمایش نیست',
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'خالی = چیزی نمایش داده نشود',
				'dynamic'     => array( 'active' => true ),
			)
		);
	}
}
