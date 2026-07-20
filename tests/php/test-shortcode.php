<?php
/**
 * Shortcode and render tests.
 *
 * BSWP-35/36/37/45. Covers attribute handling, escaping, the shared render path,
 * and the "render nothing" edge states.
 *
 * @package BookSurfer
 */

use BookSurfer\Token_Store;

/**
 * [booksurfer] behaviour.
 */
class Test_Shortcode extends WP_UnitTestCase {

	/**
	 * Token store.
	 *
	 * @var Token_Store
	 */
	private $store;

	/**
	 * Set up: connect a studio so the shortcode can resolve a handle.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->store = new Token_Store();
		$this->store->set( 'access', 'refresh', 1800 );
		$this->store->set_studio( 'demo-studio', 'Demo Studio' );
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->store->clear();
		parent::tear_down();
	}

	/**
	 * The shortcode is registered.
	 *
	 * @return void
	 */
	public function test_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( 'booksurfer' ) );
	}

	/**
	 * A basic class shortcode renders the iframe with the expected src.
	 *
	 * @return void
	 */
	public function test_renders_class_iframe() {
		$html = do_shortcode( '[booksurfer id="42"]' );

		$this->assertStringContainsString( '<iframe', $html );
		$this->assertStringContainsString( 'demo-studio/book/42', $html );
		$this->assertStringContainsString( 'layout=classic', $html );
		$this->assertStringContainsString( 'allow="payment"', $html );
		$this->assertStringContainsString( 'loading="lazy"', $html );
		$this->assertStringContainsString( 'width:480px', $html );
		$this->assertStringContainsString( 'height:720px', $html );
	}

	/**
	 * The shortcode and the builder agree — one code path, not two.
	 *
	 * @return void
	 */
	public function test_shortcode_src_matches_the_shared_builder() {
		$html = do_shortcode( '[booksurfer id="42" layout="modern"]' );

		$expected = booksurfer_build_embed_url(
			array(
				'variant'      => 'class',
				'studioHandle' => 'demo-studio',
				'classId'      => '42',
				'layout'       => 'modern',
			)
		);

		$this->assertStringContainsString( esc_url( $expected ), $html );
	}

	/**
	 * Layout drives the default size.
	 *
	 * @return void
	 */
	public function test_layout_drives_default_size() {
		$html = do_shortcode( '[booksurfer id="42" layout="compact"]' );

		$this->assertStringContainsString( 'width:360px', $html );
		$this->assertStringContainsString( 'height:400px', $html );
	}

	/**
	 * Explicit width/height override the layout default.
	 *
	 * @return void
	 */
	public function test_explicit_size_overrides_default() {
		$html = do_shortcode( '[booksurfer id="42" width="600" height="900"]' );

		$this->assertStringContainsString( 'width:600px', $html );
		$this->assertStringContainsString( 'height:900px', $html );
	}

	/**
	 * All-in-one variants.
	 *
	 * @return void
	 */
	public function test_all_in_one_dropdown() {
		$html = do_shortcode( '[booksurfer variant="allInOne" style="dropdown"]' );

		$this->assertStringContainsString( 'demo-studio/book', $html );
		$this->assertStringContainsString( 'style=dropdown', $html );
		$this->assertStringContainsString( 'width:440px', $html );
	}

	/**
	 * Schedule variants take no layout or styling.
	 *
	 * @return void
	 */
	public function test_schedule_variant() {
		$html = do_shortcode( '[booksurfer variant="schedule" id="42"]' );

		$this->assertStringContainsString( 'demo-studio/schedule/42', $html );
		$this->assertStringNotContainsString( 'layout=', $html );
		$this->assertStringNotContainsString( 'primary=', $html );
		$this->assertStringContainsString( 'width:820px', $html );
	}

	/**
	 * All-classes schedule.
	 *
	 * @return void
	 */
	public function test_all_classes_schedule() {
		$html = do_shortcode( '[booksurfer variant="schedule"]' );

		$this->assertStringContainsString( 'demo-studio/schedule', $html );
		$this->assertStringContainsString( 'width:1040px', $html );
	}

	/**
	 * A missing id renders nothing for anonymous visitors.
	 *
	 * @return void
	 */
	public function test_missing_id_renders_nothing_for_visitors() {
		wp_set_current_user( 0 );

		$this->assertSame( '', do_shortcode( '[booksurfer]' ) );
	}

	/**
	 * Garbage attributes are ignored safely rather than reaching the URL.
	 *
	 * @return void
	 */
	public function test_garbage_attributes_are_ignored() {
		wp_set_current_user( 0 );

		// An id with a path separator must not build a URL.
		$this->assertSame( '', do_shortcode( '[booksurfer id="../../etc/passwd"]' ) );

		// An unknown variant renders nothing.
		$this->assertSame( '', do_shortcode( '[booksurfer id="42" variant="bogus_variant"]' ) );

		// An unknown layout silently falls back to classic rather than leaking.
		$html = do_shortcode( '[booksurfer id="42" layout="<script>"]' );
		$this->assertStringContainsString( 'layout=classic', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * A logged-in editor gets a helpful notice on a misconfigured embed; an
	 * anonymous visitor gets nothing.
	 *
	 * @return void
	 */
	public function test_editor_sees_notice_visitor_does_not() {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );

		wp_set_current_user( $editor );
		$this->assertStringContainsString( 'not configured', do_shortcode( '[booksurfer]' ) );

		wp_set_current_user( 0 );
		$this->assertSame( '', do_shortcode( '[booksurfer]' ) );
	}

	/**
	 * A disconnected site still renders published embeds — the iframe is a public
	 * SaaS URL and does not depend on the connection.
	 *
	 * @return void
	 */
	public function test_published_embed_survives_disconnect() {
		$html_before = do_shortcode( '[booksurfer id="42" handle="demo-studio"]' );
		$this->store->clear();
		$html_after = do_shortcode( '[booksurfer id="42" handle="demo-studio"]' );

		$this->assertStringContainsString( '<iframe', $html_after );
		$this->assertSame( $html_before, $html_after );
	}

	/**
	 * The src attribute is escaped.
	 *
	 * @return void
	 */
	public function test_output_is_escaped() {
		$html = do_shortcode( '[booksurfer id="42" primary="\"><script>alert(1)</script>"]' );

		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Explicit handle beats the connected studio.
	 *
	 * @return void
	 */
	public function test_explicit_handle_overrides_connected_studio() {
		$html = do_shortcode( '[booksurfer id="42" handle="other-studio"]' );

		$this->assertStringContainsString( 'other-studio/book/42', $html );
	}
}
