<?php
/**
 * ACF content fields for the FAQ CPT (spec Appendix A.5).
 *
 * One field: the answer WYSIWYG — the question is the post title. The
 * answer renders into the native <details> accordion (§10 #13) and the
 * FAQPage JSON-LD (SEO03), always through salt_sanitize_html().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the FAQ content field group (module-gated).
 *
 * @return void
 */
function salt_register_faq_fields(): void {
	if ( ! salt_module_enabled( 'faq' ) ) {
		return;
	}

	salt_register_field_group(
		array(
			'key'      => 'group_salt_faq',
			'title'    => __( 'FAQ', 'salt' ),
			'fields'   => array(
				array(
					'key'          => 'field_salt_faq_answer',
					'name'         => 'answer',
					'label'        => __( 'Answer', 'salt' ),
					'type'         => 'wysiwyg',
					'tabs'         => 'visual',
					'toolbar'      => 'full',
					'media_upload' => 0,
					'required'     => 1,
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'faq',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'salt_register_faq_fields' );
