<?php
/**
 * PHPUnit bootstrap.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

// Dev-only Composer autoloader (PHPUnit itself). Production code never uses it.
require dirname( __DIR__ ) . '/vendor/autoload.php';

require __DIR__ . '/wp-shims.php';

// The production loading path for vendored runtime libs.
require dirname( __DIR__ ) . '/inc/services/vendor.php';
