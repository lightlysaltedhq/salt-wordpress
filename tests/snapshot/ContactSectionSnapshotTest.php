<?php
/**
 * Contact section snapshots (FLEET01) — one per meaningful variant:
 * the v1 default (details + form_intro + mailto CTA; form OFF), and the
 * compliance-phase form (salt_contact_form_enabled true — honeypot,
 * label + aria-describedby fields, submit button).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class ContactSectionSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Freeze the form's time-trap token so snapshots are byte-stable.
		add_filter( 'salt_contact_clock', static fn(): int => 1783000000, 20 );
		salt_test_seed_fc( array() );

		$GLOBALS['salt_test_fields']['option'] = array(
			'org_name'      => 'Lightly Salted',
			'company_phone' => '+44 1905 123456',
			'company_email' => 'hello@example.test',
		);
	}

	/**
	 * Render ONE contact section row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_contact_section( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'contact',
					'fields' => $fields,
				),
			)
		);
		have_rows( 'sections' );
		the_row();

		ob_start();
		salt_render_section(
			'contact',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_contact_details_only_default(): void {
		$this->assertMatchesHtmlSnapshot(
			'contact-details-default',
			$this->render_contact_section(
				array(
					'heading'    => 'Get in touch',
					'text'       => 'We usually reply within one working day.',
					'form_intro' => 'Prefer email? Use the button below.',
				)
			)
		);
	}

	public function test_contact_form_enabled(): void {
		add_filter( 'salt_contact_form_enabled', static fn (): bool => true, 20 );

		$this->assertMatchesHtmlSnapshot(
			'contact-form-enabled',
			$this->render_contact_section(
				array(
					'heading'    => 'Get in touch',
					'text'       => 'We usually reply within one working day.',
					'form_intro' => 'Send us a message and we will get back to you.',
				)
			)
		);
	}
}
