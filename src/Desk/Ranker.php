<?php
/**
 * Candidate ranking.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Config;
use Masthead\Desk\Candidates\Candidate;

/**
 * Deterministic order: kind, then score, then id, then title. Same data, same order, whatever the input order.
 */
final class Ranker {

	/**
	 * Kind precedence.
	 */
	public const KIND_ORDER = array( 'draft', 'series', 'reshare', 'gap', 'idea' );

	/**
	 * Drops dismissed candidates, sorts, and keeps the first desk.max_suggestions.
	 *
	 * @param Candidate[] $candidates     Candidates.
	 * @param string[]    $dismissed_keys Keys the user dismissed for the date.
	 * @return Candidate[]
	 */
	public function rank( array $candidates, array $dismissed_keys ): array {
		$rows = array();
		foreach ( $candidates as $candidate ) {
			$row = $candidate->to_array();
			if ( in_array( $row['key'], $dismissed_keys, true ) ) {
				continue;
			}
			$rows[] = array( $candidate, $row );
		}
		usort( $rows, array( $this, 'compare' ) );
		$ranked = array_map( static fn( array $pair ) => $pair[0], $rows );
		return array_slice( $ranked, 0, max( 0, (int) Config::get( 'desk.max_suggestions' ) ) );
	}

	/**
	 * Comparison: kind order, score descending, sort_id ascending, tiebreak ascending, key ascending.
	 *
	 * @param array $a First [ Candidate, row ].
	 * @param array $b Second [ Candidate, row ].
	 * @return int
	 */
	private function compare( array $a, array $b ): int {
		$x = $a[1];
		$y = $b[1];
		$kind = $this->kind_rank( $x['kind'] ) <=> $this->kind_rank( $y['kind'] );
		if ( 0 !== $kind ) {
			return $kind;
		}
		$score = $y['score'] <=> $x['score'];
		if ( 0 !== $score ) {
			return $score;
		}
		$id = $x['sort_id'] <=> $y['sort_id'];
		if ( 0 !== $id ) {
			return $id;
		}
		$title = strcmp( $x['tiebreak'], $y['tiebreak'] );
		if ( 0 !== $title ) {
			return $title;
		}
		return strcmp( $x['key'], $y['key'] );
	}

	/**
	 * Position of a kind; unknown kinds sort last.
	 *
	 * @param string $kind Kind.
	 * @return int
	 */
	private function kind_rank( string $kind ): int {
		$position = array_search( $kind, self::KIND_ORDER, true );
		return false === $position ? count( self::KIND_ORDER ) : (int) $position;
	}
}
