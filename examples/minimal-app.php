<?php
/**
 * Plugin Name: Minimal App
 * Description: The simplest possible wp-app
 * Version: 1.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';
use WpApp\WpApp;

class MinimalApp {
    private $app;

    public function __construct() {
        $this->app = new WpApp( __DIR__ . '/templates', 'minimal' );
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        $this->app->init();
    }
}

new MinimalApp();