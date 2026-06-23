<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Masterbar;
use WpApp\Settings;
use WpApp\WpApp;

class MasterbarSettingsTest extends TestCase {
    protected function setUp(): void {
        global $__wp_app_test_current_user_can, $__wp_app_test_filters, $__wp_app_test_options, $wp_query;

        $__wp_app_test_current_user_can = true;
        $__wp_app_test_filters          = [];
        $__wp_app_test_options          = [];
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub resets the queried object.
        $wp_query = null;
    }

    public function test_sanitize_settings_preserves_app_paths() {
        $settings = Settings::sanitize_settings(
            [
                'only_show_active_app'           => '1',
                'show_inactive_apps_in_overflow' => '1',
                'app_order'                      => [ 'team/tools', 'bad path!', 'team/tools', '/Reports_App/' ],
                'apps'                           => [
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
        $this->assertTrue( $settings['show_inactive_apps_in_overflow'] );
        $this->assertSame( [ 'team/tools', 'badpath', 'reports_app' ], $settings['app_order'] );
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

    public function test_show_inactive_apps_in_overflow_defaults_on() {
        $settings = Settings::get_settings();

        $this->assertTrue( $settings['show_inactive_apps_in_overflow'] );
    }

    public function test_registered_apps_follow_saved_order_before_new_apps() {
        global $__wp_app_test_options;

        new WpApp( '', 'zeta-settings-order-app', [ 'app_name' => 'Zeta Settings Order App' ] );
        new WpApp( '', 'alpha-settings-order-app', [ 'app_name' => 'Alpha Settings Order App' ] );
        new WpApp( '', 'middle-settings-order-app', [ 'app_name' => 'Middle Settings Order App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'app_order' => [
                'zeta-settings-order-app',
                'alpha-settings-order-app',
            ],
        ];

        $keys = array_keys( Settings::get_registered_apps() );

        $this->assertLessThan(
            array_search( 'alpha-settings-order-app', $keys, true ),
            array_search( 'zeta-settings-order-app', $keys, true )
        );
        $this->assertLessThan(
            array_search( 'middle-settings-order-app', $keys, true ),
            array_search( 'alpha-settings-order-app', $keys, true )
        );
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
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => false,
            'apps'                           => [
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

    public function test_inactive_app_links_can_show_in_overflow_on_app_pages() {
        global $__wp_app_test_options, $wp_query;

        $active_app   = new WpApp( '', 'active-overflow-app', [ 'app_name' => 'Active Overflow App' ] );
        $inactive_app = new WpApp( '', 'inactive-overflow-app', [ 'app_name' => 'Inactive Overflow App' ] );
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'active-overflow-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [
                'active-overflow-app'   => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => true,
                    'show_text'            => true,
                    'always_show'          => false,
                ],
                'inactive-overflow-app' => [
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
        $active_app->masterbar()->add_wp_admin_bar_app_context_items( $admin_bar );
        $inactive_app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-active_overflow_app', $admin_bar->nodes );
        $this->assertArrayNotHasKey( 'wp-app-link-inactive_overflow_app', $admin_bar->nodes );
        $this->assertArrayHasKey( 'wp-app-admin-overflow', $admin_bar->nodes );
        $this->assertStringContainsString( 'wp-app-admin-overflow-sticky', $admin_bar->nodes['wp-app-admin-overflow']['meta']['class'] );
        $this->assertArrayHasKey( 'wp-app-admin-overflow-inactive-overflow-app', $admin_bar->nodes );
        $this->assertSame( 'wp-app-admin-overflow', $admin_bar->nodes['wp-app-admin-overflow-inactive-overflow-app']['parent'] );
    }

    public function test_always_show_inactive_app_stays_top_level_when_overflow_is_enabled() {
        global $__wp_app_test_options, $wp_query;

        $active_app = new WpApp( '', 'active-always-show-app', [ 'app_name' => 'Active Always Show App' ] );
        $pinned_app = new WpApp( '', 'pinned-always-show-app', [ 'app_name' => 'Pinned Always Show App' ] );
        $pinned_app->init();
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'active-always-show-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [
                'pinned-always-show-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => true,
                    'show_text'            => true,
                    'always_show'          => true,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $active_app->masterbar()->add_wp_admin_bar_app_context_items( $admin_bar );
        $pinned_app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-active_always_show_app', $admin_bar->nodes );
        $this->assertArrayHasKey( 'wp-app-link-pinned_always_show_app', $admin_bar->nodes );
        $this->assertArrayNotHasKey( 'wp-app-admin-overflow-pinned-always-show-app', $admin_bar->nodes );
    }

    public function test_always_show_inactive_app_includes_app_dropdown_items() {
        global $__wp_app_test_options, $wp_query;

        $active_app = new WpApp( '', 'active-dropdown-app', [ 'app_name' => 'Active Dropdown App' ] );
        $pinned_app = new WpApp( '', 'pinned-dropdown-app', [ 'app_name' => 'Pinned Dropdown App' ] );
        $pinned_app->add_menu_item( 'settings', 'Settings', 'https://example.org/pinned-dropdown-app/settings' );
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'active-dropdown-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [
                'pinned-dropdown-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => true,
                    'show_text'            => true,
                    'always_show'          => true,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $active_app->masterbar()->add_wp_admin_bar_app_context_items( $admin_bar );
        $pinned_app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-link-pinned_dropdown_app', $admin_bar->nodes );
        $this->assertStringContainsString( 'menupop', $admin_bar->nodes['wp-app-link-pinned_dropdown_app']['meta']['class'] );
        $this->assertArrayHasKey( 'wp-app-link-pinned_dropdown_app-settings', $admin_bar->nodes );
        $this->assertSame( 'wp-app-link-pinned_dropdown_app', $admin_bar->nodes['wp-app-link-pinned_dropdown_app-settings']['parent'] );
        $this->assertSame( 'Settings', $admin_bar->nodes['wp-app-link-pinned_dropdown_app-settings']['title'] );
        $this->assertSame( 'https://example.org/pinned-dropdown-app/settings', $admin_bar->nodes['wp-app-link-pinned_dropdown_app-settings']['href'] );
    }

    public function test_always_show_inactive_app_scopes_nested_dropdown_parents() {
        global $__wp_app_test_options, $wp_query;

        $active_app = new WpApp( '', 'active-nested-dropdown-app', [ 'app_name' => 'Active Nested Dropdown App' ] );
        $pinned_app = new WpApp( '', 'pinned-nested-dropdown-app', [ 'app_name' => 'Pinned Nested Dropdown App' ] );
        $pinned_app->add_menu_item( 'manage', 'Manage', '' );
        $pinned_app->add_menu_item(
            'settings',
            'Settings',
            'https://example.org/pinned-nested-dropdown-app/settings',
            [
                'parent' => 'manage',
            ]
        );
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'active-nested-dropdown-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [
                'pinned-nested-dropdown-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => true,
                    'show_text'            => true,
                    'always_show'          => true,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $active_app->masterbar()->add_wp_admin_bar_app_context_items( $admin_bar );
        $pinned_app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $this->assertSame( 'wp-app-link-pinned_nested_dropdown_app', $admin_bar->nodes['wp-app-link-pinned_nested_dropdown_app-manage']['parent'] );
        $this->assertSame( 'wp-app-link-pinned_nested_dropdown_app-manage', $admin_bar->nodes['wp-app-link-pinned_nested_dropdown_app-settings']['parent'] );
    }

    public function test_overflow_collects_inactive_registered_apps_hidden_by_global_setting() {
        global $__wp_app_test_options, $wp_query;

        $active_app = new WpApp( '', 'active-registered-overflow-app', [ 'app_name' => 'Active Registered Overflow App' ] );
        $first_app  = new WpApp( '', 'first-registered-overflow-app', [ 'app_name' => 'First Registered Overflow App' ] );
        $second_app = new WpApp( '', 'second-registered-overflow-app', [ 'app_name' => 'Second Registered Overflow App' ] );
        $first_app->init();
        $second_app->init();
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'active-registered-overflow-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [],
        ];

        $admin_bar = new FakeAdminBar();
        $active_app->masterbar()->add_wp_admin_bar_app_context_items( $admin_bar );
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-admin-overflow', $admin_bar->nodes );
        $this->assertArrayHasKey( 'wp-app-admin-overflow-first-registered-overflow-app', $admin_bar->nodes );
        $this->assertArrayHasKey( 'wp-app-admin-overflow-second-registered-overflow-app', $admin_bar->nodes );
        $this->assertArrayNotHasKey( 'wp-app-admin-overflow-active-registered-overflow-app', $admin_bar->nodes );
    }

    public function test_overflow_adds_wp_apps_settings_link_at_bottom_for_admins() {
        global $__wp_app_test_options, $wp_query;

        $active_app   = new WpApp( '', 'active-settings-overflow-app', [ 'app_name' => 'Active Settings Overflow App' ] );
        $inactive_app = new WpApp( '', 'inactive-settings-overflow-app', [ 'app_name' => 'Inactive Settings Overflow App' ] );
        $inactive_app->init();
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'active-settings-overflow-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [],
        ];

        $admin_bar = new FakeAdminBar();
        $active_app->masterbar()->add_wp_admin_bar_app_context_items( $admin_bar );
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-admin-overflow-settings', $admin_bar->nodes );
        $this->assertSame( 'wp-app-admin-overflow', $admin_bar->nodes['wp-app-admin-overflow-settings']['parent'] );
        $this->assertSame( 'https://example.org/wp-admin/options-general.php?page=wp-apps', $admin_bar->nodes['wp-app-admin-overflow-settings']['href'] );
        $this->assertStringContainsString( '#wpadminbar li#wp-admin-bar-wp-app-admin-overflow-settings', $this->get_admin_bar_overflow_styles() );
        $this->assertStringContainsString( 'border-top: 1px solid', $this->get_admin_bar_overflow_styles() );
        $this->assertSame( 'wp-app-admin-overflow-settings', array_key_last( $admin_bar->nodes ) );
    }

    public function test_overflow_uses_current_app_query_var_without_active_masterbar_instance() {
        global $__wp_app_test_options, $wp_query;

        $inactive_app = new WpApp( '', 'query-var-overflow-app', [ 'app_name' => 'Query Var Overflow App' ] );
        $inactive_app->init();
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'current-query-var-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [],
        ];

        $admin_bar = new FakeAdminBar();
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-admin-overflow', $admin_bar->nodes );
        $this->assertArrayHasKey( 'wp-app-admin-overflow-query-var-overflow-app', $admin_bar->nodes );
    }

    public function test_sticky_overflow_styles_do_not_hide_arrow() {
        global $__wp_app_test_options, $wp_query;

        new WpApp( '', 'active-sticky-overflow-app', [ 'app_name' => 'Active Sticky Overflow App' ] );
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'active-sticky-overflow-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [],
        ];

        $styles = $this->get_admin_bar_overflow_styles();

        $this->assertStringContainsString( 'li#wp-admin-bar-wp-app-admin-overflow', $styles );
        $this->assertStringContainsString( 'cursor: pointer;', $styles );
        $this->assertStringContainsString( 'display: block;', $styles );
        $this->assertStringNotContainsString(
            "#wpadminbar li#wp-admin-bar-wp-app-admin-overflow {\n                display: none;",
            $styles
        );
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
                    'icon'                 => 'dashicons-admin-site',
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
        $this->assertStringContainsString( 'wp-app-link-icon-dashicon', $title );
        $this->assertStringContainsString( 'dashicons-admin-site', $title );
        $this->assertStringNotContainsString( 'Customized App</span>', $title );
    }

    public function test_dashicon_override_requires_full_dashicon_class() {
        global $__wp_app_test_options;

        $app = new WpApp( '', 'short-dashicon-app', [ 'app_name' => 'Short Dashicon App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => false,
            'apps'                 => [
                'short-dashicon-app' => [
                    'title'                => '',
                    'icon'                 => 'admin-site',
                    'show_icon'            => true,
                    'generate_letter_icon' => false,
                    'show_text'            => true,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $title = $admin_bar->nodes['wp-app-link-short_dashicon_app']['title'];

        $this->assertStringContainsString( 'wp-app-link-icon-generated', $title );
        $this->assertStringContainsString( 'admin-site', $title );
        $this->assertStringNotContainsString( 'wp-app-link-icon-dashicon', $title );
        $this->assertStringNotContainsString( 'dashicons-admin-site', $title );
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
        $this->assertStringContainsString( 'wp-app-link-icon-generated', $admin_bar->nodes['wp-app-link-emoji_app']['title'] );
        $this->assertStringContainsString( 'screen-reader-text', $admin_bar->nodes['wp-app-link-emoji_app']['title'] );
    }

    public function test_letter_icon_is_generated_when_icons_are_enabled_without_icon_override() {
        global $__wp_app_test_options;

        $app = new WpApp( '', 'letter-fallback-app', [ 'app_name' => 'Letter Fallback App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => false,
            'apps'                 => [
                'letter-fallback-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => false,
                    'show_text'            => false,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $title = $admin_bar->nodes['wp-app-link-letter_fallback_app']['title'];

        $this->assertStringContainsString( 'wp-app-link-icon-generated', $title );
        $this->assertStringContainsString( '>L</span>', $title );
        $this->assertStringContainsString( 'screen-reader-text', $title );
    }

    public function test_overflow_forces_visible_text_for_icon_only_apps() {
        global $__wp_app_test_options, $wp_query;

        $active_app = new WpApp( '', 'active-icon-only-overflow-app', [ 'app_name' => 'Active Icon Only Overflow App' ] );
        $icon_app   = new WpApp( '', 'translated-icon-only-app', [ 'app_name' => 'Kochbuch' ] );
        $icon_app->init();
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'active-icon-only-overflow-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [
                'translated-icon-only-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => true,
                    'show_text'            => false,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $active_app->masterbar()->add_wp_admin_bar_app_context_items( $admin_bar );
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $title = $admin_bar->nodes['wp-app-admin-overflow-translated-icon-only-app']['title'];

        $this->assertStringContainsString( '<span class="wp-app-link-text">Kochbuch</span>', $title );
        $this->assertStringNotContainsString( 'screen-reader-text', $title );
    }

    public function test_active_app_link_forces_visible_text_for_icon_only_app() {
        global $__wp_app_test_options, $wp_query;

        $app = new WpApp( '', 'active-icon-only-app', [ 'app_name' => 'Kochbuch' ] );
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'active-icon-only-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [
                'active-icon-only-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => true,
                    'show_text'            => false,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_app_context_items( $admin_bar );

        $title = $admin_bar->nodes['wp-app-active_icon_only_app']['title'];

        $this->assertStringContainsString( '<span class="wp-app-link-text">Kochbuch</span>', $title );
        $this->assertStringNotContainsString( 'screen-reader-text', $title );
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

    public function test_svg_metadata_icon_renders_as_contained_img() {
        global $__wp_app_test_options;

        $app = new WpApp(
            '',
            'svg-icon-app',
            [
                'app_name'     => 'SVG Icon App',
                'my_apps_icon' => 'https://example.org/wp-content/plugins/svg-icon-app/assets/icon.svg',
            ]
        );
        $app->init();

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => false,
            'apps'                 => [
                'svg-icon-app' => [
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

        $title = $admin_bar->nodes['wp-app-link-svg_icon_app']['title'];

        $this->assertStringContainsString( '<img src="https://example.org/wp-content/plugins/svg-icon-app/assets/icon.svg"', $title );
        $this->assertStringContainsString( 'decoding="async"', $title );
        $this->assertStringContainsString( 'height: 18px;', Masterbar::get_app_link_styles( '#wpadminbar' ) );
        $this->assertStringContainsString( 'object-fit: contain;', Masterbar::get_app_link_styles( '#wpadminbar' ) );
        $this->assertStringNotContainsString( 'transform: scale(0.5);', Masterbar::get_app_link_styles( '#wpadminbar' ) );
        $this->assertStringNotContainsString( 'mask:', Masterbar::get_app_link_styles( '#wpadminbar' ) );
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

        $this->assertStringContainsString( 'wp-app-link-icon-dashicon', $title );
        $this->assertStringContainsString( 'dashicons-admin-site', $title );
    }

    public function test_settings_preview_uses_metadata_dashicon() {
        $method = new \ReflectionMethod( Settings::class, 'render_preview_icon' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke(
            null,
            [
                'icon'                 => '',
                'show_icon'            => true,
                'generate_letter_icon' => false,
            ],
            [
                'dashicon' => 'dashicons-welcome-learn-more',
            ],
            'WordPress Courses'
        );
        $html = ob_get_clean();

        $this->assertStringContainsString( 'wp-app-link-icon', $html );
        $this->assertStringContainsString( 'dashicons dashicons-welcome-learn-more', $html );
        $this->assertStringNotContainsString( ' hidden', $html );
    }

    public function test_app_link_styles_preserve_dashicons_font_inside_admin_bar() {
        $styles = Masterbar::get_app_link_styles( '#wpadminbar' );

        $this->assertStringContainsString( '.wp-app-link-icon.wp-app-link-icon-generated', $styles );
        $this->assertStringContainsString( 'font-family: dashicons !important;', $styles );
        $this->assertStringContainsString( '.wp-app-link-icon .dashicons:before', $styles );
    }

    public function test_visibility_status_explains_active_only_entries() {
        global $__wp_app_test_options;

        new WpApp( '', 'active-only-app', [ 'app_name' => 'Active Only App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => false,
            'apps'                           => [
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

    private function get_admin_bar_overflow_styles() {
        $method = new \ReflectionMethod( Masterbar::class, 'get_admin_bar_overflow_styles' );
        $method->setAccessible( true );

        return $method->invoke( null );
    }
}
