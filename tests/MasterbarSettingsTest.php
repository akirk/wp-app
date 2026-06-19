<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Settings;
use WpApp\WpApp;

class MasterbarSettingsTest extends TestCase {
    protected function setUp(): void {
        global $__wp_app_test_filters, $__wp_app_test_options, $wp_query;

        $__wp_app_test_filters = [];
        $__wp_app_test_options = [];
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub resets the queried object.
        $wp_query = null;
    }

    public function test_sanitize_settings_preserves_app_paths() {
        $settings = Settings::sanitize_settings(
            [
                'only_show_active_app' => '1',
                'apps'                 => [
                    'team/tools' => [
                        'title'                => '<b>Team Tools</b>',
                        'icon'                 => 'dashicons-admin-site',
                        'show_icon'            => '1',
                        'generate_letter_icon' => '0',
                        'show_text'            => '1',
                        'always_show'          => '0',
                    ],
                ],
            ]
        );

        $this->assertTrue( $settings['only_show_active_app'] );
        $this->assertArrayHasKey( 'team/tools', $settings['apps'] );
        $this->assertSame( 'Team Tools', $settings['apps']['team/tools']['title'] );
        $this->assertSame( 'dashicons-admin-site', $settings['apps']['team/tools']['icon'] );
        $this->assertTrue( $settings['apps']['team/tools']['show_icon'] );
        $this->assertFalse( $settings['apps']['team/tools']['generate_letter_icon'] );
    }

    public function test_only_show_active_app_defaults_on() {
        $settings = Settings::get_settings();

        $this->assertTrue( $settings['only_show_active_app'] );
    }

    public function test_only_show_active_app_preserves_saved_false() {
        global $__wp_app_test_options;

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => false,
        ];

        $settings = Settings::get_settings();

        $this->assertFalse( $settings['only_show_active_app'] );
    }

    public function test_global_only_active_setting_hides_inactive_app_link_unless_always_show() {
        global $__wp_app_test_options;

        $app = new WpApp( '', 'hidden-app', [ 'app_name' => 'Hidden App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => true,
            'apps'                 => [
                'hidden-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => true,
                    'show_text'            => true,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );
        $this->assertSame( [], $admin_bar->nodes );

        $__wp_app_test_options[ Settings::OPTION ]['apps']['hidden-app']['always_show'] = true;

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-link-hidden_app', $admin_bar->nodes );
        $this->assertStringContainsString( 'Hidden App', $admin_bar->nodes['wp-app-link-hidden_app']['title'] );
    }

    public function test_app_link_is_hidden_when_text_and_icon_are_disabled() {
        global $__wp_app_test_options;

        $app = new WpApp( '', 'empty-link-app', [ 'app_name' => 'Empty Link App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => false,
            'apps'                 => [
                'empty-link-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => false,
                    'generate_letter_icon' => false,
                    'show_text'            => false,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $this->assertSame( [], $admin_bar->nodes );
    }

    public function test_app_title_and_dashicon_can_be_overridden() {
        global $__wp_app_test_options;

        $app = new WpApp( '', 'customized-app', [ 'app_name' => 'Customized App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => false,
            'apps'                 => [
                'customized-app' => [
                    'title'                => 'Renamed App',
                    'icon'                 => 'admin-site',
                    'show_icon'            => true,
                    'generate_letter_icon' => true,
                    'show_text'            => true,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $title = $admin_bar->nodes['wp-app-link-customized_app']['title'];

        $this->assertStringContainsString( 'Renamed App', $title );
        $this->assertStringContainsString( 'dashicons-admin-site', $title );
        $this->assertStringNotContainsString( 'Customized App</span>', $title );
    }

    public function test_icon_override_counts_as_visible_link_content_without_text() {
        global $__wp_app_test_options;

        $app = new WpApp( '', 'emoji-app', [ 'app_name' => 'Emoji App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => false,
            'apps'                 => [
                'emoji-app' => [
                    'title'                => '',
                    'icon'                 => '*',
                    'show_icon'            => true,
                    'generate_letter_icon' => false,
                    'show_text'            => false,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-link-emoji_app', $admin_bar->nodes );
        $this->assertStringContainsString( '*', $admin_bar->nodes['wp-app-link-emoji_app']['title'] );
        $this->assertStringContainsString( 'screen-reader-text', $admin_bar->nodes['wp-app-link-emoji_app']['title'] );
    }

    public function test_registered_apps_ignore_my_apps_filter_entries() {
        add_filter(
            'my_apps_plugins',
            function ( $apps ) {
                $apps['my-apps-only'] = [
                    'name'     => 'My Apps Only',
                    'url'      => 'https://example.org/my-apps-only/',
                    'icon_url' => 'https://example.org/icon.png',
                ];

                return $apps;
            }
        );

        $apps = Settings::get_registered_apps();

        $this->assertArrayNotHasKey( 'my-apps-only', $apps );
    }

    public function test_my_apps_icon_dashicon_registers_as_dashicon() {
        $app = new WpApp(
            '',
            'courses',
            [
                'app_name'     => 'Courses',
                'my_apps_icon' => 'dashicons-welcome-learn-more',
            ]
        );

        $apps = $app->register_my_apps( [] );

        $this->assertSame( 'dashicons-welcome-learn-more', $apps['courses']['dashicon'] );
        $this->assertArrayNotHasKey( 'icon_url', $apps['courses'] );
    }

    public function test_my_apps_registration_preserves_existing_icon_when_no_icon_is_configured() {
        $app = new WpApp( '', 'apiary-press', [ 'app_name' => 'Apiary Press' ] );

        $apps = $app->register_my_apps(
            [
                'apiary-press' => [
                    'name'     => 'Apiary Press',
                    'url'      => 'https://example.org/apiary-press/',
                    'icon_url' => 'https://example.org/wp-content/plugins/apiary-press/assets/icon.svg',
                ],
            ]
        );

        $this->assertSame( 'https://example.org/wp-content/plugins/apiary-press/assets/icon.svg', $apps['apiary-press']['icon_url'] );
    }

    public function test_my_apps_icon_dashicon_is_available_to_masterbar_metadata() {
        global $__wp_app_test_options;

        $app = new WpApp(
            '',
            'metadata-icon-app',
            [
                'app_name'     => 'Metadata Icon App',
                'my_apps_icon' => 'dashicons-admin-site',
            ]
        );
        $app->init();

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => false,
            'apps'                 => [
                'metadata-icon-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => false,
                    'show_text'            => true,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $title = $admin_bar->nodes['wp-app-link-metadata_icon_app']['title'];

        $this->assertStringContainsString( 'dashicons-admin-site', $title );
    }

    public function test_visibility_status_explains_active_only_entries() {
        global $__wp_app_test_options;

        new WpApp( '', 'active-only-app', [ 'app_name' => 'Active Only App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => true,
            'apps'                 => [
                'active-only-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => true,
                    'show_text'            => true,
                    'always_show'          => false,
                ],
            ],
        ];

        $status = Settings::get_masterbar_visibility_status( 'active-only-app' );

        $this->assertSame( 'active_only', $status['state'] );
    }

    public function test_visibility_status_explains_disabled_automatic_link() {
        $app = new WpApp( '', 'disabled-link-app', [ 'app_name' => 'Disabled Link App' ] );
        $app->admin_bar_app_link( false );

        $status = Settings::get_masterbar_visibility_status( 'disabled-link-app' );

        $this->assertSame( 'disabled', $status['state'] );
        $this->assertStringContainsString( 'no masterbar entry to customize', $status['message'] );
    }
}
