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

## D. Moving an existing install to salt-wordpress (5.0.0)

From 5.0.0 the repo is `lightlysaltedhq/salt-wordpress`, and Git Updater
only matches a theme whose folder is the repo name. A site installed before
5.0.0 has the parent at `wp-content/themes/salt` and a child saying
`Template: salt`. Move it in this order, over SFTP/SSH with WP-CLI, outside
busy hours. Do not use Git Updater for any of it: until step 4 is done it
cannot see the theme.

1. **Back up.** Snapshot the parent and child folders and the theme options:
   `cp -r wp-content/themes/salt wp-content/themes/.salt-backup-pre-5.0.0`,
   the same for the child, and
   `wp option get template`, `wp option get stylesheet` and
   `wp option list --search='theme_mods_*' --format=json` saved to a file
   (or take a full DB backup).
2. **Rename the parent and repoint the child in one step**, with no page
   load in between. WordPress falls back to a default theme if it finds the
   active parent missing, so do all three together:
   `mv wp-content/themes/salt wp-content/themes/salt-wordpress`, change the
   child's `style.css` to `Template: salt-wordpress`, and
   `wp option update template salt-wordpress`. If the parent itself is the
   active theme (no child), also `wp option update stylesheet salt-wordpress`.
3. **Carry the theme mods if the parent was ever the active theme.**
   WordPress keys Customizer settings and menu locations by folder, so
   `theme_mods_salt` does not follow the rename. If it exists, copy it:
   `wp option get theme_mods_salt --format=json > /tmp/mods.json` then
   `wp option update theme_mods_salt-wordpress --format=json < /tmp/mods.json`.
   A site that only ever ran the child keeps its mods under the child's own
   folder and needs nothing here.
4. **Update the parent to 5.0.0** in place: in the parent folder,
   `git fetch --tags && git checkout v5.0.0` (git-deploy), or replace its
   contents with the v5.0.0 release, renaming the extracted
   `salt-wordpress-5.0.0/` folder to `salt-wordpress`. Then replace the
   site's `SALT_UPDATER_TOKEN` with a fine-grained read-only token scoped to
   `lightlysaltedhq/salt-wordpress` (a token issued for `otigs/salt` does
   not cover the transferred repo).
5. **Confirm Git Updater sees the repo.** Themes shows Salt 5.0.0 as the
   parent, the "Git Updater will not offer updates" notice is absent, and
   Git Updater's Remote Management/theme list shows
   `lightlysaltedhq/salt-wordpress` on branch `main` with no update error.
   Spot-check a composed page, an archive and menu locations.

To undo, restore the step 1 snapshots and the saved `template`/`stylesheet`
values.

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
