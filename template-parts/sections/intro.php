<?php
/**
 * Section: Intro / Lead (spec §10 #2) — inline.
 *
 * Fields: eyebrow, heading, content (WYSIWYG, salt_allowed_html-
 * constrained), align (left|center).
 *
 * STATES (COMPL02): heading AND content both empty ⇒ renders NOTHING
 * (an eyebrow alone is not a section). The body sits in a narrow reading
 * measure (max-w-prose); align 'center' centres the measure + text.
 * No schema, no JS.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_eyebrow = (string) get_sub_field( 'eyebrow' );
$salt_heading = (string) get_sub_field( 'heading' );
$salt_content = (string) get_sub_field( 'content' );
$salt_align   = (string) get_sub_field( 'align' );

if ( '' === trim( $salt_heading ) && '' === trim( $salt_content ) ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

if ( ! in_array( $salt_align, array( 'left', 'center' ), true ) ) {
	if ( '' !== $salt_align ) {
		salt_log( sprintf( 'section intro: unknown align "%s" — using left.', $salt_align ) );
	}
	$salt_align = 'left';
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'intro', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_eyebrow, $salt_heading, $salt_content, $salt_align ): void {
		// Narrow reading measure; centred variant centres measure + text.
		$salt_classes = 'x-intro max-w-prose';
		if ( 'center' === $salt_align ) {
			$salt_classes .= ' mx-auto text-center';
		}

		printf( '<div class="%s">', esc_attr( $salt_classes ) );
		if ( '' !== trim( $salt_eyebrow ) ) {
			salt_component(
				'eyebrow',
				array(
					'text'    => $salt_eyebrow,
					'classes' => 'mb-3',
				)
			);
		}
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
				'<div class="x-prose text-lg text-ink">%s</div>',
				salt_sanitize_html( $salt_content ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constrained by salt_allowed_html() (§16a), the named sanitisation artifact.
			);
		}
		echo '</div>';
	}
);
