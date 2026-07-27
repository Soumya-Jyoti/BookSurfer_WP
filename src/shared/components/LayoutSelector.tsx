/**
 * Per-class booking-form layout picker.
 *
 * BSWP-30. Ported from the Framer plugin's LayoutSelector.tsx with no behavioural
 * change: same four layouts, same copy, same resulting sizes.
 *
 * Selection is expressed once, through aria-pressed — .bs-option styles that state
 * in CSS, so there is no second source of truth (and no inline colour literals).
 *
 * @package
 */

import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { arrowLeft, check } from '@wordpress/icons';
import { LAYOUTS, LAYOUT_CONFIG, type LayoutStyle } from '../config';

interface Props {
	value?: LayoutStyle;
	onSelect: ( layout: LayoutStyle ) => void;
	onBack?: () => void;
	title?: string;
}

export default function LayoutSelector( {
	value,
	onSelect,
	onBack,
	title,
}: Props ) {
	return (
		<div className="bs-space-y-3">
			<div className="bs-flex bs-items-center bs-justify-between bs-gap-2">
				<h3 className="bs-text-base bs-font-semibold bs-m-0">
					{ title || __( 'Choose a layout', 'book-surfer' ) }
				</h3>
				{ onBack && (
					<button
						type="button"
						className="bs-btn-ghost bs-btn-sm"
						onClick={ onBack }
					>
						<Icon icon={ arrowLeft } size={ 16 } />
						{ __( 'Back', 'book-surfer' ) }
					</button>
				) }
			</div>

			<div className="bs-grid bs-grid-cols-1 sm:bs-grid-cols-2 bs-gap-3">
				{ LAYOUTS.map( ( layout ) => {
					const size = LAYOUT_CONFIG[ layout.id ];
					const selected = value === layout.id;
					return (
						<button
							type="button"
							key={ layout.id }
							onClick={ () => onSelect( layout.id ) }
							aria-pressed={ selected }
							className="bs-option"
						>
							<span className="bs-option__title">
								{ layout.name }
								{ selected && (
									<Icon icon={ check } size={ 16 } />
								) }
							</span>
							<span className="bs-option__desc">
								{ layout.description }
							</span>
							<span className="bs-option__size">
								{ size.width } × { size.height }
							</span>
						</button>
					);
				} ) }
			</div>
		</div>
	);
}
