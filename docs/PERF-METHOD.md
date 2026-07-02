# CWV / performance measurement method (§7 targets; PERF01)

Targets are design guidance (LCP < 2.0s, CLS < 0.05, INP < 200ms) — **the
method is the committed artifact**, not a CI gate.

## Lab (pre-release, per template, on the canary)

- Tool: Lighthouse (Chrome DevTools), **Mobile** preset: Moto G Power
  emulation, Slow 4G throttle, cold cache. 3 runs, take the median.
- Read: LCP and CLS directly. Lighthouse does NOT measure INP — use
  **TBT < 200ms as the lab proxy** and verify interaction latency by hand
  (open accordion, drawer, carousel next) with the Performance panel's
  Interactions track on 4× CPU throttle.
- Record: page transfer weight (KB) for the representative composed page —
  the Principle-1 falsifiable line. Baseline expectation: no page composed
  purely of salt sections should exceed ~300 KB transfer without images,
  or ship any third-party request (must be zero, PRIV01).

## Field (post-launch, where traffic allows)

- Source: CrUX (PageSpeed Insights field panel) per origin, 28-day p75.
  INP is a field metric — **field p75 INP < 200ms is the real target**;
  low-traffic SME sites without CrUX data rely on the lab proxy above.

## What's measured when

| Moment | What |
|---|---|
| Canary sign-off (every release) | Lab pass on front-page + heaviest composed page + one archive |
| Quarterly | Field CrUX review per live site (where data exists) |

Interactive components (accordion/drawer/carousel) are the INP risk (§7):
any new section JS must be event-driven, run < 50ms per interaction on 4×
throttle, and never attach when its section is absent (enforced by the
per-section enqueue scan).
