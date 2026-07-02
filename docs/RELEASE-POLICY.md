# Release policy: semver, changelog, auto-update allow-list

## Semver contract (§4)

**MAJOR** — any of:
- A component **args signature** change (rename/retype/remove; adding an
  optional arg with a default is MINOR).
- A **markup structure** change in any shared component or section part —
  mechanically detected: a changed `.snap.html` file (FLEET01). The snapshot
  diff in the PR is the semver evidence.
- An ACF **field name or FC layout slug** rename/removal (FLEET07 —
  mechanically enforced by `bin/check-slug-stability.php`; requires an entry
  in `docs/contracts/slug-migrations.json`).
- Widening `salt_allowed_html()` (security-reviewed, §16a).
- Adding/removing a **pluggable** function (OVERRIDES.md list).

**MINOR** — new sections, new components, new optional args/fields, new
filters, non-breaking behaviour changes.

**PATCH** — copy/i18n, bug fixes that change no markup structure, internal
refactors, asset rebuilds.

The semver call is made by the release owner **in the PR that tags**, and
recorded in CHANGELOG.md (Keep-a-Changelog style). Every release with
breaking changes carries a **BREAKING** section enumerating the changed
component/section names at args-signature and markup granularity — this is
what a maintainer diffs against a site's override manifest.

## Trivial-patch auto-update allow-list (FLEET03)

Auto-update (Git Updater native auto-update) may be enabled ONLY for
releases where **every** change is on this list:

- Copy/i18n string changes.
- Asset rebuilds whose rendered markup is **byte-identical** (all
  `.snap.html` files unchanged).
- Non-markup PHP internals (refactors with unchanged public behaviour).
- Security patches to the vendored SVG sanitiser (SEC04 — these are
  fast-tracked BY DESIGN).

**Never auto-eligible:**
- Anything touching `template-parts/**`.
- Any args-signature change.
- Any change to registered field names / layout slugs.
- Any dependency floor change (PHP/WP/ACF minimums).

Mechanical precondition: the FLEET01 snapshot suite must be green with **no
snapshot file modified** in the release diff. A modified snapshot
disqualifies the release from auto-update regardless of anyone's judgement.

The release owner who applies the `auto-eligible` label is named in the
release notes. Default fleet posture remains **pinned** (§4); auto-update is
opt-in per site for patch releases only.

## Rollout path

tag → canary site → §16 sign-off checklist (incl. "rollback rehearsed") →
deliberate promotion site-by-site. See docs/RUNBOOK-ROLLBACK.md for the
reverse path.
