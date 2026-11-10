import { __, sprintf } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import { shortDayLabel, timeLabel } from '../data/dates';

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
 * @return {Element} The list.
 */
export default function ListView( { days, hidden = new Set(), onSelectPost } ) {
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
							</span>
						</button>
					</li>
				) ) }
			</ul>
		</section>
	);
}
