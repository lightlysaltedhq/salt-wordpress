<?php
/**
 * Site Settings → Header & Footer (page 4 of the setup walk).
 *
 * Site chrome behaviour. Menus themselves stay in Appearance → Menus
 * (locations: primary, footer) — the message field points there. All
 * fields NEW (additive).
 *
 * Runtime consumers: template-parts/header/site-header.php,
 * template-parts/footer/site-footer.php.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Header and Footer groups.
 *
 * @return void
 */
function salt_register_header_footer_options(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_header',
			'title'      => __( 'Header', 'salt' ),
			'menu_order' => 0,
			'fields'     => array(
				array(
					'key'     => 'field_salt_options_header_menus_note',
					'label'   => __( 'Navigation menus', 'salt' ),
					'type'    => 'message',
					'message' => __( 'Menus are managed under Appearance → Menus (locations: Primary, Footer). This page controls header behaviour only.', 'salt' ),
				),
				array(
					'key'           => 'field_salt_options_header_sticky',
					'name'          => 'header_sticky',
					'label'         => __( 'Sticky header', 'salt' ),
					'instructions'  => __( 'Keep the header pinned while scrolling. In-page anchor jumps are offset automatically so sections aren\'t hidden beneath it.', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
				),
				array(
					'key'          => 'field_salt_options_header_cta',
					'name'         => 'header_cta',
					'label'        => __( 'Header call-to-action (optional)', 'salt' ),
					'instructions' => __( 'A button at the end of the primary navigation. Leave empty for none.', 'salt' ),
					'type'         => 'link',
				),
			),
			'location'   => salt_options_location( 'salt-settings-header-footer' ),
		)
	);

	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_footer',
			'title'      => __( 'Footer', 'salt' ),
			'menu_order' => 10,
			'fields'     => array(
				array(
					'key'          => 'field_salt_options_footer_text',
					'name'         => 'footer_text',
					'label'        => __( 'Footer line', 'salt' ),
					'instructions' => __( 'Short line above the copyright — strapline or registered-office note. The © year and organisation name are added automatically from Company & Schema.', 'salt' ),
					'type'         => 'textarea',
					'rows'         => 2,
				),
				array(
					'key'           => 'field_salt_options_footer_show_social',
					'name'          => 'footer_show_social',
					'label'         => __( 'Show social icons', 'salt' ),
					'instructions'  => __( 'Icon links built from the profile URLs in Company & Schema → Profiles & Reviews.', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
				),
			),
			'location'   => salt_options_location( 'salt-settings-header-footer' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_header_footer_options' );
