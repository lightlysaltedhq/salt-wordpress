<?php
/**
 * Section: FAQ / Accordion (spec §10 #13) — CPT-backed, zero JS.
 *
 * Questions come from the display-only faq CPT via the Principle-7 query
 * funnel; each renders as a native <details> item through the frozen
 * x-accordion component. Malformed posts (empty question title or empty
 * answer) are skipped + logged, never rendered broken (COMPL02).
 *
 * States (COMPL02): 0 valid items => renders NOTHING; 1 item => the same
 * static accordion (native <details> needs no JS in any state); N => full
 * list. Teaser rule (§6): faq is display-only (never public), so there is
 * no archive to link — no "view all", no pagination.
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

if ( ! salt_module_enabled( 'faq' ) ) {
	return;
}

$salt_eyebrow = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'eyebrow' ) : '';
$salt_heading = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';

// true_false, default 1: rows saved before the field existed read null => on.
$salt_schema_raw = function_exists( 'get_sub_field' ) ? get_sub_field( 'schema_toggle' ) : null;
$salt_schema_on  = null === $salt_schema_raw ? true : (bool) $salt_schema_raw;

$salt_posts = salt_resolve_query( salt_query_controls_from_row(), 'faq' );

$salt_items = array();
foreach ( $salt_posts as $salt_post ) {
	$salt_question = trim( (string) $salt_post->post_title );
	$salt_answer   = get_field( 'answer', $salt_post->ID );
	$salt_answer   = is_string( $salt_answer ) ? salt_sanitize_html( $salt_answer ) : '';

	if ( '' === $salt_question || '' === trim( wp_strip_all_tags( $salt_answer ) ) ) {
		salt_log(
			sprintf(
				'faq section: post %d skipped — %s is empty (broken row).',
				(int) $salt_post->ID,
				'' === $salt_question ? 'question title' : 'answer'
			)
		);
		continue;
	}

	$salt_items[] = array(
		'title'   => $salt_question,
		'content' => $salt_answer,
	);
}

if ( array() === $salt_items ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

// SEO03: one FAQPage node per section instance; the graph builder funnels
// every FAQPage into the single {page}#faq node, merging mainEntity arrays.
if ( $salt_schema_on ) {
	salt_schema_add(
		array(
			'@type'      => 'FAQPage',
			'mainEntity' => array_map(
				static fn( array $item ): array => array(
					'@type'          => 'Question',
					'name'           => $item['title'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $item['content'],
					),
				),
				$salt_items
			),
		)
	);
}

// Unique native <details name> group per section instance, so two FAQ
// sections on one page never share exclusive-open state.
$salt_group = 'faq-' . (int) ( $args['index'] ?? 0 );

salt_section(
	array_merge(
		salt_section_wrapper_args( 'faq', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_eyebrow, $salt_heading, $salt_items, $salt_group ): void {
		if ( '' !== trim( $salt_eyebrow ) ) {
			salt_component(
				'eyebrow',
				array(
					'text'    => $salt_eyebrow,
					'classes' => 'mb-2',
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
		salt_component(
			'accordion',
			array(
				'items' => $salt_items,
				'name'  => $salt_group,
			)
		);
	}
);
