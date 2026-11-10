<?php
/**
 * Candidate tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk\Candidates;

use Masthead\Desk\Candidates\Candidate;
use PHPUnit\Framework\TestCase;

/**
 * Candidate tests.
 */
final class CandidateTest extends TestCase {

	public function test_to_array_omits_absent_optional_fields(): void {
		$array = Candidate::make( 'draft', 'draft:5', 'A title', 1.0 )->to_array();
		$this->assertSame(
			array(
				'kind'     => 'draft',
				'key'      => 'draft:5',
				'title'    => 'A title',
				'score'    => 1.0,
				'sort_id'  => 0,
				'tiebreak' => 'A title',
			),
			$array
		);
		$full = Candidate::make(
			'series',
			'series:3:2',
			'Part 2',
			0.5,
			array(
				'sort_id'  => 12156,
				'tiebreak' => 'zzz',
				'category' => 'writing',
				'post_id'  => 12156,
				'series'   => array(
					'term_id' => 3,
					'part'    => 2,
				),
				'outline'  => array( 'One' ),
				'data'     => array( 'k' => 'v' ),
			)
		)->to_array();
		$this->assertSame( 12156, $full['sort_id'] );
		$this->assertSame( 'zzz', $full['tiebreak'] );
		$this->assertSame( 'writing', $full['category'] );
		$this->assertSame( array( 'One' ), $full['outline'] );
		$this->assertSame( array( 'k' => 'v' ), $full['data'] );
	}

	public function test_idea_key_is_sha1_of_title(): void {
		$this->assertSame( 'idea:' . sha1( 'Why vectors beat keywords' ), Candidate::idea_key( 'Why vectors beat keywords' ) );
		$this->assertNotSame( Candidate::idea_key( 'a' ), Candidate::idea_key( 'b' ) );
	}
}
