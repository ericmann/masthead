import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import StatValue from '../components/StatValue';
import { runAbility } from '../data/useAbility';

const TYPE_LABELS = {
	lane_mismatch: __( 'Lane mismatch', 'masthead' ),
	collision: __( 'Collision', 'masthead' ),
	cadence_break: __( 'Cadence break', 'masthead' ),
	better_candidate: __( 'Better candidate', 'masthead' ),
};

const STAT_LABELS = [
	[
		'scheduled_category_views',
		__( "Views in this post's category", 'masthead' ),
	],
	[
		'candidate_category_views',
		__( "Views in the candidate's category", 'masthead' ),
	],
	[ 'weekday_avg_views', __( 'Average views on this weekday', 'masthead' ) ],
];

/**
 * Runs one option: its ability then each also call in order (stopping at the first error), or a wp/v2 request.
 *
 * @param {Object} option Finding option.
 */
async function applyOption( option ) {
	if ( option.ability === null && option.input?.rest ) {
		const { path, method, data } = option.input.rest;
		await apiFetch( { path, method, data } );
		return;
	}
	await runAbility( option.ability, option.input );
	for ( const call of option.also || [] ) {
		await runAbility( call.ability, call.input );
	}
}

/**
 * Swap-check findings for a scheduled post, with the options to act on them.
 *
 * @param {Object}   props           Props.
 * @param {Object}   props.post      The selected PlanPost.
 * @param {Object[]} props.findings  Findings for the post.
 * @param {Function} props.onApplied Async; refreshes the plan and the findings after an option ran.
 * @param {Function} props.onClose   Closes the panel.
 * @return {Element} The panel.
 */
export default function SwapPanel( { post, findings, onApplied, onClose } ) {
	const [ outcome, setOutcome ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const apply = async ( option ) => {
		setBusy( true );
		setOutcome( '' );
		try {
			await applyOption( option );
			await onApplied();
			setOutcome( __( 'Done.', 'masthead' ) );
		} catch ( error ) {
			await onApplied();
			setOutcome(
				error?.message || __( 'That did not work.', 'masthead' )
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<section
			className="masthead-swap"
			aria-label={ __( 'Swap check', 'masthead' ) }
		>
			<h2>
				{ sprintf(
					/* translators: %s: post title. */
					__( 'Swap check · %s', 'masthead' ),
					post.title
				) }
			</h2>
			{ findings.length === 0 ? (
				<p>
					{ __( 'No findings. This post fits its day.', 'masthead' ) }
				</p>
			) : (
				findings.map( ( finding ) => (
					<article
						key={ finding.key }
						className="masthead-swap__finding"
						data-type={ finding.type }
					>
						<h3>{ TYPE_LABELS[ finding.type ] || finding.type }</h3>
						<p>{ finding.why }</p>
						{ finding.numbers ? (
							<ul className="masthead-swap__numbers">
								{ STAT_LABELS.filter( ( [ name ] ) =>
									Object.keys(
										finding.numbers.values
									).includes( name )
								).map( ( [ name, label ] ) => (
									<li key={ name }>
										<StatValue
											numbers={ finding.numbers }
											name={ name }
											label={ label }
										/>
									</li>
								) ) }
							</ul>
						) : null }
						{ finding.options.map( ( option ) => (
							<p key={ option.label }>
								<button
									type="button"
									className="button"
									disabled={ busy }
									onClick={ () => apply( option ) }
								>
									{ option.label }
								</button>
								{ option.also?.length ? (
									<span className="masthead-swap__count">
										{ sprintf(
											/* translators: %d: number of posts rescheduled. */
											__(
												'Reschedules %d posts',
												'masthead'
											),
											option.also.length + 1
										) }
									</span>
								) : null }
							</p>
						) ) }
					</article>
				) )
			) }
			<p aria-live="polite" className="masthead-swap__outcome">
				{ outcome }
			</p>
			<p>
				<button
					type="button"
					className="button-link"
					onClick={ onClose }
				>
					{ __( 'Keep as is', 'masthead' ) }
				</button>
			</p>
		</section>
	);
}
