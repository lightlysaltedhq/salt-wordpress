# Override manifest — `salt-{client}` (template)

Copy into the child theme repo root as `OVERRIDES.md` and keep current: a
parent update re-verifies EXACTLY the parts listed here (§4 drift control).
CI/tooling may also scan `template-parts/**` in the child — this manifest
must agree with the filesystem.

## Site

- Client: {client}
- Parent version pinned: v{x.y.z}
- Transport: Git Updater / git-deploy
- Canary: yes/no

## Template-part overrides

| Overridden file | Since parent | Reason | Re-verified against |
|---|---|---|---|
| template-parts/components/… | v1.2.0 | … | v1.3.0 ✅ |

## Pluggable-function overrides

| Function | Reason |
|---|---|
| salt_query_default_args() | … |

## Filter hooks (priority 20+)

| Hook | Callback | Reason |
|---|---|---|

## Child stylesheet

- [ ] Uses `assets/child.css` only (buildless — default)
- [ ] Site is on the core-rebuild path (Tailwind rebuild required on every
      parent update — record why)

## Update procedure for this site

1. Read the parent CHANGELOG BREAKING section for the target version.
2. Cross-reference every row above; re-verify affected overrides.
3. Update the pin, run the §16 sign-off checklist, record the date here.
