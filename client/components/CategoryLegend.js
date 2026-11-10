import { __ } from '@wordpress/i18n';

import LaneDot from './LaneDot';

/**
 * Legend of categories whose buttons toggle visibility.
 *
 * @param {Object}      props            Props.
 * @param {Object[]}    props.categories { slug, name, color }.
 * @param {Set<string>} props.hidden     Slugs currently hidden.
 * @param {Function}    props.onToggle   Called with a slug.
 * @return {Element} The legend.
 */
export default function CategoryLegend( { categories, hidden, onToggle } ) {
	return (
		<ul
			className="masthead-legend"
			aria-label={ __( 'Categories', 'masthead' ) }
		>
			{ categories.map( ( category ) => (
				<li key={ category.slug }>
					<button
						type="button"
						className="masthead-legend__button"
						aria-pressed={ ! hidden.has( category.slug ) }
						onClick={ () => onToggle( category.slug ) }
					>
						<LaneDot
							color={ category.color }
							label={ category.name }
						/>
					</button>
				</li>
			) ) }
		</ul>
	);
}
