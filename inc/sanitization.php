<?php
/**
 * Content sanitisation: the WYSIWYG allow-list and its TinyMCE mirror.
 *
 * One artifact — salt_allowed_html() — defines what markup WYSIWYG content
 * may contain. wp_kses enforcement (salt_sanitize_html) and the TinyMCE
 * editor configuration (salt_mce_valid_elements / salt_tinymce_config) are
 * both derived from it, so the sanitiser and the editor can never drift
 * (EDIT01).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The content allow-list, in wp_kses format.
 *
 * FINAL by design: no filter, no pluggability. Widening this allow-list is
 * a security-reviewed MAJOR change — the only path to a wider list is a
 * parent theme release, enforced by making this function the single,
 * unfilterable source of truth (spec §16a).
 *
 * Deliberately narrow for v1: no figure/figcaption (narrower is safer;
 * widening is a major bump, narrowing is not). No class, id, or style
 * attribute on anything — presentation belongs to the theme, not content.
 *
 * @return array<string,array<string,true>> Allowed tags => allowed attributes.
 */
function salt_allowed_html(): array {
	return array(
		'p'          => array(),
		'a'          => array(
			'href'   => true,
			'title'  => true,
			'rel'    => true,
			'target' => true,
		),
		'strong'     => array(),
		'em'         => array(),
		'ul'         => array(),
		'ol'         => array(),
		'li'         => array(),
		'blockquote' => array(),
		'h3'         => array(),
		'h4'         => array(),
		'br'         => array(),
		'code'       => array(),
	);
}

/**
 * Sanitise WYSIWYG HTML against the salt allow-list.
 *
 * @param string $html Raw HTML.
 * @return string Sanitised HTML.
 */
function salt_sanitize_html( string $html ): string {
	return wp_kses( $html, salt_allowed_html(), array( 'http', 'https', 'mailto', 'tel' ) );
}

/**
 * TinyMCE valid_elements string derived from salt_allowed_html().
 *
 * Built programmatically by iterating the allow-list array so the editor
 * configuration and the kses sanitiser can never drift (EDIT01). The only
 * hardcoding is the b/strong and i/em aliasing, which maps legacy pasted
 * markup onto the allowed semantic elements.
 *
 * @return string TinyMCE valid_elements value.
 */
function salt_mce_valid_elements(): string {
	$aliases = array(
		'strong' => 'strong/b',
		'em'     => 'em/i',
	);

	$elements = array();
	foreach ( salt_allowed_html() as $tag => $attrs ) {
		$element = $aliases[ $tag ] ?? $tag;
		if ( array() !== $attrs ) {
			$element .= '[' . implode( '|', array_keys( $attrs ) ) . ']';
		}
		$elements[] = $element;
	}

	return implode( ',', $elements );
}

/**
 * TinyMCE init config mirroring the allow-list at the editor layer (EDIT01).
 *
 * No paste_preprocess JS is injected: in WordPress' TinyMCE 4,
 * valid_elements already applies at paste-time, so pasted markup is
 * filtered down to the allow-list before it ever reaches the content.
 *
 * @param array<string,mixed> $init TinyMCE init settings.
 * @return array<string,mixed> Modified settings.
 */
function salt_tinymce_config( array $init ): array {
	$init['block_formats']                 = 'Paragraph=p;Heading 3=h3;Heading 4=h4';
	$init['valid_elements']                = salt_mce_valid_elements();
	$init['invalid_elements']              = 'script,iframe,style,span,font,h1,h2';
	$init['paste_webkit_styles']           = 'none';
	$init['paste_remove_styles_if_webkit'] = true;
	return $init;
}

/**
 * First-row TinyMCE buttons — only controls that map to allowed markup.
 *
 * @return string[] Button IDs.
 */
function salt_mce_buttons(): array {
	return array(
		'formatselect',
		'bold',
		'italic',
		'blockquote',
		'bullist',
		'numlist',
		'link',
		'unlink',
		'removeformat',
		'undo',
		'redo',
	);
}

/**
 * Second-row TinyMCE buttons — none (no forecolor, fontsize, etc.).
 *
 * @return array Empty button row.
 */
function salt_mce_buttons_2(): array {
	return array();
}

/**
 * Register the editor-layer filters that mirror the allow-list (EDIT01).
 *
 * @return void
 */
function salt_configure_editor(): void {
	add_filter( 'tiny_mce_before_init', 'salt_tinymce_config' );
	add_filter( 'mce_buttons', 'salt_mce_buttons' );
	add_filter( 'mce_buttons_2', 'salt_mce_buttons_2' );
}
add_action( 'after_setup_theme', 'salt_configure_editor' );
