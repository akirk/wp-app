<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\BaseApp;
use WpApp\Registry;
use WpApp\WpApp;

class WpAppTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['__wp_app_test_action_counts'] = [];
		$GLOBALS['__wp_app_test_actions']       = [];
		$GLOBALS['__wp_app_test_filters']       = [];
		Registry::reset();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['__wp_app_test_translations'] );
	}

	public function test_app_name_is_returned_as_configured_without_textdomain() {
		$app = new WpApp(
			'/test/templates',
			'my-app',
			[
				'app_name' => 'My App',
			]
		);

		$this->assertSame( 'My App', $app->get_app_name() );
	}

	public function test_app_name_is_not_translated_with_configured_textdomain_before_init() {
		$GLOBALS['__wp_app_test_translations'] = [
			'my-app' => [
				'My App' => 'Meine App',
			],
		];

		$app = new WpApp(
			'/test/templates',
			'my-app',
			[
				'app_name'            => 'My App',
				'app_name_textdomain' => 'my-app',
			]
		);

		$this->assertSame( 'My App', $app->get_app_name() );
	}

	public function test_app_name_is_translated_with_configured_textdomain_after_init() {
		$GLOBALS['__wp_app_test_action_counts']['init'] = 1;
		$GLOBALS['__wp_app_test_translations']          = [
			'my-app' => [
				'My App' => 'Meine App',
			],
		];

		$app = new WpApp(
			'/test/templates',
			'my-app',
			[
				'app_name'            => 'My App',
				'app_name_textdomain' => 'my-app',
			]
		);

		$this->assertSame( 'Meine App', $app->get_app_name() );
	}

	public function test_app_name_falls_back_when_translation_is_empty() {
		$GLOBALS['__wp_app_test_action_counts']['init'] = 1;
		$GLOBALS['__wp_app_test_translations']          = [
			'my-app' => [
				'My App' => '',
			],
		];

		$app = new WpApp(
			'/test/templates',
			'my-app',
			[
				'app_name'            => 'My App',
				'app_name_textdomain' => 'my-app',
			]
		);

		$this->assertSame( 'My App', $app->get_app_name() );
	}

	public function test_app_init_filter_can_set_translated_app_name_before_metadata_refresh() {
		$app = new WpApp( '/test/templates', 'my-app' );

		add_filter(
			$app->get_init_filter_name(),
			function ( WpApp $app ) {
				$app->set_app_name( 'Meine App' );

				return $app;
			}
		);

		$app->init();

		$metadata = Registry::get_app_metadata();
		$this->assertSame( 'My App', $metadata['my-app']['name'] );

		do_action( 'init' );

		$metadata = Registry::get_app_metadata();
		$this->assertSame( 'Meine App', $metadata['my-app']['name'] );
	}

	public function test_init_filter_name_uses_normalized_app_path() {
		$app = new WpApp( '/test/templates', 'my/app-path' );

		$this->assertSame( 'wp_app_init_my_app-path', $app->get_init_filter_name() );
	}

	public function test_base_app_setup_menu_runs_on_wordpress_init() {
		$base_app = new class() extends BaseApp {
			public $menu_setup_count = 0;

			public function __construct() {
				$this->app = new WpApp( '/test/templates', 'base-test-app' );
			}

			protected function setup_database() {
			}

			protected function setup_routes() {
			}

			protected function setup_menu() {
				++$this->menu_setup_count;
				$this->app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/base-test-app/dashboard' ) );
			}
		};

		$base_app->init();

		$this->assertSame( 0, $base_app->menu_setup_count );
		$this->assertSame( [], $base_app->get_app()->masterbar()->get_preview_menu_items() );

		do_action( 'init' );

		$this->assertSame( 1, $base_app->menu_setup_count );
		$items = $base_app->get_app()->masterbar()->get_preview_menu_items();
		$this->assertArrayHasKey( 'dashboard', $items );
	}

	public function test_require_login_defaults_to_true() {
		$app = new WpApp( '/test/templates', 'my-app' );

		$this->assertSame( 'read', $app->get_required_capability() );
	}

	public function test_require_login_can_be_disabled_explicitly() {
		$app = new WpApp(
			'/test/templates',
			'my-app',
			[
				'require_login' => false,
			]
		);

		$this->assertNull( $app->get_required_capability() );
	}

	public function test_require_login_default_does_not_override_explicit_capability() {
		$app = new WpApp(
			'/test/templates',
			'my-app',
			[
				'require_capability' => 'manage_options',
			]
		);

		$this->assertSame( 'manage_options', $app->get_required_capability() );
	}

	public function test_require_login_true_overrides_require_capability() {
		$app = new WpApp(
			'/test/templates',
			'my-app',
			[
				'require_capability' => 'manage_options',
				'require_login'      => true,
			]
		);

		$this->assertSame( 'read', $app->get_required_capability() );
	}
}
