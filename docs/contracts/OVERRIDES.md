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
| `salt_render_section()` | inc/render.php | Layout → template resolution |

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
those parts. Overriding a part couples the child to that part's **markup
structure**; snapshot diffs in the parent changelog (BREAKING section) tell
you when a re-verify is due.

## Component render rule (Open Q6 — resolved)

- **Slot-style or contract-validated components** (everything in
  `template-parts/components/`) render via `salt_component()` /
  `salt_get_component()` — output-buffered, `salt_require_args()` enforced.
- **Simple data-only parts** (content cards, loops) render via raw
  `get_template_part( $slug, $variant, $args )`.
- Sections render via `salt_render_section()` from the loop and compose
  their body inside `salt_section( $wrapper_args, $content_callback )` —
  the callback receives the derived heading level (A11Y01).
