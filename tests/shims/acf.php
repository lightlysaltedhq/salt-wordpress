<?php
/**
 * ACF Flexible Content loop shims (fixture-backed).
 *
 * Fixtures: $GLOBALS['salt_test_fc'] = array(
 *   'rows' => array( array( 'layout' => 'rich_text', 'fields' => array( 'heading' => '…' ) ), … ),
 *   'i'    => -1,
 * );
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	/**
	 * acf_add_local_field_group capture shim — its presence makes
	 * salt_acf_active() true suite-wide (the "ACF active" environment).
	 *
	 * @param array<string,mixed> $field_group Group definition.
	 * @return void
	 */
	function acf_add_local_field_group( array $field_group ): void {
		$GLOBALS['salt_test_acf_groups'][] = $field_group;
	}
}

if ( ! function_exists( 'have_rows' ) ) {
	/**
	 * have_rows shim.
	 *
	 * @param string $selector Field selector.
	 * @return bool
	 */
	function have_rows( string $selector ): bool {
		unset( $selector );
		$fc = $GLOBALS['salt_test_fc'] ?? array(
			'rows' => array(),
			'i'    => -1,
		);
		return ( $fc['i'] + 1 ) < count( $fc['rows'] );
	}
}

if ( ! function_exists( 'the_row' ) ) {
	/**
	 * the_row shim — advances the row pointer.
	 *
	 * @return void
	 */
	function the_row(): void {
		++$GLOBALS['salt_test_fc']['i'];
	}
}

if ( ! function_exists( 'get_row_layout' ) ) {
	/**
	 * get_row_layout shim.
	 *
	 * @return string
	 */
	function get_row_layout(): string {
		$fc = $GLOBALS['salt_test_fc'];
		return (string) ( $fc['rows'][ $fc['i'] ]['layout'] ?? '' );
	}
}

if ( ! function_exists( 'get_sub_field' ) ) {
	/**
	 * get_sub_field shim — reads the current row's fixture fields.
	 *
	 * @param string $selector Field name.
	 * @return mixed
	 */
	function get_sub_field( string $selector ) {
		$fc = $GLOBALS['salt_test_fc'] ?? null;
		if ( null === $fc || $fc['i'] < 0 ) {
			return null;
		}
		return $fc['rows'][ $fc['i'] ]['fields'][ $selector ] ?? null;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * get_the_title shim.
	 *
	 * @return string
	 */
	function get_the_title(): string {
		return (string) ( $GLOBALS['salt_test_title'] ?? 'Fixture page title' );
	}
}

/**
 * Seed the Flexible Content fixture rows.
 *
 * @param array<int,array<string,mixed>> $rows Rows: layout + fields.
 * @return void
 */
function salt_test_seed_fc( array $rows ): void {
	$GLOBALS['salt_test_fc'] = array(
		'rows' => $rows,
		'i'    => -1,
	);
}
