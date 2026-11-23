# SECTIONS.md — the section library contract

Owner: parent-theme maintainer. This file + `docs/contracts/*` are the
frozen contracts Phase 2 sections are built against. Kept current as a
release requirement ("SECTIONS.md current" checklist line).

## Naming rules (DOC07)

- A section's **layout slug** is its salt-contract section id verbatim
  (`rich-text`, `call-to-action`; SC-001, since 6.0.0), and the resolver,
  view and fields files use it as it is (`rich-text.php`). Only the
  resolver's function name writes `-` as `_`
  (`salt_section_resolver_function()`; CONVENTIONS.md, "The hyphen
  rule"). One layout ↔ one file, always.
- Components are `x-{name}` ↔ `template-parts/components/{name}.php`.
- Field keys: `field_salt_{scope}_{name}`; groups `group_salt_{scope}`
  (DATA02). Layout slugs, field names and field storage paths are
  CI-guarded (FLEET07).

## Adding a section (§17 recipe, as implemented)

1. Fields come from salt-contract, never by hand (6.0.0, ID-3610): add the
   section id to `sections` in `inc/fields/generated/sections.options.json`,
   run `php bin/check-fields-from-contract.php --write` and commit
   `inc/fields/generated/sections.json` as written. Every layout carries
   the contract's section settings in a group named `settings`, read only
   by `salt_section_wrapper_args()`; a section built on the contract's
   collection query reads its `query` group with
   `salt_query_controls_from_query()`. No layout is hand-written since
   unit 8 (`inc/fields/layouts/` is gone; `bin/check-fields-from-contract.php`
   refuses a hand-written copy of a generated section if one returns.)
2. Logic and view, split (docs/contracts/CONVENTIONS.md, "Sections:
   logic and view"):
   - `inc/sections/{name}.php` — the resolver
     `salt_resolve_section_{name_}( array $context ): ?array` reads
     `get_sub_field()`, decides its 0/1/N states (0 items ⇒ return `null`
     or props for a visible empty-state, NEVER a blank band — COMPL02),
     and returns the wrapper args (`salt_section_wrapper_args()` +
     `['has_heading' => …]`, A11Y01) and the view props, named after the
     contract's view props.
   - `template-parts/sections/{name}.php` — the view: draws
     `$args['props']` only; main heading at
     `$args['plan']['headingLevel']`, sub-headings one level below.
3. Schema (if any): `salt_schema_add( $node )` in the resolver — never an
   ad-hoc script tag (SEO01/02/03 live in the graph builder).
4. JS only if genuinely needed: functional no-JS base first (CSS
   scroll-snap / stacked grid), module as progressive enhancement (FAIL02).
5. Tests: a snapshot test per meaningful variant (FLEET01) + unit tests for
   logic. Run `php bin/extract-slugs.php --write` and commit the registry.
6. Document the section in the table below.

**The exemplar:** Rich Text (`rich-text` / `inc/sections/rich-text.php` +
`template-parts/sections/rich-text.php`) implements this recipe
end-to-end — copy it.

## Section registry

| # | Section | Layout slug | Source | Schema | Notes |
|---|---|---|---|---|---|
| 1 | Hero | `hero` | inline | — | Image only in v1 (video absent this build); LCP image eager |
| 3 | Rich Text | `rich-text` | inline | — | The §17 exemplar; intro folded in (6.0.0) |
| 5 | Feature Grid | `features` | inline repeater | — | |
| 6 | Media + Text | `media-text` | inline repeater | — | Alternating mode auto-flips |
| 7 | Stats | `stats` | inline repeater | — | `x-stat`; no count-up JS (INP) |
| 9 | Logo Strip | `logos` | inline repeater | — | Also the "as featured in" home |
| 10 | Call to Action | `call-to-action` | inline | — | Newsletter variant deferred (compliance phase) |
| 13 | FAQ | `faq` | `faq` ✦ (contract `query`) | FAQPage (merged once per page, SEO03) | Native `<details>`, zero JS |
| 14 | Process | `process` | inline repeater | HowTo (opt-in toggle, AEO) | `<ol>` semantics |
| 16 | Gallery | `gallery` | inline | — | Carousel = scroll-snap base + JS enhancement (FAIL02) |
| 17 | Collection showcase | `collection-showcase` | services, case studies, team, testimonials or posts ✦ (contract `query`) | Service / CreativeWork / Person (no PII) | Layouts grid, list, featured, accordion, index; cards per each collection's approved `display`; in place of services, work, team, testimonials (grid) and blog_teaser (unit 8) |
| 18 | Carousel | `carousel` | inline cards, or a collection ✦ (contract `query`) | as collection-showcase | Generic or quote cards; scroll-snap track, previous and next mounted by `sections/carousel.js`; in place of scroll_carousel and testimonials (slider) (unit 8) |
| 19 | Contact | `contact` | inline + options | ContactPoint | **LIVE (v2)**: REST + no-JS paths, honeypot/time-trap/rate-limit/CAPTCHA (SEC01/02), privacy link at collection; goes live when a recipient is configured (`salt_contact_form_enabled` kill-switch remains) |
| 20 | Locations | `locations` | `location` ✦ (contract `query`) | LocalBusiness (+ OpeningHoursSpecification; `parentOrganization` linked, SEO01) | Zero-JS accordion; status pill via `salt_location_status`; OSM directions link; no map in v1 |

✦ = CPT-backed; all resolve through `salt_resolve_query()` from the
contract's `query` group (`salt_query_controls_from_query()`, SCOPE01).
The six collection layouts (services, work, team, testimonials,
blog_teaser, scroll_carousel) and `salt_query_control_fields()` were
removed in unit 8 (EP-72 M3).
A post's body is the same builder (its `layout` field, ID-3952 unit 10),
limited by salt-contract rc.7 to rich-text, media-text, gallery,
call-to-action and faq: a rich-text row that is prose alone is drawn as the
post's prose, every other row as a band (`salt_the_post_layout()`).
Collection showcase and carousel are page sections only.
Intro (folded into Rich Text) and Pricing (a Grain for WordPress recipe,
SC-006) were removed in 6.0.0.

## Component args registry

The authoritative args contract for every component is the ARGS CONTRACT
docblock at the top of its partial (enforced at runtime by
`salt_require_args()`, FAIL01). Index:

| Component | File | Notes |
|---|---|---|
| x-section | components/section.php | Render via `salt_section()` only |
| x-image | components/image.php | **`<img srcset>` default; `<picture>` opt-in (AMBIG01)** |
| x-logo | components/logo.php | Light/dark display logos, text fallback; dark swap via `[data-theme=dark]` CSS |
| x-display-preferences | components/display-preferences.php | §14 widget; server-rendered `hidden`, JS-activated (FAIL02) |
| x-map | components/map.php | Click-to-load facade; consent-gated (v2) |
| x-booking | components/booking.php | Provider facade; plain link until consent (v2) |
| x-share-links | components/share-links.php | Social share intents (x/facebook/linkedin/whatsapp/bluesky/reddit/email + pinterest opt-in); copy-link button `hidden` until sections/share.js activates it (FAIL02) |
| x-author-box | components/author-box.php | Post author attribution (name, role, bio and photo from the credited team member, never the WordPress user; ID-3952) |

Changing any args signature or markup structure = MAJOR (RELEASE-POLICY.md;
snapshot diff is the trigger).

## Deprecation policy (DOC01)

A deprecated section/component/arg ships as a working back-compat shim for
**at least one minor release** before removal; removal is a MAJOR. The shim
logs via `salt_log()` ("deprecated since …"). Renamed layout slugs/field
names, and fields moved into or out of a group or repeater, additionally require a `docs/contracts/slug-migrations.json` entry
(FLEET07) describing how existing DB rows are handled.

## Migration from the live theme (DATA01 — documented later task)

Field-shape parity is guaranteed now (`work` + `case_study` alias; Services
default order = legacy `services_overview`, DATA04). The one-shot WP-CLI
importer (rewrite `case_study`→`work` post_type, remap taxonomy terms,
field-key mapping table, relationship-ID remap) is a **later task,
deliberately not in this build** — do not improvise per-site conversions;
build the tool when the first migration is scheduled.
