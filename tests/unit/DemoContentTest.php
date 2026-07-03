<?php
/**
 * Drift guard for the demo seeder (bin/demo/data.php).
 *
 * The seeder writes content via ACF update_field() using field NAMES. If a
 * layout slug or field name in the theme changes, the seeder silently
 * writes nothing for that field. This test loads the pure content map and
 * asserts every acf_fc_layout is a registered layout and every field/section
 * key it uses is a real (top-level) field for that layout / CPT / options —
 * so the seeder stays in lock-step with the schema and drift fails in CI.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

require_once dirname( __DIR__, 2 ) . '/inc/fields/options.php';
$salt_demo_field_files = glob( dirname( __DIR__, 2 ) . '/inc/fields/{options-,cpt-}*.php', GLOB_BRACE );
foreach ( false === $salt_demo_field_files ? array() : $salt_demo_field_files as $salt_demo_field_file ) {
	require_once $salt_demo_field_file;
}
require_once dirname( __DIR__, 2 ) . '/inc/fields/sections.php';
unset( $salt_demo_field_files, $salt_demo_field_file );

$GLOBALS['salt_demo_acf_init_snapshot'] = $GLOBALS['salt_test_hooks']['acf/init'] ?? array();

final class DemoContentTest extends SaltTestCase {

	/** @var array<string,mixed> */
	private static array $data;

	public static function setUpBeforeClass(): void {
		self::$data = require dirname( __DIR__, 2 ) . '/bin/demo/data.php';
	}

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_acf_groups']        = array();
		$GLOBALS['salt_test_hooks']['acf/init'] = $GLOBALS['salt_demo_acf_init_snapshot'];
		salt_test_do_action( 'acf/init' );
	}

	/**
	 * Top-level field names of a captured group (skips tabs; ignores nesting).
	 *
	 * @param array<int,array<string,mixed>> $fields Field definitions.
	 * @return string[]
	 */
	private function top_level_names( array $fields ): array {
		$names = array();
		foreach ( $fields as $field ) {
			if ( isset( $field['name'] ) && '' !== $field['name'] ) {
				$names[] = (string) $field['name'];
			}
		}
		return $names;
	}

	/**
	 * Find a captured group by key.
	 *
	 * @param string $key Group key.
	 * @return array<string,mixed>|null
	 */
	private function group( string $key ): ?array {
		foreach ( $GLOBALS['salt_test_acf_groups'] as $group ) {
			if ( ( $group['key'] ?? '' ) === $key ) {
				return $group;
			}
		}
		return null;
	}

	/**
	 * layout name => allowed top-level sub-field names.
	 *
	 * @return array<string,string[]>
	 */
	private function layout_fields(): array {
		$sections = $this->group( 'group_salt_sections' );
		$this->assertNotNull( $sections, 'group_salt_sections must register.' );

		$fc = $sections['fields'][0] ?? array();
		$this->assertSame( 'sections', $fc['name'] ?? '', 'First field must be the "sections" flexible content.' );

		$map = array();
		foreach ( $fc['layouts'] ?? array() as $layout ) {
			$map[ (string) $layout['name'] ] = $this->top_level_names( $layout['sub_fields'] ?? array() );
		}
		return $map;
	}

	public function test_every_section_row_uses_a_real_layout_and_fields(): void {
		$layouts = $this->layout_fields();

		foreach ( self::$data['pages'] as $slug => $page ) {
			foreach ( $page['sections'] ?? array() as $i => $row ) {
				$layout = $row['acf_fc_layout'] ?? '';
				$this->assertArrayHasKey( $layout, $layouts, "Page '{$slug}' section {$i}: unknown layout '{$layout}'." );

				foreach ( array_keys( $row ) as $key ) {
					if ( 'acf_fc_layout' === $key ) {
						continue;
					}
					$this->assertContains(
						$key,
						$layouts[ $layout ],
						"Page '{$slug}' layout '{$layout}': '{$key}' is not a sub-field of that layout."
					);
				}
			}
		}
	}

	public function test_every_cpt_field_is_registered_for_that_cpt(): void {
		$group_key = array(
			'service'     => 'group_salt_service',
			'testimonial' => 'group_salt_testimonial',
			'team_member' => 'group_salt_team_member',
			'work'        => 'group_salt_work',
			'faq'         => 'group_salt_faq',
			'location'    => 'group_salt_location',
		);

		foreach ( self::$data['cpts'] as $cpt => $items ) {
			if ( 'post' === $cpt || ! isset( $group_key[ $cpt ] ) ) {
				continue; // Core post uses no ACF fields.
			}
			$group = $this->group( $group_key[ $cpt ] );
			$this->assertNotNull( $group, "Field group {$group_key[$cpt]} must register." );
			$allowed = $this->top_level_names( $group['fields'] ?? array() );

			foreach ( $items as $item ) {
				foreach ( array_keys( $item['fields'] ?? array() ) as $field ) {
					$this->assertContains(
						$field,
						$allowed,
						"CPT '{$cpt}' item '{$item['slug']}': '{$field}' is not a registered field."
					);
				}
			}
		}
	}

	public function test_every_option_key_is_a_registered_option_field(): void {
		$allowed = array();
		foreach ( $GLOBALS['salt_test_acf_groups'] as $group ) {
			if ( str_starts_with( (string) ( $group['key'] ?? '' ), 'group_salt_options_' ) ) {
				$allowed = array_merge( $allowed, $this->flatten_option_names( $group['fields'] ?? array() ) );
			}
		}

		foreach ( array_keys( self::$data['options'] ) as $name ) {
			$this->assertContains(
				$name,
				$allowed,
				"Option '{$name}' is not a registered ACF option field."
			);
		}
	}

	/**
	 * Flatten option field names (fields + sub_fields; skip tabs).
	 *
	 * @param array<int,array<string,mixed>> $fields Fields.
	 * @return string[]
	 */
	private function flatten_option_names( array $fields ): array {
		$names = array();
		foreach ( $fields as $field ) {
			if ( isset( $field['name'] ) && '' !== $field['name'] ) {
				$names[] = (string) $field['name'];
			}
			if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				$names = array_merge( $names, $this->flatten_option_names( $field['sub_fields'] ) );
			}
		}
		return $names;
	}

	public function test_referenced_cpts_and_pages_exist_in_the_map(): void {
		// Every CPT item slug + page slug that could be a ref target.
		$known = array();
		foreach ( self::$data['cpts'] as $cpt => $items ) {
			foreach ( $items as $item ) {
				$known[ $cpt . '/' . $item['slug'] ] = true;
			}
		}
		foreach ( array_keys( self::$data['pages'] ) as $slug ) {
			$known[ 'page/' . $slug ] = true;
		}

		$this->assert_refs_resolvable( self::$data, $known );
	}

	/**
	 * Walk the map; assert every ref:/url: token to a post/page target exists.
	 * image/ and term/ and archive/ refs are created at runtime — skip those.
	 *
	 * @param mixed                 $value Value.
	 * @param array<string,bool>    $known Known ref targets.
	 * @return void
	 */
	private function assert_refs_resolvable( $value, array $known ): void {
		if ( is_string( $value ) && ( str_starts_with( $value, 'ref:' ) || str_starts_with( $value, 'url:' ) ) ) {
			$target = substr( $value, 4 );
			if ( str_starts_with( $target, 'image/' ) || str_starts_with( $target, 'term/' ) || str_starts_with( $target, 'archive/' ) ) {
				return;
			}
			$this->assertArrayHasKey( $target, $known, "Reference '{$value}' points at a target not in the content map." );
			return;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$this->assert_refs_resolvable( $item, $known );
			}
		}
	}
}
