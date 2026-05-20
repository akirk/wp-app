<?php
/**
 * Base App Abstract Class
 *
 * Provides a structured pattern for building WordPress applications with WpApp.
 * This follows the personal-crm pattern with separate storage and app instances.
 *
 * @package WpApp
 */

namespace WpApp;

if ( class_exists( 'WpApp\BaseApp' ) ) {
	return;
}

abstract class BaseApp {
	/**
	 * WpApp instance
	 *
	 * @var WpApp
	 */
	protected $app;

	/**
	 * Storage instance extending BaseStorage
	 *
	 * @var BaseStorage
	 */
	protected $storage;

	/**
	 * Whether route/core setup has run.
	 *
	 * @var bool
	 */
	private $core_initialized = false;

	/**
	 * Whether localized UI setup has run.
	 *
	 * @var bool
	 */
	private $ui_initialized = false;

	/**
	 * Whether the full initialization action has fired.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Initialize the application
	 *
	 * Call this method on plugins_loaded. Route and rewrite setup happens
	 * immediately; menu labels are deferred until init so translated strings
	 * do not trigger just-in-time textdomain loading notices.
	 */
	public function init() {
		$this->setup_core();

		if ( did_action( 'init' ) || doing_action( 'init' ) ) {
			$this->setup_localized_ui();
			return;
		}

		add_action( 'init', [ $this, 'setup_localized_ui' ], 0 );
	}

	/**
	 * Set up route/core behavior that is safe before init.
	 */
	protected function setup_core() {
		if ( $this->core_initialized ) {
			return;
		}

		$this->setup_database();
		$this->setup_routes();
		$this->app->init();

		$this->core_initialized = true;
	}

	/**
	 * Set up translated labels and other UI that must wait until init.
	 */
	public function setup_localized_ui() {
		if ( $this->ui_initialized ) {
			return;
		}

		$this->setup_menu();

		$this->ui_initialized = true;
		$this->maybe_finish_initialization();
	}

	/**
	 * Fire the initialized action once both phases have run.
	 */
	private function maybe_finish_initialization() {
		if ( $this->initialized || ! $this->core_initialized || ! $this->ui_initialized ) {
			return;
		}

		$this->initialized = true;

		do_action( 'base_app_initialized', $this );
	}

	/**
	 * Set up database tables
	 *
	 * Use WordPress dbDelta() function to create/update tables.
	 * Tables should be created in the activation hook.
	 *
	 * Example:
	 *   global $wpdb;
	 *   require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	 *   $charset_collate = $wpdb->get_charset_collate();
	 *   $sql = "CREATE TABLE {$wpdb->prefix}my_table (
	 *       id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	 *       name varchar(255) NOT NULL,
	 *       PRIMARY KEY (id)
	 *   ) $charset_collate;";
	 *   dbDelta( $sql );
	 */
	abstract protected function setup_database();

	/**
	 * Set up application routes
	 *
	 * Define URL patterns and their corresponding templates.
	 *
	 * Example:
	 *   $this->app->route( '' );
	 *   $this->app->route( 'dashboard' );
	 *   $this->app->route( 'user/{user_id}' );
	 */
	abstract protected function setup_routes();

	/**
	 * Set up admin bar menu items
	 *
	 * Add navigation items to the WordPress admin bar.
	 *
	 * Example:
	 *   $this->app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/my-app/dashboard' ) );
	 *   $this->app->add_user_menu_item( 'profile', 'My Profile', home_url( '/my-app/profile' ) );
	 */
	abstract protected function setup_menu();

	/**
	 * Get the WpApp instance
	 *
	 * @return WpApp
	 */
	public function get_app() {
		return $this->app;
	}

	/**
	 * Get the Storage instance
	 *
	 * @return BaseStorage|null
	 */
	public function get_storage() {
		return $this->storage;
	}
}
