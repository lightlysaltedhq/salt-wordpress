<?php
/**
 * Site header — opens the document, skip link, and <main>.
 *
 * Stub — the header/nav region (x-nav, spec §12a) lands in Phase 1/2.
 *
 * @package Salt
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="visually-hidden" href="#main"><?php esc_html_e( 'Skip to content', 'salt' ); ?></a>
<main id="main">
