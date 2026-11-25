<?php
/**
 * Resolver: Tabs (salt-contract 1.0.0). Native radios and salt.css provide
 * the interaction; the view draws only these props, with no script.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current row. A panel needs both a label and usable content.
 *
 * @param array<string,mixed> $context Page plan context.
 * @return array|null Wrapper and view props, or the empty state.
 */
function salt_resolve_section_tabs( array $context ): ?array {
	$sub     = static fn( string $field ): string => function_exists( 'get_sub_field' ) && is_string( get_sub_field( $field ) ) ? (string) get_sub_field( $field ) : '';
	$eyebrow = salt_nullable_text( $sub( 'eyebrow' ) );
	$heading = salt_nullable_text( $sub( 'heading' ) );
	$intro   = salt_nullable_text( $sub( 'intro' ) );
	$label   = salt_nullable_text( $sub( 'label' ) );
	$rows    = function_exists( 'get_sub_field' ) ? get_sub_field( 'tabs' ) : array();
	$tabs    = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$title   = is_string( $row['label'] ?? null ) ? salt_nullable_text( wp_strip_all_tags( $row['label'] ) ) : null;
		$content = salt_sanitize_html( is_string( $row['content'] ?? null ) ? $row['content'] : '', salt_field_allowed( 'field_salt_tabs_tabs_content' ) );
		if ( null === $title || ! salt_rich_text_has_words( $content ) ) {
			continue;
		}
		$tabs[] = array(
			'id'      => (string) ( count( $tabs ) + 1 ),
			'label'   => $title,
			'content' => $content,
		);
	}
	if ( null === $eyebrow && null === $heading && null === $intro && array() === $tabs ) {
		return null;
	}
	return array(
		'section' => array_merge( salt_section_contract_args( 'tabs', $context ), array( 'has_heading' => null !== $heading ) ),
		'props'   => array(
			'eyebrow' => $eyebrow,
			'heading' => $heading,
			'intro'   => $intro,
			'label'   => $label,
			'named'   => null !== $heading || null !== $label,
			'tabs'    => $tabs,
		),
	);
}
