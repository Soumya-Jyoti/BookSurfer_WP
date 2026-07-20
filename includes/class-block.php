<?php
/**
 * Gutenberg block: registration and server-side render.
 *
 * BSWP-29/35/37. The block is dynamic — no saved markup; the front end is rendered
 * by render_callback through the shared embed helper. Zero front-end JS/CSS.
 *
 * @package BookSurfer
 */

namespace BookSurfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers booksurfer/booking-form and renders its iframe.
 */
class Block {

	/**
	 * Hook registration onto init.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block from its metadata directory.
	 *
	 * Prefers build/block (produced by wp-scripts); falls back to src/block so the
	 * block still registers before a build during development.
	 *
	 * @return void
	 */
	public function register_block() {
		$dir = BOOKSURFER_PATH . 'build/block';
		if ( ! file_exists( $dir . '/block.json' ) ) {
			$dir = BOOKSURFER_PATH . 'src/block';
		}
		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}

		register_block_type(
			$dir,
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);

		$this->bootstrap_editor_script();
	}

	/**
	 * Hand the editor script its configuration and translations.
	 *
	 * Editor-only: block.json's editorScript is enqueued in the editor exclusively,
	 * so nothing here reaches the front end (BSWP-40).
	 *
	 * @return void
	 */
	private function bootstrap_editor_script() {
		if ( ! function_exists( 'generate_block_asset_handle' ) ) {
			return;
		}

		$handle = generate_block_asset_handle( 'booksurfer/booking-form', 'editorScript' );
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}

		wp_add_inline_script(
			$handle,
			'window.BookSurferBlock = ' . wp_json_encode(
				array(
					'apiBase'      => booksurfer_api_base(),
					'settingsUrl'  => admin_url( 'admin.php?page=' . Admin::SLUG ),
					'dashboardUrl' => Admin::DASHBOARD_URL,
				)
			) . ';',
			'before'
		);

		wp_set_script_translations( $handle, 'book-surfer', BOOKSURFER_PATH . 'languages' );
	}

	/**
	 * Render the block on the front end.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();

		$atts = array(
			'variant'       => $attributes['variant'] ?? 'class',
			'studioHandle'  => $attributes['studioHandle'] ?? '',
			'classId'       => $attributes['classId'] ?? '',
			'embedUrl'      => $attributes['embedUrl'] ?? '',
			'layout'        => $attributes['layout'] ?? 'classic',
			'allInOneStyle' => $attributes['allInOneStyle'] ?? 'sidebar',
			'width'         => $attributes['width'] ?? 0,
			'height'        => $attributes['height'] ?? 0,
			'styling'       => isset( $attributes['styling'] ) && is_array( $attributes['styling'] ) ? $attributes['styling'] : array(),
		);

		// Align/spacing supports flow through the wrapper.
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';

		return booksurfer_render_embed( $atts, $wrapper );
	}
}
