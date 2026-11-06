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
		Artiey_AET_Tabs::register_behavior_controls( $this, 'profile_layout', 'tabs', self::tab_labels() );
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
				'options'     => Artiey_AET_Gallery::SECTION_LABELS,
				'default'     => Artiey_AET_Gallery::ALL_SECTIONS,
				'description' => 'بخش‌های بدون اطلاعات به‌صورت خودکار پنهان می‌شوند؛ بنابراین می‌توانید همه را فعال نگه دارید.',
			)
		);
		$this->add_control( 'header_show_follow', array( 'label' => 'دکمه دنبال کردن در سربرگ', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );

		$this->end_controls_section();

		$this->start_controls_section( 'gallery_content_statement', array( 'label' => 'بیانیه، منتقدان و رسانه', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'statement_style', array( 'label' => 'سبک بیانیه', 'type' => Controls_Manager::SELECT, 'default' => 'quote', 'options' => array( 'quote' => 'نقل‌قول برجسته', 'highlight' => 'کادر رنگی', 'plain' => 'متن ساده' ) ) );
		$this->add_control( 'statement_heading', array( 'label' => 'عنوان بخش بیانیه', 'type' => Controls_Manager::TEXT, 'placeholder' => 'بیانیه گالری', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'critics_layout', array( 'label' => 'چیدمان نقدها', 'type' => Controls_Manager::SELECT, 'default' => 'cards', 'options' => array( 'cards' => 'کارت‌های شبکه‌ای', 'quotes' => 'نقل‌قول‌های پشت سر هم', 'slider' => 'اسلایدر لمسی' ) ) );
		$this->add_control( 'critics_heading', array( 'label' => 'عنوان بخش منتقدان', 'type' => Controls_Manager::TEXT, 'placeholder' => 'نقد و نظر منتقدان', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'critics_limit', array( 'label' => 'حداکثر تعداد نقد', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => Artiey_AET_Gallery::MAX_CRITICS, 'default' => 6 ) );
		$this->add_control( 'critics_words', array( 'label' => 'تعداد کلمات پیش از «متن کامل»', 'type' => Controls_Manager::NUMBER, 'min' => 15, 'max' => 400, 'default' => 70 ) );
		$this->add_control( 'press_limit', array( 'label' => 'حداکثر مطالب رسانه‌ای', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => Artiey_AET_Gallery::MAX_PRESS, 'default' => 10 ) );
		$this->add_control( 'followers_avatars', array( 'label' => 'تصویر دنبال‌کنندگان (تعداد)', 'type' => Controls_Manager::NUMBER, 'min' => 0, 'max' => 48, 'default' => 10 ) );
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

		$this->start_controls_section( 'style_statement', array( 'label' => 'بیانیه و نقدها', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'statement_bg', array( 'label' => 'پس‌زمینه بیانیه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-statement-text' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'statement_color', array( 'label' => 'رنگ متن بیانیه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-statement-text' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'statement_typo', 'label' => 'تایپوگرافی بیانیه', 'selector' => '{{WRAPPER}} .aetp-statement-text' ) );
		$this->add_control( 'critic_bg', array( 'label' => 'پس‌زمینه کارت نقد', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-critic' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'critic_color', array( 'label' => 'رنگ متن نقد', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-critic-text' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'critic_typo', 'label' => 'تایپوگرافی نقد', 'selector' => '{{WRAPPER}} .aetp-critic-text' ) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'critic_border', 'selector' => '{{WRAPPER}} .aetp-critic' ) );
		$this->add_responsive_control( 'critic_radius', array( 'label' => 'گردی گوشه کارت نقد', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-critic' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'critic_columns', array( 'label' => 'حداقل عرض کارت نقد', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 180, 'max' => 600 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-critics-list' => 'grid-template-columns: repeat(auto-fill, minmax({{SIZE}}{{UNIT}}, 1fr));' ) ) );
		$this->add_control( 'critic_accent', array( 'label' => 'رنگ تأکیدی (گیومه، سال جوایز)', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp' => '--aetp-accent: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/** Tab labels: short versions of the section names. */
	public static function tab_labels() {
		return array(
			'header' => 'سربرگ', 'short_bio' => 'بیوگرافی کوتاه', 'statement' => 'بیانیه', 'bio' => 'بیوگرافی کامل', 'critics' => 'منتقدان',
			'details' => 'مشخصات', 'awards' => 'جوایز', 'team' => 'تیم', 'contact' => 'راه‌های ارتباطی', 'hours' => 'ساعات کاری',
			'map' => 'آدرس و نقشه', 'photos' => 'تصاویر گالری', 'press' => 'رسانه‌ها', 'follow' => 'دنبال‌کنندگان', 'artists' => 'هنرمندان مرتبط',
			'curators' => 'کیوریتورها', 'collectors' => 'مجموعه‌داران عمومی', 'auctions' => 'حراج‌ها', 'calls' => 'فراخوان‌ها',
			'exhibitions' => 'نمایشگاه‌های جاری', 'past_exhibitions' => 'نمایشگاه‌های گذشته',
		);
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
