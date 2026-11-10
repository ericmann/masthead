import { __ } from '@wordpress/i18n';

import CategoryLegend from '../components/CategoryLegend';
import Sparkle from '../components/Sparkle';

const VIEWS = [
	{ id: 'month', label: __( 'Month', 'masthead' ) },
	{ id: 'week', label: __( 'Week', 'masthead' ) },
	{ id: 'list', label: __( 'List', 'masthead' ) },
];

/**
 * Calendar toolbar: previous and next around the title, Today, the view switch, the legend and Fill open days.
 *
 * @param {Object}      props                  Props.
 * @param {string}      props.view             month, week or list.
 * @param {string}      props.title            Range title.
 * @param {Function}    props.onPrev           Previous.
 * @param {Function}    props.onNext           Next.
 * @param {Function}    props.onToday          Today.
 * @param {Function}    props.onView           Called with a view id.
 * @param {Object[]}    props.categories       Legend categories { slug, name, color }.
 * @param {Set<string>} props.hidden           Hidden category slugs.
 * @param {Function}    props.onToggleCategory Called with a slug.
 * @param {Function}    props.onFillOpenDays   Runs suggestions for every open day in view.
 * @param {string}      props.fillStatus       Progress text while Fill open days runs.
 * @param {boolean}     props.filling          Whether Fill open days is running.
 * @param {string}      props.fillBlocked      Why there is nothing to fill, or an empty string.
 * @return {Element} The toolbar.
 */
export default function Toolbar( {
	view,
	title,
	onPrev,
	onNext,
	onToday,
	onView,
	categories = [],
	hidden = new Set(),
	onToggleCategory = () => {},
	onFillOpenDays = null,
	fillStatus = '',
	filling = false,
	fillBlocked = '',
} ) {
	const labels = {
		month: [
			__( 'Previous month', 'masthead' ),
			__( 'Next month', 'masthead' ),
		],
		week: [
			__( 'Previous week', 'masthead' ),
			__( 'Next week', 'masthead' ),
		],
		list: [
			__( 'Previous page', 'masthead' ),
			__( 'Next page', 'masthead' ),
		],
	}[ view ];
	const fillNote = filling ? fillStatus : fillBlocked;
	return (
		<div
			className="masthead-toolbar"
			role="toolbar"
			aria-label={ __( 'Calendar', 'masthead' ) }
		>
			<div className="masthead-toolbar__nav">
				<button
					type="button"
					className="button button-compact masthead-toolbar__step"
					aria-label={ labels[ 0 ] }
					title={ labels[ 0 ] }
					onClick={ onPrev }
				>
					<span
						className="dashicons dashicons-arrow-left-alt2"
						aria-hidden="true"
					/>
				</button>
				<h2 className="masthead-toolbar__title">{ title }</h2>
				<button
					type="button"
					className="button button-compact masthead-toolbar__step"
					aria-label={ labels[ 1 ] }
					title={ labels[ 1 ] }
					onClick={ onNext }
				>
					<span
						className="dashicons dashicons-arrow-right-alt2"
						aria-hidden="true"
					/>
				</button>
			</div>
			<button
				type="button"
				className="button button-compact masthead-toolbar__today"
				onClick={ onToday }
			>
				{ __( 'Today', 'masthead' ) }
			</button>
			<div
				className="button-group button-compact masthead-toolbar__views"
				role="group"
				aria-label={ __( 'View', 'masthead' ) }
			>
				{ VIEWS.map( ( item ) => (
					<button
						key={ item.id }
						type="button"
						className={
							view === item.id
								? 'button button-primary'
								: 'button'
						}
						aria-pressed={ view === item.id }
						onClick={ () => onView( item.id ) }
					>
						{ item.label }
					</button>
				) ) }
			</div>
			{ categories.length > 0 ? (
				<CategoryLegend
					categories={ categories }
					hidden={ hidden }
					onToggle={ onToggleCategory }
				/>
			) : null }
			{ onFillOpenDays ? (
				<div className="masthead-toolbar__fill">
					<span
						id="masthead-fill-note"
						className="masthead-toolbar__fill-note"
						role="status"
					>
						{ fillNote }
					</span>
					<button
						type="button"
						className="button button-primary button-compact masthead-toolbar__fill-button"
						disabled={ filling || fillBlocked !== '' }
						aria-describedby={
							fillNote ? 'masthead-fill-note' : undefined
						}
						title={ fillNote || undefined }
						onClick={ onFillOpenDays }
					>
						<Sparkle />
						{ __( 'Fill open days', 'masthead' ) }
					</button>
				</div>
			) : null }
		</div>
	);
}
