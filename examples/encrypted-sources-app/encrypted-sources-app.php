<?php
/**
 * Plugin Name: Encrypted Sources App
 * Description: Example WpApp app that stores structural data in WordPress and sensitive text as client-side encrypted meta.
 * Version: 1.0.0
 * Author: Your Name
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>Encrypted Sources App: Please run <code>composer install</code> in the plugin directory.</p></div>';
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

use WpApp\BaseApp;
use WpApp\WpApp;

class EncryptedSourcesApp extends BaseApp {
	const POST_TYPE = 'journalist_source';
	const REST_NS   = 'encrypted-sources/v1';

	public function __construct() {
		$this->app = new WpApp(
			plugin_dir_path( __FILE__ ) . 'templates',
			'encrypted-sources',
			[
				'require_login' => true,
				'app_name'      => 'Encrypted Sources',
				'my_apps_icon'  => 'dashicons-lock',
			]
		);

		add_action( 'plugins_loaded', [ $this, 'init' ] );
		add_action( 'init', [ $this, 'register_content_model' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );
		add_action( 'template_redirect', [ $this, 'maybe_enqueue_assets' ] );
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
	}

	protected function setup_database() {
		// This example uses a custom post type, taxonomies, and post meta.
	}

	protected function setup_routes() {
		$this->app->route( '' );
	}

	protected function setup_menu() {
		$this->app->add_menu_item( 'sources', 'Sources', home_url( '/encrypted-sources/' ) );
	}

	public function activate() {
		$this->register_content_model();
		$this->setup_routes();
		flush_rewrite_rules();
	}

	public function register_content_model() {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'          => [
					'name'          => 'Protected Sources',
					'singular_name' => 'Protected Source',
				],
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'supports'        => [ 'title', 'author' ],
				'capability_type' => 'post',
			]
		);

		register_taxonomy(
			'source_risk',
			self::POST_TYPE,
			[
				'label'        => 'Risk',
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
			]
		);

		register_taxonomy(
			'source_workflow',
			self::POST_TYPE,
			[
				'label'        => 'Workflow',
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
			]
		);
	}

	public function maybe_enqueue_assets() {
		if ( ! $this->app->is_app_request() ) {
			return;
		}

		wp_app_enqueue_crypto_runtime( 'encrypted-sources' );
		wp_app_enqueue_style(
			'encrypted-sources',
			plugin_dir_url( __FILE__ ) . 'assets/app.css',
			[],
			'1.0.0',
			'encrypted-sources'
		);
		wp_app_enqueue_script(
			'encrypted-sources',
			plugin_dir_url( __FILE__ ) . 'assets/app.js',
			[],
			'1.0.0',
			true,
			'encrypted-sources'
		);
		wp_app_add_inline_script(
			'encrypted-sources-config',
			'window.EncryptedSourcesConfig = ' . wp_json_encode(
				[
					'restUrl' => esc_url_raw( rest_url( self::REST_NS ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'userId'  => get_current_user_id(),
				]
			) . ';',
			false,
			'encrypted-sources'
		);
	}

	public function register_rest_endpoints() {
		register_rest_route(
			self::REST_NS,
			'/settings',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_settings' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			]
		);

		register_rest_route(
			self::REST_NS,
			'/sources',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'rest_get_sources' ],
					'permission_callback' => [ $this, 'rest_permission_check' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'rest_create_source' ],
					'permission_callback' => [ $this, 'rest_permission_check' ],
				],
			]
		);
	}

	public function rest_permission_check() {
		return is_user_logged_in();
	}

	public function rest_get_settings() {
		$user_id = get_current_user_id();
		$salt    = get_user_meta( $user_id, '_encrypted_sources_salt', true );

		if ( ! is_string( $salt ) || '' === $salt ) {
			$salt = wp_generate_password( 32, false, false );
			update_user_meta( $user_id, '_encrypted_sources_salt', $salt );
		}

		return rest_ensure_response(
			[
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes a KDF salt for JSON transport.
				'salt'       => base64_encode( $salt ),
				'iterations' => 250000,
			]
		);
	}

	public function rest_get_sources() {
		$query = new WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'author'         => get_current_user_id(),
				'post_status'    => [ 'private', 'draft' ],
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);

		$sources = array_map( [ $this, 'format_source' ], $query->posts );

		return rest_ensure_response( [ 'sources' => $sources ] );
	}

	public function rest_create_source( WP_REST_Request $request ) {
		$body      = $request->get_json_params();
		$encrypted = isset( $body['encrypted'] ) && is_array( $body['encrypted'] ) ? $body['encrypted'] : [];

		$post_id = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_author' => get_current_user_id(),
				'post_title'  => 'Protected source ' . gmdate( 'Y-m-d H:i:s' ),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		foreach ( [ 'name', 'contact', 'notes', 'private_tags' ] as $field ) {
			if ( isset( $encrypted[ $field ] ) ) {
				update_post_meta( $post_id, '_encrypted_' . $field, wp_json_encode( $encrypted[ $field ] ) );
			}
		}

		foreach (
			[
				'source_risk'     => 'risk',
				'source_workflow' => 'workflow',
			] as $taxonomy => $param
		) {
			if ( ! empty( $body[ $param ] ) && is_string( $body[ $param ] ) ) {
				wp_set_object_terms( $post_id, sanitize_key( $body[ $param ] ), $taxonomy, false );
			}
		}

		return rest_ensure_response( [ 'source' => $this->format_source( get_post( $post_id ) ) ] );
	}

	private function format_source( WP_Post $post ) {
		$encrypted = [];

		foreach ( [ 'name', 'contact', 'notes', 'private_tags' ] as $field ) {
			$value = get_post_meta( $post->ID, '_encrypted_' . $field, true );
			if ( is_string( $value ) && '' !== $value ) {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) {
					$encrypted[ $field ] = $decoded;
				}
			}
		}

		return [
			'id'        => $post->ID,
			'created'   => mysql_to_rfc3339( $post->post_date_gmt ),
			'modified'  => mysql_to_rfc3339( $post->post_modified_gmt ),
			'risk'      => wp_get_object_terms( $post->ID, 'source_risk', [ 'fields' => 'slugs' ] ),
			'workflow'  => wp_get_object_terms( $post->ID, 'source_workflow', [ 'fields' => 'slugs' ] ),
			'encrypted' => $encrypted,
		];
	}
}

new EncryptedSourcesApp();
