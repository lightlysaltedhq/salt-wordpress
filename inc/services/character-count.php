<?php
/**
 * The character count: a count beside a field in the editor, against the
 * length the contract suggests for it (the founder's ruling of 19/11/2026:
 * a title or name of 120 characters, a topic name of 120 and a summary of
 * 300 are targets, never limits).
 *
 * admin/character-count.js draws "N / target" beside each field it is given
 * and updates it as the editor types. Past the target it changes appearance
 * and says "Over the suggested N characters". A field may also have softer
 * notes at shorter lengths: a service's, case study's or post's title says
 * search results may shorten it past about 60 (the founder's ruling of
 * 19/11/2026). None of it stops a save: Save Draft, Publish and Update work
 * whatever the length.
 *
 * One script serves every field: a screen asks for a count with
 * salt_character_count(), once per field.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A text's length in characters, as the count shows it.
 *
 * Counted in Unicode code points (mb_strlen), with a CRLF line break as
 * one character, so "é" is one character, not two bytes (the founder's
 * ruling on PR #26; the same as ACF counts its own maxlength limits).
 * admin/character-count.js counts the same way.
 *
 * @param string $text The text, unslashed.
 * @return int
 */
function salt_character_length( string $text ): int {
	return mb_strlen( str_replace( "\r\n", "\n", $text ) );
}

/**
 * The message the count shows past a target.
 *
 * @param int $target The suggested length.
 * @return string
 */
function salt_character_count_over( int $target ): string {
	/* translators: %d: the suggested length in characters. */
	return sprintf( __( 'Over the suggested %d characters', 'salt' ), $target );
}

/**
 * Show a count beside a field on this admin screen (call from
 * 'admin_enqueue_scripts').
 *
 * Enqueues admin/character-count.js once and adds the field to the list it
 * reads (window.saltCharacterCount), so each screen can add its own fields.
 * The count reads "N / target". Past the target it shows the strong note
 * (salt_character_count_over()); past a shorter length in $notes it shows
 * that length's softer note instead, until the target is passed. Neither
 * stops a save.
 *
 * @param string            $field  The field's element ID.
 * @param int               $target The suggested length, in characters.
 * @param array<int,string> $notes  Softer notes, by the length past which each shows.
 * @return void
 */
function salt_character_count( string $field, int $target, array $notes = array() ): void {
	$src = salt_asset_optional( 'admin/character-count.js' );
	if ( null === $src ) {
		salt_log( 'salt_character_count: no manifest entry for "admin/character-count.js" — no count is shown beside "' . $field . '". Re-run the asset build.' );
		return;
	}
	wp_enqueue_script(
		'salt-character-count',
		$src,
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Content hash in the dist filename is the cache-buster (ARCH03).
		array( 'in_footer' => true )
	);
	ksort( $notes );
	$marks = array();
	foreach ( $notes as $at => $note ) {
		$marks[] = array(
			'at'     => (int) $at,
			'note'   => $note,
			'strong' => false,
		);
	}
	$marks[] = array(
		'at'     => $target,
		'note'   => salt_character_count_over( $target ),
		'strong' => true,
	);
	wp_add_inline_script(
		'salt-character-count',
		'window.saltCharacterCount = ( window.saltCharacterCount || [] ).concat( ' . wp_json_encode(
			array(
				array(
					'field'  => $field,
					'target' => $target,
					'marks'  => $marks,
				),
			),
			JSON_HEX_TAG
		) . ' );',
		'before'
	);
}
