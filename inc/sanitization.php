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
 * Headings h2 to h6 follow salt-contract 1.0.0-rc.7 (ID-3950): rich text
 * stores heading-2 to heading-6; h1 stays the page's alone.
 *
 * This is the SUPERSET. Each rich-text field allows only the element kinds
 * its contract field lists (`salt.allowed` in the generated JSON; see
 * salt_field_allowed()), and salt_allowed_html_for() narrows this list to
 * them; nothing can widen it. Only the rich-text section's body allows h2,
 * h5 and h6 (security review of the rc.7 widening, PR #26).
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
		'h2'         => array(),
		'h3'         => array(),
		'h4'         => array(),
		'h5'         => array(),
		'h6'         => array(),
		'br'         => array(),
		'code'       => array(),
	);
}

/**
 * The tags each of the contract's rich-text element kinds stands for
 * (salt-contract schema/field-definition.schema.json, richTextField
 * `allowed`). A paragraph may hold line breaks, as the editor writes them.
 *
 * @return array<string,string[]> Element kind => tags.
 */
function salt_rich_text_kinds(): array {
	return array(
		'paragraph'      => array( 'p', 'br' ),
		'bold'           => array( 'strong' ),
		'italic'         => array( 'em' ),
		'code'           => array( 'code' ),
		'link'           => array( 'a' ),
		'unordered-list' => array( 'ul', 'li' ),
		'ordered-list'   => array( 'ol', 'li' ),
		'blockquote'     => array( 'blockquote' ),
		'heading-2'      => array( 'h2' ),
		'heading-3'      => array( 'h3' ),
		'heading-4'      => array( 'h4' ),
		'heading-5'      => array( 'h5' ),
		'heading-6'      => array( 'h6' ),
	);
}

/**
 * The allow-list narrowed to a field's element kinds, in wp_kses format.
 *
 * Intersected with salt_allowed_html(): a kind the theme does not know, or
 * a tag outside the superset, adds nothing.
 *
 * @param string[] $kinds The field's contract element kinds.
 * @return array<string,array<string,true>> Allowed tags => allowed attributes.
 */
function salt_allowed_html_for( array $kinds ): array {
	$superset = salt_allowed_html();
	$map      = salt_rich_text_kinds();
	$allowed  = array();
	foreach ( $superset as $tag => $attrs ) {
		foreach ( $kinds as $kind ) {
			if ( in_array( $tag, $map[ $kind ] ?? array(), true ) ) {
				$allowed[ $tag ] = $attrs;
				break;
			}
		}
	}
	return $allowed;
}

/**
 * Sanitise WYSIWYG HTML against the salt allow-list.
 *
 * @param string        $html    Raw HTML.
 * @param string[]|null $allowed The field's contract element kinds
 *                               (salt_field_allowed()); null for the whole
 *                               superset.
 * @return string Sanitised HTML.
 */
function salt_sanitize_html( string $html, ?array $allowed = null ): string {
	$tags = null === $allowed ? salt_allowed_html() : salt_allowed_html_for( $allowed );
	return wp_kses( $html, $tags, array( 'http', 'https', 'mailto', 'tel' ) );
}

/**
 * The element kinds of the service's intro, which WordPress keeps in the
 * post's body (salt.native), so no generated field carries its list.
 * Copied from salt-contract contract/collections/services.json `intro`;
 * SanitizationTest holds it to the pinned contract.
 */
const SALT_SERVICE_INTRO_ALLOWED = array( 'paragraph', 'bold', 'italic', 'link', 'unordered-list', 'ordered-list' );

/**
 * TinyMCE valid_elements string derived from salt_allowed_html().
 *
 * Built programmatically by iterating the allow-list array so the editor
 * configuration and the kses sanitiser can never drift (EDIT01). The only
 * hardcoding is the b/strong and i/em aliasing, which maps legacy pasted
 * markup onto the allowed semantic elements.
 *
 * @param string[]|null $allowed A field's contract element kinds; null for
 *                               the whole superset.
 * @return string TinyMCE valid_elements value.
 */
function salt_mce_valid_elements( ?array $allowed = null ): string {
	$aliases = array(
		'strong' => 'strong/b',
		'em'     => 'em/i',
	);

	$elements = array();
	foreach ( null === $allowed ? salt_allowed_html() : salt_allowed_html_for( $allowed ) as $tag => $attrs ) {
		$element = $aliases[ $tag ] ?? $tag;
		if ( array() !== $attrs ) {
			$element .= '[' . implode( '|', array_keys( $attrs ) ) . ']';
		}
		$elements[] = $element;
	}

	return implode( ',', $elements );
}

/**
 * TinyMCE's block format menu for a field's element kinds: Paragraph and
 * the heading levels the field allows.
 *
 * @param string[]|null $allowed A field's contract element kinds; null for
 *                               the whole superset.
 * @return string TinyMCE block_formats value.
 */
function salt_mce_block_formats( ?array $allowed = null ): string {
	$formats = array( 'Paragraph=p' );
	foreach ( array_keys( null === $allowed ? salt_allowed_html() : salt_allowed_html_for( $allowed ) ) as $tag ) {
		if ( 1 === preg_match( '/^h([2-6])$/', $tag, $level ) ) {
			$formats[] = sprintf( 'Heading %1$s=h%1$s', $level[1] );
		}
	}
	return implode( ';', $formats );
}

/**
 * TinyMCE init config mirroring the allow-list at the editor layer (EDIT01).
 *
 * No paste_preprocess JS is injected: in WordPress' TinyMCE 4,
 * valid_elements already applies at paste-time, so pasted markup is
 * filtered down to the allow-list before it ever reaches the content.
 *
 * The superset is every editor's base, ACF's included ('acf_content').
 * Each ACF rich-text field is narrowed to its own list in the browser
 * (salt_wysiwyg_field_editor() and admin/rich-text-editor.js). The
 * service's own editor holds its intro, so it is narrowed here.
 *
 * @param array<string,mixed> $init      TinyMCE init settings.
 * @param string              $editor_id The editor's id.
 * @return array<string,mixed> Modified settings.
 */
function salt_tinymce_config( array $init, string $editor_id = '' ): array {
	$allowed = 'content' === $editor_id && 'service' === ( $GLOBALS['typenow'] ?? '' ) ? SALT_SERVICE_INTRO_ALLOWED : null;

	$init['block_formats']                 = salt_mce_block_formats( $allowed );
	$init['valid_elements']                = salt_mce_valid_elements( $allowed );
	$init['invalid_elements']              = 'script,iframe,style,span,font,h1';
	$init['paste_webkit_styles']           = 'none';
	$init['paste_remove_styles_if_webkit'] = true;
	if ( null !== $allowed ) {
		$init['toolbar1'] = implode( ',', salt_mce_buttons_for( $allowed ) );
	}
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
 * The first-row buttons narrowed to a field's element kinds: a button for
 * a kind the field does not allow is left out, and so is the format menu
 * when the field allows no heading.
 *
 * @param string[] $allowed A field's contract element kinds.
 * @return string[] Button IDs.
 */
function salt_mce_buttons_for( array $allowed ): array {
	$needs = array(
		'formatselect' => array( 'heading-2', 'heading-3', 'heading-4', 'heading-5', 'heading-6' ),
		'bold'         => array( 'bold' ),
		'italic'       => array( 'italic' ),
		'blockquote'   => array( 'blockquote' ),
		'bullist'      => array( 'unordered-list' ),
		'numlist'      => array( 'ordered-list' ),
		'link'         => array( 'link' ),
		'unlink'       => array( 'link' ),
	);
	return array_values(
		array_filter(
			salt_mce_buttons(),
			static fn( string $button ): bool => ! isset( $needs[ $button ] ) || array() !== array_intersect( $needs[ $button ], $allowed )
		)
	);
}

/**
 * Give an ACF rich-text field its own editor settings
 * ('acf/prepare_field/type=wysiwyg'): its contract element kinds
 * (`salt.allowed`) become data attributes on the field's wrapper, which
 * admin/rich-text-editor.js applies to its TinyMCE (format menu,
 * valid_elements, toolbar). A field without a list keeps the superset.
 *
 * @param array<string,mixed>|false $field The field.
 * @return array<string,mixed>|false
 */
function salt_wysiwyg_field_editor( $field ) {
	$allowed = is_array( $field ) ? ( $field['salt']['allowed'] ?? null ) : null;
	if ( ! is_array( $allowed ) ) {
		return $field;
	}
	$field['data'] = array_merge(
		is_array( $field['data'] ?? null ) ? $field['data'] : array(),
		array(
			'salt-allowed'        => implode( ',', $allowed ),
			'salt-block-formats'  => salt_mce_block_formats( $allowed ),
			'salt-valid-elements' => salt_mce_valid_elements( $allowed ),
			'salt-toolbar'        => implode( ',', salt_mce_buttons_for( $allowed ) ),
		)
	);
	return $field;
}

/**
 * Load the script that narrows each ACF rich-text editor to its field's
 * list ('acf/input/admin_enqueue_scripts').
 *
 * @return void
 */
function salt_rich_text_editor_enqueue(): void {
	$src = salt_asset_optional( 'admin/rich-text-editor.js' );
	if ( null === $src ) {
		salt_log( 'salt_rich_text_editor: no manifest entry for "admin/rich-text-editor.js" — rich-text editors offer the whole allow-list. Re-run the asset build.' );
		return;
	}
	wp_enqueue_script( 'salt-rich-text-editor', $src, array( 'acf-input' ), null, array( 'in_footer' => true ) );
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
	add_filter( 'tiny_mce_before_init', 'salt_tinymce_config', 10, 2 );
	add_filter( 'acf/prepare_field/type=wysiwyg', 'salt_wysiwyg_field_editor' );
	add_action( 'acf/input/admin_enqueue_scripts', 'salt_rich_text_editor_enqueue' );
	add_filter( 'mce_buttons', 'salt_mce_buttons' );
	add_filter( 'mce_buttons_2', 'salt_mce_buttons_2' );
}
add_action( 'after_setup_theme', 'salt_configure_editor' );
