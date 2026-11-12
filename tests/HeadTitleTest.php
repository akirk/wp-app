<?php
use PHPUnit\Framework\TestCase;

class HeadTitleTest extends TestCase {
	protected function setUp(): void {
		global $__wp_app_test_actions;
		$__wp_app_test_actions = [];
	}

	public function test_core_title_tag_is_removed_from_wp_head() {
		global $__wp_app_test_actions;
		add_action( 'wp_head', '_wp_render_title_tag', 1 );
		add_action( 'wp_head', '_block_template_render_title_tag', 1 );
		add_action( 'wp_head', 'wp_enqueue_scripts', 1 );

		wp_app_remove_core_title_tag();

		$callbacks = array_column( $__wp_app_test_actions['wp_head'][1], 'callback' );
		$this->assertNotContains( '_wp_render_title_tag', $callbacks );
		$this->assertNotContains( '_block_template_render_title_tag', $callbacks );
		$this->assertContains( 'wp_enqueue_scripts', $callbacks );
	}

	public function test_favicon_uses_only_the_active_apps_image_icon() {
		\WpApp\Registry::reset();
		\WpApp\Registry::register_app_metadata( 'favicon-test', [ 'icon_url' => 'https://example.org/app.png' ] );
		$GLOBALS['wp_app_route'] = [ 'app_path' => 'favicon-test' ];
		try {
			$this->assertSame( 'https://example.org/app.png', wp_app_get_favicon_url() );
			$GLOBALS['wp_app_route'] = [ 'app_path' => 'other-app' ];
			$this->assertSame( '', wp_app_get_favicon_url() );
			$GLOBALS['wp_app_route'] = [];
			$this->assertSame( '', wp_app_get_favicon_url() );
		} finally {
			unset( $GLOBALS['wp_app_route'] );
			\WpApp\Registry::reset();
		}
	}

}
