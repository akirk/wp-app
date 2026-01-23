<?php
/**
 * Plugin Name: Community App
 * Description: A community platform example using WpApp with BaseApp pattern
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
		function() {
			echo '<div class="notice notice-error"><p>Community App: Please run <code>composer install</code> in the plugin directory.</p></div>';
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

use WpApp\WpApp;
use WpApp\BaseApp;
use WpApp\BaseStorage;

/**
 * Storage class for community app data
 */
class CommunityAppStorage extends BaseStorage {

	/**
	 * Get database schema
	 *
	 * @return array Array of SQL CREATE TABLE statements.
	 */
	protected function get_schema() {
		$charset_collate = $this->wpdb->get_charset_collate();

		return array(
			"CREATE TABLE {$this->wpdb->prefix}webapp_progress (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				level int(11) DEFAULT 1,
				points int(11) DEFAULT 0,
				achievements longtext,
				last_activity datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id)
			) $charset_collate;",

			"CREATE TABLE {$this->wpdb->prefix}webapp_posts (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				author_id bigint(20) unsigned NOT NULL,
				title varchar(255) NOT NULL,
				content longtext,
				status varchar(20) DEFAULT 'published',
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY author_id (author_id),
				KEY status (status)
			) $charset_collate;",
		);
	}

	public function get_user_progress( $user_id ) {
		$progress = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}webapp_progress WHERE user_id = %d",
				$user_id
			)
		);

		if ( ! $progress ) {
			$this->wpdb->insert(
				$this->wpdb->prefix . 'webapp_progress',
				array( 'user_id' => $user_id ),
				array( '%d' )
			);

			$progress = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->wpdb->prefix}webapp_progress WHERE user_id = %d",
					$user_id
				)
			);
		}

		return $progress;
	}

	public function add_points( $user_id, $points ) {
		return $this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT INTO {$this->wpdb->prefix}webapp_progress (user_id, points)
				 VALUES (%d, %d)
				 ON DUPLICATE KEY UPDATE points = points + VALUES(points), last_activity = NOW()",
				$user_id,
				$points
			)
		);
	}
}

/**
 * Main Community App class following BaseApp pattern
 */
class CommunityApp extends BaseApp {

	public function __construct() {
		$this->storage = new CommunityAppStorage();

		$this->app = new WpApp(
			plugin_dir_path( __FILE__ ) . 'templates',
			'community',
			array(
				'require_login'   => true,
				'show_wp_logo'    => false,
				'show_site_name'  => true,
				'app_name'        => 'Community App',
			)
		);

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	protected function setup_database() {
		// Database tables are created in activate() hook using dbDelta
	}

	protected function setup_routes() {
		$this->app->route( '', 'index.php' );
		$this->app->route( 'dashboard' );
		$this->app->route( 'profile/{user_id}' );
		$this->app->route( 'posts' );
		$this->app->route( 'posts/{post_id}' );
		$this->app->route( 'posts/create' );
		$this->app->route( 'leaderboard' );

		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
		add_action( 'template_redirect', array( $this, 'maybe_setup_assets' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	protected function setup_menu() {
		$this->app->add_menu_item( 'posts', 'Posts', home_url( '/community/posts' ) );
		$this->app->add_menu_item( 'leaderboard', 'Leaderboard', home_url( '/community/leaderboard' ) );

		if ( is_user_logged_in() ) {
			$this->app->add_user_menu_item( 'dashboard', 'Dashboard', home_url( '/community/dashboard' ) );
		}
	}

	public function maybe_setup_assets() {
		if ( $this->app->is_app_request() ) {
			wp_app_enqueue_style(
				'community-app-styles',
				plugin_dir_url( __FILE__ ) . 'assets/app.css',
				array(),
				'1.0.0'
			);
		}
	}

	public function register_rest_endpoints() {
		register_rest_route(
			'community/v1',
			'/user-progress/(?P<user_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_user_progress' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
				'args'                => array(
					'user_id' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			'community/v1',
			'/add-points',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_add_points' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
				'args'                => array(
					'points' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);
	}

	public function rest_permission_check( $request ) {
		return is_user_logged_in();
	}

	public function rest_get_user_progress( $request ) {
		$user_id = intval( $request->get_param( 'user_id' ) );

		if ( $user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'You can only view your own progress.', array( 'status' => 403 ) );
		}

		$progress = $this->storage->get_user_progress( $user_id );

		return rest_ensure_response(
			array(
				'success'  => true,
				'progress' => $progress,
			)
		);
	}

	public function rest_add_points( $request ) {
		$user_id = get_current_user_id();
		$points  = intval( $request->get_param( 'points' ) );

		$this->storage->add_points( $user_id, $points );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => "Added {$points} points!",
			)
		);
	}

	public function add_admin_menu() {
		add_menu_page(
			'Community App',
			'Community App',
			'manage_options',
			'community-app',
			array( $this, 'admin_page' ),
			'dashicons-groups',
			30
		);

		add_submenu_page(
			'community-app',
			'App Settings',
			'Settings',
			'manage_options',
			'community-app-settings',
			array( $this, 'admin_settings_page' )
		);
	}

	public function admin_page() {
		$total_users = $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->wpdb->prefix}webapp_progress" );
		$total_posts = $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->wpdb->prefix}webapp_posts" );
		?>
		<div class="wrap">
			<h1>Community App Dashboard</h1>

			<div class="card">
				<h2>Statistics</h2>
				<p><strong>Active Users:</strong> <?php echo intval( $total_users ); ?></p>
				<p><strong>Total Posts:</strong> <?php echo intval( $total_posts ); ?></p>
			</div>

			<div class="card">
				<h2>Quick Actions</h2>
				<p><a href="<?php echo esc_url( home_url( '/community' ) ); ?>" class="button button-primary">View App</a></p>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=community-app-settings' ) ); ?>" class="button">Settings</a></p>
			</div>
		</div>
		<?php
	}

	public function admin_settings_page() {
		if ( isset( $_POST['submit'] ) && check_admin_referer( 'community_app_settings' ) ) {
			$this->app->set_config( 'points_per_post', intval( $_POST['points_per_post'] ) );
			$this->app->set_config( 'enable_leaderboard', ! empty( $_POST['enable_leaderboard'] ) );
			echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
		}

		$points_per_post    = $this->app->get_config( 'points_per_post', 10 );
		$enable_leaderboard = $this->app->get_config( 'enable_leaderboard', true );
		?>
		<div class="wrap">
			<h1>App Settings</h1>

			<form method="post">
				<?php wp_nonce_field( 'community_app_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">Points per Post</th>
						<td>
							<input type="number" name="points_per_post" value="<?php echo esc_attr( $points_per_post ); ?>" />
							<p class="description">How many points users get for creating a post</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Enable Leaderboard</th>
						<td>
							<input type="checkbox" name="enable_leaderboard" value="1" <?php checked( $enable_leaderboard ); ?> />
							<p class="description">Show the leaderboard to users</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function activate() {
		$this->storage->create_tables();
		$this->setup_routes();
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}
}

new CommunityApp();
