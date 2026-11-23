<?php
/**
 * View: Media + Text (salt-contract markup/media-text.json, rc.8; spec §10 #6).
 *
 * Draws the props from salt_resolve_section_media_text() (inc/sections/
 * media-text.php) as the contract's block, inside the band the shared
 * entry opens: one div.salt-media-text__row per row, media then body in
 * the DOM, the side on data-media-side. No field reads, sanitising,
 * validation or logging here: a child theme overriding this file overrides
 * the markup only.
 *
 * HEADING OUTLINE RULE: the first row's heading is the section's (plan
 * level and id); later rows' headings rank at body base + 1. A row's body
 * text ranks its headings under the row's heading, or under the body base
 * when the row has none.
 *
 * $args:
 *   props  array{ rows: array<int, array{image: array|null, heading: string|null,
 *                 content: string|null, mediaSide: string|null,
 *                 buttons: array<int, array{label: string, href: string, style: string, newTab: bool}>}>,
 *                 arrow: string|null }
 *          rows is never empty; content is HTML already sanitised.
 *   plan   array{ index: int, anchor: string, headingLevel: int, headingId: string|null, bodyBaseLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];
$salt_level = max( 1, min( 6, (int) $salt_plan['headingLevel'] ) );
$salt_base  = (int) ( $salt_plan['bodyBaseLevel'] ?? ( null === $salt_props['rows'][0]['heading'] ? 1 : $salt_level ) );
$salt_id    = $salt_plan['headingId'] ?? null;
// The row whose image is the priority image: the first that has one.
$salt_lead = array_key_first( array_filter( array_column( $salt_props['rows'], 'image' ) ) );

echo '<div class="salt-block" data-block="media-text"><div class="salt-media-text">';

foreach ( $salt_props['rows'] as $salt_i => $salt_row ) {
	echo null !== $salt_row['mediaSide'] ? '<div class="salt-media-text__row" data-media-side="' . esc_attr( (string) $salt_row['mediaSide'] ) . '">' : '<div class="salt-media-text__row">';

	if ( is_array( $salt_row['image'] ) ) {
		echo '<div class="salt-media-text__media">';
		echo salt_image_contract_html( $salt_row['image'], $salt_lead === $salt_i && ! empty( $salt_plan['priorityMedia'] ), array( 'class' => 'salt-media-text__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
		echo '</div>';
	}

	if ( null !== $salt_row['heading'] || null !== $salt_row['content'] || array() !== $salt_row['buttons'] ) {
		echo '<div class="salt-media-text__body">';
		$salt_row_base = $salt_base;
		if ( null !== $salt_row['heading'] ) {
			$salt_row_base = 0 === $salt_i ? $salt_level : min( 6, $salt_base + 1 );
			printf(
				'<h%1$d%2$s>%3$s</h%1$d>',
				(int) $salt_row_base,
				0 === $salt_i && is_string( $salt_id ) ? ' id="' . esc_attr( $salt_id ) . '"' : '',
				esc_html( (string) $salt_row['heading'] )
			);
		}
		if ( null !== $salt_row['content'] ) {
			printf(
				'<div class="salt-rich-text">%s</div>',
				salt_render_rich_text_headings( (string) $salt_row['content'], $salt_row_base ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already sanitised by the resolver; only heading ranks change.
			);
		}
		if ( array() !== $salt_row['buttons'] ) {
			echo '<div class="salt-media-text__actions">';
			foreach ( $salt_row['buttons'] as $salt_button ) {
				salt_component(
					'contract-button',
					array(
						'button' => $salt_button,
						'arrow'  => $salt_props['arrow'],
					)
				);
			}
			echo '</div>';
		}
		echo '</div>';
	}

	echo '</div>';
}

echo '</div></div>';
