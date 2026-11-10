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
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Support\Categories;
use Masthead\Support\Clock;
use Masthead\Support\Dates;

/**
 * The AI proposes names, titles and outlines; this class chooses every date.
 */
class SeriesPlanner {

	private const BAD_REQUEST = 400;
	private const CONFLICT    = 409;

	/**
	 * Constructor.
	 *
	 * @param AiClient     $ai     The budgeted client.
	 * @param Clock        $clock  Clock.
	 * @param Slots        $slots  Planned-draft writer.
	 * @param SeriesSource $series Series adapter.
	 */
	public function __construct(
		private AiClient $ai,
		private Clock $clock,
		private Slots $slots,
		private SeriesSource $series
	) {}

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
	 * Creates a proposed series: the term, its cadence and one dated draft per part. All or nothing.
	 *
	 * @param string      $name     Series name.
	 * @param array       $parts    Each { date, title, outline? }.
	 * @param array|null  $cadence  { every_weeks, weekday, time }, or null for none.
	 * @param string|null $category Category slug for the drafts; the first part's lane category when null.
	 * @return array|\WP_Error { term_id, post_ids }
	 */
	public function accept( string $name, array $parts, ?array $cadence, ?string $category ): array|\WP_Error {
		$checked = $this->check_accept( trim( $name ), $parts, $cadence, $category );
		if ( $checked instanceof \WP_Error ) {
			return $checked;
		}
		list( $name, $cadence, $category ) = $checked;

		$created = wp_insert_term( $name, $this->series->taxonomy() );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$term_id  = (int) $created['term_id'];
		$post_ids = array();
		if ( null !== $cadence ) {
			update_term_meta( $term_id, 'masthead_cadence', Cadence::to_json( $cadence ) );
		}
		update_term_meta( $term_id, $this->series->total_meta(), count( $parts ) );

		foreach ( array_values( $parts ) as $index => $part ) {
			$input = array(
				'date'     => $part['date'],
				'title'    => $part['title'],
				'category' => $category,
				'series'   => array(
					'term_id' => $term_id,
					'part'    => $index + 1,
				),
				'outline'  => $part['outline'] ?? array(),
			);
			if ( null !== $cadence ) {
				$input['time'] = $cadence['time'];
			}
			$post_id = $this->slots->create( $input );
			if ( is_wp_error( $post_id ) ) {
				foreach ( $post_ids as $made ) {
					$this->slots->delete( $made );
				}
				wp_delete_term( $term_id, $this->series->taxonomy() );
				return $post_id;
			}
			$post_ids[] = $post_id;
		}
		return array(
			'term_id'  => $term_id,
			'post_ids' => $post_ids,
		);
	}

	/**
	 * Validates an accept request before anything is written.
	 *
	 * @param string      $name     Trimmed name.
	 * @param array       $parts    Parts.
	 * @param array|null  $cadence  Cadence.
	 * @param string|null $category Category slug.
	 * @return array{0:string,1:?array,2:string}|\WP_Error Name, cleaned cadence, category slug.
	 */
	private function check_accept( string $name, array $parts, ?array $cadence, ?string $category ): array|\WP_Error {
		if ( '' === $name ) {
			return $this->invalid( __( 'A series needs a name.', 'masthead' ) );
		}
		$max = (int) Config::get( 'ai.series_max_parts' );
		if ( array() === $parts || count( $parts ) > $max || ! array_is_list( $parts ) ) {
			return $this->invalid(
				sprintf(
					/* translators: %d: maximum number of parts. */
					__( 'A series needs between one and %d parts.', 'masthead' ),
					$max
				)
			);
		}
		$today = $this->clock->today();
		foreach ( $parts as $part ) {
			$date = is_array( $part ) && is_string( $part['date'] ?? null ) ? $part['date'] : '';
			if ( ! Dates::is_date( $date ) || $date < $today ) {
				return new \WP_Error( 'masthead_invalid_date', __( 'Every part needs a real date that is not in the past.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
			}
			if ( '' === trim( is_string( $part['title'] ?? null ) ? $part['title'] : '' ) ) {
				return $this->invalid( __( 'Every part needs a title.', 'masthead' ) );
			}
		}
		if ( null !== $cadence ) {
			$cadence = Cadence::sanitize( $cadence );
			if ( null === $cadence ) {
				return $this->invalid( __( 'The cadence is not valid.', 'masthead' ) );
			}
		}
		if ( null === $category || '' === $category ) {
			$category = Lanes::for_date( $parts[0]['date'] )['categories'][0] ?? '';
			if ( '' === $category ) {
				return $this->invalid( __( 'Choose a category for this series.', 'masthead' ) );
			}
		}
		if ( ! is_object( get_term_by( 'slug', $category, 'category' ) ) ) {
			return new \WP_Error( 'masthead_unknown_category', __( 'The category does not exist.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		if ( term_exists( $name, $this->series->taxonomy() ) ) {
			return new \WP_Error( 'masthead_series_exists', __( 'A series with that name already exists.', 'masthead' ), array( 'status' => self::CONFLICT ) );
		}
		return array( $name, $cadence, $category );
	}

	/**
	 * A bad-request error.
	 *
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	private function invalid( string $message ): \WP_Error {
		return new \WP_Error( 'masthead_invalid_input', $message, array( 'status' => self::BAD_REQUEST ) );
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
