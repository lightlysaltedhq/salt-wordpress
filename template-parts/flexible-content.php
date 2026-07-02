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
	$salt_index = 0;
	while ( have_rows( 'sections' ) ) {
		the_row();
		salt_render_section(
			str_replace( '_', '-', (string) get_row_layout() ),
			array(
				'index'      => $salt_index,
				'is_primary' => 0 === $salt_index,
			)
		);
		++$salt_index;
	}
}

$salt_sections_html = (string) ob_get_clean();

if ( ! salt_h1_claimed() ) {
	salt_the_document_h1( get_the_title(), 'visually-hidden' );
}

echo $salt_sections_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Section partials escape at the point each value is printed.
