<?php
/**
 * Editors manage public copy on a separate screen, never through edit_user.
 *
 * @package Salt
 */

defined( 'ABSPATH' ) || exit;

// The native user form is also available to subscribers for their own account.
// Publication is editorial: neither drawing the fields nor submitting them is enough.
add_filter(
	'acf/prepare_field',
	static function ( $field ) {
		return str_starts_with( $field['key'] ?? '', 'field_salt_collection_team_' ) && ! current_user_can( 'edit_others_posts' ) ? false : $field;
	}
);
add_action(
	'acf/save_post',
	static function ( $object_id ) {
		if ( str_starts_with( (string) $object_id, 'user_' ) && ! current_user_can( 'edit_others_posts' ) ) {
			$fields = acf_get_fields( 'group_salt_collection_team' );
			foreach ( is_array( $fields ) ? $fields : array() as $field ) {
				unset( $_POST['acf'][ $field['key'] ] );
			}
		}
	},
	1
);

add_action(
	'admin_menu',
	static function () {
		if ( salt_module_enabled( 'team_member' ) && function_exists( 'acf_render_fields' ) ) {
			$page = add_management_page( __( 'Team profiles', 'salt' ), __( 'Team profiles', 'salt' ), 'edit_others_posts', 'salt-team', 'salt_team_editor' );
			add_action( 'load-' . $page, 'acf_enqueue_scripts' );
		}
	}
);

/** Choose an existing site member, then edit only their public fields. */
function salt_team_editor(): void {
	$id = absint( $_GET['member'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selection.
	echo '<div class="wrap"><h1>' . esc_html__( 'Team profiles', 'salt' ) . '</h1>';
	if ( ! $id || ! is_user_member_of_blog( $id ) ) {
		foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $user ) {
			printf( '<p><a href="%s">%s</a></p>', esc_url( add_query_arg( 'member', $user->ID ) ), esc_html( $user->display_name ) );
		}
	} else {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'salt_profile_' . $id );
		printf( '<input type="hidden" name="action" value="salt_profile"><input type="hidden" name="member" value="%d">', (int) $id );
		printf( '<label>%s <input required name="display_name" value="%s"></label>', esc_html__( 'Public name', 'salt' ), esc_attr( get_userdata( $id )->display_name ) );
		acf_render_fields( acf_get_fields( 'group_salt_collection_team' ), 'user_' . $id );
		submit_button();
		echo '</form>';
	}
	echo '</div>';
}

/** No posted account attributes, capabilities or arbitrary metadata are forwarded. */
function salt_save_team_profile(): void {
	$id = absint( $_POST['member'] ?? 0 );
	if ( ! current_user_can( 'edit_others_posts' ) || ! $id || ! is_user_member_of_blog( $id ) || ! salt_module_enabled( 'team_member' ) || ! function_exists( 'acf_get_fields' ) ) {
		wp_die( esc_html__( 'Not permitted.', 'salt' ), '', 403 );
	}
	check_admin_referer( 'salt_profile_' . $id );
	$name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
	if ( '' === $name ) {
		wp_die( esc_html__( 'A public name is required.', 'salt' ), '', 400 );
	}
	$values = array();
	foreach ( acf_get_fields( 'group_salt_collection_team' ) as $field ) {
		$key = $field['key'];
		if ( isset( $_POST['acf'][ $key ] ) ) {
			$value = wp_unslash( $_POST['acf'][ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ACF validates by field definition below.
			if ( ! acf_validate_value( $value, $field, 'acf[' . $key . ']' ) ) {
				wp_die( esc_html( $field['label'] . ': ' . __( 'Invalid value.', 'salt' ) ), '', 400 );
			}
			$values[ $key ] = $value;
		}
	}
	// Prevent profile_update listeners (including ACF) consuming other submitted fields.
	unset( $_POST['acf'] );
	wp_update_user(
		array(
			'ID'           => $id,
			'display_name' => $name,
		)
	);
	foreach ( $values as $key => $value ) {
		update_field( $key, $value, 'user_' . $id );
	}
	wp_safe_redirect( admin_url( 'tools.php?page=salt-team&member=' . $id ) );
	exit;
}
add_action( 'admin_post_salt_profile', 'salt_save_team_profile' );
