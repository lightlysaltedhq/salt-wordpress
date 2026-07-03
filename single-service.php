<?php
/**
 * Single service (spec §6, service-page build-out) — hero (icon + category
 * eyebrow → H1 [headline override] → summary lede → price-from), benefits
 * checklist, editor content, CTA band, related-services grid + Service schema.
 *
 * Renders EVERY group_salt_service field: icon, summary, headline, benefits,
 * price_from, related_services, cta.
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
	$salt_summary    = (string) get_field( 'summary', $salt_service_id );
	$salt_headline   = (string) get_field( 'headline', $salt_service_id );
	$salt_price_from = get_field( 'price_from', $salt_service_id );
	$salt_price_from = is_numeric( $salt_price_from ) ? (float) $salt_price_from : 0.0;

	$salt_benefits = get_field( 'benefits', $salt_service_id );
	$salt_benefits = is_array( $salt_benefits ) ? $salt_benefits : array();

	$salt_related_ids = get_field( 'related_services', $salt_service_id );
	$salt_related_ids = is_array( $salt_related_ids ) ? array_map( 'intval', $salt_related_ids ) : array();

	$salt_cta     = get_field( 'cta', $salt_service_id );
	$salt_cta_url = is_array( $salt_cta ) ? trim( (string) ( $salt_cta['url'] ?? '' ) ) : '';
	$salt_cta_txt = is_array( $salt_cta ) ? trim( (string) ( $salt_cta['title'] ?? '' ) ) : '';

	$salt_categories = get_the_terms( $salt_service_id, 'service_category' );
	$salt_categories = is_array( $salt_categories ) ? $salt_categories : array();

	// headline is the documented H1 override; the post title backstops it.
	$salt_h1 = '' !== trim( $salt_headline ) ? $salt_headline : (string) get_the_title();

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

	salt_the_document_h1( $salt_h1, 'x-heading text-4xl md:text-5xl font-bold tracking-tight' );

	if ( '' !== trim( $salt_summary ) ) {
		printf(
			'<p class="x-service-summary mt-4 max-w-prose text-lg text-ink-muted">%s</p>',
			esc_html( $salt_summary )
		);
	}

	if ( $salt_price_from > 0 ) {
		printf(
			'<p class="x-service-price mt-6 text-sm font-semibold uppercase tracking-widest text-ink-muted">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: formatted starting price. */
					__( 'Projects from £%s', 'salt' ),
					number_format_i18n( $salt_price_from )
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
		$salt_benefit = is_array( $salt_row ) ? trim( (string) ( $salt_row['benefit'] ?? '' ) ) : '';
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
	// Editor content.
	// ------------------------------------------------------------------
	if ( '' !== trim( (string) get_post_field( 'post_content', $salt_service_id ) ) ) {
		echo '<div class="mx-auto w-full max-w-3xl px-4 py-14 sm:px-6 lg:px-8 md:py-20">';
		echo '<div class="x-prose">';
		the_content();
		echo '</div>';
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// CTA band.
	// ------------------------------------------------------------------
	if ( '' !== $salt_cta_url && '' !== $salt_cta_txt ) {
		echo '<div class="x-service-cta bg-brand-tint py-14 md:py-20">';
		echo '<div class="mx-auto w-full max-w-5xl px-4 text-center sm:px-6 lg:px-8">';
		salt_component(
			'button',
			array(
				'label'  => $salt_cta_txt,
				'url'    => $salt_cta_url,
				'style'  => 'primary',
				'target' => is_array( $salt_cta ) ? (string) ( $salt_cta['target'] ?? '' ) : '',
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
			$salt_related_summary = get_field( 'summary', $salt_related_id );
			$salt_related_icon    = get_field( 'icon', $salt_related_id );
			salt_component(
				'card-service',
				array(
					'title'   => $salt_related_title,
					'url'     => (string) get_permalink( $salt_related_id ),
					'summary' => is_string( $salt_related_summary ) ? $salt_related_summary : '',
					'icon'    => is_string( $salt_related_icon ) ? $salt_related_icon : '',
					'level'   => 3,
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
