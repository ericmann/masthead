import { __ } from '@wordpress/i18n';

/**
 * AI provider status. No editable field in this phase.
 *
 * @param {Object}  props               Props.
 * @param {boolean} props.supported     Whether a provider supports text generation.
 * @param {string}  props.connectorsUrl Settings > Connectors URL.
 * @return {Element} The status.
 */
export default function AiSettings( { supported, connectorsUrl } ) {
	return (
		<div className="masthead-ai">
			<p>
				{ supported
					? __( 'Provider: available', 'masthead' )
					: __( 'Provider: none active', 'masthead' ) }
			</p>
			{ supported ? null : (
				<div className="notice notice-warning inline">
					<p>
						{ __(
							'No AI provider is configured, so Desk suggestions are off. The calendar, lanes and series still work.',
							'masthead'
						) }{ ' ' }
						<a href={ connectorsUrl }>
							{ __( 'Set up in Connectors', 'masthead' ) }
						</a>
					</p>
				</div>
			) }
		</div>
	);
}
