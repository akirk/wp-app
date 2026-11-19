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
    $url = $GLOBALS['__wp_app_test_login_url'] ?? 'https://example.org/wp-login.php';
    return $redirect ? $url . '?redirect_to=' . rawurlencode( $redirect ) : $url;
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
        $GLOBALS['__wp_app_test_options']            = [];
        $GLOBALS['__wp_app_test_login_url']          = 'https://example.org/wp-login.php';
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
        unset( $GLOBALS['wp_query'], $GLOBALS['__wp_app_test_current_user_can'], $GLOBALS['__wp_app_test_login_url'] );
        $GLOBALS['__wp_app_test_options']           = [];
        $GLOBALS['__wp_app_test_is_user_logged_in'] = false;
    }

    /**
     * Private app requests stop before canonical redirects.
     *
     * @dataProvider privateRequests
     */
    public function test_private_app_stops_before_canonical_redirects( $uri, $path ) {
        $_SERVER['REQUEST_URI']                               = $uri;
        $GLOBALS['__wp_app_test_options'][ Settings::OPTION ] = [ 'anonymous_access' => '404' ];
        new WpApp( __DIR__ . '/fixtures/templates', 'private-app' );
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
        $GLOBALS['__wp_app_test_options'][ Settings::OPTION ] = [ 'anonymous_access' => $config['anonymous_access'] ?? 'auto' ];
        unset( $config['anonymous_access'] );
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

    /**
     * Automatic detection and explicit site overrides.
     *
     * @dataProvider loginPolicies
     */
    public function test_site_policy( $site_url, $login_url, $setting, $expected ) {
        $GLOBALS['__wp_app_test_options']['siteurl']          = $site_url;
        $GLOBALS['__wp_app_test_login_url']                   = $login_url;
        $GLOBALS['__wp_app_test_options'][ Settings::OPTION ] = [ 'anonymous_access' => $setting ];
        $this->assertSame( $expected, Settings::get_anonymous_access() );
    }

    public static function loginPolicies() {
        return [
            [ 'https://example.org', 'https://example.org/wp-login.php', 'auto', 'redirect' ],
            [ 'http://example.org', 'https://example.org/wp-login.php', 'auto', 'redirect' ],
            [ 'https://example.org/wp', 'https://example.org/wp/wp-login.php', 'auto', 'redirect' ],
            [ 'https://example.org', 'https://example.org/secret-login/', 'auto', '404' ],
            [ 'https://example.org/wp', 'https://example.org/secret-login/', 'auto', '404' ],
            [ 'https://example.org', 'https://example.org/?secret-login', 'auto', '404' ],
            [ 'https://example.org', 'https://sso.example.org/login', 'auto', '404' ],
            [ 'https://example.org', 'https://example.org/secret-login/', 'redirect', 'redirect' ],
            [ 'https://example.org', 'https://example.org/wp-login.php', '404', '404' ],
        ];
    }

    public function test_modified_login_url_protects_all_private_apps_without_app_configuration() {
        $GLOBALS['__wp_app_test_login_url'] = 'https://example.org/secret-login/';
        new WpApp( '', 'first-app' );
        new WpApp( '', 'second-app' );
        foreach ( [ 'first-app', 'second-app' ] as $app_path ) {
            $GLOBALS['wp_query']->query_vars = [ 'wp_app_path' => $app_path ];
            try {
                do_action( 'template_redirect' );
                $this->fail( 'Expected automatic anonymous 404.' );
            } catch ( \RuntimeException $exception ) {
                $this->assertSame( 'Anonymous request stopped', $exception->getMessage() );
            }
        }
    }

    public function test_site_setting_is_sanitized_and_rendered() {
        foreach ( [ 'auto', '404', 'redirect' ] as $mode ) {
            $settings = Settings::sanitize_settings( [ 'anonymous_access' => $mode ] );
            $this->assertSame( $mode, $settings['anonymous_access'] );
        }
        $settings = Settings::sanitize_settings( [ 'anonymous_access' => [ '404' ] ] );
        $this->assertSame( 'auto', $settings['anonymous_access'] );
        $GLOBALS['__wp_app_test_current_user_can']            = true;
        $GLOBALS['__wp_app_test_options'][ Settings::OPTION ] = [ 'anonymous_access' => 'redirect' ];
        ob_start();
        try {
            Settings::render_settings_page();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString( 'id="wp-app-anonymous-access"', $output );
        $this->assertMatchesRegularExpression( '/value="redirect"\s+selected="selected"/', $output );
        $this->assertStringContainsString( 'Automatic (404 when the login URL is modified)', $output );
    }

    /**
     * Show the control only when relevant, retaining access to saved overrides.
     *
     * @dataProvider settingVisibility
     */
    public function test_setting_visibility( $url, $mode, $visible ) {
        $GLOBALS['__wp_app_test_current_user_can']            = true;
        $GLOBALS['__wp_app_test_login_url']                   = $url;
        $GLOBALS['__wp_app_test_options'][ Settings::OPTION ] = [ 'anonymous_access' => $mode ];
        ob_start();
        try {
            Settings::render_settings_page();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertSame( $visible, false !== strpos( $output, 'id="wp-app-anonymous-access"' ) );
    }

    public static function settingVisibility() {
        return [
            [ 'https://example.org/wp-login.php', 'auto', false ],
            [ 'https://example.org/secret-login/', 'auto', true ],
            [ 'https://example.org/wp-login.php', '404', true ],
            [ 'https://example.org/wp-login.php', 'redirect', true ],
        ];
    }

    public function test_signed_in_user_without_capability_still_receives_403() {
        $router = new Router( __DIR__ . '/fixtures/templates', 'private-app' );
        $router->set_required_capability( 'manage_options' );
        $GLOBALS['__wp_app_test_options'][ Settings::OPTION ] = [ 'anonymous_access' => '404' ];
        $GLOBALS['__wp_app_test_is_user_logged_in']           = true;
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
        $this->assertStringContainsString( '/wp-login.php?', $GLOBALS['__wp_app_test_anonymous_redirect'] );
    }

    public function test_restricted_route_in_public_app_honors_policy() {
        $router = new Router( '', 'public-app' );
        $GLOBALS['__wp_app_test_options'][ Settings::OPTION ] = [ 'anonymous_access' => '404' ];
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
