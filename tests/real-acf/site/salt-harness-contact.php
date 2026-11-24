<?php
/**
 * Plugin Name: Salt harness: contact form clock and mail sink
 *
 * HARNESS ONLY. Never shipped. tests/real-acf/run.sh copies this file into
 * the throwaway site's wp-content/mu-plugins. It moves the clock the
 * contact form's tokens are minted by ('salt_contact_clock') by the
 * seconds in the salt_harness_contact_clock option, set by
 * tests/real-acf/php/contact-form.php (`clock <seconds>`), so contact.mjs
 * can hold a form token minted hours ago without waiting for it.
 *
 * @package Salt\Tests\RealAcf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'salt_contact_clock',
	static fn( int $now ): int => $now + (int) get_option( 'salt_harness_contact_clock', 0 )
);

// Never send mail from the throwaway site. Only the seeded enquiry recipient counts as a success.
add_filter(
	'pre_wp_mail',
	static fn( $result, array $mail ): bool => array( 'enquiries@example.test' ) === (array) $mail['to'],
	10,
	2
);
