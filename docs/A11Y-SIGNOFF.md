# Per-template accessibility sign-off checklist (A11Y07)

Run per template, per release, on the canary site. **Every criterion is
binary** — a template passes only when every line is a pass. Store the
completed checklist + tool output as a dated release artifact (see the
template at the bottom) attached to the release/canary promotion.

Reviewer prerequisite: familiar with axe DevTools, keyboard-only
navigation, and VoiceOver (or NVDA) basics.

## Templates to cover

front-page, page (composed, 5+ sections incl. every schema-emitting
section in use), single, archive-{each public CPT}, category/tag, search
(with and without results), 404. RTL spot-check on one composed page
(`<html dir="rtl">` override).

## Criteria (binary)

| # | Criterion | Method | Pass |
|---|---|---|---|
| 1 | Zero axe critical or serious violations | axe DevTools full-page scan | ☐ |
| 2 | Heading outline: exactly one H1, no level skips | HeadingsMap / axe outline view | ☐ |
| 3 | Every interactive element keyboard-reachable, operable, with visible focus; no keyboard trap | Tab/Shift-Tab/Enter/Esc walk | ☐ |
| 4 | No content loss or overlap at 400% zoom (reflow, WCAG 1.4.10) | Browser zoom 400%, 1280px window | ☐ |
| 5 | Focus not obscured by sticky UI (WCAG 2.4.11 AA) | Tab through with sticky header active | ☐ |
| 6 | Target size ≥ 24×24 CSS px or spacing exception (WCAG 2.5.8 AA) | Spot-check nav, accordion summaries, carousel buttons | ☐ |
| 7 | Skip link works and lands on `#main` | Keyboard first-Tab | ☐ |
| 8 | Images: meaningful images have alt; decorative are `alt=""`/aria-hidden | Inspect rendered markup | ☐ |
| 9 | Carousels/galleries: all items reachable with JS disabled | Disable JS, walk the section (FAIL02) | ☐ |
| 10 | Status pills / colour signals carry a non-colour cue (text label) | Inspect Locations/status output | ☐ |
| 11 | `prefers-reduced-motion` honoured (no animation with it set) | OS setting + reload | ☐ |
| 12 | Forms (when enabled): label + `aria-describedby` per field; errors announced | Inspect + VoiceOver pass | ☐ |
| 13 | No unexpected `fallback.php` output ("section unavailable") | Page source grep | ☐ |
| 14 | JSON-LD parses and matches page content | Schema Markup Validator | ☐ |

## Artifact template

```
Release: vX.Y.Z   Site: {canary}   Date: YYYY-MM-DD   Reviewer: {name}
Template: {name}  Result: PASS/FAIL
Failed lines + defects filed: …
Attached: axe export, screenshots (400% zoom, focus states)
```
