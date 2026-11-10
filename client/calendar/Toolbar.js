import { __ } from '@wordpress/i18n';

import CategoryLegend from '../components/CategoryLegend';

const VIEWS = [
	{ id: 'month', label: __( 'Month', 'masthead' ) },
	{ id: 'week', label: __( 'Week', 'masthead' ) },
	{ id: 'list', label: __( 'List', 'masthead' ) },
];

/**
 * Calendar toolbar: previous, next, title, Today and the view switch.
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
 * @param {string}      props.fillStatus       Progress text for the live region.
 * @param {boolean}     props.filling          Whether Fill open days is running.
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
	return (
		<div
			className="masthead-toolbar"
			role="toolbar"
			aria-label={ __( 'Calendar', 'masthead' ) }
		>
			<div className="masthead-toolbar__nav">
				<button type="button" className="button" onClick={ onPrev }>
					{ labels[ 0 ] }
				</button>
				<button type="button" className="button" onClick={ onNext }>
					{ labels[ 1 ] }
				</button>
				<button type="button" className="button" onClick={ onToday }>
					{ __( 'Today', 'masthead' ) }
				</button>
			</div>
			<h2 className="masthead-toolbar__title">{ title }</h2>
			<div
				className="masthead-toolbar__views"
				role="group"
				aria-label={ __( 'View', 'masthead' ) }
			>
				{ VIEWS.map( ( item ) => (
					<button
						key={ item.id }
						type="button"
						className="button"
						aria-pressed={ view === item.id }
						onClick={ () => onView( item.id ) }
					>
						{ item.label }
					</button>
				) ) }
			</div>
			{ onFillOpenDays ? (
				<div className="masthead-toolbar__fill">
					<button
						type="button"
						className="button"
						disabled={ filling }
						onClick={ onFillOpenDays }
					>
						{ __( 'Fill open days', 'masthead' ) }
					</button>
					<span className="screen-reader-text" aria-live="polite">
						{ fillStatus }
					</span>
				</div>
			) : null }
			{ categories.length > 0 ? (
				<CategoryLegend
					categories={ categories }
					hidden={ hidden }
					onToggle={ onToggleCategory }
				/>
			) : null }
		</div>
	);
}
