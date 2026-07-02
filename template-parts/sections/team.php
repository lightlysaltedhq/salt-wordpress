<?php
/**
 * Section: Team (spec §10 #12) — a grid of x-card-team cards backed by the
 * team_member CPT via the Principle-7 resolver (§10a).
 *
 * Fields: eyebrow, heading, columns (2|3|4) + shared query controls.
 * States (COMPL02): 0 resolved members => renders NOTHING (skip);
 * 1 member => the same static grid with one card (no JS anywhere here);
 * N => static grid. Members without a name (empty post title) are skipped
 * and logged (DATA05).
 *
 * Teaser rule (§6): bounded count via the query controls; team_member has
 * NO public archive in v1 (see inc/cpt/team-member.php), so no "view all"
 * link is rendered.
 *
 * Schema: one Person node per rendered member via salt_schema_add() —
 * name + jobTitle ONLY. Email and phone are deliberately OMITTED from the
 * structured data (PII minimisation: they render as human-facing links but
 * are not published into the machine-readable graph).
 *
 * Gated by the 'team_member' module flag (§10b, layer 2: section render).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! salt_module_enabled( 'team_member' ) ) {
	return;
}

$salt_members = salt_resolve_query( salt_query_controls_from_row(), 'team_member' );

// Malformed rows: a member with no name renders nowhere — skip + log (DATA05).
$salt_valid_members = array();
foreach ( $salt_members as $salt_member ) {
	if ( '' === trim( (string) $salt_member->post_title ) ) {
		salt_log( sprintf( 'team: team_member %d has no name — skipped (DATA05).', (int) $salt_member->ID ) );
		continue;
	}
	$salt_valid_members[] = $salt_member;
}

if ( array() === $salt_valid_members ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

$salt_eyebrow = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'eyebrow' ) : '';
$salt_heading = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';

$salt_columns = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'columns' ) : '3';
if ( ! in_array( $salt_columns, array( '2', '3', '4' ), true ) ) {
	if ( '' !== $salt_columns ) {
		salt_log( sprintf( 'team: invalid columns value "%s" — using 3.', $salt_columns ) );
	}
	$salt_columns = '3';
}

$salt_grid_classes = array(
	'2' => 'grid gap-8 sm:grid-cols-2',
	'3' => 'grid gap-8 sm:grid-cols-2 lg:grid-cols-3',
	'4' => 'grid gap-8 sm:grid-cols-2 lg:grid-cols-4',
);

salt_section(
	array_merge(
		salt_section_wrapper_args( 'team', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_valid_members, $salt_eyebrow, $salt_heading, $salt_grid_classes, $salt_columns ): void {
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
					'classes' => 'mb-8',
				)
			);
		}

		echo '<ul class="x-team-grid list-none ' . esc_attr( $salt_grid_classes[ $salt_columns ] ) . '">';

		foreach ( $salt_valid_members as $salt_member ) {
			$salt_name  = trim( (string) $salt_member->post_title );
			$salt_role  = (string) get_field( 'role', $salt_member->ID );
			$salt_bio   = (string) get_field( 'short_bio', $salt_member->ID );
			$salt_email = (string) get_field( 'email', $salt_member->ID );
			$salt_phone = (string) get_field( 'phone', $salt_member->ID );
			$salt_rows  = get_field( 'socials', $salt_member->ID );

			$salt_thumb = (int) get_post_thumbnail_id( $salt_member );
			$salt_media = $salt_thumb > 0
				? salt_get_component(
					'image',
					array(
						'image_id'      => $salt_thumb,
						'alt'           => $salt_name,
						'sizes_context' => 'card',
					)
				)
				: '';

			// Person node — name + jobTitle only; email/phone deliberately
			// omitted from the graph (PII minimisation — see file docblock).
			$salt_node = array(
				'@type' => 'Person',
				'name'  => $salt_name,
			);
			if ( '' !== trim( $salt_role ) ) {
				$salt_node['jobTitle'] = $salt_role;
			}
			salt_schema_add( $salt_node );

			echo '<li>';
			salt_component(
				'card-team',
				array(
					'name'    => $salt_name,
					'role'    => $salt_role,
					'bio'     => $salt_bio,
					'email'   => $salt_email,
					'phone'   => $salt_phone,
					'media'   => $salt_media,
					'socials' => is_array( $salt_rows ) ? $salt_rows : array(),
					// Card titles step below the section heading (A11Y01).
					'level'   => $level + 1,
				)
			);
			echo '</li>';
		}

		echo '</ul>';
	}
);
