<?php
/**
 * View: Call to Action (spec §10 #10) — thin composition over x-cta.
 *
 * Draws the props from salt_resolve_section_call_to_action() (inc/sections/call-to-action.php),
 * inside the x-section wrapper the shared entry opens. No field reads,
 * validation, logging or defaults here: a child theme overriding this file
 * overrides the markup only.
 *
 * $args:
 *   props  array{ heading: string|null, body: string|null (sanitised HTML),
 *                 buttons: array<int, array{label: string, href: string, style: string, newTab: bool}> }
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
		'body'    => $salt_props['body'] ?? '',
		'buttons' => array_map(
			static fn( array $button ): array => array(
				'label'  => $button['label'],
				'url'    => $button['href'],
				'style'  => $button['style'],
				'target' => $button['newTab'] ? '_blank' : '',
			),
			$salt_props['buttons']
		),
		'level'   => $salt_plan['headingLevel'],
	)
);
