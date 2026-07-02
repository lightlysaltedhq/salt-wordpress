# Changelog

All notable changes to the salt parent theme. Format:
[Keep a Changelog](https://keepachangelog.com/); versioning:
docs/RELEASE-POLICY.md. Releases with breaking changes carry a **BREAKING**
section enumerating changed components/sections at args-signature and
markup granularity (cross-reference your site's override manifest).

## [2.0.0] — 2026-07-02 — contact backend + integrations

Compliance is now **per-site by design**: each site picks a consent plugin;
salt ships privacy-safe mechanics + seams (docs/PRIVACY-RESPONSIBILITIES.md).

### Added
- **Live contact form** (SEC01/SEC02/SEC10/SEC11): REST + no-JS paths
  through one hardened handler — honeypot with fake-success, signed
  time-trap, nonce, proxy-aware rate limiting; Turnstile (default) /
  reCAPTCHA with server-side fail-closed verification; no-JS arithmetic
  challenge; routed email + auto-reply; HMAC-signed HTTPS webhooks with
  retry; privacy-policy link at collection.
- **Optional submission storage** (default OFF): locked-down private CPT,
  hardened 90-day retention purge (cron + catch-up + health notice),
  per-record DSAR export (SEC09/PRIV03/PRIV06).
- **Analytics — PostHog + GA4** with salt-instrumented events (cta_click,
  form_start/submit, accordion_open, carousel_interact, section_view +
  window.salt.track): GA4 boots with Consent Mode v2 all-denied defaults;
  PostHog is bundled first-party, cookieless until consent; both flipped by
  WP Consent API events or the salt:consent bridge.
- **Maps** (MapLibre bundled, click-to-load facade, consent-gated,
  per-site tile provider) and **booking facades** (Cal.com/Calendly, plain
  link until consent).
- Per-integration CSP composition (origins added only while active).

### Changed
- **BREAKING**: `salt_consent()` is now a fail-closed bridge (WP Consent
  API → `salt_consent_state` filter → deny) instead of an unfilterable
  stub. Contact section markup restructured (live form, status region,
  CAPTCHA slot, privacy line) — re-verify any child override of
  `template-parts/sections/contact.php`.

### Deferred
- Contact-form file uploads (SEC07) — deliberate scope cut; newsletter CTA
  variant; WP-CLI content importer (DATA01).

## [1.0.0] — 2026-07-02 — production-ready v1

### Added
- **The full section library** (20 sections, SECTIONS.md registry): Hero,
  Intro, Rich Text, Services, Feature Grid, Media+Text, Stats,
  Testimonials, Logo Strip, CTA, Pricing, Team, FAQ, Process, Work,
  Gallery, Blog Teaser, Scroll Carousel, Contact (markup+fields only,
  form gated off pending the compliance phase), Locations.
- **Six CPTs** (service, testimonial, team_member, work, faq, location)
  with module gating, Appendix-A fields, display-only posture for
  private types (SEC09), and seven-CPT resolver coverage (SCOPE01).
- Per-section asset enqueue from a server-side Flexible Content pre-scan;
  section JS as separate hashed bundles (gallery, scroll-carousel,
  testimonials — scroll-snap no-JS bases, FAIL02).
- Cross-section integration tests: one-H1 outlines, single deduplicated
  `@graph` (one FAQPage; Review→Service; office `parentOrganization`),
  scan-driven enqueue. RTL logical-property pass. A11Y07 sign-off
  checklist, CWV method doc, CONTRIBUTING, CODEOWNERS, §8 traceability
  (docs/TRACEABILITY.md).

### Deferred (Compliance & Integrations phase)
- Live contact submission (SEC01/SEC02/PRIV06), submissions CPT
  (SEC07/SEC09/PRIV03), consent store + banner (PRIV01 — fail-closed stub
  ships), maps/analytics/booking/video/embeds, DPA register (PRIV05),
  WP-CLI content importer (DATA01).

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
