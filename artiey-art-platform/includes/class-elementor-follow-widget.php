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
 * Elementor widget: follow button + followers / following panel for artists, galleries, curators and collectors.
 * The subject is detected from the page (profile route, artist/gallery post, template preview) or picked manually.
 * Buttons update live over AJAX and stay correct behind page caches (see assets/js/follow.js).
 */
class Artiey_AET_Elementor_Follow_Widget extends Widget_Base {

	public function get_name() {
		return 'artiey_follow';
	}

	public function get_title() {
		return 'دنبال کردن و دنبال‌کنندگان';
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_categories() {
		return array( 'artiey-art' );
	}

	public function get_keywords() {
		return array( 'دنبال', 'فالو', 'دنبال‌کننده', 'علاقه‌مندی', 'follow', 'followers', 'following', 'artist', 'gallery', 'curator' );
	}

	public function get_style_depends() {
		return array( 'artiey-follow' );
	}

	public function get_script_depends() {
		return array( 'artiey-follow' );
	}

	/** Sources the editor can pick. "auto" follows the page the widget is shown on. */
	public static function sources() {
		return array(
			'auto'         => 'خودکار (پروفایل همین صفحه)',
			'artist_post'  => 'هنرمند مشخص',
			'gallery_post' => 'گالری مشخص',
			'curator'      => 'کیوریتور مشخص',
			'artist'       => 'هنرمند (شناسه ماژول گواهی)',
			'gallery'      => 'گالری (شناسه ماژول گواهی)',
			'collector'    => 'مجموعه‌دار عمومی (شناسه)',
		);
	}

	protected function register_controls() {
		$yes = array( 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes' );

		$this->start_controls_section( 'section_source', array( 'label' => 'پروفایل', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'source', array(
			'label'       => 'منبع',
			'type'        => Controls_Manager::SELECT,
			'default'     => 'auto',
			'options'     => self::sources(),
			'description' => 'در قالب‌های تک‌هنرمند، تک‌گالری و صفحه‌های پروفایل کیوریتور/مجموعه‌دار حالت خودکار را نگه دارید.',
		) );
		$artist_options  = class_exists( 'Artiey_AET_Elementor_Options' ) ? Artiey_AET_Elementor_Options::posts( 'artiey_artist' ) : array();
		$gallery_options = class_exists( 'Artiey_AET_Elementor_Options' ) ? Artiey_AET_Elementor_Options::posts( 'artiey_gallery' ) : array();
		$this->add_control( 'artist_post', array( 'label' => 'هنرمند', 'type' => Controls_Manager::SELECT2, 'options' => $artist_options, 'label_block' => true, 'condition' => array( 'source' => 'artist_post' ) ) );
		$this->add_control( 'gallery_post', array( 'label' => 'گالری', 'type' => Controls_Manager::SELECT2, 'options' => $gallery_options, 'label_block' => true, 'condition' => array( 'source' => 'gallery_post' ) ) );
		$curators = class_exists( 'Artiey_AET_Curator_View' ) ? Artiey_AET_Curator_View::picker_options() : array();
		$this->add_control( 'curator', array( 'label' => 'کیوریتور', 'type' => Controls_Manager::SELECT2, 'options' => $curators, 'label_block' => true, 'condition' => array( 'source' => 'curator' ) ) );
		$this->add_control( 'entity_id', array(
			'label'       => 'شناسه',
			'type'        => Controls_Manager::NUMBER,
			'min'         => 1,
			'dynamic'     => array( 'active' => true ),
			'condition'   => array( 'source' => array( 'artist', 'gallery', 'collector' ) ),
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_display', array( 'label' => 'نمایش', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'layout', array(
			'label'   => 'چیدمان',
			'type'    => Controls_Manager::SELECT,
			'default' => 'button',
			'options' => array(
				'button' => 'فقط دکمه (با شمارنده)',
				'card'   => 'کارت دنبال‌کنندگان و دنبال‌شونده‌ها',
				'inline' => 'آمار و دکمه در یک ردیف',
				'stats'  => 'فقط آمار',
			),
		) );
		$this->add_control( 'show_count', $yes + array( 'label' => 'شمارندهٔ دنبال‌کنندگان', 'default' => 'yes', 'condition' => array( 'layout' => 'button' ) ) );
		$this->add_control( 'show_following', $yes + array( 'label' => 'تعداد دنبال‌شونده‌ها', 'default' => 'yes', 'condition' => array( 'layout!' => 'button' ), 'description' => 'برای پروفایلی که به یک حساب کاربری وصل است.' ) );
		$this->add_control( 'avatars', array( 'label' => 'تعداد تصویر دنبال‌کنندگان', 'type' => Controls_Manager::NUMBER, 'min' => 0, 'max' => 48, 'default' => 8, 'condition' => array( 'layout' => array( 'card', 'inline' ) ), 'description' => 'فقط کاربرانی که پروفایل عمومی (هنرمند، کیوریتور، گالری یا مجموعه‌دار) دارند با تصویر دیده می‌شوند؛ بقیه فقط شمرده می‌شوند.' ) );
		$this->add_control( 'heading', array( 'label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'label_block' => true, 'dynamic' => array( 'active' => true ), 'condition' => array( 'layout' => 'card' ) ) );
		$this->add_control( 'label_follow', array( 'label' => 'متن دکمه', 'type' => Controls_Manager::TEXT, 'placeholder' => 'دنبال کردن', 'dynamic' => array( 'active' => true ), 'condition' => array( 'layout!' => 'stats' ) ) );
		$this->add_control( 'label_following', array( 'label' => 'متن دکمه پس از دنبال کردن', 'type' => Controls_Manager::TEXT, 'placeholder' => 'دنبال می‌کنید', 'dynamic' => array( 'active' => true ), 'condition' => array( 'layout!' => 'stats' ) ) );
		$this->add_control( 'count_label', array( 'label' => 'برچسب شمارنده', 'type' => Controls_Manager::TEXT, 'placeholder' => 'دنبال‌کننده', 'condition' => array( 'layout' => 'button', 'show_count' => 'yes' ) ) );
		$this->add_control( 'button_style', array( 'label' => 'سبک دکمه', 'type' => Controls_Manager::SELECT, 'default' => 'solid', 'options' => array( 'solid' => 'توپر', 'outline' => 'خطی', 'pill' => 'کپسولی', 'ghost' => 'کم‌رنگ' ), 'condition' => array( 'layout!' => 'stats' ) ) );
		$this->add_control( 'button_size', array( 'label' => 'اندازه دکمه', 'type' => Controls_Manager::SELECT, 'default' => 'md', 'options' => array( 'sm' => 'کوچک', 'md' => 'متوسط', 'lg' => 'بزرگ' ), 'condition' => array( 'layout!' => 'stats' ) ) );
		$this->add_control( 'show_icon', $yes + array( 'label' => 'آیکون', 'default' => 'yes', 'condition' => array( 'layout!' => 'stats' ) ) );
		$this->add_responsive_control( 'align', array(
			'label'     => 'تراز',
			'type'      => Controls_Manager::SELECT,
			'default'   => '',
			'options'   => array( '' => 'پیش‌فرض', 'flex-start' => 'ابتدا', 'center' => 'وسط', 'flex-end' => 'انتها', 'space-between' => 'دو طرف' ),
			'selectors' => array( '{{WRAPPER}} .aet-follow, {{WRAPPER}} .aet-followers-stats, {{WRAPPER}} .aet-followers--inline' => 'justify-content: {{VALUE}};' ),
		) );
		$this->add_control( 'empty_text', array( 'label' => 'پیام وقتی پروفایلی پیدا نشد', 'type' => Controls_Manager::TEXT, 'default' => '', 'description' => 'خالی = در سایت چیزی نمایش داده نمی‌شود.' ) );
		$this->end_controls_section();

		/* ---------------- Style ---------------- */
		$btn = '{{WRAPPER}} .aet-follow-btn';
		$this->start_controls_section( 'style_button', array( 'label' => 'دکمه', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'btn_typo', 'selector' => $btn ) );
		$this->add_control( 'btn_color', array( 'label' => 'رنگ اصلی دکمه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-follow, {{WRAPPER}} .aet-followers' => '--aetf-p: {{VALUE}};' ) ) );
		$this->add_control( 'btn_text', array( 'label' => 'رنگ متن', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-follow' => '--aetf-fg: {{VALUE}};' ) ) );
		$this->add_control( 'btn_on_bg', array( 'label' => 'پس‌زمینه در حالت دنبال‌شده', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-follow' => '--aetf-on-bg: {{VALUE}};' ) ) );
		$this->add_control( 'btn_on_text', array( 'label' => 'متن در حالت دنبال‌شده', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-follow' => '--aetf-on-fg: {{VALUE}};' ) ) );
		$this->add_control( 'btn_focus', array( 'label' => 'رنگ حلقه فوکوس', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-follow' => '--aetf-a: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'btn_radius', array( 'label' => 'گردی گوشه‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( $btn => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'btn_padding', array( 'label' => 'فاصلهٔ داخلی', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( $btn => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'btn_width', array( 'label' => 'عرض دکمه', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => 60, 'max' => 600 ), '%' => array( 'min' => 10, 'max' => 100 ) ), 'selectors' => array( $btn => 'width: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .aet-follow-form' => 'display:block;width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'btn_shadow', 'selector' => $btn ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_count', array( 'label' => 'شمارنده و آمار', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'count_typo', 'label' => 'تایپوگرافی عدد', 'selector' => '{{WRAPPER}} .aet-follow-count strong, {{WRAPPER}} .aet-followers-stat strong' ) );
		$this->add_control( 'count_color', array( 'label' => 'رنگ عدد', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-follow-count strong, {{WRAPPER}} .aet-followers-stat strong' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'count_label_color', array( 'label' => 'رنگ برچسب', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-follow-count, {{WRAPPER}} .aet-followers-stat span, {{WRAPPER}} .aet-followers-group-label' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'فاصله اجزا', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .aet-follow, {{WRAPPER}} .aet-followers' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'avatar_size', array( 'label' => 'اندازه تصویر دنبال‌کنندگان', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 24, 'max' => 96 ) ), 'selectors' => array( '{{WRAPPER}} .aet-followers-list a, {{WRAPPER}} .aet-followers-more span' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_card', array( 'label' => 'کارت', 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'layout' => 'card' ) ) );
		$this->add_control( 'card_bg', array( 'label' => 'پس‌زمینه', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .aet-followers' => 'background: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'card_border', 'selector' => '{{WRAPPER}} .aet-followers' ) );
		$this->add_responsive_control( 'card_radius', array( 'label' => 'گردی گوشه‌ها', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .aet-followers' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'card_padding', array( 'label' => 'فاصلهٔ داخلی', 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .aet-followers' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .aet-followers' ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'heading_typo', 'label' => 'تایپوگرافی عنوان', 'selector' => '{{WRAPPER}} .aet-followers-title' ) );
		$this->end_controls_section();
	}

	/** @return array{0:string,1:int}|null */
	public static function resolve_subject( array $s ) {
		if ( ! class_exists( '\\WPArtCOA\\Follows' ) ) {
			return null;
		}
		$source = isset( $s['source'] ) ? (string) $s['source'] : 'auto';
		switch ( $source ) {
			case 'artist_post':
				return \WPArtCOA\Follows::subject_for_post( absint( $s['artist_post'] ?? 0 ) );
			case 'gallery_post':
				return \WPArtCOA\Follows::subject_for_post( absint( $s['gallery_post'] ?? 0 ) );
			case 'curator':
				$id = absint( $s['curator'] ?? 0 );
				return $id ? array( 'curator', $id ) : \WPArtCOA\Follows::context_subject( 'curator' );
			case 'artist':
			case 'gallery':
			case 'collector':
				$id = absint( $s['entity_id'] ?? 0 );
				return $id ? array( $source, $id ) : \WPArtCOA\Follows::context_subject( $source );
		}
		return \WPArtCOA\Follows::context_subject();
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$editor  = class_exists( 'Artiey_AET_Elementor_Options' ) && Artiey_AET_Elementor_Options::is_editor_mode();
		$subject = self::resolve_subject( $s );
		$html    = '';
		if ( $subject ) {
			$button = array(
				'label_follow'    => (string) ( $s['label_follow'] ?? '' ),
				'label_following' => (string) ( $s['label_following'] ?? '' ),
				'style'           => (string) ( $s['button_style'] ?? 'solid' ),
				'size'            => (string) ( $s['button_size'] ?? 'md' ),
				'icon'            => ! empty( $s['show_icon'] ),
			);
			$layout = (string) ( $s['layout'] ?? 'button' );
			if ( 'button' === $layout ) {
				$html = \WPArtCOA\Follows::button_html( $subject[0], (int) $subject[1], $button + array(
					'show_count'  => ! empty( $s['show_count'] ),
					'count_label' => (string) ( $s['count_label'] ?? '' ),
				) );
			} else {
				$html = \WPArtCOA\Follows::followers_html( $subject[0], (int) $subject[1], array(
					'avatars'        => 'stats' === $layout ? 0 : (int) ( $s['avatars'] ?? 8 ),
					'show_following' => ! empty( $s['show_following'] ),
					'show_button'    => 'stats' !== $layout,
					'heading'        => 'card' === $layout ? (string) ( $s['heading'] ?? '' ) : '',
					'layout'         => 'card' === $layout ? 'card' : ( 'inline' === $layout ? 'inline' : 'minimal' ),
					'button'         => $button,
				) );
			}
		}
		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Follows.
			return;
		}
		$text = trim( (string) ( $s['empty_text'] ?? '' ) );
		if ( '' !== $text ) {
			echo '<p class="aet-follow-empty">' . esc_html( $text ) . '</p>';
		} elseif ( $editor ) {
			echo '<div style="padding:14px;border:2px dashed #c3c8d0;border-radius:12px;text-align:center;color:#5b6470">' . esc_html( 'پروفایلی برای دکمهٔ دنبال کردن پیدا نشد. ویجت را در قالب پروفایل قرار دهید یا یک پروفایل انتخاب کنید.' ) . '</div>';
		}
	}
}
