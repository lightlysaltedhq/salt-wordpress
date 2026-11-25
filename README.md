# salt-wordpress

Salt for WordPress: an opinionated WordPress **parent theme** for the
Lightly Salted fleet. Composable ACF section library, three-tier OKLCH design
tokens, Gutenberg disabled, PHP 8.2+. Customised per site via child themes;
the parent is **read-only on every site** and updated in place via Git Updater.

## Installing on a site

Git Updater requires the theme folder to match the repo name, so install the
parent at `wp-content/themes/salt-wordpress` (not `salt`). Child themes
declare `Template: salt-wordpress`; start one from
[`lightlysaltedhq/grain-wordpress`](https://github.com/lightlysaltedhq/grain-wordpress).
The Next.js counterpart is
[`lightlysaltedhq/salt-nextjs`](https://github.com/lightlysaltedhq/salt-nextjs).

## Governing documents

The Salt contract,
[`@lightlysaltedhq/salt-contract`](https://www.npmjs.com/package/@lightlysaltedhq/salt-contract)
at the exact version pinned in `package.json`, defines the sections, fields
and markup; the conformance tests hold this theme to it.
[docs/RELEASE-POLICY.md](docs/RELEASE-POLICY.md),
[docs/RUNBOOK-ROLLBACK.md](docs/RUNBOOK-ROLLBACK.md) and
[docs/DEPENDENCY-MATRIX.md](docs/DEPENDENCY-MATRIX.md) govern releases.

## Repo layout

- `template-parts/` — sections, components, content loops, layouts, navigation
- `inc/` — fields (ACF-as-code), query resolver, schema graph, services, setup
- `assets/src/` — Tailwind v4 CSS (`@theme` tokens) + JS sources
- `assets/dist/` — **committed** minified, content-hashed build + `manifest.json` (built once in core CI; client sites never build)
- `vendor/enshrined/` — **committed** pinned SVG sanitiser (the only shipped vendor lib)
- `docs/contracts/` — machine-checked contracts (slug registry, migrations)

## CI gates (every PR and tag)

- PHPCS (WordPress-Extra, `WordPress.Security.*` as **errors** — SEC08)
- ESLint + Stylelint
- PHPUnit unit suite (shared fleet logic)
- Rendered-markup snapshot gate (FLEET01)
- Design-contract conformance: `salt_derive_ramp()` reproduces the frozen vectors, `app.css` declares every required role, and the contract's contrast rules hold in every combination of dark, high contrast and grayscale, read from [`@lightlysaltedhq/design-foundations`](https://www.npmjs.com/package/@lightlysaltedhq/design-foundations) at the exact version pinned in `package.json`
- Artifact integrity: byte-identical rebuild of `assets/dist/` + re-vendor (FLEET08)
- Slug stability: no ACF layout/field slug removed, and no field storage path changed (a field moved into or out of a group, or a group or repeater renamed), in a non-major release without a migration entry (FLEET07)

## Dev setup

```sh
composer install   # PHP dev tools (PHPUnit, PHPCS) — never shipped
npm install        # asset toolchain + the design contract — never shipped
npm run build      # rebuild assets/dist (commit the output)
composer lint && composer test
```

## Licence

Lightly Salted's own code in this repository is released under the
[MIT licence](LICENSE). The theme also ships third-party code under its own
licences:

| Code | Where | Licence |
|---|---|---|
| [enshrined/svg-sanitize](https://github.com/darylldoyle/svg-sanitizer) | `vendor/enshrined/svg-sanitize/` | GPL-2.0-or-later ([its LICENSE](vendor/enshrined/svg-sanitize/LICENSE)) |
| [MapLibre GL JS](https://github.com/maplibre/maplibre-gl-js) | bundled in `assets/dist/maps/` | BSD-3-Clause |
| [PostHog JS](https://github.com/PostHog/posthog-js) | bundled in `assets/dist/analytics/` | Apache-2.0 and MIT |
| [Tailwind CSS](https://tailwindcss.com) | compiled into `assets/dist/app.*.css` | MIT |
| [`@lightlysaltedhq/salt-contract`](https://www.npmjs.com/package/@lightlysaltedhq/salt-contract) | `assets/dist/salt.*/salt.css` | MIT |

Because the theme as shipped includes a GPL-2.0-or-later library, anyone who
redistributes the whole theme must do so on terms compatible with the GPL;
MIT is compatible, so the theme's own files stay MIT and can be reused on
their own under MIT.
