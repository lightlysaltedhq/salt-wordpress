<?php
/**
 * Resolver: Pricing (spec §10 #11) — the logic half of the section; the
 * view is template-parts/sections/pricing.php.
 *
 * Fields: eyebrow, heading, plans [name, price, period, features
 * repeater(text), cta link, featured].
 *
 * States (COMPL02): 0 valid plans => null (renders NOTHING, no band); 1
 * plan => valid static single-card grid; N => full grid. Plans missing a
 * name are skipped and logged. Multiple featured plans: only the FIRST is
 * honoured, the rest are demoted + logged.
 *
 * NO SCHEMA in v1 (Offer/PriceSpecification deferred — prices are display
 * strings, see inc/fields/layouts/pricing.php).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current pricing Flexible Content row to its view props.
 *
 * Props (contract view-props/pricing.json; WordPress-local names in snake_case):
 *   eyebrow  string|null  Contract `eyebrow`. Null when blank. TRIMMED, as
 *                         before the split (this section always trimmed it).
 *   heading  string|null  Contract `heading`. Null when blank. Trimmed, as
 *                         eyebrow.
 *   plans    array<int, array{name: string, price: string, period: string,
 *                             features: string[], cta: array{label?: string, url?: string},
 *                             featured: bool}>
 *                         Contract `plans` (pricing-plan), in row order;
 *                         never empty; at most one featured. Each plan is in
 *                         x-card-pricing's args shape and passed to it as is:
 *                         name trimmed and never empty; price/period trimmed,
 *                         '' for none; features the non-empty trimmed feature
 *                         texts; cta array{label, url} when the link has a
 *                         URL (label defaults to "Choose plan"), else array().
 *
 * Naming gaps against the contract: plan `price`/`period` are '' rather than
 * null; a feature is a plain string, not plan-feature {text, included} (no
 * `included` field); plan `cta` is {label, url} or array(), not a nullable
 * link {href, label, newTab}.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_pricing( array $context ): ?array {
	$sub = static function ( string $field ): string {
		if ( ! function_exists( 'get_sub_field' ) ) {
			return '';
		}
		$value = get_sub_field( $field );
		return is_string( $value ) ? $value : '';
	};

	$eyebrow = trim( $sub( 'eyebrow' ) );
	$heading = trim( $sub( 'heading' ) );

	$rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'plans' ) : array();
	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	// Normalise plan rows, skipping malformed entries (logged — COMPL02).
	$plans        = array();
	$has_featured = false;
	foreach ( $rows as $i => $row ) {
		if ( ! is_array( $row ) ) {
			salt_log( sprintf( 'pricing section: plan %d is not a row array — skipped (broken row).', (int) $i ) );
			continue;
		}

		$name = is_string( $row['name'] ?? null ) ? trim( $row['name'] ) : '';
		if ( '' === $name ) {
			salt_log( sprintf( 'pricing section: plan %d has no name — skipped (broken row).', (int) $i ) );
			continue;
		}

		// Features repeater rows => plain strings (x-card-pricing re-validates).
		$features     = array();
		$feature_rows = is_array( $row['features'] ?? null ) ? $row['features'] : array();
		foreach ( $feature_rows as $feature_row ) {
			if ( is_array( $feature_row ) && is_string( $feature_row['text'] ?? null ) && '' !== trim( $feature_row['text'] ) ) {
				$features[] = trim( $feature_row['text'] );
			}
		}

		// ACF link array => the card's cta { label, url }.
		$link = is_array( $row['cta'] ?? null ) ? $row['cta'] : array();
		$url  = is_string( $link['url'] ?? null ) ? trim( $link['url'] ) : '';
		$cta  = array();
		if ( '' !== $url ) {
			$label = is_string( $link['title'] ?? null ) ? trim( $link['title'] ) : '';
			$cta   = array(
				'label' => '' !== $label ? $label : __( 'Choose plan', 'salt' ),
				'url'   => $url,
			);
		}

		// Only the FIRST featured plan is honoured (one "Most popular" badge).
		$featured = (bool) ( $row['featured'] ?? false );
		if ( $featured && $has_featured ) {
			salt_log( sprintf( 'pricing section: plan %d ("%s") also flagged featured — only the first featured plan is honoured.', (int) $i, $name ) );
			$featured = false;
		}
		$has_featured = $has_featured || $featured;

		$plans[] = array(
			'name'     => $name,
			'price'    => is_string( $row['price'] ?? null ) ? trim( $row['price'] ) : '',
			'period'   => is_string( $row['period'] ?? null ) ? trim( $row['period'] ) : '',
			'features' => $features,
			'cta'      => $cta,
			'featured' => $featured,
		);
	}

	if ( array() === $plans ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'pricing', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== $heading )
		),
		'props'   => array(
			'eyebrow' => salt_nullable_text( $eyebrow ),
			'heading' => salt_nullable_text( $heading ),
			'plans'   => $plans,
		),
	);
}
