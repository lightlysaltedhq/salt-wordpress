<?php
/**
 * Section: Rich Text (spec §10 #3) — the §17 exemplar section.
 *
 * Fields: heading (text), content (WYSIWYG, salt_allowed_html-constrained).
 * States (COMPL02): both empty => renders NOTHING (skip). Heading renders
 * at the level the wrapper derives (H1 only when this is the designated
 * primary section).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_heading = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';
$salt_content = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'content' ) : '';

if ( '' === trim( $salt_heading ) && '' === trim( $salt_content ) ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'rich-text', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_heading, $salt_content ): void {
		if ( '' !== trim( $salt_heading ) ) {
			salt_component(
				'heading',
				array(
					'text'    => $salt_heading,
					'level'   => $level,
					'classes' => 'mb-6',
				)
			);
		}
		if ( '' !== trim( $salt_content ) ) {
			printf(
				'<div class="x-prose max-w-prose text-ink">%s</div>',
				salt_sanitize_html( $salt_content ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constrained by salt_allowed_html() (§16a), the named sanitisation artifact.
			);
		}
	}
);
