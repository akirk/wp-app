<?php
namespace WpApp;

class wpdb {
    public $prefix = '';
    public $last_error = '';
    public $last_result = null;
    public $last_query = '';
    public $insert_id = 0;
    public $num_rows = 0;
    public $rows_affected = 0;

    protected $pdo;
    private $charset = 'utf8mb4';
    private $collate = 'utf8mb4_unicode_ci';

    public function __construct( $dbuser, $dbpassword, $dbname, $dbhost, $dbport = 3306, $table_prefix = '' ) {
        $this->prefix = $table_prefix;

        $this->connect( $dbuser, $dbpassword, $dbname, $dbhost, $dbport );
    }

    private function connect( $dbuser, $dbpassword, $dbname, $dbhost, $dbport ) {
        try {
            $dsn = "mysql:host={$dbhost};port={$dbport};charset={$this->charset}";

            $this->pdo = new \PDO( $dsn, $dbuser, $dbpassword, array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE {$this->collate}"
            ) );

            // Create database if it doesn't exist
            $this->pdo->exec( "CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET {$this->charset} COLLATE {$this->collate}" );
            $this->pdo->exec( "USE `{$dbname}`" );

        } catch ( \PDOException $e ) {
            $this->last_error = 'Database connection failed: ' . $e->getMessage();
            throw new Exception( $this->last_error );
        }
    }

    public function prepare( $query, ...$args ) {
        if ( empty( $args ) ) {
            return $query;
        }

        // Flatten array if single array argument passed
        if ( count( $args ) === 1 && is_array( $args[0] ) ) {
            $args = $args[0];
        }

        // Replace WordPress placeholders with \PDO-style placeholders and escape values
        $prepared_query = $query;
        $arg_index = 0;

        // Handle %s (string), %d (integer), %f (float)
        $prepared_query = preg_replace_callback( '/%[sdf]/', function( $matches ) use ( $args, &$arg_index ) {
            if ( ! isset( $args[$arg_index] ) ) {
                return $matches[0];
            }

            $value = $args[$arg_index++];
            $placeholder = $matches[0];

            switch ( $placeholder ) {
                case '%s':
                    return $this->pdo->quote( (string) $value );
                case '%d':
                    return (int) $value;
                case '%f':
                    return (float) $value;
                default:
                    return $this->pdo->quote( $value );
            }
        }, $prepared_query );

        return $prepared_query;
    }

    public function query( $query ) {
        $this->last_error = '';
        $this->last_query = $query;
        $this->last_result = null;
        $this->num_rows = 0;
        $this->rows_affected = 0;
        $this->insert_id = 0;

        try {
            $stmt = $this->pdo->prepare( $query );
            $result = $stmt->execute();

            if ( $result ) {
                // For SELECT queries
                if ( stripos( trim( $query ), 'SELECT' ) === 0 ) {
                    $this->last_result = $stmt->fetchAll( \PDO::FETCH_OBJECT );
                    $this->num_rows = count( $this->last_result );
                }
                // For INSERT queries
                else if ( stripos( trim( $query ), 'INSERT' ) === 0 ) {
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

    public function get_results( $query = null, $output = OBJECT ) {
        if ( $query ) {
            $this->query( $query );
        }

        if ( ! $this->last_result ) {
            return array();
        }

        switch ( $output ) {
            case ARRAY_A:
                $results = array();
                foreach ( $this->last_result as $row ) {
                    $results[] = (array) $row;
                }
                return $results;

            case ARRAY_N:
                $results = array();
                foreach ( $this->last_result as $row ) {
                    $results[] = array_values( (array) $row );
                }
                return $results;

            case OBJECT:
            default:
                return $this->last_result;
        }
    }

    public function get_row( $query = null, $output = OBJECT, $y = 0 ) {
        if ( $query ) {
            $this->query( $query );
        }

        if ( ! $this->last_result || ! isset( $this->last_result[$y] ) ) {
            return null;
        }

        $row = $this->last_result[$y];

        switch ( $output ) {
            case ARRAY_A:
                return (array) $row;

            case ARRAY_N:
                return array_values( (array) $row );

            case OBJECT:
            default:
                return $row;
        }
    }

    public function get_col( $query = null, $x = 0 ) {
        if ( $query ) {
            $this->query( $query );
        }

        if ( ! $this->last_result ) {
            return array();
        }

        $results = array();
        foreach ( $this->last_result as $row ) {
            $row_array = array_values( (array) $row );
            if ( isset( $row_array[$x] ) ) {
                $results[] = $row_array[$x];
            }
        }

        return $results;
    }

    public function get_var( $query = null, $x = 0, $y = 0 ) {
        if ( $query ) {
            $this->query( $query );
        }

        if ( ! $this->last_result || ! isset( $this->last_result[$y] ) ) {
            return null;
        }

        $row = array_values( (array) $this->last_result[$y] );
        return isset( $row[$x] ) ? $row[$x] : null;
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

        $fields_str = '`' . implode( '`, `', $fields ) . '`';
        $values_str = implode( ', ', $placeholders );

        $query = "INSERT INTO `{$table}` ({$fields_str}) VALUES ({$values_str})";

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
                        $set_clauses[] = "`{$field}` = " . (int) $value;
                        break;
                    case '%f':
                        $set_clauses[] = "`{$field}` = " . (float) $value;
                        break;
                    case '%s':
                    default:
                        $set_clauses[] = "`{$field}` = " . $this->pdo->quote( $value );
                        break;
                }
            } else {
                $set_clauses[] = "`{$field}` = " . $this->pdo->quote( $value );
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
                        $where_clauses[] = "`{$field}` = " . (int) $value;
                        break;
                    case '%f':
                        $where_clauses[] = "`{$field}` = " . (float) $value;
                        break;
                    case '%s':
                    default:
                        $where_clauses[] = "`{$field}` = " . $this->pdo->quote( $value );
                        break;
                }
            } else {
                $where_clauses[] = "`{$field}` = " . $this->pdo->quote( $value );
            }
        }

        $set_str = implode( ', ', $set_clauses );
        $where_str = implode( ' AND ', $where_clauses );

        $query = "UPDATE `{$table}` SET {$set_str} WHERE {$where_str}";

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
                        $where_clauses[] = "`{$field}` = " . (int) $value;
                        break;
                    case '%f':
                        $where_clauses[] = "`{$field}` = " . (float) $value;
                        break;
                    case '%s':
                    default:
                        $where_clauses[] = "`{$field}` = " . $this->pdo->quote( $value );
                        break;
                }
            } else {
                $where_clauses[] = "`{$field}` = " . $this->pdo->quote( $value );
            }
        }

        $where_str = implode( ' AND ', $where_clauses );
        $query = "DELETE FROM `{$table}` WHERE {$where_str}";

        return $this->query( $query );
    }

    public function get_charset_collate() {
        return "DEFAULT CHARACTER SET {$this->charset} COLLATE {$this->collate}";
    }

    public function esc_like( $text ) {
        return str_replace( array( '\\', '%', '_' ), array( '\\\\', '\\%', '\\_' ), $text );
    }

    public function print_error( $str = '' ) {
        if ( ! $str ) {
            $str = $this->last_error;
        }
        echo "<div class='error'><p>Database Error: {$str}</p></div>";
    }

    public function show_errors( $show = true ) {
        // In this polyfill, we always show errors via exceptions
        return true;
    }

    public function hide_errors() {
        return $this->show_errors( false );
    }

    public function flush() {
        $this->last_result = null;
        $this->last_error = '';
        $this->num_rows = 0;
        $this->rows_affected = 0;
    }

    public function get_pdo() {
        return $this->pdo;
    }
}

// Define WordPress database constants if not already defined
if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
    define( 'ARRAY_N', 'ARRAY_N' );
}
