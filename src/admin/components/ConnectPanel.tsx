/**
 * The connect panel — the browser half of the OAuth flow.
 *
 * BSWP-24. Opens the consent popup and polls WordPress; the exchange itself is
 * PHP's job. No token ever touches this component.
 *
 * @package BookSurfer
 */

import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useConnect } from '@shared/use-connect';
import type { BootstrapData } from '@shared/types';

interface Props {
	bootstrap: BootstrapData;
	onConnected: () => void;
}

export default function ConnectPanel( { bootstrap, onConnected }: Props ) {
	const { state, error, fallbackUrl, connect, reset } = useConnect(
		bootstrap.connectUrl,
		bootstrap.siteUrl,
		onConnected
	);

	const busy = state === 'starting' || state === 'awaiting-approval';

	return (
		<div className="bs-card bs-p-6 bs-max-w-2xl">
			<h2 className="bs-text-lg bs-font-semibold bs-mt-0">
				{ __( 'Connect your Book Surfer studio', 'book-surfer' ) }
			</h2>
			<p className="bs-text-sm bs-text-slate-600">
				{ __(
					'You will be asked to approve access on booksurfer.in. Your login details are never shared with this site.',
					'book-surfer'
				) }
			</p>

			{ error && (
				<div className="bs-my-3">
					<Notice status={ state === 'failed' ? 'error' : 'warning' } isDismissible={ false }>
						{ error }
					</Notice>
				</div>
			) }

			{ state === 'popup-blocked' && fallbackUrl && (
				<div className="bs-my-3">
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Your browser blocked the sign-in window.', 'book-surfer' ) }{ ' ' }
						<a href={ fallbackUrl } target="_blank" rel="noopener noreferrer">
							{ __( 'Open it manually', 'book-surfer' ) }
						</a>
						{ __( ' — this page will finish connecting automatically.', 'book-surfer' ) }
					</Notice>
				</div>
			) }

			<div className="bs-flex bs-items-center bs-gap-3 bs-mt-4">
				<button type="button" className="bs-btn-primary" onClick={ connect } disabled={ busy }>
					{ busy
						? __( 'Waiting for approval…', 'book-surfer' )
						: __( 'Connect with Book Surfer', 'book-surfer' ) }
				</button>
				{ busy && <Spinner /> }
				{ ( state === 'failed' || state === 'popup-blocked' ) && (
					<button type="button" className="button" onClick={ reset }>
						{ __( 'Cancel', 'book-surfer' ) }
					</button>
				) }
			</div>

			<ol className="bs-text-xs bs-text-slate-500 bs-mt-5 bs-pl-4">
				<li>{ __( 'Click Connect — a Book Surfer window opens.', 'book-surfer' ) }</li>
				<li>{ __( 'Sign in and approve access for this site.', 'book-surfer' ) }</li>
				<li>{ __( 'The window closes and your classes appear here.', 'book-surfer' ) }</li>
			</ol>
		</div>
	);
}
