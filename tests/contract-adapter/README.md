# The Salt contract's fixture adapter

`adapter.php` is Salt for WordPress's command adapter for the conformance runner that ships in
the pinned `@lightlysaltedhq/salt-contract` (`conformance.mjs`, the `salt-conformance` bin). The
runner sends every fixture case in the package to the adapter and compares the HTML the theme
draws with the case's expected HTML. EP-72 M3 unit 5, ID-3626. Dev only: nothing here ships.

`baseline/` holds the report of a harness-backed run (below), `conformance.md` and
`conformance.json`. It is M4's worklist: the markup moves to the contract there, and each
section's, view's and page's row should turn green.

**The baseline records only the first difference per case**, so it does not show the seeding of a
view or page case is right. Today every view fails first on `main`'s `tabindex` and every page on
the head's `noscript`, before any of the case's content is compared. What the harness seeds has
been read by hand, not compared. For example, `search/results` lists three results, but WordPress's
own search for `hedge` returns only those whose title or text holds the word, so its third result
("Boundaries and screening") would not be drawn. Such gaps surface only once the wrapper matches,
in M4.

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
  --out tests/contract-adapter/report
```

On its own it renders the sections and the chrome; the views and the page fail with exit 4.
To render those too, run it with the real-ACF harness's WordPress up:

```sh
npm run conformance:harness                  # PHP first on PATH
npm run conformance:harness -- --php 8.5     # or --php 8.2, or --php /path/to/php
```

That is `tests/real-acf/run.sh --conformance`: it builds the harness's throwaway site with ACF
Pro, keeps it up, runs `npm run conformance:contract` with that PHP first on `PATH`, checks the
report and removes the site. It needs what the harness needs (`tests/real-acf/README.md`: ACF Pro,
a MariaDB or MySQL account, Composer), but not Playwright. It fails when any case fails for a
reason other than its markup (the adapter exited non-zero: 4, 1 or 70), and when any PHP notice,
warning, deprecation, error or `salt_log` line reaches the site's `debug.log`. **This is the run
that writes the committed baseline.** It copies its report to `baseline/` only when every case
rendered. After a contract re-pin, or when the theme's markup changes, run it on PHP 8.2 and 8.5,
check the two reports are the same, and commit `baseline/`.

The plain script prints the Markdown report and writes it to `report/` (git-ignored), never to
`baseline/`, so a run without the harness cannot put the view and page rows back to exit 4.
Regenerate the baseline with `npm run conformance:harness`, not this script. **It always exits 1**: the run is partial
(below), and the runner exits 1 on every partial run by design (SC-017). Exit 2 is a usage error.
Read the report, not the exit status.
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
case JSON on stdin and writes HTML to stdout. It renders sections and the chrome without
WordPress, through the same bootstrap and shims as the unit and snapshot suites
(`tests/bootstrap.php`, `tests/wp-shims.php`, `tests/shims/*.php`). Views and the page go to the
real-ACF harness's WordPress (below).

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
- **The section for each case.** It is the theme's slug for the contract id; `collection-showcase`
  and `carousel` are layouts of their own since unit 8.
  `tabs`, `listing` and `pricing` have no theme section: the run declares them `--not-shipped`.
  Given one anyway, the adapter exits 3 with "not shipped" rather than crash.
- **The case's other data:**
  - `media` becomes attachments in the media shim. The src is the upload at its intrinsic width.
    The srcset x-image draws is WordPress core's default sub-sizes below that width plus the
    intrinsic width. The sections drawn to the contract (collection-showcase, carousel) read the
    theme's `salt-<width>` sub-sizes instead, one per contract candidate below the upload's width,
    seeded as attachment metadata. Each URL comes from the record's `{width}` template, as an
    upload URL filter would give it.
  - `documents` become pages whose permalink is the case's `href`.
  - `collections` become posts of the theme's post types, in the order given. A field WordPress
    keeps natively (named under `salt.native` in `inc/fields/generated/collections.json`) goes to
    its place: `title` is the post's title, `text` its body and excerpt, and `image` its featured
    image. A resolved card key is stored where the collection's approved `display` reads it from
    (team's `meta` is `role`, a testimonial's `text` is `quote`). An item's `tags` or `categories`
    become terms in the source's taxonomy. Every other key becomes an ACF field on the post under
    the contract's name.
  - `site.collectionIndexes`, `site.labels` and `site.arrow` feed the theme's three seams pending
    the founder's ruling (`salt_collection_index_url`, `salt_contract_labels`,
    `salt_contract_arrow` filters), so conformance is proved against the contract whatever the
    live sources become. `context.track` sets the section's count for `data-track`.
  - `context` gives the band's place on the page: its index, whether it is the primary heading,
    a heading claimed earlier, and the collapse with the band above.
- **A chrome case** (`site-header`, `site-footer`). The case's `site` goes into the theme's
  options and bloginfo: name, organisation, logos, logo height, sticky header, header call to
  action and footer text. Then the template part is rendered. Menus are not seeded: the theme
  draws them with `wp_nav_menu()` and its walker, which the shims do not run.
- **A view or the page** (`post`, `service`, `case-study`, `archive`, `search`, `not-found`,
  `page`). These need WordPress's loop and document head, so the harness's running WordPress
  renders them. When `SALT_HARNESS_WP` and `SALT_HARNESS_URL` name a harness site (`run.sh
  --conformance` sets them), the adapter hands the case to `tests/real-acf/php/view.php` and
  passes on its output and exit status. Without them, it exits 4 and says so, as before.

  `view.php` takes one case at a time, since the cases share one site. It puts the database back
  as the harness left it, so every case starts from the same site and gets the same IDs in any
  order. It seeds the case with the helpers the harness's contract and singles phases use
  (`tests/real-acf/php/seed.php`), then requests the case's `context.path` from the site and
  writes `main#main`, or the whole document for the page. It maps the case like this:
  - `media` become attachments with the record's size and alt text. `documents` become pages
    whose slug is the href's last segment.
  - `site`: the name and time zone go into WordPress's options. The organisation, logos, logo
    height, sticky header, header call to action and footer text go into the theme's options, as
    for a chrome case. The menus are the harness's own (one Home link each), not the case's.
  - The page is a page with the document's title at the case's path (the front page for `/`),
    with its `document.sections` as rows of `sections`.
  - A post, service or case study is seeded as the singles phase seeds it, at its path. A post's
    `body` is the first row of its `layout` field (a rich-text section that is prose alone), its
    `document.sections` the rows after it, its author a team member and its category its primary
    topic. Its `adjacent` newer and older posts
    are dated a day after and before it. The case's `collections` become posts of their types.
  - An archive's topic becomes a category with its description. Its author becomes a published
    team member whose slug is the href's and whose name is the title, with the strapline as its
    role, the biography as its bio and the photo as its featured image (the theme's provisional
    mapping, ID-3952). `route.cards` become posts in the topic or crediting the team member.
  - Each search result becomes a document of its kind (`Post`, `Service`, `Page`). The search is
    requested at WordPress's own address, `/?s=<query>`, since routing stays native (SC-003); the
    theme registers no `/search/` route. A not-found case seeds nothing and expects a 404.
  - The routes the paths take are set once: posts at `/journal/<slug>/`, topics at
    `/journal/topic/<slug>/`, authors at `/journal/author/<slug>/`, services at
    `/services/<slug>/` and case studies at `/work/<slug>/`.

  A site answering another status (200, or 404 for not-found) fails the case with exit 1. Any
  PHP diagnostic that reaches `debug.log` while the case is seeded or drawn fails it with exit 70.
  The site's address is fixed at `http://salt-harness.test`, while the server listens on a free
  local port, so the report is the same on every run. Dates the case does not give (search
  results) are fixed for the same reason.

  What the theme cannot hold yet, it does not draw, and the report shows that as a markup
  difference. The case's `labels` are the theme's own strings. A case study's
  `site.caseStudy` layout has no option in the theme yet. Pagination draws only what WordPress
  holds: a case gives one page of cards or results, not the documents behind its `total` or
  `count`.

**No PHP diagnostic can pass.** Every error, warning, notice and deprecation, and any throw or
fatal, fails the case with exit 70. The diagnostic, with its file path from the theme root, goes
to stderr, which the report quotes. The baseline has none. Theme `salt_log()` lines go to stderr
as `salt_log: …` and do not fail a case.

## What each check means, and which ran

The runner holds every shipped section to four checks (contract README, "Conformance"). A
section passes only when all four ran and passed.

| Check | What it proves | In this run |
| --- | --- | --- |
| Fixtures | The adapter's HTML for each case equals the case's expected HTML once both are normalised. A failure names the section, the case and the first differing node, by path. | **Ran** for every section, the chrome, the views and the page. With the harness up, every case renders and differs only in its markup. |
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
`… against @lightlysaltedhq/salt-contract 1.0.0-rc.7`. `conformance.json` records it under
`contract` (RELEASE-POLICY §5: a conformance claim without that version is not a claim). The
implementation version is the theme's `style.css` `Version:`.

The runner reports only the first difference per case. Where the theme's section wrapper differs
from the contract's, which is true of most sections today, that is the difference shown. A view's
own differences surface only once the wrapper matches.
