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

}
