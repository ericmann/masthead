import { __, sprintf } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import { monthDayLabel, shortDayLabel, timeLabel } from '../data/dates';

const STATUS_LABELS = {
	publish: __( 'Published', 'masthead' ),
	future: __( 'Scheduled', 'masthead' ),
	draft: __( 'Draft', 'masthead' ),
};

/**
 * List view: the upcoming queue in date order.
 *
 * @param {Object}      props              Props.
 * @param {Object[]}    props.days         Plan days.
 * @param {Set<string>} props.hidden       Hidden category slugs.
 * @param {Function}    props.onSelectPost Called with a post.
 * @param {Object|null} props.findings     useFindings() result; adds Fits or Review verdicts.
 * @return {Element} The list.
 */
export default function ListView( {
	days,
	hidden = new Set(),
	onSelectPost,
	findings = null,
} ) {
	const names = new Map(
		( getBoot().categories || [] ).map( ( category ) => [
			category.slug,
			category.name,
		] )
	);
	const posts = days
		.flatMap( ( day ) =>
			day.posts.map( ( post ) => ( { post, date: day.date } ) )
		)
		.filter(
			( { post } ) =>
				! post.primary_category || ! hidden.has( post.primary_category )
		);
	const scheduled = posts.filter(
		( { post } ) => post.status === 'future'
	).length;

	return (
		<section
			className="masthead-list"
			aria-labelledby="masthead-list-heading"
		>
			<h2 id="masthead-list-heading">
				{ sprintf(
					/* translators: %d: number of scheduled posts. */
					__( 'Scheduled (%d)', 'masthead' ),
					scheduled
				) }
			</h2>
			{ findings ? (
				<p className="masthead-list__checked">
					{ findings.generatedAt
						? sprintf(
								/* translators: %s: short date, such as "Nov 9". */
								__( 'Swap check ran %s', 'masthead' ),
								monthDayLabel(
									findings.generatedAt.slice( 0, 10 )
								)
							)
						: __( 'Swap check has not run yet', 'masthead' ) }
				</p>
			) : null }
			<ul className="masthead-list__rows">
				{ posts.map( ( { post, date } ) => (
					<li key={ post.id }>
						<button
							type="button"
							className="masthead-list__row"
							data-post-id={ post.id }
							data-status={ post.status }
							onClick={ () => onSelectPost( post ) }
						>
							<span
								className="masthead-list__bar"
								style={ { backgroundColor: post.color } }
								aria-hidden="true"
							/>
							<span className="masthead-list__when">
								{ [
									shortDayLabel( date ),
									timeLabel( post.date ),
									post.categories
										.map(
											( slug ) =>
												names.get( slug ) || slug
										)
										.join( ', ' ),
								]
									.filter( Boolean )
									.join( ' · ' ) }
							</span>
							<span className="masthead-list__title">
								{ post.title }
							</span>
							<span className="masthead-list__status">
								{ STATUS_LABELS[ post.status ] || post.status }
							</span>{ ' ' }
							{ findings &&
							( post.status === 'future' ||
								post.status === 'draft' ) ? (
								<span
									className="masthead-list__verdict"
									data-verdict={
										findings
											.byPost( post.id )
											.some(
												( finding ) =>
													finding.severity ===
													'review'
											)
											? 'review'
											: 'fits'
									}
								>
									{ findings
										.byPost( post.id )
										.some(
											( finding ) =>
												finding.severity === 'review'
										)
										? __( 'Review', 'masthead' )
										: __( 'Fits', 'masthead' ) }
								</span>
							) : null }
						</button>
					</li>
				) ) }
			</ul>
		</section>
	);
}
