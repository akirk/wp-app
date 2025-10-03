<?php
/**
 * Basic usage example for WpApp framework
 *
 * This file shows how to set up a simple web app using the WpApp framework
 */

// Include Composer autoloader (adjust path as needed)
require_once __DIR__ . '/vendor/autoload.php';

use WpApp\WpApp;

// Initialize the app with your template directory
$app = new WpApp( __DIR__ . '/templates' );

// Add some database tables
$app->add_table( 'app_users', [
    'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
    'user_id' => 'bigint(20) unsigned NOT NULL',
    'app_data' => 'longtext',
    'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
    'PRIMARY KEY' => '(id)',
    'KEY user_id' => '(user_id)'
], '1.0.0' );

$app->add_table( 'app_settings', [
    'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
    'setting_key' => 'varchar(255) NOT NULL',
    'setting_value' => 'longtext',
    'PRIMARY KEY' => '(id)',
    'UNIQUE KEY setting_key' => '(setting_key)'
], '1.0.1' );

// Add some routes
$app->route( 'app', 'app-home.php' );
$app->route( 'app/profile/{user_id}', 'profile.php', [ 'user_id' ] );
$app->route( 'app/dashboard', 'dashboard.php' );

// Add custom menu items to the fake masterbar
$app->add_menu_item( 'app-home', 'My App', home_url( '/app' ) );
$app->add_menu_item( 'app-dashboard', 'Dashboard', home_url( '/app/dashboard' ) );

// Add user menu items
$app->add_user_menu_item( 'app-profile', 'App Profile', home_url( '/app/profile/' . get_current_user_id() ) );
$app->add_user_menu_item( 'app-settings', 'App Settings', home_url( '/app/settings' ) );

// Create a simple API endpoint
$app->api( 'user-data/{user_id}', function( $params ) {
    global $wpdb;

    $user_id = intval( $params['user_id'] );

    $data = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}app_users WHERE user_id = %d",
        $user_id
    ) );

    return [
        'success' => true,
        'data' => $data
    ];
} );

// Initialize the app
$app->init();

// Example of using the app in a template
function example_template_usage() {
    global $app;

    // Check if we're on an app route
    if ( $app->is_app_request() ) {
        // Get route parameters
        $params = $app->get_route_params();

        // Use the parameters in your template
        if ( isset( $params['user_id'] ) ) {
            $user_id = $params['user_id'];
            // Load user data...
        }
    }
}