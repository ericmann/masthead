import { gmdateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

const DAYS_IN_WEEK = 7;
// 1970-01-04 was a Sunday.
const SUNDAY_1970 = Date.UTC( 1970, 0, 4 );
const MS_PER_DAY = 86400000;

/**
 * Lane slug from a label.
 *
 * @param {string} label Lane label.
 * @return {string} Slug.
 */
export function slugify( label ) {
	return label
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
}

/**
 * The weekly rhythm: one row per weekday starting at the site's week start.
 *
 * @param {Object}   props              Props.
 * @param {Object}   props.rhythm       Rhythm keyed by weekday "0".."6".
 * @param {Object[]} props.categories   { slug, name } options.
 * @param {number}   props.weekStartsOn First weekday, 0 = Sunday.
 * @param {Function} props.setRhythm    Called with the whole new rhythm.
 * @return {Element} The table.
 */
export default function RhythmTable( {
	rhythm,
	categories,
	weekStartsOn,
	setRhythm,
} ) {
	const update = ( weekday, changes ) =>
		setRhythm( {
			...rhythm,
			[ weekday ]: { ...rhythm[ weekday ], ...changes },
		} );

	const rows = Array.from( { length: DAYS_IN_WEEK }, ( _, i ) => {
		const weekday = ( weekStartsOn + i ) % DAYS_IN_WEEK;
		return {
			weekday,
			name: gmdateI18n(
				'l',
				new Date( SUNDAY_1970 + weekday * MS_PER_DAY )
			),
			day: rhythm[ weekday ],
		};
	} );

	return (
		<table className="widefat masthead-rhythm">
			<thead>
				<tr>
					<th scope="col">{ __( 'Day', 'masthead' ) }</th>
					<th scope="col">{ __( 'Lane', 'masthead' ) }</th>
					<th scope="col">{ __( 'Categories', 'masthead' ) }</th>
					<th scope="col">{ __( 'Default time', 'masthead' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( { weekday, name, day } ) => (
					<tr key={ weekday } data-weekday={ weekday }>
						<th scope="row">{ name }</th>
						<td>
							<input
								type="text"
								value={ day.label }
								aria-label={ sprintf(
									/* translators: %s: weekday name. */
									__( '%s lane label', 'masthead' ),
									name
								) }
								onChange={ ( event ) =>
									update( weekday, {
										label: event.target.value,
										lane:
											slugify( event.target.value ) ||
											day.lane,
									} )
								}
							/>
						</td>
						<td>
							<select
								multiple
								value={ day.categories }
								aria-label={ sprintf(
									/* translators: %s: weekday name. */
									__( '%s categories', 'masthead' ),
									name
								) }
								onChange={ ( event ) =>
									update( weekday, {
										categories: Array.from(
											event.target.selectedOptions,
											( option ) => option.value
										),
									} )
								}
							>
								{ categories.map( ( category ) => (
									<option
										key={ category.slug }
										value={ category.slug }
									>
										{ category.name }
									</option>
								) ) }
							</select>
							{ day.categories.length === 0 ? (
								<span className="masthead-rhythm__any">
									{ __( 'Any category', 'masthead' ) }
								</span>
							) : null }
						</td>
						<td>
							<input
								type="time"
								value={ day.time }
								aria-label={ sprintf(
									/* translators: %s: weekday name. */
									__( '%s default time', 'masthead' ),
									name
								) }
								onChange={ ( event ) =>
									update( weekday, {
										time: event.target.value,
									} )
								}
							/>
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}
