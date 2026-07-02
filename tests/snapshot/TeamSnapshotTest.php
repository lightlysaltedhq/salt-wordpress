<?php
/**
 * Team section snapshots (FLEET01) — one per meaningful variant:
 * single member (static 1-state), full 3-column grid with headshots and
 * contact/social links, and a 2-column headingless grid.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/team.php';

final class TeamSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();
	}

	/**
	 * Seed team_member posts + field fixtures, render the section.
	 *
	 * @param array<int,array<string,mixed>> $members    Post props + 'fields'.
	 * @param array<string,mixed>            $row_fields FC row sub-field values.
	 * @return string
	 */
	private function render_team( array $members, array $row_fields ): string {
		$posts = array();
		foreach ( $members as $member ) {
			$fields = $member['fields'] ?? array();
			unset( $member['fields'] );
			$member['post_type'] = 'team_member';
			$posts[]             = $member;

			$GLOBALS['salt_test_fields'][ (string) $member['ID'] ] = $fields;
		}
		$this->seed_posts( $posts );

		salt_test_seed_fc(
			array(
				array(
					'layout' => 'team',
					'fields' => $row_fields,
				),
			)
		);
		have_rows( 'sections' );
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

	public function test_team_single_member(): void {
		$this->assertMatchesHtmlSnapshot(
			'team-single',
			$this->render_team(
				array(
					array(
						'ID'         => 1,
						'post_title' => 'Jane Doe',
						'menu_order' => 1,
						'fields'     => array(
							'role'      => 'Founder',
							'short_bio' => 'Leads the studio.',
						),
					),
				),
				array( 'heading' => 'Meet the team' )
			)
		);
	}

	public function test_team_grid_full_options(): void {
		$GLOBALS['salt_test_attachments'][77] = array(
			'src'    => 'https://example.test/uploads/jane.jpg',
			'width'  => 800,
			'height' => 800,
			'srcset' => 'https://example.test/uploads/jane-400.jpg 400w, https://example.test/uploads/jane.jpg 800w',
			'alt'    => '',
		);

		$this->assertMatchesHtmlSnapshot(
			'team-grid-full',
			$this->render_team(
				array(
					array(
						'ID'                => 1,
						'post_title'        => 'Jane Doe',
						'menu_order'        => 1,
						'salt_thumbnail_id' => 77,
						'fields'            => array(
							'role'      => 'Founder',
							'short_bio' => 'Leads the studio.',
							'email'     => 'jane@example.test',
							'phone'     => '01905 123456',
							'socials'   => array(
								array(
									'platform' => 'linkedin',
									'url'      => 'https://example.test/in/jane',
								),
								array(
									'platform' => 'x',
									'url'      => 'https://example.test/x/jane',
								),
							),
						),
					),
					array(
						'ID'         => 2,
						'post_title' => 'John Smith',
						'menu_order' => 2,
						'fields'     => array( 'role' => 'Designer' ),
					),
					array(
						'ID'         => 3,
						'post_title' => 'Ada Jones',
						'menu_order' => 3,
						'fields'     => array(),
					),
				),
				array(
					'eyebrow' => 'Our people',
					'heading' => 'Meet the team',
					'columns' => '3',
				)
			)
		);
	}

	public function test_team_two_columns_headingless(): void {
		$this->assertMatchesHtmlSnapshot(
			'team-two-columns-headingless',
			$this->render_team(
				array(
					array(
						'ID'         => 1,
						'post_title' => 'Jane Doe',
						'menu_order' => 1,
						'fields'     => array( 'role' => 'Founder' ),
					),
					array(
						'ID'         => 2,
						'post_title' => 'John Smith',
						'menu_order' => 2,
						'fields'     => array( 'role' => 'Designer' ),
					),
				),
				array( 'columns' => '2' )
			)
		);
	}
}
