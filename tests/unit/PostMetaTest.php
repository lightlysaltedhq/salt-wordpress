<?php
/**
 * Post-meta services: reading time + share-link builders (pure helpers).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class PostMetaTest extends SaltTestCase {

	// -----------------------------------------------------------------------
	// Reading time.
	// -----------------------------------------------------------------------

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
}
