<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Omitting the scope resolves it from whatever is rendering, which is almost
 * never what the caller means. The behaviour is unchanged; it just says so.
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

	public function test_enqueueing_a_script_without_a_scope_warns() {
		wp_app_enqueue_script( 'demo', 'https://example.org/demo.js' );

		$warnings = $this->warnings();

		$this->assertCount( 1, $warnings );
		$this->assertSame( 'wp_app_enqueue_script', $warnings[0]['function'] );
		$this->assertSame( '1.7.0', $warnings[0]['version'] );
		$this->assertStringContainsString( 'scope', $warnings[0]['message'] );
	}

	public function test_enqueueing_a_style_without_a_scope_warns() {
		wp_app_enqueue_style( 'demo', 'https://example.org/demo.css' );

		$this->assertSame( 'wp_app_enqueue_style', $this->warnings()[0]['function'] );
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

	public function test_the_warning_does_not_change_where_the_asset_lands() {
		global $__wp_app_test_actions, $wp_app_route;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub sets the route.
		$wp_app_route = [ 'app_path' => 'rendering-app' ];

		wp_app_enqueue_script( 'demo', 'https://example.org/demo.js' );

		$this->assertArrayHasKey( 'wp_app_body_close_rendering-app', $__wp_app_test_actions );
		$this->assertNotEmpty( $this->warnings() );
	}
}
