<?php
/**
 * Plugin Name: Encrypted Sources App
 * Description: Example WpApp app that augments an existing CPT with client-side encrypted fields.
 * Version: 1.0.0
 * Author: Your Name
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>Encrypted Sources App: Please run <code>composer install</code> in the plugin directory.</p></div>';
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

use WpApp\WpApp;

const ENCRYPTED_SOURCES_POST_TYPE = 'journalist_source';

add_action( 'init', 'encrypted_sources_register_content_model' );
add_action( 'plugins_loaded', 'encrypted_sources_register_app' );

register_activation_hook( __FILE__, 'encrypted_sources_activate' );

/**
 * Register the normal WordPress content model in PHP.
 */
function encrypted_sources_register_content_model() {
	register_post_type(
		ENCRYPTED_SOURCES_POST_TYPE,
		[
			'labels'       => [
				'name'          => 'Protected Sources',
				'singular_name' => 'Protected Source',
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => false,
			'supports'     => [ 'title', 'author' ],
		]
	);

	register_taxonomy(
		'source_risk',
		ENCRYPTED_SOURCES_POST_TYPE,
		[
			'label'        => 'Risk',
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => false,
		]
	);

	register_taxonomy(
		'source_workflow',
		ENCRYPTED_SOURCES_POST_TYPE,
		[
			'label'        => 'Workflow',
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => false,
		]
	);
}

/**
 * Register WpApp and augment the CPT with client-side encrypted fields.
 */
function encrypted_sources_register_app() {
	$app = new WpApp(
		plugin_dir_path( __FILE__ ) . 'templates',
		'encrypted-sources',
		[
			'require_login' => true,
			'app_name'      => 'Encrypted Sources',
			'my_apps_icon'  => 'dashicons-lock',
		]
	);

	$app->route( '' );
	$app->init();

	$encrypted_fields = wp_app_register_client_encrypted_fields(
		__DIR__ . '/wp-app-encrypted-fields.json',
		[
			'action_prefix' => 'encrypted_sources',
			'capability'    => 'read',
		]
	);

	add_action(
		'template_redirect',
		function () use ( $app, $encrypted_fields ) {
			if ( ! $app->is_app_request() ) {
				return;
			}

			$encrypted_fields->enqueue_assets( 'encrypted-sources' );
			wp_app_enqueue_style(
				'encrypted-sources',
				plugin_dir_url( __FILE__ ) . 'assets/app.css',
				[],
				'1.0.0',
				'encrypted-sources'
			);
			wp_app_enqueue_script(
				'encrypted-sources',
				plugin_dir_url( __FILE__ ) . 'assets/app.js',
				[],
				'1.0.0',
				true,
				'encrypted-sources'
			);
		}
	);
}

/**
 * Flush routes when the example is activated.
 */
function encrypted_sources_activate() {
	encrypted_sources_register_content_model();
	flush_rewrite_rules();
}
