<?php

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook_name ) {
		return 0;
	}
}

if ( ! function_exists( 'doing_action' ) ) {
	function doing_action( $hook_name = null ) {
		return false;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'get_user_option' ) ) {
	function get_user_option( $option, $user = 0 ) {
		global $__wp_app_test_user_options;

		if ( isset( $__wp_app_test_user_options[ $option ] ) ) {
			return $__wp_app_test_user_options[ $option ];
		}

		return false;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'get_stylesheet_directory_uri' ) ) {
	function get_stylesheet_directory_uri() {
		return 'https://example.org/wp-content/themes/child-theme';
	}
}

if ( ! function_exists( 'get_template_directory_uri' ) ) {
	function get_template_directory_uri() {
		return 'https://example.org/wp-content/themes/parent-theme';
	}
}

if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {
		return true;
	}
}

require_once __DIR__ . '/../vendor/autoload.php';
