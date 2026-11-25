<?php
/**
 * Fixture child WHOLE SECTION for stats: tier "logic" in
 * salt-overrides.json, so the parent's resolver never runs and this
 * template is loaded with the loop context, as an unsplit section's is.
 * It does its own reads and composes its own band.
 *
 * $args: array{ index: int, is_primary: bool } (the loop context).
 *
 * @package Grain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grain_heading = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';

salt_section(
	array_merge(
		salt_section_contract_args( 'stats', $args ),
		array( 'has_heading' => '' !== $grain_heading )
	),
	static function ( int $level ) use ( $grain_heading ): void {
		printf(
			'<div class="grain-stats"><h%1$d>%2$s</h%1$d><p>%3$s</p></div>',
			(int) $level,
			esc_html( $grain_heading ),
			esc_html__( 'Figures from the site\'s own source.', 'grain' )
		);
	}
);
