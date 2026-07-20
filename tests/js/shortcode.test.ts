/**
 * Shortcode builder tests.
 *
 * BSWP-46. The copied string must round-trip through the PHP shortcode handler, so
 * defaults are omitted and only meaningful deviations are emitted.
 *
 * @package BookSurfer
 */

import { buildShortcode } from '../../src/shared/shortcode';

describe( 'buildShortcode', () => {
	it( 'omits every default for a plain classic class form', () => {
		expect( buildShortcode( { variant: 'class', classId: '42', layout: 'classic' } ) ).toBe(
			'[booksurfer id="42"]'
		);
	} );

	it( 'emits a non-default layout', () => {
		expect( buildShortcode( { variant: 'class', classId: '42', layout: 'modern' } ) ).toBe(
			'[booksurfer id="42" layout="modern"]'
		);
	} );

	it( 'emits the variant for all-in-one and omits the id', () => {
		expect( buildShortcode( { variant: 'allInOne', allInOneStyle: 'sidebar' } ) ).toBe(
			'[booksurfer variant="allInOne"]'
		);
	} );

	it( 'emits a non-default all-in-one style', () => {
		expect( buildShortcode( { variant: 'allInOne', allInOneStyle: 'dropdown' } ) ).toBe(
			'[booksurfer variant="allInOne" style="dropdown"]'
		);
	} );

	it( 'emits a schedule with its class id', () => {
		expect( buildShortcode( { variant: 'schedule', classId: '42' } ) ).toBe(
			'[booksurfer variant="schedule" id="42"]'
		);
	} );

	it( 'omits size when it matches the layout default', () => {
		expect(
			buildShortcode( { variant: 'class', classId: '42', layout: 'classic', width: 480, height: 720 } )
		).toBe( '[booksurfer id="42"]' );
	} );

	it( 'emits size when it deviates from the default', () => {
		expect(
			buildShortcode( { variant: 'class', classId: '42', layout: 'classic', width: 600, height: 800 } )
		).toBe( '[booksurfer id="42" width="600" height="800"]' );
	} );

	it( 'emits only the styling values that differ from the defaults', () => {
		expect(
			buildShortcode( {
				variant: 'class',
				classId: '42',
				layout: 'classic',
				styling: { primary: '#ff0000', font: 'Roboto', radius: 14 },
			} )
		).toBe( '[booksurfer id="42" primary="#ff0000"]' );
	} );

	it( 'omits styling entirely for schedules', () => {
		expect(
			buildShortcode( { variant: 'schedule', classId: '42', styling: { primary: '#ff0000' } } )
		).toBe( '[booksurfer variant="schedule" id="42"]' );
	} );
} );
