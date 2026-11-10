import { __, sprintf } from '@wordpress/i18n';

import LaneDot from '../components/LaneDot';
import { monthDayLabel } from '../data/dates';

/**
 * Unscheduled (undated) drafts.
 *
 * @param {Object}   props             Props.
 * @param {Object[]} props.unscheduled PlanPost records of undated drafts.
 * @return {Element} The tray.
 */
export default function DraftTray( { unscheduled } ) {
	return (
		<section
			className="masthead-tray"
			aria-labelledby="masthead-tray-heading"
		>
			<h2 id="masthead-tray-heading">
				{ sprintf(
					/* translators: %d: number of unscheduled drafts. */
					__( 'Unscheduled drafts (%d)', 'masthead' ),
					unscheduled.length
				) }
			</h2>
			{ unscheduled.length === 0 ? (
				<p>{ __( 'No unscheduled drafts.', 'masthead' ) }</p>
			) : (
				<ul className="masthead-tray__rows">
					{ unscheduled.map( ( post ) => (
						<li
							key={ post.id }
							className="masthead-tray__row"
							data-post-id={ post.id }
						>
							<LaneDot color={ post.color || '#50575e' } />
							<span className="masthead-tray__title">
								{ post.title }
							</span>
							{ post.modified ? (
								<span className="masthead-tray__edited">
									{ sprintf(
										/* translators: %s: short date, such as "Oct 29". */
										__( 'edited %s', 'masthead' ),
										monthDayLabel(
											post.modified.slice( 0, 10 )
										)
									) }
								</span>
							) : null }
							<a href={ post.edit_url }>
								{ __( 'Open draft', 'masthead' ) }
								<span className="screen-reader-text">
									{ ' ' + post.title }
								</span>
							</a>
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}
