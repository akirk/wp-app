<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;

class AssetIsolationTest extends TestCase {
    private function registry_with_src( $handle, $src ) {
        return (object) [
            'registered' => [
                $handle => (object) [
                    'src' => $src,
                ],
            ],
        ];
    }

    public function test_plugin_style_is_not_dequeued_by_default() {
        $registry = $this->registry_with_src(
            'ai-assistant-chat',
            'https://example.org/wp-content/plugins/ai-assistant/assets/css/chat.css'
        );

        $this->assertFalse( wp_app_should_dequeue_asset( 'ai-assistant-chat', $registry, 'style' ) );
    }

    public function test_theme_style_is_dequeued_by_source() {
        $registry = $this->registry_with_src(
            'theme-style',
            'https://example.org/wp-content/themes/child-theme/style.css?ver=1.0'
        );

        $this->assertTrue( wp_app_should_dequeue_asset( 'theme-style', $registry, 'style' ) );
    }

    public function test_global_styles_are_dequeued_by_handle() {
        $registry = $this->registry_with_src( 'global-styles', '' );

        $this->assertTrue( wp_app_should_dequeue_asset( 'global-styles', $registry, 'style' ) );
    }
}
