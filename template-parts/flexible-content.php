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
 * The page plan: every band's id is settled before any band draws
 * (salt_section_plan_rows(), section#anchors), and a band's index and
 * primacy count only the bands drawn before it, so a first row that draws
 * nothing leaves the next one first.
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

if ( have_rows( 'sections' ) ) {
	// The page plan's first pass: every band's id, settled before any draws.
	$salt_ids   = salt_section_plan_rows( 'sections' );
	$salt_first = salt_render_state()['section_index'];
	$salt_row   = -1;
	while ( have_rows( 'sections' ) ) {
		the_row();
		++$salt_row;
		// The contract's firstPageOnly: hidden past page 1, and not counted,
		// so the next drawn section takes its place in the document.
		if ( salt_section_hidden_on_this_page() ) {
			continue;
		}
		// The band's place among the bands drawn so far: a row that draws
		// nothing takes no place, so the next one is the page's first.
		$salt_index = salt_render_state()['section_index'] - $salt_first;
		salt_render_section(
			salt_section_slug_for_layout( (string) get_row_layout() ),
			array(
				'index'      => $salt_index,
				'is_primary' => 0 === $salt_index,
				'id'         => $salt_ids[ $salt_row ] ?? '',
			)
		);
	}
}

$salt_sections_html = (string) ob_get_clean();

if ( ! salt_h1_claimed() ) {
	salt_the_document_h1( get_the_title(), 'visually-hidden' );
}

echo $salt_sections_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Section partials escape at the point each value is printed.
