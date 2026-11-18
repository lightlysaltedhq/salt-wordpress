# Real ACF Pro harness

Proves the Salt contract's field shape works natively on real ACF Pro. The field shape is the pinned
`@lightlysaltedhq/salt-contract` emitter's `acfSnapshot` output. On ACF Pro 6.8.11 and WordPress
7.1.4 (the latest release), the harness checks that it registers, saves, reads back, edits and
re-saves. The theme's hand-written ACF stand-in (`tests/shims/acf.php`) can then be checked against real ACF behaviour.
Dev only: nothing here ships, and the theme's own `composer install` and `npm ci` do not touch it.

## Run it

```sh
npm ci                                   # at the repository root: the pinned salt-contract
tests/real-acf/run.sh --php 8.2          # or --php 8.5, or --php /path/to/php
```

Options: `--no-browser` skips the editor checks, `--keep` keeps the site and database for
inspection (it prints how to drop them), `--offline` skips the latest-release check (below) and
reports it as `STALENESS NOT CHECKED`, `--report <file>` copies the report. The script exits 0
only when every check passes.

`--conformance` runs none of the checks below. It builds the same site, keeps it up and runs the
contract's conformance runner against it, so the view and page cases render on real WordPress and
ACF Pro (`npm run conformance:harness`; see `tests/contract-adapter/README.md`). After the install
phase, `php/view.php setup` sets the routes the cases' paths take, removes WordPress's sample
content, adds the menus and snapshots the database. Then the built-in server starts, and `npm run
conformance:contract` runs with the chosen PHP first on `PATH`. For each view or page case, the
adapter calls `php/view.php`, which restores that snapshot, seeds the case and requests its path.
`php/conformance.php` then fails the phase when any case did not render. As in every phase, any
entry in `debug.log` fails it too. The site's address is `http://salt-harness.test`, whatever port
the server takes, so the report is the same on every run. It does not need Playwright.

The phases share their seeding helpers in `php/seed.php`: the contract phase's section values to
ACF, and the singles phase's service, case-study and post documents, images, related services,
menus and HTTP GET. `php/view.php` uses the same ones.

It needs:

- PHP 8.2 or later with `mysqli`, Node 22 or later, Composer and `curl`.
- A MariaDB or MySQL account that may create and drop databases named `salt_harness_*`. Set
  `SALT_HARNESS_DB_HOST` (`host`, `host:port` or `localhost:/path/to.sock`; default the socket at
  `/tmp/mysql.sock`), `SALT_HARNESS_DB_USER` (default your login) and
  `SALT_HARNESS_DB_PASSWORD` (default empty).
- ACF Pro 6.8.11, in one of two forms:
  - the zip: `SALT_ACF_ZIP` (default `/Users/Shared/salt-harness/acf-pro-6.8.11.zip`). Its SHA-256
    is checked against `SALT_ACF_SHA256` (default the 6.8.11 zip's) before use. It is unzipped
    into the temporary site only.
  - a Composer copy: `composer install --working-dir=tests/real-acf/acf-composer` with
    `COMPOSER_AUTH` set to ACF's HTTP basic credentials (the licence key as username and an
    activated site URL, with `https://`, as password; see
    <https://www.advancedcustomfields.com/resources/installing-acf-pro-with-composer>). The
    harness uses `acf-composer/vendor/wpengine/advanced-custom-fields-pro` when it exists and
    no zip is named, or any directory you name in `SALT_ACF_DIR`.

  Never commit the zip, `auth.json` or the credentials. `.gitignore` here covers the first two.

On first use it installs its own locked dependencies: WordPress 7.1.4 (`roots/wordpress-no-content`
in `composer.json`, pinned exactly) and Playwright 1.65.1 with its headless Chromium
(`package.json`). It reinstalls WordPress when the installed copy differs from `composer.lock`.

Clients run the latest WordPress, and the theme must raise no deprecation on it. So before it
builds the site, each run asks `api.wordpress.org/core/version-check/1.7/` for the latest release
(`php/latest.php`) and fails, naming both versions, when the pinned one is older, or when the API
cannot be read. The pin keeps runs reproducible; this check keeps them from falling behind. To
move up, pin the new release in `composer.json`, run `composer update --working-dir=tests/real-acf`
and rerun on both PHP versions. Only `--offline` skips it, and the summary then says
`STALENESS NOT CHECKED (--offline)`. CI never passes `--offline`.

## What one run does

1. Builds a throwaway site in a temporary directory with a random `salt_harness_*` database. It
   has the salt theme active (symlinked to this checkout), ACF Pro activated, `WP_DEBUG_LOG` on
   and outbound HTTP blocked.
2. **The theme's own groups.** It loads every field group the theme registers through ACF. That
   is 24 groups. The page sections group holds the 12 layouts generated from the contract
   (`inc/fields/generated/sections.json`) and the 6 hand-written collection layouts. The 7
   collection groups, one per post type, are generated too (`inc/fields/generated/collections.json`).
   Any PHP notice, warning, deprecation or `salt_log` line in `debug.log` fails the phase. This
   applies to every phase.
3. **The contract's field shape.** `emit.mjs` writes `acfSnapshot()` from the theme's pinned
   contract with the theme's own options (`inc/fields/generated/sections.options.json`) and
   reports whether it is identical to the committed JSON. The theme registers that JSON through
   its own loader (`salt_section_layouts()` in `inc/acf.php`); no harness plugin is involved.
   `php/contract.php` then checks the following:
   - the theme registers the emitted layouts first and in order, with no hand-written layout for a
     generated section, and every field with its name, type and `salt` provenance kept;
   - one page is seeded with one row per contract fixture case through `update_field()`. That is
     every `fixtures/<section>/*.json` whose section is a page-sections layout. Named images,
     galleries and documents become real attachments and posts;
   - the values are read back with the theme's idioms (`have_rows( 'sections' )`,
     `get_sub_field()`, nested `have_rows( 'settings' )`) and compared leaf by leaf. The result
     is reported per case;
   - in the database, each saved value is at its contract storage path with its `_key` reference.
     A probe page sets the paths named in M3, for example `sections_0_settings_spacing`,
     `sections_0_settings_backgroundImage_scrimStrength` and a faq row's `sections_1_query_mode`,
     `_categories`, `_items`, `_order` and `_count`. Meta keys must not differ only in case, either in the stored
     rows or anywhere in the emitted shape.
4. **The theme renders it.** `php/render.php` renders the fixtures page through the theme's page
   loop, so every resolver reads real `get_sub_field()` values. Every generated section with
   content must draw a band (faq and locations have no posts to query there), button links must
   resolve (external with a new tab, internal to its document), `settings.anchorId` must reach the
   band, and the page must have one H1.
5. **The collection fields.** `php/collections.php` checks that the theme registers the 7
   collection groups verbatim from `inc/fields/generated/collections.json`, each located on its
   post type (services on `service`, case studies on `work`, testimonials, posts, team on
   `team_member`, faqs and locations). It makes a post of each type and seeds a value for every
   generated field through `update_field()`, including a post's layout with a row of every section
   it offers. Each value is read back with `get_field()` and compared, and every meta path must
   carry its field key. The places WordPress keeps natively (`salt.native`: the title, the
   excerpt, the body and the featured image) are set the WordPress way and read back through the
   theme's own readers. A post's `author` and `layout` must be drawn in its group and
   `primaryTopic` only in the Topics box; posts must have no classic editor and no Tags box, and
   their categories must be labelled Topics. Every topic, posts' categories included, must be
   stored flat, whatever parent is asked for on insert or update.
6. **The single templates over HTTP.** PHP's built-in server starts here (with or without
   `--no-browser`). `php/singles.php` seeds one document each from the pinned contract's
   `fixtures/service/full.json`, `fixtures/case-study/full.json` and `fixtures/post/full.json`:
   each `document` value goes to its native place or, through `update_field()`, to its generated
   field; named media become attachments with the fixture's alt text, and related or listed
   services become services. The post's body is its `layout`'s first row (prose), its author a
   team member published under the author's href slug (`seed.php`, `salt_harness_team_member()`),
   and its category its `primaryTopic`. Fields are seeded by key: `update_field()` looks a name up
   loosely, and the page gallery's `layout` select shares the post's `layout` name. It requests
   `single-service.php`, `single-work.php` and `single-post.php` and checks each answers 200 and
   draws every seeded value the template draws: for the post, the primary topic as the badge and
   the team member's name, role and photo.
   It also checks the service intro runs no shortcode or embed, and that a testimonial rated 4.5
   draws 90% of five stars on a page. Known fixture mismatch, pending the contract's EP-70
   correction: rc.7's service intro holds an `<h3>` its own list does not allow, so the intro's
   heading is stripped to text; the phase checks the intro's two paragraphs and that no `<h2>` or
   `<h3>` comes from it, not the heading. This is a smoke render, not conformance: the view cases'
   markup is a later unit.
7. **The editor, in headless Chromium** against the same server (`browser.mjs`):
   - the fixtures page's classic edit screen renders every row and layout with no console errors;
   - the faq section's collection query mode shows categories or items, and order and count unless
     the mode is manual;
   - an empty required field (a features item's title) blocks publishing, and filling it lets the
     page publish;
   - re-saving the fixtures page from the browser changes nothing unexplained. Every meta row is
     diffed before and after, and each change is put in a named class or failed;
   - each rich-text editor offers only its field's contract list;
   - the Topics box and the topic screens offer no parent; the service editor has no Add Media
     button and Text tab buttons for its list alone; a summary of 301 two-byte characters is
     refused with the editor message on a service and a post, and 300 is accepted; a post's editor
     is the section builder with the credited author, no classic editor and no Tags box, and its
     Topics box has no parent and holds the primary topic;
   - a url that is not absolute http, https, mailto or tel is refused on save: a service's external
     cta refuses `/contact`, `#pricing`, `//x.example`, `javascript:` and `ftp:` and takes https and
     mailto, and a case study's `externalUrl` refuses a path and takes https;
   - on each collection's edit screen, a title of 121 two-byte characters is refused with the
     editor message and 120 is not.
8. Prints a summary, drops the database and removes the temporary directory, whether the run
   passed, failed or was interrupted.

## Reading the re-save diff

These changes are expected and reported, not failed:

- Fields a fixture leaves unset are stored by the form at their default or empty, with their
  `_key` rows.
- An unchecked true/false is stored as `""` by `update_field( false )` but as `"0"` by the form.
  Both read back as `false`.
- In wysiwyg fields, the editor saves paragraphs as line breaks. The check is that
  `acf_the_content` renders the before and after values identically.
- In textareas, `\n` becomes `\r\n`, as browsers submit it.

## Known limits on a real site

- **`max_input_vars`.** The fixtures page posts about 1,800 variables. PHP's default of 1000 makes
  PHP drop the rest, and ACF then saves a truncated sections list. The harness server raises the
  limit and reports the count. A site with long section pages needs its host's limit above the
  page's count.
- **Save time.** Saving that page takes about 20–40 s on the built-in server. That is beyond PHP's
  default 30 s `max_execution_time` there, so the harness raises it too.
- **Chromium's unload reports.** Chromium reports each `unload` listener in TinyMCE as a
  permissions-policy console error. These come from WordPress core, are counted by script in the
  report, and are not treated as errors.

## What it does not prove

- **Rendered markup.** The render phase checks the resolvers read the shape and draw; it does
  not compare markup with the contract's fixtures (that is M4). Collection-showcase, carousel,
  tabs and listing are not registered yet (unit 8 and later), so their fixtures are not seeded.
- **Picker filling.** The multi-taxonomy category select (`choices` filled by the site) and
  `salt.modeRequires` server validation are not built yet. They are only stored and read back.
- **Licensing.** It does not cover ACF Pro licence activation or updates. Pro field types work on
  this unlicensed local site.
- **Other environments.** It covers ACF Pro 6.8.11 and WordPress 7.1.4 only, and does not check
  the block editor (the theme turns it off) or browsers other than Chromium.
