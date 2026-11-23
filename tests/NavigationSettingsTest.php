<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Masterbar;
use WpApp\Registry;
use WpApp\Settings;
use WpApp\WpApp;

class NavigationSettingsTest extends TestCase {
    protected function setUp(): void {
        global $__wp_app_test_options, $__wp_app_test_current_user_can, $wp_query;
        $__wp_app_test_options = [];
        $__wp_app_test_current_user_can = true;
        $wp_query = null;
        Registry::reset();
        foreach ( [ 'one', 'two', 'three' ] as $path ) {
            $app = new WpApp( '', 'navigation-' . $path, [] );
            $app->init();
        }
    }

    public function test_legacy_pins_and_sequence_initialize_both_orders_without_writing_settings() {
        global $__wp_app_test_options;
        $legacy = [
            'only_show_active_app' => true,
            'show_inactive_apps_in_overflow' => true,
            'app_order' => [ 'navigation-three', 'navigation-one', 'navigation-two' ],
            'apps' => [ 'navigation-one' => [ 'always_show' => true ] ],
        ];
        $__wp_app_test_options[ Settings::OPTION ] = $legacy;
        $this->assertSame( $legacy['app_order'], Settings::get_navigation_order( 'launcher' ) );
        $this->assertSame( $legacy['app_order'], Settings::get_navigation_order( 'desktop' ) );
        $this->assertSame( [ 'navigation-one' ], array_keys( Settings::get_navigation_apps( 'desktop' ) ) );
        $this->assertCount( 3, Settings::get_navigation_apps( 'launcher' ) );
        $this->assertSame( $legacy, $__wp_app_test_options[ Settings::OPTION ] );
    }

    public function test_launcher_visibility_and_pinning_are_independent() {
        global $__wp_app_test_options;
        $__wp_app_test_options[ Settings::OPTION ] = [
            'apps' => [
                'navigation-one' => [ 'show_in_launcher' => false, 'pin_to_desktop' => true ],
                'navigation-two' => [ 'show_in_launcher' => true, 'pin_to_desktop' => true ],
                'navigation-three' => [ 'show_in_launcher' => true, 'pin_to_desktop' => false ],
            ],
            'desktop_order' => [ 'navigation-two', 'navigation-one' ],
            'launcher_order' => [ 'navigation-three', 'navigation-two', 'navigation-one' ],
        ];
        $this->assertSame( [ 'navigation-three', 'navigation-two' ], array_keys( Settings::get_navigation_apps( 'launcher' ) ) );
        $this->assertSame( [ 'navigation-two', 'navigation-one' ], array_keys( Settings::get_navigation_apps( 'desktop' ) ) );
        $this->assertTrue( Registry::can_user_access_app( 'navigation-one' ) );
        $bar = new FakeAdminBar();
        Masterbar::add_wp_admin_bar_admin_context_items_for_all( $bar );
        Masterbar::add_admin_bar_overflow_menu( $bar );
        $this->assertArrayNotHasKey( 'wp-app-admin-overflow-navigation-one', $bar->nodes );
        $this->assertArrayHasKey( 'wp-app-admin-overflow-navigation-two', $bar->nodes );
        $this->assertArrayHasKey( 'wp-app-link-navigation_one', $bar->nodes );
        $this->assertLessThan( array_search( 'wp-app-link-navigation_one', array_keys( $bar->nodes ), true ), array_search( 'wp-app-link-navigation_two', array_keys( $bar->nodes ), true ) );
    }

    public function test_explicit_pin_always_has_an_icon_and_accessible_name() {
        global $__wp_app_test_options;
        $__wp_app_test_options[ Settings::OPTION ] = [ 'apps' => [ 'navigation-one' => [
            'title' => 'Pinned App', 'show_icon' => false, 'show_text' => false, 'pin_to_desktop' => true,
        ] ] ];
        $bar = new FakeAdminBar();
        Masterbar::add_wp_admin_bar_admin_context_items_for_all( $bar );
        $node = $bar->nodes['wp-app-link-navigation_one'];
        $this->assertSame( 'Pinned App', $node['meta']['title'] );
        $this->assertStringContainsString( 'wp-app-link-icon', $node['title'] );
        $this->assertStringContainsString( '<span class="screen-reader-text">Pinned App</span>', $node['title'] );
        $this->assertStringNotContainsString( 'class="wp-app-link-text"', $node['title'] );
        $records = Masterbar::get_admin_bar_refresh_data()['nodes'];
        $pin = array_values( array_filter( $records, static function ( $record ) {
            return 'wp-app-link-navigation_one' === $record['id'];
        } ) )[0];
        $this->assertStringContainsString( 'title="Pinned App"', $pin['html'] );
    }

    public function test_disabled_navigation_and_denied_apps_do_not_leak_into_either_preview() {
        global $__wp_app_test_options, $__wp_app_test_current_user_can;
        Masterbar::get_instance_for_app( 'navigation-one' )->admin_bar_app_link( false );
        Registry::register_app_capability( 'navigation-two', 'restricted_capability' );
        $__wp_app_test_current_user_can = [ 'read' => true ];
        $__wp_app_test_options[ Settings::OPTION ] = [ 'only_show_active_app' => false ];
        $this->assertSame( [ 'navigation-three' ], array_keys( Settings::get_navigation_apps( 'desktop' ) ) );
        $this->assertSame( [ 'navigation-three' ], array_keys( Settings::get_navigation_apps( 'launcher' ) ) );
    }

    public function test_saving_keeps_independent_orders_and_explicit_visibility() {
        global $__wp_app_test_options;
        $saved = Settings::sanitize_settings( [
            'only_show_active_app' => true,
            'show_inactive_apps_in_overflow' => true,
            'launcher_order' => [ '/navigation-three/', 'navigation-one', 'navigation-three', 'team/tools', '' ],
            'desktop_order' => [ 'navigation-two', 'navigation-one' ],
            'apps' => [ 'navigation-one' => [
                'title' => 'Updated Name', 'icon' => 'dashicons-book', 'icon_background' => '#fff',
                'show_in_launcher' => '0', 'pin_to_desktop' => '1', 'default_theme' => 'compact',
            ] ],
        ] );
        $__wp_app_test_options[ Settings::OPTION ] = $saved;
        $this->assertSame( [ 'navigation-three', 'navigation-one', 'team/tools' ], $saved['launcher_order'] );
        $this->assertSame( [ 'navigation-two', 'navigation-one' ], $saved['desktop_order'] );
        $this->assertFalse( Settings::get_app_settings( 'navigation-one' )['show_in_launcher'] );
        $this->assertTrue( Settings::get_app_settings( 'navigation-one' )['pin_to_desktop'] );
        $this->assertSame( 'compact', Settings::get_app_settings( 'navigation-one' )['default_theme'] );
        $this->assertSame( 'Updated Name', Settings::get_app_settings( 'navigation-one' )['title'] );
        $this->assertSame( [ 'navigation-one' ], array_keys( Settings::get_navigation_apps( 'desktop' ) ) );
    }
}
