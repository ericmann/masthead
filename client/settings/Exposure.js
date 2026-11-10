import { __ } from '@wordpress/i18n';

/**
 * The two MCP exposure toggles.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.values     Effective values { exposeMcp, exposeShare }.
 * @param {Function} props.setSetting Called with a dotted settings path and a value.
 * @return {Element} The toggles.
 */
export default function Exposure( { values, setSetting } ) {
	return (
		<fieldset className="masthead-exposure">
			<legend className="screen-reader-text">
				{ __( 'MCP exposure', 'masthead' ) }
			</legend>
			<p>
				<label htmlFor="masthead-expose-mcp">
					<input
						id="masthead-expose-mcp"
						type="checkbox"
						checked={ Boolean( values.exposeMcp ) }
						onChange={ ( event ) =>
							setSetting(
								'abilities.expose_mcp',
								event.target.checked
							)
						}
					/>
					{ __(
						'Expose calendar abilities to MCP clients',
						'masthead'
					) }
				</label>
			</p>
			<p>
				<label htmlFor="masthead-expose-share">
					<input
						id="masthead-expose-share"
						type="checkbox"
						checked={ Boolean( values.exposeShare ) }
						onChange={ ( event ) =>
							setSetting(
								'abilities.expose_share',
								event.target.checked
							)
						}
					/>
					{ __( 'Also expose sharing to MCP clients', 'masthead' ) }
				</label>
			</p>
		</fieldset>
	);
}
