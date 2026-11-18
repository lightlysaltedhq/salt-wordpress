<?php
/**
 * Single work item (spec §6, case-study build-out) — hero (work_type eyebrow
 * → H1 → summary lede → client/services meta + live link), featured image,
 * challenge/solution, results stats (x-stat), gallery grid + CreativeWork
 * schema.
 *
 * Reads salt-contract's case study (collections/case-studies.json): the
 * title, topics (the work_type terms), summary (the excerpt) and
 * featuredImage (the featured image) from WordPress, and client, gallery,
 * challenge, solution, results, services and externalUrl from the
 * generated group. A case study has no page body. details is stored but
 * not drawn yet (M4).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) {
	the_post();

	$salt_work_id   = (int) get_the_ID();
	$salt_permalink = (string) get_permalink();

	$salt_client       = (string) get_field( 'client', $salt_work_id );
	$salt_summary      = salt_post_summary( $salt_work_id );
	$salt_challenge    = (string) get_field( 'challenge', $salt_work_id );
	$salt_solution     = (string) get_field( 'solution', $salt_work_id );
	$salt_external_url = (string) get_field( 'externalUrl', $salt_work_id );

	$salt_results = get_field( 'results', $salt_work_id );
	$salt_results = is_array( $salt_results ) ? $salt_results : array();

	$salt_gallery = get_field( 'gallery', $salt_work_id );
	$salt_gallery = is_array( $salt_gallery ) ? array_map( 'intval', $salt_gallery ) : array();

	$salt_service_ids = get_field( 'services', $salt_work_id );
	$salt_service_ids = is_array( $salt_service_ids ) ? array_map( 'intval', $salt_service_ids ) : array();

	$salt_work_types = get_the_terms( $salt_work_id, 'work_type' );
	$salt_work_types = is_array( $salt_work_types ) ? $salt_work_types : array();

	// ------------------------------------------------------------------
	// Hero.
	// ------------------------------------------------------------------
	echo '<header class="x-work-hero bg-surface-alt py-14 md:py-20">';
	echo '<div class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8">';

	if ( array() !== $salt_work_types ) {
		salt_component(
			'eyebrow',
			array(
				'text'    => implode( ' · ', wp_list_pluck( $salt_work_types, 'name' ) ),
				'classes' => 'mb-3',
			)
		);
	}

	salt_the_document_h1( get_the_title(), 'x-heading text-4xl md:text-5xl font-bold tracking-tight' );

	if ( '' !== trim( $salt_summary ) ) {
		printf(
			'<p class="x-work-summary mt-4 max-w-prose text-lg text-ink-muted">%s</p>',
			esc_html( $salt_summary )
		);
	}

	// Fact strip: client / services (linked) / live link.
	$salt_facts = array();
	if ( '' !== trim( $salt_client ) ) {
		$salt_facts[] = array( __( 'Client', 'salt' ), esc_html( $salt_client ) );
	}
	if ( array() !== $salt_service_ids ) {
		$salt_service_links = array();
		foreach ( $salt_service_ids as $salt_service_id ) {
			$salt_service_title = (string) get_the_title( $salt_service_id );
			if ( '' === $salt_service_title ) {
				continue;
			}
			$salt_service_links[] = sprintf(
				'<a class="text-brand hover:text-brand-strong" href="%s">%s</a>',
				esc_url( (string) get_permalink( $salt_service_id ) ),
				esc_html( $salt_service_title )
			);
		}
		if ( array() !== $salt_service_links ) {
			$salt_facts[] = array( __( 'Services', 'salt' ), implode( ', ', $salt_service_links ) );
		}
	}

	if ( array() !== $salt_facts ) {
		echo '<dl class="x-work-facts mt-8 flex flex-wrap gap-x-10 gap-y-4 text-sm">';
		foreach ( $salt_facts as $salt_fact ) {
			echo '<div class="flex flex-col gap-1">';
			echo '<dt class="font-semibold uppercase tracking-widest text-ink-muted">' . esc_html( $salt_fact[0] ) . '</dt>';
			echo '<dd class="text-ink">' . $salt_fact[1] . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where each fact value is built above.
			echo '</div>';
		}
		echo '</dl>';
	}

	if ( '' !== trim( $salt_external_url ) ) {
		echo '<div class="mt-8">';
		salt_component(
			'button',
			array(
				'label'  => __( 'Visit live site', 'salt' ),
				'url'    => $salt_external_url,
				'style'  => 'secondary',
				'target' => '_blank',
			)
		);
		echo '</div>';
	}

	echo '</div>';
	echo '</header>';

	// ------------------------------------------------------------------
	// Featured image — standardised crop, THE LCP candidate.
	// ------------------------------------------------------------------
	$salt_thumbnail_id = (int) get_post_thumbnail_id( $salt_work_id );
	if ( $salt_thumbnail_id > 0 ) {
		echo '<div class="x-work-media mx-auto w-full max-w-5xl px-4 pt-10 sm:px-6 lg:px-8 md:pt-14">';
		salt_component(
			'image',
			array(
				'image_id'      => $salt_thumbnail_id,
				'alt'           => '',
				'priority'      => true,
				'sizes_context' => 'hero',
				'classes'       => 'aspect-[16/9] w-full rounded-lg object-cover',
			)
		);
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// Challenge / solution.
	// ------------------------------------------------------------------
	if ( '' !== trim( $salt_challenge ) || '' !== trim( $salt_solution ) ) {
		echo '<div class="mx-auto w-full max-w-5xl px-4 pb-14 sm:px-6 lg:px-8 md:pb-20">';
		echo '<div class="grid gap-10 md:grid-cols-2">';
		foreach ( array(
			array( __( 'The challenge', 'salt' ), $salt_challenge, 'field_salt_collection_case_studies_challenge' ),
			array( __( 'The solution', 'salt' ), $salt_solution, 'field_salt_collection_case_studies_solution' ),
		) as $salt_block ) {
			if ( '' === trim( $salt_block[1] ) ) {
				continue;
			}
			echo '<div class="x-work-block">';
			salt_component(
				'heading',
				array(
					'text'    => $salt_block[0],
					'level'   => 2,
					'size'    => 'md',
					'classes' => 'mb-4',
				)
			);
			echo '<div class="x-prose">' . salt_sanitize_html( $salt_block[1], salt_field_allowed( $salt_block[2] ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised to the field's contract list.
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// Results — x-stat strip.
	// ------------------------------------------------------------------
	$salt_stats = array();
	foreach ( $salt_results as $salt_row ) {
		$salt_stat  = is_array( $salt_row ) ? trim( (string) ( $salt_row['stat'] ?? '' ) ) : '';
		$salt_label = is_array( $salt_row ) ? trim( (string) ( $salt_row['label'] ?? '' ) ) : '';
		if ( '' === $salt_stat ) {
			continue;
		}
		$salt_stats[] = array( $salt_stat, $salt_label );
	}
	if ( array() !== $salt_stats ) {
		echo '<div class="x-work-results bg-surface-alt py-14 md:py-20">';
		echo '<div class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8">';
		salt_component(
			'heading',
			array(
				'text'    => __( 'Results', 'salt' ),
				'level'   => 2,
				'size'    => 'lg',
				'classes' => 'mb-8',
			)
		);
		// Literal class strings only — Tailwind cannot detect concatenated names.
		$salt_stats_grid = array(
			2 => 'grid gap-8 sm:grid-cols-2',
			3 => 'grid gap-8 sm:grid-cols-3',
			4 => 'grid gap-8 sm:grid-cols-2 lg:grid-cols-4',
		);
		echo '<div class="' . esc_attr( $salt_stats_grid[ min( 4, max( 2, count( $salt_stats ) ) ) ] ) . '">';
		foreach ( $salt_stats as $salt_stat ) {
			salt_component(
				'stat',
				array(
					'number' => $salt_stat[0],
					'label'  => $salt_stat[1],
				)
			);
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// Gallery grid.
	// ------------------------------------------------------------------
	if ( array() !== $salt_gallery ) {
		echo '<div class="x-work-gallery mx-auto w-full max-w-5xl px-4 py-14 sm:px-6 lg:px-8 md:py-20">';
		echo '<div class="grid gap-6 sm:grid-cols-2">';
		foreach ( $salt_gallery as $salt_image_id ) {
			if ( $salt_image_id <= 0 ) {
				continue;
			}
			salt_component(
				'image',
				array(
					'image_id'      => $salt_image_id,
					'alt'           => '',
					'sizes_context' => 'half',
					'classes'       => 'aspect-[4/3] w-full rounded-lg object-cover',
				)
			);
		}
		echo '</div>';
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// CreativeWork schema (queued into the single page @graph — SEO §15).
	// ------------------------------------------------------------------
	$salt_node = array(
		'@type'            => 'CreativeWork',
		'@id'              => $salt_permalink . '#creativework',
		'name'             => (string) get_the_title(),
		'url'              => $salt_permalink,
		'author'           => array( '@id' => home_url( '/' ) . '#organization' ),
		'mainEntityOfPage' => array( '@id' => $salt_permalink . '#webpage' ),
	);
	if ( '' !== trim( $salt_summary ) ) {
		$salt_node['description'] = $salt_summary;
	}
	if ( $salt_thumbnail_id > 0 ) {
		$salt_image_url = wp_get_attachment_image_url( $salt_thumbnail_id, 'large' );
		if ( is_string( $salt_image_url ) && '' !== $salt_image_url ) {
			$salt_node['image'] = $salt_image_url;
		}
	}
	salt_schema_add( $salt_node );
}

get_footer();
