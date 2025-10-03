<?php
/**
 * Plugin Name: My Web App
 * Description: A simple web application built with the akirk/wp-app framework
 * Version: 1.0.0
 * Author: Your Name
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include Composer autoloader
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>My Web App: Please run <code>composer install</code> in the plugin directory.</p></div>';
    } );
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

use WpApp\WpApp;

class MyWebApp {
    private $app;

    public function __construct() {
        // Initialize WpApp with template directory, URL path, and configuration
        $this->app = new WpApp( plugin_dir_path( __FILE__ ) . 'templates', 'my-web-app', [
            'require_login' => true,  // Require users to be logged in
            'show_wp_logo' => false,
            'show_site_name' => true
        ] );

        // Hook into WordPress
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    public function init() {
        // Setup database tables
        $this->setup_database();

        // Setup routes
        $this->setup_routes();

        // Setup masterbar
        $this->setup_masterbar();

        // Initialize the app
        $this->app->init();

        // Setup app assets on template_redirect when wp_query is available
        add_action( 'template_redirect', [ $this, 'maybe_setup_assets' ] );

        // Add admin menu
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
    }

    private function setup_database() {
        // User progress tracking table
        $this->app->add_table( 'webapp_progress', [
            'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
            'user_id' => 'bigint(20) unsigned NOT NULL',
            'level' => 'int(11) DEFAULT 1',
            'points' => 'int(11) DEFAULT 0',
            'achievements' => 'longtext',
            'last_activity' => 'datetime DEFAULT CURRENT_TIMESTAMP',
            'PRIMARY KEY' => '(id)',
            'UNIQUE KEY user_id' => '(user_id)'
        ], '1.0.0' );

        // App posts/content table
        $this->app->add_table( 'webapp_posts', [
            'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
            'author_id' => 'bigint(20) unsigned NOT NULL',
            'title' => 'varchar(255) NOT NULL',
            'content' => 'longtext',
            'status' => 'varchar(20) DEFAULT "published"',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'PRIMARY KEY' => '(id)',
            'KEY author_id' => '(author_id)',
            'KEY status' => '(status)'
        ], '1.0.0' );
    }

    private function setup_routes() {
        // Main app route (empty string matches /my-app) -> templates/index.php
        $this->app->route( '' );

        // User dashboard -> templates/dashboard.php (auto-discovered)
        $this->app->route( 'dashboard' );

        // User profile with dynamic user ID -> templates/profile.php (auto-discovered, vars auto-extracted)
        $this->app->route( 'profile/{user_id}' );

        // Posts section -> templates/posts.php (auto-discovered)
        $this->app->route( 'posts' );

        // Single post -> templates/posts.php (auto-discovered, vars auto-extracted)
        $this->app->route( 'posts/{post_id}' );

        // Create post -> templates/posts-create.php (auto-discovered)
        $this->app->route( 'posts/create' );

        // Leaderboard -> templates/leaderboard.php (auto-discovered)
        $this->app->route( 'leaderboard' );

        // Register REST API endpoints instead of custom API
        add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );
    }

    private function setup_masterbar() {
        // Add main app menu items (framework automatically adds "My Web App" home link)
        $this->app->add_menu_item( 'my-app-posts', 'Posts', home_url( '/my-web-app/posts' ) );
        $this->app->add_menu_item( 'my-app-leaderboard', 'Leaderboard', home_url( '/my-web-app/leaderboard' ) );

        // Add user-specific menu items (shown when logged in)
        if ( is_user_logged_in() ) {
            $current_user_id = get_current_user_id();
            $this->app->add_user_menu_item( 'my-app-dashboard', 'Dashboard', home_url( '/my-web-app/dashboard' ) );
        }
    }

    public function maybe_setup_assets() {
        // Only setup assets on app requests
        if ( $this->app->is_app_request() ) {
            $this->setup_assets();
        }
    }

    private function setup_assets() {
        // Enqueue app styles
        wp_app_enqueue_style(
            'my-web-app-styles',
            plugin_dir_url( __FILE__ ) . 'assets/app.css',
            [],
            '1.0.0'
        );
    }

    public function register_rest_endpoints() {
        // Register user progress endpoint
        register_rest_route( 'my-web-app/v1', '/user-progress/(?P<user_id>\d+)', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_get_user_progress' ],
            'permission_callback' => [ $this, 'rest_permission_check' ],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'validate_callback' => function( $param, $request, $key ) {
                        return is_numeric( $param );
                    }
                ]
            ]
        ] );

        // Register add points endpoint
        register_rest_route( 'my-web-app/v1', '/add-points', [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_add_points' ],
            'permission_callback' => [ $this, 'rest_permission_check' ],
            'args' => [
                'points' => [
                    'required' => true,
                    'validate_callback' => function( $param, $request, $key ) {
                        return is_numeric( $param ) && $param > 0;
                    }
                ]
            ]
        ] );
    }

    public function rest_permission_check( $request ) {
        // Require user to be logged in for these endpoints
        return is_user_logged_in();
    }

    public function rest_get_user_progress( $request ) {
        global $wpdb;

        $user_id = intval( $request->get_param( 'user_id' ) );

        // Only allow users to see their own progress or admins to see any
        if ( $user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You can only view your own progress.', [ 'status' => 403 ] );
        }

        $progress = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webapp_progress WHERE user_id = %d",
            $user_id
        ) );

        if ( ! $progress ) {
            // Create initial progress record
            $wpdb->insert(
                $wpdb->prefix . 'webapp_progress',
                [ 'user_id' => $user_id ],
                [ '%d' ]
            );

            $progress = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}webapp_progress WHERE user_id = %d",
                $user_id
            ) );
        }

        return rest_ensure_response( [
            'success' => true,
            'progress' => $progress
        ] );
    }

    public function rest_add_points( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $points = intval( $request->get_param( 'points' ) );

        // Add points to user's progress
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}webapp_progress (user_id, points)
             VALUES (%d, %d)
             ON DUPLICATE KEY UPDATE points = points + VALUES(points), last_activity = NOW()",
            $user_id,
            $points
        ) );

        return rest_ensure_response( [
            'success' => true,
            'message' => "Added {$points} points!"
        ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            'My Web App',
            'My Web App',
            'manage_options',
            'my-web-app',
            [ $this, 'admin_page' ],
            'dashicons-smartphone',
            30
        );

        add_submenu_page(
            'my-web-app',
            'App Settings',
            'Settings',
            'manage_options',
            'my-web-app-settings',
            [ $this, 'admin_settings_page' ]
        );
    }

    public function admin_page() {
        global $wpdb;

        $total_users = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}webapp_progress" );
        $total_posts = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}webapp_posts" );

        ?>
        <div class="wrap">
            <h1>My Web App Dashboard</h1>

            <div class="card">
                <h2>Statistics</h2>
                <p><strong>Active Users:</strong> <?php echo intval( $total_users ); ?></p>
                <p><strong>Total Posts:</strong> <?php echo intval( $total_posts ); ?></p>
            </div>

            <div class="card">
                <h2>Quick Actions</h2>
                <p><a href="<?php echo esc_url( home_url( '/my-app' ) ); ?>" class="button button-primary">View App</a></p>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=my-web-app-settings' ) ); ?>" class="button">Settings</a></p>
            </div>
        </div>
        <?php
    }

    public function admin_settings_page() {
        if ( isset( $_POST['submit'] ) ) {
            $this->app->set_config( 'points_per_post', intval( $_POST['points_per_post'] ) );
            $this->app->set_config( 'enable_leaderboard', ! empty( $_POST['enable_leaderboard'] ) );
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }

        $points_per_post = $this->app->get_config( 'points_per_post', 10 );
        $enable_leaderboard = $this->app->get_config( 'enable_leaderboard', true );

        ?>
        <div class="wrap">
            <h1>App Settings</h1>

            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">Points per Post</th>
                        <td>
                            <input type="number" name="points_per_post" value="<?php echo esc_attr( $points_per_post ); ?>" />
                            <p class="description">How many points users get for creating a post</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Leaderboard</th>
                        <td>
                            <input type="checkbox" name="enable_leaderboard" value="1" <?php checked( $enable_leaderboard ); ?> />
                            <p class="description">Show the leaderboard to users</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function activate() {
        // Run database migrations on activation
        $this->setup_database();
        $this->app->database()->migrate();

        // Setup routes before flushing
        $this->setup_routes();

        // Flush rewrite rules
        flush_rewrite_rules();

    }

    public function deactivate() {
        // Flush rewrite rules to clean up
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new MyWebApp();
