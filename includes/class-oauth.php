<?php
/**
 * Server-side OAuth: connect, refresh, revoke.
 *
 * BSWP-11/13/14/15. The browser opens the consent popup and polls WordPress; PHP
 * mints the state, performs the exchange, and holds the tokens. The browser never
 * sees a token.
 *
 * Design note (BSWP-13): we do NOT long-poll the exchange endpoint from PHP — that
 * pins a PHP-FPM worker per connecting admin. Each try_exchange() makes one bounded
 * attempt (timeout 10s); the browser drives the 2s poll cadence.
 *
 * @package BookSurfer
 */

namespace BookSurfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OAuth connect / refresh / revoke against the Book Surfer SaaS.
 */
class OAuth {

	/**
	 * Transient holding the single in-flight connect state.
	 *
	 * @var string
	 */
	const PENDING_TRANSIENT = 'booksurfer_pending_connect';

	/**
	 * Atomic single-flight refresh lock option.
	 *
	 * @var string
	 */
	const REFRESH_LOCK = 'booksurfer_refresh_lock';

	/**
	 * Lock time-to-live in seconds. A lock older than this is treated as stale.
	 *
	 * @var int
	 */
	const LOCK_TTL = 15;

	/**
	 * Pending-state TTL (seconds).
	 *
	 * @var int
	 */
	const PENDING_TTL = 600;

	/**
	 * Token store.
	 *
	 * @var Token_Store
	 */
	private $tokens;

	/**
	 * Constructor.
	 *
	 * @param Token_Store $tokens Token store.
	 */
	public function __construct( Token_Store $tokens ) {
		$this->tokens = $tokens;
	}

	/* --------------------------------------------------------------------- */
	/* Connect                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Mint a fresh connect state, record it, and return it to the browser.
	 *
	 * 64 lowercase hex chars — mirrors the Framer plugin's randomState().
	 *
	 * @return string
	 */
	public function start_connect() {
		$state = bin2hex( random_bytes( 32 ) );
		set_transient( self::PENDING_TRANSIENT, $state, self::PENDING_TTL );
		return $state;
	}

	/**
	 * Get the recorded pending state, if any.
	 *
	 * @return string
	 */
	public function pending_state() {
		$state = get_transient( self::PENDING_TRANSIENT );
		return is_string( $state ) ? $state : '';
	}

	/**
	 * Clear the pending connect.
	 *
	 * @return void
	 */
	public function clear_pending() {
		delete_transient( self::PENDING_TRANSIENT );
	}

	/**
	 * Validate a state string's format (before any outbound call).
	 *
	 * @param string $state State.
	 * @return bool
	 */
	public function is_valid_state_format( $state ) {
		return is_string( $state ) && (bool) preg_match( '/^[A-Za-z0-9_-]{32,64}$/', $state );
	}

	/**
	 * Attempt one bounded exchange for the given state.
	 *
	 * The state must match the recorded pending state, so an attacker cannot make
	 * PHP exchange an arbitrary state.
	 *
	 * @param string $state State to exchange.
	 * @return array{status:string,studio?:array,code?:string,message?:string}
	 */
	public function try_exchange( $state ) {
		if ( ! $this->is_valid_state_format( $state ) ) {
			return array(
				'status'  => 'error',
				'code'    => 'booksurfer_bad_state',
				'message' => __( 'Invalid connection state.', 'book-surfer' ),
			);
		}

		if ( ! hash_equals( $this->pending_state(), (string) $state ) ) {
			return array(
				'status'  => 'error',
				'code'    => 'booksurfer_unknown_state',
				'message' => __( 'This connection attempt is no longer active. Please try again.', 'book-surfer' ),
			);
		}

		$response = $this->post_json( '/oauth/plugin/exchange', array( 'state' => $state ) );
		if ( is_wp_error( $response ) ) {
			// Transient network hiccup — stay pending so the browser keeps polling.
			return array( 'status' => 'pending' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 202 === $code ) {
			return array( 'status' => 'pending' );
		}

		if ( 410 === $code ) {
			$this->clear_pending();
			return array(
				'status'  => 'expired',
				'code'    => 'booksurfer_link_expired',
				'message' => __( 'This connection link expired. Please try connecting again.', 'book-surfer' ),
			);
		}

		if ( 200 === $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) || empty( $body['access_token'] ) || empty( $body['refresh_token'] ) ) {
				return array( 'status' => 'pending' );
			}

			$this->tokens->set(
				$body['access_token'],
				$body['refresh_token'],
				isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 1800
			);

			$studio = $this->fetch_studio( (string) $body['access_token'] );
			if ( ! empty( $studio['handle'] ) ) {
				$this->tokens->set_studio( $studio['handle'], $studio['name'] );
			}

			$this->clear_pending();

			return array(
				'status' => 'connected',
				'studio' => $this->tokens->get_studio(),
			);
		}

		// Any other status: treat as still-pending unless it is a hard client error.
		if ( $code >= 400 && $code < 500 ) {
			$this->clear_pending();
			return array(
				'status'  => 'error',
				'code'    => 'booksurfer_connect_failed',
				'message' => __( 'The connection was denied. Please try again.', 'book-surfer' ),
			);
		}

		return array( 'status' => 'pending' );
	}

	/**
	 * Fetch studio metadata with a freshly-minted access token (best-effort).
	 *
	 * @param string $access_token Access token.
	 * @return array{handle:string,name:string}
	 */
	private function fetch_studio( $access_token ) {
		$response = wp_remote_get(
			rtrim( booksurfer_api_base(), '/' ) . '/widget/classes',
			array(
				'timeout'    => 10,
				'sslverify'  => true,
				'headers'    => array( 'Authorization' => 'Bearer ' . $access_token ),
				'user-agent' => $this->user_agent(),
			)
		);

		$out = array(
			'handle' => '',
			'name'   => '',
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $out;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) && isset( $body['studio'] ) && is_array( $body['studio'] ) ) {
			$out['handle'] = isset( $body['studio']['handle'] ) ? (string) $body['studio']['handle'] : '';
			$out['name']   = isset( $body['studio']['name'] ) ? (string) $body['studio']['name'] : '';
		}
		return $out;
	}

	/* --------------------------------------------------------------------- */
	/* Refresh (single-flight)                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Refresh the access token if it is expired (within skew).
	 *
	 * @return string|\WP_Error Fresh access token, or WP_Error on hard failure.
	 */
	public function maybe_refresh() {
		if ( ! $this->tokens->is_connected() ) {
			return new \WP_Error( 'booksurfer_not_connected', __( 'Not connected.', 'book-surfer' ) );
		}
		if ( ! $this->tokens->is_expired( 60 ) ) {
			return $this->tokens->get_access_token();
		}
		return $this->refresh();
	}

	/**
	 * Perform a single-flight token refresh.
	 *
	 * Rotating refresh tokens mean two concurrent refreshes can invalidate the good
	 * token, so we serialise with an atomic add_option() lock. Losers wait and
	 * re-read rather than issuing their own refresh.
	 *
	 * @return string|\WP_Error
	 */
	public function refresh() {
		if ( ! $this->acquire_lock() ) {
			// Someone else is refreshing — wait and re-read (bounded).
			for ( $i = 0; $i < 10; $i++ ) {
				usleep( 300000 ); // 0.3s.
				if ( ! $this->tokens->is_expired( 60 ) ) {
					return $this->tokens->get_access_token();
				}
				if ( ! $this->is_locked() ) {
					break;
				}
			}
			// Fall through: either the winner failed or timed out; try ourselves.
			if ( ! $this->acquire_lock() ) {
				return $this->tokens->is_expired( 60 )
					? new \WP_Error( 'booksurfer_refresh_busy', __( 'Refresh in progress.', 'book-surfer' ) )
					: $this->tokens->get_access_token();
			}
		}

		try {
			$refresh_token = $this->tokens->get_refresh_token();
			if ( '' === $refresh_token ) {
				return new \WP_Error( 'booksurfer_reauth_required', __( 'Please reconnect your Book Surfer account.', 'book-surfer' ) );
			}

			$response = $this->post_json( '/oauth/plugin/refresh', array( 'refresh_token' => $refresh_token ) );
			if ( is_wp_error( $response ) ) {
				return new \WP_Error( 'booksurfer_upstream_error', __( 'Could not reach Book Surfer. Please try again.', 'book-surfer' ) );
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( $code >= 400 && $code < 500 ) {
				// Hard failure (invalid_grant / revoked): the grant is genuinely dead.
				$this->tokens->clear();
				$this->tokens->set_needs_reconnect( true );
				return new \WP_Error( 'booksurfer_reauth_required', __( 'Your Book Surfer session expired. Please reconnect.', 'book-surfer' ) );
			}

			if ( 200 !== $code ) {
				// A 5xx is the SaaS having a bad day, not a revoked grant. Keep the
				// connection — clearing here would disconnect every site during a blip.
				return new \WP_Error( 'booksurfer_upstream_error', __( 'Could not reach Book Surfer. Please try again.', 'book-surfer' ) );
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) || empty( $body['access_token'] ) || empty( $body['refresh_token'] ) ) {
				return new \WP_Error( 'booksurfer_upstream_error', __( 'Unexpected response from Book Surfer.', 'book-surfer' ) );
			}

			// Rotating refresh token replaces the old one atomically (single option write).
			$this->tokens->set(
				$body['access_token'],
				$body['refresh_token'],
				isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 1800
			);

			return (string) $body['access_token'];
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Acquire the atomic refresh lock. Steals a stale lock (> LOCK_TTL).
	 *
	 * @return bool True if acquired.
	 */
	private function acquire_lock() {
		// add_option is atomic in MySQL: it fails if the row already exists.
		if ( add_option( self::REFRESH_LOCK, time(), '', false ) ) {
			return true;
		}

		$held = (int) get_option( self::REFRESH_LOCK, 0 );
		if ( $held > 0 && ( time() - $held ) > self::LOCK_TTL ) {
			// Stale (a fatal mid-refresh left it wedged): steal it.
			delete_option( self::REFRESH_LOCK );
			return (bool) add_option( self::REFRESH_LOCK, time(), '', false );
		}

		return false;
	}

	/**
	 * Is the refresh lock currently held (and fresh)?
	 *
	 * @return bool
	 */
	private function is_locked() {
		$held = (int) get_option( self::REFRESH_LOCK, 0 );
		return $held > 0 && ( time() - $held ) <= self::LOCK_TTL;
	}

	/**
	 * Release the refresh lock.
	 *
	 * @return void
	 */
	private function release_lock() {
		delete_option( self::REFRESH_LOCK );
	}

	/* --------------------------------------------------------------------- */
	/* Revoke                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Revoke the connection server-side, then clear local state.
	 *
	 * Local state is cleared even if the remote revoke fails — a user who clicks
	 * Disconnect must never be left stuck connected. The failure is logged.
	 *
	 * @return void
	 */
	public function revoke() {
		$refresh_token = $this->tokens->get_refresh_token();

		if ( '' !== $refresh_token ) {
			$response = $this->post_json( '/oauth/plugin/revoke', array( 'refresh_token' => $refresh_token ) );
			if ( is_wp_error( $response ) ) {
				booksurfer_log( 'Revoke failed (ignored): ' . $response->get_error_message() );
			}
		}

		$this->tokens->clear();
		$this->clear_pending();
	}

	/* --------------------------------------------------------------------- */
	/* HTTP                                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * POST a JSON body to a SaaS OAuth endpoint. No auth header (state / refresh
	 * token in the body is the only credential).
	 *
	 * @param string              $path Endpoint path, e.g. /oauth/plugin/exchange.
	 * @param array<string,mixed> $body Request body.
	 * @return array|\WP_Error wp_remote_post response.
	 */
	private function post_json( $path, array $body ) {
		return wp_remote_post(
			rtrim( booksurfer_api_base(), '/' ) . $path,
			array(
				'timeout'    => 10,
				'sslverify'  => true,
				'headers'    => array( 'Content-Type' => 'application/json' ),
				'body'       => wp_json_encode( $body ),
				'user-agent' => $this->user_agent(),
			)
		);
	}

	/**
	 * Identifying User-Agent for outbound requests.
	 *
	 * @return string
	 */
	private function user_agent() {
		return sprintf( 'BookSurfer-WP/%s; %s', BOOKSURFER_VERSION, home_url( '/' ) );
	}
}
