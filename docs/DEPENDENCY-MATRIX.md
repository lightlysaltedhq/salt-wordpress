# Supported dependency matrix (DEP05)

Declared per parent release; validated on the canary before promotion
("theme × ACF × WP combination verified" checklist line). Background
auto-update of ACF Pro and WP core is **disabled on fleet sites** — they
move through the same pinned/canary discipline as the theme.

| salt release | WordPress core | ACF Pro | PHP | Updater |
|---|---|---|---|---|
| v0.2.x (foundation) | 6.5 – 6.8 | ≥ 6.3.0 (pinned min, `SALT_MIN_ACF_VERSION`) | 8.2+ | Git Updater ≥ 12 or git-deploy |

## Vendored runtime libraries (SEC04/DEP02)

| Library | Version | Pin location | CVE owner |
|---|---|---|---|
| enshrined/svg-sanitize | 0.22.0 | composer.lock (committed) + vendor/enshrined (committed, CI byte-verified — FLEET08) | Parent-theme maintainer: watches GitHub security advisories + `composer audit` in every CI run; a disclosed bypass triggers a fast-tracked patch release (auto-update-eligible, RELEASE-POLICY.md) |

## Single points of failure

- **ACF Pro** (DEP01): guarded — absence shows a blocking admin notice and
  safe fallbacks, never a fatal (`inc/acf.php`). Licensing: agency developer
  licence covers the fleet; record the licence account in the agency vault.
  The ACF→Secure Custom Fields fork situation should be re-verified before
  each major release (this matrix is where the verdict is recorded).
- **Node/npm toolchain**: dev-only, never on client sites. Pinned via
  package-lock.json + .nvmrc; reproducibility enforced by FLEET08.
