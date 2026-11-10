import { __, sprintf } from '@wordpress/i18n';

import { timeLabel } from '../data/dates';

/**
 * The only component that renders a statistic. A number always appears with its source and time.
 *
 * @param {Object}      props         Props.
 * @param {Object|null} props.numbers { source, fetched_at, values } or null when unavailable.
 * @param {string}      props.name    Key of numbers.values.
 * @param {string}      props.label   Label for the number.
 * @return {Element} The stat.
 */
export default function StatValue( { numbers, name, label } ) {
	const value = numbers?.values?.[ name ];

	if ( ! numbers || value === null || value === undefined ) {
		return (
			<span className="masthead-stat masthead-stat--unavailable">
				<span className="masthead-stat__label">{ label }</span>{ ' ' }
				<span className="masthead-stat__value">
					{ __( 'Unavailable', 'masthead' ) }
				</span>
			</span>
		);
	}

	const fetched = numbers.fetched_at
		? `${ numbers.fetched_at.slice( 0, 10 ) } ${ timeLabel(
				numbers.fetched_at
			) }`.trim()
		: '';

	return (
		<span className="masthead-stat">
			<span className="masthead-stat__label">{ label }</span>{ ' ' }
			<span className="masthead-stat__value">
				{ new Intl.NumberFormat().format( value ) }
			</span>{ ' ' }
			<span className="masthead-stat__source">
				{ fetched
					? sprintf(
							/* translators: 1: data source, 2: date and time the numbers were fetched. */
							__( '%1$s, %2$s', 'masthead' ),
							numbers.source,
							fetched
						)
					: numbers.source }
			</span>
		</span>
	);
}
