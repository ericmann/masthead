import { __ } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import RhythmTable from './RhythmTable';
import { useSettings } from './useSettings';

export default function App() {
	const boot = getBoot();
	const { rhythm, setRhythm, save, isSaving, isSaved, error } = useSettings();
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
			<p className="submit">
				<button
					type="button"
					className="button button-primary"
					disabled={ isSaving }
					onClick={ save }
				>
					{ __( 'Save changes', 'masthead' ) }
				</button>
			</p>
		</div>
	);
}
