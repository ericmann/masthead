import { __ } from '@wordpress/i18n';

const VIEWS = [
	{ id: 'month', label: __( 'Month', 'masthead' ) },
	{ id: 'week', label: __( 'Week', 'masthead' ) },
	{ id: 'list', label: __( 'List', 'masthead' ) },
];

/**
 * Calendar toolbar: previous, next, title, Today and the view switch.
 *
 * @param {Object}   props         Props.
 * @param {string}   props.view    month, week or list.
 * @param {string}   props.title   Range title.
 * @param {Function} props.onPrev  Previous.
 * @param {Function} props.onNext  Next.
 * @param {Function} props.onToday Today.
 * @param {Function} props.onView  Called with a view id.
 * @return {Element} The toolbar.
 */
export default function Toolbar( {
	view,
	title,
	onPrev,
	onNext,
	onToday,
	onView,
} ) {
	const weekly = view === 'week';
	return (
		<div
			className="masthead-toolbar"
			role="toolbar"
			aria-label={ __( 'Calendar', 'masthead' ) }
		>
			<div className="masthead-toolbar__nav">
				<button type="button" className="button" onClick={ onPrev }>
					{ weekly
						? __( 'Previous week', 'masthead' )
						: __( 'Previous month', 'masthead' ) }
				</button>
				<button type="button" className="button" onClick={ onNext }>
					{ weekly
						? __( 'Next week', 'masthead' )
						: __( 'Next month', 'masthead' ) }
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
		</div>
	);
}
