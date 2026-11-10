<?php
/**
 * Acceptance fixture tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Guards docs/fixtures/queue-2026-11-09.json: synthetic titles, unchanged scenario.
 */
final class FixtureTest extends PhpUnitTestCase {

	private const SYNTHETIC_IDS = array( 10193, 12177, 10194, 10178, 10192, 10191 );

	private function fixture(): array {
		$path = dirname( __DIR__, 2 ) . '/docs/fixtures/queue-2026-11-09.json';
		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertIsArray( $data );
		return $data;
	}

	/**
	 * Posts by ID.
	 *
	 * @return array<int,array>
	 */
	private function posts(): array {
		$by_id = array();
		foreach ( $this->fixture()['posts'] as $post ) {
			$by_id[ $post['id'] ] = $post;
		}
		return $by_id;
	}

	public function test_fixture_declares_synthetic_titles(): void {
		$fixture = $this->fixture();
		$this->assertStringContainsString( 'synthetic', $fixture['_source'] );
		$this->assertStringContainsString( 'synthetic', $fixture['_note'] );
		$this->assertStringNotContainsString( 'are real', $fixture['_note'] );
		$this->assertStringNotContainsString( 'eric.mann', $fixture['_source'] );

		$posts  = $this->posts();
		$titles = array();
		foreach ( self::SYNTHETIC_IDS as $id ) {
			$titles[ $id ] = $posts[ $id ]['title'];
		}
		$this->assertCount( count( self::SYNTHETIC_IDS ), array_unique( $titles ) );
		// Split so this file does not itself contain the retired titles.
		$parts = array(
			array( 'Craft', 'smen' ),
			array( 'DailyMed', 'Today' ),
			array( 'Embeddings ', 'Reveal' ),
			array( 'Nineteen ', 'Years' ),
			array( 'Voice to ', 'Vectors' ),
			array( 'RAG Over ', 'Markdown' ),
		);
		$real  = array_map( static fn( array $pair ) => implode( '', $pair ), $parts );
		foreach ( $real as $needle ) {
			$this->assertStringNotContainsString( $needle, (string) json_encode( $fixture ), $needle );
		}
		$this->assertSame( array( 'automation', 'team tooling' ), $posts[10178]['tags'] );
	}

	public function test_fixture_ids_dates_statuses_and_categories_are_unchanged(): void {
		$expected = array(
			12155 => array( 'publish', '2026-11-09 06:23:15', array( 'writing' ) ),
			10193 => array( 'publish', '2026-11-09 05:47:05', array( 'technology', 'faith' ) ),
			12177 => array( 'future', '2026-11-11 08:00:00', array( 'technology' ) ),
			10194 => array( 'future', '2026-11-12 07:37:00', array( 'faith', 'technology' ) ),
			12156 => array( 'future', '2026-12-14 06:23:23', array( 'writing' ) ),
			12157 => array( 'future', '2027-01-11 06:20:00', array( 'writing' ) ),
			12158 => array( 'future', '2027-02-08 06:23:33', array( 'writing' ) ),
			10178 => array( 'draft', null, array( 'technology' ) ),
			10192 => array( 'publish', '2026-10-01 07:57:00', array( 'technology' ) ),
			10191 => array( 'publish', '2026-09-29 07:57:00', array( 'technology' ) ),
		);
		$posts = $this->posts();
		$this->assertSame( array_keys( $expected ), array_keys( $posts ) );
		foreach ( $expected as $id => $row ) {
			$this->assertSame( $row[0], $posts[ $id ]['status'], (string) $id );
			$this->assertSame( $row[1], $posts[ $id ]['date'], (string) $id );
			$this->assertSame( $row[2], $posts[ $id ]['categories'], (string) $id );
		}
		$this->assertSame( '2026-10-29 21:46:13', $posts[10178]['modified'] );
		$fixture = $this->fixture();
		$this->assertSame( '2026-11-09 14:00:00', $fixture['now'] );
		$this->assertSame( 'America/Los_Angeles', $fixture['timezone'] );
	}

	public function test_series_posts_keep_their_titles(): void {
		$posts = $this->posts();
		foreach ( array(
			12155 => 1,
			12156 => 2,
			12157 => 3,
			12158 => 4,
		) as $id => $part ) {
			$this->assertSame( 'The Mantle – Part ' . $part . ' of 4', $posts[ $id ]['title'] );
			$this->assertSame( 'the-mantle', $posts[ $id ]['series'] );
			$this->assertSame( $part, $posts[ $id ]['part'] );
		}
	}
}
