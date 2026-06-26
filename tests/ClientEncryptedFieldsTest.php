<?php

namespace WpApp\Tests;

use PHPUnit\Framework\TestCase;
use WpApp\ClientEncryptedFields;

class ClientEncryptedFieldsTest extends TestCase {
	private $manifest_path;

	protected function setUp(): void {
		parent::setUp();

		global $__wp_app_test_actions, $wp_app_route;

		$__wp_app_test_actions = [];
		$wp_app_route          = null;
		$this->manifest_path   = tempnam( sys_get_temp_dir(), 'wp-app-encrypted-fields-' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture manifest.
		file_put_contents(
			$this->manifest_path,
			wp_json_encode(
				[
					'version' => 1,
					'app'     => [
						'slug' => 'sources',
					],
					'cpts'    => [
						'journalist_source' => [
							'encryptedFields' => [
								'post_title' => [
									'storage' => 'post_field',
									'field'   => 'post_title',
								],
								'notes'      => [
									'metaKey'  => '_source_notes',
									'minBytes' => 1024,
								],
							],
							'taxonomies'      => [ 'source_risk' ],
						],
					],
				]
			)
		);
	}

	protected function tearDown(): void {
		if ( $this->manifest_path && file_exists( $this->manifest_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
			unlink( $this->manifest_path );
		}

		parent::tearDown();
	}

	public function test_manifest_defaults_are_normalized() {
		$runtime  = new ClientEncryptedFields( $this->manifest_path );
		$manifest = $runtime->get_manifest();

		$this->assertSame( 'sources_encrypted_fields', $runtime->get_action_prefix() );
		$this->assertSame( [ 'source_risk' ], $manifest['cpts']['journalist_source']['taxonomies'] );
		$this->assertSame( 'post_field', $manifest['cpts']['journalist_source']['encryptedFields']['post_title']['storage'] );
		$this->assertSame( 'post_meta', $manifest['cpts']['journalist_source']['encryptedFields']['notes']['storage'] );
		$this->assertSame( 1024, $manifest['cpts']['journalist_source']['encryptedFields']['notes']['minBytes'] );
		$this->assertSame( 512, $manifest['cpts']['journalist_source']['encryptedFields']['notes']['bucketBytes'] );
	}

	public function test_client_config_contains_manifest_and_ajax_settings() {
		$runtime = new ClientEncryptedFields(
			$this->manifest_path,
			[
				'action_prefix' => 'sources_secure',
			]
		);
		$config  = $runtime->get_client_config();

		$this->assertSame( 'https://example.org/wp-admin/admin-ajax.php', $config['ajaxUrl'] );
		$this->assertSame( 'sources_secure', $config['actionPrefix'] );
		$this->assertSame( 'test-nonce-sources_secure', $config['nonce'] );
		$this->assertArrayHasKey( 'journalist_source', $config['manifest']['cpts'] );
	}

	public function test_enqueue_assets_outputs_crypto_client_and_manifest_config_for_scope() {
		global $wp_app_route;

		$runtime = new ClientEncryptedFields(
			$this->manifest_path,
			[
				'action_prefix' => 'sources_secure',
			]
		);

		$runtime->enqueue_assets( 'sources' );

		$wp_app_route = [
			'app_path' => 'other-app',
		];

		ob_start();
		wp_app_body_close();
		$other_output = ob_get_clean();

		$this->assertStringNotContainsString( 'wp-app-encrypted-fields.js', $other_output );

		$wp_app_route = [
			'app_path' => 'sources',
		];

		ob_start();
		wp_app_do_scoped_action( 'wp_app_head_scripts' );
		$head_output = ob_get_clean();

		ob_start();
		wp_app_body_close();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wp-app-crypto.js?ver=' . WP_APP_VERSION, $output );
		$this->assertStringContainsString( 'wp-app-encrypted-fields.js?ver=' . WP_APP_VERSION, $output );
		$this->assertStringContainsString( 'window.WpAppEncryptedFieldsConfig', $head_output );
		$this->assertStringContainsString( '"actionPrefix":"sources_secure"', $head_output );
	}
}
