<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;

class ScopedHooksTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		global $__wp_app_test_actions, $__wp_app_test_action_counts, $wp_app_route;

		$__wp_app_test_actions       = [];
		$__wp_app_test_action_counts = [];
		$wp_app_route                = null;
	}

	public function test_scoped_hook_name_uses_current_app_path() {
		global $wp_app_route;

		$wp_app_route = [
			'app_path' => 'wordcamp-companion',
		];

		$this->assertSame(
			'wp_app_body_close_wordcamp-companion',
			\wp_app_get_scoped_hook_name( 'wp_app_body_close' )
		);
	}

	public function test_scoped_hook_name_can_use_explicit_app_scope() {
		$this->assertSame(
			'wp_app_head_styles_my-app',
			\wp_app_get_scoped_hook_name( 'wp_app_head_styles', [ 'app' => 'my-app' ] )
		);
	}

	public function test_scoped_action_runs_global_and_current_app_hooks() {
		global $wp_app_route;

		$events       = [];
		$wp_app_route = [
			'app_path' => 'wordcamp-companion',
		];

		\add_action(
			'wp_app_body_close',
			function () use ( &$events ) {
				$events[] = 'global';
			}
		);
		\add_action(
			'wp_app_body_close_wordcamp-companion',
			function () use ( &$events ) {
				$events[] = 'wordcamp';
			}
		);
		\add_action(
			'wp_app_body_close_other-app',
			function () use ( &$events ) {
				$events[] = 'other';
			}
		);

		\wp_app_do_scoped_action( 'wp_app_body_close' );

		$this->assertSame( [ 'global', 'wordcamp' ], $events );
	}

	public function test_script_enqueued_in_app_context_only_prints_for_that_app() {
		global $wp_app_route;

		$wp_app_route = [
			'app_path' => 'wordcamp-companion',
		];

		\wp_app_enqueue_script(
			'wordcamp-companion',
			'https://example.org/wordcamp.js',
			[],
			'1.0.0'
		);

		$wp_app_route = [
			'app_path' => 'other-app',
		];

		ob_start();
		\wp_app_body_close();
		$other_app_output = ob_get_clean();

		$this->assertStringNotContainsString( 'wordcamp.js', $other_app_output );

		$wp_app_route = [
			'app_path' => 'wordcamp-companion',
		];

		ob_start();
		\wp_app_body_close();
		$wordcamp_output = ob_get_clean();

		$this->assertStringContainsString( 'wordcamp.js?ver=1.0.0', $wordcamp_output );
	}

	public function test_global_script_scope_still_prints_for_any_app() {
		global $wp_app_route;

		\wp_app_enqueue_script(
			'global-script',
			'https://example.org/global.js',
			[],
			false,
			true,
			[ 'app' => 'global' ]
		);

		$wp_app_route = [
			'app_path' => 'other-app',
		];

		ob_start();
		\wp_app_body_close();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'global.js', $output );
	}

	public function test_crypto_runtime_can_be_enqueued_for_app_scope() {
		global $wp_app_route;

		\wp_app_enqueue_crypto_runtime( 'encrypted-sources' );

		$wp_app_route = [
			'app_path' => 'other-app',
		];

		ob_start();
		\wp_app_body_close();
		$other_app_output = ob_get_clean();

		$this->assertStringNotContainsString( 'wp-app-crypto.js', $other_app_output );

		$wp_app_route = [
			'app_path' => 'encrypted-sources',
		];

		ob_start();
		\wp_app_body_close();
		$encrypted_app_output = ob_get_clean();

		$this->assertStringContainsString( 'wp-app-crypto-js', $encrypted_app_output );
		$this->assertStringContainsString( 'wp-app-crypto.js?ver=' . WP_APP_VERSION, $encrypted_app_output );
	}
}
