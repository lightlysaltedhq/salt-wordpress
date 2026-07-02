<?php
/**
 * ACF content fields for the team_member CPT (Appendix A.3).
 *
 * Name = post title, headshot = featured image, full bio = editor; the
 * fields here are the card-level extras: role, short_bio, email, phone and
 * the socials repeater (platform + url).
 *
 * Keys follow field_salt_team_member_{name} (DATA02). Gated by the
 * 'team_member' module flag (§10b).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the team_member field group.
 *
 * @return void
 */
function salt_register_team_member_fields(): void {
	if ( ! salt_module_enabled( 'team_member' ) ) {
		return;
	}

	salt_register_field_group(
		array(
			'key'      => 'group_salt_team_member',
			'title'    => __( 'Team member details', 'salt' ),
			'fields'   => array(
				array(
					'key'   => 'field_salt_team_member_role',
					'name'  => 'role',
					'label' => __( 'Role / title', 'salt' ),
					'type'  => 'text',
				),
				array(
					'key'          => 'field_salt_team_member_short_bio',
					'name'         => 'short_bio',
					'label'        => __( 'Short bio', 'salt' ),
					'instructions' => __( 'Card blurb — the full bio lives in the main editor.', 'salt' ),
					'type'         => 'textarea',
					'rows'         => 3,
					'new_lines'    => '',
				),
				array(
					'key'   => 'field_salt_team_member_email',
					'name'  => 'email',
					'label' => __( 'Email', 'salt' ),
					'type'  => 'email',
				),
				array(
					'key'   => 'field_salt_team_member_phone',
					'name'  => 'phone',
					'label' => __( 'Phone', 'salt' ),
					'type'  => 'text',
				),
				array(
					'key'          => 'field_salt_team_member_socials',
					'name'         => 'socials',
					'label'        => __( 'Social links', 'salt' ),
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => __( 'Add social link', 'salt' ),
					'sub_fields'   => array(
						array(
							'key'           => 'field_salt_team_member_socials_platform',
							'name'          => 'platform',
							'label'         => __( 'Platform', 'salt' ),
							'type'          => 'select',
							'choices'       => array(
								'linkedin' => __( 'LinkedIn', 'salt' ),
								'x'        => __( 'X', 'salt' ),
								'other'    => __( 'Other', 'salt' ),
							),
							'default_value' => 'linkedin',
						),
						array(
							'key'   => 'field_salt_team_member_socials_url',
							'name'  => 'url',
							'label' => __( 'URL', 'salt' ),
							'type'  => 'url',
						),
					),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'team_member',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'salt_register_team_member_fields' );
