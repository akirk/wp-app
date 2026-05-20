<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\BaseApp;
use WpApp\WpApp;

class DeferredMenuBaseApp extends BaseApp {
	public $database_setups = 0;
	public $route_setups    = 0;
	public $menu_setups     = 0;

	public function __construct() {
		$this->app = new WpApp( '/test/templates', 'deferred-menu' );
	}

	protected function setup_database() {
		$this->database_setups++;
	}

	protected function setup_routes() {
		$this->route_setups++;
		$this->app->route( 'dashboard' );
	}

	protected function setup_menu() {
		$this->menu_setups++;
		$this->app->add_menu_item( 'dashboard', 'Dashboard', '/deferred-menu/dashboard' );
	}
}

class BaseAppTest extends TestCase {
	protected function setUp(): void {
		\wp_app_tests_reset_hooks();
	}

	public function test_base_app_defers_menu_setup_until_init() {
		$app               = new DeferredMenuBaseApp();
		$initialized_count = 0;

		\add_action(
			'base_app_initialized',
			function() use ( &$initialized_count ) {
				$initialized_count++;
			}
		);

		$app->init();

		$this->assertSame( 1, $app->database_setups );
		$this->assertSame( 1, $app->route_setups );
		$this->assertSame( 0, $app->menu_setups );
		$this->assertSame( 0, $initialized_count );

		\do_action( 'init' );

		$this->assertSame( 1, $app->menu_setups );
		$this->assertSame( 1, $initialized_count );
	}

	public function test_base_app_initialization_is_idempotent() {
		$app = new DeferredMenuBaseApp();

		$app->init();
		$app->init();
		\do_action( 'init' );
		\do_action( 'init' );

		$this->assertSame( 1, $app->database_setups );
		$this->assertSame( 1, $app->route_setups );
		$this->assertSame( 1, $app->menu_setups );
	}
}
