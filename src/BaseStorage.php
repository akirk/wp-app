<?php
/**
 * Base Storage Class
 *
 * Abstract base class for database storage with common functionality.
 * Provides migration system, wpdb access, and utility methods.
 */

namespace WpApp;

abstract class BaseStorage {
	protected $wpdb;

	public function __construct( $wpdb_instance ) {
		global $wpdb;
		$this->wpdb = $wpdb_instance;

		if ( ! $wpdb ) {
			$wpdb = $wpdb_instance;
		}

		$this->init_database();
	}

	/**
	 * Initialize database - create tables and run migrations
	 */
	private function init_database() {
		if ( ! function_exists( 'dbDelta' ) ) {
			if ( ! defined( 'ABSPATH' ) ) {
				throw new \Exception( 'ABSPATH not defined. BaseStorage requires WordPress environment.' );
			}
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		}

		$this->init_migrations();

		$this->run_migration( 'initial_table_creation', function() {
			$this->create_tables();
		} );

		$this->run_migrations();
	}

	/**
	 * Create database tables - to be implemented by child classes
	 */
	abstract protected function create_tables();

	/**
	 * Run migrations - to be implemented by child classes
	 */
	abstract protected function run_migrations();

	/**
	 * Initialize migrations table if it doesn't exist
	 */
	protected function init_migrations() {
		$charset_collate = $this->wpdb->get_charset_collate();
		$migrations_table = $this->get_migrations_table_name();

		$sql = "CREATE TABLE {$migrations_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			migration_name varchar(255) NOT NULL,
			applied_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_migration (migration_name)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Get migrations table name - can be overridden by child classes
	 */
	protected function get_migrations_table_name() {
		return $this->wpdb->prefix . 'migrations';
	}

	/**
	 * Run a specific migration
	 */
	protected function run_migration( $migration_name, $migration_callback ) {
		$migrations_table = $this->get_migrations_table_name();

		$migration_exists = $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$migrations_table} WHERE migration_name = %s",
			$migration_name
		) );

		if ( ! $migration_exists ) {
			call_user_func( $migration_callback );

			$this->wpdb->insert(
				$migrations_table,
				array( 'migration_name' => $migration_name ),
				array( '%s' )
			);
		}
	}

	/**
	 * Get wpdb instance for custom queries
	 */
	public function get_wpdb() {
		return $this->wpdb;
	}
}
