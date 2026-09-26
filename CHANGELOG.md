# Changelog

All notable changes to the salt parent theme. Format:
[Keep a Changelog](https://keepachangelog.com/); versioning:
docs/RELEASE-POLICY.md. Releases with breaking changes carry a **BREAKING**
section enumerating changed components/sections at args-signature and
markup granularity (cross-reference your site's override manifest).

## [Unreleased]

### Changed
- **The design contract is now `@lightlysaltedhq/design-foundations` 3.0.0**
  (was 2.1.0), exact-pinned and locked by integrity hash as before. `PINNED`
  and `VOCABULARY_SHA256` are re-pinned; the new hash was recomputed
  independently in Python from the canonical form and agrees. 3.0.0 is a
  major, and each of its breaking changes was checked against salt:
  - **The derivation's quantise step rounds half to even** (derivation.json
    step 5, the owner's ruling of 25/09/2026). `salt_oklch_to_hex()` ends in
    a new `salt_color_quantise()`, which clamps, applies the transfer,
    clamps, multiplies by 255 and rounds that binary64 product half to even
    as an exact comparison (`salt_color_round_half_even()`), where it used
    `round()`, which rounds half away from zero. The JS port in
    `contrast-checker.js` does the same in place of `Math.round()`. No
    ramp changes: the old and new rules agree on every channel of every
    ramp step for all 16,777,216 hex seeds, as the contract says, and salt's
    colour fixture, the contract's ramp vectors and PHP↔JS parity all pass
    unchanged. The conformance suite now also runs the contract's two
    `quantise` vectors. The old `round()` fails `exact-ties` (#030409 for
    #020408), and multiplying by 255 before 12.92 fails `operation-order`
    (#020000 for #030000). `bin/check-color-parity.mjs` runs the same two
    vectors against the JS port's exported `quantise()`, where
    `Math.round()` put back fails `exact-ties` the same way; it moved from
    CI's PHP job to the ESLint + Stylelint job, which installs the contract.
  - **Each vector's `exercises` is a map of claims, with the prose in
    `$what`.** The suite used to check only that `exercises` was non-empty,
    which the new shape also passes; it now requires a non-empty `$what`
    string and a non-empty map, and that `counts.quantise` matches the
    quantise vectors. The five replaced seeds reproduce byte for byte.
  - **The focus ring is held to 3:1 on `surface`, `surface.alt` and
    `brand.tint`.** The existing contrast gate picked these three pairs up
    unchanged, and salt passes them in all eight display-mode combinations
    for the frozen seeds, the sRGB grid at a step of 15 and the compiled
    defaults. Worst case, light mode with no preferences: 4.88:1 on
    `surface` and 4.08:1 on `surface.alt` (seed #0ff00f, focus #008401 on
    #ffffff and #e7ebf1), and 4.54:1 on `brand.tint` (seed #009600, #008400
    on #e3fee0). No colour changed to meet them.
  - **The ring is at least 2px thick and 1px clear of the control, in px
    only.** salt drew 2px and 2px as literals. `@theme` now declares
    `--focus-ring-width: 2px` and `--focus-ring-offset: 2px`, the global
    `:focus-visible` rule reads them, and the suite checks each is declared
    once, as a px length or 0 (refusing `!important`, `rem`, `calc()` and
    the rest by name), meets its floor compared as decimal digits, and is
    what the ring draws. The rendered ring is unchanged; the built
    stylesheet carries the two properties and reads them.
  - **Every focus ring now comes from those tokens.** x-button (`button.php`),
    `booking.php`, `map.php`, the contact form's submit (`contact.php`) and
    the display-preference toggles (`display-preferences.php`) no longer
    carry `focus-visible:outline-2 focus-visible:outline-offset-2`, whose
    literal 2px beat the base rule. Markup: those two classes leave each
    element's `class` attribute and nothing else changes (17 snapshots,
    32 lines). A child theme that copied one of those class strings keeps
    the old literal ring. Chrome for Testing 148.0.7778.96 measured each of
    the five on `surface` and on the inverse band, light and dark, at 320
    and 1280 CSS px, for three frozen seeds, before and after: computed
    outline, offset, box-shadow and the pixels leaving the element were
    identical in all 120 cases. The suite now refuses any outline utility
    in a file Tailwind scans.
  - **Nothing may draw over the keyline either.** A `shadow-*`, `ring-*` or
    `inset-shadow-*` utility on a control in an inverse section, or a later
    `box-shadow` on it in app.css, replaces the keyline and leaves the ring
    alone on the band (2.77:1 light, 2.24:1 dark) with every gate green
    (re-review of otigs/salt#8). The suite now refuses those utilities in
    any file Tailwind scans, behind any variant and in either important
    spelling, read from class attributes and string literals with PHP's own
    tokenizer. The nav submenu's `shadow-lg`, on a `<ul>` in the header that
    takes no focus, is the one allowed use, counted exactly. In app.css only
    the keyline rule and its nested-ground reset (`none`) may set a
    `box-shadow`. A bare `outline` class, which Tailwind 4.3.2 builds as
    `outline-width: 1px` and which beats the ring, is refused too, in a
    class attribute or a class-string literal; the word in prose is not.
  - **Text roles bind a product that declares one.** salt declares no
    `--text-*` property at all, so none binds. The suite now fails if a
    text-role property appears without the full required set.
  - **The token-file schema's `$id` moved to jsDelivr.** salt resolves the
    `./schema/token-file` export by path and never refers to the URI, so
    nothing changed.
  - **The package declares `"engines": { "node": ">=22" }`.** `.nvmrc`
    already names 22.
- **The design contract is now `@lightlysaltedhq/design-foundations` 2.1.0**,
  an exact-pinned devDependency, replacing the sibling checkout of the
  archived base-design-system (which CI cloned with a deploy key and no ref,
  so it floated on that repository's HEAD). The conformance suite
  (`tests/conformance/FoundationsConformanceTest.php`) resolves each file
  through the package's `exports`, accepts only a bare version, and fails by
  name if the package, an export or a file is missing. It also pins the
  contract's rules whole: a SHA-256 of vocabulary.json in canonical form (keys
  sorted, `$` prose keys and `version` removed) is recorded next to the
  version salt was checked against, so any normative change fails with the
  `npm diff` command to read until salt is updated and the hash re-pinned.
  Dev and CI only: no theme code, token or rendered output changes.
- Contract differences from base-design-system v1.0.0 (bc82e01), the only
  copy salt's CI ever read (both conformance runs, 16/07/2026): vectors and
  derivation are byte-identical, and the required colour, status and
  typography roles, the unconditional contrast rules, the tiers, the neutral
  ramp and the dark-mode rule are unchanged. The vocabulary adds the whole
  `extended` colour section (48 roles plus three status-companion suffixes),
  the shadcn `adapters` mapping, optional `display` and `lead` typography
  roles, and all 16 conditional contrast rules. All of that is opt-in except
  one rule: `ink` on `brand.tint` at 4.5:1 names two required roles, so it
  binds salt.
- Where that rule holds, measured over 815 distinct brand seeds: light mode
  at 13.23:1 or better with grayscale off, and 12.20:1 with grayscale on
  (neutral-900 #26292e on neutral-100 #e7ebf1, the same for every seed);
  dark mode with grayscale off at 14.91:1 or better. High contrast only
  raises those. Where it does not: dark mode with grayscale on, because `[data-grayscale]` re-points
  `brand.tint` to neutral-100, which is the dark theme's `ink`. That renders
  at 1.00:1, and at 1.20:1 with high contrast on as well. The CSS predates
  this change; the fix is under Fixed below (otigs/salt#4).

### Fixed
- **The focus ring on an "Inverse (dark)" section is visible** (review of
  otigs/salt#8). `focus` is a brand step and `surface-inverse` the far end
  of the neutral ramp, so on that band the ring measured 2.34–2.99:1 in
  light mode and 2.05–2.56:1 in dark over the 5,832 grid seeds, under the
  3:1 a focus indicator needs. design-foundations 3.0.0's
  `focusIndicator.keyline` does not rely on the ring there and asks for an
  `ink.inverse` keyline, at least 2px, as the indicator's outermost part.
  A focused element in a `bg-surface-inverse` section now carries one: a
  box-shadow keeps the 2px offset gap in the band's colour and draws a 2px
  keyline (`--focus-keyline-width`) outside the ring. A card or panel
  painted inside the band is its own ground and gets none. **Visual
  change:** in light mode a near-white #f2f5fa line now surrounds the ring
  on the dark band; in dark mode a dark #26292e line surrounds it on the
  light band. Measured in Chrome for Testing 148.0.7778.96 (headless, DPR
  1), for a primary x-button and a text link, in light and dark, at 320 and
  1280 CSS px wide, over the contract's 15 frozen seeds: the keyline meets
  the band at 13.35:1 light and 12.20:1 dark, the ring meets the keyline at
  4.47–5.53:1 light and 4.85–5.93:1 dark, and the ring's own ratio to the
  band, still what shows in the gap, is 2.41–2.99:1 light and 2.06–2.51:1
  dark. The keyline measured at least 2 device pixels in every case, with
  the gap and ring in place inside it. The conformance suite checks the
  keyline's rule, width and colour.
- **Dark-mode primary buttons meet AA** (otigs/salt#3). `--color-on-brand`
  was #f8fafc in every mode, and dark mode fills `brand` with primary-400
  (primary-300 on hover, primary-200 and primary-100 with high contrast). Over
  the contract's 15 seeds that measured 2.35–2.80:1 at rest and 1.68–1.89:1
  on hover, and 1.31–1.41:1 and 1.10–1.15:1 with high contrast. On-brand now
  follows the mode: `salt_brand_css()` also prints
  `--color-on-primary-{200,400,600,700}`, `salt_text_on()` of each step a mode
  fills `brand` with, app.css defaults them for the neutral ramp, and each
  mode points `--color-on-brand` at its own step. Dark now measures
  6.09–7.26:1 at rest and 9.05–10.17:1 on hover; with high contrast,
  12.07–13.02:1 and 14.81–15.46:1. **Visual change:** in dark mode, text on
  `bg-brand` (primary buttons, the pricing badge, process step numbers,
  pressed display-preference toggles) turns from near-white #f8fafc to
  near-black #0f172a. Light mode, with or without high contrast, renders
  unchanged for every frozen seed.
- **Dark mode with grayscale renders the brand tint dark** (otigs/salt#4). A
  new `[data-theme="dark"][data-grayscale]` rule puts the dark theme's steps
  on the neutral ramp: brand neutral-400 (was neutral-600), brand-strong
  neutral-300 (was neutral-700), brand-tint neutral-900 #26292e (was
  neutral-100 #e7ebf1), focus neutral-400 (was neutral-700), on-brand
  #0f172a. `ink` on `brand.tint` goes from 1.00:1 to 12.20:1, and from 1.20:1
  to 14.59:1 with high contrast as well. Outside the contract's rules, the
  same change takes `text-brand` on the page surface from 3.45:1 to 6.78:1,
  and the focus ring from 2.44:1 to 6.78:1.
- **The other two grayscale combinations get their own rules**, so no two
  equally specific rules disagree about a token and leave it to source order,
  the shape behind #4. Light with high contrast and grayscale had lost high
  contrast's steps to grayscale that way. It now uses them on the neutral
  ramp: brand neutral-700 (was neutral-600), brand-strong neutral-800 (was
  neutral-700), focus #000 (was neutral-700); on-brand is 7.10:1 at rest (was
  5.03:1). Dark with high contrast and grayscale kept the brand in colour
  (primary-200 and primary-100), because the dark + high-contrast rule
  outranked grayscale. It is now neutral-200 and neutral-100, with #0f172a
  on-brand at 12.44:1 (was 1.31–1.41:1).

### Added
- **The conformance suite evaluates the contract's contrast rules.** The
  five unconditional rules, plus each conditional rule whose roles app.css
  declares (in 2.1.0 only `ink` on `brand.tint`), are checked in all eight
  combinations of dark, high contrast and grayscale. Each check runs at rest
  and on hover (`brand.strong`), for the contract's 15 frozen seeds, the
  sRGB grid at a step of 15 (5,832 seeds; failures still name the frozen
  ones) and the compiled defaults, using salt's own `salt_derive_ramp()` and
  `salt_contrast_ratio()`. app.css is read as a table of rules: `@theme`,
  plus one rule per mode combination. The suite fails by name on any colour
  token it cannot rank: one declared under another selector, nested,
  `!important`, set by two equally specific rules that disagree (the
  source-order tie behind #4), or printed by `salt_brand_css()` as well as
  by a mode rule. It also fails when app.css reads a per-site token that
  `salt_brand_css()` does not derive. Before this, the suite read the
  contrast rules and evaluated none of them. The contract pin (2.1.0) and
  its vocabulary hash are unchanged. Chrome for Testing 148.0.7778.96
  (headless) confirmed the gate's resolved values against the built
  `app.59614ed624.css` for every seed and combination. The only mismatches
  were `accent` and `on-accent`, which Tailwind leaves out of the build
  because nothing reads them.

## [4.1.0] — 2026-07-03

Spacing-rhythm pass from a rendered-page UI audit of every nav destination.

### Added
- **Same-background adjacency collapse**: two neighbouring sections sharing a
  background have no visual boundary, so their paddings stacked into dead
  zones (~16rem on the FAQ page, ~13rem on Team). `salt_section()` now tracks
  the previous section's background in the render state and passes a new
  OPTIONAL `collapse_top` arg to x-section (additive — defaults render 4.0.0
  markup byte-identically), which drops the later section's top padding
  (`pt-0 md:pt-0`). A divider on the previous section is a boundary and keeps
  the full gap; `spacing: none` never collapses. Also exposed as a
  `flush_top` wrapper arg for sections that force it.
- Pagination styling for core `the_posts_pagination()` (chip treatment
  matching the share/social buttons).

### Changed
- **Full-bleed hero banner now sits flush against the header** (flush_top when
  an image is set) — removes the dead strip above the image. This alters the
  existing `hero-full-bleed.snap.html` (wrapper gains `pt-0 md:pt-0`); semver
  call for the tag owner — shipped as MINOR because 4.0.0 is untagged and the
  x-section change itself is additive.
- Singles normalised to one padding rule (content bands `py-14 md:py-20`,
  media bands `pt-10 md:pt-14`, benefits `pt-14 md:pt-20`, work
  challenge/solution `pb-14 md:pb-20`, gallery + related `py-14 md:py-20`);
  the service "Related services" heading drops lg → md (in-flow sub-section,
  not a page band).
- Demo seeder alternates section backgrounds so no two adjacent seeded
  sections share one (home logos/services swapped, team + faq + about
  rich-text rows → surface-alt) — belt and braces with the collapse.

## [4.0.0] — 2026-07-03

Engagement build-out: rich single templates (post/work/service), share +
author-box components, social icon set, archive hero band. MAJOR per
RELEASE-POLICY (changed `.snap.html` files — see BREAKING).

### Fixed (post-deploy visual QA)
- **Blog posts index rendered "Archives" as its H1** — `get_the_archive_title()`
  has no case for the posts page (`is_home()`) and falls back to the generic
  word. `archive.php` now uses the assigned posts-page title ("Blog").
- **Full-bleed hero image still filled the viewport** — the 60vh cap left a
  ~576px edge-to-edge image sitting above the heading, so interior heroes
  read as a full-screen photo. Standardised to a contained banner
  (`h-[clamp(220px,34vh,360px)]`): the image and the heading/CTA now share
  the first screen. (Approach hero 1004px→754px, image 576px→326px.)
- (Content/ops, not theme code) Stale `/blog/services/` links — the nav +
  footer "Services" items (404) and the 6 Approach capability cards — were
  baked by a reseed that ran before the service-CPT `with_front` fix
  deployed. Repaired live with a serialization-safe search-replace to
  `/services/`; future reseeds generate them correctly.

### Added
- **`single-post.php`** — engagement-first blog single: hero with the
  primary-category badge above the H1, byline (avatar + author link),
  published date, "Updated" date (shown only when the modified day differs),
  reading time (`salt_reading_time_minutes()`, 200 wpm), standardised 16/9
  featured image, `.x-prose` content typography, tag list, share row,
  author box, prev/next navigation, and a queued BlogPosting schema node.
- **`single-work.php`** — case-study single rendering EVERY
  `group_salt_work` field: client/industry/services fact strip (services
  link to their singles), live-site button (`live_url`), challenge/solution
  two-up, results as an x-stat strip, gallery grid + CreativeWork schema.
- **`single-service.php`** — service single rendering EVERY
  `group_salt_service` field: icon, category eyebrow, `headline` H1
  override, summary lede, "Projects from £X" (`price_from`), benefits
  checklist, CTA band (`cta` link), related-services card grid + Service
  schema.
- **x-share-links** component + `salt_share_services()` /
  `salt_share_links()` registry (X, Facebook, LinkedIn, WhatsApp, Bluesky,
  Reddit, email; Pinterest opt-in) and a copy-link button that ships
  `hidden` and is activated by the new `sections/share.js` module (FAIL02).
- **x-author-box** component — author name + bio from the WP user; avatar
  slot. New ACF **user field `profile_photo`** (`group_salt_user`) feeds it,
  falling back to `get_avatar()`.
- **14 bundled icons**: linkedin, x, facebook, instagram, youtube, tiktok,
  github, bluesky, whatsapp, reddit, pinterest, globe, link, star-filled.
  The footer social row (salt_chrome_social_hosts) now renders icons for
  known hosts — its designed no-code upgrade path — and the map gains
  bsky.app, github.com and pinterest.com.
- **`.x-prose`** long-form typography (app.css) for `the_content()` and
  wysiwyg field output.
- Archive loop cards: `content/card-post.php` (thumbnail/date/excerpt via
  x-card-post) and `content/card-work.php` (client/summary via x-card-work);
  the generic `content/card.php` now delegates to x-card so all archives
  grid consistently.
- Team-member `socials` platform choices extended: instagram, facebook,
  youtube, tiktok, github, bluesky (additive; stored values unchanged).
- Demo seeder: posts get fixed authors + backdated publish dates, the
  first administrator gets the author profile (display name, bio,
  profile_photo), capability cards on Approach link to their service
  singles, hero rows standardise to `md` spacing, and permalinks are set
  to `/blog/%postname%/`.

### Fixed
- **Work archive H1 rendered literal escaped markup** ("Archives:
  &lt;span&gt;Work&lt;/span&gt;") — core's `get_the_archive_title()` wraps
  the title in a raw `<span>` whenever a prefix is present, which
  `salt_the_document_h1()` correctly escapes. New
  `get_the_archive_title_prefix` filter returns '' (child themes re-add at
  20+), and `archive.php` strips tags defence-in-depth.
- **Testimonial ratings looked absent** — the card rendered N outline
  stars, so 4/5 was indistinguishable from "no rating". x-card-testimonial
  now always renders 5 stars: N filled (`star-filled`) + the remainder as
  muted outlines.
- **Hero media unbounded** — a large upload blew the band out. Hero images
  are now aspect-locked + object-cover (full-bleed 16/9, 21/9 from md,
  capped 60vh; split 4/3 rounded).
- **Service CPT rewrite lacked `with_front => false`** — under a
  /blog/%postname%/ permalink structure the service singles (and
  service-category archives) silently moved to /blog/services/… and 404ed
  at their real URLs. Now pinned like the work CPT.
- **Legacy top-level post URLs 404ed after the permalink move** — /{slug}/
  parses as a page request, which core's 404-guess ignores. New
  `salt_redirect_moved_post()` (template_redirect) 301s a 404 whose
  top-level pagename matches a published post slug.

### Changed
- `archive.php` renders a standard hero band (title + archive description)
  and a responsive card grid instead of a flat bordered list.
- Team-card social links render as icon links (aria-label "Name on
  Platform") instead of underlined text labels.
- `salt_enqueue_section_assets()` also enqueues route-scoped modules
  (`sections/share.js` on single posts).

### BREAKING
Markup structure changed (snapshot evidence — diff your override manifest):
- **x-card-testimonial** — rating group now 5 icons (filled + `span.x-card-testimonial-star-empty` outlines).
- **x-card-team** — `.x-card-team-socials` items are icon anchors (`.x-card-team-social`), not text links.
- **hero section** — media `<img>` carries aspect/object-cover classes in both variants.
- **site-footer** — social row renders x-icon glyphs for known hosts (was text labels).
- **content/card.php** (archive/search loop) — now the base x-card markup inside a grid; archive routes wrap the loop in `.x-archive-header` + `.x-archive-grid`.

## [3.0.5] — 2026-07-03

### Fixed
- Scoped Tailwind content detection to the markup directories that carry
  utility classes (`template-parts/`, `inc/`, `assets/src/js/`) via
  `source(none)` + `@source` in `assets/src/css/app.css`. Tailwind v4's
  whole-project auto-detection was scanning `.php` prose in `bin/`, `tests/`
  and `docs/`, so unrelated text could leak candidate class names into the
  built CSS and drift the FLEET08 artifact-integrity gate (as it did when
  the demo seeder was added). The built CSS is now deterministic against
  real markup only.

## [3.0.4] — 2026-07-03

### Fixed
- **Administrators lost `manage_options` — WP Admin hid Settings, plugin
  option pages, ACF, and salt's own Site Settings** (found on the live
  deploy). The submissions CPT (SEC09) mapped the META capabilities
  `edit_post`/`read_post`/`delete_post` to `manage_options`; WordPress'
  `_post_type_meta_capabilities()` then registers `manage_options` as a
  meta-cap alias in `$post_type_meta_caps`, so every bare
  `current_user_can('manage_options')` is rewritten to a post-scoped meta
  cap (no post ID) and denied for everyone, admins included. Fixed by
  mapping only the PRIMITIVE caps to `manage_options` and letting
  `map_meta_cap => true` derive the meta caps — admin-only access is
  unchanged, `manage_options` is no longer poisoned. Regression guard added
  in SubmissionsTest (the CPT's `capabilities` must not remap meta caps).

## [3.0.3] — 2026-07-02

### Fixed
- Rebuilt `assets/dist/` to match source (FLEET08 artifact-integrity gate):
  the committed CSS predated a template's use of the `shadow` utility, so
  a fresh build differed by one rule. No code or markup change; the live
  effect was a single missing box-shadow. Release gates now always rebuild
  and commit `assets/dist/` before tagging.

## [3.0.2] — 2026-07-02

### Fixed
- **Front-end white-screen for ALL sites** (found on first real deploy):
  `salt_schema_render()` is typed `?array $context` but was hooked directly
  to `wp_footer`; `do_action()` passes an empty string to callbacks, so the
  argument bind TypeError-fataled every front-end page — with or without
  ACF. Fixed with a parameterless `salt_schema_render_footer()` wrapper.
  The PHPUnit `do_action` shim now passes the `''` argument WordPress
  really passes (so this class of bug fails in CI), plus a `HookFiringTest`
  that fires `wp_head`/`wp_footer`.
- **ACF-activation fatal + DEP01, done correctly.** v3.0.1's fix defined
  `get_field()` stubs when ACF was absent — which then collided with ACF
  Pro on activation (`Cannot redeclare get_field()`). Replaced with a
  single guarded accessor `salt_option()` (inc/helpers.php); all ~42
  runtime option reads route through it, so the theme NEVER defines an ACF
  function (no collision) and every read returns null when ACF is absent
  (no fatal, safe fallbacks + DEP01 notice). `bin/check-acf-absent.php`
  now also asserts the theme defines no `get_field()`.

### Superseded
- 3.0.1's `salt_ensure_acf_fallbacks()` approach (removed).

## [3.0.1] — 2026-07-02

### Fixed
- **DEP01 (Blocker) regression**: the theme white-screened (HTTP 500) on a
  site without ACF Pro instead of showing the admin notice + safe
  fallbacks. `salt_module_enabled()` and ~25 other option reads call
  `get_field()` on render hooks; with ACF absent that function is undefined
  and fataled during CPT registration on `init`. Fixed with a boot-time
  safety net (`salt_ensure_acf_fallbacks()`, inc/acf.php) that defines
  null/false-returning stubs for ACF's read API when ACF is absent, so
  every consumer degrades to its documented default. The PHPUnit harness
  masked this by always defining `get_field()`; added
  `bin/check-acf-absent.php` (in CI) which reproduces a genuinely ACF-less
  request and drives the CPT-registration path that fataled. Patch per
  RELEASE-POLICY.md (non-markup PHP internals; no field/args/markup change).

## [3.0.0] — 2026-07-02 — Site Settings IA + appearance controls

### Added
- **Site Settings restructured into seven sub-pages** ordered as the
  day-one setup walk: Company & Schema, Brand & Design, Logo & Fonts,
  Header & Footer, Contact, Integrations, Advanced. All existing values
  survive untouched (field names/keys frozen; page location is
  presentation, not storage).
- **Self-hosted fonts** (upload woff2/woff/ttf per weight, format
  preference, ≤2 preloads, `--font-heading`/`--font-body` role tokens).
- **Logo panel**: light/dark display logos (attribute-driven dark swap),
  height token, text fallback — distinct from the schema logo.
- **Header & Footer options**: sticky header with automatic anchor offset
  (§12a), header CTA, footer line + social icons; `Salt_Nav_Walker` with
  registered `primary`/`footer` menu locations.
- **Display Preferences widget** (§14, off by default): font size steps,
  high contrast, grayscale, dark mode, motion — token-level, localStorage
  persisted, pre-paint init, never an overlay.
- **Admin live contrast checker** on Brand & Design: WCAG AA pass/fail for
  the derived pairs in light AND dark, with a PHP↔JS colour-math parity
  gate in CI.
- Schema: `org_type` (single-location LocalBusiness), review profile into
  `sameAs`, `company_address` passthrough; SEO02 hardening (@id-based
  self-rating policing incl. post-merge).

### Fixed
- `company_address` was consumed by the Contact section but never
  registered; the schema builder read `org_phone`/`org_email` instead of
  the registered `company_phone`/`company_email`.

### Changed
- **BREAKING**: `header.php`/`footer.php` now render the site chrome
  (header/footer parts, logo, menus) on every page — child themes
  overriding either shell or styling the header area must re-verify.
  New template parts under `template-parts/header|footer/`. The consent
  init script grew preference keys (same nonce'd-script contract).

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
