<?php
/**
 * Site header — opens the document, skip link, and <main>.
 *
 * The skip link moves focus to <main>, which takes tabindex="-1" so it can
 * hold focus (salt-contract markup/page.json). The header itself is
 * template-parts/header/site-header.php; the head's scriptless header style
 * is salt_output_header_noscript() (wp_head).
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
<a class="salt-skip-link" href="#main"><?php echo esc_html( salt_chrome_labels( 'header' )['skip'] ); ?></a>
<?php get_template_part( 'template-parts/header/site-header' ); ?>
<main id="main" tabindex="-1">
