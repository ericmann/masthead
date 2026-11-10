import { __, sprintf } from '@wordpress/i18n';

import LaneDot from '../components/LaneDot';
import { monthDayLabel } from '../data/dates';
import MoveTo from './MoveTo';

/**
 * Unscheduled (undated) drafts.
 *
 * @param {Object}   props             Props.
 * @param {Object[]} props.unscheduled PlanPost records of undated drafts.
 * @param {Function} props.onMove      Called with a post and a date; enables dragging and Move to.
 * @return {Element} The tray.
 */
export default function DraftTray( { unscheduled, onMove } ) {
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
							draggable={ Boolean( onMove ) }
							onDragStart={
								onMove
									? ( event ) => {
											event.dataTransfer.setData(
												'text/plain',
												String( post.id )
											);
											event.dataTransfer.effectAllowed =
												'move';
										}
									: undefined
							}
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
							{ onMove ? (
								<MoveTo post={ post } onMove={ onMove } />
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
