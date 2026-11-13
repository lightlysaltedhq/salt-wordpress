<?php
/**
 * View: Pricing (spec §10 #11) — a grid of x-card-pricing. No JS.
 *
 * Draws the props from salt_resolve_section_pricing() (inc/sections/
 * pricing.php), inside the x-section wrapper the shared entry opens. No
 * field reads, validation or logging here: a child theme overriding this
 * file overrides the markup only.
 *
 * Grid of up to 3 columns (1 plan is a valid static single-card grid);
 * plan names render one level below the section heading (A11Y01).
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null,
 *                 plans: array<int, array{name: string, price: string, period: string,
 *                                         features: string[], cta: array, featured: bool}> }
 *          plans is never empty; each plan is in x-card-pricing's args shape.
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

// Plan names step one level below the section heading (A11Y01).
$salt_card_level = min( 6, $salt_plan['headingLevel'] + 1 );

// Grid caps at 3 columns; 1 plan is a valid static single-card grid.
$salt_count          = count( $salt_props['plans'] );
$salt_column_classes = array(
	1 => 'sm:max-w-md',
	2 => 'sm:grid-cols-2',
	3 => 'sm:grid-cols-2 lg:grid-cols-3',
);

printf( '<div class="x-pricing-grid grid gap-6 pt-3 %s">', esc_attr( $salt_column_classes[ min( 3, $salt_count ) ] ) );
foreach ( $salt_props['plans'] as $salt_pricing_plan ) {
	salt_component(
		'card-pricing',
		array(
			'name'     => $salt_pricing_plan['name'],
			'price'    => $salt_pricing_plan['price'],
			'period'   => $salt_pricing_plan['period'],
			'features' => $salt_pricing_plan['features'],
			'cta'      => $salt_pricing_plan['cta'],
			'featured' => $salt_pricing_plan['featured'],
			'level'    => $salt_card_level,
		)
	);
}
echo '</div>';
