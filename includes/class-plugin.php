<?php
/**
 * Singleton bootstrap. Instantiates and wires the subsystems on the correct hooks.
 *
 * BSWP-8. WordPress calls us at the right moments; we register capabilities, we
 * do not run a main loop. No work is done at file-include time.
 *
 * @package BookSurfer
 */

namespace BookSurfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin container.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Encrypted server-side token store.
	 *
	 * @var Token_Store
	 */
	private $token_store;

	/**
	 * OAuth connect / refresh / revoke service.
	 *
	 * @var OAuth
	 */
	private $oauth;

	/**
	 * Authenticated SaaS proxy.
	 *
	 * @var Api_Proxy
	 */
	private $api_proxy;

	/**
	 * REST route registry.
	 *
	 * @var Rest
	 */
	private $rest;

	/**
	 * Gutenberg block subsystem.
	 *
	 * @var Block
	 */
	private $block;

	/**
	 * Shortcode subsystem.
	 *
	 * @var Shortcode
	 */
	private $shortcode;

	/**
	 * Admin settings page subsystem.
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Get the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — wire dependencies but touch no WordPress hooks yet.
	 */
	private function __construct() {
		self::define_constants();

		// Function file (render entry points) — not autoloaded.
		require_once BOOKSURFER_PATH . 'includes/helpers-embed.php';

		$this->token_store = new Token_Store();
		$this->oauth       = new OAuth( $this->token_store );
		$this->api_proxy   = new Api_Proxy( $this->token_store, $this->oauth );

		$this->rest = new Rest( $this->oauth, $this->api_proxy );

		// Block and Shortcode take no services: the render path is deliberately
		// offline — it builds a public iframe URL and makes no SaaS call.
		$this->block     = new Block();
		$this->shortcode = new Shortcode();
		$this->admin     = new Admin();
	}

	/**
	 * Define the runtime constants that depend on WordPress being loaded.
	 *
	 * BOOKSURFER_API_BASE is filterable so staging / the BSWP-5 stub / tests can point
	 * the plugin elsewhere. The base host is constant + filter only — never
	 * user-supplied — which is the SSRF guard (BSWP-44).
	 *
	 * @return void
	 */
	private static function define_constants() {
		if ( ! defined( 'BOOKSURFER_APP_BASE' ) ) {
			// Consent-page host (booksurfer.in). Used to build the connect popup URL.
			define( 'BOOKSURFER_APP_BASE', apply_filters( 'booksurfer_app_base', 'https://booksurfer.in' ) );
		}

		if ( ! defined( 'BOOKSURFER_API_BASE' ) ) {
			// REST API host — already includes /api. All proxy/oauth calls append to this.
			define( 'BOOKSURFER_API_BASE', apply_filters( 'booksurfer_api_base', 'https://api.booksurfer.in/api' ) );
		}
	}

	/**
	 * Register every subsystem on its correct hook. Called once from the main file.
	 *
	 * @return void
	 */
	public function run() {
		// init: block + shortcode.
		$this->block->register();
		$this->shortcode->register();

		// rest_api_init: routes.
		$this->rest->register();

		// admin_menu / admin_enqueue_scripts: settings page + assets.
		$this->admin->register();
	}

	/**
	 * Accessor for the token store (used by tests and lifecycle).
	 *
	 * @return Token_Store
	 */
	public function token_store() {
		return $this->token_store;
	}

	/**
	 * Accessor for the OAuth service.
	 *
	 * @return OAuth
	 */
	public function oauth() {
		return $this->oauth;
	}

	/**
	 * Accessor for the API proxy.
	 *
	 * @return Api_Proxy
	 */
	public function api_proxy() {
		return $this->api_proxy;
	}
}
