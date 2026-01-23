<?php
/**
 * Plugin Name: Minimal App
 * Description: The simplest possible wp-app example
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Requires at least: 5.0
 */

require_once __DIR__ . '/vendor/autoload.php';
use WpApp\WpApp;

$app = new WpApp( __DIR__ . '/templates', 'minimal', [
	'show_masterbar_for_anonymous' => true,
	'show_wp_logo' => false,
	'show_site_name' => true,
] );
$app->init();