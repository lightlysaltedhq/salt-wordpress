<?php
/**
 * Resolver: Call to Action (spec §10 #10) — the logic half of the section;
 * the view is template-parts/sections/call-to-action.php.
 *
 * Fields (contract/fields/call-to-action.json): heading (text), body
 * (rich text), buttons (a list of up to two { link, style }). The tone
 * defaults to brand-tint (the contract's default for this section), also
 * when a row has no stored tone.
 *
 * States (COMPL02): heading, body AND valid buttons all empty => null
 * (renders NOTHING). Malformed button rows are skipped and logged;
 * anything beyond the first two valid buttons is dropped + logged. No JS,
 * no schema.
 *
 * DEFERRED (compliance phase): the spec's 'newsletter' variant — no email
 * input may ship while nothing can process the submission (PRIV01
 * posture).
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
 *   body     string|null  Contract `body` (rich text). Sanitised HTML
 *                         (salt_sanitize_html()); null when the stored
 *                         body is blank.
 *   buttons  array<int, array{label: string, href: string, style: string, newTab: bool}>
 *                         Contract `buttons` (button): `label`, `href`,
 *                         `style`, `newTab` as in the contract. At most
 *                         two, in row order; may be empty.
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
	$body    = $sub( 'body' );

	$buttons = array_map(
		static fn( array $button ): array => array(
			'label'  => $button['label'],
			'href'   => $button['url'],
			'style'  => $button['style'],
			'newTab' => '_blank' === $button['target'],
		),
		salt_contract_buttons( function_exists( 'get_sub_field' ) ? get_sub_field( 'buttons' ) : array(), 'call-to-action section', 2 )
	);

	if ( '' === $heading && '' === trim( $body ) && array() === $buttons ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	$wrapper = salt_section_wrapper_args( 'call-to-action', $context );

	// The contract's default tone for this section, also for a row with none
	// stored (salt_section_wrapper_args() falls back to 'surface').
	$tone = salt_section_settings()['tone'] ?? '';
	if ( ! is_string( $tone ) || '' === trim( $tone ) ) {
		$wrapper['background'] = 'brand-tint';
	}

	return array(
		'section' => array_merge(
			$wrapper,
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== $heading )
		),
		'props'   => array(
			'heading' => salt_nullable_text( $heading ),
			'body'    => '' !== trim( $body ) ? salt_sanitize_html( $body, salt_field_allowed( 'field_salt_call_to_action_body' ) ) : null,
			'buttons' => $buttons,
		),
	);
}
