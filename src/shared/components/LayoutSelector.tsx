/**
 * Per-class booking-form layout picker.
 *
 * BSWP-30. Ported from the Framer plugin's LayoutSelector.tsx with no behavioural
 * change: same four layouts, same copy, same resulting sizes.
 *
 * @package BookSurfer
 */

import { __ } from '@wordpress/i18n';
import { LAYOUTS, LAYOUT_CONFIG, type LayoutStyle } from '../config';

interface Props {
	value?: LayoutStyle;
	onSelect: ( layout: LayoutStyle ) => void;
	onBack?: () => void;
	title?: string;
}

export default function LayoutSelector( { value, onSelect, onBack, title }: Props ) {
	return (
		<div className="bs-space-y-3">
			<div className="bs-flex bs-items-center bs-justify-between">
				<h3 className="bs-text-base bs-font-semibold bs-m-0">
					{ title || __( 'Choose a layout', 'book-surfer' ) }
				</h3>
				{ onBack && (
					<button type="button" className="button-link" onClick={ onBack }>
						{ __( 'Back', 'book-surfer' ) }
					</button>
				) }
			</div>

			<div className="bs-grid bs-grid-cols-2 bs-gap-3">
				{ LAYOUTS.map( ( layout ) => {
					const size = LAYOUT_CONFIG[ layout.id ];
					const selected = value === layout.id;
					return (
						<button
							type="button"
							key={ layout.id }
							onClick={ () => onSelect( layout.id ) }
							aria-pressed={ selected }
							className={ `bs-card bs-text-left bs-p-3 bs-cursor-pointer ${
								selected ? 'bs-ring-2 bs-ring-brand' : ''
							}` }
							style={ selected ? { borderColor: '#0d9488', boxShadow: '0 0 0 1px #0d9488' } : undefined }
						>
							<span className="bs-block bs-font-semibold bs-text-sm">{ layout.name }</span>
							<span className="bs-block bs-text-xs bs-text-slate-500 bs-mt-1">{ layout.description }</span>
							<span className="bs-block bs-text-xs bs-text-slate-400 bs-mt-2">
								{ size.width } × { size.height }
							</span>
						</button>
					);
				} ) }
			</div>
		</div>
	);
}
