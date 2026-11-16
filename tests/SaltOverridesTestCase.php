<?php
/**
 * Shared set-up for the extension-point tests (ID-3613): fixture and
 * temporary child themes, valid declarations to vary, and a section render
 * that captures its output.
 *
 * The shims' locate_template() is parent-only, so a render hands the
 * child's template path to salt_render_resolved_section() itself — the path
 * WordPress's locate_template() returns when the child has the file.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/shims/acf.php';

abstract class SaltOverridesTestCase extends SaltTestCase {

	/**
	 * Temporary directories / files to delete after each test.
	 *
	 * @var string[]
	 */
	protected array $temp = array();

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		unset( $GLOBALS['salt_test_screen'] );
	}

	protected function tearDown(): void {
		foreach ( array_reverse( $this->temp ) as $path ) {
			if ( is_dir( $path ) ) {
				$this->remove_dir( $path );
			} elseif ( is_file( $path ) ) {
				unlink( $path );
			}
		}
		$this->temp = array();
		unset( $GLOBALS['salt_test_screen'] );
		salt_test_use_child_theme( null );
		parent::tearDown();
	}

	protected function remove_dir( string $dir ): void {
		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir( $path ) ? $this->remove_dir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	protected static function fixture(): string {
		return __DIR__ . '/fixtures/themes/grain-fixture';
	}

	protected static function parent_view( string $slug ): string {
		return dirname( __DIR__ ) . '/template-parts/sections/' . $slug . '.php';
	}

	/**
	 * A temporary child theme directory.
	 *
	 * @param array<string,string> $files Relative path => contents.
	 * @return string Directory.
	 */
	protected function temp_child( array $files ): string {
		$dir = sys_get_temp_dir() . '/salt-child-' . bin2hex( random_bytes( 6 ) );
		mkdir( $dir );
		$this->temp[] = $dir;
		foreach ( $files as $path => $contents ) {
			if ( ! is_dir( dirname( $dir . '/' . $path ) ) ) {
				mkdir( dirname( $dir . '/' . $path ), 0777, true );
			}
			file_put_contents( $dir . '/' . $path, $contents );
		}
		return $dir;
	}

	/**
	 * A valid declaration, as an array, with changes merged in.
	 *
	 * @param array<string,mixed> $changes Top-level keys to replace.
	 * @return array<string,mixed>
	 */
	protected static function declaration( array $changes = array() ): array {
		return array_merge(
			array(
				'$schema'  => 'https://unpkg.com/@lightlysaltedhq/salt-contract@1.0.0/schema/replaced-logic.schema.json',
				'site'     => 'example-site',
				'platform' => 'wordpress',
				'contract' => '1.0.0',
				'sections' => array( self::entry() ),
			),
			$changes
		);
	}

	/**
	 * One valid sections[] entry, with changes merged in.
	 *
	 * @param array<string,mixed> $changes Keys to replace.
	 * @return array<string,mixed>
	 */
	protected static function entry( array $changes = array() ): array {
		return array_merge(
			array(
				'section'  => 'hero',
				'tier'     => 'view',
				'files'    => array( 'template-parts/sections/hero.php' ),
				'reason'   => 'A reason.',
				'since'    => '14/11/2026',
				'verified' => '1.0.0',
			),
			$changes
		);
	}

	protected static function json( array $data ): string {
		return (string) json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	}

	protected function seed_row( string $layout, array $fields ): void {
		salt_test_seed_fc(
			array(
				array(
					'layout' => $layout,
					'fields' => $fields,
				),
			)
		);
		the_row();
	}

	protected function render( string $layout, string $view, array $context = array() ): string {
		$context += array(
			'index'      => 0,
			'is_primary' => false,
		);
		ob_start();
		try {
			salt_render_resolved_section( $layout, $view, $context );
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}
}
