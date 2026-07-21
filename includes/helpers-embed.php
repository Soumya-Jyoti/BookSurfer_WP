<?php
/**
 * Embed-URL builder and iframe renderer — shared by the block and the shortcode.
 *
 * BSWP-34 / BSWP-35 / BSWP-36. This URL builder exists twice: here (front-end
 * render) and in src/shared/embed.js (editor preview). Drift between them is the
 * highest-likelihood silent bug in the project, so a golden-fixture parity test
 * (tests/fixtures/embed-urls.json) asserts both produce byte-identical output.
 *
 * Canonical param order (both builders MUST match): layout|style, primary, text,
 * bg, font, radius. The SaaS ignores order, but the parity gate does not.
 *
 * These are the only global functions the plugin leaks — the render entry points.
 *
 * @package BookSurfer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'booksurfer_log' ) ) {
	/**
	 * Log a plugin diagnostic when WP_DEBUG logging is on. Never logs a token.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	function booksurfer_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Book Surfer] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

if ( ! function_exists( 'booksurfer_api_base' ) ) {
	/**
	 * The SaaS API base (already includes /api). Constant + filter only — never
	 * user-supplied — which is the SSRF guard.
	 *
	 * @return string
	 */
	function booksurfer_api_base() {
		if ( defined( 'BOOKSURFER_API_BASE' ) ) {
			return BOOKSURFER_API_BASE;
		}
		return apply_filters( 'booksurfer_api_base', 'https://api.booksurfer.in/api' );
	}
}

if ( ! function_exists( 'booksurfer_is_allowed_embed_url' ) ) {
	/**
	 * Whether a SaaS-provided embedUrl may be trusted as the iframe base: it must
	 * point at the same host as the API base.
	 *
	 * BSWP-44 SSRF guard for the class variant. Without this, an authored or imported
	 * block could set embedUrl to any origin and the resulting allow="payment" iframe
	 * would load it — esc_url() constrains the protocol but not the host. Mirrors
	 * hostMatches() in src/shared/embed.ts so both builders reject the same inputs.
	 *
	 * @param string $url Candidate embed URL.
	 * @return bool
	 */
	function booksurfer_is_allowed_embed_url( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		$base = wp_parse_url( booksurfer_api_base(), PHP_URL_HOST );
		return is_string( $host ) && '' !== $host
			&& is_string( $base ) && '' !== $base
			&& strtolower( $host ) === strtolower( $base );
	}
}

if ( ! function_exists( 'booksurfer_layout_config' ) ) {
	/**
	 * Per-class booking-form layouts and their default sizes.
	 * Mirrors LAYOUT_CONFIG (ClassesTab.tsx) exactly.
	 *
	 * @return array<string,array{width:int,height:int}>
	 */
	function booksurfer_layout_config() {
		return array(
			'classic' => array(
				'width'  => 480,
				'height' => 720,
			),
			'modern'  => array(
				'width'  => 520,
				'height' => 680,
			),
			'minimal' => array(
				'width'  => 400,
				'height' => 500,
			),
			'compact' => array(
				'width'  => 360,
				'height' => 400,
			),
		);
	}
}

if ( ! function_exists( 'booksurfer_all_in_one_config' ) ) {
	/**
	 * All-in-one (studio-wide) booking-form styles.
	 * Mirrors ALL_IN_ONE_CONFIG (ClassesTab.tsx) exactly.
	 *
	 * @return array<string,array{width:int,height:int,style:string}>
	 */
	function booksurfer_all_in_one_config() {
		return array(
			'sidebar'  => array(
				'width'  => 900,
				'height' => 680,
				'style'  => '', // No ?style= param.
			),
			'dropdown' => array(
				'width'  => 440,
				'height' => 720,
				'style'  => 'dropdown',
			),
		);
	}
}

if ( ! function_exists( 'booksurfer_default_styling' ) ) {
	/**
	 * Default styling. Mirrors DEFAULT_STYLING (ClassesTab.tsx).
	 * `padding` is CSS-only and never sent to the SaaS.
	 *
	 * @return array{primary:string,text:string,bg:string,radius:int,padding:int,font:string}
	 */
	function booksurfer_default_styling() {
		return array(
			'primary' => '#0D9488',
			'text'    => '#1e293b',
			'bg'      => '#ffffff',
			'radius'  => 14,
			'padding' => 32,
			'font'    => 'Roboto',
		);
	}
}

if ( ! function_exists( 'booksurfer_schedule_sizes' ) ) {
	/**
	 * Schedule embed sizes. Mirrors SCHEDULE_SIZE / SCHEDULE_CLASS_SIZE.
	 *
	 * @return array{all:array{width:int,height:int},single:array{width:int,height:int}}
	 */
	function booksurfer_schedule_sizes() {
		return array(
			'all'    => array(
				'width'  => 1040,
				'height' => 640,
			),
			'single' => array(
				'width'  => 820,
				'height' => 440,
			),
		);
	}
}

if ( ! function_exists( 'booksurfer_sanitize_handle' ) ) {
	/**
	 * Allowlist a studio handle. Anything outside [a-z0-9-] yields ''.
	 *
	 * @param mixed $handle Raw handle.
	 * @return string
	 */
	function booksurfer_sanitize_handle( $handle ) {
		$handle = strtolower( (string) $handle );
		return preg_match( '/^[a-z0-9][a-z0-9-]{0,63}$/', $handle ) ? $handle : '';
	}
}

if ( ! function_exists( 'booksurfer_sanitize_class_id' ) ) {
	/**
	 * Allowlist a class id. Anything outside [A-Za-z0-9_-] yields ''.
	 *
	 * @param mixed $class_id Raw class id.
	 * @return string
	 */
	function booksurfer_sanitize_class_id( $class_id ) {
		$class_id = (string) $class_id;
		return preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $class_id ) ? $class_id : '';
	}
}

if ( ! function_exists( 'booksurfer_encode_param' ) ) {
	/**
	 * Encode a query value to match JS URLSearchParams (spaces -> +).
	 * PHP urlencode() uses the same application/x-www-form-urlencoded scheme.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function booksurfer_encode_param( $value ) {
		return urlencode( (string) $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
	}
}

if ( ! function_exists( 'booksurfer_build_embed_url' ) ) {
	/**
	 * Build a booking/schedule embed URL from block/shortcode attributes.
	 *
	 * Returns '' (render nothing) when required inputs are missing or invalid.
	 *
	 * Recognised keys:
	 *   variant       'class' | 'allInOne' | 'schedule'
	 *   studioHandle  string
	 *   classId       string  (class booking + single-class schedule)
	 *   embedUrl      string  (authoritative base for the class variant, from the SaaS)
	 *   layout        'classic'|'modern'|'minimal'|'compact'
	 *   allInOneStyle 'sidebar'|'dropdown'
	 *   styling       array{ primary, text, bg, font, radius }
	 *
	 * @param array<string,mixed> $atts Attributes.
	 * @return string
	 */
	function booksurfer_build_embed_url( array $atts ) {
		$variant = isset( $atts['variant'] ) ? (string) $atts['variant'] : 'class';
		$handle  = booksurfer_sanitize_handle( $atts['studioHandle'] ?? '' );
		$api     = rtrim( booksurfer_api_base(), '/' );

		$base  = '';
		$query = array(); // Ordered param list.

		switch ( $variant ) {
			case 'class':
				$layouts = booksurfer_layout_config();
				$layout  = isset( $atts['layout'] ) ? (string) $atts['layout'] : 'classic';
				if ( ! isset( $layouts[ $layout ] ) ) {
					$layout = 'classic';
				}

				// Prefer the SaaS-provided base URL (byte-exact parity for the block),
				// but only when it points at our own API host (BSWP-44 SSRF guard).
				$provided = isset( $atts['embedUrl'] ) ? (string) $atts['embedUrl'] : '';
				if ( '' !== $provided && 0 === strpos( $provided, 'http' ) && booksurfer_is_allowed_embed_url( $provided ) ) {
					$base = booksurfer_strip_query( $provided, $query );
				} else {
					$class_id = booksurfer_sanitize_class_id( $atts['classId'] ?? '' );
					if ( '' === $handle || '' === $class_id ) {
						return '';
					}
					$base = $api . '/' . $handle . '/book/' . $class_id;
				}

				$query['layout'] = $layout;
				booksurfer_append_styling( $query, $atts['styling'] ?? array() );
				break;

			case 'allInOne':
				if ( '' === $handle ) {
					return '';
				}
				$config = booksurfer_all_in_one_config();
				$style  = isset( $atts['allInOneStyle'] ) ? (string) $atts['allInOneStyle'] : 'sidebar';
				if ( ! isset( $config[ $style ] ) ) {
					$style = 'sidebar';
				}
				$base = $api . '/' . $handle . '/book';
				if ( '' !== $config[ $style ]['style'] ) {
					$query['style'] = $config[ $style ]['style'];
				}
				booksurfer_append_styling( $query, $atts['styling'] ?? array() );
				break;

			case 'schedule':
				if ( '' === $handle ) {
					return '';
				}
				$class_id = booksurfer_sanitize_class_id( $atts['classId'] ?? '' );
				$base     = $api . '/' . $handle . '/schedule';
				if ( '' !== $class_id ) {
					$base .= '/' . $class_id;
				}
				// Schedules take no layout and no styling (timetable view).
				break;

			default:
				return '';
		}

		if ( empty( $query ) ) {
			return $base;
		}

		$pairs = array();
		foreach ( $query as $key => $value ) {
			$pairs[] = $key . '=' . booksurfer_encode_param( $value );
		}

		$separator = ( false === strpos( $base, '?' ) ) ? '?' : '&';
		return $base . $separator . implode( '&', $pairs );
	}
}

if ( ! function_exists( 'booksurfer_strip_query' ) ) {
	/**
	 * Split a base URL into its path portion and merge any existing query params
	 * into $query (preserving their order, last value winning per key), so we can
	 * re-emit deterministically.
	 *
	 * Splits manually on '&'/'=' rather than parse_str() so the result is identical
	 * to the JS twin (stripQuery in embed.ts): parse_str() rewrites `a.b`/`a b` keys
	 * to `a_b`, folds `a[]` into arrays, and would silently break byte-parity.
	 *
	 * @param string               $url   Source URL.
	 * @param array<string,string> $query Query accumulator (by reference).
	 * @return string Base URL without the query string.
	 */
	function booksurfer_strip_query( $url, array &$query ) {
		$hash = strpos( $url, '?' );
		if ( false === $hash ) {
			return $url;
		}
		$base = substr( $url, 0, $hash );
		$qs   = substr( $url, $hash + 1 );
		if ( '' !== $qs ) {
			foreach ( explode( '&', $qs ) as $part ) {
				$eq  = strpos( $part, '=' );
				$key = ( false === $eq ) ? $part : substr( $part, 0, $eq );
				$raw = ( false === $eq ) ? '' : substr( $part, $eq + 1 );
				// rawurldecode leaves '+' intact (keys); urldecode maps '+' -> space
				// (values) — matching decodeURIComponent(key) / value.replace(+, ' ').
				$query[ rawurldecode( $key ) ] = urldecode( $raw );
			}
		}
		return $base;
	}
}

if ( ! function_exists( 'booksurfer_append_styling' ) ) {
	/**
	 * Append the five backend-honoured styling params in canonical order.
	 * Colors have the first '#' stripped (matching JS .replace("#","")).
	 *
	 * @param array<string,string> $query   Query accumulator (by reference).
	 * @param array<string,mixed>  $styling Styling overrides.
	 * @return void
	 */
	function booksurfer_append_styling( array &$query, array $styling ) {
		$defaults = booksurfer_default_styling();
		$styling  = array_merge( $defaults, $styling );

		$query['primary'] = preg_replace( '/#/', '', (string) $styling['primary'], 1 );
		$query['text']    = preg_replace( '/#/', '', (string) $styling['text'], 1 );
		$query['bg']      = preg_replace( '/#/', '', (string) $styling['bg'], 1 );
		$query['font']    = (string) $styling['font'];
		$query['radius']  = (string) (int) $styling['radius'];
	}
}

if ( ! function_exists( 'booksurfer_resolve_dimensions' ) ) {
	/**
	 * Resolve width/height for a set of attributes, applying per-variant defaults
	 * when width/height are missing or zero.
	 *
	 * @param array<string,mixed> $atts Attributes.
	 * @return array{width:int,height:int}
	 */
	function booksurfer_resolve_dimensions( array $atts ) {
		$width  = isset( $atts['width'] ) ? (int) $atts['width'] : 0;
		$height = isset( $atts['height'] ) ? (int) $atts['height'] : 0;
		if ( $width > 0 && $height > 0 ) {
			return array(
				'width'  => $width,
				'height' => $height,
			);
		}

		$variant = isset( $atts['variant'] ) ? (string) $atts['variant'] : 'class';
		switch ( $variant ) {
			case 'allInOne':
				$config = booksurfer_all_in_one_config();
				$style  = isset( $atts['allInOneStyle'] ) ? (string) $atts['allInOneStyle'] : 'sidebar';
				if ( ! isset( $config[ $style ] ) ) {
					$style = 'sidebar';
				}
				return array(
					'width'  => $config[ $style ]['width'],
					'height' => $config[ $style ]['height'],
				);
			case 'schedule':
				$sizes = booksurfer_schedule_sizes();
				$key   = '' !== booksurfer_sanitize_class_id( $atts['classId'] ?? '' ) ? 'single' : 'all';
				return $sizes[ $key ];
			case 'class':
			default:
				$layouts = booksurfer_layout_config();
				$layout  = isset( $atts['layout'] ) ? (string) $atts['layout'] : 'classic';
				if ( ! isset( $layouts[ $layout ] ) ) {
					$layout = 'classic';
				}
				return $layouts[ $layout ];
		}
	}
}

if ( ! function_exists( 'booksurfer_render_embed' ) ) {
	/**
	 * Render the escaped booking-form iframe (or nothing).
	 *
	 * BSWP-35 / BSWP-36 / BSWP-37: one code path for block and shortcode. Invalid
	 * or missing attributes render nothing — never a PHP warning, never a broken
	 * iframe. Zero front-end JS/CSS.
	 *
	 * @param array<string,mixed> $atts               Attributes.
	 * @param string              $wrapper_attributes Optional get_block_wrapper_attributes() output.
	 * @return string
	 */
	function booksurfer_render_embed( array $atts, $wrapper_attributes = '' ) {
		// BSWP-37: an iframe must never render in a feed, REST, or AMP context —
		// even a fully valid one. Feeds/AMP can't run the embed and REST payloads
		// aren't HTML surfaces.
		if ( booksurfer_is_suppressed_context() ) {
			return '';
		}

		$src = booksurfer_build_embed_url( $atts );
		if ( '' === $src ) {
			return booksurfer_render_misconfigured_notice();
		}

		$dimensions = booksurfer_resolve_dimensions( $atts );

		$iframe = sprintf(
			'<iframe src="%s" title="%s" loading="lazy" allow="payment" style="border:0;display:block;width:%dpx;height:%dpx;max-width:100%%"></iframe>',
			esc_url( $src ),
			esc_attr__( 'Booking form', 'book-surfer' ),
			(int) $dimensions['width'],
			(int) $dimensions['height']
		);

		if ( '' !== $wrapper_attributes ) {
			return sprintf( '<div %s>%s</div>', $wrapper_attributes, $iframe );
		}

		return sprintf( '<div class="wp-block-booksurfer-booking-form">%s</div>', $iframe );
	}
}

if ( ! function_exists( 'booksurfer_is_suppressed_context' ) ) {
	/**
	 * Contexts where the booking iframe must not render at all: RSS/Atom feeds,
	 * REST responses, and AMP pages. BSWP-37.
	 *
	 * @return bool
	 */
	function booksurfer_is_suppressed_context() {
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
			return true;
		}
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'booksurfer_render_misconfigured_notice' ) ) {
	/**
	 * BSWP-37: logged-in editors see an inline notice on a misconfigured embed;
	 * anonymous visitors see nothing. Rendering is suppressed in feed/REST/AMP.
	 *
	 * @return string
	 */
	function booksurfer_render_misconfigured_notice() {
		if ( booksurfer_is_suppressed_context() ) {
			return '';
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return '';
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return sprintf(
			'<div class="booksurfer-notice" style="padding:12px;border:1px dashed #d63638;color:#d63638;font-size:13px">%s</div>',
			esc_html__( 'Book Surfer: this booking form is not configured. Pick a class in the block settings.', 'book-surfer' )
		);
	}
}
