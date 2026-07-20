<?php
/**
 * Lifecycle and uninstall tests.
 *
 * BSWP-9/45. The asymmetry under test: deactivation must KEEP the connection
 * (people deactivate to debug and expect it back), uninstall must remove every
 * trace — verified by direct DB query, not by the UI looking clean.
 *
 * @package BookSurfer
 */

use BookSurfer\Lifecycle;
use BookSurfer\Token_Store;

/**
 * Activation / deactivation / uninstall.
 */
class Test_Lifecycle extends WP_UnitTestCase {

	/**
	 * Token store.
	 *
	 * @var Token_Store
	 */
	private $store;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->store = new Token_Store();
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->store->clear();
		delete_option( 'booksurfer_schema_version' );
		parent::tear_down();
	}

	/**
	 * Count every row the plugin could own.
	 *
	 * @return int
	 */
	private function count_plugin_rows() {
		global $wpdb;
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			"SELECT COUNT(*) FROM {$wpdb->options}
			 WHERE option_name LIKE 'booksurfer\_%'
			    OR option_name LIKE '\_transient\_booksurfer\_%'
			    OR option_name LIKE '\_transient\_timeout\_booksurfer\_%'"
		);
	}

	/**
	 * Activation stamps the schema version.
	 *
	 * @return void
	 */
	public function test_activation_sets_schema_version() {
		delete_option( 'booksurfer_schema_version' );

		Lifecycle::activate();

		$this->assertSame( Lifecycle::SCHEMA_VERSION, (int) get_option( 'booksurfer_schema_version' ) );
	}

	/**
	 * Deactivation clears caches but must NOT delete tokens.
	 *
	 * @return void
	 */
	public function test_deactivation_keeps_the_connection() {
		$this->store->set( 'access', 'refresh', 1800 );
		set_transient( 'booksurfer_classes_abc', array( 'cached' => true ), 300 );

		Lifecycle::deactivate();

		$this->assertTrue( $this->store->is_connected(), 'Deactivation must not disconnect the site.' );
		$this->assertSame( 'refresh', $this->store->get_refresh_token() );
		$this->assertFalse( get_transient( 'booksurfer_classes_abc' ), 'Transients should be flushed.' );
	}

	/**
	 * The full manual flow: activate -> connect -> deactivate -> reactivate keeps
	 * the connection.
	 *
	 * @return void
	 */
	public function test_activate_connect_deactivate_reactivate_keeps_connection() {
		Lifecycle::activate();
		$this->store->set( 'access', 'refresh', 1800 );
		$this->store->set_studio( 'demo-studio', 'Demo Studio' );

		Lifecycle::deactivate();
		Lifecycle::activate();

		$this->assertTrue( $this->store->is_connected() );
		$this->assertSame( 'demo-studio', $this->store->get_studio()['handle'] );
	}

	/**
	 * Uninstall removes every trace, including the encrypted token blob.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_every_trace() {
		// Seed everything the plugin could own.
		$this->store->set( 'access', 'refresh', 1800 );
		$this->store->set_studio( 'demo-studio', 'Demo Studio' );
		update_option( 'booksurfer_schema_version', 1 );
		update_option( 'booksurfer_needs_reconnect', 1 );
		set_transient( 'booksurfer_classes_abc', array( 'cached' => true ), 300 );
		set_transient( 'booksurfer_pending_connect', 'abc', 300 );

		$this->assertGreaterThan( 0, $this->count_plugin_rows() );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'book-surfer/book-surfer.php' );
		}
		require_once dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertSame( 0, $this->count_plugin_rows(), 'Uninstall must leave zero booksurfer_* rows.' );
	}

	/**
	 * The encrypted refresh token specifically must not survive uninstall — leaving
	 * it in the DB is a security issue.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_the_token_blob() {
		$this->store->set( 'access', 'VERY-SECRET-REFRESH', 1800 );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'book-surfer/book-surfer.php' );
		}
		require_once dirname( __DIR__, 2 ) . '/uninstall.php';
		booksurfer_uninstall_site();

		$this->assertFalse( get_option( Token_Store::OPTION, false ) );
		$this->assertFalse( ( new Token_Store() )->is_connected() );
	}
}
