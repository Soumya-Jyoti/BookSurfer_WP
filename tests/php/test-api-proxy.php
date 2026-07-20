<?php
/**
 * API proxy tests.
 *
 * BSWP-19/20/21/45. Covers the happy path, the not-connected guard, the
 * at-most-once 401 refresh-and-replay, upstream failure normalization, and caching.
 * All HTTP is mocked — zero real network calls.
 *
 * @package BookSurfer
 */

use BookSurfer\Api_Proxy;
use BookSurfer\OAuth;
use BookSurfer\Token_Store;

/**
 * Proxy behaviour.
 */
class Test_Api_Proxy extends WP_UnitTestCase {

	/**
	 * Token store.
	 *
	 * @var Token_Store
	 */
	private $store;

	/**
	 * OAuth service.
	 *
	 * @var OAuth
	 */
	private $oauth;

	/**
	 * Subject.
	 *
	 * @var Api_Proxy
	 */
	private $proxy;

	/**
	 * Recorded requests.
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
		$this->proxy    = new Api_Proxy( $this->store, $this->oauth );
		$this->requests = array();

		$this->store->clear();
		delete_option( OAuth::REFRESH_LOCK );
		$this->proxy->clear_cache();
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		$this->proxy->clear_cache();
		$this->store->clear();
		delete_option( OAuth::REFRESH_LOCK );
		parent::tear_down();
	}

	/**
	 * Queue a scripted sequence of responses for /widget/classes, plus a canned
	 * refresh response.
	 *
	 * @param array<int,array{code:int,body:mixed}> $classes_sequence Responses in order.
	 * @param array{code:int,body:mixed}|null       $refresh          Refresh response.
	 * @return void
	 */
	private function mock_http( array $classes_sequence, $refresh = null ) {
		$index = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$index, $classes_sequence, $refresh ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);

				if ( false !== strpos( $url, '/oauth/plugin/refresh' ) ) {
					$response = $refresh ?? array(
						'code' => 200,
						'body' => array(
							'access_token'  => 'refreshed-access',
							'refresh_token' => 'rotated-refresh',
							'expires_in'    => 1800,
						),
					);
					return array(
						'response' => array( 'code' => $response['code'] ),
						'body'     => wp_json_encode( $response['body'] ),
					);
				}

				if ( false !== strpos( $url, '/widget/classes' ) ) {
					$response = $classes_sequence[ $index ] ?? end( $classes_sequence );
					$index++;
					if ( isset( $response['error'] ) ) {
						return new WP_Error( 'http_request_failed', 'network down' );
					}
					return array(
						'response' => array( 'code' => $response['code'] ),
						'body'     => is_string( $response['body'] ) ? $response['body'] : wp_json_encode( $response['body'] ),
					);
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
	 * A successful payload.
	 *
	 * @return array<string,mixed>
	 */
	private function classes_payload() {
		return array(
			'success' => true,
			'classes' => array(
				array(
					'id'         => '1',
					'name'       => 'Sunrise Yoga',
					'class_type' => 'Yoga',
					'instructor' => 'Ana',
					'price'      => '500',
					'currency'   => 'INR',
					'location'   => 'Studio A',
					'embed_url'  => 'https://api.booksurfer.in/api/demo-studio/book/1',
				),
			),
			'studio'  => array(
				'handle' => 'demo-studio',
				'name'   => 'Demo Studio',
			),
		);
	}

	/**
	 * Count requests matching a needle.
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

	/**
	 * Not connected returns a clean typed error, not a PHP notice.
	 *
	 * @return void
	 */
	public function test_not_connected_returns_403() {
		$this->mock_http( array() );

		$result = $this->proxy->get_classes();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_not_connected', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * Happy path returns the payload unchanged in shape.
	 *
	 * @return void
	 */
	public function test_happy_path_passes_payload_through_unchanged() {
		$this->store->set( 'access-1', 'refresh-1', 1800 );
		$this->mock_http( array( array( 'code' => 200, 'body' => $this->classes_payload() ) ) );

		$result = $this->proxy->get_classes();

		$this->assertSame( $this->classes_payload(), $result );
	}

	/**
	 * Outbound requests carry the Bearer, a UA, sslverify and a bounded timeout.
	 *
	 * @return void
	 */
	public function test_request_shape() {
		$this->store->set( 'access-1', 'refresh-1', 1800 );
		$this->mock_http( array( array( 'code' => 200, 'body' => $this->classes_payload() ) ) );

		$this->proxy->get_classes();

		$args = $this->requests[0]['args'];
		$this->assertSame( 'Bearer access-1', $args['headers']['Authorization'] );
		$this->assertTrue( $args['sslverify'] );
		$this->assertSame( 10, $args['timeout'] );
		$this->assertStringContainsString( 'BookSurfer-WP/', $args['user-agent'] );
	}

	/**
	 * A 401 triggers exactly one refresh and one replay, and the replay carries the
	 * NEW token — not the stale one.
	 *
	 * @return void
	 */
	public function test_401_refreshes_once_and_replays_with_new_token() {
		$this->store->set( 'stale-access', 'refresh-1', 1800 );
		$this->mock_http(
			array(
				array( 'code' => 401, 'body' => array( 'error' => 'unauthorized' ) ),
				array( 'code' => 200, 'body' => $this->classes_payload() ),
			)
		);

		$result = $this->proxy->get_classes();

		$this->assertSame( $this->classes_payload(), $result );
		$this->assertSame( 1, $this->count_requests( '/oauth/plugin/refresh' ) );
		$this->assertSame( 2, $this->count_requests( '/widget/classes' ) );

		// The replay used the refreshed token.
		$replay = array_values(
			array_filter(
				$this->requests,
				static function ( $r ) {
					return false !== strpos( $r['url'], '/widget/classes' );
				}
			)
		)[1];
		$this->assertSame( 'Bearer refreshed-access', $replay['args']['headers']['Authorization'] );
	}

	/**
	 * A second 401 after the refresh must not loop — it clears the connection and
	 * asks for a reconnect. This is the infinite-recursion guard.
	 *
	 * @return void
	 */
	public function test_second_401_does_not_recurse() {
		$this->store->set( 'stale-access', 'refresh-1', 1800 );
		$this->mock_http(
			array(
				array( 'code' => 401, 'body' => array() ),
				array( 'code' => 401, 'body' => array() ),
				array( 'code' => 401, 'body' => array() ),
			)
		);

		$result = $this->proxy->get_classes();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_reauth_required', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );

		// Exactly one refresh, exactly two class attempts — no loop.
		$this->assertSame( 1, $this->count_requests( '/oauth/plugin/refresh' ) );
		$this->assertSame( 2, $this->count_requests( '/widget/classes' ) );

		$this->assertFalse( $this->store->is_connected() );
		$this->assertTrue( $this->store->needs_reconnect() );
	}

	/**
	 * If the refresh endpoint is having a bad day (5xx), we surface a transient
	 * upstream error and KEEP the connection — a SaaS blip must not disconnect the
	 * site. Only a 4xx means the grant is genuinely dead.
	 *
	 * @return void
	 */
	public function test_refresh_endpoint_5xx_does_not_disconnect() {
		$this->store->set( 'stale-access', 'refresh-1', 1800 );
		$this->mock_http(
			array( array( 'code' => 401, 'body' => array() ) ),
			array( 'code' => 500, 'body' => array( 'error' => 'boom' ) )
		);

		$result = $this->proxy->get_classes();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_upstream_error', $result->get_error_code() );
		$this->assertSame( 502, $result->get_error_data()['status'] );
		$this->assertSame( 1, $this->count_requests( '/widget/classes' ) );

		// The connection survives a transient upstream failure.
		$this->assertTrue( $this->store->is_connected() );
		$this->assertFalse( $this->store->needs_reconnect() );
	}

	/**
	 * A revoked grant (4xx on refresh) DOES clear the connection.
	 *
	 * @return void
	 */
	public function test_refresh_4xx_after_401_requires_reauth() {
		$this->store->set( 'stale-access', 'refresh-1', 1800 );
		$this->mock_http(
			array( array( 'code' => 401, 'body' => array() ) ),
			array( 'code' => 400, 'body' => array( 'error' => 'invalid_grant' ) )
		);

		$result = $this->proxy->get_classes();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_reauth_required', $result->get_error_code() );
		$this->assertFalse( $this->store->is_connected() );
		$this->assertTrue( $this->store->needs_reconnect() );
	}

	/**
	 * An upstream 500 is normalized and the raw body is never echoed — it can carry
	 * internal detail.
	 *
	 * @return void
	 */
	public function test_upstream_500_is_normalized_and_body_not_echoed() {
		$this->store->set( 'access-1', 'refresh-1', 1800 );
		$this->mock_http( array( array( 'code' => 500, 'body' => 'DB host db-7 password leaked in stack trace' ) ) );

		$result = $this->proxy->get_classes();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_upstream_error', $result->get_error_code() );
		$this->assertSame( 502, $result->get_error_data()['status'] );
		$this->assertStringNotContainsString( 'db-7', $result->get_error_message() );
	}

	/**
	 * A network error / timeout is a 502, not a fatal.
	 *
	 * @return void
	 */
	public function test_network_error_returns_502() {
		$this->store->set( 'access-1', 'refresh-1', 1800 );
		$this->mock_http( array( array( 'error' => true, 'code' => 0, 'body' => '' ) ) );

		$result = $this->proxy->get_classes();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_upstream_error', $result->get_error_code() );
		$this->assertSame( 502, $result->get_error_data()['status'] );
	}

	/**
	 * Malformed JSON is a 502, not a crash.
	 *
	 * @return void
	 */
	public function test_malformed_body_returns_502() {
		$this->store->set( 'access-1', 'refresh-1', 1800 );
		$this->mock_http( array( array( 'code' => 200, 'body' => 'not json at all' ) ) );

		$result = $this->proxy->get_classes();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'booksurfer_upstream_error', $result->get_error_code() );
	}

	/**
	 * Repeated calls hit the cache — one upstream call per TTL window.
	 *
	 * @return void
	 */
	public function test_second_call_is_served_from_cache() {
		$this->store->set( 'access-1', 'refresh-1', 1800 );
		$this->mock_http( array( array( 'code' => 200, 'body' => $this->classes_payload() ) ) );

		$this->proxy->get_classes();
		$this->proxy->get_classes();

		$this->assertSame( 1, $this->count_requests( '/widget/classes' ) );
	}

	/**
	 * ?refresh=1 bypasses the transient.
	 *
	 * @return void
	 */
	public function test_refresh_flag_bypasses_cache() {
		$this->store->set( 'access-1', 'refresh-1', 1800 );
		$this->mock_http( array( array( 'code' => 200, 'body' => $this->classes_payload() ) ) );

		$this->proxy->get_classes();
		$this->proxy->get_classes( true );

		$this->assertSame( 2, $this->count_requests( '/widget/classes' ) );
	}

	/**
	 * clear_cache() forces the next call upstream — this is what stops a new studio
	 * seeing the previous studio's classes.
	 *
	 * @return void
	 */
	public function test_clear_cache_forces_refetch() {
		$this->store->set( 'access-1', 'refresh-1', 1800 );
		$this->mock_http( array( array( 'code' => 200, 'body' => $this->classes_payload() ) ) );

		$this->proxy->get_classes();
		$this->proxy->clear_cache();
		$this->proxy->get_classes();

		$this->assertSame( 2, $this->count_requests( '/widget/classes' ) );
	}

	/**
	 * The TTL is filterable.
	 *
	 * @return void
	 */
	public function test_cache_ttl_is_filterable() {
		$captured = null;
		add_filter(
			'booksurfer_classes_cache_ttl',
			static function ( $ttl ) use ( &$captured ) {
				$captured = $ttl;
				return 30;
			}
		);

		$this->store->set( 'access-1', 'refresh-1', 1800 );
		$this->mock_http( array( array( 'code' => 200, 'body' => $this->classes_payload() ) ) );
		$this->proxy->get_classes();

		$this->assertSame( 300, $captured, 'Default TTL should be 300s.' );
	}
}
