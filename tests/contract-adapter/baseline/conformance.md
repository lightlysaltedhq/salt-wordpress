# Salt conformance: wordpress 6.0.0-dev against @lightlysaltedhq/salt-contract 1.0.0-rc.6

**Partial, not conforming.** 0 section(s) pass, 14 fail, 0 incomplete, 3 not shipped. Chrome, views and page: 0 pass, 9 fail, 0 not shipped. Adapter: command `php tests/contract-adapter/adapter.php`.

| Section | Fixtures | Field parity | Classes | Stylesheet pin |
| --- | --- | --- | --- | --- |
| hero | 1/10 | pass | pass | pass |
| rich-text | 1/5 | pass | pass | pass |
| call-to-action | 1/4 | pass | pass | pass |
| media-text | 1/4 | pass | pass | pass |
| features | 1/4 | pass | pass | pass |
| stats | 1/3 | pass | pass | pass |
| logos | 1/3 | pass | pass | pass |
| gallery | 1/6 | pass | pass | pass |
| process | 1/4 | pass | pass | pass |
| faq | 1/3 | pass | pass | pass |
| tabs | not shipped | not shipped | not shipped | not shipped |
| collection-showcase | 1/8 | fail (1) | pass | pass |
| carousel | 1/6 | fail (1) | pass | pass |
| listing | not shipped | not shipped | not shipped | not shipped |
| contact | 0/5 | pass | pass | pass |
| locations | 1/3 | pass | pass | pass |
| pricing | not shipped | not shipped | not shipped | not shipped |

| Chrome, views and page | Kind | Fixtures | Classes |
| --- | --- | --- | --- |
| site-header | chrome | 0/6 | pass |
| site-footer | chrome | 0/5 | pass |
| page | page | 0/4 | pass |
| post | view | 0/2 | pass |
| service | view | 0/2 | pass |
| case-study | view | 0/4 | pass |
| archive | view | 0/2 | pass |
| search | view | 0/3 | pass |
| not-found | view | 0/2 | pass |

## Stylesheet pin

Pass: `assets/dist/salt.4f3bca2639/salt.css` against this contract's `styles/salt.css` (1.0.0-rc.6): identical.

## Field parity

acf snapshot `inc/fields/generated/sections.json` with options `inc/fields/generated/sections.options.json`.

## Failures

### hero

- `hero/full-bleed-background-inverse`: attribute `aria-labelledby` at `section.salt-section`: expected `welcome__heading`, found nothing
- `hero/full-bleed-background-no-focal-point`: attribute `aria-labelledby` at `section.salt-section`: expected `garden-tour__heading`, found nothing
- `hero/full-bleed-no-background`: attribute `aria-labelledby` at `section.salt-section`: expected `hero-plain__heading`, found nothing
- `hero/minimal-centre-inverse-dark-alt`: attribute `aria-labelledby` at `section.salt-section`: expected `about__heading`, found nothing
- `hero/minimal-left-brand-tint-collapsed`: missing at `div.salt-section`: expected `<div class="salt-section" data-collapse-top data-spacing="md" data-tone="brand-tint" data-tone-dark="brand-tint" data-track="hero-2" data-width="default" id="intro" style="--salt-section-space:var(--s…`, found nothing
- `hero/split-image-left`: attribute `aria-labelledby` at `section.salt-section`: expected `hero-split__heading`, found nothing
- `hero/split-image-right-later`: attribute `aria-labelledby` at `section.salt-section`: expected `aftercare__heading`, found nothing
- `hero/split-no-image-new-tab`: attribute `aria-labelledby` at `section.salt-section`: expected `guide__heading`, found nothing
- `hero/stacked-image-divider`: attribute `aria-labelledby` at `section.salt-section`: expected `clifton__heading`, found nothing

### rich-text

- `rich-text/body-only-first-on-page`: element at `div.salt-section`: expected `<div class="salt-section" data-spacing="md" data-tone="surface" data-tone-dark="surface" data-track="rich-text-1" data-width="default" id="approach-first" style="--salt-section-space:var(--space-secti…`, found `<section class="bg-surface md:py-20 py-14 section-rich-text text-ink x-section" data-section="rich-text" id="approach-first">`
- `rich-text/body-only-no-heading`: element at `div.salt-section`: expected `<div class="salt-section" data-spacing="md" data-tone="surface" data-tone-dark="surface" data-track="rich-text-1" data-width="default" id="approach" style="--salt-section-space:var(--space-section-md)…`, found `<section class="bg-surface md:py-20 py-14 section-rich-text text-ink x-section" data-section="rich-text" id="approach">`
- `rich-text/centre-inverse`: attribute `aria-labelledby` at `section.salt-section`: expected `small-team__heading`, found nothing
- `rich-text/left-eyebrow-heading-body`: attribute `aria-labelledby` at `section.salt-section`: expected `how-we-work__heading`, found nothing

### call-to-action

- `call-to-action/body-and-button-no-heading`: element at `div.salt-section`: expected `<div class="salt-section" data-spacing="md" data-tone="brand-tint" data-tone-dark="surface-inverse" data-track="call-to-action-3" data-width="full" id="calendar" style="--salt-section-space:var(--spac…`, found `<section class="bg-surface md:py-20 py-14 section-call-to-action text-ink x-section" data-section="call-to-action" id="calendar">`
- `call-to-action/heading-body-two-buttons`: attribute `aria-labelledby` at `section.salt-section`: expected `book__heading`, found nothing
- `call-to-action/heading-only-inverse`: attribute `aria-labelledby` at `section.salt-section`: expected `spring__heading`, found nothing

### media-text

- `media-text/alternating-inverse`: attribute `aria-labelledby` at `section.salt-section`: expected `process-rows__heading`, found nothing
- `media-text/single-many-rows`: attribute `aria-labelledby` at `section.salt-section`: expected `services-rows__heading`, found nothing
- `media-text/single-one-row-first`: attribute `aria-labelledby` at `section.salt-section`: expected `planting__heading`, found nothing

### features

- `features/four-columns-inverse`: attribute `aria-labelledby` at `section.salt-section`: expected `features-four__heading`, found nothing
- `features/many-items-linked`: attribute `aria-labelledby` at `section.salt-section`: expected `features-many__heading`, found nothing
- `features/one-item`: attribute `aria-labelledby` at `section.salt-section`: expected `features-one__heading`, found nothing

### stats

- `stats/many-with-icons`: attribute `aria-labelledby` at `section.salt-section`: expected `stats-many__heading`, found nothing
- `stats/one-stat-inverse`: element at `div.salt-section`: expected `<div class="salt-section" data-spacing="md" data-tone="surface-inverse" data-tone-dark="surface-inverse" data-track="stats-1" data-width="default" id="stats-one" style="--salt-section-space:var(--spac…`, found `<section class="bg-surface-inverse md:py-20 py-14 section-stats text-ink-inverse x-section" data-section="stats" id="stats-one">`

### logos

- `logos/many-linked-greyscale`: attribute `aria-labelledby` at `section.salt-section`: expected `logos-many__heading`, found nothing
- `logos/one-unlinked-colour-inverse`: element at `div.salt-section`: expected `<div class="salt-section" data-spacing="md" data-tone="surface-inverse" data-tone-dark="surface-inverse" data-track="logos-1" data-width="default" id="logos-one" style="--salt-section-space:var(--spac…`, found `<section class="bg-surface-inverse md:py-20 py-14 section-logos text-ink-inverse x-section" data-section="logos" id="logos-one">`

### gallery

- `gallery/carousel-with-heading`: attribute `aria-labelledby` at `section.salt-section`: expected `gallery-slides__heading`, found nothing
- `gallery/carousel-with-label`: element at `div.salt-section`: expected `<div class="salt-section" data-spacing="md" data-tone="surface" data-tone-dark="surface" data-track="gallery-3" data-width="default" id="gallery-label" style="--salt-section-space:var(--space-section-…`, found `<section class="bg-surface md:py-20 py-14 section-gallery text-ink x-section" data-section="gallery" id="gallery-label">`
- `gallery/grid-captions-first`: attribute `aria-labelledby` at `section.salt-section`: expected `gallery-grid__heading`, found nothing
- `gallery/masonry-captions-off`: element at `div.salt-section`: expected `<div class="salt-section" data-spacing="md" data-tone="surface-inverse" data-tone-dark="surface-inverse" data-track="gallery-1" data-width="default" id="gallery-masonry" style="--salt-section-space:va…`, found `<section class="bg-surface-inverse md:py-20 py-14 section-gallery text-ink-inverse x-section" data-section="gallery" id="gallery-masonry">`
- `gallery/one-image`: attribute `aria-labelledby` at `section.salt-section`: expected `gallery-one__heading`, found nothing

### process

- `process/cards-inverse`: attribute `aria-labelledby` at `section.salt-section`: expected `process-cards__heading`, found nothing
- `process/one-step`: attribute `aria-labelledby` at `section.salt-section`: expected `process-one__heading`, found nothing
- `process/timeline-many`: attribute `aria-labelledby` at `section.salt-section`: expected `process-timeline__heading`, found nothing

### faq

- `faq/many`: missing at `section.salt-section`: expected `<section aria-labelledby="faq-help__heading" class="salt-section" data-spacing="md" data-tone="surface" data-tone-dark="surface" data-track="faq-1" data-width="default" id="faq-help" style="--salt-sec…`, found nothing
- `faq/one-inverse`: missing at `section.salt-section`: expected `<section aria-labelledby="faq-one__heading" class="salt-section" data-spacing="md" data-tone="surface-inverse" data-tone-dark="surface-inverse" data-track="faq-1" data-width="default" id="faq-one" sty…`, found nothing

### collection-showcase

- `collection-showcase/accordion-testimonials`: missing at `section.salt-section`: expected `<section aria-labelledby="kind-words__heading" class="salt-section" data-spacing="md" data-tone="surface-inverse" data-tone-dark="surface-inverse" data-track="collection-showcase-4" data-width="defaul…`, found nothing
- `collection-showcase/featured-case-studies`: attribute `aria-labelledby` at `section.salt-section`: expected `recent-work__heading`, found nothing
- `collection-showcase/grid-services-first`: attribute `aria-labelledby` at `section.salt-section`: expected `services-grid__heading`, found nothing
- `collection-showcase/grid-team-contacts`: attribute `aria-labelledby` at `section.salt-section`: expected `the-team__heading`, found nothing
- `collection-showcase/index-posts-grouped`: attribute `aria-labelledby` at `section.salt-section`: expected `journal-index__heading`, found nothing
- `collection-showcase/index-posts-ungrouped`: attribute `aria-labelledby` at `section.salt-section`: expected `journal-all__heading`, found nothing
- `collection-showcase/list-posts-dated`: attribute `aria-labelledby` at `section.salt-section`: expected `latest-posts__heading`, found nothing
- fields: collection-showcase is not in the field snapshot

### carousel

- `carousel/inline-generic-heading`: attribute `aria-labelledby` at `section.salt-section`: expected `recent-gardens__heading`, found nothing
- `carousel/inline-no-name-list`: element at `div.salt-section`: expected `<div class="salt-section" data-spacing="none" data-tone="surface" data-tone-dark="surface" data-track="carousel-3" data-width="narrow" id="two-steps">`, found `<section class="bg-surface section-scroll-carousel text-ink x-section" data-section="scroll-carousel" id="two-steps">`
- `carousel/inline-one-card`: attribute `aria-labelledby` at `section.salt-section`: expected `our-base__heading`, found nothing
- `carousel/inline-quote-label-inverse`: element at `div.salt-section`: expected `<div class="salt-section" data-spacing="md" data-tone="surface-inverse" data-tone-dark="surface-inverse" data-track="carousel-2" data-width="default" id="client-quotes" style="--salt-section-space:var…`, found `<section class="bg-surface-inverse md:py-20 py-14 section-scroll-carousel text-ink-inverse x-section" data-section="scroll-carousel" id="client-quotes">`
- `carousel/testimonials-source`: missing at `section.salt-section`: expected `<section aria-labelledby="kind-words-carousel__heading" class="salt-section" data-spacing="md" data-tone="brand-tint" data-tone-dark="brand-tint" data-track="carousel-5" data-width="default" id="kind-…`, found nothing
- fields: carousel is not in the field snapshot

### contact

- `contact/closed-details-hidden-inverse`: missing at `div.salt-section`: expected `<div class="salt-section" data-spacing="md" data-tone="surface-inverse" data-tone-dark="surface-inverse" data-track="contact-1" data-width="default" id="contact-closed" style="--salt-section-space:var…`, found nothing
- `contact/closed-with-details`: attribute `aria-labelledby` at `section.salt-section`: expected `contact__heading`, found nothing
- `contact/form-invalid`: attribute `aria-labelledby` at `section.salt-section`: expected `enquire__heading`, found nothing
- `contact/form-open`: attribute `aria-labelledby` at `section.salt-section`: expected `enquire__heading`, found nothing
- `contact/form-sent`: attribute `aria-labelledby` at `section.salt-section`: expected `enquire__heading`, found nothing

### locations

- `locations/many-offices-map-inverse`: attribute `aria-labelledby` at `section.salt-section`: expected `offices__heading`, found nothing
- `locations/one-office-open`: attribute `aria-labelledby` at `section.salt-section`: expected `find-us__heading`, found nothing

### site-header

- `site-header/drawer-open`: attribute `class` at `header.salt-header`: expected `salt-header`, found `bg-surface border-b border-border x-header`
- `site-header/light-and-dark-logo`: attribute `class` at `header.salt-header`: expected `salt-header`, found `bg-surface border-b border-border x-header`
- `site-header/menu-submenus-phone-cta-sticky`: attribute `class` at `header.salt-header`: expected `salt-header`, found `bg-surface border-b border-border is-sticky x-header`
- `site-header/theme-toggle-button`: attribute `class` at `header.salt-header`: expected `salt-header`, found `bg-surface border-b border-border x-header`
- `site-header/theme-toggle`: attribute `class` at `header.salt-header`: expected `salt-header`, found `bg-surface border-b border-border x-header`
- `site-header/wordmark-only`: attribute `class` at `header.salt-header`: expected `salt-header`, found `bg-surface border-b border-border x-header`

### site-footer

- `site-footer/columns-socials-copyright`: attribute `class` at `footer.salt-footer`: expected `salt-footer`, found `bg-surface-alt border-border border-t x-footer`
- `site-footer/consent-panel-open`: attribute `class` at `footer.salt-footer`: expected `salt-footer`, found `bg-surface-alt border-border border-t x-footer`
- `site-footer/display-preferences`: attribute `class` at `footer.salt-footer`: expected `salt-footer`, found `bg-surface-alt border-border border-t x-footer`
- `site-footer/single-flat-menu-inverse`: attribute `class` at `footer.salt-footer`: expected `salt-footer`, found `bg-surface-alt border-border border-t x-footer`
- `site-footer/theme-toggle-button`: attribute `class` at `footer.salt-footer`: expected `salt-footer`, found `bg-surface-alt border-border border-t x-footer`

### page

- `page/fallback-heading`: the adapter exited 4; stderr: `salt adapter: the page page is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `page/header-section-footer`: the adapter exited 4; stderr: `salt adapter: the page page is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `page/hero-minimal-centre`: the adapter exited 4; stderr: `salt adapter: the page page is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `page/hero-split-image`: the adapter exited 4; stderr: `salt adapter: the page page is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`

### post

- `post/full`: the adapter exited 4; stderr: `salt adapter: the view post is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `post/minimal`: the adapter exited 4; stderr: `salt adapter: the view post is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`

### service

- `service/full`: the adapter exited 4; stderr: `salt adapter: the view service is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `service/minimal`: the adapter exited 4; stderr: `salt adapter: the view service is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`

### case-study

- `case-study/aside`: the adapter exited 4; stderr: `salt adapter: the view case-study is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `case-study/band`: the adapter exited 4; stderr: `salt adapter: the view case-study is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `case-study/full`: the adapter exited 4; stderr: `salt adapter: the view case-study is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `case-study/minimal`: the adapter exited 4; stderr: `salt adapter: the view case-study is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`

### archive

- `archive/author`: the adapter exited 4; stderr: `salt adapter: the view archive is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `archive/topic`: the adapter exited 4; stderr: `salt adapter: the view archive is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`

### search

- `search/no-query`: the adapter exited 4; stderr: `salt adapter: the view search is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `search/no-results`: the adapter exited 4; stderr: `salt adapter: the view search is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `search/results`: the adapter exited 4; stderr: `salt adapter: the view search is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`

### not-found

- `not-found/minimal`: the adapter exited 4; stderr: `salt adapter: the view not-found is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
- `not-found/with-search`: the adapter exited 4; stderr: `salt adapter: the view not-found is not rendered by this adapter yet: it needs WordPress's loop and document head, which the shims do not provide (M4).`
