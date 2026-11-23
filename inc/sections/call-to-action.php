<?php
/**
 * Resolver: Call to Action (salt-contract call-to-action, rc.8; spec §10
 * #10) — the logic half of the section; the view is
 * template-parts/sections/call-to-action.php. Drawn in the contract's
 * shared wrapper (salt_section(), EP-72 M4).
 *
 * Fields (contract/fields/call-to-action.json): heading (text), body
 * (rich text), buttons (a list of up to two { link, style }) and the
 * shared settings. The tone defaults to brand-tint (the contract's default
 * for this section), also when a row has no stored tone.
 *
 * States (COMPL02, markup zeroState): heading, body (words) AND valid
 * buttons all empty => null (renders NOTHING). Malformed button rows are
 * skipped and logged. No JS, no schema.
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
 *   heading  string|null  Contract `heading`. Null when blank.
 *   body     string|null  Contract `body` (rich text): sanitised HTML
 *                         (salt_sanitize_html()); null when it has no words.
 *   buttons  array<int, array{label: string, href: string, style: string, newTab: bool}>
 *                         Contract `buttons`: valid links only, in row order.
 *   arrow    string|null  The site's arrow (salt_contract_arrow()).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_call_to_action( array $context ): ?array {
	$sub     = static fn( string $field ): string => function_exists( 'get_sub_field' ) && is_string( get_sub_field( $field ) ) ? (string) get_sub_field( $field ) : '';
	$heading = salt_nullable_text( $sub( 'heading' ) );
	$body    = salt_sanitize_html( $sub( 'body' ), salt_field_allowed( 'field_salt_call_to_action_body' ) );
	$words   = salt_rich_text_has_words( $body );
	$buttons = salt_contract_button_props( salt_contract_buttons( function_exists( 'get_sub_field' ) ? get_sub_field( 'buttons' ) : array(), 'call-to-action section', 2 ) );

	if ( null === $heading && ! $words && array() === $buttons ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	$section = salt_section_contract_args( 'call-to-action', $context );

	// The contract's default tone for this section, also for a row with none
	// stored or an unknown one (salt_section_contract_args() falls back to
	// surface); an unset or auto dark tone follows it.
	$settings = salt_section_settings();
	$tones    = array( 'surface', 'surface-alt', 'brand-tint', 'surface-inverse' );
	if ( ! in_array( $settings['tone'] ?? null, $tones, true ) ) {
		$section['background'] = 'brand-tint';
		$section['tone']       = 'brand-tint';
		$section['tone_dark']  = in_array( $settings['toneDark'] ?? null, $tones, true ) ? (string) $settings['toneDark'] : 'brand-tint';
	}

	return array(
		'section' => array_merge(
			$section,
			// A section with no heading is a div and must not claim the page H1 (A11Y01).
			array( 'has_heading' => null !== $heading )
		),
		'props'   => array(
			'heading' => $heading,
			'body'    => $words ? $body : null,
			'buttons' => $buttons,
			'arrow'   => salt_contract_arrow(),
		),
	);
}
