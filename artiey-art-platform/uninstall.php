<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

wp_clear_scheduled_hook( 'art_coa_close_expired_calls' );
delete_option( 'wp_art_coa_hmac_secret' ); // legacy secret from versions before the single certificate engine
wp_clear_scheduled_hook( 'art_coa_close_expired_auctions' );

// Cached timelines are always removed.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_aet_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_aet_' ) . '%'
	)
);

$settings = get_option( 'artiey_aet_options', array() );

// Everything else only if the admin explicitly asked for it on the settings page.
if ( is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] ) ) {
	foreach ( array( 'artiey_exhibition', 'artiey_gallery', 'artiey_artist', 'artiey_artwork' ) as $type ) {
		$ids = get_posts(
			array(
				'post_type'   => $type,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}
	delete_option( 'artiey_aet_options' );
	delete_option( 'artiey_aet_cache_ver' );
	delete_option( 'artiey_aet_ver' );
	foreach ( array( 'artiey_aet_appearance', 'artiey_aet_page_overrides', 'artiey_aet_pages', 'artiey_aet_pages_version' ) as $artiey_opt ) {
		delete_option( $artiey_opt );
	}
	// NOTE: WP COA Pro tables (coa_certificates, coa_events, coa_galleries, coa_person_profiles) and the
	// signing key are intentionally NEVER deleted automatically: certificates are legal-style records.
}
