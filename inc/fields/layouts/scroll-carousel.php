<?php
/**
 * Flexible Content layout: Scroll Carousel (spec §10 #18).
 *
 * Layout slug 'scroll_carousel' is a stability contract (FLEET07) —
 * renaming it is a MAJOR bump + slug-migrations.json entry. Content fields
 * first, then the shared §11 Advanced tab.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_scroll_carousel',
	'name'       => 'scroll_carousel',
	'label'      => __( 'Scroll Carousel', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_scroll_carousel_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_scroll_carousel_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'           => 'field_salt_scroll_carousel_card_type',
				'name'          => 'card_type',
				'label'         => __( 'Card type', 'salt' ),
				'type'          => 'select',
				'choices'       => array(
					'generic'     => __( 'Generic', 'salt' ),
					'testimonial' => __( 'Testimonial', 'salt' ),
				),
				'default_value' => 'generic',
				'wrapper'       => array( 'width' => '50' ),
			),
			array(
				'key'          => 'field_salt_scroll_carousel_cards',
				'name'         => 'cards',
				'label'        => __( 'Cards', 'salt' ),
				'type'         => 'repeater',
				'layout'       => 'block',
				'button_label' => __( 'Add card', 'salt' ),
				'sub_fields'   => array(
					array(
						'key'           => 'field_salt_scroll_carousel_cards_image',
						'name'          => 'image',
						'label'         => __( 'Image', 'salt' ),
						'instructions'  => __( 'Optional image or avatar.', 'salt' ),
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
					),
					array(
						'key'   => 'field_salt_scroll_carousel_cards_title',
						'name'  => 'title',
						'label' => __( 'Title', 'salt' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'field_salt_scroll_carousel_cards_text',
						'name'  => 'text',
						'label' => __( 'Text', 'salt' ),
						'type'  => 'textarea',
						'rows'  => 4,
					),
					array(
						'key'          => 'field_salt_scroll_carousel_cards_role',
						'name'         => 'role',
						'label'        => __( 'Role', 'salt' ),
						'instructions' => __( 'Optional — shown with testimonial cards.', 'salt' ),
						'type'         => 'text',
					),
				),
			),
		),
		salt_section_wrapper_fields( 'scroll_carousel' )
	),
);
