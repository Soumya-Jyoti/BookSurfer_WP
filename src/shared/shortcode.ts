/**
 * Shortcode string builder.
 *
 * BSWP-36. The settings page and the block sidebar both expose a copy-to-clipboard
 * shortcode for the current selection. Emits only the attributes that differ from
 * the defaults, so the copied string stays readable.
 *
 * @package BookSurfer
 */

import { DEFAULT_STYLING, type Styling, type Variant } from './config';
import { resolveDimensions } from './embed';

export interface ShortcodeAttributes {
	variant?: Variant;
	classId?: string | number;
	layout?: string;
	allInOneStyle?: string;
	width?: number;
	height?: number;
	styling?: Partial< Styling >;
	/** Include the handle explicitly (defaults to the connected studio server-side). */
	studioHandle?: string;
}

export function buildShortcode( atts: ShortcodeAttributes ): string {
	const variant: Variant = atts.variant || 'class';
	const parts: string[] = [ 'booksurfer' ];

	if ( variant !== 'class' ) {
		parts.push( `variant="${ variant }"` );
	}

	if ( atts.classId !== undefined && atts.classId !== '' && variant !== 'allInOne' ) {
		parts.push( `id="${ String( atts.classId ) }"` );
	}

	if ( variant === 'class' && atts.layout && atts.layout !== 'classic' ) {
		parts.push( `layout="${ atts.layout }"` );
	}

	if ( variant === 'allInOne' && atts.allInOneStyle && atts.allInOneStyle !== 'sidebar' ) {
		parts.push( `style="${ atts.allInOneStyle }"` );
	}

	// Only emit size when it deviates from the variant default.
	const defaults = resolveDimensions( {
		variant,
		layout: atts.layout,
		allInOneStyle: atts.allInOneStyle,
		classId: atts.classId,
	} );
	if ( atts.width && atts.width !== defaults.width ) {
		parts.push( `width="${ atts.width }"` );
	}
	if ( atts.height && atts.height !== defaults.height ) {
		parts.push( `height="${ atts.height }"` );
	}

	// Styling: only non-default values (schedules take none).
	if ( variant !== 'schedule' && atts.styling ) {
		const s = { ...DEFAULT_STYLING, ...atts.styling };
		( [ 'primary', 'text', 'bg', 'font' ] as const ).forEach( ( key ) => {
			if ( String( s[ key ] ) !== String( DEFAULT_STYLING[ key ] ) ) {
				parts.push( `${ key }="${ s[ key ] }"` );
			}
		} );
		if ( Number( s.radius ) !== DEFAULT_STYLING.radius ) {
			parts.push( `radius="${ Number( s.radius ) }"` );
		}
	}

	if ( atts.studioHandle ) {
		parts.push( `handle="${ atts.studioHandle }"` );
	}

	return `[${ parts.join( ' ' ) }]`;
}
