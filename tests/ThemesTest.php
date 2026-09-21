<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Registry;
use WpApp\WpApp;

class ThemesTest extends TestCase {
	private $base_directory;
	private $theme_directory;

	protected function setUp(): void {
		global $__wp_app_test_user_options, $__wp_app_test_is_user_logged_in, $__wp_app_test_current_user_id;

		Registry::reset();
		$__wp_app_test_user_options      = [];
		$__wp_app_test_is_user_logged_in = true;
		$__wp_app_test_current_user_id   = 42;
		$_GET                            = [];
		$this->base_directory            = __DIR__ . '/fixtures/templates';
		$this->theme_directory           = $this->base_directory . '/compact';
	}

	protected function tearDown(): void {
		$_GET = [];
	}

	public function test_selected_theme_is_remembered_and_added_to_menu() {
		global $__wp_app_test_user_options;

		$app = new WpApp( $this->base_directory, 'reader' );
		$app->register_theme( 'compact', 'Compact', $this->theme_directory );
		$_GET['wp_app_theme'] = 'compact';

		$this->assertSame( 'compact', $app->get_selected_theme() );
		$this->assertSame( 'compact', $__wp_app_test_user_options['wp_app_theme_reader'] );

		$items = $app->masterbar()->get_preview_menu_items();
		$this->assertSame( 'Theme', $items['wp-app-theme-reader']['title'] );
		$this->assertSame( 'wp-app-theme-reader', $items['wp-app-theme-reader-compact']['parent'] );
		$this->assertSame( '✓ Compact', $items['wp-app-theme-reader-compact']['title'] );
	}

	public function test_theme_template_overrides_and_falls_back_to_app_templates() {
		$app = new WpApp( $this->base_directory, 'reader' );
		$app->register_theme( 'compact', 'Compact', $this->theme_directory );
		$_GET['wp_app_theme'] = 'compact';

		$locate_template = new \ReflectionMethod( $app->router(), 'locate_template' );
		$locate_template->setAccessible( true );
		$index           = $locate_template->invoke( $app->router(), 'index.php' );
		$details         = $locate_template->invoke( $app->router(), 'details.php' );

		$this->assertSame( $this->theme_directory . '/index.php', $index );
		$this->assertSame( $this->base_directory . '/details.php', $details );
	}
}
