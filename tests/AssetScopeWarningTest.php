<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Passing a null scope resolves it from whatever is rendering, which is almost
 * never what the caller means.
 */
class AssetScopeWarningTest extends TestCase {
	protected function setUp(): void {
		global $__wp_app_test_actions, $__wp_app_test_doing_it_wrong, $wp_app_route;

		$__wp_app_test_actions        = [];
		$__wp_app_test_doing_it_wrong = [];
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub resets the route.
		$wp_app_route = null;
	}

	private function warnings() {
		global $__wp_app_test_doing_it_wrong;

		return $__wp_app_test_doing_it_wrong;
	}

	public function test_enqueue_helpers_require_scope_parameter() {
		$script           = new \ReflectionFunction( 'wp_app_enqueue_script' );
		$style            = new \ReflectionFunction( 'wp_app_enqueue_style' );
		$crypto           = new \ReflectionFunction( 'wp_app_enqueue_crypto_runtime' );
		$encrypted_fields = new \ReflectionFunction( 'wp_app_enqueue_encrypted_fields_runtime' );
		$script_scope     = $script->getParameters()[5];
		$style_scope      = $style->getParameters()[4];

		$this->assertSame( 'scope', $script_scope->getName() );
		$this->assertFalse( $script_scope->isDefaultValueAvailable() );
		$this->assertSame( 'scope', $style_scope->getName() );
		$this->assertFalse( $style_scope->isDefaultValueAvailable() );
		$this->assertSame( 6, $script->getNumberOfRequiredParameters() );
		$this->assertSame( 5, $style->getNumberOfRequiredParameters() );
		$this->assertSame( 1, $crypto->getNumberOfRequiredParameters() );
		$this->assertSame( 1, $encrypted_fields->getNumberOfRequiredParameters() );
	}

	public function test_inline_helpers_without_a_scope_warn() {
		wp_app_add_inline_script( 'demo', 'void 0;' );
		wp_app_add_inline_style( 'demo', 'body{}' );

		$this->assertSame(
			[ 'wp_app_add_inline_script', 'wp_app_add_inline_style' ],
			array_column( $this->warnings(), 'function' )
		);
	}

	public function test_naming_a_scope_does_not_warn() {
		wp_app_enqueue_script( 'demo', 'https://example.org/demo.js', [], false, true, 'demo-app' );
		wp_app_enqueue_style( 'demo', 'https://example.org/demo.css', [], false, 'demo-app' );

		$this->assertSame( [], $this->warnings() );
	}

	public function test_asking_for_global_scope_does_not_warn() {
		wp_app_enqueue_script( 'demo', 'https://example.org/demo.js', [], false, true, 'global' );

		$this->assertSame( [], $this->warnings() );
	}

}
