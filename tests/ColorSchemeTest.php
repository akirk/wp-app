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
}
