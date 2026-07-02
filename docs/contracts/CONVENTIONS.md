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

## Third parties

- None. `salt_consent()` is a fail-closed stub in this build (PRIV01): no
  asset, script, font, tile, or beacon from any non-site origin may be
  emitted by any code path.
