<?php
/**
 * OAuth tests.
 *
 * BSWP-13/14/15/45. All outbound HTTP is mocked via pre_http_request — this suite
 * makes zero real network calls.
 *
 * @package BookSurfer
 */

use BookSurfer\OAuth;
use BookSurfer\Token_Store;

/**
 * Connect, refresh (single-flight), and revoke.
 */
class Test_OAuth extends WP_UnitTestCase {

	/**
	 * Token store.
	 *
	 * @var Token_Store
	 */
	private $store;

	/**
	 * Subject.
	 *
	 * @var OAuth
	 */
	private $oauth;

	/**
	 * Recorded outbound requests: list of [ url, body ].
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $requests = array();

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->store    = new Token_Store();
		$this->oauth    = new OAuth( $this->store );
		$this->requests = array();
		$this->store->clear();
		delete_option( OAuth::REFRESH_LOCK );
		delete_transient( OAuth::PENDING_TRANSIENT );
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		$this->store->clear();
		delete_option( OAuth::REFRESH_LOCK );
		delete_transient( OAuth::PENDING_TRANSIENT );
		parent::tear_down();
	}

	/**
	 * Install a canned HTTP responder keyed by URL substring.
	 *
	 * @param array<string,array{code:int,body:mixed}> $routes Map of substring => response.
	 * @return void
	 */
	private function mock_http( array $routes ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $routes ) {
				$this->requests[] = array(
					'url'  => $url,
					'body' => isset( $args['body'] ) ? json_decode( $args['body'], true ) : null,
					'args' => $args,
				);

				foreach ( $routes as $needle => $response ) {
					if ( false !== strpos( $url, $needle ) ) {
						if ( isset( $response['error'] ) ) {
							return new WP_Error( 'http_request_failed', 'boom' );
						}
						return array(
							'response' => array( 'code' => $response['code'] ),
							'body'     => is_string( $response['body'] ) ? $response['body'] : wp_json_encode( $response['body'] ),
						);
					}
				}

				return array(
					'response' => array( 'code' => 404 ),
					'body'     => '{}',
				);
			},
			10,
			3
		);
	}

	/**
	 * Count requests whose URL contains a needle.
	 *
	 * @param string $needle Substring.
	 * @return int
	 */
	private function count_requests( $needle ) {
		return count(
			array_filter(
				$this->requests,
				static function ( $r ) use ( $needle ) {
					return false !== strpos( $r['url'], $needle );
				}
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Connect / exchange                                                    */
	/* --------------------------------------------------------------------- */

	/**
	 * start_connect mints a 64-hex state and records it.
	 *
	 * @return void
	 */
	public function test_start_connect_mints_and_records_state() {
		$state = $this->oauth->start_connect();

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $state );
		$this->assertSame( $state, $this->oauth->pending_state() );
		$this->assertTrue( $this->oauth->is_valid_state_format( $state ) );
	}

	/**
	 * register_connect sends the state, the plugin name and this site's address,
	 * with no auth header, and returns the request token and connection code.
	 *
	 * @return void
	 */
	public function test_register_connect_returns_token_and_code() {
		$state = $this->oauth->start_connect();
		$this->mock_http(
			array(
				'/oauth/plugin/start' => array(
					'code' => 200,
					'body' => array(
						'request_token' => 'aaa.bbb.ccc',
						'expires_in'    => 600,
						'code'          => 'A1B-2C3',
					),
				),
			)
		);

		$result = $this->oauth->register_connect( $state );

		$this->assertSame( 'aaa.bbb.ccc', $result['request_token'] );
		$this->assertSame( 'A1B-2C3', $result['code'] );
		$this->assertSame( 1, $this->count_requests( '/oauth/plugin/start' ) );

		$sent = $this->requests[0];
		$this->assertSame( $state, $sent['body']['state'] );
		$this->assertSame( 'wordpress', $sent['body']['host'] );
		$this->assertSame( home_url( '/' ), $sent['body']['origin'] );
		$this->assertArrayNotHasKey( 'Authorization', $sent['args']['headers'] );
		// Registering must not disturb the pending connect.
		$this->assertSame( $state, $this->oauth->pending_state() );
	}

	/**
	 * A Book Surfer API that does not offer the endpoint (404) leaves both values
	 * empty, so the connect carries on the way it did before.
	 *
	 * @return void
	 */
	public function test_register_connect_is_empty_when_endpoint_is_missing() {
		$state = $this->oauth->start_connect();
		$this->mock_http( array() );

		$result = $this->oauth->register_connect( $state );

		$this->assertSame( '', $result['request_token'] );
		$this->assertSame( '', $result['code'] );
		$this->assertSame( $state, $this->oauth->pending_state() );
	}

	/**
	 * A network error leaves both values empty.
	 *
	 * @return void
	 */
	public function test_register_connect_is_empty_on_network_error() {
		$state = $this->oauth->start_connect();
		$this->mock_http(
			array(
				'/oauth/plugin/start' => array(
					'error' => true,
					'code'  => 0,
					'body'  => '',
				),
			)
		);

		$result = $this->oauth->register_connect( $state );

		$this->assertSame( '', $result['request_token'] );
		$this->assertSame( '', $result['code'] );
	}

	/**
	 * Anything that is not a three-part signed token is not passed to the browser,
	 * and a code in an unexpected shape is dropped.
	 *
	 * @return void
	 */
	public function test_register_connect_ignores_malformed_values() {
		$state = $this->oauth->start_connect();
		$this->mock_http(
			array(
				'/oauth/plugin/start' => array(
					'code' => 200,
					'body' => array(
						'request_token' => 'not a token"><script>',
						'code'          => 'A1B-2C3',
					),
				),
			)
		);
		$this->assertSame( '', $this->oauth->register_connect( $state )['request_token'] );

		remove_all_filters( 'pre_http_request' );
		$this->mock_http(
			array(
				'/oauth/plugin/start' => array(
					'code' => 200,
					'body' => array(
						'request_token' => 'aaa.bbb.ccc',
						'code'          => '<b>oops</b>',
					),
				),
			)
		);
		$result = $this->oauth->register_connect( $state );
		$this->assertSame( 'aaa.bbb.ccc', $result['request_token'] );
		$this->assertSame( '', $result['code'] );
	}

	/**
	 * A malformed state is never sent anywhere.
	 *
	 * @return void
	 */
	public function test_register_connect_makes_no_request_for_a_bad_state() {
		$this->mock_http( array() );

		$result = $this->oauth->register_connect( 'short' );

		$this->assertSame( '', $result['request_token'] );
		$this->assertSame( 0, $this->count_requests( '/oauth/plugin/start' ) );
	}

	/**
	 * A malformed state is rejected before any outbound call.
	 *
	 * @return void
	 */
	public function test_bad_state_format_makes_no_request() {
		$this->mock_http( array() );

		$result = $this->oauth->try_exchange( 'nope' );

		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 'booksurfer_bad_state', $result['code'] );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * An attacker cannot make PHP exchange a state we never minted.
	 *
	 * @return void
	 */
	public function test_unknown_state_is_refused_without_a_request() {
		$this->oauth->start_connect();
		$this->mock_http( array() );

		$attacker = str_repeat( 'a', 64 );
		$result   = $this->oauth->try_exchange( $attacker );

		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 'booksurfer_unknown_state', $result['code'] );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * 202 keeps the flow pending.
	 *
	 * @return void
	 */
	public function test_exchange_202_is_pending() {
		$state = $this->oauth->start_connect();
		$this->mock_http( array( '/oauth/plugin/exchange' => array( 'code' => 202, 'body' => array( 'pending' => true ) ) ) );

		$result = $this->oauth->try_exchange( $state );

		$this->assertSame( 'pending', $result['status'] );
		$this->assertSame( $state, $this->oauth->pending_state() );
		$this->assertFalse( $this->store->is_connected() );
	}

	/**
	 * 200 stores the tokens, records the studio, and burns the pending state.
	 *
	 * @return void
	 */
	public function test_exchange_200_connects() {
		$state = $this->oauth->start_connect();
		$this->mock_http(
			array(
				'/oauth/plugin/exchange' => array(
					'code' => 200,
					'body' => array(
						'access_token'  => 'access-1',
						'refresh_token' => 'refresh-1',
						'expires_in'    => 1800,
					),
				),
				'/widget/classes'        => array(
					'code' => 200,
					'body' => array(
						'success' => true,
						'classes' => array(),
						'studio'  => array(
							'handle' => 'demo-studio',
							'name'   => 'Demo Studio',
						),
					),
				),
			)
		);

		$result = $this->oauth->try_exchange( $state );

		$this->assertSame( 'connected', $result['status'] );
		$this->assertSame( 'demo-studio', $result['studio']['handle'] );
		$this->assertTrue( $this->store->is_connected() );
		$this->assertSame( 'refresh-1', $this->store->get_refresh_token() );
		// State burned.
		$this->assertSame( '', $this->oauth->pending_state() );
	}

	/**
	 * 410 is a terminal expiry that burns the state.
	 *
	 * @return void
	 */
	public function test_exchange_410_expires() {
		$state = $this->oauth->start_connect();
		$this->mock_http( array( '/oauth/plugin/exchange' => array( 'code' => 410, 'body' => array( 'error' => 'expired' ) ) ) );

		$result = $this->oauth->try_exchange( $state );

		$this->assertSame( 'expired', $result['status'] );
		$this->assertSame( '', $this->oauth->pending_state() );
		$this->assertFalse( $this->store->is_connected() );
	}

	/**
	 * A network blip stays pending so the browser keeps polling.
	 *
	 * @return void
	 */
	public function test_exchange_network_error_stays_pending() {
		$state = $this->oauth->start_connect();
		$this->mock_http( array( '/oauth/plugin/exchange' => array( 'error' => true, 'code' => 0, 'body' => '' ) ) );

		$result = $this->oauth->try_exchange( $state );

		$this->assertSame( 'pending', $result['status'] );
		$this->assertSame( $state, $this->oauth->pending_state() );
	}

	/**
	 * The exchange sends only the state, and no Authorization header.
	 *
	 * @return void
	 */
	public function test_exchange_sends_only_state_and_no_auth_header() {
		$state = $this->oauth->start_connect();
		$this->mock_http( array( '/oauth/plugin/exchange' => array( 'code' => 202, 'body' => array() ) ) );

		$this->oauth->try_exchange( $state );

		$request = $this->requests[0];
		$this->assertSame( array( 'state' => $state ), $request['body'] );
		$this->assertArrayNotHasKey( 'Authorization', $request['args']['headers'] );
		$this->assertTrue( $request['args']['sslverify'] );
		$this->assertSame( 10, $request['args']['timeout'] );
	}

	/* --------------------------------------------------------------------- */
	/* Refresh                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * A successful refresh rotates the refresh token atomically.
	 *
	 * @return void
	 */
	public function test_refresh_rotates_tokens() {
		$this->store->set( 'old-access', 'old-refresh', 1800 );
		$this->mock_http(
			array(
				'/oauth/plugin/refresh' => array(
					'code' => 200,
					'body' => array(
						'access_token'  => 'new-access',
						'refresh_token' => 'new-refresh',
						'expires_in'    => 1800,
					),
				),
			)
		);

		$token = $this->oauth->refresh();

		$this->assertSame( 'new-access', $token );
		$this->assertSame( 'new-access', $this->store->get_access_token() );
		$this->assertSame( 'new-refresh', $this->store->get_refresh_token() );
		$this->assertSame( 1, $this->count_requests( '/oauth/plugin/refresh' ) );
	}

	/**
	 * The refresh request carries the refresh token in the body, never a Bearer.
	 *
	 * @return void
	 */
	public function test_refresh_sends_refresh_token_in_body() {
		$this->store->set( 'a', 'the-refresh-token', 1800 );
		$this->mock_http(
			array(
				'/oauth/plugin/refresh' => array(
					'code' => 200,
					'body' => array(
						'access_token'  => 'a2',
						'refresh_token' => 'r2',
						'expires_in'    => 1800,
					),
				),
			)
		);

		$this->oauth->refresh();

		$this->assertSame( array( 'refresh_token' => 'the-refresh-token' ), $this->requests[0]['body'] );
	}

	/**
	 * A hard failure (invalid_grant / revoked) clears the connection and flags a
	 * reconnect rather than leaving a dead token in place.
	 *
	 * @return void
	 */
	public function test_refresh_hard_failure_clears_connection() {
		$this->store->set( 'a', 'r', 1800 );
		$this->mock_http( array( '/oauth/plugin/refresh' => array( 'code' => 400, 'body' => array( 'error' => 'invalid_grant' ) ) ) );

		$result = $this->oauth->refresh();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_reauth_required', $result->get_error_code() );
		$this->assertFalse( $this->store->is_connected() );
		$this->assertTrue( $this->store->needs_reconnect() );
	}

	/**
	 * A transient upstream failure (5xx) must NOT clear the connection — otherwise a
	 * SaaS blip would disconnect every site running the plugin.
	 *
	 * @return void
	 */
	public function test_refresh_5xx_keeps_connection() {
		$this->store->set( 'a', 'r', 1800 );
		$this->mock_http( array( '/oauth/plugin/refresh' => array( 'code' => 503, 'body' => array( 'error' => 'unavailable' ) ) ) );

		$result = $this->oauth->refresh();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_upstream_error', $result->get_error_code() );
		$this->assertTrue( $this->store->is_connected(), 'A 5xx is not a revoked grant.' );
		$this->assertFalse( $this->store->needs_reconnect() );
		$this->assertSame( 'r', $this->store->get_refresh_token() );
	}

	/**
	 * A network failure on refresh also keeps the connection.
	 *
	 * @return void
	 */
	public function test_refresh_network_error_keeps_connection() {
		$this->store->set( 'a', 'r', 1800 );
		$this->mock_http( array( '/oauth/plugin/refresh' => array( 'error' => true, 'code' => 0, 'body' => '' ) ) );

		$result = $this->oauth->refresh();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_upstream_error', $result->get_error_code() );
		$this->assertTrue( $this->store->is_connected() );
	}

	/**
	 * maybe_refresh() is a no-op while the token is still fresh.
	 *
	 * @return void
	 */
	public function test_maybe_refresh_skips_when_token_is_fresh() {
		$this->store->set( 'fresh-access', 'r', 1800 );
		$this->mock_http( array() );

		$this->assertSame( 'fresh-access', $this->oauth->maybe_refresh() );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * maybe_refresh() fires once the token is inside the skew window.
	 *
	 * @return void
	 */
	public function test_maybe_refresh_fires_when_expired() {
		$this->store->set( 'stale', 'r', 1 ); // Expires in 1s: inside the 60s skew.
		$this->mock_http(
			array(
				'/oauth/plugin/refresh' => array(
					'code' => 200,
					'body' => array(
						'access_token'  => 'fresh',
						'refresh_token' => 'r2',
						'expires_in'    => 1800,
					),
				),
			)
		);

		$this->assertSame( 'fresh', $this->oauth->maybe_refresh() );
		$this->assertSame( 1, $this->count_requests( '/oauth/plugin/refresh' ) );
	}

	/**
	 * Single-flight: a caller that loses the lock does NOT issue its own refresh.
	 *
	 * This is the concurrency guarantee — with rotating refresh tokens, two
	 * simultaneous refreshes can invalidate the good token and disconnect the site.
	 *
	 * @return void
	 */
	public function test_single_flight_loser_issues_no_refresh() {
		$this->store->set( 'stale', 'r', 1 );
		$this->mock_http(
			array(
				'/oauth/plugin/refresh' => array(
					'code' => 200,
					'body' => array(
						'access_token'  => 'should-not-happen',
						'refresh_token' => 'r2',
						'expires_in'    => 1800,
					),
				),
			)
		);

		// Simulate a concurrent winner holding a fresh lock.
		add_option( OAuth::REFRESH_LOCK, time(), '', 'no' );

		$result = $this->oauth->refresh();

		$this->assertSame( 0, $this->count_requests( '/oauth/plugin/refresh' ), 'The lock loser must not refresh.' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_refresh_busy', $result->get_error_code() );
	}

	/**
	 * A stale lock (a fatal mid-refresh) is stolen rather than wedging the site
	 * until the TTL passes.
	 *
	 * @return void
	 */
	public function test_stale_lock_is_stolen() {
		$this->store->set( 'stale', 'r', 1 );
		$this->mock_http(
			array(
				'/oauth/plugin/refresh' => array(
					'code' => 200,
					'body' => array(
						'access_token'  => 'recovered',
						'refresh_token' => 'r2',
						'expires_in'    => 1800,
					),
				),
			)
		);

		// A lock left behind well beyond LOCK_TTL.
		add_option( OAuth::REFRESH_LOCK, time() - 600, '', 'no' );

		$this->assertSame( 'recovered', $this->oauth->refresh() );
		$this->assertSame( 1, $this->count_requests( '/oauth/plugin/refresh' ) );
	}

	/**
	 * The lock is always released, including on the failure path.
	 *
	 * @return void
	 */
	public function test_lock_released_after_failure() {
		$this->store->set( 'a', 'r', 1800 );
		$this->mock_http( array( '/oauth/plugin/refresh' => array( 'code' => 400, 'body' => array() ) ) );

		$this->oauth->refresh();

		$this->assertFalse( get_option( OAuth::REFRESH_LOCK, false ) );
	}

	/* --------------------------------------------------------------------- */
	/* Revoke                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Revoke calls the SaaS and clears local state.
	 *
	 * @return void
	 */
	public function test_revoke_calls_saas_and_clears() {
		$this->store->set( 'a', 'the-refresh', 1800 );
		$this->mock_http( array( '/oauth/plugin/revoke' => array( 'code' => 200, 'body' => array( 'ok' => true ) ) ) );

		$this->oauth->revoke();

		$this->assertSame( 1, $this->count_requests( '/oauth/plugin/revoke' ) );
		$this->assertSame( array( 'refresh_token' => 'the-refresh' ), $this->requests[0]['body'] );
		$this->assertFalse( $this->store->is_connected() );
	}

	/**
	 * A user who clicks Disconnect must never be left stuck connected because the
	 * SaaS was briefly down.
	 *
	 * @return void
	 */
	public function test_revoke_clears_local_state_even_when_remote_fails() {
		$this->store->set( 'a', 'r', 1800 );
		$this->mock_http( array( '/oauth/plugin/revoke' => array( 'error' => true, 'code' => 0, 'body' => '' ) ) );

		$this->oauth->revoke();

		$this->assertFalse( $this->store->is_connected() );
	}

	/**
	 * Revoking with no connection makes no request and does not error.
	 *
	 * @return void
	 */
	public function test_revoke_without_connection_is_a_noop() {
		$this->mock_http( array() );

		$this->oauth->revoke();

		$this->assertCount( 0, $this->requests );
		$this->assertFalse( $this->store->is_connected() );
	}
}
