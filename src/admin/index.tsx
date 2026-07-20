/**
 * Admin settings entry point.
 *
 * BSWP-23. Mounts the React app into the div rendered by class-admin.php via
 * @wordpress/element's createRoot. React resolves to wp.element — it is never
 * bundled (WordPress ships its own copy; a second one causes hook errors).
 *
 * @package BookSurfer
 */

import { createRoot } from '@wordpress/element';
import App from './App';
import './style.css';

const container = document.getElementById( 'booksurfer-admin-root' );

if ( container ) {
	createRoot( container ).render( <App /> );
}
