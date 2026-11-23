<?php
/**
 * The section loop (spec §5) — iterates the "sections" Flexible Content
 * field, passing document position into each section (AMBIG03).
 *
 * H1 invariant (A11Y01): the FIRST section is the page's designated
 * primary heading (its heading renders as H1 via the wrapper's claim).
 * The loop renders into a buffer; if no section claimed the H1 (e.g. a
 * heading-less first section, or zero sections), a visually-hidden H1
 * with the post title is emitted BEFORE the sections — no builder page
 * can ship a zero-H1 outline.
 *
 * A row whose settings turn on firstPageOnly is skipped on any page after
 * the first (salt_section_hidden_on_this_page()).
 *
 * The page plan: every band's id is settled once the bands have drawn,
 * over the rows that drew one (salt_section_plan_settle(), section#anchors),
 * and a band's index and primacy count only the bands drawn before it, so
 * a first row that draws nothing leaves the next one first and never takes
 * an editor's anchor.
 *
 * ACF-absent (DEP01): renders the safe fallback (title as H1) — no fatal.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! salt_acf_active() ) {
	salt_the_document_h1( get_the_title() );
	return;
}

ob_start();

$salt_rows = array();
if ( have_rows( 'sections' ) ) {
	$salt_first = salt_render_state()['section_index'];
	$salt_row   = -1;
	while ( have_rows( 'sections' ) ) {
		the_row();
		++$salt_row;
		$salt_rows[ $salt_row ] = salt_section_plan_row();
		// The contract's firstPageOnly: hidden past page 1, and not counted,
		// so the next drawn section takes its place in the document.
		if ( salt_section_hidden_on_this_page() ) {
			continue;
		}
		// The band's place among the bands drawn so far: a row that draws
		// nothing takes no place, so the next one is the page's first.
		$salt_before = salt_render_state()['section_index'];
		$salt_index  = $salt_before - $salt_first;
		salt_render_section(
			salt_section_slug_for_layout( (string) get_row_layout() ),
			array(
				'index'      => $salt_index,
				'is_primary' => 0 === $salt_index,
				'id'         => salt_section_plan_placeholder( $salt_row ),
			)
		);
		$salt_rows[ $salt_row ]['on_page'] = salt_render_state()['section_index'] > $salt_before;
	}
}

// The page plan's ids, settled over the rows that drew a band.
$salt_sections_html = salt_section_plan_settle( (string) ob_get_clean(), $salt_rows );

if ( ! salt_h1_claimed() ) {
	salt_the_document_h1( get_the_title(), 'visually-hidden' );
}

echo $salt_sections_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Section partials escape at the point each value is printed.
