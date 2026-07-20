/**
 * Price/currency formatting tests.
 *
 * BSWP-46. Locks the visible behaviour carried over from the Framer plugin:
 * zero -> "Free", USD -> $ with en-US grouping, everything else -> ₹ with en-IN
 * grouping, plus the €/£ support the richer formatPrice adds.
 *
 * @package
 */

import { formatPrice } from '../../src/shared/format';

describe( 'formatPrice', () => {
	it( 'renders a zero price as Free', () => {
		expect( formatPrice( 0 ) ).toBe( 'Free' );
		expect( formatPrice( '0' ) ).toBe( 'Free' );
		expect( formatPrice( '0', 'USD' ) ).toBe( 'Free' );
	} );

	it( 'renders an empty price as an em dash', () => {
		expect( formatPrice( '' ) ).toBe( '—' );
		expect( formatPrice( null ) ).toBe( '—' );
		expect( formatPrice( undefined ) ).toBe( '—' );
	} );

	it( 'formats USD with en-US grouping', () => {
		expect( formatPrice( '1200', 'USD' ) ).toBe( '$1,200' );
	} );

	it( 'defaults to INR with en-IN grouping', () => {
		// en-IN groups as 1,20,000 rather than 120,000.
		expect( formatPrice( '120000', 'INR' ) ).toBe( '₹1,20,000' );
	} );

	it( 'supports EUR and GBP', () => {
		expect( formatPrice( '50', 'EUR' ) ).toBe( '€50' );
		expect( formatPrice( '50', 'GBP' ) ).toBe( '£50' );
	} );

	it( 'passes through a price that already carries a symbol', () => {
		expect( formatPrice( '₹999' ) ).toBe( '₹999' );
		expect( formatPrice( '$42' ) ).toBe( '$42' );
	} );

	it( 'strips a leading currency word or code', () => {
		expect( formatPrice( 'INR 500', 'INR' ) ).toBe( '₹500' );
		expect( formatPrice( 'Rs. 500', 'INR' ) ).toBe( '₹500' );
	} );

	it( 'falls back to $ for an unknown currency', () => {
		expect( formatPrice( '10', 'XYZ' ) ).toBe( '$10' );
		expect( formatPrice( '10' ) ).toBe( '$10' );
	} );

	it( 'keeps a non-numeric price readable rather than emitting NaN', () => {
		expect( formatPrice( 'donation', 'USD' ) ).toBe( '$donation' );
	} );
} );
