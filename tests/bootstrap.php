<?php

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-app-test-content' );
}

if ( ! is_dir( WP_CONTENT_DIR ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test bootstrap fixture setup.
	mkdir( WP_CONTENT_DIR, 0777, true );
}

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

if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( $hook_name, $callback, $priority = 10 ) {
		global $__wp_app_test_actions;

		if ( empty( $__wp_app_test_actions[ $hook_name ][ $priority ] ) ) {
			return false;
		}

		foreach ( $__wp_app_test_actions[ $hook_name ][ $priority ] as $index => $action ) {
			if ( $action['callback'] === $callback ) {
				unset( $__wp_app_test_actions[ $hook_name ][ $priority ][ $index ] );
				return true;
			}
		}

		return false;
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
		global $__wp_app_test_filters;

		if ( ! isset( $__wp_app_test_filters[ $hook_name ] ) ) {
			$__wp_app_test_filters[ $hook_name ] = [];
		}

		$__wp_app_test_filters[ $hook_name ][] = $callback;
		return true;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain -- Test stub mirrors WordPress' dynamic helper signature.
		return esc_html( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value, ...$args ) {
		global $__wp_app_test_filters;

		if ( empty( $__wp_app_test_filters[ $hook_name ] ) ) {
			return $value;
		}

		foreach ( $__wp_app_test_filters[ $hook_name ] as $callback ) {
			$value = call_user_func( $callback, $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) {
		return null;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $__wp_app_test_options;

		if ( isset( $__wp_app_test_options[ $option ] ) ) {
			return $__wp_app_test_options[ $option ];
		}

		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		global $__wp_app_test_options;

		$__wp_app_test_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '', $scheme = null ) {
		return 'https://example.org' . $path;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = null ) {
		return 'https://example.org/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '', $plugin = '' ) {
		$plugin_path = str_replace( '\\', '/', (string) $plugin );
		$base        = 'https://example.org/wp-content/plugins';

		if ( '' !== $plugin_path ) {
			$plugin_dir = basename( dirname( $plugin_path ) );
			$base      .= '/' . $plugin_dir;
		}

		return $base . '/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		global $__wp_app_test_current_user_can;

		if ( is_array( $__wp_app_test_current_user_can ?? null ) ) {
			return ! empty( $__wp_app_test_current_user_can[ $capability ] );
		}

		if ( isset( $__wp_app_test_current_user_can ) ) {
			return (bool) $__wp_app_test_current_user_can;
		}

		return true;
	}
}

if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( $var, $default = '' ) {
		global $wp_query;

		if ( $wp_query && isset( $wp_query->query_vars[ $var ] ) ) {
			return $wp_query->query_vars[ $var ];
		}

		return $default;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $text ) {
		return trim( wp_strip_all_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test stub for WordPress helper.
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		global $__wp_app_test_is_user_logged_in;

		return (bool) $__wp_app_test_is_user_logged_in;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		global $__wp_app_test_current_user_id;

		return (int) ( $__wp_app_test_current_user_id ?? 0 );
	}
}

if ( ! function_exists( 'switch_to_user_locale' ) ) {
	function switch_to_user_locale( $user_id ) {
		global $__wp_app_test_locale_stack, $__wp_app_test_current_locale, $__wp_app_test_user_locales;

		$__wp_app_test_locale_stack[] = $__wp_app_test_current_locale ?? 'en_US';
		$__wp_app_test_current_locale = $__wp_app_test_user_locales[ $user_id ] ?? 'en_US';

		return true;
	}
}

if ( ! function_exists( 'restore_previous_locale' ) ) {
	function restore_previous_locale() {
		global $__wp_app_test_locale_stack, $__wp_app_test_current_locale;

		if ( empty( $__wp_app_test_locale_stack ) ) {
			return false;
		}

		$__wp_app_test_current_locale = array_pop( $__wp_app_test_locale_stack );
		return true;
	}
}

if ( ! function_exists( 'get_language_attributes' ) ) {
	function get_language_attributes() {
		global $__wp_app_test_current_locale, $__wp_app_test_is_rtl;

		$lang       = str_replace( '_', '-', $__wp_app_test_current_locale ?? 'en_US' );
		$attributes = [ 'lang="' . esc_attr( $lang ) . '"' ];

		if ( ! empty( $__wp_app_test_is_rtl ) ) {
			$attributes[] = 'dir="rtl"';
		}

		return implode( ' ', $attributes );
	}
}

if ( ! function_exists( 'translate' ) ) {
	function translate( $text, $domain = 'default' ) {
		global $__wp_app_test_translations;

		return $__wp_app_test_translations[ $domain ][ $text ] ?? $text;
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
