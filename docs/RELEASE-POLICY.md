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

## Release tag gate

Git Updater offers the newest tag and requires the `style.css` `Version`
header to match it. On every `v*` tag push, CI's "Release tag gate" job runs
`bin/check-release-tag.mjs` and fails unless all hold: the tag is exactly
`vX.Y.Z` (no pre-release suffix), `style.css` `Version` and `SALT_VERSION` at
the tagged commit equal the tag without the `v`, and the tagged commit is on
`main` (an ancestor of, or equal to, `origin/main`). Tag only after the
version-bump PR has merged.

The check runs **after** the tag is pushed, so it detects a bad tag; it does
not prevent one, and the tag is already visible to Git Updater. If it fails:
delete the tag at once (`git push origin :refs/tags/vX.Y.Z`), fix the versions
on `main`, re-tag, and check any site on opt-in auto-update. Real prevention is
a repository tag ruleset restricting who can create `v*` tags; that is a
settings matter outside this check.

Limitation: GitHub runs the workflow file from the tagged commit itself. A tag
on a commit that predates this gate, or on a branch that edits `ci.yml` or the
script, runs no gate or a modified one, so no red check appears.

Self-test: `bash bin/check-release-tag.test.sh`.

## Rollout path

tag → canary site → §16 sign-off checklist (incl. "rollback rehearsed") →
deliberate promotion site-by-site. See docs/RUNBOOK-ROLLBACK.md for the
reverse path.
