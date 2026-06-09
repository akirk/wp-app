<?php

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook_name ) {
		global $__wp_app_test_action_counts;

		return $__wp_app_test_action_counts[ $hook_name ] ?? 0;
	}
}

if ( ! function_exists( 'doing_action' ) ) {
	function doing_action( $hook_name = null ) {
		return false;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		global $__wp_app_test_actions;

		$__wp_app_test_actions[ $hook_name ][ $priority ][] = [
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		];

		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) {
		global $__wp_app_test_actions, $__wp_app_test_action_counts;

		$__wp_app_test_action_counts[ $hook_name ] = ( $__wp_app_test_action_counts[ $hook_name ] ?? 0 ) + 1;

		if ( empty( $__wp_app_test_actions[ $hook_name ] ) ) {
			return;
		}

		ksort( $__wp_app_test_actions[ $hook_name ] );

		foreach ( $__wp_app_test_actions[ $hook_name ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				call_user_func_array(
					$callback['callback'],
					array_slice( $args, 0, $callback['accepted_args'] )
				);
			}
		}
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

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return esc_attr( $url );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
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
