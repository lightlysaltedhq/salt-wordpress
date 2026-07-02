<?php
/**
 * Contact form presentation options (PRIV06 code-half: the privacy-notice
 * link rendered at the point of collection).
 *
 * Delivery/routing options live in inc/fields/options-contact.php (the
 * delivery service's group).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the contact-form options group.
 *
 * @return void
 */
function salt_register_contact_form_options(): void {
	salt_register_field_group(
		array(
			'key'      => 'group_salt_options_contact_form',
			'title'    => __( 'Contact form', 'salt' ),
			'fields'   => array(
				array(
					'key'           => 'field_salt_options_contact_form_privacy_page',
					'name'          => 'contact_privacy_page',
					'label'         => __( 'Privacy policy page', 'salt' ),
					'instructions'  => __( 'Linked from the contact form (“see our privacy policy”). Transparency at the point of collection — strongly recommended. Policy CONTENT is the site’s responsibility (see docs/PRIVACY-RESPONSIBILITIES.md).', 'salt' ),
					'type'          => 'post_object',
					'post_type'     => array( 'page' ),
					'return_format' => 'id',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'salt-settings',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'salt_register_contact_form_options' );
