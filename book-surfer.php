<?php
/**
 * Plugin Name:       Book Surfer Booking Forms
 * Plugin URI:        https://booksurfer.in/wordpress
 * Description:       Add live Book Surfer booking forms and schedules to your site with a Gutenberg block or the [booksurfer] shortcode.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Book Surfer
 * Author URI:        https://booksurfer.in
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       book-surfer
 * Domain Path:       /languages
 *
 * @package BookSurfer
 */

// BSWP-7: direct-access guard. Must be the first executable statement in every PHP file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// BSWP-8: core constants. BOOKSURFER_API_BASE is defined (and made filterable) inside Plugin::define_constants().
define( 'BOOKSURFER_VERSION', '1.0.0' );
define( 'BOOKSURFER_FILE', __FILE__ );
define( 'BOOKSURFER_PATH', plugin_dir_path( __FILE__ ) );
define( 'BOOKSURFER_URL', plugin_dir_url( __FILE__ ) );
define( 'BOOKSURFER_BASENAME', plugin_basename( __FILE__ ) );

// BSWP-8: autoloader + bootstrap. No work is done at include time beyond wiring hooks.
require_once BOOKSURFER_PATH . 'includes/class-autoloader.php';
\BookSurfer\Autoloader::register();

// BSWP-9: lifecycle hooks. These reference static methods so no subsystem is instantiated on (de)activation.
register_activation_hook( __FILE__, array( '\BookSurfer\Lifecycle', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\BookSurfer\Lifecycle', 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 *
 * WordPress calls us on the right hooks; the singleton only *registers* capabilities,
 * it never runs a main loop.
 */
add_action(
	'plugins_loaded',
	static function () {
		\BookSurfer\Plugin::instance()->run();
	}
);
