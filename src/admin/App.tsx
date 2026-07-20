/**
 * Admin settings app.
 *
 * BSWP-23/24/25/26. This is the plugin's ONLY admin UI. It does not rebuild the
 * Book Surfer dashboard — classes, bookings and payments all stay on booksurfer.in
 * and we link out to them. If this file starts growing dashboard features, it has
 * left its scope.
 *
 * @package
 */

import { Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getStatus } from '@shared/api';
import type { BootstrapData, ConnectionStatus } from '@shared/types';
import ClassesPanel from './components/ClassesPanel';
import ConnectPanel from './components/ConnectPanel';
import ConnectedPanel from './components/ConnectedPanel';

declare global {
	interface Window {
		BookSurferAdmin?: BootstrapData;
	}
}

const bootstrap: BootstrapData = window.BookSurferAdmin || {
	restBase: 'booksurfer/v1',
	nonce: '',
	appBase: 'https://booksurfer.in',
	connectUrl: 'https://booksurfer.in/connect/wordpress',
	dashboardUrl: 'https://booksurfer.in/dashboard',
	siteUrl: window.location.origin,
	status: {
		connected: false,
		studio: null,
		needs_reconnect: false,
		pending: false,
	},
};

export default function App() {
	// Preloaded by rest_preload_api_request — no first-paint spinner.
	const [ status, setStatus ] = useState< ConnectionStatus >(
		bootstrap.status
	);

	const refreshStatus = useCallback( async () => {
		try {
			setStatus( await getStatus() );
		} catch {
			// Leave the last known status in place rather than flashing an error.
		}
	}, [] );

	return (
		<div className="bs-p-0">
			<h1 className="bs-text-xl bs-font-semibold bs-mb-1">
				{ __( 'Book Surfer', 'book-surfer' ) }
			</h1>
			<p className="bs-text-sm bs-text-slate-500 bs-mt-0 bs-mb-4">
				{ __(
					'Connect your studio, then add booking forms to any page with the Book Surfer block or the [booksurfer] shortcode.',
					'book-surfer'
				) }
			</p>

			{ status.degraded && (
				<div className="bs-mb-4">
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'This server has no OpenSSL or libsodium support, so your Book Surfer token is stored without encryption. Ask your host to enable one of them.',
							'book-surfer'
						) }
					</Notice>
				</div>
			) }

			{ status.needs_reconnect && (
				<div className="bs-mb-4">
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Your Book Surfer session expired. Please reconnect.',
							'book-surfer'
						) }
					</Notice>
				</div>
			) }

			{ ! status.connected ? (
				<ConnectPanel
					bootstrap={ bootstrap }
					onConnected={ refreshStatus }
				/>
			) : (
				<div className="bs-space-y-6">
					<ConnectedPanel
						bootstrap={ bootstrap }
						status={ status }
						onDisconnected={ refreshStatus }
					/>
					<ClassesPanel />
				</div>
			) }
		</div>
	);
}
