# The Salt contract's fixture adapter

`adapter.php` is Salt for WordPress's command adapter for the conformance runner that ships in
the pinned `@lightlysaltedhq/salt-contract` (`conformance.mjs`, the `salt-conformance` bin). The
runner sends every fixture case in the package to the adapter and compares the HTML the theme
draws with the case's expected HTML. EP-72 M3 unit 5, ID-3626. Dev only: nothing here ships.

`baseline/` holds the first run's report, `conformance.md` and `conformance.json`. It is M4's
worklist: the markup moves to the contract there, and each section's row should turn green.

## Run it

```sh
npm ci --ignore-scripts      # the pinned salt-contract
composer install             # the shims' PHPUnit autoloader
npm run conformance:contract
```

The script runs:

```sh
salt-conformance --platform wordpress \
  --adapter 'php tests/contract-adapter/adapter.php' \
  --not-shipped tabs,listing,pricing --partial \
  --acf-snapshot inc/fields/generated/sections.json \
  --fields-options inc/fields/generated/sections.options.json \
  --styles assets/dist/salt.*/salt.css \
  --implementation-version <Version: in style.css> \
  --out tests/contract-adapter/baseline
```

It prints the Markdown report and rewrites `baseline/`. **It always exits 1**: the run is partial
(below), and the runner exits 1 on every partial run by design (SC-017). Exit 2 is a usage error.
Read the report, not the exit status. Commit `baseline/` again when the theme's markup changes.
The run is deterministic: the same theme and contract give the same files byte for byte.

`php` is whichever PHP is first on `PATH`. To run another, put it first:
`PATH=/opt/homebrew/opt/php@8.2/bin:$PATH npm run conformance:contract`.

One case by hand:

```sh
php tests/contract-adapter/adapter.php \
  < node_modules/@lightlysaltedhq/salt-contract/fixtures/hero/split-image-left.json
```

## What the adapter does

It follows the contract README's "The adapter protocol". It is one process per case. It reads the
case JSON on stdin and writes HTML to stdout. It renders without WordPress, through the same
bootstrap and shims as the unit and snapshot suites (`tests/bootstrap.php`, `tests/wp-shims.php`,
`tests/shims/*.php`).

- **A section case.** The case's `values` are seeded into the ACF shims in the contract's stored
  shape. This is what `get_sub_field()` returns for the emitted field group (`acfSnapshot`, the
  shape unit 3 registers):
  - every name is as the contract writes it, settings included under `settings`;
  - a field the case leaves out takes its contract default;
  - an image is an attachment ID, and a gallery is a list of them;
  - a link's `document` and a collection query's `items` are post IDs.

  Nothing is renamed to the theme's hand-written field names. Where the theme still reads its own
  names, the gap shows in the report, which is the point. The row is rendered through
  `salt_render_section()`, so it goes through the theme's resolver (`inc/sections/<slug>.php`)
  and view (`template-parts/sections/<slug>.php`).
- **The section for each case.** It is the theme's slug for the contract id. `collection-showcase`
  is drawn by the former section for its `source`: services, work, team, testimonials or
  blog-teaser. `carousel` is drawn by testimonials for that source and scroll-carousel otherwise.
  `tabs`, `listing` and `pricing` have no theme section: the run declares them `--not-shipped`.
  Given one anyway, the adapter exits 3 with "not shipped" rather than crash.
- **The case's other data:**
  - `media` becomes attachments in the media shim. The src is the upload at its intrinsic width.
    The srcset is WordPress core's default sub-sizes below that width plus the intrinsic width,
    since the theme registers no sizes of its own. Each URL comes from the record's `{width}`
    template, as an upload URL filter would give it.
  - `documents` become pages whose permalink is the case's `href`.
  - `collections` become posts of the theme's post types, in the order given. `title` and `text`
    become the post's title and content, and every other key becomes an ACF field on the post.
  - `context` gives the band's place on the page: its index, whether it is the primary heading,
    a heading claimed earlier, and the collapse with the band above.
- **A chrome case** (`site-header`, `site-footer`). The case's `site` goes into the theme's
  options and bloginfo: name, organisation, logos, logo height, sticky header, header call to
  action and footer text. Then the template part is rendered. Menus are not seeded: the theme
  draws them with `wp_nav_menu()` and its walker, which the shims do not run.
- **A view or the page** (`post`, `service`, `case-study`, `archive`, `search`, `not-found`,
  `page`). These are not rendered yet: they need WordPress's loop and document head. The adapter
  exits 4 and says so, and the report lists every such case as an adapter failure. A WordPress
  render path for them is M4 work.

**No PHP diagnostic can pass.** Every error, warning, notice and deprecation, and any throw or
fatal, fails the case with exit 70. The diagnostic, with its file path from the theme root, goes
to stderr, which the report quotes. The baseline has none. Theme `salt_log()` lines go to stderr
as `salt_log: …` and do not fail a case.

## What each check means, and which ran

The runner holds every shipped section to four checks (contract README, "Conformance"). A
section passes only when all four ran and passed.

| Check | What it proves | In this run |
| --- | --- | --- |
| Fixtures | The adapter's HTML for each case equals the case's expected HTML once both are normalised. A failure names the section, the case and the first differing node, by path. | **Ran** for every section, the chrome, the views and the page. |
| Classes | Every `salt-*` class in the output is one the contract's markup uses. | **Ran**, but it passes trivially: the theme draws `x-*` classes and no `salt-*` class yet. It starts to bite in M4. |
| Field parity | The theme's committed ACF field snapshot is the one the contract's emitter generates. | **Ran** against `inc/fields/generated/sections.json`. It passes for the twelve generated sections and fails for collection-showcase and carousel, which the snapshot does not contain until unit 8 builds them. |
| Stylesheet pin | The CSS the theme serves is byte for byte the contract's `styles/salt.css`. | **Ran** against the served copy, `assets/dist/salt.<hash>/salt.css`, and passes. |

The chrome, views and page have no fields, so the runner holds them to fixtures and classes only.

## Why the run is partial

A conforming run needs all four checks for every section the theme ships (SC-017). Three things
are missing today, so the script passes `--partial` and the report says "Partial, not conforming".

- **Most fixtures fail.** The theme still draws its own `x-*` markup; M4 moves each section's
  view to the contract's markup.
- **collection-showcase and carousel fail field parity.** They are still drawn by the six
  hand-written legacy layouts, which are not in the generated snapshot. Unit 8 replaces them.
- **`tabs`, `listing` and `pricing` are not shipped.** The contract records `tabs` and `listing`
  as owed by WordPress (`contract/sections.json`). The theme retired its pricing layout in 6.0.0;
  pricing becomes a grain-wordpress recipe (ID-3955). Only a partial run may leave a section out.

## The report

The Markdown report's first line names the contract release it ran against, for example
`… against @lightlysaltedhq/salt-contract 1.0.0-rc.6`. `conformance.json` records it under
`contract` (RELEASE-POLICY §5: a conformance claim without that version is not a claim). The
implementation version is the theme's `style.css` `Version:`.

The runner reports only the first difference per case. Where the theme's section wrapper differs
from the contract's, which is true of most sections today, that is the difference shown. A view's
own differences surface only once the wrapper matches.
