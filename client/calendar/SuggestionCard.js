import { __ } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import { NEUTRAL_COLOR } from '../data/colors';

export const KIND_LABELS = {
	draft: __( 'DRAFT', 'masthead' ),
	series: __( 'SERIES', 'masthead' ),
	reshare: __( 'RESHARE', 'masthead' ),
	gap: __( 'GAP', 'masthead' ),
	idea: __( 'IDEA', 'masthead' ),
};

/**
 * One Desk suggestion with its reason and the actions the client can perform.
 *
 * @param {Object}   props                 Props.
 * @param {Object}   props.suggestion      Suggestion { key, kind, title, why, actions, post_id? }.
 * @param {string}   props.color           Lane colour for the kind label.
 * @param {Function} props.onPlace         Called with the suggestion ("Place on this day").
 * @param {Function} props.onShare         Called with the suggestion ("Share again").
 * @param {Function} props.onDismiss       Called with the suggestion.
 * @param {Function} props.onPlan          Called with a suggestion ("Plan this").
 * @param {Function} props.onSuggestTopics Called to ask for AI ideas ("Suggest topics").
 * @return {Element} The card.
 */
export default function SuggestionCard( {
	suggestion,
	color = NEUTRAL_COLOR,
	onPlan,
	onPlace,
	onShare,
	onDismiss,
	onSuggestTopics,
} ) {
	const editUrl = suggestion.post_id
		? ( getBoot().urls?.editPost || '' ).replace( '%d', suggestion.post_id )
		: '';

	const renderAction = ( action ) => {
		switch ( action ) {
			case 'schedule':
				return (
					<button
						key={ action }
						type="button"
						className="button button-primary button-compact"
						onClick={ () => onPlace( suggestion ) }
					>
						{ __( 'Place on this day', 'masthead' ) }
					</button>
				);
			case 'open':
				return editUrl ? (
					<a
						key={ action }
						className="button button-compact"
						href={ editUrl }
					>
						{ suggestion.kind === 'draft'
							? __( 'Open draft', 'masthead' )
							: __( 'Open post', 'masthead' ) }
					</a>
				) : null;
			case 'create_draft':
				return onPlan ? (
					<button
						key={ action }
						type="button"
						className="button button-primary button-compact"
						onClick={ () => onPlan( suggestion ) }
					>
						{ __( 'Plan this', 'masthead' ) }
					</button>
				) : null;
			case 'share':
				return (
					<button
						key={ action }
						type="button"
						className="button button-compact"
						onClick={ () => onShare( suggestion ) }
					>
						{ __( 'Share again', 'masthead' ) }
					</button>
				);
			case 'dismiss':
				return (
					<button
						key={ action }
						type="button"
						className="button-link"
						onClick={ () => onDismiss( suggestion ) }
					>
						{ __( 'Dismiss', 'masthead' ) }
					</button>
				);
			case 'suggest_topics':
				return onSuggestTopics ? (
					<button
						key={ action }
						type="button"
						className="button button-compact"
						onClick={ () => onSuggestTopics( suggestion ) }
					>
						{ __( 'Suggest topics', 'masthead' ) }
					</button>
				) : null;
			default:
				return null;
		}
	};

	return (
		<article
			className="masthead-suggestion"
			data-key={ suggestion.key }
			data-kind={ suggestion.kind }
		>
			<p className="masthead-suggestion__kind" style={ { color } }>
				{ KIND_LABELS[ suggestion.kind ] ||
					suggestion.kind.toUpperCase() }
			</p>
			<h3 className="masthead-suggestion__title">{ suggestion.title }</h3>
			<p className="masthead-suggestion__why">{ suggestion.why }</p>
			<div className="masthead-suggestion__actions">
				{ suggestion.actions.map( renderAction ) }
			</div>
		</article>
	);
}
