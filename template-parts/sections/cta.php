<?php
/**
 * Section: Call to Action (spec §10 #10) — thin composition over x-cta.
 *
 * Fields: heading (text), text (textarea), buttons (repeater, max 2:
 * label, url, style primary|secondary|ghost). Background defaults to
 * 'brand-tint' when the editor leaves the shared wrapper control unset.
 *
 * States (COMPL02): heading, text AND valid buttons all empty => renders
 * NOTHING (skip before the wrapper). Malformed button rows are skipped and
 * logged; anything beyond the first two valid buttons is dropped + logged.
 * No JS, no schema.
 *
 * DEFERRED (compliance phase): the spec's 'newsletter' variant — no email
 * input may ship while nothing can process the submission (PRIV01
 * posture). See inc/fields/layouts/cta.php.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_sub = static function ( string $field ): string {
	if ( ! function_exists( 'get_sub_field' ) ) {
		return '';
	}
	$value = get_sub_field( $field );
	return is_string( $value ) ? $value : '';
};

$salt_heading = trim( $salt_sub( 'heading' ) );
$salt_text    = trim( $salt_sub( 'text' ) );

$salt_rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'buttons' ) : array();
if ( ! is_array( $salt_rows ) ) {
	$salt_rows = array();
}

// Normalise button rows, skipping malformed entries (logged — COMPL02).
$salt_buttons = array();
foreach ( $salt_rows as $salt_i => $salt_row ) {
	if ( ! is_array( $salt_row ) ) {
		salt_log( sprintf( 'cta section: button %d is not a row array — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_label = is_string( $salt_row['label'] ?? null ) ? trim( $salt_row['label'] ) : '';
	$salt_url   = is_string( $salt_row['url'] ?? null ) ? trim( $salt_row['url'] ) : '';
	if ( '' === $salt_label || '' === $salt_url ) {
		salt_log( sprintf( 'cta section: button %d is missing a label or URL — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_style = is_string( $salt_row['style'] ?? null ) ? $salt_row['style'] : 'primary';
	if ( ! in_array( $salt_style, array( 'primary', 'secondary', 'ghost' ), true ) ) {
		salt_log( sprintf( 'cta section: button %d has unknown style "%s" — using primary.', (int) $salt_i, $salt_style ) );
		$salt_style = 'primary';
	}

	$salt_buttons[] = array(
		'label' => $salt_label,
		'url'   => $salt_url,
		'style' => $salt_style,
	);
}

if ( count( $salt_buttons ) > 2 ) {
	salt_log( sprintf( 'cta section: %d buttons given — only the first 2 render.', count( $salt_buttons ) ) );
	$salt_buttons = array_slice( $salt_buttons, 0, 2 );
}

if ( '' === $salt_heading && '' === $salt_text && array() === $salt_buttons ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

$salt_wrapper = salt_section_wrapper_args( 'cta', $args ?? array() );

// The CTA band defaults to the brand tint when the editor left the shared
// wrapper control unset (salt_section_wrapper_args falls back to 'surface').
if ( '' === trim( $salt_sub( 'section_background' ) ) ) {
	$salt_wrapper['background'] = 'brand-tint';
}

salt_section(
	array_merge(
		$salt_wrapper,
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== $salt_heading )
	),
	static function ( int $level ) use ( $salt_heading, $salt_text, $salt_buttons ): void {
		salt_component(
			'cta',
			array(
				'heading' => $salt_heading,
				'text'    => $salt_text,
				'buttons' => $salt_buttons,
				'level'   => $level,
			)
		);
	}
);
