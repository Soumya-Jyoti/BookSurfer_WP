/**
 * Connected-state panel: studio identity, the link out, and disconnect.
 *
 * BSWP-26. This is where the thin-client boundary becomes visible to the user —
 * the plugin's job is to hand them off to booksurfer.in, not to grow a dashboard.
 *
 * @package
 */

import { Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { disconnect as disconnectRequest, normalizeError } from '@shared/api';
import type { BootstrapData, ConnectionStatus } from '@shared/types';

interface Props {
	bootstrap: BootstrapData;
	status: ConnectionStatus;
	onDisconnected: () => void;
}

export default function ConnectedPanel( {
	bootstrap,
	status,
	onDisconnected,
}: Props ) {
	const [ confirming, setConfirming ] = useState( false );
	const [ working, setWorking ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	const handleDisconnect = async () => {
		setWorking( true );
		setError( null );
		try {
			await disconnectRequest();
			setConfirming( false );
			onDisconnected();
		} catch ( err ) {
			setError( normalizeError( err ).message );
		} finally {
			setWorking( false );
		}
	};

	return (
		<div className="bs-card bs-p-5">
			<div className="bs-flex bs-items-start bs-justify-between bs-gap-4 bs-flex-wrap">
				<div>
					<div className="bs-flex bs-items-center bs-gap-2">
						<span
							className="bs-inline-block bs-w-2 bs-h-2 bs-rounded-full"
							style={ { background: '#0d9488' } }
							aria-hidden="true"
						/>
						<span className="bs-font-semibold">
							{ status.studio?.name ||
								__( 'Connected', 'book-surfer' ) }
						</span>
						{ status.studio?.handle && (
							<span className="bs-text-xs bs-text-slate-500">
								@{ status.studio.handle }
							</span>
						) }
					</div>
					{ status.studio?.connected_at ? (
						<p className="bs-text-xs bs-text-slate-500 bs-mt-1 bs-mb-0">
							{ sprintf(
								/* translators: %s: the date the site connected to Book Surfer. */
								__( 'Connected since %s', 'book-surfer' ),
								new Date(
									status.studio.connected_at * 1000
								).toLocaleDateString()
							) }
						</p>
					) : null }
					<p className="bs-text-xs bs-text-slate-500 bs-mt-2 bs-mb-0">
						{ __(
							'Classes, bookings and payments are managed on booksurfer.in. This plugin only embeds your forms.',
							'book-surfer'
						) }
					</p>
				</div>

				<div className="bs-flex bs-gap-2">
					<a
						className="button button-secondary"
						href={ bootstrap.dashboardUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Open Book Surfer Dashboard', 'book-surfer' ) }
					</a>
					<button
						type="button"
						className="button"
						onClick={ () => setConfirming( true ) }
					>
						{ __( 'Disconnect', 'book-surfer' ) }
					</button>
				</div>
			</div>

			{ confirming && (
				<Modal
					title={ __( 'Disconnect Book Surfer?', 'book-surfer' ) }
					onRequestClose={ () =>
						working ? undefined : setConfirming( false )
					}
				>
					<p className="bs-text-sm">
						{ __(
							'Booking forms already published on your pages will keep working — they are public Book Surfer links.',
							'book-surfer'
						) }
					</p>
					<p className="bs-text-sm">
						{ __(
							'You will need to reconnect before you can browse classes or add new forms.',
							'book-surfer'
						) }
					</p>

					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }

					<div className="bs-flex bs-gap-2 bs-justify-end bs-mt-4">
						<button
							type="button"
							className="button"
							onClick={ () => setConfirming( false ) }
							disabled={ working }
						>
							{ __( 'Cancel', 'book-surfer' ) }
						</button>
						<button
							type="button"
							className="button button-primary"
							onClick={ handleDisconnect }
							disabled={ working }
						>
							{ working
								? __( 'Disconnecting…', 'book-surfer' )
								: __( 'Disconnect', 'book-surfer' ) }
						</button>
					</div>
				</Modal>
			) }
		</div>
	);
}
