<?php
/**
 * Section: Pricing (spec §10 #11) — inline repeater of x-card-pricing.
 *
 * Fields: eyebrow, heading, plans [name, price, period, features
 * repeater(text), cta link, featured]. Grid of up to 3 columns; plan names
 * render at $level + 1 (A11Y01).
 *
 * States (COMPL02): 0 valid plans => renders NOTHING (skip before the
 * wrapper); 1 plan => valid static single-card grid; N => full grid. Plans
 * missing a name are skipped and logged. Multiple featured plans: only the
 * FIRST is honoured, the rest are demoted + logged.
 *
 * NO SCHEMA in v1 (Offer/PriceSpecification deferred — prices are display
 * strings, see inc/fields/layouts/pricing.php). No JS.
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

$salt_eyebrow = trim( $salt_sub( 'eyebrow' ) );
$salt_heading = trim( $salt_sub( 'heading' ) );

$salt_rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'plans' ) : array();
if ( ! is_array( $salt_rows ) ) {
	$salt_rows = array();
}

// Normalise plan rows, skipping malformed entries (logged — COMPL02).
$salt_plans        = array();
$salt_has_featured = false;
foreach ( $salt_rows as $salt_i => $salt_row ) {
	if ( ! is_array( $salt_row ) ) {
		salt_log( sprintf( 'pricing section: plan %d is not a row array — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_name = is_string( $salt_row['name'] ?? null ) ? trim( $salt_row['name'] ) : '';
	if ( '' === $salt_name ) {
		salt_log( sprintf( 'pricing section: plan %d has no name — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	// Features repeater rows => plain strings (x-card-pricing re-validates).
	$salt_features     = array();
	$salt_feature_rows = is_array( $salt_row['features'] ?? null ) ? $salt_row['features'] : array();
	foreach ( $salt_feature_rows as $salt_feature_row ) {
		if ( is_array( $salt_feature_row ) && is_string( $salt_feature_row['text'] ?? null ) && '' !== trim( $salt_feature_row['text'] ) ) {
			$salt_features[] = trim( $salt_feature_row['text'] );
		}
	}

	// ACF link array => the card's cta { label, url }.
	$salt_link = is_array( $salt_row['cta'] ?? null ) ? $salt_row['cta'] : array();
	$salt_url  = is_string( $salt_link['url'] ?? null ) ? trim( $salt_link['url'] ) : '';
	$salt_cta  = array();
	if ( '' !== $salt_url ) {
		$salt_label = is_string( $salt_link['title'] ?? null ) ? trim( $salt_link['title'] ) : '';
		$salt_cta   = array(
			'label' => '' !== $salt_label ? $salt_label : __( 'Choose plan', 'salt' ),
			'url'   => $salt_url,
		);
	}

	// Only the FIRST featured plan is honoured (one "Most popular" badge).
	$salt_featured = (bool) ( $salt_row['featured'] ?? false );
	if ( $salt_featured && $salt_has_featured ) {
		salt_log( sprintf( 'pricing section: plan %d ("%s") also flagged featured — only the first featured plan is honoured.', (int) $salt_i, $salt_name ) );
		$salt_featured = false;
	}
	$salt_has_featured = $salt_has_featured || $salt_featured;

	$salt_plans[] = array(
		'name'     => $salt_name,
		'price'    => is_string( $salt_row['price'] ?? null ) ? trim( $salt_row['price'] ) : '',
		'period'   => is_string( $salt_row['period'] ?? null ) ? trim( $salt_row['period'] ) : '',
		'features' => $salt_features,
		'cta'      => $salt_cta,
		'featured' => $salt_featured,
	);
}

if ( array() === $salt_plans ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'pricing', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== $salt_heading )
	),
	static function ( int $level ) use ( $salt_eyebrow, $salt_heading, $salt_plans ): void {
		if ( '' !== $salt_eyebrow ) {
			salt_component(
				'eyebrow',
				array(
					'text'    => $salt_eyebrow,
					'classes' => 'mb-2',
				)
			);
		}
		if ( '' !== $salt_heading ) {
			salt_component(
				'heading',
				array(
					'text'    => $salt_heading,
					'level'   => $level,
					'classes' => 'mb-6',
				)
			);
		}

		// Plan names step one level below the section heading (A11Y01).
		$card_level = min( 6, $level + 1 );

		// Grid caps at 3 columns; 1 plan is a valid static single-card grid.
		$count          = count( $salt_plans );
		$column_classes = array(
			1 => 'sm:max-w-md',
			2 => 'sm:grid-cols-2',
			3 => 'sm:grid-cols-2 lg:grid-cols-3',
		);

		printf( '<div class="x-pricing-grid grid gap-6 pt-3 %s">', esc_attr( $column_classes[ min( 3, $count ) ] ) );
		foreach ( $salt_plans as $plan ) {
			salt_component(
				'card-pricing',
				array(
					'name'     => $plan['name'],
					'price'    => $plan['price'],
					'period'   => $plan['period'],
					'features' => $plan['features'],
					'cta'      => $plan['cta'],
					'featured' => $plan['featured'],
					'level'    => $card_level,
				)
			);
		}
		echo '</div>';
	}
);
