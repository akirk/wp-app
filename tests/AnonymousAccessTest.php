<?php

namespace WpApp;

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Namespace-scoped WordPress response doubles.

// Keep terminating WordPress responses observable without exiting PHPUnit.
function nocache_headers() {
    $GLOBALS['__wp_app_test_anonymous_nocache'] = true;
}

function wp_die( $message, $title = '', $args = [] ) {
    $GLOBALS['__wp_app_test_anonymous_response'] = compact( 'message', 'title', 'args' );
    throw new \RuntimeException( 'Anonymous request stopped' );
}

function wp_login_url( $redirect = '' ) {
    return 'https://example.org/secret-login?redirect_to=' . rawurlencode( $redirect );
}

function wp_redirect( $url ) {
    $GLOBALS['__wp_app_test_anonymous_redirect'] = $url;
    throw new \RuntimeException( 'Login redirect' );
}

function status_header( $code ) {
    if ( 403 === $code ) {
        throw new \RuntimeException( 'Forbidden' );
    }
    return \status_header( $code );
}

class AnonymousAccessTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        Registry::reset();
        $GLOBALS['__wp_app_test_is_user_logged_in']  = false;
        $GLOBALS['__wp_app_test_current_user_can']   = false;
        $GLOBALS['__wp_app_test_actions']            = [];
        $GLOBALS['__wp_app_test_anonymous_nocache']  = false;
        $GLOBALS['__wp_app_test_anonymous_response'] = null;
        $GLOBALS['__wp_app_test_anonymous_redirect'] = null;
        $GLOBALS['wp_query']                         = (object) [ 'query_vars' => [] ];
    }

    protected function tearDown(): void {
        Registry::reset();
        unset( $GLOBALS['wp_query'], $GLOBALS['__wp_app_test_current_user_can'] );
        $GLOBALS['__wp_app_test_is_user_logged_in'] = false;
    }

    /**
     * Private app requests stop before canonical redirects.
     *
     * @dataProvider privateRequests
     */
    public function test_private_app_stops_before_canonical_redirects( $uri, $path ) {
        $_SERVER['REQUEST_URI'] = $uri;
        new WpApp( __DIR__ . '/fixtures/templates', 'private-app', [ 'anonymous_access' => '404' ] );
        $GLOBALS['wp_query']->query_vars = [
			'wp_app_path'    => 'private-app',
			'wp_app_request' => $path,
		];
        $canonical_ran                   = false;
        add_action(
            'template_redirect',
            function () use ( &$canonical_ran ) {
				$canonical_ran = true;
			},
            10
        );

        try {
            do_action( 'template_redirect' );
            $this->fail( 'Anonymous request reached canonical redirects.' );
        } catch ( \RuntimeException $exception ) {
            $this->assertSame( 'Anonymous request stopped', $exception->getMessage() );
        }
        $this->assertFalse( $canonical_ran );
        $this->assertTrue( $GLOBALS['__wp_app_test_anonymous_nocache'] );
        $this->assertSame(
            [
				'response'  => 404,
				'back_link' => false,
			],
			$GLOBALS['__wp_app_test_anonymous_response']['args']
        );
        $this->assertSame( 'Not found.', $GLOBALS['__wp_app_test_anonymous_response']['message'] );
        $this->assertNull( $GLOBALS['__wp_app_test_anonymous_redirect'] );
    }

    public static function privateRequests() {
        return [
            [ '/private-app/', '' ],
            [ '/private-app/recipe/123', 'recipe/123' ],
            [ '/private-app/missing', 'missing' ],
            [ '/subdirectory/private-app/settings', 'settings' ],
            [ '/?wp_app_path=private-app&wp_app_request=recipe/123', 'recipe/123' ],
        ];
    }

    /**
     * Other requests keep their existing behavior.
     *
     * @dataProvider allowedRequests
     */
    public function test_early_guard_leaves_other_requests_alone( $config, $logged_in, $app_path ) {
        new WpApp( __DIR__ . '/fixtures/templates', 'private-app', $config );
        $GLOBALS['__wp_app_test_is_user_logged_in'] = $logged_in;
        $GLOBALS['wp_query']->query_vars            = [ 'wp_app_path' => $app_path ];
        do_action( 'template_redirect' );
        $this->assertNull( $GLOBALS['__wp_app_test_anonymous_response'] );
        $this->assertFalse( $GLOBALS['__wp_app_test_anonymous_nocache'] );
    }

    public static function allowedRequests() {
        return [
            [ [], false, 'private-app' ],
            [ [ 'anonymous_access' => 'invalid' ], false, 'private-app' ],
            [ [ 'anonymous_access' => '404' ], true, 'private-app' ],
            [ [ 'anonymous_access' => '404' ], false, 'other-app' ],
            [ [ 'anonymous_access' => '404' ], false, '' ],
            [
				[
					'anonymous_access' => '404',
					'require_login'    => false,
				],
				false,
				'private-app',
			],
        ];
    }

    public function test_signed_in_user_without_capability_still_receives_403() {
        $router = new Router( __DIR__ . '/fixtures/templates', 'private-app' );
        $router->set_required_capability( 'manage_options' );
        $router->set_anonymous_access( '404' );
        $GLOBALS['__wp_app_test_is_user_logged_in'] = true;
        try {
            $router->handle_app_request_directly( '' );
            $this->fail( 'Expected the existing signed-in 403 response.' );
        } catch ( \RuntimeException $exception ) {
            $this->assertSame( 'Forbidden', $exception->getMessage() );
        }
        $this->assertNull( $GLOBALS['__wp_app_test_anonymous_response'] );
    }

    public function test_default_still_redirects_to_login() {
        $router = new Router( '', 'private-app' );
        $router->set_required_capability( 'read' );
        try {
            $router->handle_app_request_directly( 'recipe/123' );
            $this->fail( 'Expected login redirect.' );
        } catch ( \RuntimeException $exception ) {
            $this->assertSame( 'Login redirect', $exception->getMessage() );
        }
        $this->assertStringContainsString( '/secret-login?', $GLOBALS['__wp_app_test_anonymous_redirect'] );
    }

    public function test_restricted_route_in_public_app_honors_policy() {
        $router = new Router( '', 'public-app' );
        $router->set_anonymous_access( '404' );
        $router->add_route( 'private', 'private.php', [], 'edit_posts' );
        try {
            $router->handle_app_request_directly( 'private' );
            $this->fail( 'Expected anonymous 404.' );
        } catch ( \RuntimeException $exception ) {
            $this->assertSame( 'Anonymous request stopped', $exception->getMessage() );
        }
        $this->assertSame( 404, $GLOBALS['__wp_app_test_anonymous_response']['args']['response'] );
        $this->assertNull( $GLOBALS['__wp_app_test_anonymous_redirect'] );
    }
}
