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

## Declared sections (`salt-overrides.json`)

Section view swaps (tier `view`), section takeovers (tier `logic`), image
sizes and client-only sections are declared in
[`salt-overrides.json`](salt-overrides.json) at the child theme root, in the
salt-contract's format (`schema/replaced-logic.schema.json`). Do not repeat
them here: that file is the record, and the parent reads it to honour a
takeover and to raise the update flag. The parent's
docs/contracts/OVERRIDES.md ("Declaring replaced sections") says how.

## Template-part overrides

Every other `template-parts/**` override (components, chrome parts).

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
2. Cross-reference every row above; re-verify affected overrides. For
   declared sections, work through the parent's update flag (the
   "salt theme:" notice on the Themes and Updates screens): it lists every
   `logic` entry, every `view` entry whose `verified` differs from the
   file's `contract`, and any mismatch between `salt-overrides.json` and
   the child's files. It does NOT list a view swap whose `verified` equals
   `contract`, and it cannot tell whether the update changed a section's
   view props: when the update moves Salt to a newer contract release,
   re-verify every view swap by hand, then set `contract` and each entry's
   `verified` to that release. The parent does not yet record its contract
   release; until its CHANGELOG names one, leave `contract` as it is.
3. Update the pin, run the §16 sign-off checklist, record the date here.
