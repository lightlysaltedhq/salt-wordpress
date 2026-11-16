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

// ---------------------------------------------------------------------------
// Section logic/view split (ID-3587 — docs/contracts/CONVENTIONS.md).
// ---------------------------------------------------------------------------

/**
 * A text field as a nullable view prop: null when blank, else the value
 * UNCHANGED (never trimmed — the view prints exactly what was stored).
 *
 * Resolvers use this for every optional text prop, so a view tests
 * `null !== $props['x']` instead of re-deciding emptiness.
 *
 * @param string $value Raw field value.
 * @return string|null
 */
function salt_nullable_text( string $value ): ?string {
	return '' === trim( $value ) ? null : $value;
}

/**
 * The resolver file of a split section, or null when the section has not
 * been split (its template is still the whole section).
 *
 * Resolvers live in the PARENT theme only — inc/sections/{layout}.php,
 * under get_template_directory(), never found through locate_template() —
 * so a child theme cannot replace a section's logic by dropping in a file.
 * The file's existence is what makes a section split. (A child takes a
 * section's logic over only by declaring it in salt-overrides.json and
 * supplying the whole template: salt_render_replaced_section().)
 *
 * @param string $layout Layout slug (hyphenated, as the loop passes it).
 * @return string|null Absolute path.
 */
function salt_section_resolver_file( string $layout ): ?string {
	if ( 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $layout ) ) {
		return null;
	}

	$file = get_template_directory() . '/inc/sections/' . $layout . '.php';
	return is_readable( $file ) ? $file : null;
}

/**
 * Load a split section's resolver and return its function name.
 *
 * The file defines salt_resolve_section_{layout, "-" as "_"}( array
 * $context ): ?array. Resolvers are final (not pluggable). Fails loudly —
 * salt_log(), and a throw under salt_is_debug() — and returns null when:
 *  - the section is split but the file does not define that function
 *    (a mis-named resolver must never fall back to the unsplit path);
 *  - the function already exists from another file (a child or plugin
 *    defined it): the parent file is NOT loaded, so PHP never fatals on
 *    the redeclaration, and the other definition is never used.
 * Returns null without logging when the section is not split.
 *
 * @param string $layout Layout slug (hyphenated).
 * @return string|null Resolver function name.
 * @throws LogicException On a mis-named or conflicting resolver when debug is active.
 */
function salt_section_resolver( string $layout ): ?string {
	$file = salt_section_resolver_file( $layout );
	if ( null === $file ) {
		return null;
	}

	$function = 'salt_resolve_section_' . str_replace( '-', '_', $layout );
	$message  = '';

	if ( function_exists( $function ) && realpath( (string) ( new ReflectionFunction( $function ) )->getFileName() ) !== realpath( $file ) ) {
		$message = sprintf( 'section "%1$s": %2$s() is already defined in %3$s — a resolver cannot be replaced (only the parent\'s inc/sections/%1$s.php may define it); section skipped.', $layout, $function, (string) ( new ReflectionFunction( $function ) )->getFileName() );
	} else {
		require_once $file;
		if ( ! function_exists( $function ) ) {
			$message = sprintf( 'section "%1$s": inc/sections/%1$s.php does not define %2$s() — section skipped.', $layout, $function );
		}
	}

	if ( '' !== $message ) {
		salt_log( $message );
		if ( salt_is_debug() ) {
			throw new LogicException( esc_html( $message ) );
		}
		return null;
	}

	return $function;
}

/**
 * Render a split section the child theme has DECLARED replaced: tier
 * "logic" in salt-overrides.json (inc/overrides.php; OVERRIDES.md
 * "Declaring replaced sections").
 *
 * The site has taken the section over entirely, so nothing of the parent's
 * runs for it: no resolver, no wrapper, no H1 claim, no schema. The child's
 * template-parts/sections/{layout}.php is loaded WHOLE with the loop context
 * as $args (index, is_primary), exactly as an unsplit section's template
 * is, and composes the section itself: it must call salt_section(), or the
 * render state (section index, adjacency collapse) is left describing the
 * section before it — logged as a warning when it draws markup without it.
 *
 * Fails loudly — salt_log(), a throw under salt_is_debug(), nothing
 * rendered — when the located template is not the child's: a declaration
 * with no child template behind it must not fall back to the parent view,
 * which cannot draw without props.
 *
 * @param string              $layout  Layout slug (hyphenated).
 * @param string              $view    Located template path.
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return void
 * @throws LogicException When the child theme has no template for the layout and debug is active.
 */
function salt_render_replaced_section( string $layout, string $view, array $context = array() ): void {
	if ( salt_override_is_child_file( $view ) ) {
		$before = salt_render_state()['section_index'];

		ob_start();
		load_template( $view, false, $context );
		$html = (string) ob_get_clean();
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The child template's own output, passed through unchanged.

		// Drawn without the shared band: the render state still describes the
		// section before this one, so the next section's index and adjacency
		// collapse are computed from the wrong neighbour.
		if ( '' !== $html && salt_render_state()['section_index'] === $before ) {
			salt_log( sprintf( 'section "%1$s": its replacing template (tier "logic") drew markup without calling salt_section() — the next section\'s index and spacing collapse are now wrong. Wrap the section in salt_section() (OVERRIDES.md).', $layout ) );
		}
		return;
	}

	$message = sprintf( 'section "%1$s": %2$s declares it replaced (tier "logic"), but the child theme has no template-parts/sections/%1$s.php — section skipped.', $layout, SALT_OVERRIDES_FILE );
	salt_log( $message );
	if ( salt_is_debug() ) {
		throw new LogicException( esc_html( $message ) );
	}
}

/**
 * Render one section from its located template: resolver → 0-state →
 * wrapper → view (the shared entry every split section goes through).
 *
 * The resolver does ALL the logic (field reads, queries, sanitising,
 * schema, row skipping + logging, wrapper inputs) and returns either
 * null — the 0-state: render nothing, no wrapper band (COMPL02) — or
 * array{ section: array, props: array, plan?: array{ priorityMedia?: bool } }:
 *  - section: the salt_section() wrapper args, has_heading included;
 *  - props:   the view props;
 *  - plan:    optional — the contract plan keys only the section can know
 *             (today: priorityMedia).
 * The view is then loaded INSIDE the wrapper with
 * $args = array{ props: array, plan: array{ index: int, headingLevel: int, priorityMedia: bool } }
 * and only draws markup.
 *
 * A section that is not split (no inc/sections/{layout}.php — not yet
 * converted, or a child-only layout) loads its template exactly as before,
 * with the loop context as $args. A split section whose resolver cannot be
 * loaded renders nothing (salt_section_resolver() has logged / thrown).
 * A split section the child theme's salt-overrides.json declares replaced
 * (tier "logic") skips the parent resolver and wrapper and loads the
 * child's template whole (salt_render_replaced_section()).
 *
 * @param string              $layout  Layout slug (hyphenated).
 * @param string              $view    Located template path.
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return void
 * @throws UnexpectedValueException When a resolver returns a malformed value and debug is active.
 */
function salt_render_resolved_section( string $layout, string $view, array $context = array() ): void {
	if ( null === salt_section_resolver_file( $layout ) ) {
		load_template( $view, false, $context );
		return;
	}

	if ( null !== salt_overrides() && 'logic' === salt_override_tier( $layout, salt_contract_section_variant( $layout ) ) ) {
		salt_render_replaced_section( $layout, $view, $context );
		return;
	}

	$resolver = salt_section_resolver( $layout );
	if ( null === $resolver ) {
		return; // Split, but the resolver is unusable: already logged (fail loud, never the unsplit path).
	}

	$resolved = $resolver( $context );
	if ( null === $resolved ) {
		return; // 0-state: nothing to render (COMPL02).
	}

	$plan_in = is_array( $resolved ) ? ( $resolved['plan'] ?? array() ) : null;
	if (
		! is_array( $resolved )
		|| ! is_array( $resolved['section'] ?? null )
		|| ! is_array( $resolved['props'] ?? null )
		|| ! is_array( $plan_in )
		|| array() !== array_diff( array_keys( $plan_in ), array( 'priorityMedia' ) )
	) {
		$message = sprintf( 'section "%s": %s() must return null or array{section: array, props: array, plan?: array{priorityMedia?: bool}} — section skipped.', $layout, $resolver );
		salt_log( $message );
		if ( salt_is_debug() ) {
			throw new UnexpectedValueException( esc_html( $message ) );
		}
		return;
	}

	$props          = $resolved['props'];
	$index          = (int) ( $context['index'] ?? 0 );
	$priority_media = (bool) ( $plan_in['priorityMedia'] ?? false );

	salt_section(
		$resolved['section'],
		static function ( int $level ) use ( $layout, $view, $props, $index, $priority_media ): void {
			$before = salt_render_state()['section_index'];

			load_template(
				$view,
				false,
				array(
					'props' => $props,
					'plan'  => array(
						'index'         => $index,
						'headingLevel'  => $level,
						'priorityMedia' => $priority_media,
					),
				)
			);

			// A view that opens its own wrapper is a whole-section override
			// written before the split: say so instead of nesting silently.
			if ( salt_render_state()['section_index'] !== $before ) {
				salt_log( sprintf( 'section "%s": its view called salt_section() — a whole-section override from before the logic/view split? A view receives $args[\'props\'] and draws markup only (OVERRIDES.md).', $layout ) );
			}
		}
	);
}

if ( ! function_exists( 'salt_render_section' ) ) {
	/**
	 * Resolve a Flexible Content layout to its section partial (PLUGGABLE — ARCH07).
	 *
	 * A missing layout degrades to fallback.php — error_logged, editor-visible,
	 * never a silent public blank and never a fatal (ARCH05).
	 *
	 * The located template is handed to salt_render_resolved_section(): for
	 * a split section it is the VIEW, drawn from its parent resolver's props;
	 * for a section not yet split it is loaded whole, as before. A child
	 * that overrides this function must render through
	 * salt_render_resolved_section(), never load_template() directly.
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

		salt_render_resolved_section( $layout, $located, $context );
	}
}
