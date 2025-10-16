<?php

// WordPress escaping function polyfills for non-WordPress contexts
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $attribute ) {
        return htmlspecialchars( $attribute, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_textarea' ) ) {
    function esc_textarea( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

// Sanitization functions
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title, $fallback_title = '', $context = 'save' ) {
		$title = strip_tags( $title );
		$title = strtolower( $title );
		$title = preg_replace( '/[^a-z0-9\s\-_]/', '', $title );
		$title = preg_replace( '/[\s_]+/', '-', $title );
		$title = preg_replace( '/-+/', '-', $title );
		$title = trim( $title, '-' );

		if ( empty( $title ) && ! empty( $fallback_title ) ) {
			return sanitize_title( $fallback_title );
		}

		return $title;
	}
}

if ( ! function_exists( 'sanitize_url' ) ) {
	function sanitize_url( $url ) {
		return filter_var( trim( $url ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_html' ) ) {
	function sanitize_html( $html ) {
		// Allow only <a> tags with href and target attributes
		$allowed_tags = '<a>';
		$clean_html = strip_tags( $html, $allowed_tags );

		// Additional security: ensure href attributes don't contain javascript
		$clean_html = preg_replace('/javascript:/i', '', $clean_html);

		return $clean_html;
	}
}

// WordPress plugin system polyfills for standalone mode
if ( ! function_exists( 'add_action' ) ) {
	global $wp_actions;
	if ( ! isset( $wp_actions ) ) {
		$wp_actions = array();
	}

	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		global $wp_actions;
		if ( ! isset( $wp_actions[ $hook_name ] ) ) {
			$wp_actions[ $hook_name ] = array();
		}
		if ( ! isset( $wp_actions[ $hook_name ][ $priority ] ) ) {
			$wp_actions[ $hook_name ][ $priority ] = array();
		}
		$wp_actions[ $hook_name ][ $priority ][] = array(
			'callback' => $callback,
			'accepted_args' => $accepted_args
		);
		// Sort by priority
		ksort( $wp_actions[ $hook_name ] );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) {
		global $wp_actions;
		if ( isset( $wp_actions[ $hook_name ] ) ) {
			foreach ( $wp_actions[ $hook_name ] as $priority => $callbacks ) {
				foreach ( $callbacks as $callback_info ) {
					$callback = $callback_info['callback'];
					$accepted_args = $callback_info['accepted_args'];
					$callback_args = array_slice( $args, 0, $accepted_args );
					if ( is_callable( $callback ) ) {
						call_user_func_array( $callback, $callback_args );
					}
				}
			}
		}
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	global $wp_filters;
	if ( ! isset( $wp_filters ) ) {
		$wp_filters = array();
	}

	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		global $wp_filters;
		if ( ! isset( $wp_filters[ $hook_name ] ) ) {
			$wp_filters[ $hook_name ] = array();
		}
		if ( ! isset( $wp_filters[ $hook_name ][ $priority ] ) ) {
			$wp_filters[ $hook_name ][ $priority ] = array();
		}
		$wp_filters[ $hook_name ][ $priority ][] = array(
			'callback' => $callback,
			'accepted_args' => $accepted_args
		);
		// Sort by priority
		ksort( $wp_filters[ $hook_name ] );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value, ...$args ) {
		global $wp_filters;
		if ( isset( $wp_filters[ $hook_name ] ) ) {
			foreach ( $wp_filters[ $hook_name ] as $priority => $callbacks ) {
				foreach ( $callbacks as $callback_info ) {
					$callback = $callback_info['callback'];
					$accepted_args = $callback_info['accepted_args'];
					$callback_args = array_merge( array( $value ), array_slice( $args, 0, $accepted_args - 1 ) );
					if ( is_callable( $callback ) ) {
						$value = call_user_func_array( $callback, $callback_args );
					}
				}
			}
		}
		return $value;
	}
}

// WordPress activation/deactivation hooks polyfills
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		// In standalone mode, store the callback but don't call it
		// (activation hooks are WordPress-specific)
		// The callback should handle standalone mode gracefully if called
		return true;
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		// In standalone mode, store for potential cleanup (not implemented)
		// Could be extended to register shutdown functions if needed
	}
}

// WordPress options API polyfills
if ( ! function_exists( 'get_option' ) ) {
	global $wp_options;
	if ( ! isset( $wp_options ) ) {
		$wp_options = array();
	}

	function get_option( $option_name, $default = false ) {
		global $wp_options;
		return isset( $wp_options[ $option_name ] ) ? $wp_options[ $option_name ] : $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option_name, $value ) {
		global $wp_options;
		if ( ! isset( $wp_options[ $option_name ] ) ) {
			$wp_options[ $option_name ] = $value;
		}
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option_name, $value ) {
		global $wp_options;
		$wp_options[ $option_name ] = $value;
	}
}

// WordPress URL functions polyfills
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		// Simple implementation for standalone mode
		$protocol = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		return $protocol . '://' . $host . rtrim( $path, '/' );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return home_url( '/admin/' . ltrim( $path, '/' ) );
	}
}

// WordPress plugin functions
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		// Return base URL with trailing slash for standalone mode
		return home_url() . '/';
	}
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( $plugin ) {
		// In standalone mode, assume plugins are not active
		return false;
	}
}

// WordPress user functions
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		// In standalone mode, assume user has all capabilities
		return true;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		// In standalone mode, assume user is always logged in
		return true;
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		static $current_user = null;

		if ( $current_user === null ) {
			$current_user = new class {
				public $ID = 1;
				public $user_login = 'admin';
				public $display_name = 'Administrator';
				public $user_email = 'admin@example.com';
				public $allcaps = [];

				public function __construct() {
					$this->allcaps = [
						'read' => true,
						'edit_posts' => true,
						'publish_posts' => true,
						'manage_options' => true
					];
				}

				public function has_cap( $capability ) {
					if ( is_array( $capability ) ) {
						$capability = array_shift( $capability );
					}
					return isset( $this->allcaps[ $capability ] ) && $this->allcaps[ $capability ];
				}

				public function exists() {
					return true;
				}
			};
		}

		return $current_user;
	}
}

// WordPress rewrite functions
if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules() {
		// No-op in standalone mode
	}
}

// WordPress site info functions
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		switch ( $show ) {
			case 'name':
				return 'Personal CRM';
			case 'description':
				return 'Personal CRM Tool';
			case 'url':
			case 'home':
				return home_url();
			default:
				return 'Personal CRM';
		}
	}
}

// Additional WordPress functions needed for wp-app
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		// Simple nonce for standalone mode
		return md5( $action . time() );
	}
}

if ( ! function_exists( 'is_admin_bar_showing' ) ) {
	function is_admin_bar_showing() {
		// In standalone mode, don't show admin bar
		return false;
	}
}

if ( ! function_exists( 'get_language_attributes' ) ) {
	function get_language_attributes() {
		return 'en';
	}
}

if ( ! function_exists( 'get_avatar' ) ) {
	function get_avatar( $id_or_email, $size = 96, $default = '', $alt = '', $args = null ) {
		// Simple avatar for standalone mode
		$email = is_email( $id_or_email ) ? $id_or_email : 'admin@example.com';
		$hash = md5( strtolower( trim( $email ) ) );
		$url = "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp";
		$alt = $alt ?: 'Avatar';
		return "<img alt='{$alt}' src='{$url}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
	}
}

// Translation functions
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo __( $text, $domain );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return esc_attr( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( __( $text, $domain ) );
	}
}

// WordPress URL functions
if ( ! function_exists( 'wp_logout_url' ) ) {
	function wp_logout_url( $redirect = '' ) {
		// Simple logout URL for standalone mode
		$logout_url = home_url( '/logout' );
		if ( $redirect ) {
			$logout_url .= '?redirect_to=' . urlencode( $redirect );
		}
		return $logout_url;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		switch ( $type ) {
			case 'mysql':
			return date( 'Y-m-d H:i:s' );
			case 'timestamp':
			return time();
			default:
			return date( 'Y-m-d H:i:s' );
		}
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}
if ( ! class_exists( 'wpdb' ) ) {
	require_once __DIR__ . '/sqlite_wpdb.php';
}