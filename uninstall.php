<?php
/**
 * Uninstall cleanup.
 *
 * BSWP-9. Runs when the plugin is deleted from the WordPress admin. Removes every
 * booksurfer_* option and transient, including the encrypted refresh-token blob —
 * leaving that behind after uninstall is a security issue and a WordPress.org
 * requirement to avoid.
 *
 * @package BookSurfer
 */

// Guard: only run in the genuine uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin options and transients for the current site.
 *
 * @return void
 */
function booksurfer_uninstall_site() {
	global $wpdb;

	// Named options (fast path, autoload-safe).
	$options = array(
		'booksurfer_connection',
		'booksurfer_schema_version',
		'booksurfer_refresh_lock',
		'booksurfer_needs_reconnect',
		'booksurfer_crypto_degraded',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Catch-all for any remaining booksurfer_* options and pending-connect transients.
	$like_option    = $wpdb->esc_like( 'booksurfer_' ) . '%';
	$like_transient = $wpdb->esc_like( '_transient_booksurfer_' ) . '%';
	$like_timeout   = $wpdb->esc_like( '_transient_timeout_booksurfer_' ) . '%';

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_option )
	);
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_transient )
	);
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout )
	);
}

// Multisite: iterate every site when network-activated; otherwise clean the single site.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		booksurfer_uninstall_site();
		restore_current_blog();
	}
} else {
	booksurfer_uninstall_site();
}
