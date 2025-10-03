<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Router;

class RouterTest extends TestCase {
    private $router;

    protected function setUp(): void {
        $this->router = new Router( '/test/templates' );
    }

    public function test_add_route() {
        $this->router->add_route( 'test/{id}', 'test.php', [ 'id' ] );

        $reflection = new \ReflectionClass( $this->router );
        $routes_property = $reflection->getProperty( 'routes' );
        $routes_property->setAccessible( true );
        $routes = $routes_property->getValue( $this->router );

        $this->assertCount( 1, $routes );
        $this->assertEquals( 'test/{id}', $routes[0]['pattern'] );
        $this->assertEquals( 'test.php', $routes[0]['template'] );
        $this->assertEquals( [ 'id' ], $routes[0]['vars'] );
    }

    public function test_pattern_to_regex() {
        $this->router->add_route( 'test/{id}/edit/{action}', 'test.php', [ 'id', 'action' ] );

        $reflection = new \ReflectionClass( $this->router );
        $routes_property = $reflection->getProperty( 'routes' );
        $routes_property->setAccessible( true );
        $routes = $routes_property->getValue( $this->router );

        $expected_regex = '^test\/([^\/]+)\/edit\/([^\/]+)$';
        $this->assertEquals( $expected_regex, $routes[0]['regex'] );
    }
}