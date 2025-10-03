<?php
/**
 * Dark Mode Toggle Example
 *
 * This example shows how to initialize wpApp with a dark mode toggle in the masterbar
 */

// Include Composer autoloader (adjust path as needed)
require_once __DIR__ . '/../vendor/autoload.php';

use WpApp\WpApp;

// Initialize the app with dark mode toggle enabled
$app = new WpApp( __DIR__ . '/templates', 'dark-mode-app', [
    'show_dark_mode_toggle' => true,
    'show_wp_logo' => true,
    'show_site_name' => true,
    'show_masterbar_for_anonymous' => true
] );

// Add some routes
$app->route( '', 'index.php' );
$app->route( 'settings', 'settings.php' );

// Add menu items
$app->add_menu_item( 'home', 'Home', home_url( '/dark-mode-app' ) );
$app->add_menu_item( 'settings', 'Settings', home_url( '/dark-mode-app/settings' ) );

// Initialize the app
$app->init();

// You can also enable/disable the dark mode toggle programmatically:
// $app->show_dark_mode_toggle( true );  // Enable
// $app->show_dark_mode_toggle( false ); // Disable