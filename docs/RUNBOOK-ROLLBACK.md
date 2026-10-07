# Rollback & recovery runbook (FLEET02)

**Scenario:** a promoted parent release is breaking a live site. This runbook
is the rehearsed escape hatch — follow it top to bottom. Roll BACK first,
diagnose later.

**Downtime target: ≤ 15 minutes** from incident confirmation to the prior
tag serving traffic. Rollback is verified on the canary at every release
(release checklist line "rollback rehearsed").

## 0. Before every promotion (the insurance)

1. Record the site's current known-good tag in the site log (e.g. `v1.3.2`).
2. Snapshot the parent theme directory:
   `cp -r wp-content/themes/salt-wordpress wp-content/themes/.salt-backup-v1.3.2`
   (or rely on the host's file snapshot if it restores in < 5 min).
3. Confirm a filesystem access path (SFTP/SSH) INDEPENDENT of wp-admin.

## A. wp-admin is reachable (non-fatal breakage)

**Git Updater transport:**
1. Themes → salt → re-pin to the prior tag (Git Updater branch/tag switcher)
   → reinstall. Verify the version in the theme header.
2. Spot-check the pages that were broken + one composed page + one archive.

**git-deploy transport:**
1. `cd wp-content/themes/salt-wordpress && git fetch --tags && git checkout <prior-tag>`
2. Same spot-checks.

## B. wp-admin is NOT reachable (fatal — the child can't even load)

A fatal in the parent takes wp-admin down with it (`Template: salt-wordpress` means the
child cannot boot without the parent). Do NOT try to fix forward on
production.

1. Over SFTP/SSH, move the broken parent aside:
   `mv wp-content/themes/salt-wordpress wp-content/themes/.salt-broken`
2. Restore the snapshot: `mv wp-content/themes/.salt-backup-<tag> wp-content/themes/salt-wordpress`
   — or `git clone --branch <prior-tag> --depth 1 <repo> salt-wordpress` if no snapshot
   exists (the deploy token is in wp-config as `SALT_UPDATER_TOKEN`, SEC03).
3. Confirm the site renders and wp-admin loads.
4. Keep `.salt-broken` for diagnosis; delete after the post-mortem.

## C. After any rollback

1. Re-pin the site to the restored tag so no auto-update re-breaks it.
2. Post in the fleet channel: site, symptom, rolled-back-to tag.
3. File the defect against the parent repo; the bad tag gets a `-yanked`
   note in CHANGELOG.md and must never be promoted to another site.
4. Re-run the site's override re-verify if the rollback crossed a major
   version (child overrides may target the newer markup).

## Data-safety notes

- Parent releases in this line NEVER run destructive DB migrations; ACF
  values are keyed by field names whose stability is CI-enforced (FLEET07),
  so rolling the parent back cannot orphan content created under the newer
  tag unless that tag introduced NEW fields (their values simply go dormant
  and reappear on roll-forward).
- Options values (brand config etc.) are untouched by theme file swaps.

## Token custody (SEC03)

- Each site holds its own **fine-grained, read-only** GitHub deploy token
  scoped to this single repo, defined in `wp-config.php` as
  `SALT_UPDATER_TOKEN`. Never store a token in the database.
- **Rotation:** rotate annually and on staff departure. Issue the new token,
  update wp-config, revoke the old one in GitHub settings.
- **Incident revocation:** if a site is compromised, revoke THAT site's
  token immediately in GitHub → the rest of the fleet is unaffected (this is
  why tokens are per-site).

## Rehearsal

"Rollback rehearsed on canary" is a named line on the release checklist:
before promoting any release beyond the canary, perform step A (and step B
once per quarter) on the canary site and record the time taken.
