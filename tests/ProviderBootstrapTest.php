<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;

class ProviderBootstrapTest extends TestCase {
    public function test_selected_provider_loads_before_first_plugin_extends_storage() {
        $command = escapeshellarg( PHP_BINARY ) . ' -d opcache.enable_cli=1 '
            . escapeshellarg( __DIR__ . '/fixtures/provider-bootstrap.php' );
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Bootstrap isolation requires a fresh PHP process.
        exec( $command . ' 2>&1', $output, $status );

        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $this->assertSame( [ 'selected provider loaded' ], $output );
    }
}
