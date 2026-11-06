<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/** Preserve the current Art COA route's original relational content inside custom Elementor layouts. */
final class Artiey_AET_Elementor_Route_Content_Widget extends Widget_Base {
	public function get_name() { return 'artiey_route_content'; }
	public function get_title() { return 'محتوای صفحهٔ جاری Artiey'; }
	public function get_icon() { return 'eicon-single-post'; }
	public function get_categories() { return array( 'artiey-art', 'art-coa' ); }
	public function get_keywords() { return array( 'محتوای صفحه', 'پروفایل جاری', 'آرتی', 'COA', 'route content', 'current profile' ); }

	protected function register_controls() {
		$this->start_controls_section( 'route_content_settings', array( 'label' => 'محتوای صفحهٔ جاری', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'info', array(
			'type' => Controls_Manager::RAW_HTML,
			'raw' => '<p>این ویجت اطلاعات اصلی صفحهٔ جاری Art COA را در قالب سفارشی شما قرار می‌دهد؛ از جمله اطلاعات پروفایل، آثار، روابط و سوابقی که renderer اصلی تولید می‌کند.</p>',
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$content = Artiey_AET_Elementor_Layouts::current_route_content();
		if ( '' === $content ) {
			if ( \Artiey_AET_Elementor_Options::is_elementor_preview() ) {
				echo '<div class="art-coa-empty">این ویجت فقط در پیش‌نمایش صفحات اختصاصی Artiey/COA محتوای زنده دریافت می‌کند.</div>';
			}
			return;
		}
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by the trusted, privacy-filtered Art COA profile renderer.
	}
}
