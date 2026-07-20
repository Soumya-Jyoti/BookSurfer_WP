/**
 * Block attribute types.
 *
 * BSWP-29. Mirrors src/block/block.json — keep the two in step.
 *
 * @package BookSurfer
 */

import type { AllInOneStyle, LayoutStyle, Styling, Variant } from '@shared/config';

export interface BlockAttributes {
	variant: Variant;
	embedUrl: string;
	studioHandle: string;
	classId: string;
	/**
	 * Display label for the chosen class — sidebar only, never rendered.
	 * Deliberately not `className`: that name is owned by core's customClassName
	 * support and declaring it would clash with the block wrapper's own class.
	 */
	classLabel: string;
	layout: LayoutStyle;
	allInOneStyle: AllInOneStyle;
	width: number;
	height: number;
	styling: Styling;
}
