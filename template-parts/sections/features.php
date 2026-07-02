<?php
/**
 * Section: Feature / Benefits Grid (spec §10 #5) — inline repeater.
 *
 * Fields: eyebrow, heading, columns (2|3|4), items [icon, title, text, link].
 * States (COMPL02): 0 valid items => renders NOTHING (skip before the
 * wrapper); 1 item => valid static single-card grid; N => full grid. Items
 * missing a title are skipped and logged — never a blank card. No schema,
 * no JS.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_sub = static function ( string $field ): string {
	if ( ! function_exists( 'get_sub_field' ) ) {
		return '';
	}
	$value = get_sub_field( $field );
	return is_string( $value ) ? $value : '';
};

$salt_eyebrow = $salt_sub( 'eyebrow' );
$salt_heading = $salt_sub( 'heading' );

$salt_columns = $salt_sub( 'columns' );
if ( ! in_array( $salt_columns, array( '2', '3', '4' ), true ) ) {
	$salt_columns = '3';
}

$salt_rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'items' ) : array();
if ( ! is_array( $salt_rows ) ) {
	$salt_rows = array();
}

// Normalise repeater rows, skipping malformed items (logged — COMPL02).
$salt_items = array();
foreach ( $salt_rows as $salt_i => $salt_row ) {
	if ( ! is_array( $salt_row ) ) {
		salt_log( sprintf( 'features section: item %d is not a row array — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_title = is_string( $salt_row['title'] ?? null ) ? trim( $salt_row['title'] ) : '';
	if ( '' === $salt_title ) {
		salt_log( sprintf( 'features section: item %d has no title — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_link = is_array( $salt_row['link'] ?? null ) ? $salt_row['link'] : array();

	$salt_items[] = array(
		'icon'  => is_string( $salt_row['icon'] ?? null ) ? trim( $salt_row['icon'] ) : '',
		'title' => $salt_title,
		'text'  => is_string( $salt_row['text'] ?? null ) ? trim( $salt_row['text'] ) : '',
		'url'   => is_string( $salt_link['url'] ?? null ) ? $salt_link['url'] : '',
	);
}

if ( array() === $salt_items ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'features', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_eyebrow, $salt_heading, $salt_columns, $salt_items ): void {
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
					'classes' => 'mb-6',
				)
			);
		}

		// Card titles step one level below the section heading (A11Y01).
		$card_level = min( 6, $level + 1 );

		$column_classes = array(
			'2' => 'sm:grid-cols-2',
			'3' => 'sm:grid-cols-2 lg:grid-cols-3',
			'4' => 'sm:grid-cols-2 lg:grid-cols-4',
		);

		printf( '<div class="x-features-grid grid gap-6 %s">', esc_attr( $column_classes[ $salt_columns ] ) );
		foreach ( $salt_items as $item ) {
			$icon_html = '';
			if ( '' !== $item['icon'] ) {
				$icon_html = salt_get_component(
					'icon',
					array(
						'name' => $item['icon'],
						'size' => 'lg',
					)
				);
			}

			echo salt_get_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component output escapes at the point each value is printed.
				'card',
				array(
					'title'   => $item['title'],
					'url'     => $item['url'],
					'level'   => $card_level,
					'body'    => $item['text'],
					'media'   => '' !== $icon_html
						? '<div class="x-card-feature-icon px-6 pt-6 text-brand">' . $icon_html . '</div>'
						: '',
					'classes' => 'x-card-feature',
				)
			);
		}
		echo '</div>';
	}
);
