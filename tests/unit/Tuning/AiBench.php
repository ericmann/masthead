<?php
/**
 * Tuning harness for the AI assumptions (P5-13).
 *
 * Run: vendor/bin/phpunit -c phpunit.xml.dist --group tuning --filter AiBench
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Tuning;

use Brain\Monkey\Functions;
use Masthead\Ai\Prompts;
use Masthead\Ai\Schemas;
use Masthead\Config;
use Masthead\Tests\Unit\TestCase;

/**
 * Measures what can be measured about the AI defaults without a live provider.
 *
 * @group tuning
 */
final class AiBench extends TestCase {

	private const CHARS_PER_TOKEN   = 4;
	private const TITLE_CHARS       = 80;
	private const EXCERPT_CHARS     = 300;
	private const ANGLE_CHARS       = 240;
	private const OUTLINE_LINES     = 6;
	private const OUTLINE_LINE_CHARS = 120;
	private const TYPICAL_PARTS     = 4;
	private const TYPICAL_LINES     = 4;
	private const TYPICAL_LINE_CHARS = 60;
	private const DAYS_PER_WEEK     = 7;
	private const PROMPT_BUDGET     = 8000;
	private const MIN_DAYS_COVERED  = 31;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array() );
	}

	private function tokens( string $text ): int {
		return (int) ceil( strlen( $text ) / self::CHARS_PER_TOKEN );
	}

	private function text( int $length, string $seed ): string {
		return substr( str_repeat( $seed . ' ', (int) ceil( $length / ( strlen( $seed ) + 1 ) ) ), 0, $length );
	}

	/**
	 * Largest schema-valid series reply for a part count.
	 *
	 * @param int $parts Part count.
	 * @param int $lines Outline lines per part.
	 * @param int $chars Characters per line.
	 * @return array
	 */
	private function series_reply( int $parts, int $lines, int $chars ): array {
		$list = array();
		for ( $i = 0; $i < $parts; $i++ ) {
			$list[] = array(
				'title'   => $this->text( self::TITLE_CHARS, 'title' ),
				'outline' => array_fill( 0, $lines, $this->text( $chars, 'line' ) ),
			);
		}
		return array(
			'name'        => $this->text( self::TITLE_CHARS, 'name' ),
			'category'    => 'technology',
			'every_weeks' => 1,
			'parts'       => $list,
		);
	}

	public function test_measure_ai_assumptions(): void {
		// Table 1: ai.context_max_posts.
		$context_rows = array();
		foreach ( array( 30, 60, 120 ) as $posts ) {
			$context = array(
				'posts'  => array_fill(
					0,
					$posts,
					array(
						'title'   => $this->text( self::TITLE_CHARS, 'title' ),
						'excerpt' => $this->text( self::EXCERPT_CHARS, 'excerpt' ),
					)
				),
				'series' => array(),
				'gaps'   => array(),
			);
			$prompt = Prompts::ideas(
				array(
					'label' => 'Standalone',
					'categories' => array( 'technology' ),
				),
				'2026-11-18',
				$context,
				(int) Config::get( 'desk.ideas_per_request' ) 
			);
			$context_rows[] = array( $posts, strlen( $prompt['user'] ), $this->tokens( $prompt['user'] ) );
		}

		// Table 2: ai.max_tokens.
		$ideas_count = (int) Config::get( 'desk.ideas_per_request' );
		$ideas_reply = array(
			'ideas' => array_fill(
				0,
				$ideas_count,
				array(
					'title'    => $this->text( self::TITLE_CHARS, 'title' ),
					'angle'    => $this->text( self::ANGLE_CHARS, 'angle' ),
					'category' => 'technology',
				)
			),
		);
		$max_parts   = (int) Config::get( 'ai.series_max_parts' );
		$replies     = array(
			'ideas (desk.ideas_per_request)' => array( $ideas_reply, Schemas::ideas( array( 'technology' ), $ideas_count ) ),
			'series worst case'               => array( $this->series_reply( $max_parts, self::OUTLINE_LINES, self::OUTLINE_LINE_CHARS ), Schemas::series_proposal( array( 'technology' ), $max_parts ) ),
			'series typical'                  => array( $this->series_reply( self::TYPICAL_PARTS, self::TYPICAL_LINES, self::TYPICAL_LINE_CHARS ), Schemas::series_proposal( array( 'technology' ), $max_parts ) ),
		);
		$token_rows  = array();
		foreach ( $replies as $name => $pair ) {
			$this->assertTrue( Schemas::validate( $pair[0], $pair[1] ), $name );
			$token_rows[ $name ] = $this->tokens( (string) json_encode( $pair[0] ) );
		}

		// Table 3: ai.monthly_request_cap.
		$per_week = 3 * self::DAYS_PER_WEEK + 1;
		$cap_rows = array();
		foreach ( array( 150, 300, 600 ) as $cap ) {
			$cap_rows[] = array( $cap, intdiv( $cap * self::DAYS_PER_WEEK, $per_week ) );
		}

		echo "\nAiBench table 1: ai.context_max_posts (80-char titles, 300-char excerpts)\n";
		foreach ( $context_rows as $row ) {
			echo esc_html( sprintf( "  posts=%d chars=%d est_tokens=%d under_%d=%s\n", $row[0], $row[1], $row[2], self::PROMPT_BUDGET, $row[2] <= self::PROMPT_BUDGET ? 'yes' : 'no' ) );
		}
		echo "AiBench table 2: ai.max_tokens (estimated reply tokens, chars/4)\n";
		foreach ( $token_rows as $name => $tokens ) {
			$fits = array();
			foreach ( array( 800, 1200, 2000 ) as $limit ) {
				$fits[] = $limit . '=' . ( $tokens <= $limit ? 'fits' : 'no' );
			}
			echo esc_html( sprintf( "  %s tokens=%d %s\n", $name, $tokens, implode( ' ', $fits ) ) );
		}
		echo "AiBench table 3: ai.monthly_request_cap (22 requests a week: 3 a day plus 1 series proposal)\n";
		foreach ( $cap_rows as $row ) {
			echo esc_html( sprintf( "  cap=%d days_covered=%d at_least_%d=%s\n", $row[0], $row[1], self::MIN_DAYS_COVERED, $row[1] >= self::MIN_DAYS_COVERED ? 'yes' : 'no' ) );
		}

		$this->assertCount( 3, $context_rows );
		$this->assertCount( 3, $token_rows );
		$this->assertCount( 3, $cap_rows );
	}
}
