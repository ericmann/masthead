<?php
/**
 * A Desk candidate.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk\Candidates;

/**
 * The record every Desk source produces. Immutable.
 */
final class Candidate {

	/**
	 * Constructor. Use make().
	 *
	 * @param array<string,mixed> $values All values.
	 */
	private function __construct( private array $values ) {}

	/**
	 * Builds a candidate.
	 *
	 * @param string              $kind   draft, series, reshare, gap or idea.
	 * @param string              $key    Stable key.
	 * @param string              $title  Title.
	 * @param float               $score  Score from 0 to 1.
	 * @param array<string,mixed> $fields Optional: sort_id, tiebreak, category, post_id, series, outline, data.
	 * @return self
	 */
	public static function make( string $kind, string $key, string $title, float $score, array $fields = array() ): self {
		$values = array(
			'kind'     => $kind,
			'key'      => $key,
			'title'    => $title,
			'score'    => $score,
			'sort_id'  => (int) ( $fields['sort_id'] ?? 0 ),
			'tiebreak' => (string) ( $fields['tiebreak'] ?? $title ),
		);
		foreach ( array( 'category', 'post_id', 'series', 'outline', 'data' ) as $optional ) {
			if ( isset( $fields[ $optional ] ) ) {
				$values[ $optional ] = $fields[ $optional ];
			}
		}
		return new self( $values );
	}

	/**
	 * The stable key of an idea: idea: plus the sha1 of its title.
	 *
	 * @param string $title Idea title.
	 * @return string
	 */
	public static function idea_key( string $title ): string {
		return 'idea:' . sha1( $title );
	}

	/**
	 * The candidate as an array. Absent optional fields are omitted.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return $this->values;
	}
}
