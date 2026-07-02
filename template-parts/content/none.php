<?php
/**
 * Empty result state for native loops (search/archives — spec §6).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="text-ink-muted"><?php esc_html_e( 'Nothing found. Try a different search term.', 'salt' ); ?></p>
