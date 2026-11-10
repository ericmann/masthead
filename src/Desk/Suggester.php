<?php
/**
 * The deterministic Desk.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Config;
use Masthead\Desk\Candidates\CategoryGap;
use Masthead\Desk\Candidates\DraftMatch;
use Masthead\Desk\Candidates\Reshare;
use Masthead\Desk\Candidates\SeriesNext;
use Masthead\Plan\Lanes;
use Masthead\Plan\PlanReader;
use Masthead\Support\Clock;
use Masthead\Support\Dates;

/**
 * Assembles the suggestion list for an empty day from the candidate sources.
 */
class Suggester {

	/**
	 * Actions per candidate kind.
	 */
	private const ACTIONS = array(
		'draft'   => array( 'schedule', 'open', 'dismiss' ),
		'reshare' => array( 'share', 'open', 'dismiss' ),
		'gap'     => array( 'create_draft', 'suggest_topics', 'dismiss' ),
	);

	/**
	 * Constructor.
	 *
	 * @param DraftMatch  $drafts     Draft source.
	 * @param SeriesNext  $series     Series source.
	 * @param Reshare     $reshares   Reshare source.
	 * @param CategoryGap $gaps       Gap source.
	 * @param Ranker      $ranker     Ranker.
	 * @param Reasons     $reasons    Reasons.
	 * @param Dismissals  $dismissals Dismissals.
	 * @param PlanReader  $reader     Plan reader.
	 * @param Clock       $clock      Clock.
	 */
	public function __construct(
		private DraftMatch $drafts,
		private SeriesNext $series,
		private Reshare $reshares,
		private CategoryGap $gaps,
		private Ranker $ranker,
		private Reasons $reasons,
		private Dismissals $dismissals,
		private PlanReader $reader,
		private Clock $clock
	) {}

	/**
	 * The last date the Desk pre-computes for batch work.
	 *
	 * @return string Y-m-d.
	 */
	public function horizon_end(): string {
		return Dates::add_days( $this->clock->today(), (int) Config::get( 'desk.horizon_days' ) );
	}

	/**
	 * Suggestions for a date. Empty for a past date and for a day that already has a post.
	 *
	 * @param string   $date    Date, Y-m-d.
	 * @param int      $user_id User asking.
	 * @param string[] $kinds   Restrict to these kinds; empty for all four deterministic kinds.
	 * @return array{date:string,lane:array,suggestions:array,ai:array}
	 */
	public function suggest( string $date, int $user_id, array $kinds = array() ): array {
		$lane = Lanes::for_date( $date );
		$out  = array(
			'date'        => $date,
			'lane'        => array(
				'slug'       => $lane['slug'],
				'label'      => $lane['label'],
				'categories' => $lane['categories'],
			),
			'suggestions' => array(),
			'ai'          => array(
				'used'      => false,
				'remaining' => 0,
			),
		);
		if ( $date < $this->clock->today() || $this->has_post( $date ) ) {
			return $out;
		}

		$sources    = array(
			'draft'   => $this->drafts,
			'series'  => $this->series,
			'reshare' => $this->reshares,
			'gap'     => $this->gaps,
		);
		$candidates = array();
		foreach ( $sources as $kind => $source ) {
			if ( array() === $kinds || in_array( $kind, $kinds, true ) ) {
				$candidates = array_merge( $candidates, $source->for_date( $date, $lane ) );
			}
		}
		$ranked = $this->ranker->rank( $candidates, $this->dismissals->for_date( $user_id, $date ) );

		$list = array();
		foreach ( $ranked as $candidate ) {
			$list[] = $this->suggestion( $candidate->to_array(), $date, $lane );
		}
		/**
		 * Filters the suggestions for a date.
		 *
		 * @param array  $list Suggestions.
		 * @param string $date Date.
		 */
		$filtered = apply_filters( 'masthead_suggestions', $list, $date );
		$out['suggestions'] = is_array( $filtered ) ? array_values( $filtered ) : $list;
		return $out;
	}

	/**
	 * Whether the day already holds a post.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	private function has_post( string $date ): bool {
		$plan = $this->reader->range( $date, $date );
		return array() !== ( $plan['days'][0]['posts'] ?? array() );
	}

	/**
	 * A candidate as a Suggestion.
	 *
	 * @param array  $candidate Candidate::to_array().
	 * @param string $date      Date.
	 * @param array  $lane      Lane.
	 * @return array<string,mixed>
	 */
	private function suggestion( array $candidate, string $date, array $lane ): array {
		$suggestion = array(
			'key'     => $candidate['key'],
			'kind'    => $candidate['kind'],
			'title'   => $candidate['title'],
			'why'     => $this->reasons->for_candidate( $candidate, $date, $lane ),
			'actions' => $this->actions( $candidate ),
		);
		foreach ( array( 'category', 'post_id', 'series', 'outline' ) as $optional ) {
			if ( isset( $candidate[ $optional ] ) ) {
				$suggestion[ $optional ] = $candidate[ $optional ];
			}
		}
		return $suggestion;
	}

	/**
	 * Actions for a candidate.
	 *
	 * @param array $candidate Candidate::to_array().
	 * @return string[]
	 */
	private function actions( array $candidate ): array {
		if ( 'series' === $candidate['kind'] ) {
			return isset( $candidate['post_id'] ) ? array( 'schedule', 'open', 'dismiss' ) : array( 'create_draft', 'dismiss' );
		}
		return self::ACTIONS[ $candidate['kind'] ] ?? array( 'dismiss' );
	}
}
