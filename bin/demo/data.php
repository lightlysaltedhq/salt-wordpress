<?php
/**
 * Demo-content map for the salt seeder — a fictional digital agency,
 * "Northbeam Studio". PURE data (no I/O), so tests/unit/DemoContentTest.php
 * can validate it against the registered field schema.
 *
 * Cross-links use tokens the runner resolves after creating objects:
 *   ref:{cpt}/{slug}   ref:page/{slug}   ref:image/{suffix}   ref:term/{tax}/{slug}
 *   url:page/{slug}    url:archive/{cpt}   (→ permalink / archive link)
 *
 * The bulk CPT content and the page section stacks live in the two part
 * files required at the bottom, so they can be authored/validated
 * independently against the same ref namespace.
 *
 * @package Salt\Demo
 */

declare(strict_types=1);

return array(

	// All content modules on (they default enabled; explicit for clarity).
	'modules' => array( 'service', 'testimonial', 'team_member', 'work', 'faq', 'location' ),

	// ACF OPTION fields (written with update_field(name, value, 'option')).
	'options' => array(
		'org_name'            => 'Northbeam Studio',
		'org_legal_name'      => 'Northbeam Studio Ltd',
		'company_phone'       => '+44 20 7946 0123',
		'company_email'       => 'hello@northbeam.studio',
		'company_address'     => "48 Rivington Street\nShoreditch\nLondon\nEC2A 3QP\nUnited Kingdom",
		'org_same_as'         => array(
			array( 'url' => 'https://www.linkedin.com/company/northbeam-studio' ),
			array( 'url' => 'https://x.com/northbeamstudio' ),
			array( 'url' => 'https://www.instagram.com/northbeamstudio' ),
			array( 'url' => 'https://dribbble.com/northbeamstudio' ),
		),
		'reviews_provider'    => 'google',
		'reviews_profile_url' => 'https://www.google.com/maps/place/Northbeam+Studio',
		'org_type'            => 'organization',
		'org_logo'            => 'ref:image/brand-logo',

		'brand_primary'       => '#4c6ef5',
		'brand_secondary'     => '#12b886',
		'brand_accent'        => '#f76707',
		'dark_mode'           => 1,

		'logo_light'          => 'ref:image/brand-logo',
		'logo_dark'           => 'ref:image/brand-logo-dark',
		'logo_height'         => 44,
		'logo_text_fallback'  => 'Northbeam',

		'header_sticky'       => 1,
		'header_cta'          => array(
			'title'  => 'Start a project',
			'url'    => 'url:page/contact',
			'target' => '',
		),
		'footer_text'         => 'Northbeam Studio — design & web for ambitious brands.',
		'footer_show_social'  => 1,
	),

	// Deterministic placeholder media. type 'photo' → picsum seed; 'logo' →
	// placehold wordmark. Registered as ref:image/{key}.
	'images'  => array(
		'brand-logo'              => array(
			'type'  => 'logo',
			'label' => 'NORTHBEAM',
			'bg'    => '1f2933',
			'w'     => 280,
			'h'     => 72,
			'alt'   => 'Northbeam Studio',
		),
		'brand-logo-dark'         => array(
			'type'  => 'logo',
			'label' => 'NORTHBEAM',
			'bg'    => 'f8fafc',
			'w'     => 280,
			'h'     => 72,
			'alt'   => 'Northbeam Studio',
		),

		'hero-home'               => array(
			'type' => 'photo',
			'seed' => 'northbeam-hero-home',
			'w'    => 1600,
			'h'    => 900,
			'alt'  => 'Studio team collaborating',
		),
		'hero-approach'           => array(
			'type' => 'photo',
			'seed' => 'northbeam-hero-approach',
			'w'    => 1600,
			'h'    => 900,
			'alt'  => 'Design workshop in progress',
		),
		'hero-team'               => array(
			'type' => 'photo',
			'seed' => 'northbeam-hero-team',
			'w'    => 1600,
			'h'    => 900,
			'alt'  => 'The Northbeam team',
		),
		'about-story'             => array(
			'type' => 'photo',
			'seed' => 'northbeam-about',
			'w'    => 1200,
			'h'    => 900,
			'alt'  => 'Northbeam studio space',
		),

		'service-brand-identity'  => array(
			'type' => 'photo',
			'seed' => 'nb-svc-brand',
			'w'    => 800,
			'h'    => 600,
			'alt'  => 'Brand identity boards',
		),
		'service-web-design'      => array(
			'type' => 'photo',
			'seed' => 'nb-svc-webdesign',
			'w'    => 800,
			'h'    => 600,
			'alt'  => 'Website design mockups',
		),
		'service-web-development' => array(
			'type' => 'photo',
			'seed' => 'nb-svc-webdev',
			'w'    => 800,
			'h'    => 600,
			'alt'  => 'Developer at work',
		),
		'service-seo-content'     => array(
			'type' => 'photo',
			'seed' => 'nb-svc-seo',
			'w'    => 800,
			'h'    => 600,
			'alt'  => 'Analytics dashboard',
		),
		'service-ecommerce'       => array(
			'type' => 'photo',
			'seed' => 'nb-svc-ecom',
			'w'    => 800,
			'h'    => 600,
			'alt'  => 'Online store interface',
		),
		'service-ux-research'     => array(
			'type' => 'photo',
			'seed' => 'nb-svc-ux',
			'w'    => 800,
			'h'    => 600,
			'alt'  => 'User research session',
		),

		'work-harborview'         => array(
			'type' => 'photo',
			'seed' => 'nb-work-harborview',
			'w'    => 1200,
			'h'    => 800,
			'alt'  => 'Harborview rebrand',
		),
		'work-meridian'           => array(
			'type' => 'photo',
			'seed' => 'nb-work-meridian',
			'w'    => 1200,
			'h'    => 800,
			'alt'  => 'Meridian store',
		),
		'work-atlas'              => array(
			'type' => 'photo',
			'seed' => 'nb-work-atlas',
			'w'    => 1200,
			'h'    => 800,
			'alt'  => 'Atlas platform',
		),
		'work-verdant'            => array(
			'type' => 'photo',
			'seed' => 'nb-work-verdant',
			'w'    => 1200,
			'h'    => 800,
			'alt'  => 'Verdant app',
		),
		'work-northside'          => array(
			'type' => 'photo',
			'seed' => 'nb-work-northside',
			'w'    => 1200,
			'h'    => 800,
			'alt'  => 'Northside Clinic site',
		),
		'work-harborview-g1'      => array(
			'type' => 'photo',
			'seed' => 'nb-work-harborview-g1',
			'w'    => 1000,
			'h'    => 750,
			'alt'  => 'Harborview detail',
		),
		'work-harborview-g2'      => array(
			'type' => 'photo',
			'seed' => 'nb-work-harborview-g2',
			'w'    => 1000,
			'h'    => 750,
			'alt'  => 'Harborview detail',
		),
		'work-atlas-g1'           => array(
			'type' => 'photo',
			'seed' => 'nb-work-atlas-g1',
			'w'    => 1000,
			'h'    => 750,
			'alt'  => 'Atlas detail',
		),
		'work-atlas-g2'           => array(
			'type' => 'photo',
			'seed' => 'nb-work-atlas-g2',
			'w'    => 1000,
			'h'    => 750,
			'alt'  => 'Atlas detail',
		),

		'team-amelia'             => array(
			'type' => 'photo',
			'seed' => 'nb-team-amelia',
			'w'    => 600,
			'h'    => 600,
			'alt'  => 'Amelia Hart',
		),
		'team-marcus'             => array(
			'type' => 'photo',
			'seed' => 'nb-team-marcus',
			'w'    => 600,
			'h'    => 600,
			'alt'  => 'Marcus Bell',
		),
		'team-priya'              => array(
			'type' => 'photo',
			'seed' => 'nb-team-priya',
			'w'    => 600,
			'h'    => 600,
			'alt'  => 'Priya Nadar',
		),
		'team-tom'                => array(
			'type' => 'photo',
			'seed' => 'nb-team-tom',
			'w'    => 600,
			'h'    => 600,
			'alt'  => 'Tom Okafor',
		),
		'team-sofia'              => array(
			'type' => 'photo',
			'seed' => 'nb-team-sofia',
			'w'    => 600,
			'h'    => 600,
			'alt'  => 'Sofia Lindqvist',
		),

		'blog-1'                  => array(
			'type' => 'photo',
			'seed' => 'nb-blog-1',
			'w'    => 1200,
			'h'    => 800,
			'alt'  => 'Design systems',
		),
		'blog-2'                  => array(
			'type' => 'photo',
			'seed' => 'nb-blog-2',
			'w'    => 1200,
			'h'    => 800,
			'alt'  => 'Core Web Vitals',
		),
		'blog-3'                  => array(
			'type' => 'photo',
			'seed' => 'nb-blog-3',
			'w'    => 1200,
			'h'    => 800,
			'alt'  => 'Rebrand process',
		),
		'blog-4'                  => array(
			'type' => 'photo',
			'seed' => 'nb-blog-4',
			'w'    => 1200,
			'h'    => 800,
			'alt'  => 'Accessible design',
		),
		'blog-5'                  => array(
			'type' => 'photo',
			'seed' => 'nb-blog-5',
			'w'    => 1200,
			'h'    => 800,
			'alt'  => 'Headless commerce',
		),

		'client-1'                => array(
			'type'  => 'logo',
			'label' => 'Harborview',
			'bg'    => '495057',
			'w'     => 200,
			'h'     => 80,
		),
		'client-2'                => array(
			'type'  => 'logo',
			'label' => 'Meridian',
			'bg'    => '495057',
			'w'     => 200,
			'h'     => 80,
		),
		'client-3'                => array(
			'type'  => 'logo',
			'label' => 'Atlas',
			'bg'    => '495057',
			'w'     => 200,
			'h'     => 80,
		),
		'client-4'                => array(
			'type'  => 'logo',
			'label' => 'Verdant',
			'bg'    => '495057',
			'w'     => 200,
			'h'     => 80,
		),
		'client-5'                => array(
			'type'  => 'logo',
			'label' => 'Northside',
			'bg'    => '495057',
			'w'     => 200,
			'h'     => 80,
		),
	),

	// Taxonomy terms (get-or-create; registered as ref:term/{taxonomy}/{slug}).
	'terms'   => array(
		'service_category' => array(
			array(
				'slug' => 'strategy',
				'name' => 'Strategy',
			),
			array(
				'slug' => 'design',
				'name' => 'Design',
			),
			array(
				'slug' => 'build',
				'name' => 'Build',
			),
			array(
				'slug' => 'growth',
				'name' => 'Growth',
			),
		),
		'work_type'        => array(
			array(
				'slug' => 'web',
				'name' => 'Web',
			),
			array(
				'slug' => 'brand',
				'name' => 'Brand',
			),
			array(
				'slug' => 'ecommerce',
				'name' => 'E-commerce',
			),
		),
		'faq_category'     => array(
			array(
				'slug' => 'general',
				'name' => 'General',
			),
			array(
				'slug' => 'pricing',
				'name' => 'Pricing',
			),
			array(
				'slug' => 'process',
				'name' => 'Process',
			),
			array(
				'slug' => 'support',
				'name' => 'Support',
			),
		),
		'location_area'    => array(
			array(
				'slug' => 'uk',
				'name' => 'United Kingdom',
			),
		),
		'category'         => array(
			array(
				'slug' => 'insights',
				'name' => 'Insights',
			),
			array(
				'slug' => 'guides',
				'name' => 'Guides',
			),
			array(
				'slug' => 'news',
				'name' => 'News',
			),
		),
	),

	// Menus → set_theme_mod('nav_menu_locations'). CPT listings use the
	// native archives (§6 one-owner-per-URL), not builder pages.
	'menus'   => array(
		'primary' => array(
			array(
				'title' => 'Home',
				'ref'   => 'ref:page/home',
			),
			array(
				'title'   => 'Services',
				'archive' => 'service',
			),
			array(
				'title'   => 'Work',
				'archive' => 'work',
			),
			array(
				'title' => 'About',
				'ref'   => 'ref:page/about',
			),
			array(
				'title' => 'Approach',
				'ref'   => 'ref:page/approach',
			),
			array(
				'title' => 'Team',
				'ref'   => 'ref:page/team',
			),
			array(
				'title' => 'Blog',
				'ref'   => 'ref:page/blog',
			),
			array(
				'title' => 'Contact',
				'ref'   => 'ref:page/contact',
			),
		),
		'footer'  => array(
			array(
				'title' => 'About',
				'ref'   => 'ref:page/about',
			),
			array(
				'title'   => 'Services',
				'archive' => 'service',
			),
			array(
				'title'   => 'Work',
				'archive' => 'work',
			),
			array(
				'title' => 'FAQ',
				'ref'   => 'ref:page/faq',
			),
			array(
				'title' => 'Contact',
				'ref'   => 'ref:page/contact',
			),
		),
	),

	// Blog author profile — written onto the first administrator (WP display
	// name + bio).
	'author'  => array(
		'display_name' => 'Amelia Hart',
		'description'  => 'Founder and creative director at Northbeam. Amelia has spent ten years helping ambitious brands look the part and perform online.',
	),

	// Bulk content (authored against the ref namespace above).
	'cpts'    => require __DIR__ . '/parts/cpts.php',
	'pages'   => require __DIR__ . '/parts/pages.php',
);
