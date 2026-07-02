<?php
/**
 * Shared helpers.
 *
 * Pluggable salt_* helpers live here. Every pluggable definition is wrapped
 * in function_exists() so a child theme (loaded first) can replace it —
 * see docs/contracts/OVERRIDES.md for the override contract (ARCH07).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
