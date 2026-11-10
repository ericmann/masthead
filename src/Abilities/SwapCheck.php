<?php
/**
 * Ability masthead/swap-check.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;

/**
 * Reads the findings the nightly run stored, minus the ones that are stale. It never runs the check and never writes.
 */
final class SwapCheck implements Ability {

	/**
	 * Option holding the stored findings.
	 */
	private const OPTION = 'masthead_findings';

	/**
	 * Constructor.
	 *
	 * @param PlanReader   $reader Plan reader.
	 * @param SeriesSource $series Series adapter.
	 */
	public function __construct( private PlanReader $reader, private SeriesSource $series ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/swap-check';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Swap check', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Returns the findings from the last nightly check: scheduled posts that do not fit their lane, collide, break a series cadence or have a better candidate. Findings that are no longer true are left out.', 'masthead' );
	}

	/**
	 * Input schema.
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'from' => array( 'type' => 'string' ),
				'to'   => array( 'type' => 'string' ),
			),
			'additionalProperties' => false,
			// A read-only ability is called with GET; an empty input is not sent, so null must mean the empty object.
			'default'              => array(),
		);
	}

	/**
	 * Output schema.
	 *
	 * @return array<string,mixed>
	 */
	public function output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'generated_at' => array( 'type' => array( 'string', 'null' ) ),
				'findings'     => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
			'required'   => array( 'generated_at', 'findings' ),
		);
	}

	/**
	 * Annotations.
	 *
	 * @return array{readonly:bool,destructive:bool,idempotent:bool}
	 */
	public function annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}

	/**
	 * Permission.
	 *
	 * @param mixed $input Input.
	 * @return bool
	 */
	public function permission( $input ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input { from?, to? }.
	 * @return array<string,mixed>
	 */
	public function execute( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$from   = is_string( $input['from'] ?? null ) ? $input['from'] : '';
		$to     = is_string( $input['to'] ?? null ) ? $input['to'] : '';
		$stored = get_option( self::OPTION );
		if ( ! is_array( $stored ) || ! is_array( $stored['items'] ?? null ) ) {
			return array(
				'generated_at' => null,
				'findings'     => array(),
			);
		}

		$findings = array();
		foreach ( $stored['items'] as $finding ) {
			$date = (string) ( $finding['date'] ?? '' );
			if ( ( '' !== $from && $date < $from ) || ( '' !== $to && $date > $to ) || $this->stale( $finding ) ) {
				continue;
			}
			$findings[] = $finding;
		}
		return array(
			'generated_at' => is_string( $stored['generated_at'] ?? null ) ? $stored['generated_at'] : null,
			'findings'     => $findings,
		);
	}

	/**
	 * Whether a finding is no longer true.
	 *
	 * @param array $finding Stored finding.
	 * @return bool
	 */
	private function stale( array $finding ): bool {
		$post = $this->reader->post( (int) ( $finding['post_id'] ?? 0 ) );
		if ( null === $post || ! in_array( $post['status'], array( 'future', 'draft' ), true ) || null === $post['date'] ) {
			return true;
		}
		$post_date = explode( 'T', (string) $post['date'] )[0];
		if ( ( $finding['date'] ?? '' ) !== $post_date ) {
			return true;
		}
		return 'lane_mismatch' === ( $finding['type'] ?? '' )
			&& isset( $post['series']['term_id'] )
			&& $this->series->lane_exception( (int) $post['series']['term_id'] );
	}
}
