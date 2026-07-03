<?php
/**
 * Generic result-loop card (spec §6) — used by archive/search loops.
 * Thin delegate to the base x-card so every loop grids consistently;
 * CPT-specific cards (card-service.php etc.) override via the
 * get_template_part() variant mechanism.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

salt_component(
	'card',
	array(
		'title' => (string) get_the_title(),
		'url'   => (string) get_the_permalink(),
		'body'  => has_excerpt() || '' !== get_the_excerpt() ? (string) get_the_excerpt() : '',
		'level' => 2,
	)
);
