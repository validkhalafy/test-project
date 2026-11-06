<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Artiey_AET_Elementor_Widget extends Widget_Base {

	public function get_name() {
		return 'artiey_exhibition_timeline';
	}

	public function get_title() {
		return 'تایم‌لاین تقویمی نمایشگاه‌ها (روزبه‌روز و متصل به گالری)';
	}

	public function get_icon() {
		return 'eicon-calendar';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'نمایشگاه', 'گالری', 'تقویم', 'exhibition', 'gallery', 'timeline' );
	}

	public function get_style_depends() {
		return array( 'artiey-aet' );
	}

	public function get_script_depends() {
		return array( 'artiey-aet' );
	}

	private function gallery_options() {
		return Artiey_AET_Elementor_Options::posts( 'artiey_gallery' );
	}

	protected function register_controls() {

		/* ---------------- Content ---------------- */
		$this->start_controls_section(
			'section_content',
			array(
				'label' => 'عنوان و معرفی تقویم',
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'global_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'این ویجت تقویم روزبه‌روز نمایشگاه‌هاست. برای اتصال گالری، ابتدا هر نمایشگاه را ویرایش کنید و در فیلد «گالری برگزارکننده» یک گالری انتخاب و ذخیره کنید؛ سپس در کنترل «فقط این گالری‌ها» در صورت نیاز فهرست را محدود کنید. رویداد فقط وقتی دیده می‌شود که منتشر شده باشد و تاریخش با بازه روزهای تایم‌لاین هم‌پوشانی داشته باشد.',
				'content_classes' => 'elementor-descriptor',
			)
		);

		// Editable widget heading. Empty values intentionally keep older layouts unchanged.
		$this->add_control(
			'heading_title',
			array(
				'label'       => 'عنوان اصلی تایم‌لاین',
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => 'مثلاً تقویم نمایشگاه‌های هنری',
				'dynamic'     => array( 'active' => true ),
				'description' => 'این عنوان بالای کل تایم‌لاین نمایش داده می‌شود؛ خالی باشد، نمایش داده نمی‌شود.',
			)
		);
		$this->add_control(
			'heading_subtitle',
			array(
				'label'       => 'زیرعنوان اصلی تایم‌لاین',
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'default'     => '',
				'placeholder' => 'توضیح کوتاه درباره بازه یا رویدادهای نمایش‌داده‌شده',
				'dynamic'     => array( 'active' => true ),
				'description' => 'زیر عنوان اصلی قرار می‌گیرد و مستقل از زیرعنوان هر نمایشگاه است.',
			)
		);
		$this->add_control(
			'heading_tag',
			array(
				'label'   => 'تگ عنوان اصلی',
				'type'    => Controls_Manager::SELECT,
				'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ),
				'default' => 'h2',
			)
		);

		$this->add_control(
			'sub_fields_mode',
			array(
				'label'       => 'منبع زیرعنوان هر نمایشگاه',
				'type'        => Controls_Manager::SELECT,
				'options'     => array( 'global' => 'استفاده از تنظیمات کلی تایم‌لاین', 'custom' => 'شخصی‌سازی برای همین ویجت' ),
				'default'     => 'global',
				'description' => 'عنوان هر نوار از عنوان خودِ نمایشگاه می‌آید. این گزینه اجزای زیرعنوان (نوع، هنرمند و گالری) را برای همین ویجت تعیین می‌کند.',
			)
		);
		$this->add_control(
			'sub_fields',
			array(
				'label'       => 'اجزای زیرعنوان هر نمایشگاه',
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => array( 'type' => 'نوع نمایشگاه', 'artist' => 'هنرمند', 'gallery' => 'گالری' ),
				'default'     => array( 'type', 'artist', 'gallery' ),
				'condition'   => array( 'sub_fields_mode' => 'custom' ),
			)
		);
		$this->add_control(
			'sub_sep',
			array(
				'label'       => 'جداکننده اجزای زیرعنوان',
				'type'        => Controls_Manager::TEXT,
				'default'     => ' — ',
				'placeholder' => ' — ',
				'condition'   => array( 'sub_fields_mode' => 'custom' ),
			)
		);
		$this->add_control(
			'item_title_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'برای تغییر نام یک نمایشگاه، خودِ نمایشگاه را از منوی «نمایشگاه‌ها» ویرایش کنید. عنوان‌های این ویجت، عنوان کلی بالای تایم‌لاین و تنظیمات نمایش آن هستند.',
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section( 'calendar_range_settings', array( 'label' => 'بازه و تاریخ تقویم', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control(
			'days',
			array(
				'label'       => 'تعداد روزهای نمایش‌داده‌شده',
				'type'        => Controls_Manager::NUMBER,
				'min'         => 3,
				'max'         => 31,
				'placeholder' => 'پیش‌فرض',
			)
		);

		$this->add_control(
			'start_mode',
			array(
				'label'   => 'شروع بازه از',
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'today'  => 'امروز (خودکار)',
					'custom' => 'تاریخ ثابت (شمسی)',
				),
				'default' => 'today',
			)
		);

		$this->add_control(
			'before_days',
			array(
				'label'       => 'چند روز قبل از امروز شروع شود؟',
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 60,
				'placeholder' => 'پیش‌فرض',
				'description' => 'مثلاً ۶ یعنی بازه از ۶ روز پیش شروع می‌شود و امروز در میان جدول دیده می‌شود.',
				'condition'   => array( 'start_mode' => 'today' ),
			)
		);

		$this->add_control(
			'custom_start',
			array(
				'label'       => 'تاریخ شروع (مثلاً 1405/08/01)',
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'condition'   => array( 'start_mode' => 'custom' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section( 'calendar_filter_settings', array( 'label' => 'انتخاب گالری‌ها', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control(
			'galleries',
			array(
				'label'       => 'فیلتر بر اساس گالری (خالی = همه)',
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->gallery_options(),
				'label_block' => true,
				'description' => 'این فیلتر، نمایشگاه‌های متصل به گالری انتخاب‌شده را نشان می‌دهد. اتصال اصلی را در «نمایشگاه‌ها ← ویرایش نمایشگاه ← گالری برگزارکننده» بسازید.',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section( 'calendar_display_settings', array( 'label' => 'نمایش و رفتار تقویم', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control(
			'show_gallery_name',
			array(
				'label' => 'نمایش نام گالری زیر لوگو',
				'type' => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default' => '',
				'description' => 'در صورت فعال‌سازی، نام گالری زیر لوگو هم نمایش داده می‌شود تا هویت گالری بدون نیاز به تشخیص لوگو مشخص باشد.',
			)
		);

		$this->add_control(
			'max_rows',
			array(
				'label'   => 'حداکثر تعداد ردیف',
				'type'    => Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 50,
				'placeholder' => 'پیش‌فرض',
			)
		);

		$this->add_control(
			'show_nav',
			array(
				'label'   => 'دکمه‌های قبل / بعد',
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					''    => 'پیش‌فرض (از تنظیمات)',
					'yes' => 'نمایش',
					'no'  => 'مخفی',
				),
				'default' => '',
			)
		);

		$this->add_control(
			'more_text',
			array(
				'label'       => 'متن دکمه پایین',
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'placeholder' => 'پیش‌فرض',
			)
		);

		$this->add_control(
			'more_url',
			array(
				'label'       => 'لینک دکمه پایین (خالی = بدون دکمه)',
				'type'        => Controls_Manager::URL,
				'dynamic'     => array( 'active' => true ),
				'placeholder' => 'https://',
			)
		);

		$yn = array(
			''    => 'پیش‌فرض (از تنظیمات)',
			'yes' => 'روشن',
			'no'  => 'خاموش',
		);
		$this->add_control( 'show_range', array( 'label' => 'نمایش بازهٔ تاریخ بالای جدول', 'type' => Controls_Manager::SELECT, 'options' => $yn, 'default' => '' ) );
		$this->add_control( 'stripes', array( 'label' => 'ستون‌های راه‌راه', 'type' => Controls_Manager::SELECT, 'options' => $yn, 'default' => '' ) );
		$this->add_control( 'highlight_today', array( 'label' => 'برجسته‌کردن ستون امروز', 'type' => Controls_Manager::SELECT, 'options' => $yn, 'default' => '' ) );
		$this->add_control( 'highlight_fri', array( 'label' => 'برجسته‌کردن جمعه‌ها', 'type' => Controls_Manager::SELECT, 'options' => $yn, 'default' => '' ) );
		$this->add_control( 'status_colors', array( 'label' => 'رنگ نوار بر اساس وضعیت (جاری / آینده / گذشته)', 'type' => Controls_Manager::SELECT, 'options' => $yn, 'default' => '' ) );

		$this->end_controls_section();

		/* ---------------- Style ---------------- */
		$this->start_controls_section(
			'section_style',
			array(
				'label' => 'ظاهر',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array( 'name' => 'widget_heading_typography', 'label' => 'تایپوگرافی عنوان اصلی', 'selector' => '{{WRAPPER}} .aet-widget-title' )
		);
		$this->add_control(
			'widget_heading_color',
			array( 'label' => 'رنگ عنوان اصلی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-widget-title' => 'color: {{VALUE}};' ) )
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array( 'name' => 'widget_subtitle_typography', 'label' => 'تایپوگرافی زیرعنوان اصلی', 'selector' => '{{WRAPPER}} .aet-widget-subtitle' )
		);
		$this->add_control(
			'widget_subtitle_color',
			array( 'label' => 'رنگ زیرعنوان اصلی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-widget-subtitle' => 'color: {{VALUE}};' ) )
		);
		$this->add_responsive_control(
			'widget_heading_align',
			array(
				'label' => 'چینش عنوان و زیرعنوان اصلی', 'type' => Controls_Manager::SELECT,
				'options' => array( '' => 'پیش‌فرض', 'right' => 'راست', 'center' => 'وسط', 'left' => 'چپ' ),
				'selectors' => array( '{{WRAPPER}} .aet-widget-heading' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'widget_heading_gap',
			array(
				'label' => 'فاصله عنوان اصلی از تایم‌لاین', 'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ), 'em' => array( 'min' => 0, 'max' => 5 ) ),
				'selectors' => array( '{{WRAPPER}} .aet-widget-heading' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'widget_subtitle_gap',
			array(
				'label' => 'فاصله عنوان تا زیرعنوان', 'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ), 'em' => array( 'min' => 0, 'max' => 3 ) ),
				'selectors' => array( '{{WRAPPER}} .aet-widget-subtitle' => 'margin-top: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'bar_bg',
			array(
				'label'     => 'رنگ نوار نمایشگاه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .aet' => '--aet-bar: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'bar_text',
			array(
				'label'     => 'رنگ متن نوار',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .aet' => '--aet-bar-text: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'today_color',
			array(
				'label'     => 'رنگ نشانگر امروز',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .aet' => '--aet-today: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'col_width',
			array(
				'label'      => 'حداقل عرض هر روز',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 36, 'max' => 140 ) ),
				'selectors'  => array( '{{WRAPPER}} .aet' => '--aet-col: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'row_height',
			array(
				'label'      => 'ارتفاع هر ردیف',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 40, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}} .aet' => '--aet-row: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typo',
				'label'    => 'تایپوگرافی عنوان',
				'selector' => '{{WRAPPER}} .aet-title',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'sub_typo',
				'label'    => 'تایپوگرافی زیرعنوان',
				'selector' => '{{WRAPPER}} .aet-sub',
			)
		);

		$this->add_control( 'title_color', array( 'label' => 'رنگ عنوان نمایشگاه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet .aet-title' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'sub_color', array( 'label' => 'رنگ زیرعنوان', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet .aet-sub' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control(
			'bar_align',
			array(
				'label'     => 'چینش متن داخل نوار',
				'type'      => Controls_Manager::SELECT,
				'options'   => array( '' => 'پیش‌فرض (وسط)', 'flex-start' => 'راست', 'center' => 'وسط', 'flex-end' => 'چپ' ),
				'selectors' => array( '{{WRAPPER}} .aet .aet-bar' => 'align-items: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'title_wrap',
			array(
				'label'     => 'نمایش کامل عنوان‌های بلند',
				'type'      => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => 'به‌جای بریدن عنوان با «…»، متن در چند خط نوشته می‌شود (ارتفاع ردیف را هم افزایش دهید).',
				'selectors' => array( '{{WRAPPER}} .aet .aet-title, {{WRAPPER}} .aet .aet-sub' => 'white-space: normal; overflow: visible; text-overflow: clip;' ),
			)
		);
		$this->add_control(
			'sub_visibility',
			array(
				'label'     => 'زیرعنوان',
				'type'      => Controls_Manager::SELECT,
				'options'   => array( '' => 'نمایش', 'none' => 'مخفی' ),
				'selectors' => array( '{{WRAPPER}} .aet .aet-sub' => 'display: {{VALUE}};' ),
			)
		);
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => 'bar_shadow', 'label' => 'سایهٔ نوار نمایشگاه', 'selector' => '{{WRAPPER}} .aet .aet-bar' ) );
		$this->add_control( 'bar_hover_filter', array( 'label' => 'روشنایی نوار هنگام اشاره', 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0.7, 'max' => 1.5, 'step' => 0.05 ) ), 'selectors' => array( '{{WRAPPER}} .aet .aet-bar:hover, {{WRAPPER}} .aet .aet-bar:focus' => 'filter: brightness({{SIZE}});' ) ) );
		$this->end_controls_section();

		/* ---------------- Gallery column: width, names and typography ---------------- */
		$this->start_controls_section(
			'section_gallery_column',
			array( 'label' => 'ستون گالری: عرض، فونت و چیدمان', 'tab' => Controls_Manager::TAB_STYLE )
		);
		$this->add_control( 'gallery_column_help', array(
			'type' => Controls_Manager::RAW_HTML,
			'raw' => 'این تنظیمات فقط ستون اول جدول، عنوان «گالری» و نام/لوگوی گالری‌ها را کنترل می‌کند. عرض ستون برای هر دستگاه جداگانه قابل تنظیم است.',
			'content_classes' => 'elementor-descriptor',
		) );
		$this->add_responsive_control( 'logo_col', array(
			'label' => 'عرض ستون گالری (ستون اول جدول)', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ),
			'range' => array( 'px' => array( 'min' => 50, 'max' => 280 ) ),
			'description' => 'عرض ستون نام/لوگوی گالری را مستقل از عرض روزها تعیین کنید. برای دسکتاپ، تبلت و موبایل مقدار جداگانه تنظیم‌پذیر است.',
			'selectors' => array( '{{WRAPPER}} .aet' => '--aet-gallery-col-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'logo_h', array( 'label' => 'حداکثر ارتفاع لوگوی گالری', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 20, 'max' => 120 ) ), 'selectors' => array( '{{WRAPPER}} .aet' => '--aet-logo-h: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name' => 'gallery_header_typography', 'label' => 'فونت و اندازه عنوان ستون («گالری»)', 'selector' => '{{WRAPPER}} .aet .aet-corner',
		) );
		$this->add_control( 'gallery_header_color', array( 'label' => 'رنگ عنوان ستون گالری', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet .aet-corner' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'gallery_header_background', array( 'label' => 'پس‌زمینه عنوان ستون گالری', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet .aet-corner' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'gallery_cell_background', array( 'label' => 'پس‌زمینه خانه‌های گالری', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet .aet-logocell' => 'background-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name' => 'logo_text_typo', 'label' => 'نوع فونت و اندازه نام گالری (واکنش‌گرا)', 'selector' => '{{WRAPPER}} .aet .aet-logo-text',
		) );
		$this->add_control( 'gallery_name_color', array( 'label' => 'رنگ نام گالری', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet .aet-logo-text' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'gallery_cell_padding', array(
			'label' => 'فاصله داخلی خانه‌های ستون گالری', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ),
			'selectors' => array( '{{WRAPPER}} .aet .aet-logocell' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'gallery_logo_width', array(
			'label' => 'حداکثر عرض لوگوی گالری', 'type' => Controls_Manager::SLIDER, 'size_units' => array( '%', 'px' ),
			'range' => array( '%' => array( 'min' => 25, 'max' => 100 ), 'px' => array( 'min' => 20, 'max' => 180 ) ),
			'selectors' => array( '{{WRAPPER}} .aet .aet-logo' => 'max-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'gallery_cell_alignment', array(
			'label' => 'جایگاه لوگو/نام در خانه گالری', 'type' => Controls_Manager::SELECT,
			'options' => array( 'center' => 'وسط', 'flex-start' => 'ابتدای خانه', 'flex-end' => 'انتهای خانه' ), 'default' => 'center',
			'selectors' => array( '{{WRAPPER}} .aet .aet-logocell' => 'align-items: {{VALUE}};', '{{WRAPPER}} .aet .aet-logocell a' => 'align-items: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'gallery_name_alignment', array(
			'label' => 'تراز نوشتاری نام گالری', 'type' => Controls_Manager::SELECT,
			'options' => array( 'center' => 'وسط', 'right' => 'راست', 'left' => 'چپ' ), 'default' => 'center',
			'selectors' => array( '{{WRAPPER}} .aet .aet-logo-text' => 'text-align: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* ---------------- Style: grid, colours, scheme ---------------- */
		$this->start_controls_section(
			'section_style_grid',
			array(
				'label' => 'جدول و رنگ‌ها',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'color_scheme',
			array(
				'label'        => 'طرح رنگی',
				'type'         => Controls_Manager::SELECT,
				'options'      => array( '' => 'روشن', 'dark' => 'تیره', 'auto' => 'خودکار (مطابق دستگاه بازدیدکننده)' ),
				'default'      => '',
				'prefix_class' => 'aet-scheme-',
			)
		);
		foreach ( array(
			'head_bg'     => array( 'پس‌زمینهٔ ردیف تاریخ', '--aet-head-bg' ),
			'head_alt'    => array( 'پس‌زمینهٔ ستون‌های راه‌راه تاریخ', '--aet-head-alt' ),
			'grid_bg'     => array( 'پس‌زمینهٔ جدول', '--aet-bg' ),
			'grid_bg_alt' => array( 'پس‌زمینهٔ ستون‌های راه‌راه', '--aet-bg-alt' ),
			'grid_bg_fri' => array( 'پس‌زمینهٔ جمعه‌ها', '--aet-bg-fri' ),
			'fri_text'    => array( 'رنگ نوشتهٔ جمعه', '--aet-fri-text' ),
			'st_now'      => array( 'رنگ «در حال برگزاری»', '--aet-now' ),
			'st_upcoming' => array( 'رنگ «به‌زودی»', '--aet-upcoming' ),
			'st_past'     => array( 'رنگ «برگزارشده»', '--aet-past' ),
		) as $id => $def ) {
			$this->add_control( $id, array( 'label' => $def[0], 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet' => $def[1] . ': {{VALUE}};' ) ) );
		}
		$this->add_responsive_control( 'bar_radius', array( 'label' => 'گردی گوشهٔ نوارها و جدول', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 24 ) ), 'selectors' => array( '{{WRAPPER}} .aet' => '--aet-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'bar_gap', array( 'label' => 'فاصلهٔ نوارها از لبهٔ خانه', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 16 ) ), 'selectors' => array( '{{WRAPPER}} .aet' => '--aet-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'head_typo', 'label' => 'تایپوگرافی تاریخ‌ها', 'selector' => '{{WRAPPER}} .aet-head' ) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => 'grid_shadow', 'selector' => '{{WRAPPER}} .aet' ) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'grid_border', 'selector' => '{{WRAPPER}} .aet-scroll' ) );
		$this->end_controls_section();

		/* ---------------- Navigation arrows ---------------- */
		$this->start_controls_section(
			'section_nav_arrows',
			array( 'label' => 'فلش‌های ناوبری', 'tab' => Controls_Manager::TAB_STYLE )
		);
		$this->add_control( 'nav_arrow_glyph', array(
			'label' => 'طرح فلش', 'type' => Controls_Manager::SELECT, 'default' => 'chevron', 'prefix_class' => 'aet-nav-glyph-',
			'options' => array( 'chevron' => 'چورون', 'arrow' => 'فلش کلاسیک', 'double' => 'دوگانه', 'long' => 'فلش بلند', 'triangle' => 'مثلث', 'caret' => 'باریک', 'minimal' => 'مینیمال', 'none' => 'بدون فلش' ),
		) );
		$this->add_control( 'nav_arrow_shape', array(
			'label' => 'قاب فلش', 'type' => Controls_Manager::SELECT, 'default' => 'plain', 'prefix_class' => 'aet-nav-shape-',
			'options' => array( 'plain' => 'بدون قاب', 'outline' => 'قاب خطی', 'circle' => 'دایره خطی', 'filled-circle' => 'دایره توپر', 'rounded-square' => 'مربع گرد', 'soft-square' => 'مربع ملایم', 'glass' => 'شیشه‌ای', 'pill' => 'کپسولی' ),
		) );
		$this->add_control( 'nav_arrow_motion', array(
			'label' => 'حرکت هنگام اشاره', 'type' => Controls_Manager::SELECT, 'default' => 'lift', 'prefix_class' => 'aet-nav-motion-',
			'options' => array( 'none' => 'بدون حرکت', 'lift' => 'حرکت کوتاه', 'scale' => 'بزرگ‌نمایی', 'slide' => 'لغزش جهت‌دار', 'rotate' => 'چرخش ظریف' ),
		) );
		$this->add_control( 'nav_label_mode', array(
			'label' => 'متن دکمه‌ها', 'type' => Controls_Manager::SELECT, 'default' => 'show', 'prefix_class' => 'aet-nav-labels-',
			'options' => array( 'show' => 'فلش همراه با متن «قبل/بعد»', 'icons' => 'فقط فلش (متن برای دسترس‌پذیری حفظ می‌شود)' ),
		) );
		$this->add_control( 'nav_text_color', array( 'label' => 'رنگ متن دکمه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-nav-btn' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'nav_button_background', array( 'label' => 'پس‌زمینه دکمه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-nav-btn' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'nav_button_border_color', array( 'label' => 'رنگ حاشیه دکمه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-nav-btn' => 'border-color: {{VALUE}};' ) ) );
		$this->add_control( 'nav_button_hover_color', array( 'label' => 'رنگ متن هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-nav-btn:hover, {{WRAPPER}} .aet-nav-btn:focus-visible' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'nav_button_hover_background', array( 'label' => 'پس‌زمینه هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-nav-btn:hover, {{WRAPPER}} .aet-nav-btn:focus-visible' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'nav_icon_color', array( 'label' => 'رنگ فلش', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-nav-icon' => '--aet-nav-icon-color: {{VALUE}}; color: {{VALUE}};' ) ) );
		$this->add_control( 'nav_icon_background', array( 'label' => 'پس‌زمینه قاب فلش', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-nav-icon' => '--aet-nav-icon-bg: {{VALUE}}; background-color: {{VALUE}};' ) ) );
		$this->add_control( 'nav_icon_hover_color', array( 'label' => 'رنگ فلش هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-nav-btn:hover .aet-nav-icon, {{WRAPPER}} .aet-nav-btn:focus-visible .aet-nav-icon' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'nav_icon_hover_background', array( 'label' => 'پس‌زمینه فلش هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-nav-btn:hover .aet-nav-icon, {{WRAPPER}} .aet-nav-btn:focus-visible .aet-nav-icon' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'nav_icon_box_size', array( 'label' => 'اندازه قاب فلش', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', 'em' ), 'range' => array( 'px' => array( 'min' => 16, 'max' => 64 ), 'em' => array( 'min' => 1, 'max' => 4 ) ), 'selectors' => array( '{{WRAPPER}} .aet-nav-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'nav_icon_glyph_size', array( 'label' => 'اندازه خود فلش', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', 'em' ), 'range' => array( 'px' => array( 'min' => 8, 'max' => 48 ), 'em' => array( 'min' => .5, 'max' => 2.5 ) ), 'selectors' => array( '{{WRAPPER}} .aet-nav-icon:before' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'nav_button_radius', array( 'label' => 'گردی گوشه دکمه', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ), '%' => array( 'min' => 0, 'max' => 50 ) ), 'selectors' => array( '{{WRAPPER}} .aet-nav-btn' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'nav_button_gap', array( 'label' => 'فاصله فلش از متن', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', 'em' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ), 'em' => array( 'min' => 0, 'max' => 2 ) ), 'selectors' => array( '{{WRAPPER}} .aet-nav-btn' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'nav_button_padding', array( 'label' => 'فاصله داخلی دکمه', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .aet-nav-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'nav_button_border', 'label' => 'حاشیه پیشرفته', 'selector' => '{{WRAPPER}} .aet-nav-btn' ) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => 'nav_button_shadow', 'label' => 'سایه دکمه', 'selector' => '{{WRAPPER}} .aet-nav-btn' ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$custom_subtitle = isset( $s['sub_fields_mode'] ) && 'custom' === $s['sub_fields_mode'];
		$args = array(
			'show_gallery_name' => ! empty( $s['show_gallery_name'] ) && 'yes' === $s['show_gallery_name'] ? 'yes' : 'no',
			'sub_fields_mode' => $custom_subtitle ? 'custom' : 'global',
			'sub_fields'      => $custom_subtitle && isset( $s['sub_fields'] ) ? (array) $s['sub_fields'] : array(),
			'sub_sep'         => $custom_subtitle && isset( $s['sub_sep'] ) ? $s['sub_sep'] : '',
			'days'         => isset( $s['days'] ) ? $s['days'] : '',
			'start_mode'   => isset( $s['start_mode'] ) ? $s['start_mode'] : 'today',
			'before_days'  => isset( $s['before_days'] ) ? $s['before_days'] : '',
			'custom_start' => isset( $s['custom_start'] ) ? $s['custom_start'] : '',
			'galleries'    => ! empty( $s['galleries'] ) ? implode( ',', (array) $s['galleries'] ) : '',
			'max_rows'     => isset( $s['max_rows'] ) ? $s['max_rows'] : '',
			'show_nav'     => isset( $s['show_nav'] ) ? $s['show_nav'] : '',
			'more_text'    => isset( $s['more_text'] ) ? $s['more_text'] : '',
			'more_url'     => ! empty( $s['more_url']['url'] ) ? $s['more_url']['url'] : '',
			'show_range'      => isset( $s['show_range'] ) ? $s['show_range'] : '',
			'stripes'         => isset( $s['stripes'] ) ? $s['stripes'] : '',
			'highlight_today' => isset( $s['highlight_today'] ) ? $s['highlight_today'] : '',
			'highlight_fri'   => isset( $s['highlight_fri'] ) ? $s['highlight_fri'] : '',
			'status_colors'   => isset( $s['status_colors'] ) ? $s['status_colors'] : '',
		);

		$heading_title = isset( $s['heading_title'] ) && is_scalar( $s['heading_title'] ) ? trim( (string) $s['heading_title'] ) : '';
		$heading_subtitle = isset( $s['heading_subtitle'] ) && is_scalar( $s['heading_subtitle'] ) ? trim( (string) $s['heading_subtitle'] ) : '';
		// Older saved Elementor instances used different setting keys. Only fall back when the new fields are empty.
		if ( '' === $heading_title ) {
			foreach ( array( 'widget_title', 'heading', 'title' ) as $legacy_key ) {
				if ( isset( $s[ $legacy_key ] ) && is_scalar( $s[ $legacy_key ] ) && '' !== trim( (string) $s[ $legacy_key ] ) ) {
					$heading_title = trim( (string) $s[ $legacy_key ] );
					break;
				}
			}
		}
		if ( '' === $heading_subtitle ) {
			foreach ( array( 'widget_subtitle', 'intro_subtitle', 'subtitle' ) as $legacy_key ) {
				if ( isset( $s[ $legacy_key ] ) && is_scalar( $s[ $legacy_key ] ) && '' !== trim( (string) $s[ $legacy_key ] ) ) {
					$heading_subtitle = trim( (string) $s[ $legacy_key ] );
					break;
				}
			}
		}
		if ( '' !== $heading_title || '' !== $heading_subtitle ) {
			$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'div' );
			$heading_tag  = isset( $s['heading_tag'] ) && in_array( $s['heading_tag'], $allowed_tags, true ) ? $s['heading_tag'] : 'h2';
			echo '<div class="aet-widget-heading" dir="rtl">';
			if ( '' !== $heading_title ) {
				echo '<' . $heading_tag . ' class="aet-widget-title">' . esc_html( $heading_title ) . '</' . $heading_tag . '>';
			}
			if ( '' !== $heading_subtitle ) {
				echo '<p class="aet-widget-subtitle">' . esc_html( $heading_subtitle ) . '</p>';
			}
			echo '</div>';
		}

		echo Artiey_AET_Renderer::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside renderer.
	}
}
