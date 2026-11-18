<?php
/**
 * View: FAQ / Accordion (spec §10 #13) — zero JS.
 *
 * Draws the props from salt_resolve_section_faq() (inc/sections/faq.php),
 * inside the x-section wrapper the shared entry opens: each item renders
 * as a native <details> through the frozen x-accordion component. No field
 * reads, queries, sanitising, schema or logging here: a child theme
 * overriding this file overrides the markup only.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null,
 *                 items: array<int, array{id: string, question: string, answer: string}>,
 *                 structuredData: bool }
 *          items is never empty; answer is HTML already sanitised.
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

if ( null !== $salt_props['eyebrow'] ) {
	salt_component(
		'eyebrow',
		array(
			'text'    => $salt_props['eyebrow'],
			'classes' => 'mb-2',
		)
	);
}
if ( null !== $salt_props['heading'] ) {
	salt_component(
		'heading',
		array(
			'text'    => $salt_props['heading'],
			'level'   => $salt_plan['headingLevel'],
			'classes' => 'mb-6',
		)
	);
}
salt_component(
	'accordion',
	array(
		'items'   => array_map(
			static fn( array $item ): array => array(
				'title'   => $item['question'],
				'content' => $item['answer'],
			),
			$salt_props['items']
		),
		// Unique native <details name> group per section instance, so two FAQ
		// sections on one page never share exclusive-open state.
		'name'    => 'faq-' . (int) $salt_plan['index'],
		'allowed' => salt_field_allowed( 'field_salt_collection_faqs_answer' ),
	)
);
