import { getBoot } from './boot';

/**
 * Colour for a lane with no category to take one from. 5.5:1 on white.
 */
export const NEUTRAL_COLOR = '#646970';

/**
 * The colour of a category from boot data.
 *
 * @param {string|null|undefined} slug Category slug.
 * @return {string|null} Hex colour, or null for an unknown slug.
 */
export function categoryColor( slug ) {
	if ( ! slug ) {
		return null;
	}
	return (
		( getBoot().categories || [] ).find(
			( category ) => category.slug === slug
		)?.color || null
	);
}

/**
 * The colour a day's suggestion is drawn in: the lane's first category, else the suggestion's own category
 * (a lane such as Standalone accepts any category), else neutral grey.
 *
 * @param {Object|null} lane       Lane { categories }.
 * @param {Object|null} suggestion Suggestion { category }.
 * @return {string} Hex colour.
 */
export function laneColor( lane, suggestion = null ) {
	return (
		categoryColor( lane?.categories?.[ 0 ] ) ||
		categoryColor( suggestion?.category ) ||
		NEUTRAL_COLOR
	);
}
