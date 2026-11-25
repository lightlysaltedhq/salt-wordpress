<?php
/**
 * The design dials check's site half (HARNESS ONLY, run by
 * tests/real-acf/dials.mjs through run.sh). The modes:
 *
 *   seed            an Editor (harness-dials-editor, SALT_HARNESS_EDITOR_PASS)
 *                   and a published page drawing cards (features), buttons
 *                   (call to action) and sections at every spacing; prints
 *                   {"url": …} as JSON.
 *   set <id> <val>  stores one dial's choice ('' clears it).
 *   state           prints each dial's stored option as JSON.
 *   restore         clears every dial, deletes the page and the Editor.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';
require __DIR__ . '/seed.php';

$salt_dials_mode = (string) ( $argv[1] ?? '' );

if ( 'seed' === $salt_dials_mode ) {
	wp_insert_user(
		array(
			'user_login' => 'harness-dials-editor',
			'user_pass'  => (string) getenv( 'SALT_HARNESS_EDITOR_PASS' ),
			'user_email' => 'dials-editor@salt-harness.test',
			'role'       => 'editor',
		)
	);
	$salt_dials_layouts = array();
	foreach ( acf_get_field( 'field_salt_sections_container' )['layouts'] as $salt_dials_layout ) {
		$salt_dials_layouts[ $salt_dials_layout['name'] ] = $salt_dials_layout;
	}
	$salt_dials_rows = array();
	foreach ( array(
		'features/many-items-linked'              => 'sm',
		'call-to-action/heading-body-two-buttons' => 'md',
		'features/four-columns-inverse'           => 'lg',
	) as $salt_dials_name => $salt_dials_spacing ) {
		$salt_dials_case                          = salt_harness_fixture( $salt_dials_name );
		$GLOBALS['salt_harness_media']            = $salt_dials_case['media'] ?? array();
		$salt_dials_values                        = $salt_dials_case['values'];
		$salt_dials_values['settings']['spacing'] = $salt_dials_spacing;
		$salt_dials_rows[]                        = array( 'acf_fc_layout' => $salt_dials_case['section'] ) + salt_harness_to_acf(
			$salt_dials_values,
			array(
				'type'       => 'group',
				'sub_fields' => $salt_dials_layouts[ $salt_dials_case['section'] ]['sub_fields'],
			)
		);
	}
	$salt_dials_page = (int) wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Dials check',
		)
	);
	update_field( 'field_salt_sections_container', $salt_dials_rows, $salt_dials_page );
	echo wp_json_encode( array( 'url' => (string) get_permalink( $salt_dials_page ) ) );
	exit( 0 );
}

if ( 'set' === $salt_dials_mode ) {
	update_field( salt_design_dials()[ (string) $argv[2] ]['field'], (string) ( $argv[3] ?? '' ), 'option' );
	exit( 0 );
}

if ( 'state' === $salt_dials_mode ) {
	$salt_dials_state = array();
	foreach ( salt_design_dials() as $salt_dials_id => $salt_dials_dial ) {
		$salt_dials_state[ $salt_dials_id ] = get_option( 'options_' . $salt_dials_dial['field'], null );
	}
	echo wp_json_encode( $salt_dials_state );
	exit( 0 );
}

// What real ACF does with the dials for the Editor: whether it would draw
// each field, and what a save that names them stores (ACF's own save, after
// its nonce check, with the dials posted).
if ( 'as-editor' === $salt_dials_mode ) {
	wp_set_current_user( get_user_by( 'login', 'harness-dials-editor' )->ID );
	$salt_dials_out = array(
		'can'   => current_user_can( 'manage_options' ),
		'drawn' => array(),
	);
	$_POST['acf']   = array();
	foreach ( salt_design_dials() as $salt_dials_id => $salt_dials_dial ) {
		$salt_dials_field                          = acf_get_field( 'field_salt_options_brand_' . $salt_dials_dial['field'] );
		$salt_dials_out['drawn'][ $salt_dials_id ] = false !== acf_prepare_field( $salt_dials_field );
		$_POST['acf'][ $salt_dials_field['key'] ]  = array_key_last( $salt_dials_dial['options'] );
	}
	$_POST['acf']['field_salt_options_brand_dark_mode'] = 1;
	do_action( 'acf/save_post', 'options' );
	$salt_dials_out['dark_mode_saved'] = (bool) get_option( 'options_dark_mode' );
	delete_field( 'dark_mode', 'option' );
	echo wp_json_encode( $salt_dials_out );
	exit( 0 );
}

if ( 'restore' === $salt_dials_mode ) {
	foreach ( salt_design_dials() as $salt_dials_dial ) {
		delete_field( $salt_dials_dial['field'], 'option' );
	}
	foreach ( get_posts( array( 'post_type' => 'page', 'title' => 'Dials check', 'post_status' => 'any', 'fields' => 'ids' ) ) as $salt_dials_id ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Harness one-liner.
		wp_delete_post( (int) $salt_dials_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	$salt_dials_user = get_user_by( 'login', 'harness-dials-editor' );
	if ( $salt_dials_user ) {
		wp_delete_user( $salt_dials_user->ID );
	}
	exit( 0 );
}

fwrite( STDERR, "usage: dials.php seed|set <id> <value>|state|restore\n" );
exit( 2 );
