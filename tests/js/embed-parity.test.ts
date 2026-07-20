/**
 * Golden-fixture parity test (JS side).
 *
 * BSWP-34/46. The identical fixture is asserted by the PHP suite in
 * tests/php/test-embed-parity.php. If the two builders ever drift, exactly one of
 * these suites goes red — which is the entire point. Do not "fix" a failure here by
 * editing the fixture; fix the builder that diverged.
 *
 * @package
 */

import fixture from '../fixtures/embed-urls.json';
import { buildEmbedUrl, type EmbedAttributes } from '../../src/shared/embed';

interface FixtureCase {
	name: string;
	atts: Record< string, unknown >;
	expected: string;
}

describe( 'buildEmbedUrl golden fixture', () => {
	const cases = fixture.cases as FixtureCase[];

	it( 'has cases to assert', () => {
		expect( cases.length ).toBeGreaterThan( 15 );
	} );

	test.each( cases.map( ( c ) => [ c.name, c ] as [ string, FixtureCase ] ) )(
		'%s',
		( _name, testCase ) => {
			const atts = {
				...testCase.atts,
				apiBase: fixture.apiBase,
			} as EmbedAttributes;
			expect( buildEmbedUrl( atts ) ).toBe( testCase.expected );
		}
	);
} );

describe( 'encoding parity rules', () => {
	it( 'encodes spaces as + rather than %20 (matching PHP urlencode)', () => {
		const url = buildEmbedUrl( {
			variant: 'class',
			studioHandle: 'demo-studio',
			classId: '1',
			apiBase: fixture.apiBase,
			styling: { font: 'Open Sans' },
		} );
		expect( url ).toContain( 'font=Open+Sans' );
		expect( url ).not.toContain( 'font=Open%20Sans' );
	} );

	it( 'strips only the first # from a colour', () => {
		const url = buildEmbedUrl( {
			variant: 'class',
			studioHandle: 'demo-studio',
			classId: '1',
			apiBase: fixture.apiBase,
			styling: { primary: '#abcdef' },
		} );
		expect( url ).toContain( 'primary=abcdef' );
	} );

	it( 'emits styling params in the canonical order', () => {
		const url = buildEmbedUrl( {
			variant: 'class',
			studioHandle: 'demo-studio',
			classId: '1',
			apiBase: fixture.apiBase,
		} );
		const query = url.split( '?' )[ 1 ];
		expect(
			query.split( '&' ).map( ( p ) => p.split( '=' )[ 0 ] )
		).toEqual( [ 'layout', 'primary', 'text', 'bg', 'font', 'radius' ] );
	} );
} );
