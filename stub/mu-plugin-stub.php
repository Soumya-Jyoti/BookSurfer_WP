<?php
/**
 * Plugin Name: Book Surfer — point at the local stub
 * Description: Development only. Redirects the plugin's SaaS calls at the BSWP-5 stub server.
 *
 * BSWP-5. Mapped into wp-env via .wp-env.json. Activates only when BOOKSURFER_STUB
 * is defined, so it is inert in any other environment.
 *
 * Note the asymmetry, which is easy to get wrong:
 *   - api_base is called by PHP *inside the container* -> host.docker.internal
 *   - app_base is opened by the *host browser* (the consent popup) -> localhost
 * Using one host for both makes either the exchange or the popup fail.
 *
 * @package BookSurfer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BOOKSURFER_STUB' ) || ! BOOKSURFER_STUB ) {
	return;
}

$booksurfer_stub_port = getenv( 'BOOKSURFER_STUB_PORT' ) ? getenv( 'BOOKSURFER_STUB_PORT' ) : '5199';

// Server-side calls (exchange, refresh, revoke, widget/classes) originate in the container.
add_filter(
	'booksurfer_api_base',
	static function () use ( $booksurfer_stub_port ) {
		$host = getenv( 'BOOKSURFER_STUB_HOST_INTERNAL' ) ? getenv( 'BOOKSURFER_STUB_HOST_INTERNAL' ) : 'host.docker.internal';
		return "http://{$host}:{$booksurfer_stub_port}/api";
	}
);

// The consent popup is opened by the browser on the host machine.
add_filter(
	'booksurfer_app_base',
	static function () use ( $booksurfer_stub_port ) {
		return "http://localhost:{$booksurfer_stub_port}";
	}
);

// Make it obvious the site is talking to a stub, not production.
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>Book Surfer:</strong> ';
		echo esc_html__( 'development mode — connected to the local SaaS stub, not booksurfer.in.', 'book-surfer' );
		echo '</p></div>';
	}
);
