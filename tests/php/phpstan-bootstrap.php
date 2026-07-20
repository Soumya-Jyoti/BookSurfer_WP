<?php
/**
 * Constants PHPStan needs to understand the plugin without booting WordPress.
 *
 * BSWP-10.
 *
 * @package BookSurfer
 */

define( 'ABSPATH', __DIR__ );
define( 'BOOKSURFER_VERSION', '1.0.0' );
define( 'BOOKSURFER_FILE', __DIR__ . '/book-surfer.php' );
define( 'BOOKSURFER_PATH', __DIR__ . '/' );
define( 'BOOKSURFER_URL', 'https://example.test/wp-content/plugins/book-surfer/' );
define( 'BOOKSURFER_BASENAME', 'book-surfer/book-surfer.php' );
define( 'BOOKSURFER_APP_BASE', 'https://booksurfer.in' );
define( 'BOOKSURFER_API_BASE', 'https://api.booksurfer.in/api' );
define( 'AUTH_KEY', 'phpstan-auth-key' );
define( 'AUTH_SALT', 'phpstan-auth-salt' );
