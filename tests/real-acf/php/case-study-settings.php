<?php
/**
 * The Case Studies settings page's state, for the browser phase's admin
 * check (browser.mjs). HARNESS ONLY.
 *
 * Usage: php case-study-settings.php            (print the state as JSON)
 *        php case-study-settings.php --work 0|1 (turn the work module off or on)
 *
 * The state: whether the work module is on, whether the page's field group
 * is registered, what salt_case_study_settings() reads (the contract's
 * site.caseStudy) and the newest published case study's address.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

if ( '--work' === ( $argv[1] ?? '' ) ) {
	// The group registers on acf/init, which has run: the state is read in a fresh process.
	update_field( 'module_work', (int) ( $argv[2] ?? 1 ), 'option' );
	exit( 0 );
}

$salt_harness_work = get_posts(
	array(
		'post_type'   => 'work',
		'post_status' => 'publish',
		'numberposts' => 1,
		'fields'      => 'ids',
	)
);
echo wp_json_encode(
	array(
		'work'     => salt_module_enabled( 'work' ),
		'group'    => false !== acf_get_field_group( 'group_salt_options_case_studies' ),
		'settings' => salt_case_study_settings(),
		'stored'   => array(
			'header'   => get_option( 'options_case_study_header', null ),
			'tone'     => get_option( 'options_case_study_header_tone', null ),
			'toneDark' => get_option( 'options_case_study_header_tone_dark', null ),
			'media'    => get_option( 'options_case_study_media', null ),
		),
		'url'      => array() !== $salt_harness_work ? (string) get_permalink( (int) $salt_harness_work[0] ) : null,
	)
);
