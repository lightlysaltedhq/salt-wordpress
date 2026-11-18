<?php
/**
 * Team section + team_member CPT slice tests (spec §10 #12, Appendix A.3).
 *
 * States (COMPL02): 0 members => '' ; 1 member => static grid, no JS;
 * N => grid. Malformed members (no name) and malformed social rows
 * (unknown platform / no url) are skipped + logged. Person schema nodes
 * are queued per rendered member with NO email/phone (PII minimisation).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/team.php';

require_once dirname( __DIR__, 2 ) . '/inc/cpt/team-member.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/cpt-team-member.php';

final class TeamSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_cpt_registry_reset();
		salt_test_attachments_reset();
		$GLOBALS['salt_test_acf_groups'] = array();
	}

	/**
	 * Seed team_member posts + their ACF field fixtures.
	 *
	 * @param array<int,array<string,mixed>> $members Each: post props + 'fields' array.
	 * @return void
	 */
	private function seed_members( array $members ): void {
		$posts = array();
		foreach ( $members as $member ) {
			$fields = $member['fields'] ?? array();
			unset( $member['fields'] );
			$member['post_type'] = 'team_member';
			$posts[]             = $member;

			$GLOBALS['salt_test_fields'][ (string) $member['ID'] ] = $fields;
		}
		$this->seed_posts( $posts );
	}

	/**
	 * Render the team section for one seeded FC row.
	 *
	 * @param array<string,mixed> $row_fields Sub-field fixture values.
	 * @return string
	 */
	private function render_team( array $row_fields = array() ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'team',
					'fields' => $row_fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section(
			'team',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------------

	public function test_zero_members_renders_nothing(): void {
		$this->assertSame( '', $this->render_team( array( 'heading' => 'Meet the team' ) ) );
	}

	public function test_disabled_module_renders_nothing(): void {
		$this->seed_members( array( $this->member( 1, 'Jane Doe' ) ) );
		$GLOBALS['salt_test_fields']['option']['module_team_member'] = false;

		$this->assertSame( '', $this->render_team( array( 'heading' => 'Meet the team' ) ) );
	}

	public function test_one_member_renders_static_layout_without_js(): void {
		$this->seed_members( array( $this->member( 1, 'Jane Doe', array( 'role' => 'Founder' ) ) ) );

		$html = $this->render_team( array( 'heading' => 'Meet the team' ) );

		$this->assertSame( 1, substr_count( $html, 'x-card-team' ), 'One member => exactly one card.' );
		$this->assertStringContainsString( 'Jane Doe', $html );
		$this->assertStringContainsString( 'Founder', $html );
		$this->assertStringNotContainsString( '<script', $html, 'Team is a static grid — no JS on any state.' );
	}

	public function test_n_members_render_a_grid_with_the_selected_columns(): void {
		$this->seed_members(
			array(
				$this->member( 1, 'Jane Doe' ),
				$this->member( 2, 'John Smith' ),
				$this->member( 3, 'Ada Jones' ),
				$this->member( 4, 'Sam Poe' ),
			)
		);

		$html = $this->render_team(
			array(
				'heading' => 'Meet the team',
				'columns' => '4',
			)
		);

		$this->assertSame( 4, substr_count( $html, 'x-card-team' ) );
		$this->assertStringContainsString( 'lg:grid-cols-4', $html );
	}

	public function test_invalid_columns_value_falls_back_to_three_and_logs(): void {
		$this->seed_members( array( $this->member( 1, 'Jane Doe' ) ) );

		$html = $this->render_team(
			array(
				'heading' => 'Meet the team',
				'columns' => '7',
			)
		);

		$this->assertStringContainsString( 'lg:grid-cols-3', $html );
		$this->assertStringContainsString( 'invalid columns value "7"', implode( ' ', $this->logs() ) );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$this->seed_members( array( $this->member( 1, 'Jane Doe' ) ) );

		$html = $this->render_team( array() );

		$this->assertStringNotContainsString( '<h1', $html, 'No section heading => card names must not become the H1 (A11Y01).' );
		$this->assertFalse( salt_h1_claimed() );
	}

	// -----------------------------------------------------------------------
	// Malformed data (DATA05).
	// -----------------------------------------------------------------------

	public function test_member_without_a_name_is_skipped_and_logged(): void {
		$this->seed_members(
			array(
				$this->member( 1, 'Jane Doe' ),
				$this->member( 2, '' ),
			)
		);

		$html = $this->render_team( array( 'heading' => 'Meet the team' ) );

		$this->assertSame( 1, substr_count( $html, 'x-card-team' ), 'Nameless member renders no card.' );
		$this->assertStringContainsString( 'team_member 2 has no name', implode( ' ', $this->logs() ) );
	}

	public function test_all_members_malformed_renders_nothing(): void {
		$this->seed_members( array( $this->member( 1, '' ) ) );

		$this->assertSame( '', $this->render_team( array( 'heading' => 'Meet the team' ) ) );
	}

	public function test_social_row_with_unknown_platform_is_skipped_and_logged(): void {
		$this->seed_members(
			array(
				$this->member(
					1,
					'Jane Doe',
					array(
						'socials' => array(
							array(
								'platform' => 'linkedin',
								'url'      => 'https://example.test/in/jane',
							),
							array(
								'platform' => 'myspace',
								'url'      => 'https://example.test/myspace/jane',
							),
							array(
								'platform' => 'x',
								'url'      => '',
							),
						),
					)
				),
			)
		);

		$html = $this->render_team( array( 'heading' => 'Meet the team' ) );

		$this->assertStringContainsString( 'https://example.test/in/jane', $html );
		$this->assertStringContainsString( 'aria-label="Jane Doe on LinkedIn"', $html );
		$this->assertStringNotContainsString( 'myspace', $html );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'unknown platform "myspace"', $logs );
		$this->assertStringContainsString( 'social row (x) without a url skipped', $logs );
	}

	// -----------------------------------------------------------------------
	// Contact links.
	// -----------------------------------------------------------------------

	public function test_email_and_phone_render_as_mailto_and_tel_links(): void {
		$this->seed_members(
			array(
				$this->member(
					1,
					'Jane Doe',
					array(
						'email' => 'jane@example.test',
						'phone' => '+44 (0)1905 123 456',
					)
				),
			)
		);

		$html = $this->render_team( array( 'heading' => 'Meet the team' ) );

		$this->assertStringContainsString( 'href="mailto:jane@example.test"', $html );
		$this->assertStringContainsString( 'href="tel:+4401905123456"', $html );
		$this->assertStringContainsString( '+44 (0)1905 123 456', $html, 'Visible phone text keeps the editor formatting.' );
	}

	public function test_absent_email_and_phone_render_no_contact_list(): void {
		$this->seed_members( array( $this->member( 1, 'Jane Doe' ) ) );

		$html = $this->render_team( array( 'heading' => 'Meet the team' ) );

		$this->assertStringNotContainsString( 'x-card-team-contact', $html );
		$this->assertStringNotContainsString( 'mailto:', $html );
	}

	// -----------------------------------------------------------------------
	// Schema (Person nodes; PII minimisation).
	// -----------------------------------------------------------------------

	public function test_person_nodes_are_queued_without_email_or_phone(): void {
		$this->seed_members(
			array(
				$this->member(
					1,
					'Jane Doe',
					array(
						'role'  => 'Founder',
						'email' => 'jane@example.test',
						'phone' => '01905 123456',
					)
				),
				$this->member( 2, 'John Smith' ),
			)
		);

		$this->render_team( array( 'heading' => 'Meet the team' ) );

		$graph   = salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
			)
		);
		$persons = array_values(
			array_filter(
				$graph['@graph'],
				static fn( array $node ): bool => 'Person' === $node['@type']
			)
		);

		$this->assertCount( 2, $persons );
		$this->assertSame( 'Jane Doe', $persons[0]['name'] );
		$this->assertSame( 'Founder', $persons[0]['jobTitle'] );
		$this->assertSame( 'John Smith', $persons[1]['name'] );
		$this->assertArrayNotHasKey( 'jobTitle', $persons[1], 'No role => jobTitle omitted.' );

		foreach ( $persons as $person ) {
			$this->assertArrayNotHasKey( 'email', $person, 'PII minimisation: email never enters the graph.' );
			$this->assertArrayNotHasKey( 'telephone', $person, 'PII minimisation: phone never enters the graph.' );
		}
	}

	public function test_skipped_members_queue_no_person_node(): void {
		$this->seed_members(
			array(
				$this->member( 1, 'Jane Doe' ),
				$this->member( 2, '' ),
			)
		);

		$this->render_team( array( 'heading' => 'Meet the team' ) );

		$graph   = salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
			)
		);
		$persons = array_filter(
			$graph['@graph'],
			static fn( array $node ): bool => 'Person' === $node['@type']
		);

		$this->assertCount( 1, $persons );
	}

	// -----------------------------------------------------------------------
	// CPT registration (SEC09 posture; module gate).
	// -----------------------------------------------------------------------

	public function test_cpt_registers_with_the_not_public_v1_posture(): void {
		salt_register_cpt_team_member();

		$this->assertArrayHasKey( 'team_member', $GLOBALS['salt_test_post_types'] );
		$args = $GLOBALS['salt_test_post_types']['team_member'];

		$this->assertFalse( $args['public'], 'v1 decision: team_member is NOT public.' );
		$this->assertTrue( $args['show_ui'] );
		$this->assertFalse( $args['publicly_queryable'] );
		$this->assertTrue( $args['exclude_from_search'] );
		$this->assertFalse( $args['show_in_rest'] );
		$this->assertFalse( $args['has_archive'] );

		$this->assertArrayHasKey( 'department', $GLOBALS['salt_test_taxonomies'] );
		$this->assertSame( array( 'team_member' ), $GLOBALS['salt_test_taxonomies']['department']['object_type'] );
	}

	public function test_disabled_module_registers_no_cpt_or_taxonomy(): void {
		$GLOBALS['salt_test_fields']['option']['module_team_member'] = false;

		salt_register_cpt_team_member();

		$this->assertSame( array(), $GLOBALS['salt_test_post_types'] );
		$this->assertSame( array(), $GLOBALS['salt_test_taxonomies'] );
	}

	// -----------------------------------------------------------------------
	// Fields-as-code (DATA02).
	// -----------------------------------------------------------------------

	public function test_team_member_field_group_registers_the_appendix_a3_fields(): void {
		salt_register_team_member_fields();

		$this->assertCount( 1, $GLOBALS['salt_test_acf_groups'] );
		$group = $GLOBALS['salt_test_acf_groups'][0];

		$this->assertSame( 'group_salt_team_member', $group['key'] );

		$names = array_map( static fn( array $f ): string => (string) $f['name'], $group['fields'] );
		$this->assertSame( array( 'role', 'short_bio', 'email', 'phone', 'socials' ), $names );

		$socials = $group['fields'][4];
		$this->assertSame( 'repeater', $socials['type'] );
		$this->assertSame(
			array( 'platform', 'url' ),
			array_map( static fn( array $f ): string => (string) $f['name'], $socials['sub_fields'] )
		);
		$this->assertSame(
			array( 'linkedin', 'x', 'instagram', 'facebook', 'youtube', 'tiktok', 'github', 'bluesky', 'other' ),
			array_keys( $socials['sub_fields'][0]['choices'] )
		);

		$this->assertSame( array(), array_filter( $this->logs(), static fn( string $l ): bool => str_contains( $l, 'DATA02' ) ), 'No key-convention violations.' );
	}

	public function test_disabled_module_registers_no_field_group(): void {
		$GLOBALS['salt_test_fields']['option']['module_team_member'] = false;

		salt_register_team_member_fields();

		$this->assertSame( array(), $GLOBALS['salt_test_acf_groups'] );
	}

	// -----------------------------------------------------------------------
	// FC layout shape (DATA02/FLEET07).
	// -----------------------------------------------------------------------

	public function test_team_layout_orders_content_then_query_controls_then_wrapper(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/team.php';

		$this->assertSame( 'team', $layout['name'] );
		$this->assertSame( 'layout_salt_team', $layout['key'] );

		$names = array_map(
			static fn( array $f ): string => (string) ( $f['name'] ?? $f['key'] ),
			$layout['sub_fields']
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'columns' ), array_slice( $names, 0, 3 ), 'Content fields first.' );
		$this->assertContains( 'source', $names, 'Query controls embedded (§10a).' );
		$this->assertContains( 'taxonomy_terms', $names, 'department taxonomy filter present.' );
		$this->assertSame( 'settings', end( $names ), 'The contract\'s section settings group last.' );
	}

	// -----------------------------------------------------------------------
	// Fixture builder.
	// -----------------------------------------------------------------------

	// -----------------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------------

	/**
	 * Seed one team FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $row_fields Sub-field fixture values.
	 * @return array<string,mixed>|null
	 */
	private function resolve_team( array $row_fields = array() ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'team',
					'fields' => $row_fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'team' );
		$this->assertSame( 'salt_resolve_section_team', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
	}

	public function test_resolver_returns_null_for_zero_members_malformed_members_and_a_disabled_module(): void {
		$this->assertNull( $this->resolve_team( array( 'heading' => 'Meet the team' ) ) );

		$this->seed_members( array( $this->member( 1, '' ) ) );
		$this->assertNull( $this->resolve_team( array( 'heading' => 'Meet the team' ) ) );
		$this->assertSame( array(), salt_schema_queue(), 'A skipped section queues no Person node.' );

		$this->seed_members( array( $this->member( 1, 'Jane Doe' ) ) );
		$GLOBALS['salt_test_fields']['option']['module_team_member'] = false;
		$this->assertNull( $this->resolve_team( array( 'heading' => 'Meet the team' ) ) );
	}

	public function test_resolver_props_members_and_fallbacks(): void {
		$GLOBALS['salt_test_attachments'][40] = array(
			'src'    => 'https://example.test/uploads/jane.jpg',
			'width'  => 600,
			'height' => 600,
			'srcset' => '',
		);
		$jane                                 = $this->member(
			1,
			'  Jane Doe ',
			array(
				'role'      => 'Founder',
				'short_bio' => 'Builds things.',
				'email'     => 'jane@example.test',
				'phone'     => '+44 1234',
				'socials'   => 'not-an-array',
			)
		);
		$jane['salt_thumbnail_id']            = 40;
		$this->seed_members( array( $jane, $this->member( 2, 'John Smith' ) ) );

		$resolved = $this->resolve_team(
			array(
				'eyebrow' => 'People',
				'columns' => '7',
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'columns', 'members' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'People', $resolved['props']['eyebrow'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertSame( '3', $resolved['props']['columns'] );
		$this->assertStringContainsString( 'invalid columns value "7"', implode( ' ', $this->logs() ) );

		$this->assertCount( 2, $resolved['props']['members'] );
		$member = $resolved['props']['members'][0];
		$this->assertSame( array( 'name', 'role', 'bio', 'email', 'phone', 'image_id', 'socials' ), array_keys( $member ) );
		$this->assertSame( 'Jane Doe', $member['name'], 'The name is trimmed, as before.' );
		$this->assertSame( 'Founder', $member['role'] );
		$this->assertSame( 40, $member['image_id'] );
		$this->assertSame( array(), $member['socials'], 'A non-array socials field is an empty list.' );
		$this->assertSame( 0, $resolved['props']['members'][1]['image_id'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
		$this->assertSame( 'team', $resolved['section']['name'] );
	}

	public function test_resolver_queues_the_person_nodes_itself(): void {
		$this->seed_members( array( $this->member( 1, 'Jane Doe', array( 'role' => 'Founder' ) ) ) );

		$this->resolve_team();

		$queue = salt_schema_queue();
		$this->assertCount( 1, $queue, 'Schema is logic: queued without the view ever running.' );
		$this->assertSame( 'Person', $queue[0]['@type'] );
		$this->assertSame( 'Founder', $queue[0]['jobTitle'] );
	}

	public function test_view_draws_from_props_alone(): void {
		$GLOBALS['salt_test_attachments'][40] = array(
			'src'    => 'https://example.test/uploads/prop.jpg',
			'width'  => 600,
			'height' => 600,
			'srcset' => '',
		);
		$props                                = array(
			'eyebrow' => 'People',
			'heading' => 'From props',
			'columns' => '2',
			'members' => array(
				array(
					'name'     => 'Prop Person',
					'role'     => 'Designer',
					'bio'      => 'Drawn from props.',
					'email'    => '',
					'phone'    => '',
					'image_id' => 40,
					'socials'  => array(),
				),
			),
		);

		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/team.php',
			false,
			array(
				'props' => $props,
				'plan'  => array(
					'index'         => 0,
					'headingLevel'  => 2,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ), 'Section heading at the plan level.' );
		$this->assertSame( 1, preg_match_all( '/<h3[\s>]/', $html ), 'Card title one level below.' );
		$this->assertStringContainsString( 'Prop Person', $html );
		$this->assertStringContainsString( 'prop.jpg', $html, 'The view draws the photo from image_id.' );
		$this->assertStringContainsString( 'grid gap-8 sm:grid-cols-2"', $html );
		$this->assertStringNotContainsString( 'lg:grid-cols-3', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );
	}

	/**
	 * Build a team_member post fixture.
	 *
	 * @param int                 $id     Post ID.
	 * @param string              $name   Post title (member name).
	 * @param array<string,mixed> $fields ACF field fixtures.
	 * @return array<string,mixed>
	 */
	private function member( int $id, string $name, array $fields = array() ): array {
		return array(
			'ID'         => $id,
			'post_title' => $name,
			'menu_order' => $id,
			'fields'     => $fields,
		);
	}
}
