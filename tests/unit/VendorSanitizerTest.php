<?php
/**
 * Vendored SVG sanitiser: production loading path + behaviour smoke test.
 *
 * SEC04: the sanitiser is the sole defence against stored XSS via uploaded
 * SVGs. This suite proves (a) the theme's own autoloader resolves the
 * vendored classes, and (b) the pinned library actually strips script
 * vectors — so a bad re-vendor or a broken pin fails CI, not a client site.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use enshrined\svgSanitize\Sanitizer;
use PHPUnit\Framework\TestCase;

final class VendorSanitizerTest extends TestCase {

	public function test_vendor_class_path_resolves_sanitizer(): void {
		$path = salt_vendor_class_path( 'enshrined\\svgSanitize\\Sanitizer' );

		$this->assertNotNull( $path );
		$this->assertFileExists( $path );
	}

	public function test_vendor_class_path_ignores_foreign_classes(): void {
		$this->assertNull( salt_vendor_class_path( 'Some\\Other\\Library' ) );
	}

	public function test_autoloader_loads_and_sanitizer_strips_script(): void {
		salt_register_vendor_autoloader();

		$sanitizer = new Sanitizer();
		$dirty     = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><rect width="10" height="10"/></svg>';
		$clean     = $sanitizer->sanitize( $dirty );

		$this->assertNotFalse( $clean );
		$this->assertStringNotContainsString( '<script', $clean );
		$this->assertStringNotContainsString( 'onload', $clean );
		$this->assertStringContainsString( '<rect', $clean );
	}
}
