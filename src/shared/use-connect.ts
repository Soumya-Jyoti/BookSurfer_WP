/**
 * useConnect — the browser half of the OAuth connect flow.
 *
 * BSWP-24. Opens the consent popup and polls WordPress. The exchange itself is
 * PHP's job (BSWP-13) — this hook never touches a token. The `state` is minted
 * server-side and handed to us; a client-minted state would let an attacker drive
 * the exchange.
 *
 * @package
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { advanceConnect, normalizeError, startConnect } from './api';

export type ConnectState =
	| 'idle'
	| 'starting'
	| 'awaiting-approval'
	| 'connected'
	| 'failed'
	| 'popup-blocked';

const POLL_INTERVAL_MS = 2000;
const POLL_CAP_MS = 120000;
/** Polls tolerated after the popup closes — the consent page closes itself on approve. */
const GRACE_POLLS_AFTER_CLOSE = 5;

export interface UseConnectResult {
	state: ConnectState;
	error: string | null;
	/** Present when the popup was blocked, so the UI can render an inline link. */
	fallbackUrl: string | null;
	connect: () => Promise< void >;
	reset: () => void;
}

export function useConnect(
	connectUrl: string,
	origin: string,
	onConnected: () => void
): UseConnectResult {
	const [ state, setState ] = useState< ConnectState >( 'idle' );
	const [ error, setError ] = useState< string | null >( null );
	const [ fallbackUrl, setFallbackUrl ] = useState< string | null >( null );

	const timerRef = useRef< ReturnType< typeof setInterval > | null >( null );
	const popupRef = useRef< Window | null >( null );
	const mountedRef = useRef( true );
	// Guards against overlapping polls. setInterval does NOT await the async
	// callback, so a slow exchange (a cold first-time round-trip to the SaaS) lets
	// the next tick fire a SECOND exchange for the same state. The `state` grant is
	// single-use: the first poll to arrive after approval claims it (200 + tokens →
	// connected) and the duplicate finds it already consumed → 410 "link expired",
	// which then clobbers the successful connect. One exchange at a time keeps the
	// grant claimed exactly once, so no spurious 410 is ever produced.
	const inFlightRef = useRef( false );

	const stopPolling = useCallback( () => {
		if ( timerRef.current ) {
			clearInterval( timerRef.current );
			timerRef.current = null;
		}
	}, [] );

	// Stop polling on unmount — no leaked intervals.
	useEffect( () => {
		mountedRef.current = true;
		return () => {
			mountedRef.current = false;
			stopPolling();
		};
	}, [ stopPolling ] );

	const reset = useCallback( () => {
		stopPolling();
		setState( 'idle' );
		setError( null );
		setFallbackUrl( null );
	}, [ stopPolling ] );

	const beginPolling = useCallback(
		( stateToken: string ) => {
			const deadline = Date.now() + POLL_CAP_MS;
			let pollsAfterClose = 0;

			stopPolling();
			inFlightRef.current = false;
			timerRef.current = setInterval( async () => {
				if ( ! mountedRef.current ) {
					stopPolling();
					return;
				}

				if ( Date.now() > deadline ) {
					stopPolling();
					setState( 'failed' );
					setError(
						__(
							'Connection timed out. Please try connecting again.',
							'book-surfer'
						)
					);
					return;
				}

				// A previous exchange is still running — skip this tick rather than
				// firing a duplicate for the same single-use state (see inFlightRef).
				if ( inFlightRef.current ) {
					return;
				}
				inFlightRef.current = true;

				try {
					const result = await advanceConnect( stateToken );

					if ( result.connected ) {
						stopPolling();
						try {
							popupRef.current?.close();
						} catch {
							// Ignore — cross-origin popups may refuse close().
						}
						if ( mountedRef.current ) {
							setState( 'connected' );
							onConnected();
						}
						return;
					}
				} catch ( err ) {
					const normalized = normalizeError( err );
					// A rate-limit is transient; keep polling. Anything else is terminal.
					if ( normalized.status !== 429 ) {
						stopPolling();
						if ( mountedRef.current ) {
							setState( 'failed' );
							setError( normalized.message );
						}
						return;
					}
				} finally {
					inFlightRef.current = false;
				}

				// The popup closing is not immediately a cancel — the consent page
				// closes itself after approval, so allow a short grace window.
				if ( popupRef.current && popupRef.current.closed ) {
					pollsAfterClose += 1;
					if ( pollsAfterClose >= GRACE_POLLS_AFTER_CLOSE ) {
						stopPolling();
						if ( mountedRef.current ) {
							setState( 'idle' );
							setError(
								__( 'Connection was cancelled.', 'book-surfer' )
							);
						}
					}
				}
			}, POLL_INTERVAL_MS );
		},
		[ onConnected, stopPolling ]
	);

	const connect = useCallback( async () => {
		setError( null );
		setFallbackUrl( null );
		setState( 'starting' );

		let stateToken: string;
		try {
			const started = await startConnect();
			stateToken = started.state;
		} catch ( err ) {
			setState( 'failed' );
			setError( normalizeError( err ).message );
			return;
		}

		const url = `${ connectUrl }?state=${ encodeURIComponent(
			stateToken
		) }&origin=${ encodeURIComponent( origin ) }`;

		const popup = window.open(
			url,
			'booksurfer-connect',
			'width=520,height=680,menubar=no,toolbar=no'
		);
		popupRef.current = popup;

		if ( ! popup ) {
			// Popup blockers are common — offer an inline link, never fail silently.
			setState( 'popup-blocked' );
			setFallbackUrl( url );
			// Still poll: the user may open the link manually.
			beginPolling( stateToken );
			return;
		}

		setState( 'awaiting-approval' );
		beginPolling( stateToken );
	}, [ beginPolling, connectUrl, origin ] );

	return { state, error, fallbackUrl, connect, reset };
}
