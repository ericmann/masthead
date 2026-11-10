import { __ } from '@wordpress/i18n';

import Sparkle from '../components/Sparkle';

// About 6% of the lane colour over white: every lane colour keeps 4.5:1 on it.
const TINT_ALPHA = '0f';

const KIND_LABELS = {
	draft: __( 'DRAFT', 'masthead' ),
	series: __( 'SERIES', 'masthead' ),
	reshare: __( 'RESHARE', 'masthead' ),
	gap: __( 'GAP', 'masthead' ),
	idea: __( 'IDEA', 'masthead' ),
};

/**
 * A suggestion for an empty day: dashed, tinted and lettered in the lane colour.
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
			style={ {
				borderColor: color,
				color,
				backgroundColor: '#fff',
				backgroundImage: `linear-gradient(${ color }${ TINT_ALPHA }, ${ color }${ TINT_ALPHA })`,
			} }
			onClick={ onSelect }
		>
			<span className="masthead-ghost__kind">
				<Sparkle size={ 10 } />
				{ KIND_LABELS[ suggestion.kind ] ||
					suggestion.kind.toUpperCase() }
			</span>
			<span className="masthead-ghost__title" title={ suggestion.title }>
				{ suggestion.title }
			</span>
		</button>
	);
}
