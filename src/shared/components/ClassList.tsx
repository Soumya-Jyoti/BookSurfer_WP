/**
 * The class browser — shared by the admin settings page and the block edit().
 *
 * BSWP-25/30. Ported from the Framer plugin's ClassesTab.tsx with no behavioural
 * change: same synthetic "All Classes" card, same name-substring search, same
 * card fields, same price formatting.
 *
 * @package BookSurfer
 */

import { Spinner, TextControl } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { formatPrice } from '../format';
import type { ClassInfo, StudioInfo } from '../types';

export interface ClassSelection {
	variant: 'class' | 'allInOne' | 'schedule';
	classInfo: ClassInfo | null;
}

interface Props {
	classes: ClassInfo[];
	studio: StudioInfo | null;
	loading: boolean;
	/** Called when the user picks something to embed. */
	onSelect: ( selection: ClassSelection ) => void;
	onRefresh?: () => void;
	/** Hide the schedule action where it is not offered. */
	showSchedule?: boolean;
}

export default function ClassList( {
	classes,
	studio,
	loading,
	onSelect,
	onRefresh,
	showSchedule = true,
}: Props ) {
	const [ query, setQuery ] = useState( '' );

	const filtered = useMemo(
		() => classes.filter( ( c ) => c.name.toLowerCase().includes( query.toLowerCase() ) ),
		[ classes, query ]
	);

	if ( loading ) {
		return (
			<div className="bs-flex bs-items-center bs-gap-2 bs-py-8 bs-justify-center">
				<Spinner />
				<span className="bs-text-sm bs-text-slate-500">{ __( 'Loading classes…', 'book-surfer' ) }</span>
			</div>
		);
	}

	return (
		<div className="bs-space-y-4">
			<div className="bs-flex bs-items-end bs-gap-2">
				<div className="bs-flex-1">
					<TextControl
						label={ __( 'Search classes', 'book-surfer' ) }
						value={ query }
						onChange={ setQuery }
						placeholder={ __( 'Search by name…', 'book-surfer' ) }
						__nextHasNoMarginBottom
					/>
				</div>
				{ onRefresh && (
					<button type="button" className="button" onClick={ onRefresh }>
						{ __( 'Refresh classes', 'book-surfer' ) }
					</button>
				) }
			</div>

			{ /* All Classes — the studio-wide all-in-one form. */ }
			{ studio?.handle && (
				<div className="bs-card bs-p-4 bs-flex bs-items-center bs-justify-between bs-gap-3">
					<div>
						<div className="bs-font-semibold bs-text-sm">{ __( 'All Classes', 'book-surfer' ) }</div>
						<div className="bs-text-xs bs-text-slate-500 bs-mt-1">
							{ __( 'One form listing every class in your studio.', 'book-surfer' ) }
						</div>
					</div>
					<div className="bs-flex bs-gap-2">
						<button
							type="button"
							className="bs-btn-primary"
							onClick={ () => onSelect( { variant: 'allInOne', classInfo: null } ) }
						>
							{ __( 'Use this', 'book-surfer' ) }
						</button>
						{ showSchedule && (
							<button
								type="button"
								className="button"
								onClick={ () => onSelect( { variant: 'schedule', classInfo: null } ) }
							>
								{ __( 'Schedule', 'book-surfer' ) }
							</button>
						) }
					</div>
				</div>
			) }

			{ filtered.length === 0 && (
				<p className="bs-text-sm bs-text-slate-500">
					{ query
						? __( 'No classes match that search.', 'book-surfer' )
						: __( 'No classes found. Add a class on booksurfer.in, then refresh.', 'book-surfer' ) }
				</p>
			) }

			<ul className="bs-space-y-2 bs-list-none bs-m-0 bs-p-0">
				{ filtered.map( ( classItem ) => (
					<li key={ String( classItem.id ) } className="bs-card bs-p-4">
						<div className="bs-flex bs-items-start bs-justify-between bs-gap-3">
							<div className="bs-min-w-0">
								<div className="bs-flex bs-items-center bs-gap-2">
									<span className="bs-font-semibold bs-text-sm bs-truncate">{ classItem.name }</span>
									{ classItem.class_type && (
										<span className="bs-text-xs bs-bg-slate-100 bs-text-slate-600 bs-px-2 bs-py-0.5 bs-rounded">
											{ classItem.class_type }
										</span>
									) }
								</div>
								<div className="bs-text-xs bs-text-slate-500 bs-mt-1 bs-flex bs-gap-3 bs-flex-wrap">
									{ classItem.instructor && <span>{ classItem.instructor }</span> }
									{ classItem.location && <span>{ classItem.location }</span> }
									<span className="bs-font-medium bs-text-slate-700">
										{ formatPrice( classItem.price, classItem.currency ) }
									</span>
								</div>
							</div>
							<div className="bs-flex bs-gap-2 bs-shrink-0">
								<button
									type="button"
									className="bs-btn-primary"
									onClick={ () => onSelect( { variant: 'class', classInfo: classItem } ) }
								>
									{ __( 'Use this', 'book-surfer' ) }
								</button>
								{ showSchedule && (
									<button
										type="button"
										className="button"
										onClick={ () => onSelect( { variant: 'schedule', classInfo: classItem } ) }
									>
										{ __( 'Schedule', 'book-surfer' ) }
									</button>
								) }
							</div>
						</div>
					</li>
				) ) }
			</ul>
		</div>
	);
}
