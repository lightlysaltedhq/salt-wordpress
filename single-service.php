<?php
/**
 * Single service (spec §6, service-page build-out) — hero (icon + category
 * eyebrow → H1 → summary lede → price-from), benefits checklist, the
 * introduction, CTA band, related-services grid + Service schema.
 *
 * Reads salt-contract's service (collections/services.json): the title,
 * topics (the service_category terms), summary (the excerpt), intro (the
 * body) and featuredImage (the featured image) from WordPress, and icon,
 * benefits, priceFrom, relatedServices and cta from the generated group.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) {
	the_post();

	$salt_service_id = (int) get_the_ID();
	$salt_permalink  = (string) get_permalink();

	$salt_icon       = (string) get_field( 'icon', $salt_service_id );
	$salt_summary    = salt_post_summary( $salt_service_id );
	$salt_price_from = trim( (string) get_field( 'priceFrom', $salt_service_id ) );

	$salt_benefits = get_field( 'benefits', $salt_service_id );
	$salt_benefits = is_array( $salt_benefits ) ? $salt_benefits : array();

	$salt_related_ids = get_field( 'relatedServices', $salt_service_id );
	$salt_related_ids = is_array( $salt_related_ids ) ? array_map( 'intval', $salt_related_ids ) : array();
	// Never the service itself (collections/services.json, relatedServices).
	$salt_related_ids = array_values( array_diff( $salt_related_ids, array( $salt_service_id ) ) );

	// The contract's link (label, type, document, url, newTab); null when unusable.
	$salt_cta = salt_contract_link( get_field( 'cta', $salt_service_id ) );

	$salt_categories = get_the_terms( $salt_service_id, 'service_category' );
	$salt_categories = is_array( $salt_categories ) ? $salt_categories : array();

	// ------------------------------------------------------------------
	// Hero.
	// ------------------------------------------------------------------
	echo '<header class="x-service-hero bg-surface-alt py-14 md:py-20">';
	echo '<div class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8">';

	if ( '' !== trim( $salt_icon ) && '' !== salt_icon_svg( $salt_icon ) ) {
		echo '<div class="x-service-icon mb-6 inline-flex size-14 items-center justify-center rounded-lg bg-brand-tint text-brand">';
		salt_component(
			'icon',
			array(
				'name' => $salt_icon,
				'size' => 'lg',
			)
		);
		echo '</div>';
	}

	if ( array() !== $salt_categories ) {
		salt_component(
			'eyebrow',
			array(
				'text'    => implode( ' · ', wp_list_pluck( $salt_categories, 'name' ) ),
				'classes' => 'mb-3',
			)
		);
	}

	salt_the_document_h1( (string) get_the_title(), 'x-heading text-4xl md:text-5xl font-bold tracking-tight' );

	if ( '' !== trim( $salt_summary ) ) {
		printf(
			'<p class="x-service-summary mt-4 max-w-prose text-lg text-ink-muted">%s</p>',
			esc_html( $salt_summary )
		);
	}

	// priceFrom is a display value, drawn as written (£500, POA).
	if ( '' !== $salt_price_from ) {
		printf(
			'<p class="x-service-price mt-6 text-sm font-semibold uppercase tracking-widest text-ink-muted">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: the starting price as the editor wrote it, such as £500. */
					__( 'Projects from %s', 'salt' ),
					$salt_price_from
				)
			)
		);
	}

	echo '</div>';
	echo '</header>';

	// ------------------------------------------------------------------
	// Benefits checklist.
	// ------------------------------------------------------------------
	$salt_benefit_items = array();
	foreach ( $salt_benefits as $salt_row ) {
		$salt_benefit = is_array( $salt_row ) ? trim( (string) ( $salt_row['text'] ?? '' ) ) : '';
		if ( '' !== $salt_benefit ) {
			$salt_benefit_items[] = $salt_benefit;
		}
	}
	if ( array() !== $salt_benefit_items ) {
		echo '<div class="x-service-benefits mx-auto w-full max-w-5xl px-4 pt-14 sm:px-6 lg:px-8 md:pt-20">';
		salt_component(
			'heading',
			array(
				'text'    => __( 'What you get', 'salt' ),
				'level'   => 2,
				'size'    => 'md',
				'classes' => 'mb-6',
			)
		);
		echo '<ul class="grid gap-3 sm:grid-cols-2">';
		foreach ( $salt_benefit_items as $salt_benefit ) {
			echo '<li class="flex items-start gap-3"><span class="mt-0.5 shrink-0 text-brand">';
			salt_component(
				'icon',
				array(
					'name' => 'check',
					'size' => 'sm',
				)
			);
			echo '</span><span>' . esc_html( $salt_benefit ) . '</span></li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// The introduction (the contract's intro, stored in the body).
	// ------------------------------------------------------------------
	if ( '' !== trim( (string) get_post_field( 'post_content', $salt_service_id ) ) ) {
		echo '<div class="mx-auto w-full max-w-3xl px-4 py-14 sm:px-6 lg:px-8 md:py-20">';
		echo '<div class="x-prose">';
		// The intro allows only its contract list (SALT_SERVICE_INTRO_ALLOWED).
		ob_start();
		the_content();
		echo salt_sanitize_html( (string) ob_get_clean(), SALT_SERVICE_INTRO_ALLOWED ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised to the intro's contract list.
		echo '</div>';
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// CTA band.
	// ------------------------------------------------------------------
	if ( null !== $salt_cta ) {
		echo '<div class="x-service-cta bg-brand-tint py-14 md:py-20">';
		echo '<div class="mx-auto w-full max-w-5xl px-4 text-center sm:px-6 lg:px-8">';
		salt_component(
			'button',
			array(
				'label'  => $salt_cta['label'],
				'url'    => $salt_cta['url'],
				'style'  => 'primary',
				'target' => $salt_cta['target'],
			)
		);
		echo '</div>';
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// Related services grid.
	// ------------------------------------------------------------------
	if ( array() !== $salt_related_ids ) {
		echo '<div class="x-service-related mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8 md:py-20">';
		salt_component(
			'heading',
			array(
				'text'    => __( 'Related services', 'salt' ),
				'level'   => 2,
				'size'    => 'md',
				'classes' => 'mb-6',
			)
		);
		echo '<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">';
		foreach ( $salt_related_ids as $salt_related_id ) {
			$salt_related_title = (string) get_the_title( $salt_related_id );
			if ( '' === $salt_related_title ) {
				continue;
			}
			$salt_related_icon = get_field( 'icon', $salt_related_id );
			salt_component(
				'card-service',
				array(
					'title'    => $salt_related_title,
					'url'      => (string) get_permalink( $salt_related_id ),
					'summary'  => salt_post_summary( $salt_related_id ),
					'icon'     => is_string( $salt_related_icon ) ? $salt_related_icon : '',
					'image_id' => (int) get_post_thumbnail_id( $salt_related_id ),
					'level'    => 3,
				)
			);
		}
		echo '</div>';
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// Service schema (queued into the single page @graph — SEO §15).
	// ------------------------------------------------------------------
	$salt_node = array(
		'@type'            => 'Service',
		'@id'              => $salt_permalink . '#service',
		'name'             => (string) get_the_title(),
		'url'              => $salt_permalink,
		'provider'         => array( '@id' => home_url( '/' ) . '#organization' ),
		'mainEntityOfPage' => array( '@id' => $salt_permalink . '#webpage' ),
	);
	if ( '' !== trim( $salt_summary ) ) {
		$salt_node['description'] = $salt_summary;
	}
	salt_schema_add( $salt_node );
}

get_footer();
