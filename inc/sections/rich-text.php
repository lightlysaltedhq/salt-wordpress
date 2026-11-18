<?php
/**
 * Resolver: Rich Text (spec §10 #3) — the logic half of the section; the
 * view is template-parts/sections/rich-text.php. The §17 exemplar for the
 * logic/view split (docs/contracts/CONVENTIONS.md).
 *
 * Fields (contract/fields/rich-text.json): heading (text), body (WYSIWYG,
 * salt_allowed_html-constrained). eyebrow and alignment, which intro
 * folded in (ID-3599), are stored but not drawn yet (M4).
 * States (COMPL02): both blank => null (renders NOTHING, no band).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current rich-text Flexible Content row to its view props.
 *
 * Props (contract view-props/rich-text.json):
 *   heading  string|null  Contract `heading`. Null when blank.
 *   body     string|null  Contract `body` (rich text), from the `body`
 *                         field. Sanitised HTML (salt_sanitize_html()); null
 *                         when the stored content is blank.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_rich_text( array $context ): ?array {
	$heading = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';
	$content = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'body' ) : '';

	if ( '' === trim( $heading ) && '' === trim( $content ) ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'rich-text', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'heading' => salt_nullable_text( $heading ),
			// Blankness is decided on the STORED content (as before the split):
			// content that sanitises to nothing still draws its prose block.
			'body'    => '' !== trim( $content ) ? salt_sanitize_html( $content, salt_field_allowed( 'field_salt_rich_text_body' ) ) : null,
		),
	);
}
