<?php
/**
 * Plugin Name: Minimal Web App
 * Description: The simplest possible web app using wp-app framework
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include Composer autoloader
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>Minimal Web App: Please run <code>composer install</code> in the plugin directory.</p></div>';
    } );
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

use WpApp\WpApp;

class MinimalApp {
    private $app;

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    }

    private function setup_app() {
        if ( $this->app ) {
            return;
        }

        // Initialize WpApp - should have sensible defaults
        $this->app = new WpApp( plugin_dir_path( __FILE__ ) . 'templates', 'my-minimal-app' );

        // Only add custom routes if needed
        $this->app->route( 'about' );           // -> templates/about.php
        $this->app->route( 'contact' );         // -> templates/contact.php
    }

    public function init() {
        $this->setup_app();

        // The framework should automatically:
        // 1. Create a route for '' -> index.php
        // 2. Add a masterbar menu item for the app home
        // 3. Enable the masterbar

        $this->app->init();
    }

    public function activate() {
        $this->setup_app();

        // Minimal activation - just flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new MinimalApp();
