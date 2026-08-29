<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Openstation;
use WpApp\Registry;
use WpApp\Rest\Access;
use WpApp\WpApp;

class OpenstationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['__wp_app_test_action_counts'] = [];
		$GLOBALS['__wp_app_test_actions']       = [];
		$GLOBALS['__wp_app_test_filters']       = [];
		$GLOBALS['__wp_app_test_icons']         = [];
		unset( $GLOBALS['__wp_app_test_current_user_can'] );
		Registry::reset();
		Access::reset();
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

	public function test_icon_styles_feed_launchers_and_reject_unsafe_values() {
		$app = new WpApp(
			'/t',
			'styled-app',
			[
				'app_icon'            => 'dashicons-food',
				'app_icon_background' => 'linear-gradient(135deg, #f7971e, #ffd200)',
				'app_icon_color'      => '#fff',
				'app_icon_shadow'     => true,
			]
		);
		$app->init();

		$my_apps = $app->register_my_apps( [] );
		$this->assertSame( 'dashicons-food', $my_apps['styled-app']['dashicon'] );
		$this->assertSame( 'linear-gradient(135deg, #f7971e, #ffd200)', $my_apps['styled-app']['icon_background'] );
		$this->assertSame( '#fff', $my_apps['styled-app']['icon_color'] );
		$this->assertTrue( $my_apps['styled-app']['icon_shadow'] );

		$metadata = Registry::get_app_metadata();
		$this->assertSame( '#fff', $metadata['styled-app']['icon_color'] );

		Openstation::register_icons();
		$icons = $GLOBALS['__wp_app_test_icons'];
		$this->assertSame( '#fff', $icons[0]['args']['icon_color'] );
		$this->assertSame( 'linear-gradient(135deg, #f7971e, #ffd200)', $icons[0]['args']['icon_background'] );

		$unsafe = new WpApp(
			'/t',
			'unsafe-app',
			[
				'app_icon'            => 'dashicons-lock',
				'app_icon_background' => 'url(https://evil.example/x.png)',
				'app_icon_color'      => 'red" onmouseover="alert(1)',
				'app_icon_shadow'     => '0 2px 4px rgba(0,0,0,.4)',
			]
		);
		$unsafe->init();
		$my_apps = $unsafe->register_my_apps( [] );
		$this->assertArrayNotHasKey( 'icon_background', $my_apps['unsafe-app'] );
		$this->assertArrayNotHasKey( 'icon_color', $my_apps['unsafe-app'] );
		$this->assertSame( '0 2px 4px rgba(0,0,0,.4)', $my_apps['unsafe-app']['icon_shadow'] );
	}

	public function test_chromeless_request_hides_masterbar_and_exposes_menu_as_tabs() {
		global $wp_query;

		$app = new WpApp( '/t', 'slim-app', [ 'app_name' => 'Slim App' ] );
		$app->init();
		$app->masterbar()->add_menu_item( 'settings', 'Settings', 'https://example.org/slim-app/settings/' );
		$app->masterbar()->add_menu_item( 'label-only', 'Label Only' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test fixture.
		$wp_query                            = (object) [
			'query_vars' => [
				'wp_app_request' => '',
				'wp_app_path'    => 'slim-app',
			],
		];
		$GLOBALS['__wp_app_test_chromeless'] = true;

		$this->assertFalse( $app->masterbar()->should_show_admin_bar( true ) );

		ob_start();
		$app->masterbar()->render_custom_masterbar_if_needed();
		$app->masterbar()->maybe_render_fallback();
		$html = ob_get_clean();
		$this->assertSame( '', trim( $html ) );
		$this->assertSame( '', $app->masterbar()->render() );

		$items = Openstation::add_dock_items( [] );
		$this->assertCount( 1, $items );
		$this->assertSame( 'wp-app-slim-app', $items[0]['id'] );
		$this->assertSame( 'Slim App', $items[0]['title'] );
		$this->assertSame( 'Slim App', $items[0]['selfLabel'] );
		$this->assertStringContainsString( 'openstation_chromeless=1', $items[0]['url'] );
		$this->assertSame(
			[
				[
					'title' => 'Settings',
					'url'   => 'https://example.org/slim-app/settings/?openstation_chromeless=1',
				],
			],
			$items[0]['submenu']
		);

		$GLOBALS['__wp_app_test_chromeless'] = false;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test fixture.
		$wp_query = null;
	}

	public function test_dock_items_skip_disabled_and_inaccessible_apps() {
		( new WpApp( '/t', 'off-app', [ 'launcher' => false ] ) )->init();
		( new WpApp( '/t', 'gated-app', [ 'require_capability' => 'manage_options' ] ) )->init();
		( new WpApp( '/t', 'open-app' ) )->init();
		$GLOBALS['__wp_app_test_current_user_can'] = [
			'manage_options' => false,
			'read'           => true,
		];

		$items = Openstation::add_dock_items( [ [ 'id' => 'existing' ] ] );

		$this->assertSame( [ 'existing', 'wp-app-open-app' ], array_column( $items, 'id' ) );
		$this->assertSame( [], $items[1]['submenu'] );
	}

	public function test_post_types_config_protects_rest_and_hides_dock_menus() {
		( new WpApp( '/t', 'library', [ 'post_types' => [ 'book' ] ] ) )->init();
		( new WpApp(
			'/t',
			'newsroom',
			[
				'require_capability' => 'edit_posts',
				'post_types'         => [
					'article' => 'publish_posts',
					'memo',
				],
				'taxonomies'         => [ 'desk' ],
			]
		) )->init();
		( new WpApp(
            '/t',
            'public-app',
            [
				'require_login' => false,
				'post_types'    => [ 'leaflet' ],
			]
        ) )->init();

		$this->assertSame( 'read', Access::capability_for_post_type( 'book' ) );
		$this->assertSame( 'publish_posts', Access::capability_for_post_type( 'article' ) );
		$this->assertSame( 'edit_posts', Access::capability_for_post_type( 'memo' ) );
		$this->assertSame( 'edit_posts', Access::capability_for_taxonomy( 'desk' ) );
		$this->assertNull( Access::capability_for_post_type( 'leaflet' ) );

		$args = Access::filter_post_type_args(
            [
				'public'       => false,
				'show_in_rest' => true,
			],
			'book'
        );
		$this->assertTrue( $args['show_in_rest'] );
		$this->assertNotEmpty( $args['rest_controller_class'] );

		foreach ( [ 'book', 'article', 'memo', 'leaflet' ] as $type ) {
			$this->assertSame( 'hidden', Openstation::hide_owned_post_type_menus( 'dock', 'edit.php?post_type=' . $type ) );
		}
	}

	public function test_protected_post_type_menus_are_hidden_from_the_dock() {
		Access::protect_post_type( 'book', 'read' );
		Access::protect_post_type( 'author' );

		$this->assertSame( 'hidden', Openstation::hide_owned_post_type_menus( 'dock', 'edit.php?post_type=book' ) );
		$this->assertSame( 'hidden', Openstation::hide_owned_post_type_menus( 'dock', 'edit.php?post_type=author' ) );
		$this->assertSame( 'dock', Openstation::hide_owned_post_type_menus( 'dock', 'edit.php?post_type=page' ) );
		$this->assertSame( 'dock', Openstation::hide_owned_post_type_menus( 'dock', 'edit.php' ) );
		$this->assertSame( 'dock', Openstation::hide_owned_post_type_menus( 'dock', 'options-general.php' ) );
	}

	public function test_register_icons_skips_apps_the_user_cannot_access() {
		( new WpApp( '/t', 'gated-app', [ 'require_capability' => 'manage_options' ] ) )->init();
		$GLOBALS['__wp_app_test_current_user_can'] = [ 'manage_options' => false ];

		Openstation::register_icons();

		$this->assertSame( [], $GLOBALS['__wp_app_test_icons'] );
	}
}
