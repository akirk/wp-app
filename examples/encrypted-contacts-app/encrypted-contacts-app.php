<?php
/**
 * Plugin Name: Encrypted Contacts App
 * Description: Example WpApp app that augments an existing CPT with client-side encrypted contact fields.
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
				echo '<div class="notice notice-error"><p>Encrypted Contacts App: Please run <code>composer install</code> in the plugin directory.</p></div>';
			}
		);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

use WpApp\WpApp;

const ENCRYPTED_CONTACTS_POST_TYPE = 'encrypted_contact';

add_action( 'init', 'encrypted_contacts_register_content_model' );
add_action( 'plugins_loaded', 'encrypted_contacts_register_app' );

register_activation_hook( __FILE__, 'encrypted_contacts_activate' );

/**
 * Register the normal WordPress content model in PHP.
 */
function encrypted_contacts_register_content_model() {
	register_post_type(
		ENCRYPTED_CONTACTS_POST_TYPE,
		[
			'labels'       => [
				'name'          => 'Encrypted Contacts',
				'singular_name' => 'Encrypted Contact',
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => false,
			'supports'     => [ 'title', 'author' ],
		]
	);

	register_taxonomy(
		'contact_type',
		ENCRYPTED_CONTACTS_POST_TYPE,
		[
			'label'        => 'Type',
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => false,
		]
	);
}

/**
 * Register WpApp and augment the CPT with client-side encrypted fields.
 */
function encrypted_contacts_register_app() {
	$app = new WpApp(
			plugin_dir_path( __FILE__ ) . 'templates',
			'encrypted-contacts',
			[
				'require_login' => true,
				'app_name'      => 'Encrypted Contacts',
				'my_apps_icon'  => 'dashicons-lock',
			]
	);

	$app->route( '' );
	$app->init();

	$encrypted_fields = wp_app_register_client_encrypted_fields(
		__DIR__ . '/wp-app-encrypted-fields.json',
			[
				'action_prefix' => 'encrypted_contacts',
				'capability'    => 'read',
			]
	);

	add_action(
		'template_redirect',
		function () use ( $app, $encrypted_fields ) {
			if ( ! $app->is_app_request() ) {
				return;
			}

			$encrypted_fields->enqueue_assets( 'encrypted-contacts' );
			wp_app_enqueue_style(
				'encrypted-contacts',
				plugin_dir_url( __FILE__ ) . 'assets/app.css',
				[],
				filemtime( __DIR__ . '/assets/app.css' ),
				'encrypted-contacts'
			);
			wp_app_enqueue_script(
				'encrypted-contacts',
				plugin_dir_url( __FILE__ ) . 'assets/app.js',
				[],
				filemtime( __DIR__ . '/assets/app.js' ),
				true,
				'encrypted-contacts'
			);
		}
	);
}

/**
 * Flush routes when the example is activated.
 */
function encrypted_contacts_activate() {
	encrypted_contacts_register_content_model();
	flush_rewrite_rules();
}

/**
 * Get encrypted contact records for the locked server-rendered preview.
 *
 * @return array
 */
function encrypted_contacts_get_encrypted_records() {
	$manifest_path = __DIR__ . '/wp-app-encrypted-fields.json';
	if ( ! is_readable( $manifest_path ) ) {
		return [];
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Manifest is a local plugin file.
	$manifest   = json_decode( file_get_contents( $manifest_path ), true );
	$definition = isset( $manifest['cpts'][ ENCRYPTED_CONTACTS_POST_TYPE ] ) ? $manifest['cpts'][ ENCRYPTED_CONTACTS_POST_TYPE ] : null;
	if ( ! is_array( $definition ) || empty( $definition['encryptedFields'] ) || ! is_array( $definition['encryptedFields'] ) ) {
		return [];
	}

	$query = new WP_Query(
		[
			'post_type'      => ENCRYPTED_CONTACTS_POST_TYPE,
			'post_status'    => [ 'publish', 'private', 'draft' ],
			'posts_per_page' => 100,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		]
	);

	return array_map(
		function ( $post ) use ( $definition ) {
			$record = [
				'id'        => (int) $post->ID,
				'encrypted' => [],
			];

			foreach ( $definition['encryptedFields'] as $field => $field_definition ) {
				$value = '';
				if ( isset( $field_definition['storage'], $field_definition['field'] ) && 'post_field' === $field_definition['storage'] ) {
					$value = isset( $post->{$field_definition['field']} ) ? $post->{$field_definition['field']} : '';
				} elseif ( isset( $field_definition['metaKey'] ) ) {
					$value = get_post_meta( $post->ID, $field_definition['metaKey'], true );
				}

				$decoded = is_string( $value ) && '' !== $value ? json_decode( $value, true ) : null;
				if ( is_array( $decoded ) ) {
					$record['encrypted'][ $field ] = $decoded;
				}
			}

			return $record;
		},
		$query->posts
	);
}

/**
 * Convert an encrypted field envelope ciphertext to a short hex preview.
 *
 * @param array $envelope Encrypted field envelope.
 * @param int   $max_bytes Max bytes to show.
 * @return string
 */
function encrypted_contacts_ciphertext_hex( $envelope, $max_bytes = 18 ) {
	if ( ! is_array( $envelope ) || empty( $envelope['ciphertext'] ) || ! is_string( $envelope['ciphertext'] ) ) {
		return '';
	}

	$bytes = base64_decode( $envelope['ciphertext'], true );
	if ( false === $bytes ) {
		return '';
	}

	$preview = substr( $bytes, 0, $max_bytes );
	$hex     = trim( chunk_split( bin2hex( $preview ), 2, ' ' ) );

	return strlen( $bytes ) > strlen( $preview ) ? $hex . ' ...' : $hex;
}
