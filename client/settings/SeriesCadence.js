import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback, useRef } from '@wordpress/element';
import { gmdateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

import { getBoot } from '../data/boot';

const DAYS_IN_WEEK = 7;
// 1970-01-04 was a Sunday.
const SUNDAY_1970 = Date.UTC( 1970, 0, 4 );
const MS_PER_DAY = 86400000;
const SERIES_PAGE_SIZE = 100;

function parseCadence( json ) {
	try {
		const parsed = JSON.parse( json );
		return parsed && typeof parsed === 'object' ? parsed : {};
	} catch {
		return {};
	}
}

/**
 * Cadence and lane exception for each series.
 *
 * @param {Object}   props          Props.
 * @param {Object[]} props.terms    Series terms with id, name and meta.
 * @param {Function} props.onChange Called with a term ID and a meta patch.
 * @return {Element} The section body.
 */
export default function SeriesCadence( { terms, onChange } ) {
	if ( terms.length === 0 ) {
		return <p>{ __( 'No series yet.', 'masthead' ) }</p>;
	}

	const weekdays = Array.from( { length: DAYS_IN_WEEK }, ( _, weekday ) => ( {
		weekday,
		name: gmdateI18n( 'l', new Date( SUNDAY_1970 + weekday * MS_PER_DAY ) ),
	} ) );

	return (
		<ul className="masthead-series">
			{ terms.map( ( term ) => {
				const cadence = parseCadence(
					term.meta?.masthead_cadence || ''
				);
				const prefix = sprintf(
					/* translators: %s: series name. Read before each field label, such as "The Mantle: Weekday". */
					__( '%s:', 'masthead' ),
					term.name
				);
				const update = ( changes ) =>
					onChange( term.id, {
						masthead_cadence: JSON.stringify( {
							every_weeks: cadence.every_weeks ?? '',
							weekday: cadence.weekday ?? null,
							time: cadence.time ?? '',
							...changes,
						} ),
					} );
				return (
					<li
						key={ term.id }
						className="masthead-series__item"
						data-term-id={ term.id }
					>
						<strong className="masthead-series__name">
							{ term.name }
						</strong>
						<label htmlFor={ `masthead-series-${ term.id }-weeks` }>
							<span className="screen-reader-text">
								{ prefix + ' ' }
							</span>
							{ __( 'Every N weeks', 'masthead' ) }
							<input
								id={ `masthead-series-${ term.id }-weeks` }
								type="number"
								min="1"
								value={ cadence.every_weeks ?? '' }
								onChange={ ( event ) =>
									update( {
										every_weeks:
											event.target.value === ''
												? ''
												: Number( event.target.value ),
									} )
								}
							/>
						</label>
						<label
							className="masthead-series__weekday"
							htmlFor={ `masthead-series-${ term.id }-weekday` }
						>
							<span className="screen-reader-text">
								{ prefix + ' ' }
							</span>
							{ __( 'Weekday', 'masthead' ) }
							<select
								id={ `masthead-series-${ term.id }-weekday` }
								value={ cadence.weekday ?? '' }
								onChange={ ( event ) =>
									update( {
										weekday:
											event.target.value === ''
												? null
												: Number( event.target.value ),
									} )
								}
							>
								<option value="">
									{ __(
										'Same as previous part',
										'masthead'
									) }
								</option>
								{ weekdays.map( ( { weekday, name } ) => (
									<option key={ weekday } value={ weekday }>
										{ name }
									</option>
								) ) }
							</select>
						</label>
						<label htmlFor={ `masthead-series-${ term.id }-time` }>
							<span className="screen-reader-text">
								{ prefix + ' ' }
							</span>
							{ __( 'Time', 'masthead' ) }
							<input
								id={ `masthead-series-${ term.id }-time` }
								type="time"
								value={ cadence.time ?? '' }
								onChange={ ( event ) =>
									update( { time: event.target.value } )
								}
							/>
						</label>
						<label
							className="masthead-series__exception"
							htmlFor={ `masthead-series-${ term.id }-exception` }
						>
							<input
								id={ `masthead-series-${ term.id }-exception` }
								type="checkbox"
								checked={ Boolean(
									term.meta?.masthead_lane_exception
								) }
								onChange={ ( event ) =>
									onChange( term.id, {
										masthead_lane_exception:
											event.target.checked,
									} )
								}
							/>
							<span className="screen-reader-text">
								{ prefix + ' ' }
							</span>
							{ __( 'Lane exception', 'masthead' ) }
						</label>
					</li>
				);
			} ) }
		</ul>
	);
}

/**
 * Series terms from core-data with a way to edit their meta and save the changed terms.
 *
 * @return {{terms: Object[], onChange: Function, saveAll: Function}} Series state.
 */
export function useSeries() {
	const taxonomy = getBoot().series?.taxonomy;
	const terms = useSelect(
		( select ) => {
			if ( ! taxonomy ) {
				return [];
			}
			const store = select( coreStore );
			const records = store.getEntityRecords( 'taxonomy', taxonomy, {
				per_page: SERIES_PAGE_SIZE,
				context: 'edit',
			} );
			return ( records || [] ).map( ( record ) =>
				store.getEditedEntityRecord( 'taxonomy', taxonomy, record.id )
			);
		},
		[ taxonomy ]
	);
	const { editEntityRecord, saveEditedEntityRecord } =
		useDispatch( coreStore );
	const changed = useRef( new Set() );

	const onChange = useCallback(
		( termId, patch ) => {
			const current = terms.find( ( term ) => term.id === termId );
			changed.current.add( termId );
			editEntityRecord( 'taxonomy', taxonomy, termId, {
				meta: { ...( current?.meta || {} ), ...patch },
			} );
		},
		[ terms, taxonomy, editEntityRecord ]
	);

	const saveAll = useCallback( async () => {
		const ids = [ ...changed.current ];
		changed.current = new Set();
		for ( const id of ids ) {
			await saveEditedEntityRecord( 'taxonomy', taxonomy, id );
		}
	}, [ taxonomy, saveEditedEntityRecord ] );

	return { terms, onChange, saveAll };
}
