<?php
/**
 * Basic usage example for WpApp framework
 *
 * This file shows how to set up a simple web app using the WpApp framework
 */

require_once __DIR__ . '/vendor/autoload.php';

use WpApp\WpApp;

add_action( 'plugins_loaded', function() {
    $app = new WpApp( __DIR__ . '/templates', 'app' );

    $app->route( 'home', 'app-home.php' );
    $app->route( 'profile/{user_id}', 'profile.php', [ 'user_id' ] );
    $app->route( 'dashboard' );

    $app->add_menu_item( 'app-home', 'My App', home_url( '/app/home' ) );
    $app->add_menu_item( 'app-dashboard', 'Dashboard', home_url( '/app/dashboard' ) );

    $app->add_user_menu_item( 'app-profile', 'App Profile', home_url( '/app/profile/' . get_current_user_id() ) );
    $app->add_user_menu_item( 'app-settings', 'App Settings', home_url( '/app/settings' ) );

    $app->init();
} );
