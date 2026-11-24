<?php
/**
 * Single service, as the contract draws it (salt-contract rc.8
 * markup/service.json): the article with its header (the title as the
 * document's h1, the summary), the introduction, the benefits named by
 * service__benefits-heading, the price after its label, the call to action
 * as a primary button without the arrow, and the related services as cards
 * named by service__related-heading; then the Service schema. The page
 * draws no icon and no image: the title names the service.
 *
 * Reads salt-contract's service (collections/services.json): the title,
 * summary (the excerpt) and intro (the body, drawn by
 * salt_service_intro_html(): paragraphs added and its contract list kept,
 * with no shortcodes, embeds or other the_content filters) from WordPress,
 * and benefits, priceFrom, relatedServices and cta from the generated group.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$salt_labels = salt_contract_labels( 'service' );

while ( have_posts() ) {
	the_post();

	$salt_service_id = (int) get_the_ID();
	$salt_permalink  = (string) get_permalink();
	$salt_summary    = salt_post_summary( $salt_service_id );
	$salt_price_from = trim( (string) get_field( 'priceFrom', $salt_service_id ) );
	$salt_intro      = salt_service_intro_html( (string) get_post_field( 'post_content', $salt_service_id, 'raw' ) );

	$salt_benefits = array();
	foreach ( (array) get_field( 'benefits', $salt_service_id ) as $salt_row ) {
		$salt_benefit = is_array( $salt_row ) ? trim( (string) ( $salt_row['text'] ?? '' ) ) : '';
		if ( '' !== $salt_benefit ) {
			$salt_benefits[] = $salt_benefit;
		}
	}

	// Never the service itself (collections/services.json, relatedServices).
	$salt_related_ids = get_field( 'relatedServices', $salt_service_id );
	$salt_related_ids = is_array( $salt_related_ids ) ? array_map( 'intval', $salt_related_ids ) : array();
	$salt_related     = array();
	$salt_card_sizes  = salt_image_slot_sizes( 'card', 'full', '3' );
	foreach ( array_diff( $salt_related_ids, array( $salt_service_id ) ) as $salt_related_id ) {
		$salt_related_post = get_post( $salt_related_id );
		$salt_card         = $salt_related_post instanceof WP_Post && 'publish' === $salt_related_post->post_status ? salt_contract_view_card( $salt_related_post, 'services', $salt_card_sizes ) : null;
		if ( null !== $salt_card ) {
			$salt_related[] = $salt_card;
		}
	}

	// The contract's link (label, type, document, url, newTab); null when unusable.
	$salt_cta = salt_contract_link( get_field( 'cta', $salt_service_id ) );

	echo '<article class="salt-service">';
	echo '<header class="salt-service__header">';
	salt_the_document_h1( salt_view_title( $salt_service_id ) );
	if ( '' !== trim( $salt_summary ) ) {
		echo '<p class="salt-service__summary">' . esc_html( $salt_summary ) . '</p>';
	}
	echo '</header>';

	if ( salt_rich_text_has_words( $salt_intro ) ) {
		echo '<div class="salt-service__intro"><div class="salt-rich-text">' . $salt_intro . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised to the intro's contract list.
	}

	if ( array() !== $salt_benefits ) {
		echo '<section class="salt-service__benefits" aria-labelledby="service__benefits-heading">';
		echo '<h2 id="service__benefits-heading">' . esc_html( (string) $salt_labels['benefits'] ) . '</h2>';
		echo '<ul role="list">';
		foreach ( $salt_benefits as $salt_benefit ) {
			echo '<li>' . esc_html( $salt_benefit ) . '</li>';
		}
		echo '</ul></section>';
	}

	// priceFrom is a display value, drawn as written after the label (£500, POA).
	if ( '' !== $salt_price_from ) {
		echo '<p class="salt-service__price"><span class="salt-service__price-label">' . esc_html( (string) $salt_labels['price'] ) . '</span> ' . esc_html( $salt_price_from ) . '</p>';
	}

	if ( null !== $salt_cta ) {
		echo '<p class="salt-service__cta">';
		salt_component(
			'contract-button',
			array(
				'button' => array(
					'label'  => $salt_cta['label'],
					'href'   => salt_contract_url( $salt_cta['url'] ),
					'style'  => 'primary',
					'newTab' => '_blank' === $salt_cta['target'],
				),
				'arrow'  => null,
			)
		);
		echo '</p>';
	}

	if ( array() !== $salt_related ) {
		echo '<section class="salt-related" aria-labelledby="service__related-heading">';
		echo '<h2 id="service__related-heading">' . esc_html( (string) $salt_labels['relatedServices'] ) . '</h2>';
		echo '<ul class="salt-grid salt-showcase salt-related__list" role="list" data-layout="grid">';
		foreach ( $salt_related as $salt_card ) {
			echo '<li>';
			salt_component(
				'contract-card',
				array(
					'card'   => $salt_card,
					'style'  => 'generic',
					'level'  => 3,
					'labels' => $salt_labels,
					'arrow'  => salt_contract_arrow(),
				)
			);
			echo '</li>';
		}
		echo '</ul></section>';
	}
	echo '</article>';

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
