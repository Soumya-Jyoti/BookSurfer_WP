<?php
/**
 * Authenticated proxy to the Book Surfer SaaS widget API.
 *
 * BSWP-19/20/21. Replaces apiClient.ts (Axios + interceptors). The call chain
 * inverts: browser -> WordPress REST (nonce) -> SaaS (Bearer attached in PHP).
 * Secrets never touch client JS, and the raw upstream body is never echoed.
 *
 * @package BookSurfer
 */

namespace BookSurfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Proxies GET /classes with refresh-and-replay and transient caching.
 */
class Api_Proxy {

	/**
	 * Cache-key prefix for the classes transient.
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'booksurfer_classes_';

	/**
	 * Default cache TTL (seconds).
	 *
	 * @var int
	 */
	const CACHE_TTL = 300;

	/**
	 * Token store.
	 *
	 * @var Token_Store
	 */
	private $tokens;

	/**
	 * OAuth service (for refresh).
	 *
	 * @var OAuth
	 */
	private $oauth;

	/**
	 * Constructor.
	 *
	 * @param Token_Store $tokens Token store.
	 * @param OAuth       $oauth  OAuth service.
	 */
	public function __construct( Token_Store $tokens, OAuth $oauth ) {
		$this->tokens = $tokens;
		$this->oauth  = $oauth;
	}

	/**
	 * Fetch the studio's classes, refreshing and caching transparently.
	 *
	 * @param bool $bypass_cache Skip the transient (wired to ?refresh=1).
	 * @return array<string,mixed>|\WP_Error Passed through unchanged in shape.
	 */
	public function get_classes( $bypass_cache = false ) {
		if ( ! $this->tokens->is_connected() ) {
			return new \WP_Error(
				'booksurfer_not_connected',
				__( 'Book Surfer is not connected. Connect your studio in Settings.', 'book-surfer' ),
				array( 'status' => 403 )
			);
		}

		$cache_key = $this->cache_key();
		if ( ! $bypass_cache ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		// Proactive refresh (BSWP-14) before the first attempt.
		$token = $this->oauth->maybe_refresh();
		if ( is_wp_error( $token ) ) {
			return $this->map_token_error( $token );
		}

		// At-most-once refresh-and-replay (BSWP-20): loop runs twice at most,
		// enforced structurally by $replayed rather than by recursion.
		$replayed = false;
		do {
			$response = $this->request_classes( $token );

			if ( is_wp_error( $response ) ) {
				return new \WP_Error(
					'booksurfer_upstream_error',
					__( 'Could not reach Book Surfer. Please try again shortly.', 'book-surfer' ),
					array( 'status' => 502 )
				);
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( 401 === $code && ! $replayed ) {
				$replayed  = true;
				$new_token = $this->oauth->refresh();
				if ( is_wp_error( $new_token ) ) {
					return $this->map_token_error( $new_token );
				}
				$token = $new_token; // Replay carries the fresh token.
				continue;
			}

			if ( 401 === $code ) {
				// Second 401 after refresh: hard reauth.
				$this->tokens->clear();
				$this->tokens->set_needs_reconnect( true );
				return new \WP_Error(
					'booksurfer_reauth_required',
					__( 'Your Book Surfer session expired. Please reconnect.', 'book-surfer' ),
					array( 'status' => 401 )
				);
			}

			if ( 200 !== $code ) {
				// Never echo the raw upstream body — it can carry internal detail.
				return new \WP_Error(
					'booksurfer_upstream_error',
					__( 'Book Surfer returned an unexpected response.', 'book-surfer' ),
					array( 'status' => 502 )
				);
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) ) {
				return new \WP_Error(
					'booksurfer_upstream_error',
					__( 'Book Surfer returned malformed data.', 'book-surfer' ),
					array( 'status' => 502 )
				);
			}

			// Keep studio metadata fresh for the render path.
			if ( isset( $body['studio']['handle'] ) ) {
				$this->tokens->set_studio(
					(string) $body['studio']['handle'],
					isset( $body['studio']['name'] ) ? (string) $body['studio']['name'] : ''
				);
			}

			$ttl = (int) apply_filters( 'booksurfer_classes_cache_ttl', self::CACHE_TTL );
			set_transient( $this->cache_key(), $body, max( 0, $ttl ) );

			return $body;
		} while ( true );
	}

	/**
	 * Invalidate the classes cache. Called on connect and disconnect so a new
	 * studio never sees the previous studio's classes.
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_transient( $this->cache_key() );
	}

	/**
	 * The per-studio, per-version cache key.
	 *
	 * @return string
	 */
	private function cache_key() {
		$studio = $this->tokens->get_studio();
		$handle = $studio ? $studio['handle'] : '';
		return self::CACHE_PREFIX . md5( $handle . '|' . BOOKSURFER_VERSION );
	}

	/**
	 * Perform the authenticated GET.
	 *
	 * @param string $token Access token.
	 * @return array|\WP_Error
	 */
	private function request_classes( $token ) {
		return wp_remote_get(
			rtrim( booksurfer_api_base(), '/' ) . '/widget/classes',
			array(
				'timeout'    => 10,
				'sslverify'  => true,
				'headers'    => array( 'Authorization' => 'Bearer ' . $token ),
				'user-agent' => sprintf( 'BookSurfer-WP/%s; %s', BOOKSURFER_VERSION, home_url( '/' ) ),
			)
		);
	}

	/**
	 * Map an OAuth WP_Error into a proxy-shaped error with an HTTP status.
	 *
	 * @param \WP_Error $error OAuth error.
	 * @return \WP_Error
	 */
	private function map_token_error( \WP_Error $error ) {
		if ( 'booksurfer_reauth_required' === $error->get_error_code() ) {
			return new \WP_Error(
				'booksurfer_reauth_required',
				$error->get_error_message(),
				array( 'status' => 401 )
			);
		}
		return new \WP_Error(
			'booksurfer_upstream_error',
			$error->get_error_message(),
			array( 'status' => 502 )
		);
	}
}
