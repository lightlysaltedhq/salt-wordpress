<?php
/**
 * View: Call to Action (spec §10 #10) — thin composition over x-cta.
 *
 * Draws the props from salt_resolve_section_cta() (inc/sections/cta.php),
 * inside the x-section wrapper the shared entry opens. No field reads,
 * validation, logging or defaults here: a child theme overriding this file
 * overrides the markup only.
 *
 * $args:
 *   props  array{ heading: string|null, text: string|null,
 *                 buttons: array<int, array{label: string, href: string, style: string}> }
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

salt_component(
	'cta',
	array(
		'heading' => $salt_props['heading'] ?? '',
		'text'    => $salt_props['text'] ?? '',
		'buttons' => array_map(
			static fn( array $button ): array => array(
				'label' => $button['label'],
				'url'   => $button['href'],
				'style' => $button['style'],
			),
			$salt_props['buttons']
		),
		'level'   => $salt_plan['headingLevel'],
	)
);
