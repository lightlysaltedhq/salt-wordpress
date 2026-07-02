# Contributing to salt

salt is **shared infrastructure**: a defect in the parent fans out to every
client site on the next update. The gates below exist for that reason —
none are optional.

## Dev setup

```sh
composer install    # PHP dev tools (PHPUnit, PHPCS) — never shipped
npm install         # asset toolchain (Tailwind v4, esbuild) — never shipped
```

## The local loop

```sh
composer lint                 # PHPCS: WordPress-Extra + security sniffs as ERRORS
composer test                 # PHPUnit (unit + snapshot suites)
npm run lint:js && npm run lint:css
npm run build                 # rebuild assets/dist — COMMIT the output
php bin/extract-slugs.php --write   # after any field/layout change — COMMIT the registry
```

Snapshots (FLEET01): a failing snapshot means you changed shared markup.
If intended, decide the semver impact (markup structure = MAJOR,
docs/RELEASE-POLICY.md), regenerate deliberately, and commit the diff:

```sh
SALT_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --testsuite snapshot
```

## Adding a section

Follow the recipe in [SECTIONS.md](SECTIONS.md) — the Rich Text section is
the copyable exemplar. A new section touches ONLY its layout file, its
partial, its tests, and the SECTIONS.md registry row.

## Branching & releases

- Work on branches; PR into `main`. CI must be green (all four jobs).
- Releases: bump `style.css` + `SALT_VERSION`, update CHANGELOG.md
  (BREAKING section when applicable), tag `vX.Y.Z`, promote per
  docs/RELEASE-POLICY.md (canary first — never straight to the fleet).
- Field/layout renames additionally need a
  `docs/contracts/slug-migrations.json` entry (CI enforces, FLEET07).

## Non-negotiables

- Escape on output; WYSIWYG only through `salt_sanitize_html()`.
- Components consume semantic role tokens only.
- No third-party asset may load (PRIV01 — fail-closed consent stub).
- Never edit `vendor/enshrined/**` or `assets/dist/**` by hand (CI
  byte-compares both against source, FLEET08).
