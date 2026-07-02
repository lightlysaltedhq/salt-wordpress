<?php
/**
 * Section: Logo / Trust Strip (spec §10 #9).
 *
 * A flex row of small client/press logos rendered through x-image (the
 * single image authority) at an explicit small size (sizes_context 'card',
 * height-capped utility classes). Logos with a URL are wrapped in a plain
 * <a> — external hrefs are explicitly allowed for logo links (a LINK, not
 * a loaded asset: PRIV01 holds — nothing loads from a foreign origin
 * unless the visitor clicks).
 *
 * "AS FEATURED IN" HOME (§18): the spec deliberately ships NO separate
 * press strip section — press/"as featured in" bands use THIS section
 * (heading e.g. "As featured in", logos linking to the coverage).
 *
 * Alt text: the repeater 'name' feeds the image alt ONLY when the
 * attachment has no stored alt (x-image's precedence: an explicit
 * non-empty alt wins; an empty alt arg falls back to the stored alt).
 * With neither, the logo is decorative (alt="").
 *
 * Greyscale: the 'grayscale' utility + hover:grayscale-0 (pure CSS — no
 * JS in this section at all).
 *
 * STATES (COMPL02): 0 resolvable logos => renders NOTHING (never a blank
 * band); broken rows (missing/unresolvable attachment) are skipped +
 * logged (DATA05). 1 logo => the same static flex row (inherently
 * JS-free). Headings are optional and usually absent — has_heading tracks
 * the field (A11Y01).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_heading   = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';
$salt_greyscale = function_exists( 'get_sub_field' ) ? (bool) get_sub_field( 'greyscale' ) : false;

$salt_rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'logos' ) : null;
$salt_rows = is_array( $salt_rows ) ? $salt_rows : array();

// Normalise to renderable logos; broken rows skipped + logged (DATA05).
$salt_logos = array();
foreach ( $salt_rows as $salt_row ) {
	if ( ! is_array( $salt_row ) ) {
		salt_log( 'section logos: malformed logo row — skipped.' );
		continue;
	}

	$salt_raw_image = $salt_row['image'] ?? 0;
	$salt_image_id  = is_array( $salt_raw_image ) ? (int) ( $salt_raw_image['ID'] ?? 0 ) : (int) $salt_raw_image;
	if ( $salt_image_id <= 0 ) {
		salt_log( 'section logos: logo row has no image — skipped (broken row).' );
		continue;
	}

	$salt_data = salt_image_data( $salt_image_id, 'card' );
	if ( null === $salt_data ) {
		continue; // Unresolvable attachment: salt_image_data() already logged (DATA05).
	}

	$salt_name = trim( (string) ( $salt_row['name'] ?? '' ) );

	$salt_logos[] = array(
		'image_id' => $salt_image_id,
		'url'      => trim( (string) ( $salt_row['url'] ?? '' ) ),
		'name'     => $salt_name,
		// 'name' feeds alt ONLY when the attachment alt is empty — an
		// explicit non-empty alt arg beats the stored alt in x-image.
		'alt'      => '' === trim( (string) $salt_data['alt'] ) ? $salt_name : '',
	);
}

if ( array() === $salt_logos ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'logos', $args ?? array() ),
		// Usually heading-less: never claims the page H1 without one (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_heading, $salt_logos, $salt_greyscale ): void {
		if ( '' !== trim( $salt_heading ) ) {
			salt_component(
				'heading',
				array(
					'text'    => $salt_heading,
					'level'   => $level,
					'size'    => 'sm',
					'classes' => 'mb-6 text-ink-muted',
				)
			);
		}

		// Explicit small rendering: height-capped, width auto — a logo
		// never renders at its intrinsic (potentially huge) size.
		$salt_image_classes = salt_classnames(
			array(
				'h-10 md:h-12 w-auto object-contain',
				$salt_greyscale ? 'grayscale transition hover:grayscale-0' : '',
			)
		);

		echo '<ul class="x-logos flex flex-wrap items-center justify-center gap-x-10 gap-y-6" data-section-component="logos">';

		foreach ( $salt_logos as $salt_logo ) {
			$salt_img = salt_get_component(
				'image',
				array(
					'image_id'      => $salt_logo['image_id'],
					'alt'           => $salt_logo['alt'],
					'sizes_context' => 'card',
					'classes'       => $salt_image_classes,
				)
			);

			echo '<li class="x-logos-item flex items-center">';
			if ( '' !== $salt_logo['url'] ) {
				// External logo links are allowed (plain <a> — see docblock).
				printf(
					'<a href="%1$s"%2$s>%3$s</a>',
					esc_url( $salt_logo['url'] ),
					'' !== $salt_logo['name']
						? ' aria-label="' . esc_attr( $salt_logo['name'] ) . '"'
						: '',
					$salt_img // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- x-image escapes internally.
				);
			} else {
				echo $salt_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- x-image escapes internally.
			}
			echo '</li>';
		}

		echo '</ul>';
	}
);
