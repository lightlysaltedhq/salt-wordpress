<?php
/**
 * Media helpers: the sizes-attribute map and attachment resolution behind
 * x-image (spec §8; review AMBIG01 — x-image is THE single authority for
 * image markup; ARCH07 — salt_image_data() is pluggable; DATA05 — a broken
 * attachment ID degrades loudly, never into a broken <img>).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FINAL sizes-attribute map for x-image rendering contexts (spec §8).
 *
 * The map is frozen contract: changing a value changes rendered markup on
 * every fleet site (snapshot-gated, FLEET01). Unknown contexts degrade to
 * '100vw' — logged, never blank (FAIL01).
 *
 * @param string $context hero|full|half|card|gallery.
 * @return string The sizes attribute value.
 */
function salt_image_sizes( string $context ): string {
	$map = array(
		'hero'    => '100vw',
		'full'    => '100vw',
		'half'    => '(min-width: 768px) 50vw, 100vw',
		'card'    => '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw',
		'gallery' => '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw',
	);

	if ( ! isset( $map[ $context ] ) ) {
		salt_log( sprintf( 'salt_image_sizes: unknown context "%s" — using 100vw.', $context ) );
		return '100vw';
	}

	return $map[ $context ];
}

if ( ! function_exists( 'salt_image_data' ) ) {
	/**
	 * Resolve an attachment into the data x-image needs (PLUGGABLE — ARCH07).
	 *
	 * A child theme (or an offload/CDN plugin's child integration) may replace
	 * this to resolve src/srcset from another source; the returned shape is
	 * the frozen contract x-image consumes.
	 *
	 * Returns null when the attachment cannot be resolved (broken ID — DATA05
	 * spirit): logged, and x-image renders a comment instead of a broken img.
	 *
	 * @param int    $image_id Attachment ID.
	 * @param string $context  Sizes context (salt_image_sizes()).
	 * @return array{src: string, srcset: string, sizes: string, width: int, height: int, alt: string}|null
	 */
	function salt_image_data( int $image_id, string $context ): ?array {
		$src = wp_get_attachment_image_src( $image_id, 'full' );

		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			salt_log( sprintf( 'salt_image_data: attachment %d could not be resolved (DATA05).', $image_id ) );
			return null;
		}

		$srcset = wp_get_attachment_image_srcset( $image_id, 'full' );
		$alt    = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		return array(
			'src'    => (string) $src[0],
			'srcset' => is_string( $srcset ) ? $srcset : '',
			'sizes'  => salt_image_sizes( $context ),
			'width'  => (int) ( $src[1] ?? 0 ),
			'height' => (int) ( $src[2] ?? 0 ),
			'alt'    => is_string( $alt ) ? $alt : '',
		);
	}
}

// ---------------------------------------------------------------------------
// The contract's image slots (salt-contract contract/image-sizes.json, SC-016).
// Drawn by the sections whose markup is the contract's (collection-showcase,
// carousel); x-image above keeps its own sizes until each section moves.
// ---------------------------------------------------------------------------

/**
 * The contract's candidate widths, ascending (contract/image-sizes.json
 * `candidates`). Each is registered as an uncropped image size named
 * salt-<width>, so WordPress makes it for every upload wider than it.
 *
 * @return int[]
 */
function salt_image_candidates(): array {
	return array( 32, 48, 64, 96, 128, 256, 384, 640, 750, 828, 1080, 1200, 1920, 2048, 3840 );
}

/**
 * The image slots the contract's sections here draw, copied from
 * contract/image-sizes.json `slots` (tests/conformance/ImageSlotsContractTest
 * holds the copy to the pinned file): each slot's unbanded sizes, and per
 * band (a section's width setting; a card's also per column count).
 *
 * @return array<string,array{sizes: string, bands?: array<string,string|array<string,string>>}>
 */
function salt_image_slots(): array {
	$card_default = array(
		'2' => '(min-width: 75rem) 29.25rem, (min-width: 64rem) 29.47rem, (min-width: 40.01rem) calc((100vw - 3.5rem) / 2), calc(100vw - 2rem)',
		'3' => '(min-width: 75rem) 19rem, (min-width: 64rem) 19.1467rem, (min-width: 48.01rem) calc((100vw - 5rem) / 3), (min-width: 40.01rem) calc((100vw - 3.5rem) / 2), calc(100vw - 2rem)',
		'4' => '(min-width: 75rem) 13.875rem, (min-width: 64rem) 13.985rem, (min-width: 48.01rem) calc((100vw - 6.5rem) / 4), (min-width: 40.01rem) calc((100vw - 3.5rem) / 2), calc(100vw - 2rem)',
	);
	$content      = '(min-width: 75rem) 60rem, (min-width: 64rem) 60.44rem, calc(100vw - 2rem)';
	$half         = '(min-width: 75rem) 29rem, (min-width: 64rem) 29.22rem, (min-width: 48.01rem) calc((100vw - 4rem) / 2), calc(100vw - 2rem)';

	return array(
		'full'       => array( 'sizes' => '100vw' ),
		'content'    => array(
			'sizes' => '(min-width: 64rem) 60rem, 100vw',
			'bands' => array(
				'full'    => 'calc(100vw)',
				'wide'    => '(min-width: 80rem) 76rem, calc(100vw - 2rem)',
				'default' => $content,
				'narrow'  => $content,
			),
		),
		'half'       => array(
			'sizes' => '(min-width: 64rem) 30rem, 100vw',
			'bands' => array(
				'full'    => '(min-width: 48.01rem) calc((100vw - 2rem) / 2), calc(100vw)',
				'wide'    => '(min-width: 80rem) 37rem, (min-width: 48.01rem) calc((100vw - 4rem) / 2), calc(100vw - 2rem)',
				'default' => $half,
				'narrow'  => $half,
			),
		),
		'card'       => array(
			'sizes' => '(min-width: 64rem) 19rem, (min-width: 40rem) 50vw, 100vw',
			'bands' => array(
				'full'    => array(
					'2' => '(min-width: 40.01rem) calc((100vw - 1.5rem) / 2), calc(100vw)',
					'3' => '(min-width: 48.01rem) calc((100vw - 3rem) / 3), (min-width: 40.01rem) calc((100vw - 1.5rem) / 2), calc(100vw)',
					'4' => '(min-width: 48.01rem) calc((100vw - 4.5rem) / 4), (min-width: 40.01rem) calc((100vw - 1.5rem) / 2), calc(100vw)',
				),
				'wide'    => array(
					'2' => '(min-width: 80rem) 37.25rem, (min-width: 40.01rem) calc((100vw - 3.5rem) / 2), calc(100vw - 2rem)',
					'3' => '(min-width: 80rem) 24.3333rem, (min-width: 48.01rem) calc((100vw - 5rem) / 3), (min-width: 40.01rem) calc((100vw - 3.5rem) / 2), calc(100vw - 2rem)',
					'4' => '(min-width: 80rem) 17.875rem, (min-width: 48.01rem) calc((100vw - 6.5rem) / 4), (min-width: 40.01rem) calc((100vw - 3.5rem) / 2), calc(100vw - 2rem)',
				),
				'default' => $card_default,
				'narrow'  => $card_default,
			),
		),
		'aside'      => array(
			'sizes' => '(min-width: 64rem) 23.83rem, 100vw',
			'bands' => array(
				'full'    => '(min-width: 64rem) 23.83rem, 100vw',
				'wide'    => '(min-width: 80rem) 30.5rem, (min-width: 64rem) calc((100vw - 2rem) * 5 / 12 - 2rem), 100vw',
				'default' => '(min-width: 64rem) 23.83rem, 100vw',
				'narrow'  => '(min-width: 64rem) 23.83rem, 100vw',
			),
		),
		'aside-card' => array( 'sizes' => '(min-width: 75rem) 19rem, (min-width: 64rem) 19.1467rem, (min-width: 48.01rem) calc((100vw - 3rem) / 3), (min-width: 40.01rem) calc((100vw - 1.5rem) / 2), calc(100vw)' ),
		'thumb'      => array( 'sizes' => '8rem' ),
	);
}

/**
 * An image slot's sizes for a band (contract/image-sizes.json): the band's
 * entry, and for a card the grid's column count; the slot's unbanded sizes
 * when no band is given. An unknown slot, band or count is logged and gives
 * the unbanded sizes, or 100vw for an unknown slot.
 *
 * @param string      $slot    full|content|card|thumb.
 * @param string|null $band    full|wide|default|narrow, or null for unbanded.
 * @param string|null $columns '2'|'3'|'4' for a banded card.
 * @return string
 */
function salt_image_slot_sizes( string $slot, ?string $band = null, ?string $columns = null ): string {
	$slots = salt_image_slots();
	if ( ! isset( $slots[ $slot ] ) ) {
		salt_log( sprintf( 'salt_image_slot_sizes: unknown slot "%s" — using 100vw.', $slot ) );
		return '100vw';
	}
	if ( null === $band || ! isset( $slots[ $slot ]['bands'] ) ) {
		return $slots[ $slot ]['sizes'];
	}

	$sizes = $slots[ $slot ]['bands'][ $band ] ?? null;
	if ( is_array( $sizes ) ) {
		$sizes = $sizes[ (string) $columns ] ?? null;
	}
	if ( ! is_string( $sizes ) ) {
		salt_log( sprintf( 'salt_image_slot_sizes: slot "%1$s" has no band "%2$s" for %3$s columns — using its unbanded sizes.', $slot, $band, (string) $columns ) );
		return $slots[ $slot ]['sizes'];
	}
	return $sizes;
}

/**
 * The srcset floor a sizes value gives (contract/image-sizes.json srcset):
 * the viewport floor (640) times the smallest bare viewport share in it (an
 * <n>vw term at its start or after white space, never inside calc()) / 100,
 * or 0 when it holds none.
 *
 * @param string $sizes A sizes attribute value.
 * @return float
 */
function salt_image_srcset_floor( string $sizes ): float {
	$depth  = 0;
	$shares = array();
	foreach ( preg_split( '/(\(|\)|(?<![^\s(])\d+(?:\.\d+)?vw\b)/', $sizes, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) as $token ) {
		if ( '(' === $token ) {
			++$depth;
		} elseif ( ')' === $token ) {
			--$depth;
		} elseif ( 0 === $depth && 1 === preg_match( '/^(\d+(?:\.\d+)?)vw$/', $token, $match ) ) {
			$shares[] = (float) $match[1];
		}
	}
	return array() === $shares ? 0.0 : 640 * min( $shares ) / 100;
}

/**
 * Register an uncropped image size for every contract candidate width, so
 * WordPress makes each one an upload is wider than (markup/media.json,
 * WordPress owes) ('after_setup_theme').
 *
 * @return void
 */
function salt_register_contract_image_sizes(): void {
	foreach ( salt_image_candidates() as $width ) {
		add_image_size( 'salt-' . $width, $width, 0, false );
	}
}
add_action( 'after_setup_theme', 'salt_register_contract_image_sizes' );

/**
 * Keep the candidate sizes out of every other image's srcset
 * ('wp_calculate_image_srcset'): registering them would otherwise add them
 * to the srcset WordPress writes for images the contract's slots do not
 * draw. A width that is also one of core's own sizes, or the upload's, stays.
 *
 * @param array<int,array<string,mixed>> $sources    Width => source.
 * @param array{0:int,1:int}             $size_array Drawn size.
 * @param string                         $image_src  The src.
 * @param array<string,mixed>            $image_meta Attachment metadata.
 * @return array<int,array<string,mixed>>
 */
function salt_image_srcset_without_candidates( $sources, $size_array, $image_src, $image_meta ) {
	unset( $size_array, $image_src );
	if ( ! is_array( $sources ) ) {
		return $sources;
	}
	$keep = array( (int) ( $image_meta['width'] ?? 0 ) => true );
	foreach ( (array) ( $image_meta['sizes'] ?? array() ) as $name => $size ) {
		if ( ! str_starts_with( (string) $name, 'salt-' ) ) {
			$keep[ (int) ( $size['width'] ?? 0 ) ] = true;
		}
	}
	foreach ( array_keys( $sources ) as $width ) {
		if ( ! isset( $keep[ $width ] ) && in_array( (int) $width, salt_image_candidates(), true ) ) {
			unset( $sources[ $width ] );
		}
	}
	return $sources;
}
add_filter( 'wp_calculate_image_srcset', 'salt_image_srcset_without_candidates', 10, 4 );

/**
 * The contract's srcset for an attachment and a slot's sizes
 * (contract/image-sizes.json srcset): ascending, each candidate width at or
 * above the sizes' floor and below the upload's width that WordPress made
 * (its salt-<width> sub-size), then the upload itself at its own width.
 *
 * An upload made before the theme registered its salt-<width> sizes has
 * none of them until its thumbnails are regenerated. Its core sub-sizes of
 * the upload's own proportions (medium, large and the rest; never a
 * cropped thumbnail) stand in, at or above the floor and below the upload's
 * width, so it is not served only at full size.
 *
 * @param int    $image_id Attachment ID.
 * @param string $src      The upload's URL.
 * @param int    $width    The upload's width.
 * @param string $sizes    The slot's sizes.
 * @return string
 */
function salt_image_contract_srcset( int $image_id, string $src, int $width, string $sizes ): string {
	$meta   = wp_get_attachment_metadata( $image_id );
	$made   = is_array( $meta ) ? (array) ( $meta['sizes'] ?? array() ) : array();
	$height = is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0;
	$floor  = salt_image_srcset_floor( $sizes );
	$names  = array();
	foreach ( salt_image_candidates() as $candidate ) {
		if ( isset( $made[ 'salt-' . $candidate ] ) ) {
			$names[ $candidate ] = 'salt-' . $candidate;
		}
	}
	if ( array() === $names ) {
		foreach ( $made as $name => $size ) {
			$w = (int) ( $size['width'] ?? 0 );
			$h = (int) ( $size['height'] ?? 0 );
			// The upload's proportions, as core's own srcset keeps them.
			if ( $w > 0 && $h > 0 && ( $height <= 0 || abs( $height * $w / $width - $h ) <= 1 ) ) {
				$names[ $w ] ??= (string) $name;
			}
		}
		ksort( $names );
	}

	$sources = array();
	foreach ( $names as $w => $name ) {
		if ( $w < $floor || $w >= $width ) {
			continue;
		}
		$sub = wp_get_attachment_image_src( $image_id, $name );
		if ( is_array( $sub ) && (int) ( $sub[1] ?? 0 ) === $w ) {
			$sources[] = $sub[0] . ' ' . $w . 'w';
		}
	}
	$sources[] = $src . ' ' . $width . 'w';
	return implode( ', ', $sources );
}

/**
 * An attachment as the contract's resolved image (view-props/_shared.json
 * image) for one slot: src the upload at its own width, srcset by the
 * contract's rule for the slot's sizes (salt_image_contract_srcset()), the
 * upload's width and height, its alt, its caption, and its focal point
 * (salt_image_focal_point()). Null when the attachment does not resolve
 * (logged).
 *
 * @param int    $image_id Attachment ID.
 * @param string $sizes    The slot's sizes (salt_image_slot_sizes()).
 * @return array{src: string, srcset: string|null, sizes: string, width: int, height: int, alt: string, caption: string|null, focalPoint: array{x: int, y: int}|null}|null
 */
function salt_image_contract( int $image_id, string $sizes ): ?array {
	if ( $image_id <= 0 ) {
		return null;
	}
	$src = wp_get_attachment_image_src( $image_id, 'full' );
	if ( ! is_array( $src ) || empty( $src[0] ) ) {
		salt_log( sprintf( 'salt_image_contract: attachment %d could not be resolved (DATA05).', $image_id ) );
		return null;
	}

	$width  = (int) ( $src[1] ?? 0 );
	$height = (int) ( $src[2] ?? 0 );
	$srcset = $width > 0 ? salt_image_contract_srcset( $image_id, (string) $src[0], $width, $sizes ) : null;

	$alt     = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
	$caption = wp_get_attachment_caption( $image_id );

	return array(
		'src'        => (string) $src[0],
		'srcset'     => $srcset,
		'sizes'      => $sizes,
		'width'      => $width,
		'height'     => $height,
		'alt'        => is_string( $alt ) ? $alt : '',
		'caption'    => is_string( $caption ) && '' !== trim( $caption ) ? $caption : null,
		'focalPoint' => salt_image_focal_point( $image_id ),
	);
}

/**
 * An attachment's stored focal point (view-props/_shared.json focalPoint):
 * the `_salt_focal_point` meta { x, y }, percentages of the image's width
 * and height, each snapped to the contract's step of 5 and held to 0-100
 * (section#background-position): min(100, max(0, 5 × floor(v ÷ 5 + 0.5))),
 * so 32.5 gives 35 and 2.4 gives 0. Null when none is stored or either
 * value is not a finite number. Nothing sets it from the editor yet.
 *
 * @param int $image_id Attachment ID.
 * @return array{x: int, y: int}|null
 */
function salt_image_focal_point( int $image_id ): ?array {
	$stored = get_post_meta( $image_id, '_salt_focal_point', true );
	if ( ! is_array( $stored ) ) {
		return null;
	}
	$step = static function ( $value ): ?int {
		if ( ! is_int( $value ) && ! is_float( $value ) && ! ( is_string( $value ) && is_numeric( $value ) ) ) {
			return null;
		}
		$value = (float) $value;
		return is_finite( $value ) ? (int) min( 100, max( 0, 5 * floor( $value / 5 + 0.5 ) ) ) : null;
	};
	$x    = $step( $stored['x'] ?? null );
	$y    = $step( $stored['y'] ?? null );
	return null === $x || null === $y ? null : array(
		'x' => $x,
		'y' => $y,
	);
}

/**
 * Draw a resolved image as the contract's media (markup/media.json): one
 * img with src, srcset, sizes, width, height and alt; fetchpriority high on
 * the priority image, loading lazy on every other; decoding async.
 *
 * @param array<string,mixed>  $image    salt_image_contract().
 * @param bool                 $priority The page's priority image.
 * @param array<string,string> $extra    Further attributes (class, alt override, aria-hidden, data-*).
 * @return string
 */
function salt_image_contract_html( array $image, bool $priority = false, array $extra = array() ): string {
	$attributes = array(
		'src'    => (string) $image['src'],
		'srcset' => $image['srcset'] ?? null,
		'sizes'  => (string) ( $image['sizes'] ?? '' ),
		'width'  => (string) $image['width'],
		'height' => (string) $image['height'],
		'alt'    => (string) $image['alt'],
	);
	$attributes = array_merge( $attributes, $extra );
	if ( $priority ) {
		$attributes['fetchpriority'] = 'high';
	} else {
		$attributes['loading'] = 'lazy';
	}
	$attributes['decoding'] = 'async';

	$html = '<img';
	foreach ( $attributes as $name => $value ) {
		if ( null === $value ) {
			continue;
		}
		$html .= ' ' . $name . '="' . ( 'src' === $name ? esc_url( $value ) : esc_attr( $value ) ) . '"';
	}
	return $html . '>';
}
