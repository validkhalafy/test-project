<?php
if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('\\Elementor\\Widget_Base')) {
    /** Responsive gallery/institution directory widget. */
    final class WP_COA_Pro_Elementor_Gallery_Directory_Widget extends \Elementor\Widget_Base {
        public function get_name() { return 'wp-coa-gallery-directory'; }
        public function get_title() { return __('COA — Gallery Directory', 'wp-coa-pro'); }
        public function get_icon() { return 'eicon-posts-grid'; }
        public function get_categories() { return array('wp-coa-pro', 'general'); }
        public function get_style_depends() { return array('wp-coa-pro-components'); }
        public function get_keywords() { return array('gallery', 'institution', 'directory', 'activity', 'گالری', 'مؤسسه'); }

        protected function register_controls() {
            $this->start_controls_section('section_content', array('label' => __('Directory content', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT));
            $this->add_control('title', array('label' => __('Heading', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'گالری‌ها و مؤسسات صادرکننده گواهی اصالت', 'label_block' => true, 'dynamic' => array('active' => true)));
            $this->add_control('language', array('label' => __('Language', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'fa', 'options' => array('both' => __('Persian + English', 'wp-coa-pro'), 'fa' => __('Persian', 'wp-coa-pro'), 'en' => __('English', 'wp-coa-pro'), 'site' => __('Site language', 'wp-coa-pro'))));
            $this->add_control('columns', array('label' => __('Columns (desktop)', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '3', 'options' => array('1' => '1', '2' => '2', '3' => '3', '4' => '4')));
            $this->add_control('columns_tablet', array('label' => __('Columns (tablet)', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '2', 'options' => array('1' => '1', '2' => '2', '3' => '3')));
            $this->add_control('columns_mobile', array('label' => __('Columns (mobile)', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '1', 'options' => array('1' => '1', '2' => '2')));
            $this->add_control('limit', array('label' => __('Maximum profiles', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 48, 'min' => 1, 'max' => 100));
            $this->add_control('show_filters', array('label' => __('Show search and activity filters', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
            $this->add_control('show_logo', array('label' => __('Show institution logo', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
            $this->add_control('show_activity', array('label' => __('Show activity badge', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
            $this->add_control('show_template', array('label' => __('Show certificate template', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'no'));
            $this->end_controls_section();

            $this->start_controls_section('section_style', array('label' => __('Directory styling', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE));
            $this->add_control('primary_color', array('label' => __('Primary color', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#65513b', 'selectors' => array('{{WRAPPER}} .wp-coa-directory' => '--wp-coa-directory-primary: {{VALUE}};')));
            $this->add_control('accent_color', array('label' => __('Accent color', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#b29a68', 'selectors' => array('{{WRAPPER}} .wp-coa-directory' => '--wp-coa-directory-accent: {{VALUE}};')));
            $this->add_control('card_background', array('label' => __('Card background', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array('{{WRAPPER}} .wp-coa-gallery-card' => 'background-color: {{VALUE}};')));
            $this->add_control('card_radius', array('label' => __('Card corner radius', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px'), 'range' => array('px' => array('min' => 0, 'max' => 32)), 'default' => array('unit' => 'px', 'size' => 15), 'selectors' => array('{{WRAPPER}} .wp-coa-gallery-card' => 'border-radius: {{SIZE}}{{UNIT}};')));
            $this->add_responsive_control('card_padding', array('label' => __('Card content padding', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px', 'em'), 'range' => array('px' => array('min' => 8, 'max' => 48), 'em' => array('min' => .5, 'max' => 3)), 'default' => array('unit' => 'px', 'size' => 20), 'selectors' => array('{{WRAPPER}} .wp-coa-gallery-card-content' => 'padding: {{SIZE}}{{UNIT}};')));
            $this->end_controls_section();
        }

        protected function render() {
            if (!class_exists('WP_COA_Pro_Page_Integration')) { return; }
            $s = $this->get_settings_for_display();
            echo WP_COA_Pro_Page_Integration::render_gallery_directory(array(
                'title' => isset($s['title']) ? $s['title'] : '',
                'language' => isset($s['language']) ? $s['language'] : 'fa',
                'columns' => isset($s['columns']) ? $s['columns'] : 3,
                'columns_tablet' => isset($s['columns_tablet']) ? $s['columns_tablet'] : 2,
                'columns_mobile' => isset($s['columns_mobile']) ? $s['columns_mobile'] : 1,
                'limit' => isset($s['limit']) ? $s['limit'] : 48,
                'show_filters' => isset($s['show_filters']) && $s['show_filters'] === 'yes' ? '1' : '0',
                'show_logo' => isset($s['show_logo']) && $s['show_logo'] === 'yes' ? '1' : '0',
                'show_activity' => isset($s['show_activity']) && $s['show_activity'] === 'yes' ? '1' : '0',
                'show_template' => isset($s['show_template']) && $s['show_template'] === 'yes' ? '1' : '0',
            ));
        }
    }

    /** Aggregate, privacy-preserving statistics widget. */
    final class WP_COA_Pro_Elementor_Stats_Widget extends \Elementor\Widget_Base {
        public function get_name() { return 'wp-coa-public-statistics'; }
        public function get_title() { return __('COA — Public Statistics', 'wp-coa-pro'); }
        public function get_icon() { return 'eicon-counter'; }
        public function get_categories() { return array('wp-coa-pro', 'general'); }
        public function get_style_depends() { return array('wp-coa-pro-components'); }
        public function get_keywords() { return array('statistics', 'count', 'trust', 'certificates', 'آمار', 'گواهی'); }

        protected function register_controls() {
            $this->start_controls_section('section_content', array('label' => __('Statistics content', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT));
            $this->add_control('title', array('label' => __('Heading', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'آمار سامانه گواهی اصالت', 'label_block' => true, 'dynamic' => array('active' => true)));
            $this->add_control('language', array('label' => __('Language', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'fa', 'options' => array('both' => __('Persian + English', 'wp-coa-pro'), 'fa' => __('Persian', 'wp-coa-pro'), 'en' => __('English', 'wp-coa-pro'), 'site' => __('Site language', 'wp-coa-pro'))));
            $this->add_control('layout', array('label' => __('Layout', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'cards', 'options' => array('cards' => __('Cards', 'wp-coa-pro'), 'inline' => __('Inline counters', 'wp-coa-pro'))));
            foreach (array('show_total' => __('Show total certificates', 'wp-coa-pro'), 'show_active' => __('Show active certificates', 'wp-coa-pro'), 'show_galleries' => __('Show active institutions', 'wp-coa-pro')) as $key => $label) {
                $this->add_control($key, array('label' => $label, 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
            }
            $this->end_controls_section();
            $this->start_controls_section('section_style', array('label' => __('Statistics styling', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE));
            $this->add_control('primary_color', array('label' => __('Number color', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#65513b', 'selectors' => array('{{WRAPPER}} .wp-coa-public-stats' => '--wp-coa-stats-primary: {{VALUE}};')));
            $this->add_control('accent_color', array('label' => __('Accent color', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#b29a68', 'selectors' => array('{{WRAPPER}} .wp-coa-public-stats' => '--wp-coa-stats-accent: {{VALUE}};')));
            $this->add_control('card_background', array('label' => __('Card background', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#faf8f3', 'selectors' => array('{{WRAPPER}} .wp-coa-stat-card' => 'background: {{VALUE}};')));
            $this->add_responsive_control('card_radius', array('label' => __('Card corner radius', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px'), 'range' => array('px' => array('min' => 0, 'max' => 32)), 'default' => array('unit' => 'px', 'size' => 14), 'selectors' => array('{{WRAPPER}} .wp-coa-stat-card' => 'border-radius: {{SIZE}}{{UNIT}};')));
            $this->end_controls_section();
        }

        protected function render() {
            if (!class_exists('WP_COA_Pro_Page_Integration')) { return; }
            $s = $this->get_settings_for_display();
            echo WP_COA_Pro_Page_Integration::render_public_stats(array(
                'title' => isset($s['title']) ? $s['title'] : '',
                'language' => isset($s['language']) ? $s['language'] : 'fa',
                'layout' => isset($s['layout']) ? $s['layout'] : 'cards',
                'show_total' => isset($s['show_total']) && $s['show_total'] === 'yes' ? '1' : '0',
                'show_active' => isset($s['show_active']) && $s['show_active'] === 'yes' ? '1' : '0',
                'show_galleries' => isset($s['show_galleries']) && $s['show_galleries'] === 'yes' ? '1' : '0',
            ));
        }
    }

    /** Single gallery/institution profile card widget. */
    final class WP_COA_Pro_Elementor_Gallery_Profile_Widget extends \Elementor\Widget_Base {
        public function get_name() { return 'wp-coa-gallery-profile'; }
        public function get_title() { return __('COA — Gallery Profile', 'wp-coa-pro'); }
        public function get_icon() { return 'eicon-site-identity'; }
        public function get_categories() { return array('wp-coa-pro', 'general'); }
        public function get_style_depends() { return array('wp-coa-pro-components'); }
        public function get_keywords() { return array('gallery', 'institution', 'profile', 'brand', 'مؤسسه', 'گالری'); }

        protected function register_controls() {
            $options = array('' => __('Select a profile', 'wp-coa-pro'));
            if (class_exists('WP_COA_Pro_Galleries')) {
                foreach ((array) WP_COA_Pro_Galleries::list_profiles(true) as $profile) {
                    $label = $profile->name . (!empty($profile->name_en) ? ' / ' . $profile->name_en : '');
                    $options[(string) $profile->id] = $label;
                }
            }
            $this->start_controls_section('section_content', array('label' => __('Gallery profile', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT));
            $this->add_control('profile_id', array('label' => __('Active gallery or institution', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $options, 'default' => ''));
            $this->add_control('language', array('label' => __('Language', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'fa', 'options' => array('both' => __('Persian + English', 'wp-coa-pro'), 'fa' => __('Persian', 'wp-coa-pro'), 'en' => __('English', 'wp-coa-pro'), 'site' => __('Site language', 'wp-coa-pro'))));
            foreach (array('show_logo' => __('Show logo', 'wp-coa-pro'), 'show_activity' => __('Show activity', 'wp-coa-pro'), 'show_template' => __('Show certificate style', 'wp-coa-pro'), 'show_button' => __('Show verification button', 'wp-coa-pro')) as $key => $label) {
                $this->add_control($key, array('label' => $label, 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
            }
            $this->add_control('button_text', array('label' => __('Button label (Persian / primary)', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'استعلام گواهی'));
            $this->add_control('button_text_en', array('label' => __('Button label (English)', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Verify a certificate'));
            $this->add_control('verification_url', array('label' => __('Custom verification page URL (optional)', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::URL, 'placeholder' => 'https://example.com/coa-verification', 'options' => array('url', 'is_external', 'nofollow')));
            $this->end_controls_section();
            $this->start_controls_section('section_style', array('label' => __('Profile card styling', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE));
            $this->add_control('background_color', array('label' => __('Card background', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#faf8f4', 'selectors' => array('{{WRAPPER}} .wp-coa-gallery-profile-card' => 'background: {{VALUE}};')));
            $this->add_responsive_control('padding', array('label' => __('Card padding', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px', 'em'), 'range' => array('px' => array('min' => 8, 'max' => 64), 'em' => array('min' => .5, 'max' => 4)), 'default' => array('unit' => 'px', 'size' => 24), 'selectors' => array('{{WRAPPER}} .wp-coa-gallery-profile-card' => 'padding: {{SIZE}}{{UNIT}};')));
            $this->add_responsive_control('card_radius', array('label' => __('Card corner radius', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px'), 'range' => array('px' => array('min' => 0, 'max' => 36)), 'default' => array('unit' => 'px', 'size' => 18), 'selectors' => array('{{WRAPPER}} .wp-coa-gallery-profile-card' => 'border-radius: {{SIZE}}{{UNIT}};')));
            $this->end_controls_section();
        }

        protected function render() {
            if (!class_exists('WP_COA_Pro_Page_Integration')) { return; }
            $s = $this->get_settings_for_display();
            if (empty($s['profile_id'])) {
                if (current_user_can('manage_coa_certificates')) { echo '<div class="elementor-alert elementor-alert-info">' . esc_html__('Select an active gallery profile in the widget settings.', 'wp-coa-pro') . '</div>'; }
                return;
            }
            $url = '';
            if (!empty($s['verification_url']['url'])) { $url = esc_url_raw($s['verification_url']['url']); }
            echo WP_COA_Pro_Page_Integration::render_gallery_profile(array(
                'id' => absint($s['profile_id']),
                'language' => isset($s['language']) ? $s['language'] : 'fa',
                'show_logo' => isset($s['show_logo']) && $s['show_logo'] === 'yes' ? '1' : '0',
                'show_activity' => isset($s['show_activity']) && $s['show_activity'] === 'yes' ? '1' : '0',
                'show_template' => isset($s['show_template']) && $s['show_template'] === 'yes' ? '1' : '0',
                'show_button' => isset($s['show_button']) && $s['show_button'] === 'yes' ? '1' : '0',
                'button_text' => isset($s['button_text']) ? $s['button_text'] : '',
                'button_text_en' => isset($s['button_text_en']) ? $s['button_text_en'] : '',
                'verification_url' => $url,
            ));
        }
    }

    /** Clear, bilingual explanation block for public trust and onboarding pages. */
    final class WP_COA_Pro_Elementor_Certificate_Guide_Widget extends \Elementor\Widget_Base {
        public function get_name() { return 'wp-coa-certificate-guide'; }
        public function get_title() { return __('COA — Verification Guide', 'wp-coa-pro'); }
        public function get_icon() { return 'eicon-info-circle-o'; }
        public function get_categories() { return array('wp-coa-pro', 'general'); }
        public function get_style_depends() { return array('wp-coa-pro-components'); }
        public function get_keywords() { return array('guide', 'steps', 'verification', 'trust', 'راهنما', 'اعتبارسنجی'); }

        protected function register_controls() {
            $this->start_controls_section('section_content', array('label' => __('Guide content', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT));
            $this->add_control('title', array('label' => __('Heading', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'راهنمای استعلام گواهی اصالت اثر هنری', 'label_block' => true, 'dynamic' => array('active' => true)));
            $this->add_control('language', array('label' => __('Language', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'fa', 'options' => array('both' => __('Persian + English', 'wp-coa-pro'), 'fa' => __('Persian', 'wp-coa-pro'), 'en' => __('English', 'wp-coa-pro'), 'site' => __('Site language', 'wp-coa-pro'))));
            $this->add_control('show_disclaimer', array('label' => __('Show authenticity limitation notice', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
            $this->end_controls_section();
            $this->start_controls_section('section_style', array('label' => __('Guide styling', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE));
            $this->add_control('primary_color', array('label' => __('Number badge color', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#65513b', 'selectors' => array('{{WRAPPER}} .wp-coa-certificate-guide' => '--wp-coa-guide-primary: {{VALUE}};')));
            $this->add_control('accent_color', array('label' => __('Notice accent color', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#b29a68', 'selectors' => array('{{WRAPPER}} .wp-coa-certificate-guide' => '--wp-coa-guide-accent: {{VALUE}};')));
            $this->add_control('card_background', array('label' => __('Step card background', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array('{{WRAPPER}} .wp-coa-guide-step' => 'background: {{VALUE}};')));
            $this->add_responsive_control('card_padding', array('label' => __('Step card padding', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px', 'em'), 'range' => array('px' => array('min' => 8, 'max' => 48), 'em' => array('min' => .5, 'max' => 3)), 'default' => array('unit' => 'px', 'size' => 20), 'selectors' => array('{{WRAPPER}} .wp-coa-guide-step' => 'padding: {{SIZE}}{{UNIT}};')));
            $this->add_responsive_control('card_radius', array('label' => __('Step card corner radius', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px'), 'range' => array('px' => array('min' => 0, 'max' => 32)), 'default' => array('unit' => 'px', 'size' => 14), 'selectors' => array('{{WRAPPER}} .wp-coa-guide-step' => 'border-radius: {{SIZE}}{{UNIT}};')));
            $this->end_controls_section();
        }

        protected function render() {
            if (!class_exists('WP_COA_Pro_Page_Integration')) { return; }
            $s = $this->get_settings_for_display();
            echo WP_COA_Pro_Page_Integration::render_certificate_guide(array(
                'title' => isset($s['title']) ? $s['title'] : '',
                'language' => isset($s['language']) ? $s['language'] : 'fa',
                'show_disclaimer' => isset($s['show_disclaimer']) && $s['show_disclaimer'] === 'yes' ? '1' : '0',
            ));
        }
    }
}
