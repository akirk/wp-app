<?php
/**
 * Plugin Name: Minimal App
 * Description: The simplest possible wp-app - should work with just this
 * Version: 1.0.0
 */

require_once __DIR__ . '/vendor/autoload.php';
use WpApp\WpApp;

// This should be enough for a basic app:
$app = new WpApp( __DIR__ . '/templates', 'minimal', [
    'show_masterbar_for_anonymous' => true,
    'show_wp_logo' => false,
    'show_site_name' => true
] );
$app->init();