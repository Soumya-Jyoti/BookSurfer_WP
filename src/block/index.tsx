/**
 * Block registration entry point.
 *
 * BSWP-29. The block is dynamic — save() returns null and PHP's render_callback
 * emits the iframe, so a change to the URL builder fixes every published page at
 * once rather than leaving stale saved markup behind.
 *
 * @package
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import type { BlockAttributes } from './types';
import './editor.css';

registerBlockType(
	metadata as unknown as BlockConfiguration< BlockAttributes >,
	{
		edit: Edit,
		save: () => null,
	}
);
