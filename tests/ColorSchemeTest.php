<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;

class ColorSchemeTest extends TestCase {
    public function test_admin_color_scheme_css_outputs_auto_dark_mode() {
        $css = wp_app_get_admin_color_scheme_css();

        $this->assertStringContainsString( '--wp-app-color-scheme: light;', $css );
        $this->assertStringContainsString( '--wp-app-color-background: #f6f7f7;', $css );
        $this->assertStringContainsString( '@media (prefers-color-scheme: dark)', $css );
        $this->assertStringContainsString( '--wp-app-color-scheme: dark;', $css );
        $this->assertStringContainsString( '--wp-app-color-background: #101517;', $css );
    }

    public function test_dark_color_scheme_variables_keep_admin_colors() {
        $scheme    = wp_app_get_admin_color_scheme();
        $variables = wp_app_get_color_scheme_variables( $scheme, 'dark' );

        $this->assertSame( $scheme['colors'][0], $variables['--wp-app-admin-color-background'] );
        $this->assertSame( $scheme['colors'][2], $variables['--wp-app-admin-color-primary'] );
        $this->assertSame( 'var(--wp-app-admin-color-primary)', $variables['--wp-app-color-primary'] );
        $this->assertSame( wp_app_darken_css_color( $scheme['colors'][2], 10 ), $variables['--wp-app-color-primary-hover'] );
        $this->assertSame( wp_app_darken_css_color( $scheme['colors'][2], 10 ), $variables['--wp-app-color-link-hover'] );
        $this->assertSame( 'dark', $variables['--wp-app-color-scheme'] );
        $this->assertSame( '#f0f0f1', $variables['--wp-app-color-text'] );
    }

    public function test_admin_color_scheme_with_three_colors_maps_primary_and_accent() {
        global $_wp_admin_css_colors, $__wp_app_test_user_options;

        $previous_admin_css_colors = $_wp_admin_css_colors ?? null;
        $previous_user_options     = $__wp_app_test_user_options ?? null;

        try {
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test fixture for WordPress admin color registry.
            $_wp_admin_css_colors = [
                'three-color-scheme' => (object) [
                    'name'        => 'Three Color Scheme',
                    'colors'      => [ '#1e1e1e', '#3858e9', '#7b90ff' ],
                    'icon_colors' => [
                        'base'    => '#cccccc',
                        'focus'   => '#72aee6',
                        'current' => '#ffffff',
                    ],
                ],
            ];

            $__wp_app_test_user_options = [
                'admin_color' => 'three-color-scheme',
            ];

            $scheme = wp_app_get_admin_color_scheme();
        } finally {
            if ( null === $previous_admin_css_colors ) {
                unset( $_wp_admin_css_colors );
            } else {
                // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore test fixture state.
                $_wp_admin_css_colors = $previous_admin_css_colors;
            }

            if ( null === $previous_user_options ) {
                unset( $__wp_app_test_user_options );
            } else {
                $__wp_app_test_user_options = $previous_user_options;
            }
        }

        $this->assertSame( [ '#1e1e1e', '#1e1e1e', '#3858e9', '#7b90ff' ], $scheme['colors'] );
    }
}
