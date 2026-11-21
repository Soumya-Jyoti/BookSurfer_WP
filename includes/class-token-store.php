<?php
/**
 * Encrypted, server-side token store.
 *
 * BSWP-12. Replaces the Framer plugin's localStorage entirely. The refresh token
 * is the durable secret, so it is encrypted at rest with a key derived from
 * AUTH_KEY/AUTH_SALT — a DB dump alone is then not enough to impersonate the studio.
 *
 * One connection per site (wp_options is site-wide), stored under a single
 * autoload=no option.
 *
 * @package BookSurfer
 */

namespace BookSurfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the encrypted connection option.
 */
class Token_Store {

	/**
	 * Option name holding the connection blob.
	 *
	 * @var string
	 */
	const OPTION = 'booksurfer_connection';

	/**
	 * Flag option set when a hard refresh failure requires the user to reconnect.
	 *
	 * @var string
	 */
	const RECONNECT_OPTION = 'booksurfer_needs_reconnect';

	/**
	 * Flag option set when no strong crypto is available and we fell back to base64.
	 *
	 * @var string
	 */
	const DEGRADED_OPTION = 'booksurfer_crypto_degraded';

	/**
	 * Stored-shape schema version.
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Get the raw stored connection array (refresh token stays encrypted).
	 *
	 * @return array<string,mixed>|null
	 */
	public function get() {
		$data = get_option( self::OPTION, null );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Persist a token set. Preserves studio metadata unless supplied.
	 *
	 * @param string     $access     Access token.
	 * @param string     $refresh    Refresh token (plaintext; encrypted here).
	 * @param int        $expires_in Access-token lifetime in seconds.
	 * @param array|null $studio     Optional { handle, name } to store.
	 * @return void
	 */
	public function set( $access, $refresh, $expires_in, $studio = null ) {
		$existing = $this->get();

		$expires_in = (int) $expires_in;
		if ( $expires_in <= 0 ) {
			$expires_in = 1800; // Mirror the Framer default (30 min).
		}

		$data = array(
			'access_token'      => (string) $access,
			'refresh_token_enc' => $this->encrypt( (string) $refresh ),
			'expires_at'        => time() + $expires_in,
			'studio_handle'     => isset( $studio['handle'] ) ? (string) $studio['handle'] : ( $existing['studio_handle'] ?? '' ),
			'studio_name'       => isset( $studio['name'] ) ? (string) $studio['name'] : ( $existing['studio_name'] ?? '' ),
			'connected_at'      => $existing['connected_at'] ?? time(),
			'schema_version'    => self::SCHEMA_VERSION,
		);

		// autoload=no: this is never needed on a front-end page load.
		update_option( self::OPTION, $data, false );

		// A successful write means we are no longer in a needs-reconnect state.
		delete_option( self::RECONNECT_OPTION );
	}

	/**
	 * Update only the studio metadata on the existing connection.
	 *
	 * @param string $handle Studio handle.
	 * @param string $name   Studio display name.
	 * @return void
	 */
	public function set_studio( $handle, $name ) {
		$data = $this->get();
		if ( null === $data ) {
			return;
		}
		$data['studio_handle'] = (string) $handle;
		$data['studio_name']   = (string) $name;
		update_option( self::OPTION, $data, false );
	}

	/**
	 * Clear the connection entirely.
	 *
	 * @return void
	 */
	public function clear() {
		delete_option( self::OPTION );
		delete_option( self::RECONNECT_OPTION );
	}

	/**
	 * Is a connection currently held?
	 *
	 * @return bool
	 */
	public function is_connected() {
		$data = $this->get();
		return null !== $data && ! empty( $data['refresh_token_enc'] );
	}

	/**
	 * Get the current (possibly stale) access token.
	 *
	 * @return string
	 */
	public function get_access_token() {
		$data = $this->get();
		return $data['access_token'] ?? '';
	}

	/**
	 * Get the decrypted refresh token.
	 *
	 * On decryption failure (e.g. AUTH_KEY/AUTH_SALT rotated) the connection is
	 * cleared and a needs-reconnect flag is raised, rather than throwing — site
	 * owners do rotate salts and this must not white-screen them.
	 *
	 * @return string Empty string if unavailable.
	 */
	public function get_refresh_token() {
		$data = $this->get();
		if ( null === $data || empty( $data['refresh_token_enc'] ) ) {
			return '';
		}

		$plain = $this->decrypt( (string) $data['refresh_token_enc'] );
		if ( null === $plain ) {
			// Salt rotation / tamper: recover gracefully.
			$this->clear();
			$this->set_needs_reconnect( true );
			return '';
		}

		return $plain;
	}

	/**
	 * Is the access token expired (within a clock-skew cushion)?
	 *
	 * @param int $skew Seconds of cushion. Defaults to 60.
	 * @return bool
	 */
	public function is_expired( $skew = 60 ) {
		$data = $this->get();
		if ( null === $data || empty( $data['expires_at'] ) ) {
			return true;
		}
		return ( (int) $data['expires_at'] - (int) $skew ) <= time();
	}

	/**
	 * Get the connected studio metadata.
	 *
	 * The connected_at value is a Unix timestamp (seconds) of the first successful
	 * connect, surfaced so the admin panel can show "Connected since …" (BSWP-26).
	 *
	 * @return array{handle:string,name:string,connected_at:int}|null
	 */
	public function get_studio() {
		$data = $this->get();
		if ( null === $data ) {
			return null;
		}
		return array(
			'handle'       => (string) ( $data['studio_handle'] ?? '' ),
			'name'         => (string) ( $data['studio_name'] ?? '' ),
			'connected_at' => (int) ( $data['connected_at'] ?? 0 ),
		);
	}

	/**
	 * Read the needs-reconnect flag.
	 *
	 * @return bool
	 */
	public function needs_reconnect() {
		return (bool) get_option( self::RECONNECT_OPTION, false );
	}

	/**
	 * Set or clear the needs-reconnect flag.
	 *
	 * @param bool $value Flag value.
	 * @return void
	 */
	public function set_needs_reconnect( $value ) {
		if ( $value ) {
			update_option( self::RECONNECT_OPTION, 1, false );
		} else {
			delete_option( self::RECONNECT_OPTION );
		}
	}

	/**
	 * Whether the store fell back to base64 (no strong crypto available).
	 *
	 * @return bool
	 */
	public function is_degraded() {
		return (bool) get_option( self::DEGRADED_OPTION, false );
	}

	// ---------------------------------------------------------------------
	// Encryption
	// ---------------------------------------------------------------------

	/**
	 * Derive a 32-byte encryption key from the site's AUTH_KEY/AUTH_SALT.
	 *
	 * @return string 32 raw bytes.
	 */
	private function derive_key() {
		$ikm = '';
		if ( defined( 'AUTH_KEY' ) ) {
			$ikm .= AUTH_KEY;
		}
		if ( defined( 'AUTH_SALT' ) ) {
			$ikm .= AUTH_SALT;
		}
		if ( '' === $ikm && function_exists( 'wp_salt' ) ) {
			$ikm = wp_salt( 'auth' );
		}

		return hash_hkdf( 'sha256', $ikm, 32, 'booksurfer-refresh-token-v1', '' );
	}

	/**
	 * Encrypt a plaintext secret. Prefers libsodium, then OpenSSL AES-256-GCM,
	 * then a base64 fallback (with an admin-notice flag) so it is never fatal.
	 *
	 * Output is a self-describing string: "<method>:<base64>".
	 *
	 * @param string $plaintext Secret to encrypt.
	 * @return string
	 */
	private function encrypt( $plaintext ) {
		$key = $this->derive_key();

		// Preferred: libsodium secretbox (authenticated).
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			return 's1:' . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		// Fallback: OpenSSL AES-256-GCM (authenticated).
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
			if ( false !== $cipher ) {
				return 'o1:' . base64_encode( $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
		}

		// Last resort: base64 only. Not encryption — raise an admin notice.
		update_option( self::DEGRADED_OPTION, 1, false );
		return 'b0:' . base64_encode( $plaintext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a value produced by encrypt(). Returns null on any failure
	 * (bad method, tamper, or salt rotation).
	 *
	 * @param string $stored Stored "<method>:<base64>" string.
	 * @return string|null
	 */
	private function decrypt( $stored ) {
		if ( strlen( $stored ) < 4 || false === strpos( $stored, ':' ) ) {
			return null;
		}

		list( $method, $payload ) = explode( ':', $stored, 2 );
		$raw                      = base64_decode( $payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return null;
		}

		$key = $this->derive_key();

		switch ( $method ) {
			case 's1':
				if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
					return null;
				}
				$nonce_len = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
				if ( strlen( $raw ) <= $nonce_len ) {
					return null;
				}
				$nonce  = substr( $raw, 0, $nonce_len );
				$cipher = substr( $raw, $nonce_len );
				$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
				return false === $plain ? null : $plain;

			case 'o1':
				if ( ! function_exists( 'openssl_decrypt' ) || strlen( $raw ) <= 28 ) {
					return null;
				}
				$iv     = substr( $raw, 0, 12 );
				$tag    = substr( $raw, 12, 16 );
				$cipher = substr( $raw, 28 );
				$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
				return false === $plain ? null : $plain;

			case 'b0':
				return $raw;

			default:
				return null;
		}
	}
}
