<?php
/**
 * Salt parent theme bootstrap.
 *
 * Loads the inc/* modules. WordPress loads a child theme's functions.php
 * BEFORE this file — that ordering is the guarantee that lets a child
 * override any pluggable salt_* function (see the override contract,
 * docs/contracts/OVERRIDES.md).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SALT_VERSION', '1.0.0' );
define( 'SALT_DIR', get_template_directory() );
define( 'SALT_URI', get_template_directory_uri() );

require_once SALT_DIR . '/inc/helpers.php';
require_once SALT_DIR . '/inc/acf.php';
require_once SALT_DIR . '/inc/colors.php';
require_once SALT_DIR . '/inc/modules.php';
require_once SALT_DIR . '/inc/sanitization.php';
require_once SALT_DIR . '/inc/render.php';
require_once SALT_DIR . '/inc/media.php';
require_once SALT_DIR . '/inc/query/resolver.php';
require_once SALT_DIR . '/inc/query/controls.php';
require_once SALT_DIR . '/inc/schema/graph.php';
require_once SALT_DIR . '/inc/services/vendor.php';
require_once SALT_DIR . '/inc/services/svg.php';
require_once SALT_DIR . '/inc/services/consent.php';
require_once SALT_DIR . '/inc/services/location-status.php';
require_once SALT_DIR . '/inc/services/updater.php';
require_once SALT_DIR . '/inc/security.php';
require_once SALT_DIR . '/inc/setup.php';
require_once SALT_DIR . '/inc/performance.php';
require_once SALT_DIR . '/inc/filters.php';
require_once SALT_DIR . '/inc/cpt/service.php';
require_once SALT_DIR . '/inc/cpt/testimonial.php';
require_once SALT_DIR . '/inc/cpt/team-member.php';
require_once SALT_DIR . '/inc/cpt/work.php';
require_once SALT_DIR . '/inc/cpt/faq.php';
require_once SALT_DIR . '/inc/cpt/location.php';
require_once SALT_DIR . '/inc/fields/options.php';
require_once SALT_DIR . '/inc/fields/cpt-service.php';
require_once SALT_DIR . '/inc/fields/cpt-testimonial.php';
require_once SALT_DIR . '/inc/fields/cpt-team-member.php';
require_once SALT_DIR . '/inc/fields/cpt-work.php';
require_once SALT_DIR . '/inc/fields/cpt-faq.php';
require_once SALT_DIR . '/inc/fields/cpt-location.php';
require_once SALT_DIR . '/inc/fields/sections.php';
