<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\Rest\Access;
use WpApp\Rest\Private_Posts_Controller;
use WpApp\Rest\Private_Terms_Controller;

class RestAccessTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['__wp_app_test_filters']           = [];
		$GLOBALS['__wp_app_test_current_user_can']  = false;
		$GLOBALS['__wp_app_test_is_user_logged_in'] = false;
		$GLOBALS['__wp_app_test_cap_calls']         = [];
		$GLOBALS['__wp_app_test_posts']             = [];
		$GLOBALS['__wp_app_test_post_types']        = [];
		$GLOBALS['__wp_app_test_post_queries']      = [];
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
	public function test_filter_injects_controller_for_protected_post_type_that_opts_into_rest() {
		Access::protect_post_type( 'note', 'read' );
		$args = Access::filter_post_type_args(
            [
				'public'       => false,
				'show_in_rest' => true,
			],
			'note'
        );

		$this->assertTrue( $args['show_in_rest'] );
		$this->assertSame( Private_Posts_Controller::class, $args['rest_controller_class'] );
	}

	public function test_filter_injects_terms_controller_for_protected_taxonomy_that_opts_into_rest() {
		Access::protect_taxonomy( 'note_tag', 'read' );
		$args = Access::filter_taxonomy_args(
            [
				'public'       => false,
				'show_in_rest' => true,
			],
			'note_tag'
        );

		$this->assertTrue( $args['show_in_rest'] );
		$this->assertSame( Private_Terms_Controller::class, $args['rest_controller_class'] );
	}

	public function test_filter_does_not_turn_on_show_in_rest_for_protected_type() {
		Access::protect_post_type( 'note', 'read' );
		$args = Access::filter_post_type_args( [ 'public' => false ], 'note' );

		$this->assertArrayNotHasKey( 'show_in_rest', $args );
		$this->assertArrayNotHasKey( 'rest_controller_class', $args );
	}

	public function test_filter_does_not_turn_on_show_in_rest_for_protected_taxonomy() {
		Access::protect_taxonomy( 'note_tag', 'read' );
		$args = Access::filter_taxonomy_args( [ 'public' => false ], 'note_tag' );

		$this->assertArrayNotHasKey( 'show_in_rest', $args );
		$this->assertArrayNotHasKey( 'rest_controller_class', $args );
	}

	public function test_filter_leaves_unprotected_types_untouched() {
		$args = Access::filter_post_type_args(
            [
				'public'       => true,
				'show_in_rest' => true,
			],
			'unrelated'
        );
		$this->assertArrayNotHasKey( 'rest_controller_class', $args );
	}

	public function test_filter_does_not_override_explicit_controller() {
		Access::protect_post_type( 'note', 'read' );
		$args = Access::filter_post_type_args(
            [
				'show_in_rest'          => true,
				'rest_controller_class' => 'My\Custom',
			],
			'note'
        );
		$this->assertSame( 'My\Custom', $args['rest_controller_class'] );
	}

	public function test_item_check_passes_object_id_to_current_user_can() {
		// Item cap is a meta cap; collection cap is a coarse primitive cap.
		Access::protect_post_type( 'trip', 'read_trip', 'read' );
		$GLOBALS['__wp_app_test_is_user_logged_in'] = true;
		$GLOBALS['__wp_app_test_current_user_can']  = [
			'read_trip' => true,
			'read'      => true,
		];

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
		$GLOBALS['__wp_app_test_current_user_can']  = [
			'read_trip' => true,
			'read'      => true,
		];

		$controller = new Private_Terms_Controller( 'trip_tag' );
		$this->assertTrue( $controller->get_item_permissions_check( $this->request( [ 'id' => 9 ] ) ) );
		$this->assertSame( [ 'read_trip', [ 9 ] ], end( $GLOBALS['__wp_app_test_cap_calls'] ) );
	}
	private function records() {
		foreach ( [ [ 1, 'note', 0 ], [ 2, 'note', 0 ], [ 3, 'attachment', 1 ], [ 4, 'attachment', 2 ], [ 5, 'attachment', 0 ], [ 6, 'post', 0 ], [ 7, 'attachment', 6 ] ] as $row ) {
			$GLOBALS['__wp_app_test_posts'][ $row[0] ] = (object) [
				'ID'          => $row[0],
				'post_type'   => $row[1],
				'post_parent' => $row[2],
			];
		}
		foreach ( [ 'note', 'attachment' ] as $name ) {
			$GLOBALS['__wp_app_test_post_types'][] = (object) [
				'name'           => $name,
				'rest_namespace' => 'app/v1',
				'rest_base'      => $name,
			];
		}
		Access::protect_post_type( 'note', 'read_note', 'read' );
	}

	public function test_collections_and_search_exclude_records_and_attached_files_before_pagination() {
		$this->records();
		$GLOBALS['__wp_app_test_current_user_can'] = function ( $cap, $id = null ) {
			return 'read' === $cap || 1 === $id;
		};
		foreach ( [ 'rest_note_query', 'rest_attachment_query', 'rest_post_search_query' ] as $hook ) {
			$args = apply_filters(
                $hook,
                [
					'post_type'    => [ 'note', 'attachment' ],
					'post__not_in' => [ 99 ],
				],
				$this->request()
            );
			$this->assertSame( [ 99, 2, 4 ], $args['post__not_in'] );
		}
	}

	public function test_include_cannot_bypass_exclusions_or_become_an_empty_unrestricted_query() {
		$this->records();
		$args = Access::filter_rest_query(
            [
				'post_type' => [ 'note', 'attachment' ],
				'post__in'  => [ 2, 4 ],
			],
			$this->request()
        );
		$this->assertSame( [ 0 ], $args['post__in'] );
	}

	public function test_media_item_and_custom_controller_routes_use_parent_permissions() {
		$this->records();
		foreach ( [ '/app/v1/attachment/3', '/app/v1/note/1', '/app/v1/note/1/revisions' ] as $route ) {
			$result = Access::guard_rest_request( null, [ 'callback' => 'custom_controller' ], $this->request( [ '_route' => $route ] ) );
			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 401, $result->data['status'] );
		}
		$GLOBALS['__wp_app_test_is_user_logged_in'] = true;
		$this->assertSame( 403, Access::guard_rest_request( null, [], $this->request( [ '_route' => '/app/v1/attachment/3' ] ) )->data['status'] );
		$GLOBALS['__wp_app_test_current_user_can'] = true;
		$this->assertNull( Access::guard_rest_request( null, [], $this->request( [ '_route' => '/app/v1/attachment/3' ] ) ) );
		$this->assertSame( [ 'read_note', [ 1 ] ], end( $GLOBALS['__wp_app_test_cap_calls'] ) );
	}

	public function test_public_and_unattached_media_and_write_requests_are_unchanged() {
		$this->records();
		foreach ( [ 5, 7 ] as $id ) {
			$this->assertTrue( Access::guard_record( get_post( $id ), $this->request() ) );
		}
		$this->assertNull(
            Access::guard_rest_request(
                null,
                [],
                $this->request(
                    [
						'_method' => 'POST',
						'_route'  => '/app/v1/note/1',
					]
                )
            )
        );
		$error = new \WP_Error( 'existing' );
		$this->assertSame( $error, Access::guard_rest_request( $error, [], $this->request() ) );
	}

	public function test_share_filter_receives_parent_id_without_mutating_media_request() {
		$this->records();
		add_filter(
            'wp_app_rest_public_read',
            function ( $allow, $type, $request ) {
				return 'note' === $type && 1 === $request->get_param( 'id' ) && 'valid' === $request->get_param( 'token' );
			},
            10,
            3
        );
		$request = $this->request(
            [
				'id'    => 3,
				'token' => 'valid',
			]
        );
		$this->assertTrue( Access::guard_record( get_post( 3 ), $request ) );
		$this->assertSame( 3, $request->get_param( 'id' ) );
		$this->assertInstanceOf( \WP_Error::class, Access::guard_record( get_post( 4 ), $request ) );
	}
	public function test_query_id_override_cannot_bypass_media_gate() {
		$this->records();
		$request = $this->request(
            [
				'_route' => '/app/v1/attachment/5',
				'id'     => 3,
			]
        );
		$this->assertInstanceOf( \WP_Error::class, Access::guard_rest_request( null, [], $request ) );
		$request = $this->request(
            [
				'_route' => '/app/v1/attachment/3',
				'id'     => 5,
			]
        );
		$this->assertNull( Access::guard_rest_request( null, [], $request ) );
	}

	public function test_effective_parent_is_checked_for_revisions_and_autosaves() {
		$this->records();
		$GLOBALS['__wp_app_test_current_user_can'] = function ( $cap, $id = null ) {
			return 1 === $id;
		};
		foreach ( [ 'revisions', 'autosaves' ] as $child ) {
			$request = $this->request(
                [
					'_route' => '/app/v1/note/1/' . $child,
					'parent' => 2,
				]
            );
			$this->assertInstanceOf( \WP_Error::class, Access::guard_rest_request( null, [], $request ) );
			$request = $this->request(
                [
					'_route' => '/app/v1/note/2/' . $child . '/42',
					'parent' => 1,
					'id'     => 42,
				]
            );
			$this->assertNull( Access::guard_rest_request( null, [], $request ) );
		}
	}

	public function test_any_post_type_query_checks_registered_searchable_types() {
		$this->records();
		$result = Access::filter_rest_query( [ 'post_type' => 'any' ], $this->request() );
		$this->assertSame( [ 1, 2, 3, 4 ], $result['post__not_in'] );
	}

	public function test_unrelated_search_does_not_scan_app_records() {
		$this->records();
		$GLOBALS['__wp_app_test_post_queries'] = [];
		$args                                  = [
			'post_type' => 'post',
			's'         => 'public',
		];
		$this->assertSame( $args, Access::filter_rest_query( $args, $this->request() ) );
		$this->assertSame( [], $GLOBALS['__wp_app_test_post_queries'] );
	}

	public function test_candidates_respect_search_include_and_parent_constraints() {
		$this->records();
		$GLOBALS['__wp_app_test_posts'][3]->post_title = 'matching';
		$GLOBALS['__wp_app_test_posts'][4]->post_title = 'other';
		$GLOBALS['__wp_app_test_post_queries']         = [];
		$args   = [
			'post_type'   => 'attachment',
			's'           => 'matching',
			'post_parent' => 1,
			'post__in'    => [ 3, 4 ],
		];
		$result = Access::filter_rest_query( $args, $this->request() );
		$this->assertSame( [ 3 ], $result['post__not_in'] );
		$this->assertSame( [ 4 ], $result['post__in'] );
		$this->assertCount( 1, $GLOBALS['__wp_app_test_post_queries'] );
		$this->assertSame( 'id=>parent', $GLOBALS['__wp_app_test_post_queries'][0]['fields'] );
		$this->assertSame( 1, $GLOBALS['__wp_app_test_post_queries'][0]['post_parent'] );
	}

	public function test_matching_candidates_are_checked_in_bounded_batches_before_pagination() {
		$this->records();
		for ( $id = 10; $id < 460; ++$id ) {
			$GLOBALS['__wp_app_test_posts'][ $id ] = (object) [
				'ID'          => $id,
				'post_type'   => 'note',
				'post_parent' => 0,
			];
		}
		$GLOBALS['__wp_app_test_post_queries'] = [];
		$args                                  = [
			'post_type'      => 'note',
			'posts_per_page' => 2,
			'offset'         => 400,
		];
		$result                                = Access::filter_rest_query( $args, $this->request() );
		$this->assertCount( 452, $result['post__not_in'] );
		$this->assertSame( 400, $result['offset'] );
		$this->assertSame( 2, $result['posts_per_page'] );
		$this->assertSame( [ 0, 200, 400 ], array_column( $GLOBALS['__wp_app_test_post_queries'], 'offset' ) );
		foreach ( $GLOBALS['__wp_app_test_post_queries'] as $query ) {
			$this->assertSame( 'ids', $query['fields'] );
			$this->assertSame( 200, $query['posts_per_page'] );
		}
	}
}
