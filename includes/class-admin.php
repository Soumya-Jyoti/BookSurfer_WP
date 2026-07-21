<?php
/**
 * Admin settings page: menu registration and React mount shell.
 *
 * BSWP-23/26. PHP renders one empty div; all UI is React. Assets load only on this
 * screen. React is external (wp.element) — never bundled. Bootstrap data is passed
 * via wp_add_inline_script, not scraped from the DOM.
 *
 * @package BookSurfer
 */

namespace BookSurfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Book Surfer admin menu and mounts the settings app.
 */
class Admin {

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	const SLUG = 'book-surfer';

	/**
	 * Script/style handle.
	 *
	 * @var string
	 */
	const HANDLE = 'booksurfer-admin';

	/**
	 * The dashboard URL the plugin links out to.
	 *
	 * @var string
	 */
	const DASHBOARD_URL = 'https://booksurfer.in/dashboard';

	/**
	 * Stored hook suffix for the settings screen.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Hook menu + asset registration.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'maybe_degraded_notice' ) );
	}

	/**
	 * Register the top-level menu page.
	 *
	 * @return void
	 */
	public function add_menu() {
		$this->hook_suffix = add_menu_page(
			__( 'Book Surfer', 'book-surfer' ),
			__( 'Book Surfer', 'book-surfer' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' ),
			'dashicons-calendar-alt',
			58
		);
	}

	/**
	 * Render the mount point and nothing else.
	 *
	 * @return void
	 */
	public function render_page() {
		echo '<div id="booksurfer-admin-root" class="booksurfer-root"></div>';
	}

	/**
	 * Enqueue the admin bundle on this screen only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		$asset_file = BOOKSURFER_PATH . 'build/admin/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			add_action( 'admin_notices', array( $this, 'build_missing_notice' ) );
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			self::HANDLE,
			BOOKSURFER_URL . 'build/admin/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( BOOKSURFER_PATH . 'build/admin/index.css' ) ) {
			wp_enqueue_style(
				self::HANDLE,
				BOOKSURFER_URL . 'build/admin/index.css',
				array(),
				$asset['version']
			);
		}

		// Bootstrap data — not scraped from the DOM, not a token in sight.
		wp_add_inline_script(
			self::HANDLE,
			'window.BookSurferAdmin = ' . wp_json_encode( $this->bootstrap_data() ) . ';',
			'before'
		);

		// Preload /status to kill the first-paint spinner.
		$this->preload_status();

		// JS i18n.
		wp_set_script_translations( self::HANDLE, 'book-surfer', BOOKSURFER_PATH . 'languages' );
	}

	/**
	 * Data handed to the React app.
	 *
	 * @return array<string,mixed>
	 */
	private function bootstrap_data() {
		$store  = Plugin::instance()->token_store();
		$studio = $store->is_connected() ? $store->get_studio() : null;

		return array(
			'restBase'     => Rest::NS,
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			// SSRF-safe API base — respects the booksurfer_api_base filter so admin
			// JS uses the same host PHP does, instead of a hardcoded prod URL (defect 6).
			'apiBase'      => booksurfer_api_base(),
			'appBase'      => defined( 'BOOKSURFER_APP_BASE' ) ? BOOKSURFER_APP_BASE : 'https://booksurfer.in',
			'connectUrl'   => ( defined( 'BOOKSURFER_APP_BASE' ) ? BOOKSURFER_APP_BASE : 'https://booksurfer.in' ) . '/connect/wordpress',
			'dashboardUrl' => self::DASHBOARD_URL,
			'siteUrl'      => home_url( '/' ),
			'status'       => array(
				'connected'       => $store->is_connected(),
				'studio'          => $studio,
				'needs_reconnect' => $store->needs_reconnect(),
				'pending'         => '' !== Plugin::instance()->oauth()->pending_state(),
				'degraded'        => $store->is_degraded(),
			),
		);
	}

	/**
	 * Register a preloading middleware for the status route.
	 *
	 * @return void
	 */
	private function preload_status() {
		if ( ! function_exists( 'rest_preload_api_request' ) ) {
			return;
		}
		$preload = array_reduce(
			array( '/' . Rest::NS . '/status' ),
			'rest_preload_api_request',
			array()
		);
		wp_add_inline_script(
			'wp-api-fetch',
			sprintf(
				'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );',
				wp_json_encode( $preload )
			),
			'after'
		);
	}

	/**
	 * Notice shown when the build output is missing.
	 *
	 * @return void
	 */
	public function build_missing_notice() {
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Book Surfer: the admin assets have not been built yet. Run "npm install && npm run build" in the plugin folder.', 'book-surfer' );
		echo '</p></div>';
	}

	/**
	 * BSWP-12: warn admins when the refresh token could not be encrypted (neither
	 * libsodium nor OpenSSL was available) and is only base64-encoded at rest.
	 *
	 * Shown on every admin screen — not just our settings page — so it is not missed,
	 * but only to users who can act on it.
	 *
	 * @return void
	 */
	public function maybe_degraded_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! Plugin::instance()->token_store()->is_degraded() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Book Surfer: this server has neither the sodium nor the OpenSSL PHP extension, so your connection\'s refresh token is only base64-encoded, not encrypted. Ask your host to enable one of them, then reconnect.', 'book-surfer' );
		echo '</p></div>';
	}
}
