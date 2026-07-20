/**
 * The class browser panel, with copy-to-clipboard shortcodes.
 *
 * BSWP-25/36. Lists the studio's classes through our own WordPress route and hands
 * the user a ready-made [booksurfer] shortcode for the selection — the
 * compatibility path for Classic editor, Elementor and Divi users.
 *
 * @package BookSurfer
 */

import { Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import AllInOneStyleSelector from '@shared/components/AllInOneStyleSelector';
import ClassList, { type ClassSelection } from '@shared/components/ClassList';
import ErrorNotice from '@shared/components/ErrorNotice';
import LayoutSelector from '@shared/components/LayoutSelector';
import { buildShortcode } from '@shared/shortcode';
import { useClasses } from '@shared/use-classes';
import type { AllInOneStyle, LayoutStyle } from '@shared/config';

export default function ClassesPanel() {
	const { classes, studio, loading, error, refresh } = useClasses();
	const [ selection, setSelection ] = useState< ClassSelection | null >( null );
	const [ shortcode, setShortcode ] = useState< string | null >( null );
	const [ copied, setCopied ] = useState( false );

	const emit = ( code: string ) => {
		setShortcode( code );
		setSelection( null );
		setCopied( false );
	};

	const handleSelect = ( next: ClassSelection ) => {
		setCopied( false );
		if ( next.variant === 'schedule' ) {
			// Schedules take no layout — emit immediately.
			emit(
				buildShortcode( {
					variant: 'schedule',
					classId: next.classInfo?.id,
				} )
			);
			return;
		}
		setSelection( next );
		setShortcode( null );
	};

	const copy = async () => {
		if ( ! shortcode ) {
			return;
		}
		try {
			await navigator.clipboard.writeText( shortcode );
			setCopied( true );
		} catch {
			setCopied( false );
		}
	};

	return (
		<div className="bs-card bs-p-5">
			<h2 className="bs-text-base bs-font-semibold bs-mt-0 bs-mb-4">
				{ __( 'Your classes', 'book-surfer' ) }
			</h2>

			{ error && (
				<div className="bs-mb-4">
					<ErrorNotice error={ error } onRetry={ () => refresh( true ) } />
				</div>
			) }

			{ shortcode && (
				<div className="bs-mb-4">
					<Notice status="success" isDismissible onRemove={ () => setShortcode( null ) }>
						<div className="bs-flex bs-items-center bs-gap-2 bs-flex-wrap">
							<code className="bs-text-xs bs-bg-white bs-px-2 bs-py-1 bs-rounded bs-border">
								{ shortcode }
							</code>
							<button type="button" className="button button-small" onClick={ copy }>
								{ copied ? __( 'Copied', 'book-surfer' ) : __( 'Copy shortcode', 'book-surfer' ) }
							</button>
						</div>
					</Notice>
				</div>
			) }

			{ selection?.variant === 'class' && (
				<div className="bs-mb-4">
					<LayoutSelector
						title={ __( 'Choose a layout for this class', 'book-surfer' ) }
						onBack={ () => setSelection( null ) }
						onSelect={ ( layout: LayoutStyle ) =>
							emit(
								buildShortcode( {
									variant: 'class',
									classId: selection.classInfo?.id,
									layout,
								} )
							)
						}
					/>
				</div>
			) }

			{ selection?.variant === 'allInOne' && (
				<div className="bs-mb-4">
					<AllInOneStyleSelector
						onBack={ () => setSelection( null ) }
						onSelect={ ( style: AllInOneStyle ) =>
							emit( buildShortcode( { variant: 'allInOne', allInOneStyle: style } ) )
						}
					/>
				</div>
			) }

			{ ! selection && ! error && (
				<ClassList
					classes={ classes }
					studio={ studio }
					loading={ loading }
					onSelect={ handleSelect }
					onRefresh={ () => refresh( true ) }
				/>
			) }
		</div>
	);
}
