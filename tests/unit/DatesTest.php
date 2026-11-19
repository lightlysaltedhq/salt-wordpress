<?php
/**
 * Dates follow the site's language (the founder's ruling of 19/11/2026):
 * how salt_date_format() chooses a format and what salt_date_text(),
 * salt_post_date_text() and salt_contract_date_text() draw
 * (inc/query/cards.php). A language pack's own format is proved on real
 * WordPress by the real ACF harness (tests/real-acf/php/singles.php).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/blog.php';

final class DatesTest extends SaltTestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['salt_test_locale'] );
		parent::tearDown();
	}

	/**
	 * Site language, Date Format setting, what 12 March 2026 is drawn as.
	 *
	 * @return array<string,array{string,string|null,string}>
	 */
	public static function date_provider(): array {
		return array(
			'en-GB, the stock setting: the language\'s long date' => array( 'en_GB', 'F j, Y', '12 March 2026' ),
			'en-GB, no setting'                   => array( 'en_GB', null, '12 March 2026' ),
			'en-US, the stock setting'            => array( 'en_US', 'F j, Y', 'March 12, 2026' ),
			'en-GB, its language pack\'s setting' => array( 'en_GB', 'j F Y', '12 March 2026' ),
			'an owner\'s own setting is kept'     => array( 'en_GB', 'd/m/Y', '12/03/2026' ),
			'an owner\'s own setting on en-US'    => array( 'en_US', 'j F Y', '12 March 2026' ),
		);
	}

	#[DataProvider( 'date_provider' )]
	public function test_a_date_follows_the_sites_language( string $locale, ?string $setting, string $text ): void {
		$GLOBALS['salt_test_locale'] = $locale;
		if ( null !== $setting ) {
			$GLOBALS['salt_test_options']['date_format'] = $setting;
		}

		$this->assertSame( $text, salt_contract_date_text( '2026-03-12' ) );
		$this->assertSame( $text, salt_date_text( (int) gmmktime( 9, 0, 0, 3, 12, 2026 ) ) );
	}

	public function test_a_posts_dates_are_drawn_in_the_sites_timezone_and_language(): void {
		$GLOBALS['salt_test_options']['timezone_string'] = 'Europe/London';
		$this->seed_posts(
			array(
				array(
					'ID'            => 5,
					'post_type'     => 'post',
					'post_date'     => '2026-03-12 23:30:00',
					'post_modified' => '2026-04-01 08:00:00',
				),
			)
		);

		$this->assertSame( '12 March 2026', salt_post_date_text( 5 ) );
		$this->assertSame( '1 April 2026', salt_post_date_text( 5, 'modified' ) );
		$this->assertSame( '', salt_post_date_text( 99 ), 'No post, no date.' );
	}
}
