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
 * Since unit 8 it also holds what the contract's wrapper (salt_section()
 * with `contract`) needs of the page so far: each section id's count for
 * data-track (`tracks`), the previous band's dark tone, background image
 * and spacing for the adjacency collapse, and whether a section has taken
 * the page's priority image (`priority_media`); and the page plan's minted
 * id for the row a loop is drawing (`plan_id`, salt_section_plan_rows())
 * and the minted ids a band has already drawn (`plan_drawn`).
 *
 * @return array{h1_claimed: bool, h1_source: string, section_index: int, anchors: array<string,bool>, last_background: string, last_divider: bool, last_tone_dark: string, last_media: bool, last_spacing: string, tracks: array<string,int>, priority_media: bool, plan_id: string, plan_drawn: array<string,bool>}
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
			'last_tone_dark'  => '',
			'last_media'      => false,
			'last_spacing'    => '',
			'tracks'          => array(),
			'priority_media'  => false,
			'plan_id'         => '',
			'plan_drawn'      => array(),
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
 * The landmark ids an editor anchor may not take (section#anchors
 * `reserved`).
 *
 * @return string[]
 */
function salt_reserved_anchor_ids(): array {
	return array( 'main', 'content', 'header', 'footer', 'nav', 'site-navigation', 'search', 'skip-link' );
}

/**
 * The next free id for a slug under section#anchors: a landmark id becomes
 * <slug>-section, then a taken id tries -<n> from $from (-2, -3 and up
 * from the default), keeping the bare slug when $from is 1 and it is free.
 *
 * @param string             $slug  A non-empty slug.
 * @param array<string,bool> $taken Ids already settled on the page.
 * @param int                $from  The first number to ask for (1 = the bare slug).
 * @return string
 */
function salt_anchor_settle( string $slug, array $taken, int $from = 1 ): string {
	$reserved = salt_reserved_anchor_ids();
	if ( in_array( $slug, $reserved, true ) ) {
		$slug .= '-section';
	}

	for ( $n = max( 1, $from ); ; ++$n ) {
		$id = 1 === $n ? $slug : $slug . '-' . $n;
		if ( ! isset( $taken[ $id ] ) && ! in_array( $id, $reserved, true ) ) {
			return $id;
		}
	}
}

/**
 * Settle an id in one pass down the page (EDIT02): slug it, then take the
 * next free id as salt_anchor_settle() does, and register it.
 *
 * Every anchored band settles its id here as it draws (section#anchors:
 * among the anchored bands drawn so far, the loop's minted ids already
 * registered). An unanchored band in a page's loop or a post's layout takes
 * the plan's minted id (salt_section_plan_rows()) instead; one drawn
 * outside a loop, and any other id a view mints, settles here too.
 *
 * @param string $raw Editor input.
 * @return string Sanitised unique ID, or '' when input yields nothing.
 */
function salt_anchor_id( string $raw ): string {
	$slug = sanitize_title( $raw );
	if ( '' === $slug ) {
		return '';
	}

	$anchors = salt_render_state()['anchors'];
	$unique  = salt_anchor_settle( $slug, $anchors );

	$anchors[ $unique ] = true;
	salt_render_state( 'set', 'anchors', $anchors );

	return $unique;
}

/**
 * The page plan's ids for a page's sections (section#anchors, SC-013,
 * SC-025), settled in the contract's two steps:
 *  1. the rows with an anchor that draw a band, in page order: each takes its slug, a
 *     landmark id becoming <anchor>-section, and an id an earlier anchored
 *     row took or a landmark id taking the next free -n;
 *  2. the rows with none, in page order: each mints one from its section
 *     id, asking first for the number of its place among the unanchored
 *     band rows of that section id in the STORED layout (the bare id, then
 *     -2, ...), counting the rows that draw nothing or are not on this
 *     page, then taking the next free number from there.
 * So an editor's anchor never moves for an unanchored row, wherever the
 * two sit, and a minted id never moves when a row above it empties. A row
 * that is never a band (a post's prose) takes no id and no place, so prose
 * above a rich-text band leaves it #rich-text (the founder's ruling).
 *
 * @param array<int, array{section: string, anchor: string, band?: bool}> $rows The stored rows in order:
 *        section id, raw anchor, and whether the row draws as a band at
 *        all (false for a post's prose row; default true).
 * @param array<string,bool> $taken Ids already settled on the page, which neither step takes.
 * @return array<int,string> Each row's id, by the rows' keys; '' for a row that draws no band.
 */
function salt_section_plan_ids( array $rows, array $taken = array() ): array {
	$ids    = array();
	$places = array();

	foreach ( $rows as $key => $row ) {
		$ids[ $key ] = '';
		$slug        = sanitize_title( $row['anchor'] );
		if ( '' === $slug || false === ( $row['band'] ?? true ) ) {
			continue;
		}
		$ids[ $key ]           = salt_anchor_settle( $slug, $taken );
		$taken[ $ids[ $key ] ] = true;
	}

	foreach ( $rows as $key => $row ) {
		if ( '' !== sanitize_title( $row['anchor'] ) || false === ( $row['band'] ?? true ) ) {
			continue;
		}
		$places[ $row['section'] ] = ( $places[ $row['section'] ] ?? 0 ) + 1;

		$base = sanitize_title( $row['section'] );
		if ( '' === $base ) {
			continue;
		}
		$ids[ $key ]           = salt_anchor_settle( $base, $taken, $places[ $row['section'] ] );
		$taken[ $ids[ $key ] ] = true;
	}

	return $ids;
}

/**
 * Plan a loop's minted ids (the first walk of a page's loop and of a post's
 * layout): salt_section_plan_ids() over every stored row, with every
 * anchored row's name held, so a minted id avoids each editor's anchor
 * whether or not its row draws, and does not move when one above it
 * empties or fills. The minted ids are registered before any band draws;
 * an anchored band's own id is settled as it draws (salt_section()), so a
 * row that draws nothing never takes an editor's anchor. A row hidden on
 * this page (firstPageOnly past page 1) still settles and holds its minted
 * id, so every minted id is the same on every page, but is given none.
 * Walks the rows to the end, which ends the ACF loop: the caller's own
 * have_rows() loop then starts afresh.
 *
 * @param string        $selector The Flexible Content field (name or key).
 * @param int|false     $post_id  The post, or false for the current one.
 * @param callable|null $is_band  function(): bool, read on the current row:
 *                                whether it draws as a band at all (a post's
 *                                prose row does not). Such a row takes no id
 *                                and no place in a minted id's number.
 *                                Null: every row is a band.
 * @return array<int,string> Each stored row's minted id, by its place from 0; '' for an anchored row, a hidden one or one with none.
 */
function salt_section_plan_rows( string $selector, $post_id = false, ?callable $is_band = null ): array {
	$rows = array();
	while ( have_rows( $selector, $post_id ) ) {
		the_row();
		$anchor = salt_section_settings()['anchorId'] ?? '';
		$anchor = is_string( $anchor ) ? $anchor : '';
		$rows[] = array(
			'section' => salt_section_slug_for_layout( (string) get_row_layout() ),
			'anchor'  => $anchor,
			'band'    => '' !== sanitize_title( $anchor ) || null === $is_band || $is_band(),
			'hidden'  => salt_section_hidden_on_this_page(),
		);
	}

	$ids     = array();
	$anchors = salt_render_state()['anchors'];
	foreach ( salt_section_plan_ids( $rows, $anchors ) as $row => $id ) {
		if ( '' !== $id && '' === sanitize_title( $rows[ $row ]['anchor'] ) ) {
			$anchors[ $id ] = true;
		}
		$ids[ $row ] = $rows[ $row ]['hidden'] || '' !== sanitize_title( $rows[ $row ]['anchor'] ) ? '' : $id;
	}
	salt_render_state( 'set', 'anchors', $anchors );

	return $ids;
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
 * THE SHARED CONTRACT WRAPPER (EP-72 M4, ID-3620): how a section moves to
 * the contract's band (salt-contract markup/section.json). Every converted
 * section does the same three things, and nothing else of the band:
 *  1. Its resolver returns as `section` the contract args for its row,
 *     plus whether it draws a heading:
 *       array_merge( salt_section_contract_args( '<section id>', $context ),
 *                    array( 'has_heading' => null !== $heading ) )
 *     (the section id is the contract's, kebab case). A block that draws
 *     a principal image asks for the page's priority image with
 *     'plan' => array( 'priorityMedia' => salt_section_plan_priority( $context ) && <it draws that image> ).
 *     The shared entry (salt_render_resolved_section()) gives the image to
 *     the first band that has one, its background image before its block's,
 *     and refuses every later band (section#priority-media, SC-026).
 *  2. Its view draws its block, div.salt-block[data-block=<section id>],
 *     and nothing of the band, from $args['plan']: headingLevel, headingId
 *     (<anchor>__heading, the id its heading carries, or null when it draws
 *     none), bodyBaseLevel (section#body-heading-base), index (the band's
 *     place among the page's rendered bands, from 0: accordion names) and
 *     priorityMedia.
 *  3. It never prints an id of its own but <anchor>__<part> (plan.anchor).
 * Sections that have not moved pass no `contract` and keep x-section
 * (components/section.php) until their tranche converts them.
 *
 * With `contract` the band (components/contract-section.php) is:
 *  - a section named by its heading (aria-labelledby <anchor>__heading)
 *    when has_heading, otherwise a div.salt-section (section#labelled-by);
 *  - id: an editor's anchor, settled as the band draws among the anchored
 *    bands drawn so far (salt_anchor_id()); with none, the page plan's
 *    minted id (salt_section_plan_rows(): the contract's two steps, every
 *    editor's anchor held), from `id` or the loop's `plan_id`; a band drawn
 *    outside a loop mints one from its name in one pass;
 *  - data-tone, data-tone-dark (auto is the tone itself, SC-016),
 *    data-spacing, data-width, data-divider, data-media (a background image
 *    drawn), --salt-section-space when spacing is not none;
 *  - data-track <name>-<n>, n counting that section id on the page from 1
 *    (section#data-track);
 *  - data-collapse-top as section#adjacent-collapse says: the previous band
 *    shares the tone and dark tone, neither has a background image, this
 *    band has no divider and neither spacing is none.
 * An x-section band with an editor's anchor settles it the same way, as it
 * draws; one without draws no id, as before.
 *
 * The content callback receives the heading level and the settled id:
 * function( int $heading_level, string $anchor ): void.
 *
 * @param array<string,mixed> $args    Wrapper args: name (required), spacing,
 *                                     background, container, anchor, id,
 *                                     divider, is_primary, has_heading,
 *                                     flush_top; and for the contract's
 *                                     wrapper: contract, tone, tone_dark,
 *                                     background_image
 *                                     (salt_section_background()) and
 *                                     background_priority.
 * @param callable            $content function( int $heading_level, string $anchor ): void — echoes the body.
 * @return void
 */
function salt_section( array $args, callable $content ): void {
	$contract = array(
		'name'                => array(
			'type'     => 'string',
			'required' => true,
		),
		'spacing'             => array(
			'type'    => 'string',
			'default' => 'md',
		),
		'background'          => array(
			'type'    => 'string',
			'default' => 'surface',
		),
		'container'           => array(
			'type'    => 'string',
			'default' => 'default',
		),
		'anchor'              => array(
			'type'    => 'string',
			'default' => '',
		),
		'id'                  => array(
			'type'    => 'string',
			'default' => '',
		),
		'divider'             => array(
			'type'    => 'bool',
			'default' => false,
		),
		'is_primary'          => array(
			'type'    => 'bool',
			'default' => false,
		),
		'has_heading'         => array(
			'type'    => 'bool',
			'default' => true,
		),
		'flush_top'           => array(
			'type'    => 'bool',
			'default' => false,
		),
		'contract'            => array(
			'type'    => 'bool',
			'default' => false,
		),
		'tone'                => array(
			'type'    => 'string',
			'default' => 'surface',
		),
		'tone_dark'           => array(
			'type'    => 'string',
			'default' => '',
		),
		'background_image'    => array(
			'type'    => 'mixed',
			'default' => null,
		),
		'background_priority' => array(
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

	// The id is settled before the body draws, so the body can name its
	// heading <anchor>__heading (SC-012). An editor's anchor settles now, as
	// the band draws, among the anchored bands drawn so far and the page
	// plan's minted ids (section#anchors): a row that draws nothing never
	// takes one. A contract band with none takes the plan's minted id: its
	// `id`, or else the one the loop holds for its row (`plan_id`), which a
	// child's own template or an override of salt_render_section() reaches
	// without passing it on. The row's id is its first band's alone: a later
	// band of the same row mints its own in one pass.
	$planned = '' !== $args['id'] ? $args['id'] : $state['plan_id'];
	salt_render_state( 'set', 'plan_id', '' );
	if ( isset( $state['plan_drawn'][ $planned ] ) ) {
		$planned = '';
	}
	if ( '' !== sanitize_title( $args['anchor'] ) ) {
		$anchor = salt_anchor_id( $args['anchor'] );
	} elseif ( $args['contract'] && '' !== $planned ) {
		$anchor                          = $planned;
		$state['plan_drawn'][ $planned ] = true;
		salt_render_state( 'set', 'plan_drawn', $state['plan_drawn'] );
	} elseif ( $args['contract'] ) {
		$anchor = salt_anchor_id( $args['name'] );
	} else {
		$anchor = '';
	}

	$tracks                  = $state['tracks'];
	$tracks[ $args['name'] ] = ( $tracks[ $args['name'] ] ?? 0 ) + 1;
	salt_render_state( 'set', 'tracks', $tracks );

	// The dark tone in the state's vocabulary (x-section's inverse), so a
	// contract band and an x-section band compare alike.
	$tone_dark = '' === $args['tone_dark'] ? $args['background'] : ( 'surface-inverse' === $args['tone_dark'] ? 'inverse' : $args['tone_dark'] );
	$has_media = is_array( $args['background_image'] );

	if ( $args['contract'] ) {
		$last_dark    = '' !== $state['last_tone_dark'] ? $state['last_tone_dark'] : $state['last_background'];
		$collapse_top = '' !== $state['last_background']
			&& $state['last_background'] === $args['background']
			&& $last_dark === $tone_dark
			&& ! $state['last_media']
			&& ! $has_media
			&& ! $args['divider']
			&& 'none' !== $args['spacing']
			&& 'none' !== $state['last_spacing'];
	} else {
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
	}
	salt_render_state( 'set', 'last_background', $args['background'] );
	salt_render_state( 'set', 'last_divider', $args['divider'] );
	salt_render_state( 'set', 'last_tone_dark', $tone_dark );
	salt_render_state( 'set', 'last_media', $has_media );
	salt_render_state( 'set', 'last_spacing', $args['spacing'] );

	ob_start();
	$content( $level, $anchor );
	$body = (string) ob_get_clean();

	if ( $args['contract'] ) {
		salt_component(
			'contract-section',
			array(
				'anchor'              => $anchor,
				'labelled'            => $args['has_heading'],
				'tone'                => $args['tone'],
				'tone_dark'           => '' !== $args['tone_dark'] ? $args['tone_dark'] : $args['tone'],
				'spacing'             => $args['spacing'],
				'width'               => $args['container'],
				'track'               => $args['name'] . '-' . $tracks[ $args['name'] ],
				'collapse_top'        => $collapse_top,
				'divider'             => $args['divider'],
				'background_image'    => $has_media ? $args['background_image'] : null,
				'background_priority' => $args['background_priority'],
			),
			array( 'default' => $body )
		);
		return;
	}

	salt_component(
		'section',
		array(
			'name'         => $args['name'],
			'spacing'      => $args['spacing'],
			'background'   => $args['background'],
			'container'    => $args['container'],
			'anchor'       => $anchor,
			'divider'      => $args['divider'],
			'index'        => $index,
			'collapse_top' => $collapse_top,
		),
		array( 'default' => $body )
	);
}

/**
 * Build the x-section wrapper args for the current Flexible Content row
 * from its `settings` group (the contract's section settings,
 * contract/fields/_section-settings.json) + loop context (AMBIG03).
 *
 * Every setting is read by its contract name and value and mapped to the
 * wrapper's args: spacing, tone (surface-inverse is x-section's inverse
 * background), width (x-section's container), anchorId and divider.
 * toneDark and backgroundImage are stored but not drawn yet: they need the
 * contract stylesheet (EP-72 M3 unit 9). firstPageOnly is the page loop's
 * (salt_section_hidden_on_this_page()). The context's `id` is the loop's
 * id for the band, passed on so a child's own template keeps it.
 *
 * @param string              $name    Section slug (hyphenated).
 * @param array<string,mixed> $context Loop context: index, is_primary, id.
 * @return array<string,mixed>
 */
function salt_section_wrapper_args( string $name, array $context = array() ): array {
	$settings = salt_section_settings();
	$setting  = static function ( string $field, string $fallback ) use ( $settings ): string {
		$value = $settings[ $field ] ?? null;
		return is_string( $value ) && '' !== $value ? $value : $fallback;
	};

	$tone = $setting( 'tone', 'surface' );

	return array(
		'name'       => $name,
		'spacing'    => $setting( 'spacing', 'md' ),
		'background' => 'surface-inverse' === $tone ? 'inverse' : $tone,
		'container'  => $setting( 'width', 'default' ),
		'anchor'     => $setting( 'anchorId', '' ),
		'divider'    => (bool) ( $settings['divider'] ?? false ),
		'is_primary' => (bool) ( $context['is_primary'] ?? false ),
		// The loop's minted id for the band (salt_section_plan_rows()), so a
		// child's own template keeps it.
		'id'         => (string) ( $context['id'] ?? '' ),
	);
}

/**
 * The contract wrapper's args for the current row (salt_section() with
 * `contract`): salt_section_wrapper_args() plus the tone in the contract's
 * vocabulary, the dark tone resolved (auto is the tone itself, SC-016) and
 * the background image (salt_section_background()).
 *
 * @param string              $name    Contract section id.
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array<string,mixed>
 */
function salt_section_contract_args( string $name, array $context = array() ): array {
	$settings = salt_section_settings();
	$args     = salt_section_wrapper_args( $name, $context );
	$tones    = array( 'surface', 'surface-alt', 'brand-tint', 'surface-inverse' );
	// WordPress's stored 'inverse' is the contract's surface-inverse
	// (markup/section.json).
	$stored = array();
	foreach ( array( 'tone', 'toneDark' ) as $field ) {
		$stored[ $field ] = 'inverse' === ( $settings[ $field ] ?? null ) ? 'surface-inverse' : ( $settings[ $field ] ?? null );
	}
	$tone = in_array( $stored['tone'], $tones, true ) ? (string) $stored['tone'] : 'surface';
	$dark = in_array( $stored['toneDark'], $tones, true ) ? (string) $stored['toneDark'] : $tone;

	if ( ! in_array( $args['spacing'], array( 'none', 'sm', 'md', 'lg' ), true ) ) {
		$args['spacing'] = 'md';
	}
	if ( ! in_array( $args['container'], array( 'full', 'wide', 'default', 'narrow' ), true ) ) {
		$args['container'] = 'default';
	}

	return array_merge(
		$args,
		array(
			'contract'         => true,
			'background'       => 'surface-inverse' === $tone ? 'inverse' : $tone,
			'tone'             => $tone,
			'tone_dark'        => $dark,
			'background_image' => salt_section_background( $settings ),
		)
	);
}

/**
 * Whether the page's priority image is still free for this band
 * (section#priority-media, view-props/_shared.json plan.priorityMedia).
 *
 * The contract gives it to the first band on the page that HAS a principal
 * image, so a page that opens with a text band gives it to the first image
 * below (SC-026). A resolver asks only for an image it draws (see
 * salt_section()); the shared entry grants the first band that asks and
 * marks the image taken (salt_render_resolved_section()).
 *
 * @param array<string,mixed> $context Loop context (unused: the plan is the page's).
 * @return bool
 */
function salt_section_plan_priority( array $context ): bool {
	unset( $context );
	return ! salt_render_state()['priority_media'];
}

/**
 * The settings' background image as the contract's background
 * (view-props/_shared.json background), or null unless an image is set and
 * resolves. Its image is the full slot's (sizes 100vw).
 *
 * @param array<string,mixed> $settings The row's settings group.
 * @return array{image: array<string,mixed>, fit: string, position: string, scrim: bool, scrimStrength: string}|null
 */
function salt_section_background( array $settings ): ?array {
	$background = is_array( $settings['backgroundImage'] ?? null ) ? $settings['backgroundImage'] : array();
	$image      = salt_image_contract( (int) ( $background['image'] ?? 0 ), salt_image_slot_sizes( 'full' ) );
	if ( null === $image ) {
		return null;
	}
	$pick = static fn( string $key, array $allowed, string $fallback ): string => in_array( $background[ $key ] ?? null, $allowed, true ) ? (string) $background[ $key ] : $fallback;

	return array(
		'image'         => $image,
		'fit'           => $pick( 'fit', array( 'cover', 'contain' ), 'cover' ),
		'position'      => $pick( 'position', array( 'focal-point', 'top-left', 'top', 'top-right', 'left', 'centre', 'right', 'bottom-left', 'bottom', 'bottom-right' ), 'focal-point' ),
		'scrim'         => (bool) ( $background['scrim'] ?? true ),
		'scrimStrength' => $pick( 'scrimStrength', array( 'subtle', 'standard', 'strong' ), 'strong' ),
	);
}

/**
 * The current Flexible Content row's `settings` group (the contract's
 * section settings), or an empty array when it has none.
 *
 * @return array<string,mixed>
 */
function salt_section_settings(): array {
	$settings = function_exists( 'get_sub_field' ) ? get_sub_field( 'settings' ) : null;
	return is_array( $settings ) ? $settings : array();
}

/**
 * Whether the current row is a section the contract hides here: its
 * firstPageOnly setting is on and this is a later page of a paginated
 * view (`paged`, or `page` on a split singular page).
 *
 * @return bool
 */
function salt_section_hidden_on_this_page(): bool {
	if ( empty( salt_section_settings()['firstPageOnly'] ) ) {
		return false;
	}
	return max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ) > 1;
}

/**
 * A contract link (contract field type `link`: label, type internal|
 * external, document, url, newTab) as x-button's label/url/target, or
 * null when it does not make a usable link: no label, an internal link
 * whose document has no address, or an external link with no address or
 * one esc_url() refuses.
 *
 * @param mixed $link The stored link group.
 * @return array{label: string, url: string, target: string}|null
 */
function salt_contract_link( $link ): ?array {
	if ( ! is_array( $link ) ) {
		return null;
	}

	$label = trim( is_string( $link['label'] ?? null ) ? $link['label'] : '' );
	if ( 'external' === ( $link['type'] ?? 'internal' ) ) {
		$url = trim( is_string( $link['url'] ?? null ) ? $link['url'] : '' );
	} else {
		$document = $link['document'] ?? 0;
		$document = $document instanceof WP_Post ? (int) $document->ID : (int) $document;
		$url      = $document > 0 ? (string) get_permalink( $document ) : '';
	}

	// An address esc_url() would empty (a disallowed scheme such as
	// javascript:) is no address.
	if ( '' === $label || '' === $url || '' === esc_url_raw( $url ) ) {
		return null;
	}

	return array(
		'label'  => $label,
		'url'    => $url,
		'target' => empty( $link['newTab'] ) ? '' : '_blank',
	);
}

/**
 * A contract `buttons` list (rows of { link, style }) as x-button args, in
 * row order: rows that do not make a usable link are skipped and logged,
 * an unknown style becomes $default_style. A draft can exceed the publish
 * limit: still draw every usable button.
 *
 * @param mixed  $rows          The stored list.
 * @param string $where         Log prefix, e.g. 'section hero'.
 * @param int    $max           Publish limit (retained for call compatibility).
 * @param string $default_style The list's default style.
 * @return array<int, array{label: string, url: string, style: string, target: string}>
 */
function salt_contract_buttons( $rows, string $where, int $max, string $default_style = 'primary' ): array {
	$buttons = array();
	foreach ( is_array( $rows ) ? $rows : array() as $i => $row ) {
		$link = salt_contract_link( is_array( $row ) ? ( $row['link'] ?? null ) : null );
		if ( null === $link ) {
			salt_log( sprintf( '%s: button %d has no label or no address — skipped (broken row).', $where, (int) $i ) );
			continue;
		}

		$style = is_string( $row['style'] ?? null ) && '' !== $row['style'] ? $row['style'] : $default_style;
		if ( ! in_array( $style, array( 'primary', 'secondary', 'ghost' ), true ) ) {
			salt_log( sprintf( '%s: button %d has unknown style "%s" — using %s.', $where, (int) $i, $style, $default_style ) );
			$style = $default_style;
		}

		$buttons[] = array(
			'label'  => $link['label'],
			'url'    => $link['url'],
			'style'  => $style,
			'target' => $link['target'],
		);
	}

	if ( count( $buttons ) > $max ) {
		salt_log( sprintf( '%s: %d buttons exceed the publish limit of %d; rendered in full.', $where, count( $buttons ), $max ) );
	}

	return $buttons;
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
 * The section slug the loop renders for a row's stored layout name.
 *
 * A stored name maps "_" to "-" (blog_teaser => blog-teaser), EXCEPT a
 * layout name removed in 6.0.0 (ID-3599): one that is not registered
 * itself while its hyphenated form is (rich_text, media_text, now
 * rich-text and media-text). Such a row has none of the new layout's
 * sub-fields, so it is passed on unchanged, finds no template and takes
 * the fallback path (logged, editor marker — ARCH05), as an old cta row
 * does, instead of resolving to an empty section.
 *
 * @param string $stored Raw layout name (get_row_layout()).
 * @return string Section slug.
 */
function salt_section_slug_for_layout( string $stored ): string {
	static $registered = null;

	$slug = str_replace( '_', '-', $stored );
	if ( $slug === $stored ) {
		return $slug;
	}

	if ( null === $registered ) {
		$registered = array_column( salt_section_layouts(), 'name' );
	}

	return ! in_array( $stored, $registered, true ) && in_array( $slug, $registered, true ) ? $stored : $slug;
}

/**
 * The resolver function name for a section slug: the one place the
 * hyphen rule lives (docs/contracts/CONVENTIONS.md, "The resolver").
 *
 * Section slugs are the salt-contract section ids verbatim
 * (`call-to-action`), and the layout name, resolver file and view file all
 * use them as they are. PHP function names cannot contain a hyphen, so the
 * function name alone writes each "-" as "_":
 * `call-to-action` => salt_resolve_section_call_to_action().
 *
 * @param string $layout Section slug (hyphenated).
 * @return string Function name.
 */
function salt_section_resolver_function( string $layout ): string {
	return 'salt_resolve_section_' . str_replace( '-', '_', $layout );
}

/**
 * Load a split section's resolver and return its function name.
 *
 * The file defines salt_section_resolver_function( $layout )( array
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

	$function = salt_section_resolver_function( $layout );
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
 *             (today: priorityMedia, its request for the page's priority
 *             image, granted only to the first band that asks).
 * The context's `id` is the row's minted id (salt_section_plan_rows()),
 * '' for an anchored row, whose band settles its editor's anchor as it
 * draws (salt_section()); it reaches the wrapper as `id` unless the
 * section args name one. The view is then loaded INSIDE the wrapper with
 * $args = array{ props: array, plan: array{ index: int, anchor: string, headingLevel: int, headingId: string|null, bodyBaseLevel: int, priorityMedia: bool } }
 * (anchor: the band's settled id, '' when it has none; headingId:
 * <anchor>__heading when the band draws a heading and has an id, else
 * null; bodyBaseLevel: headingLevel, or 1 when it draws no heading)
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
 * @param array<string,mixed> $context Loop context: index, is_primary, id.
 * @return void
 * @throws UnexpectedValueException When a resolver returns a malformed value and debug is active.
 */
function salt_render_resolved_section( string $layout, string $view, array $context = array() ): void {
	if ( null === salt_section_resolver_file( $layout ) ) {
		load_template( $view, false, $context );
		return;
	}

	if ( null !== salt_overrides() && 'logic' === salt_override_tier( $layout ) ) {
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

	$props       = $resolved['props'];
	$index       = (int) ( $context['index'] ?? 0 );
	$section     = array_merge( array( 'id' => (string) ( $context['id'] ?? '' ) ), $resolved['section'] );
	$has_heading = (bool) ( $section['has_heading'] ?? true );

	// The page's one priority image (section#priority-media): the first band
	// with one takes it. A contract band's background image is its principal
	// image, over the block's own; a block's image counts when its resolver
	// asks for it (plan.priorityMedia).
	$free                           = ! salt_render_state()['priority_media'];
	$background                     = ! empty( $section['contract'] ) && is_array( $section['background_image'] ?? null );
	$section['background_priority'] = $free && $background;
	$priority_media                 = $free && ! $background && (bool) ( $plan_in['priorityMedia'] ?? false );
	if ( $section['background_priority'] || $priority_media ) {
		salt_render_state( 'set', 'priority_media', true );
	}

	salt_section(
		$section,
		static function ( int $level, string $anchor = '' ) use ( $layout, $view, $props, $index, $priority_media, $has_heading ): void {
			$before = salt_render_state()['section_index'];

			load_template(
				$view,
				false,
				array(
					'props' => $props,
					'plan'  => array(
						'index'         => $index,
						'anchor'        => $anchor,
						'headingLevel'  => $level,
						'headingId'     => $has_heading && '' !== $anchor ? $anchor . '__heading' : null,
						'bodyBaseLevel' => $has_heading ? $level : 1,
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
