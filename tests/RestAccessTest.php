<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Rest\Access;
use WpApp\Rest\Private_Posts_Controller;
use WpApp\Rest\Private_Terms_Controller;

class RestAccessTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['__wp_app_test_filters']          = [];
		$GLOBALS['__wp_app_test_current_user_can'] = false;
		$GLOBALS['__wp_app_test_is_user_logged_in'] = false;
		$GLOBALS['__wp_app_test_cap_calls'] = [];
		Access::reset();
	}

	private function request( $params = [] ) {
		return new \WP_REST_Request( $params );
	}

	public function test_protect_returns_controller_class_names() {
		$this->assertSame( Private_Posts_Controller::class, Access::protect_post_type( 'note', 'read' ) );
		$this->assertSame( Private_Terms_Controller::class, Access::protect_taxonomy( 'note_tag', 'read' ) );
	}

	public function test_anonymous_read_is_denied_with_401() {
		Access::protect_post_type( 'note', 'read' );
		$controller = new Private_Posts_Controller( 'note' );

		$result = $controller->get_items_permissions_check( $this->request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->data['status'] );
		$this->assertSame( 401, $controller->get_item_permissions_check( $this->request() )->data['status'] );
	}

	public function test_capable_user_is_allowed() {
		Access::protect_post_type( 'note', 'read' );
		$GLOBALS['__wp_app_test_is_user_logged_in'] = true;
		$GLOBALS['__wp_app_test_current_user_can']  = [ 'read' => true ];

		$controller = new Private_Posts_Controller( 'note' );

		$this->assertTrue( $controller->get_items_permissions_check( $this->request() ) );
		$this->assertTrue( $controller->get_item_permissions_check( $this->request() ) );
	}

	public function test_logged_in_user_without_capability_is_denied_with_403() {
		Access::protect_post_type( 'recipe', 'edit_posts' );
		$GLOBALS['__wp_app_test_is_user_logged_in'] = true;
		$GLOBALS['__wp_app_test_current_user_can']  = [ 'read' => true ]; // has read, not edit_posts

		$controller = new Private_Posts_Controller( 'recipe' );
		$result     = $controller->get_items_permissions_check( $this->request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 403, $result->data['status'] );
	}

	public function test_login_only_default_when_no_capability_registered() {
		Access::protect_post_type( 'thing', null );
		$controller = new Private_Posts_Controller( 'thing' );

		// Anonymous denied.
		$this->assertInstanceOf( \WP_Error::class, $controller->get_items_permissions_check( $this->request() ) );

		// Any logged-in user allowed.
		$GLOBALS['__wp_app_test_is_user_logged_in'] = true;
		$this->assertTrue( $controller->get_items_permissions_check( $this->request() ) );
	}

	public function test_public_read_filter_opts_back_in() {
		Access::protect_post_type( 'trip', 'read' );
		$GLOBALS['__wp_app_test_filters']['wp_app_rest_public_read'] = [
			function ( $allow, $object_name, $request ) {
				return 'trip' === $object_name;
			},
		];

		$controller = new Private_Posts_Controller( 'trip' );
		$this->assertTrue( $controller->get_items_permissions_check( $this->request() ) );
	}

	public function test_taxonomy_reads_are_gated() {
		Access::protect_taxonomy( 'note_tag', 'read' );
		$controller = new Private_Terms_Controller( 'note_tag' );

		$this->assertInstanceOf( \WP_Error::class, $controller->get_items_permissions_check( $this->request() ) );

		$GLOBALS['__wp_app_test_is_user_logged_in'] = true;
		$GLOBALS['__wp_app_test_current_user_can']  = [ 'read' => true ];
		$this->assertTrue( $controller->get_items_permissions_check( $this->request() ) );
	}
	public function test_filter_injects_controller_and_show_in_rest_for_protected_post_type() {
		Access::protect_post_type( "note", "read" );
		$args = Access::filter_post_type_args( [ "public" => false ], "note" );

		$this->assertTrue( $args["show_in_rest"] );
		$this->assertSame( Private_Posts_Controller::class, $args["rest_controller_class"] );
	}

	public function test_filter_injects_terms_controller_for_protected_taxonomy() {
		Access::protect_taxonomy( "note_tag", "read" );
		$args = Access::filter_taxonomy_args( [ "public" => false ], "note_tag" );

		$this->assertTrue( $args["show_in_rest"] );
		$this->assertSame( Private_Terms_Controller::class, $args["rest_controller_class"] );
	}

	public function test_filter_leaves_unprotected_types_untouched() {
		$args = Access::filter_post_type_args( [ "public" => true ], "unrelated" );
		$this->assertArrayNotHasKey( "rest_controller_class", $args );
		$this->assertArrayNotHasKey( "show_in_rest", $args );
	}

	public function test_filter_does_not_override_explicit_controller() {
		Access::protect_post_type( "note", "read" );
		$args = Access::filter_post_type_args( [ "rest_controller_class" => "My\Custom" ], "note" );
		$this->assertSame( "My\Custom", $args["rest_controller_class"] );
	}

	public function test_item_check_passes_object_id_to_current_user_can() {
		// Item cap is a meta cap; collection cap is a coarse primitive cap.
		Access::protect_post_type( 'trip', 'read_trip', 'read' );
		$GLOBALS['__wp_app_test_is_user_logged_in'] = true;
		$GLOBALS['__wp_app_test_current_user_can']  = [ 'read_trip' => true, 'read' => true ];

		$controller = new Private_Posts_Controller( 'trip' );

		// Single-item read: capability checked WITH the object id.
		$this->assertTrue( $controller->get_item_permissions_check( $this->request( [ 'id' => 42 ] ) ) );
		$this->assertSame( [ 'read_trip', [ 42 ] ], end( $GLOBALS['__wp_app_test_cap_calls'] ) );

		// Collection read: coarse cap, no id.
		$GLOBALS['__wp_app_test_cap_calls'] = [];
		$this->assertTrue( $controller->get_items_permissions_check( $this->request() ) );
		$this->assertSame( [ 'read', [] ], end( $GLOBALS['__wp_app_test_cap_calls'] ) );
	}

	public function test_item_check_denies_when_meta_cap_fails_for_that_object() {
		Access::protect_post_type( 'trip', 'read_trip', 'read' );
		$GLOBALS['__wp_app_test_is_user_logged_in'] = true;
		$GLOBALS['__wp_app_test_current_user_can']  = [ 'read' => true ]; // has collection cap, not read_trip

		$controller = new Private_Posts_Controller( 'trip' );

		$this->assertInstanceOf( \WP_Error::class, $controller->get_item_permissions_check( $this->request( [ 'id' => 42 ] ) ) );
		$this->assertTrue( $controller->get_items_permissions_check( $this->request() ) );
	}

	public function test_collection_capability_defaults_to_item_capability() {
		Access::protect_post_type( 'note', 'read' ); // single cap -> both item and collection
		$GLOBALS['__wp_app_test_is_user_logged_in'] = true;
		$GLOBALS['__wp_app_test_current_user_can']  = [ 'read' => true ];

		$controller = new Private_Posts_Controller( 'note' );
		$this->assertTrue( $controller->get_items_permissions_check( $this->request() ) );
		$this->assertTrue( $controller->get_item_permissions_check( $this->request( [ 'id' => 7 ] ) ) );
	}

	public function test_taxonomy_item_check_passes_term_id() {
		Access::protect_taxonomy( 'trip_tag', 'read_trip', 'read' );
		$GLOBALS['__wp_app_test_is_user_logged_in'] = true;
		$GLOBALS['__wp_app_test_current_user_can']  = [ 'read_trip' => true, 'read' => true ];

		$controller = new Private_Terms_Controller( 'trip_tag' );
		$this->assertTrue( $controller->get_item_permissions_check( $this->request( [ 'id' => 9 ] ) ) );
		$this->assertSame( [ 'read_trip', [ 9 ] ], end( $GLOBALS['__wp_app_test_cap_calls'] ) );
	}
}
