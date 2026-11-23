<?php
/**
 * Site Settings — page registrar ONLY (spec §13; approved v3 IA).
 *
 * One top-level menu + eight sub-pages ordered as the day-one setup walk:
 * identity → look → assets → chrome → case-study layout → conversion →
 * third parties → developer switches. Case Studies shows only with the
 * work module. Field groups live in inc/fields/options-{page}.php,
 * one file per sub-page, each targeting its sub-page slug.
 *
 * Values live in wp_options as options_{field_name} regardless of page —
 * page slugs and group locations are presentation, never storage (that is
 * why this restructure needs no migration; field NAMES/KEYS stay frozen,
 * FLEET07/DATA02).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Site Settings sub-pages, in menu order (pure — unit-testable).
 *
 * Array order IS the menu order (ACF renders sub-pages in registration
 * order). Adding a sub-page later = one entry here + a group file
 * targeting the slug. Slug convention: salt-settings-{scope}, matching
 * the group scope group_salt_options_{scope}.
 *
 * @return array<string,array{title: string}>
 */
function salt_options_subpages(): array {
	$pages = array(
		'salt-settings-company'       => array( 'title' => __( 'Company & Schema', 'salt' ) ),
		'salt-settings-brand'         => array( 'title' => __( 'Brand & Design', 'salt' ) ),
		'salt-settings-logo-fonts'    => array( 'title' => __( 'Logo & Fonts', 'salt' ) ),
		'salt-settings-header-footer' => array( 'title' => __( 'Header & Footer', 'salt' ) ),
		'salt-settings-case-studies'  => array( 'title' => __( 'Case Studies', 'salt' ) ),
		'salt-settings-contact'       => array( 'title' => __( 'Contact', 'salt' ) ),
		'salt-settings-integrations'  => array( 'title' => __( 'Integrations', 'salt' ) ),
		'salt-settings-advanced'      => array( 'title' => __( 'Advanced', 'salt' ) ),
	);
	// The case studies' layout, only on a site with case studies (the work module).
	if ( ! salt_module_enabled( 'work' ) ) {
		unset( $pages['salt-settings-case-studies'] );
	}
	return $pages;
}

/**
 * Register the Site Settings parent + sub-pages.
 *
 * redirect => true: the parent holds no groups; admin.php?page=salt-settings
 * lands on the first sub-page, so pre-v3 bookmarks keep working.
 *
 * @return void
 */
function salt_register_options_pages(): void {
	if ( ! function_exists( 'acf_add_options_page' ) || ! function_exists( 'acf_add_options_sub_page' ) ) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title' => __( 'Site Settings', 'salt' ),
			'menu_title' => __( 'Site Settings', 'salt' ),
			'menu_slug'  => 'salt-settings',
			'capability' => 'manage_options',
			'redirect'   => true,
		)
	);

	foreach ( salt_options_subpages() as $slug => $def ) {
		acf_add_options_sub_page(
			array(
				'page_title'  => $def['title'],
				'menu_title'  => $def['title'],
				'menu_slug'   => $slug,
				'parent_slug' => 'salt-settings',
				'capability'  => 'manage_options',
			)
		);
	}
}
add_action( 'acf/init', 'salt_register_options_pages' );

/**
 * Location rule targeting one Site Settings sub-page (registration helper).
 *
 * @param string $slug Sub-page slug.
 * @return array<int,array<int,array<string,string>>>
 */
function salt_options_location( string $slug ): array {
	return array(
		array(
			array(
				'param'    => 'options_page',
				'operator' => '==',
				'value'    => $slug,
			),
		),
	);
}
