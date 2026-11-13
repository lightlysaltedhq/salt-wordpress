<?php
/**
 * View: Team (spec §10 #12) — a grid of x-card-team cards.
 *
 * Draws the props from salt_resolve_section_team() (inc/sections/team.php),
 * inside the x-section wrapper the shared entry opens. No field reads,
 * queries, schema or logging here: a child theme overriding this file
 * overrides the markup only.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null,
 *                 columns: '2'|'3'|'4',
 *                 members: array<int, array{name: string, role: string, bio: string,
 *                          email: string, phone: string, image_id: int, socials: array}> }
 *          members is never empty; image_id 0 = no photo.
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

$salt_grid_classes = array(
	'2' => 'grid gap-8 sm:grid-cols-2',
	'3' => 'grid gap-8 sm:grid-cols-2 lg:grid-cols-3',
	'4' => 'grid gap-8 sm:grid-cols-2 lg:grid-cols-4',
);

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
			'classes' => 'mb-8',
		)
	);
}

echo '<ul class="x-team-grid list-none ' . esc_attr( $salt_grid_classes[ $salt_props['columns'] ] ) . '">';

foreach ( $salt_props['members'] as $salt_member ) {
	$salt_media = $salt_member['image_id'] > 0
		? salt_get_component(
			'image',
			array(
				'image_id'      => $salt_member['image_id'],
				'alt'           => $salt_member['name'],
				'sizes_context' => 'card',
			)
		)
		: '';

	echo '<li>';
	salt_component(
		'card-team',
		array(
			'name'    => $salt_member['name'],
			'role'    => $salt_member['role'],
			'bio'     => $salt_member['bio'],
			'email'   => $salt_member['email'],
			'phone'   => $salt_member['phone'],
			'media'   => $salt_media,
			'socials' => $salt_member['socials'],
			// Card titles step below the section heading (A11Y01).
			'level'   => $salt_plan['headingLevel'] + 1,
		)
	);
	echo '</li>';
}

echo '</ul>';
