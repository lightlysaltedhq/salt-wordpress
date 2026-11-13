# salt — code conventions (frozen contract)

Everything in the parent theme follows these rules. Phase 2 section agents
consume this document verbatim and must not deviate.

## PHP

- PHP 8.2+, WordPress-Extra PHPCS ruleset, tabs, `salt_` prefix on every
  global symbol. `WordPress.Security.*` sniffs are CI **errors** (SEC08).
- Every file starts with a docblock and an `ABSPATH` guard (templates and
  `inc/` modules alike). i18n: `__( '…', 'salt' )` from line one.
- Escape on output, always: `esc_html`, `esc_attr`, `esc_url`. WYSIWYG
  content goes through `salt_sanitize_html()` (never raw `the_content`-style
  output in sections).

## Pluggable functions & override contract (ARCH07)

- A **pluggable** function is wrapped in `if ( ! function_exists( … ) )` and
  listed in `docs/contracts/OVERRIDES.md`. Child themes override it by
  defining it first (child `functions.php` loads before parent). Adding or
  removing pluggability is a semver event.
- Everything else is final; extension points are **filters**, with priority
  bands: parent/core prep runs at priority 10; site/child overrides use
  20+; never use priorities below 10 in the parent.
- A pluggable function can be overridden **once** (child OR plugin). Where
  contention is plausible, the parent provides a filter instead.

## State

- No globals. Per-request state (heading claims, anchor registry, schema
  nodes) lives in `static` variables inside accessor functions, each with a
  `salt_*_reset()` companion so tests are hermetic.

## Errors (fail loud, never blank)

- Contract violations: `salt_require_args()` **throws** under `WP_DEBUG`
  and `error_log()`s + degrades gracefully in production. Silent blank
  renders are a defect.

## Tests

- PHPUnit, no WordPress boot. Shared WP shims live in `tests/wp-shims.php`
  (owned by the foundation — **agents must not edit it**; missing shims go
  in a new `tests/shims/{domain}.php` required from the test file).
- Unit tests: `tests/unit/{Thing}Test.php`, `final class`, snake_case or
  camelCase test methods, table-driven with data providers where natural.
- Snapshot tests: `tests/snapshot/{Component}SnapshotTest.php` extending
  `Salt_Snapshot_TestCase` — render with fixture args, compare to
  `tests/snapshot/__snapshots__/{name}.snap.html`. Regenerate ONLY via
  `SALT_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --testsuite snapshot` and
  commit the diff for review (FLEET01: a changed snapshot is the machine
  trigger for the semver major/minor decision).

## Components (`x-…`)

- One file per component: `template-parts/components/{name}.php` renders
  `<x-name>`; slot-style/validated components render via
  `salt_component( $name, $args, $slots )`; simple parts may use raw
  `get_template_part( …, $args )` (Open Q6 resolution — see OVERRIDES.md).
- Args contract declared at the top of the partial in a documented
  `$contract` array passed to `salt_require_args()`. Changing an args
  signature or markup structure = **major** bump.
- Components consume ONLY semantic role tokens (`--color-…` roles), never
  primitive ramp steps.

## ACF (fields-as-code)

- Registered in PHP under `inc/fields/`, hooked on `acf/init`, guarded by
  `salt_acf_active()` (DEP01 — absence shows an admin notice, never fatals).
- Canonical keys: groups `group_salt_{scope}`, fields
  `field_salt_{scope}_{name}` (DATA02). Renaming a field name or FC layout
  slug is a MAJOR bump + entry in `docs/contracts/slug-migrations.json`
  (FLEET07 — CI enforces).

## Schema

- Sections NEVER emit `<script type="application/ld+json">` themselves; they
  call `salt_schema_add( $node )` and the page-level graph builder assembles
  one deduplicated `@graph` per page (SEO01/02/03).

## Sections: logic and view

Every Flexible Content section is two files: a **resolver** (the logic)
and a **view** (the markup). The resolver returns the section's view
props, named after the design contract's `contract/view-props/{section}.json`;
the view draws them and does nothing else. A child theme overrides the
view only, so every fix to the logic still reaches it (OVERRIDES.md).

A section is *split* when `inc/sections/{slug}.php` exists. A section
without one is *unsplit*: its template is still the whole section and
renders exactly as before. Hero, FAQ and Rich Text are split; Rich Text
is the exemplar to copy.

### The resolver

- **File:** `inc/sections/{slug}.php`, where `{slug}` is the hyphenated
  template name (`rich-text`). Docblock + `ABSPATH` guard; it defines one
  function and nothing else.
- **Function:** `salt_resolve_section_{slug with "-" as "_"}( array $context ): ?array`
  — e.g. `salt_resolve_section_rich_text()`. `$context` is the loop
  context, `array{ index: int, is_primary: bool }`.
- **Final, not pluggable:** never wrapped in `function_exists()`. It is
  loaded from the PARENT theme directory by `salt_section_resolver()`,
  never through `locate_template()`, so no child file can replace it.
  Nothing registers it: the file's existence is the registration
  (`functions.php` and `tests/bootstrap.php` need no change).
- **Does all the logic:** field and option reads (`get_sub_field`,
  `get_field`, `salt_option`), queries (`salt_resolve_query`), sanitising
  (`salt_sanitize_html`), validation and fallbacks, broken-row skipping +
  `salt_log()`, schema (`salt_schema_add`), anything request-bound a view
  would print (nonces, time tokens, request status, admin URLs) and the
  wrapper inputs.
- **Returns** `null` (see the 0-state rule) or:

```php
array(
	'section' => array_merge(
		salt_section_wrapper_args( '{slug}', $context ), // then any overrides: container, flush_top
		array( 'has_heading' => '' !== trim( $heading ) ) // A11Y01
	),
	'props'   => array( /* view props */ ),
)
```

### The 0-state rule

- Return `null` exactly where the section renders nothing at all: module
  disabled, nothing to show under its COMPL02 rule, every row broken. On
  `null` the entry draws nothing: no wrapper band, no H1 claim, no view.
  Side effects already made before that point (logs) stand, as before.
- Otherwise return props, and the view MUST draw a non-empty body from
  them. A resolver never returns props that draw an empty band. A section
  that shows a visible empty-state message returns props describing that
  state, and its view draws the message.
- Schema is queued only on a path that returns props.

### Props

- **Names:** the contract's `data` names verbatim (camelCase:
  `structuredData`, `priorityMedia`) where a prop corresponds 1:1. Where
  WordPress has something the contract does not, or shapes it
  differently, use a snake_case WordPress-local name (`image_id`, `ctas`)
  and list it as WordPress-local in the resolver's docblock.
- **Optional text:** `salt_nullable_text( $raw )` — `null` when blank,
  otherwise the stored string UNCHANGED (never trimmed).
- **Rich text / HTML:** sanitised once, in the resolver, with
  `salt_sanitize_html()`. Never sanitise twice: the view prints it as it
  is.
- **Plain text, URLs, attributes:** raw strings; the view escapes them at
  output or passes them to a component that does.
- **Images:** a validated (resolvable) attachment ID, `0` for none,
  passed to x-image by the view.
- No pre-rendered HTML except sanitised rich text: the view renders the
  components (cards, images) itself.

### The shared entry

`salt_render_section()` → `salt_render_resolved_section( $layout, $view, $context )`
(`inc/render.php`) runs resolver → `null` check → `salt_section(
$resolved['section'], … )` → the view, loaded with:

```php
$args = array(
	'props' => $resolved['props'],
	'plan'  => array(
		'index'        => $context['index'], // loop position (counts skipped rows)
		'headingLevel' => $level,            // 1 or 2, from the wrapper (A11Y01)
	),
);
```

### The view

- **File:** `template-parts/sections/{slug}.php` — the same path the whole
  section had, so a child override of that path is an override of the
  view.
- Starts `$salt_props = $args['props']; $salt_plan = $args['plan'];`;
  docblock documents the `$args` shape.
- **May:** branch and loop over props; print markup; escape at output
  (`esc_html`, `esc_attr`, `esc_url`); print resolver-sanitised HTML
  props with a `phpcs:ignore` naming the resolver; call `salt_component()`
  / `salt_get_component()`; use i18n (`esc_html_e( '…', 'salt' )`);
  derive markup-only values from props (`'faq-' . $salt_plan['index']`).
  Main heading at `$salt_plan['headingLevel']`, sub-headings one below.
- **May not:** read fields or options, query, sanitise, validate, log,
  queue schema, enqueue, create nonces, call `salt_section()` /
  `salt_section_wrapper_args()` / `salt_claim_h1()` / `salt_anchor_id()`,
  or `return` before drawing anything (the 0-state is the resolver's;
  a `return` that ends a finished branch, as in hero, is fine).

### Converting a section (checklist)

1. Create `inc/sections/{slug}.php` with
   `salt_resolve_section_{slug_}( array $context ): ?array`.
2. Move everything above the old `salt_section()` call into it, in the
   same order: `$args ?? array()` becomes `$context`, `$salt_x` becomes
   `$x`, every `return;` becomes `return null;`.
3. Move every non-markup statement from INSIDE the old `salt_section()`
   callback into the resolver too (after the 0-state check, same relative
   order): field reads, queries, status/nonce/time-token/request reads,
   `salt_schema_add()`, `salt_log()`. Rendered output does not change;
   they now run before the wrapper claims the H1, so check any test that
   asserts an exact log ARRAY.
4. Build `props` (Props above) and `section` (`salt_section_wrapper_args()`
   + the old overrides + `has_heading`), and return them.
5. Rewrite the template as the view: the old callback body, with
   `$level` → `$salt_plan['headingLevel']`, `$args['index']` →
   `$salt_plan['index']`, `'' !== trim( $salt_x )` → `null !== $salt_props['x']`.
   Keep every component args array identical (same keys, values, order).
6. Walk each snapshot case of the section through old and new code: the
   `.snap.html` files must not change. Never regenerate one for a split.
7. Add resolver tests to `tests/unit/{Section}SectionTest.php`: resolve via
   `salt_section_resolver( '{slug}' )` after seeding the row; draw the view
   via `load_template( …, false, array( 'props' => …, 'plan' => … ) )`.
8. List the naming gaps against `contract/view-props/{section}.json` in
   the resolver docblock.

## Third parties

- None. `salt_consent()` is a fail-closed stub in this build (PRIV01): no
  asset, script, font, tile, or beacon from any non-site origin may be
  emitted by any code path.
