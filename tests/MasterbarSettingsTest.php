<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Masterbar;
use WpApp\Registry;
use WpApp\Settings;
use WpApp\WpApp;

class MasterbarSettingsTest extends TestCase {
    protected function setUp(): void {
        global $__wp_app_test_current_user_can, $__wp_app_test_filters, $__wp_app_test_options, $__wp_app_test_plugin_data, $__wp_app_test_plugins, $wp_query;

        $__wp_app_test_current_user_can = true;
        $__wp_app_test_filters          = [];
        $__wp_app_test_options          = [];
        $__wp_app_test_plugin_data      = [];
        $__wp_app_test_plugins          = [];
        Registry::reset();
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub resets the queried object.
        $wp_query = null;
    }

    public function test_sanitize_settings_preserves_app_paths() {
        $settings = Settings::sanitize_settings(
            [
                'only_show_active_app'              => '1',
                'show_inactive_apps_in_overflow'    => '1',
                'sort_overflow_menu_alphabetically' => '1',
                'app_order'                         => [ 'team/tools', 'bad path!', 'team/tools', '/Reports_App/' ],
                'apps'                              => [
                    'team/tools' => [
                        'title'                => '<b>Team Tools</b>',
                        'icon'                 => 'dashicons-admin-site',
                        'icon_background'      => 'linear-gradient(135deg, #f7971e, #ffd200)',
                        'icon_color'           => '#fff',
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
        $this->assertTrue( $settings['sort_overflow_menu_alphabetically'] );
        $this->assertSame( '', $settings['provider'] );
        $this->assertSame( [ 'team/tools', 'badpath', 'reports_app' ], $settings['app_order'] );
        $this->assertArrayHasKey( 'team/tools', $settings['apps'] );
        $this->assertSame( 'Team Tools', $settings['apps']['team/tools']['title'] );
        $this->assertSame( 'dashicons-admin-site', $settings['apps']['team/tools']['icon'] );
        $this->assertSame( 'linear-gradient(135deg, #f7971e, #ffd200)', $settings['apps']['team/tools']['icon_background'] );
        $this->assertSame( '#fff', $settings['apps']['team/tools']['icon_color'] );
        $this->assertTrue( $settings['apps']['team/tools']['show_icon'] );
        $this->assertFalse( $settings['apps']['team/tools']['generate_letter_icon'] );
        $this->assertSame( '', $settings['apps']['team/tools']['default_theme'] );
    }

    public function test_sanitize_settings_preserves_default_theme_slug() {
        $settings = Settings::sanitize_settings(
            [
                'apps' => [
                    'reader' => [ 'default_theme' => 'Compact Theme!' ],
                ],
            ]
        );

        $this->assertSame( 'compacttheme', $settings['apps']['reader']['default_theme'] );
    }

    public function test_settings_page_renders_default_theme_for_apps_with_multiple_themes() {
        $app = new WpApp( '', 'reader', [ 'app_name' => 'Reader' ] );
        add_filter(
            'wp_app_init_reader',
            function () use ( $app ) {
                $app->register_theme( 'compact', 'Compact' );
            }
        );
        apply_filters( 'wp_app_init_reader', $app );

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( '>Default theme</label>', $html );
        $this->assertStringContainsString( 'name="wp_app_masterbar_settings[apps][reader][default_theme]"', $html );
        $this->assertStringContainsString( '<option value="compact"', $html );
    }

    public function test_sanitize_settings_rejects_unsafe_icon_color_values() {
        $settings = Settings::sanitize_settings(
            [
                'apps' => [
                    'unsafe-icon-style-app' => [
                        'icon_background' => 'url(https://example.org/icon.png)',
                        'icon_color'      => 'red" onclick="alert(1)',
                    ],
                ],
            ]
        );

        $this->assertSame( '', $settings['apps']['unsafe-icon-style-app']['icon_background'] );
        $this->assertSame( '', $settings['apps']['unsafe-icon-style-app']['icon_color'] );
    }

    public function test_only_show_active_app_defaults_on() {
        $settings = Settings::get_settings();

        $this->assertTrue( $settings['only_show_active_app'] );
    }

    public function test_show_inactive_apps_in_overflow_defaults_on() {
        $settings = Settings::get_settings();

        $this->assertTrue( $settings['show_inactive_apps_in_overflow'] );
    }

    public function test_sort_overflow_menu_alphabetically_defaults_off() {
        $settings = Settings::get_settings();

        $this->assertFalse( $settings['sort_overflow_menu_alphabetically'] );
    }

    public function test_sanitize_settings_preserves_safe_provider_plugin_file() {
        $settings = Settings::sanitize_settings( [ 'provider' => 'Wordopedia/Wordopedia.php' ] );

        $this->assertSame( 'Wordopedia/Wordopedia.php', $settings['provider'] );

        $settings = Settings::sanitize_settings( [ 'provider' => '../wordopedia.php' ] );

        $this->assertSame( '', $settings['provider'] );
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

    public function test_overflow_menu_can_sort_alphabetically_instead_of_saved_order() {
        global $__wp_app_test_options, $wp_query;

        $active_app = new WpApp( '', 'active-alpha-overflow-app', [ 'app_name' => 'Active Alpha Overflow App' ] );
        $zeta_app   = new WpApp( '', 'zeta-alpha-overflow-app', [ 'app_name' => 'Zeta Alpha Overflow App' ] );
        $alpha_app  = new WpApp( '', 'alpha-alpha-overflow-app', [ 'app_name' => 'Alpha Alpha Overflow App' ] );
        $zeta_app->init();
        $alpha_app->init();
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test stub simulates the current app request.
        $wp_query = (object) [
            'query_vars' => [
                'wp_app_request' => true,
                'wp_app_path'    => 'active-alpha-overflow-app',
            ],
        ];

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'              => true,
            'show_inactive_apps_in_overflow'    => true,
            'sort_overflow_menu_alphabetically' => true,
            'app_order'                         => [
                'zeta-alpha-overflow-app',
                'alpha-alpha-overflow-app',
            ],
            'apps'                              => [
                'zeta-alpha-overflow-app'  => [ 'always_show' => false ],
                'alpha-alpha-overflow-app' => [ 'always_show' => false ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $active_app->masterbar()->add_wp_admin_bar_app_context_items( $admin_bar );
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $keys = array_keys( $admin_bar->nodes );

        $this->assertLessThan(
            array_search( 'wp-app-admin-overflow-zeta-alpha-overflow-app', $keys, true ),
            array_search( 'wp-app-admin-overflow-alpha-alpha-overflow-app', $keys, true )
        );
    }

    public function test_settings_page_renders_alphabetical_overflow_checkbox() {
        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( Settings::OPTION . '[sort_overflow_menu_alphabetically]', $html );
        $this->assertStringContainsString( 'Sort overflow menu alphabetically (override order above)', $html );
    }

    public function test_settings_page_always_renders_provider_selection() {
        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( '>WP Apps library</label>', $html );
        $this->assertStringContainsString( '>Load from</span>', $html );
        $this->assertStringContainsString( 'id="wp-app-provider"', $html );
        $this->assertStringContainsString( WP_APP_VERSION, $html );
        $this->assertStringNotContainsString( 'Choose which active plugin supplies the shared wp-app framework.', $html );
    }

    public function test_global_admin_bar_links_follow_saved_app_order() {
        global $__wp_app_test_options;

        new WpApp( '', 'zeta-global-order-app', [ 'app_name' => 'Zeta Global Order App' ] );
        new WpApp( '', 'alpha-global-order-app', [ 'app_name' => 'Alpha Global Order App' ] );
        new WpApp( '', 'middle-global-order-app', [ 'app_name' => 'Middle Global Order App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'app_order'                      => [
                'middle-global-order-app',
                'alpha-global-order-app',
                'zeta-global-order-app',
            ],
            'apps'                           => [
                'zeta-global-order-app'   => [ 'always_show' => true ],
                'alpha-global-order-app'  => [ 'always_show' => true ],
                'middle-global-order-app' => [ 'always_show' => true ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        Masterbar::add_wp_admin_bar_admin_context_items_for_all( $admin_bar );

        $keys = array_keys( $admin_bar->nodes );

        $this->assertLessThan(
            array_search( 'wp-app-link-alpha_global_order_app', $keys, true ),
            array_search( 'wp-app-link-middle_global_order_app', $keys, true )
        );
        $this->assertLessThan(
            array_search( 'wp-app-link-zeta_global_order_app', $keys, true ),
            array_search( 'wp-app-link-alpha_global_order_app', $keys, true )
        );
    }

    public function test_admin_bar_refresh_data_uses_saved_app_title() {
        global $__wp_app_test_options;

        new WpApp( '', 'refresh-title-app', [ 'app_name' => 'Refresh Title App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => false,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [
                'refresh-title-app' => [
                    'title'       => 'Updated App',
                    'show_icon'   => true,
                    'show_text'   => true,
                    'always_show' => true,
                ],
            ],
        ];

        $data = Masterbar::get_admin_bar_refresh_data();
        $html = implode( '', array_column( $data['nodes'], 'html' ) );

        $this->assertStringContainsString( 'wp-admin-bar-wp-app-link-refresh_title_app', $html );
        $this->assertStringContainsString( '<span class="wp-app-link-text">Updated App</span>', $html );
    }

    public function test_admin_bar_refresh_data_renders_overflow_children() {
        global $__wp_app_test_options;

        new WpApp( '', 'refresh-overflow-first-app', [ 'app_name' => 'Refresh Overflow First App' ] );
        new WpApp( '', 'refresh-overflow-second-app', [ 'app_name' => 'Refresh Overflow Second App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [
                'refresh-overflow-first-app'  => [ 'always_show' => false ],
                'refresh-overflow-second-app' => [ 'always_show' => false ],
            ],
        ];

        $data = Masterbar::get_admin_bar_refresh_data();
        $html = implode( '', array_column( $data['nodes'], 'html' ) );

        $this->assertStringContainsString( 'wp-admin-bar-wp-app-admin-overflow', $html );
        $this->assertStringContainsString( 'wp-admin-bar-wp-app-admin-overflow-refresh-overflow-first-app', $html );
        $this->assertStringContainsString( 'wp-admin-bar-wp-app-admin-overflow-refresh-overflow-second-app', $html );
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
        $this->assertStringContainsString( '#wpadminbar li#wp-admin-bar-wp-app-admin-overflow-settings', Masterbar::get_admin_bar_overflow_styles() );
        $this->assertStringContainsString( 'border-top: 1px solid', Masterbar::get_admin_bar_overflow_styles() );
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
            'apps'                           => [
                'query-var-overflow-app' => [ 'always_show' => false ],
            ],
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

        $styles = Masterbar::get_admin_bar_overflow_styles();

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

    public function test_settings_preview_uses_metadata_image_icon_markup() {
        global $__wp_app_test_options;

        $app = new WpApp(
            '',
            'cookbook-preview-icon-app',
            [
                'app_name'     => 'Cookbook',
                'my_apps_icon' => 'https://example.org/wp-content/plugins/cookbook/assets/icon.svg',
            ]
        );
        $app->init();

        $__wp_app_test_options[ Settings::OPTION ] = [
            'apps' => [
                'cookbook-preview-icon-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => false,
                    'show_text'            => true,
                    'always_show'          => false,
                ],
            ],
        ];

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( '<span class="wp-app-link-icon wp-app-link-icon-image"><img src="https://example.org/wp-content/plugins/cookbook/assets/icon.svg" alt="" decoding="async"></span>', $html );
        $this->assertStringNotContainsString( '<span class="wp-app-link-icon">C</span>', $html );
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

    public function test_app_icon_color_settings_override_metadata_in_masterbar() {
        global $__wp_app_test_options;

        $app = new WpApp(
            '',
            'styled-settings-icon-app',
            [
                'app_name'            => 'Styled Settings Icon App',
                'my_apps_icon'        => 'dashicons-admin-site',
                'app_icon_background' => '#111111',
                'app_icon_color'      => '#eeeeee',
            ]
        );
        $app->init();

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => false,
            'apps'                 => [
                'styled-settings-icon-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'icon_background'      => '#123456',
                    'icon_color'           => '#ffffff',
                    'show_icon'            => true,
                    'generate_letter_icon' => false,
                    'show_text'            => true,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $title = $admin_bar->nodes['wp-app-link-styled_settings_icon_app']['title'];

        $this->assertStringContainsString( 'wp-app-link-icon-styled', $title );
        $this->assertStringContainsString( 'style="background: #123456; color: #ffffff"', $title );
        $this->assertStringNotContainsString( '#111111', $title );
        $this->assertStringNotContainsString( '#eeeeee', $title );
    }

    public function test_icon_url_setting_renders_as_image_in_masterbar() {
        global $__wp_app_test_options;

        $app = new WpApp( '', 'custom-url-icon-app', [ 'app_name' => 'Custom URL Icon App' ] );
        $app->init();

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app' => false,
            'apps'                 => [
                'custom-url-icon-app' => [
                    'title'                => '',
                    'icon'                 => 'https://example.org/wp-content/plugins/custom-url-icon-app/icon.svg',
                    'show_icon'            => true,
                    'generate_letter_icon' => false,
                    'show_text'            => true,
                    'always_show'          => false,
                ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $title = $admin_bar->nodes['wp-app-link-custom_url_icon_app']['title'];

        $this->assertStringContainsString( 'wp-app-link-icon-image', $title );
        $this->assertStringContainsString( '<img src="https://example.org/wp-content/plugins/custom-url-icon-app/icon.svg" alt="" decoding="async">', $title );
        $this->assertStringNotContainsString( 'wp-app-link-icon-generated', $title );
    }

    public function test_settings_preview_uses_metadata_dashicon() {
        global $__wp_app_test_options;

        $app = new WpApp(
            '',
            'metadata-preview-icon-app',
            [
                'app_name'     => 'WordPress Courses',
                'my_apps_icon' => 'dashicons-welcome-learn-more',
            ]
        );
        $app->init();

        $__wp_app_test_options[ Settings::OPTION ] = [
            'apps' => [
                'metadata-preview-icon-app' => [
                    'title'                => '',
                    'icon'                 => '',
                    'show_icon'            => true,
                    'generate_letter_icon' => false,
                    'show_text'            => true,
                    'always_show'          => false,
                ],
            ],
        ];

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( '<span class="wp-app-link-icon wp-app-link-icon-dashicon" aria-hidden="true"><span class="dashicons dashicons-welcome-learn-more"></span></span>', $html );
        $this->assertStringContainsString( 'dashicons dashicons-welcome-learn-more', $html );
        $this->assertStringNotContainsString( '<span class="wp-app-link-icon wp-app-link-icon-dashicon" aria-hidden="true" hidden', $html );
    }

    public function test_settings_icon_control_shows_editable_metadata_dashicon_default() {
        $app = new WpApp(
            '',
            'editable-default-dashicon-app',
            [
                'app_name'     => 'Editable Default Dashicon App',
                'my_apps_icon' => 'dashicons-admin-site',
            ]
        );
        $app->init();

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'data-wp-app-setting="icon"', $html );
        $this->assertStringContainsString( 'value="dashicons-admin-site"', $html );
        $this->assertStringContainsString( 'placeholder="e.g. dashicons-admin-site or https://example.org/icon.svg"', $html );
    }

    public function test_settings_icon_control_includes_dashicon_autocomplete_source() {
        $app = new WpApp( '', 'dashicon-autocomplete-app', [ 'app_name' => 'Dashicon Autocomplete App' ] );
        $app->init();

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'data-wp-app-dashicon-autocomplete="1"', $html );
        $this->assertStringContainsString( 'const wpAppDashiconOptions = ["dashicons-admin-appearance"', $html );
        $this->assertStringContainsString( '"dashicons-admin-home"', $html );
        $this->assertStringContainsString( '_renderItem', $html );
        $this->assertStringContainsString( '.addClass("dashicons " + item.value)', $html );
        $this->assertStringContainsString( 'autocomplete("search", "")', $html );
        $this->assertStringContainsString( 'wpAppUpdateMasterbarPreview({ target: input }, ui.item.value)', $html );
        $this->assertStringContainsString( 'close: function() {', $html );
        $this->assertStringContainsString( 'function wpAppUpdateMasterbarPreview(event, previewIcon)', $html );
        $this->assertStringContainsString( 'typeof previewIcon === "string" ? previewIcon.trim() : iconField.value.trim()', $html );
        $this->assertStringContainsString( 'document.addEventListener("input", wpAppAutosaveSettingChange)', $html );
        $this->assertStringContainsString( 'function wpAppAutosaveSettingChange(event)', $html );
        $this->assertStringContainsString( 'event.type === "input" ? 500 : 250', $html );
        $this->assertStringContainsString( 'document.addEventListener("keydown", wpAppPreventAutosaveFieldSubmit)', $html );
        $this->assertStringContainsString( 'function wpAppPreventAutosaveFieldSubmit(event)', $html );
        $this->assertStringContainsString( 'event.preventDefault()', $html );
        $this->assertStringContainsString( 'document.addEventListener("submit", wpAppSubmitSettingsForm)', $html );
        $this->assertStringContainsString( 'function wpAppSubmitSettingsForm(event)', $html );
        $this->assertStringContainsString( 'form.querySelector("[name^=', $html );
        $this->assertStringContainsString( 'window.addEventListener("load", wpAppSetupDashiconAutocomplete)', $html );
        $this->assertStringContainsString( 'wpAppDashiconAutocompleteReady', $html );
        $this->assertStringNotContainsString( '"dashicons-home"', $html );
        $this->assertStringNotContainsString( '<datalist id="wp-app-dashicon-options">', $html );
    }

    public function test_settings_page_enqueues_jquery_ui_autocomplete() {
        global $wp_scripts;

        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset the test script registry.
        $wp_scripts = null;

        Settings::enqueue_settings_assets( 'settings_page_wp-apps' );

        $this->assertTrue( wp_script_is( 'jquery-ui-autocomplete' ) );
    }

    public function test_settings_icon_control_shows_editable_metadata_url_default() {
        $app = new WpApp(
            '',
            'editable-default-url-icon-app',
            [
                'app_name'     => 'Editable Default URL Icon App',
                'my_apps_icon' => 'https://example.org/wp-content/plugins/editable-default-url-icon-app/icon.svg',
            ]
        );
        $app->init();

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'data-wp-app-setting="icon"', $html );
        $this->assertStringContainsString( 'value="https://example.org/wp-content/plugins/editable-default-url-icon-app/icon.svg"', $html );
    }

    public function test_settings_color_controls_show_editable_metadata_defaults() {
        $app = new WpApp(
            '',
            'editable-default-colors-app',
            [
                'app_name'            => 'Editable Default Colors App',
                'my_apps_icon'        => 'dashicons-admin-site',
                'app_icon_background' => 'linear-gradient(135deg, #f7971e, #ffd200)',
                'app_icon_color'      => '#fff',
            ]
        );
        $app->init();

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'data-wp-app-setting="icon_background"', $html );
        $this->assertStringContainsString( 'value="linear-gradient(135deg, #f7971e, #ffd200)"', $html );
        $this->assertStringContainsString( 'data-wp-app-setting="icon_color"', $html );
        $this->assertStringContainsString( 'value="#fff"', $html );
        $this->assertStringContainsString( 'placeholder="e.g. #2271b1 or linear-gradient(135deg, #f7971e, #ffd200)"', $html );
        $this->assertStringContainsString( 'placeholder="e.g. #fff"', $html );
    }

    public function test_registered_app_metadata_includes_wp_app_package_versions() {
        $plugin_dir   = sys_get_temp_dir() . '/wp-app-package-test-' . uniqid();
        $template_dir = $plugin_dir . '/templates';

        mkdir( $template_dir, 0777, true );
        file_put_contents(
            $plugin_dir . '/composer.json',
            wp_json_encode(
                [
                    'name'    => 'example/package-test-app',
                    'require' => [
                        'akirk/wp-app' => '^1.2',
                    ],
                ]
            )
        );

        $app = new WpApp( $template_dir, 'package-test-app', [ 'app_name' => 'Package Test App' ] );
        $app->init();

        $apps = Settings::get_registered_apps();

        $this->assertSame( '^1.2', $apps['package-test-app']['wp_app_package']['expected'] );
        $this->assertSame( realpath( $plugin_dir . '/composer.json' ), $apps['package-test-app']['wp_app_package']['expected_source'] );
        $this->assertSame( 'akirk/wp-app', $apps['package-test-app']['wp_app_package']['loaded']['name'] );
        $this->assertNotEmpty( $apps['package-test-app']['wp_app_package']['loaded']['path'] );
        $this->assertSame( WP_APP_VERSION, $apps['package-test-app']['wp_app_package']['loaded']['version'] );
        $this->assertSame( dirname( __DIR__ ), $apps['package-test-app']['wp_app_package']['loaded']['path'] );
    }

    public function test_settings_page_does_not_render_per_app_package_diagnostics() {
        $app = new WpApp(
            '',
            'configured-package-app',
            [
                'app_name'           => 'Configured Package App',
                'wp_app_requirement' => '~2.0',
            ]
        );
        $app->init();

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringNotContainsString( '<details class="wp-app-settings-package">', $html );
        $this->assertStringNotContainsString( '<dt>Active wp-app</dt>', $html );
        $this->assertStringNotContainsString( '<dt>Launchers</dt>', $html );
    }

    public function test_settings_page_shows_when_another_plugins_wp_app_package_is_active() {
        global $__wp_app_test_plugin_data, $__wp_app_test_plugins;

        $memex_dir      = WP_CONTENT_DIR . '/plugins/memex';
        $wordopedia_dir = WP_CONTENT_DIR . '/plugins/wordopedia';

        if ( ! is_dir( $memex_dir ) ) {
            mkdir( $memex_dir, 0777, true );
        }

        if ( ! is_dir( $wordopedia_dir ) ) {
            mkdir( $wordopedia_dir, 0777, true );
        }

        $memex_file      = $memex_dir . '/memex.php';
        $wordopedia_file = $wordopedia_dir . '/wordopedia.php';

        touch( $memex_file );
        touch( $wordopedia_file );
        file_put_contents(
            $memex_dir . '/composer.lock',
            wp_json_encode(
                [
                    'packages' => [
                        [
                            'name'    => 'akirk/wp-app',
                            'version' => 'v2.1.0',
                        ],
                    ],
                ]
            )
        );

        $__wp_app_test_plugins = [
            'memex'      => [
                'memex.php' => [],
            ],
            'wordopedia' => [
                'wordopedia.php' => [],
            ],
        ];

        $__wp_app_test_plugin_data = [
            $memex_file      => [
                'Name'    => 'Memex',
                'Version' => '1.4.0',
            ],
            $wordopedia_file => [
                'Name'    => 'Wordopedia',
                'Version' => '2.1.0',
            ],
        ];

        Registry::register_app_metadata(
            'memex',
            [
                'name'           => 'Memex',
                'url'            => 'https://example.org/memex/',
                'wp_app_package' => [
                    'expected'        => '^1.2',
                    'expected_source' => WP_CONTENT_DIR . '/plugins/memex/composer.json',
                    'loaded'          => [
                        'name'    => 'akirk/wp-app',
                        'version' => '2.0.0',
                        'path'    => WP_CONTENT_DIR . '/plugins/wordopedia/vendor/composer/../akirk/wp-app',
                    ],
                ],
            ]
        );

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringNotContainsString( '<details class="wp-app-settings-package">', $html );
        $this->assertStringContainsString( '>WP Apps library</label>', $html );
        $this->assertStringNotContainsString( 'wp-app-provider-loaded-version', $html );
        $this->assertStringNotContainsString( '<h2>WP App Version</h2>', $html );
        $this->assertStringNotContainsString( '<h2>Global Display</h2>', $html );
        $this->assertStringNotContainsString( '<h2>Installed Apps</h2>', $html );
        $this->assertGreaterThan( strpos( $html, 'data-app-path="memex"' ), strpos( $html, 'App menu visibility' ) );
        $this->assertGreaterThan( strpos( $html, 'App menu visibility' ), strpos( $html, '>WP Apps library</label>' ) );
        $this->assertStringContainsString( 'First plugin (wordopedia, wp-app 2.0.0)', $html );
        $this->assertStringContainsString( '<option value="memex/memex.php"', $html );
        $this->assertStringContainsString( '<option value="wordopedia/wordopedia.php"', $html );
        $this->assertStringContainsString( 'Memex 1.4.0 — wp-app 2.1.0', $html );
        $this->assertStringContainsString( 'Wordopedia 2.1.0 — wp-app 2.0.0 (active)', $html );
        $this->assertStringContainsString( 'Choose which active plugin supplies the shared wp-app framework.', $html );
    }

    public function test_development_wp_app_versions_are_available_as_providers() {
        global $__wp_app_test_plugin_data, $__wp_app_test_plugins;

        $development_dir  = WP_CONTENT_DIR . '/plugins/development-provider';
        $development_file = $development_dir . '/development-provider.php';

        if ( ! is_dir( $development_dir ) ) {
            mkdir( $development_dir, 0777, true );
        }

        touch( $development_file );
        file_put_contents(
            $development_dir . '/composer.lock',
            wp_json_encode(
                [
                    'packages' => [
                        [
                            'name'    => 'akirk/wp-app',
                            'version' => 'dev-main',
                        ],
                    ],
                ]
            )
        );

        $__wp_app_test_plugins = [
            'development-provider' => [
                'development-provider.php' => [],
            ],
        ];

        $__wp_app_test_plugin_data = [
            $development_file => [
                'Name'    => 'Development Provider',
                'Version' => '1.0.0',
            ],
        ];

        $providers = Settings::get_wp_app_providers(
            [
                [
                    'wp_app_package' => [
                        'expected'        => 'dev-main',
                        'expected_source' => $development_dir . '/composer.json',
                    ],
                ],
            ]
        );

        $this->assertArrayHasKey( 'development-provider/development-provider.php', $providers );
        $this->assertStringContainsString( 'wp-app dev-main', $providers['development-provider/development-provider.php']['label'] );
    }

    public function test_settings_page_infers_symlinked_provider_from_plugin_load_order() {
        global $__wp_app_test_options, $__wp_app_test_plugin_data, $__wp_app_test_plugins;

        $community_dir  = WP_CONTENT_DIR . '/plugins/community-app';
        $alternative_dir = WP_CONTENT_DIR . '/plugins/alternative-app';

        foreach ( [ $community_dir, $alternative_dir ] as $plugin_dir ) {
            if ( ! is_dir( $plugin_dir ) ) {
                mkdir( $plugin_dir, 0777, true );
            }
        }

        $community_file   = $community_dir . '/community-app.php';
        $alternative_file = $alternative_dir . '/alternative-app.php';

        touch( $community_file );
        touch( $alternative_file );
        foreach ( [ $community_dir, $alternative_dir ] as $plugin_dir ) {
            file_put_contents(
                $plugin_dir . '/composer.lock',
                wp_json_encode(
                    [
                        'packages' => [
                            [
                                'name'    => 'akirk/wp-app',
                                'version' => 'dev-main',
                            ],
                        ],
                    ]
                )
            );
        }

        $__wp_app_test_plugins = [
            'community-app'   => [ 'community-app.php' => [] ],
            'alternative-app' => [ 'alternative-app.php' => [] ],
        ];
        $__wp_app_test_plugin_data = [
            $community_file   => [ 'Name' => 'Community App', 'Version' => '1.0.0' ],
            $alternative_file => [ 'Name' => 'Alternative App', 'Version' => '1.0.0' ],
        ];
        $__wp_app_test_options['active_plugins'] = [
            'alternative-app/alternative-app.php',
            'community-app/community-app.php',
        ];

        Registry::register_app_metadata(
            'community-app',
            [
                'name'           => 'Community App',
                'url'            => 'https://example.org/community-app/',
                'wp_app_package' => [
                    'expected'        => 'dev-main',
                    'expected_source' => $alternative_dir . '/composer.json',
                    'loaded'          => [
                        'version' => '2.0.0',
                        'path'    => '/Users/example/Sites/wp-app',
                    ],
                ],
            ]
        );
        Registry::register_app_metadata(
            'alternative-app',
            [
                'name'           => 'Alternative App',
                'url'            => 'https://example.org/alternative-app/',
                'wp_app_package' => [
                    'expected'        => 'dev-main',
                    'expected_source' => $community_dir . '/composer.json',
                ],
            ]
        );

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringContainsString( '>WP Apps library</label>', $html );
        $this->assertStringContainsString( 'First plugin (alternative-app, wp-app 2.0.0)', $html );
    }

    public function test_settings_page_does_not_claim_app_includes_wp_app_when_requirement_is_unknown() {
        Registry::register_app_metadata(
            'unknown-requirement-app',
            [
                'name'           => 'Unknown Requirement App',
                'url'            => 'https://example.org/unknown-requirement-app/',
                'wp_app_package' => [
                    'expected' => null,
                    'loaded'   => [
                        'name'    => 'akirk/wp-app',
                        'version' => '1.2.4',
                        'path'    => WP_CONTENT_DIR . '/plugins/provider/vendor/akirk/wp-app',
                    ],
                ],
            ]
        );

        ob_start();
        Settings::render_settings_page();
        $html = ob_get_clean();

        $this->assertStringNotContainsString( '<details class="wp-app-settings-package">', $html );
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

    public function test_single_app_is_shown_instead_of_a_one_entry_overflow() {
        global $__wp_app_test_options;

        $app = new WpApp( '', 'lonely-app', [ 'app_name' => 'Lonely App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-link-lonely_app', $admin_bar->nodes );
        $this->assertArrayNotHasKey( 'wp-app-admin-overflow', $admin_bar->nodes );
    }

    public function test_single_app_always_show_default_can_be_unchecked() {
        global $__wp_app_test_options;

        $app = new WpApp( '', 'collapsed-app', [ 'app_name' => 'Collapsed App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [
                'collapsed-app' => [ 'always_show' => false ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $this->assertArrayNotHasKey( 'wp-app-link-collapsed_app', $admin_bar->nodes );
        $this->assertArrayHasKey( 'wp-app-admin-overflow', $admin_bar->nodes );
        $this->assertArrayHasKey( 'wp-app-admin-overflow-collapsed-app', $admin_bar->nodes );
    }

    public function test_always_show_does_not_default_on_with_several_apps() {
        global $__wp_app_test_options;

        $first  = new WpApp( '', 'first-of-two-app', [ 'app_name' => 'First Of Two App' ] );
        $second = new WpApp( '', 'second-of-two-app', [ 'app_name' => 'Second Of Two App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [],
        ];

        $this->assertFalse( Settings::get_app_settings( 'first-of-two-app' )['always_show'] );

        $admin_bar = new FakeAdminBar();
        $first->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );
        $second->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $this->assertArrayNotHasKey( 'wp-app-link-first_of_two_app', $admin_bar->nodes );
        $this->assertArrayHasKey( 'wp-app-admin-overflow-first-of-two-app', $admin_bar->nodes );
        $this->assertArrayHasKey( 'wp-app-admin-overflow-second-of-two-app', $admin_bar->nodes );
    }

    public function test_single_app_link_carries_the_settings_item() {
        global $__wp_app_test_options;

        $app = new WpApp( '', 'settings-item-app', [ 'app_name' => 'Settings Item App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [],
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-admin-overflow-settings', $admin_bar->nodes );
        $this->assertSame( 'wp-app-link-settings_item_app', $admin_bar->nodes['wp-app-admin-overflow-settings']['parent'] );
        $this->assertStringContainsString( 'menupop', $admin_bar->nodes['wp-app-link-settings_item_app']['meta']['class'] );
        $this->assertStringContainsString( 'wp-app-admin-link-standalone', $admin_bar->nodes['wp-app-link-settings_item_app']['meta']['class'] );
    }

    public function test_settings_item_stays_in_the_overflow_for_several_apps() {
        global $__wp_app_test_options;

        $first  = new WpApp( '', 'first-settings-app', [ 'app_name' => 'First Settings App' ] );
        $second = new WpApp( '', 'second-settings-app', [ 'app_name' => 'Second Settings App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [
                'first-settings-app'  => [ 'always_show' => true ],
                'second-settings-app' => [ 'always_show' => false ],
            ],
        ];

        $admin_bar = new FakeAdminBar();
        $first->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );
        $second->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );
        Masterbar::add_admin_bar_overflow_menu( $admin_bar );

        $this->assertSame( 'wp-app-admin-overflow', $admin_bar->nodes['wp-app-admin-overflow-settings']['parent'] );
        $this->assertStringNotContainsString( 'wp-app-admin-link-standalone', $admin_bar->nodes['wp-app-link-first_settings_app']['meta']['class'] );
    }

    public function test_settings_item_is_not_added_without_the_capability() {
        global $__wp_app_test_current_user_can, $__wp_app_test_options;

        $app = new WpApp( '', 'no-caps-app', [ 'app_name' => 'No Caps App' ] );

        $__wp_app_test_options[ Settings::OPTION ] = [
            'only_show_active_app'           => true,
            'show_inactive_apps_in_overflow' => true,
            'apps'                           => [],
        ];

        $__wp_app_test_current_user_can = [
            'read'           => true,
            'manage_options' => false,
        ];

        $admin_bar = new FakeAdminBar();
        $app->masterbar()->add_wp_admin_bar_admin_context_items( $admin_bar );

        $this->assertArrayHasKey( 'wp-app-link-no_caps_app', $admin_bar->nodes );
        $this->assertArrayNotHasKey( 'wp-app-admin-overflow-settings', $admin_bar->nodes );
        $this->assertStringNotContainsString( 'menupop', $admin_bar->nodes['wp-app-link-no_caps_app']['meta']['class'] );
    }
}
