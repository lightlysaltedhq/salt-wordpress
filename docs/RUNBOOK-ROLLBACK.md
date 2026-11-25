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
2. Restore the snapshot: `mv wp-content/themes/.salt-backup-<tag> wp-content/themes/salt-wordpress`.
   If no snapshot exists, install the prior tag from the public repository.
   The repository is public, so this needs no token, key or login (SEC03):
   `cd wp-content/themes && curl -fsSL -o salt-prior.zip https://github.com/lightlysaltedhq/salt-wordpress/archive/refs/tags/<prior-tag>.zip && unzip -q salt-prior.zip && mv salt-wordpress-<prior-version> salt-wordpress`,
   where `<prior-version>` is the tag without its `v` (GitHub names the
   extracted folder that way). That URL is a plain download, not a GitHub API
   request, so it is not held back by the 60-requests-an-hour limit (§Updates
   without a token). If the host cannot reach GitHub, upload the same zip from
   a copy kept with the release record, then unzip and rename it the same way.
   The zip needs no build step: it carries `assets/dist` and the vendored
   runtime libraries (FLEET08).
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
   `salt-wordpress-5.0.0/` folder to `salt-wordpress`. Then delete the
   site's `SALT_UPDATER_TOKEN` line from `wp-config.php` if it has one: the
   repo is public and updates need no token (§Updates without a token). A
   token issued for `otigs/salt` does not cover the transferred repo, and
   a dead token makes GitHub answer a public read with 401.
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

## Updates without a token (SEC03)

A site holds **no credential** to update the parent. The repository is public,
and Git Updater reads it unauthenticated. This is the handoff rule (SC-005): a
site we stop maintaining keeps working on its installed version, with nothing
to expire, rotate or revoke.

- **Why not a token.** A per-site token is a credential someone must issue,
  rotate and revoke, and a site nobody maintains any more cannot do that.
  Git Updater's own authenticated requests are also tied to its paid licence:
  once the 14-day trial ends it deletes stored access tokens every month
  unless licensed (`GU_Upgrade::flush_tokens()`). Salt's own header could carry
  a token past that, but only by working around the licence gate, and only
  until the token is revoked. Unauthenticated reads of a public repo need
  neither a licence nor a credential.
- **Distribution.** A release is the repository's tag zipball (Git Updater
  fetches `api.github.com/repos/lightlysaltedhq/salt-wordpress/zipball/<tag>`).
  The tag carries `assets/dist` committed and the runtime libraries vendored
  (FLEET08), so the zipball installs as it stands: no build step on the site.
  We do not use release assets: Git Updater only reads them when the theme
  declares a `Release Asset` header, which also makes it fail the update if a
  release has no uploaded asset, and it adds a call to the releases endpoint.
- **Site setup.** Install Git Updater, put the theme in
  `wp-content/themes/salt-wordpress`, and add nothing to `wp-config.php`. Leave
  the Git Updater GitHub token field empty and do not click "Connect GitHub".
- **Legacy token.** A site set up before this rule may still have
  `SALT_UPDATER_TOKEN` in `wp-config.php`. Delete the line, then revoke that
  token in GitHub → Settings → Developer settings. While it stays, the theme
  sends it with requests for this repo only, and once GitHub revokes or
  expires it every one of those requests, though the repo is public, is
  answered 401.
- **The 60-an-hour limit.** GitHub lets an unauthenticated client make 60 API
  requests an hour **per IP address**, shared by everything on that address.
  On shared hosting other sites' updaters and plugins draw on the same 60.
  One Git Updater refresh of this theme takes up to about 8 requests (file
  header, repo contents, assets, readme, changelog, tags, branches, repo
  metadata) and is cached for 12 hours, so one site normally uses far under 60.
  The tag zipball download is a redirect to `codeload.github.com`; in testing
  it did not count against the limit, but GitHub does not promise that.
- **When the limit is hit.** GitHub answers 403 or 429 with
  `x-ratelimit-remaining: 0` and a reset time. Git Updater (v14.5.0.10) gets
  no refresh token, so it stops asking for that theme for 5 minutes, leaves
  the refresh incomplete, and tries again within the hour. The site keeps
  running on its installed version, and wp-admin shows no update, or Git
  Updater's own error notice, until the limit resets. Nothing is lost and
  nothing needs a token. To confirm from the server:
  `curl -s https://api.github.com/rate_limit` (this call is free) shows
  `core.remaining` and the `core.reset` time in epoch seconds. If the shared
  address stays exhausted, wait for the reset, or install the release by hand
  with the zip steps in §B.

## Rehearsal

"Rollback rehearsed on canary" is a named line on the release checklist:
before promoting any release beyond the canary, perform step A (and step B
once per quarter) on the canary site and record the time taken.
