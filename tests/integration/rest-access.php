<?php
/**
 * Run against an isolated WordPress installation after loading wp-load.php.
 * Throws on failure; creates fixtures and should never run on a production site.
 */

require_once __DIR__ . '/../../src/rest/class-access.php';

use WpApp\Rest\Access;

function wp_app_rest_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function wp_app_run_rest_integration() {
	$owner = wp_insert_user(
	    [
			'user_login' => 'rest-owner',
			'user_pass'  => 'test',
			'role'       => 'subscriber',
		]
	);
	$other = wp_insert_user(
	    [
			'user_login' => 'rest-other',
			'user_pass'  => 'test',
			'role'       => 'subscriber',
		]
	);
	add_filter(
	    'map_meta_cap',
	    function ( $caps, $cap, $user_id, $args ) {
			if ( 'read_app_record' !== $cap ) {
				return $caps;
			}
			$post = get_post( $args[0] ?? 0 );
			return $post && (int) $post->post_author === (int) $user_id ? [ 'read' ] : [ 'do_not_allow' ];
		},
	    10,
	    4
	);
	Access::protect_post_type( 'app_record', 'read_app_record', 'read' );
	register_post_type(
	    'app_record',
	    [
			'public'       => false,
			'show_in_rest' => true,
		]
	);
	$record        = wp_insert_post(
	    [
			'post_type'   => 'app_record',
			'post_title'  => 'Owner record',
			'post_status' => 'publish',
			'post_author' => $owner,
		]
	);
	$private_media = wp_insert_attachment(
	    [
			'post_title'     => 'Attached image',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/png',
		],
		'/wordpress/wp-content/uploads/app-image.png',
		$record
	);
	$public_media  = wp_insert_attachment(
	    [
			'post_title'     => 'Public image',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/png',
		],
		'/wordpress/wp-content/uploads/public-image.png'
	);

	foreach ( [
		0      => 401,
		$other => 403,
		$owner => 200,
	] as $user => $status ) {
		wp_set_current_user( $user );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $public_media );
		$request->set_query_params( [ 'id' => $private_media ] );
		$response = rest_do_request( $request );
		wp_app_rest_assert( $status === $response->get_status(), 'Effective media ID must use parent permissions: user ' . $user );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_query_params(
	        [
				'include'  => [ $private_media ],
				'per_page' => 1,
			]
	    );
		$response = rest_do_request( $request );
		wp_app_rest_assert( 200 === $response->get_status(), 'Media listing should remain available.' );
		wp_app_rest_assert( ( $user === $owner ? 1 : 0 ) === count( $response->get_data() ), 'Media include must respect parent permissions.' );
	}

	wp_set_current_user( $owner );
	for ( $i = 0; $i < 205; ++$i ) {
		wp_insert_post(
	        [
				'post_type'   => 'app_record',
				'post_title'  => 'Other record',
				'post_status' => 'publish',
				'post_author' => $other,
			]
	    );
	}
	$request = new WP_REST_Request( 'GET', '/wp/v2/app_record' );
	$request->set_query_params( [ 'per_page' => 1 ] );
	$response = rest_do_request( $request );
	wp_app_rest_assert( 200 === $response->get_status(), 'Owner collection should be available.' );
	wp_app_rest_assert( [ $record ] === array_column( $response->get_data(), 'id' ), 'Collection must exclude records from every candidate batch.' );
	$headers = $response->get_headers();
	wp_app_rest_assert( 1 === (int) $headers['X-WP-Total'], 'Pagination total must count only accessible records.' );

	$queries = [];
	add_filter(
	    'posts_request',
	    function ( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		}
	);
	$request = new WP_REST_Request( 'GET', '/wp/v2/search' );
	$request->set_query_params(
	    [
			'subtype' => 'post',
			'search'  => 'unrelated',
		]
	);
	rest_do_request( $request );
	foreach ( $queries as $sql ) {
		wp_app_rest_assert( false === strpos( $sql, 'app_record' ), 'Public post search must not scan app records.' );
	}
	echo "REST integration checks passed.\n";
}

wp_app_run_rest_integration();
