<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Openstation;
use WpApp\Registry;
use WpApp\WpApp;

class OpenstationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['__wp_app_test_action_counts'] = [];
		$GLOBALS['__wp_app_test_actions']       = [];
		$GLOBALS['__wp_app_test_filters']       = [];
		$GLOBALS['__wp_app_test_icons']         = [];
		unset( $GLOBALS['__wp_app_test_current_user_can'] );
		Registry::reset();
	}

	public function test_detects_openstation_shell() {
		$this->assertSame( 'openstation', Openstation::get_prefix() );
		$this->assertTrue( Openstation::is_available() );
		$this->assertFalse( Openstation::is_chromeless_request() );
		$this->assertSame( 'openstation_chromeless', Openstation::get_chromeless_flag() );
	}

	public function test_icon_args_use_dashicon_and_chromeless_url() {
		$args = Openstation::get_icon_args(
			'my-app',
			[
				'name'     => 'My App',
				'url'      => 'https://example.org/my-app/',
				'dashicon' => 'dashicons-admin-site',
				'icon_url' => 'https://example.org/icon.png',
			]
		);

		$this->assertSame( 'My App', $args['title'] );
		$this->assertSame( 'dashicons-admin-site', $args['icon'] );
		$this->assertSame( 'https://example.org/my-app/?openstation_chromeless=1', $args['url'] );
		$this->assertArrayNotHasKey( 'icon_svg', $args );
	}

	public function test_icon_args_fall_back_to_letter_svg() {
		$args = Openstation::get_icon_args(
			'my-app',
			[
				'name' => 'My App',
				'url'  => 'https://example.org/my-app/',
			]
		);

		$this->assertSame( 'My App', $args['title'] );
		$this->assertArrayNotHasKey( 'icon', $args );
		$this->assertStringContainsString( '<svg', $args['icon_svg'] );
		$this->assertStringContainsString( '>MA<', $args['icon_svg'] );
	}

	public function test_icon_id_keeps_nested_path_segments_distinct() {
		$this->assertSame( 'parent-child', Openstation::get_icon_id( 'parent/child' ) );
		$this->assertSame( 'my-app', Openstation::get_icon_id( 'my-app' ) );
	}

	public function test_letter_svg_uses_initials_and_escapes() {
		$this->assertStringContainsString( '>?<', Openstation::build_letter_svg( '' ) );
		$this->assertStringContainsString( '>A<', Openstation::build_letter_svg( 'alpha' ) );
		$this->assertStringContainsString( '>AB<', Openstation::build_letter_svg( 'alpha beta gamma' ) );
		$this->assertStringContainsString( '>&amp;X<', Openstation::build_letter_svg( '& x' ) );
	}

	public function test_register_icons_registers_each_app_and_respects_config() {
		( new WpApp( '/t', 'first-app', [ 'my_apps_icon' => 'dashicons-star-filled' ] ) )->init();
		( new WpApp( '/t', 'second-app', [ 'launcher' => false ] ) )->init();
		( new WpApp( '/t', 'third-app', [ 'require_capability' => 'edit_posts' ] ) )->init();

		Openstation::register_icons();

		$ids = array_column( $GLOBALS['__wp_app_test_icons'], 'id' );
		$this->assertSame( [ 'first-app', 'third-app' ], $ids );

		$this->assertSame( 'dashicons-star-filled', $GLOBALS['__wp_app_test_icons'][0]['args']['icon'] );
		$this->assertSame( 10, $GLOBALS['__wp_app_test_icons'][0]['args']['position'] );
		$this->assertSame( [ 'read' ], $GLOBALS['__wp_app_test_icons'][0]['args']['capabilities'] );

		$this->assertSame( [ 'edit_posts' ], $GLOBALS['__wp_app_test_icons'][1]['args']['capabilities'] );
		$this->assertSame( 20, $GLOBALS['__wp_app_test_icons'][1]['args']['position'] );
	}

	public function test_legacy_my_apps_options_are_aliases() {
		$named = new WpApp(
			'/t',
			'named-app',
			[
				'my_apps'      => 'Launcher Name',
				'my_apps_icon' => 'dashicons-star-filled',
			]
		);
		$named->init();
		( new WpApp( '/t', 'hidden-app', [ 'my_apps' => false ] ) )->init();

		$my_apps = $named->register_my_apps( [] );
		$this->assertSame( 'Launcher Name', $my_apps['named-app']['name'] );

		Openstation::register_icons();

		$icons = $GLOBALS['__wp_app_test_icons'];
		$this->assertSame( [ 'named-app' ], array_column( $icons, 'id' ) );
		$this->assertSame( 'Launcher Name', $icons[0]['args']['title'] );
		$this->assertSame( 'dashicons-star-filled', $icons[0]['args']['icon'] );
	}

	public function test_launcher_and_app_icon_feed_both_launchers() {
		$app = new WpApp(
			'/t',
			'shared-app',
			[
				'launcher' => 'Shared Name',
				'app_icon' => 'dashicons-lock',
			]
		);
		$app->init();
		( new WpApp( '/t', 'off-app', [ 'launcher' => false ] ) )->init();

		$my_apps = $app->register_my_apps( [] );
		$this->assertSame( 'Shared Name', $my_apps['shared-app']['name'] );
		$this->assertSame( 'dashicons-lock', $my_apps['shared-app']['dashicon'] );

		Openstation::register_icons();
		$icons = $GLOBALS['__wp_app_test_icons'];
		$this->assertSame( [ 'shared-app' ], array_column( $icons, 'id' ) );
		$this->assertSame( 'Shared Name', $icons[0]['args']['title'] );
		$this->assertSame( 'dashicons-lock', $icons[0]['args']['icon'] );

		// Only the enabled app hooked the My Apps filter.
		$this->assertCount( 1, $GLOBALS['__wp_app_test_filters']['my_apps_plugins'] );
	}

	public function test_register_icons_skips_apps_the_user_cannot_access() {
		( new WpApp( '/t', 'gated-app', [ 'require_capability' => 'manage_options' ] ) )->init();
		$GLOBALS['__wp_app_test_current_user_can'] = [ 'manage_options' => false ];

		Openstation::register_icons();

		$this->assertSame( [], $GLOBALS['__wp_app_test_icons'] );
	}
}
