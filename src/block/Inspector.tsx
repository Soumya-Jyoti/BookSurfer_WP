/**
 * Block sidebar controls.
 *
 * BSWP-32. Every styling control maps 1:1 to a SaaS query param — no new params are
 * invented here. embedUrl is never hand-edited; it is recomputed from attributes by
 * the shared builder on every change.
 *
 * @package BookSurfer
 */

import {
	Button,
	PanelBody,
	PanelRow,
	RangeControl,
	SelectControl,
	TextControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	ALL_IN_ONE_STYLES,
	DEFAULT_STYLING,
	FONT_PRESETS,
	LAYOUTS,
	type AllInOneStyle,
	type LayoutStyle,
	type Styling,
} from '@shared/config';
import { resolveDimensions } from '@shared/embed';
import { buildShortcode } from '@shared/shortcode';
import type { BlockAttributes } from './types';

interface Props {
	attributes: BlockAttributes;
	setAttributes: ( next: Partial< BlockAttributes > ) => void;
	onChangeClass: () => void;
}

export default function Inspector( { attributes, setAttributes, onChangeClass }: Props ) {
	const { variant, layout, allInOneStyle, width, height, styling } = attributes;
	const [ copied, setCopied ] = useState( false );

	const currentStyling: Styling = { ...DEFAULT_STYLING, ...( styling || {} ) };

	const updateStyling = ( patch: Partial< Styling > ) =>
		setAttributes( { styling: { ...currentStyling, ...patch } } );

	/** Reset size to the current variant/layout defaults. */
	const resetSize = () => {
		const defaults = resolveDimensions( { variant, layout, allInOneStyle, classId: attributes.classId } );
		setAttributes( { width: defaults.width, height: defaults.height } );
	};

	const shortcode = buildShortcode( {
		variant,
		classId: attributes.classId,
		layout,
		allInOneStyle,
		width,
		height,
		styling: currentStyling,
	} );

	const copyShortcode = async () => {
		try {
			await navigator.clipboard.writeText( shortcode );
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		} catch {
			setCopied( false );
		}
	};

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Class', 'book-surfer' ) } initialOpen>
				<PanelRow>
					<span className="bs-text-xs">
						{ attributes.classLabel || __( 'All classes', 'book-surfer' ) }
					</span>
				</PanelRow>
				<Button variant="secondary" onClick={ onChangeClass }>
					{ __( 'Change class', 'book-surfer' ) }
				</Button>
			</PanelBody>

			{ variant === 'class' && (
				<PanelBody title={ __( 'Layout', 'book-surfer' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Layout', 'book-surfer' ) }
						value={ layout }
						options={ LAYOUTS.map( ( l ) => ( { label: l.name, value: l.id } ) ) }
						onChange={ ( value ) => {
							const next = value as LayoutStyle;
							const size = resolveDimensions( { variant: 'class', layout: next } );
							setAttributes( { layout: next, width: size.width, height: size.height } );
						} }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			) }

			{ variant === 'allInOne' && (
				<PanelBody title={ __( 'Style', 'book-surfer' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Style', 'book-surfer' ) }
						value={ allInOneStyle }
						options={ ALL_IN_ONE_STYLES.map( ( s ) => ( { label: s.name, value: s.id } ) ) }
						onChange={ ( value ) => {
							const next = value as AllInOneStyle;
							const size = resolveDimensions( { variant: 'allInOne', allInOneStyle: next } );
							setAttributes( { allInOneStyle: next, width: size.width, height: size.height } );
						} }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			) }

			<PanelBody title={ __( 'Size', 'book-surfer' ) } initialOpen={ false }>
				<NumberControl
					label={ __( 'Width (px)', 'book-surfer' ) }
					value={ width }
					min={ 240 }
					max={ 1600 }
					onChange={ ( value?: string ) => setAttributes( { width: parseInt( value || '0', 10 ) || 0 } ) }
				/>
				<NumberControl
					label={ __( 'Height (px)', 'book-surfer' ) }
					value={ height }
					min={ 240 }
					max={ 2000 }
					onChange={ ( value?: string ) => setAttributes( { height: parseInt( value || '0', 10 ) || 0 } ) }
				/>
				<Button variant="tertiary" onClick={ resetSize }>
					{ __( 'Reset to default size', 'book-surfer' ) }
				</Button>
			</PanelBody>

			{ variant !== 'schedule' && (
				<PanelBody title={ __( 'Styling', 'book-surfer' ) } initialOpen={ false }>
					<TextControl
						label={ __( 'Primary colour', 'book-surfer' ) }
						value={ currentStyling.primary }
						onChange={ ( value ) => updateStyling( { primary: value } ) }
						help={ __( 'Hex colour, e.g. #0D9488', 'book-surfer' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Text colour', 'book-surfer' ) }
						value={ currentStyling.text }
						onChange={ ( value ) => updateStyling( { text: value } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Background colour', 'book-surfer' ) }
						value={ currentStyling.bg }
						onChange={ ( value ) => updateStyling( { bg: value } ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Font', 'book-surfer' ) }
						value={ currentStyling.font }
						options={ FONT_PRESETS.map( ( f ) => ( { label: f, value: f } ) ) }
						onChange={ ( value ) => updateStyling( { font: value } ) }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Corner radius', 'book-surfer' ) }
						value={ currentStyling.radius }
						min={ 0 }
						max={ 64 }
						onChange={ ( value ) => updateStyling( { radius: value ?? 0 } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			) }

			<PanelBody title={ __( 'Shortcode', 'book-surfer' ) } initialOpen={ false }>
				<p className="bs-text-xs">
					{ __( 'Use this in a page builder or the Classic editor:', 'book-surfer' ) }
				</p>
				<code className="bs-text-xs bs-block bs-mb-2 bs-break-all">{ shortcode }</code>
				<Button variant="secondary" onClick={ copyShortcode }>
					{ copied ? __( 'Copied', 'book-surfer' ) : __( 'Copy shortcode', 'book-surfer' ) }
				</Button>
			</PanelBody>
		</InspectorControls>
	);
}
