<?php
/**
 * Post-meta services: reading time + share-link builders (pure helpers).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class PostMetaTest extends SaltTestCase {

	// -----------------------------------------------------------------------
	// Reading time.
	// -----------------------------------------------------------------------

	public function test_the_reading_text_is_every_row_of_the_posts_layout_and_nothing_else(): void {
		// A rich-text row and an FAQ row, stored by key; the FAQ row queries one FAQ.
		$k = 'field_salt_collection_posts_layout_';
		$GLOBALS['salt_test_fields']['42']['field_salt_collection_posts_layout'] = array(
			array(
				'acf_fc_layout'           => 'rich-text',
				$k . 'rich_text_heading'  => 'Pruning in winter',
				$k . 'rich_text_body'     => '<p>Cut the <strong>old wood</strong> back.</p>',
				$k . 'rich_text_settings' => array( $k . 'rich_text_settings_anchor_id' => 'not-read' ),
			),
			array(
				'acf_fc_layout'    => 'faq',
				$k . 'faq_heading' => 'Questions',
				$k . 'faq_query'   => array( $k . 'faq_query_mode' => 'automatic' ),
			),
		);
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'rich-text',
					'fields' => array(),
				),
				array(
					'layout' => 'faq',
					'fields' => array( 'query' => array( 'mode' => 'automatic' ) ),
				),
			)
		);
		$this->seed_posts(
			array(
				array(
					'ID'         => 7,
					'post_type'  => 'faq',
					'post_title' => 'When should I prune?',
				),
			)
		);
		$GLOBALS['salt_test_fields']['7']['answer'] = '<p>In late <em>winter</em>.</p>';
		// What a site's header and footer say is never read from here.
		$GLOBALS['salt_test_fields']['option']['footer_text'] = 'Footer words';

		$text = salt_post_reading_text( 42 );

		$this->assertSame( 'Pruning in winter Cut the old wood back. Questions When should I prune? In late winter.', $text );
		$this->assertStringNotContainsString( 'not-read', $text, 'A row\'s settings are not read.' );
		$this->assertStringNotContainsString( 'Footer words', $text );
	}

	public function test_empty_content_reads_as_zero_minutes(): void {
		$this->assertSame( 0, salt_reading_time_minutes( '' ) );
		$this->assertSame( 0, salt_reading_time_minutes( '   ' ) );
	}

	public function test_short_content_reads_as_at_least_one_minute(): void {
		$this->assertSame( 1, salt_reading_time_minutes( 'A handful of words only.' ) );
	}

	public function test_reading_time_rounds_up_at_200_wpm(): void {
		$this->assertSame( 2, salt_reading_time_minutes( implode( ' ', array_fill( 0, 201, 'word' ) ) ) );
		$this->assertSame( 1, salt_reading_time_minutes( implode( ' ', array_fill( 0, 200, 'word' ) ) ) );
	}

	public function test_reading_time_strips_markup_before_counting(): void {
		$html = '<p>' . implode( ' ', array_fill( 0, 50, 'word' ) ) . '</p><ul><li>word</li></ul>';
		$this->assertSame( 1, salt_reading_time_minutes( $html ) );
	}

	public function test_non_positive_wpm_degrades_to_default_and_logs(): void {
		$this->assertSame( 1, salt_reading_time_minutes( 'some words here', 0 ) );
		$this->assertStringContainsString( 'non-positive wpm', implode( "\n", $this->logs() ) );
	}

	// -----------------------------------------------------------------------
	// Share links.
	// -----------------------------------------------------------------------

	public function test_default_suite_builds_seven_services_in_order(): void {
		$links = salt_share_links( 'https://example.test/post/', 'Title' );

		$this->assertSame(
			array( 'x', 'facebook', 'linkedin', 'whatsapp', 'bluesky', 'reddit', 'email' ),
			array_column( $links, 'key' )
		);
	}

	public function test_share_urls_encode_the_target_url_and_title(): void {
		$links = salt_share_links( 'https://example.test/post/?a=b', 'Salt & pepper' );
		$by    = array_column( $links, 'url', 'key' );

		$this->assertSame( 'https://x.com/intent/post?url=https%3A%2F%2Fexample.test%2Fpost%2F%3Fa%3Db&text=Salt%20%26%20pepper', $by['x'] );
		$this->assertSame( 'mailto:?subject=Salt%20%26%20pepper&body=https%3A%2F%2Fexample.test%2Fpost%2F%3Fa%3Db', $by['email'] );
	}

	public function test_unknown_service_key_is_skipped_and_logged(): void {
		$links = salt_share_links( 'https://example.test/post/', 'Title', array( 'x', 'myspace' ) );

		$this->assertSame( array( 'x' ), array_column( $links, 'key' ) );
		$this->assertStringContainsString( 'unknown share service "myspace"', implode( "\n", $this->logs() ) );
	}

	public function test_empty_url_builds_no_links_and_logs(): void {
		$this->assertSame( array(), salt_share_links( '', 'Title' ) );
		$this->assertStringContainsString( 'empty url', implode( "\n", $this->logs() ) );
	}

	public function test_every_registry_icon_is_a_bundled_asset(): void {
		foreach ( salt_share_services() as $key => $service ) {
			$path = dirname( __DIR__, 2 ) . '/assets/src/icons/' . $service['icon'] . '.svg';
			$this->assertFileExists( $path, "share service {$key} points at a missing icon" );
		}
	}

	// -----------------------------------------------------------------------
	// A post's layout: which rich-text rows are the post's prose.
	// -----------------------------------------------------------------------

	public function test_a_rich_text_row_left_alone_is_prose(): void {
		$defaults = salt_post_prose_defaults();

		$this->assertSame( 'left', $defaults['alignment'] );
		$this->assertSame( 'md', $defaults['settings']['spacing'] );
		$this->assertTrue( salt_post_row_is_prose( array(), $defaults ), 'Nothing set.' );
		$this->assertTrue(
			salt_post_row_is_prose(
				array(
					'eyebrow'   => '',
					'heading'   => '',
					'alignment' => 'left',
					'settings'  => array(
						'spacing'         => 'md',
						'tone'            => 'surface',
						'toneDark'        => 'auto',
						'width'           => 'default',
						'divider'         => false,
						'backgroundImage' => array(
							'image'         => null,
							'fit'           => 'cover',
							'scrimStrength' => 'strong',
						),
						'anchorId'        => '',
						'firstPageOnly'   => true,
					),
				),
				$defaults
			),
			'Every setting at its default, as the form stores it (firstPageOnly is not one the rule names).'
		);
	}

	public function test_a_rich_text_row_an_editor_set_anything_on_is_a_band(): void {
		$defaults = salt_post_prose_defaults();
		foreach ( array(
			'an eyebrow'    => array( 'eyebrow' => 'Note' ),
			'a heading'     => array( 'heading' => 'Why' ),
			'centred'       => array( 'alignment' => 'centre' ),
			'small spacing' => array( 'settings' => array( 'spacing' => 'sm' ) ),
			'a tone'        => array( 'settings' => array( 'tone' => 'brand-tint' ) ),
			'a dark tone'   => array( 'settings' => array( 'toneDark' => 'surface' ) ),
			'a width'       => array( 'settings' => array( 'width' => 'wide' ) ),
			'a divider'     => array( 'settings' => array( 'divider' => true ) ),
			'an anchor'     => array( 'settings' => array( 'anchorId' => 'notes' ) ),
			'a background'  => array( 'settings' => array( 'backgroundImage' => array( 'image' => 12 ) ) ),
		) as $what => $row ) {
			$this->assertFalse( salt_post_row_is_prose( $row, $defaults ), $what );
		}
	}
}
