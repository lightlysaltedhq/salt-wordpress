# Real ACF Pro harness

Proves the Salt contract's field shape works natively on real ACF Pro. The field shape is the pinned
`@lightlysaltedhq/salt-contract` emitter's `acfSnapshot` output. On ACF Pro 6.8.11 and WordPress
6.8, the harness checks that it registers, saves, reads back, edits and re-saves. The theme's
hand-written ACF stand-in (`tests/shims/acf.php`) can then be checked against real ACF behaviour.
Dev only: nothing here ships, and the theme's own `composer install` and `npm ci` do not touch it.

## Run it

```sh
npm ci                                   # at the repository root: the pinned salt-contract
tests/real-acf/run.sh --php 8.2          # or --php 8.5, or --php /path/to/php
```

Options: `--no-browser` skips the editor checks, `--keep` keeps the site and database for
inspection (it prints how to drop them), `--report <file>` copies the report. The script exits 0
only when every check passes.

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

On first use it installs its own locked dependencies: WordPress 6.8.12 (`roots/wordpress-no-content`
in `composer.json`) and Playwright 1.65.1 with its headless Chromium (`package.json`).

## What one run does

1. Builds a throwaway site in a temporary directory with a random `salt_harness_*` database. It
   has the salt theme active (symlinked to this checkout), ACF Pro activated, `WP_DEBUG_LOG` on
   and outbound HTTP blocked.
2. **The theme's own groups.** With no harness plugin installed, it loads every field group the
   theme registers today through ACF. That is 23 groups, including the 20 hand-written section
   layouts. Any PHP notice, warning, deprecation or `salt_log` line in `debug.log` fails the
   phase. This applies to every phase.
3. **The contract's field shape.** `emit.mjs` writes `acfSnapshot()` from the theme's pinned
   contract, passing the theme's bundled icons. Then `mu-plugins/salt-harness-emitted-sections.php`
   unhooks the theme's sections group and registers the emitted JSON verbatim. `php/contract.php`
   then checks the following:
   - every layout and field is registered, with its name, type and `salt` provenance kept;
   - one page is seeded with one row per contract fixture case through `update_field()`. That is
     every `fixtures/<section>/*.json` whose section is a page-sections layout. Named images,
     galleries and documents become real attachments and posts;
   - the values are read back with the theme's idioms (`have_rows( 'sections' )`,
     `get_sub_field()`, nested `have_rows( 'settings' )`) and compared leaf by leaf. The result
     is reported per case;
   - in the database, each saved value is at its contract storage path with its `_key` reference.
     A probe page sets the paths named in M3, for example `sections_0_settings_spacing`,
     `sections_0_settings_backgroundImage_scrimStrength` and `sections_1_query_mode`, `_categories`,
     `_items`, `_order` and `_count`. Meta keys must not differ only in case, either in the stored
     rows or anywhere in the emitted shape.
4. **The editor, in headless Chromium** against PHP's built-in server (`browser.mjs`):
   - the fixtures page's classic edit screen renders every row and layout with no console errors;
   - a collection query's mode shows categories or items, and a source without categories hides
     them;
   - an empty required field (a collection showcase's source) blocks publishing, and filling it
     lets the page publish;
   - re-saving the fixtures page from the browser changes nothing unexplained. Every meta row is
     diffed before and after, and each change is put in a named class or failed.
5. Prints a summary, drops the database and removes the temporary directory, whether the run
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

- **Rendering.** It does not check the theme's rendering of these values: the theme does not yet
  load the emitted JSON (EP-72 M3 unit 3), and its resolvers still read the hand-written names.
- **Picker filling.** The multi-taxonomy category select (`choices` filled by the site) and
  `salt.modeRequires` server validation are not built yet. They are only stored and read back.
- **Licensing.** It does not cover ACF Pro licence activation or updates. Pro field types work on
  this unlicensed local site.
- **Other environments.** It covers ACF Pro 6.8.11 and WordPress 6.8 only, and does not check
  the block editor (the theme turns it off) or browsers other than Chromium.

## When the theme registers the JSON itself (unit 3)

Delete `mu-plugins/salt-harness-emitted-sections.php`. The contract phase then checks whatever the
theme registers under `group_salt_sections` against the same emitted JSON. If unit 3 also removes
`inc/fields/layouts/`, update the hand-written layout count check in `php/theme-groups.php`.
`site/salt-harness-offline.php` stays: it only stops wp-admin's update checks from reaching the
network.
