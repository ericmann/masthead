import { __, sprintf } from '@wordpress/i18n';

import { timeLabel } from '../data/dates';
import MoveTo from './MoveTo';

const DRAFT_TINT_ALPHA = '24';

/**
 * One post as a chip. Styles: published (solid), scheduled (outlined), dated draft (tinted).
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.post     PlanPost.
 * @param {Function} props.onSelect Called with the post.
 * @param {Function} props.onMove   Called with the post and a date; enables dragging and Move to.
 * @return {Element} The chip.
 */
export default function PostChip( { post, onSelect, onMove } ) {
	const color = post.color || '#50575e';
	const time = timeLabel( post.date );
	const movable =
		Boolean( onMove ) &&
		( post.status === 'future' || post.status === 'draft' );
	let meta;
	let style;

	if ( post.status === 'publish' ) {
		style = { backgroundColor: color, borderColor: color, color: '#fff' };
		meta = sprintf(
			/* translators: %s: time of day. */
			__( '✓ Published · %s', 'masthead' ),
			time
		);
	} else if ( post.status === 'future' ) {
		style = {
			backgroundColor: '#fff',
			borderColor: color,
			borderLeftColor: color,
			color,
		};
		meta = sprintf(
			/* translators: %s: time of day. */
			__( '◷ Scheduled · %s', 'masthead' ),
			time
		);
	} else {
		style = {
			backgroundColor: `${ color }${ DRAFT_TINT_ALPHA }`,
			borderColor: color,
			color: '#1d2327',
		};
		meta = sprintf(
			/* translators: %s: time of day. */
			__( 'Draft · %s', 'masthead' ),
			time
		);
	}

	return (
		<>
			<button
				type="button"
				className={ `masthead-chip masthead-chip--${ post.status }` }
				data-post-id={ post.id }
				data-status={ post.status }
				title={ post.title }
				draggable={ movable }
				onDragStart={
					movable
						? ( event ) => {
								event.dataTransfer.setData(
									'text/plain',
									String( post.id )
								);
								event.dataTransfer.effectAllowed = 'move';
							}
						: undefined
				}
				style={ style }
				onClick={ () => onSelect( post ) }
			>
				<span className="masthead-chip__title">{ post.title }</span>
				<span className="masthead-chip__meta">{ meta }</span>
			</button>
			{ movable ? <MoveTo post={ post } onMove={ onMove } /> : null }
		</>
	);
}
