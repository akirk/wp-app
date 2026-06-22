<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\WpApp;

class WpAppTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['__wp_app_test_action_counts'] = [];
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
}
