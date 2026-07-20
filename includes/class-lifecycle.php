<?php
/**
 * Activation and deactivation lifecycle.
 *
 * BSWP-9. Note the asymmetry: deactivation must NOT delete tokens (users
 * deactivate to debug and expect their connection back); uninstall.php deletes
 * everything. Uninstall lives in its own root file because WordPress runs it in
 * an isolated context.
 *
 * @package BookSurfer
 */

namespace BookSurfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static lifecycle handlers referenced by register_activation_hook /
 * register_deactivation_hook. No subsystem is instantiated here.
 */
class Lifecycle {

	/**
	 * Current options schema version. Bump when the stored shape changes so a
	 * migration can run.
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Minimum supported PHP version.
	 *
	 * @var string
	 */
	const MIN_PHP = '7.4';

	/**
	 * Minimum supported WordPress version.
	 *
	 * @var string
	 */
	const MIN_WP = '6.4';

	/**
	 * Activation: verify the environment, then set the schema version.
	 *
	 * @return void
	 */
	public static function activate() {
		self::guard_environment();

		update_option( 'booksurfer_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Deactivation: clear transient/scheduled state only. Tokens are preserved.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::flush_transients();

		// Clear any plugin-scheduled events (none yet, but keep the contract).
		wp_clear_scheduled_hook( 'booksurfer_refresh_cron' );
	}

	/**
	 * Delete all booksurfer transients (used by deactivate and disconnect).
	 *
	 * @return void
	 */
	public static function flush_transients() {
		global $wpdb;

		// Object-cache-safe on a single site: delete the DB rows for our transients.
		$like = $wpdb->esc_like( '_transient_booksurfer_' ) . '%';
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);

		$like_timeout = $wpdb->esc_like( '_transient_timeout_booksurfer_' ) . '%';
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout )
		);

		wp_cache_flush();
	}

	/**
	 * Bail out of activation with a readable notice if the environment is too old.
	 *
	 * @return void
	 */
	private static function guard_environment() {
		$errors = array();

		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			/* translators: 1: required PHP version, 2: current PHP version. */
			$errors[] = sprintf(
				__( 'Book Surfer requires PHP %1$s or newer. This site runs PHP %2$s.', 'book-surfer' ),
				self::MIN_PHP,
				PHP_VERSION
			);
		}

		global $wp_version;
		if ( isset( $wp_version ) && version_compare( $wp_version, self::MIN_WP, '<' ) ) {
			/* translators: 1: required WordPress version, 2: current WordPress version. */
			$errors[] = sprintf(
				__( 'Book Surfer requires WordPress %1$s or newer. This site runs WordPress %2$s.', 'book-surfer' ),
				self::MIN_WP,
				$wp_version
			);
		}

		if ( empty( $errors ) ) {
			return;
		}

		// Deactivate ourselves and show a readable message instead of white-screening.
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		deactivate_plugins( BOOKSURFER_BASENAME );

		wp_die(
			esc_html( implode( ' ', $errors ) ),
			esc_html__( 'Book Surfer — activation failed', 'book-surfer' ),
			array( 'back_link' => true )
		);
	}
}
