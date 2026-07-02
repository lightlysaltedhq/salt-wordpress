# Changelog

All notable changes to the salt parent theme. Format:
[Keep a Changelog](https://keepachangelog.com/); versioning:
docs/RELEASE-POLICY.md. Releases with breaking changes carry a **BREAKING**
section enumerating changed components/sections at args-signature and
markup granularity (cross-reference your site's override manifest).

## [0.2.0] — 2026-07-02 — "foundation"

### Added
- Three-tier OKLCH token system with neutral default Primary, dark-mode
  inversion, runtime per-site brand derivation (§13/§13a; FAIL03).
- CI-tested core functions: `salt_resolve_query` (+ per-CPT defaults,
  DATA04/DATA05), `salt_best_image_format`, OKLCH ramp + AA contrast
  property test, `salt_module_enabled`, `salt_allowed_html` (TEST01),
  JSON-LD graph validity (TEST02), `salt_location_status` (TEST03),
  fail-closed `salt_consent()` stub (PRIV01).
- Render layer: `salt_require_args` (FAIL01), x-section wrapper with
  derived heading level + H1 invariant (A11Y01), anchor sanitisation
  (EDIT02), `fallback.php` editor-visible degradation (ARCH05).
- Component primitives: x-image (`<img srcset>` default — AMBIG01),
  x-button, x-heading, x-eyebrow, x-icon (+ SVG upload sanitisation,
  SEC04), x-cta, x-accordion (native `<details>`), x-card base.
- Schema graph builder: one deduplicated `@graph` per page, stable `@id`s,
  single FAQPage merge, office `parentOrganization` linkage, self-serving
  AggregateRating stripping (SEO01/02/03).
- ACF-as-code framework with DEP01 boot guard (admin notice, no fatal),
  canonical key convention (DATA02), options pages, FC container +
  Rich Text exemplar section.
- Security baseline: CSP with per-request nonce + hardening headers
  (SEC06), TinyMCE paste/toolbar parity with the allow-list (EDIT01),
  updater token custody via wp-config constant (SEC03).
- Full template hierarchy with guaranteed per-route H1 and decided
  attachment policy (301→parent, orphan→404).
- Governance docs: rollback runbook (FLEET02), release policy + trivial-
  patch allow-list (FLEET03), dependency matrix (DEP05), override contract
  (ARCH07), what-travels-where (DATA03), SECTIONS.md, tokens contract.

## [0.1.0] — 2026-07-02

### Added
- Repo scaffold, toolchain, CI pipeline: PHPCS with security sniffs as
  errors (SEC08), PHPUnit, snapshot gate (FLEET01), artifact integrity
  (FLEET08), slug stability (FLEET07). Vendored pinned SVG sanitiser
  (SEC04/DEP02).
