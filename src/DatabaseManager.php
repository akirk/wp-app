<?php

namespace WpApp;

/**
 * Database manager for handling table creation and updates using dbDelta
 */
class DatabaseManager {
    private $migrations = [];
    private $version_option_name = 'wp_app_db_version';

    public function __construct( $version_option_name = 'wp_app_db_version' ) {
        $this->version_option_name = $version_option_name;
    }

    /**
     * Add a migration with SQL schema
     *
     * @param string $name Migration name
     * @param string $sql SQL schema for table creation
     * @param string $version Migration version
     */
    public function add_migration( $name, $sql, $version ) {
        $this->migrations[ $version ] = [
            'name' => $name,
            'sql' => $sql,
            'version' => $version
        ];
    }

    /**
     * Run all pending migrations
     */
    public function migrate() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $current_version = get_option( $this->version_option_name, '0.0.0' );

        // Sort migrations by version
        uksort( $this->migrations, 'version_compare' );

        foreach ( $this->migrations as $version => $migration ) {
            if ( version_compare( $current_version, $version, '<' ) ) {
                $this->run_migration( $migration );
                update_option( $this->version_option_name, $version );
            }
        }
    }

    /**
     * Run a single migration
     */
    private function run_migration( $migration ) {
        global $wpdb;

        // Replace table prefix placeholder
        $sql = str_replace( '{table_prefix}', $wpdb->prefix, $migration['sql'] );

        dbDelta( $sql );

        do_action( 'wp_app_migration_complete', $migration['name'], $migration['version'] );
    }

    /**
     * Add a table schema
     *
     * @param string $table_name Table name (without prefix)
     * @param array $columns Column definitions
     * @param string $version Migration version
     * @param array $indexes Index definitions
     */
    public function add_table( $table_name, $columns, $version, $indexes = [] ) {
        global $wpdb;

        $table_name_with_prefix = $wpdb->prefix . $table_name;

        $sql = "CREATE TABLE {table_prefix}{$table_name} (\n";

        $column_definitions = [];
        foreach ( $columns as $column_name => $definition ) {
            $column_definitions[] = "  {$column_name} {$definition}";
        }

        $sql .= implode( ",\n", $column_definitions );

        if ( ! empty( $indexes ) ) {
            foreach ( $indexes as $index_name => $index_definition ) {
                $sql .= ",\n  {$index_definition}";
            }
        }

        $sql .= "\n) {$wpdb->get_charset_collate()};";

        $this->add_migration( "create_table_{$table_name}", $sql, $version );
    }

    /**
     * Check if a table exists
     */
    public function table_exists( $table_name ) {
        global $wpdb;

        $table_name_with_prefix = $wpdb->prefix . $table_name;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name_with_prefix
            )
        );

        return $result === $table_name_with_prefix;
    }

    /**
     * Get current database version
     */
    public function get_current_version() {
        return get_option( $this->version_option_name, '0.0.0' );
    }

    /**
     * Force update database version
     */
    public function set_version( $version ) {
        update_option( $this->version_option_name, $version );
    }

    /**
     * Drop all tables (use with caution)
     */
    public function drop_all_tables() {
        global $wpdb;

        foreach ( $this->migrations as $migration ) {
            // Extract table name from CREATE TABLE statement
            if ( preg_match( '/CREATE TABLE\s+\{table_prefix\}(\w+)/i', $migration['sql'], $matches ) ) {
                $table_name = $wpdb->prefix . $matches[1];
                $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
            }
        }

        delete_option( $this->version_option_name );
    }
}