import { __, sprintf } from '@wordpress/i18n';

import LaneDot from '../components/LaneDot';
import { NEUTRAL_COLOR } from '../data/colors';

/**
 * A weekday's categories: the chosen ones as coloured dots, opening to a checkbox list.
 *
 * @param {Object}   props            Props.
 * @param {string}   props.idPrefix   Prefix for the checkbox IDs, unique per weekday.
 * @param {string}   props.dayName    Weekday name, such as "Thursday".
 * @param {Object[]} props.categories { slug, name, color } options.
 * @param {string[]} props.value      Chosen slugs, in stored order.
 * @param {Function} props.onChange   Called with the new list of slugs.
 * @return {Element} The picker.
 */
export default function CategoryPicker( {
	idPrefix,
	dayName,
	categories,
	value,
	onChange,
} ) {
	// A stored slug the site no longer lists (deleted, or past the category cap) stays visible and removable.
	const options = [
		...categories,
		...value
			.filter(
				( slug ) =>
					! categories.some( ( category ) => category.slug === slug )
			)
			.map( ( slug ) => ( {
				slug,
				name: slug,
				color: NEUTRAL_COLOR,
			} ) ),
	];
	const chosen = value
		.map( ( slug ) => options.find( ( option ) => option.slug === slug ) )
		.filter( Boolean );
	const toggle = ( slug, checked ) => {
		const next = checked
			? [ ...value, slug ]
			: value.filter( ( item ) => item !== slug );
		// Keep the option order so the first category (the lane colour) is predictable.
		onChange(
			options
				.map( ( option ) => option.slug )
				.filter( ( item ) => next.includes( item ) )
		);
	};

	return (
		<details className="masthead-picker">
			<summary>
				<span className="screen-reader-text">
					{ sprintf(
						/* translators: %s: weekday name. */
						__( '%s categories:', 'masthead' ),
						dayName
					) + ' ' }
				</span>
				{ chosen.length > 0 ? (
					chosen.map( ( category ) => (
						<LaneDot
							key={ category.slug }
							color={ category.color }
							label={ category.name }
						/>
					) )
				) : (
					<span className="masthead-rhythm__any">
						{ __( 'Any category', 'masthead' ) }
					</span>
				) }
			</summary>
			<fieldset>
				<legend className="screen-reader-text">
					{ sprintf(
						/* translators: %s: weekday name. */
						__( '%s categories', 'masthead' ),
						dayName
					) }
				</legend>
				{ options.map( ( category ) => (
					<label
						key={ category.slug }
						htmlFor={ `${ idPrefix }-${ category.slug }` }
					>
						<input
							id={ `${ idPrefix }-${ category.slug }` }
							type="checkbox"
							value={ category.slug }
							checked={ value.includes( category.slug ) }
							onChange={ ( event ) =>
								toggle( category.slug, event.target.checked )
							}
						/>
						<LaneDot
							color={ category.color }
							label={ category.name }
						/>
					</label>
				) ) }
			</fieldset>
		</details>
	);
}
