<?php
/**
 * Plugin Name: Salt harness: a case's chrome
 *
 * HARNESS ONLY. Never shipped. tests/real-acf/run.sh copies this file into
 * the throwaway site's wp-content/mu-plugins. A view or page case's chrome
 * data that the theme takes through code seams, not Site Settings (labels,
 * the footer's copyright and profiles, and a consent plugin with its
 * categories in use), cannot be
 * set from view.php, which runs in another process than the request it
 * makes: view.php stores it in the salt_harness_chrome option
 * (salt_view_chrome()), and this feeds the theme from it, as
 * tests/contract-adapter/adapter.php does for the chrome cases. The Site
 * Settings (theme toggle, phone, footer tone and logo) view.php seeds as
 * options (salt_view_site()).
 *
 * @package Salt\Tests\RealAcf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_harness_chrome = get_option( 'salt_harness_chrome' );
if ( ! is_array( $salt_harness_chrome ) ) {
	return;
}

// The case's words; only text labels (a case may carry structured ones the
// chrome does not take).
$salt_harness_labels = array_filter( (array) ( $salt_harness_chrome['labels'] ?? array() ), 'is_string' );
if ( array() !== $salt_harness_labels ) {
	add_filter( 'salt_contract_labels', static fn( $labels ) => array_merge( (array) $labels, $salt_harness_labels ), 20 );
}
if ( null !== ( $salt_harness_chrome['copyright'] ?? null ) ) {
	add_filter( 'salt_footer_copyright', static fn() => (string) $salt_harness_chrome['copyright'], 20 );
}
if ( null !== ( $salt_harness_chrome['profiles'] ?? null ) ) {
	add_filter(
		'salt_footer_social_links',
		static fn() => array_map(
			static fn( array $profile ): array => array(
				'url'   => (string) $profile['url'],
				'icon'  => (string) ( $profile['icon'] ?? '' ),
				'label' => (string) $profile['name'],
			),
			(array) $salt_harness_chrome['profiles']
		),
		20
	);
}

// Founder ruling C, SC-030: the footer draws its consent control only when
// an integration needs consent and a cookie plugin is wired to the WP
// Consent API. A case's site.consent says one is: the API's server
// functions with an opt-in type (no consent is recorded, so
// wp_has_consent() denies), the Salt categories its boxes cover as the ones
// in use, and its words. The theme groups the boxes itself.
$salt_harness_consent = $salt_harness_chrome['consent'] ?? null;
if ( is_array( $salt_harness_consent ) ) {
	if ( ! function_exists( 'wp_get_consent_type' ) ) {
		/**
		 * The harness's consent plugin: opt-in.
		 *
		 * @return string
		 */
		function wp_get_consent_type() {
			return 'optin';
		}

		/**
		 * The harness's consent plugin: nothing is consented.
		 *
		 * @param string $category WP Consent API category.
		 * @return bool
		 */
		function wp_has_consent( $category ) {
			unset( $category );
			return false;
		}
	}
	$salt_harness_boxes  = (array) ( $salt_harness_consent['categories'] ?? array() );
	$salt_harness_in_use = array();
	foreach ( $salt_harness_boxes as $salt_harness_box ) {
		$salt_harness_in_use = array_merge( $salt_harness_in_use, (array) ( $salt_harness_box['covers'] ?? array() ) );
	}
	add_filter( 'salt_consent_categories_in_use', static fn() => $salt_harness_in_use, 20 );
	add_filter(
		'salt_consent_panel',
		static fn() => array(
			'labels'     => (array) ( $salt_harness_consent['labels'] ?? array() ),
			'categories' => $salt_harness_boxes,
		),
		20
	);
}
