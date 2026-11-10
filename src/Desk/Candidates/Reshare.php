<?php
/**
 * Candidate source: posts worth sharing again.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk\Candidates;

use Masthead\Config;
use Masthead\Social\ShareSource;
use Masthead\Stats\StatsSource;
use Masthead\Support\Categories;
use Masthead\Support\Clock;
use Masthead\Support\Dates;

/**
 * Published posts in the lane, old enough, not shared lately, ranked by views. Nothing without stats or sharing.
 */
class Reshare {

	/**
	 * Constructor.
	 *
	 * @param StatsSource $stats Stats source.
	 * @param ShareSource $share Share source.
	 * @param Clock       $clock Clock.
	 */
	public function __construct( private StatsSource $stats, private ShareSource $share, private Clock $clock ) {}

	/**
	 * Candidates for a date.
	 *
	 * @param string $date Date, Y-m-d.
	 * @param array  $lane Lane for the date.
	 * @return Candidate[]
	 */
	public function for_date( string $date, array $lane ): array {
		if ( ! $this->stats->available() || ! $this->share->available() ) {
			return array();
		}
		$top = $this->stats->top_posts( (int) Config::get( 'stats.window_days' ), (int) Config::get( 'desk.candidate_pool' ) );
		if ( null === $top ) {
			return array();
		}

		$accepted = $lane['categories'] ?? array();
		$today    = $this->clock->today();
		$min_age  = (int) Config::get( 'desk.reshare_min_age_days' );
		$cooldown = (int) Config::get( 'desk.reshare_cooldown_days' );

		$kept = array();
		foreach ( $top as $post_id => $views ) {
			$post = get_post( (int) $post_id );
			if ( ! is_object( $post ) || 'publish' !== $post->post_status ) {
				continue;
			}
			$categories = Categories::for_post( (int) $post_id );
			if ( array() !== $accepted && array() === array_intersect( $categories['slugs'], $accepted ) ) {
				continue;
			}
			$published = Dates::parse_local( (string) $post->post_date );
			if ( null === $published || Dates::diff_days( Dates::to_ymd( $published ), $today ) <= $min_age ) {
				continue;
			}
			$shared = get_post_meta( (int) $post_id, 'masthead_last_shared', true );
			if ( is_string( $shared ) && '' !== $shared ) {
				$when = Dates::parse_iso( $shared );
				if ( null !== $when && Dates::diff_days( Dates::to_ymd( $when ), $today ) <= $cooldown ) {
					continue;
				}
			}
			$kept[] = array(
				'post'       => $post,
				'post_id'    => (int) $post_id,
				'views'      => (int) $views,
				'primary'    => $categories['primary'],
				'published'  => Dates::to_ymd( $published ),
			);
		}
		if ( array() === $kept ) {
			return array();
		}

		$most       = max( array_column( $kept, 'views' ) );
		$candidates = array();
		foreach ( $kept as $row ) {
			$candidates[] = Candidate::make(
				'reshare',
				'reshare:' . $row['post_id'],
				(string) $row['post']->post_title,
				$most > 0 ? $row['views'] / $most : 0.0,
				array(
					'sort_id'  => $row['post_id'],
					'tiebreak' => (string) $row['post']->post_title,
					'category' => $row['primary'],
					'post_id'  => $row['post_id'],
					'data'     => array( 'published' => $row['published'] ),
				)
			);
		}
		return $candidates;
	}
}
