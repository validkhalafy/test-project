<?php
if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('\Elementor\Widget_Base')) {
    /** Elementor directory for publicly published artist/collector profile records. */
    final class WP_COA_Pro_Elementor_Profile_Directory_Widget extends \Elementor\Widget_Base {
        public function get_name() { return 'wp-coa-person-directory'; }
        public function get_title() { return __('COA — Artist & Collector Directory', 'wp-coa-pro'); }
        public function get_icon() { return 'eicon-person'; }
        public function get_categories() { return array('wp-coa-pro', 'general'); }
        public function get_style_depends() { return array('wp-coa-pro-components'); }
        public function get_keywords() { return array('artist', 'collector', 'profile', 'directory', 'هنرمند', 'کلکتور', 'مجموعه دار'); }

        protected function register_controls() {
            $this->start_controls_section('content_section', array('label' => __('Directory content', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT));
            $this->add_control('profile_type', array('label' => __('Profile type', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'artist', 'options' => array('artist' => 'هنرمندان / Artists', 'collector' => 'کلکتورها و مجموعه‌ها / Collectors')));
            $this->add_control('title', array('label' => __('Heading', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'شناسنامه هنرمندان', 'label_block' => true, 'dynamic' => array('active' => true)));
            $this->add_control('language', array('label' => __('Display language', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'fa', 'options' => array('fa' => 'فارسی', 'en' => 'English', 'both' => 'فارسی و انگلیسی', 'site' => __('Site language', 'wp-coa-pro'))));
            $this->add_control('columns', array('label' => __('Desktop columns', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '3', 'options' => array('1' => '1', '2' => '2', '3' => '3', '4' => '4')));
            $this->add_control('limit', array('label' => __('Maximum profiles', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 24, 'min' => 1, 'max' => 100));
            foreach (array('featured_only' => __('Featured profiles only', 'wp-coa-pro'), 'show_search' => __('Show search', 'wp-coa-pro'), 'show_bio' => __('Show biography excerpt', 'wp-coa-pro'), 'show_location' => __('Show location', 'wp-coa-pro'), 'show_verified' => __('Show verification badge', 'wp-coa-pro')) as $key => $label) {
                $this->add_control($key, array('label' => $label, 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => in_array($key, array('show_search', 'show_bio', 'show_location', 'show_verified'), true) ? 'yes' : ''));
            }
            $this->end_controls_section();

            $this->start_controls_section('style_section', array('label' => __('Card style', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE));
            $this->add_control('card_background', array('label' => __('Card background', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array('{{WRAPPER}} .wp-coa-person-card, {{WRAPPER}} .aetd-artist-archive .aetp-card' => 'background-color: {{VALUE}};')));
            $this->add_control('primary_color', array('label' => __('Accent color', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#65513b', 'selectors' => array('{{WRAPPER}} .wp-coa-person-directory, {{WRAPPER}} .aetd-artist-archive .aetd' => '--wp-coa-person-primary: {{VALUE}}; --aetp-accent: {{VALUE}};')));
            $this->add_responsive_control('card_padding', array('label' => __('Card padding', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px', 'em'), 'range' => array('px' => array('min' => 8, 'max' => 48), 'em' => array('min' => .5, 'max' => 3)), 'default' => array('unit' => 'px', 'size' => 20), 'selectors' => array('{{WRAPPER}} .wp-coa-person-card-content, {{WRAPPER}} .aetd-artist-archive .aetp-card' => 'padding: {{SIZE}}{{UNIT}};')));
            $this->add_responsive_control('card_radius', array('label' => __('Card radius', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px'), 'range' => array('px' => array('min' => 0, 'max' => 36)), 'default' => array('unit' => 'px', 'size' => 16), 'selectors' => array('{{WRAPPER}} .wp-coa-person-card, {{WRAPPER}} .aetd-artist-archive .aetp-card' => 'border-radius: {{SIZE}}{{UNIT}};')));
            $this->end_controls_section();
        }

        protected function render() {
            if (!class_exists('WP_COA_Pro_Person_Profiles')) { return; }
            $settings = $this->get_settings_for_display();
            echo WP_COA_Pro_Person_Profiles::render_directory(array(
                'type' => isset($settings['profile_type']) ? $settings['profile_type'] : 'artist',
                'title' => isset($settings['title']) ? $settings['title'] : '',
                'language' => isset($settings['language']) ? $settings['language'] : 'fa',
                'columns' => isset($settings['columns']) ? $settings['columns'] : 3,
                'limit' => isset($settings['limit']) ? $settings['limit'] : 24,
                'featured_only' => isset($settings['featured_only']) && $settings['featured_only'] === 'yes' ? '1' : '0',
                'show_search' => isset($settings['show_search']) && $settings['show_search'] === 'yes' ? '1' : '0',
                'show_bio' => isset($settings['show_bio']) && $settings['show_bio'] === 'yes' ? '1' : '0',
                'show_location' => isset($settings['show_location']) && $settings['show_location'] === 'yes' ? '1' : '0',
                'show_verified' => isset($settings['show_verified']) && $settings['show_verified'] === 'yes' ? '1' : '0',
            ));
        }
    }

    /** Public profile details widget: can use an explicit profile or the current profile URL parameters. */
    final class WP_COA_Pro_Elementor_Profile_Detail_Widget extends \Elementor\Widget_Base {
        public function get_name() { return 'wp-coa-person-profile'; }
        public function get_title() { return __('COA — Profile Details', 'wp-coa-pro'); }
        public function get_icon() { return 'eicon-single-page'; }
        public function get_categories() { return array('wp-coa-pro', 'general'); }
        public function get_style_depends() { return array('wp-coa-pro-components'); }
        public function get_keywords() { return array('artist profile', 'collector profile', 'biography', 'portfolio', 'شناسنامه هنرمند', 'شناسنامه کلکتور'); }

        protected function register_controls() {
            $options = array('0' => __('Use profile selected in URL', 'wp-coa-pro'));
            if (class_exists('WP_COA_Pro_Person_Profiles')) {
                foreach ((array) WP_COA_Pro_Person_Profiles::list_profiles('', 'published', 300) as $profile) {
                    $options[(string) $profile->id] = ($profile->profile_type === 'artist' ? 'هنرمند: ' : 'کلکتور: ') . $profile->display_name . ($profile->display_name_en ? ' / ' . $profile->display_name_en : '');
                }
            }
            $this->start_controls_section('content_section', array('label' => __('Profile source', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT));
            $this->add_control('profile_id', array('label' => __('Profile', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $options, 'default' => '0'));
            $this->add_control('profile_type', array('label' => __('Profile type for URL mode', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '', 'options' => array('' => __('Read type from URL', 'wp-coa-pro'), 'artist' => 'هنرمند', 'collector' => 'کلکتور / مجموعه')));
            $this->add_control('language', array('label' => __('Display language', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'fa', 'options' => array('fa' => 'فارسی', 'en' => 'English', 'both' => 'فارسی و انگلیسی', 'site' => __('Site language', 'wp-coa-pro'))));
            $this->add_control('show_works', array('label' => __('Show linked active certificates', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
            $this->add_control('show_contact', array('label' => __('Show public contact details', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
            $this->add_control('show_social', array('label' => __('Show social links', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
            $this->end_controls_section();
            $this->start_controls_section('style_section', array('label' => __('Profile appearance', 'wp-coa-pro'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE));
            $this->add_control('profile_background', array('label' => __('Profile background', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff', 'selectors' => array('{{WRAPPER}} .wp-coa-person-profile' => 'background-color: {{VALUE}};')));
            $this->add_control('profile_accent', array('label' => __('Accent color', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#65513b', 'selectors' => array('{{WRAPPER}} .wp-coa-person-profile' => '--wp-coa-person-primary: {{VALUE}};')));
            $this->add_responsive_control('profile_padding', array('label' => __('Content padding', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px', 'em'), 'range' => array('px' => array('min' => 8, 'max' => 64)), 'default' => array('unit' => 'px', 'size' => 28), 'selectors' => array('{{WRAPPER}} .wp-coa-person-profile-body' => 'padding: {{SIZE}}{{UNIT}};')));
            $this->add_responsive_control('profile_radius', array('label' => __('Profile radius', 'wp-coa-pro'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array('px'), 'range' => array('px' => array('min' => 0, 'max' => 36)), 'default' => array('unit' => 'px', 'size' => 18), 'selectors' => array('{{WRAPPER}} .wp-coa-person-profile' => 'border-radius: {{SIZE}}{{UNIT}};')));
            $this->end_controls_section();
        }

        protected function render() {
            if (!class_exists('WP_COA_Pro_Person_Profiles')) { return; }
            $settings = $this->get_settings_for_display();
            $args = array(
                'language' => isset($settings['language']) ? $settings['language'] : 'fa',
                'show_works' => isset($settings['show_works']) && $settings['show_works'] === 'yes' ? '1' : '0',
                'show_contact' => isset($settings['show_contact']) && $settings['show_contact'] === 'yes' ? '1' : '0',
                'show_social' => isset($settings['show_social']) && $settings['show_social'] === 'yes' ? '1' : '0',
            );
            if (!empty($settings['profile_id']) && absint($settings['profile_id']) > 0) { $args['id'] = absint($settings['profile_id']); }
            elseif (!empty($settings['profile_type'])) { $args['type'] = sanitize_key($settings['profile_type']); }
            echo WP_COA_Pro_Person_Profiles::render_profile_view($args);
        }
    }
}
