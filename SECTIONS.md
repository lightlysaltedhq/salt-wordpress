# SECTIONS.md — the section library contract

Owner: parent-theme maintainer. This file + `docs/contracts/*` are the
frozen contracts Phase 2 sections are built against. Kept current as a
release requirement ("SECTIONS.md current" checklist line).

## Naming rules (DOC07)

- ACF FC **layout slugs** use underscores (`rich_text`); section **template
  files** use hyphens (`rich-text.php`) — the loop maps
  `str_replace('_','-')`. One layout ↔ one file, always.
- Components are `x-{name}` ↔ `template-parts/components/{name}.php`.
- Field keys: `field_salt_{scope}_{name}`; groups `group_salt_{scope}`
  (DATA02). Layout slugs + field names are CI-guarded (FLEET07).

## Adding a section (§17 recipe, as implemented)

1. `inc/fields/layouts/{name}.php` — returns the FC layout array: content
   fields first, then `salt_section_wrapper_fields('{name}')` (the shared
   §11 Advanced tab). CPT-backed sections embed
   `salt_query_control_fields($cpt)`.
2. `template-parts/sections/{name}.php` — reads `get_sub_field()`, decides
   its 0/1/N states (0 items ⇒ skip or empty-state, NEVER a blank band —
   COMPL02), then renders through
   `salt_section( wrapper_args + ['has_heading' => …], fn( int $level ) { … } )`.
   The callback receives the derived heading level; render your main
   heading at `$level`, sub-headings at `$level + 1` (A11Y01: a section
   with no heading sets `has_heading => false`).
3. Schema (if any): `salt_schema_add( $node )` — never an ad-hoc script tag
   (SEO01/02/03 live in the graph builder).
4. JS only if genuinely needed: functional no-JS base first (CSS
   scroll-snap / stacked grid), module as progressive enhancement (FAIL02).
5. Tests: a snapshot test per meaningful variant (FLEET01) + unit tests for
   logic. Run `php bin/extract-slugs.php --write` and commit the registry.
6. Document the section in the table below.

**The exemplar:** Rich Text (`rich_text` / `rich-text.php`) implements this
recipe end-to-end — copy it.

## Section registry

| # | Section | Layout slug | Source | Schema | Status |
|---|---|---|---|---|---|
| 3 | Rich Text | `rich_text` | inline | — | ✅ foundation exemplar |
| 1,2,4–20 | (Phase 2) | | | | pending fan-out |

## Component args registry

The authoritative args contract for every component is the ARGS CONTRACT
docblock at the top of its partial (enforced at runtime by
`salt_require_args()`, FAIL01). Index:

| Component | File | Notes |
|---|---|---|
| x-section | components/section.php | Render via `salt_section()` only |
| x-heading | components/heading.php | Level clamped 1–6; size derivable |
| x-eyebrow | components/eyebrow.php | |
| x-button | components/button.php | `_blank` ⇒ `rel="noopener noreferrer"` |
| x-cta | components/cta.php | Level arrives from the section |
| x-image | components/image.php | **`<img srcset>` default; `<picture>` opt-in (AMBIG01)** |
| x-icon | components/icon.php | Built-in set or pre-sanitised SVG |
| x-accordion | components/accordion.php | Native `<details>`, zero JS |
| x-card | components/card.php | Base for the Phase 2 card family |

Changing any args signature or markup structure = MAJOR (RELEASE-POLICY.md;
snapshot diff is the trigger).

## Deprecation policy (DOC01)

A deprecated section/component/arg ships as a working back-compat shim for
**at least one minor release** before removal; removal is a MAJOR. The shim
logs via `salt_log()` ("deprecated since …"). Renamed layout slugs/field
names additionally require a `docs/contracts/slug-migrations.json` entry
(FLEET07) describing how existing DB rows are handled.

## Migration from the live theme (DATA01 — documented later task)

Field-shape parity is guaranteed now (`work` + `case_study` alias; Services
default order = legacy `services_overview`, DATA04). The one-shot WP-CLI
importer (rewrite `case_study`→`work` post_type, remap taxonomy terms,
field-key mapping table, relationship-ID remap) is a **later task,
deliberately not in this build** — do not improvise per-site conversions;
build the tool when the first migration is scheduled.
