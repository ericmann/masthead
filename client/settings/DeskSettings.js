import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const FIELDS = [
	{
		key: 'desk.horizon_days',
		label: __( 'Planning horizon (days)', 'masthead' ),
	},
	{
		key: 'desk.ghost_days',
		label: __( 'Ghost suggestion window (days)', 'masthead' ),
	},
	{
		key: 'desk.max_suggestions',
		label: __( 'Suggestions per day', 'masthead' ),
	},
	{
		key: 'desk.ideas_per_request',
		label: __( 'Ideas per request', 'masthead' ),
	},
	{ key: 'desk.gap_days', label: __( 'Category gap (days)', 'masthead' ) },
	{
		key: 'desk.reshare_min_age_days',
		label: __( 'Reshare minimum age (days)', 'masthead' ),
	},
	{
		key: 'desk.reshare_cooldown_days',
		label: __( 'Reshare cooldown (days)', 'masthead' ),
	},
	{
		key: 'desk.series_window_days',
		label: __( 'Series date window (days)', 'masthead' ),
	},
	{
		key: 'swap.lookahead_days',
		label: __( 'Swap check look-ahead (days)', 'masthead' ),
	},
	{
		key: 'swap.candidate_window_days',
		label: __( 'Swap candidate window (days)', 'masthead' ),
	},
	{
		key: 'stats.window_days',
		label: __( 'Stats window (days)', 'masthead' ),
	},
];

const UNIT_FIELDS = [
	{
		key: 'swap.min_score_delta',
		label: __( 'Minimum swap improvement (0 to 1)', 'masthead' ),
	},
];

const WEIGHTS = [
	{ name: 'lane', label: __( 'Swap weight: lane (0 to 1)', 'masthead' ) },
	{
		name: 'category',
		label: __( 'Swap weight: category (0 to 1)', 'masthead' ),
	},
	{
		name: 'weekday',
		label: __( 'Swap weight: weekday (0 to 1)', 'masthead' ),
	},
];

/**
 * A number input that keeps what the user typed and reports a change only when it holds a valid number.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.id       Input ID.
 * @param {string}   props.label    Label.
 * @param {number}   props.value    Current value.
 * @param {number}   props.min      Minimum.
 * @param {number}   props.max      Maximum.
 * @param {number}   props.step     Step.
 * @param {Function} props.onChange Called with the new number.
 * @return {Element} The field.
 */
export function NumberField( { id, label, value, min, max, step, onChange } ) {
	const [ text, setText ] = useState( String( value ?? '' ) );
	// Follow the value when it changes from outside what was typed.
	if (
		Number( text ) !== Number( value ) &&
		text !== '' &&
		value !== undefined
	) {
		setText( String( value ) );
	}
	return (
		<p>
			<label htmlFor={ id }>{ label }</label>{ ' ' }
			<input
				id={ id }
				type="number"
				min={ min }
				max={ max }
				step={ step }
				value={ text }
				onChange={ ( event ) => {
					setText( event.target.value );
					const next = event.target.valueAsNumber;
					if ( Number.isFinite( next ) ) {
						onChange( next );
					}
				} }
			/>
		</p>
	);
}

/**
 * Desk, swap-check and stats settings.
 *
 * @param {Object}   props            Props.
 * @param {Function} props.get        Returns the effective value of a dotted key.
 * @param {Function} props.setSetting Called with a dotted key and the new value.
 * @return {Element} The fields.
 */
export default function DeskSettings( { get, setSetting } ) {
	const weights = get( 'swap.weights' ) || {};
	return (
		<div className="masthead-desk-settings">
			{ FIELDS.map( ( field ) => (
				<NumberField
					key={ field.key }
					id={ `masthead-setting-${ field.key }` }
					label={ field.label }
					value={ get( field.key ) }
					min={ 1 }
					step={ 1 }
					onChange={ ( value ) => setSetting( field.key, value ) }
				/>
			) ) }
			{ UNIT_FIELDS.map( ( field ) => (
				<NumberField
					key={ field.key }
					id={ `masthead-setting-${ field.key }` }
					label={ field.label }
					value={ get( field.key ) }
					min={ 0 }
					max={ 1 }
					step={ 0.05 }
					onChange={ ( value ) => setSetting( field.key, value ) }
				/>
			) ) }
			{ WEIGHTS.map( ( weight ) => (
				<NumberField
					key={ weight.name }
					id={ `masthead-setting-swap.weights.${ weight.name }` }
					label={ weight.label }
					value={ weights[ weight.name ] }
					min={ 0 }
					max={ 1 }
					step={ 0.05 }
					onChange={ ( value ) =>
						setSetting( 'swap.weights', {
							...weights,
							[ weight.name ]: value,
						} )
					}
				/>
			) ) }
		</div>
	);
}
