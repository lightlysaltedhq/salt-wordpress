<?php
/**
 * No two storage paths in one layout differ only in case.
 *
 * ACF stores a Flexible Content row's values as post meta named by path
 * (sections_0_settings_anchorId). MariaDB and MySQL compare meta_key with a
 * case-insensitive collation, so sections_0_settings_anchorId and
 * sections_0_settings_anchorid would read and overwrite each other. This
 * walks every field group the theme registers on acf/init and, per layout
 * (and per group without layouts), lower-cases every storage path, with a
 * repeater's rows written as N, and fails on any two that collide.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

require_once dirname( __DIR__, 2 ) . '/inc/fields/options.php';
$salt_case_field_files = glob( dirname( __DIR__, 2 ) . '/inc/fields/*.php' );
foreach ( false === $salt_case_field_files ? array() : $salt_case_field_files as $salt_case_field_file ) {
	require_once $salt_case_field_file;
}
unset( $salt_case_field_files, $salt_case_field_file );

$GLOBALS['salt_case_acf_init_snapshot'] = $GLOBALS['salt_test_hooks']['acf/init'] ?? array();

final class StoragePathCaseTest extends SaltTestCase {

	/**
	 * Every registered group, as acf/init registers it.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function registered_groups(): array {
		salt_generated_sections_group( true );
		$GLOBALS['salt_test_acf_groups']        = array();
		$GLOBALS['salt_test_hooks']['acf/init'] = $GLOBALS['salt_case_acf_init_snapshot'];
		salt_test_do_action( 'acf/init' );
		return $GLOBALS['salt_test_acf_groups'];
	}

	/**
	 * Storage path templates under a field list (repeater rows as N).
	 *
	 * @param array<int,array<string,mixed>> $fields Fields.
	 * @param string                         $prefix Path so far ('' at a group's top).
	 * @return string[]
	 */
	private static function paths( array $fields, string $prefix ): array {
		$out = array();
		foreach ( $fields as $field ) {
			$name = (string) ( $field['name'] ?? '' );
			if ( '' === $name ) {
				continue; // A tab or message stores nothing.
			}
			$path  = '' === $prefix ? $name : $prefix . '_' . $name;
			$out[] = $path;
			$below = 'repeater' === ( $field['type'] ?? '' ) ? $path . '_N' : $path;
			$out   = array_merge( $out, self::paths( $field['sub_fields'] ?? array(), $below ) );
		}
		return $out;
	}

	/**
	 * Case-only collisions per layout of every group: scope => colliding paths.
	 *
	 * @param array<int,array<string,mixed>> $groups Field groups.
	 * @return array<string,array<int,string[]>>
	 */
	public static function collisions( array $groups ): array {
		$scopes = array();
		foreach ( $groups as $group ) {
			$key = (string) ( $group['key'] ?? '?' );
			$top = array();
			foreach ( $group['fields'] ?? array() as $field ) {
				if ( 'flexible_content' === ( $field['type'] ?? '' ) ) {
					foreach ( $field['layouts'] ?? array() as $layout ) {
						$scopes[ $key . ' / ' . $layout['name'] ] = self::paths( $layout['sub_fields'] ?? array(), $field['name'] . '_N' );
					}
				} else {
					$top[] = $field;
				}
			}
			$scopes[ $key ] = self::paths( $top, '' );
		}

		$found = array();
		foreach ( $scopes as $scope => $paths ) {
			$lower = array();
			foreach ( array_unique( $paths ) as $path ) {
				$lower[ strtolower( $path ) ][] = $path;
			}
			foreach ( $lower as $set ) {
				if ( count( $set ) > 1 ) {
					$found[ $scope ][] = $set;
				}
			}
		}
		return $found;
	}

	public function test_no_registered_layout_has_storage_paths_that_differ_only_in_case(): void {
		$groups = $this->registered_groups();
		$this->assertNotEmpty( $groups );
		$this->assertContains( 'group_salt_sections', array_column( $groups, 'key' ) );

		$this->assertSame( array(), self::collisions( $groups ), 'MariaDB matches meta_key case-insensitively.' );
	}

	public function test_a_planted_anchorid_beside_anchor_id_fails(): void {
		$groups = $this->registered_groups();
		foreach ( $groups as $g => $group ) {
			if ( 'group_salt_sections' !== $group['key'] ) {
				continue;
			}
			foreach ( $group['fields'][0]['layouts'] as $l => $layout ) {
				if ( 'hero' !== $layout['name'] ) {
					continue;
				}
				foreach ( $layout['sub_fields'] as $f => $field ) {
					if ( 'settings' === $field['name'] ) {
						$groups[ $g ]['fields'][0]['layouts'][ $l ]['sub_fields'][ $f ]['sub_fields'][] = array(
							'key'  => 'field_salt_hero_settings_anchorid_planted',
							'name' => 'anchorid',
							'type' => 'text',
						);
					}
				}
			}
		}

		$this->assertSame(
			array( 'group_salt_sections / hero' => array( array( 'sections_N_settings_anchorId', 'sections_N_settings_anchorid' ) ) ),
			self::collisions( $groups )
		);
	}
}
