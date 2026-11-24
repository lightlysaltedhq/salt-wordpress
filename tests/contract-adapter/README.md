# WordPress contract conformance

Run the release gate against the pinned `@lightlysaltedhq/salt-contract` with a disposable real-ACF installation. Install the [harness prerequisites](../real-acf/README.md) and the root Composer and npm lockfiles first.

```sh
npm run conformance:harness -- --php 8.2
npm run conformance:harness -- --php 8.5
```

On Studio, add `--offline` to skip the WordPress release freshness lookup. This does not skip any fixture. The two runs must produce byte-identical `baseline/conformance.{md,json}`. Check and commit intentional baseline changes alongside the theme change; do not update expected HTML to excuse a mismatch.

The harness starts WordPress, sets `SALT_HARNESS_WP` and `SALT_HARNESS_URL`, and runs `npm run conformance:contract` with the chosen PHP first on `PATH`. The runner checks normalised fixture HTML, class names, inline styles, emitted field parity and the bytes of the contract CSS in `assets/dist`. It runs without `--partial`; only `tabs`, `listing` and `pricing` are declared not shipped. The contract runner's non-zero exit fails the gate, and the harness checks the site's PHP diagnostics. The report names both the contract and implementation versions.

## Diagnose a failed case

The runner starts `adapter.php` once per fixture, passing the JSON on stdin and reading HTML from stdout. For a section or chrome case, run the adapter directly, for example:

```sh
php tests/contract-adapter/adapter.php < node_modules/@lightlysaltedhq/salt-contract/fixtures/hero/split-image-left.json
```

Section values enter the same shims as the snapshot suite in their emitted ACF shape, including attachment IDs, relationship IDs and `settings`. They go through the theme's resolver and view, not a copy of the markup. Native post fields, users, terms and attachment candidates are seeded from the fixture records. The chrome uses the fixture's site settings and menus.

Views and whole pages use `tests/real-acf/php/view.php`: it restores the disposable database snapshot for each case, seeds the route using `php/seed.php`, and requests its native WordPress URL. It compares `main#main` for a view and the whole document for a page. The site uses a fixed host and deterministic dates, independent of its local listening port. Without the harness, `npm run conformance:contract` alone must fail: its page and view cases cannot render. Its diagnostic report is in `report/`, never the committed baseline.
