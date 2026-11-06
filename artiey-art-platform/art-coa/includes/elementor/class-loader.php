<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor;

defined('ABSPATH') || exit;

final class Loader
{
    public static function init(): void
    {
        add_action('elementor/widgets/register', [self::class, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [self::class, 'register_category']);
    }

    public static function register_category($elements_manager): void
    {
        $elements_manager->add_category('art-coa', [
            'title' => 'Art COA — گواهی اصالت',
            'icon'  => 'fa fa-certificate',
        ]);
    }

    public static function register_widgets($widgets_manager): void
    {
        require_once WP_ART_COA_DIR . 'includes/elementor/class-design-controls.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-verifier.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-certificate-card.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-calendar.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-timeline.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-artwork-history.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-auction-timeline.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-hierarchy.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-portfolio.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-shop.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-charts.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-artist-bio.php';
        require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-auction-list.php';

        $widgets_manager->register(new Widgets\Verifier());
        $widgets_manager->register(new Widgets\Certificate_Card());
        $widgets_manager->register(new Widgets\Calendar_Widget());
        $widgets_manager->register(new Widgets\Timeline_Widget());
        $widgets_manager->register(new Widgets\Artwork_History_Widget());
        $widgets_manager->register(new Widgets\Auction_Timeline_Widget());
        $widgets_manager->register(new Widgets\Widget_Hierarchy());
        $widgets_manager->register(new Widgets\Widget_Portfolio());
        $widgets_manager->register(new Widgets\Widget_Shop());
        $widgets_manager->register(new Widgets\Charts_Widget());
        $widgets_manager->register(new Widgets\Artist_Bio_Widget());
        $widgets_manager->register(new Widgets\Auction_List_Widget());
    }
}
