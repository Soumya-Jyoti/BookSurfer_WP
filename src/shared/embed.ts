/**
 * Embed-URL builder (editor side).
 *
 * BSWP-34. The parity twin of booksurfer_build_embed_url() in helpers-embed.php.
 * Canonical param order: layout|style, primary, text, bg, font, radius. Encoding
 * matches PHP urlencode() (spaces -> +). A golden-fixture test asserts both
 * builders produce byte-identical output; CI fails on divergence.
 *
 * @package
 */

import {
	ALL_IN_ONE_CONFIG,
	DEFAULT_STYLING,
	LAYOUT_CONFIG,
	SCHEDULE_SIZES,
	type AllInOneStyle,
	type LayoutStyle,
	type Styling,
	type Variant,
} from './config';

export interface EmbedAttributes {
	variant?: Variant;
	studioHandle?: string;
	classId?: string | number;
	embedUrl?: string;
	layout?: string;
	allInOneStyle?: string;
	styling?: Partial< Styling >;
	width?: number;
	height?: number;
	/** Test/staging override; defaults to the runtime API base. */
	apiBase?: string;
}

/**
 * The SaaS API base (already includes /api).
 * @param override
 */
export function getApiBase( override?: string ): string {
	if ( override ) {
		return override;
	}
	const globalConfig =
		( typeof window !== 'undefined' &&
			( (
				window as unknown as { BookSurferAdmin?: { apiBase?: string } }
			 ).BookSurferAdmin ||
				(
					window as unknown as {
						BookSurferBlock?: { apiBase?: string };
					}
				 ).BookSurferBlock ) ) ||
		undefined;
	return globalConfig?.apiBase || 'https://api.booksurfer.in/api';
}

export function sanitizeHandle( handle: unknown ): string {
	const value = String( handle ?? '' ).toLowerCase();
	return /^[a-z0-9][a-z0-9-]{0,63}$/.test( value ) ? value : '';
}

export function sanitizeClassId( classId: unknown ): string {
	const value = String( classId ?? '' );
	return /^[A-Za-z0-9_-]{1,64}$/.test( value ) ? value : '';
}

/**
 * Encode a query value to match PHP urlencode() (application/x-www-form-urlencoded).
 * @param value
 */
export function encodeParam( value: unknown ): string {
	return encodeURIComponent( String( value ) )
		.replace(
			/[!'()*~]/g,
			( c ) => '%' + c.charCodeAt( 0 ).toString( 16 ).toUpperCase()
		)
		.replace( /%20/g, '+' );
}

/**
 * Own-property lookup.
 *
 * Deliberately not the `in` operator: `'constructor' in LAYOUT_CONFIG` is true via
 * the prototype chain, which would let layout="constructor" pass validation here
 * while PHP's isset() correctly rejects it — a parity break and a way to smuggle a
 * value into the URL.
 * @param object
 * @param key
 */
function hasKey< T extends object >( object: T, key: unknown ): key is keyof T {
	return (
		typeof key === 'string' &&
		Object.prototype.hasOwnProperty.call( object, key )
	);
}

/**
 * Whether a URL's host matches the API base host.
 *
 * BSWP-44 SSRF guard: a SaaS-provided embedUrl is only trusted as an iframe base
 * when it points at the same host as the API base — otherwise an authored or
 * imported block could aim an allow="payment" iframe at any origin. Mirrors
 * booksurfer_is_allowed_embed_url() in helpers-embed.php so both builders reject the
 * same inputs and the parity gate stays honest.
 * @param url
 * @param apiBase
 */
function hostMatches( url: string, apiBase: string ): boolean {
	try {
		return (
			new URL( url ).hostname.toLowerCase() ===
			new URL( apiBase ).hostname.toLowerCase()
		);
	} catch {
		return false;
	}
}

/**
 * Split a URL into its base and an ordered map of existing query params (last value
 * wins per key), mirroring PHP booksurfer_strip_query().
 * @param url
 */
function stripQuery( url: string ): {
	base: string;
	query: Map< string, string >;
} {
	const query = new Map< string, string >();
	const hash = url.indexOf( '?' );
	if ( hash === -1 ) {
		return { base: url, query };
	}
	const base = url.slice( 0, hash );
	const qs = url.slice( hash + 1 );
	if ( qs ) {
		for ( const part of qs.split( '&' ) ) {
			const eq = part.indexOf( '=' );
			const key = eq === -1 ? part : part.slice( 0, eq );
			const raw = eq === -1 ? '' : part.slice( eq + 1 );
			query.set(
				decodeURIComponent( key ),
				decodeURIComponent( raw.replace( /\+/g, ' ' ) )
			);
		}
	}
	return { base, query };
}

function appendStyling(
	query: Map< string, string >,
	styling?: Partial< Styling >
): void {
	const s = { ...DEFAULT_STYLING, ...( styling || {} ) };
	query.set( 'primary', String( s.primary ).replace( '#', '' ) );
	query.set( 'text', String( s.text ).replace( '#', '' ) );
	query.set( 'bg', String( s.bg ).replace( '#', '' ) );
	query.set( 'font', String( s.font ) );
	query.set( 'radius', String( parseInt( String( s.radius ), 10 ) || 0 ) );
}

/**
 * Build a booking/schedule embed URL. Returns '' (render nothing) when required
 * inputs are missing or invalid.
 * @param atts
 */
export function buildEmbedUrl( atts: EmbedAttributes ): string {
	const variant: Variant = atts.variant || 'class';
	const handle = sanitizeHandle( atts.studioHandle );
	// eslint-disable-next-line @wordpress/no-unused-vars-before-return
	const api = getApiBase( atts.apiBase ).replace( /\/$/, '' );

	let base = '';
	let query = new Map< string, string >();

	switch ( variant ) {
		case 'class': {
			const layout: LayoutStyle = hasKey( LAYOUT_CONFIG, atts.layout )
				? atts.layout
				: 'classic';
			const provided = String( atts.embedUrl || '' );
			if (
				provided &&
				provided.indexOf( 'http' ) === 0 &&
				hostMatches( provided, api )
			) {
				const stripped = stripQuery( provided );
				base = stripped.base;
				query = stripped.query;
			} else {
				const classId = sanitizeClassId( atts.classId );
				if ( ! handle || ! classId ) {
					return '';
				}
				base = `${ api }/${ handle }/book/${ classId }`;
			}
			query.set( 'layout', layout );
			appendStyling( query, atts.styling );
			break;
		}

		case 'allInOne': {
			if ( ! handle ) {
				return '';
			}
			const style: AllInOneStyle = hasKey(
				ALL_IN_ONE_CONFIG,
				atts.allInOneStyle
			)
				? atts.allInOneStyle
				: 'sidebar';
			base = `${ api }/${ handle }/book`;
			if ( ALL_IN_ONE_CONFIG[ style ].style !== '' ) {
				query.set( 'style', ALL_IN_ONE_CONFIG[ style ].style );
			}
			appendStyling( query, atts.styling );
			break;
		}

		case 'schedule': {
			if ( ! handle ) {
				return '';
			}
			const classId = sanitizeClassId( atts.classId );
			base = `${ api }/${ handle }/schedule`;
			if ( classId ) {
				base += `/${ classId }`;
			}
			break;
		}

		default:
			return '';
	}

	if ( query.size === 0 ) {
		return base;
	}

	const queryString = [ ...query ]
		.map( ( [ key, value ] ) => `${ key }=${ encodeParam( value ) }` )
		.join( '&' );
	const separator = base.indexOf( '?' ) === -1 ? '?' : '&';
	return `${ base }${ separator }${ queryString }`;
}

/**
 * Resolve width/height for a set of attributes, applying per-variant defaults.
 * @param atts
 */
export function resolveDimensions( atts: EmbedAttributes ): {
	width: number;
	height: number;
} {
	const width = Number( atts.width ) || 0;
	const height = Number( atts.height ) || 0;
	if ( width > 0 && height > 0 ) {
		return { width, height };
	}

	switch ( atts.variant ) {
		case 'allInOne': {
			const style: AllInOneStyle = hasKey(
				ALL_IN_ONE_CONFIG,
				atts.allInOneStyle
			)
				? atts.allInOneStyle
				: 'sidebar';
			return {
				width: ALL_IN_ONE_CONFIG[ style ].width,
				height: ALL_IN_ONE_CONFIG[ style ].height,
			};
		}
		case 'schedule':
			return sanitizeClassId( atts.classId )
				? SCHEDULE_SIZES.single
				: SCHEDULE_SIZES.all;
		case 'class':
		default: {
			const layout: LayoutStyle = hasKey( LAYOUT_CONFIG, atts.layout )
				? atts.layout
				: 'classic';
			return LAYOUT_CONFIG[ layout ];
		}
	}
}
