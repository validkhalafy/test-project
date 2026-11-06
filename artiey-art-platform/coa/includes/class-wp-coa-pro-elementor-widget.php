<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Register and render the optional Elementor verification widget. */
final class WP_COA_Pro_Elementor_Widget {
    public static function init() {
        add_action('elementor/widgets/register', array(__CLASS__, 'register_widget'));
        add_action('elementor/elements/categories_registered', array(__CLASS__, 'register_category'));
    }

    public static function register_category($elements_manager) {
        if (method_exists($elements_manager, 'add_category')) {
            $elements_manager->add_category('wp-coa-pro', array(
                'title' => __('WP COA Pro', 'wp-coa-pro'),
                'icon' => 'fa fa-certificate',
            ));
        }
    }

    public static function register_widget($widgets_manager) {
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }
        if (!class_exists('WP_COA_Pro_Elementor_Verification_Widget')) {
            require_once WP_COA_PRO_DIR . 'includes/class-wp-coa-pro-elementor-verification.php';
        }
        if (class_exists('WP_COA_Pro_Elementor_Verification_Widget')) {
            $widgets_manager->register(new WP_COA_Pro_Elementor_Verification_Widget());
        }
        require_once WP_COA_PRO_DIR . 'includes/class-wp-coa-pro-elementor-extra-widgets.php';
        require_once WP_COA_PRO_DIR . 'includes/class-wp-coa-pro-elementor-person-widgets.php';
        foreach (array(
            'WP_COA_Pro_Elementor_Gallery_Directory_Widget',
            'WP_COA_Pro_Elementor_Stats_Widget',
            'WP_COA_Pro_Elementor_Gallery_Profile_Widget',
            'WP_COA_Pro_Elementor_Certificate_Guide_Widget',
            'WP_COA_Pro_Elementor_Profile_Directory_Widget',
            'WP_COA_Pro_Elementor_Profile_Detail_Widget',
        ) as $widget_class) {
            if (class_exists($widget_class)) {
                $widgets_manager->register(new $widget_class());
            }
        }
    }
}
