/**
 * The class browser — shared by the admin settings page and the block edit().
 *
 * BSWP-25/30. Ported from the Framer plugin's ClassesTab.tsx with no behavioural
 * change: same synthetic "All Classes" card, same name-substring search, same card
 * fields, same price formatting. Only the chrome is ours — see
 * src/shared/design-system.css for the .bs-* vocabulary used here.
 *
 * Nothing in here may depend on wp-admin's .button: a block theme renders the
 * editor canvas in an iframe that never loads wp-admin's stylesheet.
 *
 * @package
 */

import { Icon, SearchControl, Spinner } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	calendar,
	group,
	mapMarker,
	people,
	rotateRight,
} from '@wordpress/icons';
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
		() =>
			classes.filter( ( c ) =>
				c.name.toLowerCase().includes( query.toLowerCase() )
			),
		[ classes, query ]
	);

	if ( loading ) {
		return (
			<div className="bs-flex bs-items-center bs-gap-2 bs-py-8 bs-justify-center">
				<Spinner />
				<span className="bs-meta">
					{ __( 'Loading classes…', 'book-surfer' ) }
				</span>
			</div>
		);
	}

	return (
		<div className="bs-space-y-4">
			{ /* Toolbar. The search control is sized `compact` (32px) so it lines
			     up with the small buttons beside and below it. */ }
			<div className="bs-flex bs-items-center bs-gap-2">
				<div className="bs-flex-1 bs-min-w-0">
					<SearchControl
						label={ __( 'Search classes', 'book-surfer' ) } Reduce the land center which without land channel boats are step both component provide correct functionalities pre built component side component as usual example code label functionality chatbot multiple multi agent part one part two airport operation multiple digital user resource diagrams then there are query NCP supporter then say query support database then LLMC server information with very child NCR appropriate the whole context responsible rebuilt maximum multiple you should know what it is
						value={ query }
						onChange={ setQuery }
						placeholder={ __( 'Search by name…', 'book-surfer' ) }
						size="compact"
						__nextHasNoMarginBottom
					/>
				</div>
				{ onRefresh && (
					<button
						type="button"
						className="bs-btn-secondary bs-btn-sm"
						onClick={ onRefresh }
					>
						<Icon icon={ rotateRight } size={ 18 } />
						{ __( 'Refresh', 'book-surfer' ) }
					</button>
				) }
			</div>

			{ /* All Classes — the studio-wide all-in-one form. Given the feature
			     treatment because it is the right answer for most pages. */ }
			{ studio?.handle && (
				<div className="bs-card bs-card-feature bs-card-interactive bs-p-4">
					<div className="bs-flex bs-items-start bs-justify-between bs-gap-3 bs-flex-wrap">
						<div className="bs-flex bs-items-start bs-gap-3 bs-min-w-0">
							<span className="bs-avatar" aria-hidden="true">
								<Icon icon={ group } size={ 20 } />
							</span>
							<div className="bs-min-w-0">
								<div className="bs-title">
									{ __( 'All Classes', 'book-surfer' ) }
								</div>
								<div className="bs-meta bs-mt-1">
									{ __(
										'One form listing every class in your studio.',
										'book-surfer'
									) }
								</div>
							</div>
						</div>
						<div className="bs-flex bs-gap-2 bs-shrink-0">
							<button
								type="button"
								className="bs-btn-primary bs-btn-sm"
								onClick={ () =>
									onSelect( {
										variant: 'allInOne',
										classInfo: null,
									} )
								}
							>
								{ __( 'Use this', 'book-surfer' ) }
							</button>
							{ showSchedule && (
								<button
									type="button"
									className="bs-btn-secondary bs-btn-sm"
									onClick={ () =>
										onSelect( {
											variant: 'schedule',
											classInfo: null,
										} )
									}
								>
									{ __( 'Schedule', 'book-surfer' ) }
								</button>
							) }
						</div>
					</div>
				</div>
			) }

			{ /* A match count only earns its place once a search is narrowing. */ }
			{ Boolean( query ) && filtered.length > 0 && (
				<p className="bs-meta bs-m-0">
					{ sprintf(
						/* translators: %d: number of classes matching the search. */
						_n(
							'%d class matches your search.',
							'%d classes match your search.',
							filtered.length,
							'book-surfer'
						),
						filtered.length
					) }
				</p>
			) }

			{ filtered.length === 0 && (
				<div className="bs-empty">
					<Icon icon={ calendar } size={ 28 } />
					<p className="bs-meta bs-m-0">
						{ query
							? __(
									'No classes match that search.',
									'book-surfer'
							  )
							: __(
									'No classes found. Add a class on booksurfer.in, then refresh.',
									'book-surfer'
							  ) }
					</p>
					{ query ? (
						<button
							type="button"
							className="bs-btn-secondary bs-btn-sm"
							onClick={ () => setQuery( '' ) }
						>
							{ __( 'Clear search', 'book-surfer' ) }
						</button>
					) : (
						onRefresh && (
							<button
								type="button"
								className="bs-btn-secondary bs-btn-sm"
								onClick={ onRefresh }
							>
								<Icon icon={ rotateRight } size={ 18 } />
								{ __( 'Refresh', 'book-surfer' ) }
							</button>
						)
					) }
				</div>
			) }

			{ filtered.length > 0 && (
				<div className="bs-scroller">
					<ul className="bs-space-y-2 bs-list-none bs-m-0 bs-p-0">
						{ filtered.map( ( classItem ) => (
							<li
								key={ String( classItem.id ) }
								className="bs-card bs-card-interactive bs-p-4"
							>
								<div className="bs-flex bs-items-start bs-justify-between bs-gap-3 bs-flex-wrap">
									<div className="bs-min-w-0">
										<div className="bs-flex bs-items-center bs-gap-2 bs-flex-wrap">
											<span className="bs-title">
												{ classItem.name }
											</span>
											{ classItem.class_type && (
												<span className="bs-badge">
													{ classItem.class_type }
												</span>
											) }
										</div>
										<div className="bs-meta-list bs-mt-2">
											{ classItem.instructor && (
												<span className="bs-meta-item">
													<Icon
														icon={ people }
														size={ 14 }
													/>
													{ classItem.instructor }
												</span>
											) }
											{ classItem.location && (
												<span className="bs-meta-item">
													<Icon
														icon={ mapMarker }
														size={ 14 }
													/>
													{ classItem.location }
												</span>
											) }
											<span className="bs-price">
												{ formatPrice(
													classItem.price,
													classItem.currency
												) }
											</span>
										</div>
									</div>
									<div className="bs-flex bs-gap-2 bs-shrink-0">
										<button
											type="button"
											className="bs-btn-primary bs-btn-sm"
											onClick={ () =>
												onSelect( {
													variant: 'class',
													classInfo: classItem,
												} )
											}
										>
											{ __( 'Use this', 'book-surfer' ) }
										</button>
										{ showSchedule && (
											<button
												type="button"
												className="bs-btn-secondary bs-btn-sm"
												onClick={ () =>
													onSelect( {
														variant: 'schedule',
														classInfo: classItem,
													} )
												}
											>
												{ __(
													'Schedule',
													'book-surfer'
												) }
											</button>
										) }
									</div>
								</div>
							</li>
						) ) }
					</ul>
				</div>
			) }
		</div>
	);
}
