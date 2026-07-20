<?php
/**
 * Golden-fixture parity test (PHP side).
 *
 * BSWP-34/46. Asserts booksurfer_build_embed_url() returns exactly what the JS
 * builder returns for the same fixture (tests/js/embed-parity.test.ts). If the two
 * drift, exactly one suite goes red — that is the point. Do not "fix" a failure by
 * editing the fixture; fix the builder that diverged.
 *
 * @package BookSurfer
 */

/**
 * Embed URL builder parity.
 */
class Test_Embed_Parity extends WP_UnitTestCase {

	/**
	 * Decoded fixture.
	 *
	 * @var array<string,mixed>
	 */
	private static $fixture;

	/**
	 * Load the shared fixture once.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		$path          = dirname( __DIR__ ) . '/fixtures/embed-urls.json';
		self::$fixture = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Provide each fixture case.
	 *
	 * @return array<int,array<int,mixed>>
	 */
	public function fixture_cases() {
		$path    = dirname( __DIR__ ) . '/fixtures/embed-urls.json';
		$fixture = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$out = array();
		foreach ( $fixture['cases'] as $case ) {
			$out[ $case['name'] ] = array( $case['atts'], $case['expected'] );
		}
		return $out;
	}

	/**
	 * Every fixture case must match byte-for-byte.
	 *
	 * @dataProvider fixture_cases
	 *
	 * @param array<string,mixed> $atts     Attributes.
	 * @param string              $expected Expected URL.
	 * @return void
	 */
	public function test_matches_fixture( $atts, $expected ) {
		$this->assertSame( $expected, booksurfer_build_embed_url( $atts ) );
	}

	/**
	 * The fixture's pinned apiBase must equal the plugin's default, or every case
	 * would be asserting the wrong host.
	 *
	 * @return void
	 */
	public function test_fixture_api_base_matches_plugin_default() {
		$this->assertSame( self::$fixture['apiBase'], rtrim( booksurfer_api_base(), '/' ) );
	}

	/**
	 * Spaces must encode as + (PHP urlencode), never %20 (rawurlencode) — this is
	 * the exact mismatch that would silently break the JS/PHP parity.
	 *
	 * @return void
	 */
	public function test_space_encodes_as_plus() {
		$url = booksurfer_build_embed_url(
			array(
				'variant'      => 'class',
				'studioHandle' => 'demo-studio',
				'classId'      => '1',
				'styling'      => array( 'font' => 'Open Sans' ),
			)
		);
		$this->assertStringContainsString( 'font=Open+Sans', $url );
		$this->assertStringNotContainsString( 'font=Open%20Sans', $url );
	}

	/**
	 * Canonical param order is part of the contract.
	 *
	 * @return void
	 */
	public function test_canonical_param_order() {
		$url = booksurfer_build_embed_url(
			array(
				'variant'      => 'class',
				'studioHandle' => 'demo-studio',
				'classId'      => '1',
			)
		);
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		$keys  = array_map(
			static function ( $pair ) {
				return explode( '=', $pair )[0];
			},
			explode( '&', $query )
		);
		$this->assertSame( array( 'layout', 'primary', 'text', 'bg', 'font', 'radius' ), $keys );
	}
}
