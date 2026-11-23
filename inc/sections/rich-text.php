<?php
/**
 * Resolver: Rich Text (salt-contract rich-text, rc.8; spec §10 #3) — the
 * logic half of the section; the view is template-parts/sections/rich-text.php.
 * The §17 exemplar for the logic/view split (docs/contracts/CONVENTIONS.md),
 * and since EP-72 M4 the pilot of the contract's shared wrapper
 * (salt_section()).
 *
 * Fields (contract/fields/rich-text.json): eyebrow (text), heading (text),
 * body (WYSIWYG, salt_allowed_html-constrained), alignment (left|centre),
 * and the shared settings.
 * States (COMPL02, markup zeroState): eyebrow, heading and body all empty
 * => null (renders NOTHING, no band).
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
 *   eyebrow    string|null  Contract `eyebrow`. Null when blank.
 *   heading    string|null  Contract `heading`. Null when blank.
 *   body       string|null  Contract `body` (rich text): sanitised HTML
 *                           (salt_sanitize_html()); null when it has no
 *                           words (an empty paragraph has none).
 *   alignment  string       left|centre (a stored `center` is centre).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_rich_text( array $context ): ?array {
	$sub     = static fn( string $field ): string => function_exists( 'get_sub_field' ) && is_string( get_sub_field( $field ) ) ? (string) get_sub_field( $field ) : '';
	$eyebrow = salt_nullable_text( $sub( 'eyebrow' ) );
	$heading = salt_nullable_text( $sub( 'heading' ) );
	$body    = salt_sanitize_html( $sub( 'body' ), salt_field_allowed( 'field_salt_rich_text_body' ) );
	$words   = trim( str_replace( "\u{00A0}", ' ', html_entity_decode( wp_strip_all_tags( $body ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

	if ( null === $eyebrow && null === $heading && '' === $words ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			salt_section_contract_args( 'rich-text', $context ),
			// A section with no heading is a div and must not claim the page H1 (A11Y01).
			array( 'has_heading' => null !== $heading )
		),
		'props'   => array(
			'eyebrow'   => $eyebrow,
			'heading'   => $heading,
			'body'      => '' !== $words ? $body : null,
			'alignment' => in_array( $sub( 'alignment' ), array( 'centre', 'center' ), true ) ? 'centre' : 'left',
		),
	);
}
