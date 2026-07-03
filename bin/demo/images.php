<?php
/**
 * Demo seeder — deterministic image sideloading.
 *
 * Placeholder media is fetched from licence-free, no-key, DETERMINISTIC
 * sources so re-runs reuse identical images:
 *  - photos  : https://picsum.photos/seed/{seed}/{w}/{h}
 *  - logos   : https://placehold.co/{w}x{h}/{bg}/{fff}?text={label}
 *
 * Each sideloaded attachment is tagged _salt_demo=1 so the purge step can
 * find and remove it. Sideloading is server-side (front-end CSP is
 * irrelevant); rendered images are same-origin from the media library.
 *
 * @package Salt\Demo
 */

declare(strict_types=1);

/**
 * Sideload every image declared in the content map's 'images' list and
 * register each under its ref (e.g. ref:image/hero-home => attachment ID).
 *
 * @param array<string,array{type:string,seed?:string,label?:string,w:int,h:int,alt?:string,bg?:string}> $images Image specs keyed by ref suffix.
 * @return void
 */
function salt_demo_seed_images( array $images ): void {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	foreach ( $images as $ref_suffix => $spec ) {
		$url = salt_demo_image_url( $spec );
		$id  = salt_demo_sideload( $url, (string) ( $spec['alt'] ?? $ref_suffix ) );

		if ( null !== $id ) {
			salt_demo_ref_set( 'image/' . $ref_suffix, $id );
		} else {
			salt_demo_log( sprintf( 'image "%s" failed to sideload (%s) — sections degrade gracefully.', $ref_suffix, $url ) );
		}
	}
}

/**
 * Build the source URL for an image spec.
 *
 * @param array<string,mixed> $spec Image spec.
 * @return string
 */
function salt_demo_image_url( array $spec ): string {
	$w = (int) ( $spec['w'] ?? 800 );
	$h = (int) ( $spec['h'] ?? 600 );

	if ( 'logo' === ( $spec['type'] ?? 'photo' ) ) {
		$bg    = (string) ( $spec['bg'] ?? '1f2933' );
		$label = rawurlencode( (string) ( $spec['label'] ?? 'Logo' ) );
		return sprintf( 'https://placehold.co/%dx%d/%s/ffffff/png?text=%s', $w, $h, $bg, $label );
	}

	$seed = rawurlencode( (string) ( $spec['seed'] ?? 'salt' ) );
	return sprintf( 'https://picsum.photos/seed/%s/%d/%d', $seed, $w, $h );
}

/**
 * Sideload a remote image into the media library and mark it demo content.
 *
 * @param string $url URL.
 * @param string $alt Alt text.
 * @return int|null Attachment ID, or null on failure.
 */
function salt_demo_sideload( string $url, string $alt ): ?int {
	$id = media_sideload_image( $url, 0, $alt, 'id' );

	if ( is_wp_error( $id ) || ! is_int( $id ) ) {
		return null;
	}

	update_post_meta( $id, '_salt_demo', 1 );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );

	return $id;
}
