# Changelog

All notable changes to the salt parent theme. Format:
[Keep a Changelog](https://keepachangelog.com/); versioning:
docs/RELEASE-POLICY.md. Releases with breaking changes carry a **BREAKING**
section enumerating changed components/sections at args-signature and
markup granularity (cross-reference your site's override manifest).

## [Unreleased]

### Added

- **A PHP fixture adapter and a partial contract conformance baseline
  (ID-3626, EP-72 M3 unit 5).** `tests/contract-adapter/adapter.php` is
  the command adapter for the pinned salt-contract's conformance runner.
  It reads one fixture case on stdin and seeds its values into the ACF
  shims in the contract's stored shape. It then renders the case through
  the theme's resolver and view, or the chrome's template part, without
  WordPress. Any PHP diagnostic fails the case. `npm run
  conformance:contract` runs it with `--partial`; it always exits 1, as
  every partial run does (SC-017). It checks field parity against
  `inc/fields/generated/sections.json` and the stylesheet pin against the
  served `salt.css`; `tabs`, `listing` and `pricing` are not shipped.
  The baseline report against salt-contract 1.0.0-rc.6 is committed in
  `tests/contract-adapter/baseline/` as M4's worklist. Views and the page
  are not rendered yet. See `tests/contract-adapter/README.md`.
- **The contract's view and page cases render on the real-ACF harness's
  WordPress (ID-3626, EP-72 M3 unit 5b).** `npm run conformance:harness`
  (`tests/real-acf/run.sh --conformance`) builds the harness's site with
  ACF Pro, keeps it up and runs `npm run conformance:contract` against it.
  With the site up, the adapter hands each view and page case to
  `tests/real-acf/php/view.php`. That script resets the site, seeds the
  case's document, site, route, media and documents into posts, terms,
  options and routes, requests `context.path` (a search at WordPress's own
  `/?s=`), and returns `main#main`, or the whole document for the page.
  Without the harness, those cases still exit 4. All 23 view and page cases in salt-contract 1.0.0-rc.7 now render
  with no PHP diagnostic and differ only in their markup. Any PHP notice,
  warning, deprecation or error fails the case (exit 70) and the run. The
  committed baseline is regenerated from this run, and it is the same byte
  for byte on PHP 8.2 and 8.5. The harness's phases now share their seeding
  helpers in `tests/real-acf/php/seed.php`, and `db.php` can snapshot and
  restore the site's tables. No theme code changed.
- **The contract's token layer and stylesheet, beside Tailwind (ID-3611,
  EP-72 M3 unit 9).** `assets/src/css/app.css` now emits every token
  salt-contract 1.0.0-rc.6's `contract/token-layer.json` asks of a theme
  (109 required, plus the optional `--ease-in-out`): four new colour roles
  (`ink-muted-inverse`, `border-inverse`, `border-strong`,
  `border-strong-inverse`) in `@theme`, now `@theme static` so Tailwind
  keeps roles only salt.css reads, and the text roles, type scale, space,
  layout roles, measures, radii, opacity and scrims in a plain `:root`
  block. The build copies the pinned `styles/salt.css` verbatim to
  `assets/dist/salt.<hash>/salt.css` (the contract finds it by that file
  name), and `salt_enqueue_assets()` serves it as `salt-contract`, after
  `salt-app`, as the contract's README requires. `TokenLayerTest` holds the
  built tokens to the token layer, `ContractStylesheetTest` holds the
  served file to the package byte for byte, and FoundationsConformanceTest
  now also measures `border-strong` at 3:1 on `surface` and `surface-alt`.
  salt.css's base layer sets body text and the headings inside `#main` in
  their text roles where no utility sets them, so body text grows from
  16px to a fluid 16–18px. Tailwind stays until ID-3946.
- **Every section's fields generated from the contract (ID-3610 part A,
  EP-72 M3 unit 3).** `inc/fields/generated/sections.json` is the pinned
  salt-contract 1.0.0-rc.6 emitter's `acfSnapshot` output, committed
  verbatim, for the twelve sections the theme renders (hero, rich-text,
  call-to-action, media-text, features, stats, logos, gallery, process,
  faq, contact, locations), with its options in
  `sections.options.json` (the section list and the theme's 24 icons).
  `salt_section_layouts()` loads it on `acf/init`; a missing or invalid
  file is logged and the theme falls back to the hand-written layouts
  (DEP01 still holds). The six collection layouts (blog_teaser,
  scroll_carousel, services, team, testimonials, work) stay hand-written
  until unit 8, and a hand-written layout named after a generated section
  is refused and logged. tabs, listing, collection-showcase and carousel
  are not registered: nothing renders them yet.
- **`bin/check-fields-from-contract.php`** (drift check): fails when the
  committed JSON differs from the pinned emitter's output (it runs the
  emitter with `node`) or when the registered layouts differ from the
  JSON. `--write` regenerates; `--contract <dir>` points at another copy
  of the package. Its CI step is proposed in
  `docs/ci/fields-from-contract.patch`.
- **Shared collection-query reader** `salt_query_controls_from_query()`:
  turns the contract's `query` group (`mode` automatic, by-category or
  manual; `categories`; `items`; `order` default, newest or title;
  `count`, capped at 24) into the query controls `salt_resolve_query()`
  runs. faq and locations use it.
- **FLEET07 now guards field storage paths (ID-3943, EP-72 M3 unit 4).**
  `docs/contracts/slug-registry.json` gains `paths`: for each field group,
  and each Flexible Content layout in it (`group_salt_sections / hero`), the
  meta-key path of every field as ACF builds it (enclosing group, repeater
  or Flexible Content names, then the field name, rows written as `N`:
  `sections_N_settings_spacing`, `contact_routes_N_recipient`). It is
  generated by `bin/extract-slugs.php` from what the theme registers: the
  generated sections JSON, the six hand-written collection layouts and the
  options and post type groups. `bin/check-slug-stability.php` treats a
  changed path exactly as a removed slug: a field moved into or out of a
  group or repeater, or a renamed group or repeater, fails a MINOR or
  PATCH release unless `slug-migrations.json` has a
  `path:<scope>:<path>` entry, and is listed for the changelog on a MAJOR
  one. The failure names the old path and the likely new one. The registry
  is rebaselined at 6.0.0-dev; v5.0.0's registry has no paths, so the
  first comparison is against the next tag. Self-test:
  `bash bin/check-slug-stability.test.sh`; its CI step is proposed in
  `docs/ci/slug-stability-self-test.patch`.
- **`tests/unit/StoragePathCaseTest.php`** walks every registered field
  group and fails when two storage paths in one layout differ only in case
  (MariaDB matches `meta_key` case-insensitively).
- **The section settings group.** Every section, generated or
  hand-written, has the contract's `settings` group: `spacing`, `tone`,
  `toneDark`, `width`, `backgroundImage`, `anchorId`, `firstPageOnly`,
  `divider`. The wrapper reads only this group. `firstPageOnly` hides the
  section on page 2 and later of a paginated page. `toneDark` and
  `backgroundImage` are stored but not drawn until the contract
  stylesheet lands (unit 9), except that a full-bleed hero takes its image
  from `backgroundImage`.
- **Real ACF Pro harness for the contract's field shape (ID-3610, EP-72
  M3 unit 2).** `tests/real-acf/run.sh --php 8.2|8.5` builds a throwaway
  WordPress 6.8 with the theme and ACF Pro 6.8.11 (the hash-checked zip,
  or a Composer copy from ACF's private repository). It registers the
  pinned emitter's `acfSnapshot` output through a harness-only mu-plugin
  until the theme loads it (unit 3), and seeds every contract fixture case
  through `update_field`. It then reads them back with the theme's
  idioms, checks storage paths and case collisions in the database, and
  drives the classic editor in headless Chromium: layouts, conditional
  logic, required fields and a re-save diff. Afterwards it drops the
  database and removes the site. It has its own locked
  `composer.json`/`package.json`, and the theme's installs are unchanged.
  It is `export-ignore`d. A CI job for it is proposed in
  `docs/ci/real-acf-harness.patch` and needs the `ACF_PRO_KEY` and
  `ACF_PRO_SITE_URL` secrets. See `tests/real-acf/README.md`.
- **Every collection's fields generated from the contract (ID-3610 part
  B, EP-72 M3 unit 7).** `inc/fields/generated/collections.json` is
  salt-contract 1.0.0-rc.7's ACF emitter output (`emit/acf.mjs` with
  `inc/fields/generated/collections.options.json`): one group per
  collection the theme installs, `group_salt_collection_{collection}`,
  located on its post type (services on `service`, case studies on
  `work`, testimonials on `testimonial`, posts on `post`, team on
  `team_member`, faqs on `faq`, locations on `location`). The loader
  (`salt_generated_collection_groups()` in `inc/acf.php`) registers it
  through `inc/fields/collections.php`, each group behind its module gate;
  posts always register. What the contract names under `salt.native` is
  read from WordPress, not ACF: the title, the body, the excerpt (the
  summary, through the new `salt_post_summary()`), the featured image,
  categories and the post date. `bin/check-fields-from-contract.php` now
  also fails when the collections JSON is not the pinned emitter's
  output, when a registered collection group differs from it, when another
  group sits on a collection's post type, or when an
  `inc/fields/cpt-*.php` file exists. The options and user groups stay
  hand-written. `docs/ci/fields-from-contract.patch` names the wider check.
  The real ACF harness has a new phase (`tests/real-acf/php/collections.php`)
  that seeds every generated collection field through `update_field()`
  on all seven post types, reads it back and checks its storage.
- **salt-contract pinned exactly (ID-3627).** `@lightlysaltedhq/salt-contract`
  1.0.0-rc.6 is a devDependency on a committed `npm pack` tarball,
  `vendor-contract/lightlysaltedhq-salt-contract-1.0.0-rc.6.tgz`, packed
  from lightlysaltedhq/contracts at `30fe7ef`, because release candidates
  are never published. `tests/conformance/SaltContractPinTest.php` fails
  on any other spec in `package.json` or the lockfile, on a changed byte
  in the tarball, and on an installed copy that is not the tarball's files
  unchanged. Read at build and test time only; the tarball is
  `export-ignore`d, so client sites never receive it.
- **Declared extension points: `salt-overrides.json` (ID-3613).** The
  parent reads the salt-contract's replaced-logic declaration
  (`schema/replaced-logic.schema.json`, SC-015) from the root of the child
  theme: `salt_overrides()` returns it validated and memoised, or `null`
  when there is no child theme, no file, or an invalid one (logged once,
  never fatal). Section ids are the contract's, mapped to layout slugs by
  `salt_contract_section_layouts()` (since ID-3599, `call-to-action` is
  the `call-to-action` layout).
  - Tier `view`: no rendering change; a child view loads as before,
    declared or not.
  - Tier `logic`: the declared section's parent resolver and wrapper no
    longer run; the child's `template-parts/sections/{slug}.php` is loaded
    whole with the loop context and must call `salt_section()` (drawing
    markup without it is logged). With no child template it logs, throws
    under `WP_DEBUG` and renders nothing. An UNDECLARED replacement fails
    exactly as before.
  - Testimonials is matched per row: a `grid` row is collection-showcase,
    a `slider` row is carousel (the contract's `when`).
  - `clientSections` and `imageSizes`: read and exposed, and raised by the
    update flag; no rendering change.
  - **The update flag:** a notice on the Themes and Updates screens (and
    the network Updates screen), for `switch_themes` users, listing every
    `logic` entry, every `view` entry whose `verified` is not the file's
    `contract`, `imageSizes` rows whose section is raised or undeclared,
    client sections whose id Salt uses, an invalid file, and every
    mismatch between the declaration and the child's files (including
    declared files outside the child theme). It cannot tell whether an
    update changed a section's view props: a view swap whose `verified`
    equals `contract` is not listed, so every view swap is re-verified by
    hand when an update moves to a newer contract release.
  - Design dials are not supported yet (ID-3612).
  Docs: docs/contracts/OVERRIDES.md "Declaring replaced sections",
  docs/templates/override-manifest.md, docs/contracts/CONVENTIONS.md.
  Semver: MINOR (a new extension point; no pluggable function added or
  removed, no args or markup change, no `.snap.html` changed, and a site
  without `salt-overrides.json` renders byte-identically).
- A "Release tag gate" CI job, on tag pushes only, runs `bin/check-release-tag.mjs`:
  a `vX.Y.Z` tag fails unless `style.css` `Version` and `SALT_VERSION` equal it and
  the tagged commit is on `main`. It detects a bad tag after the push; it does not
  prevent one. See docs/RELEASE-POLICY.md.

### Changed

- **Version `6.0.0-dev`** in `style.css` and `SALT_VERSION` (founder
  decision D11). No tag and no release.
- **Resolvers read the contract's field names and shapes.** View props and
  view markup are unchanged (that is M4) except where the new shape
  forces it; see BREAKING for the visible changes.
- **The real ACF harness registers through the theme's own loader.** The
  harness-only mu-plugin is gone; the harness checks that the theme's
  generated layouts are the emitter's output, and a new phase renders the
  fixture page through the theme's section loop on real ACF values.
- **The real ACF harness runs on the latest WordPress, 7.1.4 (the
  founder's ruling: clients run the latest release, and the theme must
  raise no deprecation on it).** `roots/wordpress-no-content` is pinned to
  7.1.4 exactly, in place of 6.8. Each run asks api.wordpress.org for the
  latest release and fails, naming both, when the pin is older
  (`tests/real-acf/php/latest.php`); only `--offline` skips that, reported
  as "STALENESS NOT CHECKED". The PHP 8.5 exemption for a WordPress 6.8
  core deprecation is gone: any PHP deprecation, notice or warning fails
  the run, on every PHP version. `docs/DEPENDENCY-MATRIX.md` gains a
  v6.0.x row: the latest WordPress release only.
- **salt-contract 1.0.0-rc.7 (ID-3944, ID-3949, ID-3950).** The pin moves
  to `vendor-contract/lightlysaltedhq-salt-contract-1.0.0-rc.7.tgz`, packed
  from lightlysaltedhq/contracts at `d292ecc`; the rc.6 tarball is gone.
  `inc/fields/generated/sections.json` is regenerated: every layout gains a
  Settings tab (it stores nothing) before its `settings` group. The
  conformance baseline in `tests/contract-adapter/baseline/` is
  regenerated against rc.7, and the adapter seeds a collection's native
  places from `salt.native`.
- **The rich-text section's body stores headings 2 to 6 (ID-3950).**
  Before, an editor re-save turned a stored h2, h5 or h6 into a paragraph.
  Stored levels are drawn as stored; the contract's re-ranking from the
  section's heading base is M4. Every other rich-text field keeps its own,
  narrower list (see BREAKING).
- **Slugs (FLEET07).** `docs/contracts/slug-registry.json` regenerated.
  The 6.0.0 entries in `docs/contracts/slug-migrations.json` are retired:
  they only described stored content from before 6.0.0, and no site holds
  any.
- The design contract pin moves to `@lightlysaltedhq/design-foundations` 3.0.1, the
  first release from the public `lightlysaltedhq/contracts` repository, with an npm
  provenance attestation. The contract itself is unchanged from 3.0.0, and the
  conformance suite passes against it with only `PINNED` updated.
- **Every section split into logic and view (all 20: blog-teaser, contact,
  cta, faq, features, gallery, hero, intro, locations, logos, media-text,
  pricing, process, rich-text, scroll-carousel, services, stats, team,
  testimonials, work).** Each section has a parent-only resolver,
  `inc/sections/{slug}.php` (`salt_resolve_section_{slug}()`), that does
  the field reads, queries,
  sanitising, schema, row skipping and logging, and returns the view props
  named after the contract's view props. `template-parts/sections/{slug}.php`
  is now the view and only draws `$args['props']` and `$args['plan']`
  (`index`, `headingLevel`, `priorityMedia`). New shared entry
  `salt_render_resolved_section()`; `salt_render_section()` renders through
  it. A layout with no parent resolver (a child-only layout) still loads
  its template whole, exactly as before. Rendered markup is unchanged (no
  `.snap.html` file changed). Rules:
  docs/contracts/CONVENTIONS.md "Sections: logic and view"; child themes:
  docs/contracts/OVERRIDES.md "Section views".

### Fixed

- **Muted text on the brand tint clears 4.5:1 in light mode with grayscale
  (ID-3588, lightlysaltedhq/salt-wordpress#6).** Grayscale pointed
  `brand-tint` at neutral-100, where `ink-muted` (neutral-600) measured
  4.40:1. It now uses neutral-50, the step light mode already uses on the
  primary ramp: 4.81:1 for every seed. **Visual change:** brand-tint
  grounds in light grayscale are a shade lighter (#f2f5fa, was #e7ebf1);
  `ink` and `focus` on them rise to 13.35:1 and 6.80:1 (without high
  contrast). Dark grayscale is unchanged. The conformance suite now also
  measures `ink.muted` on `brand.tint`, a pair salt paints that the
  contract has no rule for, in every display mode.

### BREAKING
- **Rich-text allow-list widened, and narrowed per field (salt-contract
  1.0.0-rc.7, ID-3950; security-reviewed on PR #26).** `salt_allowed_html()`
  is the superset and now holds h2 to h6 (still no attribute on any of them;
  h1 stays forbidden). Each rich-text field is sanitised and edited to its
  own contract list only: `salt_sanitize_html( $html, $allowed )` takes the
  field's element kinds, which `salt_field_allowed( $key )` reads from its
  `salt.allowed` in the generated JSON (failing closed to paragraphs for an
  unknown key), and `salt_allowed_html_for()` intersects them with the
  superset, so no list can widen it. The service intro, kept in the post
  body, uses `SALT_SERVICE_INTRO_ALLOWED`, held to the pinned contract by a
  test. In the editor, `acf/prepare_field/type=wysiwyg` puts each field's
  list on its wrapper and `assets/src/js/admin/rich-text-editor.js` narrows
  its TinyMCE format menu, `valid_elements` and toolbar; the service's own
  editor is narrowed in `salt_tinymce_config()`. Per field, h2 to h6 are
  allowed only in the rich-text body:
  - call-to-action `body` and media-text `content`: h3 and h4, plus
    paragraph, bold, italic, code, link, lists and blockquote;
  - a features item's `text`: no heading (it was h3 and h4);
  - a case study's `challenge` and `solution`: h3 and h4, no code and no
    blockquote (they were drawn through `wp_kses_post()`);
  - an FAQ `answer`: paragraph, bold, italic, link and lists only (it was
    also h3, h4, blockquote and code);
  - the service intro: paragraph, bold, italic, link and lists only (it was
    drawn unfiltered by `the_content()`).

  Anything else is stripped to its text. `salt_sanitize_html()` with no
  list, as a child theme calls it, keeps the whole superset. The accordion
  component takes an optional `allowed` list for its re-run sanitiser.
- **Collections read the contract's own field shape (ID-3610 part B,
  EP-72 M3 unit 7; FLEET07: MAJOR, 6.0.0).** As with the sections, no site
  holds Salt for WordPress content, so there is no dual reader and no
  migration. Generated keys are `group_salt_collection_{collection}` and
  `field_salt_collection_{collection}_{path}`.
  - **Removed files:** `inc/fields/cpt-{service,work,testimonial,
    team-member,faq,location}.php` and their groups (`group_salt_service`
    and the rest); the six legacy collection layouts and their resolvers
    stay until unit 8.
  - **Dropped fields:** testimonial `logo` (`companyLogo`; the card draws
    the author's photo, the featured image, instead) and `service`; service
    `headline` (the H1 is the title); the `summary` fields on services and
    case studies (the summary is the excerpt); team socials `platform` (the
    network is worked out from the address, `socials.json`); location
    `geo` (now `lat`, `lng` and `zoom`).
  - **Dropped from WordPress:** the `industry` taxonomy on work and the
    `department` taxonomy on team members; the body (editor) of work and
    team members; the location's featured image; the Gravatar fallback for
    a post author (the avatar is the user's `profile_photo` or nothing).
  - **Renamed fields:** work `live_url` → `externalUrl`; service
    `price_from` (a number) → `priceFrom` (text, drawn as written),
    `related_services` → `relatedServices`, benefit row `benefit` → `text`,
    `cta` (an ACF link) → a contract link `{label, type, document, url,
    newTab}`; team `short_bio` → `bio`; location address `line1` and
    `line2` → `street` (a line each), `city` → `locality`, `postcode` →
    `postalCode`, `opening_hours` → `hours`, row `appointment_only` →
    `appointment`.
  - **New, stored but not drawn until M4:** case study `details`;
    location `lat`, `lng` and `zoom`; post `author`, `primaryTopic` and
    `layout`.
  - **Removed schema:** the testimonial `Review` nodes. A Review needs the
    service it reviews, and the contract stores none.
  - **Half stars (testimonials.json rating, step 0.5).** The resolver keeps
    a stored rating as stored (clamped to 0..5), and `card-testimonial`'s
    `rating` arg is a float. The stars are drawn as the contract's card draws
    them (`markup/card.json`, rating): five empty stars with five filled ones
    clipped to `--salt-rating-fill`, the rating as a percentage of 5, so 4.5
    fills 90%. The label reads "Rated 4.5 out of 5 stars" (the founder's
    ruling on how a half value is written). The rest of the card's markup is
    unchanged until M4.
  - **Topics (`_topic.json`; the topics owes of services, case studies, FAQs
    and locations).** `service_category`, `work_type`, `faq_category` and
    `location_area` are labelled Topics (a Topic) in place of "Service
    categories", "Work types", "FAQ categories" and "Areas", keep their
    checkbox box, and offer no parent anywhere: not in the box's "add new"
    form (`salt_topic_meta_box()`) and not on the term add or edit screens.
    Every term is stored at the top level, whatever parent is sent
    (`inc/cpt/topics.php`). Posts' categories are ID-3952's.
  - **The summary is checked on save (services, case studies, posts).** An
    excerpt over 300 characters is refused in the editor
    (`admin/summary-limit.js`) with "The summary (the Excerpt box) is N
    characters long. Shorten it to 300 characters or fewer". Characters are
    counted as Unicode code points (`mb_strlen`, a CRLF as one; the
    founder's ruling).
  - **The title is checked on save (every collection; the founder's
    ruling).** The post title of a service, case study, post, testimonial
    (name), team member (name), FAQ (question) and location (name) over the
    contract's 120 characters is refused in the editor
    (`admin/title-limit.js`, the same way as the summary) with "The title is
    N characters long. Shorten it to 120 characters or fewer", counted as
    the summary is.
  - **Other checks on save the contract asks for** (`inc/fields/checks.php`):
    a field with the email format (a team member's and a location's
    `email`) must be one @, a domain with a dot and no spaces; a location's
    `street` holds at most two lines (`maxLines`); a service cannot be among
    its own `relatedServices`; and a field with the url format must be an
    absolute http, https, mailto or tel address (the founder's ruling): a
    team member's `socials` url, a case study's `externalUrl`, a features or
    logos item's `url`, and the `url` of every external link (a service's
    `cta`, the sections' buttons and links). A path such as `/contact`, an
    anchor such as `#pricing`, `//x.example`, `javascript:` and `ftp:` are
    refused with a message naming the four schemes.
  - **Post fields hidden until unit 10.** `author`, `primaryTopic` and
    `layout` stay in the generated posts group (so the drift check holds and
    they store and read), but `acf/prepare_field` keeps them out of the
    editor until ID-3952 wires them.
  - **The service intro (the founder's ruling).** `single-service.php` draws
    the stored body with paragraphs added (`wpautop()`) and sanitised to the
    intro's list, with no shortcode, oEmbed or other `the_content` filter
    (`salt_service_intro_html()`). A service's body is stored sanitised to
    the same list (`wp_insert_post_data`), so what is stored is what shows.
    The service editor has no Add Media button, and its Visual and Text
    toolbars offer only the list.
  - **Visible changes:** the service card draws its featured image, and its
    icon only when it has none (rc.7 card mapping); the testimonial card
    draws the author's photo, not the company logo; a team member's social
    link is labelled by the network its address belongs to; the service
    single's H1 is the title, its price reads "Projects from" and the text
    as written (no £ added), and a service is never related to itself; the
    case study single no longer draws its industry fact or its body; a post
    author with no profile photo has no avatar; a testimonial rated 4.5
    draws four and a half stars (the star markup is now an empty row with a
    clipped filled row over it); the topics taxonomies are labelled Topics
    with no parent; the service intro draws no shortcode, embed or media;
    an FAQ answer loses h3, h4, blockquote and code, a features item's text
    loses its headings, and a case study's challenge and solution narrow
    from `wp_kses_post()` to their lists (see the rich-text entry above).
  - **Removed field slugs in the registry:** `appointment_only`, `benefit`,
    `city`, `geo`, `headline`, `line1`, `line2`, `live_url`, `logo`,
    `opening_hours`, `platform`, `postcode`, `price_from`,
    `related_services`, `service`, `short_bio`, `summary`.
- **Sections read the contract's own field shape (ID-3610 part A, EP-72 M3
  unit 3; FLEET07: MAJOR, 6.0.0).** Nothing stored before this change is
  read: no site holds Salt for WordPress content, so there is no dual
  reader and no migration. Generated field keys are the emitter's
  (`field_salt_{section}_{path}`); generated layout keys are
  `layout_salt_{section}`.
  - **Removed layouts:** `intro` (use `rich-text`; this completes
    ID-3599's fold) and `pricing` (SC-006: pricing becomes a Grain for
    WordPress recipe, ID-3955), with their resolvers
    (`salt_resolve_section_intro()`, `salt_resolve_section_pricing()`),
    views, `template-parts/components/card-pricing.php` and their fields
    (`featured`, `features`, `period`, `plans`, `price`). Removed layout
    slugs in the registry: `cta`, `intro`, `media_text`, `pricing`,
    `rich_text`.
  - **Removed files and functions:** `inc/fields/layouts/{hero,rich-text,
    call-to-action,media-text,features,stats,logos,gallery,process,faq,
    contact,locations}.php` (generated now) and
    `salt_section_wrapper_fields()` (the hand-written Advanced tab).
  - **Section settings move into one `settings` group** on every layout:
    `spacing`, `tone`, `width`, `anchorId` and `divider` are now
    `settings.spacing` … `settings.divider` (same values), joined by
    `settings.toneDark`, `settings.backgroundImage` and
    `settings.firstPageOnly`. Top-level settings fields are ignored.
  - **hero:** `cta_primary` and `cta_secondary` → the `buttons` list (at
    most two; each a contract link `{label, type, document, url, newTab}`
    and a `style`). New variant `stacked` (drawn as split until M4).
    full-bleed takes its image from `settings.backgroundImage.image`, not
    `image`. `mediaSide` and `alignment` are stored but not drawn yet.
  - **call-to-action:** `body` is rich text (was a textarea); each
    `buttons` row is `{link, style}` (was `label`, `url`, `style`), and
    `link.newTab` opens a new tab. View prop `body_text` → `body` (HTML).
  - **media-text:** each row's `cta` link → a `buttons` list (at most
    two).
  - **features:** item `text` is rich text (was a textarea); item `url`
    is a plain address (was an ACF link); item `icon` is a select of the
    theme's icons (was free text); new `intro`. View prop `body_text` →
    `text` (HTML).
  - **faq, locations:** a `query` group (`mode`, `categories`, `items`,
    `order`, `count`) replaces the hand-written query controls
    (`source`, `taxonomy_terms`, `manual`, `orderby`, `order`, `count`).
  - **contact:** no shape change (its names moved in ID-3599); only its
    pre-6.0.0 names leave the registry.
  - **Fields new in the contract, stored but not drawn until M4:**
    rich-text `eyebrow` and `alignment`; stats `intro` and item `icon`;
    logos `intro` and item `name`; gallery `heading`, `intro` and
    `label`; process `intro`, step `image` and step `cta`.
  - **Removed field slugs in the registry:** `align`, `cta_primary`,
    `cta_secondary`, `featured`, `features`, `form_intro`, `logos`,
    `media_side`, `number`, `period`, `plans`, `price`, `schema_toggle`,
    `section_anchor`, `section_background`, `section_container`,
    `section_divider`, `section_spacing`, `show_address`, `show_email`,
    `show_phone`.
  - **Visible changes:** the call-to-action body is drawn as
    `<div class="x-prose text-ink-muted">` around its paragraphs (was one
    `<p class="text-ink-muted">`); a features card body is
    `<div class="x-card-body x-prose text-ink-muted">` around its
    paragraphs (was `<p class="x-card-body …">`); call-to-action buttons
    can open in a new tab; media-text rows can show two buttons; links to
    a page resolve through its permalink; a section with `firstPageOnly`
    is hidden after page 1; intro and pricing rows no longer render.
  - **Child themes:** a view override of `intro.php` or `pricing.php` is
    never loaded; a view override of call-to-action or features must read
    `body` / `text` as HTML; code reading these fields with
    `get_sub_field()` must use the new names and shapes.
- **Child overrides of any `template-parts/sections/{slug}.php`** (all 20
  sections listed above) are now overrides of the VIEW. They receive
  `$args = { props, plan }`, not the loop context, and must draw from the
  props only. A copy written before the split still reads fields and calls
  `salt_section()`, so it renders a second band nested inside the
  parent's: the section heading drops to h2 and the page has no H1 (the
  fallback H1 is suppressed). A pre-split copy of a section that queues
  schema (contact, faq, locations, process, services, team, testimonials,
  work) queues its nodes a second time; for faq every question then
  appears twice in the page's FAQPage node. The parent logs
  `whole-section override` when this happens. Rewrite each such override
  from the parent's new view and re-verify it in the site's override
  manifest.
- **Code that loads a split section's template itself** (a child override
  of the pluggable `salt_render_section()`, or a direct
  `get_template_part()` / `load_template()` of the template) must render
  through `salt_render_resolved_section( $layout, $located, $context )`.
  Loaded directly, a view gets no props and emits PHP warnings: hero draws
  an empty broken hero, rich-text draws nothing, blog-teaser, cta, faq,
  gallery, pricing, scroll-carousel and work are a fatal error
  (`TypeError`), and the rest draw broken markup.
- **Resolvers cannot be replaced by a child theme.** They load from the
  parent theme only: a child file at `inc/sections/{slug}.php` is ignored,
  and a child that defines `salt_resolve_section_*()` makes that section
  render nothing (logged; an exception under `WP_DEBUG`). To own a
  section's logic, declare it `logic` in `salt-overrides.json` (Added,
  above).
- **Layouts and fields renamed to the salt-contract's section vocabulary
  (ID-3599; SC-001; FLEET07: MAJOR, next release 6.0.0).** No WordPress client site
  is live, so no database migration ships (the
  `docs/contracts/slug-migrations.json` entries first written for these
  renames are retired; see Changed). Field keys move with the names
  (keys stay snake_case, `field_salt_{scope}_{name}`).
  - Layout slugs are the contract ids: `cta` → `call-to-action` (layout key
    `layout_salt_call_to_action`, field keys `field_salt_call_to_action_*`,
    resolver `salt_resolve_section_call_to_action()`, files
    `inc/fields/layouts/`, `inc/sections/` and
    `template-parts/sections/call-to-action.php`); `rich_text` →
    `rich-text`; `media_text` → `media-text` (their files were already
    hyphenated). A resolver's function name comes from
    `salt_section_resolver_function()`, the one place a `-` becomes `_`.
  - Section settings, on every layout: `section_spacing` → `spacing`,
    `section_background` → `tone` (stored value `inverse` →
    `surface-inverse`; x-section's `background` arg is unchanged),
    `section_container` → `width`, `section_anchor` → `anchorId`,
    `section_divider` → `divider`.
  - Fields: rich-text `content` → `body`; call-to-action `text` → `body`
    (still a textarea); media-text rows `media_side` → `mediaSide`; contact
    `text` → `intro`, `show_phone`/`show_email`/`show_address` →
    `showPhone`/`showEmail`/`showAddress`, `form_intro` → `formIntro`;
    stats items `number` → `value`; logos `logos` → `items`; features items
    `link` → `url` (still an ACF link); faq and process `schema_toggle` →
    `structuredData`.
  - Markup: only the call-to-action band's printed slug changes:
    `section-cta` → `section-call-to-action` and `data-section="cta"` →
    `data-section="call-to-action"` (snapshot renamed
    `call-to-action-section-brand-tint.snap.html`). Analytics events that
    report the section (`section_view`, `cta_click`) now say
    `call-to-action` for it. No other `.snap.html` changed.
  - Child themes: rename a view override `template-parts/sections/cta.php`
    to `call-to-action.php` (and its path in `salt-overrides.json`); CSS or
    JS keyed to `.section-cta` or `[data-section="cta"]` must follow; code
    reading the renamed fields with `get_sub_field()` must use the new
    names.
  - Not renamed yet: the sections the contract folds from several layouts
    (collection-showcase: services, work, team, testimonials grid,
    blog_teaser; carousel: scroll_carousel, testimonials slider), `intro`
    (its fold into rich-text changes markup, an owner decision), `pricing`
    (the contract renames none of its fields), and every structural change
    the contract lists as owed (hero's and media-text's buttons lists, the
    faq and locations query group, the settings group).

## [5.0.0] — 2026-10-07

The repo rename to `lightlysaltedhq/salt-wordpress` and design-foundations
3.0.0. MAJOR per RELEASE-POLICY: child themes must change their `Template`
header, and `.snap.html` files changed (see BREAKING).

### Changed
- **The repo is now `lightlysaltedhq/salt-wordpress`** (transferred from
  `otigs/salt` on 07/10/2026; GitHub redirects the old URL). Git Updater
  requires a theme's folder to match its repo name, so a site must install
  the parent at `wp-content/themes/salt-wordpress`, and every child theme's
  `style.css` must say `Template: salt-wordpress`. `GitHub Theme URI`,
  `Theme URI` and `Update URI` point at the new repo, and the updater now
  sends the `SALT_UPDATER_TOKEN` deploy token only to requests for
  `lightlysaltedhq/salt-wordpress`, so a site's token must be scoped to the
  new repo. The child-theme starter is now `lightlysaltedhq/grain-wordpress`.
  Internal names are unchanged: the `salt_` prefix, the `salt` text domain,
  option keys and the `salt/v1` REST namespace. The rename changes no
  front-end markup. Existing installs: docs/RUNBOOK-ROLLBACK.md §D.
- **The deploy token goes to the exact repo only.** The updater matched any
  GitHub URL whose path contained `owner/repo`, so a sibling such as
  `salt-wordpress-legacy`, or another repo's path that embedded the pair,
  also received `SALT_UPDATER_TOKEN`. `salt_updater_is_repo_url()` now
  requires HTTPS and the pair as whole segments where each GitHub host puts
  them (`/repos/{owner}/{repo}` on the API, `/{owner}/{repo}` on github.com,
  codeload and raw), followed by `/` or the end, and refuses dot segments.
- **Admin notice when the theme sits in the wrong folder.** If the parent's
  folder is not `salt-wordpress`, users who can switch themes see a warning
  that Git Updater will not offer updates until it is renamed. A unit test
  now also holds `SALT_UPDATE_REPO` to the `GitHub Theme URI` and
  `Update URI` headers, and `SALT_VERSION` to `Version`.
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
    (re-review of lightlysaltedhq/salt-wordpress#8). The suite now refuses those utilities in
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
  this change; the fix is under Fixed below (lightlysaltedhq/salt-wordpress#4).

### Fixed
- **The focus ring on an "Inverse (dark)" section is visible** (review of
  lightlysaltedhq/salt-wordpress#8). `focus` is a brand step and `surface-inverse` the far end
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
- **Dark-mode primary buttons meet AA** (lightlysaltedhq/salt-wordpress#3). `--color-on-brand`
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
- **Dark mode with grayscale renders the brand tint dark** (lightlysaltedhq/salt-wordpress#4). A
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

### BREAKING
- **Theme folder and child `Template` header.** Install the parent at
  `wp-content/themes/salt-wordpress` and set `Template: salt-wordpress` in
  every child theme's `style.css`; a child still declaring `Template: salt`
  will not find its parent. Scope each site's `SALT_UPDATER_TOKEN` to
  `lightlysaltedhq/salt-wordpress`. Move an existing install in the order
  in docs/RUNBOOK-ROLLBACK.md §D (back up, rename the folder and the child's
  `Template` together, carry `theme_mods_salt`, update, confirm Git Updater).
- Markup structure changed (snapshot evidence, diff your override manifest):
  **x-button**, **booking**, **map**, the **contact** form's submit and the
  **display-preference** toggles drop `focus-visible:outline-2
  focus-visible:outline-offset-2` from their `class` attribute (17 snapshots).

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
