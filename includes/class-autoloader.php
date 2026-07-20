<?php
/**
 * PSR-4-style autoloader that resolves the BookSurfer\ namespace to includes/,
 * using the WordPress class-file naming convention.
 *
 * BSWP-8.
 *
 * @package BookSurfer
 */

namespace BookSurfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps fully-qualified class names to files:
 *
 *   BookSurfer\Plugin            -> includes/class-plugin.php
 *   BookSurfer\Token_Store       -> includes/class-token-store.php
 *   BookSurfer\Rest\Route_Registry -> includes/rest/class-route-registry.php
 */
class Autoloader {

	/**
	 * Namespace prefix this autoloader owns.
	 *
	 * @var string
	 */
	const PREFIX = 'BookSurfer\\';

	/**
	 * Register the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Resolve and require a class file.
	 *
	 * @param string $class_name Fully-qualified class name.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$relative = str_replace( '\\', '/', $relative );

		$segments = explode( '/', $relative );
		$leaf     = array_pop( $segments );

		// Directory segments: lowercased, underscores -> hyphens.
		$dir = '';
		foreach ( $segments as $segment ) {
			$dir .= strtolower( str_replace( '_', '-', $segment ) ) . '/';
		}

		// Leaf: class-<lowercased, underscores -> hyphens>.php.
		$file = 'class-' . strtolower( str_replace( '_', '-', $leaf ) ) . '.php';

		$path = BOOKSURFER_PATH . 'includes/' . $dir . $file;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
