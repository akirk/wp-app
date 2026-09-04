<?php
use PHPUnit\Framework\TestCase;

class LanguageAttributesTest extends TestCase {
	protected function setUp(): void {
		global $__wp_app_test_current_locale, $__wp_app_test_is_rtl;
		$__wp_app_test_current_locale = 'en_US';
		$__wp_app_test_is_rtl         = false;
	}

	/**
	 * The whole point of the 1.8.0 change: printing and also returning made
	 * `echo wp_app_language_attributes()` emit the attributes twice.
	 */
	public function test_echoing_the_return_value_prints_the_attributes_once() {
		ob_start();
		echo wp_app_language_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Asserting on the output.
		$printed = ob_get_clean();

		$this->assertSame( 1, substr_count( $printed, 'lang=' ) );
	}

	public function test_printing_returns_an_empty_string() {
		ob_start();
		$returned = wp_app_language_attributes();
		$printed  = ob_get_clean();

		$this->assertNotSame( '', $printed );
		$this->assertSame( '', $returned );
	}

	public function test_passing_false_returns_without_printing() {
		ob_start();
		$returned = wp_app_language_attributes( false );
		$printed  = ob_get_clean();

		$this->assertSame( '', $printed );
		$this->assertStringContainsString( 'lang=', $returned );
	}
}
