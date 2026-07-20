<?php
/**
 * Token store tests.
 *
 * BSWP-12/45. Covers the encrypt/decrypt round-trip, expiry maths, tamper
 * detection (the GCM/secretbox auth tag), and — the one that matters most in
 * production — the salt-rotation path, which must prompt a reconnect rather than
 * white-screen the site.
 *
 * @package BookSurfer
 */

use BookSurfer\Token_Store;

/**
 * Encrypted token store.
 */
class Test_Token_Store extends WP_UnitTestCase {

	/**
	 * Subject.
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
		$this->store->clear();
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->store->clear();
		delete_option( Token_Store::DEGRADED_OPTION );
		parent::tear_down();
	}

	/**
	 * A refresh token survives a round trip.
	 *
	 * @return void
	 */
	public function test_encrypt_decrypt_round_trip() {
		$this->store->set( 'access-abc', 'refresh-xyz', 1800 );
		$this->assertSame( 'refresh-xyz', $this->store->get_refresh_token() );
		$this->assertSame( 'access-abc', $this->store->get_access_token() );
		$this->assertTrue( $this->store->is_connected() );
	}

	/**
	 * The refresh token must never be stored in plaintext.
	 *
	 * @return void
	 */
	public function test_refresh_token_is_not_stored_in_plaintext() {
		$this->store->set( 'access-abc', 'super-secret-refresh', 1800 );
		$raw = get_option( Token_Store::OPTION );

		$this->assertArrayNotHasKey( 'refresh_token', $raw );
		$this->assertArrayHasKey( 'refresh_token_enc', $raw );
		$this->assertStringNotContainsString( 'super-secret-refresh', wp_json_encode( $raw ) );
	}

	/**
	 * The option must not autoload — it is never needed on a front-end page load.
	 *
	 * @return void
	 */
	public function test_option_does_not_autoload() {
		$this->store->set( 'a', 'b', 1800 );

		global $wpdb;
		$autoload = $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Token_Store::OPTION )
		);
		$this->assertNotSame( 'yes', $autoload );
	}

	/**
	 * Expiry maths honours the skew cushion.
	 *
	 * @return void
	 */
	public function test_expiry_maths() {
		$this->store->set( 'a', 'b', 1800 );
		$this->assertFalse( $this->store->is_expired( 60 ) );

		// 30s of life left, 60s skew -> considered expired.
		$data               = $this->store->get();
		$data['expires_at'] = time() + 30;
		update_option( Token_Store::OPTION, $data, false );
		$this->assertTrue( $this->store->is_expired( 60 ) );
		$this->assertFalse( $this->store->is_expired( 10 ) );
	}

	/**
	 * A missing expires_in falls back to the Framer default of 30 minutes.
	 *
	 * @return void
	 */
	public function test_expires_in_defaults_to_1800() {
		$this->store->set( 'a', 'b', 0 );
		$data = $this->store->get();
		$this->assertEqualsWithDelta( time() + 1800, $data['expires_at'], 5 );
	}

	/**
	 * Tampering with the ciphertext is detected (auth tag) and treated as a
	 * rotation: connection cleared, reconnect requested — never an exception.
	 *
	 * @return void
	 */
	public function test_tamper_detection_clears_connection() {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) && ! function_exists( 'openssl_encrypt' ) ) {
			$this->markTestSkipped( 'No strong crypto available; store is in base64 fallback mode.' );
		}

		$this->store->set( 'access', 'refresh-secret', 1800 );

		$data = $this->store->get();
		// Flip a byte in the payload after the method prefix.
		$prefix                    = substr( $data['refresh_token_enc'], 0, 3 );
		$payload                   = substr( $data['refresh_token_enc'], 3 );
		$payload                   = strtr( $payload, array( 'A' => 'B' ) );
		$data['refresh_token_enc'] = $prefix . $payload;
		update_option( Token_Store::OPTION, $data, false );

		$this->assertSame( '', $this->store->get_refresh_token() );
		$this->assertFalse( $this->store->is_connected() );
		$this->assertTrue( $this->store->needs_reconnect() );
	}

	/**
	 * Salt rotation: a value encrypted under different keys cannot be decrypted, so
	 * the store clears and asks for a reconnect instead of throwing.
	 *
	 * @return void
	 */
	public function test_salt_rotation_prompts_reconnect() {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) && ! function_exists( 'openssl_encrypt' ) ) {
			$this->markTestSkipped( 'No strong crypto available.' );
		}

		$this->store->set( 'access', 'refresh-secret', 1800 );

		// Simulate rotated salts by replacing the blob with one encrypted under a
		// different key: a well-formed but undecryptable payload.
		$data                      = $this->store->get();
		$data['refresh_token_enc'] = 'o1:' . base64_encode( random_bytes( 60 ) ); // phpcs:ignore
		update_option( Token_Store::OPTION, $data, false );

		$this->assertSame( '', $this->store->get_refresh_token() );
		$this->assertTrue( $this->store->needs_reconnect() );
		$this->assertFalse( $this->store->is_connected() );
	}

	/**
	 * An unknown encryption method is handled, not fatal.
	 *
	 * @return void
	 */
	public function test_unknown_method_is_not_fatal() {
		$this->store->set( 'access', 'refresh', 1800 );
		$data                      = $this->store->get();
		$data['refresh_token_enc'] = 'zz:' . base64_encode( 'nonsense' ); // phpcs:ignore
		update_option( Token_Store::OPTION, $data, false );

		$this->assertSame( '', $this->store->get_refresh_token() );
	}

	/**
	 * clear() removes everything.
	 *
	 * @return void
	 */
	public function test_clear() {
		$this->store->set( 'a', 'b', 1800 );
		$this->store->clear();

		$this->assertFalse( $this->store->is_connected() );
		$this->assertNull( $this->store->get() );
		$this->assertSame( '', $this->store->get_access_token() );
	}

	/**
	 * Studio metadata is preserved across a token rotation.
	 *
	 * @return void
	 */
	public function test_studio_survives_token_rotation() {
		$this->store->set( 'a1', 'r1', 1800 );
		$this->store->set_studio( 'demo-studio', 'Demo Studio' );

		// Rotation: new tokens, no studio argument.
		$this->store->set( 'a2', 'r2', 1800 );

		$studio = $this->store->get_studio();
		$this->assertSame( 'demo-studio', $studio['handle'] );
		$this->assertSame( 'Demo Studio', $studio['name'] );
		$this->assertSame( 'r2', $this->store->get_refresh_token() );
	}

	/**
	 * connected_at is stamped once and not reset by a rotation.
	 *
	 * @return void
	 */
	public function test_connected_at_is_stable_across_rotation() {
		$this->store->set( 'a1', 'r1', 1800 );
		$first = $this->store->get()['connected_at'];

		$this->store->set( 'a2', 'r2', 1800 );
		$this->assertSame( $first, $this->store->get()['connected_at'] );
	}
}
