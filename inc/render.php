<?php
/**
 * Render layer: args contracts, component renderer, section machinery,
 * heading invariant, anchor registry (spec §5/§11/§12; review A11Y01,
 * ARCH05, EDIT02, FAIL01, AMBIG03; Open Q6 resolved — see OVERRIDES.md).
 *
 * Open Q6 resolution (frozen): simple, data-only partials render via raw
 * get_template_part( $slug, null, $args ); slot-style or contract-validated
 * components render via salt_component(), which output-buffers and runs
 * salt_require_args() against the component's declared contract.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Args contract (§12, FAIL01).
// ---------------------------------------------------------------------------

/**
 * Validate component args against a declared contract.
 *
 * Contract shape: name => array{ type: 'string'|'int'|'float'|'bool'|'array'|'callable'|'mixed',
 * required?: bool, default?: mixed }.
 *
 * Under WP_DEBUG a violation THROWS (a renamed arg must fail in staging);
 * in production it is error_logged and the arg degrades to its declared
 * default (or a typed empty value) — logged and visible, never a silent
 * blank render.
 *
 * @param array<string,mixed> $args      Incoming args.
 * @param array<string,array> $contract  Declared contract.
 * @param string              $component Component name for diagnostics.
 * @return array<string,mixed> Normalised args (defaults applied).
 * @throws InvalidArgumentException On violation when debug is active.
 */
function salt_require_args( array $args, array $contract, string $component ): array {
	$empty_for_type = array(
		'string' => '',
		'int'    => 0,
		'float'  => 0.0,
		'bool'   => false,
		'array'  => array(),
	);

	foreach ( $contract as $name => $rules ) {
		$type     = $rules['type'] ?? 'mixed';
		$required = (bool) ( $rules['required'] ?? false );
		$has_arg  = array_key_exists( $name, $args );

		if ( ! $has_arg ) {
			if ( $required ) {
				$message = sprintf( 'x-%s: required arg "%s" missing.', $component, $name );
				salt_log( $message );
				if ( salt_is_debug() ) {
					throw new InvalidArgumentException( esc_html( $message ) );
				}
			}
			$args[ $name ] = $rules['default'] ?? ( $empty_for_type[ $type ] ?? null );
			continue;
		}

		$valid = match ( $type ) {
			'string'   => is_string( $args[ $name ] ),
			'int'      => is_int( $args[ $name ] ),
			'float'    => is_float( $args[ $name ] ) || is_int( $args[ $name ] ),
			'bool'     => is_bool( $args[ $name ] ),
			'array'    => is_array( $args[ $name ] ),
			'callable' => is_callable( $args[ $name ] ),
			default    => true,
		};

		if ( ! $valid ) {
			$message = sprintf( 'x-%s: arg "%s" must be %s, %s given.', $component, $name, $type, get_debug_type( $args[ $name ] ) );
			salt_log( $message );
			if ( salt_is_debug() ) {
				throw new InvalidArgumentException( esc_html( $message ) );
			}
			$args[ $name ] = $rules['default'] ?? ( $empty_for_type[ $type ] ?? null );
		}
	}

	return $args;
}

// ---------------------------------------------------------------------------
// Component renderer (Open Q6).
// ---------------------------------------------------------------------------

/**
 * Render a component partial to a string (output-buffered).
 *
 * Slots are passed to the partial under $args['slots'] (reserved key),
 * each slot a pre-rendered HTML string.
 *
 * @param string              $name  Component name (template-parts/components/{name}.php).
 * @param array<string,mixed> $args  Component args.
 * @param array<string,string> $slots Named slot HTML.
 * @return string
 * @throws RuntimeException When the component file is missing and debug is active.
 */
function salt_get_component( string $name, array $args = array(), array $slots = array() ): string {
	$located = locate_template( 'template-parts/components/' . $name . '.php' );

	if ( '' === $located ) {
		$message = sprintf( 'component "%s" not found.', $name );
		salt_log( $message );
		if ( salt_is_debug() ) {
			throw new RuntimeException( esc_html( $message ) );
		}
		return '';
	}

	$args['slots'] = $slots;

	ob_start();
	load_template( $located, false, $args );
	return (string) ob_get_clean();
}

/**
 * Echo a component (see salt_get_component()).
 *
 * @param string               $name  Component name.
 * @param array<string,mixed>  $args  Component args.
 * @param array<string,string> $slots Named slot HTML.
 * @return void
 */
function salt_component( string $name, array $args = array(), array $slots = array() ): void {
	echo salt_get_component( $name, $args, $slots ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component output escapes at the point each value is printed.
}

// ---------------------------------------------------------------------------
// Per-request render state: H1 claim + section index + anchors.
// ---------------------------------------------------------------------------

/**
 * Access/mutate the per-request render state (single authoritative store).
 *
 * @param string      $op    'get'|'set'|'reset'.
 * @param string|null $key   Key when setting.
 * @param mixed       $value Value when setting.
 * @return array{h1_claimed: bool, h1_source: string, section_index: int, anchors: array<string,bool>, last_background: string, last_divider: bool}
 */
function salt_render_state( string $op = 'get', ?string $key = null, $value = null ): array {
	static $state = null;

	if ( null === $state || 'reset' === $op ) {
		$state = array(
			'h1_claimed'      => false,
			'h1_source'       => '',
			'section_index'   => 0,
			'anchors'         => array(),
			'last_background' => '',
			'last_divider'    => false,
		);
	}

	if ( 'set' === $op && null !== $key ) {
		$state[ $key ] = $value;
	}

	return $state;
}

/**
 * Reset render state (per request / per test).
 *
 * @return void
 */
function salt_render_state_reset(): void {
	salt_render_state( 'reset' );
}

/**
 * Claim the document's single H1 (A11Y01: at-most-one-H1).
 *
 * @param string $source Diagnostic label for who claimed it.
 * @return bool True when the claim succeeded (caller renders the H1).
 */
function salt_claim_h1( string $source ): bool {
	static $warned = false;

	$state = salt_render_state();
	if ( $state['h1_claimed'] ) {
		if ( ! $warned ) {
			salt_log( sprintf( 'H1 already claimed by "%s"; "%s" downgraded to h2 (A11Y01).', $state['h1_source'], $source ) );
			$warned = true;
		}
		return false;
	}

	salt_render_state( 'set', 'h1_claimed', true );
	salt_render_state( 'set', 'h1_source', $source );
	$warned = false;
	return true;
}

/**
 * Whether the document H1 has been claimed.
 *
 * @return bool
 */
function salt_h1_claimed(): bool {
	return salt_render_state()['h1_claimed'];
}

/**
 * Echo the document H1 for native-loop routes (A11Y01: at-least-one-H1).
 *
 * Archives, search, 404, author, date and home call this so no route can
 * emit zero H1s. If a builder section already claimed the H1, this
 * defensively renders an h2 and logs.
 *
 * @param string $text  Heading text (raw; escaped here).
 * @param string $class_names Optional class list.
 * @return void
 */
function salt_the_document_h1( string $text, string $class_names = '' ): void {
	$level = salt_claim_h1( 'template' ) ? 1 : 2;
	printf(
		'<h%1$d%2$s>%3$s</h%1$d>',
		(int) $level,
		'' !== $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '',
		esc_html( $text )
	);
}

// ---------------------------------------------------------------------------
// Anchor IDs (EDIT02).
// ---------------------------------------------------------------------------

/**
 * Theme-owned element IDs an editor anchor may not take.
 *
 * @return string[]
 */
function salt_reserved_anchor_ids(): array {
	return array( 'main', 'content', 'header', 'footer', 'nav', 'primary-navigation', 'search', 'skip-link' );
}

/**
 * Sanitise an editor-supplied anchor ID: slugify, dodge reserved IDs,
 * suffix on within-page collision (EDIT02).
 *
 * @param string $raw Editor input.
 * @return string Sanitised unique ID, or '' when input yields nothing.
 */
function salt_anchor_id( string $raw ): string {
	$slug = sanitize_title( $raw );
	if ( '' === $slug ) {
		return '';
	}

	$state   = salt_render_state();
	$anchors = $state['anchors'];
	$taken   = static fn( string $candidate ): bool => isset( $anchors[ $candidate ] )
		|| in_array( $candidate, salt_reserved_anchor_ids(), true );

	$unique = $slug;
	$suffix = 2;
	while ( $taken( $unique ) ) {
		$unique = $slug . '-' . $suffix;
		++$suffix;
	}

	$anchors[ $unique ] = true;
	salt_render_state( 'set', 'anchors', $anchors );

	return $unique;
}

// ---------------------------------------------------------------------------
// Sections (§5, §11; ARCH05, AMBIG03).
// ---------------------------------------------------------------------------

/**
 * Render a section body inside the shared x-section wrapper.
 *
 * The canonical section entry point: computes the heading level BEFORE the
 * body renders (the wrapper knows document position — AMBIG03), claims the
 * H1 when this section is the page's designated primary heading, then
 * renders the body inside the wrapper component.
 *
 * @param array<string,mixed> $args    Wrapper args: name (required), spacing,
 *                                     background, container, anchor, divider,
 *                                     is_primary, has_heading, flush_top.
 * @param callable            $content function( int $heading_level ): void — echoes the body.
 * @return void
 */
function salt_section( array $args, callable $content ): void {
	$contract = array(
		'name'        => array(
			'type'     => 'string',
			'required' => true,
		),
		'spacing'     => array(
			'type'    => 'string',
			'default' => 'md',
		),
		'background'  => array(
			'type'    => 'string',
			'default' => 'surface',
		),
		'container'   => array(
			'type'    => 'string',
			'default' => 'default',
		),
		'anchor'      => array(
			'type'    => 'string',
			'default' => '',
		),
		'divider'     => array(
			'type'    => 'bool',
			'default' => false,
		),
		'is_primary'  => array(
			'type'    => 'bool',
			'default' => false,
		),
		'has_heading' => array(
			'type'    => 'bool',
			'default' => true,
		),
		'flush_top'   => array(
			'type'    => 'bool',
			'default' => false,
		),
	);

	$args = salt_require_args( $args, $contract, 'section' );

	// Claim the H1 only when this section will actually RENDER a heading —
	// a heading-less primary section must not consume the claim, or the
	// page-level fallback H1 could never fire (A11Y01).
	$level = 2;
	if ( $args['is_primary'] && $args['has_heading'] && salt_claim_h1( 'section:' . $args['name'] ) ) {
		$level = 1;
	}

	$state = salt_render_state();
	$index = $state['section_index'];
	salt_render_state( 'set', 'section_index', $index + 1 );

	// Same-background adjacency collapse: two neighbouring sections sharing a
	// background have no visual boundary, so their paddings stack into a dead
	// zone (pb + pt of the same colour). The later section drops its top
	// padding — ONE unit of spacing between them, not two. A divider on the
	// previous section is a boundary and keeps the full gap. A section may
	// also force the collapse itself (flush_top — e.g. the full-bleed hero
	// banner meeting the header).
	$collapse_top = $args['flush_top']
		|| ( 'none' !== $args['spacing']
			&& '' !== $state['last_background']
			&& $state['last_background'] === $args['background']
			&& ! $state['last_divider'] );
	salt_render_state( 'set', 'last_background', $args['background'] );
	salt_render_state( 'set', 'last_divider', $args['divider'] );

	ob_start();
	$content( $level );
	$body = (string) ob_get_clean();

	salt_component(
		'section',
		array(
			'name'         => $args['name'],
			'spacing'      => $args['spacing'],
			'background'   => $args['background'],
			'container'    => $args['container'],
			'anchor'       => salt_anchor_id( $args['anchor'] ),
			'divider'      => $args['divider'],
			'index'        => $index,
			'collapse_top' => $collapse_top,
		),
		array( 'default' => $body )
	);
}

/**
 * Build the x-section wrapper args for the current Flexible Content row:
 * the shared §11 Advanced-tab controls + loop context (AMBIG03).
 *
 * @param string              $name    Section slug (hyphenated).
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array<string,mixed>
 */
function salt_section_wrapper_args( string $name, array $context = array() ): array {
	$sub = static function ( string $field, string $fallback ): string {
		if ( ! function_exists( 'get_sub_field' ) ) {
			return $fallback;
		}
		$value = get_sub_field( $field );
		return is_string( $value ) && '' !== $value ? $value : $fallback;
	};

	return array(
		'name'       => $name,
		'spacing'    => $sub( 'section_spacing', 'md' ),
		'background' => $sub( 'section_background', 'surface' ),
		'container'  => $sub( 'section_container', 'default' ),
		'anchor'     => $sub( 'section_anchor', '' ),
		'divider'    => function_exists( 'get_sub_field' ) ? (bool) get_sub_field( 'section_divider' ) : false,
		'is_primary' => (bool) ( $context['is_primary'] ?? false ),
	);
}

if ( ! function_exists( 'salt_render_section' ) ) {
	/**
	 * Resolve a Flexible Content layout to its section partial (PLUGGABLE — ARCH07).
	 *
	 * A missing layout degrades to fallback.php — error_logged, editor-visible,
	 * never a silent public blank and never a fatal (ARCH05).
	 *
	 * @param string              $layout  Layout slug (hyphenated).
	 * @param array<string,mixed> $context Loop context: index, is_primary.
	 * @return void
	 */
	function salt_render_section( string $layout, array $context = array() ): void {
		$slug    = 'template-parts/sections/' . $layout;
		$located = locate_template( $slug . '.php' );

		if ( '' === $located ) {
			salt_log( sprintf( 'section layout "%s" has no template — rendered fallback (ARCH05).', $layout ) );
			$fallback = locate_template( 'template-parts/sections/fallback.php' );
			if ( '' !== $fallback ) {
				load_template( $fallback, false, array( 'missing_layout' => $layout ) );
			}
			return;
		}

		load_template( $located, false, $context );
	}
}
