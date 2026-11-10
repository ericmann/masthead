<?php
/**
 * AI series proposals with dates computed by Masthead.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Ai\AiClient;
use Masthead\Ai\Prompts;
use Masthead\Ai\Schemas;
use Masthead\Config;
use Masthead\Plan\Cadence;
use Masthead\Plan\Lanes;
use Masthead\Support\Categories;
use Masthead\Support\Clock;
use Masthead\Support\Dates;

/**
 * The AI proposes names, titles and outlines; this class chooses every date.
 */
class SeriesPlanner {

	private const BAD_REQUEST = 400;

	/**
	 * Clock.
	 *
	 * @var Clock
	 */
	private Clock $clock;

	/**
	 * Constructor.
	 *
	 * @param AiClient   $ai    The budgeted client.
	 * @param Clock|null $clock Clock; the shared Clock when omitted.
	 */
	public function __construct( private AiClient $ai, ?Clock $clock = null ) {
		$this->clock = $clock ?? new Clock();
	}

	/**
	 * Proposes a series. Writes nothing.
	 *
	 * @param string      $prompt The editor's request.
	 * @param string|null $start  Earliest first date, Y-m-d; the day after today when omitted.
	 * @return array|\WP_Error { name, category, cadence, lane, parts: [ { date, title, outline } ] }
	 */
	public function propose( string $prompt, ?string $start ): array|\WP_Error {
		if ( '' === trim( $prompt ) ) {
			return new \WP_Error( 'masthead_invalid_input', __( 'Describe the series you want.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		$today = $this->clock->today();
		$start = null === $start || '' === $start ? Dates::add_days( $today, 1 ) : $start;
		if ( ! Dates::is_date( $start ) || $start < $today ) {
			return new \WP_Error( 'masthead_invalid_date', __( 'The start must be a real date that is not in the past.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}

		$max    = (int) Config::get( 'ai.series_max_parts' );
		$slugs  = array_column( Categories::all(), 'slug' );
		$schema = Schemas::series_proposal( $slugs, $max );
		$ask    = Prompts::series_proposal( $prompt, $slugs, $max );

		$reply = $this->ai->json( $ask['system'], $ask['user'], $schema, 'strong' );
		if ( $reply instanceof \WP_Error ) {
			return $reply;
		}
		if ( true !== Schemas::validate( $reply, $schema ) || array() === $reply['parts'] ) {
			return new \WP_Error( 'masthead_ai_invalid', __( 'The AI reply did not match the expected shape.', 'masthead' ) );
		}

		$first   = $this->first_date( $start, $reply['category'] );
		$lane    = Lanes::for_date( $first );
		$cadence = array(
			'every_weeks' => $reply['every_weeks'],
			'weekday'     => Dates::weekday( $first ),
			'time'        => $lane['time'],
		);
		$dates   = Cadence::schedule( $first, $cadence, count( $reply['parts'] ) );

		$parts = array();
		foreach ( $reply['parts'] as $index => $part ) {
			$parts[] = array(
				'date'    => $dates[ $index ],
				'title'   => $part['title'],
				'outline' => $part['outline'],
			);
		}
		return array(
			'name'     => $reply['name'],
			'category' => $reply['category'],
			'cadence'  => $cadence,
			'lane'     => array(
				'slug'       => $lane['slug'],
				'label'      => $lane['label'],
				'categories' => $lane['categories'],
			),
			'parts'    => $parts,
		);
	}

	/**
	 * First day on or after the start whose lane lists the category, within a week; else the start.
	 *
	 * @param string $start    Earliest date.
	 * @param string $category Category slug.
	 * @return string
	 */
	private function first_date( string $start, string $category ): string {
		for ( $offset = 0; $offset < Dates::DAYS_IN_WEEK; $offset++ ) {
			$date = Dates::add_days( $start, $offset );
			if ( in_array( $category, Lanes::for_date( $date )['categories'], true ) ) {
				return $date;
			}
		}
		return $start;
	}
}
