<?php

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook_name ) {
		return $GLOBALS['wp_app_test_did_actions'][ $hook_name ] ?? 0;
	}
}

if ( ! function_exists( 'doing_action' ) ) {
	function doing_action( $hook_name = null ) {
		$current_actions = $GLOBALS['wp_app_test_current_actions'] ?? [];

		if ( null === $hook_name ) {
			return ! empty( $current_actions );
		}

		return in_array( $hook_name, $current_actions, true );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wp_app_test_actions'][ $hook_name ][ $priority ][] = [
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		];

		return true;
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( $hook_name, $callback, $priority = 10 ) {
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) {
		$GLOBALS['wp_app_test_did_actions'][ $hook_name ] = ( $GLOBALS['wp_app_test_did_actions'][ $hook_name ] ?? 0 ) + 1;
		$GLOBALS['wp_app_test_current_actions'][]         = $hook_name;

		$callbacks = $GLOBALS['wp_app_test_actions'][ $hook_name ] ?? [];
		ksort( $callbacks );

		foreach ( $callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $callback_data ) {
				call_user_func_array(
					$callback_data['callback'],
					array_slice( $args, 0, $callback_data['accepted_args'] )
				);
			}
		}

		array_pop( $GLOBALS['wp_app_test_current_actions'] );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'wp_app_tests_reset_hooks' ) ) {
	function wp_app_tests_reset_hooks() {
		$GLOBALS['wp_app_test_actions']         = [];
		$GLOBALS['wp_app_test_did_actions']     = [];
		$GLOBALS['wp_app_test_current_actions'] = [];
		$GLOBALS['wp_app_test_options']         = [];
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['wp_app_test_options'][ $option ] = $value;

		return true;
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
