<?php
/**
 * [booksurfer] shortcode — the compatibility floor for Classic editor, Elementor,
 * Divi, and text widgets.
 *
 * BSWP-36. Shares booksurfer_build_embed_url() and booksurfer_render_embed() with
 * the block — one code path, not two. Defaults mirror the block's attribute defaults.
 *
 * @package BookSurfer
 */

namespace BookSurfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the [booksurfer] shortcode.
 */
class Shortcode {

	/**
	 * Hook registration onto init.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
	}

	/**
	 * Register the shortcode tag.
	 *
	 * @return void
	 */
	public function register_shortcode() {
		add_shortcode( 'booksurfer', array( $this, 'render' ) );
	}

	/**
	 * Render [booksurfer ...].
	 *
	 * @param array<string,mixed>|string $atts Raw shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$defaults = booksurfer_default_styling();

		$atts = shortcode_atts(
			array(
				'id'      => '',
				'handle'  => '',
				'variant' => 'class',
				'layout'  => 'classic',
				'style'   => 'sidebar', // all-in-one style token.
				'width'   => '',
				'height'  => '',
				'primary' => $defaults['primary'],
				'text'    => $defaults['text'],
				'bg'      => $defaults['bg'],
				'font'    => $defaults['font'],
				'radius'  => (string) $defaults['radius'],
			),
			is_array( $atts ) ? $atts : array(),
			'booksurfer'
		);

		// Handle defaults to the connected studio's handle.
		$handle = booksurfer_sanitize_handle( $atts['handle'] );
		if ( '' === $handle ) {
			$studio = Plugin::instance()->token_store()->get_studio();
			$handle = $studio ? booksurfer_sanitize_handle( $studio['handle'] ) : '';
		}

		$embed_atts = array(
			'variant'       => in_array( $atts['variant'], array( 'class', 'allInOne', 'schedule' ), true ) ? $atts['variant'] : 'class',
			'studioHandle'  => $handle,
			'classId'       => booksurfer_sanitize_class_id( $atts['id'] ),
			'layout'        => $atts['layout'],
			'allInOneStyle' => $atts['style'],
			'width'         => '' === $atts['width'] ? 0 : (int) $atts['width'],
			'height'        => '' === $atts['height'] ? 0 : (int) $atts['height'],
			'styling'       => array(
				'primary' => $atts['primary'],
				'text'    => $atts['text'],
				'bg'      => $atts['bg'],
				'font'    => $atts['font'],
				'radius'  => (int) $atts['radius'],
			),
		);

		return booksurfer_render_embed( $embed_atts );
	}
}
