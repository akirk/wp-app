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
	/**
	 * Table definitions, keyed by unprefixed table name. BaseStorage wraps each
	 * in CREATE TABLE with the site's charset and runs dbDelta on activation.
	 */
	protected function get_schema() {
		return array(
			'webapp_progress' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				level int(11) DEFAULT 1,
				points int(11) DEFAULT 0,
				achievements longtext,
				last_activity datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id)",

			'webapp_posts'    => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				author_id bigint(20) unsigned NOT NULL,
				title varchar(255) NOT NULL,
				content longtext,
				status varchar(20) DEFAULT 'published',
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY author_id (author_id),
				KEY status (status)",
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

	/**
	 * Paged list of published posts, newest first, with author display names.
	 */
	public function list_posts( $page = 1, $per_page = 10 ) {
		$page     = max( 1, (int) $page );
		$per_page = min( 50, max( 1, (int) $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT p.id, p.title, p.author_id, u.display_name AS author, p.created_at
				 FROM {$this->wpdb->prefix}webapp_posts p
				 LEFT JOIN {$this->wpdb->users} u ON u.ID = p.author_id
				 WHERE p.status = 'published'
				 ORDER BY p.created_at DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
	}

	public function count_posts() {
		return (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->wpdb->prefix}webapp_posts WHERE status = 'published'"
		);
	}

	public function get_post( $post_id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT p.*, u.display_name AS author
				 FROM {$this->wpdb->prefix}webapp_posts p
				 LEFT JOIN {$this->wpdb->users} u ON u.ID = p.author_id
				 WHERE p.id = %d AND p.status = 'published'",
				$post_id
			)
		);
	}

	public function create_post( $author_id, $title, $content ) {
		$this->wpdb->insert(
			$this->wpdb->prefix . 'webapp_posts',
			array(
				'author_id' => $author_id,
				'title'     => $title,
				'content'   => $content,
			),
			array( '%d', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
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

	public static $instance;

	public function __construct() {
		self::$instance = $this;
		$this->storage  = new CommunityAppStorage();

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
		$this->app->route( 'posts/create' );
		// Without an explicit template this would map to posts.php like the list.
		$this->app->route( 'posts/{post_id}', 'post.php' );
		$this->app->route( 'leaderboard' );

		add_action( 'init', array( $this, 'maybe_handle_create_post' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
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

	/**
	 * Handle the form on /community/posts/create: insert the post, award the
	 * configured points and send the author to the new post.
	 */
	public function maybe_handle_create_post() {
		if ( ! isset( $_POST['community_create_post'] ) || ! is_user_logged_in() ) {
			return;
		}

		check_admin_referer( 'community_create_post' );

		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';

		if ( '' === $title || '' === $content ) {
			// Not "error": WordPress reserves that query var and strips it.
			wp_safe_redirect( add_query_arg( 'missing', '1', home_url( '/community/posts/create' ) ) );
			exit;
		}

		$post_id = $this->storage->create_post( get_current_user_id(), $title, $content );
		$this->storage->add_points( get_current_user_id(), intval( $this->app->get_config( 'points_per_post', 10 ) ) );

		wp_safe_redirect( home_url( '/community/posts/' . $post_id ) );
		exit;
	}

	/**
	 * Whether the leaderboard is enabled in the app settings. Templates cannot
	 * reach the app instance, so this is what they ask.
	 */
	public static function is_leaderboard_enabled() {
		return (bool) self::$instance->app->get_config( 'enable_leaderboard', true );
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

	/**
	 * Abilities: the app's API for callers that read descriptions instead of
	 * code (assistants, automation, other apps). See docs/abilities.md for the
	 * design rules these follow. Both hooks only fire on WordPress with the
	 * Abilities API, so older installs simply have no abilities.
	 */
	public function register_ability_category() {
		wp_register_ability_category(
			'community',
			array(
				'label'       => 'Community',
				'description' => 'Posts and member progress in the Community app.',
			)
		);
	}

	public function register_abilities() {
		// Every ability requires what the app itself requires, so an ability is
		// never a way around the app's access control.
		$can_use_app = function () {
			return current_user_can( $this->app->get_required_capability() ?: 'read' );
		};

		$post_summary = array(
			'type'       => 'object',
			'properties' => array(
				'id'         => array( 'type' => 'integer', 'description' => 'Post ID. Pass to community/get-post for the full text.' ),
				'title'      => array( 'type' => 'string' ),
				'author'     => array( 'type' => 'string', 'description' => 'Display name of the author.' ),
				'author_id'  => array( 'type' => 'integer', 'description' => 'WordPress user ID of the author.' ),
				'created_at' => array( 'type' => 'string', 'format' => 'date-time' ),
			),
		);

		wp_register_ability(
			'community/list-posts',
			array(
				'label'               => 'List community posts',
				'description'         => 'Returns a page of published community posts, newest first, as summaries (id, title, author, created_at) without the body text. Use community/get-post with an id to read a post. Returns total so the caller knows whether to fetch further pages.',
				'category'            => 'community',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10 ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'posts' => array( 'type' => 'array', 'items' => $post_summary ),
						'total' => array( 'type' => 'integer', 'description' => 'Number of published posts across all pages.' ),
						'page'  => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$page  = isset( $input['page'] ) ? (int) $input['page'] : 1;
					return array(
						'posts' => array_map( array( $this, 'format_post_summary' ), $this->storage->list_posts( $page, isset( $input['per_page'] ) ? (int) $input['per_page'] : 10 ) ),
						'total' => $this->storage->count_posts(),
						'page'  => $page,
					);
				},
				'permission_callback' => $can_use_app,
				'meta'                => array(
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		wp_register_ability(
			'community/get-post',
			array(
				'label'               => 'Get community post',
				'description'         => 'Returns one published community post by ID including its full content. Returns error code not_found if there is no published post with that ID; report that rather than creating a replacement.',
				'category'            => 'community',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array( 'type' => 'integer', 'description' => 'Post ID, as returned by community/list-posts.' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $post_summary['properties'] + array(
						'content' => array( 'type' => 'string', 'description' => 'Plain-text body of the post.' ),
					),
				),
				'execute_callback'    => function ( $input ) {
					$post = $this->storage->get_post( (int) $input['id'] );
					if ( ! $post ) {
						return new WP_Error( 'not_found', 'No published post has that ID.' );
					}
					return $this->format_post_summary( $post ) + array( 'content' => $post->content );
				},
				'permission_callback' => $can_use_app,
				'meta'                => array(
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		wp_register_ability(
			'community/create-post',
			array(
				'label'               => 'Create community post',
				'description'         => 'Publishes a new community post by the current user and awards them the configured points. Returns the new post id; pass it to community/get-post. Calling twice creates two posts, so confirm with the user before creating.',
				'category'            => 'community',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'   => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
						'content' => array( 'type' => 'string', 'minLength' => 1, 'description' => 'Plain text; HTML is stripped.' ),
					),
					'required'             => array( 'title', 'content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'  => array( 'type' => 'integer', 'description' => 'ID of the created post.' ),
						'url' => array( 'type' => 'string', 'format' => 'uri', 'description' => 'Where the post can be viewed in the app.' ),
					),
				),
				'execute_callback'    => function ( $input ) {
					$title   = sanitize_text_field( $input['title'] );
					$content = sanitize_textarea_field( $input['content'] );
					if ( '' === $title || '' === $content ) {
						return new WP_Error( 'invalid_input', 'Title and content must not be empty after stripping HTML.' );
					}
					$post_id = $this->storage->create_post( get_current_user_id(), $title, $content );
					$this->storage->add_points( get_current_user_id(), intval( $this->app->get_config( 'points_per_post', 10 ) ) );
					return array(
						'id'  => $post_id,
						'url' => home_url( '/community/posts/' . $post_id ),
					);
				},
				'permission_callback' => $can_use_app,
				'meta'                => array(
					'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
				),
			)
		);

		wp_register_ability(
			'community/get-my-progress',
			array(
				'label'               => 'Get my community progress',
				'description'         => 'Returns the current user\'s level, points and last activity in the Community app. Takes no input; other members\' progress is not available through abilities.',
				'category'            => 'community',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'level'         => array( 'type' => 'integer' ),
						'points'        => array( 'type' => 'integer' ),
						'last_activity' => array( 'type' => 'string', 'format' => 'date-time' ),
					),
				),
				'execute_callback'    => function () {
					$progress = $this->storage->get_user_progress( get_current_user_id() );
					return array(
						'level'         => (int) $progress->level,
						'points'        => (int) $progress->points,
						'last_activity' => $progress->last_activity,
					);
				},
				'permission_callback' => $can_use_app,
				'meta'                => array(
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);
	}

	public function format_post_summary( $post ) {
		return array(
			'id'         => (int) $post->id,
			'title'      => $post->title,
			'author'     => (string) $post->author,
			'author_id'  => (int) $post->author_id,
			'created_at' => $post->created_at,
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
		global $wpdb;
		$total_users = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}webapp_progress" );
		$total_posts = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}webapp_posts" );
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
