<?php
/**
 * Elementor Dynamic Tags for the complete Artiey shortcode library.
 *
 * @package ArtieyArtPlatform
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
    return;
}

/** Register and provide shortcode-backed Elementor Dynamic Tags. */
final class Artiey_AET_Elementor_Shortcode_Dynamic_Tags {

    /** @return array<string,array{title:string,group:string}> */
    public static function definitions() {
        return array(
            // Core Artiey platform.
            'artiey_artist_archive'          => array( 'title' => 'آرشیو هنرمندان', 'group' => 'artiey-platform' ),
            'artiey_artist_field'            => array( 'title' => 'فیلد هنرمند', 'group' => 'artiey-platform', 'context' => 'artist' ),
            'artiey_artist_profile'          => array( 'title' => 'پروفایل هنرمند', 'group' => 'artiey-platform', 'context' => 'artist' ),
            'artiey_artwork_field'           => array( 'title' => 'فیلد اثر هنری', 'group' => 'artiey-platform', 'context' => 'artwork' ),
            'artiey_artwork_profile'         => array( 'title' => 'پروفایل اثر هنری', 'group' => 'artiey-platform', 'context' => 'artwork' ),
            'artiey_charts'                  => array( 'title' => 'نمودارهای آرتی', 'group' => 'artiey-platform' ),
            'artiey_directory'               => array( 'title' => 'فهرست و دایرکتوری', 'group' => 'artiey-platform' ),
            'artiey_galleries'                => array( 'title' => 'فهرست گالری‌ها', 'group' => 'artiey-platform' ),
            'artiey_gallery_field'           => array( 'title' => 'فیلد گالری', 'group' => 'artiey-platform', 'context' => 'gallery' ),
            'artiey_gallery_profile'         => array( 'title' => 'پروفایل گالری', 'group' => 'artiey-platform', 'context' => 'gallery' ),
            'artiey_related_exhibitions'     => array( 'title' => 'نمایشگاه‌های مرتبط', 'group' => 'artiey-platform', 'context' => 'artist-gallery' ),
            'artiey_stats'                   => array( 'title' => 'آمار پلتفرم آرتی', 'group' => 'artiey-platform' ),
            'artiey_timeline'                => array( 'title' => 'تایم‌لاین نمایشگاه‌ها', 'group' => 'artiey-platform' ),

            // Art COA module.
            'art_coa_artist_bio'             => array( 'title' => 'بیوگرافی هنرمند (Art COA)', 'group' => 'artiey-art-coa' ),
            'art_coa_artist_portfolio_upload'=> array( 'title' => 'فرم بارگذاری پورتفولیوی هنرمند', 'group' => 'artiey-art-coa' ),
            'art_coa_artwork_history'        => array( 'title' => 'تاریخچهٔ اثر هنری', 'group' => 'artiey-art-coa' ),
            'art_coa_auction'                => array( 'title' => 'فهرست و جزئیات حراج', 'group' => 'artiey-art-coa' ),
            'art_coa_auction_timeline'       => array( 'title' => 'تایم‌لاین حراج‌ها', 'group' => 'artiey-art-coa' ),
            'art_coa_calendar'               => array( 'title' => 'تقویم رویدادها', 'group' => 'artiey-art-coa' ),
            'art_coa_calls'                  => array( 'title' => 'فراخوان‌های هنری', 'group' => 'artiey-art-coa' ),
            'art_coa_catalog'                => array( 'title' => 'کاتالوگ آثار', 'group' => 'artiey-art-coa' ),
            'art_coa_certificate'            => array( 'title' => 'کارت گواهی اصالت', 'group' => 'artiey-art-coa' ),
            'art_coa_certificate_full'       => array( 'title' => 'گواهی اصالت کامل', 'group' => 'artiey-art-coa' ),
            'art_coa_charts'                 => array( 'title' => 'نمودارهای COA', 'group' => 'artiey-art-coa' ),
            'art_coa_directory'              => array( 'title' => 'دایرکتوری COA', 'group' => 'artiey-art-coa' ),
            'art_coa_follow_button'          => array( 'title' => 'دکمهٔ دنبال‌کردن', 'group' => 'artiey-art-coa' ),
            'art_coa_member_dashboard'       => array( 'title' => 'داشبورد عضو', 'group' => 'artiey-art-coa' ),
            'art_coa_my_follows'              => array( 'title' => 'فهرست دنبال‌شده‌ها', 'group' => 'artiey-art-coa' ),
            'art_coa_portfolio'              => array( 'title' => 'پورتفولیوی آثار', 'group' => 'artiey-art-coa' ),
            'art_coa_register'               => array( 'title' => 'فرم ثبت‌نام', 'group' => 'artiey-art-coa' ),
            'art_coa_search'                 => array( 'title' => 'جست‌وجوی آثار', 'group' => 'artiey-art-coa' ),
            'art_coa_secondary'              => array( 'title' => 'بازار ثانویهٔ آثار', 'group' => 'artiey-art-coa' ),
            'art_coa_shop'                   => array( 'title' => 'فروشگاه آثار', 'group' => 'artiey-art-coa' ),
            'art_coa_sitemap'                => array( 'title' => 'نقشهٔ سایت COA', 'group' => 'artiey-art-coa' ),
            'art_coa_timeline'               => array( 'title' => 'تایم‌لاین تقویمی', 'group' => 'artiey-art-coa' ),
            'art_coa_verify'                 => array( 'title' => 'فرم استعلام گواهی', 'group' => 'artiey-art-coa' ),

            // Legacy COA Pro compatibility shortcodes bundled with the platform.
            'coa_artist_directory'           => array( 'title' => 'فهرست هنرمندان COA', 'group' => 'artiey-coa-pro' ),
            'coa_certificate_guide'          => array( 'title' => 'راهنمای گواهی اصالت', 'group' => 'artiey-coa-pro' ),
            'coa_collector_directory'        => array( 'title' => 'فهرست مجموعه‌داران', 'group' => 'artiey-coa-pro' ),
            'coa_gallery_directory'          => array( 'title' => 'فهرست گالری‌های COA', 'group' => 'artiey-coa-pro' ),
            'coa_gallery_profile'            => array( 'title' => 'پروفایل گالری COA', 'group' => 'artiey-coa-pro' ),
            'coa_profile_directory'          => array( 'title' => 'فهرست پروفایل‌ها', 'group' => 'artiey-coa-pro' ),
            'coa_profile_view'               => array( 'title' => 'نمایش پروفایل COA', 'group' => 'artiey-coa-pro' ),
            'coa_public_stats'               => array( 'title' => 'آمار عمومی گواهی‌ها', 'group' => 'artiey-coa-pro' ),
            'coa_verify'                     => array( 'title' => 'استعلام گواهی COA', 'group' => 'artiey-coa-pro' ),
        );
    }

    /** Register groups, all bundled shortcode tags, and a catch-all tag for other active plugins. */
    public static function register( $manager ) {
        $groups = array(
            'artiey-platform'  => 'پلتفرم آرتی — شورت‌کدها',
            'artiey-art-coa'   => 'Art COA — شورت‌کدها',
            'artiey-coa-pro'   => 'COA — شورت‌کدهای سازگاری',
            'artiey-any-shortcode' => 'شورت‌کدهای سایر افزونه‌ها',
        );

        foreach ( $groups as $slug => $title ) {
            $manager->register_group( $slug, array( 'title' => $title ) );
        }

        foreach ( self::definitions() as $shortcode => $definition ) {
            $manager->register(
                new Artiey_AET_Shortcode_Dynamic_Tag(
                    $shortcode,
                    $definition['title'],
                    $definition['group'],
                    isset( $definition['context'] ) ? $definition['context'] : ''
                )
            );
        }

        // One configurable tag exposes every other shortcode registered by WordPress,
        // including shortcodes supplied by third-party plugins and the active theme.
        $manager->register( new Artiey_AET_Shortcode_Dynamic_Tag( '', 'شورت‌کد دلخواه (تمام افزونه‌های فعال)', 'artiey-any-shortcode', '', true ) );
    }
}

/** One shortcode-backed Dynamic Tag instance. */
final class Artiey_AET_Shortcode_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

    private $shortcode;
    private $title;
    private $group;
    private $context;
    private $generic;

    public function __construct( $shortcode, $title, $group, $context = '', $generic = false ) {
        $this->shortcode = (string) $shortcode;
        $this->title     = (string) $title;
        $this->group     = (string) $group;
        $this->context   = (string) $context;
        $this->generic   = (bool) $generic;
        parent::__construct();
    }

    public function get_name(): string {
        return 'artiey-shortcode-' . ( $this->generic ? 'any' : sanitize_key( $this->shortcode ) );
    }

    public function get_title(): string {
        return $this->title;
    }

    public function get_group(): array {
        return array( $this->group );
    }

    public function get_categories(): array {
        $category_class = '\\Elementor\\Modules\\DynamicTags\\Module';
        $category = class_exists( $category_class ) && defined( $category_class . '::TEXT_CATEGORY' )
            ? constant( $category_class . '::TEXT_CATEGORY' )
            : 'text';
        return array( $category );
    }

    protected function register_controls(): void {
        if ( $this->generic ) {
            $options = self::registered_shortcode_options();
            $default = isset( $options['artiey_artist_field'] ) ? 'artiey_artist_field' : ( $options ? (string) array_key_first( $options ) : '' );
            $this->add_control(
                'shortcode_tag',
                array(
                    'label'       => 'انتخاب شورت‌کد فعال در سایت',
                    'type'        => \Elementor\Controls_Manager::SELECT2,
                    'options'     => $options,
                    'default'     => $default,
                    'label_block' => true,
                    'description' => 'فهرست از تمام شورت‌کدهای ثبت‌شده در وردپرس، از جمله افزونه‌ها و قالب فعال، ساخته می‌شود.',
                )
            );
        }

        $this->add_control(
            'shortcode_attributes',
            array(
                'label'       => 'ویژگی‌ها و پارامترهای شورت‌کد',
                'type'        => \Elementor\Controls_Manager::TEXTAREA,
                'rows'        => 4,
                'label_block' => true,
                'placeholder' => 'field="name" sections="header,bio" limit="8"',
                'description' => 'پارامترها را بدون براکت وارد کنید؛ مانند field="name" یا limit="12". برای چند پارامتر، بین آن‌ها فاصله بگذارید.',
            )
        );

        if ( '' !== $this->context && ! $this->generic ) {
            $this->add_control(
                'use_current_post_context',
                array(
                    'label'        => 'اتصال خودکار به محتوای جاری',
                    'type'         => \Elementor\Controls_Manager::SWITCHER,
                    'label_on'     => 'فعال',
                    'label_off'    => 'غیرفعال',
                    'return_value' => 'yes',
                    'default'      => 'yes',
                    'description'  => 'در قالب تکی، شناسهٔ هنرمند، گالری یا اثر جاری به‌صورت خودکار به شورت‌کد داده می‌شود. مقدار دستی id/artist/gallery اولویت دارد.',
                )
            );
        }

        $this->add_control(
            'shortcode_output_mode',
            array(
                'label'   => 'نحوهٔ خروجی',
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => array(
                    'html' => 'خروجی کامل شورت‌کد (HTML)',
                    'text' => 'فقط متن ساده',
                ),
                'default' => 'html',
            )
        );
    }

    public function render(): void {
        $shortcode = $this->shortcode;
        if ( $this->generic ) {
            $shortcode = sanitize_key( (string) $this->get_settings( 'shortcode_tag' ) );
        }

        if ( '' === $shortcode || ! shortcode_exists( $shortcode ) ) {
            return;
        }

        $raw_attributes = (string) $this->get_settings( 'shortcode_attributes' );
        $attributes = self::parse_attributes( $raw_attributes );

        if ( ! $this->generic && 'yes' === $this->get_settings( 'use_current_post_context' ) ) {
            $attributes = $this->apply_current_post_context( $attributes );
        }

        $shortcode_call = '[' . $shortcode;
        foreach ( $attributes as $key => $value ) {
            $shortcode_call .= ' ' . $key . '="' . esc_attr( $value ) . '"';
        }
        $shortcode_call .= ']';

        // Buffer both return values and incidental echo output for compatibility with older callbacks.
        ob_start();
        $returned = do_shortcode( $shortcode_call );
        $echoed = ob_get_clean();
        $output = (string) $echoed . ( is_scalar( $returned ) ? (string) $returned : '' );

        if ( 'text' === $this->get_settings( 'shortcode_output_mode' ) ) {
            $plain = wp_strip_all_tags( $output, true );
            $plain = html_entity_decode( $plain, ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
            echo esc_html( $plain ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as plain text.
            return;
        }

        // Registered shortcodes are the trusted content-rendering boundary; their callbacks
        // are responsible for escaping user data and may emit forms or chart markup.
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** @return array<string,string> */
    private static function parse_attributes( $raw ) {
        $parsed = function_exists( 'shortcode_parse_atts' ) ? shortcode_parse_atts( (string) $raw ) : array();
        if ( ! is_array( $parsed ) ) {
            return array();
        }

        $attributes = array();
        foreach ( $parsed as $key => $value ) {
            if ( ! is_string( $key ) || ! preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]*$/', $key ) || ! is_scalar( $value ) ) {
                continue;
            }
            $attributes[ strtolower( $key ) ] = (string) $value;
        }
        return $attributes;
    }

    /**
     * Add a safe current-post reference only for shortcodes whose ID parameter
     * is documented to expect a WordPress post ID (never a COA table ID).
     *
     * @param array<string,string> $attributes
     * @return array<string,string>
     */
    private function apply_current_post_context( array $attributes ): array {
        $post_id = function_exists( 'get_the_ID' ) ? absint( get_the_ID() ) : 0;
        if ( ! $post_id && function_exists( 'get_queried_object_id' ) ) {
            $post_id = absint( get_queried_object_id() );
        }
        if ( ! $post_id || ! function_exists( 'get_post_type' ) ) {
            return $attributes;
        }

        $post_type = get_post_type( $post_id );
        $context = $this->context;
        $expected_types = array(
            'artist'  => array( 'artiey_artist' ),
            'gallery' => array( 'artiey_gallery' ),
            'artwork' => array( 'artiey_artwork' ),
        );

        if ( 'artist-gallery' === $context ) {
            if ( 'artiey_artist' === $post_type && empty( $attributes['artist'] ) ) {
                $attributes['artist'] = (string) $post_id;
            } elseif ( 'artiey_gallery' === $post_type && empty( $attributes['gallery'] ) ) {
                $attributes['gallery'] = (string) $post_id;
            }
            return $attributes;
        }

        if ( isset( $expected_types[ $context ] ) && in_array( $post_type, $expected_types[ $context ], true ) && empty( $attributes['id'] ) ) {
            $attributes['id'] = (string) $post_id;
        }
        return $attributes;
    }

    /** @return array<string,string> */
    private static function registered_shortcode_options() {
        $registered = isset( $GLOBALS['shortcode_tags'] ) && is_array( $GLOBALS['shortcode_tags'] )
            ? array_keys( $GLOBALS['shortcode_tags'] )
            : array();
        $options = array();
        foreach ( $registered as $tag ) {
            if ( ! is_string( $tag ) || ! preg_match( '/^[a-zA-Z0-9_-]+$/', $tag ) ) {
                continue;
            }
            $options[ $tag ] = $tag;
        }
        ksort( $options, SORT_NATURAL | SORT_FLAG_CASE );
        return $options;
    }
}
