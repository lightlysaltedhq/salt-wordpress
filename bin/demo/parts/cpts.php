<?php
/**
 * Demo CPT content for the salt seeder — "Northbeam Studio".
 *
 * PURE data: a nested array of the six content CPTs plus core blog posts.
 * The runner creates each object then writes 'fields' via update_field() by
 * NAME, assigns 'terms' per taxonomy, and resolves ref:/url: cross-link
 * tokens after all objects exist. Field/sub-field names here are validated
 * against the registered ACF schema by tests/unit/DemoContentTest.php.
 *
 * @package Salt\Demo
 */

declare(strict_types=1);

return array(

	// -- Services (6) -------------------------------------------------------
	'service'     => array(
		array(
			'slug'       => 'brand-identity',
			'title'      => 'Brand Identity',
			'menu_order' => 1,
			'content'    => '<p>Your brand is the sum of every impression you make — the logo, the tone, the way a page feels the moment it loads. We build identity systems that hold together across a business card and a billboard, giving your team the confidence to grow without second-guessing.</p><p>Every engagement starts with the strategy: who you serve, what you stand for, and why anyone should care. From there we craft the marks, palettes, type and voice that make it real, and hand it all over as guidelines your team can actually use.</p><h3>What you walk away with</h3><ul><li>A positioning line and messaging framework</li><li>A primary logo, responsive variants and favicon set</li><li>Colour, typography and layout guidelines</li><li>A shareable brand book (PDF + Figma)</li></ul>',
			'excerpt'    => 'Positioning, identity systems and brand guidelines that scale with you.',
			'thumbnail'  => 'ref:image/service-brand-identity',
			'terms'      => array(
				'service_category' => array( 'ref:term/service_category/strategy' ),
			),
			'fields'     => array(
				'icon'             => 'star',
				'summary'          => 'Positioning and identity systems that make you unmistakable.',
				'headline'         => 'Brand identity that earns a second look',
				'benefits'         => array(
					array( 'benefit' => 'A clear, defensible market position' ),
					array( 'benefit' => 'A cohesive visual system across every touchpoint' ),
					array( 'benefit' => 'Guidelines your whole team can apply confidently' ),
				),
				'price_from'       => 6000,
				'cta'              => array(
					'title'  => 'Discuss your brand',
					'url'    => 'url:page/contact',
					'target' => '',
				),
				'related_services' => array(
					'ref:service/web-design',
					'ref:service/ux-research',
				),
			),
		),
		array(
			'slug'       => 'web-design',
			'title'      => 'Web Design',
			'menu_order' => 2,
			'content'    => '<p>A website should feel effortless to use and impossible to ignore. We design responsive, accessible interfaces that put your message first and guide people towards the action that matters — without the clutter that slows everyone down.</p><p>We work in the browser early, testing real content on real screens rather than polishing pixels in a vacuum. The result is a design system your developers can build fast and your marketers can extend for years.</p><h3>How we work</h3><ol><li>Content and structure before decoration</li><li>Mobile-first layouts, designed to scale up</li><li>A reusable component library in Figma</li></ol>',
			'excerpt'    => 'Responsive, accessible interfaces designed around your content and goals.',
			'thumbnail'  => 'ref:image/service-web-design',
			'terms'      => array(
				'service_category' => array( 'ref:term/service_category/design' ),
			),
			'fields'     => array(
				'icon'             => 'star',
				'summary'          => 'Accessible, conversion-focused interfaces built as a design system.',
				'headline'         => 'Web design that works as hard as you do',
				'benefits'         => array(
					array( 'benefit' => 'Interfaces designed around your users, not trends' ),
					array( 'benefit' => 'A component library that keeps you consistent' ),
					array( 'benefit' => 'Accessibility baked in from the first frame' ),
				),
				'price_from'       => 8000,
				'cta'              => array(
					'title'  => 'Start a design project',
					'url'    => 'url:page/contact',
					'target' => '',
				),
				'related_services' => array(
					'ref:service/brand-identity',
					'ref:service/web-development',
				),
			),
		),
		array(
			'slug'       => 'web-development',
			'title'      => 'Web Development',
			'menu_order' => 3,
			'content'    => '<p>We build fast, standards-based websites that hold up under real traffic and are a pleasure to maintain. Whether it is a marketing site on WordPress or a headless front end, we write clean, well-documented code your team can trust.</p><p>Performance and accessibility are not afterthoughts — they are how we measure the work. Every build ships with sensible defaults for Core Web Vitals, semantic markup and progressive enhancement, so the experience stays solid on any device.</p><h3>Under the hood</h3><ul><li>Component-driven templates and reusable blocks</li><li>Performance budgets and Core Web Vitals targets</li><li>Editor-friendly content models</li></ul>',
			'excerpt'    => 'Fast, standards-based builds that are a pleasure to maintain.',
			'thumbnail'  => 'ref:image/service-web-development',
			'terms'      => array(
				'service_category' => array( 'ref:term/service_category/build' ),
			),
			'fields'     => array(
				'icon'             => 'check',
				'summary'          => 'Clean, performant builds with accessibility and speed built in.',
				'headline'         => 'Web development you can build on',
				'benefits'         => array(
					array( 'benefit' => 'Fast page loads and strong Core Web Vitals' ),
					array( 'benefit' => 'Maintainable, well-documented code' ),
					array( 'benefit' => 'An editing experience your team will actually enjoy' ),
				),
				'price_from'       => 10000,
				'cta'              => array(
					'title'  => 'Scope your build',
					'url'    => 'url:page/contact',
					'target' => '',
				),
				'related_services' => array(
					'ref:service/web-design',
					'ref:service/ecommerce',
				),
			),
		),
		array(
			'slug'       => 'seo-content',
			'title'      => 'SEO & Content',
			'menu_order' => 4,
			'content'    => '<p>Great sites deserve to be found. We combine technical SEO, content strategy and clear writing to grow the traffic that actually converts — not vanity numbers that never turn into customers.</p><p>We start with the queries your buyers really use, map them to the pages that answer them, and fix the technical debt holding you back. Then we help you publish content worth ranking for and measure what it earns you.</p><h3>Where we focus</h3><ul><li>Keyword and intent research grounded in your funnel</li><li>Technical audits and Core Web Vitals fixes</li><li>Editorial calendars and on-page optimisation</li></ul>',
			'excerpt'    => 'Technical SEO and content strategy that grows traffic that converts.',
			'thumbnail'  => 'ref:image/service-seo-content',
			'terms'      => array(
				'service_category' => array( 'ref:term/service_category/growth' ),
			),
			'fields'     => array(
				'icon'             => 'arrow-right',
				'summary'          => 'Technical SEO and content that grows qualified traffic.',
				'headline'         => 'SEO and content that pull their weight',
				'benefits'         => array(
					array( 'benefit' => 'Rankings for the terms your buyers actually search' ),
					array( 'benefit' => 'A technical foundation search engines reward' ),
					array( 'benefit' => 'Content mapped to every stage of your funnel' ),
				),
				'price_from'       => 3000,
				'cta'              => array(
					'title'  => 'Grow your traffic',
					'url'    => 'url:page/contact',
					'target' => '',
				),
				'related_services' => array(
					'ref:service/web-development',
					'ref:service/ux-research',
				),
			),
		),
		array(
			'slug'       => 'ecommerce',
			'title'      => 'E-commerce',
			'menu_order' => 5,
			'content'    => '<p>We design and build online stores that turn browsers into buyers. From product discovery to checkout, every step is measured, tested and tuned to remove friction and lift revenue.</p><p>Whether you sell ten products or ten thousand, we build a storefront that is fast, trustworthy and easy to manage. We integrate the payment, shipping and inventory tools you already rely on, so launch day is the start of growth, not a scramble.</p><h3>Built to sell</h3><ul><li>Conversion-optimised product and checkout flows</li><li>Integrations for payments, shipping and stock</li><li>Merchandising tools your team controls</li></ul>',
			'excerpt'    => 'Online stores engineered to turn browsers into buyers.',
			'thumbnail'  => 'ref:image/service-ecommerce',
			'terms'      => array(
				'service_category' => array( 'ref:term/service_category/build' ),
			),
			'fields'     => array(
				'icon'             => 'check',
				'summary'          => 'Conversion-optimised storefronts built to grow revenue.',
				'headline'         => 'E-commerce built to convert',
				'benefits'         => array(
					array( 'benefit' => 'Checkout flows tuned to reduce drop-off' ),
					array( 'benefit' => 'Integrations for payments, shipping and stock' ),
					array( 'benefit' => 'A storefront your team can merchandise without us' ),
				),
				'price_from'       => 12000,
				'cta'              => array(
					'title'  => 'Plan your store',
					'url'    => 'url:page/contact',
					'target' => '',
				),
				'related_services' => array(
					'ref:service/web-development',
					'ref:service/web-design',
				),
			),
		),
		array(
			'slug'       => 'ux-research',
			'title'      => 'UX Research',
			'menu_order' => 6,
			'content'    => '<p>The best design decisions are the ones backed by evidence. We run pragmatic research — interviews, usability testing and analytics review — so you invest in the changes that move the numbers, not the ones that sound good in a meeting.</p><p>We keep research lightweight and continuous, feeding insights straight into design and build rather than parking them in a report nobody reads. You get a clear picture of where users struggle and a prioritised list of what to fix first.</p><h3>Our toolkit</h3><ul><li>User interviews and moderated usability tests</li><li>Journey mapping and behavioural analytics</li><li>Prioritised, actionable recommendations</li></ul>',
			'excerpt'    => 'Pragmatic research that points your investment at what actually moves numbers.',
			'thumbnail'  => 'ref:image/service-ux-research',
			'terms'      => array(
				'service_category' => array( 'ref:term/service_category/strategy' ),
			),
			'fields'     => array(
				'icon'             => 'star',
				'summary'          => 'Evidence-led research that de-risks your design decisions.',
				'headline'         => 'UX research that pays for itself',
				'benefits'         => array(
					array( 'benefit' => 'Decisions grounded in real user behaviour' ),
					array( 'benefit' => 'A prioritised roadmap of what to fix first' ),
					array( 'benefit' => 'Continuous insight, not a report left on a shelf' ),
				),
				'price_from'       => 4000,
				'cta'              => array(
					'title'  => 'Talk to a researcher',
					'url'    => 'url:page/contact',
					'target' => '',
				),
				'related_services' => array(
					'ref:service/web-design',
					'ref:service/seo-content',
				),
			),
		),
	),

	// -- Testimonials (5) ---------------------------------------------------
	'testimonial' => array(
		array(
			'slug'      => 't1',
			'title'     => 'Claire Donovan',
			'thumbnail' => 'ref:image/team-amelia',
			'fields'    => array(
				'quote'   => 'Northbeam reframed our whole identity in a way that finally felt like us. Six months on, our sales team leads with the new positioning and it lands every time.',
				'role'    => 'Marketing Director',
				'company' => 'Harborview',
				'rating'  => 5,
				'service' => array( 'ref:service/brand-identity' ),
			),
		),
		array(
			'slug'      => 't2',
			'title'     => 'James Whitfield',
			'thumbnail' => 'ref:image/team-marcus',
			'fields'    => array(
				'quote'   => 'The new site is faster, clearer and far easier for us to update. Our team ships landing pages in an afternoon now instead of waiting a fortnight.',
				'role'    => 'Founder',
				'company' => 'Meridian',
				'rating'  => 5,
				'service' => array( 'ref:service/web-design' ),
			),
		),
		array(
			'slug'      => 't3',
			'title'     => 'Anita Rao',
			'thumbnail' => 'ref:image/team-priya',
			'fields'    => array(
				'quote'   => 'They rebuilt our platform without a single day of downtime, and our Core Web Vitals went from red to green. Engineering finally trusts the front end.',
				'role'    => 'VP Engineering',
				'company' => 'Atlas',
				'rating'  => 5,
				'service' => array( 'ref:service/web-development' ),
			),
		),
		array(
			'slug'      => 't4',
			'title'     => 'Daniel Reyes',
			'thumbnail' => 'ref:image/team-tom',
			'fields'    => array(
				'quote'   => 'Our organic traffic nearly doubled within two quarters. Northbeam did not just chase keywords — they fixed the plumbing first, then built content that ranks.',
				'role'    => 'Growth Lead',
				'company' => 'Verdant',
				'rating'  => 4,
				'service' => array( 'ref:service/seo-content' ),
			),
		),
		array(
			'slug'      => 't5',
			'title'     => 'Hannah Croft',
			'thumbnail' => 'ref:image/team-sofia',
			'fields'    => array(
				'quote'   => 'The research phase saved us from building the wrong thing. We launched a booking flow patients actually complete, and no-shows dropped noticeably.',
				'role'    => 'Practice Manager',
				'company' => 'Northside Clinic',
				'rating'  => 5,
				'service' => array( 'ref:service/ux-research' ),
			),
		),
	),

	// -- Team members (5) ---------------------------------------------------
	'team_member' => array(
		array(
			'slug'       => 'amelia',
			'title'      => 'Amelia Hart',
			'menu_order' => 1,
			'content'    => '<p>Amelia founded Northbeam in 2016 after a decade leading brand and product teams at agencies across London and Berlin. She still sits in on every kick-off, convinced that the best work starts with the sharpest questions.</p><p>Outside the studio she mentors early-career designers and is a reliably strong contender at the office table-tennis ladder.</p>',
			'thumbnail'  => 'ref:image/team-amelia',
			'terms'      => array(
				'department' => array( 'ref:term/department/leadership' ),
			),
			'fields'     => array(
				'role'      => 'Founder & Creative Director',
				'short_bio' => 'Founder and creative director; ten years leading brand and product teams before Northbeam.',
				'email'     => 'amelia@northbeam.studio',
				'phone'     => '+44 20 7946 0124',
				'socials'   => array(
					array(
						'platform' => 'linkedin',
						'url'      => 'https://www.linkedin.com/in/amelia-hart',
					),
					array(
						'platform' => 'x',
						'url'      => 'https://x.com/ameliahart',
					),
				),
			),
		),
		array(
			'slug'       => 'marcus',
			'title'      => 'Marcus Bell',
			'menu_order' => 2,
			'content'    => '<p>Marcus leads design at Northbeam, turning fuzzy briefs into clear, usable systems. He is happiest three prototypes deep, testing an idea with real users before a single line of production code is written.</p><p>He previously designed products in fintech and healthtech, and brings a healthy obsession with accessibility to everything the team ships.</p>',
			'thumbnail'  => 'ref:image/team-marcus',
			'terms'      => array(
				'department' => array( 'ref:term/department/design' ),
			),
			'fields'     => array(
				'role'      => 'Design Lead',
				'short_bio' => 'Leads design; a systems thinker with a healthy obsession for accessibility.',
				'email'     => 'marcus@northbeam.studio',
				'phone'     => '+44 20 7946 0125',
				'socials'   => array(
					array(
						'platform' => 'linkedin',
						'url'      => 'https://www.linkedin.com/in/marcus-bell',
					),
				),
			),
		),
		array(
			'slug'       => 'priya',
			'title'      => 'Priya Nadar',
			'menu_order' => 3,
			'content'    => '<p>Priya heads engineering, keeping Northbeam builds fast, secure and maintainable. She champions performance budgets and progressive enhancement, and has never met a Lighthouse score she could not improve.</p><p>She contributes to several open-source WordPress projects and runs the studio&rsquo;s internal code-review guild.</p>',
			'thumbnail'  => 'ref:image/team-priya',
			'terms'      => array(
				'department' => array( 'ref:term/department/engineering' ),
			),
			'fields'     => array(
				'role'      => 'Engineering Lead',
				'short_bio' => 'Heads engineering; champion of performance budgets and clean, maintainable code.',
				'email'     => 'priya@northbeam.studio',
				'phone'     => '+44 20 7946 0126',
				'socials'   => array(
					array(
						'platform' => 'linkedin',
						'url'      => 'https://www.linkedin.com/in/priya-nadar',
					),
					array(
						'platform' => 'other',
						'url'      => 'https://github.com/priyanadar',
					),
				),
			),
		),
		array(
			'slug'       => 'tom',
			'title'      => 'Tom Okafor',
			'menu_order' => 4,
			'content'    => '<p>Tom runs growth at Northbeam, connecting design and content to the numbers that matter. He treats SEO as an engineering problem first and a writing problem second, and has the traffic charts to prove it works.</p><p>Before Northbeam he built and sold a small analytics tool, an experience that still shapes how he thinks about measurement.</p>',
			'thumbnail'  => 'ref:image/team-tom',
			'terms'      => array(
				'department' => array( 'ref:term/department/growth' ),
			),
			'fields'     => array(
				'role'      => 'Growth Lead',
				'short_bio' => 'Runs growth and SEO; treats search as an engineering problem first.',
				'email'     => 'tom@northbeam.studio',
				'phone'     => '+44 161 496 0127',
				'socials'   => array(
					array(
						'platform' => 'linkedin',
						'url'      => 'https://www.linkedin.com/in/tom-okafor',
					),
					array(
						'platform' => 'x',
						'url'      => 'https://x.com/tomokafor',
					),
				),
			),
		),
		array(
			'slug'       => 'sofia',
			'title'      => 'Sofia Lindqvist',
			'menu_order' => 5,
			'content'    => '<p>Sofia leads UX research, making sure Northbeam builds the right thing before building it well. She has a talent for getting honest feedback from users and translating it into decisions the whole team can rally behind.</p><p>She holds a masters in human-computer interaction and speaks four languages, which comes in handy when the research goes international.</p>',
			'thumbnail'  => 'ref:image/team-sofia',
			'terms'      => array(
				'department' => array( 'ref:term/department/design' ),
			),
			'fields'     => array(
				'role'      => 'UX Research Lead',
				'short_bio' => 'Leads UX research; turns honest user feedback into decisions teams rally behind.',
				'email'     => 'sofia@northbeam.studio',
				'phone'     => '+44 20 7946 0128',
				'socials'   => array(
					array(
						'platform' => 'linkedin',
						'url'      => 'https://www.linkedin.com/in/sofia-lindqvist',
					),
				),
			),
		),
	),

	// -- Work / case studies (5) --------------------------------------------
	'work'        => array(
		array(
			'slug'      => 'harborview',
			'title'     => 'Harborview Rebrand',
			'excerpt'   => 'A full identity system for a coastal property group ready to move upmarket.',
			'content'   => '<p>Harborview had outgrown a logo it designed itself a decade earlier. We rebuilt the brand from positioning outwards, giving a growing property group the confidence to compete at the premium end of its market.</p>',
			'thumbnail' => 'ref:image/work-harborview',
			'terms'     => array(
				'work_type' => array( 'ref:term/work_type/brand' ),
				'industry'  => array( 'ref:term/industry/hospitality' ),
			),
			'fields'    => array(
				'client'    => 'Harborview',
				'summary'   => 'A positioning-led rebrand and identity system for a premium coastal property group.',
				'gallery'   => array(
					'ref:image/work-harborview-g1',
					'ref:image/work-harborview-g2',
				),
				'challenge' => '<p>Harborview was winning higher-value clients but still looked like the local agency it started as. The identity was inconsistent across signage, print and web, and the team had no guidelines to keep new material on-brand.</p>',
				'solution'  => '<p>We ran a positioning workshop, then designed a flexible identity system — a refined wordmark, a considered palette and a photographic style that felt genuinely premium. Everything shipped with clear guidelines and a template kit the in-house team could run with.</p>',
				'results'   => array(
					array(
						'stat'  => '+38%',
						'label' => 'average deal value',
					),
					array(
						'stat'  => '3 weeks',
						'label' => 'to full rollout',
					),
					array(
						'stat'  => '100%',
						'label' => 'brand-consistent touchpoints',
					),
				),
				'services'  => array(
					'ref:service/brand-identity',
					'ref:service/web-design',
				),
				'live_url'  => 'https://harborview.example.com',
			),
		),
		array(
			'slug'      => 'meridian',
			'title'     => 'Meridian Marketing Site',
			'excerpt'   => 'A fast, self-serve marketing site that lets a lean team ship pages in hours.',
			'content'   => '<p>Meridian needed a marketing site their small team could actually run. We designed and built a flexible block library that turns a fortnight of dev tickets into an afternoon of self-serve editing.</p>',
			'thumbnail' => 'ref:image/work-meridian',
			'terms'     => array(
				'work_type' => array( 'ref:term/work_type/web' ),
				'industry'  => array( 'ref:term/industry/saas' ),
			),
			'fields'    => array(
				'client'    => 'Meridian',
				'summary'   => 'A component-driven marketing site that lets a lean SaaS team publish pages in hours.',
				'gallery'   => array(
					'ref:image/work-meridian',
				),
				'challenge' => '<p>Every new campaign page meant a developer ticket and a two-week wait. Marketing was bottlenecked, and the old site was slow enough to hurt conversions.</p>',
				'solution'  => '<p>We built a library of flexible, on-brand sections the marketing team assembles themselves. Pages load fast, stay consistent, and no longer need engineering to go live.</p>',
				'results'   => array(
					array(
						'stat'  => '10x',
						'label' => 'faster page publishing',
					),
					array(
						'stat'  => '-45%',
						'label' => 'largest contentful paint',
					),
					array(
						'stat'  => '+22%',
						'label' => 'demo sign-ups',
					),
				),
				'services'  => array(
					'ref:service/web-design',
					'ref:service/web-development',
				),
				'live_url'  => 'https://meridian.example.com',
			),
		),
		array(
			'slug'      => 'atlas',
			'title'     => 'Atlas Platform Rebuild',
			'excerpt'   => 'A zero-downtime front-end rebuild that turned red vitals green.',
			'content'   => '<p>Atlas ran a capable product on a front end that had aged badly. We rebuilt it component by component, with no downtime, and handed engineering a codebase they trust again.</p>',
			'thumbnail' => 'ref:image/work-atlas',
			'terms'     => array(
				'work_type' => array( 'ref:term/work_type/web' ),
				'industry'  => array( 'ref:term/industry/saas' ),
			),
			'fields'    => array(
				'client'    => 'Atlas',
				'summary'   => 'A phased, zero-downtime front-end rebuild that fixed performance and developer trust.',
				'gallery'   => array(
					'ref:image/work-atlas-g1',
					'ref:image/work-atlas-g2',
				),
				'challenge' => '<p>Years of quick fixes had left the Atlas front end slow and fragile. Core Web Vitals were firmly in the red and every change risked breaking something else.</p>',
				'solution'  => '<p>We migrated the interface to a component-driven architecture in phases, shipping continuously with no downtime. Each release came with performance budgets and tests, so the numbers improved and stayed improved.</p>',
				'results'   => array(
					array(
						'stat'  => '0',
						'label' => 'minutes of downtime',
					),
					array(
						'stat'  => '92',
						'label' => 'Lighthouse performance',
					),
					array(
						'stat'  => '-60%',
						'label' => 'time to interactive',
					),
				),
				'services'  => array(
					'ref:service/web-development',
					'ref:service/ux-research',
				),
				'live_url'  => 'https://atlas.example.com',
			),
		),
		array(
			'slug'      => 'verdant',
			'title'     => 'Verdant Content Growth',
			'excerpt'   => 'An SEO and content programme that nearly doubled organic traffic.',
			'content'   => '<p>Verdant had good products and almost no organic reach. We fixed the technical foundations, then built a content engine that now brings in qualified traffic month after month.</p>',
			'thumbnail' => 'ref:image/work-verdant',
			'terms'     => array(
				'work_type' => array( 'ref:term/work_type/web' ),
				'industry'  => array( 'ref:term/industry/retail' ),
			),
			'fields'    => array(
				'client'    => 'Verdant',
				'summary'   => 'A technical-SEO and content programme that nearly doubled qualified organic traffic.',
				'gallery'   => array(
					'ref:image/work-verdant',
				),
				'challenge' => '<p>Verdant relied almost entirely on paid acquisition. Organic traffic was flat, the site had crawl and speed issues, and no one owned content strategy.</p>',
				'solution'  => '<p>We audited and fixed the technical debt, mapped high-intent queries to a content plan, and helped the team publish articles worth ranking for. Then we measured what each one earned.</p>',
				'results'   => array(
					array(
						'stat'  => '+94%',
						'label' => 'organic traffic',
					),
					array(
						'stat'  => '+31%',
						'label' => 'organic revenue',
					),
					array(
						'stat'  => '2 quarters',
						'label' => 'to break even on spend',
					),
				),
				'services'  => array(
					'ref:service/seo-content',
					'ref:service/web-development',
				),
				'live_url'  => 'https://verdant.example.com',
			),
		),
		array(
			'slug'      => 'northside',
			'title'     => 'Northside Clinic Booking',
			'excerpt'   => 'A research-led booking flow that patients actually finish.',
			'content'   => '<p>Northside Clinic was losing patients at the booking stage. We researched where the flow broke down, redesigned it around real behaviour, and built something people complete on the first try.</p>',
			'thumbnail' => 'ref:image/work-northside',
			'terms'     => array(
				'work_type' => array( 'ref:term/work_type/web' ),
				'industry'  => array( 'ref:term/industry/healthcare' ),
			),
			'fields'    => array(
				'client'    => 'Northside Clinic',
				'summary'   => 'A research-led redesign of an online booking flow that lifted completion and cut no-shows.',
				'gallery'   => array(
					'ref:image/work-northside',
				),
				'challenge' => '<p>Patients started booking online but abandoned partway through a confusing, multi-step form. Reception was swamped with phone bookings and no-shows were high.</p>',
				'solution'  => '<p>We interviewed patients and staff, watched real sessions, and rebuilt the flow around how people actually book. Clear steps, sensible defaults and honest confirmation copy did the rest.</p>',
				'results'   => array(
					array(
						'stat'  => '+41%',
						'label' => 'booking completion',
					),
					array(
						'stat'  => '-27%',
						'label' => 'no-show rate',
					),
					array(
						'stat'  => '-35%',
						'label' => 'reception call volume',
					),
				),
				'services'  => array(
					'ref:service/ux-research',
					'ref:service/web-design',
				),
				'live_url'  => 'https://northside-clinic.example.com',
			),
		),
	),

	// -- FAQs (8) -----------------------------------------------------------
	'faq'         => array(
		array(
			'slug'       => 'faq1',
			'title'      => 'What kind of clients do you work with?',
			'menu_order' => 1,
			'terms'      => array(
				'faq_category' => array( 'ref:term/faq_category/general' ),
			),
			'fields'     => array(
				'answer' => '<p>We work best with ambitious small and mid-sized businesses — typically SaaS, retail, healthcare and hospitality brands — that have outgrown their current site or identity. If you have a real product and a reason to invest in growth, we are probably a good fit.</p>',
			),
		),
		array(
			'slug'       => 'faq2',
			'title'      => 'How much does a project cost?',
			'menu_order' => 2,
			'terms'      => array(
				'faq_category' => array( 'ref:term/faq_category/pricing' ),
			),
			'fields'     => array(
				'answer' => '<p>Every project is scoped to its goals, but as a guide: brand work starts around <strong>&pound;6,000</strong>, website design and build from <strong>&pound;8,000&ndash;&pound;20,000</strong>, and ongoing SEO and content from <strong>&pound;3,000</strong> a month. We will always give you a clear, fixed proposal before any work begins.</p>',
			),
		),
		array(
			'slug'       => 'faq3',
			'title'      => 'How long does a typical website take?',
			'menu_order' => 3,
			'terms'      => array(
				'faq_category' => array( 'ref:term/faq_category/process' ),
			),
			'fields'     => array(
				'answer' => '<p>Most marketing sites run <strong>eight to twelve weeks</strong> from kick-off to launch. Larger platforms or e-commerce builds can take longer. We work in visible stages, so you see progress every couple of weeks rather than waiting for a big reveal at the end.</p>',
			),
		),
		array(
			'slug'       => 'faq4',
			'title'      => 'What does your process look like?',
			'menu_order' => 4,
			'terms'      => array(
				'faq_category' => array( 'ref:term/faq_category/process' ),
			),
			'fields'     => array(
				'answer' => '<p>We run four phases: <strong>discover</strong>, <strong>design</strong>, <strong>build</strong> and <strong>grow</strong>. Each ends in something concrete you can react to — a strategy, a prototype, a staging site, a results review — so there are no surprises and you stay in control throughout.</p>',
			),
		),
		array(
			'slug'       => 'faq5',
			'title'      => 'Which platforms do you build on?',
			'menu_order' => 5,
			'terms'      => array(
				'faq_category' => array( 'ref:term/faq_category/general' ),
			),
			'fields'     => array(
				'answer' => '<p>We specialise in WordPress and headless front ends, and we build e-commerce on platforms like WooCommerce and Shopify. We choose the tool that fits your team, not the one that fits our habits.</p>',
			),
		),
		array(
			'slug'       => 'faq6',
			'title'      => 'Do you offer ongoing support after launch?',
			'menu_order' => 6,
			'terms'      => array(
				'faq_category' => array( 'ref:term/faq_category/support' ),
			),
			'fields'     => array(
				'answer' => '<p>Yes. Most clients stay on a support and care plan covering updates, monitoring, backups and small changes. We also offer growth retainers for teams that want us to keep improving performance and content over time.</p>',
			),
		),
		array(
			'slug'       => 'faq7',
			'title'      => 'Can you work with our in-house team?',
			'menu_order' => 7,
			'terms'      => array(
				'faq_category' => array( 'ref:term/faq_category/process' ),
			),
			'fields'     => array(
				'answer' => '<p>Absolutely. We often embed alongside in-house designers, developers and marketers, sharing our systems and documentation so your team can take the work forward confidently once we hand over.</p>',
			),
		),
		array(
			'slug'       => 'faq8',
			'title'      => 'How do we get started?',
			'menu_order' => 8,
			'terms'      => array(
				'faq_category' => array( 'ref:term/faq_category/general' ),
			),
			'fields'     => array(
				'answer' => '<p>Get in touch through our <a href="url:page/contact">contact page</a> and tell us a little about your goals. We will set up a short call, and if it feels like a fit, follow up with a clear proposal and timeline.</p>',
			),
		),
	),

	// -- Locations (2) ------------------------------------------------------
	'location'    => array(
		array(
			'slug'       => 'london',
			'title'      => 'London Studio',
			'menu_order' => 1,
			'thumbnail'  => 'ref:image/hero-home',
			'terms'      => array(
				'location_area' => array( 'ref:term/location_area/uk' ),
			),
			'fields'     => array(
				'address'       => array(
					'line1'    => '48 Rivington Street',
					'line2'    => 'Shoreditch',
					'city'     => 'London',
					'region'   => 'Greater London',
					'postcode' => 'EC2A 3QP',
					'country'  => 'United Kingdom',
				),
				'geo'           => '51.5265,-0.0800',
				'phone'         => '+44 20 7946 0123',
				'email'         => 'london@northbeam.studio',
				'opening_hours' => array(
					array(
						'day'              => 'monday',
						'open'             => '09:00',
						'close'            => '18:00',
						'appointment_only' => 0,
					),
					array(
						'day'              => 'tuesday',
						'open'             => '09:00',
						'close'            => '18:00',
						'appointment_only' => 0,
					),
					array(
						'day'              => 'wednesday',
						'open'             => '09:00',
						'close'            => '18:00',
						'appointment_only' => 0,
					),
					array(
						'day'              => 'thursday',
						'open'             => '09:00',
						'close'            => '18:00',
						'appointment_only' => 0,
					),
					array(
						'day'              => 'friday',
						'open'             => '09:00',
						'close'            => '18:00',
						'appointment_only' => 0,
					),
					array(
						'day'              => 'saturday',
						'open'             => '',
						'close'            => '',
						'appointment_only' => 1,
					),
				),
			),
		),
		array(
			'slug'       => 'manchester',
			'title'      => 'Manchester Studio',
			'menu_order' => 2,
			'thumbnail'  => 'ref:image/hero-team',
			'terms'      => array(
				'location_area' => array( 'ref:term/location_area/uk' ),
			),
			'fields'     => array(
				'address'       => array(
					'line1'    => '12 Tariff Street',
					'line2'    => 'Northern Quarter',
					'city'     => 'Manchester',
					'region'   => 'Greater Manchester',
					'postcode' => 'M1 2FF',
					'country'  => 'United Kingdom',
				),
				'geo'           => '53.4830,-2.2320',
				'phone'         => '+44 161 496 0127',
				'email'         => 'manchester@northbeam.studio',
				'opening_hours' => array(
					array(
						'day'              => 'monday',
						'open'             => '09:00',
						'close'            => '17:30',
						'appointment_only' => 0,
					),
					array(
						'day'              => 'tuesday',
						'open'             => '09:00',
						'close'            => '17:30',
						'appointment_only' => 0,
					),
					array(
						'day'              => 'wednesday',
						'open'             => '09:00',
						'close'            => '17:30',
						'appointment_only' => 0,
					),
					array(
						'day'              => 'thursday',
						'open'             => '09:00',
						'close'            => '17:30',
						'appointment_only' => 0,
					),
					array(
						'day'              => 'friday',
						'open'             => '09:00',
						'close'            => '17:30',
						'appointment_only' => 0,
					),
				),
			),
		),
	),

	// -- Blog posts (5, core post — no ACF fields) --------------------------
	'post'        => array(
		array(
			'slug'      => 'post1',
			'title'     => 'Why we design in the browser, not in a mockup',
			'excerpt'   => 'Static mockups lie. Here is how designing with real content on real screens leads to better websites.',
			'thumbnail' => 'ref:image/blog-1',
			'terms'     => array(
				'category' => array( 'ref:term/category/insights' ),
			),
			'content'   => '<p>A polished mockup can hide a hundred problems. It shows the happy path — the perfect headline, the ideal image, the content that never runs long — and quietly ignores everything that happens on a real device with real data.</p><p>At Northbeam we move into the browser as early as we can. Designing with live content on actual screen sizes surfaces the awkward truths sooner, when they are cheap to fix.</p><h3>What changes when you design in the browser</h3><ul><li>Long titles and empty states stop being surprises</li><li>Responsive behaviour is designed, not retrofitted</li><li>Developers and designers speak the same language</li></ul><p>The result is a design that survives contact with reality — and a build that starts far closer to done.</p>',
		),
		array(
			'slug'      => 'post2',
			'title'     => 'A practical guide to Core Web Vitals',
			'excerpt'   => 'What the three Core Web Vitals actually measure, and the fixes that move them most.',
			'thumbnail' => 'ref:image/blog-2',
			'terms'     => array(
				'category' => array( 'ref:term/category/guides' ),
			),
			'content'   => '<p>Core Web Vitals sound intimidating, but they boil down to three simple questions: does the page load quickly, does it respond quickly, and does it stay still while it loads? Get those right and both Google and your users will thank you.</p><h3>The three metrics</h3><ol><li><strong>LCP</strong> — how fast the main content appears</li><li><strong>INP</strong> — how quickly the page responds to input</li><li><strong>CLS</strong> — how much the layout shifts unexpectedly</li></ol><p>The biggest wins are usually the least glamorous: right-sizing images, reserving space for media, and cutting the render-blocking scripts you forgot you added.</p><p>Measure with real-user data, not just lab tools, and fix the metric that is actually failing rather than chasing a perfect score.</p>',
		),
		array(
			'slug'      => 'post3',
			'title'     => 'How to run a rebrand without losing your nerve',
			'excerpt'   => 'A rebrand is daunting. A clear process turns it from a gamble into a considered upgrade.',
			'thumbnail' => 'ref:image/blog-3',
			'terms'     => array(
				'category' => array( 'ref:term/category/insights' ),
			),
			'content'   => '<p>Rebrands go wrong when they start with a logo and end in an argument. They go right when they start with a question: who are we for, and why should they choose us?</p><p>We treat every rebrand as a strategy project first and a design project second. Once the positioning is clear, the visual decisions stop being matters of taste and start being answers to a brief.</p><h3>A calmer way through</h3><ul><li>Align on positioning before anyone opens a design tool</li><li>Test the new direction on real touchpoints, not just a hero image</li><li>Ship guidelines so the brand survives after launch</li></ul><p>Done this way, a rebrand feels less like a leap of faith and more like finally putting on clothes that fit.</p>',
		),
		array(
			'slug'      => 'post4',
			'title'     => 'Accessibility is not a checklist',
			'excerpt'   => 'Treating accessibility as a compliance chore misses the point — and the opportunity.',
			'thumbnail' => 'ref:image/blog-4',
			'terms'     => array(
				'category' => array( 'ref:term/category/insights' ),
			),
			'content'   => '<p>It is tempting to treat accessibility as a box to tick just before launch. Run a scanner, fix the red flags, move on. But the sites that are genuinely usable for everyone are the ones that considered accessibility from the very first sketch.</p><p>Good contrast, clear focus states, sensible heading structure and honest link text are not constraints on design — they are design done well. They also happen to help everyone, not just users with disabilities.</p><h3>Where to start</h3><ul><li>Design with real contrast ratios, not hopeful greys</li><li>Make every interaction work with a keyboard</li><li>Write link text that means something out of context</li></ul><p>Build it in from the start and accessibility stops being a scramble and becomes simply how you work.</p>',
		),
		array(
			'slug'      => 'post5',
			'title'     => 'Is headless commerce right for you?',
			'excerpt'   => 'Headless is powerful and overhyped in equal measure. Here is how to tell if you need it.',
			'thumbnail' => 'ref:image/blog-5',
			'terms'     => array(
				'category' => array( 'ref:term/category/news' ),
			),
			'content'   => '<p>Headless commerce — separating your storefront from the platform that powers it — promises speed and total design freedom. For some businesses it is transformative. For others it is a lot of complexity in search of a problem.</p><p>The honest answer is that it depends on your ambitions and your team. Headless shines when you have unusual front-end needs, multiple channels, or the engineering capacity to own more of the stack.</p><h3>Signs you might benefit</h3><ul><li>Your storefront needs to do things off-the-shelf themes cannot</li><li>You are selling across web, app and other surfaces</li><li>You have developers ready to maintain it</li></ul><p>If none of those ring true, a well-built conventional store will serve you better — and cost you far less.</p>',
		),
	),
);
