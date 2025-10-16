<?php
namespace WpApp;

require_once __DIR__ . '/wpdb-polyfill.php';

class sqlite_wpdb extends wpdb {

    public function __construct( $sqlite_file, $table_prefix = '' ) {
        $this->prefix = $table_prefix;
        $this->connect_sqlite( $sqlite_file );
    }

    private function connect_sqlite( $sqlite_file ) {
        try {
            // Ensure directory exists
            $db_dir = dirname( $sqlite_file );
            if ( ! is_dir( $db_dir ) ) {
                mkdir( $db_dir, 0755, true );
            }

            $this->pdo = new \PDO( "sqlite:{$sqlite_file}", null, null, array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ) );

            // Enable foreign keys and set pragmas for better MySQL compatibility
            $this->pdo->exec( 'PRAGMA foreign_keys = ON' );
            $this->pdo->exec( 'PRAGMA journal_mode = WAL' );

        } catch ( \PDOException $e ) {
            $this->last_error = 'SQLite connection failed: ' . $e->getMessage();
            throw new Exception( $this->last_error );
        }
    }

    public function query( $query ) {
        $this->last_error = '';
        $this->last_query = $query;
        $this->last_result = null;
        $this->num_rows = 0;
        $this->rows_affected = 0;
        $this->insert_id = 0;

        // Translate MySQL to SQLite
        $translated_query = $this->translate_mysql_to_sqlite( $query );

        try {
            $stmt = $this->pdo->prepare( $translated_query );
            $result = $stmt->execute();

            if ( $result ) {
                // For SELECT queries and PRAGMA queries (SQLite DESCRIBE equivalent)
                if ( stripos( trim( $translated_query ), 'SELECT' ) === 0 ||
                     stripos( trim( $translated_query ), 'PRAGMA' ) === 0 ) {
                    $this->last_result = $stmt->fetchAll( \PDO::FETCH_OBJ );
                    $this->num_rows = count( $this->last_result );
                }
                // For INSERT queries
                else if ( stripos( trim( $translated_query ), 'INSERT' ) === 0 ) {
                    $this->insert_id = $this->pdo->lastInsertId();
                    $this->rows_affected = $stmt->rowCount();
                }
                // For UPDATE/DELETE queries
                else {
                    $this->rows_affected = $stmt->rowCount();
                }

                return $result;
            }

            return false;

        } catch ( \PDOException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    private function translate_mysql_to_sqlite( $query ) {
        // MySQL to SQLite translations
        $translations = array(
            // Data types
            '/\bbigint\(\d+\)\s+unsigned/i' => 'INTEGER',
            '/\bbigint\(\d+\)/i' => 'INTEGER',
            '/\bint\(\d+\)\s+unsigned/i' => 'INTEGER',
            '/\bint\(\d+\)/i' => 'INTEGER',
            '/\btinyint\(1\)/i' => 'INTEGER',
            '/\bvarchar\((\d+)\)/i' => 'TEXT',
            '/\btext\b/i' => 'TEXT',
            '/\blongtext\b/i' => 'TEXT',
            '/\bdatetime\b/i' => 'TEXT',

            // Auto increment
            '/\bAUTO_INCREMENT\b/i' => 'AUTOINCREMENT',

            // Character set and collation (remove for SQLite)
            '/\s+CHARACTER\s+SET\s+\w+/i' => '',
            '/\s+COLLATE\s+\w+/i' => '',
            '/\s+DEFAULT\s+CHARACTER\s+SET\s+\w+\s+COLLATE\s+\w+/i' => '',

            // Table options (remove for SQLite)
            '/\s+ENGINE\s*=\s*\w+/i' => '',

            // Current timestamp
            '/\bCURRENT_TIMESTAMP\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP\b/i' => 'CURRENT_TIMESTAMP',

            // Backticks to double quotes for identifiers
            '/`([^`]+)`/' => '"$1"',

            // IF NOT EXISTS for CREATE TABLE
            '/CREATE\s+TABLE\s+(["\w]+)/i' => 'CREATE TABLE IF NOT EXISTS $1',

            // DESCRIBE to PRAGMA table_info
            '/\bDESCRIBE\s+(["\w]+)/i' => 'PRAGMA table_info($1)',
        );

        $translated = $query;
        foreach ( $translations as $pattern => $replacement ) {
            $translated = preg_replace( $pattern, $replacement, $translated );
        }

        // Handle CREATE DATABASE (ignore for SQLite)
        if ( stripos( $translated, 'CREATE DATABASE' ) === 0 ) {
            return 'SELECT 1'; // No-op query
        }

        // Handle USE database (ignore for SQLite)
        if ( stripos( $translated, 'USE ' ) === 0 ) {
            return 'SELECT 1'; // No-op query
        }

        return $translated;
    }

    public function get_charset_collate() {
        return ''; // SQLite doesn't use charset/collate in this context
    }

    public function insert( $table, $data, $format = null ) {
        if ( empty( $data ) ) {
            return false;
        }

        $fields = array_keys( $data );
        $values = array_values( $data );
        $placeholders = array();

        // Build placeholders and format values
        foreach ( $values as $i => $value ) {
            if ( $format && isset( $format[$i] ) ) {
                switch ( $format[$i] ) {
                    case '%d':
                        $placeholders[] = (int) $value;
                        break;
                    case '%f':
                        $placeholders[] = (float) $value;
                        break;
                    case '%s':
                    default:
                        $placeholders[] = $this->pdo->quote( $value );
                        break;
                }
            } else {
                $placeholders[] = $this->pdo->quote( $value );
            }
        }

        $fields_str = '"' . implode( '", "', $fields ) . '"';
        $values_str = implode( ', ', $placeholders );

        $query = "INSERT INTO \"{$table}\" ({$fields_str}) VALUES ({$values_str})";

        return $this->query( $query );
    }

    public function update( $table, $data, $where, $format = null, $where_format = null ) {
        if ( empty( $data ) || empty( $where ) ) {
            return false;
        }

        $set_clauses = array();
        $data_values = array_values( $data );
        $data_fields = array_keys( $data );

        // Build SET clauses
        foreach ( $data_fields as $i => $field ) {
            $value = $data_values[$i];
            if ( $format && isset( $format[$i] ) ) {
                switch ( $format[$i] ) {
                    case '%d':
                        $set_clauses[] = "\"{$field}\" = " . (int) $value;
                        break;
                    case '%f':
                        $set_clauses[] = "\"{$field}\" = " . (float) $value;
                        break;
                    case '%s':
                    default:
                        $set_clauses[] = "\"{$field}\" = " . $this->pdo->quote( $value );
                        break;
                }
            } else {
                $set_clauses[] = "\"{$field}\" = " . $this->pdo->quote( $value );
            }
        }

        $where_clauses = array();
        $where_values = array_values( $where );
        $where_fields = array_keys( $where );

        // Build WHERE clauses
        foreach ( $where_fields as $i => $field ) {
            $value = $where_values[$i];
            if ( $where_format && isset( $where_format[$i] ) ) {
                switch ( $where_format[$i] ) {
                    case '%d':
                        $where_clauses[] = "\"{$field}\" = " . (int) $value;
                        break;
                    case '%f':
                        $where_clauses[] = "\"{$field}\" = " . (float) $value;
                        break;
                    case '%s':
                    default:
                        $where_clauses[] = "\"{$field}\" = " . $this->pdo->quote( $value );
                        break;
                }
            } else {
                $where_clauses[] = "\"{$field}\" = " . $this->pdo->quote( $value );
            }
        }

        $set_str = implode( ', ', $set_clauses );
        $where_str = implode( ' AND ', $where_clauses );

        $query = "UPDATE \"{$table}\" SET {$set_str} WHERE {$where_str}";

        return $this->query( $query );
    }

    public function delete( $table, $where, $where_format = null ) {
        if ( empty( $where ) ) {
            return false;
        }

        $where_clauses = array();
        $where_values = array_values( $where );
        $where_fields = array_keys( $where );

        // Build WHERE clauses
        foreach ( $where_fields as $i => $field ) {
            $value = $where_values[$i];
            if ( $where_format && isset( $where_format[$i] ) ) {
                switch ( $where_format[$i] ) {
                    case '%d':
                        $where_clauses[] = "\"{$field}\" = " . (int) $value;
                        break;
                    case '%f':
                        $where_clauses[] = "\"{$field}\" = " . (float) $value;
                        break;
                    case '%s':
                    default:
                        $where_clauses[] = "\"{$field}\" = " . $this->pdo->quote( $value );
                        break;
                }
            } else {
                $where_clauses[] = "\"{$field}\" = " . $this->pdo->quote( $value );
            }
        }

        $where_str = implode( ' AND ', $where_clauses );
        $query = "DELETE FROM \"{$table}\" WHERE {$where_str}";

        return $this->query( $query );
    }
}