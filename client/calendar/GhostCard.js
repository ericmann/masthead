import { __ } from '@wordpress/i18n';

const KIND_LABELS = {
	draft: __( 'DRAFT', 'masthead' ),
	series: __( 'SERIES', 'masthead' ),
	reshare: __( 'RESHARE', 'masthead' ),
	gap: __( 'GAP', 'masthead' ),
	idea: __( 'IDEA', 'masthead' ),
};

/**
 * A suggestion for an empty day: dashed, in the lane colour.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.suggestion Suggestion { kind, title }.
 * @param {string}   props.color      Lane colour.
 * @param {Function} props.onSelect   Called when the card is clicked.
 * @return {Element} The card.
 */
export default function GhostCard( { suggestion, color, onSelect } ) {
	return (
		<button
			type="button"
			className="masthead-ghost"
			data-kind={ suggestion.kind }
			style={ { borderColor: color, color } }
			onClick={ onSelect }
		>
			<span className="masthead-ghost__kind">
				{ KIND_LABELS[ suggestion.kind ] ||
					suggestion.kind.toUpperCase() }
			</span>
			<span className="masthead-ghost__title" title={ suggestion.title }>
				{ suggestion.title }
			</span>
		</button>
	);
}
