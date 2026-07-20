/**
 * Price and currency formatting.
 *
 * BSWP-25. Ported from ClassesTab.tsx. The Framer plugin had two competing paths:
 * a richer formatPrice() + CURRENCY_SYMBOLS map (₹/$/€/£) that was defined but
 * unused, and an inline INR-vs-USD-only branch that actually rendered. We keep the
 * richer one — same visible behaviour for INR/USD, plus EUR/GBP support.
 *
 * @package BookSurfer
 */

export const CURRENCY_SYMBOLS: Record< string, string > = {
	inr: '₹',
	rupee: '₹',
	rupees: '₹',
	rs: '₹',
	'₹': '₹',
	usd: '$',
	dollar: '$',
	dollars: '$',
	$: '$',
	eur: '€',
	euro: '€',
	'€': '€',
	gbp: '£',
	pound: '£',
	pounds: '£',
	'£': '£',
};

/** Locale used for digit grouping, keyed by currency symbol. */
const GROUPING_LOCALE: Record< string, string > = {
	'₹': 'en-IN',
	$: 'en-US',
	'€': 'de-DE',
	'£': 'en-GB',
};

/**
 * Format a class price for display.
 *
 * - A zero price renders as "Free" (matching the Framer plugin's visible behaviour).
 * - A price that already carries a symbol is returned as-is.
 * - Otherwise the currency code selects a symbol (defaulting to $) and locale grouping.
 */
export function formatPrice( price: string | number | null | undefined, currency?: string ): string {
	const raw = String( price ?? '' ).trim();
	if ( ! raw ) {
		return '—';
	}

	const numeric = Number( raw );
	if ( ! Number.isNaN( numeric ) && numeric === 0 ) {
		return 'Free';
	}

	// Already symbol-prefixed — trust it.
	if ( /^[₹$€£]/.test( raw ) ) {
		return raw;
	}

	// Strip a leading code/word ("INR ", "Rs. ") to isolate the number.
	const numericPart = raw.replace( /^[a-z₹$€£.\s]+/i, '' ).trim() || raw;
	const key = ( currency ?? '' ).trim().toLowerCase();
	const symbol = CURRENCY_SYMBOLS[ key ] ?? '$';

	const parsed = Number( numericPart );
	if ( Number.isNaN( parsed ) ) {
		return `${ symbol }${ numericPart }`;
	}
	if ( parsed === 0 ) {
		return 'Free';
	}

	const locale = GROUPING_LOCALE[ symbol ] ?? 'en-US';
	return `${ symbol }${ parsed.toLocaleString( locale ) }`;
}
