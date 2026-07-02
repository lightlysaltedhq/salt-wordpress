# salt

Private, opinionated WordPress **parent theme** for the Lightly Salted fleet.
Composable ACF section library, three-tier OKLCH design tokens, Gutenberg
disabled, PHP 8.2+. Customised per site via child themes; the parent is
**read-only on every site** and updated in place via Git Updater.

## Governing documents

| Doc | Role |
|---|---|
| [docs/lightly-salted-starter-spec-v3.md](docs/lightly-salted-starter-spec-v3.md) | Design, output & authoring intent |
| [docs/SPEC-REVIEW.md](docs/SPEC-REVIEW.md) | Corrected requirements (3 Blockers + 37 Majors) — **wins on conflict** |
| [docs/BUILD-BRIEF.md](docs/BUILD-BRIEF.md) | The build contract: phases, gates, locked decisions, traceability |

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
- Artifact integrity: byte-identical rebuild of `assets/dist/` + re-vendor (FLEET08)
- Slug stability: no ACF layout/field slug removed in a non-major release without a migration entry (FLEET07)

## Dev setup

```sh
composer install   # PHP dev tools (PHPUnit, PHPCS) — never shipped
npm install        # asset toolchain — never shipped
npm run build      # rebuild assets/dist (commit the output)
composer lint && composer test
```
