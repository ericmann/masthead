import { __ } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import AiSettings, { AiNotice } from './AiSettings';
import DeskSettings from './DeskSettings';
import Exposure from './Exposure';
import RhythmTable from './RhythmTable';
import SeriesCadence, { useSeries } from './SeriesCadence';
import { useSettings } from './useSettings';

/**
 * Reads a dotted path from a nested object.
 *
 * @param {Object} source Nested object.
 * @param {string} path   Dotted path.
 * @return {*} The value, or undefined.
 */
function getPath( source, path ) {
	return path.split( '.' ).reduce( ( node, part ) => node?.[ part ], source );
}

/**
 * A settings card: a titled section with an optional one-line description.
 *
 * @param {Object}  props             Props.
 * @param {string}  props.className   Extra class for grid placement.
 * @param {string}  props.title       Heading.
 * @param {string}  props.description Help text under the heading.
 * @param {Element} props.children    Body.
 * @return {Element} The card.
 */
function Card( { className, title, description = '', children } ) {
	return (
		<section className={ `masthead-card ${ className }` }>
			<header className="masthead-card__head">
				<h2>{ title }</h2>
				{ description ? (
					<p className="description">{ description }</p>
				) : null }
			</header>
			<div className="masthead-card__body">{ children }</div>
		</section>
	);
}

export default function App() {
	const boot = getBoot();
	const {
		rhythm,
		settings,
		setRhythm,
		setSetting,
		save,
		isSaving,
		isSaved,
		error,
	} = useSettings();
	const series = useSeries();
	const exposure = {
		exposeMcp: settings.abilities?.expose_mcp ?? boot.exposure?.exposeMcp,
		exposeShare:
			settings.abilities?.expose_share ?? boot.exposure?.exposeShare,
	};
	const saveAll = async () => {
		await save();
		await series.saveAll();
	};
	const effective = rhythm || boot.rhythm || {};
	const get = ( key ) =>
		getPath( settings, key ) ?? boot.settingsDefaults?.[ key ];

	const supported = Boolean( boot.ai?.supported );

	return (
		<div className="masthead-settings">
			<h1>{ __( 'Calendar Settings', 'masthead' ) }</h1>
			{ supported ? null : (
				<AiNotice connectorsUrl={ boot.urls?.connectors } />
			) }
			{ error ? (
				<div className="notice notice-error inline" role="alert">
					<p>{ error.message }</p>
				</div>
			) : null }
			{ isSaved ? (
				<div className="notice notice-success inline" role="status">
					<p>{ __( 'Settings saved.', 'masthead' ) }</p>
				</div>
			) : null }
			<div className="masthead-settings__grid">
				<Card
					className="masthead-settings__rhythm"
					title={ __( 'Weekly rhythm', 'masthead' ) }
					description={ __(
						"Each weekday's lane sets its categories and default publishing time, and tells the Desk what an empty day is for.",
						'masthead'
					) }
				>
					<RhythmTable
						rhythm={ effective }
						categories={ boot.categories || [] }
						weekStartsOn={ Number( boot.weekStartsOn ?? 0 ) }
						setRhythm={ setRhythm }
					/>
				</Card>
				<Card
					className="masthead-settings__series"
					title={ __( 'Series', 'masthead' ) }
					description={ __(
						'A series is a taxonomy term with a cadence. Planned parts appear on the calendar before any draft exists.',
						'masthead'
					) }
				>
					<SeriesCadence
						terms={ series.terms }
						onChange={ series.onChange }
					/>
				</Card>
				<Card
					className="masthead-settings__ai"
					title={ __( 'Desk (AI suggestions)', 'masthead' ) }
					description={ __(
						'Uses the WordPress AI Client. Whichever provider is active in Connectors does the work; Masthead never stores a key.',
						'masthead'
					) }
				>
					<AiSettings
						supported={ supported }
						get={ get }
						setSetting={ setSetting }
						remaining={ boot.ai?.remaining ?? null }
						models={ boot.ai?.models || {} }
						fallbackCapability={ boot.ai?.fallbackCapability || '' }
					/>
				</Card>
				<Card
					className="masthead-settings__desk"
					title={ __( 'Desk', 'masthead' ) }
					description={ __(
						'How far ahead the Desk looks and what counts as a gap, a reshare or a better swap.',
						'masthead'
					) }
				>
					<DeskSettings get={ get } setSetting={ setSetting } />
				</Card>
				<Card
					className="masthead-settings__exposure"
					title={ __( 'Expose over MCP', 'masthead' ) }
				>
					<Exposure values={ exposure } setSetting={ setSetting } />
				</Card>
			</div>
			<p className="submit">
				<button
					type="button"
					className="button button-primary"
					disabled={ isSaving }
					onClick={ saveAll }
				>
					{ __( 'Save changes', 'masthead' ) }
				</button>
			</p>
		</div>
	);
}
