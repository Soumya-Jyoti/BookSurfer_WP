/**
 * useClasses — the shared class-list hook.
 *
 * BSWP-25/30. A thin rewrite of the Framer plugin's useClasses.ts: the only change
 * is the transport (Axios-to-SaaS -> apiFetch to our own WordPress route). The
 * returned shape is identical, so ClassesTab needs no data-mapping changes.
 *
 * Both the admin settings page and the block edit() import this — not copy-pasted.
 *
 * @package BookSurfer
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { getClasses, normalizeError } from './api';
import type { ClassInfo, NormalizedError, StudioInfo } from './types';

export interface UseClassesResult {
	classes: ClassInfo[];
	studio: StudioInfo | null;
	loading: boolean;
	error: NormalizedError | null;
	refresh: ( bypassCache?: boolean ) => Promise< void >;
}

export function useClasses( enabled = true ): UseClassesResult {
	const [ classes, setClasses ] = useState< ClassInfo[] >( [] );
	const [ studio, setStudio ] = useState< StudioInfo | null >( null );
	const [ loading, setLoading ] = useState< boolean >( enabled );
	const [ error, setError ] = useState< NormalizedError | null >( null );

	const fetchClasses = useCallback( async ( bypassCache = false ) => {
		setLoading( true );
		setError( null );
		try {
			const response = await getClasses( bypassCache );
			if ( response?.success && Array.isArray( response.classes ) ) {
				setClasses( response.classes );
				setStudio( response.studio || null );
			} else {
				setClasses( [] );
				setStudio( response?.studio || null );
			}
		} catch ( err ) {
			setError( normalizeError( err ) );
			setClasses( [] );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		if ( ! enabled ) {
			setLoading( false );
			return;
		}
		fetchClasses();
	}, [ enabled, fetchClasses ] );

	return { classes, studio, loading, error, refresh: fetchClasses };
}
