<?php
/**
 * Performance: asset manifest resolution, enqueue, image formats.
 *
 * Built out in Phase 1 (§5.8, ARCH03) — manifest-resolved enqueue with
 * $ver = null (no ?ver= query strings), salt_best_image_format().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
