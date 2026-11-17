<?php
/**
 * Resolver: Call to Action (spec §10 #10) — the logic half of the section;
 * the view is template-parts/sections/call-to-action.php.
 *
 * Fields: heading (text), text (textarea), buttons (repeater, max 2:
 * label, url, style primary|secondary|ghost). Background defaults to
 * 'brand-tint' when the editor leaves the shared wrapper control unset.
 *
 * States (COMPL02): heading, text AND valid buttons all empty => null
 * (renders NOTHING). Malformed button rows are skipped and logged;
 * anything beyond the first two valid buttons is dropped + logged. No JS,
 * no schema.
 *
 * DEFERRED (compliance phase): the spec's 'newsletter' variant — no email
 * input may ship while nothing can process the submission (PRIV01
 * posture). See inc/fields/layouts/call-to-action.php.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current call-to-action Flexible Content row to its view props.
 *
 * Props (contract view-props/call-to-action.json):
 *   heading  string|null  Contract `heading`. Null when blank; trimmed.
 *   body_text string|null WordPress-local. The contract's `body` is rich
 *                         text; this field is a plain textarea that x-cta
 *                         escapes, so the name is not shared. Null when
 *                         blank; trimmed.
 *   buttons  array<int, array{label: string, href: string, style: string}>
 *                         Contract `buttons` (button): `label`, `href`,
 *                         `style` as in the contract. The contract's
 *                         `newTab` is not supplied (the field has none).
 *                         At most two, in row order; may be empty.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_call_to_action( array $context ): ?array {
	$sub = static function ( string $field ): string {
		if ( ! function_exists( 'get_sub_field' ) ) {
			return '';
		}
		$value = get_sub_field( $field );
		return is_string( $value ) ? $value : '';
	};

	$heading = trim( $sub( 'heading' ) );
	$text    = trim( $sub( 'text' ) );

	$rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'buttons' ) : array();
	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	// Normalise button rows, skipping malformed entries (logged — COMPL02).
	$buttons = array();
	foreach ( $rows as $i => $row ) {
		if ( ! is_array( $row ) ) {
			salt_log( sprintf( 'call-to-action section: button %d is not a row array — skipped (broken row).', (int) $i ) );
			continue;
		}

		$label = is_string( $row['label'] ?? null ) ? trim( $row['label'] ) : '';
		$url   = is_string( $row['url'] ?? null ) ? trim( $row['url'] ) : '';
		if ( '' === $label || '' === $url ) {
			salt_log( sprintf( 'call-to-action section: button %d is missing a label or URL — skipped (broken row).', (int) $i ) );
			continue;
		}

		$style = is_string( $row['style'] ?? null ) ? $row['style'] : 'primary';
		if ( ! in_array( $style, array( 'primary', 'secondary', 'ghost' ), true ) ) {
			salt_log( sprintf( 'call-to-action section: button %d has unknown style "%s" — using primary.', (int) $i, $style ) );
			$style = 'primary';
		}

		$buttons[] = array(
			'label' => $label,
			'href'  => $url,
			'style' => $style,
		);
	}

	if ( count( $buttons ) > 2 ) {
		salt_log( sprintf( 'call-to-action section: %d buttons given — only the first 2 render.', count( $buttons ) ) );
		$buttons = array_slice( $buttons, 0, 2 );
	}

	if ( '' === $heading && '' === $text && array() === $buttons ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	$wrapper = salt_section_wrapper_args( 'call-to-action', $context );

	// The CTA band defaults to the brand tint when the editor left the shared
	// wrapper control unset (salt_section_wrapper_args falls back to 'surface').
	if ( '' === trim( $sub( 'tone' ) ) ) {
		$wrapper['background'] = 'brand-tint';
	}

	return array(
		'section' => array_merge(
			$wrapper,
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== $heading )
		),
		'props'   => array(
			'heading'   => salt_nullable_text( $heading ),
			'body_text' => salt_nullable_text( $text ),
			'buttons'   => $buttons,
		),
	);
}
