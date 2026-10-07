# What travels where (DATA03) — the Principle-6 boundary, fixed

Principle 6 as-corrected: **schema is versioned, not stored in the
database.** Field definitions, section code and token/config SCHEMA live in
the repo; per-site config VALUES and all content are DB-resident. A fresh
checkout + child theme yields an **unbranded, unconfigured shell** — a site
is reproduced by code + database together, never by checkout alone. Scope
backups and disaster recovery accordingly.

| Lives in | Contents |
|---|---|
| **Parent repo** (`salt-wordpress`) | Section library, components, templates, design-token schema + compiled CSS/JS (`assets/dist/`), ACF field/layout **definitions** (`inc/fields/`), query resolver, schema graph builder, services, vendored SVG sanitiser, this documentation |
| **Child repo** (`salt-{client}`) | `style.css` (`Template: salt-wordpress` + pin note), `functions.php` (site hooks, filter overrides at priority 20+), overridden `template-parts/**` files, `assets/child.css`, the **override manifest** |
| **Database** (per site, backed up per site) | Uploaded fonts + logos (media library — the @font-face CSS is derived at render), brand config VALUES (colours, fonts, logo — ACF options in `wp_options`), company/schema info, module toggles, form recipients + routing, page composition (the Flexible Content `sections` rows in `wp_postmeta`), all CPT content, menus, media |

## Consequences

- **Disaster recovery:** repo restore alone gives neutral-branded templates
  with no pages. DB restore alone gives content that renders only when the
  matching theme version is deployed. Restore both; pin the theme to the
  tag the DB was last running.
- **Environment sync (staging → prod):** code moves via git (deploy the same
  tag); content + options move via DB migration. ACF **Relationship fields
  store post IDs** and do not survive naive WXR re-imports (DATA05) — move
  content by full-DB migration, not selective export, or hand-pick
  selections degrade silently to query defaults (the resolver logs this
  under WP_DEBUG).
- **Migration from the live LS theme (DATA01):** this build guarantees
  field-shape parity (`work` CPT + `case_study` alias; Services default =
  legacy `services_overview` order, DATA04) but ships **no importer**. The
  WP-CLI content-migration script (post_type rewrite + term remap + field
  key mapping) is a documented LATER task — see SECTIONS.md §Migration.
