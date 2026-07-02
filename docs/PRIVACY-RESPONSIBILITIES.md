# Privacy responsibilities — what salt does vs what each SITE must do

Locked decision (v2.0.0): **compliance is per-site, delivered by a consent
plugin the site chooses.** salt ships privacy-safe mechanics and integration
seams; it ships NO banner, no consent store, no legal content. This table is
the boundary — put it in front of whoever owns each client site.

## What the parent theme guarantees (mechanics)

| Surface | Behaviour |
|---|---|
| `salt_consent()` | Fail-closed bridge: grants only via the WP Consent API (Complianz/CookieYes/Cookiebot…) or the `salt_consent_state` filter; no CMP wired ⇒ non-essential denied. Gates maps/embeds/booking loads. |
| GA4 | Loads only when enabled per site, with **Consent Mode v2 defaults DENIED** (all four signals) before the tag; cookieless pings until the site's CMP updates consent. |
| PostHog | **First-party bundled** (no CDN), `cookieless_mode: 'on_reject'` — no cookies/storage until consent; flipped by WP Consent API events or `salt:consent`. Requires "Cookieless server hash mode" ON in the PostHog project. |
| Contact form | Privacy-policy link rendered at the point of collection (configure the page in Site Settings → Contact form); no marketing consent is bundled into submission; submitter data emailed to the routed recipient, `Reply-To` only. |
| Submission storage | OFF by default. When a site turns it on: locked-down private CPT (no REST/search/front-end), retention auto-purge (default 90 days, reliability-hardened), per-record JSON export for DSARs. IPs stored only as salted hashes. |
| CAPTCHA | Turnstile default (reCAPTCHA selectable); provider script loads only on pages with the form; classified strictly-necessary anti-fraud — sites can disable by unsetting the provider. Secrets in wp-config, never the DB. |
| Webhooks | HMAC-signed, secret in wp-config; delivery logs never contain payload PII. |

## What EACH SITE must do (the offloaded compliance piece)

1. **Install + configure a consent plugin** (one that speaks the WP Consent
   API, or wire `salt_consent_state` glue in the child theme). Without one,
   analytics stays cookieless/denied and maps/embeds/booking facades will
   not load third-party assets — by design.
2. **Publish a privacy policy** and set it in Site Settings → Contact form.
   Content is the site's: name the processors actually enabled (GA4 →
   Google; PostHog + its region; Turnstile → Cloudflare; Calendly/Cal.com;
   the map tile provider), retention numbers (mirror the submissions
   setting), and DSAR contact.
3. **Records of processing / DPAs** (UK-GDPR Art. 28/30): each toggle a
   site enables adds a processor — the site's operator records it. salt's
   per-integration options screens name the recipient to record.
4. **Retention truthfulness**: if storage is on, the purge health notice in
   wp-admin must be green before quoting the retention number in the policy.
5. **DSARs**: export via the submission row action; erasure via native
   delete; check the mail provider's own retention separately.

## Non-negotiables that survive regardless of plugin choice

No third-party asset loads without either affirmative consent (via the
bridge) or an explicit user click on a facade; the consent bridge cannot be
filtered into a silent allow (strict-true checks); CAPTCHA/webhook secrets
never enter the database.
