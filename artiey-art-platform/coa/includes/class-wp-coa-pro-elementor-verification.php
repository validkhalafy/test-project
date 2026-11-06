<?php
if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('\Elementor\Widget_Base')) {
    /**
     * Elementor front-end verification widget.
     * Controls intentionally use the plugin settings as defaults while allowing
     * per-widget design overrides through Elementor's native style system.
     */
    final class WP_COA_Pro_Elementor_Verification_Widget extends \Elementor\Widget_Base {
        public function get_name() {
            return 'wp-coa-certificate-verification';
        }

        public function get_title() {
            return __('WP COA Certificate Verification', 'wp-coa-pro');
        }

        public function get_icon() {
            return 'eicon-check-circle';
        }

        public function get_categories() {
            return array('wp-coa-pro', 'general');
        }

        public function get_style_depends() {
            return array('wp-coa-pro-components');
        }

        public function get_keywords() {
            return array('coa', 'certificate', 'artwork', 'verify', 'authenticity', 'artiey', 'گواهی', 'اصالت');
        }

        protected function register_controls() {
            $global = class_exists('WP_COA_Pro_Admin') ? WP_COA_Pro_Admin::get_settings() : array();
            $default_title = isset($global['verification_title']) ? $global['verification_title'] : 'استعلام گواهی اصالت اثر هنری';
            $default_description = isset($global['verification_description']) ? $global['verification_description'] : 'کد گواهی را وارد کنید تا وضعیت ثبت و یکپارچگی اطلاعات آن بررسی شود.';
            $default_placeholder = isset($global['verification_placeholder']) ? $global['verification_placeholder'] : 'COA-XXXXXXXX-XXXXXXXXXXXX';
            $default_button = isset($global['verification_button']) ? $global['verification_button'] : 'استعلام گواهی';
            $default_language = isset($global['display_language']) ? $global['display_language'] : 'fa';
            $default_title_en = isset($global['verification_title_en']) ? $global['verification_title_en'] : 'Artwork Certificate Verification';
            $default_description_en = isset($global['verification_description_en']) ? $global['verification_description_en'] : 'Enter the certificate code to check its current status and the integrity of the issuer record.';
            $default_placeholder_en = isset($global['verification_placeholder_en']) ? $global['verification_placeholder_en'] : 'COA-XXXXXXXX-XXXXXXXXXXXX';
            $default_button_en = isset($global['verification_button_en']) ? $global['verification_button_en'] : 'Verify certificate';
            $default_primary = isset($global['primary_color']) ? $global['primary_color'] : '#65513b';
            $default_border = '#dedbd5';
            $default_panel = '#ffffff';

            $this->start_controls_section('section_content', array(
                'label' => __('Content', 'wp-coa-pro'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ));
            $this->add_control('show_title', array(
                'label' => __('Show heading', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'wp-coa-pro'),
                'label_off' => __('Hide', 'wp-coa-pro'),
                'return_value' => 'yes',
                'default' => 'yes',
            ));
            $this->add_control('title', array(
                'label' => __('Heading', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => $default_title,
                'label_block' => true,
                'dynamic' => array('active' => true),
            ));
            $this->add_control('description', array(
                'label' => __('Introductory text', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => $default_description,
                'rows' => 3,
                'dynamic' => array('active' => true),
            ));
            $this->add_control('title_en', array(
                'label' => __('Heading (English)', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => $default_title_en,
                'label_block' => true,
            ));
            $this->add_control('description_en', array(
                'label' => __('Introductory text (English)', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => $default_description_en,
                'rows' => 3,
            ));
            $this->add_control('placeholder', array(
                'label' => __('Input placeholder', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => $default_placeholder,
                'label_block' => true,
            ));
            $this->add_control('placeholder_en', array(
                'label' => __('Input placeholder (English)', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => $default_placeholder_en,
                'label_block' => true,
            ));
            $this->add_control('button_text', array(
                'label' => __('Button label', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => $default_button,
                'label_block' => true,
            ));
            $this->add_control('button_text_en', array(
                'label' => __('Button label (English)', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => $default_button_en,
                'label_block' => true,
            ));
            $this->add_control('language', array(
                'label' => __('Certificate display language', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => $default_language,
                'options' => array(
                    'both' => __('Persian and English', 'wp-coa-pro'),
                    'fa' => __('Persian only', 'wp-coa-pro'),
                    'en' => __('English only', 'wp-coa-pro'),
                    'site' => __('Follow WordPress site locale', 'wp-coa-pro'),
                ),
            ));
            $this->add_control('alignment', array(
                'label' => __('Text alignment', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array('title' => __('Left', 'wp-coa-pro'), 'icon' => 'eicon-text-align-left'),
                    'center' => array('title' => __('Center', 'wp-coa-pro'), 'icon' => 'eicon-text-align-center'),
                    'right' => array('title' => __('Right', 'wp-coa-pro'), 'icon' => 'eicon-text-align-right'),
                ),
                'default' => function_exists('is_rtl') && is_rtl() ? 'right' : 'left',
                'toggle' => true,
            ));
            $this->end_controls_section();

            $this->start_controls_section('section_style_container', array(
                'label' => __('Container', 'wp-coa-pro'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ));
            $this->add_responsive_control('max_width', array(
                'label' => __('Maximum width', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', '%', 'vw'),
                'range' => array(
                    'px' => array('min' => 280, 'max' => 1400),
                    '%' => array('min' => 30, 'max' => 100),
                    'vw' => array('min' => 30, 'max' => 100),
                ),
                'default' => array('unit' => '%', 'size' => 100),
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget' => 'max-width: {{SIZE}}{{UNIT}}; width: 100%;'),
            ));
            $this->add_control('content_text_color', array(
                'label' => __('Body text color', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget' => '--wp-coa-widget-content-text: {{VALUE}};'),
            ));
            $this->add_control('panel_background', array(
                'label' => __('Result panel background', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => $default_panel,
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget' => '--wp-coa-widget-panel: {{VALUE}};'),
            ));
            $this->add_control('border_color', array(
                'label' => __('Input and panel border color', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => $default_border,
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget' => '--wp-coa-widget-border: {{VALUE}};'),
            ));
            $this->add_responsive_control('panel_padding', array(
                'label' => __('Result panel padding', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', 'em'),
                'range' => array('px' => array('min' => 8, 'max' => 64), 'em' => array('min' => 0.5, 'max' => 4)),
                'default' => array('unit' => 'px', 'size' => 18),
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget' => '--wp-coa-widget-panel-padding: {{SIZE}}{{UNIT}};'),
            ));
            $this->add_responsive_control('border_radius', array(
                'label' => __('Corner radius', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 0, 'max' => 32)),
                'default' => array('unit' => 'px', 'size' => 8),
                'selectors' => array(
                    '{{WRAPPER}} .wp-coa-elementor-widget .wp-coa-result' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .wp-coa-elementor-widget .wp-coa-verification-form input, {{WRAPPER}} .wp-coa-elementor-widget .wp-coa-verification-form button' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            ));
            $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), array(
                'name' => 'result_shadow',
                'label' => __('Result panel shadow', 'wp-coa-pro'),
                'selector' => '{{WRAPPER}} .wp-coa-elementor-widget .wp-coa-result',
            ));
            $this->end_controls_section();

            $this->start_controls_section('section_style_heading', array(
                'label' => __('Heading and description', 'wp-coa-pro'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ));
            $this->add_control('title_color', array(
                'label' => __('Heading color', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#222222',
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget .wp-coa-elementor-title' => 'color: {{VALUE}};'),
            ));
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), array(
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .wp-coa-elementor-widget .wp-coa-elementor-title',
            ));
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), array(
                'name' => 'body_typography',
                'selector' => '{{WRAPPER}} .wp-coa-elementor-widget .wp-coa-verification-description, {{WRAPPER}} .wp-coa-elementor-widget .wp-coa-verification-form, {{WRAPPER}} .wp-coa-elementor-widget .wp-coa-result',
            ));
            $this->end_controls_section();

            $this->start_controls_section('section_style_fields', array(
                'label' => __('Form fields and button', 'wp-coa-pro'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ));
            $this->add_control('primary_color', array(
                'label' => __('Button background', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => $default_primary,
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget' => '--wp-coa-widget-primary: {{VALUE}};'),
            ));
            $this->add_control('button_text_color', array(
                'label' => __('Button text color', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget' => '--wp-coa-widget-button-text: {{VALUE}};'),
            ));
            $this->add_control('input_background', array(
                'label' => __('Input background', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget' => '--wp-coa-widget-input-bg: {{VALUE}};'),
            ));
            $this->add_control('input_text_color', array(
                'label' => __('Input text color', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#222222',
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget' => '--wp-coa-widget-input-text: {{VALUE}};'),
            ));
            $this->add_responsive_control('input_padding', array(
                'label' => __('Input padding', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 6, 'max' => 24)),
                'default' => array('unit' => 'px', 'size' => 10),
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget .wp-coa-verification-form input' => 'padding: {{SIZE}}{{UNIT}};'),
            ));
            $this->add_responsive_control('button_padding', array(
                'label' => __('Button padding', 'wp-coa-pro'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 6, 'max' => 28)),
                'default' => array('unit' => 'px', 'size' => 12),
                'selectors' => array('{{WRAPPER}} .wp-coa-elementor-widget .wp-coa-verification-form button' => 'padding: {{SIZE}}{{UNIT}};'),
            ));
            $this->end_controls_section();
        }

        protected function render() {
            if (!class_exists('WP_COA_Pro') || !class_exists('WP_COA_Pro_Admin')) {
                return;
            }
            $settings = $this->get_settings_for_display();
            $global = WP_COA_Pro_Admin::get_settings();
            $language = isset($settings['language']) && in_array($settings['language'], array('fa', 'en', 'both', 'site'), true) ? $settings['language'] : $global['display_language'];
            if (!empty($global['enable_language_switcher']) && isset($_GET['coa_lang']) && is_scalar($_GET['coa_lang'])) {
                $query_language = sanitize_key(wp_unslash((string) $_GET['coa_lang']));
                if (in_array($query_language, array('fa', 'en', 'both'), true)) { $language = $query_language; }
            }
            if ($language === 'site') {
                $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
                $language = strpos(strtolower($locale), 'fa') === 0 ? 'fa' : 'en';
            }
            $title_primary = isset($settings['title']) ? sanitize_text_field($settings['title']) : $global['verification_title'];
            $title_english = isset($settings['title_en']) ? sanitize_text_field($settings['title_en']) : $global['verification_title_en'];
            $description_primary = isset($settings['description']) ? sanitize_textarea_field($settings['description']) : $global['verification_description'];
            $description_english = isset($settings['description_en']) ? sanitize_textarea_field($settings['description_en']) : $global['verification_description_en'];
            $placeholder_primary = isset($settings['placeholder']) ? sanitize_text_field($settings['placeholder']) : $global['verification_placeholder'];
            $placeholder_english = isset($settings['placeholder_en']) ? sanitize_text_field($settings['placeholder_en']) : $global['verification_placeholder_en'];
            $button_primary = isset($settings['button_text']) ? sanitize_text_field($settings['button_text']) : $global['verification_button'];
            $button_english = isset($settings['button_text_en']) ? sanitize_text_field($settings['button_text_en']) : $global['verification_button_en'];
            $title = ($language === 'en') ? ($title_english !== '' ? $title_english : $title_primary) : (($language === 'both' && $title_english !== '') ? $title_primary . ' / ' . $title_english : $title_primary);
            $description = ($language === 'en') ? ($description_english !== '' ? $description_english : $description_primary) : (($language === 'both' && $description_english !== '') ? $description_primary . "\n" . $description_english : $description_primary);
            $placeholder = ($language === 'en') ? ($placeholder_english !== '' ? $placeholder_english : $placeholder_primary) : $placeholder_primary;
            $button_text = ($language === 'en') ? ($button_english !== '' ? $button_english : $button_primary) : (($language === 'both' && $button_english !== '') ? $button_primary . ' / ' . $button_english : $button_primary);
            $primary = isset($settings['primary_color']) && sanitize_hex_color($settings['primary_color']) ? sanitize_hex_color($settings['primary_color']) : $global['primary_color'];
            $border = isset($settings['border_color']) && sanitize_hex_color($settings['border_color']) ? sanitize_hex_color($settings['border_color']) : '#dedbd5';
            $panel = isset($settings['panel_background']) && sanitize_hex_color($settings['panel_background']) ? sanitize_hex_color($settings['panel_background']) : '#ffffff';
            $input_bg = isset($settings['input_background']) && sanitize_hex_color($settings['input_background']) ? sanitize_hex_color($settings['input_background']) : '#ffffff';
            $input_text = isset($settings['input_text_color']) && sanitize_hex_color($settings['input_text_color']) ? sanitize_hex_color($settings['input_text_color']) : '#222222';
            $button_text_color = isset($settings['button_text_color']) && sanitize_hex_color($settings['button_text_color']) ? sanitize_hex_color($settings['button_text_color']) : '#ffffff';
            $content_text = isset($settings['content_text_color']) && sanitize_hex_color($settings['content_text_color']) ? sanitize_hex_color($settings['content_text_color']) : 'inherit';
            $alignment = isset($settings['alignment']) && in_array($settings['alignment'], array('left', 'center', 'right'), true) ? $settings['alignment'] : (is_rtl() ? 'right' : 'left');

            echo '<div class="wp-coa-elementor-widget aap-s-9df678">';
            if (!empty($settings['show_title']) && $title !== '') {
                echo '<h2 class="wp-coa-elementor-title">' . esc_html($title) . '</h2>';
            }
            if ($description !== '') {
                echo '<p class="wp-coa-verification-description">' . esc_html($description) . '</p>';
            }
            echo WP_COA_Pro::render_verification(array(
                'title' => '',
                'description' => '',
                'placeholder' => $placeholder_primary,
                'placeholder_en' => $placeholder_english,
                'button_text' => $button_primary,
                'button_text_en' => $button_english,
                'primary_color' => $primary,
                'border_color' => $border,
                'border_radius' => 8,
                'show_title' => '0',
                'hide_description' => '1',
                'language' => $language,
            ));
            echo '</div>';
        }
    }
}
