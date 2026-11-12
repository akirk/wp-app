<?php

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Isolated fixture defines a WordPress stub and a dependent plugin class.

// Run in a fresh process so the main test bootstrap cannot pre-load wp-app.
$root     = sys_get_temp_dir() . '/wp-app-provider-' . uniqid();
$source   = dirname( __DIR__, 2 ) . '/src';
$early    = $root . '/early/vendor/akirk/wp-app/src';
$selected = $root . '/selected/vendor/akirk/wp-app';
mkdir( $early, 0777, true );
mkdir( $selected, 0777, true );
copy( $source . '/bootstrap.php', $early . '/bootstrap.php' );
symlink( $source, $selected . '/src' );

define( 'WP_PLUGIN_DIR', realpath( $root ) );
function get_option( $name, $default = false ) {
    if ( 'wp_app_masterbar_settings' === $name ) {
        return [ 'provider' => 'selected/selected.php' ];
    }
    if ( 'active_plugins' === $name ) {
        return [ 'early/early.php', 'selected/selected.php' ];
    }
    return $default;
}

try {
    // Reproduce Composer's shared files registry across two plugin loaders.
    $files = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/composer.json' ), true )['autoload']['files'];
    foreach ( [ dirname( $early ), $selected ] as $package ) {
        foreach ( $files as $file ) {
            $file_id = md5( 'akirk/wp-app:' . $file );
            if ( empty( $GLOBALS['__composer_autoload_files'][ $file_id ] ) ) {
                $GLOBALS['__composer_autoload_files'][ $file_id ] = true;
                require $package . '/' . $file;
            }
        }
        // Community App declares this immediately after its autoloader returns.
        if ( ! class_exists( 'ProviderStorage', false ) ) {
            class ProviderStorage extends \WpApp\BaseStorage {
                protected function get_schema() {
					return []; }
            }
        }
    }
    $reflection = new ReflectionClass( 'WpApp\\BaseStorage' );
    if ( realpath( $source . '/BaseStorage.php' ) !== realpath( $reflection->getFileName() ) ) {
        throw new RuntimeException( 'Wrong provider loaded' );
    }
    echo "selected provider loaded\n";
} finally {
    unlink( $selected . '/src' );
    unlink( $early . '/bootstrap.php' );
    foreach ( [ $early, dirname( $early ), dirname( $early, 2 ), dirname( $early, 3 ), dirname( $early, 4 ), $selected, dirname( $selected ), dirname( $selected, 2 ), dirname( $selected, 3 ), $root ] as $directory ) {
        rmdir( $directory );
    }
}
