<?php
/**
 * The contact form's real-request check, its site half (HARNESS ONLY, run
 * by tests/real-acf/contact.mjs through run.sh). The modes:
 *
 *   seed               a published page with two contact forms (anchors
 *                      `callback`, then `enquire`), a recipient, two enquiry
 *                      routes, an optional phone and submission storage;
 *                      prints the page's ID.
 *   permalinks <plain|pretty>  switches the site's permalinks, rewrite
 *                      rules flushed; prints the page's address.
 *   reset              empties the rate limit buckets (every request here
 *                      comes from one address) and the stored submissions.
 *   stored <name>      prints how many stored submissions carry that name.
 *   post               a published post (once); prints its address.
 *   clock <seconds>    moves the clock form tokens are minted by
 *                      (site/salt-harness-contact.php); 0 puts it back.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

$salt_contact_mode = (string) ( $argv[1] ?? '' );

if ( 'seed' === $salt_contact_mode ) {
	$salt_contact_page = (int) wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Contact check',
			'post_name'   => 'contact-check',
		)
	);
	update_field(
		'field_salt_sections_container',
		array(
			// A second form above it: a scriptless reply must bring the form
			// that was sent into view, not the first on the page.
			array(
				'acf_fc_layout' => 'contact',
				'heading'       => 'Request a call back',
				'settings'      => array( 'anchorId' => 'callback' ),
			),
			array(
				'acf_fc_layout' => 'contact',
				'heading'       => 'Start a project',
				'settings'      => array( 'anchorId' => 'enquire' ),
			),
		),
		$salt_contact_page
	);
	update_field( 'contact_recipient', 'enquiries@example.test', 'option' );
	update_field( 'contact_form_phone', 'optional', 'option' );
	update_field(
		'contact_routes',
		array(
			array(
				'enquiry_type' => 'Garden design',
				'recipient'    => '',
			),
			array(
				'enquiry_type' => 'Maintenance',
				'recipient'    => '',
			),
		),
		'option'
	);
	update_field( 'submissions_store', 1, 'option' );
	echo $salt_contact_page; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 0 );
}

if ( 'permalinks' === $salt_contact_mode ) {
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( 'pretty' === ( $argv[2] ?? '' ) ? '/%postname%/' : '' );
	flush_rewrite_rules();
	$salt_contact_page = get_page_by_path( 'contact-check' );
	echo esc_url_raw( (string) get_permalink( $salt_contact_page ) );
	exit( 0 );
}

if ( 'reset' === $salt_contact_mode ) {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%salt\\_rl\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The throwaway site.
	wp_cache_flush();
	foreach ( get_posts(
		array(
			'post_type'   => 'salt_submission',
			'post_status' => 'any',
			'numberposts' => -1,
		)
	) as $salt_contact_post ) {
		wp_delete_post( $salt_contact_post->ID, true );
	}
	exit( 0 );
}

if ( 'stored' === $salt_contact_mode ) {
	$salt_contact_count = 0;
	foreach ( get_posts(
		array(
			'post_type'   => 'salt_submission',
			'post_status' => 'any',
			'numberposts' => -1,
		)
	) as $salt_contact_post ) {
		$salt_contact_count += (string) get_post_meta( $salt_contact_post->ID, 'salt_name', true ) === (string) ( $argv[2] ?? '' ) ? 1 : 0;
	}
	echo (int) $salt_contact_count;
	exit( 0 );
}

if ( 'post' === $salt_contact_mode ) {
	$salt_contact_post = get_page_by_path( 'contact-check-post', OBJECT, 'post' );
	$salt_contact_id   = null !== $salt_contact_post ? $salt_contact_post->ID : wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Contact check post',
			'post_name'    => 'contact-check-post',
			'post_content' => '',
		)
	);
	echo esc_url_raw( (string) get_permalink( $salt_contact_id ) );
	exit( 0 );
}

if ( 'clock' === $salt_contact_mode ) {
	update_option( 'salt_harness_contact_clock', (int) ( $argv[2] ?? 0 ), false );
	exit( 0 );
}

fwrite( STDERR, "usage: contact-form.php seed|permalinks <plain|pretty>|reset|stored <name>|post|clock <seconds>\n" );
exit( 2 );
