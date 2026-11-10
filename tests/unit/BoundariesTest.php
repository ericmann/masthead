<?php
/**
 * Module boundary tests (SPEC 4.2).
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Enforces the module import table.
 */
final class BoundariesTest extends PhpUnitTestCase {

	/**
	 * Modules each module may import.
	 *
	 * @var array<string,string[]>
	 */
	private const ALLOWED = array(
		'Admin'     => array( 'Config', 'Support', 'Plan' ),
		'Cli'       => array( 'Abilities', 'Desk', 'Plan', 'Stats', 'Social', 'Config', 'Support' ),
		'Abilities' => array( 'Desk', 'Plan', 'Stats', 'Social', 'Ai', 'Config', 'Support' ),
		'Cron'      => array( 'Desk', 'Stats', 'Config', 'Support' ),
		'Desk'      => array( 'Plan', 'Config', 'Support' ),
		'Ai'        => array( 'Config', 'Support' ),
		'Social'    => array( 'Config', 'Support' ),
		'Stats'     => array( 'Config', 'Support' ),
		'Plan'      => array( 'Config', 'Support' ),
		'Support'   => array( 'Config' ),
		'Config'    => array(),
	);

	/**
	 * The only names Desk may import from other modules.
	 *
	 * @var string[]
	 */
	private const DESK_NAMED = array(
		'Stats\StatsSource',
		'Social\ShareSource',
		'Ai\AiClient',
		'Ai\Prompts',
		'Ai\Schemas',
	);

	/**
	 * Top-level names every module may reference.
	 *
	 * @var string[]
	 */
	private const GLOBAL_NAMES = array( 'SLUG', 'VERSION' );

	/**
	 * Returns the module a source path belongs to.
	 *
	 * @param string $path Path containing src/.
	 * @return string
	 */
	private static function module_of( string $path ): string {
		$relative = substr( $path, (int) strrpos( $path, 'src/' ) + strlen( 'src/' ) );
		$parts    = explode( '/', $relative );
		if ( count( $parts ) > 1 ) {
			return $parts[0];
		}
		return basename( $parts[0], '.php' );
	}

	/**
	 * Scans one file for forbidden imports.
	 *
	 * @param string $path   Path containing src/.
	 * @param string $source PHP source.
	 * @return string[] Violation messages.
	 */
	public static function scan( string $path, string $source ): array {
		$module = self::module_of( $path );
		if ( 'Plugin' === $module ) {
			return array();
		}
		$code = preg_replace( '#/\*.*?\*/#s', '', $source );
		$code = preg_replace( '#^\s*(//|\#).*$#m', '', (string) $code );
		$code = preg_replace( '#^\s*namespace\s+[^;{]+;#m', '', (string) $code );

		preg_match_all( '/\\\\?Masthead\\\\([A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*)/', (string) $code, $matches );

		$violations = array();
		foreach ( array_unique( $matches[1] ) as $name ) {
			$segments = explode( '\\', $name );
			$target   = $segments[0];
			$rest     = implode( '\\', array_slice( $segments, 1 ) );

			if ( $target === $module || in_array( $target, self::GLOBAL_NAMES, true ) ) {
				continue;
			}
			if ( 'Tests' === $target ) {
				$violations[] = "$path: production code must not reference Masthead\\$name";
				continue;
			}
			$allowed = self::ALLOWED[ $module ] ?? array();
			if ( 'Config' === $target && 'Config' !== $module && '' === $rest ) {
				continue;
			}
			if ( in_array( $target, $allowed, true ) ) {
				continue;
			}
			if ( 'Desk' === $module && in_array( $name, self::DESK_NAMED, true ) ) {
				continue;
			}
			$violations[] = "$path: module $module may not import Masthead\\$name";
		}
		return $violations;
	}

	public function test_every_source_file_respects_the_module_map(): void {
		$root       = dirname( __DIR__, 2 ) . '/src';
		$iterator   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		$violations = array();
		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$source = (string) file_get_contents( $file->getPathname() );
			foreach ( self::scan( $file->getPathname(), $source ) as $violation ) {
				$violations[] = $violation;
			}
		}
		$this->assertSame( array(), $violations );
	}

	public function test_scanner_reports_a_forbidden_import(): void {
		$source = "<?php\nnamespace Masthead\\Plan;\nuse Masthead\\Desk\\Suggester;\nuse Masthead\\Config;\n";
		$found  = self::scan( 'src/Plan/Slots.php', $source );
		$this->assertCount( 1, $found );
		$this->assertStringContainsString( 'Masthead\\Desk\\Suggester', $found[0] );
	}

	public function test_scanner_allows_desk_to_import_only_named_interfaces(): void {
		$ok  = "<?php\nnamespace Masthead\\Desk;\nuse Masthead\\Stats\\StatsSource;\nuse Masthead\\Ai\\Schemas;\nuse Masthead\\Plan\\Lanes;\n";
		$bad = "<?php\nnamespace Masthead\\Desk;\nuse Masthead\\Stats\\JetpackStats;\nuse Masthead\\Social\\Factory;\n";
		$this->assertSame( array(), self::scan( 'src/Desk/Ranker.php', $ok ) );
		$this->assertCount( 2, self::scan( 'src/Desk/Ranker.php', $bad ) );
	}

	public function test_scanner_reports_fully_qualified_references(): void {
		$source = "<?php\nnamespace Masthead\\Stats;\n\$x = new \\Masthead\\Social\\JetpackSocial();\n\$y = \\Masthead\\SLUG;\n";
		$found  = self::scan( 'src/Stats/Cache.php', $source );
		$this->assertCount( 1, $found );
		$this->assertStringContainsString( 'Masthead\\Social\\JetpackSocial', $found[0] );
	}
}
