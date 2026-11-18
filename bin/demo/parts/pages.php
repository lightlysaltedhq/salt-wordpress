<?php
/**
 * Demo-content part: the seven builder pages and their section stacks
 * (spec §6/§10). PURE data — no logic, no I/O — required by
 * bin/demo/data.php and drift-validated by tests/unit/DemoContentTest.php
 * (every acf_fc_layout + sub-field name is checked against the registered
 * ACF schema, so names are exact).
 *
 * Cross-links use the seeder's tokens (resolved after objects exist):
 *   ref:{cpt}/{slug}   ref:page/{slug}   ref:image/{suffix}
 *   ref:term/{tax}/{slug}   url:page/{slug}   url:archive/{cpt}
 *
 * §6 (one owner per URL): there are NO 'services' or 'work' builder pages —
 * CPT sections use view_all => 1 to link to the native archives via
 * url:archive/service and url:archive/work.
 *
 * Every section row carries the contract's section settings in its
 * `settings` group (spacing, tone, width, anchorId, divider);
 * backgrounds are varied for vertical rhythm.
 *
 * @package Salt\Demo
 */

declare(strict_types=1);

return array(

	// ---------------------------------------------------------------
	// HOME — the full showcase stack (front page).
	// ---------------------------------------------------------------
	'home'     => array(
		'title'      => 'Home',
		'front_page' => true,
		'sections'   => array(

			array(
				'acf_fc_layout' => 'hero',
				'variant'       => 'split',
				'eyebrow'       => 'Design & web studio',
				'heading'       => 'We build brands people remember',
				'subheading'    => 'Northbeam Studio is a London design and development team helping ambitious companies launch brands, websites and products that earn attention and keep it.',
				'image'         => 'ref:image/hero-home',
				'buttons'       => array(
					array(
						'link'  => array(
							'label'    => 'Start a project',
							'type'     => 'internal',
							'document' => 'ref:page/contact',
						),
						'style' => 'primary',
					),
					array(
						'link'  => array(
							'label' => 'See our work',
							'type'  => 'external',
							'url'   => 'url:archive/work',
						),
						'style' => 'secondary',
					),
				),
				'settings'      => array(
					'spacing'  => 'md',
					'tone'     => 'surface',
					'width'    => 'wide',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'logos',
				'heading'       => 'Trusted by teams building the next thing',
				'items'         => array(
					array(
						'image' => 'ref:image/client-1',
						'url'   => '',
						'name'  => 'Harborview',
					),
					array(
						'image' => 'ref:image/client-2',
						'url'   => '',
						'name'  => 'Meridian Health',
					),
					array(
						'image' => 'ref:image/client-3',
						'url'   => '',
						'name'  => 'Atlas Commerce',
					),
					array(
						'image' => 'ref:image/client-4',
						'url'   => '',
						'name'  => 'Verdant',
					),
					array(
						'image' => 'ref:image/client-5',
						'url'   => '',
						'name'  => 'Northside',
					),
				),
				'greyscale'     => 1,
				'settings'      => array(
					'spacing'  => 'md',
					'tone'     => 'surface-alt',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'services',
				'layout'        => 'grid',
				'columns'       => '3',
				'eyebrow'       => 'What we do',
				'heading'       => 'Services built around your goals',
				'view_all'      => 1,
				'source'        => 'manual',
				'manual'        => array(
					'ref:service/brand-identity',
					'ref:service/web-design',
					'ref:service/seo-content',
				),
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'stats',
				'heading'       => 'Numbers we are proud of',
				'columns'       => '4',
				'items'         => array(
					array(
						'value'  => '120',
						'suffix' => '+',
						'label'  => 'Projects shipped',
					),
					array(
						'value'  => '12',
						'suffix' => '',
						'label'  => 'Years in practice',
					),
					array(
						'value'  => '38',
						'suffix' => '%',
						'label'  => 'Average lift in conversions',
					),
					array(
						'value'  => '9',
						'suffix' => '/10',
						'label'  => 'Clients who return',
					),
				),
				'settings'      => array(
					'spacing'  => 'md',
					'tone'     => 'surface-inverse',
					'width'    => 'wide',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'media-text',
				'mode'          => 'single',
				'rows'          => array(
					array(
						'image'     => 'ref:image/about-story',
						'heading'   => 'A studio, not a factory',
						'content'   => '<p>We are a small senior team by design. The people who scope your project are the people who deliver it, so nothing gets lost between the pitch and the launch.</p><p>That means fewer handoffs, sharper decisions, and work we are genuinely proud to put our name to.</p>',
						'mediaSide' => 'left',
						'buttons'   => array(
							array(
								'link'  => array(
									'label'    => 'More about us',
									'type'     => 'internal',
									'document' => 'ref:page/about',
								),
								'style' => 'secondary',
							),
						),
					),
				),
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'testimonials',
				'layout'        => 'slider',
				'heading'       => 'Kind words',
				'eyebrow'       => 'Client stories',
				'source'        => 'default',
				'count'         => 6,
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface-alt',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'work',
				'eyebrow'       => 'Portfolio',
				'heading'       => 'Selected work',
				'columns'       => '2',
				'view_all'      => 1,
				'source'        => 'default',
				'count'         => 4,
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'wide',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout'  => 'process',
				'eyebrow'        => 'How we work',
				'heading'        => 'A clear path from idea to launch',
				'layout'         => 'timeline',
				'steps'          => array(
					array(
						'title' => 'Discover',
						'text'  => 'We dig into your goals, audience and market so every decision after this has a reason behind it.',
						'icon'  => 'search',
					),
					array(
						'title' => 'Define',
						'text'  => 'We shape the strategy, scope and success measures, then agree a plan you can hold us to.',
						'icon'  => 'compass',
					),
					array(
						'title' => 'Design',
						'text'  => 'We craft the brand and interface in the open, reviewing real screens rather than slides.',
						'icon'  => 'pen',
					),
					array(
						'title' => 'Build & launch',
						'text'  => 'We develop, test and ship — then stay close to measure, learn and improve.',
						'icon'  => 'rocket',
					),
				),
				'structuredData' => 0,
				'settings'       => array(
					'spacing'  => 'md',
					'tone'     => 'surface-alt',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'team',
				'eyebrow'       => 'The people',
				'heading'       => 'Meet the team',
				'columns'       => '4',
				'source'        => 'default',
				'count'         => 4,
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'wide',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout'  => 'faq',
				'eyebrow'        => 'Good to know',
				'heading'        => 'Common questions',
				'structuredData' => 1,
				'query'          => array(
					'mode'       => 'by-category',
					'categories' => array( 'ref:term/faq_category/general' ),
				),
				'settings'       => array(
					'spacing'  => 'lg',
					'tone'     => 'surface-alt',
					'width'    => 'narrow',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'call-to-action',
				'heading'       => 'Ready to start?',
				'body'          => '<p>Tell us what you are building. We will come back within a day with a few honest thoughts and next steps.</p>',
				'buttons'       => array(
					array(
						'link'  => array(
							'label'    => 'Start a project',
							'type'     => 'internal',
							'document' => 'ref:page/contact',
						),
						'style' => 'primary',
					),
					array(
						'link'  => array(
							'label' => 'See our work',
							'type'  => 'external',
							'url'   => 'url:archive/work',
						),
						'style' => 'ghost',
					),
				),
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'brand-tint',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),
		),
	),

	// ---------------------------------------------------------------
	// ABOUT — story, numbers, team, process, culture carousel.
	// ---------------------------------------------------------------
	'about'    => array(
		'title'    => 'About',
		'sections' => array(

			array(
				'acf_fc_layout' => 'rich-text',
				'eyebrow'       => 'About Northbeam',
				'heading'       => 'Design and technology in equal measure',
				'body'          => '<p>We started Northbeam because too much good work falls apart in the gap between design and build. We close that gap by keeping both under one roof and one senior team.</p>',
				'alignment'     => 'centre',
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'narrow',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'rich-text',
				'heading'       => 'Our story',
				'body'          => '<p>Northbeam Studio began in 2013 above a coffee roastery in Shoreditch, with two founders, one laptop each and a stubborn belief that brand and product should be built together.</p><p>A decade on, we are a team of designers, engineers and strategists working with founders, marketing leads and product teams across the UK and beyond. We have grown carefully — hiring for craft and character rather than headcount — so the studio still feels like the one we set out to build.</p><blockquote><p>Do fewer things, and do them properly.</p></blockquote><p>That principle shows up everywhere: in the projects we take on, the way we scope them, and the long relationships that follow. Most of our new work still comes from people we have worked with before.</p>',
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface-alt',
					'width'    => 'narrow',
					'anchorId' => 'story',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'stats',
				'heading'       => 'A studio by the numbers',
				'columns'       => '4',
				'items'         => array(
					array(
						'value'  => '2013',
						'suffix' => '',
						'label'  => 'Founded in Shoreditch',
					),
					array(
						'value'  => '18',
						'suffix' => '',
						'label'  => 'People in the studio',
					),
					array(
						'value'  => '120',
						'suffix' => '+',
						'label'  => 'Projects delivered',
					),
					array(
						'value'  => '70',
						'suffix' => '%',
						'label'  => 'Work from repeat clients',
					),
				),
				'settings'      => array(
					'spacing'  => 'md',
					'tone'     => 'surface-inverse',
					'width'    => 'wide',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'team',
				'eyebrow'       => 'The people',
				'heading'       => 'Everyone you will actually work with',
				'columns'       => '3',
				'source'        => 'default',
				'count'         => 0,
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'wide',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout'  => 'process',
				'eyebrow'        => 'How we work',
				'heading'        => 'A process built on fewer surprises',
				'layout'         => 'cards',
				'steps'          => array(
					array(
						'title' => 'Listen first',
						'text'  => 'Every engagement opens with questions, not answers. We want to understand the business before we touch a pixel.',
						'icon'  => 'ear',
					),
					array(
						'title' => 'Work in the open',
						'text'  => 'You see progress weekly on real screens, so feedback is cheap and course-corrections are early.',
						'icon'  => 'eye',
					),
					array(
						'title' => 'Ship and support',
						'text'  => 'Launch is a milestone, not the finish line. We stay on to measure, refine and hand over cleanly.',
						'icon'  => 'life-buoy',
					),
				),
				'structuredData' => 0,
				'settings'       => array(
					'spacing'  => 'md',
					'tone'     => 'surface-alt',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'scroll_carousel',
				'eyebrow'       => 'Studio life',
				'heading'       => 'What it is like to work with us',
				'card_type'     => 'generic',
				'cards'         => array(
					array(
						'image' => 'ref:image/team-amelia',
						'title' => 'Senior by default',
						'text'  => 'No junior teams learning on your budget. The people in the pitch are the people at the keyboard.',
						'role'  => '',
					),
					array(
						'image' => 'ref:image/team-marcus',
						'title' => 'Opinionated, not precious',
						'text'  => 'We will tell you what we think and why — then happily change our minds when the evidence says so.',
						'role'  => '',
					),
					array(
						'image' => 'ref:image/team-priya',
						'title' => 'Built to hand over',
						'text'  => 'Everything we make is documented and yours to own. No lock-in, no mystery, no hostage code.',
						'role'  => '',
					),
					array(
						'image' => 'ref:image/team-tom',
						'title' => 'In it for the long run',
						'text'  => 'The best measure of our work is whether clients come back. Most of them do.',
						'role'  => '',
					),
				),
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'wide',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'call-to-action',
				'heading'       => 'Like how we think?',
				'body'          => '<p>We would love to hear what you are working on. Start a conversation and we will take it from there.</p>',
				'buttons'       => array(
					array(
						'link'  => array(
							'label'    => 'Start a project',
							'type'     => 'internal',
							'document' => 'ref:page/contact',
						),
						'style' => 'primary',
					),
					array(
						'link'  => array(
							'label'    => 'Meet the team',
							'type'     => 'internal',
							'document' => 'ref:page/team',
						),
						'style' => 'secondary',
					),
				),
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'brand-tint',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),
		),
	),

	// ---------------------------------------------------------------
	// APPROACH — services as features, process, proof, CTA.
	// ---------------------------------------------------------------
	'approach' => array(
		'title'    => 'Our Approach',
		'sections' => array(

			array(
				'acf_fc_layout' => 'hero',
				'variant'       => 'full-bleed',
				'eyebrow'       => 'Our approach',
				'heading'       => 'A considered way of working',
				'subheading'    => 'Great outcomes are rarely an accident. Here is how we turn a brief into work that performs — and how we price it.',
				'buttons'       => array(
					array(
						'link'  => array(
							'label'    => 'Start a project',
							'type'     => 'internal',
							'document' => 'ref:page/contact',
						),
						'style' => 'primary',
					),
				),
				'settings'      => array(
					'spacing'         => 'md',
					'tone'            => 'surface',
					'width'           => 'full',
					'anchorId'        => '',
					'divider'         => 0,
					'backgroundImage' => array( 'image' => 'ref:image/hero-approach' ),
				),
			),

			array(
				'acf_fc_layout' => 'features',
				'eyebrow'       => 'Capabilities',
				'heading'       => 'Everything you need in one team',
				'columns'       => '3',
				'items'         => array(
					array(
						'icon'  => 'sparkles',
						'title' => 'Brand identity',
						'text'  => 'Names, logos, systems and guidelines that make you look the size you want to be.',
						'url'   => 'url:service/brand-identity',
					),
					array(
						'icon'  => 'layout',
						'title' => 'Web design',
						'text'  => 'Websites designed around your goals and your visitors, not a template.',
						'url'   => 'url:service/web-design',
					),
					array(
						'icon'  => 'code',
						'title' => 'Web development',
						'text'  => 'Fast, accessible, maintainable builds — WordPress, headless or bespoke.',
						'url'   => 'url:service/web-development',
					),
					array(
						'icon'  => 'trending-up',
						'title' => 'SEO & content',
						'text'  => 'The words and structure that help the right people find you and stay.',
						'url'   => 'url:service/seo-content',
					),
					array(
						'icon'  => 'shopping-cart',
						'title' => 'Ecommerce',
						'text'  => 'Storefronts that turn browsers into buyers and keep them coming back.',
						'url'   => 'url:service/ecommerce',
					),
					array(
						'icon'  => 'users',
						'title' => 'UX research',
						'text'  => 'Evidence from real users so big decisions rest on more than opinion.',
						'url'   => 'url:service/ux-research',
					),
				),
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface-alt',
					'width'    => 'wide',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout'  => 'process',
				'eyebrow'        => 'The method',
				'heading'        => 'Four phases, no black boxes',
				'layout'         => 'timeline',
				'steps'          => array(
					array(
						'title' => 'Discover',
						'text'  => 'Workshops, interviews and audits to map the real problem before we spend a penny solving it.',
						'icon'  => 'search',
					),
					array(
						'title' => 'Define',
						'text'  => 'A tight strategy, scope and roadmap — with the success measures written down.',
						'icon'  => 'compass',
					),
					array(
						'title' => 'Design & build',
						'text'  => 'Brand, interface and code developed in tight loops, reviewed on real screens.',
						'icon'  => 'pen',
					),
					array(
						'title' => 'Launch & grow',
						'text'  => 'A confident release, then ongoing measurement and iteration against those measures.',
						'icon'  => 'rocket',
					),
				),
				'structuredData' => 1,
				'settings'       => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'default',
					'anchorId' => 'method',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'testimonials',
				'layout'        => 'grid',
				'heading'       => 'Proof it works',
				'eyebrow'       => 'In their words',
				'source'        => 'default',
				'count'         => 3,
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'call-to-action',
				'heading'       => 'Have a project in mind?',
				'body'          => '<p>Tell us about it. We will suggest the right way to work together — and never sell you more than you need.</p>',
				'buttons'       => array(
					array(
						'link'  => array(
							'label'    => 'Start a project',
							'type'     => 'internal',
							'document' => 'ref:page/contact',
						),
						'style' => 'primary',
					),
				),
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'brand-tint',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),
		),
	),

	// ---------------------------------------------------------------
	// CONTACT — contact block + offices.
	// ---------------------------------------------------------------
	'contact'  => array(
		'title'    => 'Contact',
		'sections' => array(

			array(
				'acf_fc_layout' => 'contact',
				'heading'       => 'Let us talk',
				'intro'         => 'Whether you have a detailed brief or just the beginnings of an idea, we would love to hear from you. Tell us a little and we will come back within one working day.',
				'showPhone'     => 1,
				'showEmail'     => 1,
				'showAddress'   => 1,
				'formIntro'     => 'Prefer to write it out? Drop us a line and the right person will pick it up.',
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'locations',
				'eyebrow'       => 'Find us',
				'heading'       => 'Our studios',
				'query'         => array(
					'mode' => 'automatic',
				),
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface-alt',
					'width'    => 'wide',
					'anchorId' => '',
					'divider'  => 0,
				),
			),
		),
	),

	// ---------------------------------------------------------------
	// TEAM — hero, full team grid, CTA.
	// ---------------------------------------------------------------
	'team'     => array(
		'title'    => 'Team',
		'sections' => array(

			array(
				'acf_fc_layout' => 'hero',
				'variant'       => 'minimal',
				'eyebrow'       => 'Our people',
				'heading'       => 'The team behind the work',
				'subheading'    => 'Designers, engineers and strategists who genuinely like working together — and it shows in what we make.',
				'buttons'       => array(
					array(
						'link'  => array(
							'label'    => 'Work with us',
							'type'     => 'internal',
							'document' => 'ref:page/contact',
						),
						'style' => 'primary',
					),
				),
				'settings'      => array(
					'spacing'  => 'md',
					'tone'     => 'surface',
					'width'    => 'narrow',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'team',
				'eyebrow'       => '',
				'heading'       => 'Everyone at Northbeam',
				'columns'       => '3',
				'source'        => 'default',
				'count'         => 0,
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface-alt',
					'width'    => 'wide',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'call-to-action',
				'heading'       => 'Want to join us?',
				'body'          => '<p>We are always glad to meet good people. If you care about craft as much as we do, get in touch.</p>',
				'buttons'       => array(
					array(
						'link'  => array(
							'label'    => 'Say hello',
							'type'     => 'internal',
							'document' => 'ref:page/contact',
						),
						'style' => 'primary',
					),
				),
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'brand-tint',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),
		),
	),

	// ---------------------------------------------------------------
	// FAQ — rich-text lead, full FAQ accordion (schema on), CTA.
	// ---------------------------------------------------------------
	'faq'      => array(
		'title'    => 'FAQ',
		'sections' => array(

			array(
				'acf_fc_layout' => 'rich-text',
				'eyebrow'       => 'Questions & answers',
				'heading'       => 'Everything you were about to ask',
				'body'          => '<p>A few of the things people most often want to know before we begin. Cannot find your question? <a href="url:page/contact">Just ask us</a> — we are happy to help.</p>',
				'alignment'     => 'centre',
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'surface',
					'width'    => 'narrow',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout'  => 'faq',
				'eyebrow'        => '',
				'heading'        => 'Frequently asked questions',
				'structuredData' => 1,
				'query'          => array(
					'mode' => 'automatic',
				),
				'settings'       => array(
					'spacing'  => 'lg',
					'tone'     => 'surface-alt',
					'width'    => 'narrow',
					'anchorId' => '',
					'divider'  => 0,
				),
			),

			array(
				'acf_fc_layout' => 'call-to-action',
				'heading'       => 'Still have a question?',
				'body'          => '<p>We are real people and we reply quickly. Send it over and we will get you an honest answer.</p>',
				'buttons'       => array(
					array(
						'link'  => array(
							'label'    => 'Contact us',
							'type'     => 'internal',
							'document' => 'ref:page/contact',
						),
						'style' => 'primary',
					),
				),
				'settings'      => array(
					'spacing'  => 'lg',
					'tone'     => 'brand-tint',
					'width'    => 'default',
					'anchorId' => '',
					'divider'  => 0,
				),
			),
		),
	),

	// ---------------------------------------------------------------
	// BLOG — the posts page. Native loop renders it; no sections.
	// ---------------------------------------------------------------
	'blog'     => array(
		'title'      => 'Blog',
		'posts_page' => true,
		'sections'   => array(),
	),
);
