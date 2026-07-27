/**
 * Block edit().
 *
 * BSWP-30. The critical swap from the Framer plugin: host.insertEmbed( descriptor )
 * becomes setAttributes( descriptor ). An unconfigured block shows a Placeholder
 * with the class picker; a configured one shows the live preview plus a
 * "Change class" action.
 *
 * The Framer useCanInsert() gate is gone: permission is enforced server-side by the
 * REST route's edit_posts capability, not merely hidden in the UI.
 *
 * @package
 */

import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { calendar } from '@wordpress/icons';
import { getStatus } from '@shared/api';
import AllInOneStyleSelector from '@shared/components/AllInOneStyleSelector';
import ClassList, { type ClassSelection } from '@shared/components/ClassList';
import ErrorNotice from '@shared/components/ErrorNotice';
import LayoutSelector from '@shared/components/LayoutSelector';
import {
	DEFAULT_STYLING,
	type AllInOneStyle,
	type LayoutStyle,
} from '@shared/config';
import { buildEmbedUrl, resolveDimensions } from '@shared/embed';
import type { ConnectionStatus } from '@shared/types';
import { useClasses } from '@shared/use-classes';
import Inspector from './Inspector';
import Preview from './Preview';
import type { BlockAttributes } from './types';

interface EditProps {
	attributes: BlockAttributes;
	setAttributes: ( next: Partial< BlockAttributes > ) => void;
}

const blockConfig = (
	window as unknown as { BookSurferBlock?: { settingsUrl?: string } }
 ).BookSurferBlock;

export default function Edit( { attributes, setAttributes }: EditProps ) {
	// booksurfer-root is the design system's scope (tokens + the important: selector
	// in tailwind.config.js). Putting it on the wrapper covers the placeholder chrome
	// as well as our own markup. Editor-only — save() returns null.
	const blockProps = useBlockProps( { className: 'booksurfer-root' } );
	const [ status, setStatus ] = useState< ConnectionStatus | null >( null );
	const [ picking, setPicking ] = useState( false );
	const [ pendingSelection, setPendingSelection ] =
		useState< ClassSelection | null >( null );

	const connected = status?.connected ?? false;
	const { classes, studio, loading, error, refresh } =
		useClasses( connected );

	useEffect( () => {
		let active = true;
		getStatus()
			.then( ( next ) => {
				if ( active ) {
					setStatus( next );
				}
			} )
			.catch( () => {
				if ( active ) {
					setStatus( {
						connected: false,
						studio: null,
						needs_reconnect: false,
						pending: false,
					} );
				}
			} );
		return () => {
			active = false;
		};
	}, [] );

	const isConfigured =
		Boolean( attributes.embedUrl ) || Boolean( attributes.studioHandle );

	const previewSrc = useMemo(
		() =>
			buildEmbedUrl( {
				variant: attributes.variant,
				studioHandle: attributes.studioHandle,
				classId: attributes.classId,
				embedUrl: attributes.embedUrl,
				layout: attributes.layout,
				allInOneStyle: attributes.allInOneStyle,
				styling: attributes.styling,
			} ),
		[
			attributes.variant,
			attributes.studioHandle,
			attributes.classId,
			attributes.embedUrl,
			attributes.layout,
			attributes.allInOneStyle,
			attributes.styling,
		]
	);

	/**
	 * Commit a full selection in ONE setAttributes call.
	 * @param variant
	 * @param selection
	 * @param options
	 * @param options.layout
	 * @param options.allInOneStyle
	 */
	const commit = (
		variant: BlockAttributes[ 'variant' ],
		selection: ClassSelection | null,
		options: { layout?: LayoutStyle; allInOneStyle?: AllInOneStyle } = {}
	) => {
		const handle = studio?.handle || attributes.studioHandle;
		const classInfo = selection?.classInfo || null;
		const layout = options.layout || attributes.layout || 'classic';
		const allInOneStyle =
			options.allInOneStyle || attributes.allInOneStyle || 'sidebar';
		const classId = classInfo ? String( classInfo.id ) : '';

		const size = resolveDimensions( {
			variant,
			layout,
			allInOneStyle,
			classId,
		} );

		setAttributes( {
			variant,
			studioHandle: handle,
			classId,
			classLabel: classInfo?.name || '',
			// For the class variant the SaaS-provided embed_url is authoritative.
			embedUrl:
				variant === 'class' && classInfo?.embed_url
					? classInfo.embed_url
					: '',
			layout,
			allInOneStyle,
			width: size.width,
			height: size.height,
			styling: attributes.styling || DEFAULT_STYLING,
		} );

		setPicking( false );
		setPendingSelection( null );
	};

	const handleSelect = ( selection: ClassSelection ) => {
		if ( selection.variant === 'schedule' ) {
			commit( 'schedule', selection );
			return;
		}
		// Booking forms need a layout/style choice first.
		setPendingSelection( selection );
	};

	/* ------------------------------------------------------------------ */
	/* Render                                                             */
	/* ------------------------------------------------------------------ */

	if ( ! status ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon={ calendar }
					label={ __( 'Book Surfer', 'book-surfer' ) }
				>
					<div className="bs-flex bs-items-center bs-gap-2 bs-w-full">
						<Spinner />
						<span className="bs-meta">
							{ __( 'Checking your studio…', 'book-surfer' ) }
						</span>
					</div>
				</Placeholder>
			</div>
		);
	}

	// Not connected: link to settings rather than showing an error.
	if ( ! connected ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon={ calendar }
					label={ __( 'Book Surfer', 'book-surfer' ) }
					instructions={ __(
						'Connect your Book Surfer studio to add booking forms to this page.',
						'book-surfer'
					) }
				>
					<a
						className="bs-btn-primary"
						href={ blockConfig?.settingsUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Open Book Surfer settings', 'book-surfer' ) }
					</a>
				</Placeholder>
			</div>
		);
	}

	// Configured and not actively re-picking: show the preview.
	if ( isConfigured && ! picking ) {
		return (
			<div { ...blockProps }>
				<Inspector
					attributes={ attributes }
					setAttributes={ setAttributes }
					onChangeClass={ () => setPicking( true ) }
				/>
				<Preview
					src={ previewSrc }
					width={ attributes.width }
					height={ attributes.height }
				/>
			</div>
		);
	}

	// Layout / style step.
	if ( pendingSelection?.variant === 'class' ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon={ calendar }
					label={
						pendingSelection.classInfo?.name ||
						__( 'Booking form', 'book-surfer' )
					}
				>
					<div className="bs-w-full">
						<LayoutSelector
							value={ attributes.layout }
							onBack={ () => setPendingSelection( null ) }
							onSelect={ ( layout ) =>
								commit( 'class', pendingSelection, { layout } )
							}
						/>
					</div>
				</Placeholder>
			</div>
		);
	}

	if ( pendingSelection?.variant === 'allInOne' ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon={ calendar }
					label={ __( 'All classes', 'book-surfer' ) }
				>
					<div className="bs-w-full">
						<AllInOneStyleSelector
							value={ attributes.allInOneStyle }
							onBack={ () => setPendingSelection( null ) }
							onSelect={ ( allInOneStyle ) =>
								commit( 'allInOne', pendingSelection, {
									allInOneStyle,
								} )
							}
						/>
					</div>
				</Placeholder>
			</div>
		);
	}

	// Class picker.
	return (
		<div { ...blockProps }>
			<Placeholder
				icon={ calendar }
				label={ __( 'Book Surfer booking form', 'book-surfer' ) }
				instructions={ __(
					'Choose what to embed on this page.',
					'book-surfer'
				) }
			>
				<div className="bs-w-full">
					{ error ? (
						<ErrorNotice
							error={ error }
							onRetry={ () => refresh( true ) }
							settingsUrl={ blockConfig?.settingsUrl }
						/>
					) : (
						<ClassList
							classes={ classes }
							studio={ studio }
							loading={ loading }
							onSelect={ handleSelect }
							onRefresh={ () => refresh( true ) }
						/>
					) }
					{ isConfigured && (
						<div className="bs-mt-4 bs-flex bs-justify-end">
							<button
								type="button"
								className="bs-btn-ghost bs-btn-sm"
								onClick={ () => setPicking( false ) }
							>
								{ __( 'Cancel', 'book-surfer' ) }
							</button>
						</div>
					) }
				</div>
			</Placeholder>
		</div>
	);
}
