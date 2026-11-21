<?php
/**
 * REST namespace, route registry, and permission callbacks.
 *
 * BSWP-18/13/15/16. All routes live under booksurfer/v1. Every route has an
 * explicit permission_callback (never __return_true), an args schema with
 * sanitize/validate callbacks, and returns WP_Error with stable machine codes.
 *
 * Nonce: @wordpress/api-fetch sends X-WP-Nonce automatically and WordPress's REST
 * cookie auth rejects any cookie-authenticated request without a valid nonce, so
 * the capability checks below run only for genuine, nonce-verified callers.
 *
 * @package BookSurfer
 */

namespace BookSurfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles the plugin's REST routes.
 */
class Rest {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NS = 'booksurfer/v1';

	/**
	 * OAuth service.
	 *
	 * @var OAuth
	 */
	private $oauth;

	/**
	 * API proxy.
	 *
	 * @var Api_Proxy
	 */
	private $proxy;

	/**
	 * Constructor.
	 *
	 * @param OAuth     $oauth OAuth service.
	 * @param Api_Proxy $proxy API proxy.
	 */
	public function __construct( OAuth $oauth, Api_Proxy $proxy ) {
		$this->oauth = $oauth;
		$this->proxy = $proxy;
	}

	/**
	 * Hook route registration onto rest_api_init.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all four routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'can_read' ),
				'args'                => array(
					'verify' => array(
						'type'              => 'boolean',
						'required'          => false,
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/connect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'connect' ),
				'permission_callback' => array( $this, 'can_write' ),
				'args'                => array(
					'state' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_state' ),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => array( $this, 'can_write' ),
			)
		);

		register_rest_route(
			self::NS,
			'/classes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'classes' ),
				'permission_callback' => array( $this, 'can_read' ),
				'args'                => array(
					'refresh' => array(
						'type'              => 'boolean',
						'required'          => false,
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);
	}

	// ---------------------------------------------------------------------
	// Permission + validation
	// ---------------------------------------------------------------------

	/**
	 * Read routes: block editors need to know connection state.
	 *
	 * @return bool
	 */
	public function can_read() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Write routes: only admins may change the connection.
	 *
	 * @return bool
	 */
	public function can_write() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate the state format before it reaches any outbound call.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function validate_state( $value ) {
		return '' === $value || $this->oauth->is_valid_state_format( (string) $value );
	}

	// ---------------------------------------------------------------------
	// Handlers
	// ---------------------------------------------------------------------

	/**
	 * GET /status — connection state. No outbound call unless ?verify=1.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function status( \WP_REST_Request $request ) {
		$store = Plugin::instance()->token_store();

		if ( $request->get_param( 'verify' ) && $store->is_connected() ) {
			// Bounded liveness check; keeps the token warm. Never returns a token.
			$this->oauth->maybe_refresh();
		}

		return new \WP_REST_Response( $this->status_payload(), 200 );
	}

	/**
	 * Build the status payload. Never contains a token.
	 *
	 * @return array<string,mixed>
	 */
	private function status_payload() {
		$store     = Plugin::instance()->token_store();
		$connected = $store->is_connected();
		$studio    = $connected ? $store->get_studio() : null;

		return array(
			'connected'       => $connected,
			'studio'          => $studio,
			'needs_reconnect' => $store->needs_reconnect(),
			'pending'         => '' !== $this->oauth->pending_state(),
			'degraded'        => $store->is_degraded(),
		);
	}

	/**
	 * POST /connect — start a connect (no state) or advance one (with state).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function connect( \WP_REST_Request $request ) {
		$limited = $this->rate_limit( 'connect', 120, 300 );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$state = (string) $request->get_param( 'state' );

		if ( '' === $state ) {
			// Start: mint a server-side state and hand it to the browser.
			return new \WP_REST_Response(
				array(
					'state' => $this->oauth->start_connect(),
				),
				200
			);
		}

		// Advance: one bounded exchange attempt.
		$result = $this->oauth->try_exchange( $state );

		switch ( $result['status'] ) {
			case 'connected':
				$this->proxy->clear_cache();
				return new \WP_REST_Response(
					array(
						'connected' => true,
						'studio'    => $result['studio'],
					),
					200
				);

			case 'pending':
				return new \WP_REST_Response(
					array(
						'connected' => false,
						'pending'   => true,
					),
					200
				);

			case 'expired':
			case 'error':
			default:
				return new \WP_Error(
					$result['code'] ?? 'booksurfer_connect_failed',
					$result['message'] ?? __( 'Connection failed.', 'book-surfer' ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * POST /disconnect — revoke server-side and clear local state.
	 *
	 * @return \WP_REST_Response
	 */
	public function disconnect() {
		// Clear the class cache first: its key derives from the studio handle, which
		// revoke() wipes — clearing after revoke would target a null-studio key and
		// leak the cached list until it expired (defect 5).
		$this->proxy->clear_cache();
		$this->oauth->revoke();
		Lifecycle::flush_transients();

		return new \WP_REST_Response( array( 'connected' => false ), 200 );
	}

	/**
	 * GET /classes — proxied class list.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function classes( \WP_REST_Request $request ) {
		$result = $this->proxy->get_classes( (bool) $request->get_param( 'refresh' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( $result, 200 );
	}

	// ---------------------------------------------------------------------
	// Rate limiting
	// ---------------------------------------------------------------------

	/**
	 * Simple per-user sliding-window rate limit (BSWP-44).
	 *
	 * @param string $bucket Bucket name.
	 * @param int    $max    Max requests in the window.
	 * @param int    $window Window length in seconds.
	 * @return true|\WP_Error
	 */
	private function rate_limit( $bucket, $max, $window ) {
		$key   = 'booksurfer_rl_' . $bucket . '_' . get_current_user_id();
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return new \WP_Error(
				'booksurfer_rate_limited',
				__( 'Too many attempts. Please wait a moment and try again.', 'book-surfer' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, $window );
		return true;
	}
}
