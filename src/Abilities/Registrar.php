<?php
/**
 * Ability registrar.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Config;
use const Masthead\SLUG;

/**
 * Registers the masthead category and every ability.
 */
final class Registrar {

	/**
	 * The abilities.
	 *
	 * @var Ability[]
	 */
	private array $abilities;

	/**
	 * Constructor.
	 *
	 * @param Ability ...$abilities Abilities.
	 */
	public function __construct( Ability ...$abilities ) {
		$this->abilities = $abilities;
	}

	/**
	 * Registers the category. Hooked on wp_abilities_api_categories_init.
	 *
	 * @return void
	 */
	public function register_category(): void {
		wp_register_ability_category(
			SLUG,
			array(
				'label'       => __( 'Masthead', 'masthead' ),
				'description' => __( 'Editorial planning: the calendar, suggestions, swap checks and sharing.', 'masthead' ),
			)
		);
	}

	/**
	 * Registers every ability. Hooked on wp_abilities_api_init.
	 *
	 * @return void
	 */
	public function register(): void {
		foreach ( $this->abilities as $ability ) {
			$public = 'masthead/share-post' === $ability->name()
				? (bool) Config::get( 'abilities.expose_share' )
				: (bool) Config::get( 'abilities.expose_mcp' );
			wp_register_ability(
				$ability->name(),
				array(
					'label'               => $ability->label(),
					'description'         => $ability->description(),
					'category'            => SLUG,
					'input_schema'        => $ability->input_schema(),
					'output_schema'       => $ability->output_schema(),
					'execute_callback'    => array( $ability, 'execute' ),
					'permission_callback' => array( $ability, 'permission' ),
					'meta'                => array(
						'annotations'  => $ability->annotations(),
						// The admin UI runs every ability over REST, so REST exposure is always on.
						'show_in_rest' => true,
						'public'       => $public,
					),
				)
			);
		}
	}

	/**
	 * One ability by name.
	 *
	 * @param string $name Ability name.
	 * @return Ability|null
	 */
	public function get( string $name ): ?Ability {
		foreach ( $this->abilities as $ability ) {
			if ( $ability->name() === $name ) {
				return $ability;
			}
		}
		return null;
	}

	/**
	 * Which abilities are read-only, for the client to choose GET or POST.
	 *
	 * @return array<string,array{readonly:bool}>
	 */
	public function describe(): array {
		$described = array();
		foreach ( $this->abilities as $ability ) {
			$described[ $ability->name() ] = array( 'readonly' => (bool) $ability->annotations()['readonly'] );
		}
		return $described;
	}
}
