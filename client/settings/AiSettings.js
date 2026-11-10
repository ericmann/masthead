import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { NumberField } from './DeskSettings';

const TIERS = [
	{
		tier: 'fast',
		key: 'ai.models.fast',
		label: __( 'Fast model ids', 'masthead' ),
	},
	{
		tier: 'strong',
		key: 'ai.models.strong',
		label: __( 'Strong model ids', 'masthead' ),
	},
];

/**
 * A comma-separated list input that keeps what the user typed and reports the parsed list.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.id       Input ID.
 * @param {string}   props.label    Label.
 * @param {string[]} props.value    Current list.
 * @param {Function} props.onChange Called with a non-empty list.
 * @return {Element} The field.
 */
function ListField( { id, label, value, onChange } ) {
	const [ text, setText ] = useState( ( value || [] ).join( ', ' ) );
	return (
		<div className="masthead-field">
			<label htmlFor={ id }>{ label }</label>
			<input
				id={ id }
				type="text"
				value={ text }
				onChange={ ( event ) => {
					setText( event.target.value );
					const list = event.target.value
						.split( ',' )
						.map( ( item ) => item.trim() )
						.filter( Boolean );
					if ( list.length > 0 ) {
						onChange( list );
					}
				} }
			/>
		</div>
	);
}

/**
 * The page-level notice shown when no AI provider is active.
 *
 * @param {Object} props               Props.
 * @param {string} props.connectorsUrl Settings > Connectors URL.
 * @return {Element} The notice.
 */
export function AiNotice( { connectorsUrl } ) {
	return (
		<div className="notice notice-warning inline masthead-ai-notice">
			<p>
				{ __(
					'No AI provider is configured, so Desk suggestions are off. The calendar, lanes and series still work.',
					'masthead'
				) }
			</p>
			<p>
				<a href={ connectorsUrl }>
					{ __( 'Set up in Connectors', 'masthead' ) }
				</a>
			</p>
		</div>
	);
}

/**
 * AI provider status and the AI settings.
 *
 * @param {Object}   props                    Props.
 * @param {boolean}  props.supported          Whether a provider supports text generation.
 * @param {Function} props.get                Returns the effective value of a dotted key.
 * @param {Function} props.setSetting         Called with a dotted key and the new value.
 * @param {number}   props.remaining          Requests left this month.
 * @param {Object}   props.models             Last model id used per tier.
 * @param {string}   props.fallbackCapability Capability that receives prompt_ai when nothing else sets it.
 * @return {Element} The section.
 */
export default function AiSettings( {
	supported,
	get = () => undefined,
	setSetting = () => {},
	remaining = null,
	models = {},
	fallbackCapability = '',
} ) {
	const known = TIERS.filter( ( { tier } ) => models?.[ tier ] );
	return (
		<div className="masthead-ai masthead-settings__columns">
			<div className="masthead-settings__column">
				<h3>{ __( 'Provider', 'masthead' ) }</h3>
				<p className="masthead-ai__status">
					{ supported
						? __( 'Provider: available', 'masthead' )
						: __( 'Provider: none active', 'masthead' ) }
				</p>
				<p className="description masthead-ai__access">
					{ fallbackCapability
						? sprintf(
								/* translators: %s: capability name, such as publish_posts. */
								__(
									'The AI features need the prompt_ai capability. WordPress does not give it to any role, so Masthead grants it to users who have %s. A role or plugin that grants or denies prompt_ai itself takes precedence.',
									'masthead'
								),
								fallbackCapability
							)
						: __(
								'The AI features need the prompt_ai capability.',
								'masthead'
							) }
				</p>
			</div>
			<div className="masthead-settings__column">
				<h3>{ __( 'Model preference', 'masthead' ) }</h3>
				{ TIERS.map( ( { key, label } ) => (
					<ListField
						key={ key }
						id={ `masthead-setting-${ key }` }
						label={ label }
						value={ get( key ) }
						onChange={ ( list ) => setSetting( key, list ) }
					/>
				) ) }
				<p className="description">
					{ __(
						"Used only if the active provider offers them; otherwise the provider's default model is used.",
						'masthead'
					) }
				</p>
				{ known.length > 0 ? (
					<ul className="masthead-ai__models">
						{ known.map( ( { tier, label } ) => (
							<li key={ tier }>
								{ sprintf(
									/* translators: 1: tier name, 2: model id. */
									__(
										'Last model used (%1$s): %2$s',
										'masthead'
									),
									label
										.replace( / model ids$/, '' )
										.toLowerCase(),
									models[ tier ]
								) }
							</li>
						) ) }
					</ul>
				) : (
					<p className="description">
						{ __(
							'The AI Client does not report which model was used.',
							'masthead'
						) }
					</p>
				) }
			</div>
			<div className="masthead-settings__column">
				<h3>{ __( 'Budget', 'masthead' ) }</h3>
				<NumberField
					id="masthead-setting-ai.monthly_request_cap"
					label={ __( 'Monthly AI request cap', 'masthead' ) }
					help={ __( "0 turns the Desk's AI off.", 'masthead' ) }
					value={ get( 'ai.monthly_request_cap' ) }
					min={ 0 }
					step={ 1 }
					onChange={ ( value ) =>
						setSetting( 'ai.monthly_request_cap', value )
					}
				/>
				{ remaining === null ? null : (
					<p className="masthead-ai__remaining">
						{ sprintf(
							/* translators: %d: number of requests. */
							_n(
								'%d request left this month',
								'%d requests left this month',
								remaining,
								'masthead'
							),
							remaining
						) }
					</p>
				) }
			</div>
			<div className="masthead-settings__column">
				<h3>{ __( 'What the Desk may read', 'masthead' ) }</h3>
				<NumberField
					id="masthead-setting-ai.context_days"
					label={ __( 'Context window (days)', 'masthead' ) }
					help={ __(
						'Recent posts from this many days go to the model as context.',
						'masthead'
					) }
					value={ get( 'ai.context_days' ) }
					min={ 1 }
					step={ 1 }
					onChange={ ( value ) =>
						setSetting( 'ai.context_days', value )
					}
				/>
				<p className="masthead-checkbox">
					<label htmlFor="masthead-setting-ai.include_draft_excerpts">
						<input
							id="masthead-setting-ai.include_draft_excerpts"
							type="checkbox"
							aria-describedby="masthead-setting-ai.include_draft_excerpts-help"
							checked={ Boolean(
								get( 'ai.include_draft_excerpts' )
							) }
							onChange={ ( event ) =>
								setSetting(
									'ai.include_draft_excerpts',
									event.target.checked
								)
							}
						/>
						{ __( 'Draft titles and excerpts', 'masthead' ) }
					</label>
				</p>
				<p
					className="description"
					id="masthead-setting-ai.include_draft_excerpts-help"
				>
					{ __(
						'Only titles and excerpts; post bodies are never sent.',
						'masthead'
					) }
				</p>
			</div>
		</div>
	);
}
