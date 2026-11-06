<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Widget_Base;

/**
 * Elementor widget: artist profile.
 */
class Artiey_AET_Elementor_Artist_Widget extends Widget_Base {
	use Artiey_AET_Elementor_Style;

	public function get_name() {
		return 'artiey_artist_profile';
	}

	public function get_title() {
		return 'پروفایل هنرمند';
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'هنرمند', 'پروفایل', 'artist', 'profile' );
	}

	public function get_style_depends() {
		return array( 'artiey-aet-profile' );
	}

	public function get_script_depends() {
		return array( 'artiey-aet-profile' );
	}

	/** Elementor advanced conditions: SELECT2 multiple values must use `contains`. */
	private function artist_section_conditions( $sections, $extra = array(), $relation = 'and' ) {
		$terms = array();
		foreach ( (array) $sections as $section ) {
			$terms[] = array( 'name' => 'sections', 'operator' => 'contains', 'value' => sanitize_key( $section ) );
		}
		foreach ( (array) $extra as $name => $value ) {
			$terms[] = array( 'name' => sanitize_key( $name ), 'operator' => '===', 'value' => $value );
		}
		return array( 'relation' => in_array( $relation, array( 'and', 'or' ), true ) ? $relation : 'and', 'terms' => $terms );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => 'منبع و چیدمان', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'profile_layout', array( 'label' => 'چیدمان بخش‌ها', 'type' => Controls_Manager::SELECT, 'options' => array( 'stacked' => 'پشت سر هم', 'tabs' => 'تب‌دار (هر بخش در تب مستقل)', ), 'default' => 'stacked', 'description' => 'در حالت تب‌دار، هر بخش فعال و دارای اطلاعات در تب مستقل قرار می‌گیرد.' ) );
		Artiey_AET_Tabs::register_behavior_controls( $this, 'profile_layout', 'tabs', array( 'header' => 'سربرگ', 'short_bio' => 'معرفی کوتاه', 'statement' => 'بیانیه هنری', 'bio' => 'بیوگرافی', 'details' => 'مشخصات', 'awards' => 'جوایز و افتخارات', 'career_timeline' => 'خط زمانی تحصیلات و جوایز', 'contact' => 'ارتباط با هنرمند', 'works' => 'نمونه آثار', 'galleries' => 'گالری‌های همکار', 'exhibitions' => 'نمایشگاه‌ها', 'activity_chart' => 'نمودار فعالیت', 'sales_chart' => 'نمودار فروش', 'artwork_history' => 'تاریخچه آثار', 'interviews' => 'مصاحبه‌ها و مطالب مرتبط', 'certificate_stats' => 'آمار گواهی اصالت' ) );
		$this->aet_source_control( 'artist', 'artiey_artist', 'هنرمند', 'هنرمند جاری (در قالب تک‌هنرمند)' );
		$this->end_controls_section();

		$this->start_controls_section( 'artist_content_sections', array( 'label' => 'بخش‌ها و سربرگ', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'artist_header_style', array( 'label' => 'سبک سربرگ هنرمند', 'type' => Controls_Manager::SELECT, 'options' => array( 'classic' => 'کلاسیک', 'editorial' => 'مجله‌ای / کیوریتوری', 'centered' => 'متمرکز', 'compact' => 'فشرده' ), 'default' => 'classic', 'prefix_class' => 'aetp-artist-header-', 'conditions' => $this->artist_section_conditions( array( 'header' ) ) ) );
		$this->add_control( 'header_title_tag', array( 'label' => 'تگ عنوان نام هنرمند', 'type' => Controls_Manager::SELECT, 'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ), 'default' => 'h2', 'conditions' => $this->artist_section_conditions( array( 'header' ) ) ) );
		$this->add_control( 'sections', array(
			'label' => 'بخش‌ها', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true,
			'options' => array(
				'header' => 'سربرگ (کاور، آواتار، نام‌ها، شهر، سن و سبک‌ها)',
				'short_bio' => 'معرفی کوتاه', 'statement' => 'بیانیهٔ هنری', 'bio' => 'بیوگرافی کامل با «ادامهٔ بیوگرافی»',
				'details' => 'مشخصات', 'awards' => 'جوایز و افتخارات (فهرست)', 'career_timeline' => 'خط زمانی تحصیلات و جوایز',
				'contact' => 'شبکه‌های ارتباطی', 'works' => 'نمونه آثار (Portfolio)', 'galleries' => 'گالری‌های همکار',
				'exhibitions' => 'تایم‌لاین نمایشگاه‌ها', 'activity_chart' => 'نمودار فعالیت هنرمند',
				'sales_chart' => 'نمودار فروش (فقط مالک/مدیر مجاز)', 'artwork_history' => 'تاریخچه آثار، حراج و مالکیت',
				'interviews' => 'مصاحبه‌ها و مطالب مرتبط (ویدیو پلیر)', 'certificate_stats' => 'گواهی اصالت آثار (آمار و آثار دارای گواهی)',
			),
			'default' => array( 'header', 'short_bio', 'statement', 'bio', 'details', 'awards', 'career_timeline', 'contact', 'works', 'galleries', 'exhibitions', 'activity_chart', 'sales_chart', 'artwork_history', 'interviews', 'certificate_stats' ),
			'description' => 'هر بخش را جداگانه فعال یا غیرفعال کنید. ترتیب رندر بر اساس ساختار پروفایل است؛ برای تغییر ظاهر به زبانهٔ «استایل» بروید. داده‌های فروش تابع سطح دسترسی COA هستند.',
		) );
		$this->add_control( 'show_title', array( 'label' => 'نمایش نام در سربرگ', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'header' ) ) ) );
		$this->add_control( 'header_show_cover', array( 'label' => 'نمایش تصویر کاور', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'header' ) ) ) );
		$this->add_control( 'header_show_avatar', array( 'label' => 'نمایش آواتار / تصویر شاخص', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'header' ) ) ) );
		$this->add_control( 'header_show_name_en', array( 'label' => 'نمایش نام لاتین در سربرگ', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'header' ) ) ) );
		$this->add_control( 'header_show_city', array( 'label' => 'نمایش شهر در سربرگ', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'header' ) ) ) );
		$this->add_control( 'header_show_age', array( 'label' => 'نمایش سن / سال تولد در سربرگ', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'header' ) ) ) );
		$this->add_control( 'header_show_styles', array( 'label' => 'نمایش چیپ‌های سبک هنری', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'header' ) ) ) );
		$this->add_control( 'header_show_follow', array( 'label' => 'دکمه دنبال کردن و تعداد دنبال‌کنندگان', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'header' ) ) ) );
		$this->add_control( 'empty_text', array( 'label' => 'پیام وقتی داده‌ای نیست', 'type' => Controls_Manager::TEXT, 'default' => 'برای این هنرمند در بخش‌های انتخاب‌شده اطلاعاتی ثبت نشده است. هنرمند را انتخاب کنید یا ویجت را داخل قالب تک‌هنرمند قرار دهید.' ) );

		$this->end_controls_section();

		$this->start_controls_section( 'artist_content_bio', array( 'label' => 'بیوگرافی و معرفی', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'bio_readmore', array( 'label' => 'نمایش دکمهٔ «ادامهٔ بیوگرافی»', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'bio' ) ) ) );
		$this->add_control( 'bio_visible_paragraphs', array( 'label' => 'پاراگراف‌های کاملِ نمایان', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 12, 'default' => 2, 'conditions' => $this->artist_section_conditions( array( 'bio' ), array( 'bio_readmore' => 'yes' ) ) ) );
		$this->add_control( 'bio_more_label', array( 'label' => 'متن دکمه (بسته)', 'type' => Controls_Manager::TEXT, 'default' => 'ادامهٔ بیوگرافی', 'conditions' => $this->artist_section_conditions( array( 'bio' ), array( 'bio_readmore' => 'yes' ) ) ) );
		$this->add_control( 'bio_less_label', array( 'label' => 'متن دکمه (باز)', 'type' => Controls_Manager::TEXT, 'default' => 'بستن بیوگرافی', 'conditions' => $this->artist_section_conditions( array( 'bio' ), array( 'bio_readmore' => 'yes' ) ) ) );

		$this->end_controls_section();

		$this->start_controls_section( 'artist_content_charts', array( 'label' => 'نمودارها و اتصال داده', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'chart_months', array( 'label' => 'بازهٔ نمودارها (ماه)', 'type' => Controls_Manager::NUMBER, 'min' => 3, 'max' => 36, 'step' => 1, 'default' => 12, 'description' => 'برای اتصال خودکار به داده‌های داخلی COA؛ مقدار برحسب ماه است.', 'conditions' => $this->artist_section_conditions( array( 'activity_chart', 'sales_chart' ), array(), 'or' ) ) );
		$this->add_control( 'activity_chart_connection', array( 'label' => 'روش اتصال نمودار فعالیت', 'type' => Controls_Manager::SELECT, 'options' => array( 'auto' => 'خودکار از داده‌های آرتی / COA', 'shortcode' => 'شورت‌کد دستی' ), 'default' => 'auto', 'conditions' => $this->artist_section_conditions( array( 'activity_chart' ) ), 'description' => 'اگر اتصال خودکار داده‌ای برنمی‌گرداند، شورت‌کد نمودار را در فیلد بعد وارد کنید.' ) );
		$this->add_control( 'activity_chart_shortcode', array( 'label' => 'شورت‌کد نمودار فعالیت', 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'dynamic' => array( 'active' => true ), 'placeholder' => '[art_coa_charts type="activity" artist="{artist_id}" months="{months}"]', 'conditions' => $this->artist_section_conditions( array( 'activity_chart' ), array( 'activity_chart_connection' => 'shortcode' ) ), 'description' => 'فقط [art_coa_charts] یا [artiey_charts] مجاز است. توکن‌ها: {artist_id}=شناسه هنرمند در COA، {artist_post_id}=شناسه پست وردپرس، {months}=بازه انتخاب‌شده. برای اتصال خودکار از {artist_id} استفاده کنید.' ) );
		$this->add_control( 'sales_chart_connection', array( 'label' => 'روش اتصال نمودار فروش', 'type' => Controls_Manager::SELECT, 'options' => array( 'auto' => 'خودکار از داده‌های آرتی / COA', 'shortcode' => 'شورت‌کد دستی' ), 'default' => 'auto', 'conditions' => $this->artist_section_conditions( array( 'sales_chart' ) ), 'description' => 'نمودار دستی هم تابع محدودیت‌های دسترسی و حریم خصوصی COA می‌ماند.' ) );
		$this->add_control( 'sales_chart_shortcode', array( 'label' => 'شورت‌کد نمودار فروش', 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'dynamic' => array( 'active' => true ), 'placeholder' => '[art_coa_charts type="sales_value" artist="{artist_id}" months="{months}"]', 'conditions' => $this->artist_section_conditions( array( 'sales_chart' ), array( 'sales_chart_connection' => 'shortcode' ) ), 'description' => 'فقط [art_coa_charts] یا [artiey_charts] اجرا می‌شود. توکن‌ها: {artist_id}، {artist_post_id}، {months}. سیاست نمایش قیمت‌های فروش همچنان توسط خود COA کنترل می‌شود.' ) );
		$this->add_control( 'activity_chart_title', array( 'label' => 'عنوان نمودار فعالیت', 'type' => Controls_Manager::TEXT, 'default' => 'نمودار فعالیت هنرمند', 'dynamic' => array( 'active' => true ), 'conditions' => $this->artist_section_conditions( array( 'activity_chart', 'sales_chart' ), array(), 'or' ) ) );
		$this->add_control( 'sales_chart_title', array( 'label' => 'عنوان نمودار فروش', 'type' => Controls_Manager::TEXT, 'default' => 'نمودار فروش هنرمند', 'dynamic' => array( 'active' => true ), 'conditions' => $this->artist_section_conditions( array( 'activity_chart', 'sales_chart' ), array(), 'or' ) ) );
		$this->add_control( 'chart_show_title', array( 'label' => 'نمایش عنوان نمودارها', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'activity_chart', 'sales_chart' ), array(), 'or' ) ) );
		$this->add_control( 'chart_show_legend', array( 'label' => 'نمایش راهنمای نمودارها', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'activity_chart', 'sales_chart' ), array(), 'or' ) ) );
		$this->add_control( 'chart_show_table', array( 'label' => 'نمایش جدول داده‌های نمودار (دسترس‌پذیری)', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'activity_chart', 'sales_chart' ), array(), 'or' ) ) );
		$this->add_control( 'chart_height', array( 'label' => 'ارتفاع نمودار', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 180, 'max' => 520, 'step' => 10 ) ), 'default' => array( 'size' => 280 ), 'conditions' => $this->artist_section_conditions( array( 'activity_chart', 'sales_chart' ), array(), 'or' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'artist_content_portfolio', array( 'label' => 'نمونه آثار', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'works_limit', array( 'label' => 'تعداد آثار کاتالوگ در پروفایل هنرمند', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 48, 'default' => 12, 'description' => 'آثار منتشرشده‌ای که در بخش «آثار هنری» به این هنرمند متصل شده‌اند.', 'conditions' => $this->artist_section_conditions( array( 'works' ) ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'artist_content_history', array( 'label' => 'تاریخچه و مالکیت آثار', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'history_source', array( 'label' => 'نوع رویدادهای تاریخچه', 'type' => Controls_Manager::SELECT, 'options' => array( 'all' => 'همهٔ رویدادها', 'auction' => 'حراج‌ها', 'sales' => 'فروش‌های ثبت‌شده', 'ownership' => 'انتقال مالکیت', 'market' => 'بازار ثانویه' ), 'default' => 'all', 'conditions' => $this->artist_section_conditions( array( 'artwork_history' ) ) ) );
		$this->add_control( 'history_limit', array( 'label' => 'تعداد رویدادهای تاریخچه', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 50, 'default' => 12, 'conditions' => $this->artist_section_conditions( array( 'artwork_history' ) ) ) );
		$this->add_control( 'history_show_title', array( 'label' => 'نمایش عنوان تاریخچه', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'artwork_history' ) ) ) );
		$this->add_control( 'history_title', array( 'label' => 'عنوان تاریخچه', 'type' => Controls_Manager::TEXT, 'default' => 'تاریخچهٔ آثار هنرمند', 'dynamic' => array( 'active' => true ), 'conditions' => $this->artist_section_conditions( array( 'artwork_history' ), array( 'history_show_title' => 'yes' ) ) ) );
		$this->add_control( 'history_layout', array( 'label' => 'چیدمان تاریخچه', 'type' => Controls_Manager::SELECT, 'options' => array( 'line' => 'خط زمانی عمودی', 'cards' => 'کارت‌های جداگانه' ), 'default' => 'line', 'conditions' => $this->artist_section_conditions( array( 'artwork_history' ) ) ) );
		$this->add_control( 'history_show_image', array( 'label' => 'نمایش تصویر آثار در تاریخچه', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'artwork_history' ) ) ) );
		$this->add_control( 'history_show_price', array( 'label' => 'نمایش قیمت‌های ثبت‌شده', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'artwork_history' ) ) ) );
		$this->add_control( 'history_show_owner', array( 'label' => 'نمایش نام مالک در انتقال‌ها', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '', 'conditions' => $this->artist_section_conditions( array( 'artwork_history' ) ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'artist_content_portfolio_display', array( 'label' => 'نمایش و پیوند نمونه آثار', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'works_carousel', array( 'label' => 'نمایش نمونه آثار به‌صورت کاروسل', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'conditions' => $this->artist_section_conditions( array( 'works' ) ) ) );
		$this->add_control( 'works_show_caption', array( 'label' => 'نمایش عنوان / توضیح زیر نمونه آثار', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '', 'conditions' => $this->artist_section_conditions( array( 'works' ) ) ) );
		$this->add_control( 'works_link_target', array( 'label' => 'بازشدن تصویر نمونه اثر', 'type' => Controls_Manager::SELECT, 'options' => array( '_blank' => 'تب جدید', '_self' => 'همین تب' ), 'default' => '_blank', 'conditions' => $this->artist_section_conditions( array( 'works' ) ) ) );
		$this->end_controls_section();

		$this->aet_style_controls( array( 'hero' => true, 'stats' => true, 'sections' => true, 'tabs' => true ) );

		$this->start_controls_section( 'artist_profile_extra_style', array( 'label' => 'بیوگرافی، خط زمانی، نمودار و تاریخچه', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'readmore_color', array( 'label' => 'رنگ متن دکمه ادامه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-bio-more>summary' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'readmore_background', array( 'label' => 'پس‌زمینهٔ دکمه ادامه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-bio-more>summary' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'readmore_hover_color', array( 'label' => 'رنگ متن دکمه هنگام اشاره', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-bio-more>summary:hover' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'readmore_typography', 'label' => 'تایپوگرافی دکمه ادامه', 'selector' => '{{WRAPPER}} .aetp .aetp-bio-more>summary' ) );
		$this->add_control( 'career_line_color', array( 'label' => 'رنگ خط زمانی تحصیلات و جوایز', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-career-list' => '--aetp-career-line: {{VALUE}};' ) ) );
		$this->add_control( 'career_year_color', array( 'label' => 'رنگ سال‌های خط زمانی', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-career-year' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'career_kind_color', array( 'label' => 'رنگ برچسب تحصیلات/جایزه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-career-kind' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'career_typography', 'label' => 'تایپوگرافی خط زمانی', 'selector' => '{{WRAPPER}} .aetp-career-text' ) );
		$this->add_control( 'artist_chart_accent', array( 'label' => 'رنگ سری اول نمودار', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-chart' => '--ac-c1: {{VALUE}};' ) ) );
		$this->add_control( 'artist_chart_grid', array( 'label' => 'رنگ شبکهٔ نمودار', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-chart' => '--ac-grid: {{VALUE}};' ) ) );
		$this->add_control( 'artist_chart_axis', array( 'label' => 'رنگ برچسب محور نمودار', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-chart' => '--ac-axis: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'artist_chart_title_typography', 'label' => 'تایپوگرافی عنوان نمودار', 'selector' => '{{WRAPPER}} .aetp-artist-chart>h3, {{WRAPPER}} .art-coa-chart-title' ) );
		$this->add_control( 'artist_history_accent', array( 'label' => 'رنگ تأکیدی تاریخچه اثر', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-artwork-history' => '--art-coa-ah-accent: {{VALUE}};' ) ) );
		$this->add_control( 'artist_history_line', array( 'label' => 'رنگ خط زمانی تاریخچه اثر', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-ah-list.is-line' => 'border-inline-start-color: {{VALUE}};' ) ) );
		$this->add_control( 'artist_history_card_bg', array( 'label' => 'پس‌زمینهٔ کارت‌های تاریخچه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-ah-event' => 'background-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'artist_history_text_typography', 'label' => 'تایپوگرافی رویدادهای تاریخچه', 'selector' => '{{WRAPPER}} .art-coa-ah-title, {{WRAPPER}} .art-coa-ah-detail, {{WRAPPER}} .art-coa-ah-date' ) );
		$this->add_control( 'statement_panel_background', array( 'label' => 'پس‌زمینهٔ بیانیهٔ هنری', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-statement' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'statement_panel_border', array( 'label' => 'رنگ حاشیهٔ بیانیه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp .aetp-statement' => 'border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'statement_panel_radius', array( 'label' => 'گردی گوشه‌های بیانیه', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 36 ) ), 'selectors' => array( '{{WRAPPER}} .aetp .aetp-statement' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'career_text_color', array( 'label' => 'رنگ متن خط زمانی تحصیلات و جوایز', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-career-text' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'artist_history_date_color', array( 'label' => 'رنگ تاریخ رویدادها', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-artwork-history .art-coa-ah-date' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'artist_header_chip_background', array( 'label' => 'پس‌زمینهٔ چیپ‌های سبک در سربرگ', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-hero-artist .aetp-hero-chips .aetp-chip' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'artist_header_chip_color', array( 'label' => 'رنگ چیپ‌های سبک در سربرگ', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-hero-artist .aetp-hero-chips .aetp-chip' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'artist_header_chip_gap', array( 'label' => 'فاصلهٔ چیپ‌های سبک', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 24 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-hero-artist .aetp-hero-chips' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'artist_history_title_color', array( 'label' => 'رنگ عنوان آثار در تاریخچه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-artwork-history .art-coa-ah-title' => 'color: {{VALUE}};' ) ) );
		foreach ( array( 2, 3, 4, 5, 6 ) as $series ) {
			$this->add_control( 'artist_chart_series_' . $series, array( 'label' => 'رنگ سری ' . $series . ' نمودار', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-chart' => '--ac-c' . $series . ': {{VALUE}};' ) ) );
		}
		$this->add_control( 'artist_chart_card_background', array( 'label' => 'پس‌زمینهٔ قاب نمودار', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-chart' => '--ac-card: {{VALUE}}; background-color: {{VALUE}};' ) ) );
		$this->add_control( 'artist_chart_border_color', array( 'label' => 'رنگ حاشیهٔ نمودار', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-chart' => 'border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'artist_chart_radius', array( 'label' => 'گردی گوشهٔ نمودار', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 36 ) ), 'selectors' => array( '{{WRAPPER}} .art-coa-chart' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'artist_chart_shadow', 'label' => 'سایهٔ قاب نمودار', 'selector' => '{{WRAPPER}} .art-coa-chart' ) );
		$this->add_control( 'artist_history_border_color', array( 'label' => 'رنگ حاشیهٔ کارت تاریخچه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .art-coa-ah-event' => 'border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'artist_history_radius', array( 'label' => 'گردی گوشهٔ کارت تاریخچه', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 36 ) ), 'selectors' => array( '{{WRAPPER}} .art-coa-ah-event' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'career_item_gap', array( 'label' => 'فاصلهٔ آیتم‌های خط زمانی تحصیلات و جوایز', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-career-list' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		if ( Artiey_AET_Elementor_Options::is_elementor_preview() ) {
			// Keep the editor canvas responsive; live COA history/charts remain on the saved preview.
			$s['elementor_light_preview'] = 'yes';
		}
		$id = $this->aet_resolve_post( $s, 'artist', 'artiey_artist' );
		if ( ! $id ) {
			$this->aet_empty_message( $s, isset( $s['empty_text'] ) && '' !== trim( (string) $s['empty_text'] ) ? $s['empty_text'] : 'یک هنرمند انتخاب کنید یا ویجت را داخل قالب تک‌هنرمند قرار دهید.' );
			return;
		}
		$sections = Artiey_AET_Elementor_Options::sections( $s, 'sections', Artiey_AET_Artist::ALL_SECTIONS );
		Artiey_AET_Profile_Guard::mark( $id );
		if ( isset( $s['profile_layout'] ) && 'tabs' === $s['profile_layout'] ) {
			$output = $this->aet_render_profile_tabs( $id, 'artiey_artist', $sections, ! empty( $s['show_title'] ), $s );
		} else {
			$output = Artiey_AET_Artist::render_profile( $id, $sections, ! empty( $s['show_title'] ), $s );
		}
		if ( ! is_string( $output ) || '' === trim( wp_strip_all_tags( $output ) ) ) {
			$this->aet_empty_message( $s, isset( $s['empty_text'] ) && '' !== trim( (string) $s['empty_text'] ) ? $s['empty_text'] : 'برای این هنرمند در بخش‌های انتخاب‌شده اطلاعاتی ثبت نشده است.' );
			return;
		}
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer returns escaped profile markup.
	}
}

/**
 * Elementor widget: filterable directory (galleries / exhibitions / artists).
 */
class Artiey_AET_Elementor_Directory_Widget extends Widget_Base {
	use Artiey_AET_Elementor_Style;

	public function get_name() {
		return 'artiey_directory';
	}

	public function get_title() {
		return 'فهرست با فیلتر (گالری / نمایشگاه / هنرمند / اثر)';
	}

	public function get_icon() {
		return 'eicon-filter';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'فیلتر', 'فهرست', 'گالری', 'نمایشگاه', 'هنرمند', 'directory', 'filter' );
	}

	public function get_style_depends() {
		return array( 'artiey-aet-profile' );
	}

	public function get_script_depends() {
		return array( 'artiey-aet-profile' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => 'نوع فهرست و چیدمان', 'tab' => Controls_Manager::TAB_CONTENT ) );

		$this->add_control( 'directory_layout', array( 'label' => 'چیدمان فهرست', 'type' => Controls_Manager::SELECT, 'options' => array( 'single' => 'یک فهرست معمولی', 'tabs' => 'تب جدا برای هر نوع محتوا' ), 'default' => 'single', 'description' => 'در حالت تب‌دار می‌توان گالری‌ها، هنرمندان، آثار و نمایشگاه‌ها را در یک ویجت جمع کرد.' ) );
		$this->add_control( 'directory_tabs', array( 'label' => 'انواع محتوا در تب‌ها', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => array( 'galleries' => 'گالری‌ها', 'artists' => 'هنرمندان', 'artworks' => 'آثار هنری', 'exhibitions' => 'نمایشگاه‌ها' ), 'default' => array( 'galleries', 'artists', 'artworks', 'exhibitions' ), 'condition' => array( 'directory_layout' => 'tabs' ) ) );
		Artiey_AET_Tabs::register_behavior_controls( $this, 'directory_layout', 'tabs', array( 'galleries' => 'گالری‌ها', 'artists' => 'هنرمندان', 'artworks' => 'آثار هنری', 'exhibitions' => 'نمایشگاه‌ها' ) );
		foreach ( array( 'galleries' => array( 'artiey_gallery', 'گالری‌ها' ), 'artists' => array( 'artiey_artist', 'هنرمندان' ), 'artworks' => array( 'artiey_artwork', 'آثار هنری' ), 'exhibitions' => array( 'artiey_exhibition', 'نمایشگاه‌ها' ) ) as $tab_type => $tab_config ) {
			$this->add_control( 'tabs_include_' . $tab_type, array( 'label' => 'انتخاب دستی ' . $tab_config[1] . ' برای تب', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => Artiey_AET_Elementor_Options::posts( $tab_config[0] ), 'condition' => array( 'directory_layout' => 'tabs', 'directory_tabs' => $tab_type ), 'description' => 'خالی = همه موارد مطابق فیلترها.' ) );
		}

		$this->add_control(
			'type',
			array(
				'label'   => 'نوع فهرست',
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'galleries'   => 'گالری‌ها',
					'exhibitions' => 'نمایشگاه‌ها',
					'artists'     => 'هنرمندان',
					'artworks'    => 'آثار هنری',
				),
				'default' => 'galleries',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section( 'directory_filter_settings', array( 'label' => 'فیلترها و محدودسازی', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control(
			'filters',
			array(
				'label'       => 'فیلترهای قابل مشاهده',
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => array(
					'search'  => 'جستجو',
					'city'    => 'شهر',
					'style'   => 'سبک هنری',
					'status'  => 'وضعیت (نمایشگاه‌ها: جاری/آینده/گذشته، آثار: موجود/فروخته)',
					'gallery' => 'گالری (نمایشگاه‌ها، هنرمندان و آثار)',
					'artist'  => 'هنرمند (نمایشگاه‌ها و آثار)',
					'open'    => 'فقط بازها (فقط گالری‌ها)',
					'venue'   => 'نوع گالری (فقط فهرست گالری‌ها)',
					'sort'    => 'مرتب‌سازی',
				),
				'description' => 'خالی = فیلترهای پیش‌فرض همان نوع. برای حذف همه فیلترها یک مورد را انتخاب نکنید و در شورت‌کد از filters="none" استفاده کنید.',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section( 'directory_output_settings', array( 'label' => 'تعداد، چیدمان و انتخاب موارد', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'per_page', array( 'label' => 'تعداد در هر صفحه', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 60, 'placeholder' => 'پیش‌فرض' ) );
		$this->add_control( 'columns', array( 'label' => 'تعداد ستون‌ها', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'placeholder' => 'پیش‌فرض' ) );
		$this->add_control( 'card_style', array( 'label' => 'نمای کارت‌ها', 'type' => Controls_Manager::SELECT, 'options' => array( 'standard' => 'استاندارد', 'editorial' => 'مجله‌ای', 'compact' => 'فشرده و افقی', 'framed' => 'قاب‌دار', ), 'default' => 'standard', 'description' => 'این تنظیم فقط ظاهر کارت‌ها را تغییر می‌دهد و به داده‌های آن‌ها دست نمی‌زند.' ) );

		$this->add_control(
			'status',
			array(
				'label'     => 'قفل وضعیت نمایشگاه‌ها',
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					''         => 'بدون قفل',
					'now'      => 'فقط در حال برگزاری',
					'upcoming' => 'فقط به‌زودی',
					'past'     => 'فقط گذشته',
				),
				'default'   => '',
				'condition' => array( 'type' => 'exhibitions' ),
			)
		);
		$this->add_control(
			'artwork_status',
			array(
				'label'     => 'قفل وضعیت فروش آثار',
				'type'      => Controls_Manager::SELECT,
				'options'   => array( '' => 'بدون قفل', 'available' => 'موجود', 'reserved' => 'رزرو شده', 'sold' => 'فروخته شد', 'not_for_sale' => 'غیرقابل فروش' ),
				'default'   => '',
				'condition' => array( 'type' => 'artworks' ),
			)
		);

		$this->add_control( 'city', array( 'label' => 'قفل شهر (اختیاری)', 'type' => Controls_Manager::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'style', array( 'label' => 'قفل سبک هنری (نامک/slug)', 'type' => Controls_Manager::TEXT, 'description' => 'مثلاً: naghashi', 'dynamic' => array( 'active' => true ) ) );

		$this->add_control(
			'sort',
			array(
				'label'       => 'مرتب‌سازی پیش‌فرض',
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					''          => 'پیش‌فرض همان نوع',
					'title'     => 'الفبایی',
					'newest'    => 'جدیدترین ثبت',
					'date_desc' => 'نمایشگاه‌ها: جدیدترین تاریخ',
					'date_asc'  => 'نمایشگاه‌ها: قدیمی‌ترین تاریخ',
					'price_asc' => 'آثار: ارزان‌ترین',
					'price_desc' => 'آثار: گران‌ترین',
					'founded'   => 'گالری‌ها: قدیمی‌ترین تأسیس',
					'manual'    => 'به ترتیب انتخاب دستی (فقط با «انتخاب دستی»)',
				),
				'default'     => '',
				'description' => 'بازدیدکننده در صورت نمایش فیلتر «مرتب‌سازی» می‌تواند آن را تغییر دهد.',
			)
		);
		$this->add_control(
			'show_count',
			array(
				'label'        => 'نمایش «چند مورد یافت شد»',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control( 'include_galleries', array( 'label' => 'انتخاب دستی گالری‌ها (خالی = همه)', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => Artiey_AET_Elementor_Options::posts( 'artiey_gallery' ), 'condition' => array( 'type' => 'galleries' ) ) );
		$this->add_control( 'include_exhibitions', array( 'label' => 'انتخاب دستی نمایشگاه‌ها (خالی = همه)', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => Artiey_AET_Elementor_Options::posts( 'artiey_exhibition' ), 'condition' => array( 'type' => 'exhibitions' ) ) );
		$this->add_control( 'include_artists', array( 'label' => 'انتخاب دستی هنرمندان (خالی = همه)', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => Artiey_AET_Elementor_Options::posts( 'artiey_artist' ), 'condition' => array( 'type' => 'artists' ) ) );
		$this->add_control( 'include_artworks', array( 'label' => 'انتخاب دستی آثار (خالی = همه)', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => Artiey_AET_Elementor_Options::posts( 'artiey_artwork' ), 'condition' => array( 'type' => 'artworks' ) ) );
		$this->add_control( 'exclude', array( 'label' => 'شناسهٔ موارد مستثنی‌شده (با کاما)', 'type' => Controls_Manager::TEXT, 'placeholder' => '12, 45', 'dynamic' => array( 'active' => true ), 'description' => 'با تگ دینامیک می‌توانید «شناسهٔ نوشتهٔ جاری» را بگذارید تا خودش در فهرست نمایش داده نشود.' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'directory_behavior_settings', array( 'label' => 'رفتار و پیام‌ها', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'empty_text', array( 'label' => 'پیام وقتی نتیجه‌ای نیست', 'type' => Controls_Manager::TEXT, 'placeholder' => 'موردی با این فیلترها پیدا نشد.', 'dynamic' => array( 'active' => true ) ) );

		$this->add_control(
			'ajax',
			array(
				'label'   => 'فیلتر بدون بارگذاری مجدد',
				'type'    => Controls_Manager::SELECT,
				'options' => array( '' => 'پیش‌فرض (از تنظیمات)', 'yes' => 'بله', 'no' => 'خیر' ),
				'default' => '',
			)
		);

		$this->end_controls_section();

		$this->aet_style_controls( array( 'filters' => true, 'tabs' => true ) );
	}

	private function build_directory_args( $s, $type_override = '' ) {
		$type = in_array( $type_override, array( 'galleries', 'artists', 'artworks', 'exhibitions' ), true ) ? $type_override : ( isset( $s['type'] ) ? $s['type'] : 'galleries' );
		$include_key = ( isset( $s['directory_layout'] ) && 'tabs' === $s['directory_layout'] && isset( $s['tabs_include_' . $type ] ) ) ? 'tabs_include_' . $type : 'include_' . $type;
		$include = ! empty( $s[ $include_key ] ) ? implode( ',', array_map( 'absint', (array) $s[ $include_key ] ) ) : '';
		$status = 'exhibitions' === $type ? ( $s['status'] ?? '' ) : ( 'artworks' === $type ? ( $s['artwork_status'] ?? '' ) : '' );
		return array(
			'type'     => $type,
			'filters'  => ! empty( $s['filters'] ) ? implode( ',', (array) $s['filters'] ) : '',
			'per_page' => isset( $s['per_page'] ) ? $s['per_page'] : '',
			'columns'  => isset( $s['columns'] ) ? $s['columns'] : '',
			'card_style' => isset( $s['card_style'] ) ? $s['card_style'] : 'standard',
			'status'   => $status,
			'city'     => isset( $s['city'] ) ? $s['city'] : '',
			'style'    => isset( $s['style'] ) ? $s['style'] : '',
			'ajax'     => isset( $s['ajax'] ) ? $s['ajax'] : '',
			'sort'     => isset( $s['sort'] ) ? $s['sort'] : '',
			'show_count' => ! empty( $s['show_count'] ) ? 'yes' : 'no',
			'include'  => $include,
			'exclude'  => isset( $s['exclude'] ) ? $s['exclude'] : '',
			'empty_text' => isset( $s['empty_text'] ) ? $s['empty_text'] : '',
		);
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		if ( isset( $s['directory_layout'] ) && 'tabs' === $s['directory_layout'] ) {
			$selected = isset( $s['directory_tabs'] ) ? array_values( array_intersect( array( 'galleries', 'artists', 'artworks', 'exhibitions' ), array_map( 'sanitize_key', (array) $s['directory_tabs'] ) ) ) : array();
			if ( ! $selected ) { $selected = array( isset( $s['type'] ) ? sanitize_key( $s['type'] ) : 'galleries' ); }
			$labels = array( 'galleries' => 'گالری‌ها', 'artists' => 'هنرمندان', 'artworks' => 'آثار هنری', 'exhibitions' => 'نمایشگاه‌ها' );
			$panels = array();
			foreach ( $selected as $type ) {
				if ( ! isset( $labels[ $type ] ) ) { continue; }
				$panels[] = array( 'key' => $type, 'label' => $labels[ $type ], 'html' => Artiey_AET_Directory::render( $this->build_directory_args( $s, $type ) ) );
			}
			$html = Artiey_AET_Tabs::render( $panels, $s, 'فهرست محتوای هنری' );
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tabs contain renderer-escaped directory markup.
			return;
		}
		echo Artiey_AET_Directory::render( $this->build_directory_args( $s ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderer.
	}
}


/**
 * Elementor widget: artwork profile (image, details, price, certificate).
 */
class Artiey_AET_Elementor_Artwork_Widget extends Widget_Base {
	use Artiey_AET_Elementor_Style;

	public function get_name() {
		return 'artiey_artwork_profile';
	}

	public function get_title() {
		return 'پروفایل اثر هنری';
	}

	public function get_icon() {
		return 'eicon-image-box';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'اثر', 'گواهی', 'artwork', 'certificate' );
	}

	public function get_style_depends() {
		return array( 'artiey-aet-profile' );
	}

	public function get_script_depends() {
		return array( 'artiey-aet-profile' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => 'محتوا', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'profile_layout', array( 'label' => 'چیدمان بخش‌ها', 'type' => Controls_Manager::SELECT, 'options' => array( 'stacked' => 'پشت سر هم', 'tabs' => 'تب‌دار (هر بخش در تب مستقل)', ), 'default' => 'stacked', 'description' => 'مشخصات اثر، تصاویر و آثار مرتبط می‌توانند در تب‌های جدا نمایش داده شوند.' ) );
		Artiey_AET_Tabs::register_behavior_controls( $this, 'profile_layout', 'tabs', array( 'main' => 'مشخصات اثر', 'certificate' => 'گواهی اصالت', 'market' => 'خرید و حراج', 'photos' => 'تصاویر اثر', 'related' => 'آثار مرتبط' ) );
		$this->aet_source_control( 'artwork', 'artiey_artwork', 'اثر', 'اثر جاری (در قالب تک‌اثر)' );
		$this->add_control( 'artwork_profile_style', array( 'label' => 'سبک نمایش اثر', 'type' => Controls_Manager::SELECT, 'options' => array( 'catalog' => 'کاتالوگی', 'showcase' => 'ویترینی', 'minimal' => 'مینیمال' ), 'default' => 'catalog', 'prefix_class' => 'aetp-artwork-style-' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'artwork_content_sections', array( 'label' => 'بخش‌های اطلاعات اثر', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control(
			'sections',
			array(
				'label'       => 'بخش‌ها',
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => Artiey_AET_Artwork::SECTION_LABELS,
				'default'     => Artiey_AET_Artwork::SECTIONS,
				'description' => 'وقتی «گواهی اصالت» جدا انتخاب شود، در بخش اصلی فقط نشان کوچک گواهی نمایش داده می‌شود.',
			)
		);
		$this->add_control( 'show_title', array( 'label' => 'نمایش عنوان', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->aet_empty_text_control();
		$this->end_controls_section();

		$this->aet_style_controls( array( 'hero' => false, 'sections' => true, 'tabs' => true ) );
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$id = $this->aet_resolve_post( $s, 'artwork', 'artiey_artwork' );
		if ( ! $id ) {
			$this->aet_empty_message( $s, 'یک اثر انتخاب کنید یا ویجت را داخل قالب تک‌اثر قرار دهید.' );
			return;
		}
		$sections = Artiey_AET_Artwork::upgrade_default_sections( Artiey_AET_Elementor_Options::sections( $s, 'sections', Artiey_AET_Artwork::SECTIONS ) );
		Artiey_AET_Profile_Guard::mark( $id );
		if ( isset( $s['profile_layout'] ) && 'tabs' === $s['profile_layout'] ) {
			$html = $this->aet_render_profile_tabs( $id, 'artiey_artwork', $sections, ! empty( $s['show_title'] ), $s );
			if ( '' !== trim( (string) $html ) ) {
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- profile renderer escapes content.
			} else {
				$this->aet_empty_message( $s, 'برای این اثر در بخش‌های انتخاب‌شده اطلاعاتی ثبت نشده است.' );
			}
			return;
		}
		echo Artiey_AET_Artwork::render_profile( $id, $sections, ! empty( $s['show_title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderer.
	}
}

/** Elementor controls for the cached platform statistics shortcode. */
class Artiey_AET_Elementor_Stats_Widget extends Widget_Base {
	use Artiey_AET_Elementor_Style;
	public function get_name() { return 'artiey_platform_stats'; }
	public function get_title() { return 'آمار پویای پلتفرم'; }
	public function get_icon() { return 'eicon-counter'; }
	public function get_categories() { return array( 'artiey-art' ); }
	public function get_keywords() { return array( 'آمار', 'تعداد', 'statistics', 'stats' ); }
	public function get_style_depends() { return array( 'artiey-aet-profile' ); }
	public function get_script_depends() { return array( 'artiey-aet-profile' ); }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => 'محتوا', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'stats_layout', array( 'label' => 'چیدمان آمار', 'type' => Controls_Manager::SELECT, 'options' => array( 'single' => 'یک مجموعه شمارنده', 'tabs' => 'هر شاخص در یک تب' ), 'default' => 'single' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'stats_content_metrics', array( 'label' => 'شاخص‌های آماری', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'metrics_tabs', array( 'label' => 'شاخص‌های تب‌دار', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => array( 'galleries' => 'گالری‌ها', 'artists' => 'هنرمندان', 'artworks' => 'آثار هنری', 'exhibitions' => 'نمایشگاه‌ها', 'current' => 'نمایشگاه‌های جاری', 'upcoming' => 'نمایشگاه‌های پیش‌رو' ), 'default' => array( 'galleries', 'artists', 'artworks', 'exhibitions', 'current', 'upcoming' ), 'condition' => array( 'stats_layout' => 'tabs' ) ) );
		Artiey_AET_Tabs::register_behavior_controls( $this, 'stats_layout', 'tabs', array( 'galleries' => 'گالری‌ها', 'artists' => 'هنرمندان', 'artworks' => 'آثار هنری', 'exhibitions' => 'نمایشگاه‌ها', 'current' => 'نمایشگاه‌های جاری', 'upcoming' => 'نمایشگاه‌های پیش‌رو' ) );
		$this->add_control( 'metrics', array(
			'label' => 'شاخص‌ها', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true,
			'options' => array(
				'galleries' => 'گالری‌ها', 'artists' => 'هنرمندان', 'artworks' => 'آثار هنری',
				'exhibitions' => 'نمایشگاه‌ها', 'current' => 'نمایشگاه‌های جاری', 'upcoming' => 'نمایشگاه‌های پیش‌رو',
			),
			'default' => array( 'galleries', 'artists', 'artworks', 'exhibitions', 'current', 'upcoming' ),
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'stats_content_heading', array( 'label' => 'عنوان و معرفی', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'show_title', array( 'label' => 'نمایش عنوان', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->add_control( 'title', array( 'label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'default' => 'آمار پلتفرم', 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_title' => 'yes' ) ) );
		$this->end_controls_section();
		$this->aet_style_controls( array( 'cards' => true, 'images' => false, 'stats' => true, 'tabs' => true ) );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		if ( isset( $s['stats_layout'] ) && 'tabs' === $s['stats_layout'] ) {
			$selected = isset( $s['metrics_tabs'] ) ? array_values( array_intersect( array( 'galleries', 'artists', 'artworks', 'exhibitions', 'current', 'upcoming' ), array_map( 'sanitize_key', (array) $s['metrics_tabs'] ) ) ) : array();
			if ( ! $selected ) { $selected = isset( $s['metrics'] ) ? array_values( array_intersect( array( 'galleries', 'artists', 'artworks', 'exhibitions', 'current', 'upcoming' ), array_map( 'sanitize_key', (array) $s['metrics'] ) ) ) : array(); }
			$labels = array( 'galleries' => 'گالری‌ها', 'artists' => 'هنرمندان', 'artworks' => 'آثار هنری', 'exhibitions' => 'نمایشگاه‌ها', 'current' => 'نمایشگاه‌های جاری', 'upcoming' => 'نمایشگاه‌های پیش‌رو' );
			$panels = array();
			foreach ( $selected as $metric ) {
				$panels[] = array( 'key' => $metric, 'label' => $labels[ $metric ], 'html' => Artiey_AET_Statistics::shortcode( array( 'metrics' => $metric, 'title' => 'no' ) ) );
			}
			if ( ! empty( $s['show_title'] ) && ! empty( $s['title'] ) ) { echo '<h2 class="aetp-stats-heading">' . esc_html( (string) $s['title'] ) . '</h2>'; }
			echo Artiey_AET_Tabs::render( $panels, $s, 'شاخص‌های آماری پلتفرم' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted statistics renderer.
			return;
		}
		$metrics = ! empty( $s['metrics'] ) ? implode( ',', array_map( 'sanitize_key', (array) $s['metrics'] ) ) : '';
		$atts = array( 'metrics' => $metrics, 'title' => isset( $s['title'] ) ? $s['title'] : 'آمار پلتفرم' );
		if ( empty( $s['show_title'] ) ) { $atts['title'] = 'no'; }
		echo Artiey_AET_Statistics::shortcode( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in shortcode renderer.
	}
}

/**
 * Elementor widget: single exhibition profile (poster, dates, venue, curator, artists).
 */
class Artiey_AET_Elementor_Exhibition_Widget extends Widget_Base {
	use Artiey_AET_Elementor_Style;

	public function get_name() {
		return 'artiey_exhibition_profile';
	}

	public function get_title() {
		return 'پروفایل نمایشگاه';
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'نمایشگاه', 'پوستر', 'exhibition', 'event', 'profile' );
	}

	public function get_style_depends() {
		return array( 'artiey-aet-profile' );
	}

	public function get_script_depends() {
		return array( 'artiey-aet-profile' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => 'محتوا', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'profile_layout', array( 'label' => 'چیدمان اطلاعات نمایشگاه', 'type' => Controls_Manager::SELECT, 'options' => array( 'stacked' => 'پشت سر هم', 'tabs' => 'هر بخش در تب مستقل' ), 'default' => 'stacked' ) );
		Artiey_AET_Tabs::register_behavior_controls( $this, 'profile_layout', 'tabs', array( 'header' => 'معرفی نمایشگاه', 'details' => 'جزئیات و تاریخ‌ها', 'artists' => 'هنرمندان شرکت‌کننده', 'gallery' => 'گالری برگزارکننده', 'styles' => 'سبک‌های هنری' ) );
		$this->aet_source_control( 'exhibition', 'artiey_exhibition', 'نمایشگاه', 'نمایشگاه جاری (در قالب تک‌نمایشگاه)' );
		$this->add_control( 'exhibition_profile_style', array( 'label' => 'سبک نمایش پروفایل نمایشگاه', 'type' => Controls_Manager::SELECT, 'options' => array( 'editorial' => 'مجله‌ای / کیوریتوری', 'poster' => 'پوستر محور', 'compact' => 'فشرده' ), 'default' => 'editorial', 'prefix_class' => 'aetp-exhibition-style-' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'exhibition_content_sections', array( 'label' => 'بخش‌های نمایشگاه', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'sections', array( 'label' => 'بخش‌های قابل نمایش', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => array( 'header' => 'معرفی و پوستر', 'details' => 'تاریخ و جزئیات', 'artists' => 'هنرمندان شرکت‌کننده', 'gallery' => 'گالری برگزارکننده', 'styles' => 'سبک‌های هنری' ), 'default' => array( 'header', 'details', 'artists', 'gallery', 'styles' ), 'description' => 'بخش‌هایی که در هر دو چیدمان پشت‌سرهم و تب‌دار نمایش داده می‌شوند.' ) );
		$this->add_control( 'show_title', array( 'label' => 'نمایش عنوان نمایشگاه در معرفی', 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'exhibition_content_fallback', array( 'label' => 'عنوان و پیام جایگزین', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->aet_empty_text_control();
		$this->end_controls_section();

		$this->aet_style_controls( array( 'hero' => true, 'cards' => true, 'images' => true, 'sections' => true, 'tabs' => true ) );
		$this->start_controls_section( 'exhibition_profile_extra_style', array( 'label' => 'پوستر، جزئیات و برگزارکننده', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'exhibition_title_color', array( 'label' => 'رنگ عنوان نمایشگاه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-ex-title' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'exhibition_title_typography', 'label' => 'تایپوگرافی عنوان نمایشگاه', 'selector' => '{{WRAPPER}} .aetp-ex-title' ) );
		$this->add_responsive_control( 'exhibition_poster_height', array( 'label' => 'ارتفاع پوستر', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', 'vh' ), 'range' => array( 'px' => array( 'min' => 160, 'max' => 900 ), 'vh' => array( 'min' => 20, 'max' => 90 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-ex-poster img' => 'height: {{SIZE}}{{UNIT}}; width: 100%; object-fit: cover;' ) ) );
		$this->add_responsive_control( 'exhibition_poster_position', array( 'label' => 'جایگاه برش پوستر', 'type' => Controls_Manager::SELECT, 'options' => array( 'center center' => 'وسط', 'center top' => 'بالا', 'center bottom' => 'پایین', 'right center' => 'راست', 'left center' => 'چپ' ), 'selectors' => array( '{{WRAPPER}} .aetp-ex-poster img' => 'object-position: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'exhibition_poster_radius', array( 'label' => 'گردی گوشه‌های پوستر', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-ex-poster, {{WRAPPER}} .aetp-ex-poster img' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'exhibition_details_background', array( 'label' => 'پس‌زمینهٔ جزئیات نمایشگاه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-ex-details' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'exhibition_details_label_color', array( 'label' => 'رنگ برچسب‌های جزئیات', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-ex-details .aetp-dl dt' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'exhibition_details_value_color', array( 'label' => 'رنگ مقادیر جزئیات', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-ex-details .aetp-dl dd' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'exhibition_relation_card_background', array( 'label' => 'پس‌زمینهٔ کارت برگزارکننده', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aetp-ex-gallery-card' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'exhibition_relation_card_radius', array( 'label' => 'گردی گوشهٔ کارت برگزارکننده', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 36 ) ), 'selectors' => array( '{{WRAPPER}} .aetp-ex-gallery-card' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'exhibition_relation_card_shadow', 'label' => 'سایهٔ کارت برگزارکننده', 'selector' => '{{WRAPPER}} .aetp-ex-gallery-card' ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$id = $this->aet_resolve_post( $s, 'exhibition', 'artiey_exhibition' );
		if ( ! $id ) {
			$this->aet_empty_message( $s, 'یک نمایشگاه انتخاب کنید یا ویجت را داخل قالب تک‌نمایشگاه قرار دهید.' );
			return;
		}
		Artiey_AET_Profile_Guard::mark( $id );
		$allowed  = array( 'header', 'details', 'artists', 'gallery', 'styles' );
		$sections = isset( $s['sections'] ) ? array_values( array_intersect( $allowed, array_map( 'sanitize_key', (array) $s['sections'] ) ) ) : $allowed;
		if ( isset( $s['profile_layout'] ) && 'tabs' === $s['profile_layout'] ) {
			$html = $this->aet_render_profile_tabs( $id, 'artiey_exhibition', $sections, ! empty( $s['show_title'] ), $s );
			if ( '' !== trim( $html ) ) { echo $html; } else { $this->aet_empty_message( $s, 'برای این نمایشگاه در بخش‌های انتخاب‌شده اطلاعاتی ثبت نشده است.' ); }
			return;
		}
		$parts = array();
		foreach ( $sections as $section ) {
			$part = Artiey_AET_Exhibition::render_profile_section( $id, $section, ! empty( $s['show_title'] ) );
			if ( is_string( $part ) && '' !== trim( $part ) ) { $parts[] = $part; }
		}
		if ( $parts ) {
			echo implode( '', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- semantic renderer escapes field values.
		} else {
			$this->aet_empty_message( $s, 'برای این نمایشگاه در بخش‌های انتخاب‌شده اطلاعاتی ثبت نشده است.' );
		}
	}
}
