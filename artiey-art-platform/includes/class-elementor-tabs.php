<?php
/**
 * Shared accessible, configurable tabs for Artiey and the bundled Art COA Elementor widgets.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Artiey_AET_Tabs {
	/**
	 * Render tabs from already-rendered trusted widget panels.
	 * Each panel: [ 'key' => string, 'label' => string, 'html' => string ].
	 */
	public static function render( $panels, $settings = array(), $aria_label = 'بخش‌های محتوا' ) {
		if ( ! is_array( $panels ) ) {
			return '';
		}
		$clean = array();
		$seen  = array();
		foreach ( $panels as $panel ) {
			if ( ! is_array( $panel ) || ! isset( $panel['html'] ) || ! is_string( $panel['html'] ) ) {
				continue;
			}
			$html = trim( $panel['html'] );
			if ( '' === $html ) {
				continue;
			}
			$plain = trim( wp_strip_all_tags( $html ) );
			if ( '' === $plain && ! preg_match( '/<(img|iframe|svg|video|audio|canvas)\b/i', $html ) ) {
				continue;
			}
			$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) ( $panel['key'] ?? '' ) ) : preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) ( $panel['key'] ?? '' ) ) );
			if ( '' === $key ) {
				$key = 'section-' . count( $clean );
			}
			if ( isset( $seen[ $key ] ) ) {
				$seen[ $key ]++;
				$key .= '-' . $seen[ $key ];
			} else {
				$seen[ $key ] = 1;
			}
			$label = trim( wp_strip_all_tags( (string) ( $panel['label'] ?? $key ) ) );
			if ( '' === $label ) {
				$label = $key;
			}
			$clean[] = array( 'key' => $key, 'label' => $label, 'html' => $html );
		}
		if ( ! $clean ) {
			return '';
		}
		$clean = self::customize_panels( $clean, $settings );
		if ( ! $clean ) {
			return '';
		}
		if ( 1 === count( $clean ) && empty( $settings['force_tabs'] ) ) {
			return $clean[0]['html'];
		}
		$orientation = ( isset( $settings['tabs_orientation'] ) && 'vertical' === $settings['tabs_orientation'] ) ? 'vertical' : 'horizontal';
		$mobile = sanitize_key( (string) ( $settings['tabs_mobile_orientation'] ?? 'horizontal' ) );
		if ( ! in_array( $mobile, array( 'same', 'horizontal', 'vertical' ), true ) ) {
			$mobile = 'horizontal';
		}
		$default = ( 'last' === ( $settings['tabs_default_tab'] ?? 'first' ) ) ? count( $clean ) - 1 : 0;
		$animation = sanitize_key( (string) ( $settings['tabs_animation'] ?? 'none' ) );
		if ( ! in_array( $animation, array( 'none', 'fade', 'slide' ), true ) ) {
			$animation = 'none';
		}
		$base = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'aetp-tabs-' ) : 'aetp-tabs-' . str_replace( '.', '', uniqid( '', true ) );
		$skin = sanitize_key( (string) ( $settings['tabs_skin'] ?? 'pills' ) );
		if ( ! in_array( $skin, array( 'pills', 'underline', 'boxed', 'minimal' ), true ) ) { $skin = 'pills'; }
		$alignment = sanitize_key( (string) ( $settings['tabs_alignment'] ?? 'start' ) );
		if ( ! in_array( $alignment, array( 'start', 'center', 'end', 'stretch' ), true ) ) { $alignment = 'start'; }
		$panel_style = sanitize_key( (string) ( $settings['tabs_panel_style'] ?? 'bordered' ) );
		if ( ! in_array( $panel_style, array( 'bordered', 'card', 'flat' ), true ) ) { $panel_style = 'bordered'; }
		$classes = array( 'aetp-tabs', 'aetp-elementor-tabs', 'is-' . $orientation, 'aetp-tabs-skin-' . $skin, 'aetp-tabs-align-' . $alignment, 'aetp-tabs-panel-' . $panel_style );
		if ( ! empty( $settings['tabs_scrollable'] ) && 'yes' === $settings['tabs_scrollable'] ) { $classes[] = 'is-scrollable'; }
		if ( ! empty( $settings['tabs_equal_width'] ) && 'yes' === $settings['tabs_equal_width'] ) {
			$classes[] = 'is-equal-width';
		}
		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-aetp-tabs data-tabs-orientation="' . esc_attr( $orientation ) . '" data-tabs-mobile-orientation="' . esc_attr( $mobile ) . '" data-tabs-default="' . esc_attr( $default === count( $clean ) - 1 ? 'last' : 'first' ) . '" data-tabs-animation="' . esc_attr( $animation ) . '" data-tabs-url-hash="' . ( ! empty( $settings['tabs_url_hash'] ) && 'yes' === $settings['tabs_url_hash'] ? 'yes' : 'no' ) . '" id="' . esc_attr( $base ) . '">';
		$html .= '<div class="aetp-tablist" role="tablist" aria-orientation="' . esc_attr( $orientation ) . '" aria-label="' . esc_attr( $aria_label ) . '">';
		foreach ( $clean as $index => $panel ) {
			$tab_id = $base . '-tab-' . sanitize_html_class( $panel['key'] );
			$panel_id = $base . '-panel-' . sanitize_html_class( $panel['key'] );
			$active = $index === $default;
			$label_html = '';
			if ( ! empty( $panel['icon_html'] ) ) {
				$label_html .= '<span class="aetp-tab-icon" aria-hidden="true">' . $panel['icon_html'] . '</span>';
			}
			$label_html .= '<span class="aetp-tab-label">' . esc_html( $panel['label'] ) . '</span>';
			$html .= '<button type="button" role="tab" class="aetp-tabbtn' . ( $active ? ' is-active' : '' ) . '" id="' . esc_attr( $tab_id ) . '" aria-controls="' . esc_attr( $panel_id ) . '" aria-selected="' . ( $active ? 'true' : 'false' ) . '" tabindex="' . ( $active ? '0' : '-1' ) . '" data-t="' . esc_attr( $panel['key'] ) . '" aria-label="' . esc_attr( $panel['label'] ) . '">' . $label_html . '</button>';
		}
		$html .= '</div>';
		foreach ( $clean as $index => $panel ) {
			$tab_id = $base . '-tab-' . sanitize_html_class( $panel['key'] );
			$panel_id = $base . '-panel-' . sanitize_html_class( $panel['key'] );
			$active = $index === $default;
			$html .= '<div class="aetp-tabpanel' . ( $active ? ' is-active' : '' ) . '" role="tabpanel" id="' . esc_attr( $panel_id ) . '" aria-labelledby="' . esc_attr( $tab_id ) . '" aria-hidden="' . ( $active ? 'false' : 'true' ) . '" data-t="' . esc_attr( $panel['key'] ) . '">' . $panel['html'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML comes from plugin renderers.
		}
		return $html . '</div>';
	}

	/** Sanitize the icon HTML generated by Elementor while retaining safe inline SVG geometry. */
	private static function sanitize_icon_markup( $html ) {
		$allowed = array(
			'i' => array( 'class' => true, 'aria-hidden' => true, 'role' => true ),
			'span' => array( 'class' => true, 'aria-hidden' => true ),
			'svg' => array( 'class' => true, 'xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'aria-hidden' => true, 'focusable' => true, 'role' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'g' => array( 'fill' => true, 'stroke' => true, 'transform' => true, 'opacity' => true ),
			'path' => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true, 'clip-rule' => true, 'transform' => true ),
			'circle' => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'rect' => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'line' => array( 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'polygon' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'ellipse' => array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
		);
		if ( function_exists( 'wp_kses' ) ) {
			return wp_kses( $html, $allowed );
		}
		return strip_tags( (string) $html, '<i><span><svg><g><path><circle><rect><line><polyline><polygon><ellipse>' );
	}

	/**
	 * Apply the user-defined order, labels, icons and visibility to already-rendered panels.
	 * The renderer never accepts arbitrary HTML from control values; only Elementor's icon renderer
	 * may provide the optional icon markup.
	 */
	private static function customize_panels( $panels, $settings ) {
		// No saved repeater means this is a legacy widget: preserve its original tabs.
		// An explicitly saved empty repeater means the editor removed every tab: respect it.
		if ( ! array_key_exists( 'tabs_items', $settings ) ) {
			return $panels;
		}
		if ( ! is_array( $settings['tabs_items'] ) ) {
			return $panels;
		}
		$items = $settings['tabs_items'];
		if ( empty( $items ) ) {
			return array();
		}
		$by_key = array();
		foreach ( $panels as $panel ) {
			$by_key[ $panel['key'] ] = $panel;
		}
		$ordered = array();
		$seen    = array();
		$matched = 0;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$key = sanitize_key( (string) ( $item['tab_section'] ?? '' ) );
			if ( '' === $key || ! isset( $by_key[ $key ] ) || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$matched++;
			if ( isset( $item['tab_enabled'] ) && 'yes' !== $item['tab_enabled'] ) {
				continue;
			}
			$panel = $by_key[ $key ];
			$label = sanitize_text_field( (string) ( $item['tab_label'] ?? '' ) );
			if ( '' !== $label ) {
				$panel['label'] = $label;
			}
			$icon = isset( $item['tab_icon'] ) && is_array( $item['tab_icon'] ) ? $item['tab_icon'] : array();
			if ( $icon && class_exists( '\Elementor\Icons_Manager' ) && is_callable( array( '\Elementor\Icons_Manager', 'render_icon' ) ) ) {
				$icon_html = \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
				if ( is_string( $icon_html ) && '' !== trim( $icon_html ) ) {
					$panel['icon_html'] = self::sanitize_icon_markup( $icon_html );
				}
			}
			$ordered[] = $panel;
		}
		// A malformed/stale repeater must not blank a working widget; an intentional
		// all-disabled configuration, however, should keep all tabs hidden.
		return $matched > 0 ? $ordered : $panels;
	}

	/** Reusable behaviour controls; use a condition key/value specific to the widget. */
	public static function register_behavior_controls( $widget, $condition_key = 'profile_layout', $condition_value = 'tabs', $tab_options = array(), $extra_conditions = array() ) {
		$condition = array( $condition_key => $condition_value );
		if ( is_array( $extra_conditions ) ) {
			foreach ( $extra_conditions as $extra_key => $extra_value ) {
				if ( is_string( $extra_key ) && '' !== $extra_key && is_scalar( $extra_value ) ) {
					$condition[ $extra_key ] = $extra_value;
				}
			}
		}
		if ( is_array( $tab_options ) && $tab_options && class_exists( '\Elementor\Repeater' ) ) {
			$repeater = new \Elementor\Repeater();
			$repeater->add_control( 'tab_section', array(
				'label' => 'بخش متصل به این تب', 'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $tab_options, 'default' => (string) key( $tab_options ),
			) );
			$repeater->add_control( 'tab_label', array(
				'label' => 'عنوان دلخواه تب', 'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '', 'label_block' => true, 'placeholder' => 'خالی = عنوان پیش‌فرض بخش',
			) );
			$repeater->add_control( 'tab_icon', array(
				'label' => 'آیکون تب (اختیاری)', 'type' => \Elementor\Controls_Manager::ICONS,
				'default' => array( 'value' => '', 'library' => '' ),
			) );
			$repeater->add_control( 'tab_enabled', array(
				'label' => 'نمایش این تب', 'type' => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes', 'default' => 'yes',
			) );
			$defaults = array();
			foreach ( $tab_options as $section_key => $section_label ) {
				$defaults[] = array( 'tab_section' => (string) $section_key, 'tab_label' => (string) $section_label, 'tab_icon' => array( 'value' => '', 'library' => '' ), 'tab_enabled' => 'yes' );
			}
			$widget->add_control( 'tabs_items', array(
				'label' => 'ویرایش و ترتیب تب‌ها', 'type' => \Elementor\Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(), 'default' => $defaults,
				'title_field' => '{{{ tab_label || tab_section }}}', 'condition' => $condition,
				'button_text' => 'افزودن تب', 'prevent_empty' => false,
				'description' => 'ردیف‌ها را بکشید و مرتب کنید؛ عنوان و آیکون دلخواه بدهید یا هر تب را خاموش کنید. فقط بخش‌های فعال و دارای محتوای واقعی نمایش داده می‌شوند. حذف ردیف نیز تب را از خروجی حذف می‌کند.',
			) );
		}
		$widget->add_control( 'tabs_orientation', array(
			'label' => 'جهت تب‌ها', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'horizontal' => 'افقی (بالای محتوا)', 'vertical' => 'عمودی (کنار محتوا)' ),
			'default' => 'horizontal', 'condition' => $condition,
		) );
		$widget->add_control( 'tabs_mobile_orientation', array(
			'label' => 'جهت تب‌ها در موبایل', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'horizontal' => 'افقی', 'vertical' => 'عمودی', 'same' => 'مانند دسکتاپ' ),
			'default' => 'horizontal', 'condition' => $condition,
		) );
		$widget->add_control( 'tabs_default_tab', array(
			'label' => 'تب پیش‌فرض هنگام بارگذاری', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'first' => 'اولین تب', 'last' => 'آخرین تب' ), 'default' => 'first', 'condition' => $condition,
		) );
		$widget->add_control( 'tabs_animation', array(
			'label' => 'انیمیشن تغییر تب', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'none' => 'بدون انیمیشن', 'fade' => 'محو شدن ملایم', 'slide' => 'لغزش ملایم' ), 'default' => 'none', 'condition' => $condition,
		) );
		$widget->add_control( 'tabs_skin', array(
			'label' => 'طرح تب‌ها', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'pills' => 'کپسولی', 'underline' => 'زیرخط‌دار', 'boxed' => 'کادردار', 'minimal' => 'مینیمال' ),
			'default' => 'pills', 'condition' => $condition,
		) );
		$widget->add_control( 'tabs_alignment', array(
			'label' => 'تراز نوار تب‌ها', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'start' => 'شروع', 'center' => 'وسط', 'end' => 'پایان', 'stretch' => 'تمام عرض' ),
			'default' => 'start', 'condition' => $condition,
		) );
		$widget->add_control( 'tabs_panel_style', array(
			'label' => 'سبک پنل محتوا', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'bordered' => 'دارای حاشیه', 'card' => 'کارت برجسته', 'flat' => 'بدون قاب' ),
			'default' => 'bordered', 'condition' => $condition,
		) );
		$widget->add_control( 'tabs_scrollable', array(
			'label' => 'پیمایش افقی نوار تب‌ها در موبایل', 'type' => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes', 'default' => 'yes', 'condition' => $condition,
		) );
		$widget->add_control( 'tabs_equal_width', array(
			'label' => 'عرض یکسان تب‌ها', 'type' => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes', 'default' => '', 'condition' => $condition,
		) );
		$widget->add_control( 'tabs_url_hash', array(
			'label' => 'همگام‌سازی تب با نشانی صفحه', 'type' => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes', 'default' => '', 'condition' => $condition,
			'description' => 'با انتخاب تب، شناسه آن به نشانی صفحه افزوده می‌شود تا بتوان مستقیماً به همان بخش پیوند داد.',
		) );
	}

	/** Additional pro styling for bundled COA widgets that do not use the shared style trait. */
	public static function register_style_controls( $widget, $prefix = 'aetpro' ) {
		$widget->start_controls_section( $prefix . '_tabs_appearance', array( 'label' => 'تب‌ها و پنل‌ها — حرفه‌ای', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$widget->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => $prefix . '_tabs_typography', 'label' => 'تایپوگرافی تب‌ها', 'selector' => '{{WRAPPER}} .aetp-tabs .aetp-tabbtn' ) );
		$widget->add_control( $prefix . '_tabs_nav_bg', array( 'label' => 'پس‌زمینه نوار تب‌ها', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tablist' => 'background-color: {{VALUE}};' ) ) );
		$widget->add_control( $prefix . '_tabs_hover_color', array( 'label' => 'رنگ متن در هاور', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn:hover' => 'color: {{VALUE}};' ) ) );
		$widget->add_control( $prefix . '_tabs_hover_bg', array( 'label' => 'پس‌زمینه در هاور', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn:hover' => 'background-color: {{VALUE}};' ) ) );
		$widget->add_control( $prefix . '_tabs_active_color', array( 'label' => 'رنگ متن تب فعال', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn.is-active' => 'color: {{VALUE}};' ) ) );
		$widget->add_control( $prefix . '_tabs_active_bg', array( 'label' => 'پس‌زمینه تب فعال', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn.is-active' => 'background-color: {{VALUE}};' ) ) );
		$widget->add_control( $prefix . '_tabs_border', array( 'label' => 'رنگ حاشیه تب و پنل', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn, {{WRAPPER}} .aetp-tabs .aetp-tabpanel' => 'border-color: {{VALUE}};' ) ) );
		$widget->add_responsive_control( $prefix . '_tabs_radius', array( 'label' => 'گردی گوشه تب و پنل', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabbtn, {{WRAPPER}} .aetp-tabs .aetp-tabpanel' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$widget->add_responsive_control( $prefix . '_tabs_panel_padding', array( 'label' => 'فاصله داخلی پنل', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em', '%' ), 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabpanel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$widget->add_responsive_control( $prefix . '_tabs_gap', array( 'label' => 'فاصله بین تب‌ها', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array( 'px', 'em' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ), 'em' => array( 'min' => 0, 'max' => 2 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tablist' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$widget->add_control( $prefix . '_tabs_nav_border', array( 'label' => 'رنگ حاشیه نوار تب‌ها', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tablist' => 'border-color: {{VALUE}};' ) ) );
		$widget->add_control( $prefix . '_tabs_panel_bg', array( 'label' => 'پس‌زمینه پنل', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-tabs .aetp-tabpanel' => 'background-color: {{VALUE}};' ) ) );
		$widget->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => $prefix . '_tabs_panel_shadow', 'label' => 'سایه پنل', 'selector' => '{{WRAPPER}} .aetp-tabs .aetp-tabpanel' ) );
		$widget->end_controls_section();
	}
}
