<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Translation-plugin bridge for a real bilingual front-end language switcher. */
final class Artiey_AET_Language_Switcher {
	public static function init() { add_shortcode( 'artiey_language_switcher', array( __CLASS__, 'render' ) ); }

	public static function render( $atts = array() ) {
		$atts = shortcode_atts( array( 'style' => 'list', 'show_flags' => 'yes', 'show_names' => 'yes', 'class' => '' ), (array) $atts, 'artiey_language_switcher' );
		$html = '';
		if ( function_exists( 'pll_the_languages' ) ) {
			$html = (string) pll_the_languages( array( 'dropdown' => 0, 'show_flags' => 'yes' === $atts['show_flags'], 'show_names' => 'yes' === $atts['show_names'], 'hide_if_empty' => 0, 'echo' => 0 ) );
		} else {
			$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0, 'orderby' => 'code' ) );
			if ( is_array( $languages ) && $languages ) {
				foreach ( $languages as $language ) {
					if ( empty( $language['url'] ) ) { continue; }
					$label = ! empty( $language['native_name'] ) ? $language['native_name'] : ( $language['translated_name'] ?? $language['language_code'] ?? '' );
					$html .= '<a href="' . esc_url( $language['url'] ) . '" hreflang="' . esc_attr( $language['language_code'] ?? '' ) . '" lang="' . esc_attr( $language['language_code'] ?? '' ) . '"' . ( ! empty( $language['active'] ) ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
				}
				$html = '<div class="artiey-language-switcher-list">' . $html . '</div>';
			}
		}
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			if ( current_user_can( 'manage_options' ) ) { return '<!-- Artiey language switcher: install/configure WPML or Polylang and publish translations. -->'; }
			return '';
		}
		$class = sanitize_html_class( $atts['class'] );
		return '<nav class="artiey-language-switcher ' . esc_attr( $class ) . '" aria-label="' . esc_attr__( 'Language selection', 'artiey-aet' ) . '">' . $html . '</nav>';
	}
}
