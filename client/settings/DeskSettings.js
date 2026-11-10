import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const DESK_FIELDS = [
	{
		key: 'desk.horizon_days',
		label: __( 'Planning horizon (days)', 'masthead' ),
		help: __(
			'How far ahead the Desk plans. Fill open days stops here.',
			'masthead'
		),
	},
	{
		key: 'desk.ghost_days',
		label: __( 'Ghost suggestion window (days)', 'masthead' ),
		help: __(
			'Empty days this close show a suggestion card; later ones show “Open · lane”.',
			'masthead'
		),
	},
	{
		key: 'desk.max_suggestions',
		label: __( 'Suggestions per day', 'masthead' ),
		help: __( 'The most suggestions listed for one day.', 'masthead' ),
	},
	{
		key: 'desk.ideas_per_request',
		label: __( 'Ideas per request', 'masthead' ),
		help: __( 'How many AI ideas one request asks for.', 'masthead' ),
	},
	{
		key: 'desk.gap_days',
		label: __( 'Category gap (days)', 'masthead' ),
		help: __(
			'A category with nothing published for this long is a gap.',
			'masthead'
		),
	},
	{
		key: 'desk.reshare_min_age_days',
		label: __( 'Reshare minimum age (days)', 'masthead' ),
		help: __(
			'A post must be this old before the Desk suggests sharing it again.',
			'masthead'
		),
	},
	{
		key: 'desk.reshare_cooldown_days',
		label: __( 'Reshare cooldown (days)', 'masthead' ),
		help: __(
			'Days after a share before the same post is suggested again.',
			'masthead'
		),
	},
	{
		key: 'desk.series_window_days',
		label: __( 'Series date window (days)', 'masthead' ),
		help: __(
			'A series part is due within this many days of its cadence date.',
			'masthead'
		),
	},
];

const SWAP_FIELDS = [
	{
		key: 'swap.lookahead_days',
		label: __( 'Swap check look-ahead (days)', 'masthead' ),
		help: __(
			'How far ahead the nightly swap check reads the schedule.',
			'masthead'
		),
	},
	{
		key: 'swap.candidate_window_days',
		label: __( 'Swap candidate window (days)', 'masthead' ),
		help: __(
			'Other scheduled posts this many days either side are swap candidates.',
			'masthead'
		),
	},
	{
		key: 'stats.window_days',
		label: __( 'Stats window (days)', 'masthead' ),
		help: __( 'Days of Jetpack Stats behind each view count.', 'masthead' ),
	},
];

const UNIT_FIELDS = [
	{
		key: 'swap.min_score_delta',
		label: __( 'Minimum swap improvement (0 to 1)', 'masthead' ),
		help: __(
			'A candidate must beat the scheduled post by this much to raise a finding.',
			'masthead'
		),
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
 * @param {string}   props.help     Optional help text under the input.
 * @param {number}   props.value    Current value.
 * @param {number}   props.min      Minimum.
 * @param {number}   props.max      Maximum.
 * @param {number}   props.step     Step.
 * @param {Function} props.onChange Called with the new number.
 * @return {Element} The field.
 */
export function NumberField( {
	id,
	label,
	help = '',
	value,
	min,
	max,
	step,
	onChange,
} ) {
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
		<div className="masthead-field">
			<label htmlFor={ id }>{ label }</label>
			<input
				id={ id }
				type="number"
				min={ min }
				max={ max }
				step={ step }
				value={ text }
				aria-describedby={ help ? `${ id }-help` : undefined }
				onChange={ ( event ) => {
					setText( event.target.value );
					const next = event.target.valueAsNumber;
					if ( Number.isFinite( next ) ) {
						onChange( next );
					}
				} }
			/>
			{ help ? (
				<p className="description" id={ `${ id }-help` }>
					{ help }
				</p>
			) : null }
		</div>
	);
}

/**
 * Desk, swap-check and stats settings, in two groups of fields.
 *
 * @param {Object}   props            Props.
 * @param {Function} props.get        Returns the effective value of a dotted key.
 * @param {Function} props.setSetting Called with a dotted key and the new value.
 * @return {Element} The fields.
 */
export default function DeskSettings( { get, setSetting } ) {
	const weights = get( 'swap.weights' ) || {};
	const whole = ( field ) => (
		<NumberField
			key={ field.key }
			id={ `masthead-setting-${ field.key }` }
			label={ field.label }
			help={ field.help }
			value={ get( field.key ) }
			min={ 1 }
			step={ 1 }
			onChange={ ( value ) => setSetting( field.key, value ) }
		/>
	);
	return (
		<div className="masthead-desk-settings">
			<fieldset className="masthead-settings__group">
				<legend>{ __( 'Suggestions', 'masthead' ) }</legend>
				<div className="masthead-settings__fields">
					{ DESK_FIELDS.map( whole ) }
				</div>
			</fieldset>
			<fieldset className="masthead-settings__group">
				<legend>{ __( 'Swap check and stats', 'masthead' ) }</legend>
				<div className="masthead-settings__fields">
					{ SWAP_FIELDS.map( whole ) }
					{ UNIT_FIELDS.map( ( field ) => (
						<NumberField
							key={ field.key }
							id={ `masthead-setting-${ field.key }` }
							label={ field.label }
							help={ field.help }
							value={ get( field.key ) }
							min={ 0 }
							max={ 1 }
							step={ 0.05 }
							onChange={ ( value ) =>
								setSetting( field.key, value )
							}
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
				<p className="description">
					{ __(
						'The three weights set how much lane fit, category views and weekday views count in a swap score.',
						'masthead'
					) }
				</p>
			</fieldset>
		</div>
	);
}
