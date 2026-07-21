/**
 * Shared layout / styling configuration.
 *
 * BSWP-25/30/34. These constants mirror the PHP helpers-embed.php config exactly
 * (LAYOUT_CONFIG, ALL_IN_ONE_CONFIG, SCHEDULE_SIZES, DEFAULT_STYLING). Both the
 * admin app and the block import from here — never copy-pasted.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';

export type LayoutStyle = 'classic' | 'modern' | 'minimal' | 'compact';
export type AllInOneStyle = 'sidebar' | 'dropdown';
export type Variant = 'class' | 'allInOne' | 'schedule';

export interface Styling {
	primary: string;
	text: string;
	bg: string;
	radius: number;
	padding: number;
	font: string;
}

export const LAYOUT_CONFIG: Record<
	LayoutStyle,
	{ width: number; height: number }
> = {
	classic: { width: 480, height: 720 },
	modern: { width: 520, height: 680 },
	minimal: { width: 400, height: 500 },
	compact: { width: 360, height: 400 },
};

export const ALL_IN_ONE_CONFIG: Record<
	AllInOneStyle,
	{ width: number; height: number; style: string }
> = {
	sidebar: { width: 900, height: 680, style: '' },
	dropdown: { width: 440, height: 720, style: 'dropdown' },
};

export const SCHEDULE_SIZES = {
	all: { width: 1040, height: 640 },
	single: { width: 820, height: 440 },
};

export const DEFAULT_STYLING: Styling = {
	primary: '#0D9488',
	text: '#1e293b',
	bg: '#ffffff',
	radius: 14,
	padding: 32,
	font: 'Roboto',
};

export const FONT_PRESETS = [
	'Roboto',
	'Inter',
	'Poppins',
	'Lato',
	'Montserrat',
	'Open Sans',
] as const;

/** Display metadata for the per-class layout picker (LayoutSelector). */
export const LAYOUTS: Array< {
	id: LayoutStyle;
	name: string;
	description: string;
} > = [
	{
		id: 'classic',
		name: __( 'Classic', 'book-surfer' ),
		description: __( 'Standard list layout', 'book-surfer' ),
	},
	{
		id: 'modern',
		name: __( 'Modern', 'book-surfer' ),
		description: __( 'Clean cards with shadows', 'book-surfer' ),
	},
	{
		id: 'minimal',
		name: __( 'Minimalist', 'book-surfer' ),
		description: __( 'Simple and typography-focused', 'book-surfer' ),
	},
	{
		id: 'compact',
		name: __( 'Compact', 'book-surfer' ),
		description: __( 'Tight layout for small spaces', 'book-surfer' ),
	},
];

/** Display metadata for the all-in-one style picker (AllInOneStyleSelector). */
export const ALL_IN_ONE_STYLES: Array< {
	id: AllInOneStyle;
	name: string;
	description: string;
} > = [
	{
		id: 'sidebar',
		name: __( 'Sidebar', 'book-surfer' ),
		description: __(
			'Class list on the left, details + form on the right',
			'book-surfer'
		),
	},
	{
		id: 'dropdown',
		name: __( 'Dropdown', 'book-surfer' ),
		description: __(
			'Compact single column; pick the class from a dropdown',
			'book-surfer'
		),
	},
];
