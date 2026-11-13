<?php
/**
 * Resolver: Intro / Lead (spec §10 #2) — the logic half of the section;
 * the view is template-parts/sections/intro.php.
 *
 * Fields: eyebrow, heading, content (WYSIWYG, salt_allowed_html-
 * constrained), align (left|center).
 *
 * STATES (COMPL02): heading AND content both empty => null (renders
 * NOTHING; an eyebrow alone is not a section). The body sits in a narrow
 * reading measure (max-w-prose); align 'center' centres the measure + text.
 * No schema, no JS.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current intro Flexible Content row to its view props.
 *
 * Intro is WordPress-local: the contract folds it into rich-text, which
 * this work item does not do, so props keep snake_case local names and
 * take a contract name only where the meaning is identical.
 *
 * Props:
 *   eyebrow  string|null  Shared name `eyebrow` (rich-text.json has no eyebrow;
 *                         same meaning as in hero/faq). Null when blank.
 *   heading  string|null  Contract `heading` (rich-text.json). Null when blank.
 *   content  string|null  WordPress-local (rich-text.json calls it `body`).
 *                         Sanitised HTML (salt_sanitize_html()); null when
 *                         the stored content is blank.
 *   align    string       WordPress-local: 'left'|'center' (unknown => 'left').
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_intro( array $context ): ?array {
	$eyebrow = (string) get_sub_field( 'eyebrow' );
	$heading = (string) get_sub_field( 'heading' );
	$content = (string) get_sub_field( 'content' );
	$align   = (string) get_sub_field( 'align' );

	if ( '' === trim( $heading ) && '' === trim( $content ) ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	if ( ! in_array( $align, array( 'left', 'center' ), true ) ) {
		if ( '' !== $align ) {
			salt_log( sprintf( 'section intro: unknown align "%s" — using left.', $align ) );
		}
		$align = 'left';
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'intro', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'eyebrow' => salt_nullable_text( $eyebrow ),
			'heading' => salt_nullable_text( $heading ),
			// Blankness is decided on the STORED content (as before the split):
			// content that sanitises to nothing still draws its prose block.
			'content' => '' !== trim( $content ) ? salt_sanitize_html( $content ) : null,
			'align'   => $align,
		),
	);
}
