<?php
/**
 * REST route tests.
 *
 * BSWP-18/13/16/44/45. The security-critical assertions live here: every route
 * rejects the unauthorised, and no response body ever contains a token.
 *
 * @package BookSurfer
 */

use BookSurfer\Rest;
use BookSurfer\Token_Store;

/**
 * REST surface.
 */
class Test_Rest extends WP_UnitTestCase {

	/**
	 * REST server.
	 *
	 * @var WP_REST_Server
	 */
	private $server;

	/**
	 * Token store.
	 *
	 * @var Token_Store
	 */
	private $store;

	/**
	 * Set up the REST server.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$this->store = new Token_Store();
		$this->store->clear();

		// Nothing in this suite may reach the network. Starting a connect now
		// registers the request with Book Surfer (/oauth/plugin/start), so answer
		// every outbound call with a 404 unless a test installs its own responder
		// (those run later, at the default priority, and win).
		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'response' => array( 'code' => 404 ),
					'body'     => '{}',
				);
			},
			1
		);
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;
		remove_all_filters( 'pre_http_request' );
		$this->store->clear();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Dispatch a request.
	 *
	 * @param string              $method Method.
	 * @param string              $route  Route.
	 * @param array<string,mixed> $params Params.
	 * @return WP_REST_Response
	 */
	private function dispatch( $method, $route, $params = array() ) {
		$request = new WP_REST_Request( $method, '/booksurfer/v1' . $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->server->dispatch( $request );
	}

	/**
	 * All four routes are registered under the right namespace.
	 *
	 * @return void
	 */
	public function test_routes_are_registered() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/booksurfer/v1/status', $routes );
		$this->assertArrayHasKey( '/booksurfer/v1/connect', $routes );
		$this->assertArrayHasKey( '/booksurfer/v1/disconnect', $routes );
		$this->assertArrayHasKey( '/booksurfer/v1/classes', $routes );
	}

	/**
	 * Every route declares an explicit permission_callback. A route that forgets one
	 * (or uses __return_true) is the single most common WordPress plugin security
	 * failure, so we assert it structurally rather than by review.
	 *
	 * @return void
	 */
	public function test_every_route_has_a_real_permission_callback() {
		$routes = $this->server->get_routes();

		foreach ( $routes as $route => $handlers ) {
			if ( 0 !== strpos( $route, '/booksurfer/v1' ) ) {
				continue;
			}
			foreach ( $handlers as $handler ) {
				$this->assertArrayHasKey( 'permission_callback', $handler, "{$route} has no permission_callback" );
				$this->assertNotEmpty( $handler['permission_callback'], "{$route} has an empty permission_callback" );
				$this->assertNotSame( '__return_true', $handler['permission_callback'], "{$route} is wide open" );
			}
		}
	}

	/**
	 * Logged-out users get 401/403 from every route — never data.
	 *
	 * @return void
	 */
	public function test_logged_out_is_refused_everywhere() {
		wp_set_current_user( 0 );

		foreach ( array( array( 'GET', '/status' ), array( 'GET', '/classes' ), array( 'POST', '/connect' ), array( 'POST', '/disconnect' ) ) as $route ) {
			$response = $this->dispatch( $route[0], $route[1] );
			$this->assertContains(
				$response->get_status(),
				array( 401, 403 ),
				"{$route[1]} should refuse anonymous access"
			);
		}
	}

	/**
	 * A subscriber cannot read or write.
	 *
	 * @return void
	 */
	public function test_subscriber_is_refused() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertContains( $this->dispatch( 'GET', '/status' )->get_status(), array( 401, 403 ) );
		$this->assertContains( $this->dispatch( 'GET', '/classes' )->get_status(), array( 401, 403 ) );
	}

	/**
	 * An editor may read status but not change the connection — the read routes are
	 * deliberately looser than the write routes.
	 *
	 * @return void
	 */
	public function test_editor_can_read_but_not_write() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 200, $this->dispatch( 'GET', '/status' )->get_status() );
		$this->assertContains( $this->dispatch( 'POST', '/connect' )->get_status(), array( 401, 403 ) );
		$this->assertContains( $this->dispatch( 'POST', '/disconnect' )->get_status(), array( 401, 403 ) );
	}

	/**
	 * Status reports the never-connected state and never leaks a token.
	 *
	 * @return void
	 */
	public function test_status_never_connected() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->dispatch( 'GET', '/status' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $data['connected'] );
		$this->assertNull( $data['studio'] );
		$this->assertFalse( $data['needs_reconnect'] );
		$this->assertFalse( $data['pending'] );
	}

	/**
	 * Status reports the connected state.
	 *
	 * @return void
	 */
	public function test_status_connected() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->store->set( 'access', 'refresh', 1800 );
		$this->store->set_studio( 'demo-studio', 'Demo Studio' );

		$data = $this->dispatch( 'GET', '/status' )->get_data();

		$this->assertTrue( $data['connected'] );
		$this->assertSame( 'demo-studio', $data['studio']['handle'] );
		$this->assertSame( 'Demo Studio', $data['studio']['name'] );
	}

	/**
	 * No status response may contain a token value — the whole point of the
	 * server-side design.
	 *
	 * @return void
	 */
	public function test_status_never_returns_a_token() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->store->set( 'SECRET-ACCESS-TOKEN', 'SECRET-REFRESH-TOKEN', 1800 );

		$json = wp_json_encode( $this->dispatch( 'GET', '/status' )->get_data() );

		$this->assertStringNotContainsString( 'SECRET-ACCESS-TOKEN', $json );
		$this->assertStringNotContainsString( 'SECRET-REFRESH-TOKEN', $json );
		$this->assertStringNotContainsString( 'refresh_token', $json );
	}

	/**
	 * Status makes no outbound call by default — it is polled every 2s during
	 * connect and hit on every editor load.
	 *
	 * @return void
	 */
	public function test_status_makes_no_outbound_call_by_default() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->store->set( 'access', 'refresh', 1800 );

		$calls = 0;
		add_filter(
			'pre_http_request',
			static function () use ( &$calls ) {
				$calls++;
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{}',
				);
			}
		);

		$this->dispatch( 'GET', '/status' );

		$this->assertSame( 0, $calls );
	}

	/**
	 * POST /connect with no state mints one server-side.
	 *
	 * @return void
	 */
	public function test_connect_mints_state_server_side() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$data = $this->dispatch( 'POST', '/connect' )->get_data();

		$this->assertArrayHasKey( 'state', $data );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $data['state'] );

		// Book Surfer did not register the request here (the default responder
		// answers 404), so the connect goes on without a token or a code.
		$this->assertSame( '', $data['requestToken'] );
		$this->assertSame( '', $data['code'] );
	}

	/**
	 * POST /connect with no state registers the request with Book Surfer and
	 * passes the request token and the connection code to the browser.
	 *
	 * @return void
	 */
	public function test_connect_start_returns_request_token_and_code() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$sent = array();
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$sent ) {
				if ( false !== strpos( $url, '/oauth/plugin/start' ) ) {
					$sent = json_decode( $args['body'], true );
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'request_token' => 'aaa.bbb.ccc',
								'expires_in'    => 600,
								'code'          => 'A1B-2C3',
							)
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$data = $this->dispatch( 'POST', '/connect' )->get_data();

		$this->assertSame( 'aaa.bbb.ccc', $data['requestToken'] );
		$this->assertSame( 'A1B-2C3', $data['code'] );
		$this->assertSame( $data['state'], $sent['state'] );
		$this->assertSame( 'wordpress', $sent['host'] );
	}

	/**
	 * A malformed state is rejected by the args schema before the handler runs.
	 *
	 * @return void
	 */
	public function test_connect_rejects_malformed_state() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->dispatch( 'POST', '/connect', array( 'state' => 'short' ) );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * The connect response carries the studio but never a token.
	 *
	 * @return void
	 */
	public function test_connect_response_contains_no_token() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$state = $this->dispatch( 'POST', '/connect' )->get_data()['state'];

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) {
				if ( false !== strpos( $url, '/oauth/plugin/exchange' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'access_token'  => 'SECRET-ACCESS',
								'refresh_token' => 'SECRET-REFRESH',
								'expires_in'    => 1800,
							)
						),
					);
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'success' => true,
							'classes' => array(),
							'studio'  => array(
								'handle' => 'demo-studio',
								'name'   => 'Demo Studio',
							),
						)
					),
				);
			},
			10,
			3
		);

		$response = $this->dispatch( 'POST', '/connect', array( 'state' => $state ) );
		$json     = wp_json_encode( $response->get_data() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringNotContainsString( 'SECRET-ACCESS', $json );
		$this->assertStringNotContainsString( 'SECRET-REFRESH', $json );
		$this->assertStringContainsString( 'demo-studio', $json );
		$this->assertTrue( $this->store->is_connected() );
	}

	/**
	 * Disconnect clears the connection.
	 *
	 * @return void
	 */
	public function test_disconnect() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->store->set( 'access', 'refresh', 1800 );

		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"ok":true}',
				);
			}
		);

		$response = $this->dispatch( 'POST', '/disconnect' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['connected'] );
		$this->assertFalse( $this->store->is_connected() );
	}

	/**
	 * /classes surfaces the not-connected error cleanly.
	 *
	 * @return void
	 */
	public function test_classes_not_connected() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->dispatch( 'GET', '/classes' );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'booksurfer_not_connected', $response->get_data()['code'] );
	}
}
