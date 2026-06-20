<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Router;

class RouterTest extends TestCase {
    private $router;

    protected function setUp(): void {
        global $__wp_app_test_actions, $__wp_app_test_action_counts, $__wp_app_test_current_locale,
            $__wp_app_test_locale_stack, $__wp_app_test_is_user_logged_in, $__wp_app_test_current_user_id,
            $__wp_app_test_user_locales;

        $__wp_app_test_actions           = [];
        $__wp_app_test_action_counts     = [];
        $__wp_app_test_current_locale    = 'en_US';
        $__wp_app_test_locale_stack      = [];
        $__wp_app_test_is_user_logged_in = false;
        $__wp_app_test_current_user_id   = 0;
        $__wp_app_test_user_locales      = [];
        $_SERVER['REQUEST_URI']          = '';

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

    public function test_registering_router_for_current_app_url_switches_to_user_locale() {
        global $__wp_app_test_current_locale, $__wp_app_test_is_user_logged_in, $__wp_app_test_current_user_id,
            $__wp_app_test_user_locales;

        $_SERVER['REQUEST_URI']          = '/cookbook/recipe/123/';
        $__wp_app_test_current_locale    = 'en_US';
        $__wp_app_test_is_user_logged_in = true;
        $__wp_app_test_current_user_id   = 123;
        $__wp_app_test_user_locales      = [
            123 => 'de_DE',
        ];

        new Router( '/test/templates', 'cookbook' );

        $this->assertSame( 'de_DE', $__wp_app_test_current_locale );
    }

    public function test_app_request_switches_to_current_user_locale() {
        global $__wp_app_test_current_locale, $__wp_app_test_is_user_logged_in, $__wp_app_test_current_user_id,
            $__wp_app_test_user_locales;

        $__wp_app_test_current_locale    = 'en_US';
        $__wp_app_test_is_user_logged_in = true;
        $__wp_app_test_current_user_id   = 123;
        $__wp_app_test_user_locales      = [
            123 => 'de_DE',
        ];

        $template_dir = WP_CONTENT_DIR . '/wp-app-router-test';
        if ( ! is_dir( $template_dir ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture setup.
            mkdir( $template_dir, 0777, true );
        }

        $template = $template_dir . '/locale.php';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
        file_put_contents( $template, '<?php echo $GLOBALS["__wp_app_test_current_locale"];' );

        $this->router = new Router( $template_dir );
        $this->router->add_route( '', 'locale.php' );

        ob_start();
        $this->router->handle_app_request_directly( '' );
        $output = ob_get_clean();

        // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
        unlink( $template );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture cleanup.
        rmdir( $template_dir );

        $this->assertSame( 'de_DE', $output );
        $this->assertSame( 'en_US', $__wp_app_test_current_locale );
    }

    public function test_wp_app_language_attributes_uses_active_locale() {
        global $__wp_app_test_current_locale;

        $__wp_app_test_current_locale = 'de_DE';

        $this->assertSame( 'lang="de-DE"', \wp_app_language_attributes( false ) );
    }
}
