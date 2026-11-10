<?php
/**
 * AI-written post ideas for an open day.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Ai\AiClient;
use Masthead\Ai\Prompts;
use Masthead\Ai\Schemas;
use Masthead\Config;
use Masthead\Desk\Candidates\Candidate;
use Masthead\Desk\Candidates\CategoryGap;
use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Support\Categories;
use Masthead\Support\Dates;

/**
 * Asks the AI for ideas and keeps only what validates. Titles and excerpts go out; bodies never do.
 */
class Ideas {

	/**
	 * Constructor.
	 *
	 * @param AiClient     $ai     The budgeted client.
	 * @param PlanReader   $reader Plan reader.
	 * @param SeriesSource $series Series adapter.
	 * @param CategoryGap  $gaps   Gap source.
	 * @param Budget       $budget Budget.
	 */
	public function __construct(
		private AiClient $ai,
		private PlanReader $reader,
		private SeriesSource $series,
		private CategoryGap $gaps,
		private Budget $budget
	) {}

	/**
	 * Whether ideas can be asked for, and how many requests are left.
	 *
	 * @return array{supported:bool,remaining:int}
	 */
	public function status(): array {
		return array(
			'supported' => $this->ai->supported(),
			'remaining' => $this->budget->remaining(),
		);
	}

	/**
	 * Context for the prompt. Post-derived text only: titles and excerpts, never content.
	 *
	 * @param string $date Target day, Y-m-d.
	 * @return array{posts:array<int,array{title:string,excerpt:string}>,series:string[],gaps:string[]}
	 */
	public function context( string $date ): array {
		$plan    = $this->reader->range( Dates::add_days( $date, - (int) Config::get( 'ai.context_days' ) ), $date );
		$drafts  = (bool) Config::get( 'ai.include_draft_excerpts' );
		$planned = array();
		foreach ( array_reverse( $plan['days'] ) as $day ) {
			foreach ( array_reverse( $day['posts'] ?? array() ) as $post ) {
				$planned[] = $post;
			}
		}
		$planned = array_merge( $planned, $plan['unscheduled'] ?? array() );

		$posts = array();
		$limit = (int) Config::get( 'ai.context_max_posts' );
		foreach ( $planned as $post ) {
			if ( count( $posts ) >= $limit ) {
				break;
			}
			if ( 'draft' === ( $post['status'] ?? '' ) && ! $drafts ) {
				continue;
			}
			$posts[] = array(
				'title'   => (string) $post['title'],
				'excerpt' => $this->excerpt( (int) $post['id'] ),
			);
		}

		$gaps = array();
		foreach ( $this->gaps->gaps() as $candidate ) {
			$gaps[] = (string) ( $candidate->to_array()['data']['category_name'] ?? '' );
		}
		return array(
			'posts'  => $posts,
			'series' => $this->open_series(),
			'gaps'   => array_values( array_filter( $gaps ) ),
		);
	}

	/**
	 * Asks for ideas for a day.
	 *
	 * @param string   $date      Target day, Y-m-d.
	 * @param array    $lane      Lane for the day.
	 * @param string[] $dismissed Dismissed suggestion keys.
	 * @return Candidate[]|\WP_Error
	 */
	public function ideas( string $date, array $lane, array $dismissed ): array|\WP_Error {
		$every   = array_column( Categories::all(), 'slug' );
		$allowed = array() === ( $lane['categories'] ?? array() ) ? $every : array_values( $lane['categories'] );
		$count   = (int) Config::get( 'desk.ideas_per_request' );
		$prompt  = Prompts::ideas( $lane, $date, $this->context( $date ), $count );

		$reply = $this->ai->json( $prompt['system'], $prompt['user'], Schemas::ideas( $allowed, $count ), 'fast' );
		if ( $reply instanceof \WP_Error ) {
			return $reply;
		}
		// Structure is checked against every category; the lane filter below drops, never coerces.
		if ( true !== Schemas::validate( $reply, Schemas::ideas( $every, $count ) ) ) {
			return new \WP_Error( 'masthead_ai_invalid', __( 'The AI reply did not match the expected shape.', 'masthead' ) );
		}

		$out = array();
		foreach ( $reply['ideas'] as $idea ) {
			$key = Candidate::idea_key( $idea['title'] );
			if ( ! in_array( $idea['category'], $allowed, true ) || in_array( $key, $dismissed, true ) || isset( $out[ $key ] ) ) {
				continue;
			}
			$out[ $key ] = Candidate::make(
				'idea',
				$key,
				$idea['title'],
				0.0,
				array(
					'category' => $idea['category'],
					'data'     => array( 'angle' => $idea['angle'] ),
				)
			);
		}
		return array_values( $out );
	}

	/**
	 * Excerpt of a post, plain and short. The body is never read.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function excerpt( int $post_id ): string {
		return wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $post_id ) ) );
	}

	/**
	 * Names of series that still have undated or missing parts.
	 *
	 * @return string[]
	 */
	private function open_series(): array {
		$names = array();
		foreach ( $this->series->terms() as $term ) {
			$parts = $this->series->parts( (int) $term->term_id );
			if ( array() === $parts ) {
				continue;
			}
			$total   = $this->series->for_post( $parts[0]['post_id'] )['total'] ?? null;
			$undated = array() !== array_filter( $parts, static fn( $part ) => ! $part['dated'] );
			if ( $undated || ( null !== $total && count( $parts ) < $total ) ) {
				$names[] = (string) $term->name;
			}
		}
		return $names;
	}
}
