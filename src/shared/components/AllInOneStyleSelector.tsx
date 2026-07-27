/**
 * All-in-one (studio-wide) booking-form style picker.
 *
 * BSWP-30. Ported from the Framer plugin's AllInOneStyleSelector.tsx. The two
 * styles map to the SaaS ?style= param: sidebar (default, no param) and dropdown.
 *
 * Shares .bs-option with LayoutSelector — same selected state, same keyboard and
 * focus behaviour, one place to change it.
 *
 * @package
 */

import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { arrowLeft, check } from '@wordpress/icons';
import {
	ALL_IN_ONE_CONFIG,
	ALL_IN_ONE_STYLES,
	type AllInOneStyle,
} from '../config';

interface Props {
	value?: AllInOneStyle;
	onSelect: ( style: AllInOneStyle ) => void;
	onBack?: () => void;
}

export default function AllInOneStyleSelector( {
	value,
	onSelect,
	onBack,
}: Props ) {
	return (
		<div className="bs-space-y-3">
			<div className="bs-flex bs-items-center bs-justify-between bs-gap-2">
				<h3 className="bs-text-base bs-font-semibold bs-m-0">
					{ __( 'Choose a style', 'book-surfer' ) }
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
				{ ALL_IN_ONE_STYLES.map( ( style ) => {
					const size = ALL_IN_ONE_CONFIG[ style.id ];
					const selected = value === style.id;
					return (
						<button
							type="button"
							key={ style.id }
							onClick={ () => onSelect( style.id ) }
							aria-pressed={ selected }
							className="bs-option"
						>
							<span className="bs-option__title">
								{ style.name }
								{ selected && (
									<Icon icon={ check } size={ 16 } />
								) }
							</span>
							<span className="bs-option__desc">
								{ style.description }
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
