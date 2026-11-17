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
  `field_salt_{scope}_{name}` (DATA02). `{scope}` and `{name}` are
  written in snake_case in the key: a section's scope is its slug with
  `-` as `_` (`field_salt_call_to_action_body`), and a camelCase contract
  field name is split at its capitals (`mediaSide` →
  `field_salt_media_text_row_media_side`). Field NAMES follow the
  salt-contract's `contract/fields/*.json` verbatim (camelCase where the
  contract is). Renaming a field name or FC layout
  slug is a MAJOR bump + entry in `docs/contracts/slug-migrations.json`
  (FLEET07 — CI enforces).

## Schema

- Sections NEVER emit `<script type="application/ld+json">` themselves; they
  call `salt_schema_add( $node )` and the page-level graph builder assembles
  one deduplicated `@graph` per page (SEO01/02/03).

## Sections: logic and view

Every Flexible Content section is two files: a **resolver** (the logic)
and a **view** (the markup). The resolver returns the section's view
props, named after the design contract's view props; the view draws them
and does nothing else. A child theme overrides the view only, so every
fix to the logic still reaches it (OVERRIDES.md) — unless it DECLARES the
section replaced (tier `logic` in its `salt-overrides.json`), when its own
template is the whole section and none of the parent's code runs for it
(OVERRIDES.md "Declaring replaced sections").

A section is *split* when `inc/sections/{slug}.php` exists. All 20
parent sections are split; Rich Text is the exemplar to copy. A layout
with no parent resolver (a child-only layout) is *unsplit*: its template
is still the whole section and renders exactly as before.

**The split changes no output.** Every rule below exists to keep each
section's rendered bytes, queued schema and log lines identical. Where
today's behaviour looks like a bug, keep it, and report it in your
handoff as a proposed new Work Item; never fix it in passing.

### Where the contract lives, and how slugs map

The view props are specified in the `lightlysaltedhq/contracts`
repository, under `salt-contract/contract/view-props/{file}.json`
(`_shared.json` holds the shared types, including the plan). Read it;
salt-wordpress takes no dependency on it. `salt-contract/contract/sections.json`
records, per contract section, which WordPress layouts it was `formerly`.
Since 6.0.0 (ID-3599) every section WordPress builds as one layout has
the contract id as its slug, and its fields carry the contract's field
names (`contract/fields/*.json`); the layouts the contract folds into
collection-showcase and carousel, and intro, keep their former slugs
until they are folded. Every renamed slug and field is listed in
`docs/contracts/slug-migrations.json`.

| WordPress slug | Contract view-props file | Prop names |
|---|---|---|
| `hero` | `hero.json` | contract |
| `intro` | `rich-text.json` (folds into rich-text) | WordPress-local — no fold here |
| `rich-text` | `rich-text.json` | contract |
| `services` | `collection-showcase.json` (folds) | WordPress-local — no fold here |
| `features` | `features.json` | contract |
| `media-text` | `media-text.json` | contract |
| `stats` | `stats.json` | contract |
| `testimonials` | `collection-showcase.json` (grid rows) / `carousel.json` (slider rows), decided per row by the Layout field (folds) | WordPress-local — no fold here |
| `logos` | `logos.json` | contract |
| `call-to-action` | `call-to-action.json` | contract |
| `pricing` | `pricing.json` | contract |
| `team` | `collection-showcase.json` (folds) | WordPress-local — no fold here |
| `faq` | `faq.json` | contract |
| `process` | `process.json` | contract |
| `work` | `collection-showcase.json` (folds) | WordPress-local — no fold here |
| `gallery` | `gallery.json` | contract |
| `blog-teaser` | `collection-showcase.json` (folds) | WordPress-local — no fold here |
| `scroll-carousel` | `carousel.json` (folds) | WordPress-local — no fold here |
| `contact` | `contact.json` | contract, except the service and form props (below) |
| `locations` | `locations.json` | contract |

The same mapping, from contract section id to layout slugs, is code:
`salt_contract_section_layouts()` in `inc/overrides.php`, which reads a
child theme's `salt-overrides.json` by contract id. Testimonials is the
one layout whose contract section depends on its row:
`salt_contract_section_conditions()` maps a `grid` row to
collection-showcase and a `slider` row to carousel, normalising the field
with `salt_testimonials_layout()`, which the testimonials resolver uses
too. A new layout or a new
contract section adds a row to both (OverridesDeclarationTest fails on a
parent layout with no contract section).

"contract": name each prop after the file's `data` entry where it
corresponds 1:1. "WordPress-local — no fold here": the contract folds
this layout into another section; this work item does NOT fold it. Its
props keep WordPress-local snake_case names, and take the contract's name
only for a prop that is identical in meaning (`eyebrow`, `heading`). The
fold belongs to a later item. A prop such a section shares with other
sections but its nearest contract file lacks (intro's `eyebrow`) is
described as a "shared name", not "contract".

A plain-text value never takes a contract prop name whose contract type
is rich text: it gets a WordPress-local name (`body_text` in cta and
features, where the contract's `body` / item `text` are rich text).

Contact is contract-named, but its service and form props stay flat and
WordPress-local (named as rule 4 names them) instead of forming the
contract's nullable `form` object. The mapping: `form_enabled` = `form`
non-null; `form_action` = `form.action`; `privacy_url` =
`form.privacyHref`; `enquiry_types` = `form.enquiryTypes`, labels only;
`null !== challenge` = `form.captcha`. `form.id` and `form.phone` have no
WordPress counterpart yet; `request_status`, `nonce`, `time_token`,
`page_url` and `captcha_widget_html` have no contract counterpart.

### The resolver

- **File:** `inc/sections/{slug}.php`, where `{slug}` is the hyphenated
  template name (`rich-text`). Docblock + `ABSPATH` guard; it defines one
  function and nothing else.
- **Function:** `salt_resolve_section_{slug with "-" as "_"}( array $context ): ?array`
  — e.g. `salt_resolve_section_rich_text()`. `$context` is the loop
  context, `array{ index: int, is_primary: bool }`.
- **The hyphen rule:** a section's slug is the contract section id
  verbatim (`call-to-action`), and the ACF layout name, the resolver file,
  the view file and the layout's fields file all use it as it is. PHP
  function names cannot hold a hyphen, so the resolver's function name is
  the only place a `-` is written `_`. The rule is code in one place,
  `salt_section_resolver_function()` (`inc/render.php`); the render entry
  and the tests call it, and nothing else builds the name.
- **Final, not pluggable:** never wrapped in `function_exists()`. It is
  loaded from the PARENT theme directory by `salt_section_resolver()`,
  never through `locate_template()`, so no child file can replace it.
  Nothing registers it: the file's existence is the registration
  (`functions.php` and `tests/bootstrap.php` need no change). If the file
  exists but does not define the right function name, or the function is
  already defined elsewhere, the section logs, throws under
  `salt_is_debug()` and renders nothing — it never falls back to the
  unsplit path. The single exception is a section the child theme
  declares replaced (tier `logic` in `salt-overrides.json`): the entry
  never loads its resolver at all and loads the child's template whole
  (see The shared entry). A replacement that is not declared still fails
  as above.
- **Does all the logic:** field and option reads (`get_sub_field`,
  `get_field`, `salt_option`), queries (`salt_resolve_query`), sanitising
  (`salt_sanitize_html`), validation and fallbacks, broken-row skipping +
  `salt_log()`, schema (`salt_schema_add`), every service and stateful
  call (rule 4 below) and the wrapper inputs.
- **Returns** `null` (see the 0-state rule) or:

```php
array(
	'section' => array_merge(
		salt_section_wrapper_args( '{slug}', $context ), // then any overrides: container, flush_top
		array( 'has_heading' => '' !== trim( $heading ) ) // A11Y01
	),
	'props'   => array( /* view props */ ),
	'plan'    => array( 'priorityMedia' => true ), // OPTIONAL — only a section that draws THE priority image
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

- **Names:** see the slug table. Contract names verbatim (camelCase:
  `structuredData`) where a prop corresponds 1:1; otherwise a snake_case
  WordPress-local name (`image_id`, `ctas`), listed as WordPress-local in
  the resolver's docblock.
- **Optional text:** `salt_nullable_text( $raw )` — `null` when blank,
  otherwise the stored string UNCHANGED (it never trims). Where the
  pre-split code trimmed a value before use, the resolver trims first and
  then nulls (rule 2 wins): pricing `eyebrow` and `heading`; stats item
  `suffix` and `label`; features item `icon` and `body_text`; cta
  `heading` and `body_text`.
- **Rich text:** sanitised once, in the resolver, with
  `salt_sanitize_html()`. Never sanitise twice: the view prints it as it
  is.
- **Service-produced HTML** (rule 4): a prop whose name ends in `_html`
  (`captcha_widget_html`). This and sanitised rich text are the ONLY
  pre-rendered HTML a prop may hold; the view prints both as they are,
  with a `phpcs:ignore` naming where they were made safe.
- **Plain text, URLs, attributes:** raw strings; the view escapes them at
  output or passes them to a component that does.
- **Images:** a validated (resolvable) attachment ID, `0` for none,
  passed to x-image by the view, where the pre-split code validated it.
  Where it did not (scroll-carousel cards; work and blog-teaser
  thumbnails), the unvalidated ID is passed through as before and x-image
  decides (rule 2 wins).
- **Components are never props** (rule 3): the view renders cards,
  images and every other component itself.

### The plan

The view's `$args['plan']` carries the contract's plan keys
(`_shared.json`, type `plan`) that WordPress can supply today:

- `index` (int) — the loop position. Unlike the contract's it counts
  skipped rows too (it is the `index` the loop passed).
- `headingLevel` (1|2) — from the wrapper's H1 claim (A11Y01).
- `priorityMedia` (bool) — `false` unless the resolver returns
  `plan.priorityMedia`; today only hero sets it (always `true`).

Not supplied yet: `anchor` and `headingId` (the wrapper settles the
anchor after the body renders), `bodyBaseLevel` (use `headingLevel + 1`
for sub-headings), `collapseTop` and `settings` (the wrapper's own).
A resolver may return no other plan key: anything but `priorityMedia` is
a malformed return.

### The shared entry

`salt_render_section()` → `salt_render_resolved_section( $layout, $view, $context )`
(`inc/render.php`) runs resolver → `null` check → `salt_section(
$resolved['section'], … )` → the view, loaded with
`$args = array( 'props' => …, 'plan' => array( 'index' => …, 'headingLevel' => …, 'priorityMedia' => … ) )`.

Before the resolver, it asks `salt_override_tier( $layout, $variant )`
(`$variant`: the row's Layout field for testimonials). When the
child theme's `salt-overrides.json` declares the layout's contract
section `logic`, it calls `salt_render_replaced_section()` instead: the
located template is loaded whole with the loop context, if it is the
child's, and otherwise the section logs, throws under `salt_is_debug()`
and renders nothing. A replacing template must call `salt_section()`; one
that draws markup without it is logged, because the render state (section
index, adjacency collapse) is then wrong for the next section. With no declaration (every site without the file)
this step is a no-op and output is unchanged. A view never needs to know
about it: tier `view` changes nothing at render time.

### The view

- **File:** `template-parts/sections/{slug}.php` — the same path the whole
  section had, so a child override of that path is an override of the
  view.
- Starts `$salt_props = $args['props']; $salt_plan = $args['plan'];`;
  docblock documents the `$args` shape.
- **May:** branch and loop over props; print markup; escape at output
  (`esc_html`, `esc_attr`, `esc_url`, `nl2br( esc_html( … ) )`); print
  sanitised rich text and `_html` props as they are; call
  `salt_component()` / `salt_get_component()`; use i18n (`__()`,
  `esc_html_e()`, `esc_attr__()`, `sprintf` of a translated string);
  derive markup-only values from props (`'faq-' . $salt_plan['index']`,
  a class string). Main heading at `$salt_plan['headingLevel']`,
  sub-headings one below.
- **May not:** read fields or options, query, sanitise, validate, log,
  queue schema, enqueue, call any service or stateful function (rule 4),
  decide a branch from raw data (rule 5), call `salt_section()` /
  `salt_section_wrapper_args()` / `salt_claim_h1()` / `salt_anchor_id()`,
  or `return` before drawing anything (the 0-state is the resolver's;
  a `return` that ends a finished branch, as in hero, is fine).

### Converting a section (checklist)

1. Create `inc/sections/{slug}.php` with
   `salt_resolve_section_{slug_}( array $context ): ?array`. Look the
   slug up in the table above for the contract file and naming mode.
2. Move everything above the old `salt_section()` call into it, in the
   same order: `$args ?? array()` becomes `$context`, `$salt_x` becomes
   `$x`, every `return;` becomes `return null;`.
3. Move every non-markup statement from INSIDE the old `salt_section()`
   callback into the resolver too, after the 0-state check, keeping their
   relative order and their conditions (rules 4, 5): field reads, queries,
   `salt_location_status()`, `salt_schema_add()`, `salt_log()`, services.
   They now run before the wrapper's own work; rendered output and schema
   do not change, only the position of their log lines relative to the
   wrapper's (no section test asserts an exact log array — keep it so).
4. Build `props`, `section` (`salt_section_wrapper_args()` + the old
   overrides + `has_heading`) and, only if the section draws THE priority
   image, `plan`. Return them.
5. Rewrite the template as the view: the old callback body, with
   `$level` → `$salt_plan['headingLevel']`, `'' !== trim( $salt_x )` →
   `null !== $salt_props['x']`, and `$args['index']` →
   `$salt_plan['index']` ONLY where the old code really saw the loop
   context (rule 2). Keep every component args array identical (same keys,
   values, order) and every inline-HTML byte (rule 1).
6. Walk each snapshot case of the section through old and new code: the
   `.snap.html` files must not change. Never regenerate one for a split.
   Run `vendor/bin/phpunit --testsuite snapshot` and `--testsuite unit`.
7. Add resolver tests to `tests/unit/{Section}SectionTest.php`: resolve via
   `salt_section_resolver( '{slug}' )` after seeding the row; draw the view
   via `load_template( …, false, array( 'props' => …, 'plan' => … ) )`.
8. List the naming gaps against the contract file in the resolver
   docblock, and any kept-as-is suspected bug (rule 2) in your handoff.

#### Rule 1 — Inline HTML: keep every emitted byte

Everything outside `<?php … ?>` is output, and the snapshots compare it
byte for byte — including the tabs in front of an opening `<?php` tag and
the indentation inside an HTML block (`contact.php`'s form,
`locations.php`'s `<details>` block: `\t\t\t<details …` is in
`locations-*.snap.html`). PHP also swallows exactly one newline straight
after `?>`. So when a callback body moves to file scope in the view:

- copy every `?> … <?php` stretch VERBATIM, keeping its indentation as it
  is; do NOT re-indent those lines to file scope, and do not add or
  remove a space or newline around `?>` / `<?php`;
- re-indent only lines that are entirely inside one `<?php … ?>` block;
- the loop that used to enclose the block becomes a file-scope loop that
  closes PHP and reopens it around the verbatim block — phpcs
  (WordPress-Extra) accepts this as is:

```php
foreach ( $salt_props['offices'] as $salt_office ) {
	?>
			<details class="x-accordion-item …" name="<?php echo esc_attr( $salt_props['details_group'] ); ?>">
			…
			</details>
			<?php
}
```

  The `?>` ends its line (its newline is swallowed), so the block still
  starts with exactly the old three tabs; the closing `\t\t\t<?php`
  line still emits its three tabs.
- when you take a call OUT of an inline `<?php … ?>` tag (rule 4), keep
  the tag, its leading tabs and its line: replace only the call with the
  prop. `contact.php` line `\t\t\t\t\t\t<?php $salt_challenge = salt_challenge_create(); ?>`
  inside `<noscript>` becomes
  `\t\t\t\t\t\t<?php $salt_challenge = $salt_props['challenge']; ?>` —
  deleting the line would delete its six tabs from the output.

#### Rule 2 — Keep existing behaviour, even when it looks like a bug

Convert what the code DOES, not what it seems to mean. Example:
`locations.php` names each office's `<details>` group
`'locations-' . (int) ( $args['index'] ?? 0 )`, but that line is inside
`static function ( int $level ) use ( $salt_heading, $salt_eyebrow,
$salt_offices )`, which does not capture `$args`; a closure cannot see
the enclosing scope, so `$args` is undefined there, `??` hides it, and
every page emits `locations-0` (index 7 also gives `locations-0`). The
converted section must keep emitting `locations-0`: the resolver passes a
WordPress-local prop with the constant and a comment —
`'details_group' => 'locations-0', // Pre-split behaviour: always 0 (closure never saw $args). Reported as a Work Item; do not "fix" here.`
— and the view prints `esc_attr( $salt_props['details_group'] )`, not
`$salt_plan['index']`. Report the suspected bug in your handoff as a
proposed new Work Item.

#### Rule 3 — Components: the resolver returns data, the view renders components

A template that pre-renders components above `salt_section()` (e.g.
`testimonials.php` builds `$salt_cards` with `salt_get_component(
'image' … )` and `salt_get_component( 'card-testimonial' … )`) moves those
calls into the view; the resolver returns the data the components need
(`quote`, `author`, `role`, `company`, `rating`, `logo_id`). This cannot
change output or any side effect that matters: no component touches the
H1 claim, the section index, anchors, schema or enqueues (only
`components/section.php`, the wrapper, uses render state); a component's
output depends only on its args (the icon cache in `inc/services/svg.php`
is a pure cache); the resolver still clamps ratings and skips empty
quotes first, so the cards' own clamp/empty logs stay silent as before.
The only difference is that a component's log line (e.g. an unresolvable
logo) is written after the wrapper's own log lines instead of before.
That is accepted; there is no exception to this rule.

#### Rule 4 — Services and stateful calls belong to the resolver

The view never calls anything that reads request or site state, creates
state, or calls a service: `wp_create_nonce()`, `salt_contact_time_token()`,
`salt_contact_request_status()`, `salt_contact_enquiry_types()`,
`salt_captcha_provider()`, `salt_captcha_widget_html()`,
`salt_challenge_create()` (writes a transient), `admin_url()`,
`home_url( add_query_arg( … ) )`, `get_permalink()`,
`salt_location_status()` (reads the clock), option reads. The resolver
calls them and passes the RESULTS as props: values as plain props
(`nonce`, `time_token`, `form_action`, `page_url`, `privacy_url`,
`challenge` = `array{question, token}`), service-produced markup as an
`_html` prop (`captcha_widget_html`).

Call them **under exactly the same conditions, the same number of times
and in the same order as the old markup did**, and pass `null` for a
result the old code never computed, except an `_html` prop, which is `''`
when not computed (the view gates on the related prop, never on the
HTML). "The same number of times" binds service and stateful calls
(nonces, transients, tokens, providers, functions that log once per
request). A pure read (`get_field()`, a permalink) may be read once and
the value reused where the old code read it again, when the values are
provably the same: work reads each item's permalink and `client` once,
for both its schema node and its card. For contact's CAPTCHA:

```php
$captcha_widget_html = '';
$challenge           = null; // array{question: string, token: string}|null
if ( '' !== salt_captcha_provider() ) {
	$captcha_widget_html = salt_captcha_widget_html();
	$challenge           = salt_challenge_create();
}
```

and the view's `<?php if ( '' !== salt_captcha_provider() ) : ?>` becomes
`<?php if ( null !== $salt_props['challenge'] ) : ?>` on the same line
with the same tabs. In `contact.php` that is,
only when the form is enabled: `salt_contact_request_status()`,
`salt_contact_enquiry_types()`, the `contact_privacy_page` option, then
`admin_url( 'admin-post.php' )`, `wp_create_nonce( 'salt_contact' )`,
`salt_contact_time_token()`, `home_url( add_query_arg( array() ) )`,
`salt_captcha_provider()`; only when that is non-empty,
`salt_captcha_widget_html()` then `salt_challenge_create()`; only when the
privacy page is a positive int, `get_permalink()`. Order matters because
`salt_challenge_create()` writes a transient and must not run when the
challenge is not drawn, and `salt_captcha_provider()` logs once per
request. Values are unchanged: the nonce and time token are computed
within the same request second as before (tests freeze the clock with
`salt_contact_clock`), and the challenge is random either way.

#### Rule 5 — Logic woven into markup: the resolver decides, the view branches

When a log or a condition decides which markup is drawn, the resolver
evaluates the condition, logs, and passes the result as a prop; the view
branches on the prop only. `logos.php` today, inside the callback:

```php
if ( '' !== $salt_logo['url'] && ( '' !== $salt_logo['name'] || '' !== $salt_logo['alt'] ) ) {
	printf( '<a href="%1$s" aria-label="%2$s">%3$s</a>', … );
} else {
	if ( '' !== $salt_logo['url'] ) {
		salt_log( 'section logos: linked logo has no name or alt — rendered unlinked (accessible-name rule).' );
	}
	echo $salt_img;
}
```

Resolver, per logo (after the row checks, same order as before):

```php
$linked = '' !== $url && ( '' !== $name || '' !== $alt );
if ( ! $linked && '' !== $url ) {
	salt_log( 'section logos: linked logo has no name or alt — rendered unlinked (accessible-name rule).' );
}
$logos[] = array(
	'image_id'   => $image_id,
	'alt'        => $alt,
	'href'       => $linked ? $url : '',                       // '' = draw unlinked
	'link_label' => $linked ? ( '' !== $name ? $name : $alt ) : '',
);
```

View: `if ( '' !== $salt_logo['href'] ) { printf( …, esc_url( $salt_logo['href'] ), esc_attr( $salt_logo['link_label'] ), $salt_img ); } else { echo $salt_img; }`.
The same rule turns `contact.php`'s dialable-phone test into the
contract's `details.phoneHref` prop (`null` = print the number as text),
and `locations.php`'s into `offices[].phoneHref`, likewise.

## Third parties

- None. `salt_consent()` is a fail-closed stub in this build (PRIV01): no
  asset, script, font, tile, or beacon from any non-site origin may be
  emitted by any code path.
