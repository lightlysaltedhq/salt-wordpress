<?php
/**
 * Resolver: Team (spec §10 #12) — the logic half of the section; the view
 * is template-parts/sections/team.php. A grid of x-card-team cards backed
 * by the team_member CPT via the Principle-7 resolver (§10a).
 *
 * Fields: eyebrow, heading, columns (2|3|4) + shared query controls.
 * States (COMPL02): module off or 0 resolved members => null (renders
 * NOTHING); 1 member => the same static grid with one card (no JS anywhere
 * here); N => static grid. Members without a name (empty post title) are
 * skipped and logged (DATA05).
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

/**
 * Resolve the current team Flexible Content row to its view props.
 *
 * Props. The contract folds this layout into collection-showcase
 * (view-props/collection-showcase.json) in a LATER work item; until then
 * only the identical-meaning props carry the contract's names:
 *   eyebrow   string|null  Contract `eyebrow`. Null when blank.
 *   heading   string|null  Contract `heading`. Null when blank.
 *   columns   string       Contract `columns` ('2'|'3'|'4'; invalid => '3').
 *   members   array<int, array{name: string, role: string, bio: string, email: string, phone: string, image_id: int, socials: array}>
 *                          WordPress-local (contract `cards`): the members
 *                          in query order; never empty. image_id is the
 *                          featured image's attachment ID, 0 for none; the
 *                          view draws it. The Person nodes are already queued.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_team( array $context ): ?array {
	if ( ! salt_module_enabled( 'team_member' ) ) {
		return null;
	}

	$posts = salt_resolve_query( salt_query_controls_from_row(), 'team_member' );

	// Malformed rows: a member with no name renders nowhere — skip + log (DATA05).
	$valid_posts = array();
	foreach ( $posts as $post_item ) {
		if ( '' === trim( (string) $post_item->post_title ) ) {
			salt_log( sprintf( 'team: team_member %d has no name — skipped (DATA05).', (int) $post_item->ID ) );
			continue;
		}
		$valid_posts[] = $post_item;
	}

	if ( array() === $valid_posts ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	$eyebrow = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'eyebrow' ) : '';
	$heading = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';

	$columns = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'columns' ) : '3';
	if ( ! in_array( $columns, array( '2', '3', '4' ), true ) ) {
		if ( '' !== $columns ) {
			salt_log( sprintf( 'team: invalid columns value "%s" — using 3.', $columns ) );
		}
		$columns = '3';
	}

	$members = array();
	foreach ( $valid_posts as $post_item ) {
		$name  = trim( (string) $post_item->post_title );
		$role  = (string) get_field( 'role', $post_item->ID );
		$bio   = (string) get_field( 'bio', $post_item->ID );
		$email = (string) get_field( 'email', $post_item->ID );
		$phone = (string) get_field( 'phone', $post_item->ID );
		$rows  = get_field( 'socials', $post_item->ID );

		$image_id = (int) get_post_thumbnail_id( $post_item );

		// Person node — name + jobTitle only; email/phone deliberately
		// omitted from the graph (PII minimisation — see file docblock).
		$node = array(
			'@type' => 'Person',
			'name'  => $name,
		);
		if ( '' !== trim( $role ) ) {
			$node['jobTitle'] = $role;
		}
		salt_schema_add( $node );

		$members[] = array(
			'name'     => $name,
			'role'     => $role,
			'bio'      => $bio,
			'email'    => $email,
			'phone'    => $phone,
			'image_id' => $image_id,
			'socials'  => is_array( $rows ) ? $rows : array(),
		);
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'team', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'eyebrow' => salt_nullable_text( $eyebrow ),
			'heading' => salt_nullable_text( $heading ),
			'columns' => $columns,
			'members' => $members,
		),
	);
}
