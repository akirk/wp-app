<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WpApp\Masterbar;
use WpApp\Registry;

/**
 * Every app on a site builds its own Masterbar, but the styles and scripts are
 * the same for all of them, so they belong on wp_app_head exactly once.
 */
class MasterbarSharedHooksTest extends TestCase {
	protected function setUp(): void {
		global $__wp_app_test_actions, $__wp_app_test_filters, $__wp_app_test_options;

		$__wp_app_test_actions = [];
		$__wp_app_test_filters = [];
		$__wp_app_test_options = [];
		Registry::reset();

		$this->reset_static( 'instances', [] );

		foreach ( [ 'shared_hooks_initialized', 'admin_bar_overflow_styles_output', 'admin_bar_app_link_styles_output' ] as $name ) {
			$this->reset_static( $name, false );
		}
	}

	/**
	 * Reset one of the class-level flags, ignoring any this version does not have.
	 */
	private function reset_static( $name, $value ) {
		if ( ! property_exists( Masterbar::class, $name ) ) {
			return;
		}

		$property = new ReflectionProperty( Masterbar::class, $name );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}

	private function build_masterbars( $count ) {
		$masterbars = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$masterbars[] = new Masterbar( 'app-' . $i );
		}

		return $masterbars;
	}

	public function test_head_callbacks_are_registered_once_for_many_apps() {
		global $__wp_app_test_actions;

		$this->build_masterbars( 20 );

		$callbacks = array_column( $__wp_app_test_actions['wp_app_head'][10], 'callback' );

		$this->assertSame(
			[
				[ Masterbar::class, 'output_styles' ],
				[ Masterbar::class, 'output_scripts' ],
			],
			$callbacks
		);
	}

	public function test_one_app_registers_the_same_callbacks_as_many() {
		global $__wp_app_test_actions;

		$this->build_masterbars( 1 );
		$single = $__wp_app_test_actions['wp_app_head'];

		$this->setUp();
		$this->build_masterbars( 20 );

		$this->assertEquals( $single, $__wp_app_test_actions['wp_app_head'] );
	}

	public function test_styles_and_scripts_are_output_once_for_many_apps() {
		$this->build_masterbars( 20 );

		ob_start();
		do_action( 'wp_app_head' );
		$output = ob_get_clean();

		$this->assertSame( 1, substr_count( $output, '<style id="wp-app-masterbar-styles">' ) );
		$this->assertSame( 1, substr_count( $output, '<script id="wp-app-masterbar-scripts">' ) );
	}

	public function test_output_is_identical_whether_one_or_many_apps_are_installed() {
		$this->build_masterbars( 1 );
		ob_start();
		do_action( 'wp_app_head' );
		$single = ob_get_clean();

		$this->setUp();
		$this->build_masterbars( 20 );
		ob_start();
		do_action( 'wp_app_head' );
		$many = ob_get_clean();

		$this->assertNotSame( '', $single );
		$this->assertSame( $single, $many );
	}
}
