<?php
/**
 * Site Settings → Case Studies: the case-study view's layout, which a site
 * sets once for all its case studies (salt-contract rc.8 `site.caseStudy`,
 * markup/case-study.json variants). Hand-written: the contract's ACF
 * emitter covers no options page.
 *
 *  - case_study_header: plain (the default) or a band, a toned section
 *    band across the page;
 *  - case_study_header_tone and case_study_header_tone_dark: the band's
 *    tone and its dark-mode tone (the section tones; automatic is the tone
 *    itself), shown only for the band;
 *  - case_study_media: the featured image in the flow (the default) or
 *    aside, beside the challenge, solution, gallery and live-site link.
 *
 * Registered with the work module (case studies). Runtime consumer:
 * salt_case_study_settings() (inc/services/views.php), read by
 * single-work.php.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Case Studies group.
 *
 * @return void
 */
function salt_register_case_studies_options(): void {
	if ( ! salt_module_enabled( 'work' ) ) {
		return;
	}
	$tones = array(
		'surface'         => __( 'Surface (the page background)', 'salt' ),
		'surface-alt'     => __( 'Surface, alternate (a subtle step away from it)', 'salt' ),
		'brand-tint'      => __( 'Brand tint', 'salt' ),
		'surface-inverse' => __( 'Inverse (light on dark)', 'salt' ),
	);
	$band  = array(
		array(
			array(
				'field'    => 'field_salt_options_case_study_header',
				'operator' => '==',
				'value'    => 'band',
			),
		),
	);

	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_case_studies',
			'title'      => __( 'Case study layout', 'salt' ),
			'menu_order' => 0,
			'fields'     => array(
				array(
					'key'           => 'field_salt_options_case_study_header',
					'name'          => 'case_study_header',
					'label'         => __( 'Header', 'salt' ),
					'instructions'  => __( 'How every case study\'s header is drawn: plain, on the page, or as a coloured band across the page.', 'salt' ),
					'type'          => 'button_group',
					'choices'       => array(
						'plain' => __( 'Plain', 'salt' ),
						'band'  => __( 'Band', 'salt' ),
					),
					'default_value' => 'plain',
					'return_format' => 'value',
				),
				array(
					'key'               => 'field_salt_options_case_study_header_tone',
					'name'              => 'case_study_header_tone',
					'label'             => __( 'Band tone', 'salt' ),
					'instructions'      => __( 'The band\'s background and text colour roles.', 'salt' ),
					'type'              => 'select',
					'choices'           => $tones,
					'default_value'     => 'surface',
					'conditional_logic' => $band,
				),
				array(
					'key'               => 'field_salt_options_case_study_header_tone_dark',
					'name'              => 'case_study_header_tone_dark',
					'label'             => __( 'Band tone in dark mode', 'salt' ),
					'instructions'      => __( 'Automatic keeps the tone above, and the dark palette draws it in its dark form.', 'salt' ),
					'type'              => 'select',
					'choices'           => array( 'auto' => __( 'Automatic (the tone above, in its dark form)', 'salt' ) ) + $tones,
					'default_value'     => 'auto',
					'conditional_logic' => $band,
				),
				array(
					'key'           => 'field_salt_options_case_study_media',
					'name'          => 'case_study_media',
					'label'         => __( 'Featured image', 'salt' ),
					'instructions'  => __( 'In the flow, the image runs across the page under the header. Aside, it sits beside the challenge, solution, gallery and live-site link on wide screens.', 'salt' ),
					'type'          => 'button_group',
					'choices'       => array(
						'flow'  => __( 'In the flow', 'salt' ),
						'aside' => __( 'Aside', 'salt' ),
					),
					'default_value' => 'flow',
					'return_format' => 'value',
				),
			),
			'location'   => salt_options_location( 'salt-settings-case-studies' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_case_studies_options' );
