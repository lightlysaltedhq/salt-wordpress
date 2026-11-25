<?php
/**
 * View: Listing (salt-contract 1.0.0). Draws the route's resolved props
 * using the shared card and pagination components; no queries here.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];
$salt_level = (int) $salt_plan['headingLevel'];

echo '<div class="salt-block" data-block="listing">';
if ( null !== $salt_props['eyebrow'] ) {
	echo '<p class="salt-eyebrow">' . esc_html( (string) $salt_props['eyebrow'] ) . '</p>';
}
if ( null !== $salt_props['heading'] ) {
	printf( '<h%1$d id="%2$s">%3$s</h%1$d>', (int) $salt_level, esc_attr( (string) $salt_plan['headingId'] ), esc_html( (string) $salt_props['heading'] ) );
}
if ( null !== $salt_props['intro'] ) {
	echo '<p class="salt-intro">' . esc_html( (string) $salt_props['intro'] ) . '</p>';
}
if ( array() === $salt_props['cards'] ) {
	echo '<p class="salt-listing__empty">' . esc_html( (string) $salt_props['labels']['empty'] ) . '</p>';
} else {
	echo '<ul class="salt-grid salt-showcase" role="list" data-layout="grid">';
	foreach ( $salt_props['cards'] as $salt_i => $salt_card ) {
		echo '<li>';
		salt_component(
			'contract-card',
			array(
				'card'     => $salt_card,
				'style'    => 'generic',
				'level'    => min( 6, (int) $salt_plan['bodyBaseLevel'] + 1 ),
				'priority' => 0 === $salt_i && $salt_plan['priorityMedia'],
				'labels'   => $salt_props['labels'],
				'arrow'    => $salt_props['arrow'],
			)
		);
		echo '</li>';
	}
	echo '</ul>';
}
$salt_pages = array_column( $salt_props['pages'], 'href', 'number' );
salt_component(
	'pagination',
	array(
		'current' => $salt_props['page'],
		'total'   => $salt_props['totalPages'],
		'href'    => static fn( int $page ): string => $salt_pages[ $page ],
		'labels'  => $salt_props['labels'],
	)
);
echo '</div>';
