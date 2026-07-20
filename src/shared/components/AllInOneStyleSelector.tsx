/**
 * All-in-one (studio-wide) booking-form style picker.
 *
 * BSWP-30. Ported from the Framer plugin's AllInOneStyleSelector.tsx. The two
 * styles map to the SaaS ?style= param: sidebar (default, no param) and dropdown.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
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
			<div className="bs-flex bs-items-center bs-justify-between">
				<h3 className="bs-text-base bs-font-semibold bs-m-0">
					{ __( 'Choose a style', 'book-surfer' ) }
				</h3>
				{ onBack && (
					<button
						type="button"
						className="button-link"
						onClick={ onBack }
					>
						{ __( 'Back', 'book-surfer' ) }
					</button>
				) }
			</div>

			<div className="bs-grid bs-grid-cols-2 bs-gap-3">
				{ ALL_IN_ONE_STYLES.map( ( style ) => {
					const size = ALL_IN_ONE_CONFIG[ style.id ];
					const selected = value === style.id;
					return (
						<button
							type="button"
							key={ style.id }
							onClick={ () => onSelect( style.id ) }
							aria-pressed={ selected }
							className="bs-card bs-text-left bs-p-3 bs-cursor-pointer"
							style={
								selected
									? {
											borderColor: '#0d9488',
											boxShadow: '0 0 0 1px #0d9488',
									  }
									: undefined
							}
						>
							<span className="bs-block bs-font-semibold bs-text-sm">
								{ style.name }
							</span>
							<span className="bs-block bs-text-xs bs-text-slate-500 bs-mt-1">
								{ style.description }
							</span>
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
