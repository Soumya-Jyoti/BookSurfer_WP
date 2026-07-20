/**
 * The single shared error component.
 *
 * BSWP-21. Every proxy error is normalized to { code, message, status }, so the UI
 * needs exactly one error component rather than a switch per call site. It switches
 * on the machine `code`, never on the message text.
 *
 * @package
 */

import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ComponentProps } from 'react';
import type { NormalizedError } from '../types';

interface Props {
	error: NormalizedError;
	onRetry?: () => void;
	/** Where to send a user who needs to connect or reconnect. */
	settingsUrl?: string;
}

type NoticeAction = NonNullable<
	ComponentProps< typeof Notice >[ 'actions' ]
>[ number ];

export default function ErrorNotice( { error, onRetry, settingsUrl }: Props ) {
	const actions: NoticeAction[] = [];

	switch ( error.code ) {
		case 'booksurfer_not_connected':
		case 'booksurfer_reauth_required':
			if ( settingsUrl ) {
				actions.push( {
					label: __( 'Open Book Surfer settings', 'book-surfer' ),
					url: settingsUrl,
				} );
			}
			break;
		case 'booksurfer_upstream_error':
		case 'booksurfer_rate_limited':
		default:
			if ( onRetry ) {
				actions.push( {
					label: __( 'Try again', 'book-surfer' ),
					onClick: onRetry,
				} );
			}
			break;
	}

	return (
		<Notice status="error" isDismissible={ false } actions={ actions }>
			{ error.message }
		</Notice>
	);
}
