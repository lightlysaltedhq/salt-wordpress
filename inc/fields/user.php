<?php
/**
 * ACF user-profile fields (blog single build-out).
 *
 * profile_photo backs the x-author-box avatar on single posts. There is
 * no Gravatar fallback (salt-contract 1.0.0-rc.7, collections/team.json).
 *
 * Keys follow field_salt_user_{name} (DATA02).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the user profile field group.
 *
 * @return void
 */
function salt_register_user_fields(): void {
	salt_register_field_group(
		array(
			'key'      => 'group_salt_user',
			'title'    => __( 'Author profile', 'salt' ),
			'fields'   => array(
				array(
					'key'           => 'field_salt_user_profile_photo',
					'name'          => 'profile_photo',
					'label'         => __( 'Profile photo', 'salt' ),
					'instructions'  => __( 'Shown in the post author box and byline; without one, no picture is shown. Square crop works best.', 'salt' ),
					'type'          => 'image',
					'return_format' => 'id',
					'library'       => 'all',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'user_form',
						'operator' => '==',
						'value'    => 'all',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'salt_register_user_fields' );
