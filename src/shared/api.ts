/**
 * WordPress REST client.
 *
 * BSWP-25. Replaces the Framer plugin's Axios-to-SaaS client. api-fetch injects the
 * X-WP-Nonce automatically — that is why it replaces Axios rather than sitting
 * alongside it. No token ever touches this layer; PHP holds them.
 *
 * All errors are normalized to { code, message, status } so the UI needs exactly
 * one error component (BSWP-21).
 *
 * @package
 */

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import type {
	ClassesResponse,
	ConnectionStatus,
	NormalizedError,
} from './types';

const NS = 'booksurfer/v1';

/**
 * Normalize anything thrown by api-fetch into our one error shape.
 * @param error
 */
export function normalizeError( error: unknown ): NormalizedError {
	const e = error as {
		code?: string;
		message?: string;
		data?: { status?: number };
	};
	return {
		code: e?.code || 'booksurfer_unknown_error',
		message:
			e?.message ||
			__( 'Something went wrong. Please try again.', 'book-surfer' ),
		status: e?.data?.status || 0,
	};
}

/**
 * GET /status — cheap by default; pass verify to force an upstream check.
 * @param verify
 */
export async function getStatus( verify = false ): Promise< ConnectionStatus > {
	return apiFetch< ConnectionStatus >( {
		path: `/${ NS }/status${ verify ? '?verify=1' : '' }`,
	} );
}

/**
 * POST /connect with no state — mints a server-side state and returns it.
 * The state is never generated client-side (an attacker could otherwise drive
 * the exchange).
 */
export interface StartedConnect {
	state: string;
	/** Signed by Book Surfer when the request was registered; '' when it was not. */
	requestToken?: string;
	/** Short code the consent window shows too; '' when the request was not registered. */
	code?: string;
}

export async function startConnect(): Promise< StartedConnect > {
	return apiFetch< StartedConnect >( {
		path: `/${ NS }/connect`,
		method: 'POST',
		data: {},
	} );
}

/**
 * POST /connect with a state — advances the flow by one bounded exchange attempt.
 * Returns { connected: true, studio } on success, { pending: true } while waiting.
 * Never returns a token.
 * @param state
 */
export async function advanceConnect( state: string ): Promise< {
	connected: boolean;
	pending?: boolean;
	studio?: { handle: string; name: string };
} > {
	return apiFetch( {
		path: `/${ NS }/connect`,
		method: 'POST',
		data: { state },
	} );
}

/** POST /disconnect — revokes server-side and clears local state. */
export async function disconnect(): Promise< { connected: boolean } > {
	return apiFetch< { connected: boolean } >( {
		path: `/${ NS }/disconnect`,
		method: 'POST',
		data: {},
	} );
}

/**
 * GET /classes — proxied class list. Pass refresh to bypass the transient.
 * @param refresh
 */
export async function getClasses(
	refresh = false
): Promise< ClassesResponse > {
	return apiFetch< ClassesResponse >( {
		path: `/${ NS }/classes${ refresh ? '?refresh=1' : '' }`,
	} );
}
