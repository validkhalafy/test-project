<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class Artiey_AET_Elementor_Profile_Widget extends Widget_Base {
	use Artiey_AET_Elementor_Style;

	public function get_name() {
		return 'artiey_gallery_profile';
	}

	public function get_title() {
		return 'پروفایل گالری';
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'گالری', 'پروفایل', 'هنرمند', 'کیوریتور', 'مجموعه‌دار', 'حراج', 'gallery', 'profile', 'artist', 'curator', 'collector', 'auction' );
	}

	public function get_style_depends() {
		return array( 'artiey-aet-profile' );
	}

	public function get_script_depends() {
		return array( 'artiey-aet-profile' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => 'منبع و چیدمان گالری', 'tab' => Controls_Manager::TAB_CONTENT ) );

		$this->add_control( 'profile_layout', array( 'label' => 'چیدمان بخش‌ها', 'type' => Controls_Manager::SELECT, 'options' => array( 'stacked' => 'پشت سر هم', 'tabs' => 'تب‌دار (هر بخش در تب مستقل)', ), 'default' => 'stacked', 'description' => 'در حالت تب‌دار، هر بخش فعال و دارای اطلاعات در تب مستقل قرار می‌گیرد.' ) );
		Artiey_AET_Tabs::register_behavior_controls( $this, 'profile_layout', 'tabs', array( 'header' => 'سربرگ', 'short_bio' => 'بیوگرافی کوتاه', 'bio' => 'بیوگرافی کامل', 'details' => 'مشخصات', 'contact' => 'راه‌های ارتباطی', 'hours' => 'ساعات کاری', 'map' => 'آدرس و نقشه', 'photos' => 'تصاویر گالری', 'artists' => 'هنرمندان مرتبط', 'curators' => 'کیوریتورها', 'collectors' => 'مجموعه‌داران عمومی', 'auctions' => 'حراج‌ها', 'exhibitions' => 'نمایشگاه‌های جاری', 'past_exhibitions' => 'نمایشگاه‌های گذشته' ) );
		$this->aet_source_control( 'gallery', 'artiey_gallery', 'گالری', 'گالری جاری (در قالب تک‌گالری)' );
		$this->add_control( 'gallery_header_style', array( 'label' => 'سبک سربرگ گالری', 'type' => Controls_Manager::SELECT, 'options' => array( 'classic' => 'کلاسیک', 'editorial' => 'مجله‌ای', 'centered' => 'متمرکز', 'compact' => 'فشرده' ), 'default' => 'classic', 'prefix_class' => 'aetp-gallery-header-' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'gallery_content_sections', array( 'label' => 'بخش‌های پروفایل', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control(
			'sections',
			array(
				'label'       => 'بخش‌ها',
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => array(
					'header'      => 'سربرگ (کاور، لوگو، نام‌ها، وضعیت)',
					'short_bio'   => 'بیوگرافی کوتاه',
					'bio'         => 'بیوگرافی کامل',
					'details'     => 'مشخصات',
					'contact'     => 'راه‌های ارتباطی',
					'hours'       => 'ساعات کاری',
					'map'         => 'آدرس و نقشه',
					'photos'      => 'تصاویر گالری',
					'artists'     => 'هنرمندان مرتبط',
					'curators'     => 'کیوریتورهای همکار',
					'collectors'   => 'مجموعه‌داران عمومی مرتبط',
					'auctions'     => 'حراج‌ها و لات‌های مرتبط',
					'exhibitions' => 'نمایشگاه‌های جاری و پیش‌رو',
					'past_exhibitions' => 'پوستر نمایشگاه‌های برگزارشده',
				),
				'default'     => array( 'header', 'short_bio', 'bio', 'details', 'contact', 'hours', 'map', 'photos', 'artists', 'curators', 'collectors', 'auctions', 'exhibitions', 'past_exhibitions' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section( 'gallery_content_relationships', array( 'label' => 'ارتباطات اکوسیستم هنری', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control(
			'related_items_limit',
			array(
				'label'       => 'تعداد کارت‌های هر ارتباط',
				'type'        => Controls_Manager::NUMBER,
				'min'         => 2,
				'max'         => 24,
				'step'        => 1,
				'default'     => 8,
				'description' => 'برای هنرمندان، کیوریتورها، مجموعه‌داران و حراج‌ها؛ فقط رکوردهای مرتبط و مجاز نمایش داده می‌شوند.',
			)
		);
		$this->add_control(
			'related_card_layout',
			array(
				'label'   => 'چیدمان کارت‌های ارتباطی',
				'type'    => Controls_Manager::SELECT,
				'options' => array( 'grid' => 'شبکه‌ای حرفه‌ای', 'compact' => 'فشرده و افقی' ),
				'default' => 'grid',
			)
		);
		$this->add_control(
			'show_related_meta',
			array(
				'label'        => 'نمایش جزئیات کارت‌های ارتباطی',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section( 'gallery_content_display', array( 'label' => 'عنوان و پیام جایگزین', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control(
			'show_title',
			array(
				'label'        => 'نمایش نام گالری در سربرگ',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->aet_empty_text_control();
		$this->end_controls_section();

		$this->aet_style_controls( array( 'hero' => true, 'stats' => true, 'map' => true, 'sections' => true, 'tabs' => true ) );
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$id = $this->aet_resolve_post( $s, 'gallery', 'artiey_gallery' );

		if ( ! $id ) {
			$this->aet_empty_message( $s, 'یک گالری انتخاب کنید یا ویجت را داخل قالب تک‌گالری قرار دهید.' );
			return;
		}

		$sections = Artiey_AET_Elementor_Options::sections( $s, 'sections', Artiey_AET_Gallery::ALL_SECTIONS );
		$sections = Artiey_AET_Gallery::upgrade_default_sections( $sections );
		Artiey_AET_Profile_Guard::mark( $id );
		if ( isset( $s['profile_layout'] ) && 'tabs' === $s['profile_layout'] ) {
			$html = $this->aet_render_profile_tabs( $id, 'artiey_gallery', $sections, ! empty( $s['show_title'] ), $s );
			if ( '' !== trim( (string) $html ) ) {
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- profile renderer escapes content.
			} else {
				$this->aet_empty_message( $s, 'برای این گالری در بخش‌های انتخاب‌شده اطلاعاتی ثبت نشده است.' );
			}
			return;
		}
		echo Artiey_AET_Gallery::render_profile( $id, $sections, ! empty( $s['show_title'] ), $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderer.
	}
}
