<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Pwa;
use WpApp\WpApp;

class PwaTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		global $__wp_app_test_actions, $__wp_app_test_action_counts, $__wp_app_test_filters, $__wp_app_test_rewrite_rules, $wp_app_route;

		$__wp_app_test_actions       = [];
		$__wp_app_test_action_counts = [];
		$__wp_app_test_filters       = [];
		$__wp_app_test_rewrite_rules = [];
		$wp_app_route                = null;
	}

	public function test_wpapp_can_enable_pwa_support_for_app_scope() {
		global $wp_app_route;

		$app = new WpApp(
			'/test/templates',
			'field-notes',
			[
				'app_name' => 'Field Notes',
			]
		);

		$config = $app->enable_pwa(
			[
				'short_name'  => 'Notes',
				'theme_color' => '#14532d',
				'precache'    => [
					'https://example.org/field-notes/assets/app.css',
				],
			]
		);

		$this->assertSame( 'https://example.org/field-notes/wp-app-manifest', $config['manifest_url'] );
		$this->assertSame( 'https://example.org/field-notes/wp-app-service-worker', $config['service_worker_url'] );
		$this->assertSame( 'Field Notes', $config['manifest']['name'] );
		$this->assertSame( 'Notes', $config['manifest']['short_name'] );

		$wp_app_route = [
			'app_path' => 'other-app',
		];

		ob_start();
		\wp_app_do_scoped_action( 'wp_app_head_meta' );
		$other_output = ob_get_clean();

		$this->assertStringNotContainsString( 'wp-app-manifest', $other_output );

		$wp_app_route = [
			'app_path' => 'field-notes',
		];

		ob_start();
		\wp_app_do_scoped_action( 'wp_app_head_meta' );
		$head_output = ob_get_clean();

		$this->assertStringContainsString( '<link rel="manifest" href="https://example.org/field-notes/wp-app-manifest">', $head_output );
		$this->assertStringContainsString( '<meta name="theme-color" content="#14532d">', $head_output );

		ob_start();
		\wp_app_do_scoped_action( 'wp_app_body_close' );
		$body_output = ob_get_clean();

		$this->assertStringContainsString( 'navigator.serviceWorker.register', $body_output );
		$this->assertStringContainsString( 'https:\/\/example.org\/field-notes\/wp-app-service-worker', $body_output );
		$this->assertStringContainsString( 'https:\/\/example.org\/field-notes\/', $body_output );
		$this->assertStringContainsString( 'window.wpAppPwa', $body_output );
		$this->assertStringContainsString( '[data-wp-app-cache-url]', $body_output );
		$this->assertStringContainsString( 'data-wp-app-cache-available', $body_output );
		$this->assertStringContainsString( 'wp-app-pwa-cache-status', $body_output );
	}

	public function test_manifest_endpoint_outputs_generated_manifest() {
		Pwa::register(
			'recipe-box',
			[
				'name'             => 'Recipe Box',
				'short_name'       => 'Recipes',
				'description'      => 'Offline recipe notes.',
				'background_color' => '#f8fafc',
				'theme_color'      => '#0f766e',
				'icons'            => [
					[
						'src'   => 'https://example.org/wp-content/plugins/recipe-box/icon-192.png',
						'sizes' => '192x192',
						'type'  => 'image/png',
					],
				],
			]
		);

		ob_start();
		$handled = Pwa::maybe_handle_app_request( 'recipe-box', 'wp-app-manifest' );
		$output  = ob_get_clean();

		$manifest = json_decode( $output, true );

		$this->assertTrue( $handled );
		$this->assertSame( 'Recipe Box', $manifest['name'] );
		$this->assertSame( 'Recipes', $manifest['short_name'] );
		$this->assertSame( 'https://example.org/recipe-box/', $manifest['start_url'] );
		$this->assertSame( 'https://example.org/recipe-box/', $manifest['scope'] );
		$this->assertSame( 'standalone', $manifest['display'] );
		$this->assertSame( 'Offline recipe notes.', $manifest['description'] );
		$this->assertSame( '192x192', $manifest['icons'][0]['sizes'] );
	}

	public function test_service_worker_endpoint_outputs_offline_cache_runtime() {
		Pwa::register(
			'recipe-cache',
			[
				'name'        => 'Recipe Cache',
				'offline_url' => 'https://example.org/recipe-cache/offline',
				'precache'    => [
					'https://example.org/recipe-cache/assets/app.css',
				],
			]
		);

		ob_start();
		$handled = Pwa::maybe_handle_app_request( 'recipe-cache', 'wp-app-service-worker' );
		$output  = ob_get_clean();

		$this->assertTrue( $handled );
		$this->assertStringContainsString( 'self.addEventListener("install"', $output );
		$this->assertStringContainsString( 'self.addEventListener("fetch"', $output );
		$this->assertStringContainsString( '"cacheName":"wp-app-recipe-cache-v1"', $output );
		$this->assertStringContainsString( 'https:\/\/example.org\/recipe-cache\/offline', $output );
		$this->assertStringContainsString( 'https:\/\/example.org\/recipe-cache\/assets\/app.css', $output );
	}

	public function test_travel_app_style_paths_and_messages_are_configurable() {
		Pwa::register(
			'travel-app',
			[
				'manifest_path'             => 'manifest.webmanifest',
				'service_worker_path'       => 'service-worker.js',
				'scope'                     => 'https://example.org/',
				'service_worker_allowed'    => '/',
				'cache_name'                => 'travel-app-v7',
				'cache_prefix'              => 'travel-app-',
				'cacheable_paths'           => [
					'/wp-content/plugins/travel-app/assets/',
					'/wp-content/uploads/',
				],
				'cacheable_search_params'   => [
					'travel_app_share=',
				],
				'cache_message_type'        => 'travel-app-cache-url',
				'cache_status_message_type' => 'travel-app-cache-status',
				'version_message_type'      => 'travel-app-version',
				'sync_tag'                  => 'travel-app-sync',
				'sync_message_type'         => 'travel-app-sync',
			]
		);

		ob_start();
		$handled = Pwa::maybe_handle_app_request( 'travel-app', 'service-worker.js' );
		$output  = ob_get_clean();

		$this->assertTrue( $handled );
		$this->assertStringContainsString( '"cacheName":"travel-app-v7"', $output );
		$this->assertStringContainsString( '"cachePrefix":"travel-app-"', $output );
		$this->assertStringContainsString( '\/wp-content\/plugins\/travel-app\/assets\/', $output );
		$this->assertStringContainsString( 'travel_app_share=', $output );
		$this->assertStringContainsString( 'travel-app-cache-url', $output );
		$this->assertStringContainsString( 'travel-app-sync', $output );
	}

	public function test_client_cache_status_helpers_are_configurable() {
		global $wp_app_route;

		Pwa::register(
			'download-kit',
			[
				'name'                             => 'Download Kit',
				'client_cache_urls'                => [
					'https://example.org/download-kit/',
					'https://example.org/download-kit/assets/app.css',
				],
				'client_cache_selector'            => '[data-download-cache-url]',
				'client_cache_url_attribute'       => 'data-download-cache-url',
				'client_cache_available_attribute' => 'data-download-cache-ready',
				'client_cache_status_event'        => 'download-kit-cache-status',
			]
		);

		$wp_app_route = [
			'app_path' => 'download-kit',
		];

		ob_start();
		\wp_app_do_scoped_action( 'wp_app_body_close' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'window.wpAppPwa', $output );
		$this->assertStringContainsString( '[data-download-cache-url]', $output );
		$this->assertStringContainsString( 'data-download-cache-ready', $output );
		$this->assertStringContainsString( 'download-kit-cache-status', $output );
		$this->assertStringContainsString( 'https:\/\/example.org\/download-kit\/assets\/app.css', $output );
		$this->assertStringContainsString( 'api.checkCache', $output );
	}

	public function test_scoped_manifest_filter_can_generate_contextual_manifest() {
		add_filter(
			'wp_app_pwa_manifest_travel-app-manifest',
			function ( $manifest, $config ) {
				$manifest['name']       = 'Summer Trip';
				$manifest['short_name'] = 'Summer Trip';
				$manifest['start_url']  = 'https://example.org/travel-app/trip/123/';
				$manifest['scope']      = 'https://example.org/';
				$manifest['icons']      = [
					[
						'src'   => 'https://example.org/travel-app/icon.svg',
						'sizes' => 'any',
						'type'  => 'image/svg+xml',
					],
				];

				return $manifest;
			},
			10,
			2
		);

		Pwa::register(
			'travel-app-manifest',
			[
				'name' => 'Travel Timeline',
			]
		);

		ob_start();
		$handled = Pwa::maybe_handle_app_request( 'travel-app-manifest', 'wp-app-manifest' );
		$output  = ob_get_clean();

		$manifest = json_decode( $output, true );

		$this->assertTrue( $handled );
		$this->assertSame( 'Summer Trip', $manifest['name'] );
		$this->assertSame( 'https://example.org/travel-app/trip/123/', $manifest['start_url'] );
		$this->assertSame( 'image/svg+xml', $manifest['icons'][0]['type'] );
	}

	public function test_manifest_filter_non_array_result_falls_back_to_generated_manifest() {
		add_filter(
			'wp_app_pwa_manifest_invalid-filter-result',
			function ( $manifest ) {
				return null;
			}
		);

		Pwa::register(
			'invalid-filter-result',
			[
				'name' => 'Generated Manifest',
			]
		);

		ob_start();
		$handled = Pwa::maybe_handle_app_request( 'invalid-filter-result', 'wp-app-manifest' );
		$output  = ob_get_clean();

		$manifest = json_decode( $output, true );

		$this->assertTrue( $handled );
		$this->assertSame( 'Generated Manifest', $manifest['name'] );
	}

	public function test_pwa_rewrite_rules_include_dotted_endpoint_paths() {
		global $__wp_app_test_rewrite_rules;

		Pwa::register(
			'travel-app-routes',
			[
				'manifest_path'       => 'manifest.webmanifest',
				'service_worker_path' => 'service-worker.js',
			]
		);

		Pwa::add_rewrite_rules_for_app( 'travel-app-routes' );

		$rules = array_column( $__wp_app_test_rewrite_rules, 'regex' );

		$this->assertContains( '^travel\\-app\\-routes/manifest\\.webmanifest/?$', $rules );
		$this->assertContains( '^travel\\-app\\-routes/service\\-worker\\.js/?$', $rules );
	}

	public function test_wpapp_exposes_manifest_url_with_query_args() {
		$app = new WpApp( '/test/templates', 'travel-app-link' );
		$app->enable_pwa(
			[
				'manifest_path' => 'manifest.webmanifest',
			]
		);

		$this->assertSame(
			'https://example.org/travel-app-link/manifest.webmanifest?trip_id=123&token=abc',
			$app->get_pwa_manifest_url(
				[
					'trip_id' => 123,
					'token'   => 'abc',
				]
			)
		);
	}

	public function test_pwa_endpoint_ignores_unregistered_app_request() {
		$this->assertFalse( Pwa::maybe_handle_app_request( 'missing-app', 'wp-app-manifest' ) );
	}
}
