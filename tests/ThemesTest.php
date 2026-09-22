<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Registry;
use WpApp\Settings;
use WpApp\WpApp;

class ThemesTest extends TestCase {
	private $base_directory;
	private $theme_directory;

	protected function setUp(): void {
		global $__wp_app_test_user_options, $__wp_app_test_is_user_logged_in, $__wp_app_test_current_user_id,
			$__wp_app_test_filters, $__wp_app_test_filter_stack, $__wp_app_test_doing_it_wrong;

		Registry::reset();
		$__wp_app_test_user_options      = [];
		$__wp_app_test_is_user_logged_in = true;
		$__wp_app_test_current_user_id   = 42;
		$__wp_app_test_filters           = [];
		$__wp_app_test_filter_stack      = [];
		$__wp_app_test_doing_it_wrong    = [];
		$_GET                            = [];
		$this->base_directory            = __DIR__ . '/fixtures/templates';
		$this->theme_directory           = $this->base_directory . '/compact';
	}

	private function register_theme( WpApp $app, $slug, $name, $template_directory = '' ) {
		add_filter(
			$app->get_init_filter_name(),
			static function ( $app ) use ( $slug, $name, $template_directory ) {
				$app->register_theme( $slug, $name, $template_directory );
				return $app;
			}
		);

		apply_filters( $app->get_init_filter_name(), $app );
	}

	public function test_theme_registration_outside_app_init_filter_is_rejected() {
		global $__wp_app_test_doing_it_wrong;

		$app = new WpApp( $this->base_directory, 'reader' );

		$this->assertFalse( $app->register_theme( 'compact', 'Compact', $this->theme_directory ) );
		$this->assertArrayNotHasKey( 'compact', $app->get_themes() );
		$this->assertSame( 'WpApp\\WpApp::register_theme', $__wp_app_test_doing_it_wrong[0]['function'] );
		$this->assertStringContainsString( 'wp_app_init_reader', $__wp_app_test_doing_it_wrong[0]['message'] );
	}

	protected function tearDown(): void {
		$_GET = [];
	}

	public function test_selected_theme_is_remembered_and_added_to_menu() {
		global $__wp_app_test_user_options;

		$app = new WpApp( $this->base_directory, 'reader' );
		$this->register_theme( $app, 'compact', 'Compact', $this->theme_directory );
		$_GET['wp_app_theme'] = 'compact';

		$this->assertSame( 'compact', $app->get_selected_theme() );
		$this->assertSame( 'compact', $__wp_app_test_user_options['wp_app_theme_reader'] );

		$items = $app->masterbar()->get_preview_menu_items();
		$this->assertSame( 'Themes', $items['wp-app-theme-reader']['title'] );
		$this->assertSame( 'wp-app-theme-reader', $items['wp-app-theme-reader-choice-default']['parent'] );
		$this->assertSame( 'Default', $items['wp-app-theme-reader-choice-default']['title'] );
		$this->assertSame( 'wp-app-theme-reader', $items['wp-app-theme-reader-choice-compact']['parent'] );
		$this->assertSame( '✓ Compact', $items['wp-app-theme-reader-choice-compact']['title'] );
		$this->assertArrayNotHasKey( 'wp-app-theme-reader-default', $items );
	}

	public function test_theme_template_overrides_and_falls_back_to_app_templates() {
		$app = new WpApp( $this->base_directory, 'reader' );
		$this->register_theme( $app, 'compact', 'Compact', $this->theme_directory );
		$_GET['wp_app_theme'] = 'compact';

		$index   = $app->router()->locate_template( 'index.php' );
		$details = $app->router()->locate_template( 'details.php' );

		$this->assertSame( $this->theme_directory . '/index.php', $index );
		$this->assertSame( $this->base_directory . '/details.php', $details );
	}

	public function test_site_default_theme_is_used_without_a_personal_selection() {
		global $__wp_app_test_options;

		$__wp_app_test_options[ Settings::OPTION ] = [
			'apps' => [
				'reader' => [ 'default_theme' => 'compact' ],
			],
		];

		$app = new WpApp( $this->base_directory, 'reader' );
		$app->register_theme( 'compact', 'Compact', $this->theme_directory );

		$this->assertSame( 'compact', $app->get_selected_theme() );
		$this->assertSame( [ 'default', 'compact' ], array_keys( Registry::get_app_metadata()['reader']['themes'] ) );
	}

	public function test_personal_theme_selection_overrides_site_default() {
		global $__wp_app_test_options, $__wp_app_test_user_options;

		$__wp_app_test_options[ Settings::OPTION ]         = [
			'apps' => [
				'reader' => [ 'default_theme' => 'compact' ],
			],
		];
		$__wp_app_test_user_options['wp_app_theme_reader'] = 'default';

		$app = new WpApp( $this->base_directory, 'reader' );
		$app->register_theme( 'compact', 'Compact', $this->theme_directory );

		$this->assertSame( 'default', $app->get_selected_theme() );
	}
}
