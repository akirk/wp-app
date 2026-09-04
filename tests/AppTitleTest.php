<?php
use PHPUnit\Framework\TestCase;

class AppTitleTest extends TestCase {
	protected function setUp(): void {
		global $wp_app_route;
		$wp_app_route = null;
	}

	public function test_get_title_returns_unescaped_text() {
		$this->assertSame(
			'Tom & Jerry - Test Blog',
			wp_app_get_title( 'Tom & Jerry' )
		);
	}

	public function test_the_title_prints_escaped_and_returns_nothing() {
		ob_start();
		$returned = wp_app_the_title( 'Tom & Jerry' );
		$printed  = ob_get_clean();

		$this->assertSame( 'Tom &amp; Jerry - Test Blog', $printed );
		$this->assertNull( $returned );
	}

	public function test_deprecated_wp_app_title_still_returns_escaped_html() {
		$this->assertSame(
			'Tom &amp; Jerry - Test Blog',
			wp_app_title( 'Tom & Jerry' )
		);
	}

	public function test_title_falls_back_to_the_current_route() {
		global $wp_app_route;
		$wp_app_route = [ 'pattern' => 'plan-your/sessions' ];

		$this->assertSame( 'Plan Your Sessions - Test Blog', wp_app_get_title() );
	}

	public function test_separator_is_configurable() {
		$this->assertSame( 'Notes | Test Blog', wp_app_get_title( 'Notes', '|' ) );
	}
}
