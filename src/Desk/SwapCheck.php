<?php
/**
 * The swap check.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Config;
use Masthead\Plan\Cadence;
use Masthead\Plan\Lanes;
use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Stats\StatsSource;
use Masthead\Support\Clock;
use Masthead\Support\Dates;

/**
 * Looks at scheduled posts and dated drafts and reports what does not fit the rhythm.
 */
class SwapCheck {

	/**
	 * Finding types in tie-break order.
	 */
	public const TYPES = array( 'lane_mismatch', 'collision', 'cadence_break', 'better_candidate' );

	/**
	 * Constructor.
	 *
	 * @param PlanReader   $reader  Plan reader.
	 * @param SeriesSource $series  Series adapter.
	 * @param Scoring      $scoring Scoring.
	 * @param Reasons      $reasons Reasons.
	 * @param StatsSource  $stats   Stats source.
	 * @param Clock        $clock   Clock.
	 */
	public function __construct(
		private PlanReader $reader,
		private SeriesSource $series,
		private Scoring $scoring,
		private Reasons $reasons,
		private StatsSource $stats,
		private Clock $clock
	) {}

	/**
	 * Findings for a range, ordered by date, post ID and type.
	 *
	 * @param string|null $from First date; defaults to tomorrow.
	 * @param string|null $to   Last date; defaults to today plus swap.lookahead_days.
	 * @return array<int,array<string,mixed>>
	 */
	public function run( ?string $from = null, ?string $to = null ): array {
		$today = $this->clock->today();
		$from  = $from ?? Dates::add_days( $today, 1 );
		$to    = $to ?? Dates::add_days( $today, (int) Config::get( 'swap.lookahead_days' ) );

		$posts = $this->examined( $from, $to );
		$findings = array_merge(
			$this->lane_mismatches( $posts ),
			$this->collisions( $posts ),
			$this->cadence_breaks( $posts ),
			$this->better_candidates( $posts )
		);
		usort(
			$findings,
			static fn( $a, $b ) => array( $a['date'], $a['post_id'], array_search( $a['type'], self::TYPES, true ) )
				<=> array( $b['date'], $b['post_id'], array_search( $b['type'], self::TYPES, true ) )
		);
		return $findings;
	}

	/**
	 * The posts to examine: scheduled posts and dated drafts in the range, plus every later scheduled part of any series with a part in it.
	 *
	 * @param string $from First date.
	 * @param string $to   Last date.
	 * @return array<int,array<string,mixed>> PlanPost keyed by post ID.
	 */
	private function examined( string $from, string $to ): array {
		$posts = array();
		foreach ( $this->reader->scheduled_between( $from, $to ) as $post ) {
			$posts[ $post['id'] ] = $post;
		}
		$terms = array();
		foreach ( $posts as $post ) {
			if ( isset( $post['series']['term_id'] ) ) {
				$terms[ $post['series']['term_id'] ] = true;
			}
		}
		foreach ( array_keys( $terms ) as $term_id ) {
			foreach ( $this->series->parts( (int) $term_id ) as $part ) {
				if ( isset( $posts[ $part['post_id'] ] ) || ! $part['dated'] || 'publish' === $part['status'] ) {
					continue;
				}
				$extra = $this->reader->post( $part['post_id'] );
				if ( null !== $extra && null !== $extra['date'] && $this->date_of( $extra ) > $to ) {
					$posts[ $extra['id'] ] = $extra;
				}
			}
		}
		return $posts;
	}

	/**
	 * Local date of a PlanPost.
	 *
	 * @param array $post PlanPost.
	 * @return string Y-m-d.
	 */
	private function date_of( array $post ): string {
		return explode( 'T', (string) $post['date'] )[0];
	}

	/**
	 * Lane mismatch findings with their options.
	 *
	 * @param array<int,array<string,mixed>> $posts Examined posts.
	 * @return array<int,array<string,mixed>>
	 */
	private function lane_mismatches( array $posts ): array {
		$window = (int) Config::get( 'desk.series_window_days' );

		$mismatches = array();
		foreach ( $posts as $post ) {
			$date      = $this->date_of( $post );
			$lane      = Lanes::for_date( $date );
			$exception = isset( $post['series']['term_id'] ) && $this->series->lane_exception( (int) $post['series']['term_id'] );
			$terms     = $this->scoring->terms( $post, $date, $lane, $exception, null, null );
			if ( $terms['lane'] >= 1.0 ) {
				continue;
			}
			$primary = $post['primary_category'] ?? null;
			$target  = null === $primary ? null : Lanes::nearest_date_for_category( $date, $primary, $window );
			$mismatches[ $post['id'] ] = array(
				'post'   => $post,
				'date'   => $date,
				'lane'   => $lane,
				'target' => $target,
			);
		}

		$findings = array();
		foreach ( $mismatches as $id => $mismatch ) {
			$post    = $mismatch['post'];
			$options = array();
			$series  = $post['series'] ?? null;

			if ( null !== $mismatch['target'] ) {
				if ( null === $series ) {
					$options[] = array(
						'label'   => sprintf(
							/* translators: 1: weekday name, 2: date. */
							__( 'Move to %1$s: %2$s', 'masthead' ),
							$this->weekday_name( $mismatch['target'] ),
							$mismatch['target']
						),
						'ability' => 'masthead/reschedule',
						'input'   => array(
							'post_id' => $id,
							'date'    => $mismatch['target'],
						),
					);
				} else {
					$options[] = $this->series_move_option( $id, (int) $series['term_id'], $mismatches );
				}
			}
			if ( null !== $series ) {
				$options[] = array(
					'label'   => sprintf(
						/* translators: %s: plural weekday name, such as "Mondays". */
						__( 'Keep %s and mark this series as a lane exception', 'masthead' ),
						$this->plural_weekday( Dates::weekday( $mismatch['date'] ) )
					),
					'ability' => null,
					'input'   => array(
						'rest' => array(
							'path'   => '/wp/v2/' . $this->series->rest_base() . '/' . (int) $series['term_id'],
							'method' => 'POST',
							'data'   => array( 'meta' => array( 'masthead_lane_exception' => true ) ),
						),
					),
				);
			}

			$findings[] = array(
				'key'      => 'lane_mismatch:' . $id,
				'post_id'  => $id,
				'date'     => $mismatch['date'],
				'type'     => 'lane_mismatch',
				'severity' => 'review',
				'why'      => $this->reasons->for_lane_mismatch( (string) $post['title'], $mismatch['date'], $mismatch['lane'] ),
				'options'  => $options,
			);
		}
		return $findings;
	}

	/**
	 * The option that moves every remaining mismatched part of a series to its fitting date.
	 *
	 * @param int                            $own        This finding's post ID.
	 * @param int                            $term_id    Series term ID.
	 * @param array<int,array<string,mixed>> $mismatches All mismatches by post ID.
	 * @return array<string,mixed>
	 */
	private function series_move_option( int $own, int $term_id, array $mismatches ): array {
		$parts = array();
		foreach ( $mismatches as $id => $mismatch ) {
			if ( null !== $mismatch['target'] && (int) ( $mismatch['post']['series']['term_id'] ?? 0 ) === $term_id ) {
				$parts[ $id ] = array(
					'part'   => (int) ( $mismatch['post']['series']['part'] ?? 0 ),
					'target' => $mismatch['target'],
				);
			}
		}
		uasort( $parts, static fn( $a, $b ) => $a['part'] <=> $b['part'] );

		$also  = array();
		foreach ( $parts as $id => $part ) {
			if ( $id !== $own ) {
				$also[] = array(
					'ability' => 'masthead/reschedule',
					'input'   => array(
						'post_id' => $id,
						'date'    => $part['target'],
					),
				);
			}
		}
		$first = reset( $parts );
		return array(
			'label'   => sprintf(
				/* translators: 1: plural weekday name such as "Saturdays", 2: dates joined by commas. */
				__( 'Move to %1$s: %2$s', 'masthead' ),
				$this->plural_weekday( Dates::weekday( (string) $first['target'] ) ),
				implode( ', ', array_column( $parts, 'target' ) )
			),
			'ability' => 'masthead/reschedule',
			'input'   => array(
				'post_id' => $own,
				'date'    => $parts[ $own ]['target'],
			),
			'also'    => $also,
		);
	}

	/**
	 * Cadence break findings.
	 *
	 * @param array<int,array<string,mixed>> $posts Examined posts.
	 * @return array<int,array<string,mixed>>
	 */
	private function cadence_breaks( array $posts ): array {
		$window   = (int) Config::get( 'desk.series_window_days' );
		$findings = array();
		foreach ( $posts as $id => $post ) {
			$series = $post['series'] ?? null;
			if ( null === $series || empty( $series['part'] ) ) {
				continue;
			}
			$cadence = $this->series->cadence( (int) $series['term_id'] );
			if ( null === $cadence ) {
				continue;
			}
			$previous = null;
			foreach ( $this->series->parts( (int) $series['term_id'] ) as $part ) {
				if ( $part['part'] === (int) $series['part'] - 1 && $part['dated'] ) {
					$previous = Dates::parse_local( (string) $part['date'] );
					break;
				}
			}
			if ( null === $previous ) {
				continue;
			}
			$date = $this->date_of( $post );
			$last = Dates::to_ymd( $previous );
			if ( ! Cadence::is_break( $last, $date, $cadence, $window ) ) {
				continue;
			}
			$expected   = Cadence::expected_date( $last, $cadence );
			$findings[] = array(
				'key'      => 'cadence_break:' . $id,
				'post_id'  => $id,
				'date'     => $date,
				'type'     => 'cadence_break',
				'severity' => 'review',
				'why'      => $this->reasons->for_cadence_break( (int) $series['part'], $date, (int) $cadence['every_weeks'], $expected ),
				'options'  => array(
					array(
						'label'   => sprintf(
							/* translators: %s: date. */
							__( 'Move to %s', 'masthead' ),
							$expected
						),
						'ability' => 'masthead/reschedule',
						'input'   => array(
							'post_id' => $id,
							'date'    => $expected,
						),
					),
				),
			);
		}
		return $findings;
	}

	/**
	 * Collision findings: two or more examined posts on one date, unless one of them is in an exempt category.
	 *
	 * @param array<int,array<string,mixed>> $posts Examined posts.
	 * @return array<int,array<string,mixed>>
	 */
	private function collisions( array $posts ): array {
		$exempt  = (array) Config::get( 'swap.collision_exempt_categories' );
		$by_date = array();
		foreach ( $posts as $post ) {
			$by_date[ $this->date_of( $post ) ][] = $post;
		}
		$findings = array();
		foreach ( $by_date as $date => $group ) {
			if ( count( $group ) <= 1 ) {
				continue;
			}
			$primaries = array_map( static fn( $post ) => $post['primary_category'] ?? null, $group );
			if ( array() !== array_intersect( $primaries, $exempt ) ) {
				continue;
			}
			foreach ( $group as $post ) {
				$others = array_map(
					static fn( $other ) => (string) $other['title'],
					array_filter( $group, static fn( $other ) => $other['id'] !== $post['id'] )
				);
				$findings[] = array(
					'key'      => 'collision:' . $post['id'],
					'post_id'  => $post['id'],
					'date'     => (string) $date,
					'type'     => 'collision',
					'severity' => 'review',
					'why'      => $this->reasons->for_collision( (string) $post['title'], (string) $date, array_values( $others ) ),
					'options'  => array(),
				);
			}
		}
		return $findings;
	}

	/**
	 * Better candidate findings: a draft or a nearby scheduled post that fits the date clearly better.
	 *
	 * @param array<int,array<string,mixed>> $posts Examined posts.
	 * @return array<int,array<string,mixed>>
	 */
	private function better_candidates( array $posts ): array {
		$delta  = (float) Config::get( 'swap.min_score_delta' );
		$window = (int) Config::get( 'swap.candidate_window_days' );
		$days   = (int) Config::get( 'stats.window_days' );

		$stats_on = $this->stats->available();
		$by_cat   = $stats_on ? $this->stats->views_by_category( $days ) : null;
		$by_day   = $stats_on ? $this->stats->views_by_weekday( $days ) : null;
		$fetched  = $stats_on ? $this->stats->fetched_at() : null;

		$drafts   = $this->reader->unscheduled();
		$findings = array();
		foreach ( $posts as $post ) {
			$date      = $this->date_of( $post );
			$lane      = Lanes::for_date( $date );
			$own       = $this->score_on( $post, $date, $lane, $by_cat, $by_day );
			$best      = null;
			$best_score = 0.0;

			$candidates = array();
			foreach ( $drafts as $draft ) {
				$candidates[] = $draft;
			}
			foreach ( $posts as $other ) {
				$other_date = $this->date_of( $other );
				if ( $other['id'] !== $post['id'] && $other_date !== $date && abs( Dates::diff_days( $date, $other_date ) ) <= $window ) {
					$candidates[] = $other;
				}
			}
			foreach ( $candidates as $candidate ) {
				$score = $this->score_on( $candidate, $date, $lane, $by_cat, $by_day );
				if ( null === $best || $score > $best_score || ( $score === $best_score && $candidate['id'] < $best['id'] ) ) {
					$best       = $candidate;
					$best_score = $score;
				}
			}
			if ( null === $best || $best_score - $own < $delta - 1e-9 ) {
				continue;
			}

			$scheduled = null !== ( $best['date'] ?? null );
			if ( $scheduled ) {
				$option = array(
					'label'   => sprintf(
						/* translators: %s: post title. */
						__( 'Swap dates with %s', 'masthead' ),
						(string) $best['title']
					),
					'ability' => 'masthead/reschedule',
					'input'   => array(
						'post_id' => $post['id'],
						'date'    => $this->date_of( $best ),
					),
					'also'    => array(
						array(
							'ability' => 'masthead/reschedule',
							'input'   => array(
								'post_id' => $best['id'],
								'date'    => $date,
							),
						),
					),
				);
			} else {
				$option = array(
					'label'   => sprintf(
						/* translators: 1: draft title, 2: date. */
						__( 'Put %1$s on %2$s', 'masthead' ),
						(string) $best['title'],
						$date
					),
					'ability' => 'masthead/reschedule',
					'input'   => array(
						'post_id' => $best['id'],
						'date'    => $date,
					),
				);
			}

			$finding = array(
				'key'      => 'better_candidate:' . $post['id'],
				'post_id'  => $post['id'],
				'date'     => $date,
				'type'     => 'better_candidate',
				'severity' => 'info',
				'why'      => $this->reasons->for_better_candidate( (string) $best['title'], $this->weekday_name( $date ), (string) $lane['label'], (string) $post['title'] ),
				'options'  => array( $option ),
			);
			if ( null !== $by_cat && null !== $by_day && null !== $fetched ) {
				$finding['numbers'] = array(
					'source'     => 'Jetpack Stats',
					'fetched_at' => $fetched,
					'values'     => array(
						'scheduled_category_views' => $by_cat[ $post['primary_category'] ?? '' ] ?? 0,
						'candidate_category_views' => $by_cat[ $best['primary_category'] ?? '' ] ?? 0,
						'weekday_avg_views'        => $by_day[ Dates::weekday( $date ) ] ?? 0,
					),
				);
			}
			$findings[] = $finding;
		}
		return $findings;
	}

	/**
	 * A post's score on a date.
	 *
	 * @param array      $post   PlanPost.
	 * @param string     $date   Date.
	 * @param array      $lane   Lane for the date.
	 * @param array|null $by_cat Views by category.
	 * @param array|null $by_day Average views by weekday.
	 * @return float
	 */
	private function score_on( array $post, string $date, array $lane, ?array $by_cat, ?array $by_day ): float {
		$exception = isset( $post['series']['term_id'] ) && $this->series->lane_exception( (int) $post['series']['term_id'] );
		return $this->scoring->score( $this->scoring->terms( $post, $date, $lane, $exception, $by_cat, $by_day ) );
	}

	/**
	 * Weekday name of a date.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	private function weekday_name( string $date ): string {
		$parsed = Dates::parse_date( $date );
		return null === $parsed ? '' : $this->clock->format( $parsed, 'l' );
	}

	/**
	 * Plural weekday name, from seven translatable strings.
	 *
	 * @param int $weekday Weekday, 0 = Sunday.
	 * @return string
	 */
	private function plural_weekday( int $weekday ): string {
		$names = array(
			__( 'Sundays', 'masthead' ),
			__( 'Mondays', 'masthead' ),
			__( 'Tuesdays', 'masthead' ),
			__( 'Wednesdays', 'masthead' ),
			__( 'Thursdays', 'masthead' ),
			__( 'Fridays', 'masthead' ),
			__( 'Saturdays', 'masthead' ),
		);
		return $names[ $weekday ] ?? '';
	}
}
