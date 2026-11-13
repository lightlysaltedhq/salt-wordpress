<?php
/**
 * Resolver: FAQ / Accordion (spec §10 #13) — the logic half of the
 * section; the view is template-parts/sections/faq.php.
 *
 * Questions come from the display-only faq CPT via the Principle-7 query
 * funnel. Malformed posts (empty question title or empty answer) are
 * skipped + logged, never rendered broken (COMPL02).
 *
 * States (COMPL02): module off or 0 valid items => null (renders NOTHING);
 * 1 item => the same static accordion (native <details> needs no JS in
 * any state); N => full list. Teaser rule (§6): faq is display-only
 * (never public), so there is no archive to link — no "view all", no
 * pagination.
 *
 * Schema (SEO03): when schema_toggle is on, ONE FAQPage node is queued via
 * salt_schema_add() per section instance; the graph builder merges every
 * queued FAQPage into the single {page}#faq node.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current faq Flexible Content row to its view props.
 *
 * Props (contract view-props/faq.json):
 *   eyebrow         string|null  Contract `eyebrow`. Null when blank.
 *   heading         string|null  Contract `heading`. Null when blank.
 *   items           array<int, array{id: string, question: string, answer: string}>
 *                                Contract `items` (faq-item), in query order;
 *                                never empty. answer is HTML already
 *                                sanitised by salt_sanitize_html().
 *   structuredData  bool         Contract `structuredData`, from the
 *                                schema_toggle field. The FAQPage node is
 *                                already queued; a view draws nothing for it.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_faq( array $context ): ?array {
	if ( ! salt_module_enabled( 'faq' ) ) {
		return null;
	}

	$eyebrow = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'eyebrow' ) : '';
	$heading = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';

	// true_false, default 1: rows saved before the field existed read null => on.
	$schema_raw = function_exists( 'get_sub_field' ) ? get_sub_field( 'schema_toggle' ) : null;
	$schema_on  = null === $schema_raw ? true : (bool) $schema_raw;

	$faq_posts = salt_resolve_query( salt_query_controls_from_row(), 'faq' );

	$items = array();
	foreach ( $faq_posts as $faq_post ) {
		$question = trim( (string) $faq_post->post_title );
		$answer   = get_field( 'answer', $faq_post->ID );
		$answer   = is_string( $answer ) ? salt_sanitize_html( $answer ) : '';

		if ( '' === $question || '' === trim( wp_strip_all_tags( $answer ) ) ) {
			salt_log(
				sprintf(
					'faq section: post %d skipped — %s is empty (broken row).',
					(int) $faq_post->ID,
					'' === $question ? 'question title' : 'answer'
				)
			);
			continue;
		}

		$items[] = array(
			'id'       => (string) (int) $faq_post->ID,
			'question' => $question,
			'answer'   => $answer,
		);
	}

	if ( array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	// SEO03: one FAQPage node per section instance; the graph builder funnels
	// every FAQPage into the single {page}#faq node, merging mainEntity arrays.
	if ( $schema_on ) {
		salt_schema_add(
			array(
				'@type'      => 'FAQPage',
				'mainEntity' => array_map(
					static fn( array $item ): array => array(
						'@type'          => 'Question',
						'name'           => $item['question'],
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $item['answer'],
						),
					),
					$items
				),
			)
		);
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'faq', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'eyebrow'        => salt_nullable_text( $eyebrow ),
			'heading'        => salt_nullable_text( $heading ),
			'items'          => $items,
			'structuredData' => $schema_on,
		),
	);
}
