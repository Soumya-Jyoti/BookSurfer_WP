/**
 * Book Surfer SaaS stub.
 *
 * BSWP-5. Reproduces the real connect contract exactly — status codes, state
 * burning, rate limits, token rotation — so plugin work and the E2E suite proceed
 * with no SaaS access. Zero dependencies: `node stub/server.js`.
 *
 * Point the plugin at it from wp-config / a mu-plugin:
 *
 *   add_filter( 'booksurfer_api_base', fn() => 'http://localhost:5199/api' );
 *   add_filter( 'booksurfer_app_base', fn() => 'http://localhost:5199' );
 *
 * Contract implemented (see docs/wordpress-connect-contract.md):
 *   GET  /connect/wordpress?state=&origin=   -> consent page (Approve / Deny)
 *   POST /api/oauth/plugin/start {state,host,origin} -> 200 { request_token, expires_in, code }
 *   POST /api/oauth/plugin/exchange {state}  -> 202 pending | 200 tokens | 410 expired
 *   POST /api/oauth/plugin/refresh  {refresh_token} -> 200 rotated | 400 invalid_grant
 *   POST /api/oauth/plugin/revoke   {refresh_token} -> 200
 *   GET  /api/widget/classes  (Bearer)       -> 200 { success, classes, studio } | 401
 *   GET  /api/:handle/book[/:id] | /schedule[/:id] -> a fake rendered form (for iframes)
 *
 * @package
 */

'use strict';

const http = require( 'http' );
const { URL } = require( 'url' );

const PORT = Number( process.env.BOOKSURFER_STUB_PORT || 5199 );
const HOST = process.env.BOOKSURFER_STUB_HOST || '127.0.0.1';

/** Tunables that let the E2E suite drive the negative paths. */
const STATE_TTL_MS = 10 * 60 * 1000;
const RATE_MIN_INTERVAL_MS = Number( process.env.BOOKSURFER_STUB_RATE_MS || 0 ); // 0 = off for tests.
const RATE_MAX_ATTEMPTS = 60;

/** state -> { status, tokens, createdAt, attempts, lastAttemptAt, origin } */
const pendingConnects = new Map();
/** refresh_token -> { studio } */
const refreshTokens = new Map();
/** access_token -> { studio, expiresAt } */
const accessTokens = new Map();

const STUDIO = {
	handle: 'demo-studio',
	name: 'Demo Studio',
	location: 'Bengaluru',
	email: 'hello@demo-studio.test',
};

const CLASSES = [
	{
		id: '1',
		name: 'Sunrise Yoga',
		class_type: 'Yoga',
		instructor: 'Ana Rao',
		price: '500',
		currency: 'INR',
		location: 'Studio A',
		embed_url: `http://localhost:${ PORT }/api/demo-studio/book/1`,
	},
	{
		id: '2',
		name: 'Evening Pilates',
		class_type: 'Pilates',
		instructor: 'Marco Silva',
		price: '750',
		currency: 'INR',
		location: 'Studio B',
		embed_url: `http://localhost:${ PORT }/api/demo-studio/book/2`,
	},
	{
		id: '3',
		name: 'Free Community Flow',
		class_type: 'Yoga',
		instructor: 'Ana Rao',
		price: '0',
		currency: 'INR',
		location: 'Studio A',
		embed_url: `http://localhost:${ PORT }/api/demo-studio/book/3`,
	},
];

let tokenCounter = 0;

function mintTokens() {
	tokenCounter += 1;
	const access = `stub-access-${ tokenCounter }-${ Date.now() }`;
	const refresh = `stub-refresh-${ tokenCounter }-${ Date.now() }`;
	accessTokens.set( access, {
		studio: STUDIO,
		expiresAt: Date.now() + 1800 * 1000,
	} );
	refreshTokens.set( refresh, { studio: STUDIO } );
	return { access_token: access, refresh_token: refresh, expires_in: 1800 };
}

function json( res, status, body, headers = {} ) {
	const payload = JSON.stringify( body );
	res.writeHead( status, { 'Content-Type': 'application/json', ...headers } );
	res.end( payload );
}

function html( res, status, body ) {
	res.writeHead( status, { 'Content-Type': 'text/html; charset=utf-8' } );
	res.end( body );
}

function readBody( req ) {
	return new Promise( ( resolve ) => {
		let data = '';
		req.on( 'data', ( chunk ) => {
			data += chunk;
			if ( data.length > 1e6 ) {
				req.destroy();
			}
		} );
		req.on( 'end', () => {
			try {
				resolve( data ? JSON.parse( data ) : {} );
			} catch {
				resolve( {} );
			}
		} );
	} );
}

function escapeHtml( value ) {
	return String( value ).replace(
		/[&<>"']/g,
		( c ) =>
			( {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;',
			} )[ c ]
	);
}

/* ------------------------------------------------------------------------ */
/* Consent page                                                             */
/* ------------------------------------------------------------------------ */

function consentPage( url, res ) {
	const origin = url.searchParams.get( 'origin' ) || '';
	const host = url.pathname.split( '/' )[ 2 ];

	// Unknown hosts are rejected rather than falling through to a default.
	if ( ! [ 'wordpress', 'framer', 'webflow' ].includes( host ) ) {
		return html( res, 400, '<h1>Bad host</h1>' );
	}

	const state = url.searchParams.get( 'state' );
	if ( ! state || ! /^[A-Za-z0-9_-]{32,64}$/.test( state ) ) {
		return html( res, 400, '<h1>Missing or malformed state</h1>' );
	}

	pendingConnects.set( state, {
		status: 'pending',
		tokens: null,
		createdAt: Date.now(),
		attempts: 0,
		lastAttemptAt: 0,
		origin,
	} );

	return html(
		res,
		200,
		`<!doctype html><meta charset="utf-8"><title>Book Surfer — Approve access</title>
		<body style="font-family:system-ui;max-width:420px;margin:40px auto;text-align:center">
		<h1 style="font-size:20px">Book Surfer (stub)</h1>
		<p>Approve access for:</p>
		<p><code style="background:#f1f5f9;padding:4px 8px;border-radius:4px">${ escapeHtml(
			origin
		) }</code></p>
		<p style="color:#64748b;font-size:13px">Host: ${ escapeHtml( host ) }</p>
		<form method="POST" action="/connect/${ escapeHtml( host ) }/approve">
			<input type="hidden" name="state" value="${ escapeHtml( state ) }">
			<button style="background:#0d9488;color:#fff;border:0;padding:10px 20px;border-radius:6px;cursor:pointer">Approve</button>
		</form>
		<form method="POST" action="/connect/${ escapeHtml(
			host
		) }/deny" style="margin-top:8px">
			<input type="hidden" name="state" value="${ escapeHtml( state ) }">
			<button style="background:none;border:0;color:#64748b;cursor:pointer;text-decoration:underline">Deny</button>
		</form>
		</body>`
	);
}

async function handleApprove( req, res, approved ) {
	// The consent form posts urlencoded, not JSON.
	const raw = await new Promise( ( resolve ) => {
		let data = '';
		req.on( 'data', ( c ) => ( data += c ) );
		req.on( 'end', () => resolve( data ) );
	} );
	const params = new URLSearchParams( raw );
	const state = params.get( 'state' );
	const record = state ? pendingConnects.get( state ) : null;

	if ( ! record ) {
		return html( res, 400, '<h1>Unknown state</h1>' );
	}

	if ( approved ) {
		record.status = 'ready';
		record.tokens = mintTokens();
		return html(
			res,
			200,
			`<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;text-align:center;margin-top:60px">
			<h1 style="font-size:18px">Approved — you can close this window.</h1>
			<script>setTimeout(function(){ window.close(); }, 400);</script></body>`
		);
	}

	// Deny burns the state and lands on a clear terminal screen.
	pendingConnects.delete( state );
	return html(
		res,
		200,
		`<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;text-align:center;margin-top:60px">
		<h1 style="font-size:18px">Access denied.</h1><p>You can close this window.</p></body>`
	);
}

/* ------------------------------------------------------------------------ */
/* OAuth endpoints                                                          */
/* ------------------------------------------------------------------------ */

/**
 * POST /api/oauth/plugin/start {state, host, origin} -> 200 { request_token, expires_in, code }
 *
 * The plugin registers a connect request before it opens the consent window.
 * The real API signs the token; this stub only needs something token-shaped.
 *
 * @param {Object} req Request.
 * @param {Object} res Response.
 */
async function handleStart( req, res ) {
	const body = await readBody( req );

	if (
		! body.state ||
		typeof body.state !== 'string' ||
		body.host !== 'wordpress'
	) {
		return json( res, 400, { error: 'bad_request' } );
	}

	const claims = Buffer.from(
		JSON.stringify( { host: body.host, origin: body.origin || null } )
	).toString( 'base64url' );

	return json( res, 200, {
		request_token: `stub.${ claims }.signature`,
		expires_in: 600,
		code: 'STB-001',
	} );
}

async function handleExchange( req, res ) {
	const body = await readBody( req );
	const state = body.state;

	if ( ! state || typeof state !== 'string' ) {
		return json( res, 400, { error: 'missing_state' } );
	}

	const record = pendingConnects.get( state );
	if ( ! record ) {
		// Never approved, or already burned.
		return json( res, 202, { pending: true } );
	}

	// Rate limiting, tuned for a polling client.
	record.attempts += 1;
	if ( record.attempts > RATE_MAX_ATTEMPTS ) {
		return json(
			res,
			429,
			{ error: 'too_many_attempts' },
			{ 'Retry-After': '5' }
		);
	}
	if (
		RATE_MIN_INTERVAL_MS > 0 &&
		Date.now() - record.lastAttemptAt < RATE_MIN_INTERVAL_MS
	) {
		return json( res, 429, { error: 'slow_down' }, { 'Retry-After': '2' } );
	}
	record.lastAttemptAt = Date.now();

	if ( Date.now() - record.createdAt > STATE_TTL_MS ) {
		pendingConnects.delete( state );
		return json( res, 410, { error: 'expired' } );
	}

	if ( record.status !== 'ready' ) {
		return json( res, 202, { pending: true } );
	}

	// One-time use: hand the tokens over, then burn the record. A replay gets 202
	// (record gone) and never a second token pair.
	const tokens = record.tokens;
	pendingConnects.delete( state );
	return json( res, 200, tokens );
}

async function handleRefresh( req, res ) {
	const body = await readBody( req );
	const token = body.refresh_token;

	if ( ! token || ! refreshTokens.has( token ) ) {
		return json( res, 400, { error: 'invalid_grant' } );
	}

	// Rotate: the old refresh token is invalidated.
	refreshTokens.delete( token );
	return json( res, 200, mintTokens() );
}

async function handleRevoke( req, res ) {
	const body = await readBody( req );
	if ( body.refresh_token ) {
		refreshTokens.delete( body.refresh_token );
	}
	return json( res, 200, { ok: true } );
}

function handleClasses( req, res ) {
	const auth = req.headers.authorization || '';
	const token = auth.startsWith( 'Bearer ' ) ? auth.slice( 7 ) : '';
	const record = accessTokens.get( token );

	if ( ! record ) {
		return json( res, 401, { error: 'unauthorized' } );
	}
	if ( record.expiresAt < Date.now() ) {
		return json( res, 401, { error: 'token_expired' } );
	}

	return json( res, 200, {
		success: true,
		classes: CLASSES,
		studio: STUDIO,
	} );
}

/* ------------------------------------------------------------------------ */
/* Fake rendered forms (so iframes actually show something in E2E)          */
/* ------------------------------------------------------------------------ */

function renderForm( url, res ) {
	const params = url.searchParams;
	const primary = '#' + ( params.get( 'primary' ) || '0D9488' );
	const bg = '#' + ( params.get( 'bg' ) || 'ffffff' );
	const text = '#' + ( params.get( 'text' ) || '1e293b' );
	const font = params.get( 'font' ) || 'Roboto';
	const radius = params.get( 'radius' ) || '14';
	const layout = params.get( 'layout' ) || params.get( 'style' ) || 'default';

	return html(
		res,
		200,
		`<!doctype html><meta charset="utf-8"><title>Booking form (stub)</title>
		<body data-testid="booksurfer-stub-form" style="margin:0;background:${ escapeHtml(
			bg
		) };color:${ escapeHtml( text ) };font-family:${ escapeHtml(
			font
		) },system-ui">
		<div style="padding:24px;border-radius:${ escapeHtml( radius ) }px">
			<h1 style="font-size:18px;color:${ escapeHtml(
				primary
			) }">Book Surfer (stub form)</h1>
			<p data-testid="stub-path" style="font-size:12px">${ escapeHtml(
				url.pathname
			) }</p>
			<p data-testid="stub-layout" style="font-size:12px">layout: ${ escapeHtml(
				layout
			) }</p>
			<button style="background:${ escapeHtml(
				primary
			) };color:#fff;border:0;padding:10px 18px;border-radius:6px">Book now</button>
		</div></body>`
	);
}

/* ------------------------------------------------------------------------ */
/* Router                                                                   */
/* ------------------------------------------------------------------------ */

const server = http.createServer( async ( req, res ) => {
	const url = new URL( req.url, `http://${ req.headers.host }` );
	const { pathname } = url;

	// CORS is irrelevant for the WordPress server-to-server path, but keep it open
	// so the same stub can serve browser-based hosts too.
	res.setHeader( 'Access-Control-Allow-Origin', '*' );
	res.setHeader(
		'Access-Control-Allow-Headers',
		'Content-Type, Authorization'
	);
	if ( req.method === 'OPTIONS' ) {
		res.writeHead( 204 );
		return res.end();
	}

	try {
		if ( req.method === 'GET' && pathname === '/__stub/reset' ) {
			pendingConnects.clear();
			refreshTokens.clear();
			accessTokens.clear();
			return json( res, 200, { ok: true } );
		}

		if ( req.method === 'GET' && /^\/connect\/[^/]+$/.test( pathname ) ) {
			return consentPage( url, res );
		}
		if (
			req.method === 'POST' &&
			/^\/connect\/[^/]+\/approve$/.test( pathname )
		) {
			return handleApprove( req, res, true );
		}
		if (
			req.method === 'POST' &&
			/^\/connect\/[^/]+\/deny$/.test( pathname )
		) {
			return handleApprove( req, res, false );
		}

		if (
			req.method === 'POST' &&
			pathname === '/api/oauth/plugin/start'
		) {
			return handleStart( req, res );
		}
		if (
			req.method === 'POST' &&
			pathname === '/api/oauth/plugin/exchange'
		) {
			return handleExchange( req, res );
		}
		if (
			req.method === 'POST' &&
			pathname === '/api/oauth/plugin/refresh'
		) {
			return handleRefresh( req, res );
		}
		if (
			req.method === 'POST' &&
			pathname === '/api/oauth/plugin/revoke'
		) {
			return handleRevoke( req, res );
		}
		if ( req.method === 'GET' && pathname === '/api/widget/classes' ) {
			return handleClasses( req, res );
		}

		// Public embed URLs: /api/{handle}/book[/{id}] and /api/{handle}/schedule[/{id}]
		if (
			req.method === 'GET' &&
			/^\/api\/[^/]+\/(book|schedule)(\/[^/]+)?$/.test( pathname )
		) {
			return renderForm( url, res );
		}

		return json( res, 404, { error: 'not_found', path: pathname } );
	} catch ( err ) {
		return json( res, 500, {
			error: 'stub_error',
			message: String( err ),
		} );
	}
} );

server.listen( PORT, HOST, () => {
	/* eslint-disable no-console */
	console.log( `Book Surfer stub listening on http://${ HOST }:${ PORT }` );
	console.log( '' );
	console.log( 'Point the plugin at it:' );
	console.log(
		`  add_filter( 'booksurfer_api_base', fn() => 'http://${ HOST }:${ PORT }/api' );`
	);
	console.log(
		`  add_filter( 'booksurfer_app_base', fn() => 'http://${ HOST }:${ PORT }' );`
	);
	/* eslint-enable no-console */
} );
