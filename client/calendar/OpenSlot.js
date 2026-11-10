import { __, sprintf } from '@wordpress/i18n';

/**
 * Marker for a day with no post.
 *
 * @param {Object} props           Props.
 * @param {string} props.laneLabel Lane label.
 * @return {Element} The marker.
 */
export default function OpenSlot( { laneLabel } ) {
	return (
		<div className="masthead-open-slot">
			{ sprintf(
				/* translators: %s: lane label, such as "Standalone". */
				__( 'Open · %s', 'masthead' ),
				laneLabel
			) }
		</div>
	);
}
