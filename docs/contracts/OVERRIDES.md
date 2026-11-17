# Override contract (ARCH07) — frozen

How a child theme customises salt, and the exact guarantees behind each
mechanism. This list is versioned: adding/removing a pluggable function or
documented filter is a semver event (RELEASE-POLICY.md).

## Load order (the guarantee)

WordPress loads the **child theme's functions.php BEFORE the parent's**.
The parent wraps every pluggable function in `if ( ! function_exists() )`,
so a child that defines the function first wins. The parent's `inc/*`
modules are required from the parent functions.php only — no parent code
defines a pluggable symbol before the child has run.

## Pluggable functions (redefinition overrides)

A pluggable function can be overridden **once** — by the child OR a plugin,
not both (a second definition fatals). Plugin-contended surfaces should use
the filters below instead.

| Function | Module | Overrides |
|---|---|---|
| `salt_log()` | inc/helpers.php | Logging channel |
| `salt_resolve_query()` | inc/query/resolver.php | Principle-7 resolution (the headline child override) |
| `salt_query_default_args()` | inc/query/resolver.php | Per-CPT "sane defaults" |
| `salt_derive_ramp()` | inc/colors.php | Ramp lightness/chroma ladder |
| `salt_location_status()` | inc/services/location-status.php | Open-status computation |
| `salt_best_image_format()` | inc/performance.php | Format capability probe |
| `salt_image_data()` | inc/media.php | Attachment → renderable data |
| `salt_render_section()` | inc/render.php | Layout → template resolution (an override must render through `salt_render_resolved_section()`, see Section views) |

## Filter priority bands

- **10** — parent/core preparation (reserved).
- **20+** — site/child overrides (your hooks go here).
- Never hook below 10 in a child.

Documented filters: `salt_is_debug`, `salt_module_enabled`,
`salt_csp_policy`, `comments_open`/`pings_open` (re-open per site at 20+),
plus WP core surfaces salt owns (`image_editor_output_format`,
`robots_txt`, `wp_sitemaps_post_types`, `tiny_mce_before_init`,
`mce_buttons`, `http_request_args` for updater auth).

Consent + contact seams (v2 — see docs/PRIVACY-RESPONSIBILITIES.md):
- `salt_consent_state( bool $granted, string $category )` — CMP glue for
  plugins without WP Consent API support. Only a strict `true` grants.
- `salt_consent_api_category( string $api, string $salt )` — remap salt
  categories onto the site CMP's taxonomy.
- `salt_consent_api_active( bool )` — force the filter bridge over the
  WP Consent API.
- `salt_contact_form_enabled( bool $configured )` — kill-switch for the
  live form (defaults to "recipient configured").
- Client-side: dispatch `salt:consent` / listen for `salt:contact-sent` and
  `salt:prefs-changed` (Display Preferences);
  `window.salt.track( name, props )` forwards custom events to both
  analytics providers.

**Deliberately un-filterable** (security invariants):
`salt_allowed_html()` (widening is a security-reviewed parent release,
§16a); `salt_consent()`'s deny-paths (the bridge grants only on a strict
CMP verdict — no filter can turn an ambiguous state into an allow).

## v3 chrome seams

`template-parts/header/site-header.php` and
`template-parts/footer/site-footer.php` carry all header/footer option
reads (the `header.php`/`footer.php` shells stay thin) — override the
PARTS, not the shells. `Salt_Nav_Walker` renders core menus (locations:
`primary`, `footer`). `salt_options_subpages()` (inc/fields/options.php)
is the Site Settings menu registry — one array entry per sub-page.

## Template overrides (native hierarchy)

Any `template-parts/**` file can be overridden by placing the same path in
the child — `locate_template()` checks the child first. Every override MUST
be recorded in the child's override manifest
(docs/templates/override-manifest.md): a parent update re-verifies exactly
those parts. A section view swap or takeover is declared in the child's
`salt-overrides.json` instead, which the manifest points at (Declaring
replaced sections, below). Overriding a part couples the child to that part's **markup
structure**; snapshot diffs in the parent changelog (BREAKING section) tell
you when a re-verify is due.

## Section views (logic/view split)

Every section is split into a **resolver** (parent-only logic) and a
**view** (markup); docs/contracts/CONVENTIONS.md has the full rules. A
section is split when the parent has `inc/sections/{slug}.php`; all 20
parent sections are.

- **Override the view, not the logic.** For a split section,
  `template-parts/sections/{slug}.php` is its VIEW. A child copy at that
  path is loaded, as before, by `locate_template()` — but now inside the
  section wrapper, with `$args['props']` (the view props: names and
  shapes in the parent view's docblock and the resolver's) and
  `$args['plan']` (`index`, `headingLevel`, `priorityMedia`). Draw from
  those only: never read fields, query, sanitise, log or queue schema,
  and never call `salt_section()`. The parent resolver keeps doing all of
  that, so its fixes reach you without touching your override. To change
  the band itself, override `template-parts/components/section.php`.
- **The 0-state is the resolver's.** When it decides a section renders
  nothing, your view is not loaded at all. A view is only loaded with
  something to draw.
- **Escaping.** Plain-text props arrive raw: escape them at output.
  HTML props (rich text, e.g. Rich Text `body`, FAQ `answer`) arrive
  already sanitised by `salt_sanitize_html()`: print them as they are.
  Props named `*_html` (service-produced markup, e.g. a CAPTCHA widget)
  are likewise printed as they are.
- **A resolver cannot be replaced; a section can be taken over only by
  declaring it.** A resolver loads from the parent theme directory only,
  never via `locate_template()`, and is not pluggable. A child file at
  `inc/sections/{slug}.php` is ignored. A child (or plugin) that defines
  `salt_resolve_section_*()` does not take over the logic: the parent
  detects the conflict, does not load its own file (so there is no PHP
  redeclaration fatal), logs `… is already defined in {file} — a resolver
  cannot be replaced`, throws under `WP_DEBUG`, and renders NOTHING for
  that section in production. Do not copy a resolver. The one way to own a
  section's logic is to declare it `logic` in `salt-overrides.json` and
  supply the whole section template (Declaring replaced sections, below);
  an undeclared replacement still fails exactly as described here.
- **Overriding `salt_render_section()`.** A child override must hand the
  located template to `salt_render_resolved_section( $layout, $located,
  $context )`, never `load_template()` it directly.
- **A layout with no parent resolver loads its template whole.** A
  child-only layout (one the parent does not define) is unsplit: its
  template is still the whole section and receives the loop context
  (`index`, `is_primary`) as `$args`, exactly as before.

### What happens to code written before the split (BREAKING)

1. **A pre-split copy of any `template-parts/sections/{slug}.php`** in
   the child is a whole section: it reads fields and calls `salt_section()`
   itself. It is now loaded as the view, INSIDE the parent's band, so the
   page gets a band nested in a band. The OUTER band (the parent's) is
   the primary section and claims the page H1, but draws no heading. The
   child's own `salt_section()` builds its wrapper args from `$args`,
   which no longer holds `is_primary`, so it sees `is_primary` false and
   renders the heading as **h2**. The page therefore has no H1, and the
   page-level fallback H1 is suppressed because the claim was already
   made. The parent logs `its view called salt_section() — a
   whole-section override from before the logic/view split?`.
2. **A pre-split copy of a section that queues schema** (contact, faq,
   locations, process, services, team, testimonials, work) does all of
   the above and also makes its own reads and `salt_schema_add()` calls,
   so its nodes are queued twice (once by the parent resolver, once by
   the copy). For faq the graph merges both FAQPage nodes, so every
   question appears twice in the page's FAQPage.
3. **Anything that loads a split section's template directly** — a
   child override of `salt_render_section()` that calls `load_template()`,
   or any `get_template_part()` / `load_template()` of a
   `template-parts/sections/{slug}.php` — passes no props. Every view
   then emits PHP warnings. Hero draws an empty, broken hero and Rich
   Text draws nothing; blog-teaser, call-to-action, faq, gallery, pricing,
   scroll-carousel and work are a **fatal error** (`TypeError` from
   `count()` or `array_map()`); the rest draw broken markup. Render
   through `salt_render_resolved_section()`.

Fix for 1 and 2: rewrite the override as a view, starting from the
parent's new `template-parts/sections/{slug}.php`, and re-verify it in the
override manifest. If the site really does need the whole section (its own
reads, queries or schema), keep the copy and declare the section `logic`
instead (below): it is then loaded whole, outside the parent's band.

## Declaring replaced sections (`salt-overrides.json`)

The salt-contract's customisation ladder (its README, "The customisation
ladder") names the extension points both Salt platforms offer. Tiers 4 to
6 are declared in one JSON file, **`salt-overrides.json` at the root of
the child theme**, in the contract's format:
`salt-contract/schema/replaced-logic.schema.json` (decision SC-015). The
child's own `OVERRIDES.md` (the override manifest) points at that file and
does not repeat it. The parent reads only the JSON, never the manifest.

```json
{
  "$schema": "https://unpkg.com/@lightlysaltedhq/salt-contract@1.0.0/schema/replaced-logic.schema.json",
  "site": "salt-{client}",
  "platform": "wordpress",
  "contract": "1.0.0",
  "sections": [
    { "section": "call-to-action", "tier": "view", "files": ["template-parts/sections/call-to-action.php"],
      "reason": "…", "since": "16/11/2026", "verified": "1.0.0" },
    { "section": "stats", "tier": "logic", "files": ["template-parts/sections/stats.php"],
      "reason": "…", "since": "16/11/2026", "verified": "1.0.0" }
  ],
  "clientSections": [ { "id": "showroom-finder", "reason": "…" } ]
}
```

- **Section ids are the contract's, not the layout slugs.** The parent maps
  them with `salt_contract_section_layouts()` (`inc/overrides.php`), the
  same mapping as the slug table in CONVENTIONS.md. Since 6.0.0 a section
  WordPress builds as one layout has the contract id as its layout slug
  (`call-to-action`, `media-text`, `rich-text`); `rich-text` is also
  `intro`;
  `collection-showcase` is `services`, `work`, `team`, `testimonials` and
  `blog-teaser`; `carousel` is `scroll-carousel` and `testimonials`.
  Declaring a folded id covers every layout it maps to. `testimonials` is
  decided **per row**, as the contract's `when` says: a row whose Layout
  field is `grid` is `collection-showcase`, a `slider` row is `carousel`
  (anything else counts as `grid`, the testimonials resolver's own
  normalisation, `salt_testimonials_layout()`). So `carousel` declared
  `logic` takes over slider testimonials only, and `collection-showcase`
  `logic` with `carousel` `view` takes over grid rows while slider rows
  load the same child file as a view. `tabs` and `listing` have no
  WordPress layout yet.
- **Reading it.** `salt_overrides()` returns the parsed declaration (plain
  arrays, shape in its docblock), read once per request, or `null` when no
  child theme is active, the file is absent, or it is invalid.
  `salt_override_tier( $layout, $variant )` gives a layout's declared tier
  (`'logic'` beats `'view'`; `null` when undeclared); for testimonials pass
  the row's Layout value as `$variant` (the render path does).
- **Validation.** Every rule of the schema (required and allowed keys,
  id, release and `DD/MM/YYYY` date patterns, tier and band enums,
  non-empty unique `files`; `sections`, `imageSizes` and `clientSections`
  must be arrays when present, so `null` is refused), plus three of the parent's own: `platform`
  must be `"wordpress"`, a section id has at most one entry, and a client
  section id is listed once. Releases are `x.y.z` only, as in the schema
  (`1.0.0-rc.6` is not accepted). A file that breaks a rule, is not JSON
  or is over 64 KB is logged once per request (`salt-overrides.json in the
  child theme "…" is not valid (…)`) and treated as **no declaration**:
  never a fatal, never a throw, even under `WP_DEBUG`. Until it is fixed, a
  section the site took over goes back through the parent's resolver and
  wrapper. The parent resolver runs first, so the section may simply
  vanish through the parent's 0-state (for example when the data the
  child's template draws is not in the fields the parent reads); if it does not, its whole-section template is loaded as a view
  and nests (item 1 above). The update flag says the file is invalid.

### Tier 4: swap a section's view (`"tier": "view"`)

The view swap is the one described under Section views: a child file at
`template-parts/sections/{slug}.php`, found by `locate_template()`,
drawing `$args['props']` and `$args['plan']`. The file path uses the
**layout slug** (`call-to-action.php`, the same as the contract id for
every one-layout section since 6.0.0); the declaration uses the
**contract id** (`call-to-action`), which for a folded section names
several layouts (`collection-showcase`). The contract names no file path for WordPress views, so
the path is the native one and nothing about it changes. Declaring the
swap changes no rendering: an **undeclared** child view still loads exactly
as before, because refusing it would break every existing child theme. The
mismatch is raised by the update flag instead (below). Declare every swap.

### Tier 5: replace a section's logic (`"tier": "logic"`)

The site takes the section over entirely: nothing of the parent runs for
it. For a layout whose contract id is declared `logic`,
`salt_render_resolved_section()` skips the parent resolver, the
`salt_section()` wrapper, the H1 claim and the parent's schema, and loads
the child's `template-parts/sections/{slug}.php` **whole**, with the loop
context (`index`, `is_primary`) as `$args`, exactly as a child-only layout
is loaded. That template is a whole section: it does its own reads,
queries, sanitising and schema, and **must** wrap what it draws in
`salt_section( salt_section_wrapper_args( '{slug}', $args ) + … )`. The
wrapper is what moves the page's render state on: without it the section
index is not advanced and the adjacency-collapse state still describes the
section before, so the NEXT section gets this one's index (a duplicate
accordion group name, for example) and collapses or keeps its top space
against the wrong neighbour, and this section never claims the H1. The
parent logs `… drew markup without calling salt_section()` when a
template draws markup without it (a template that draws nothing is a
0-state and is not warned about). Name its helpers with the child's own
prefix, never `salt_resolve_section_*()`.

- **No child template, no section.** If the declaration says `logic` but
  the child has no `template-parts/sections/{slug}.php`, the section logs
  `… declares it replaced (tier "logic"), but the child theme has no …`,
  throws under `WP_DEBUG`, and renders nothing in production. It never
  falls back to the parent view, which cannot draw without props. A folded
  id covers every layout it maps to, so declaring `collection-showcase`
  takes over all five layouts (testimonials for grid rows only): supply a
  template for each one the site uses.
- **A child resolver is never called.** For a layout declared `logic` the
  parent never loads a resolver, so a `salt_resolve_section_{slug}()` the
  child defines for it is not called and no conflict is logged. For every
  layout not declared `logic` (tier `view` included) such a function still
  fails loudly as above.
- **What still reaches it on update: nothing.** Parent fixes to that
  section's resolver, view and schema do not apply. The update flag raises
  every `logic` entry on every update.

### Tier 6: client-only sections (`clientSections`)

A layout only this site has is a child-only (unsplit) layout and renders
as before. List it under `clientSections` so the update flag can say when
Salt gains a section, or this theme a layout, of the same id. Listing it
changes no rendering.

`imageSizes` (SC-016) is read, validated and exposed with the rest; the
parent's image code does not consume it yet, and the flag raises each row
with its section.

### The update flag

The override manifest's update procedure (re-verify the overrides after
taking a parent update) is carried out in the site's admin, so the flag is
an admin notice on the **Themes** and **Updates** screens (and the network
**Updates** screen on multisite), shown to users who can `switch_themes`
(`inc/services/override-flag.php`). It lists:

- every `logic` entry, always (nothing in a release reaches it);
- a `view` entry whose `verified` is not the file's own `contract` (and
  only then: see below);
- an `imageSizes` row whose section is raised or has no entry;
- a client section whose id is a contract section or a parent layout;
- an invalid file, and every disagreement between the declaration and the
  child's files: an undeclared child view of a parent section, a declared
  view or `logic` section with no child template (testimonials by grid or
  slider rows), a declared file that is not in the child theme or lies
  outside it (an absolute path or one through `..`), an id this theme does
  not know.

**What it cannot know.** The contract wants a `view` entry raised when the
section's view-props file, or `_shared.json`, changed between the old
contract pin and the new. salt-wordpress takes no dependency on the
contract package and does not yet record which contract release it
conforms to, so **it cannot tell whether an update changed a section's
view props**. A view swap whose `verified` equals the file's `contract` is
therefore **not listed**, whatever the update changed. When an update
moves Salt to a newer contract release, re-verify every view swap by hand
against that release's view props, then set the file's `contract` and each
entry's `verified` to it. Today no parent release names the contract
release it conforms to (the parent CHANGELOG will, once salt-wordpress
conforms to one), so until then leave `contract` at the release the site
was built against. The flag cannot list the contract CHANGELOG entries for
a `logic` section either; read them in the contract's release notes. It
makes no network calls and stores nothing.

### Design dials (tier 2)

Not supported yet. The theme's token layer has no rungs for the dials to
move; the dials extension point arrives with ID-3612.

## Component render rule (Open Q6 — resolved)

- **Slot-style or contract-validated components** (everything in
  `template-parts/components/`) render via `salt_component()` /
  `salt_get_component()` — output-buffered, `salt_require_args()` enforced.
- **Simple data-only parts** (content cards, loops) render via raw
  `get_template_part( $slug, $variant, $args )`.
- Sections render via `salt_render_section()` from the loop. A split
  section's resolver returns the wrapper args and props, and the shared
  entry draws its view inside `salt_section( $wrapper_args,
  $content_callback )`, passing the derived heading level as
  `$args['plan']['headingLevel']` (A11Y01). An unsplit (child-only)
  section composes its body inside `salt_section()` itself, as before.
