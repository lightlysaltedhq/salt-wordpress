<?php
/**
 * Behavioural tests for x-heading (level clamp + size derivation),
 * x-accordion (0-state, malformed items, defensive sanitisation, open
 * attribute) and x-card (stretched link, level clamp).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

final class HeadingTest extends SaltTestCase {

	// -----------------------------------------------------------------------
	// x-heading: level clamp.
	// -----------------------------------------------------------------------

	/**
	 * Out-of-range levels are clamped into 1..6 and logged.
	 *
	 * @param int $level    Requested level.
	 * @param int $expected Rendered level.
	 */
	#[DataProvider( 'provide_clamped_levels' )]
	public function test_out_of_range_level_is_clamped_and_logged( int $level, int $expected ): void {
		$html = salt_get_component(
			'heading',
			array(
				'text'  => 'Clamped',
				'level' => $level,
			)
		);

		$this->assertStringContainsString( sprintf( '<h%d ', $expected ), $html );
		$this->assertStringContainsString( sprintf( '</h%d>', $expected ), $html );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'out of range', $this->logs()[0] );
	}

	/**
	 * @return array<string,array{int,int}>
	 */
	public static function provide_clamped_levels(): array {
		return array(
			'zero clamps to 1'     => array( 0, 1 ),
			'negative clamps to 1' => array( -3, 1 ),
			'seven clamps to 6'    => array( 7, 6 ),
			'huge clamps to 6'     => array( 99, 6 ),
		);
	}

	/**
	 * In-range levels render verbatim without a log line.
	 */
	public function test_in_range_level_renders_without_log(): void {
		$html = salt_get_component(
			'heading',
			array(
				'text'  => 'Fine',
				'level' => 4,
			)
		);

		$this->assertStringContainsString( '<h4 ', $html );
		$this->assertSame( array(), $this->logs() );
	}

	// -----------------------------------------------------------------------
	// x-heading: size derivation + override.
	// -----------------------------------------------------------------------

	/**
	 * Empty size derives the visual scale from the semantic level.
	 *
	 * @param int    $level    Level.
	 * @param string $expected Expected size class fragment.
	 */
	#[DataProvider( 'provide_derived_sizes' )]
	public function test_size_derives_from_level( int $level, string $expected ): void {
		$html = salt_get_component(
			'heading',
			array(
				'text'  => 'Derived',
				'level' => $level,
			)
		);

		$this->assertStringContainsString( $expected, $html );
	}

	/**
	 * @return array<string,array{int,string}>
	 */
	public static function provide_derived_sizes(): array {
		return array(
			'level 1 => xl'  => array( 1, 'text-4xl md:text-5xl font-bold tracking-tight' ),
			'level 2 => lg'  => array( 2, 'text-3xl md:text-4xl font-bold tracking-tight' ),
			'level 3 => md'  => array( 3, 'text-xl md:text-2xl font-semibold' ),
			'level 4+ => sm' => array( 5, 'text-lg font-semibold' ),
		);
	}

	/**
	 * An explicit size overrides the level-derived scale.
	 */
	public function test_size_override_beats_level_derivation(): void {
		$html = salt_get_component(
			'heading',
			array(
				'text'  => 'Overridden',
				'level' => 3,
				'size'  => 'xl',
			)
		);

		$this->assertStringContainsString( '<h3 ', $html );
		$this->assertStringContainsString( 'text-4xl md:text-5xl font-bold tracking-tight', $html );
	}

	/**
	 * An unknown size logs and falls back to the level-derived scale.
	 */
	public function test_unknown_size_logs_and_derives(): void {
		$html = salt_get_component(
			'heading',
			array(
				'text'  => 'Odd size',
				'level' => 2,
				'size'  => 'gigantic',
			)
		);

		$this->assertStringContainsString( 'text-3xl md:text-4xl font-bold tracking-tight', $html );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'unknown size', $this->logs()[0] );
	}

	/**
	 * Heading text is escaped at output.
	 */
	public function test_heading_text_is_escaped(): void {
		$html = salt_get_component(
			'heading',
			array(
				'text'  => 'Fish & <em>chips</em>',
				'level' => 2,
			)
		);

		$this->assertStringContainsString( 'Fish &amp; &lt;em&gt;chips&lt;/em&gt;', $html );
	}

	// -----------------------------------------------------------------------
	// x-accordion.
	// -----------------------------------------------------------------------

	/**
	 * Zero items renders nothing (COMPL02 0-state).
	 */
	public function test_accordion_empty_items_renders_nothing(): void {
		$this->assertSame( '', salt_get_component( 'accordion', array( 'items' => array() ) ) );
	}

	/**
	 * Items missing a title are skipped and logged; valid siblings render.
	 */
	public function test_accordion_malformed_item_skipped_and_logged(): void {
		$html = salt_get_component(
			'accordion',
			array(
				'items' => array(
					array( 'content' => '<p>No title here.</p>' ),
					array(
						'title'   => 'Valid item',
						'content' => '<p>Body.</p>',
					),
				),
			)
		);

		$this->assertSame( 1, substr_count( $html, '<details' ) );
		$this->assertStringContainsString( 'Valid item', $html );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'missing title', $this->logs()[0] );
	}

	/**
	 * Content is defensively passed through salt_sanitize_html().
	 */
	public function test_accordion_content_is_sanitized(): void {
		$html = salt_get_component(
			'accordion',
			array(
				'items' => array(
					array(
						'title'   => 'Dirty',
						'content' => '<p onclick="evil()">Keep me.</p><script>alert(1)</script>',
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
		$this->assertStringContainsString( '<p>Keep me.</p>', $html );
	}

	/**
	 * open => true renders the native open attribute; absent otherwise.
	 */
	public function test_accordion_open_attribute(): void {
		$html = salt_get_component(
			'accordion',
			array(
				'items' => array(
					array(
						'title' => 'Opened',
						'open'  => true,
					),
					array( 'title' => 'Closed' ),
				),
			)
		);

		$this->assertSame( 1, substr_count( $html, ' open>' ) );
	}

	/**
	 * All items share the native <details name> group for exclusive open.
	 */
	public function test_accordion_name_groups_items(): void {
		$html = salt_get_component(
			'accordion',
			array(
				'items' => array(
					array( 'title' => 'One' ),
					array( 'title' => 'Two' ),
				),
				'name'  => 'faq',
			)
		);

		$this->assertSame( 2, substr_count( $html, 'name="faq"' ) );
	}

	// -----------------------------------------------------------------------
	// x-card.
	// -----------------------------------------------------------------------

	/**
	 * The stretched-link anchor renders only when a url is given.
	 */
	public function test_card_stretched_link_only_when_url(): void {
		$linked = salt_get_component(
			'card',
			array(
				'title' => 'Linked card',
				'url'   => 'https://example.test/post/',
			)
		);
		$plain  = salt_get_component( 'card', array( 'title' => 'Plain card' ) );

		$this->assertStringContainsString( 'after:absolute after:inset-0', $linked );
		$this->assertStringContainsString( 'href="https://example.test/post/"', $linked );
		$this->assertStringNotContainsString( '<a ', $plain );
		$this->assertStringNotContainsString( 'after:absolute', $plain );
	}

	/**
	 * Card level clamps like x-heading, with a log line.
	 *
	 * @param int $level    Requested level.
	 * @param int $expected Rendered level.
	 */
	#[DataProvider( 'provide_card_clamped_levels' )]
	public function test_card_level_clamped_and_logged( int $level, int $expected ): void {
		$html = salt_get_component(
			'card',
			array(
				'title' => 'Clamped card',
				'level' => $level,
			)
		);

		$this->assertStringContainsString( sprintf( '<h%d ', $expected ), $html );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'out of range', $this->logs()[0] );
	}

	/**
	 * @return array<string,array{int,int}>
	 */
	public static function provide_card_clamped_levels(): array {
		return array(
			'zero clamps to 1'  => array( 0, 1 ),
			'seven clamps to 6' => array( 7, 6 ),
		);
	}

	/**
	 * Default card level is h3, no log.
	 */
	public function test_card_default_level_is_h3(): void {
		$html = salt_get_component( 'card', array( 'title' => 'Default level' ) );

		$this->assertStringContainsString( '<h3 ', $html );
		$this->assertSame( array(), $this->logs() );
	}

	/**
	 * Body text is escaped; media/meta slots pass through raw.
	 */
	public function test_card_body_escaped_slots_raw(): void {
		$html = salt_get_component(
			'card',
			array(
				'title' => 'Slots',
				'media' => '<img src="https://example.test/a.jpg" alt="">',
				'meta'  => '<time datetime="2026-01-01">1 Jan 2026</time>',
				'body'  => 'Tom & Jerry <b>bold</b>',
			)
		);

		$this->assertStringContainsString( '<img src="https://example.test/a.jpg" alt="">', $html );
		$this->assertStringContainsString( '<time datetime="2026-01-01">1 Jan 2026</time>', $html );
		$this->assertStringContainsString( 'Tom &amp; Jerry &lt;b&gt;bold&lt;/b&gt;', $html );
	}
}
