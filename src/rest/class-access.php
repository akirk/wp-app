<?php
/**
 * REST read-access gate for app-owned post types and taxonomies.
 *
 * WordPress core serves any *published* post of a `show_in_rest` post type, and
 * every term of a `show_in_rest` taxonomy, to anonymous callers — it keys off
 * `show_in_rest` alone, never off `public`/`publicly_queryable`. A WpApp app
 * that gates its front end with `require_login`/`require_capability` therefore
 * still leaks its content over `/wp/v2/<type>` unless the REST layer is gated
 * too.
 *
 * Declare the app's REST-backed types with {@see Access::protect_post_type()} /
 * {@see Access::protect_taxonomy()}. Access records the capability each one
 * needs and, via `register_post_type_args`/`register_taxonomy_args`, injects the
 * gated controller ({@see Private_Posts_Controller}/{@see Private_Terms_Controller})
 * automatically — so the `register_post_type()`/`register_taxonomy()` calls need
 * no `rest_controller_class` of their own. Call `protect_*` *before* the matching
 * `register_*` (both run on `init`).
 *
 * Anonymous reads then get a 401; a logged-in user without the capability gets a
 * 403; the block editor (which reads as a logged-in user) keeps working.
 * Deliberate public reads (e.g. a share-token link) can opt back in via the
 * `wp_app_rest_public_read` filter.
 *
 * @package WpApp
 */

namespace WpApp\Rest;

if ( class_exists( 'WpApp\Rest\Access' ) ) {
	return;
}

class Access {
	/**
	 * Post type => required capability (null means "any logged-in user").
	 *
	 * @var array<string,string|null>
	 */
	private static $post_type_caps = [];

	/**
	 * Taxonomy => required capability (null means "any logged-in user").
	 *
	 * @var array<string,string|null>
	 */
	private static $taxonomy_caps = [];

	/**
	 * Whether the register_*_args filters have been attached.
	 *
	 * @var bool
	 */
	private static $filters_hooked = false;

	/**
	 * Gate REST reads of a post type to a capability.
	 *
	 * Call before `register_post_type()`; Access injects the gated controller
	 * and `show_in_rest => true` into that registration automatically.
	 *
	 * @param string      $post_type  Post type key.
	 * @param string|null $capability Capability required to read via REST. Pass
	 *                                the app's `require_capability` value (e.g.
	 *                                'read' for a login-only app, 'edit_posts'
	 *                                for an editor-only app). Null requires only
	 *                                that the caller be logged in.
	 * @return string Controller class name (for callers who prefer to wire
	 *                `rest_controller_class` themselves).
	 */
	public static function protect_post_type( $post_type, $capability = null ) {
		self::$post_type_caps[ $post_type ] = $capability;
		self::ensure_controllers_loaded();
		self::hook_filters();

		return Private_Posts_Controller::class;
	}

	/**
	 * Gate REST reads of a taxonomy to a capability.
	 *
	 * @param string      $taxonomy   Taxonomy key.
	 * @param string|null $capability Capability required to read via REST.
	 * @return string Controller class name.
	 */
	public static function protect_taxonomy( $taxonomy, $capability = null ) {
		self::$taxonomy_caps[ $taxonomy ] = $capability;
		self::ensure_controllers_loaded();
		self::hook_filters();

		return Private_Terms_Controller::class;
	}

	/**
	 * Attach the argument filters that inject the gated controllers.
	 */
	private static function hook_filters() {
		if ( self::$filters_hooked || ! function_exists( 'add_filter' ) ) {
			return;
		}

		add_filter( 'register_post_type_args', [ __CLASS__, 'filter_post_type_args' ], 10, 2 );
		add_filter( 'register_taxonomy_args', [ __CLASS__, 'filter_taxonomy_args' ], 10, 2 );
		self::$filters_hooked = true;
	}

	/**
	 * Inject the gated controller + show_in_rest for a protected post type.
	 *
	 * @param array  $args      Post type arguments.
	 * @param string $post_type Post type key.
	 * @return array
	 */
	public static function filter_post_type_args( $args, $post_type ) {
		if ( ! array_key_exists( $post_type, self::$post_type_caps ) ) {
			return $args;
		}

		$args['show_in_rest'] = true;
		if ( empty( $args['rest_controller_class'] ) ) {
			self::ensure_controllers_loaded();
			$args['rest_controller_class'] = Private_Posts_Controller::class;
		}

		return $args;
	}

	/**
	 * Inject the gated controller + show_in_rest for a protected taxonomy.
	 *
	 * @param array  $args     Taxonomy arguments.
	 * @param string $taxonomy Taxonomy key.
	 * @return array
	 */
	public static function filter_taxonomy_args( $args, $taxonomy ) {
		if ( ! array_key_exists( $taxonomy, self::$taxonomy_caps ) ) {
			return $args;
		}

		$args['show_in_rest'] = true;
		if ( empty( $args['rest_controller_class'] ) ) {
			self::ensure_controllers_loaded();
			$args['rest_controller_class'] = Private_Terms_Controller::class;
		}

		return $args;
	}

	/**
	 * Capability registered for a post type, or null if it is only login-gated.
	 *
	 * @param string $post_type Post type key.
	 * @return string|null
	 */
	public static function capability_for_post_type( $post_type ) {
		return isset( self::$post_type_caps[ $post_type ] ) ? self::$post_type_caps[ $post_type ] : null;
	}

	/**
	 * Capability registered for a taxonomy, or null if it is only login-gated.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @return string|null
	 */
	public static function capability_for_taxonomy( $taxonomy ) {
		return isset( self::$taxonomy_caps[ $taxonomy ] ) ? self::$taxonomy_caps[ $taxonomy ] : null;
	}

	/**
	 * Read-permission gate for a protected post type.
	 *
	 * @param string           $post_type Post type key.
	 * @param \WP_REST_Request $request   Current request.
	 * @return true|\WP_Error
	 */
	public static function guard_post_type( $post_type, $request ) {
		return self::guard( self::capability_for_post_type( $post_type ), $post_type, $request );
	}

	/**
	 * Read-permission gate for a protected taxonomy.
	 *
	 * @param string           $taxonomy Taxonomy key.
	 * @param \WP_REST_Request $request  Current request.
	 * @return true|\WP_Error
	 */
	public static function guard_taxonomy( $taxonomy, $request ) {
		return self::guard( self::capability_for_taxonomy( $taxonomy ), $taxonomy, $request );
	}

	/**
	 * Shared gate: allow the read for a capable/logged-in caller (or a
	 * deliberate public opt-in), deny everyone else.
	 *
	 * @param string|null      $capability  Required capability, or null for login-only.
	 * @param string           $object_name Post type or taxonomy key (for the filter).
	 * @param \WP_REST_Request $request     Current request.
	 * @return true|\WP_Error
	 */
	private static function guard( $capability, $object_name, $request ) {
		/**
		 * Allow an otherwise-gated app object to be read anonymously.
		 *
		 * Return true to permit the read — e.g. when the request carries a
		 * valid share token. Default false keeps app data private.
		 *
		 * @param bool             $allow       Whether to allow the anonymous read.
		 * @param string           $object_name Post type or taxonomy key.
		 * @param \WP_REST_Request $request     The REST request.
		 */
		if ( apply_filters( 'wp_app_rest_public_read', false, $object_name, $request ) ) {
			return true;
		}

		if ( $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		} elseif ( is_user_logged_in() ) {
			return true;
		}

		return new \WP_Error(
			'wp_app_rest_forbidden',
			__( 'Authentication is required to read this data.', 'wp-app' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	/**
	 * Require the gated controller classes.
	 *
	 * They extend core REST controller classes that only exist once WordPress
	 * has loaded, so we defer the require until an app registers a protected
	 * type — which happens on `init`, after wp-settings.php has loaded the
	 * parent classes. Guarded so duplicate vendored copies do not redeclare.
	 */
	private static function ensure_controllers_loaded() {
		if ( ! class_exists( __NAMESPACE__ . '\Private_Posts_Controller', false ) ) {
			require_once __DIR__ . '/class-private-posts-controller.php';
		}
		if ( ! class_exists( __NAMESPACE__ . '\Private_Terms_Controller', false ) ) {
			require_once __DIR__ . '/class-private-terms-controller.php';
		}
	}

	/**
	 * Reset all registrations (test helper).
	 */
	public static function reset() {
		self::$post_type_caps = [];
		self::$taxonomy_caps  = [];
		self::$filters_hooked = false;
	}
}
