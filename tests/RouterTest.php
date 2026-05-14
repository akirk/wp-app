<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Router;

class RouterTest extends TestCase {
    private $router;

    protected function setUp(): void {
        $this->router = new Router( '/test/templates' );
    }

    private function get_routes() {
        $reflection      = new \ReflectionClass( $this->router );
        $routes_property = $reflection->getProperty( 'routes' );

        if ( PHP_VERSION_ID < 80100 ) {
            $routes_property->setAccessible( true );
        }

        return array_values( $routes_property->getValue( $this->router ) );
    }

    public function test_add_route() {
        $this->router->add_route( 'test/{id}', 'test.php', [ 'id' ] );

        $routes = $this->get_routes();

        $this->assertCount( 1, $routes );
        $this->assertEquals( 'test/(?P<id>\d+)', $routes[0]['pattern'] );
        $this->assertEquals( 'test.php', $routes[0]['template'] );
        $this->assertEquals( [ 'id' ], $routes[0]['vars'] );
    }

    public function test_pattern_to_regex() {
        $this->router->add_route( 'test/{id}/edit/{action}', 'test.php', [ 'id', 'action' ] );

        $routes = $this->get_routes();

        $expected_regex = '/^test\/(?P<id>\d+)\/edit\/(?P<action>[^\/]+)$/';
        $this->assertEquals( $expected_regex, $routes[0]['regex'] );
    }
}
