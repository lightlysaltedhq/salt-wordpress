<?php
/**
 * ACF Flexible Content loop shims (fixture-backed).
 *
 * Fixtures: $GLOBALS['salt_test_fc'] = array(
 *   'rows' => array( array( 'layout' => 'rich-text', 'fields' => array( 'heading' => '…' ) ), … ),
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

if ( ! function_exists( 'acf_add_options_page' ) ) {
	/**
	 * acf_add_options_page capture shim.
	 *
	 * @param array<string,mixed> $page Options page definition.
	 * @return void
	 */
	function acf_add_options_page( array $page ): void {
		$GLOBALS['salt_test_acf_pages'][] = $page;
	}
}

if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
	/**
	 * acf_add_options_sub_page capture shim.
	 *
	 * @param array<string,mixed> $page Options sub-page definition.
	 * @return void
	 */
	function acf_add_options_sub_page( array $page ): void {
		$GLOBALS['salt_test_acf_subpages'][] = $page;
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

/**
 * One row of a contract `buttons` list (contract field type link + style),
 * stored as the generated fields store it.
 *
 * @param string $label   Link label.
 * @param string $url     External address ('' for none).
 * @param string $style   primary|secondary|ghost.
 * @param bool   $new_tab Open in a new tab.
 * @return array<string,mixed>
 */
function salt_test_button( string $label, string $url, string $style = 'primary', bool $new_tab = false ): array {
	return array(
		'link'  => array(
			'label'  => $label,
			'type'   => 'external',
			'url'    => $url,
			'newTab' => $new_tab,
		),
		'style' => $style,
	);
}

/**
 * A registered page-sections layout by name (generated or hand-written).
 *
 * @param string $name Layout name.
 * @return array<string,mixed>
 */
function salt_test_layout( string $name ): array {
	foreach ( salt_section_layouts() as $layout ) {
		if ( $name === $layout['name'] ) {
			return $layout;
		}
	}
	throw new RuntimeException( esc_html( "layout {$name} is not registered" ) );
}

/**
 * The generated collection group the theme registers on acf/init for one
 * contract collection (services, case-studies, …), or null when none is
 * registered (its module is off).
 *
 * @param string $collection Contract collection id.
 * @return array<string,mixed>|null
 */
function salt_test_collection_group( string $collection ): ?array {
	$GLOBALS['salt_test_acf_groups'] = array();
	salt_register_collection_fields();
	foreach ( $GLOBALS['salt_test_acf_groups'] as $group ) {
		if ( ( $group['salt']['collection'] ?? null ) === $collection ) {
			return $group;
		}
	}
	return null;
}
