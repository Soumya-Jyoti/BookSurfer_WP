/**
 * Editor live preview.
 *
 * BSWP-31. The live iframe is wrapped in <Disabled> so clicks cannot escape into
 * the editor canvas, and the URL is debounced so dragging a slider does not thrash
 * the iframe. A load timeout falls back to a reassuring message rather than an
 * empty box — the front end will still render.
 *
 * @package
 */

import { Disabled } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const DEBOUNCE_MS = 400;
const LOAD_TIMEOUT_MS = 12000;

interface Props {
	src: string;
	width: number;
	height: number;
}

export default function Preview( { src, width, height }: Props ) {
	const [ debouncedSrc, setDebouncedSrc ] = useState( src );
	const [ loaded, setLoaded ] = useState( false );
	const [ timedOut, setTimedOut ] = useState( false );
	const timeoutRef = useRef< ReturnType< typeof setTimeout > | null >( null );

	// Debounce attribute changes so sliders don't reload the iframe on every tick.
	useEffect( () => {
		const handle = setTimeout( () => setDebouncedSrc( src ), DEBOUNCE_MS );
		return () => clearTimeout( handle );
	}, [ src ] );

	// Reset load state whenever the effective URL changes, and arm the timeout.
	useEffect( () => {
		setLoaded( false );
		setTimedOut( false );
		if ( timeoutRef.current ) {
			clearTimeout( timeoutRef.current );
		}
		timeoutRef.current = setTimeout(
			() => setTimedOut( true ),
			LOAD_TIMEOUT_MS
		);
		return () => {
			if ( timeoutRef.current ) {
				clearTimeout( timeoutRef.current );
			}
		};
	}, [ debouncedSrc ] );

	if ( ! debouncedSrc ) {
		return null;
	}

	if ( timedOut && ! loaded ) {
		return (
			<div
				className="booksurfer-block-skeleton"
				style={ { height: Math.min( height, 240 ) } }
			>
				{ __(
					'Preview unavailable — the form will still render on the front end.',
					'book-surfer'
				) }
			</div>
		);
	}

	return (
		<div className="booksurfer-block-preview" style={ { maxWidth: width } }>
			{ ! loaded && (
				<div
					className="booksurfer-block-skeleton"
					style={ { height: Math.min( height, 240 ) } }
					aria-hidden="true"
				>
					{ __( 'Loading preview…', 'book-surfer' ) }
				</div>
			) }
			<Disabled>
				<iframe
					src={ debouncedSrc }
					title={ __( 'Booking form preview', 'book-surfer' ) }
					loading="lazy"
					allow="payment"
					onLoad={ () => {
						setLoaded( true );
						if ( timeoutRef.current ) {
							clearTimeout( timeoutRef.current );
						}
					} }
					style={ {
						border: 0,
						display: loaded ? 'block' : 'none',
						width: '100%',
						height,
						maxWidth: '100%',
					} }
				/>
			</Disabled>
		</div>
	);
}
