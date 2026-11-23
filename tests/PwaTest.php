<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Pwa;
use WpApp\WpApp;

class PwaTest extends TestCase {
	public function test_shell_policy_precaches_only_explicit_shell_and_assets() {
		$config = Pwa::register(
            'private-notes',
            [
				'cache_policy' => 'shell',
				'offline_url'  => 'https://example.org/private-notes/capture/',
				'precache'     => [ 'https://example.org/assets/capture.js' ],
			]
        );
		$this->assertSame( 'shell', $config['cache_policy'] );
		$this->assertSame( [ 'https://example.org/private-notes/capture/', 'https://example.org/assets/capture.js' ], $config['precache'] );
	}

	public function test_shell_policy_requires_explicit_shell_url() {
		$this->expectException( \InvalidArgumentException::class );
		Pwa::register( 'private-notes', [ 'cache_policy' => 'shell' ] );
	}

	public function test_unknown_policy_cannot_silently_enable_scope_caching() {
		$this->expectException( \InvalidArgumentException::class );
		Pwa::register( 'private-notes', [ 'cache_policy' => 'typo' ] );
	}

	public function test_visited_pages_require_an_account_identity() {
		$this->expectException( \InvalidArgumentException::class );
		Pwa::register(
            'private-notes',
            [
				'cache_policy'  => 'shell',
				'offline_url'   => 'https://example.org/private-notes/',
				'visited_pages' => true,
			]
        );
	}

	public function test_visited_page_caches_are_isolated_by_identity() {
		$settings = [
			'cache_policy'  => 'shell',
			'offline_url'   => 'https://example.org/private-notes/',
			'visited_pages' => true,
		];
		$first    = Pwa::register( 'private-notes', $settings + [ 'cache_identity' => '7' ] );
		$second   = Pwa::register( 'private-notes', $settings + [ 'cache_identity' => '8' ] );
		$this->assertNotSame( $first['cache_name'], $second['cache_name'] );
	}

	public function test_enabled_offline_features_load_and_precache_their_own_assets_in_app_scope() {
		global $wp_app_route;
		$config = Pwa::register(
            'offline-assets',
            [
				'cache_policy'   => 'shell',
				'offline_url'    => 'https://example.org/offline-assets/capture/',
				'outbox'         => true,
				'visited_pages'  => true,
				'cache_identity' => '7',
			]
        );
		do_action( 'wp_enqueue_scripts' );
		$wp_app_route = [ 'app_path' => 'another-app' ];
		ob_start();
		wp_app_do_scoped_action( 'wp_app_head_scripts' );
		$other = ob_get_clean();
		$this->assertStringNotContainsString( 'wp-app-outbox', $other );
		$this->assertStringNotContainsString( 'wp-app-offline-client', $other );

		$wp_app_route = [ 'app_path' => 'offline-assets' ];
		ob_start();
		wp_app_do_scoped_action( 'wp_app_head_scripts' );
		$html = ob_get_clean();
		foreach ( [ 'wp-app-offline-data', 'wp-app-outbox', 'wp-app-offline-client' ] as $handle ) {
			$this->assertStringContainsString( $handle . '-js', $html );
			$this->assertContains( $config['runtime_assets'][ $handle ], $config['precache'] );
			$this->assertStringContainsString( $config['runtime_assets'][ $handle ], $html );
		}
	}

	public function test_outbox_alone_does_not_load_visited_page_runtime() {
		$config = Pwa::register( 'outbox-only', [ 'outbox' => true ] );
		$this->assertArrayHasKey( 'wp-app-outbox', $config['runtime_assets'] );
		$this->assertArrayNotHasKey( 'wp-app-offline-client', $config['runtime_assets'] );
	}

	public function test_shared_shell_is_dispatched_without_live_head_injection_or_inline_scripts() {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ . '/fixtures/' ); }
		$config = Pwa::register(
            'shared-shell',
            [
				'cache_policy'   => 'shell',
				'local_data'     => true,
				'cache_identity' => '7',
				'offline_shell'  => [
					'content'       => __DIR__ . '/fixtures/templates/offline-content.php',
					'bootstrap_url' => 'https://example.org/shared-shell/config',
					'offline_data'  => [ 'userId' => 7 ],
				],
			]
        );
		add_action(
            'wp_head',
            static function () {
				echo 'live-secret-must-not-be-cached';
			}
        );
		add_action(
            'wp_footer',
            static function () {
				echo 'live-footer-must-not-be-cached';
			}
        );
		ob_start();
		$handled = Pwa::maybe_handle_app_request( 'shared-shell', 'offline' );
		$html    = ob_get_clean();
		preg_match( '/data-wp-app-bootstrap="([^"]+)"/', $html, $match );
		$bootstrap = json_decode( html_entity_decode( $match[1], ENT_QUOTES ), true );
		$this->assertSame( $config['service_worker_url'], $bootstrap['worker']['url'] );
		$this->assertSame( $config['cache_name'], $bootstrap['worker']['cacheName'] );
		$this->assertSame( $config['precache'], $bootstrap['worker']['requiredUrls'] );
		$this->assertTrue( $handled );
		$this->assertStringContainsString( 'test-app-shell', $html );
		$this->assertStringContainsString( 'data-wp-app-bootstrap=', $html );
		$this->assertStringContainsString( 'wp-app-offline-data-js', $html );
		$this->assertStringContainsString( 'wp-app-offline-client-js', $html );
		$this->assertStringNotContainsString( 'live-secret', $html );
		$this->assertStringNotContainsString( 'live-footer', $html );
		$this->assertDoesNotMatchRegularExpression( '/<script(?![^>]*src=)[^>]*>/', $html );
	}

	protected function setUp(): void {
		parent::setUp();
		wp_scripts()->done  = [];
		wp_scripts()->queue = [];

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
			__DIR__ . '/fixtures/templates',
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

	public function test_worker_extensions_share_the_framework_worker_and_version_their_contents() {
		$file = tempnam( sys_get_temp_dir(), 'wp-app-worker-' );
		try {
			file_put_contents( $file, 'self.addEventListener("push", event => self.registration.showNotification(self.wpAppWorker.appData.title));' );
			$settings = [
				'worker_scripts' => [ $file ],
				'worker_data'    => [ 'title' => 'My app' ],
			];
			$first    = Pwa::register( 'push-app', $settings );
			ob_start();
			$this->assertTrue( Pwa::maybe_handle_app_request( 'push-app', 'wp-app-service-worker' ) );
			$script = ob_get_clean();
			$this->assertStringContainsString( '"appData":{"title":"My app"}', $script );
			$this->assertStringContainsString( 'self.addEventListener("push"', $script );
			$this->assertStringContainsString( 'self.addEventListener("fetch"', $script );
			$this->assertStringContainsString( '}).call(self);', $script );
			$this->assertNotContains( $file, $first['precache'] );
			file_put_contents( $file, 'self.addEventListener("push", event => self.registration.showNotification("Updated"));' );
			$second = Pwa::register( 'push-app', $settings );
			$this->assertNotSame( $first['cache_name'], $second['cache_name'] );
			$this->assertSame( $second['cache_name'], Pwa::register( 'push-app', $settings )['cache_name'] );
			$settings['worker_data']['title'] = 'Changed';
			$this->assertNotSame( $second['cache_name'], Pwa::register( 'push-app', $settings )['cache_name'] );
		} finally {
			unlink( $file );
		}
	}

	/**
	 * Reject script paths that cannot be bundled locally.
	 *
	 * @dataProvider invalid_worker_scripts
	 */
	public function test_worker_extensions_reject_nonlocal_or_missing_scripts( $scripts ) {
		$this->expectException( \InvalidArgumentException::class );
		Pwa::register( 'invalid-worker', [ 'worker_scripts' => $scripts ] );
	}

	public function invalid_worker_scripts() {
		return [
			'not an array' => [ 'worker.js' ],
			'missing file' => [ [ __DIR__ . '/does-not-exist.js' ] ],
			'directory'    => [ [ __DIR__ ] ],
			'remote URL'   => [ [ 'https://example.org/worker.js' ] ],
			'stream'       => [ [ 'data:text/javascript,self.close()' ] ],
			'not a path'   => [ [ null ] ],
		];
	}

	public function test_worker_extensions_reject_unserializable_data() {
		$resource = fopen( __FILE__, 'r' );
		try {
			$this->expectException( \InvalidArgumentException::class );
			Pwa::register( 'invalid-worker', [ 'worker_data' => [ 'resource' => $resource ] ] );
		} finally {
			fclose( $resource );
		}
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

	public function test_pwa_endpoints_do_not_receive_canonical_redirects() {
		global $wp_query;

		Pwa::register(
			'travel-app-canonical',
			[
				'manifest_path'       => 'manifest.webmanifest',
				'service_worker_path' => 'service-worker.js',
			]
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Query fixture for the canonical redirect filter.
		$wp_query = (object) [
			'query_vars' => [
				'wp_app_path'    => 'travel-app-canonical',
				'wp_app_request' => 'service-worker.js',
			],
		];

		$this->assertFalse(
			Pwa::filter_canonical_redirect(
				'https://example.org/travel-app-canonical/service-worker.js/',
				'https://example.org/travel-app-canonical/service-worker.js'
			)
		);

		$wp_query->query_vars['wp_app_request'] = 'trip/123';

		$this->assertSame(
			'https://example.org/travel-app-canonical/trip/123/',
			Pwa::filter_canonical_redirect(
				'https://example.org/travel-app-canonical/trip/123/',
				'https://example.org/travel-app-canonical/trip/123'
			)
		);
	}

	public function test_wpapp_exposes_manifest_url_with_query_args() {
		$app = new WpApp( __DIR__ . '/fixtures/templates', 'travel-app-link' );
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
