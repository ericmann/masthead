import { __ } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import AiSettings from './AiSettings';
import Exposure from './Exposure';
import RhythmTable from './RhythmTable';
import SeriesCadence, { useSeries } from './SeriesCadence';
import { useSettings } from './useSettings';

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

	return (
		<div className="masthead-settings">
			<h1>{ __( 'Calendar Settings', 'masthead' ) }</h1>
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
			<section className="masthead-settings__section">
				<h2>{ __( 'Weekly rhythm', 'masthead' ) }</h2>
				<p>
					{ __(
						'Each weekday has a lane: the categories that belong on that day and the default publishing time.',
						'masthead'
					) }
				</p>
				<RhythmTable
					rhythm={ effective }
					categories={ boot.categories || [] }
					weekStartsOn={ Number( boot.weekStartsOn ?? 0 ) }
					setRhythm={ setRhythm }
				/>
			</section>
			<section className="masthead-settings__section">
				<h2>{ __( 'Series cadence', 'masthead' ) }</h2>
				<SeriesCadence
					terms={ series.terms }
					onChange={ series.onChange }
				/>
			</section>
			<section className="masthead-settings__section">
				<h2>{ __( 'MCP exposure', 'masthead' ) }</h2>
				<Exposure values={ exposure } setSetting={ setSetting } />
			</section>
			<section className="masthead-settings__section">
				<h2>{ __( 'AI', 'masthead' ) }</h2>
				<AiSettings
					supported={ Boolean( boot.ai?.supported ) }
					connectorsUrl={ boot.urls?.connectors }
				/>
			</section>
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
