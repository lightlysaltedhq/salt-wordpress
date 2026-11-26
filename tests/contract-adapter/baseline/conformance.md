# Salt conformance: wordpress 6.0.0 against @lightlysaltedhq/salt-contract 3.0.0

**Pass.** 16 section(s) pass, 0 fail, 0 incomplete, 1 not shipped. Chrome, views and page: 9 pass, 0 fail, 0 not shipped. Adapter: command `php tests/contract-adapter/adapter.php`.

| Section | Fixtures | Field parity | Classes | Inline styles | Stylesheet pin |
| --- | --- | --- | --- | --- | --- |
| hero | 12/12 | pass | pass | pass | pass |
| rich-text | 9/9 | pass | pass | pass | pass |
| call-to-action | 4/4 | pass | pass | pass | pass |
| media-text | 4/4 | pass | pass | pass | pass |
| features | 4/4 | pass | pass | pass | pass |
| stats | 3/3 | pass | pass | pass | pass |
| logos | 3/3 | pass | pass | pass | pass |
| gallery | 6/6 | pass | pass | pass | pass |
| process | 4/4 | pass | pass | pass | pass |
| faq | 3/3 | pass | pass | pass | pass |
| tabs | 5/5 | pass | pass | pass | pass |
| collection-showcase | 8/8 | pass | pass | pass | pass |
| carousel | 8/8 | pass | pass | pass | pass |
| listing | 5/5 | pass | pass | pass | pass |
| contact | 5/5 | pass | pass | pass | pass |
| locations | 4/4 | pass | pass | pass | pass |
| pricing | not shipped | not shipped | not shipped | not shipped | not shipped |

| Chrome, views and page | Kind | Fixtures | Classes | Inline styles |
| --- | --- | --- | --- | --- |
| site-header | chrome | 6/6 | pass | pass |
| site-footer | chrome | 9/9 | pass | pass |
| page | page | 4/4 | pass | pass |
| post | view | 7/7 | pass | pass |
| service | view | 2/2 | pass | pass |
| case-study | view | 8/8 | pass | pass |
| archive | view | 3/3 | pass | pass |
| search | view | 3/3 | pass | pass |
| not-found | view | 2/2 | pass | pass |

## Stylesheet pin

Pass: `assets/dist/salt.a2f7a31e39/salt.css` against this contract's `styles/salt.css` (3.0.0): identical.

## Field parity

acf snapshot `inc/fields/generated/sections.json` with options `inc/fields/generated/sections.options.json`.
