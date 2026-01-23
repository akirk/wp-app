<?php

namespace WpApp;

/**
 * WordPress-style Masterbar that mimics the WordPress admin bar
 */
class Masterbar {
    private $menu_items = [];
    private $user_menu_items = [];
    private $show_wp_logo = true;
    private $show_site_name = true;
    private $disable_wp_admin_bar = true;
    private $only_on_app_routes = false;
    private $show_for_anonymous = true;
    private $show_dark_mode_toggle = false;
    private $app_url_path = null;
    private $wpapp = null;

    public function __construct( $app_url_path = null, $wpapp = null ) {
        $this->app_url_path = $app_url_path;
        $this->wpapp = $wpapp;

        // Hook into our custom app head action to enqueue styles
        add_action( 'wp_app_head', [ $this, 'output_styles' ] );
        add_action( 'wp_app_head', [ $this, 'output_scripts' ] );

        // Control admin bar display
        add_filter( 'show_admin_bar', [ $this, 'should_show_admin_bar' ] );

        // Hook into admin bar to add our custom items
        add_action( 'admin_bar_menu', [ $this, 'add_wp_admin_bar_items' ], 999 );

        // Only show on app requests to avoid interfering with regular WordPress
        add_action( 'wp_app_before_render', [ $this, 'setup_for_app_request' ] );

        // Add custom masterbar for logged-out users if WordPress admin bar is not shown
        add_action( 'wp_app_body_open', [ $this, 'render_custom_masterbar_if_needed' ] );
    }

    /**
     * Add a menu item as submenu under the main app node
     *
     * @param string $id Menu item ID
     * @param string $title Menu item title
     * @param string $href Link URL
     * @param array $args Additional arguments
     */
    public function add_menu_item( $id, $title, $href = '', $args = [] ) {
        $this->menu_items[ $id ] = array_merge( [
            'id' => $id,
            'title' => $title,
            'href' => $href,
            'class' => '',
            'target' => '',
            'parent' => 'wp-app-' . str_replace( '-', '_', $this->app_url_path ) // Default to submenu
        ], $args );
    }

    /**
     * Add a top-level menu item (not as submenu)
     *
     * @param string $id Menu item ID
     * @param string $title Menu item title
     * @param string $href Link URL
     * @param array $args Additional arguments
     */
    public function add_top_level_menu_item( $id, $title, $href = '', $args = [] ) {
        $this->menu_items[ $id ] = array_merge( [
            'id' => $id,
            'title' => $title,
            'href' => $href,
            'class' => '',
            'target' => '',
            'parent' => null // Top-level item
        ], $args );
    }

    /**
     * Add a user menu item
     *
     * @param string $id Menu item ID
     * @param string $title Menu item title
     * @param string $href Link URL
     * @param array $args Additional arguments
     */
    public function add_user_menu_item( $id, $title, $href = '', $args = [] ) {
        $this->user_menu_items[ $id ] = array_merge( [
            'id' => $id,
            'title' => $title,
            'href' => $href,
            'class' => '',
            'target' => ''
        ], $args );
    }

    /**
     * Remove a menu item
     */
    public function remove_menu_item( $id ) {
        unset( $this->menu_items[ $id ] );
    }

    /**
     * Remove a user menu item
     */
    public function remove_user_menu_item( $id ) {
        unset( $this->user_menu_items[ $id ] );
    }

    /**
     * Clear all menu items
     */
    public function clear_all_menu_items() {
        $this->menu_items = [];
        $this->user_menu_items = [];
    }

    /**
     * Remove all WordPress admin bar items and show only app items
     */
    public function remove_all_wp_admin_bar_items() {
        add_action( 'admin_bar_menu', [ $this, 'clear_wp_admin_bar' ], 1 );
    }

    /**
     * Set whether to show masterbar for anonymous users
     *
     * @param bool $show True to show, false to hide for logged-out users
     */
    public function show_for_anonymous( $show = true ) {
        $this->show_for_anonymous = $show;
    }

    /**
     * Set whether to show WordPress logo
     */
    public function show_wp_logo( $show = true ) {
        $this->show_wp_logo = $show;
    }

    /**
     * Set whether to show site name
     */
    public function show_site_name( $show = true ) {
        $this->show_site_name = $show;
    }

    /**
     * Set whether to show dark mode toggle
     */
    public function show_dark_mode_toggle( $show = true ) {
        $this->show_dark_mode_toggle = $show;
    }

    /**
     * Determine if admin bar should be shown
     */
    public function should_show_admin_bar( $show ) {
        // Only control admin bar display on app requests
        if ( ! $this->is_app_request() ) {
            return $show;
        }

        // For logged-in users, always show admin bar
        if ( is_user_logged_in() ) {
            return true;
        }

        // For anonymous users, show admin bar only if configured to do so
        return $this->show_for_anonymous;
    }

    /**
     * Render custom masterbar if needed (for anonymous users when admin bar is hidden)
     */
    public function render_custom_masterbar_if_needed() {
        // Only render on app requests
        if ( ! $this->is_app_request() ) {
            return;
        }

        // If admin bar is showing or we shouldn't show for anonymous, don't render custom
        if ( is_admin_bar_showing() || ( ! is_user_logged_in() && ! $this->show_for_anonymous ) ) {
            return;
        }

        // Add body class for custom masterbar styling
        add_filter( 'body_class', function( $classes ) {
            $classes[] = 'wp-app-has-custom-masterbar';
            return $classes;
        } );

        // Render our custom masterbar
        echo $this->render_custom_masterbar();
    }

    /**
     * Render custom masterbar for anonymous users
     */
    private function render_custom_masterbar() {
        $current_user = wp_get_current_user();
        $is_logged_in = is_user_logged_in();

        ob_start();
        ?>
        <div id="wp-app-custom-masterbar" class="wp-app-masterbar">
            <div class="wp-app-masterbar-inner">
                <div class="wp-app-masterbar-left">
                    <?php if ( $this->show_wp_logo ) : ?>
                        <div class="wp-app-masterbar-logo">
                            <a href="<?php echo esc_url( home_url() ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                                <span class="wp-app-wp-logo">WordPress</span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ( $this->show_site_name ) : ?>
                        <div class="wp-app-masterbar-site">
                            <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
                        </div>
                    <?php endif; ?>

                    <div class="wp-app-masterbar-menu">
                        <?php $this->render_menu_items(); ?>
                    </div>
                </div>

                <div class="wp-app-masterbar-right">
                    <?php if ( $this->show_dark_mode_toggle ) : ?>
                        <div class="wp-app-dark-mode-toggle">
                            <?php $this->render_dark_mode_toggle(); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $is_logged_in ) : ?>
                        <div class="wp-app-masterbar-user">
                            <a href="#" class="wp-app-user-toggle">
                                <?php echo get_avatar( $current_user->ID, 24 ); ?>
                                <span class="wp-app-user-name"><?php echo esc_html( $current_user->display_name ); ?></span>
                            </a>
                            <div class="wp-app-user-menu">
                                <a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"><?php _e( 'Edit Profile' ); ?></a>
                                <a href="<?php echo esc_url( admin_url() ); ?>"><?php _e( 'Dashboard' ); ?></a>
                                <?php $this->render_user_menu_items(); ?>
                                <a href="<?php echo esc_url( wp_logout_url() ); ?>"><?php _e( 'Log Out' ); ?></a>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="wp-app-masterbar-login">
                            <a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( 'Log In' ); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the fake masterbar (legacy method, kept for backwards compatibility)
     */
    public function render() {
        return $this->render_custom_masterbar();
    }


    /**
     * Render menu items (only if user can access this app)
     */
    private function render_menu_items() {
        // Only show menu items if user can access this app
        if ( ! $this->can_user_access_app() ) {
            return;
        }

        foreach ( $this->menu_items as $item ) {
            $class = ! empty( $item['class'] ) ? ' class="' . esc_attr( $item['class'] ) . '"' : '';
            $target = ! empty( $item['target'] ) ? ' target="' . esc_attr( $item['target'] ) . '"' : '';

            echo '<div class="wp-app-masterbar-item">';
            if ( ! empty( $item['href'] ) ) {
                echo '<a href="' . esc_url( $item['href'] ) . '"' . $class . $target . '>' . esc_html( $item['title'] ) . '</a>';
            } else {
                echo '<span' . $class . '>' . esc_html( $item['title'] ) . '</span>';
            }
            echo '</div>';
        }
    }

    /**
     * Render user menu items
     */
    private function render_user_menu_items() {
        foreach ( $this->user_menu_items as $item ) {
            $class = ! empty( $item['class'] ) ? ' class="' . esc_attr( $item['class'] ) . '"' : '';
            $target = ! empty( $item['target'] ) ? ' target="' . esc_attr( $item['target'] ) . '"' : '';

            if ( ! empty( $item['href'] ) ) {
                echo '<a href="' . esc_url( $item['href'] ) . '"' . $class . $target . '>' . esc_html( $item['title'] ) . '</a>';
            } else {
                echo '<span' . $class . '>' . esc_html( $item['title'] ) . '</span>';
            }
        }
    }

    /**
     * Output styles for the masterbar
     */
    public function output_styles() {
        echo '<style id="wp-app-masterbar-styles">';
        echo $this->get_default_styles();

        // Allow other plugins/themes to add masterbar styles
        do_action( 'wp_app_masterbar_styles' );

        echo '</style>';
    }

    /**
     * Output scripts for the masterbar
     */
    public function output_scripts() {
        echo '<script id="wp-app-masterbar-scripts">';
        echo $this->get_default_scripts();

        // Allow other plugins/themes to add masterbar scripts
        do_action( 'wp_app_masterbar_scripts' );

        echo '</script>';
    }

    /**
     * Get default CSS styles for the masterbar
     */
    private function get_default_styles() {
        return '
            /* App-specific admin bar styling */
            .wp-app-with-admin-bar #wpadminbar {
                background: #23282d;
            }

            /* App menu items spacing and positioning */
            .wp-app-menu-item > .ab-item {
                margin-left: 15px;
            }

            .wp-app-menu-item:hover > .ab-item,
            .wp-app-menu-item.hover > .ab-item {
                color: #00a0d2 !important;
            }

            /* Custom app user menu items styling */
            .wp-app-user-menu-item > .ab-item {
                color: #00a0d2 !important;
            }

            /* App-specific body margin (WordPress admin bar is 32px) */
            .wp-app-with-admin-bar body {
                margin-top: 32px !important;
            }

            /* Responsive admin bar for mobile */
            @media screen and (max-width: 782px) {
                .wp-app-with-admin-bar body {
                    margin-top: 46px !important;
                }

                .wp-app-with-admin-bar #wpadminbar {
                    position: fixed;
                }
            }

            /* Custom masterbar for anonymous users */
            .wp-app-masterbar {
                background: #23282d;
                height: 32px;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 99999;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                font-size: 13px;
                line-height: 32px;
                color: #eee;
            }

            .wp-app-masterbar .wp-app-masterbar-inner {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
                height: 100%;
                padding: 0 20px;
            }

            .wp-app-masterbar .wp-app-masterbar-left,
            .wp-app-masterbar .wp-app-masterbar-right {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .wp-app-masterbar a {
                color: #eee;
                text-decoration: none;
            }

            .wp-app-masterbar a:hover {
                color: #00a0d2;
            }

            .wp-app-masterbar .wp-app-masterbar-item {
                display: inline-block;
            }

            .wp-app-masterbar .wp-app-wp-logo {
                font-weight: 600;
            }

            .wp-app-masterbar .wp-app-masterbar-login {
                background: #0073aa;
                padding: 4px 10px;
                border-radius: 3px;
                white-space: nowrap;
                font-size: 12px;
            }

            .wp-app-masterbar .wp-app-masterbar-login:hover {
                background: #005177;
            }

            .wp-app-masterbar .wp-app-masterbar-login a {
                color: #fff !important;
            }

            .wp-app-masterbar .wp-app-masterbar-login a:hover {
                color: #fff !important;
            }

            /* Body margin when custom masterbar is shown */
            body.wp-app-has-custom-masterbar {
                margin-top: 32px !important;
            }

            /* Default body margin for apps without masterbar */
            body.wp-app-body:not(.wp-app-has-custom-masterbar):not(.admin-bar) {
                margin: 40px 20px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                line-height: 1.6;
            }

            /* Dark mode toggle styling */
            #dark-mode-toggle {
                background: none;
                border: none;
                color: #eee;
                cursor: pointer;
                padding: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 3px;
                transition: all 0.2s ease;
            }

            #dark-mode-toggle:hover {
                background: rgba(255, 255, 255, 0.1);
                color: #00a0d2;
            }

            #dark-mode-toggle svg {
                width: 16px;
                height: 16px;
                display: none;
            }

            #dark-mode-toggle .sun-icon {
                display: block;
            }

            /* Admin bar dark mode toggle */
            .wp-app-admin-bar-dark-mode-toggle {
                padding: 0 6px;
            }

            .wp-app-admin-bar-dark-mode-toggle #dark-mode-toggle {
                color: #eee;
            }

            .wp-app-admin-bar-dark-mode-toggle #dark-mode-toggle:hover {
                color: #00a0d2;
                background: rgba(255, 255, 255, 0.1);
            }

            /* Responsive custom masterbar */
            @media screen and (max-width: 782px) {
                .wp-app-masterbar {
                    height: 46px;
                    line-height: 46px;
                }

                body.wp-app-has-custom-masterbar {
                    margin-top: 46px !important;
                }

                .wp-app-masterbar .wp-app-masterbar-inner {
                    flex-direction: column;
                    height: auto;
                    padding: 8px 15px;
                    gap: 8px;
                }
            }
        ';
    }

    /**
     * Get default JavaScript for the masterbar
     */
    private function get_default_scripts() {
        return '
            // Simple dropdown toggle for user menu
            document.addEventListener("DOMContentLoaded", function() {
                const userToggle = document.querySelector(".wp-app-user-toggle");
                const userMenu = document.querySelector(".wp-app-user-menu");

                if (userToggle && userMenu) {
                    userToggle.addEventListener("click", function(e) {
                        e.preventDefault();
                        userMenu.style.display = userMenu.style.display === "block" ? "none" : "block";
                    });

                    // Close menu when clicking outside
                    document.addEventListener("click", function(e) {
                        if (!e.target.closest(".wp-app-masterbar-user")) {
                            userMenu.style.display = "none";
                        }
                    });
                }
            });
        ';
    }

    /**
     * Output masterbar automatically
     */
    public function auto_render() {
        // Use WordPress admin bar on app requests instead of custom rendering
        // The admin bar will be automatically shown and customized via hooks

        // Fallback: render custom masterbar if WordPress admin bar is disabled
        add_action( 'wp_app_body_open', [ $this, 'maybe_render_fallback' ] );
    }

    /**
     * Render fallback masterbar if WordPress admin bar is not shown
     */
    public function maybe_render_fallback() {
        if ( ! is_admin_bar_showing() ) {
            echo $this->render();
        }
    }

    /**
     * Setup admin bar for app requests
     */
    public function setup_for_app_request() {
        // Add app-specific CSS classes to body
        add_filter( 'body_class', function( $classes ) {
            $classes[] = 'wp-app-with-admin-bar';
            return $classes;
        } );

        // Enqueue WordPress admin bar styles - handled in wp_app_head() function now
    }

    /**
     * Add custom items to WordPress admin bar
     */
    public function add_wp_admin_bar_items( $wp_admin_bar ) {
        // Always add app link, but show different items based on context
        if ( $this->is_app_request() ) {
            $this->add_app_context_items( $wp_admin_bar );
        } else {
            $this->add_admin_context_items( $wp_admin_bar );
        }
    }

    /**
     * Clear all WordPress admin bar items (called when remove_all_wp_admin_bar_items is used)
     */
    public function clear_wp_admin_bar( $wp_admin_bar ) {
        // Remove all WordPress default admin bar items
        $all_wp_items = [
            'wp-logo',              // WordPress logo
            'site-name',            // Site name
            'updates',              // Updates
            'comments',             // Comments
            'new-content',          // New content menu
            'edit',                 // Edit this page
            'search',               // Search
            'my-account',           // User account menu
            'customize',            // Customize
            'themes',               // Themes
            'widgets',              // Widgets
            'menus',                // Menus
            'background',           // Background
            'header',               // Header
            'site-editor',          // Site Editor
            'view-site',            // View Site
            'archive',              // Archive
            'dashboard'             // Dashboard
        ];

        foreach ( $all_wp_items as $item_id ) {
            $wp_admin_bar->remove_node( $item_id );
        }
    }

    /**
     * Add items when on app pages
     */
    private function add_app_context_items( $wp_admin_bar ) {

        // Remove WordPress default items that aren't needed in app context
        $items_to_remove = apply_filters( 'wp_app_admin_bar_remove_items', [
            'new-content',          // "New" menu
            'comments',             // Comments
            'updates',              // Updates notification
            'site-editor',          // Site Editor (if using block theme)
        ] );

        foreach ( $items_to_remove as $item_id ) {
            $wp_admin_bar->remove_node( $item_id );
        }

        // Only add items if user can access this app
        if ( $this->can_user_access_app() ) {
            // Add main app node first
            $app_node_id = 'wp-app-' . str_replace( '-', '_', $this->app_url_path );
            $wp_admin_bar->add_node( [
                'id'    => $app_node_id,
                'title' => $this->get_app_name(),
                'href'  => $this->get_app_home_url(),
                'meta'  => [
                    'class' => 'wp-app-main-menu-item'
                ]
            ] );

            // Add custom menu items (as submenus by default, or top-level if parent is null)
            foreach ( $this->menu_items as $item ) {
                $wp_admin_bar->add_node( [
                    'id'     => $item['id'],
                    'parent' => $item['parent'],
                    'title'  => $item['title'],
                    'href'   => $item['href'],
                    'meta'   => [
                        'class' => 'wp-app-menu-item ' . $item['class'],
                        'target' => $item['target']
                    ]
                ] );
            }
        }


        // Add dark mode toggle if enabled
        if ( $this->show_dark_mode_toggle ) {
            $wp_admin_bar->add_node( [
                'id'    => 'wp-app-dark-mode-toggle',
                'title' => '<div class="wp-app-admin-bar-dark-mode-toggle">' . $this->get_dark_mode_toggle_html() . '</div>',
                'href'  => false,
                'meta'  => [
                    'class' => 'wp-app-dark-mode-toggle-wrapper'
                ]
            ] );
        }

        // Add user menu items as submenu to existing user menu
        if ( is_user_logged_in() ) {
            foreach ( $this->user_menu_items as $item ) {
                $wp_admin_bar->add_node( [
                    'id'     => $item['id'],
                    'parent' => 'user-actions',
                    'title'  => $item['title'],
                    'href'   => $item['href'],
                    'meta'   => [
                        'class' => 'wp-app-user-menu-item ' . $item['class'],
                        'target' => $item['target']
                    ]
                ] );
            }
        }

        // Allow other plugins to add items via action
        do_action( 'wp_app_admin_bar_menu', $wp_admin_bar );
    }

    /**
     * Add items when in regular WordPress admin/frontend
     */
    private function add_admin_context_items( $wp_admin_bar ) {
        // Only add link if user can access this app
        if ( $this->can_user_access_app() ) {
            // Add a simple link to the app from regular WordPress admin
            $wp_admin_bar->add_node( [
                'id'    => 'wp-app-link-' . str_replace( '-', '_', $this->app_url_path ),
                'title' => $this->get_app_name(),
                'href'  => $this->get_app_home_url(),
                'meta'  => [
                    'class' => 'wp-app-admin-link'
                ]
            ] );
        }
    }

    /**
     * Get app name for display
     */
    private function get_app_name() {
        if ( $this->wpapp && method_exists( $this->wpapp, 'get_app_name' ) ) {
            return $this->wpapp->get_app_name();
        }

        // Fallback: Use this masterbar's specific app path to generate the name
        if ( $this->app_url_path ) {
            $name = str_replace( [ '-', '_' ], ' ', $this->app_url_path );
            return ucwords( $name );
        }

        return 'App';
    }


    /**
     * Check if this is an app request
     */
    private function is_app_request() {
        global $wp_query;

        // Check if this is an app request at all
        if ( ! $wp_query || ! isset( $wp_query->query_vars['wp_app_request'] ) || ! isset( $wp_query->query_vars['wp_app_path'] ) ) {
            return false;
        }

        // Check if this request is for our specific app
        $app_path = get_query_var( 'wp_app_path' );
        return $app_path === $this->app_url_path;
    }

    /**
     * Check if current user has capability to access this app
     */
    private function can_user_access_app() {
        return \WpApp\Registry::can_user_access_app( $this->app_url_path );
    }

    /**
     * Get app home URL
     */
    private function get_app_home_url() {
        return home_url( '/' . $this->app_url_path );
    }

    /**
     * Set whether to disable WordPress admin bar
     */
    public function disable_wp_admin_bar( $disable = true, $only_on_app_routes = false ) {
        $this->disable_wp_admin_bar = $disable;
        $this->only_on_app_routes = $only_on_app_routes;

        if ( $disable ) {
            remove_filter( 'show_admin_bar', '__return_true' );
            add_filter( 'show_admin_bar', '__return_false' );
        }
    }

    /**
     * Configure which WordPress admin bar items to remove on app pages
     *
     * @param array $items_to_remove Array of admin bar item IDs to remove
     */
    public function set_removed_admin_bar_items( $items_to_remove ) {
        add_filter( 'wp_app_admin_bar_remove_items', function() use ( $items_to_remove ) {
            return $items_to_remove;
        } );
    }

    /**
     * Actually disable the WordPress admin bar
     */
    public function do_disable_wp_admin_bar() {
        // Check if we should only disable on app routes
        if ( $this->only_on_app_routes && get_query_var( 'wp_app_route' ) === '' ) {
            return;
        }

        show_admin_bar( false );
        add_filter( 'show_admin_bar', '__return_false' );

        // Remove admin bar styles and scripts
        remove_action( 'wp_head', '_admin_bar_bump_cb' );
        add_theme_support( 'admin-bar', [ 'callback' => '__return_false' ] );
    }

    /**
     * Echo the rendered masterbar
     */
    public function echo_render() {
        echo $this->render();
    }

    /**
     * Fallback for themes that don\'t support wp_body_open
     */
    public function echo_render_fallback() {
        if ( ! did_action( 'wp_body_open' ) ) {
            echo '<script>document.addEventListener("DOMContentLoaded", function() { document.body.insertAdjacentHTML("afterbegin", ' . wp_json_encode( $this->render() ) . '); });</script>';
        }
    }


    public function render_dark_mode_toggle( $aria_label = 'Toggle dark mode' ) {
        echo $this->get_dark_mode_toggle_html( $aria_label );
    }

    /**
     * Get dark mode toggle HTML
     */
    private function get_dark_mode_toggle_html( $aria_label = 'Toggle dark mode' ) {
        return '<button id="dark-mode-toggle" type="button" aria-label="' . htmlspecialchars( $aria_label ) . '">
            <svg class="sun-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
            <svg class="sun-forced-icon" width="28" height="16" viewBox="0 0 34 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                <rect x="26" y="17" width="6" height="5" rx="1"></rect>
                <path d="M27 17v-2a2 2 0 0 1 2-2 2 2 0 0 1 2 2v2"></path>
            </svg>
            <svg class="moon-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
            <svg class="moon-forced-icon" width="28" height="16" viewBox="0 0 34 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                <rect x="26" y="17" width="6" height="5" rx="1"></rect>
                <path d="M27 17v-2a2 2 0 0 1 2-2 2 2 0 0 1 2 2v2"></path>
            </svg>
        </button>';
    }
}