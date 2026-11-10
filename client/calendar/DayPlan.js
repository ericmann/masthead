import { useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import { weekday, weekdayMonthDayLabel } from '../data/dates';
import { runAbility } from '../data/useAbility';
import LaneDot from '../components/LaneDot';

/**
 * Turns a suggestion into a planned draft on a chosen day.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.suggestion The chosen suggestion.
 * @param {string}   props.date       The selected date.
 * @param {string}   props.laneLabel  Lane label of that date.
 * @param {Object[]} props.others     The day's other suggestions.
 * @param {Function} props.onPick     Called with another suggestion to plan instead.
 * @param {Function} props.onCreated  Async; refreshes the plan and the day's suggestions.
 * @param {Function} props.onBack     Returns to the rail.
 * @return {Element} The day plan.
 */
export default function DayPlan( {
	suggestion,
	date,
	laneLabel,
	others = [],
	onPick,
	onCreated,
	onBack,
} ) {
	const boot = getBoot();
	const { saveEntityRecord } = useDispatch( coreStore );
	const isGap = suggestion.kind === 'gap';
	const [ title, setTitle ] = useState( isGap ? '' : suggestion.title );
	const [ time, setTime ] = useState(
		boot.rhythm?.[ weekday( date ) ]?.time || ''
	);
	const [ saveAs, setSaveAs ] = useState( 'dated' );
	const [ busy, setBusy ] = useState( false );
	const [ created, setCreated ] = useState( false );
	const [ error, setError ] = useState( null );

	const category = ( boot.categories || [] ).find(
		( item ) => item.slug === suggestion.category
	);
	const hasOutline =
		Array.isArray( suggestion.outline ) && suggestion.outline.length > 0;
	const canCreate =
		title.trim() !== '' && Boolean( suggestion.category ) && time !== '';

	const create = async () => {
		setBusy( true );
		setError( null );
		let result;
		try {
			const input = {
				date,
				time,
				title: title.trim(),
				category: suggestion.category,
			};
			if ( suggestion.series ) {
				input.series = suggestion.series;
			}
			if ( hasOutline ) {
				input.outline = suggestion.outline;
			}
			result = await runAbility( 'masthead/create-planned-draft', input );
		} catch ( caught ) {
			setError( caught.message );
			setBusy( false );
			return;
		}
		setCreated( true );
		if ( saveAs === 'scheduled' ) {
			try {
				await saveEntityRecord( 'postType', 'post', {
					id: result.post_id,
					status: 'future',
				} );
			} catch ( caught ) {
				setError(
					sprintf(
						/* translators: %s: error message. */
						__(
							'The draft was kept, but scheduling failed: %s',
							'masthead'
						),
						caught.message
					)
				);
				await onCreated();
				setBusy( false );
				return;
			}
		}
		await onCreated();
		setBusy( false );
		onBack();
	};

	return (
		<section
			className="masthead-dayplan"
			aria-label={ __( 'Day plan', 'masthead' ) }
		>
			<h2>
				{ sprintf(
					/* translators: 1: day label such as "Wednesday, Nov 18", 2: lane label. */
					__( 'Plan %1$s · %2$s lane', 'masthead' ),
					weekdayMonthDayLabel( date ),
					laneLabel
				) }
			</h2>
			<p>
				<label htmlFor="masthead-dayplan-title">
					{ __( 'Title', 'masthead' ) }
				</label>
				<input
					id="masthead-dayplan-title"
					type="text"
					value={ title }
					required={ isGap }
					disabled={ created }
					onChange={ ( event ) => setTitle( event.target.value ) }
				/>
			</p>
			{ category ? (
				<p className="masthead-dayplan__category">
					<LaneDot color={ category.color } label={ category.name } />
				</p>
			) : null }
			{ hasOutline ? (
				<ol className="masthead-dayplan__outline">
					{ suggestion.outline.map( ( item ) => (
						<li key={ item }>{ item }</li>
					) ) }
				</ol>
			) : null }
			<h3>{ __( 'Why this day', 'masthead' ) }</h3>
			<p>{ suggestion.why }</p>
			<p>
				<label htmlFor="masthead-dayplan-time">
					{ __( 'Time', 'masthead' ) }
				</label>
				<input
					id="masthead-dayplan-time"
					type="time"
					value={ time }
					disabled={ created }
					onChange={ ( event ) => setTime( event.target.value ) }
				/>{ ' ' }
				<span className="masthead-dayplan__date">{ date }</span>
			</p>
			<p>
				<label htmlFor="masthead-dayplan-save-as">
					{ __( 'Save as', 'masthead' ) }
				</label>
				<select
					id="masthead-dayplan-save-as"
					value={ saveAs }
					disabled={ created }
					onChange={ ( event ) => setSaveAs( event.target.value ) }
				>
					<option value="dated">
						{ __( 'Dated draft', 'masthead' ) }
					</option>
					<option value="scheduled">
						{ __( 'Scheduled', 'masthead' ) }
					</option>
				</select>
			</p>
			{ error ? (
				<p role="alert" className="masthead-dayplan__error">
					{ error }
				</p>
			) : null }
			<p>
				<button
					type="button"
					className="button button-primary"
					disabled={ ! canCreate || busy || created }
					onClick={ create }
				>
					{ hasOutline
						? __( 'Create draft with outline', 'masthead' )
						: __( 'Create draft', 'masthead' ) }
				</button>{ ' ' }
				<button
					type="button"
					className="button-link"
					onClick={ onBack }
				>
					{ __( 'Back to suggestions', 'masthead' ) }
				</button>
			</p>
			{ others.length > 0 ? (
				<div className="masthead-dayplan__others">
					<h3>{ __( 'Other options for this day', 'masthead' ) }</h3>
					<ul>
						{ others.map( ( other ) => (
							<li key={ other.key }>
								<button
									type="button"
									className="button-link"
									onClick={ () => onPick( other ) }
								>
									{ other.title }
								</button>
							</li>
						) ) }
					</ul>
				</div>
			) : null }
		</section>
	);
}
